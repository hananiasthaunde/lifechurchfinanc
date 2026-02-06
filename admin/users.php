<?php
session_start();

// --- Segurança e Configuração ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// --- Verificações de Acesso ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}
$user_role = $_SESSION['user_role'];
if (!in_array($user_role, ['master_admin', 'pastor_principal'])) {
    $_SESSION['error_message'] = 'Acesso negado.';
    if ($user_role === 'lider') {
        header('Location: celulas.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$conn = connect_db();
$message = '';
$error = '';
$church_id_filter = $_SESSION['church_id']; // ID da igreja do pastor logado

// --- Lógica de Ações (Aprovar, Rejeitar, Mudar Função) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_to_manage_id = (int)$_POST['user_id'];
    
    // Ação de Aprovar
    if (isset($_POST['approve'])) {
        $sql_approve = "UPDATE users SET is_approved = 1 WHERE id = ?";
        if ($user_role === 'pastor_principal') $sql_approve .= " AND church_id = ?";
        
        $stmt = $conn->prepare($sql_approve);
        if ($user_role === 'pastor_principal') $stmt->bind_param("ii", $user_to_manage_id, $church_id_filter);
        else $stmt->bind_param("i", $user_to_manage_id);
        
        if ($stmt->execute()) $message = "Utilizador aprovado com sucesso!";
        else $error = "Erro ao aprovar utilizador.";
        $stmt->close();
    }
    // Ação de Mudar Função (Apenas Master Admin)
    elseif (isset($_POST['change_role']) && $user_role === 'master_admin') {
        $new_role = $_POST['new_role'];
        $allowed_roles = ['membro', 'lider', 'pastor', 'pastor_principal', 'master_admin'];
        if (in_array($new_role, $allowed_roles)) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_to_manage_id);
            if ($stmt->execute()) $message = "Função atualizada com sucesso!";
            else $error = "Erro ao atualizar função.";
            $stmt->close();
        } else {
            $error = "Função inválida.";
        }
    }
}

// --- Lógica de Busca e Filtro ---
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all'; // 'all', 'approved', 'pending'

$base_sql = "SELECT u.id, u.name, u.email, u.role, u.is_approved, u.created_at, c.name as church_name 
             FROM users u 
             LEFT JOIN churches c ON u.church_id = c.id";

$conditions = [];
$params = [];
$types = '';

// Filtro de permissão
if ($user_role === 'pastor_principal') {
    $conditions[] = "u.church_id = ?";
    $params[] = $church_id_filter;
    $types .= 'i';
}

// Filtro de pesquisa
if (!empty($search_term)) {
    $conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'ss';
}

// Filtro de status
if ($status_filter === 'approved') {
    $conditions[] = "u.is_approved = 1";
} elseif ($status_filter === 'pending') {
    $conditions[] = "u.is_approved = 0";
}

if (!empty($conditions)) {
    $base_sql .= " WHERE " . implode(' AND ', $conditions);
}

$base_sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($base_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestão de Utilizadores - Life Church</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = {
        theme: { extend: { colors: { primary: "#3B82F6", secondary: "#BFDBFE" } } },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <style>
      body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
      .table-header { background-color: #f1f5f9; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="lg:flex">
        <aside class="w-64 h-screen bg-white shadow-md flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-40 transform -translate-x-full lg:translate-x-0">
            <!-- (Código da sua Sidebar/Menu Lateral aqui) -->
        </aside>

        <div class="flex-1 flex flex-col w-full">
            <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
                <div class="flex items-center justify-between h-16 px-6">
                    <h1 class="text-xl font-semibold text-gray-800">Gestão de Utilizadores</h1>
                    <!-- (Pode adicionar o menu do utilizador aqui) -->
                </div>
            </header>

            <main class="flex-1 p-6 space-y-6">
                <!-- Filtros e Pesquisa -->
                <div class="bg-white p-4 rounded-lg shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                    <form method="GET" action="users.php" class="flex-grow w-full md:w-auto">
                        <div class="relative">
                            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" placeholder="Pesquisar por nome ou email..." value="<?php echo htmlspecialchars($search_term); ?>" class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </form>
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <span class="text-sm font-medium text-gray-600">Ver:</span>
                        <a href="?status=all&search=<?php echo htmlspecialchars($search_term); ?>" class="px-3 py-1 text-sm rounded-full <?php echo $status_filter === 'all' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'; ?>">Todos</a>
                        <a href="?status=approved&search=<?php echo htmlspecialchars($search_term); ?>" class="px-3 py-1 text-sm rounded-full <?php echo $status_filter === 'approved' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'; ?>">Aprovados</a>
                        <a href="?status=pending&search=<?php echo htmlspecialchars($search_term); ?>" class="px-3 py-1 text-sm rounded-full <?php echo $status_filter === 'pending' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'; ?>">Pendentes</a>
                    </div>
                </div>

                <!-- Tabela de Utilizadores -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-600">
                            <thead class="table-header text-xs text-gray-700 uppercase">
                                <tr>
                                    <th class="px-6 py-3">Nome</th>
                                    <th class="px-6 py-3">Igreja</th>
                                    <th class="px-6 py-3">Função</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3 text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result->num_rows === 0): ?>
                                    <tr><td colspan="5" class="text-center py-10 text-gray-500">Nenhum utilizador encontrado.</td></tr>
                                <?php else: ?>
                                    <?php while($user = $users_result->fetch_assoc()): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full bg-secondary text-primary flex items-center justify-center font-bold text-lg mr-4 flex-shrink-0">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['church_name'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 uppercase font-medium text-xs"><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($user['is_approved']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
                                                    <i class="ri-checkbox-circle-fill"></i> Ativo
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-yellow-700 bg-yellow-100 rounded-full">
                                                    <i class="ri-time-fill"></i> Pendente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <form method="POST" action="users.php" class="flex items-center justify-center gap-2">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <?php if (!$user['is_approved']): ?>
                                                    <button type="submit" name="approve" class="px-3 py-1 text-xs font-medium text-white bg-green-500 rounded-full hover:bg-green-600">Aprovar</button>
                                                <?php elseif ($user_role === 'master_admin'): ?>
                                                    <select name="new_role" class="block rounded-md border-gray-300 shadow-sm text-xs py-1 w-32">
                                                        <option value="membro" <?php if($user['role'] == 'membro') echo 'selected'; ?>>Membro</option>
                                                        <option value="lider" <?php if($user['role'] == 'lider') echo 'selected'; ?>>Líder</option>
                                                        <option value="pastor" <?php if($user['role'] == 'pastor') echo 'selected'; ?>>Pastor</option>
                                                        <option value="pastor_principal" <?php if($user['role'] == 'pastor_principal') echo 'selected'; ?>>Pastor Principal</option>
                                                        <option value="master_admin" <?php if($user['role'] == 'master_admin') echo 'selected'; ?>>Master Admin</option>
                                                    </select>
                                                    <button type="submit" name="change_role" class="px-3 py-1 text-xs font-medium text-white bg-gray-600 rounded-full hover:bg-gray-700">Guardar</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
