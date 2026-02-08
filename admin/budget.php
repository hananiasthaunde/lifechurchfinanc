<?php
// Mostrar erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$church_id = $_SESSION['church_id'];

// --- CONTROLE DE ACESSO ---
if ($user_role === 'lider') {
    header('Location: celulas.php');
    exit;
}

$conn = connect_db();
$message = '';
$error = '';
$current_year = date('Y');
$selected_year = $_GET['year'] ?? $current_year;
$budget_id = null;
$budget_data = null;

// --- AÇÕES DO FORMULÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. CRIAR BUDGET
    if ($action === 'create_budget') {
        $year = intval($_POST['year']);
        $stmt = $conn->prepare("INSERT INTO budgets (church_id, year, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $church_id, $year, $user_id);
        
        if ($stmt->execute()) {
            $new_budget_id = $stmt->insert_id;
            
            // Sincronizar categorias automaticamente
            $stmt_sync = $conn->prepare("
                INSERT INTO budget_items (budget_id, category_id)
                SELECT ?, id FROM categories 
                WHERE type = 'saida' 
                AND id NOT IN (SELECT category_id FROM budget_items WHERE budget_id = ?)
            ");
            $stmt_sync->bind_param("ii", $new_budget_id, $new_budget_id);
            $stmt_sync->execute();
            
            // Log
            $log_action = "Criou budget $year";
            $conn->query("INSERT INTO budget_logs (budget_id, user_id, action) VALUES ($new_budget_id, $user_id, '$log_action')");
            
            $message = "Budget de $year criado com sucesso!";
            $selected_year = $year;
        } else {
            $error = "Erro ao criar budget. Já existe um budget para este ano?";
        }
    }

    // 2. ATUALIZAR PROJEÇÃO
    elseif ($action === 'update_projection') {
        $item_id = intval($_POST['item_id']);
        $amount = floatval($_POST['amount']);
        $budget_id_post = intval($_POST['budget_id']);
        
        $stmt = $conn->prepare("UPDATE budget_items SET monthly_projection = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("dii", $amount, $user_id, $item_id);
        
        if ($stmt->execute()) {
            // Log simplificado (opcional)
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit;
        }
    }

    // 3. SINCRONIZAR CATEGORIAS
    elseif ($action === 'sync_categories') {
        $budget_id_sync = intval($_POST['budget_id']);
        
        $stmt_sync = $conn->prepare("
            INSERT INTO budget_items (budget_id, category_id)
            SELECT ?, id FROM categories 
            WHERE type = 'saida' 
            AND id NOT IN (SELECT category_id FROM budget_items WHERE budget_id = ?)
        ");
        $stmt_sync->bind_param("ii", $budget_id_sync, $budget_id_sync);
        
        if ($stmt_sync->execute()) {
            $affected = $stmt_sync->affected_rows;
            $message = "$affected novas categorias sincronizadas.";
        } else {
            $error = "Erro ao sincronizar categorias.";
        }
    }

    // 4. ATUALIZAR NOTA/DESCRIÇÃO
    elseif ($action === 'update_note') {
        $item_id = intval($_POST['item_id']);
        $note = trim($_POST['note']);
        
        $stmt = $conn->prepare("UPDATE budget_items SET notes = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $note, $user_id, $item_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit;
        }
    }

    // 5. REMOVER ITEM
    elseif ($action === 'delete_item') {
        $item_id = intval($_POST['item_id']);
        // Verificar se pertence ao budget correto antes de deletar (segurança adicional seria ideal)
        
        $stmt = $conn->prepare("DELETE FROM budget_items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        
        if ($stmt->execute()) {
            $message = "Item removido com sucesso.";
        } else {
            $error = "Erro ao remover item.";
        }
    }
}

// --- BUSCAR DADOS DO BUDGET ---

// 1. Buscar lista de budgets existentes
$budgets_list = [];
$stmt_list = $conn->prepare("SELECT year, status FROM budgets WHERE church_id = ? ORDER BY year DESC");
$stmt_list->bind_param("i", $church_id);
$stmt_list->execute();
$res_list = $stmt_list->get_result();
while ($row = $res_list->fetch_assoc()) $budgets_list[] = $row;
$stmt_list->close();

// 2. Buscar o Budget Selecionado
$stmt_budget = $conn->prepare("SELECT * FROM budgets WHERE church_id = ? AND year = ?");
$stmt_budget->bind_param("ii", $church_id, $selected_year);
$stmt_budget->execute();
$res_budget = $stmt_budget->get_result();

if ($res_budget->num_rows > 0) {
    $budget_data = $res_budget->fetch_assoc();
    $budget_id = $budget_data['id'];

    // 3. Buscar Itens do Budget + Gasto Real (Com Filtro de Data)
    $start_month = isset($_GET['start_month']) ? intval($_GET['start_month']) : 1;
    $end_month = isset($_GET['end_month']) ? intval($_GET['end_month']) : 12;

    $where_expenses = "AND YEAR(e.transaction_date) = ? AND MONTH(e.transaction_date) BETWEEN ? AND ?";
    $params = [$church_id, $selected_year, $start_month, $end_month, $budget_id];
    $types = "iiiii";

    $query_items = "
        SELECT 
            bi.id as item_id,
            bi.monthly_projection,
            bi.notes,
            c.name as category_name,
            c.id as category_id,
            COALESCE(SUM(e.amount), 0) as total_spent_year
        FROM budget_items bi
        JOIN categories c ON bi.category_id = c.id
        LEFT JOIN expenses e ON e.category_id = c.id 
            AND e.church_id = ? 
            $where_expenses
        WHERE bi.budget_id = ?
        GROUP BY bi.id, c.name, c.id
        ORDER BY c.name ASC
    ";
    
    $stmt_items = $conn->prepare($query_items);
    $stmt_items->bind_param($types, ...$params);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();
    
    $budget_items = [];
    $total_projected_period = 0; // Totais do Período Selecionado
    $total_spent_period = 0;
    $chart_data = []; 
    
    // Calcular quantos meses estão sendo filtrados
    $num_months = count(range($start_month, $end_month));

    while ($row = $res_items->fetch_assoc()) {
        $row['annual_projection'] = $row['monthly_projection'] * 12; // Mantém referência anual para tabela
        $period_projection = $row['monthly_projection'] * $num_months; // Projeção do período filtrado
        
        // Percentual baseado no período (Mais preciso para análise mensal)
        if ($period_projection > 0) {
            $row['usage_percent'] = ($row['total_spent_year'] / $period_projection) * 100;
        } else {
            // Se não tem projeção mas tem gasto, considera 100% (crítico/alerta)
            $row['usage_percent'] = ($row['total_spent_year'] > 0) ? 100 : 0;
        }
        
        $total_projected_period += $period_projection;
        $total_spent_period += $row['total_spent_year'];
        
        $budget_items[] = $row;

        if ($row['total_spent_year'] > 0) {
            $chart_data[] = [
                'name' => $row['category_name'],
                'value' => (float)$row['total_spent_year']
            ];
        }
    }
    
    // Totais dos Cards baseados no Período
    $total_balance = $total_projected_period - $total_spent_period;
    
    if ($total_projected_period > 0) {
        $total_usage_percent = ($total_spent_period / $total_projected_period) * 100;
    } else {
        $total_usage_percent = ($total_spent_period > 0) ? 100 : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Budget <?php echo $selected_year; ?></title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
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
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
        .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
        .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
        .card { transition: all 0.2s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
        
        @media screen and (max-width: 768px) {
            .table-responsive { display: block; width: 100%; overflow-x: auto; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    
    <aside id="sidebar" class="w-64 h-full bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed md:relative -translate-x-full md:translate-x-0 z-40 transition-transform duration-300">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <span class="text-2xl font-['Pacifico'] text-primary">Life Church</span>
            <button id="close-sidebar" class="md:hidden text-gray-500"><i class="ri-close-line ri-lg"></i></button>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <div class="px-4 mb-6">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
                <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
                <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
                <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
                <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
                <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
                <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
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
        <header class="bg-white border-b border-gray-200 shadow-sm z-10">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <button id="open-sidebar" class="md:hidden mr-4 text-gray-600"><i class="ri-menu-line ri-xl"></i></button>
                    <h1 class="text-lg font-medium text-gray-800">Orçamento Anual</h1>
                </div>
                <div class="relative">
                     <button class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors"><span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span><div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div></button>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-gray-50">
            
            <?php if ($message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><p><?php echo htmlspecialchars($message); ?></p></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><p><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>

            <!-- Seleção de Ano e Filtros -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 mb-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                
                <!-- Seleção de Ano -->
                <div class="flex items-center space-x-3 bg-gray-50 px-3 py-2 rounded-md border border-gray-200">
                    <div class="text-blue-600 bg-blue-100 p-1.5 rounded-md">
                        <i class="ri-calendar-line"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Ano do Orçamento</span>
                        <select onchange="window.location.href='budget.php?year='+this.value" class="bg-transparent border-none p-0 text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer">
                            <?php 
                            $has_current = false;
                            foreach($budgets_list as $b) {
                                $selected = ($b['year'] == $selected_year) ? 'selected' : '';
                                echo "<option value='{$b['year']}' $selected>{$b['year']} ({$b['status']})</option>";
                                if($b['year'] == $current_year) $has_current = true;
                            }
                            if(!$has_current && $selected_year == $current_year) {
                                echo "<option value='$current_year' selected>$current_year (Novo)</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <?php if ($budget_data): ?>
                <!-- Filtro de Período -->
                <div class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-md border border-gray-200">
                    <div class="text-purple-600 bg-purple-100 p-1.5 rounded-md">
                        <i class="ri-filter-3-line"></i>
                    </div>
                    <div class="flex flex-col">
                         <span class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Período de Execução</span>
                         <div class="flex items-center space-x-2">
                            <div class="flex items-center bg-white border border-gray-200 rounded px-2 py-0.5">
                                <span class="text-xs text-gray-400 mr-1">De:</span>
                                <select id="start_month" onchange="updateFilter()" class="border-none p-0 text-xs font-medium text-gray-700 focus:ring-0 bg-transparent cursor-pointer">
                                    <?php 
                                    $months = [1=>'Jan', 2=>'Fev', 3=>'Mar', 4=>'Abr', 5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Set', 10=>'Out', 11=>'Nov', 12=>'Dez'];
                                    foreach($months as $num => $name) {
                                        $sel = ($start_month == $num) ? 'selected' : '';
                                        echo "<option value='$num' $sel>$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <span class="text-gray-400 text-xs">-</span>
                            <div class="flex items-center bg-white border border-gray-200 rounded px-2 py-0.5">
                                <span class="text-xs text-gray-400 mr-1">Até:</span>
                                <select id="end_month" onchange="updateFilter()" class="border-none p-0 text-xs font-medium text-gray-700 focus:ring-0 bg-transparent cursor-pointer">
                                    <?php 
                                    foreach($months as $num => $name) {
                                        $sel = ($end_month == $num) ? 'selected' : '';
                                        echo "<option value='$num' $sel>$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ações -->
                <div class="flex space-x-2">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="sync_categories">
                        <input type="hidden" name="budget_id" value="<?php echo $budget_id; ?>">
                        <button type="submit" class="group bg-white border border-gray-200 text-gray-600 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 px-3 py-2 rounded-md shadow-sm text-sm font-medium flex items-center transition-all">
                            <i class="ri-refresh-line mr-2 group-hover:rotate-180 transition-transform duration-500"></i> Atualizar
                        </button>
                    </form>
                    <button onclick="window.print()" class="group bg-white border border-gray-200 text-gray-600 hover:text-gray-900 hover:bg-gray-50 px-3 py-2 rounded-md shadow-sm text-sm font-medium flex items-center transition-all">
                        <i class="ri-printer-line mr-2"></i> Exportar
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$budget_data): ?>
                <!-- Estado Vazio / Criar Budget -->
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <div class="w-16 h-16 bg-blue-100 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-calculator-line ri-2x"></i>
                    </div>
                    <h2 class="text-xl font-medium text-gray-900 mb-2">Nenhum budget encontrado para <?php echo $selected_year; ?></h2>
                    <p class="text-gray-500 mb-6">Comece o planejamento financeiro para este ano.</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_budget">
                        <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                        <button type="submit" class="bg-primary text-white hover:bg-blue-700 px-6 py-3 rounded-md shadow-sm font-medium transition-colors">
                            <i class="ri-add-line mr-2"></i> Criar Budget <?php echo $selected_year; ?>
                        </button>
                    </form>
                </div>
            <?php else: ?>

                <!-- Cards de Resumo -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm p-6 card border-l-4 border-blue-500">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Total Projetado</h3>
                        <p class="text-2xl font-bold text-gray-800 tracking-tight">
                            <?php echo number_format($total_projected_period, 2, ',', '.'); ?> <span class="text-sm text-gray-400 font-normal">MZN</span>
                        </p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 card border-l-4 border-red-500">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Total Executado</h3>
                        <p class="text-2xl font-bold text-red-600"><?php echo number_format($total_spent_period, 2, ',', '.'); ?> MZN</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 card border-l-4 <?php echo $total_balance >= 0 ? 'border-green-500' : 'border-red-500'; ?>">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Saldo Restante</h3>
                        <p class="text-2xl font-bold <?php echo $total_balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($total_balance, 2, ',', '.'); ?> MZN
                        </p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 card border-l-4 border-purple-500">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">% Execução</h3>
                        <div class="flex items-center">
                            <span class="text-2xl font-bold text-gray-800 mr-2"><?php echo number_format($total_usage_percent, 1, ',', '.'); ?>%</span>
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-purple-500" style="width: <?php echo min($total_usage_percent, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela Principal -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h2 class="text-lg font-medium text-gray-800">Detalhamento por Categoria</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3">Categoria</th>
                                    <th class="px-6 py-3 text-right">Proj. Mensal</th>
                                    <th class="px-6 py-3 text-right">Proj. Anual</th>
                                    <th class="px-6 py-3 text-right">Execução</th>
                                    <th class="px-6 py-3 text-center">Uso %</th>
                                    <th class="px-6 py-3 text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($budget_items as $item): 
                                    $percent = $item['usage_percent'];
                                ?>
                                <tr class="hover:bg-gray-50 group-row">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end group">
                                            <span class="text-gray-500 mr-2">MZN</span>
                                            <input type="number" 
                                                   value="<?php echo $item['monthly_projection']; ?>" 
                                                   step="0.01" 
                                                   class="w-28 text-right border border-gray-300 bg-white focus:ring-2 focus:ring-primary focus:border-primary rounded px-2 py-1 projection-input transition-all shadow-sm"
                                                   data-id="<?php echo $item['item_id']; ?>"
                                                   data-budget="<?php echo $budget_id; ?>"
                                            >
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-blue-600">
                                        <?php echo number_format($item['annual_projection'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-700">
                                        <?php echo number_format($item['total_spent_year'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center">
                                            <span class="text-xs font-medium w-8 text-right mr-2"><?php echo number_format($percent, 0); ?>%</span>
                                            <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full <?php echo $percent > 100 ? 'bg-red-500' : ($percent > 70 ? 'bg-yellow-500' : 'bg-green-500'); ?>" style="width: <?php echo min($percent, 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="openDeleteModal(<?php echo $item['item_id']; ?>)" class="text-red-400 hover:text-red-600 p-1 rounded-full hover:bg-red-50 transition-colors" title="Remover Item">
                                            <i class="ri-delete-bin-line ri-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Gráfico de Execução -->
                <div class="bg-white rounded-lg shadow-sm p-6 mt-8">
                    <h3 class="text-lg font-medium text-gray-800 mb-4">Distribuição dos Gastos (Execução)</h3>
                    <div id="executionChart" style="width: 100%; height: 400px;"></div>
                </div>

            <?php endif; ?>

            <!-- Modal Customizado de Confirmação -->
            <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
                <div class="absolute inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeDeleteModal()"></div>
                <div class="bg-white rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6 z-10 scale-95 opacity-0 duration-200" id="deleteModalContent">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-12 h-12 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-4">
                            <i class="ri-alert-line ri-2x"></i>
                        </div>
                        <h3 class="text-xl font-medium text-gray-900 mb-2">Confirmar Exclusão</h3>
                        <p class="text-gray-500 mb-6">Tem certeza que deseja remover esta categoria do orçamento? Esta ação não pode ser desfeita.</p>
                        
                        <div class="flex space-x-3 w-full justify-center">
                            <button onclick="closeDeleteModal()" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 font-medium">Cancelar</button>
                            <form method="POST" id="deleteForm">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" id="deleteItemId">
                                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium shadow-sm">Sim, Remover</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Scripts para interatividade -->
    <script>
        // Funções Globais
        function updateFilter() {
            const start = document.getElementById('start_month').value;
            const end = document.getElementById('end_month').value;
            
            if (parseInt(start) > parseInt(end)) {
                alert('O mês inicial não pode ser maior que o mês final.');
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('start_month', start);
            url.searchParams.set('end_month', end);
            window.location.href = url.toString();
        }

        // Modal Logic
        const deleteModal = document.getElementById('deleteModal');
        const deleteModalContent = document.getElementById('deleteModalContent');
        const deleteItemIdInput = document.getElementById('deleteItemId');

        function openDeleteModal(itemId) {
            deleteItemIdInput.value = itemId;
            deleteModal.classList.remove('hidden');
            // Animation
            setTimeout(() => {
                deleteModalContent.classList.remove('scale-95', 'opacity-0');
                deleteModalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeDeleteModal() {
            deleteModalContent.classList.remove('scale-100', 'opacity-100');
            deleteModalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                deleteModal.classList.add('hidden');
            }, 200);
        }

        // Chart Logic
        <?php if (!empty($chart_data)): ?>
        const chartDom = document.getElementById('executionChart');
        const myChart = echarts.init(chartDom);
        const option = {
            tooltip: {
                trigger: 'item',
                formatter: '{b}: {c} ({d}%)',
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                borderColor: '#ccc',
                borderWidth: 1,
                textStyle: {
                    color: '#333'
                }
            },
            legend: {
                orient: 'vertical',
                left: 'left',
                type: 'scroll'
            },
            series: [
                {
                    name: 'Execução',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    avoidLabelOverlap: true,
                    itemStyle: {
                        borderRadius: 10,
                        borderColor: '#fff',
                        borderWidth: 2
                    },
                    label: {
                        show: true,
                        position: 'outside',
                        formatter: '{b}\n{d}%',
                        backgroundColor: '#fff',
                        borderColor: '#e5e7eb',
                        borderWidth: 1,
                        borderRadius: 4,
                        padding: [6, 10],
                        color: '#4b5563',
                        lineHeight: 16,
                        shadowColor: 'rgba(0, 0, 0, 0.05)',
                        shadowBlur: 5
                    },
                    emphasis: {
                        label: {
                            show: true,
                            fontSize: 14,
                            fontWeight: 'bold'
                        },
                        itemStyle: {
                            shadowBlur: 10,
                            shadowOffsetX: 0,
                            shadowColor: 'rgba(0, 0, 0, 0.5)'
                        }
                    },
                    labelLine: {
                        show: true,
                        length: 20,
                        length2: 20,
                        smooth: true
                    },
                    data: <?php echo json_encode($chart_data); ?>
                }
            ]
        };
        myChart.setOption(option);
        
        // Responsividade do Gráfico
        window.addEventListener('resize', function() {
            myChart.resize();
        });
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', () => {
             // Toggle Sidebar Mobile
            const sidebar = document.getElementById('sidebar');
            const openBtn = document.getElementById('open-sidebar');
            const closeBtn = document.getElementById('close-sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            const toggleSidebar = () => {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            };

            if(openBtn) openBtn.addEventListener('click', toggleSidebar);
            if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
            if(overlay) overlay.addEventListener('click', toggleSidebar);

            // Atualização de Projeção com AJAX
            const inputs = document.querySelectorAll('.projection-input');
            inputs.forEach(input => {
                input.addEventListener('change', async function() {
                    const amount = this.value;
                    const itemId = this.dataset.id;
                    const budgetId = this.dataset.budget;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_projection');
                        formData.append('item_id', itemId);
                        formData.append('amount', amount);
                        formData.append('budget_id', budgetId);

                        const response = await fetch('budget.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            // Flash color text green to indicate save
                            this.classList.add('text-green-600');
                            setTimeout(() => {
                                this.classList.remove('text-green-600');
                                window.location.reload(); // Reload to recalculate totals
                            }, 500);
                        } else {
                            alert('Erro ao salvar: ' + result.error);
                        }
                    } catch (e) {
                        console.error('Erro:', e);
                        alert('Erro de conexão ao salvar.');
                    }
                });
            });

        });
    </script>
</body>
</html>
