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
if (isset($_GET['fetch_data']) || isset($_GET['fetch_chart_data']) || isset($_GET['fetch_range_data'])) {
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

    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
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

            <div class="bg-white rounded-lg shadow p-6">
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
        </main>
    </div>

    <!-- Modals -->
    <div id="dayModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-lg transform scale-95 opacity-0"><div class="flex justify-between items-center border-b p-4"><h3 id="modalDate" class="text-lg font-medium"></h3><button id="closeModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button></div><div id="modalContent" class="p-6 max-h-96 overflow-y-auto"></div></div></div>
    <div id="dateRangeModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0"><div class="flex justify-between items-center border-b p-4"><h3 class="text-lg font-medium">Relatório por Período</h3><button id="closeDateRangeModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button></div><div class="p-6 space-y-4"><div class="flex flex-col sm:flex-row gap-4"><div><label for="startDate" class="text-sm font-medium text-gray-700">Data de Início</label><input type="date" id="startDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div><div><label for="endDate" class="text-sm font-medium text-gray-700">Data de Fim</label><input type="date" id="endDate" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></div></div><button id="calculateRangeBtn" class="w-full bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700">Calcular Gasto Total</button><div id="rangeResult" class="mt-4 text-center"></div></div></div></div>

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

        prevMonthBtn.addEventListener("click", () => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } fetchCalendarData(currentYear, currentMonth); });
        nextMonthBtn.addEventListener("click", () => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } fetchCalendarData(currentYear, currentMonth); });

        // Carga inicial
        fetchCalendarData(currentYear, currentMonth);
        fetchChartData(currentChartView);
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
