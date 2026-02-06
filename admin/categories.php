<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
//    header('Location: ../login.php');
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

//require_once __DIR__ . '/../includes/config.php';
//require_once ROOT_PATH . '/includes/config.php';
//require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// --- CONTROLE DE ACESSO ---
if ($user_role === 'lider') {
    header('Location: celulas.php');
    exit;
}

$message = '';
$error = '';

// A condição de permissão que causava o redirecionamento foi removida, conforme solicitado.

$conn = connect_db();

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    // Adicionar Categoria
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? '';
        if ($name && $type) {
            $stmt = $conn->prepare("INSERT INTO categories (name, type) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $type);
            if ($stmt->execute()) {
                $message = 'Categoria adicionada com sucesso!';
            } else {
                $error = 'Erro ao adicionar categoria. A categoria já pode existir.';
            }
            $stmt->close();
        } else {
            $error = 'Por favor, preencha todos os campos.';
        }
    }
    // Editar Categoria
    elseif ($action === 'edit') {
        $id = $_POST['category_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? '';
        if ($id && $name && $type) {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $type, $id);
            if ($stmt->execute()) {
                $message = 'Categoria atualizada com sucesso!';
            } else {
                $error = 'Erro ao atualizar a categoria.';
            }
            $stmt->close();
        } else {
             $error = 'Dados inválidos para a edição.';
        }
    }
    // Apagar Categoria
    elseif ($action === 'delete') {
        $id = $_POST['category_id'] ?? 0;
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = 'Categoria apagada com sucesso!';
            } else {
                $error = 'Erro ao apagar a categoria. Verifique se não está a ser usada em alguma transação.';
            }
            $stmt->close();
        } else {
            $error = 'ID da categoria inválido.';
        }
    }
}

// Buscar todas as categorias para exibir na tabela
$categories = [];
$stmt_select = $conn->prepare("SELECT id, name, type FROM categories ORDER BY type, name");
$stmt_select->execute();
$result = $stmt_select->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
$stmt_select->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Categorias</title>
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
        .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
        .modal { transition: opacity 0.3s ease; }
        .modal-content { transition: transform 0.3s ease; }
        .dropdown-menu { transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out; }
        .sidebar { transition: transform 0.3s ease-in-out; }

        @media screen and (max-width: 768px) {
            .responsive-table thead {
                display: none;
            }
            .responsive-table, .responsive-table tbody, .responsive-table tr, .responsive-table td {
                display: block;
                width: 100%;
            }
            .responsive-table tr {
                margin-bottom: 1rem;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 1rem;
            }
            .responsive-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right;
                padding: 0.75rem 0.5rem;
                border-bottom: 1px solid #eee;
            }
            .responsive-table td:last-child {
                border-bottom: 0;
            }
            .responsive-table td[data-label]::before {
                content: attr(data-label);
                font-weight: 600;
                text-align: left;
                padding-right: 1rem;
                color: #374151;
            }
        }
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
                <a href="reports.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-file-chart-line ri-lg mr-3"></i><span>Relatórios</span></a>
                <a href="budget.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-calculator-line ri-lg mr-3"></i><span>Budget</span></a>
                <a href="categories.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-bookmark-line ri-lg mr-3"></i><span>Categorias</span></a>
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
                    <button id="hamburger-menu" class="md:hidden mr-4 text-gray-600"><i class="ri-menu-line ri-xl"></i></button>
                    <h1 class="text-lg font-medium text-gray-800">Gestão de Categorias</h1>
                </div>
                <div class="relative">
                    <button id="user-menu-button" class="flex items-center space-x-2 rounded-full p-1 hover:bg-gray-100 transition-colors"><span class="hidden sm:inline text-sm font-medium text-gray-700"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span><div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div></button>
                    <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20 dropdown-menu origin-top-right"><a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-settings-3-line mr-3"></i>Definições</a><a href="logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="ri-logout-box-line mr-3"></i>Sair</a></div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-gray-50">
            
            <?php if ($message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?php echo htmlspecialchars($message); ?></p></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>

            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-medium text-gray-800 mb-4">Adicionar Nova Categoria</h2>
                <form method="POST" action="categories.php" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                    <input type="hidden" name="action" value="add">
                    <div class="md:col-span-1"><label for="name" class="block text-sm font-medium text-gray-700">Nome da Categoria</label><input type="text" id="name" name="name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></div>
                    <div class="md:col-span-1"><label for="type" class="block text-sm font-medium text-gray-700">Tipo</label><select id="type" name="type" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"><option value="">Selecione o tipo</option><option value="entrada">Entrada</option><option value="saida">Saída</option></select></div>
                    <div class="md:col-span-1"><button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">Adicionar Categoria</button></div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow-sm">
                <h2 class="text-lg font-medium text-gray-800 p-6 border-b">Categorias Existentes</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500 responsive-table">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr><th scope="col" class="px-6 py-3">Nome</th><th scope="col" class="px-6 py-3">Tipo</th><th scope="col" class="px-6 py-3 text-right">Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="3" class="px-6 py-4 text-center">Nenhuma categoria encontrada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr class="bg-white border-b hover:bg-gray-50">
                                        <td data-label="Nome" class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td data-label="Tipo" class="px-6 py-4">
                                            <?php if ($category['type'] === 'entrada'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Entrada</span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Saída</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Ações" class="px-6 py-4 text-right">
                                            <button class="edit-btn font-medium text-primary hover:underline" data-id="<?php echo $category['id']; ?>" data-name="<?php echo htmlspecialchars($category['name']); ?>" data-type="<?php echo $category['type']; ?>">Editar</button>
                                            <form method="POST" action="categories.php" class="inline-block ml-4" onsubmit="return confirm('Tem a certeza que deseja apagar esta categoria?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="font-medium text-red-600 hover:underline">Apagar</button>
                                            </form>
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
    
    <!-- Modal de Edição -->
    <div id="editModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md transform scale-95 opacity-0 p-6">
            <form method="POST" action="categories.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="editCategoryId">
                <div class="flex justify-between items-center mb-4 border-b pb-4">
                    <h3 class="text-xl font-medium text-gray-800">Editar Categoria</h3>
                    <button type="button" id="closeEditModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button>
                </div>
                <div class="space-y-4">
                    <div><label for="editCategoryName" class="block text-sm font-medium text-gray-700">Nome da Categoria</label><input type="text" id="editCategoryName" name="name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"></div>
                    <div><label for="editCategoryType" class="block text-sm font-medium text-gray-700">Tipo</label><select id="editCategoryType" name="type" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"><option value="entrada">Entrada</option><option value="saida">Saída</option></select></div>
                </div>
                <div class="pt-6 flex justify-end">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-button shadow-sm text-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script id="main-script">
    document.addEventListener("DOMContentLoaded", function () {
        // Dropdown do utilizador
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

        // Modal de edição
        const editModal = document.getElementById("editModal");
        const closeEditModalBtn = document.getElementById("closeEditModal");
        const editButtons = document.querySelectorAll(".edit-btn");

        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Preencher dados do modal
                document.getElementById('editCategoryId').value = button.dataset.id;
                document.getElementById('editCategoryName').value = button.dataset.name;
                document.getElementById('editCategoryType').value = button.dataset.type;
                
                // Mostrar o modal
                editModal.classList.remove('hidden');
                setTimeout(() => editModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);
            });
        });

        const hideModal = () => {
            editModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
            setTimeout(() => editModal.classList.add('hidden'), 200);
        };

        if(closeEditModalBtn) {
            closeEditModalBtn.addEventListener('click', hideModal);
        }
    });
    </script>
</body>
</html>
