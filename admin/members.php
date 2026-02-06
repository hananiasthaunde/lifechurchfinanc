<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

// Linha Corrigida
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/public/login.php');
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

$message = '';
$error = '';
$editing_member = null;

// Apenas pastores e administradores podem gerir membros
$can_manage = in_array($user_role, ['pastor', 'pastor_principal', 'master_admin']);

$conn = connect_db();

// --- LÓGICA DE APROVAÇÃO, REJEIÇÃO E DESAPROVAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $member_id = (int)($_POST['member_id'] ?? 0);

    // Ação para APROVAR um membro pendente
    if (isset($_POST['approve_member'])) {
        $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND church_id = ?");
        $stmt->bind_param("ii", $member_id, $church_id);
        if ($stmt->execute()) {
            $message = "Membro aprovado com sucesso!";
        } else {
            $error = "Erro ao aprovar o membro.";
        }
        $stmt->close();

    // Ação para REJEITAR (apagar) um membro pendente
    } elseif (isset($_POST['reject_member'])) {
        $conn->begin_transaction();
        try {
            // Apaga da tabela 'users' apenas se estiver pendente
            $stmt_users = $conn->prepare("DELETE FROM users WHERE id = ? AND church_id = ? AND is_approved = 0");
            $stmt_users->bind_param("ii", $member_id, $church_id);
            $stmt_users->execute();
            $conn->commit();
            $message = "Membro rejeitado e removido com sucesso.";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $error = "Erro ao rejeitar o membro: " . $exception->getMessage();
        }

    // Ação para DESAPROVAR um membro ativo (move para pendente)
    } elseif (isset($_POST['disapprove_member'])) {
        $stmt = $conn->prepare("UPDATE users SET is_approved = 0 WHERE id = ? AND church_id = ?");
        $stmt->bind_param("ii", $member_id, $church_id);
        if ($stmt->execute()) {
            $message = "Membro desaprovado com sucesso. O acesso do utilizador foi revogado.";
        } else {
            $error = "Erro ao desaprovar o membro.";
        }
        $stmt->close();
    }
}


// --- LÓGICA DE EXCLUSÃO (DELETE) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $member_to_delete_id = (int)$_GET['id'];

    // Para segurança, verificar se o membro pertence à igreja do admin
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND church_id = ?");
    $stmt_check->bind_param("ii", $member_to_delete_id, $church_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $stmt_users = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_users->bind_param("i", $member_to_delete_id);
        if ($stmt_users->execute()) {
            $message = "Membro excluído com sucesso!";
        } else {
            $error = "Erro ao excluir o membro.";
        }
    } else {
        $error = "Membro não encontrado ou não pertence à sua igreja.";
    }
}


// --- LÓGICA DE ADIÇÃO E EDIÇÃO (CREATE / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && ($_POST['action'] === 'add_member' || $_POST['action'] === 'edit_member'))) {
    $action = $_POST['action'];
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'membro';
    $phone = $_POST['phone'] ?? '';
    $city = $_POST['city'] ?? '';
    
    if ($name && $email && $role) {
        if ($action === 'edit_member') {
            $member_id = $_POST['member_id'];
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, phone = ?, city = ? WHERE id = ? AND church_id = ?");
            $stmt->bind_param("sssssii", $name, $email, $role, $phone, $city, $member_id, $church_id);
            if ($stmt->execute()) {
                $message = "Membro atualizado com sucesso!";
            } else {
                $error = "Erro ao atualizar o membro. O email pode já estar em uso.";
            }
            $stmt->close();
        }
        elseif ($action === 'add_member') {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Este email já está registado.';
            } else {
                // Por defeito, um membro adicionado pelo pastor já vem aprovado.
                $is_approved = 1; 
                $password = password_hash('membro123', PASSWORD_DEFAULT); 
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, city, role, church_id, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssii", $name, $email, $password, $phone, $city, $role, $church_id, $is_approved);
                
                if ($stmt->execute()) {
                    $message = 'Membro adicionado e aprovado com sucesso! A senha padrão é: membro123';
                } else {
                    $error = 'Erro ao adicionar membro.';
                }
            }
            $stmt_check->close();
        }
    } else {
        if($action === 'add_member' || $action === 'edit_member'){
            $error = 'Por favor, preencha todos os campos obrigatórios (Nome, Email, Função).';
        }
    }
}

// --- LÓGICA PARA PREENCHER FORMULÁRIO DE EDIÇÃO ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $member_to_edit_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, name, email, role, phone, city FROM users WHERE id = ? AND church_id = ?");
    $stmt->bind_param("ii", $member_to_edit_id, $church_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0){
        $editing_member = $result->fetch_assoc();
    } else {
        $error = "Membro não encontrado.";
    }
}

// Buscar todos os usuários da igreja para exibir na tabela
$members = [];
if ($church_id) {
    // Query para buscar todos os usuários da igreja, ordenando por status e nome
    $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.phone, u.city, u.role, u.is_approved 
                             FROM users u 
                             WHERE u.church_id = ? 
                             ORDER BY u.is_approved ASC, u.name ASC");
    $stmt->bind_param("i", $church_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
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
    <title>Life Church - Membros</title>
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
      .sidebar-item:hover:not(.active) { background-color: #F0F7FF; }
      .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
      .dropdown-menu { transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out; }
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
                <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
                <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
                <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
                                    <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
                    <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
                <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
                </div>
                <div class="px-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
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
                        <h1 class="text-lg font-medium text-gray-800">Gestão de Membros</h1>
                    </div>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors">
                            <span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                            <div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            </div>
                        </button>
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 dropdown-menu origin-top-right">
                            <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a>
                            <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line mr-3"></i>Sair</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                <?php if ($message): ?><div id="alert-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($message); ?></span><button onclick="document.getElementById('alert-message').style.display='none'"><i class="ri-close-line"></i></button></div><?php endif; ?>
                <?php if ($error): ?><div id="alert-error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($error); ?></span><button onclick="document.getElementById('alert-error').style.display='none'"><i class="ri-close-line"></i></button></div><?php endif; ?>

                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-800 mb-4"><?php echo $editing_member ? 'Editar Membro' : 'Adicionar Novo Membro'; ?></h2>
                    <form method="POST" action="members.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <input type="hidden" name="action" value="<?php echo $editing_member ? 'edit_member' : 'add_member'; ?>">
                        <?php if ($editing_member): ?>
                            <input type="hidden" name="member_id" value="<?php echo $editing_member['id']; ?>">
                        <?php endif; ?>
                        
                        <div><label for="name" class="block text-sm font-medium text-gray-700">Nome Completo*</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editing_member['name'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></div>
                        <div><label for="email" class="block text-sm font-medium text-gray-700">Email*</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editing_member['email'] ?? ''); ?>" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></div>
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Função*</label>
                            <select id="role" name="role" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                                <option value="membro" <?php echo (isset($editing_member['role']) && $editing_member['role'] == 'membro') ? 'selected' : ''; ?>>Membro</option>
                                <option value="lider" <?php echo (isset($editing_member['role']) && $editing_member['role'] == 'lider') ? 'selected' : ''; ?>>Líder</option>
                                <option value="pastor" <?php echo (isset($editing_member['role']) && $editing_member['role'] == 'pastor') ? 'selected' : ''; ?>>Pastor</option>
                            </select>
                        </div>
                        <div><label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label><input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($editing_member['phone'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></div>
                        <div><label for="city" class="block text-sm font-medium text-gray-700">Cidade</label><input type="text" id="city" name="city" value="<?php echo htmlspecialchars($editing_member['city'] ?? ''); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></div>
                        
                        <div class="lg:col-span-3 flex items-center gap-4">
                            <button type="submit" class="w-full md:w-auto flex-grow justify-center py-2 px-4 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <?php echo $editing_member ? 'Atualizar Membro' : 'Adicionar Membro'; ?>
                            </button>
                            <?php if ($editing_member): ?>
                                <a href="members.php" class="w-full md:w-auto flex-grow justify-center text-center py-2 px-4 border border-gray-300 rounded-button shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar Edição</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-lg shadow-sm">
                    <div class="flex flex-col sm:flex-row justify-between items-center p-4 border-b">
                         <h2 class="text-lg font-medium text-gray-800 mb-4 sm:mb-0">Membros da Igreja</h2>
                         <div class="relative w-full sm:w-auto sm:max-w-xs">
                             <input type="text" id="memberSearch" placeholder="Pesquisar por nome ou email..." class="w-full pl-10 pr-4 py-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-primary">
                             <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                         </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Nome</th>
                                    <th scope="col" class="px-6 py-3 hidden md:table-cell">Função</th>
                                    <th scope="col" class="px-6 py-3 hidden lg:table-cell">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="membersTableBody">
                                <?php if (empty($members)): ?>
                                    <tr><td colspan="4" class="px-6 py-4 text-center">Nenhum membro encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($members as $member): ?>
                                        <tr class="bg-white border-b hover:bg-gray-50">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full bg-secondary text-primary flex items-center justify-center font-bold text-xs mr-3 flex-shrink-0">
                                                        <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <?php echo htmlspecialchars($member['name']); ?>
                                                        <div class="font-normal text-gray-400 md:hidden"><?php echo htmlspecialchars($member['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 hidden md:table-cell"><span class="px-2 py-1 text-xs font-medium rounded-full <?php 
                                                switch($member['role']) {
                                                    case 'pastor': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'lider': echo 'bg-green-100 text-green-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800'; break;
                                                }
                                            ?>"><?php echo ucfirst(htmlspecialchars($member['role'])); ?></span></td>
                                            <td class="px-6 py-4 hidden lg:table-cell">
                                                <?php if ($member['is_approved']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aprovado</span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pendente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex items-center justify-end space-x-1">
                                                    <?php if ($can_manage): ?>
                                                        <?php if (!$member['is_approved']): ?>
                                                            <!-- Ações para membros PENDENTES -->
                                                            <form method="POST" action="members.php" class="inline">
                                                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                                <button type="submit" name="approve_member" class="p-2 text-green-600 hover:bg-green-50 rounded-full" title="Aprovar Membro">
                                                                    <i class="ri-checkbox-circle-line"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="members.php" class="inline" onsubmit="return confirm('Tem a certeza que deseja rejeitar este membro? A conta será apagada.');">
                                                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                                <button type="submit" name="reject_member" class="p-2 text-red-600 hover:bg-red-50 rounded-full" title="Rejeitar Membro">
                                                                    <i class="ri-delete-bin-2-line"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <!-- Ação para membros APROVADOS -->
                                                            <form method="POST" action="members.php" class="inline" onsubmit="return confirm('Tem a certeza que deseja desaprovar este membro? O acesso será revogado.');">
                                                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                                <button type="submit" name="disapprove_member" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-full" title="Desaprovar Membro">
                                                                    <i class="ri-close-circle-line"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Ações de Edição e Exclusão para todos -->
                                                    <a href="members.php?action=edit&id=<?php echo $member['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-full" title="Editar">
                                                        <i class="ri-pencil-line"></i>
                                                    </a>
                                                    <a href="members.php?action=delete&id=<?php echo $member['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-full" title="Excluir" onclick="return confirm('Tem a certeza que deseja excluir este membro? Esta ação é irreversível.');">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <p id="noResults" class="text-center py-4 text-gray-500 hidden">Nenhum resultado encontrado.</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Dropdown do usuário
        const userMenuButton = document.getElementById("user-menu-button");
        const userMenu = document.getElementById("user-menu");
        if(userMenuButton) {
            userMenuButton.addEventListener("click", (event) => { userMenu.classList.toggle("hidden"); event.stopPropagation(); });
            document.addEventListener("click", (event) => { if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) { userMenu.classList.add("hidden"); } });
        }

        // Lógica da Pesquisa na tabela
        const searchInput = document.getElementById("memberSearch");
        const tableBody = document.getElementById("membersTableBody");
        const rows = tableBody.getElementsByTagName("tr");
        const noResults = document.getElementById("noResults");

        searchInput.addEventListener("keyup", function() {
            const filter = searchInput.value.toLowerCase();
            let found = false;
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].getElementsByTagName("td").length > 0) {
                    const nameCell = rows[i].getElementsByTagName("td")[0];
                    const name = nameCell.textContent.toLowerCase();
                    const email = nameCell.querySelector(".md\\:hidden") ? nameCell.querySelector(".md\\:hidden").textContent.toLowerCase() : '';
                    
                    if (name.indexOf(filter) > -1 || email.indexOf(filter) > -1) {
                        rows[i].style.display = "";
                        found = true;
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
            noResults.style.display = found ? "none" : "block";
        });

        const alertMessage = document.getElementById('alert-message');
        if (alertMessage) setTimeout(() => { alertMessage.style.display = 'none'; }, 5000);
        const alertError = document.getElementById('alert-error');
        if (alertError) setTimeout(() => { alertError.style.display = 'none'; }, 7000);
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
