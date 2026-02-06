<?php
session_start();

// Verificar se o usuário está logado
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
$report_to_edit = null;

$conn = connect_db();

// --- LÓGICA DE AÇÕES (APAGAR, EDITAR, CRIAR/ATUALIZAR) ---
try {
    // --- AÇÃO PARA APAGAR UM RELATÓRIO ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $report_id_to_delete = (int)$_GET['id'];
        
        $conn->begin_transaction();
        
        $stmt_get_offering = $conn->prepare("SELECT total_offering FROM service_reports WHERE id = ? AND church_id = ?");
        $stmt_get_offering->bind_param("ii", $report_id_to_delete, $church_id);
        $stmt_get_offering->execute();
        $result_offering = $stmt_get_offering->get_result()->fetch_assoc();
        
        if ($result_offering) {
            $offering_to_remove = $result_offering['total_offering'];

            $stmt_delete_tithes = $conn->prepare("DELETE FROM tithes WHERE report_id = ?");
            $stmt_delete_tithes->bind_param("i", $report_id_to_delete);
            $stmt_delete_tithes->execute();

            $stmt_delete_report = $conn->prepare("DELETE FROM service_reports WHERE id = ?");
            $stmt_delete_report->bind_param("i", $report_id_to_delete);
            $stmt_delete_report->execute();

            $stmt_update_balance = $conn->prepare("UPDATE churches SET balance = balance - ? WHERE id = ?");
            $stmt_update_balance->bind_param("di", $offering_to_remove, $church_id);
            $stmt_update_balance->execute();

            $conn->commit();
            $message = "Relatório apagado com sucesso!";
        } else {
            $conn->rollback();
            throw new Exception("Relatório não encontrado.");
        }
    }

    // --- AÇÃO PARA CARREGAR DADOS PARA EDIÇÃO ---
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $edit_mode = true;
        $report_id_to_edit = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM service_reports WHERE id = ? AND church_id = ?");
        $stmt->bind_param("ii", $report_id_to_edit, $church_id);
        $stmt->execute();
        $report_to_edit = $stmt->get_result()->fetch_assoc();

        if ($report_to_edit) {
            $stmt_tithes = $conn->prepare("SELECT tither_name, amount FROM tithes WHERE report_id = ?");
            $stmt_tithes->bind_param("i", $report_id_to_edit);
            $stmt_tithes->execute();
            $tithes_result = $stmt_tithes->get_result();
            $report_to_edit['tithes'] = [];
            while($tithe_row = $tithes_result->fetch_assoc()){
                $report_to_edit['tithes'][] = $tithe_row;
            }
        } else {
            $edit_mode = false;
            $error = "Relatório não encontrado para edição.";
        }
    }

    // --- AÇÃO PARA PROCESSAR SUBMISSÃO DO FORMULÁRIO (CRIAR OU ATUALIZAR) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn->begin_transaction();
        
        $report_id_to_update = isset($_POST['report_id']) && !empty($_POST['report_id']) ? (int)$_POST['report_id'] : null;

        // Coletar dados do formulário
        $service_date = $_POST['service_date'] ?? null;
        $theme = $_POST['theme'] ?? '';
        $adults_members = (int)($_POST['adults_members'] ?? 0);
        $adults_visitors = (int)($_POST['adults_visitors'] ?? 0);
        $children_members = (int)($_POST['children_members'] ?? 0);
        $children_visitors = (int)($_POST['children_visitors'] ?? 0);
        $total_attendance = $adults_members + $adults_visitors + $children_members + $children_visitors;
        $adult_saved = (int)($_POST['adult_saved'] ?? 0);
        $child_saved = (int)($_POST['child_saved'] ?? 0);
        $offering = (float)($_POST['offering'] ?? 0);
        $special_offering = (float)($_POST['special_offering'] ?? 0);
        $comments = $_POST['comments'] ?? '';
        $tither_names = $_POST['tither_name'] ?? [];
        $tither_amounts = $_POST['tither_amount'] ?? [];
        $total_tithes = array_sum(array_map('floatval', $tither_amounts));
        $new_total_offering = $offering + $special_offering + $total_tithes;

        if (!$service_date) throw new Exception("A data do culto é obrigatória.");

        if ($report_id_to_update) { // LÓGICA DE ATUALIZAÇÃO
            $stmt_old = $conn->prepare("SELECT total_offering FROM service_reports WHERE id = ?");
            $stmt_old->bind_param("i", $report_id_to_update);
            $stmt_old->execute();
            $old_total_offering = $stmt_old->get_result()->fetch_assoc()['total_offering'] ?? 0;

            $stmt_update = $conn->prepare("UPDATE service_reports SET service_date=?, theme=?, adults_members=?, adults_visitors=?, children_members=?, children_visitors=?, total_attendance=?, adult_saved=?, child_saved=?, offering=?, special_offering=?, total_offering=?, comments=? WHERE id=?");
            $stmt_update->bind_param("ssiiiiiiiddssi", $service_date, $theme, $adults_members, $adults_visitors, $children_members, $children_visitors, $total_attendance, $adult_saved, $child_saved, $offering, $special_offering, $new_total_offering, $comments, $report_id_to_update);
            $stmt_update->execute();

            $stmt_delete_tithes = $conn->prepare("DELETE FROM tithes WHERE report_id = ?");
            $stmt_delete_tithes->bind_param("i", $report_id_to_update);
            $stmt_delete_tithes->execute();
            
            if (!empty($tither_names)) {
                $stmt_insert_tithe = $conn->prepare("INSERT INTO tithes (report_id, church_id, tither_name, amount) VALUES (?, ?, ?, ?)");
                for ($i = 0; $i < count($tither_names); $i++) {
                    if (!empty($tither_names[$i]) && !empty($tither_amounts[$i])) {
                        $stmt_insert_tithe->bind_param("iisd", $report_id_to_update, $church_id, $tither_names[$i], $tither_amounts[$i]);
                        $stmt_insert_tithe->execute();
                    }
                }
            }

            $balance_change = $new_total_offering - $old_total_offering;
            $stmt_balance = $conn->prepare("UPDATE churches SET balance = balance + ? WHERE id = ?");
            $stmt_balance->bind_param("di", $balance_change, $church_id);
            $stmt_balance->execute();
            
            $conn->commit();
            header("Location: entries.php?update_success=1");
            exit;

        } else { // LÓGICA DE CRIAÇÃO
            $stmt_report = $conn->prepare("INSERT INTO service_reports (church_id, user_id, service_date, theme, adults_members, adults_visitors, children_members, children_visitors, total_attendance, adult_saved, child_saved, offering, special_offering, total_offering, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_report->bind_param("iisssiiiiiiidds", $church_id, $user_id, $service_date, $theme, $adults_members, $adults_visitors, $children_members, $children_visitors, $total_attendance, $adult_saved, $child_saved, $offering, $special_offering, $new_total_offering, $comments);
            $stmt_report->execute();
            $report_id = $conn->insert_id;

            if (!empty($tither_names)) {
                $stmt_tithe = $conn->prepare("INSERT INTO tithes (report_id, church_id, tither_name, amount) VALUES (?, ?, ?, ?)");
                for ($i = 0; $i < count($tither_names); $i++) {
                    if (!empty($tither_names[$i]) && !empty($tither_amounts[$i])) {
                        $stmt_tithe->bind_param("iisd", $report_id, $church_id, $tither_names[$i], $tither_amounts[$i]);
                        $stmt_tithe->execute();
                    }
                }
            }
            
            $stmt_balance = $conn->prepare("UPDATE churches SET balance = balance + ? WHERE id = ?");
            $stmt_balance->bind_param("di", $new_total_offering, $church_id);
            $stmt_balance->execute();

            $conn->commit();
            $message = 'Relatório do culto registado com sucesso!';
        }
    }
} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    $error = 'Erro: ' . $e->getMessage();
}

if(isset($_GET['update_success'])) $message = "Relatório atualizado com sucesso!";

// --- BUSCAR DADOS PARA EXIBIÇÃO ---
$reports = [];
$church_name_for_report = '';
if ($church_id) {
    $stmt_church = $conn->prepare("SELECT name FROM churches WHERE id = ?");
    $stmt_church->bind_param("i", $church_id);
    $stmt_church->execute();
    $church_res = $stmt_church->get_result()->fetch_assoc();
    $church_name_for_report = $church_res['name'] ?? 'Congregação';

    $stmt_fetch = $conn->prepare("SELECT *, created_at as entry_timestamp FROM service_reports WHERE church_id = ? ORDER BY created_at DESC, id DESC");
    $stmt_fetch->bind_param("i", $church_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    while ($row = $result->fetch_assoc()) {
        $stmt_tithes = $conn->prepare("SELECT tither_name, amount FROM tithes WHERE report_id = ?");
        $stmt_tithes->bind_param("i", $row['id']);
        $stmt_tithes->execute();
        $tithes_result = $stmt_tithes->get_result();
        $tithes = [];
        while($tithe_row = $tithes_result->fetch_assoc()){
            $tithes[] = $tithe_row;
        }
        $row['tithes'] = $tithes;
        $reports[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Entradas</title>
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
                    <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
                    <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
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

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="bg-white border-b border-gray-200 shadow-sm z-10 sticky top-0">
                <div class="flex items-center justify-between h-16 px-6">
                    <div class="flex items-center">
                        <button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary"><i class="ri-menu-line ri-lg"></i></button>
                        <h1 class="text-lg font-medium text-gray-800">Registo de Entradas do Culto</h1>
                    </div>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors"><span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span><div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div></button>
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 dropdown-menu origin-top-right"><a href="settings.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a><a href="logout.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line mr-3"></i>Sair</a></div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 overflow-y-auto">
                <?php if ($message): ?> <div id="alert-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($message); ?></span><button onclick="document.getElementById('alert-message').style.display='none'"><i class="ri-close-line"></i></button></div> <?php endif; ?>
                <?php if ($error): ?> <div id="alert-error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($error); ?></span><button onclick="document.getElementById('alert-error').style.display='none'"><i class="ri-close-line"></i></button></div> <?php endif; ?>

                <form id="entries-form" method="POST" action="entries.php" class="bg-white rounded-lg shadow p-6 sm:p-8 space-y-8">
                    <input type="hidden" name="report_id" value="<?php echo $edit_mode ? $report_to_edit['id'] : ''; ?>">
                    <div class="flex justify-between items-center border-b border-gray-200 pb-5">
                        <h2 class="text-xl font-semibold leading-7 text-gray-900"><?php echo $edit_mode ? 'Editar Relatório' : 'Informações Gerais'; ?></h2>
                        <?php if ($edit_mode): ?>
                            <a href="entries.php" class="text-sm font-medium text-primary hover:text-blue-700 flex items-center gap-1"><i class="ri-add-circle-line"></i>Registar Novo Relatório</a>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label for="service_date" class="block text-sm font-medium text-gray-700">Data do Culto</label><input type="date" id="service_date" name="service_date" value="<?php echo $edit_mode ? htmlspecialchars($report_to_edit['service_date']) : date('Y-m-d'); ?>" required class="form-input"></div>
                        <div><label for="theme" class="block text-sm font-medium text-gray-700">Tema do Culto</label><input type="text" id="theme" name="theme" class="form-input" placeholder="Tema da pregação" value="<?php echo $edit_mode ? htmlspecialchars($report_to_edit['theme']) : ''; ?>"></div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pt-6 border-t border-gray-200">
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Participação</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label for="adults_members" class="block text-sm font-medium">Adultos (Membros)</label><input type="number" name="adults_members" value="<?php echo $edit_mode ? $report_to_edit['adults_members'] : 0; ?>" min="0" class="form-input participation-input"></div>
                                <div><label for="adults_visitors" class="block text-sm font-medium">Adultos (Visitantes)</label><input type="number" name="adults_visitors" value="<?php echo $edit_mode ? $report_to_edit['adults_visitors'] : 0; ?>" min="0" class="form-input participation-input"></div>
                                <div><label for="children_members" class="block text-sm font-medium">Crianças (Membros)</label><input type="number" name="children_members" value="<?php echo $edit_mode ? $report_to_edit['children_members'] : 0; ?>" min="0" class="form-input participation-input"></div>
                                <div><label for="children_visitors" class="block text-sm font-medium">Crianças (Visitantes)</label><input type="number" name="children_visitors" value="<?php echo $edit_mode ? $report_to_edit['children_visitors'] : 0; ?>" min="0" class="form-input participation-input"></div>
                                <div class="col-span-2"><label for="total_attendance" class="block text-sm font-bold text-gray-500">Participação Total</label><input type="text" id="total_attendance" readonly class="form-input bg-gray-100 font-bold text-lg text-center"></div>
                            </div>
                        </div>
                        <div class="space-y-4">
                             <h3 class="text-lg font-medium leading-6 text-gray-900">Salvos</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label for="adult_saved" class="block text-sm font-medium">Adultos</label><input type="number" name="adult_saved" value="<?php echo $edit_mode ? $report_to_edit['adult_saved'] : 0; ?>" min="0" class="form-input"></div>
                                <div><label for="child_saved" class="block text-sm font-medium">Crianças</label><input type="number" name="child_saved" value="<?php echo $edit_mode ? $report_to_edit['child_saved'] : 0; ?>" min="0" class="form-input"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-6 border-t border-gray-200">
                        <h2 class="text-xl font-semibold leading-7 text-gray-900">Ofertas e dízimos</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                            <div><label for="offering" class="block text-sm font-medium">Ofertas Gerais (MZN)</label><input type="number" name="offering" value="<?php echo $edit_mode ? $report_to_edit['offering'] : '0.00'; ?>" step="0.01" min="0" class="form-input financial-input"></div>
                            <div><label for="special_offering" class="block text-sm font-medium">Oferta Especial (MZN)</label><input type="number" name="special_offering" value="<?php echo $edit_mode ? $report_to_edit['special_offering'] : '0.00'; ?>" step="0.01" min="0" class="form-input financial-input"></div>
                        </div>
                         <div class="mt-8">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Dízimos</h3>
                            <div id="tithers-container" class="mt-4 space-y-3"></div>
                            <button type="button" id="add-tither-btn" class="mt-3 text-sm font-medium text-primary hover:text-blue-700 flex items-center gap-1"><i class="ri-add-line"></i>Adicionar Dízimo</button>
                        </div>
                        <div class="mt-8 pt-4 border-t">
                            <label for="total_entries" class="block text-sm font-bold text-gray-500">Total de Entradas (MZN)</label>
                            <input type="text" id="total_entries" readonly class="form-input bg-gray-100 font-bold text-green-600 text-lg text-center">
                        </div>
                    </div>
                    
                    <div class="pt-6 border-t border-gray-200">
                        <label for="comments" class="block text-sm font-medium text-gray-700">Comentários Adicionais</label>
                        <textarea name="comments" rows="3" class="form-input" placeholder="Detalhes sobre a oferta especial, eventos, etc."><?php echo $edit_mode ? htmlspecialchars($report_to_edit['comments']) : ''; ?></textarea>
                    </div>

                    <div class="flex justify-end pt-5">
                        <button type="submit" class="inline-flex justify-center py-3 px-8 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <?php echo $edit_mode ? 'Atualizar Relatório' : 'Registar Entradas do Culto'; ?>
                        </button>
                    </div>
                </form>

                <?php
                // --- FILTROS DE MÊS/ANO ---
                $current_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
                $current_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
                
                // Array de meses em português
                $months_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
                ?>

                <div class="bg-white rounded-lg shadow p-4 sm:p-6 mt-8">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                        <h2 class="text-lg font-semibold text-gray-800">Relatórios de Entradas</h2>
                        
                        <form method="GET" class="flex items-center space-x-2 bg-gray-50 p-2 rounded-lg border border-gray-200">
                            <div class="relative">
                                <select name="month" class="appearance-none bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500 text-sm" onchange="this.form.submit()">
                                    <?php foreach ($months_pt as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $num === $current_month ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <i class="ri-arrow-down-s-line"></i>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <select name="year" class="appearance-none bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500 text-sm" onchange="this.form.submit()">
                                    <?php 
                                    $start_year = 2024;
                                    $end_year = date('Y') + 1;
                                    for ($y = $start_year; $y <= $end_year; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y === $current_year ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <i class="ri-arrow-down-s-line"></i>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-y-auto max-h-[420px] mb-8">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3">Data</th>
                                        <th class="px-4 py-3 hidden md:table-cell">Tema</th>
                                        <th class="px-4 py-3 hidden sm:table-cell">Participantes</th>
                                        <th class="px-4 py-3">Total</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Filtragem dos relatórios pelo array já carregado ou nova query
                                    // Para garantir consistência com o filtro, vamos filtrar o array $reports 
                                    // NOTA: O código original carregava TUDO. Vamos filtrar aqui para respeitar a seleção.
                                    
                                    $filtered_reports = [];
                                    $weekly_summary = [];
                                    $monthly_total = 0;

                                    if (!empty($reports)) {
                                        foreach ($reports as $report) {
                                            $rpt_date = strtotime($report['service_date']);
                                            if (date('n', $rpt_date) == $current_month && date('Y', $rpt_date) == $current_year) {
                                                $filtered_reports[] = $report;
                                                
                                                // Lógica Semanal
                                                // Semana do mês (cálculo simples baseado no dia)
                                                $day = date('j', $rpt_date);
                                                // Uma forma comum: Semana baseada na divisão por 7 dias ou calendário
                                                // O utilizador pediu "Semana 1, Semana 2..." e "datar todos os domingos"
                                                // Vamos agrupar pela data (Domingo) se for culto de Domingo, ou simplesmente pela semana do mês.
                                                
                                                // Agrupamento por Semana do Ano para ordenar corretamente, depois mapear para 1..5
                                                $week_number = date('W', $rpt_date);
                                                if (!isset($weekly_summary[$week_number])) {
                                                    $weekly_summary[$week_number] = [
                                                        'total' => 0, 
                                                        'dates' => []
                                                    ];
                                                }
                                                $weekly_summary[$week_number]['total'] += $report['total_offering'];
                                                $weekly_summary[$week_number]['dates'][] = date('d/m', $rpt_date);
                                                
                                                $monthly_total += $report['total_offering'];
                                            }
                                        }
                                    }

                                    if (empty($filtered_reports)): ?>
                                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Nenhum relatório encontrado para <?php echo $months_pt[$current_month] . ' de ' . $current_year; ?>.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($filtered_reports as $report): ?>
                                            <tr class="bg-white border-b hover:bg-gray-50">
                                                <td class="px-4 py-4 font-medium text-gray-900 whitespace-nowrap"><?php echo date('d/m/Y', strtotime($report['service_date'])); ?></td>
                                                <td class="px-4 py-4 hidden md:table-cell"><?php echo htmlspecialchars($report['theme']); ?></td>
                                                <td class="px-4 py-4 hidden sm:table-cell text-center"><?php echo $report['total_attendance']; ?></td>
                                                <td class="px-4 py-4 font-medium text-green-600"><?php echo number_format($report['total_offering'], 2, ',', '.'); ?> MZN</td>
                                                <td class="px-4 py-4 text-right">
                                                    <div class="flex items-center justify-end space-x-2">
                                                        <a href="entries.php?action=edit&id=<?php echo $report['id']; ?>" class="text-gray-400 hover:text-primary p-1" title="Editar Relatório">
                                                            <i class="ri-pencil-line"></i>
                                                        </a>
                                                        <button class="delete-report-btn text-gray-400 hover:text-red-600 p-1" title="Apagar Relatório" data-id="<?php echo $report['id']; ?>">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                        <button class="view-report-details-btn text-gray-400 hover:text-primary p-1" title="Ver Detalhes" data-details='<?php echo htmlspecialchars(json_encode($report), ENT_QUOTES, 'UTF-8'); ?>'>
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

                    <!-- TABELA DE RESUMO MENSAL (FUNDO APOSTÓLICO) -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-md font-bold text-gray-700 mb-4 uppercase tracking-wide">Resumo Financeiro - <?php echo $months_pt[$current_month] . '/' . $current_year; ?></h3>
                        <div class="overflow-x-auto bg-gray-50 rounded-lg border border-gray-200">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-500 uppercase bg-gray-100 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 font-semibold">Semana / Período</th>
                                        <th class="px-6 py-3 font-semibold text-right">Entradas Totais</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php 
                                    // Processar semanas para exibição sequencial (1, 2, 3...)
                                    ksort($weekly_summary);
                                    $counter = 1;
                                    foreach ($weekly_summary as $week_data): 
                                        // Formatar datas únicas separadas por vírgula
                                        $unique_dates = implode(', ', array_unique($week_data['dates']));
                                    ?>
                                    <tr class="bg-white">
                                        <td class="px-6 py-3 text-gray-700">
                                            <span class="font-medium">Semana <?php echo $counter; ?></span>
                                            <span class="text-xs text-gray-500 ml-2">(<?php echo $unique_dates; ?>)</span>
                                        </td>
                                        <td class="px-6 py-3 text-right font-medium text-gray-900">
                                            <?php echo number_format($week_data['total'], 2, ',', '.'); ?> MZN
                                        </td>
                                    </tr>
                                    <?php 
                                    $counter++;
                                    endforeach; 
                                    
                                    if (empty($weekly_summary)) {
                                        echo '<tr><td colspan="2" class="px-6 py-4 text-center text-gray-500 italic">Nenhuma entrada registada neste mês.</td></tr>';
                                    }
                                    
                                    $fundo_apostolico = $monthly_total * 0.10;
                                    ?>
                                </tbody>
                                <tfoot class="bg-gray-50 font-bold">
                                    <tr class="border-t-2 border-gray-200">
                                        <td class="px-6 py-4 text-gray-800 text-right uppercase text-xs tracking-wider">Total Mensal</td>
                                        <td class="px-6 py-4 text-right text-gray-900 text-lg"><?php echo number_format($monthly_total, 2, ',', '.'); ?> MZN</td>
                                    </tr>
                                    <!-- LINHA DE DESTAQUE DO FUNDO APOSTÓLICO -->
                                    <tr class="bg-blue-50 border-t border-blue-100">
                                        <td class="px-6 py-4 text-blue-800 text-right flex items-center justify-end gap-2">
                                            <i class="ri-hand-coin-line text-xl"></i>
                                            <span class="uppercase tracking-wider">Fundo Apostólico (10%)</span>
                                        </td>
                                        <td class="px-6 py-4 text-right text-blue-700 text-xl border-l-4 border-blue-500">
                                            <?php echo number_format($fundo_apostolico, 2, ',', '.'); ?> MZN
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-right">* O Fundo Apostólico corresponde a 10% do total arrecadado (Ofertas + Dízimos + Especiais) no mês selecionado.</p>
                    </div>

                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal para Detalhes do Relatório -->
    <div id="reportModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-40 flex items-center justify-center hidden opacity-0 p-4">
      <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-2xl transform scale-95 opacity-0">
        <div id="modalBody" class="p-6 max-h-[80vh] overflow-y-auto space-y-4"></div>
        <div class="flex justify-between items-center p-4 border-t bg-gray-50 rounded-b-lg">
            <button id="closeModal" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-button hover:bg-gray-300">Fechar</button>
            <a id="exportPdfLink" href="#" target="_blank" class="inline-flex items-center bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700">
                <i class="ri-printer-line mr-2"></i>Exportar como PDF
            </a>
        </div>
      </div>
    </div>

    <!-- Modal para Confirmação de Apagar -->
    <div id="deleteConfirmationModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden opacity-0 p-4">
      <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">Confirmar Exclusão</h3>
            <p class="mt-2 text-sm text-gray-600">Tem a certeza que deseja apagar este relatório? Esta ação é irreversível e ajustará o saldo da congregação. Para confirmar, digite <strong class="text-red-600">lifechurch</strong> abaixo.</p>
            <input type="text" id="delete-confirmation-key" class="form-input mt-4" placeholder="Escreva a chave de confirmação">
            <p id="delete-error-msg" class="text-red-500 text-sm mt-1 hidden">Chave de confirmação incorreta.</p>
        </div>
        <div class="flex justify-end items-center p-4 border-t bg-gray-50 rounded-b-lg space-x-3">
            <button id="cancelDelete" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-button hover:bg-gray-300">Cancelar</button>
            <button id="confirmDelete" class="bg-red-600 text-white py-2 px-4 rounded-button hover:bg-red-700">Apagar Relatório</button>
        </div>
      </div>
    </div>
    
    <script id="main-script">
    document.addEventListener("DOMContentLoaded", function () {
        const reportToEditData = <?php echo json_encode($report_to_edit); ?>;

        function calculateTotals() {
            let totalAttendance = 0;
            document.querySelectorAll('.participation-input').forEach(i => totalAttendance += parseInt(i.value) || 0);
            document.getElementById('total_attendance').value = totalAttendance;
            let totalTithes = 0;
            document.querySelectorAll('.tither-amount-input').forEach(i => totalTithes += parseFloat(i.value) || 0);
            const offering = parseFloat(document.querySelector('[name="offering"]').value) || 0;
            const specialOffering = parseFloat(document.querySelector('[name="special_offering"]').value) || 0;
            const totalEntries = totalTithes + offering + specialOffering;
            document.getElementById('total_entries').value = totalEntries.toFixed(2).replace('.', ',') + ' MZN';
        }

        function addTitherRow(name = '', amount = '') {
            const container = document.getElementById('tithers-container');
            const row = document.createElement('div');
            row.className = 'flex items-center gap-4 tither-row';
            row.innerHTML = `<div class="flex-grow"><input type="text" name="tither_name[]" class="form-input" placeholder="Nome do Dizimista" value="${name}" required></div><div class="w-1/3"><input type="number" name="tither_amount[]" class="form-input tither-amount-input" placeholder="0.00" step="0.01" min="0" value="${amount}" required></div><button type="button" class="remove-tither-btn text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 transition-colors"><i class="ri-delete-bin-line ri-lg"></i></button>`;
            container.appendChild(row);
            row.querySelectorAll('input').forEach(i => i.addEventListener('input', calculateTotals));
        }

        if (reportToEditData && reportToEditData.tithes) {
            reportToEditData.tithes.forEach(tithe => {
                addTitherRow(tithe.tither_name, tithe.amount);
            });
        }

        document.getElementById('add-tither-btn').addEventListener('click', () => addTitherRow());
        
        document.getElementById('tithers-container').addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-tither-btn');
            if (removeBtn) {
                removeBtn.closest('.tither-row').remove();
                calculateTotals();
            }
        });

        document.getElementById('entries-form').addEventListener('input', calculateTotals);
        calculateTotals();
        
        const reportModal = document.getElementById("reportModal");
        const closeModalBtn = document.getElementById("closeModal");
        const viewReportButtons = document.querySelectorAll('.view-report-details-btn');
        const churchName = "<?php echo htmlspecialchars($church_name_for_report); ?>";

        viewReportButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const details = JSON.parse(button.dataset.details);
                const modalBody = document.getElementById("modalBody");
                const exportPdfLink = document.getElementById("exportPdfLink");
                exportPdfLink.href = `export_report.php?id=${details.id}`;
                let tithesHTML = '';
                if(details.tithes && details.tithes.length > 0) {
                    details.tithes.forEach(tithe => {
                        tithesHTML += `<div class="flex justify-between text-sm py-1 border-b"><span class="w-2/3">${tithe.tither_name}</span><span>${parseFloat(tithe.amount).toFixed(2).replace('.',',')} MZN</span></div>`;
                    });
                } else {
                    tithesHTML = '<p class="text-sm text-gray-500">Nenhum dízimo registado.</p>';
                }
                const serviceDate = new Date(details.service_date + 'T00:00:00');
                const day = serviceDate.getDate();
                const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
                const month = monthNames[serviceDate.getMonth()];
                const year = serviceDate.getFullYear();
                modalBody.innerHTML = `
                    <div class="text-center"><h3 class="text-lg font-semibold">Comunidade de Vida Cristã - Life Church</h3><p class="text-sm">MOÇAMBIQUE</p><p class="text-md font-medium mt-1">Congregação de ${churchName}</p></div>
                    <div class="mt-4 border-b pb-2"><p><strong>Celebração do culto referente ao dia ${day} de ${month} de ${year}</strong></p><p><strong>Tema:</strong> ${details.theme || 'Não especificado'}</p></div>
                    <div class="mt-4"><h4 class="font-bold text-center mb-2">Detalhes de Participação</h4><div class="grid grid-cols-5 gap-1 text-center font-bold text-sm mb-1"><div class="col-span-2"></div><div>Membros</div><div>Visitantes</div><div>Salvos</div></div><div class="grid grid-cols-5 gap-1 text-sm items-center"><strong class="col-span-2">Adultos: ${parseInt(details.adults_members) + parseInt(details.adults_visitors)}</strong><div class="text-center">${details.adults_members}</div><div class="text-center">${details.adults_visitors}</div><div class="text-center">${details.adult_saved}</div></div><div class="grid grid-cols-5 gap-1 text-sm items-center"><strong class="col-span-2">Crianças: ${parseInt(details.children_members) + parseInt(details.children_visitors)}</strong><div class="text-center">${details.children_members}</div><div class="text-center">${details.children_visitors}</div><div class="text-center">${details.child_saved}</div></div><div class="grid grid-cols-5 gap-1 text-sm items-center mt-2 border-t pt-2"><strong class="col-span-2">Total: ${details.total_attendance}</strong><div></div><div></div><div class="text-center font-bold">${parseInt(details.adult_saved)+parseInt(details.child_saved)}</div></div></div>
                    <div class="mt-4 border-t pt-4"><h4 class="font-bold text-center mb-2">Ofertas e Dízimos</h4><div class="flex justify-between text-sm py-1 border-b"><span>Ofertório:</span><span>${parseFloat(details.offering).toFixed(2).replace('.',',')} MZN</span></div><div class="flex justify-between text-sm py-1 border-b"><span>Of. Especial:</span><span>${parseFloat(details.special_offering).toFixed(2).replace('.',',')} MZN</span></div><div class="mt-2"><p class="text-sm font-medium mb-1">Dízimos:</p>${tithesHTML}</div><div class="flex justify-between text-md font-bold py-2 mt-2 border-t"><span>Total de Entradas:</span><span>${parseFloat(details.total_offering).toFixed(2).replace('.',',')} MZN</span></div></div>
                    <div class="mt-4 border-t pt-4"><h4 class="font-bold mb-2">Comentários:</h4><p class="text-sm text-gray-700">${details.comments || 'Nenhum comentário.'}</p></div>`;
                reportModal.classList.remove('hidden');
                setTimeout(() => { reportModal.classList.remove('opacity-0'); reportModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'); }, 10);
            });
        });
        
        const hideReportModal = () => {
             reportModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
             reportModal.classList.add('opacity-0');
             setTimeout(() => reportModal.classList.add('hidden'), 300);
        };
        closeModalBtn.addEventListener('click', hideReportModal);
        
        // --- LÓGICA DO MODAL DE APAGAR ---
        const deleteModal = document.getElementById("deleteConfirmationModal");
        const cancelDeleteBtn = document.getElementById("cancelDelete");
        const confirmDeleteBtn = document.getElementById("confirmDelete");
        const deleteButtons = document.querySelectorAll(".delete-report-btn");
        let reportIdToDelete = null;

        deleteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                reportIdToDelete = button.dataset.id;
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
                errorMsg.classList.add('hidden');
                inputKey.value = '';
            }, 300);
        };

        cancelDeleteBtn.addEventListener('click', hideDeleteModal);
        confirmDeleteBtn.addEventListener('click', () => {
            const inputKey = document.getElementById('delete-confirmation-key');
            const errorMsg = document.getElementById('delete-error-msg');
            if (inputKey.value === 'lifechurch') {
                window.location.href = `entries.php?action=delete&id=${reportIdToDelete}`;
            } else {
                errorMsg.classList.remove('hidden');
            }
        });

        // Lógica do menu de usuário no canto superior direito
        const userMenuButton = document.getElementById("user-menu-button");
        const userMenu = document.getElementById("user-menu");
        if(userMenuButton) {
            userMenuButton.addEventListener("click", (event) => { userMenu.classList.toggle("hidden"); event.stopPropagation(); });
            document.addEventListener("click", (event) => { if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) { userMenu.classList.add("hidden"); } });
        }
    });
    </script>
    <!-- Script para o menu lateral responsivo -->
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
