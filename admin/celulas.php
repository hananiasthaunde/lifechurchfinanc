<?php
session_start();

// --- Segurança e Configuração ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// --- Verificações de Acesso: Apenas Líderes e Master Admin ---
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['lider', 'master_admin'])) {
    $_SESSION['error_message'] = 'Acesso restrito a líderes e administradores.';
    header('Location: dashboard.php');
    exit;
}

$conn = connect_db();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$church_id = $_SESSION['church_id'];
$message = '';
$error = '';
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// --- LÓGICA AJAX ---
if (isset($_GET['action']) && $_GET['action'] === 'get_available_members') {
    header('Content-Type: application/json');
    $search_term = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
    
    // Busca utilizadores da mesma igreja que ainda não estão numa célula
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE church_id = ? AND celula_id IS NULL AND (name LIKE ? OR email LIKE ?) LIMIT 1000");
    if ($stmt) {
        $stmt->bind_param("iss", $church_id, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($users);
    } else {
        echo json_encode([]);
    }
    $conn->close();
    exit;
}


// --- Lógica de Ações (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- Ação para Criar Nova Célula ---
    if ($action === 'create_celula' && $user_role === 'lider') {
        $nome_celula = $_POST['nome_celula'] ?? '';
        $dia_encontro = $_POST['dia_encontro'] ?? '';
        $horario = $_POST['horario'] ?? '';
        $endereco = $_POST['endereco'] ?? '';

        if ($nome_celula && $dia_encontro && $horario && $endereco) {
            $stmt_check = $conn->prepare("SELECT id FROM celulas WHERE lider_id = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("i", $user_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check && $result_check->num_rows > 0) {
                    $error = "Você já é líder de uma célula. Não pode criar outra.";
                } else {
                    $stmt_create = $conn->prepare("INSERT INTO celulas (nome, lider_id, church_id, dia_encontro, horario, endereco) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt_create) {
                        $stmt_create->bind_param("siisss", $nome_celula, $user_id, $church_id, $dia_encontro, $horario, $endereco);
                        if ($stmt_create->execute()) {
                            header("Location: celulas.php?creation_success=1");
                            exit;
                        } else {
                            $error = "Ocorreu um erro ao criar a sua célula.";
                        }
                        $stmt_create->close();
                    } else {
                         $error = "Erro ao preparar a criação da célula.";
                    }
                }
                $stmt_check->close();
            } else {
                 $error = "Erro ao verificar a existência da célula.";
            }
        } else {
            $error = "Por favor, preencha todos os campos para criar a sua célula.";
        }
    }
    // --- Ação para ASSOCIAR um membro existente ---
    elseif ($action === 'assign_member') {
        $celula_id_to_add = (int)$_POST['celula_id'];
        $user_id_to_add = (int)$_POST['user_id'];

        $can_add = false;
        if($user_role === 'master_admin') {
            $can_add = true;
        } elseif ($user_role === 'lider') {
            $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
            if ($stmt_verify) {
                $stmt_verify->bind_param("ii", $celula_id_to_add, $user_id);
                $stmt_verify->execute();
                $result_verify = $stmt_verify->get_result();
                if($result_verify && $result_verify->num_rows > 0) $can_add = true;
                $stmt_verify->close();
            }
        }
        
        if ($can_add) {
            $stmt_assign = $conn->prepare("UPDATE users SET celula_id = ? WHERE id = ? AND church_id = ?");
            if ($stmt_assign) {
                $stmt_assign->bind_param("iii", $celula_id_to_add, $user_id_to_add, $church_id);
                if ($stmt_assign->execute()) {
                    $message = "Membro adicionado à célula com sucesso!";
                } else {
                    $error = "Erro ao adicionar membro à célula.";
                }
                $stmt_assign->close();
            } else {
                $error = "Erro ao preparar a atribuição do membro.";
            }
        } else {
            $error = "Não tem permissão para adicionar membros a esta célula.";
        }
    }
    // --- Ação para REMOVER Membro da Célula (CORRIGIDO) ---
    elseif ($action === 'remove_member') {
        $member_id_to_remove = (int)($_POST['member_id_remove'] ?? 0);
        
        $can_remove = false;
        if ($user_role === 'master_admin') {
            $can_remove = true;
        } elseif ($user_role === 'lider') {
            $stmt_check = $conn->prepare("SELECT celula_id FROM users WHERE id = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("i", $member_id_to_remove);
                if ($stmt_check->execute()) {
                    $result = $stmt_check->get_result();
                    if ($result) {
                        $member_data = $result->fetch_assoc();
                        if ($member_data && !empty($member_data['celula_id'])) {
                            $stmt_cel_check = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
                            if ($stmt_cel_check) {
                                $stmt_cel_check->bind_param("ii", $member_data['celula_id'], $user_id);
                                if ($stmt_cel_check->execute()) {
                                    $result_cel = $stmt_cel_check->get_result();
                                    if ($result_cel && $result_cel->num_rows > 0) {
                                        $can_remove = true;
                                    }
                                }
                                $stmt_cel_check->close();
                            }
                        }
                    }
                }
                $stmt_check->close();
            }
        }

        if ($can_remove) {
            $stmt_remove = $conn->prepare("UPDATE users SET celula_id = NULL WHERE id = ?");
            if ($stmt_remove) {
                $stmt_remove->bind_param("i", $member_id_to_remove);
                if ($stmt_remove->execute()) {
                    $message = "Membro removido da célula com sucesso!";
                } else {
                    $error = "Erro ao remover o membro da célula.";
                }
                $stmt_remove->close();
            } else {
                $error = "Erro ao preparar a remoção.";
            }
        } else {
            $error = "Não tem permissão para remover este membro ou o membro não foi encontrado.";
        }
    }
}

$celula = null;
$celula_id_to_view = null;
$lista_celulas = [];
$show_create_form = false;

if ($user_role === 'lider') {
    $stmt_find_celula = $conn->prepare("SELECT id FROM celulas WHERE lider_id = ?");
    if ($stmt_find_celula) {
        $stmt_find_celula->bind_param("i", $user_id);
        $stmt_find_celula->execute();
        $result = $stmt_find_celula->get_result();
        $celula_res = $result ? $result->fetch_assoc() : null;
        if ($celula_res) {
            $celula_id_to_view = $celula_res['id'];
        } else {
            $show_create_form = true;
            if (!isset($_GET['creation_success'])) {
               $error = "Bem-vindo, líder! Parece que ainda não tem uma célula registada. Preencha os dados abaixo para começar.";
            }
        }
        $stmt_find_celula->close();
    } else {
        $error = "Erro ao buscar dados da sua célula.";
    }
} elseif ($user_role === 'master_admin') {
    if (isset($_GET['view_celula_id'])) {
        $celula_id_to_view = (int)$_GET['view_celula_id'];
    } else {
        $stmt_lista = $conn->prepare("SELECT c.id, c.nome, u.name as lider_nome, ch.name as church_name FROM celulas c JOIN users u ON c.lider_id = u.id JOIN churches ch ON c.church_id = ch.id ORDER BY ch.name, c.nome");
        if ($stmt_lista) {
            $stmt_lista->execute();
            $result_lista = $stmt_lista->get_result();
            if ($result_lista) {
                while ($row = $result_lista->fetch_assoc()) {
                    $lista_celulas[] = $row;
                }
            }
            $stmt_lista->close();
        } else {
            $error = "Erro ao buscar a lista de células.";
        }
    }
}

if ($celula_id_to_view) {
    $stmt_celula = $conn->prepare("SELECT * FROM celulas WHERE id = ?");
    if ($stmt_celula) {
        $stmt_celula->bind_param("i", $celula_id_to_view);
        $stmt_celula->execute();
        $result_celula = $stmt_celula->get_result();
        $celula = $result_celula ? $result_celula->fetch_assoc() : null;
        $stmt_celula->close();
    } else {
        $error = "Erro ao preparar a consulta da célula.";
    }
    
    if (!$celula) {
        $error = "Célula não encontrada ou erro na consulta.";
        $celula_id_to_view = null; 
    }
}

$membros_celula = [];
if ($celula) {
    $stmt_membros = $conn->prepare("SELECT id, name, email, phone FROM users WHERE celula_id = ? ORDER BY name ASC");
    if ($stmt_membros) {
        $stmt_membros->bind_param("i", $celula['id']);
        $stmt_membros->execute();
        $result_membros = $stmt_membros->get_result();
        if ($result_membros) {
            while($row = $result_membros->fetch_assoc()) {
                $membros_celula[] = $row;
            }
        }
        $stmt_membros->close();
    } else {
        $error = "Erro ao buscar membros da célula.";
    }
}

if (isset($_GET['creation_success'])) {
    $message = "Sua célula foi criada com sucesso!";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestão de Células - Life Church</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = { theme: { extend: { colors: { primary: "#1976D2", secondary: "#BBDEFB" }, borderRadius: { button: "8px" } } } };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <style>
      body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
      .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; }
      .modal, .dropdown-menu { transition: opacity 0.3s ease; }
      .modal-content { transition: transform 0.3s ease, opacity 0.3s ease; }
      #sidebar { transition: transform 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="lg:flex">
        <aside id="sidebar" class="w-64 h-screen bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-40 transform -translate-x-full lg:translate-x-0">
             <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                 <span class="text-2xl font-['Pacifico'] text-primary">Life Church</span>
                  <button id="close-sidebar-btn" class="lg:hidden text-gray-500 hover:text-gray-800"><i class="ri-close-line ri-xl"></i></button>
             </div>
             <nav class="flex-1 overflow-y-auto py-4">
                  <div class="px-4 mb-6">
                      <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                      <?php if (in_array($_SESSION['user_role'], ['lider', 'master_admin'])): ?>
                          <a href="celulas.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'celulas.php' ? 'active' : ''; ?>"><i class="ri-group-2-line ri-lg mr-3"></i><span>Minha Célula</span></a>
                          <a href="celulas_presencas.php<?php if($user_role === 'master_admin' && $celula) echo '?celula_id='.$celula['id']; ?>" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'celulas_presencas.php' ? 'active' : ''; ?>"><i class="ri-file-list-3-line ri-lg mr-3"></i><span>Registar Atividades</span></a>
                      <?php endif; ?>
                  </div>
                  <div class="px-4">
                      <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
                      <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                      <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
                  </div>
             </nav>
        </aside>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="bg-white border-b border-gray-200 shadow-sm z-20 sticky top-0">
                 <div class="flex items-center justify-between h-16 px-6">
                     <div class="flex items-center">
                         <?php if ($user_role !== 'lider'): ?>
                            <button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary"><i class="ri-menu-line ri-lg"></i></button>
                         <?php endif; ?>
                         <h1 class="text-lg font-medium text-gray-800">Gestão de Células</h1>
                     </div>
                 </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 space-y-6 overflow-y-auto">
                <?php if ($message): ?><div id="alert-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($message); ?></span><button onclick="this.parentElement.style.display='none'"><i class="ri-close-line"></i></button></div><?php endif; ?>
                <?php if ($error): ?><div id="alert-error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($error); ?></span><button onclick="this.parentElement.style.display='none'"><i class="ri-close-line"></i></button></div><?php endif; ?>

                <?php if ($celula): ?>
                       <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-primary">
                           <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                               <div>
                                   <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($celula['nome']); ?></h2>
                                   <div class="mt-2 flex items-center gap-4 text-sm text-gray-600">
                                       <div class="flex items-center gap-2"><i class="ri-calendar-event-line text-primary"></i> <span><?php echo htmlspecialchars($celula['dia_encontro']); ?></span></div>
                                       <div class="flex items-center gap-2"><i class="ri-time-line text-primary"></i> <span><?php echo date('H:i', strtotime($celula['horario'])); ?></span></div>
                                       <div class="flex items-center gap-2"><i class="ri-map-pin-line text-primary"></i> <span><?php echo htmlspecialchars($celula['endereco']); ?></span></div>
                                   </div>
                               </div>
                               <div class="flex items-center gap-2">
                                    <?php if ($user_role === 'master_admin'): ?>
                                       <a href="celulas.php" class="text-sm text-primary hover:underline">&laquo; Voltar à lista</a>
                                   <?php endif; ?>
                                   <a href="celulas_presencas.php?celula_id=<?php echo $celula['id']; ?>" class="bg-secondary text-primary font-bold py-2 px-4 rounded-button hover:bg-blue-200 transition-colors">
                                        Lançar Relatório Mensal
                                   </a>
                               </div>
                           </div>
                       </div>
                       
                       <div class="bg-white rounded-lg shadow-md">
                           <div class="flex flex-col sm:flex-row justify-between sm:items-center p-6 border-b">
                                <h3 class="text-lg font-medium text-gray-900 mb-3 sm:mb-0">Membros da Célula</h3>
                                <button id="openSelectMemberModalBtn" class="bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700 text-sm font-medium w-full sm:w-auto">Adicionar Membro</button>
                           </div>
                            <div class="space-y-4 p-4">
                                <div class="hidden lg:grid grid-cols-12 gap-4 px-4 py-2 text-xs text-gray-700 uppercase bg-gray-50 rounded-lg font-medium">
                                    <div class="col-span-4">Nome</div>
                                    <div class="col-span-4">Email</div>
                                    <div class="col-span-2">Telefone</div>
                                    <div class="col-span-2 text-right">Ações</div>
                                </div>
                                <?php if (empty($membros_celula)): ?>
                                    <div class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                        <i class="ri-user-add-line text-4xl mb-2 block text-gray-300"></i>
                                        Nenhum membro nesta célula.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($membros_celula as $membro): ?>
                                    <!-- Mobile Card / Desktop Row -->
                                    <div class="bg-white border border-gray-200 rounded-lg p-4 lg:grid lg:grid-cols-12 lg:gap-4 lg:items-center hover:bg-gray-50 transition shadow-sm lg:shadow-none">
                                        <!-- Mobile Header -->
                                        <div class="flex items-center justify-between lg:hidden mb-3 pb-3 border-b border-gray-100">
                                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($membro['name']); ?></span>
                                            <button class="remove-member-btn text-red-600 bg-red-50 p-2 rounded-full hover:bg-red-100" data-id="<?php echo $membro['id']; ?>" data-name="<?php echo htmlspecialchars($membro['name']); ?>" title="Remover da Célula">
                                                 <i class="ri-user-unfollow-line"></i>
                                            </button>
                                        </div>

                                        <!-- Desktop Fields -->
                                        <div class="col-span-4 hidden lg:block font-medium text-gray-900"><?php echo htmlspecialchars($membro['name']); ?></div>
                                        
                                        <!-- Shared Fields -->
                                        <div class="col-span-4 text-sm text-gray-600 mb-1 lg:mb-0 flex items-center lg:block">
                                            <i class="ri-mail-line lg:hidden mr-2 text-gray-400"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($membro['email']); ?></span>
                                        </div>
                                        <div class="col-span-2 text-sm text-gray-600 lg:mb-0 flex items-center lg:block">
                                            <i class="ri-phone-line lg:hidden mr-2 text-gray-400"></i>
                                            <span><?php echo htmlspecialchars($membro['phone']); ?></span>
                                        </div>

                                        <!-- Desktop Actions -->
                                        <div class="col-span-2 text-right hidden lg:block">
                                             <button class="remove-member-btn text-red-600 hover:text-red-800 p-2 rounded-full hover:bg-red-50 transition" data-id="<?php echo $membro['id']; ?>" data-name="<?php echo htmlspecialchars($membro['name']); ?>" title="Remover da Célula">
                                                 <i class="ri-user-unfollow-line"></i>
                                             </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                       </div>
                
                <?php elseif ($show_create_form): ?>
                       <div class="bg-white p-6 rounded-lg shadow-md">
                           <h3 class="text-lg font-medium text-gray-900 mb-4">Criar Minha Célula</h3>
                           <form method="POST" action="celulas.php" class="space-y-4">
                               <input type="hidden" name="action" value="create_celula">
                               <div><label for="nome_celula" class="block text-sm font-medium text-gray-700">Nome da Célula</label><input type="text" name="nome_celula" id="nome_celula" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"></div>
                               <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                   <div><label for="dia_encontro" class="block text-sm font-medium text-gray-700">Dia da Reunião</label><select name="dia_encontro" id="dia_encontro" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"><option value="Monday">Segunda-feira</option><option value="Tuesday">Terça-feira</option><option value="Wednesday">Quarta-feira</option><option value="Thursday">Quinta-feira</option><option value="Friday">Sexta-feira</option><option value="Saturday">Sábado</option><option value="Sunday">Domingo</option></select></div>
                                   <div><label for="horario" class="block text-sm font-medium text-gray-700">Horário</label><input type="time" name="horario" id="horario" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"></div>
                               </div>
                               <div><label for="endereco" class="block text-sm font-medium text-gray-700">Endereço da Reunião</label><input type="text" name="endereco" id="endereco" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"></div>
                               <div class="text-right"><button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-6 border border-transparent rounded-button shadow-sm font-medium text-white bg-primary hover:bg-blue-700">Criar Célula</button></div>
                           </form>
                       </div>

                <?php elseif ($user_role === 'master_admin'): ?>
                       <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                           <h3 class="text-lg font-medium text-gray-900 border-b pb-4 mb-4">Lista de Todas as Células</h3>
                           <div class="space-y-4">
                                <div class="hidden md:grid grid-cols-4 gap-4 px-4 py-2 text-xs text-gray-700 uppercase bg-gray-50 rounded-lg font-medium">
                                    <div class="col-span-1">Nome da Célula</div>
                                    <div class="col-span-1">Líder</div>
                                    <div class="col-span-1">Igreja</div>
                                    <div class="col-span-1 text-right">Ação</div>
                                </div>
                                <?php if (empty($lista_celulas)): ?>
                                    <div class="text-center py-4 text-gray-500">Nenhuma célula encontrada.</div>
                                <?php else: ?>
                                    <?php foreach ($lista_celulas as $item_celula): ?>
                                    <!-- Mobile Card -->
                                    <div class="bg-white border border-gray-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 md:gap-4 md:items-center hover:bg-gray-50 transition shadow-sm md:shadow-none">
                                        <div class="col-span-1 mb-2 md:mb-0">
                                            <span class="font-bold md:hidden block text-xs text-gray-500 uppercase mb-1">Célula</span>
                                            <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($item_celula['nome']); ?></span>
                                        </div>
                                        <div class="col-span-1 mb-2 md:mb-0">
                                            <span class="font-bold md:hidden block text-xs text-gray-500 uppercase mb-1">Líder</span>
                                            <div class="flex items-center">
                                                <i class="ri-user-star-line text-primary mr-2 md:hidden"></i>
                                                <?php echo htmlspecialchars($item_celula['lider_nome']); ?>
                                            </div>
                                        </div>
                                        <div class="col-span-1 mb-2 md:mb-0">
                                            <span class="font-bold md:hidden block text-xs text-gray-500 uppercase mb-1">Igreja</span>
                                            <span class="text-sm bg-blue-50 text-blue-700 px-2 py-1 rounded-full"><?php echo htmlspecialchars($item_celula['church_name']); ?></span>
                                        </div>
                                        <div class="col-span-1 text-right mt-3 md:mt-0 pt-3 md:pt-0 border-t md:border-t-0">
                                            <a href="celulas.php?view_celula_id=<?php echo $item_celula['id']; ?>" class="w-full md:w-auto block md:inline-block text-center bg-white border border-primary text-primary hover:bg-primary hover:text-white transition-colors duration-200 py-2 px-4 rounded-button text-sm font-medium">Ver Detalhes</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                           </div>
                       </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Modal para Selecionar Membro -->
    <div id="selectMemberModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-end sm:items-center justify-center hidden p-0 sm:p-4">
        <div class="modal-content bg-white w-full max-w-lg rounded-t-xl sm:rounded-lg shadow-xl transform scale-100 opacity-0 transition-all duration-300 h-[80vh] sm:h-auto flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-medium">Selecionar Membro</h3>
                <button id="closeSelectMemberModal" class="text-gray-500 hover:text-gray-800 p-2"><i class="ri-close-line ri-xl"></i></button>
            </div>
            <div class="p-4 border-b bg-gray-50">
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" id="memberSearchInput" placeholder="Pesquisar por nome ou email..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            <div id="available-members-list" class="p-4 overflow-y-auto space-y-2 flex-1">
                <!-- Lista de membros será preenchida por JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal para Confirmar Remoção -->
    <div id="removeConfirmationModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0">
            <div class="p-6">
                <h3 class="text-lg font-semibold">Confirmar Remoção</h3>
                <p class="mt-2 text-sm text-gray-600">Tem a certeza que deseja remover o membro <strong id="member_name_to_remove"></strong> desta célula? Esta ação não apaga o utilizador do sistema.</p>
            </div>
            <div class="flex justify-end items-center p-4 border-t bg-gray-50 rounded-b-lg space-x-3">
                <button type="button" id="cancelRemove" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-button hover:bg-gray-300">Cancelar</button>
                <form id="removeMemberForm" method="POST" action="celulas.php?view_celula_id=<?php echo htmlspecialchars($celula_id_to_view ?? ''); ?>">
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="member_id_remove" id="member_id_to_remove">
                    <button type="submit" class="bg-red-600 text-white py-2 px-4 rounded-button hover:bg-red-700">Confirmar Remoção</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('open-sidebar-btn');
        const closeBtn = document.getElementById('close-sidebar-btn');
        const overlay = document.getElementById('sidebar-overlay');
        const showSidebar = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
        const hideSidebar = () => { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); };
        if(openBtn) openBtn.addEventListener('click', showSidebar);
        if(closeBtn) closeBtn.addEventListener('click', hideSidebar);
        if(overlay) overlay.addEventListener('click', hideSidebar);

        // --- LÓGICA DO MODAL DE SELEÇÃO DE MEMBROS ---
        const selectMemberModal = document.getElementById('selectMemberModal');
        const openSelectMemberModalBtn = document.getElementById('openSelectMemberModalBtn');
        const closeSelectMemberModalBtn = document.getElementById('closeSelectMemberModal');
        const memberSearchInput = document.getElementById('memberSearchInput');
        const availableMembersList = document.getElementById('available-members-list');
        const celulaId = <?php echo json_encode($celula_id_to_view); ?>;

        async function fetchAvailableMembers(searchTerm = '') {
            availableMembersList.innerHTML = '<p class="text-center text-gray-500">A pesquisar...</p>';
            try {
                const response = await fetch(`celulas.php?action=get_available_members&search=${encodeURIComponent(searchTerm)}`);
                const users = await response.json();
                
                availableMembersList.innerHTML = '';
                if (users.length > 0) {
                    users.forEach(user => {
                        const userDiv = document.createElement('div');
                        userDiv.className = 'p-3 border rounded-lg hover:bg-gray-100 cursor-pointer flex justify-between items-center';
                        userDiv.innerHTML = `
                            <div>
                                <p class="font-medium">${user.name}</p>
                                <p class="text-sm text-gray-500">${user.email}</p>
                            </div>
                            <button class="assign-member-btn bg-primary text-white text-xs py-1 px-3 rounded-full hover:bg-blue-700" data-user-id="${user.id}">Adicionar</button>
                        `;
                        availableMembersList.appendChild(userDiv);
                    });
                } else {
                    availableMembersList.innerHTML = '<p class="text-center text-gray-500">Nenhum membro disponível encontrado.</p>';
                }
            } catch (e) {
                availableMembersList.innerHTML = '<p class="text-center text-red-500">Erro ao carregar membros.</p>';
            }
        }

        async function assignMemberToCell(userId) {
            const formData = new FormData();
            formData.append('action', 'assign_member');
            formData.append('user_id', userId);
            formData.append('celula_id', celulaId);

            try {
                const response = await fetch('celulas.php?view_celula_id='+celulaId, {
                    method: 'POST',
                    body: formData
                });
                window.location.reload();
            } catch (e) {
                alert('Ocorreu um erro de comunicação.');
            }
        }

        if (openSelectMemberModalBtn) {
            openSelectMemberModalBtn.addEventListener('click', () => {
                selectMemberModal.classList.remove('hidden');
                setTimeout(() => selectMemberModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);
                fetchAvailableMembers();
            });
        }

        const hideSelectMemberModal = () => {
            selectMemberModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
            setTimeout(() => selectMemberModal.classList.add('hidden'), 300);
        };

        if (closeSelectMemberModalBtn) {
            closeSelectMemberModalBtn.addEventListener('click', hideSelectMemberModal);
        }

        let searchTimeout;
        if (memberSearchInput) {
            memberSearchInput.addEventListener('keyup', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetchAvailableMembers(memberSearchInput.value);
                }, 300);
            });
        }
        
        if (availableMembersList) {
            availableMembersList.addEventListener('click', (e) => {
                if (e.target.classList.contains('assign-member-btn')) {
                    const userId = e.target.dataset.userId;
                    assignMemberToCell(userId);
                }
            });
        }

        // --- LÓGICA DO MODAL DE REMOÇÃO ---
        const removeModal = document.getElementById('removeConfirmationModal');
        const cancelRemoveBtn = document.getElementById('cancelRemove');
        const memberNameToRemoveEl = document.getElementById('member_name_to_remove');
        const memberIdToRemoveInput = document.getElementById('member_id_to_remove');

        document.querySelectorAll('.remove-member-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const memberId = button.dataset.id;
                const memberName = button.dataset.name;
                
                memberNameToRemoveEl.textContent = memberName;
                memberIdToRemoveInput.value = memberId;
                
                removeModal.classList.remove('hidden');
                setTimeout(() => removeModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);
            });
        });

        const hideRemoveModal = () => {
            removeModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
            setTimeout(() => removeModal.classList.add('hidden'), 300);
        };

        if (cancelRemoveBtn) {
            cancelRemoveBtn.addEventListener('click', hideRemoveModal);
        }
        
        setTimeout(() => {
            const alertMsg = document.getElementById('alert-message');
            const alertErr = document.getElementById('alert-error');
            if(alertMsg) alertMsg.style.display = 'none';
            if(alertErr) alertErr.style.display = 'none';
        }, 5000);
    });
    </script>
    <!-- Mobile Bottom Navigation -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around items-center h-16 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
        <a href="celulas.php" class="flex flex-col items-center justify-center w-full h-full text-primary bg-blue-50 border-t-2 border-primary transition-colors">
            <div class="mb-1"><i class="ri-group-2-line text-xl"></i></div>
            <span class="text-[10px] font-medium leading-none">Célula</span>
        </a>
        <a href="celulas_presencas.php<?php if($user_role === 'master_admin' && isset($celula_id_to_view)) echo '?celula_id='.$celula_id_to_view; ?>" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary hover:bg-gray-50 transition-colors">
            <div class="mb-1"><i class="ri-file-list-3-line text-xl"></i></div>
            <span class="text-[10px] font-medium leading-none">Atividades</span>
        </a>
        <a href="settings.php" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary hover:bg-gray-50 transition-colors">
            <div class="mb-1"><i class="ri-settings-3-line text-xl"></i></div>
            <span class="text-[10px] font-medium leading-none">Definições</span>
        </a>
    </nav>
</body>
</html>
