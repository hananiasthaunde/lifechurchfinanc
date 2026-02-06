<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';


$user_id = $_SESSION['user_id'];
$church_id = $_SESSION['church_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// --- CONTROLE DE ACESSO ---
if ($user_role === 'lider') {
    header('Location: celulas.php');
    exit;
}

// --- LÓGICA DO FILTRO DE DATA E VISUALIZAÇÃO ---
$current_year = date('Y');
$current_month = date('m');

$selected_year = $_GET['year'] ?? $current_year;
$selected_month = $_GET['month'] ?? $current_month;
$view_mode = $_GET['view'] ?? 'monthly'; // 'daily', 'weekly', 'monthly'

$conn = connect_db();

// --- DADOS PARA O GRÁFICO (Dinâmico: Diário, Semanal, Mensal) ---
$chart_data = ['labels' => [], 'adults' => [], 'children' => [], 'offerings' => []];

$sql_chart = "";
if ($view_mode === 'daily') {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
    for($i = 1; $i <= $days_in_month; $i++) {
        $chart_data['labels'][] = str_pad($i, 2, '0', STR_PAD_LEFT);
        $chart_data['adults'][] = 0;
        $chart_data['children'][] = 0;
        $chart_data['offerings'][] = 0;
    }
    $sql_chart = "SELECT DAY(service_date) as day, SUM(adults_members + adults_visitors) as total_adults, SUM(children_members + children_visitors) as total_children, SUM(total_offering) as total_offerings FROM service_reports WHERE church_id = ? AND YEAR(service_date) = ? AND MONTH(service_date) = ? GROUP BY DAY(service_date)";
    $stmt_chart = $conn->prepare($sql_chart);
    $stmt_chart->bind_param("iss", $church_id, $selected_year, $selected_month);
} elseif ($view_mode === 'weekly') {
    $chart_data['labels'] = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'];
    $chart_data['adults'] = array_fill(0, 5, 0);
    $chart_data['children'] = array_fill(0, 5, 0);
    $chart_data['offerings'] = array_fill(0, 5, 0);
    $sql_chart = "SELECT CEIL(DAY(service_date)/7) as week, SUM(adults_members + adults_visitors) as total_adults, SUM(children_members + children_visitors) as total_children, SUM(total_offering) as total_offerings FROM service_reports WHERE church_id = ? AND YEAR(service_date) = ? AND MONTH(service_date) = ? GROUP BY week";
    $stmt_chart = $conn->prepare($sql_chart);
    $stmt_chart->bind_param("iss", $church_id, $selected_year, $selected_month);
} else { // monthly (default)
    $chart_data['labels'] = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $chart_data['adults'] = array_fill(0, 12, 0);
    $chart_data['children'] = array_fill(0, 12, 0);
    $chart_data['offerings'] = array_fill(0, 12, 0);
    $sql_chart = "SELECT MONTH(service_date) as month, SUM(adults_members + adults_visitors) as total_adults, SUM(children_members + children_visitors) as total_children, SUM(total_offering) as total_offerings FROM service_reports WHERE church_id = ? AND YEAR(service_date) = ? GROUP BY MONTH(service_date)";
    $stmt_chart = $conn->prepare($sql_chart);
    $stmt_chart->bind_param("is", $church_id, $selected_year);
}

$stmt_chart->execute();
$result_chart = $stmt_chart->get_result();
while($row = $result_chart->fetch_assoc()) {
    $idx_key = array_keys($row)[0];
    $idx = $row[$idx_key] - 1;
    if(isset($chart_data['adults'][$idx])) {
        $chart_data['adults'][$idx] = (int)$row['total_adults'];
        $chart_data['children'][$idx] = (int)$row['total_children'];
        $chart_data['offerings'][$idx] = (float)$row['total_offerings'];
    }
}
$stmt_chart->close();


// --- DADOS PARA AS TABELAS (MENSAL) ---
$monthly_reports_list = [];
$first_day_of_month = "{$selected_year}-{$selected_month}-01";

$stmt_table = $conn->prepare("
    SELECT service_date, theme, adults_members, adults_visitors, children_members, children_visitors, adult_saved + child_saved as total_saved, offering, special_offering, (SELECT SUM(amount) FROM tithes WHERE report_id = sr.id) as total_tithes, total_offering
    FROM service_reports sr
    WHERE church_id = ? AND YEAR(service_date) = ? AND MONTH(service_date) = ?
    ORDER BY service_date ASC
");
$stmt_table->bind_param("iss", $church_id, $selected_year, $selected_month);
$stmt_table->execute();
$result_table = $stmt_table->get_result();
while($row = $result_table->fetch_assoc()){
    $monthly_reports_list[] = $row;
}

// --- CALCULAR TOTAIS PARA TABELA DE RESUMO ---
$summary_table_data = [];
for ($w = 1; $w <= 5; $w++) {
    $summary_table_data[$w] = [
        'adults_members' => 0, 'children_members' => 0, 'adults_visitors' => 0, 'children_visitors' => 0,
        'total_participants' => 0, 'total_saved' => 0, 'offering' => 0, 'total_tithes' => 0, 'special_offering' => 0, 'total_offering' => 0
    ];
}

foreach ($monthly_reports_list as $report) {
    $week_of_month = ceil(date('d', strtotime($report['service_date'])) / 7);
    if($week_of_month > 5) $week_of_month = 5;

    $summary_table_data[$week_of_month]['adults_members'] += $report['adults_members'];
    $summary_table_data[$week_of_month]['children_members'] += $report['children_members'];
    $summary_table_data[$week_of_month]['adults_visitors'] += $report['adults_visitors'];
    $summary_table_data[$week_of_month]['children_visitors'] += $report['children_visitors'];
    $summary_table_data[$week_of_month]['total_saved'] += $report['total_saved'];
    $summary_table_data[$week_of_month]['offering'] += $report['offering'];
    $summary_table_data[$week_of_month]['total_tithes'] += $report['total_tithes'] ?? 0;
    $summary_table_data[$week_of_month]['special_offering'] += $report['special_offering'];
    $summary_table_data[$week_of_month]['total_offering'] += $report['total_offering'];
    $summary_table_data[$week_of_month]['total_participants'] = $summary_table_data[$week_of_month]['adults_members'] + $summary_table_data[$week_of_month]['children_members'] + $summary_table_data[$week_of_month]['adults_visitors'] + $summary_table_data[$week_of_month]['children_visitors'];
}

$stmt_table->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Relatório de Presenças</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = { theme: { extend: { colors: { primary: "#1976D2", secondary: "#BBDEFB", accent: { light: '#34d399', DEFAULT: '#10b981', dark: '#059669' } }, borderRadius: { button: "8px", card: "1rem" }, fontFamily: { sans: ['Inter', 'sans-serif'], pacifico: ['Pacifico', 'cursive'] } } } };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
      body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
      .sidebar-item { transition: all 0.2s ease; }
      .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 0 4px 4px 0; }
      .dropdown-menu { transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out; }
      #sidebar { transition: transform 0.3s ease-in-out; }
      .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .filter-btn.active { background-color: #1976D2; color: white; }
      .card {
          background-color: white;
          border-radius: 0.75rem;
          box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
          transition: all 0.3s ease-in-out;
      }
      .card:hover {
          box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
      }
    </style>
  </head>
<body class="bg-slate-100">
    <div class="lg:flex">
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>

        <aside id="sidebar" class="w-64 h-screen bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-30 transform -translate-x-full lg:translate-x-0">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <span class="font-pacifico text-2xl text-primary">Life Church</span>
                <button id="close-sidebar-btn" class="lg:hidden text-gray-500 hover:text-gray-800"><i class="ri-close-line ri-xl"></i></button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 mb-6">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 px-4">Menu Principal</p>
                <?php $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
                <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
                <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
                <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
                <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
                <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 active"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
                <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
                <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
                <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
                </div>
                <div class="px-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 px-4">Sistema</p>
                <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
                </div>
            </nav>
            <div class="p-4 border-t border-gray-100"><div class="flex items-center p-2"><div class="w-10 h-10 rounded-full bg-secondary text-primary flex items-center justify-center font-bold text-lg mr-3"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div><div><p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></p><p class="text-xs text-gray-500"><?php echo ucfirst(htmlspecialchars($user_role)); ?></p></div></div></div>
        </aside>

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="bg-white border-b border-gray-200 shadow-sm z-10 sticky top-0">
                <div class="flex items-center justify-between h-16 px-6">
                    <div class="flex items-center">
                        <button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary"><i class="ri-menu-line ri-lg"></i></button>
                        <h1 class="text-xl font-semibold text-gray-800">Relatório de Presenças e Finanças</h1>
                    </div>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors"><span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span><div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div></button>
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 dropdown-menu origin-top-right"><a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a><a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line mr-3"></i>Sair</a></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 overflow-y-auto">
                <div class="card p-4 mb-6">
                    <form id="filter-form" method="GET" action="attendance.php" class="flex flex-col sm:flex-row items-center gap-4">
                        <div class="w-full sm:w-auto">
                            <label for="month" class="sr-only">Mês:</label>
                            <select name="month" id="month" class="w-full sm:w-auto mt-1 block px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="year" class="sr-only">Ano:</label>
                            <select name="year" id="year" class="w-full sm:w-auto mt-1 block px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                                <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto flex items-center bg-gray-100 rounded-lg p-1">
                            <a href="?year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>&view=daily" class="filter-btn px-3 py-1 text-sm rounded-md <?php echo $view_mode === 'daily' ? 'active' : 'hover:bg-gray-200'; ?>">Diário</a>
                            <a href="?year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>&view=weekly" class="filter-btn px-3 py-1 text-sm rounded-md <?php echo $view_mode === 'weekly' ? 'active' : 'hover:bg-gray-200'; ?>">Semanal</a>
                            <a href="?year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>&view=monthly" class="filter-btn px-3 py-1 text-sm rounded-md <?php echo $view_mode === 'monthly' ? 'active' : 'hover:bg-gray-200'; ?>">Mensal</a>
                        </div>
                    </form>
                </div>
                
                <div class="card p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Visão Geral (<?php echo ucfirst($view_mode); ?> - <?php echo $selected_year; ?>)</h2>
                    <div class="h-96"><canvas id="attendanceChart"></canvas></div>
                </div>

                <!-- Tabela de Resumo Semanal -->
                <div class="card mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 p-4 border-b">Resumo Semanal - <?php echo date('F Y', strtotime($first_day_of_month)); ?></h2>
                    <div class="table-responsive">
                        <table class="w-full text-sm text-left text-gray-500">
                             <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-2 py-3 border">Semana</th>
                                    <th class="px-2 py-3 border text-center">Adultos</th>
                                    <th class="px-2 py-3 border text-center">Crianças</th>
                                    <th class="px-2 py-3 border text-center font-bold">Total Pres.</th>
                                    <th class="px-2 py-3 border text-center">Salvos</th>
                                    <th class="px-2 py-3 border text-right">Ofertas</th>
                                    <th class="px-2 py-3 border text-right">Dízimos</th>
                                    <th class="px-2 py-3 border text-right font-bold">Total Fin.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($monthly_reports_list)): ?>
                                    <tr><td colspan="8" class="text-center py-4 text-gray-500">Nenhum relatório encontrado para este mês.</td></tr>
                                <?php else: ?>
                                    <?php
                                    $monthly_totals = ['adults'=>0,'children'=>0,'participants'=>0,'saved'=>0,'offerings'=>0,'tithes'=>0,'total_finance'=>0];
                                    for ($w = 1; $w <= 5; $w++):
                                        $data = $summary_table_data[$w];
                                        if($data['total_participants'] == 0 && $data['total_offering'] == 0 && $w > 1 && (!isset($monthly_reports_list[array_key_last($monthly_reports_list)]) || ceil(date('d', strtotime($monthly_reports_list[array_key_last($monthly_reports_list)]['service_date'])) / 7) < $w)) continue;

                                        $adults_total = $data['adults_members'] + $data['adults_visitors'];
                                        $children_total = $data['children_members'] + $data['children_visitors'];
                                        
                                        $monthly_totals['adults'] += $adults_total;
                                        $monthly_totals['children'] += $children_total;
                                        $monthly_totals['participants'] += $data['total_participants'];
                                        $monthly_totals['saved'] += $data['total_saved'];
                                        $monthly_totals['offerings'] += $data['offering'] + $data['special_offering'];
                                        $monthly_totals['tithes'] += $data['total_tithes'];
                                        $monthly_totals['total_finance'] += $data['total_offering'];
                                    ?>
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="px-2 py-2 border font-semibold">Semana <?php echo $w; ?></td>
                                        <td class="px-2 py-2 border text-center"><?php echo $adults_total; ?></td>
                                        <td class="px-2 py-2 border text-center"><?php echo $children_total; ?></td>
                                        <td class="px-2 py-2 border text-center font-bold text-primary"><?php echo $data['total_participants']; ?></td>
                                        <td class="px-2 py-2 border text-center text-accent-dark"><?php echo $data['total_saved']; ?></td>
                                        <td class="px-2 py-2 border text-right"><?php echo number_format($data['offering'] + $data['special_offering'], 2, ',', '.'); ?></td>
                                        <td class="px-2 py-2 border text-right"><?php echo number_format($data['total_tithes'], 2, ',', '.'); ?></td>
                                        <td class="px-2 py-2 border text-right font-bold text-primary"><?php echo number_format($data['total_offering'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endfor; ?>
                                    <tr class="bg-gray-200 font-bold">
                                        <td class="px-2 py-2 border text-right">Total Mensal</td>
                                        <td class="px-2 py-2 border text-center"><?php echo $monthly_totals['adults']; ?></td>
                                        <td class="px-2 py-2 border text-center"><?php echo $monthly_totals['children']; ?></td>
                                        <td class="px-2 py-2 border text-center"><?php echo $monthly_totals['participants']; ?></td>
                                        <td class="px-2 py-2 border text-center"><?php echo $monthly_totals['saved']; ?></td>
                                        <td class="px-2 py-2 border text-right"><?php echo number_format($monthly_totals['offerings'], 2, ',', '.'); ?></td>
                                        <td class="px-2 py-2 border text-right"><?php echo number_format($monthly_totals['tithes'], 2, ',', '.'); ?></td>
                                        <td class="px-2 py-2 border text-right"><?php echo number_format($monthly_totals['total_finance'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabela de Detalhes dos Cultos -->
                 <div class="card mt-6">
                    <h2 class="text-xl font-semibold text-gray-900 p-4 border-b">Lista de Cultos do Mês</h2>
                     <div class="table-responsive">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-2 py-3 border">Data</th>
                                    <th class="px-2 py-3 border">Tema</th>
                                    <th class="px-2 py-3 border text-center">Participantes</th>
                                    <th class="px-2 py-3 border text-right">Total Arrecadado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($monthly_reports_list)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-gray-500">Nenhum culto registado para este mês.</td></tr>
                                <?php else: ?>
                                    <?php foreach($monthly_reports_list as $report): ?>
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="px-2 py-2 border"><?php echo date('d/m/Y', strtotime($report['service_date'])); ?></td>
                                        <td class="px-2 py-2 border"><?php echo htmlspecialchars($report['theme']); ?></td>
                                        <td class="px-2 py-2 border text-center"><?php echo $report['adults_members'] + $report['adults_visitors'] + $report['children_members'] + $report['children_visitors']; ?></td>
                                        <td class="px-2 py-2 border text-right font-medium text-green-600"><?php echo number_format($report['total_offering'], 2, ',', '.'); ?> MZN</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const userMenuButton = document.getElementById("user-menu-button");
        const userMenu = document.getElementById("user-menu");
        if(userMenuButton) {
            userMenuButton.addEventListener("click", (event) => { userMenu.classList.toggle("hidden"); event.stopPropagation(); });
            document.addEventListener("click", (event) => { if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) { userMenu.classList.add("hidden"); } });
        }

        const filterForm = document.getElementById('filter-form');
        const monthSelect = document.getElementById('month');
        const yearSelect = document.getElementById('year');

        monthSelect.addEventListener('change', () => filterForm.submit());
        yearSelect.addEventListener('change', () => filterForm.submit());
        
        const chartData = <?php echo json_encode($chart_data); ?>;
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    { label: 'Adultos', data: chartData.adults, backgroundColor: 'rgba(59, 130, 246, 0.7)', yAxisID: 'y' },
                    { label: 'Crianças', data: chartData.children, backgroundColor: 'rgba(251, 146, 60, 0.7)', yAxisID: 'y' },
                    { label: 'Ofertas (MZN)', data: chartData.offerings, type: 'line', borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.3, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { type: 'linear', display: true, position: 'left', stacked: true, title: { display: true, text: 'Nº de Pessoas' } },
                    y1: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Valor (MZN)'}, grid: { drawOnChartArea: false } }
                },
                plugins: { tooltip: { mode: 'index', intersect: false } }
            }
        });
    });
    </script>
    <script id="sidebar-toggle-script">
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const openBtn = document.getElementById('open-sidebar-btn');
            const closeBtn = document.getElementById('close-sidebar-btn');
            const overlay = document.getElementById('sidebar-overlay');
            const showSidebar = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
            const hideSidebar = () => { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); };
            if(openBtn) openBtn.addEventListener('click', showSidebar);
            if(closeBtn) closeBtn.addEventListener('click', hideSidebar);
            if(overlay) overlay.addEventListener('click', hideSidebar);
        });
    </script>
</body>
</html>
