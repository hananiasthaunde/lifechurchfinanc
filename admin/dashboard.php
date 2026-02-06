<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Corrigido: O caminho para o login deve sair da pasta 'admin' e entrar na 'public'
    header('Location: ../public/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$church_id = $_SESSION['church_id'] ?? null; // Garantir que church_id existe

// --- CONTROLE DE ACESSO: LÍDERES ---
// Líderes não têm acesso ao Dashboard Geral, devem ser redirecionados para a Gestão de Células
if ($user_role === 'lider') {
    header('Location: celulas.php');
    exit;
}

// Conectar ao banco de dados
$conn = connect_db();
$church_name = 'Sem Igreja';
$church_balance = 0;
$member_count = 0;
$pie_chart_data_full = [];
$pie_chart_data_dashboard = [];
$line_chart_data = [];
$recent_activities = [];

/**
 * Função melhorada para formatar a descrição da despesa.
 * Se a descrição for um JSON, formata para uma string legível.
 * Ex: 'Lâmpadas (x2), Notas (x1)'
 */
function format_expense_description($description) {
    if (empty($description)) {
        return 'Despesa sem descrição';
    }
    
    // Tenta decodificar como array associativo
    $items = json_decode($description, true);

    // Verifica se é um array de itens (novo formato)
    if (json_last_error() === JSON_ERROR_NONE && is_array($items) && isset($items[0]['description'])) {
        $output_parts = [];
        foreach ($items as $item) {
            $desc_text = isset($item['description']) ? htmlspecialchars($item['description']) : 'Item';
            if (isset($item['quantity']) && $item['quantity'] > 1) {
                $desc_text .= " (x" . $item['quantity'] . ")";
            }
            $output_parts[] = $desc_text;
        }
        return implode(', ', $output_parts);
    }

    // Verifica se é um objeto JSON (formato antigo)
    if (json_last_error() === JSON_ERROR_NONE && is_object(json_decode($description))) {
         $data = json_decode($description);
         $desc_text = isset($data->description) ? htmlspecialchars($data->description) : 'Item';
         $quantity = isset($data->quantity) ? (int)$data->quantity : null;
         if ($quantity && $quantity > 1) {
              return sprintf('%s (x%d)', $desc_text, $quantity);
         }
         return $desc_text;
    }

    // Se não for JSON, retorna a descrição original
    return htmlspecialchars($description);
}

if ($church_id) {
    // --- DADOS DOS CARDS ---
    $stmt_church = $conn->prepare("SELECT name, balance FROM churches WHERE id = ?");
    $stmt_church->bind_param("i", $church_id);
    $stmt_church->execute();
    $result_church = $stmt_church->get_result();
    if ($result_church->num_rows > 0) {
        $church = $result_church->fetch_assoc();
        $church_name = $church['name'];
        $church_balance = $church['balance'];
    }
    $stmt_church->close();

    $stmt_members = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE church_id = ?");
    $stmt_members->bind_param("i", $church_id);
    $stmt_members->execute();
    $result_members = $stmt_members->get_result()->fetch_assoc();
    $member_count = $result_members['count'];
    $stmt_members->close();

    // --- DADOS PARA OS GRÁFICOS ---
    $current_month = date('m');
    $current_year = date('Y');

    // 1. Gráfico de Pizza (Saídas por Categoria no Mês Atual)
    $stmt_pie = $conn->prepare("SELECT c.name, SUM(e.amount) as total FROM expenses e JOIN categories c ON e.category_id = c.id WHERE e.church_id = ? AND MONTH(e.transaction_date) = ? AND YEAR(e.transaction_date) = ? GROUP BY c.name HAVING total > 0 ORDER BY total DESC");
    $stmt_pie->bind_param("iii", $church_id, $current_month, $current_year);
    $stmt_pie->execute();
    $pie_result = $stmt_pie->get_result();
    while ($row = $pie_result->fetch_assoc()) {
        $pie_chart_data_full[] = ['name' => $row['name'], 'value' => (float)$row['total']];
    }
    $stmt_pie->close();
    
    // Processar dados para o gráfico do dashboard (Top 3 + Outros)
    if (count($pie_chart_data_full) > 3) {
        $pie_chart_data_dashboard = array_slice($pie_chart_data_full, 0, 3);
        $other_items = array_slice($pie_chart_data_full, 3);
        $other_total = 0;
        foreach($other_items as $item) {
            $other_total += $item['value'];
        }
        if ($other_total > 0) {
            $pie_chart_data_dashboard[] = ['name' => 'Outros', 'value' => $other_total];
        }
    } else {
        $pie_chart_data_dashboard = $pie_chart_data_full;
    }
    
    // 2. Gráfico de Linhas (Últimos 30 dias)
    $labels = [];
    $entries_data = [];
    $expenses_data = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d/m', strtotime($date));
        $stmt_entries_day = $conn->prepare("SELECT SUM(total_offering) as total FROM service_reports WHERE church_id = ? AND service_date = ?");
        $stmt_entries_day->bind_param("is", $church_id, $date);
        $stmt_entries_day->execute();
        $entries_data[] = $stmt_entries_day->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt_entries_day->close();
        
        $stmt_expenses_day = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE church_id = ? AND DATE(transaction_date) = ?");
        $stmt_expenses_day->bind_param("is", $church_id, $date);
        $stmt_expenses_day->execute();
        $expenses_data[] = $stmt_expenses_day->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt_expenses_day->close();
    }
    $line_chart_data = ['labels' => $labels, 'entries' => $entries_data, 'expenses' => $expenses_data];

    // --- DADOS PARA ATIVIDADES RECENTES (Consulta corrigida e otimizada) ---
    $stmt_activities = $conn->prepare("
        SELECT * FROM (
            SELECT 'entrada' as type, CAST(service_date AS DATETIME) as transaction_timestamp, CONCAT('Entrada de Culto') as description, total_offering as amount, id, 'Oferta de Culto' as category_name
            FROM service_reports WHERE church_id = ?
            UNION ALL
            SELECT 'saida' as type, e.transaction_date as transaction_timestamp, e.description, e.amount, e.id, c.name as category_name
            FROM expenses e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.church_id = ?
        ) as activities
        ORDER BY transaction_timestamp DESC, id DESC
        LIMIT 100
    ");
    $stmt_activities->bind_param("ii", $church_id, $church_id);
    $stmt_activities->execute();
    $activities_result = $stmt_activities->get_result();
    while ($row = $activities_result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $stmt_activities->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: { primary: "#1976D2", secondary: "#BBDEFB" },
            borderRadius: { button: "8px" },
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <style>
      body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
      .sidebar-item { transition: all 0.2s ease; }
      .sidebar-item:hover:not(.active) { background-color: #F0F7FF; }
      .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
      .card { transition: all 0.2s ease; }
      .card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
      .dropdown-menu { transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out; }
      .modal-transition { transition: opacity 0.3s ease; }
      .modal-content-transition { transition: transform 0.3s ease; }
      #sidebar { transition: transform 0.3s ease-in-out; }
    </style>
  </head>
  <body class="relative lg:flex">
    <!-- Overlay para o menu mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>

    <aside id="sidebar" class="w-64 h-screen bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-30 transform -translate-x-full lg:translate-x-0">
      <div class="p-6 border-b border-gray-100 flex items-center justify-between">
        <span class="text-2xl font-['Pacifico'] text-primary">Life Church</span>
        <button id="close-sidebar-btn" class="lg:hidden text-gray-500 hover:text-gray-800">
            <i class="ri-close-line ri-xl"></i>
        </button>
      </div>
      <nav class="flex-1 overflow-y-auto py-4">
        <div class="px-4 mb-6">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
          <?php $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
          <!-- CORREÇÃO: Adicionado .php a todos os links do menu -->
          <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
          <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'entries.php' ? 'active' : ''; ?>"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
          <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'expenses.php' ? 'active' : ''; ?>"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
          <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'members.php' ? 'active' : ''; ?>"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
          <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
          <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
          <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'budget.php' ? 'active' : ''; ?>"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
          <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'categories.php' ? 'active' : ''; ?>"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
        </div>
        <div class="px-4">
          <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
          <!-- CORREÇÃO: Adicionado .php a todos os links do menu -->
          <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
          <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
        </div>
      </nav>
      <div class="p-4 border-t border-gray-100"><div class="flex items-center p-2"><div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary font-bold text-lg mr-3"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div><div><p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></p><p class="text-xs text-gray-500"><?php echo ucfirst(htmlspecialchars($user_role)); ?></p></div></div></div>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="bg-white border-b border-gray-200 shadow-sm z-10 sticky top-0">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary">
                        <i class="ri-menu-line ri-lg"></i>
                    </button>
                    <h1 class="text-lg font-medium text-gray-800">Painel de Controle</h1>
                </div>
                
                <div class="relative">
                    <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors">
                        <span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                        <div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                    </button>
                    
                    <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 dropdown-menu origin-top-right">
                        <!-- CORREÇÃO: Adicionado .php a todos os links do menu -->
                        <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a>
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line mr-3"></i>Sair</a>
                    </div>
                </div>
            </div>
        </header>

      <main class="flex-1 p-4 sm:p-6 bg-gray-50">
        <div class="mb-6">
          <h2 class="text-2xl font-medium text-gray-800">Bem-vindo(a), <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h2>
          <p class="text-gray-500">Aqui está o resumo da sua igreja.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
          <div class="bg-white rounded-lg shadow-sm p-6 card"><div class="flex items-center justify-between mb-4"><h3 class="text-gray-500 text-sm font-medium">Saldo Atual</h3><div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center text-green-500"><i class="ri-wallet-3-line ri-lg"></i></div></div><div class="flex items-end"><span class="text-2xl md:text-3xl font-semibold text-gray-800"><?php echo number_format($church_balance, 2, ',', '.'); ?> MZN</span></div></div>
          <div class="bg-white rounded-lg shadow-sm p-6 card"><div class="flex items-center justify-between mb-4"><h3 class="text-gray-500 text-sm font-medium">Total de Membros</h3><div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-primary"><i class="ri-group-line ri-lg"></i></div></div><div class="flex items-end"><span class="text-2xl md:text-3xl font-semibold text-gray-800"><?php echo $member_count; ?></span></div></div>
          <div class="bg-white rounded-lg shadow-sm p-6 card"><div class="flex items-center justify-between mb-4"><h3 class="text-gray-500 text-sm font-medium">Igreja</h3><div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center text-purple-500"><i class="ri-church-line ri-lg"></i></div></div><div class="flex items-end"><span class="text-xl md:text-2xl font-semibold text-gray-800"><?php echo htmlspecialchars($church_name); ?></span></div></div>
          <div class="bg-white rounded-lg shadow-sm p-6 card"><div class="flex items-center justify-between mb-4"><h3 class="text-gray-500 text-sm font-medium">Sua Função</h3><div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-500"><i class="ri-user-star-line ri-lg"></i></div></div><div class="flex items-end"><span class="text-xl md:text-2xl font-semibold text-gray-800"><?php echo ucfirst(htmlspecialchars($user_role)); ?></span></div></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-1 bg-white rounded-lg shadow-sm p-6 card">
                 <div class="flex justify-between items-center mb-4">
                     <h2 class="text-lg font-medium text-gray-800">Saídas por Categoria (Mês)</h2>
                     <button id="expand-pie-chart-btn" class="text-gray-400 hover:text-primary"><i class="ri-fullscreen-line"></i></button>
                 </div>
                 <div id="expenses-pie-chart" style="height: 250px;"></div>
                 <div id="pie-chart-legend" class="mt-4 text-sm space-y-1"></div>
            </div>
            <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-6 card">
                 <h2 class="text-lg font-medium text-gray-800 mb-4">Atividade Financeira (Últimos 30 dias)</h2>
                 <div id="monthly-activity-chart" style="height: 300px;"></div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 card">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Últimas Atividades Financeiras</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_activities)): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center" colspan="4">
                                    Nenhuma atividade recente.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity):
                                $type_text = $activity['type'] === 'entrada' ? 'Entrada' : 'Saída';
                                $formatted_date = date('d/m/Y', strtotime($activity['transaction_timestamp']));
                                $formatted_datetime = date('d/m/Y H:i', strtotime($activity['transaction_timestamp']));
                                $formatted_amount = number_format($activity['amount'], 2, ',', '.');
                                $display_description = $activity['type'] === 'saida' 
                                    ? format_expense_description($activity['description']) 
                                    : htmlspecialchars($activity['description']);
                            ?>
                                <tr>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-full bg-<?php echo $activity['type'] === 'entrada' ? 'green' : 'red'; ?>-100 text-<?php echo $activity['type'] === 'entrada' ? 'green' : 'red'; ?>-600">
                                                <i class="ri-<?php echo $activity['type'] === 'entrada' ? 'arrow-up' : 'arrow-down'; ?>-line ri-lg"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 truncate max-w-xs"><?php echo $display_description; ?></div>
                                                <div class="text-sm text-gray-500"><?php echo $type_text; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 hidden md:table-cell">
                                        <?php echo $formatted_date; ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm font-medium text-<?php echo $activity['type'] === 'entrada' ? 'green' : 'red'; ?>-600">
                                        <?php echo ($activity['type'] === 'entrada' ? '+ ' : '- ') . $formatted_amount; ?> MZN
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button class="view-details-btn text-primary hover:text-blue-700 text-xs sm:text-sm py-1 px-2 rounded-md bg-primary/10 hover:bg-primary/20"
                                                data-type="<?php echo $type_text; ?>"
                                                data-description="<?php echo htmlspecialchars($display_description); ?>"
                                                data-amount="<?php echo ($activity['type'] === 'entrada' ? '+ ' : '- ') . $formatted_amount . ' MZN'; ?>"
                                                data-date="<?php echo $formatted_datetime; ?>"
                                                data-category="<?php echo htmlspecialchars($activity['category_name'] ?? 'N/A'); ?>">
                                            Detalhes
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </main>
    </div>
    
    <div id="pie-chart-modal" class="modal-transition fixed inset-0 bg-black bg-opacity-60 z-40 flex items-center justify-center hidden p-4">
        <div class="modal-content-transition bg-white rounded-lg shadow-xl w-full max-w-2xl transform scale-95 opacity-0 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-medium">Saídas por Categoria (Mês)</h3>
                <button id="close-pie-chart-modal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button>
            </div>
            <div id="expanded-pie-chart" style="height: 450px;"></div>
        </div>
    </div>
    
    <div id="activity-details-modal" class="modal-transition fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden p-4">
        <div class="modal-content-transition bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0 p-6">
            <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                <h3 class="text-xl font-medium text-gray-800">Detalhes da Atividade</h3>
                <button id="close-activity-modal-btn" class="w-8 h-8 flex items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition-colors">
                    <i class="ri-close-line ri-lg"></i>
                </button>
            </div>
            <div id="activity-modal-body" class="py-4 space-y-3">
                <p class="flex justify-between text-sm"><span class="font-medium text-gray-500">Tipo:</span> <span id="modal-type" class="text-gray-800 font-semibold"></span></p>
                <p class="flex justify-between text-sm"><span class="font-medium text-gray-500">Descrição:</span> <span id="modal-description" class="text-gray-800 text-right"></span></p>
                <p class="flex justify-between text-sm"><span class="font-medium text-gray-500">Categoria:</span> <span id="modal-category" class="text-gray-800"></span></p>
                <p class="flex justify-between text-sm"><span class="font-medium text-gray-500">Valor:</span> <span id="modal-amount" class="text-gray-800 font-bold"></span></p>
                <p class="flex justify-between text-sm"><span class="font-medium text-gray-500">Data:</span> <span id="modal-date" class="text-gray-800"></span></p>
            </div>
        </div>
    </div>

    <script id="charts-script">
      document.addEventListener("DOMContentLoaded", function () {
        const pieChartContainer = document.getElementById("expenses-pie-chart");
        const pieChartLegendContainer = document.getElementById("pie-chart-legend");
        const pieChart = echarts.init(pieChartContainer);
        const pieDataFull = <?php echo json_encode($pie_chart_data_full); ?>;
        const pieDataDashboard = <?php echo json_encode($pie_chart_data_dashboard); ?>;
        
        const colorPalette = ['#1976D2', '#F46036', '#2E294E', '#1B998B', '#C5D86D', '#F4A261', '#E76F51', '#E9C46A', '#2A9D8F', '#264653'];
        let pieOption = {};

        if(pieDataDashboard.length > 0) {
            pieOption = {
              tooltip: { trigger: 'item', formatter: '{b}: {c} MZN ({d}%)' },
              legend: { show: false },
              color: colorPalette,
              series: [{
                name: 'Saídas do Mês', type: 'pie', radius: ['50%', '80%'], avoidLabelOverlap: false,
                itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
                label: { show: false }, labelLine: { show: false }, data: pieDataDashboard
              }]
            };
            pieChart.setOption(pieOption);

            pieChartLegendContainer.innerHTML = ''; 
            pieDataDashboard.forEach((item, index) => {
                const color = colorPalette[index % colorPalette.length];
                const legendItem = document.createElement('div');
                legendItem.className = 'flex items-center';
                legendItem.innerHTML = `<span class="w-3 h-3 rounded-full mr-2" style="background-color: ${color};"></span><span>${item.name}</span>`;
                pieChartLegendContainer.appendChild(legendItem);
            });
        } else {
             pieChartContainer.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">Sem dados de saídas para este mês.</div>';
        }

        const lineChart = echarts.init(document.getElementById("monthly-activity-chart"));
        const lineData = <?php echo json_encode($line_chart_data); ?>;
        const lineOption = {
          tooltip: { trigger: "axis", formatter: function(params) {
              let res = params[0].name + '<br/>';
              params.forEach(function(item) {
                  res += item.marker + item.seriesName + ': ' + parseFloat(item.value).toFixed(2) + ' MZN<br/>';
              });
              return res;
          }},
          legend: { data: ["Entradas", "Saídas"] },
          grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
          xAxis: { type: "category", boundaryGap: false, data: lineData.labels },
          yAxis: { type: "value", axisLabel: { formatter: '{value} MZN' } },
          series: [
            { name: "Entradas", type: "line", smooth: true, data: lineData.entries, lineStyle: { color: '#4CAF50' }, areaStyle: { color: 'rgba(76, 175, 80, 0.1)'}},
            { name: "Saídas", type: "line", smooth: true, data: lineData.expenses, lineStyle: { color: '#F44336' }, areaStyle: { color: 'rgba(244, 67, 54, 0.1)'}}
          ]
        };
        lineChart.setOption(lineOption);
        
        const allCharts = [pieChart, lineChart];

        const expandPieBtn = document.getElementById("expand-pie-chart-btn");
        const pieModal = document.getElementById("pie-chart-modal");
        const closePieModalBtn = document.getElementById("close-pie-chart-modal");
        let expandedPieChart = null;

        expandPieBtn.addEventListener('click', () => {
            if (pieDataFull.length === 0) return;
            pieModal.classList.remove('hidden');
            setTimeout(() => {
                pieModal.querySelector('.modal-content-transition').classList.add('opacity-100', 'scale-100');
                if (!expandedPieChart) {
                    expandedPieChart = echarts.init(document.getElementById('expanded-pie-chart'));
                    allCharts.push(expandedPieChart);
                }
                const expandedPieOption = { 
                    ...pieOption, legend: { show: true, top: 'bottom', left: 'center' }, 
                    series: [{ ...pieOption.series[0], data: pieDataFull, radius: '75%', label: { show: true, formatter: '{b}\n({d}%)' }, labelLine: { show: true } }]
                };
                expandedPieChart.setOption(expandedPieOption, true);
                setTimeout(() => expandedPieChart.resize(), 50);
            }, 10);
        });

        const closePieModal = () => {
            pieModal.querySelector('.modal-content-transition').classList.remove('opacity-100', 'scale-100');
            setTimeout(() => pieModal.classList.add('hidden'), 200);
        };
        closePieModalBtn.addEventListener('click', closePieModal);
        pieModal.addEventListener('click', (e) => { if(e.target === pieModal) closePieModal(); });
        window.addEventListener("resize", () => { allCharts.forEach(chart => chart && chart.resize()); });
      });
    </script>
    <script id="user-menu-script">
        document.addEventListener("DOMContentLoaded", function () {
            const userMenuButton = document.getElementById("user-menu-button");
            const userMenu = document.getElementById("user-menu");
            if(userMenuButton) {
                userMenuButton.addEventListener("click", function (event) {
                    userMenu.classList.toggle("hidden");
                    event.stopPropagation();
                });
                document.addEventListener("click", function (event) {
                    if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) {
                        userMenu.classList.add("hidden");
                    }
                });
            }
        });
    </script>
    <script id="activity-modal-script">
        document.addEventListener("DOMContentLoaded", function () {
            const activityModal = document.getElementById('activity-details-modal');
            if (!activityModal) return;
            const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
            const closeActivityModalBtn = document.getElementById('close-activity-modal-btn');
            const modalContent = activityModal.querySelector('.modal-content-transition');
            const modalType = document.getElementById('modal-type');
            const modalDescription = document.getElementById('modal-description');
            const modalCategory = document.getElementById('modal-category');
            const modalAmount = document.getElementById('modal-amount');
            const modalDate = document.getElementById('modal-date');

            const openModal = (button) => {
                modalType.textContent = button.dataset.type;
                modalDescription.textContent = button.dataset.description;
                modalCategory.textContent = button.dataset.category;
                modalAmount.textContent = button.dataset.amount;
                modalDate.textContent = button.dataset.date;
                if(button.dataset.type === 'Entrada') {
                    modalAmount.classList.remove('text-red-600');
                    modalAmount.classList.add('text-green-600');
                } else {
                    modalAmount.classList.remove('text-green-600');
                    modalAmount.classList.add('text-red-600');
                }
                activityModal.classList.remove('hidden');
                setTimeout(() => { modalContent.classList.add('opacity-100', 'scale-100'); }, 10);
            };

            const closeModal = () => {
                modalContent.classList.remove('opacity-100', 'scale-100');
                setTimeout(() => { activityModal.classList.add('hidden'); }, 200);
            };

            viewDetailsButtons.forEach(button => { button.addEventListener('click', () => openModal(button)); });
            closeActivityModalBtn.addEventListener('click', closeModal);
            activityModal.addEventListener('click', (e) => { if (e.target === activityModal) { closeModal(); } });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !activityModal.classList.contains('hidden')) { closeModal(); } });
        });
    </script>
    <script id="sidebar-toggle-script">
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const openBtn = document.getElementById('open-sidebar-btn');
            const closeBtn = document.getElementById('close-sidebar-btn');
            const overlay = document.getElementById('sidebar-overlay');

            const showSidebar = () => {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            };

            const hideSidebar = () => {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            };

            openBtn.addEventListener('click', showSidebar);
            closeBtn.addEventListener('click', hideSidebar);
            overlay.addEventListener('click', hideSidebar);
        });
    </script>
  </body>
</html>