<?php
session_start();

// --- Verificações de Segurança e Sessão ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$church_id = $_SESSION['church_id'];
$user_role = $_SESSION['user_role'];

// --- CONTROLE DE ACESSO ---
if ($user_role === 'lider') {
    header('Location: celulas.php');
    exit;
}

$user_name = $_SESSION['user_name'];
$church_balance = 0;

// Buscar saldo atual da igreja
$conn_balance = connect_db();
if ($church_id) {
    $stmt_balance = $conn_balance->prepare("SELECT balance FROM churches WHERE id = ?");
    $stmt_balance->bind_param("i", $church_id);
    $stmt_balance->execute();
    $result_balance = $stmt_balance->get_result()->fetch_assoc();
    if ($result_balance) {
        $church_balance = $result_balance['balance'];
    }
}
$conn_balance->close();


// --- Lógica para Respostas da API (AJAX) ---
if (isset($_GET['fetch_data']) || isset($_GET['fetch_chart_data']) || isset($_GET['fetch_range_data']) || isset($_GET['fetch_weekly_summary'])) {
    header('Content-Type: application/json');
    $conn = connect_db();

    // --- API para dados do calendário e resumos ---
    if (isset($_GET['fetch_data'])) {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        $stmt_entries = $conn->prepare("SELECT DAY(service_date) as day, SUM(total_offering) as total FROM service_reports WHERE church_id = ? AND YEAR(service_date) = ? AND MONTH(service_date) = ? GROUP BY DATE(service_date)");
        $stmt_entries->bind_param("iii", $church_id, $year, $month);
        $stmt_entries->execute();
        $entries_result = $stmt_entries->get_result();
        $entries = [];
        while($row = $entries_result->fetch_assoc()) { $entries[$row['day']] = $row['total']; }

        // CORREÇÃO: Agrupar por DATE(transaction_date) para somar corretamente todas as saídas do dia.
        $stmt_expenses = $conn->prepare("SELECT DAY(transaction_date) as day, SUM(amount) as total FROM expenses WHERE church_id = ? AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ? GROUP BY DATE(transaction_date)");
        $stmt_expenses->bind_param("iii", $church_id, $year, $month);
        $stmt_expenses->execute();
        $expenses_result = $stmt_expenses->get_result();
        $expenses = [];
        while($row = $expenses_result->fetch_assoc()) { $expenses[$row['day']] = $row['total']; }

        $day_details = [];
        $stmt_entry_details = $conn->prepare("SELECT DAY(service_date) as day, theme, total_offering FROM service_reports WHERE church_id = ? AND YEAR(service_date) = ? AND MONTH(service_date) = ?");
        $stmt_entry_details->bind_param("iii", $church_id, $year, $month);
        $stmt_entry_details->execute();
        $result = $stmt_entry_details->get_result();
        while($row = $result->fetch_assoc()) { $day_details[$row['day']]['entries'][] = ['description' => 'Entrada do Culto: ' . ($row['theme'] ?: 'Geral'), 'amount' => $row['total_offering']];}
        
        $stmt_expense_details = $conn->prepare("SELECT DAY(e.transaction_date) as day, e.description, e.amount, c.name as category FROM expenses e LEFT JOIN categories c ON e.category_id = c.id WHERE e.church_id = ? AND YEAR(e.transaction_date) = ? AND MONTH(e.transaction_date) = ?");
        $stmt_expense_details->bind_param("iii", $church_id, $year, $month);
        $stmt_expense_details->execute();
        $result = $stmt_expense_details->get_result();
        while($row = $result->fetch_assoc()) { $day_details[$row['day']]['expenses'][] = ['description' => $row['description'], 'category' => $row['category'], 'amount' => $row['amount']]; }

        echo json_encode(['entries' => $entries, 'expenses' => $expenses, 'details' => $day_details]);
    }

    // --- API para dados do gráfico de tendências ---
    if(isset($_GET['fetch_chart_data'])) {
        $view = $_GET['view'] ?? 'weekly';
        $labels = []; $entries_data = []; $expenses_data = [];
        $dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        
        switch($view) {
            case 'annual': // Agrupado por mês para o ano atual
                for ($m = 1; $m <= 12; $m++) {
                    $labels[] = date('M', mktime(0, 0, 0, $m, 1));
                    $stmt = $conn->prepare("SELECT SUM(total_offering) FROM service_reports WHERE church_id = ? AND MONTH(service_date) = ? AND YEAR(service_date) = YEAR(CURDATE())");
                    $stmt->bind_param("ii", $church_id, $m); $stmt->execute();
                    $entries_data[] = $stmt->get_result()->fetch_row()[0] ?? 0;
                    
                    $stmt = $conn->prepare("SELECT SUM(amount) FROM expenses WHERE church_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = YEAR(CURDATE())");
                    $stmt->bind_param("ii", $church_id, $m); $stmt->execute();
                    $expenses_data[] = $stmt->get_result()->fetch_row()[0] ?? 0;
                }
                break;
            case 'monthly': // Agrupado por dia para o mês atual
                $days_in_month = date('t');
                for ($d = 1; $d <= $days_in_month; $d++) {
                    $labels[] = $d;
                    $date = date('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT);
                    $stmt = $conn->prepare("SELECT SUM(total_offering) FROM service_reports WHERE church_id = ? AND service_date = ?");
                    $stmt->bind_param("is", $church_id, $date); $stmt->execute();
                    $entries_data[] = $stmt->get_result()->fetch_row()[0] ?? 0;

                    $stmt = $conn->prepare("SELECT SUM(amount) FROM expenses WHERE church_id = ? AND DATE(transaction_date) = ?");
                    $stmt->bind_param("is", $church_id, $date); $stmt->execute();
                    $expenses_data[] = $stmt->get_result()->fetch_row()[0] ?? 0;
                }
                break;
            case 'weekly':
            default: // Agrupado por dia para os últimos 7 dias
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $day_of_week_index = date('w', strtotime($date));
                    $labels[] = $dias_semana[$day_of_week_index];
                    $stmt = $conn->prepare("SELECT SUM(total_offering) FROM service_reports WHERE church_id = ? AND service_date = ?");
                    $stmt->bind_param("is", $church_id, $date); $stmt->execute();
                    $entries_data[] = $stmt->get_result()->fetch_row()[0] ?? 0;
                    
                    $stmt = $conn->prepare("SELECT SUM(amount) FROM expenses WHERE church_id = ? AND DATE(transaction_date) = ?");
                    $stmt->bind_param("is", $church_id, $date); $stmt->execute();
                    $expenses_data[] = $stmt->get_result()->fetch_row()[0] ?? 0;
                }
                break;
        }
        echo json_encode(['labels' => $labels, 'entries' => $entries_data, 'expenses' => $expenses_data]);
    }

     // --- Lógica para buscar dados de um intervalo de datas ---
    if (isset($_GET['fetch_range_data'])) {
        $start_date = ($_GET['start_date'] ?? '') . ' 00:00:00';
        $end_date = ($_GET['end_date'] ?? '') . ' 23:59:59';

        if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
            echo json_encode(['error' => 'Por favor, forneça uma data de início e de fim.']);
            exit;
        }
        $stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE church_id = ? AND transaction_date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $church_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $total = $result['total'] ?? 0;
        echo json_encode(['total_expenses' => $total]);
    }

    // --- API para buscar resumo semanal ---
    if (isset($_GET['fetch_weekly_summary'])) {
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        // Obter primeiro e último dia do mês
        $first_day = "$year-$month-01";
        $last_day = date('Y-m-t', strtotime($first_day));
        
        $weeks = [];
        $month_end = strtotime($last_day);
        
        // Encontrar o primeiro domingo do mês ou o dia 1 se já for domingo
        $first_day_timestamp = strtotime($first_day);
        $day_of_week = date('w', $first_day_timestamp); // 0 = Sunday, 6 = Saturday
        
        // Semana 1: Começa no dia 1 do mês e vai até o próximo sábado
        $week_num = 1;
        $current_start = $first_day_timestamp;
        
        // Se o dia 1 não é domingo, a primeira semana vai até o próximo sábado
        if ($day_of_week != 0) {
            // Encontrar o próximo sábado
            $days_until_saturday = 6 - $day_of_week;
            $week_end = strtotime("+$days_until_saturday days", $current_start);
        } else {
            // Se já é domingo, semana vai até sábado (6 dias depois)
            $week_end = strtotime("+6 days", $current_start);
        }
        
        if ($week_end > $month_end) {
            $week_end = $month_end;
        }
        
        while ($current_start <= $month_end) {
            $start_date = date('Y-m-d', $current_start);
            $end_date = date('Y-m-d', $week_end);
            
            // Buscar entradas da semana (usar service_date - data do culto)
            $stmt_entries = $conn->prepare("SELECT sr.id, sr.service_date, sr.theme, sr.total_offering, sr.total_attendance FROM service_reports sr WHERE sr.church_id = ? AND sr.service_date BETWEEN ? AND ? ORDER BY sr.service_date");
            $stmt_entries->bind_param("iss", $church_id, $start_date, $end_date);
            $stmt_entries->execute();
            $entries_result = $stmt_entries->get_result();
            $entries = [];
            $total_entries = 0;
            while($row = $entries_result->fetch_assoc()) {
                $entries[] = $row;
                $total_entries += floatval($row['total_offering']);
            }
            
            // Buscar saídas da semana
            $stmt_expenses = $conn->prepare("SELECT e.id, e.transaction_date, e.amount, e.description, e.paid_to, c.name as category FROM expenses e LEFT JOIN categories c ON e.category_id = c.id WHERE e.church_id = ? AND DATE(e.transaction_date) BETWEEN ? AND ? ORDER BY e.transaction_date");
            $stmt_expenses->bind_param("iss", $church_id, $start_date, $end_date);
            $stmt_expenses->execute();
            $expenses_result = $stmt_expenses->get_result();
            $expenses = [];
            $total_expenses = 0;
            while($row = $expenses_result->fetch_assoc()) {
                $expenses[] = $row;
                $total_expenses += floatval($row['amount']);
            }
            
            $balance = $total_entries - $total_expenses;
            
            $weeks[] = [
                'week_num' => $week_num,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'total_entries' => $total_entries,
                'total_expenses' => $total_expenses,
                'balance' => $balance,
                'entries' => $entries,
                'expenses' => $expenses
            ];
            
            // Avançar para o próximo domingo (próxima semana)
            $current_start = strtotime('+1 day', $week_end);
            // Próxima semana vai do domingo ao sábado (7 dias)
            $week_end = strtotime('+6 days', $current_start);
            if ($week_end > $month_end) {
                $week_end = $month_end;
            }
            $week_num++;
        }
        
        echo json_encode(['weeks' => $weeks]);
    }

    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Relatório de Atividades</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: "#1976D2", secondary: "#BBDEFB" }, borderRadius: { button: "8px" } } } };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com" /><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin /><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Pacifico&display=swap" rel="stylesheet"/><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
        .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
        .modal { transition: opacity 0.3s ease; } .modal-content { transition: transform 0.3s ease; }
        .filter-btn { transition: all 0.2s ease-in-out; }
        .filter-btn.active { background-color: #1976D2; color: white; }
        .sidebar { transition: transform 0.3s ease-in-out; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    
    <aside id="sidebar" class="sidebar w-64 h-full bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed md:relative -translate-x-full md:translate-x-0 z-40">
        <div class="p-6 border-b border-gray-100 flex items-center"><span class="text-2xl font-['Pacifico'] text-primary">Life Church</span></div>
        <nav class="flex-1 overflow-y-auto py-4">
            <?php $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
            <div class="px-4 mb-6">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
                <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
                <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
                <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
                <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
                <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
                <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
                <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
            </div>
            <div class="px-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
                <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-100"><div class="flex items-center p-2"><div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary font-bold text-lg mr-3"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div><div><p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></p><p class="text-xs text-gray-500"><?php echo ucfirst(htmlspecialchars($user_role)); ?></p></div></div></div>
    </aside>

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-gray-200 shadow-sm z-20">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <button id="hamburger-menu" class="md:hidden mr-4 text-gray-600"><i class="ri-menu-line ri-xl"></i></button>
                    <h1 class="text-lg font-medium text-gray-800">Relatório de Atividades</h1>
                </div>
                <div class="relative">
                    <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors"><span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span><div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div></button>
                    <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20"><a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a><a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line mr-3"></i>Sair</a></div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-gray-50">
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <button id="prevMonth" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-arrow-left-s-line ri-lg"></i></button>
                    <h2 id="currentMonth" class="text-xl font-medium text-center">Carregando...</h2>
                    <button id="nextMonth" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-arrow-right-s-line ri-lg"></i></button>
                </div>
                <div class="grid grid-cols-7 gap-1 text-center text-xs sm:text-sm font-medium text-gray-600 py-2 border-b">
                    <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
                </div>
                <div id="calendarGrid" class="grid grid-cols-7 gap-1 mt-2"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-4 sm:p-6"><h3 class="text-gray-500 font-medium">Entradas (Mês)</h3><p id="summary-total-entries" class="text-2xl sm:text-3xl font-semibold text-green-600 mt-2">0,00 MZN</p></div>
                <div class="bg-white rounded-lg shadow p-4 sm:p-6"><h3 class="text-gray-500 font-medium">Saídas (Mês)</h3><p id="summary-total-expenses" class="text-2xl sm:text-3xl font-semibold text-red-600 mt-2">0,00 MZN</p></div>
                <div class="bg-white rounded-lg shadow p-4 sm:p-6"><h3 class="text-gray-500 font-medium">Saldo da Conta</h3><p id="summary-balance" class="text-2xl sm:text-3xl font-semibold text-gray-800 mt-2">0,00 MZN</p></div>
                <div class="bg-white rounded-lg shadow p-4 sm:p-6 flex flex-col items-center justify-center">
                    <button id="dateRangeReportBtn" class="text-center hover:opacity-80 transition-opacity">
                        <div class="w-12 h-12 mx-auto rounded-full bg-blue-100 flex items-center justify-center text-primary mb-2"><i class="ri-calendar-event-line ri-2x"></i></div>
                        <h3 class="text-gray-600 font-medium">Relatório por Período</h3>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-4">
                    <h3 class="text-lg font-medium">Tendências de Atividade</h3>
                    <div class="flex items-center border border-gray-200 rounded-lg p-1 space-x-1 bg-gray-100">
                        <button class="filter-btn px-3 py-1 text-sm rounded-md active" data-view="weekly">Semanal</button>
                        <button class="filter-btn px-3 py-1 text-sm rounded-md" data-view="monthly">Mensal</button>
                        <button class="filter-btn px-3 py-1 text-sm rounded-md" data-view="annual">Anual</button>
                    </div>
                </div>
                <div id="activityChart" class="w-full h-80"></div>
            </div>

            <!-- Tabela de Resumo Semanal -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-4">
                    <h3 class="text-lg font-medium">Resumo Semanal do Mês</h3>
                    <div class="flex items-center gap-2">
                        <button id="exportPdfBtn" class="flex items-center gap-1 px-3 py-2 bg-red-500 text-white text-sm rounded-md hover:bg-red-600 transition-colors">
                            <i class="ri-file-pdf-line"></i> Exportar PDF
                        </button>
                    </div>
                </div>
                
                <!-- Mini Sparkline Chart -->
                <div id="weeklySparkline" class="w-full h-48 mb-4 bg-gradient-to-r from-gray-50 to-white rounded-lg border border-gray-100"></div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th class="px-3 py-3">Semana</th>
                                <th class="px-3 py-3">Período</th>
                                <th class="px-3 py-3 text-right">Entradas</th>
                                <th class="px-3 py-3 text-right">Saídas</th>
                                <th class="px-3 py-3 text-right">Saldo</th>
                                <th class="px-3 py-3 text-center">Diferença</th>
                                <th class="px-3 py-3 text-center">Detalhes</th>
                            </tr>
                        </thead>
                        <tbody id="weeklyTableBody">
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">A carregar...</td></tr>
                        </tbody>
                        <tfoot id="weeklyTableFooter" class="bg-gray-50 font-bold">
                        </tfoot>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div id="dayModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-lg transform scale-95 opacity-0"><div class="flex justify-between items-center border-b p-4"><h3 id="modalDate" class="text-lg font-medium"></h3><button id="closeModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button></div><div id="modalContent" class="p-6 max-h-96 overflow-y-auto"></div></div></div>
    <div id="dateRangeModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0"><div class="flex justify-between items-center border-b p-4"><h3 class="text-lg font-medium">Relatório por Período</h3><button id="closeDateRangeModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button></div><div class="p-6 space-y-4"><div class="flex flex-col sm:flex-row gap-4"><div><label for="startDate" class="text-sm font-medium text-gray-700">Data de Início</label><input type="date" id="startDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div><div><label for="endDate" class="text-sm font-medium text-gray-700">Data de Fim</label><input type="date" id="endDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div></div><button id="calculateRangeBtn" class="w-full bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700">Calcular Gasto Total</button><div id="rangeResult" class="mt-4 text-center"></div></div></div></div>
    
    <!-- Modal de Detalhes Semanais -->
    <div id="weeklyDetailModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-2xl transform scale-95 opacity-0">
            <div class="flex justify-between items-center border-b p-4">
                <h3 id="weeklyModalTitle" class="text-lg font-medium">Detalhes da Semana</h3>
                <button id="closeWeeklyModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button>
            </div>
            <div id="weeklyModalContent" class="p-6 max-h-96 overflow-y-auto"></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const calendarGrid = document.getElementById("calendarGrid");
        const currentMonthEl = document.getElementById("currentMonth");
        const prevMonthBtn = document.getElementById("prevMonth");
        const nextMonthBtn = document.getElementById("nextMonth");
        const activityChart = echarts.init(document.getElementById("activityChart"));
        const dayModal = document.getElementById("dayModal"), modalDateEl = document.getElementById("modalDate"), modalContentEl = document.getElementById("modalContent"), closeModalBtn = document.getElementById("closeModal");
        const dateRangeReportBtn = document.getElementById("dateRangeReportBtn"), dateRangeModal = document.getElementById("dateRangeModal"), closeDateRangeModalBtn = document.getElementById("closeDateRangeModal"), calculateRangeBtn = document.getElementById("calculateRangeBtn"), rangeResultEl = document.getElementById("rangeResult");
        const summaryEntriesEl = document.getElementById("summary-total-entries"), summaryExpensesEl = document.getElementById("summary-total-expenses"), summaryBalanceEl = document.getElementById("summary-balance");
        const filterButtons = document.querySelectorAll('.filter-btn');
        let currentChartView = 'weekly';
        
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        let activityData = {};
        const churchBalance = <?php echo json_encode($church_balance); ?>;
        
        // --- Lógica do Menu Responsivo ---
        const sidebar = document.getElementById('sidebar');
        const hamburgerMenu = document.getElementById('hamburger-menu');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        hamburgerMenu.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
        // --- Fim da Lógica do Menu ---

        function formatCurrency(value) {
            const number = parseFloat(value);
            return `${(isNaN(number) ? 0 : number).toFixed(2).replace('.', ',')} MZN`;
        }

        async function fetchCalendarData(year, month) {
            try {
                currentMonthEl.textContent = 'A carregar...';
                const response = await fetch(`reports.php?fetch_data=1&year=${year}&month=${month + 1}`);
                if (!response.ok) { throw new Error('Network response was not ok'); }
                activityData = await response.json();
                updateCalendar(month, year);
            } catch (error) {
                console.error('Failed to fetch calendar data:', error);
                calendarGrid.innerHTML = '<p class="col-span-7 text-center text-red-500">Erro ao carregar dados.</p>';
            }
        }

        function updateCalendar(month, year) {
            calendarGrid.innerHTML = "";
            const monthNames = ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"];
            currentMonthEl.textContent = `${monthNames[month]} ${year}`;
            const firstDay = new Date(year, month, 1).getDay();
            const lastDate = new Date(year, month + 1, 0).getDate();
            for (let i = 0; i < firstDay; i++) { calendarGrid.insertAdjacentHTML("beforeend", `<div class="h-16 sm:h-20"></div>`); }
            for (let day = 1; day <= lastDate; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                let indicatorsHTML = '';
                if (activityData.entries && activityData.entries[day]) { indicatorsHTML += `<div class="w-1.5 h-1.5 bg-green-600 rounded-full" title="Entradas"></div>`; }
                if (activityData.expenses && activityData.expenses[day]) { indicatorsHTML += `<div class="w-1.5 h-1.5 bg-red-600 rounded-full" title="Saídas"></div>`; }
                let dayCellHTML = `<div class="calendar-day h-16 sm:h-20 p-1 sm:p-2 border border-gray-100 rounded text-right relative hover:bg-gray-50 cursor-pointer" data-date="${dateStr}"><span class="text-xs sm:text-sm">${day}</span><div class="absolute bottom-1.5 left-0 right-0 flex justify-center gap-1">${indicatorsHTML}</div></div>`;
                calendarGrid.insertAdjacentHTML("beforeend", dayCellHTML);
            }
            updateSummaryCards(month, year);
        }

        function updateSummaryCards(month, year) {
            const entries = activityData.entries ? Object.values(activityData.entries).map(parseFloat) : [0];
            const expenses = activityData.expenses ? Object.values(activityData.expenses).map(parseFloat) : [0];
            const totalEntries = entries.reduce((a, b) => a + b, 0);
            const totalExpenses = expenses.reduce((a, b) => a + b, 0);
            summaryEntriesEl.textContent = formatCurrency(totalEntries);
            summaryExpensesEl.textContent = formatCurrency(totalExpenses);
            summaryBalanceEl.textContent = formatCurrency(churchBalance);
            summaryBalanceEl.classList.toggle('text-red-600', parseFloat(churchBalance) < 0);
            summaryBalanceEl.classList.toggle('text-gray-800', parseFloat(churchBalance) >= 0);
        }

        async function fetchChartData(view) {
            try {
                activityChart.showLoading();
                const response = await fetch(`reports.php?fetch_chart_data=1&view=${view}`);
                if (!response.ok) { throw new Error('Network response was not ok'); }
                const chartData = await response.json();
                activityChart.hideLoading();
                updateChart(chartData);
            } catch (error) {
                console.error(`Failed to fetch ${view} chart data:`, error);
                activityChart.hideLoading();
            }
        }
        
        function updateChart(chartData) {
            activityChart.setOption({
                tooltip: { trigger: 'axis' },
                legend: { data: ['Entradas', 'Saídas'], bottom: 0 },
                grid: { left: 80, right: 30, top: 20, bottom: 40 },
                xAxis: { type: 'category', boundaryGap: false, data: chartData.labels },
                yAxis: { type: 'value', axisLabel: { formatter: '{value} MZN' } },
                series: [
                    { name: 'Entradas', type: 'line', smooth: true, data: chartData.entries, color: '#2E7D32' },
                    { name: 'Saídas', type: 'line', smooth: true, data: chartData.expenses, color: '#C62828' }
                ]
            }, true);
        }

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                currentChartView = button.dataset.view;
                fetchChartData(currentChartView);
            });
        });

        calendarGrid.addEventListener('click', (e) => {
            const dayCell = e.target.closest('.calendar-day');
            if(dayCell && dayCell.dataset.date) {
                const dateStr = dayCell.dataset.date;
                const day = parseInt(dayCell.querySelector('span').textContent);
                const dayData = activityData.details[day];
                
                modalDateEl.textContent = new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' });
                
                let dayTotalEntries = 0;
                let dayTotalExpenses = 0;
                let detailsHTML = '';

                if (dayData) {
                    if (dayData.entries && dayData.entries.length > 0) {
                        dayTotalEntries = dayData.entries.reduce((sum, item) => sum + parseFloat(item.amount), 0);
                        detailsHTML += `<div><h4 class="font-semibold text-green-700 mb-2">Entradas</h4><ul class="space-y-2">`;
                        dayData.entries.forEach(item => detailsHTML += `<li class="text-sm flex justify-between"><span>${item.description}</span><span class="font-medium">${formatCurrency(item.amount)}</span></li>`);
                        detailsHTML += `</ul></div>`;
                    }
                    if (dayData.expenses && dayData.expenses.length > 0) {
                        dayTotalExpenses = dayData.expenses.reduce((sum, item) => sum + parseFloat(item.amount), 0);
                        detailsHTML += `<div><h4 class="font-semibold text-red-700 mb-2 mt-4">Saídas</h4><ul class="space-y-3">`;
                        dayData.expenses.forEach(expense => {
                            let expenseDetails = '';
                            try {
                                const items = JSON.parse(expense.description);
                                if (Array.isArray(items) && items.length > 0) {
                                    const itemParts = items.map(subItem =>
                                        `<li class="text-xs ml-4 flex justify-between">
                                            <span>- ${subItem.description || 'Item'} (${subItem.quantity || 1} x ${parseFloat(subItem.unit_price || 0).toFixed(2).replace('.', ',')})</span>
                                            <span>${parseFloat(subItem.total || 0).toFixed(2).replace('.', ',')}</span>
                                        </li>`
                                    ).join('');
                                    expenseDetails = `<ul class="mt-1 space-y-1 text-gray-600">${itemParts}</ul>`;
                                } else {
                                    expenseDetails = `<p class="text-xs ml-4 text-gray-600">${expense.description}</p>`;
                                }
                            } catch (e) {
                                expenseDetails = `<p class="text-xs ml-4 text-gray-600">${expense.description}</p>`;
                            }

                            detailsHTML += `
                                <li class="text-sm flex justify-between items-start border-b pb-2">
                                    <div>
                                        <span class="font-medium">${expense.category || 'N/A'}</span>
                                        ${expenseDetails}
                                    </div>
                                    <span class="font-medium whitespace-nowrap pl-2">${formatCurrency(expense.amount)}</span>
                                </li>`;
                        });
                        detailsHTML += `</ul></div>`;
                    }
                }

                let summaryHTML = `<div class="mb-4 space-y-2 border-b pb-4">
                    <div class="flex justify-between text-md"><span class="text-gray-600">Total de Entradas do Dia:</span><strong class="text-green-600">${formatCurrency(dayTotalEntries)}</strong></div>
                    <div class="flex justify-between text-md"><span class="text-gray-600">Total de Saídas do Dia:</span><strong class="text-red-600">${formatCurrency(dayTotalExpenses)}</strong></div>
                </div>`;
                
                modalContentEl.innerHTML = summaryHTML + (detailsHTML || '<p class="text-gray-500">Nenhuma atividade registada.</p>');
                
                dayModal.classList.remove("hidden");
                setTimeout(() => dayModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);
            }
        });

        closeModal.addEventListener('click', () => {
             dayModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
             setTimeout(() => dayModal.classList.add("hidden"), 200);
        });
        
        dateRangeReportBtn.addEventListener('click', () => {
            dateRangeModal.classList.remove("hidden");
            setTimeout(() => dateRangeModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);
        });

        closeDateRangeModal.addEventListener('click', () => {
             dateRangeModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-95');
             setTimeout(() => dateRangeModal.classList.add("hidden"), 200);
        });
        
        calculateRangeBtn.addEventListener('click', async () => {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if(!startDate || !endDate) {
                rangeResultEl.innerHTML = `<p class="text-red-500">Por favor, selecione as duas datas.</p>`;
                return;
            }
            rangeResultEl.innerHTML = `<p>A calcular...</p>`;
            try {
                const response = await fetch(`reports.php?fetch_range_data=1&start_date=${startDate}&end_date=${endDate}`);
                const result = await response.json();
                if(result.error) {
                    rangeResultEl.innerHTML = `<p class="text-red-500">${result.error}</p>`;
                } else {
                    rangeResultEl.innerHTML = `<p class="text-lg">Gasto Total no Período: <strong class="text-primary">${formatCurrency(result.total_expenses)}</strong></p>`;
                }
            } catch (error) {
                rangeResultEl.innerHTML = `<p class="text-red-500">Ocorreu um erro ao calcular.</p>`;
            }
        });

        prevMonthBtn.addEventListener("click", () => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } fetchCalendarData(currentYear, currentMonth); fetchWeeklyData(currentYear, currentMonth); });
        nextMonthBtn.addEventListener("click", () => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } fetchCalendarData(currentYear, currentMonth); fetchWeeklyData(currentYear, currentMonth); });

        // --- LÓGICA DA TABELA SEMANAL ---
        const weeklyTableBody = document.getElementById('weeklyTableBody');
        const weeklyTableFooter = document.getElementById('weeklyTableFooter');
        const weeklyDetailModal = document.getElementById('weeklyDetailModal');
        const weeklyModalTitle = document.getElementById('weeklyModalTitle');
        const weeklyModalContent = document.getElementById('weeklyModalContent');
        const closeWeeklyModalBtn = document.getElementById('closeWeeklyModal');
        let weeklyData = [];

        async function fetchWeeklyData(year, month) {
            try {
                weeklyTableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">A carregar...</td></tr>';
                const response = await fetch(`reports.php?fetch_weekly_summary=1&year=${year}&month=${month + 1}`);
                if (!response.ok) throw new Error('Network error');
                const data = await response.json();
                weeklyData = data.weeks || [];
                renderWeeklyTable();
            } catch (error) {
                console.error('Failed to fetch weekly data:', error);
                weeklyTableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">Erro ao carregar dados semanais.</td></tr>';
            }
        }

        function formatDateShort(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }

        // Inicializar sparkline chart
        const weeklySparkline = echarts.init(document.getElementById('weeklySparkline'));

        function renderWeeklyTable() {
            if (weeklyData.length === 0) {
                weeklyTableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhuma atividade neste mês.</td></tr>';
                weeklyTableFooter.innerHTML = '';
                weeklySparkline.clear();
                return;
            }

            let totalEntries = 0, totalExpenses = 0;
            let accumulatedBalance = 0;
            let html = '';
            
            // Preparar dados para sparkline
            const sparkLabels = [];
            const sparkEntries = [];
            const sparkExpenses = [];
            
            // Calcular o saldo inicial (saldo da conta - total do mês = saldo anterior)
            const monthTotal = weeklyData.reduce((sum, w) => sum + w.balance, 0);
            const startingBalance = churchBalance - monthTotal;
            let runningBalance = startingBalance; // Saldo inicial (como num banco)
            
            // Adicionar linha de saldo inicial (Início)
            const startingBalanceClass = startingBalance >= 0 ? 'text-green-600' : 'text-red-600';
            html += `
                <tr class="border-b bg-blue-50 hover:bg-blue-100 transition-colors">
                    <td class="px-3 py-3 font-medium">
                        <div class="flex items-center gap-2">
                            <span class="text-blue-700">Início</span>
                            <i class="ri-arrow-right-line text-blue-400"></i>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-xs text-gray-600 italic">Saldo anterior ao mês</td>
                    <td class="px-3 py-3 text-right font-medium text-gray-400">-</td>
                    <td class="px-3 py-3 text-right font-medium text-gray-400">-</td>
                    <td class="px-3 py-3 text-right font-bold ${startingBalanceClass}">${formatCurrency(startingBalance)}</td>
                    <td class="px-3 py-3 text-center text-gray-400">-</td>
                    <td class="px-3 py-3 text-center text-gray-400">-</td>
                </tr>`;

            weeklyData.forEach((week, index) => {
                totalEntries += week.total_entries;
                totalExpenses += week.total_expenses;
                
                // Atualizar saldo corrente (como extrato bancário)
                runningBalance += week.total_entries - week.total_expenses;
                
                // Cor de fundo condicional da linha
                let rowBgClass = '';
                if (week.balance > 100) rowBgClass = 'bg-green-50';
                else if (week.balance < -100) rowBgClass = 'bg-red-50';
                
                const balanceClass = runningBalance >= 0 ? 'text-green-600' : 'text-red-600';
                const weekDiffClass = week.balance >= 0 ? 'text-green-600' : 'text-red-600';
                const hasEntries = week.entries && week.entries.length > 0;
                const hasExpenses = week.expenses && week.expenses.length > 0;
                
                // Barra de progresso visual (entradas vs saídas)
                const maxAmount = Math.max(week.total_entries, week.total_expenses, 1);
                const entryWidth = (week.total_entries / maxAmount) * 100;
                const expenseWidth = (week.total_expenses / maxAmount) * 100;
                
                // Dados para sparkline
                sparkLabels.push(`Sem ${week.week_num}`);
                sparkEntries.push(week.total_entries);
                sparkExpenses.push(week.total_expenses);

                html += `
                    <tr class="border-b hover:bg-gray-100 ${rowBgClass} transition-colors">
                        <td class="px-3 py-3 font-medium">
                            <div class="flex items-center gap-2">
                                <span>Sem ${week.week_num}</span>
                                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden min-w-[60px] max-w-[80px]">
                                    <div class="h-full bg-gradient-to-r from-green-400 to-green-600" style="width: ${entryWidth}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs text-gray-600">${formatDateShort(week.start_date)} - ${formatDateShort(week.end_date)}</td>
                        <td class="px-3 py-3 text-right font-medium text-green-600">${formatCurrency(week.total_entries)}</td>
                        <td class="px-3 py-3 text-right font-medium text-red-600">${formatCurrency(week.total_expenses)}</td>
                        <td class="px-3 py-3 text-right font-bold ${balanceClass}">${formatCurrency(runningBalance)}</td>
                        <td class="px-3 py-3 text-center">
                            <span class="text-xs ${weekDiffClass}">${week.balance >= 0 ? '+' : ''}${formatCurrency(week.balance)}</span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <div class="flex justify-center gap-1">
                                <button class="view-entries-btn px-2 py-1 text-xs rounded ${hasEntries ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}" ${!hasEntries ? 'disabled' : ''} data-week="${index}" title="Ver Entradas">
                                    <i class="ri-arrow-right-circle-line"></i>
                                </button>
                                <button class="view-expenses-btn px-2 py-1 text-xs rounded ${hasExpenses ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}" ${!hasExpenses ? 'disabled' : ''} data-week="${index}" title="Ver Saídas">
                                    <i class="ri-arrow-left-circle-line"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
            });

            weeklyTableBody.innerHTML = html;

            // Atualizar sparkline chart com design melhorado
            weeklySparkline.setOption({
                tooltip: { 
                    trigger: 'axis',
                    backgroundColor: 'rgba(255,255,255,0.95)',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    textStyle: { color: '#333', fontSize: 12 },
                    formatter: (params) => {
                        let result = `<div style="font-weight:600;margin-bottom:8px;">${params[0].name}</div>`;
                        params.forEach(p => {
                            const color = p.seriesName === 'Entradas' ? '#10b981' : '#ef4444';
                            result += `<div style="display:flex;align-items:center;gap:8px;margin:4px 0;">
                                <span style="width:10px;height:10px;border-radius:50%;background:${color};"></span>
                                <span>${p.seriesName}:</span>
                                <strong style="color:${color}">${formatCurrency(p.value)}</strong>
                            </div>`;
                        });
                        return result;
                    }
                },
                legend: {
                    data: ['Entradas', 'Saídas'],
                    bottom: 0,
                    itemWidth: 12,
                    itemHeight: 12,
                    textStyle: { fontSize: 11, color: '#666' },
                    icon: 'roundRect'
                },
                grid: { left: 60, right: 30, top: 20, bottom: 45 },
                xAxis: { 
                    type: 'category', 
                    data: sparkLabels, 
                    axisLabel: { fontSize: 11, color: '#666', fontWeight: 500 },
                    axisLine: { lineStyle: { color: '#e5e7eb' } },
                    axisTick: { show: false }
                },
                yAxis: { 
                    type: 'value', 
                    axisLabel: { 
                        formatter: (v) => v >= 1000 ? (v/1000).toFixed(0) + 'k' : v, 
                        fontSize: 10,
                        color: '#999'
                    },
                    splitLine: { lineStyle: { color: '#f0f0f0', type: 'dashed' } },
                    axisLine: { show: false },
                    axisTick: { show: false }
                },
                series: [
                    { 
                        name: 'Entradas', 
                        type: 'bar', 
                        data: sparkEntries, 
                        barGap: '15%',
                        barWidth: '35%',
                        itemStyle: {
                            color: {
                                type: 'linear',
                                x: 0, y: 0, x2: 0, y2: 1,
                                colorStops: [
                                    { offset: 0, color: '#34d399' },
                                    { offset: 1, color: '#10b981' }
                                ]
                            },
                            borderRadius: [4, 4, 0, 0]
                        },
                        emphasis: { itemStyle: { color: '#059669' } }
                    },
                    { 
                        name: 'Saídas', 
                        type: 'bar', 
                        data: sparkExpenses,
                        barWidth: '35%',
                        itemStyle: {
                            color: {
                                type: 'linear',
                                x: 0, y: 0, x2: 0, y2: 1,
                                colorStops: [
                                    { offset: 0, color: '#f87171' },
                                    { offset: 1, color: '#ef4444' }
                                ]
                            },
                            borderRadius: [4, 4, 0, 0]
                        },
                        emphasis: { itemStyle: { color: '#dc2626' } }
                    }
                ]
            }, true);

            const totalBalance = totalEntries - totalExpenses;
            const totalBalanceClass = totalBalance >= 0 ? 'text-green-600' : 'text-red-600';
            const churchBalanceClass = parseFloat(churchBalance) >= 0 ? 'text-primary' : 'text-red-600';
            weeklyTableFooter.innerHTML = `
                <tr class="bg-gray-100 font-semibold">
                    <td class="px-3 py-3" colspan="2">TOTAL DO MÊS</td>
                    <td class="px-3 py-3 text-right text-green-600">${formatCurrency(totalEntries)}</td>
                    <td class="px-3 py-3 text-right text-red-600">${formatCurrency(totalExpenses)}</td>
                    <td class="px-3 py-3 text-right font-bold ${churchBalanceClass}">${formatCurrency(churchBalance)}</td>
                    <td class="px-3 py-3 text-center">
                        <span class="text-sm font-bold ${totalBalanceClass}">${totalBalance >= 0 ? '+' : ''}${formatCurrency(totalBalance)}</span>
                    </td>
                    <td class="px-3 py-3"></td>
                </tr>`;

            // Event listeners para botões de detalhes
            document.querySelectorAll('.view-entries-btn:not([disabled])').forEach(btn => {
                btn.addEventListener('click', () => showWeeklyDetail(parseInt(btn.dataset.week), 'entries'));
            });
            document.querySelectorAll('.view-expenses-btn:not([disabled])').forEach(btn => {
                btn.addEventListener('click', () => showWeeklyDetail(parseInt(btn.dataset.week), 'expenses'));
            });
        }

        // PDF Export functionality
        document.getElementById('exportPdfBtn').addEventListener('click', () => {
            const monthNames = ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"];
            const monthYear = `${monthNames[currentMonth]} ${currentYear}`;
            const reportDate = new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
            
            let printContent = `
                <html><head>
                    <title>Relatório Financeiro - ${monthYear}</title>
                    <style>
                        * { box-sizing: border-box; margin: 0; padding: 0; }
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; color: #333; line-height: 1.5; }
                        
                        .header { border-bottom: 3px solid #1976D2; padding-bottom: 20px; margin-bottom: 25px; }
                        .header h1 { color: #1976D2; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
                        .header h2 { color: #666; font-size: 16px; font-weight: 400; margin-top: 5px; }
                        .header .report-info { display: flex; justify-content: space-between; margin-top: 15px; font-size: 12px; color: #888; }
                        
                        .summary-cards { display: flex; gap: 15px; margin-bottom: 25px; }
                        .summary-card { flex: 1; padding: 15px; border-radius: 8px; text-align: center; }
                        .summary-card.green { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-left: 4px solid #10b981; }
                        .summary-card.red { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); border-left: 4px solid #ef4444; }
                        .summary-card.blue { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid #1976D2; }
                        .summary-card .label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
                        .summary-card .value { font-size: 20px; font-weight: 700; margin-top: 5px; }
                        .summary-card.green .value { color: #059669; }
                        .summary-card.red .value { color: #dc2626; }
                        .summary-card.blue .value { color: #1976D2; }
                        
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
                        th { background: #1976D2; color: white; padding: 12px 10px; text-align: left; font-weight: 600; }
                        td { border-bottom: 1px solid #e5e7eb; padding: 10px; }
                        tr:nth-child(even) { background-color: #f9fafb; }
                        tr:hover { background-color: #f3f4f6; }
                        
                        .text-green { color: #059669; }
                        .text-red { color: #dc2626; }
                        .text-blue { color: #1976D2; }
                        .text-right { text-align: right; }
                        .bold { font-weight: 700; }
                        
                        .expense-section { background: #fef2f2; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #ef4444; }
                        .expense-title { font-weight: 700; color: #991b1b; margin-bottom: 12px; font-size: 13px; border-bottom: 1px solid #fecaca; padding-bottom: 8px; }
                        .expense-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #fecaca; }
                        .expense-item:last-of-type { border-bottom: none; }
                        .expense-date { font-size: 10px; color: #9ca3af; }
                        .expense-category { font-weight: 600; color: #374151; font-size: 13px; }
                        .expense-recipient { font-size: 11px; color: #6b7280; }
                        .expense-items { margin-top: 5px; padding-left: 10px; }
                        .expense-items div { font-size: 10px; color: #6b7280; line-height: 1.6; }
                        .expense-amount { font-weight: 700; color: #dc2626; font-size: 13px; }
                        .expense-total { display: flex; justify-content: space-between; font-weight: 700; padding-top: 12px; margin-top: 10px; border-top: 2px solid #dc2626; }
                        
                        .footer-row { background: #f3f4f6 !important; }
                        .footer-row td { padding: 12px 10px; font-weight: 600; }
                        .balance-row { background: #dbeafe !important; }
                        .balance-row td { font-size: 14px; }
                        
                        .page-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
                        
                        @media print { body { padding: 15px; } }
                    </style>
                </head><body>
                    <div class="header">
                        <h1>Life Church</h1>
                        <h2>Relatório Financeiro Semanal</h2>
                        <div class="report-info">
                            <span>Período: ${monthYear}</span>
                            <span>Gerado em: ${reportDate}</span>
                        </div>
                    </div>`;
            
            // Calcular saldo inicial para o PDF
            const pdfMonthTotal = weeklyData.reduce((sum, w) => sum + w.balance, 0);
            const pdfStartingBalance = churchBalance - pdfMonthTotal;
            let pdfRunningBalance = pdfStartingBalance;
            let totalEnt = 0, totalExp = 0;
            
            weeklyData.forEach(week => {
                totalEnt += week.total_entries;
                totalExp += week.total_expenses;
            });
            
            const totalBal = totalEnt - totalExp;
            
            // Summary cards
            printContent += `
                    <div class="summary-cards">
                        <div class="summary-card green">
                            <div class="label">Total Entradas</div>
                            <div class="value">${formatCurrency(totalEnt)}</div>
                        </div>
                        <div class="summary-card red">
                            <div class="label">Total Saídas</div>
                            <div class="value">${formatCurrency(totalExp)}</div>
                        </div>
                        <div class="summary-card blue">
                            <div class="label">Saldo Actual</div>
                            <div class="value">${formatCurrency(churchBalance)}</div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Semana</th>
                                <th>Período</th>
                                <th class="text-right">Entradas</th>
                                <th class="text-right">Saídas</th>
                                <th class="text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            weeklyData.forEach(week => {
                pdfRunningBalance += week.total_entries - week.total_expenses;
                const balClass = pdfRunningBalance >= 0 ? 'text-green' : 'text-red';
                
                printContent += `
                            <tr>
                                <td class="bold">Semana ${week.week_num}</td>
                                <td>${formatDateShort(week.start_date)} - ${formatDateShort(week.end_date)}</td>
                                <td class="text-right text-green bold">${formatCurrency(week.total_entries)}</td>
                                <td class="text-right text-red bold">${formatCurrency(week.total_expenses)}</td>
                                <td class="text-right bold ${balClass}">${formatCurrency(pdfRunningBalance)}</td>
                            </tr>`;
                
                // Adicionar detalhes de saídas se houver
                if (week.expenses && week.expenses.length > 0) {
                    printContent += `
                            <tr>
                                <td colspan="5" style="padding: 0;">
                                    <div class="expense-section">
                                        <div class="expense-title">Detalhes das Saídas - Semana ${week.week_num}</div>`;
                    
                    week.expenses.forEach(exp => {
                        const expDate = new Date(exp.transaction_date + 'T00:00:00');
                        const dateStr = expDate.toLocaleDateString('pt-BR');
                        
                        // Extrair itens individuais do JSON
                        let itemsHtml = '';
                        try {
                            if (exp.description) {
                                const items = JSON.parse(exp.description);
                                if (Array.isArray(items) && items.length > 0) {
                                    itemsHtml = '<div class="expense-items">' + items.map(item => `<div>- ${item.description || 'Item'}</div>`).join('') + '</div>';
                                }
                            }
                        } catch(e) {
                            if (exp.description) {
                                itemsHtml = `<div class="expense-items"><div>- ${exp.description}</div></div>`;
                            }
                        }
                        
                        printContent += `
                                        <div class="expense-item">
                                            <div>
                                                <div class="expense-date">${dateStr}</div>
                                                <div class="expense-category">${exp.category || exp.category_name || 'Sem categoria'}</div>
                                                <div class="expense-recipient">${exp.paid_to || exp.received_by || ''}</div>
                                                ${itemsHtml}
                                            </div>
                                            <div class="expense-amount">${formatCurrency(exp.amount)}</div>
                                        </div>`;
                    });
                    
                    printContent += `
                                        <div class="expense-total">
                                            <span>Subtotal da Semana</span>
                                            <span class="text-red">${formatCurrency(week.total_expenses)}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>`;
                }
            });
            
            printContent += `
                        </tbody>
                        <tfoot>
                            <tr class="footer-row">
                                <td colspan="4">Saldo Inicial do Mês</td>
                                <td class="text-right">${formatCurrency(pdfStartingBalance)}</td>
                            </tr>
                            <tr class="footer-row">
                                <td colspan="2">TOTAL DO MÊS</td>
                                <td class="text-right text-green bold">${formatCurrency(totalEnt)}</td>
                                <td class="text-right text-red bold">${formatCurrency(totalExp)}</td>
                                <td class="text-right bold ${totalBal >= 0 ? 'text-green' : 'text-red'}">${totalBal >= 0 ? '+' : ''}${formatCurrency(totalBal)}</td>
                            </tr>
                            <tr class="balance-row">
                                <td colspan="4" class="bold">SALDO ACTUAL DA CONTA</td>
                                <td class="text-right bold text-blue">${formatCurrency(churchBalance)}</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="page-footer">
                        Life Church - Sistema de Gestão Financeira | Documento gerado automaticamente
                    </div>
                </body></html>`;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        });

        // Resize sparkline on window resize
        window.addEventListener('resize', () => {
            weeklySparkline.resize();
        });

        function showWeeklyDetail(weekIndex, type) {
            const week = weeklyData[weekIndex];
            if (!week) return;

            const period = `${formatDateShort(week.start_date)} - ${formatDateShort(week.end_date)}`;
            
            if (type === 'entries') {
                weeklyModalTitle.textContent = `Entradas da Semana ${week.week_num} (${period})`;
                let html = '<div class="space-y-3">';
                if (week.entries && week.entries.length > 0) {
                    week.entries.forEach(entry => {
                        const date = new Date(entry.service_date + 'T00:00:00').toLocaleDateString('pt-BR');
                        html += `
                            <div class="border-b pb-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-xs text-gray-500">${date}</span>
                                        <p class="font-medium">${entry.theme || 'Culto Geral'}</p>
                                        <p class="text-xs text-gray-600">${entry.total_attendance || 0} participantes</p>
                                    </div>
                                    <span class="font-bold text-green-600">${formatCurrency(entry.total_offering)}</span>
                                </div>
                            </div>`;
                    });
                    html += `<div class="pt-2 mt-2 border-t flex justify-between font-bold"><span>Total de Entradas:</span><span class="text-green-600">${formatCurrency(week.total_entries)}</span></div>`;
                } else {
                    html += '<p class="text-gray-500 text-center py-4">Nenhuma entrada nesta semana.</p>';
                }
                html += '</div>';
                weeklyModalContent.innerHTML = html;
            } else {
                weeklyModalTitle.textContent = `Saídas da Semana ${week.week_num} (${period})`;
                let html = '<div class="space-y-3">';
                if (week.expenses && week.expenses.length > 0) {
                    week.expenses.forEach(expense => {
                        const date = new Date(expense.transaction_date.replace(/-/g, '/')).toLocaleDateString('pt-BR');
                        let itemsHtml = '';
                        try {
                            const items = JSON.parse(expense.description);
                            if (Array.isArray(items)) {
                                itemsHtml = items.map(i => `<span class="text-xs text-gray-500">• ${i.description || 'Item'}</span>`).join('<br>');
                            }
                        } catch(e) { itemsHtml = `<span class="text-xs text-gray-500">${expense.description}</span>`; }
                        
                        html += `
                            <div class="border-b pb-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-xs text-gray-500">${date}</span>
                                        <p class="font-medium">${expense.category || 'Sem categoria'}</p>
                                        <p class="text-xs text-gray-600">${expense.paid_to || ''}</p>
                                        ${itemsHtml}
                                    </div>
                                    <span class="font-bold text-red-600">${formatCurrency(expense.amount)}</span>
                                </div>
                            </div>`;
                    });
                    html += `<div class="pt-2 mt-2 border-t flex justify-between font-bold"><span>Total de Saídas:</span><span class="text-red-600">${formatCurrency(week.total_expenses)}</span></div>`;
                } else {
                    html += '<p class="text-gray-500 text-center py-4">Nenhuma saída nesta semana.</p>';
                }
                html += '</div>';
                weeklyModalContent.innerHTML = html;
            }

            weeklyDetailModal.classList.remove('hidden');
            setTimeout(() => weeklyDetailModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);
        }

        closeWeeklyModalBtn.addEventListener('click', () => {
            weeklyDetailModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
            setTimeout(() => weeklyDetailModal.classList.add('hidden'), 200);
        });

        weeklyDetailModal.addEventListener('click', (e) => {
            if (e.target === weeklyDetailModal) {
                weeklyDetailModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
                setTimeout(() => weeklyDetailModal.classList.add('hidden'), 200);
            }
        });

        // Carga inicial
        fetchCalendarData(currentYear, currentMonth);
        fetchChartData(currentChartView);
        fetchWeeklyData(currentYear, currentMonth);
        window.addEventListener('resize', () => activityChart.resize());

        // Lógica do menu de utilizador
        const userMenuButton = document.getElementById("user-menu-button");
        const userMenu = document.getElementById("user-menu");
        if(userMenuButton) {
            userMenuButton.addEventListener("click", (event) => { userMenu.classList.toggle("hidden"); event.stopPropagation(); });
            document.addEventListener("click", (event) => { if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) { userMenu.classList.add("hidden"); } });
        }
    });
    </script>
</body>
</html>
