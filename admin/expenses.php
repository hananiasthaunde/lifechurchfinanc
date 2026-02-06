<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

// Inclui arquivos de configuração e funções
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Obtém dados da sessão
$user_id = $_SESSION['user_id'];
$church_id = $_SESSION['church_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// --- CONTROLE DE ACESSO ---
if ($user_role === 'lider') {
    header('Location: celulas.php');
    exit;
}
$message = '';
$error = '';

$edit_mode = false;
$expense_to_edit = null;
$categories = [];
$expenses = [];
$current_balance = 0;

try {
    $conn = connect_db();

    // --- BUSCAR SALDO ATUAL ---
    $stmt_bal = $conn->prepare("SELECT balance FROM churches WHERE id = ?");
    if (!$stmt_bal) throw new Exception("Erro ao buscar saldo: " . $conn->error);
    $stmt_bal->bind_param("i", $church_id);
    $stmt_bal->execute();
    $result_bal = $stmt_bal->get_result();
    if ($result_bal->num_rows > 0) $current_balance = $result_bal->fetch_assoc()['balance'];
    $stmt_bal->close();

    // --- AÇÃO PARA APAGAR ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $expense_id_to_delete = (int)$_GET['id'];
        $conn->begin_transaction();
        
        $stmt_get_amount = $conn->prepare("SELECT amount FROM expenses WHERE id = ? AND church_id = ?");
        if (!$stmt_get_amount) throw new Exception($conn->error);
        $stmt_get_amount->bind_param("ii", $expense_id_to_delete, $church_id);
        $stmt_get_amount->execute();
        $result_amount = $stmt_get_amount->get_result()->fetch_assoc();
        
        if ($result_amount) {
            $amount_to_add_back = $result_amount['amount'];
            $stmt_delete = $conn->prepare("DELETE FROM expenses WHERE id = ?");
            if (!$stmt_delete) throw new Exception($conn->error);
            $stmt_delete->bind_param("i", $expense_id_to_delete);
            $stmt_delete->execute();
            $stmt_update_balance = $conn->prepare("UPDATE churches SET balance = balance + ? WHERE id = ?");
            if (!$stmt_update_balance) throw new Exception($conn->error);
            $stmt_update_balance->bind_param("di", $amount_to_add_back, $church_id);
            $stmt_update_balance->execute();
            $conn->commit();
            header("Location: expenses.php?delete_success=1");
            exit;
        } else {
            $conn->rollback();
            throw new Exception("Saída não encontrada.");
        }
    }

    // --- AÇÃO PARA CARREGAR DADOS PARA EDIÇÃO ---
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_mode = true;
        $expense_id_to_edit = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ? AND church_id = ?");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param("ii", $expense_id_to_edit, $church_id);
        $stmt->execute();
        $expense_to_edit = $stmt->get_result()->fetch_assoc();
        if (!$expense_to_edit) {
            $edit_mode = false;
            $error = "Saída não encontrada para edição.";
        }
    }

    // --- AÇÃO PARA PROCESSAR FORMULÁRIO (CRIAR/ATUALIZAR) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn->begin_transaction();
        
        $expense_id_to_update = isset($_POST['expense_id']) && !empty($_POST['expense_id']) ? (int)$_POST['expense_id'] : null;

        $transaction_date = $_POST['transaction_date'] ?? null;
        $category_id = (int)($_POST['category_id'] ?? 0);
        $paid_to = $_POST['paid_to'] ?? '';
        $received_by = $_POST['received_by'] ?? '';
        $comments = $_POST['comments'] ?? '';
        $item_descriptions = $_POST['item_description'] ?? [];
        $item_quantities = $_POST['item_quantity'] ?? [];
        $item_unit_prices = $_POST['item_unit_price'] ?? [];

        $total_amount = 0;
        $items_for_json = [];
        if (!empty($item_descriptions)) {
            for ($i = 0; $i < count($item_descriptions); $i++) {
                $qty = (float)($item_quantities[$i] ?? 0);
                $price = (float)($item_unit_prices[$i] ?? 0);
                if (!empty($item_descriptions[$i]) && $qty > 0 && $price > 0) {
                    $total_item = $qty * $price;
                    $total_amount += $total_item;
                    $items_for_json[] = ['description' => $item_descriptions[$i], 'quantity' => $qty, 'unit_price' => $price, 'total' => $total_item];
                }
            }
        }
        $description_json = json_encode($items_for_json);

        if (!$transaction_date || $total_amount <= 0 || $category_id <= 0 || empty($paid_to) || empty($received_by)) {
            throw new Exception("Por favor, preencha todos os campos obrigatórios e adicione pelo menos um item válido.");
        }

        if ($expense_id_to_update) {
            $stmt_old = $conn->prepare("SELECT amount FROM expenses WHERE id = ?");
            if(!$stmt_old) throw new Exception($conn->error);
            $stmt_old->bind_param("i", $expense_id_to_update);
            $stmt_old->execute();
            $old_amount = $stmt_old->get_result()->fetch_assoc()['amount'] ?? 0;
            
            if ($total_amount > ($current_balance + $old_amount)) {
                throw new Exception("Saldo insuficiente para atualizar esta saída. O novo valor excede o saldo disponível.");
            }
            
            $stmt_update = $conn->prepare("UPDATE expenses SET transaction_date=?, amount=?, category_id=?, description=?, paid_to=?, received_by=?, comments=? WHERE id=?");
            if (!$stmt_update) throw new Exception($conn->error);
            $stmt_update->bind_param("sdissssi", $transaction_date, $total_amount, $category_id, $description_json, $paid_to, $received_by, $comments, $expense_id_to_update);
            $stmt_update->execute();
            
            $balance_change = $old_amount - $total_amount;
            $stmt_balance = $conn->prepare("UPDATE churches SET balance = balance + ? WHERE id = ?");
            if (!$stmt_balance) throw new Exception($conn->error);
            $stmt_balance->bind_param("di", $balance_change, $church_id);
            $stmt_balance->execute();
            
            $conn->commit();
            header("Location: expenses.php?update_success=1");
            exit;
        } else {
            if ($total_amount > $current_balance) {
                throw new Exception("Saldo insuficiente. A saída de " . number_format($total_amount, 2, ',', '.') . " MZN excede o saldo de " . number_format($current_balance, 2, ',', '.') . " MZN.");
            }
            $stmt_insert = $conn->prepare("INSERT INTO expenses (church_id, user_id, transaction_date, amount, category_id, description, paid_to, received_by, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_insert) throw new Exception($conn->error);
            $stmt_insert->bind_param("iisdissss", $church_id, $user_id, $transaction_date, $total_amount, $category_id, $description_json, $paid_to, $received_by, $comments);
            $stmt_insert->execute();
            
            $stmt_balance = $conn->prepare("UPDATE churches SET balance = balance - ? WHERE id = ?");
            if (!$stmt_balance) throw new Exception($conn->error);
            $stmt_balance->bind_param("di", $total_amount, $church_id);
            $stmt_balance->execute();
            
            $conn->commit();
            $message = 'Saída registada com sucesso!';
        }
    }

    if(isset($_GET['update_success'])) $message = "Saída atualizada com sucesso!";
    if(isset($_GET['delete_success'])) $message = "Saída apagada com sucesso!";

    // --- BUSCAR DADOS PARA EXIBIÇÃO ---
    $stmt_cat = $conn->prepare("SELECT id, name FROM categories WHERE type = 'saida' ORDER BY name");
    if (!$stmt_cat) throw new Exception("Erro ao buscar categorias: " . $conn->error);
    $stmt_cat->execute();
    $result_cat = $stmt_cat->get_result();
    while($row = $result_cat->fetch_assoc()) $categories[] = $row;
    $stmt_cat->close();

    $stmt_exp = $conn->prepare("SELECT e.*, c.name as category_name, u.name as user_name FROM expenses e JOIN categories c ON e.category_id = c.id LEFT JOIN users u ON e.user_id = u.id WHERE e.church_id = ? ORDER BY e.transaction_date DESC, e.id DESC");
    if (!$stmt_exp) throw new Exception("Erro ao buscar saídas: " . $conn->error);
    $stmt_exp->bind_param("i", $church_id);
    $stmt_exp->execute();
    $result_exp = $stmt_exp->get_result();
    while($row = $result_exp->fetch_assoc()) $expenses[] = $row;
    $stmt_exp->close();
    
} catch (Exception $e) {
    if (isset($conn) && $conn->ping() && $conn->in_transaction) $conn->rollback();
    $error = 'Erro: ' . $e->getMessage();
} finally {
    if (isset($conn) && $conn->ping()) $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Saídas</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = { theme: { extend: { colors: { primary: "#1976D2", secondary: "#BBDEFB" }, borderRadius: { button: "8px" }, } }, };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <style>
      body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
      .sidebar-item { transition: all 0.2s ease; }
      .sidebar-item.active { background-color: #eef2ff; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
      .form-input { margin-top: 0.25rem; display: block; width: 100%; padding: 0.5rem 0.75rem; background-color: white; border: 1px solid #D1D5DB; border-radius: 0.5rem; transition: all 0.2s ease-in-out; }
      .form-input:focus { outline: none; box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2); border-color: #1976D2; }
      .modal, .dropdown-menu { transition: opacity 0.3s ease; }
      .modal-content { transition: transform 0.3s ease, opacity 0.3s ease; }
      #sidebar { transition: transform 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="lg:flex">
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>

        <aside id="sidebar" class="w-64 h-screen bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-30 transform -translate-x-full lg:translate-x-0">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <span class="text-2xl font-['Pacifico'] text-primary">Life Church</span>
                <button id="close-sidebar-btn" class="lg:hidden text-gray-500 hover:text-gray-800"><i class="ri-close-line ri-xl"></i></button>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 mb-6">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                    <?php $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
                    <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
                    <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
                    <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
                    <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
                    <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
                    <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
                    <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
                    <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
                </div>
                <div class="px-4"><p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
                    <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                    <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
                </div>
            </nav>
            <div class="p-4 border-t border-gray-100"><div class="flex items-center p-2"><div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary font-bold text-lg mr-3"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div><div><p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></p><p class="text-xs text-gray-500"><?php echo ucfirst(htmlspecialchars($user_role)); ?></p></div></div></div>
        </aside>

        <div class="flex-1 flex flex-col w-full">
            <header class="bg-white border-b border-gray-200 shadow-sm z-10 sticky top-0">
                <div class="flex items-center justify-between h-16 px-6">
                    <div class="flex items-center">
                        <button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary"><i class="ri-menu-line ri-lg"></i></button>
                        <h1 class="text-lg font-medium text-gray-800">Registo de Saídas</h1>
                    </div>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors"><span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span><div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div></button>
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 dropdown-menu origin-top-right"><a href="settings.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a><a href="logout.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i>Sair</a></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 overflow-y-auto">
                <div class="bg-blue-50 border-l-4 border-primary text-primary-800 p-4 mb-6 rounded-md" role="alert">
                    <p>Saldo Atual da Igreja: <strong class="font-semibold text-lg"><?php echo number_format($current_balance, 2, ',', '.'); ?> MZN</strong></p>
                </div>
                
                <?php if ($message): ?> <div id="alert-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($message); ?></span><button onclick="document.getElementById('alert-message').style.display='none'"><i class="ri-close-line"></i></button></div> <?php endif; ?>
                <?php if ($error): ?> <div id="alert-error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($error); ?></span><button onclick="document.getElementById('alert-error').style.display='none'"><i class="ri-close-line"></i></button></div> <?php endif; ?>

                <form id="expense-form" method="POST" action="expenses.php" class="bg-white rounded-lg shadow p-6 sm:p-8 space-y-6">
                    <input type="hidden" name="expense_id" value="<?php echo $edit_mode ? $expense_to_edit['id'] : ''; ?>">
                    <div class="flex justify-between items-center border-b border-gray-200 pb-5">
                        <h2 class="text-xl font-semibold leading-7 text-gray-900"><?php echo $edit_mode ? 'Editar Saída' : 'Registar Nova Saída'; ?></h2>
                        <?php if ($edit_mode): ?>
                            <a href="expenses.php" class="text-sm font-medium text-primary hover:text-blue-700 flex items-center gap-1"><i class="ri-add-circle-line"></i>Registar Nova Saída</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="transaction_date" class="block text-sm font-medium text-gray-700">Data da Transação</label>
                            <input type="date" name="transaction_date" value="<?php echo $edit_mode ? htmlspecialchars(date('Y-m-d', strtotime($expense_to_edit['transaction_date']))) : date('Y-m-d'); ?>" required class="form-input">
                        </div>
                         <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
                            <select name="category_id" required class="form-input">
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($edit_mode && $expense_to_edit['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="paid_to" class="block text-sm font-medium text-gray-700">Compra feita no(a):</label>
                            <input type="text" name="paid_to" value="<?php echo $edit_mode ? htmlspecialchars($expense_to_edit['paid_to']) : ''; ?>" class="form-input" placeholder="Nome da loja ou estabelecimento" required>
                        </div>
                        <div>
                            <label for="received_by" class="block text-sm font-medium text-gray-700">Irmão responsável:</label>
                            <input type="text" name="received_by" id="received_by" value="<?php echo $edit_mode ? htmlspecialchars($expense_to_edit['received_by']) : ''; ?>" class="form-input" placeholder="Responsável pela compra" required>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-800 border-t pt-6">Detalhes do Pagamento</h3>
                         <div id="expense-items-container" class="mt-4 space-y-3"></div>
                         <button type="button" id="add-item-btn" class="mt-3 text-sm font-medium text-primary hover:text-blue-700 flex items-center gap-1"><i class="ri-add-line"></i>Adicionar Item</button>
                    </div>
                    
                    <div class="pt-6 border-t">
                        <label for="comments" class="block text-sm font-medium text-gray-700">Comentários</label>
                        <textarea name="comments" rows="3" class="form-input" placeholder="Adicione uma observação..."><?php echo $edit_mode ? htmlspecialchars($expense_to_edit['comments']) : ''; ?></textarea>
                    </div>

                     <div class="flex justify-end pt-4 border-t">
                         <div class="w-full md:w-1/3">
                             <label for="total_amount" class="block text-sm font-bold text-gray-500">Total Pago (MZN)</label>
                             <input type="text" id="total_amount" name="total_amount_display" readonly class="form-input bg-gray-100 font-bold text-red-600 text-lg text-right">
                         </div>
                     </div>

                    <div class="flex justify-end pt-5 border-t border-gray-200">
                        <button type="submit" class="inline-flex justify-center py-3 px-8 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <?php echo $edit_mode ? 'Atualizar Saída' : 'Registar Saída'; ?>
                        </button>
                    </div>
                </form>

                <div class="bg-white rounded-lg shadow p-4 sm:p-6 mt-8">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Todas as Saídas Registadas</h2>
                    <div class="overflow-y-auto max-h-[420px]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Data</th>
                                        <th class="px-4 py-3">Descrição</th>
                                        <th class="px-4 py-3 hidden md:table-cell">Categoria</th>
                                        <th class="px-4 py-3">Valor</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Nenhuma saída registada ainda.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr class="bg-white border-b hover:bg-gray-50">
                                                <td class="px-4 py-4 font-medium text-gray-900 whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($expense['transaction_date'])); ?></td>
                                                <td class="px-4 py-4 truncate max-w-xs"><?php 
                                                    $items = json_decode($expense['description'], true);
                                                    if (is_array($items) && !empty($items)) {
                                                        echo htmlspecialchars($items[0]['description']);
                                                        if(count($items) > 1) echo '...';
                                                    } else {
                                                        echo htmlspecialchars($expense['description']);
                                                    }
                                                ?></td>
                                                <td class="px-4 py-4 hidden md:table-cell"><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                                <td class="px-4 py-4 font-medium text-red-600"><?php echo number_format($expense['amount'], 2, ',', '.'); ?></td>
                                                <td class="px-4 py-4 text-right">
                                                    <div class="flex items-center justify-end space-x-2">
                                                        <a href="expenses.php?action=edit&id=<?php echo $expense['id']; ?>" class="text-gray-400 hover:text-primary p-1" title="Editar">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <button class="delete-expense-btn text-gray-400 hover:text-red-600 p-1" title="Apagar" data-id="<?php echo $expense['id']; ?>">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                        <button class="view-expense-details-btn text-gray-400 hover:text-primary p-1" title="Ver Detalhes" data-details='<?php echo htmlspecialchars(json_encode($expense), ENT_QUOTES, 'UTF-8'); ?>'>
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal para Detalhes da Saída -->
    <div id="expenseModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-40 flex items-center justify-center hidden opacity-0 p-4">
      <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-lg transform scale-95 opacity-0">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold">Detalhes da Saída</h3>
            <button id="closeExpenseModal" class="text-gray-500 hover:text-gray-800"><i class="ri-close-line ri-xl"></i></button>
        </div>
        <div id="expenseModalBody" class="p-6 space-y-3"></div>
        <div class="flex justify-end items-center p-4 border-t bg-gray-50 rounded-b-lg">
             <a id="exportPdfLink" href="#" target="_blank" class="inline-flex items-center bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700">
                 <i class="ri-printer-line mr-2"></i>Exportar PDF
             </a>
        </div>
      </div>
    </div>

    <!-- Modal para Confirmação de Apagar -->
    <div id="deleteConfirmationModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden opacity-0 p-4">
      <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">Confirmar Exclusão</h3>
            <p class="mt-2 text-sm text-gray-600">Tem a certeza que deseja apagar esta saída? A ação é irreversível. Para confirmar, digite <strong class="text-red-600">lifechurch</strong> abaixo.</p>
            <input type="text" id="delete-confirmation-key" class="form-input mt-4" placeholder="Escreva a chave de confirmação">
            <p id="delete-error-msg" class="text-red-500 text-sm mt-1 hidden">Chave de confirmação incorreta.</p>
        </div>
        <div class="flex justify-end items-center p-4 border-t bg-gray-50 rounded-b-lg space-x-3">
            <button id="cancelDelete" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-button hover:bg-gray-300">Cancelar</button>
            <button id="confirmDelete" class="bg-red-600 text-white py-2 px-4 rounded-button hover:bg-red-700">Apagar Saída</button>
        </div>
      </div>
    </div>
    
    <!-- Modal Saldo Insuficiente -->
    <div id="insufficient-funds-modal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden opacity-0 p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0">
            <div class="p-6 text-center">
                <i class="ri-error-warning-line text-5xl text-orange-400 mx-auto"></i>
                <h3 class="mt-2 text-lg font-semibold text-gray-900">Saldo Insuficiente</h3>
                <p id="insufficient-funds-message" class="mt-2 text-sm text-gray-600"></p>
            </div>
            <div class="flex justify-center items-center p-4 border-t bg-gray-50 rounded-b-lg">
                <button id="close-funds-modal" class="bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700">Compreendido</button>
            </div>
        </div>
    </div>

    <script id="main-script">
    document.addEventListener("DOMContentLoaded", function () {
        const expenseToEditData = <?php echo json_encode($expense_to_edit); ?>;
        const currentBalance = parseFloat(<?php echo json_encode($current_balance); ?>) || 0;
        const expenseForm = document.getElementById('expense-form');
        
        const itemsContainer = document.getElementById('expense-items-container');
        const addItemBtn = document.getElementById('add-item-btn');
        
        function calculateTotalAmount() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.item-unit-price').value) || 0;
                const itemTotal = qty * price;
                row.querySelector('.item-total').textContent = itemTotal.toFixed(2);
                total += itemTotal;
            });
            document.getElementById('total_amount').value = total.toFixed(2).replace('.',',');
            return total;
        }

        function createItemRow(desc = '', price = '', qty = '') {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 item-row items-center';
            row.innerHTML = `
                <div class="col-span-12 sm:col-span-5"><input type="text" name="item_description[]" class="form-input text-sm" placeholder="Produto / Descrição" value="${desc}" required></div>
                <div class="col-span-5 sm:col-span-3"><input type="number" name="item_unit_price[]" class="form-input text-sm item-unit-price" placeholder="Preço" value="${price}" step="0.01" min="0" required></div>
                <div class="col-span-4 sm:col-span-2"><input type="number" name="item_quantity[]" class="form-input text-sm item-quantity" placeholder="Qtd" value="${qty}" step="any" min="0" required></div>
                <div class="col-span-2 sm:col-span-1 text-center font-medium text-sm"><span class="item-total">0.00</span></div>
                <div class="col-span-1 text-right"><button type="button" class="remove-item-btn text-red-500 hover:text-red-700"><i class="ri-delete-bin-line"></i></button></div>`;
            itemsContainer.appendChild(row);
            row.querySelectorAll('input').forEach(input => input.addEventListener('input', calculateTotalAmount));
        }

        if (addItemBtn) {
            addItemBtn.addEventListener('click', () => createItemRow());
            itemsContainer.addEventListener('click', (e) => {
                if (e.target.closest('.remove-item-btn')) {
                    e.target.closest('.item-row').remove();
                    calculateTotalAmount();
                }
            });
        }

        if (expenseToEditData && expenseToEditData.description) {
            try {
                const items = JSON.parse(expenseToEditData.description);
                if (Array.isArray(items)) items.forEach(item => createItemRow(item.description, item.unit_price, item.quantity));
            } catch (e) { console.error("Could not parse items JSON", e); createItemRow(); }
        } else { if(addItemBtn) createItemRow(); }
        calculateTotalAmount();
        
        // --- LÓGICA DO MODAL DE SALDO INSUFICIENTE ---
        const fundsModal = document.getElementById("insufficient-funds-modal");
        const closeFundsModalBtn = document.getElementById("close-funds-modal");
        
        const hideFundsModal = () => {
             fundsModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
             fundsModal.classList.add('opacity-0');
             setTimeout(() => fundsModal.classList.add('hidden'), 300);
        };
        if(closeFundsModalBtn) closeFundsModalBtn.addEventListener('click', hideFundsModal);
        
        if (expenseForm) {
            expenseForm.addEventListener('submit', function(event) {
                const totalExpense = calculateTotalAmount();
                let oldAmount = 0;
                const isEditing = document.querySelector('[name="expense_id"]').value !== '';
                if(isEditing && expenseToEditData) {
                    oldAmount = parseFloat(expenseToEditData.amount) || 0;
                }

                if (totalExpense > (currentBalance + oldAmount)) {
                    event.preventDefault(); // Impede a submissão do formulário
                    const fundsModalMessage = document.getElementById('insufficient-funds-message');
                    fundsModalMessage.innerHTML = `Não é possível registar uma saída de <strong>${totalExpense.toFixed(2).replace('.',',')} MZN</strong>. <br>O saldo disponível é de <strong>${currentBalance.toFixed(2).replace('.',',')} MZN</strong>.`;
                    
                    fundsModal.classList.remove('hidden');
                    setTimeout(() => { 
                        fundsModal.classList.remove('opacity-0');
                        fundsModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100');
                    }, 10);
                }
            });
        }
        
        const expenseModal = document.getElementById("expenseModal");
        const closeExpenseModalBtn = document.getElementById("closeExpenseModal");
        const viewExpenseButtons = document.querySelectorAll('.view-expense-details-btn');
        
        const hideExpenseModal = () => {
             expenseModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
             expenseModal.classList.add('opacity-0');
             setTimeout(() => expenseModal.classList.add('hidden'), 300);
        };
        
        viewExpenseButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const details = JSON.parse(button.dataset.details);
                const modalBody = document.getElementById("expenseModalBody");
                document.getElementById("exportPdfLink").href = `export_expense.php?id=${details.id}`;
                const transactionDate = new Date(details.transaction_date.replace(/-/g, '/')).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
                let itemsHTML = '<p>Não foi possível ler os detalhes dos itens.</p>';
                try {
                    const items = JSON.parse(details.description);
                    if (Array.isArray(items) && items.length > 0) {
                        itemsHTML = items.map(item => `<div class="grid grid-cols-3 gap-2 text-sm py-1 border-b"><span class="col-span-2">${item.description} (${item.quantity} x ${parseFloat(item.unit_price).toFixed(2)})</span><span class="text-right font-medium">${parseFloat(item.total).toFixed(2)}</span></div>`).join('');
                    } else { itemsHTML = `<p class="text-sm">${details.description}</p>`; }
                } catch(err) { itemsHTML = `<p class="text-sm">${details.description}</p>`; }
                modalBody.innerHTML = `<div class="space-y-1 text-sm"><p class="flex justify-between"><strong>Data:</strong> <span>${transactionDate}</span></p><p class="flex justify-between"><strong>Categoria:</strong> <span>${details.category_name}</span></p><p class="flex justify-between"><strong>Compra feita no(a):</strong> <span>${details.paid_to || 'N/A'}</span></p><p class="flex justify-between"><strong>Irmão responsável:</strong> <span>${details.received_by || 'N/A'}</span></p>${details.comments ? `<p class="flex justify-between"><strong>Comentários:</strong> <span class="text-right">${details.comments}</span></p>` : ''}</div><div class="mt-4 pt-2 border-t"><h4 class="font-semibold mb-2">Itens:</h4><div class="space-y-1">${itemsHTML}</div></div><div class="flex justify-between font-bold text-lg mt-4 pt-2 border-t"><span>Total Pago:</span><span class="text-red-600">${parseFloat(details.amount).toFixed(2).replace('.',',')} MZN</span></div>`;
                expenseModal.classList.remove('hidden');
                setTimeout(() => { expenseModal.classList.remove('opacity-0'); expenseModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'); }, 10);
            });
        });
        
        if (closeExpenseModalBtn) closeExpenseModalBtn.addEventListener('click', hideExpenseModal);
        if (expenseModal) expenseModal.addEventListener('click', (e) => { if(e.target === expenseModal) hideExpenseModal(); });
        
        const deleteModal = document.getElementById("deleteConfirmationModal");
        const cancelDeleteBtn = document.getElementById("cancelDelete");
        const confirmDeleteBtn = document.getElementById("confirmDelete");
        const deleteButtons = document.querySelectorAll(".delete-expense-btn");
        let expenseIdToDelete = null;

        deleteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                expenseIdToDelete = button.dataset.id;
                deleteModal.classList.remove('hidden');
                setTimeout(() => {
                    deleteModal.classList.remove('opacity-0');
                    deleteModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100');
                }, 10);
            });
        });

        const hideDeleteModal = () => {
            const errorMsg = document.getElementById('delete-error-msg');
            const inputKey = document.getElementById('delete-confirmation-key');
            deleteModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
            deleteModal.classList.add('opacity-0');
            setTimeout(() => {
                deleteModal.classList.add('hidden');
                if (errorMsg) errorMsg.classList.add('hidden');
                if (inputKey) inputKey.value = '';
            }, 300);
        };

        if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', hideDeleteModal);
        if (confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', () => {
            const inputKey = document.getElementById('delete-confirmation-key');
            const errorMsg = document.getElementById('delete-error-msg');
            if (inputKey.value === 'lifechurch') {
                window.location.href = `expenses.php?action=delete&id=${expenseIdToDelete}`;
            } else { if (errorMsg) errorMsg.classList.remove('hidden'); }
        });

        const userMenuButton = document.getElementById("user-menu-button");
        const userMenu = document.getElementById("user-menu");
        if(userMenuButton) {
            userMenuButton.addEventListener("click", (event) => { userMenu.classList.toggle("hidden"); event.stopPropagation(); });
            document.addEventListener("click", (event) => { if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) { userMenu.classList.add("hidden"); } });
        }
        
        const alertMessage = document.getElementById('alert-message');
        if (alertMessage) setTimeout(() => { alertMessage.style.display = 'none'; }, 3000);
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
