<?php
session_start();

// Verificar se o usuário está logado
//if (!isset($_SESSION['user_id'])) {
  //  header('Location: ../login.php');
    //exit;
//}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$message = '';
$error = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $city = $_POST['city'] ?? '';
        
        if ($name && $email) {
            $conn = connect_db();
            
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, city = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $city, $user_id);
            
            if ($stmt->execute()) {
                // Atualiza os dados da sessão para refletir a mudança imediatamente
                $_SESSION['user_name'] = $name;
                $user_name = $name; // Atualiza a variável local também
                $message = 'Perfil atualizado com sucesso!';
            } else {
                $error = 'Erro ao atualizar perfil. O email já pode estar em uso.';
            }
            
            $stmt->close();
            $conn->close();
        } else {
            $error = 'Por favor, preencha pelo menos o nome e email.';
        }
    } elseif ($action === 'change_password') {
        $current_password_input = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($current_password_input && $new_password && $confirm_password) {
            if ($new_password === $confirm_password) {
                $conn = connect_db();
                
                // Verificar senha atual
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if ($user_data && password_verify($current_password_input, $user_data['password'])) {
                    // Atualizar senha com hash
                    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_password_hashed, $user_id);
                    
                    if ($stmt_update->execute()) {
                        $message = 'Senha alterada com sucesso!';
                    } else {
                        $error = 'Erro ao alterar senha.';
                    }
                    $stmt_update->close();
                } else {
                    $error = 'Senha atual incorreta.';
                }
                
                $stmt->close();
                $conn->close();
            } else {
                $error = 'As novas senhas não coincidem.';
            }
        } else {
            $error = 'Por favor, preencha todos os campos de senha.';
        }
    }
}

// Buscar dados do usuário para preencher o formulário
$conn = connect_db();
$stmt = $conn->prepare("SELECT name, email, phone, city FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Definições</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
        .sidebar-item { transition: all 0.2s ease; }
        .sidebar-item:hover:not(.active) { background-color: #F0F7FF; }
        .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
        .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
        .card { transition: all 0.2s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .dropdown-menu { transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out; }
        .sidebar { transition: transform 0.3s ease-in-out; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">
    
    <?php if ($user_role !== 'lider'): ?>
    <aside id="sidebar" class="sidebar w-64 h-full bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed md:relative -translate-x-full md:translate-x-0 z-40">
        <div class="p-6 border-b border-gray-100 flex items-center">
            <span class="text-2xl font-['Pacifico'] text-primary">Life Church</span>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <div class="px-4 mb-6">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
            <?php $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
            <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
            <a href="entries.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-arrow-right-circle-line ri-lg mr-3"></i><span>Entradas</span></a>
            <a href="expenses.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-arrow-left-circle-line ri-lg mr-3"></i><span>Saídas</span></a>
            <a href="members.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-group-line ri-lg mr-3"></i><span>Membros</span></a>
            <a href="attendance.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-check-double-line ri-lg mr-3"></i><span>Presenças</span></a>
            <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
            <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
            <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
            </div>
            <div class="px-4">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
            <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
            <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <div class="flex items-center p-2">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary font-bold text-lg mr-3"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <div>
                <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-xs text-gray-500"><?php echo ucfirst(htmlspecialchars($user_role)); ?></p>
            </div>
            </div>
        </div>
    </aside>
    <?php endif; ?>

    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-gray-200 shadow-sm z-10">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <?php if ($user_role !== 'lider'): ?>
                        <button id="hamburger-menu" class="md:hidden mr-4 text-gray-600"><i class="ri-menu-line ri-xl"></i></button>
                    <?php endif; ?>
                    <h1 class="text-lg font-medium text-gray-800">Definições</h1>
                </div>
                <!-- User Dropdown -->
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

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-gray-50">
            <?php if ($message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?php echo htmlspecialchars($message); ?></p></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>

            <div class="bg-white rounded-lg shadow-sm p-6 mb-6 card">
                <h2 class="text-lg font-medium text-gray-800 mb-4">Atualizar Perfil</h2>
                <form method="POST" action="settings.php" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">Cidade</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city']); ?>" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            Atualizar Perfil
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6 card">
                <h2 class="text-lg font-medium text-gray-800 mb-4">Alterar Senha</h2>
                <form method="POST" action="settings.php" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Senha Atual</label>
                        <input type="password" id="current_password" name="current_password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">Nova Senha</label>
                            <input type="password" id="new_password" name="new_password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar Nova Senha</label>
                            <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            Alterar Senha
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($user_role === 'lider'): ?>
                <div class="mt-8 mb-20 lg:mb-8">
                     <a href="logout.php" class="flex items-center justify-center w-full bg-red-50 text-red-600 font-bold py-4 rounded-xl border-2 border-red-100 hover:bg-red-100 transition-colors shadow-sm">
                        <i class="ri-logout-box-line mr-2 text-xl"></i> Sair
                    </a>
                </div>
            <?php endif; ?>

        </main>
    </div>
    <script id="main-script">
        document.addEventListener("DOMContentLoaded", function () {
            const userMenuButton = document.getElementById("user-menu-button");
            const userMenu = document.getElementById("user-menu");
            if(userMenuButton) {
                userMenuButton.addEventListener("click", (event) => { userMenu.classList.toggle("hidden"); event.stopPropagation(); });
                document.addEventListener("click", (event) => { if (userMenu && !userMenu.contains(event.target) && !userMenuButton.contains(event.target)) { userMenu.classList.add("hidden"); } });
            }

            // --- Lógica do Menu Responsivo ---
            const sidebar = document.getElementById('sidebar');
            const hamburgerMenu = document.getElementById('hamburger-menu');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', () => {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                });
            }
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }
        });
    </script>
    <?php if ($user_role === 'lider'): ?>
        <?php
        // Fetch User's Cell ID for the nav link
        $conn = connect_db();
        $stmt_cel = $conn->prepare("SELECT id FROM celulas WHERE lider_id = ?");
        $stmt_cel->bind_param("i", $user_id);
        $stmt_cel->execute();
        $res_cel = $stmt_cel->get_result();
        $celula_data = $res_cel->fetch_assoc();
        $conn->close();
        ?>
        <!-- Mobile Bottom Navigation -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around items-center h-16 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
            <a href="celulas.php" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary hover:bg-gray-50 transition-colors">
                <div class="mb-1"><i class="ri-group-2-line text-xl"></i></div>
                <span class="text-[10px] font-medium leading-none">Célula</span>
            </a>
            <a href="celulas_presencas.php<?php if($celula_data) echo '?celula_id='.$celula_data['id']; ?>" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary hover:bg-gray-50 transition-colors">
                <div class="mb-1"><i class="ri-file-list-3-line text-xl"></i></div>
                <span class="text-[10px] font-medium leading-none">Atividades</span>
            </a>
            <a href="settings.php" class="flex flex-col items-center justify-center w-full h-full text-primary bg-blue-50 border-t-2 border-primary transition-colors">
                <div class="mb-1"><i class="ri-settings-3-line text-xl"></i></div>
                <span class="text-[10px] font-medium leading-none">Definições</span>
            </a>
        </nav>
    <?php endif; ?>
</body>
</html>
