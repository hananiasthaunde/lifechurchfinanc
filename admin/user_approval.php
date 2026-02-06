<?php
session_start();

// --- Segurança e Configuração ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';


// Correção sugerida (mais robusta)

// --- Verificações de Acesso ---
if (!isset($_SESSION['user_id'])) {
 //   header('Location: ../public/login.php');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');

    exit;
}
// AGORA: Acesso permitido para 'master_admin' e 'pastor_principal'
if (!in_array($_SESSION['user_role'], ['master_admin', 'pastor_principal'])) {
    $_SESSION['error_message'] = 'Você não tem permissão para aceder a esta página.';
    if ($_SESSION['user_role'] === 'lider') {
        header('Location: celulas.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$conn = connect_db();
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$church_id_session = $_SESSION['church_id'] ?? null;
$message = '';
$error = '';

// --- Lógica de Aprovação/Rejeição (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_to_manage_id = (int)$_POST['user_id'];

    // Segurança: Master admin pode gerir qualquer um, pastor só pode gerir utilizadores da sua igreja.
    $can_manage = false;
    if ($user_role === 'master_admin') {
        $can_manage = true;
    } else {
        $stmt_check = $conn->prepare("SELECT church_id FROM users WHERE id = ?");
        $stmt_check->bind_param("i", $user_to_manage_id);
        $stmt_check->execute();
        $user_church = $stmt_check->get_result()->fetch_assoc();
        if ($user_church && $user_church['church_id'] == $church_id_session) {
            $can_manage = true;
        }
    }

    if ($can_manage) {
        if (isset($_POST['approve'])) {
            $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_to_manage_id);
            if ($stmt->execute()) {
                $message = "Utilizador aprovado com sucesso!";
            } else {
                $error = "Erro ao aprovar o utilizador.";
            }
            $stmt->close();
        } elseif (isset($_POST['reject'])) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_to_manage_id);
            if ($stmt->execute()) {
                $message = "Utilizador rejeitado e removido com sucesso.";
            } else {
                $error = "Erro ao rejeitar o utilizador.";
            }
            $stmt->close();
        }
    } else {
        $error = "Ação não permitida.";
    }
}


// --- LÓGICA AJAX PARA DETALHES DA IGREJA ---
if (isset($_GET['fetch_details']) && isset($_GET['church_id'])) {
    header('Content-Type: application/json');
    $church_id_details = (int)$_GET['church_id'];
    
    // Segurança: Master admin pode ver qualquer igreja, pastor só a sua.
    if ($user_role === 'pastor_principal' && $church_id_details !== $church_id_session) {
        echo json_encode(['error' => 'Acesso negado.']);
        exit;
    }

    // Buscar membros, finanças e presenças (lógica mantida da versão original)
    $stmt_members = $conn->prepare("SELECT name, email, role FROM users WHERE church_id = ? ORDER BY name ASC");
    $stmt_members->bind_param("i", $church_id_details);
    $stmt_members->execute();
    $members = $stmt_members->get_result()->fetch_all(MYSQLI_ASSOC);

    $financial_summary = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('m', strtotime("-$i month"));
        $year = date('Y', strtotime("-$i month"));
        $month_name = date('M/Y', strtotime("-$i month"));
        
        $stmt_income = $conn->prepare("SELECT SUM(total_offering) as total FROM service_reports WHERE church_id = ? AND MONTH(service_date) = ? AND YEAR(service_date) = ?");
        $stmt_income->bind_param("iss", $church_id_details, $month, $year);
        $stmt_income->execute();
        $income = $stmt_income->get_result()->fetch_assoc()['total'] ?? 0;

        $stmt_expenses = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE church_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
        $stmt_expenses->bind_param("iss", $church_id_details, $month, $year);
        $stmt_expenses->execute();
        $expenses = $stmt_expenses->get_result()->fetch_assoc()['total'] ?? 0;
        
        $financial_summary[] = ['month' => $month_name, 'income' => (float)$income, 'expenses' => (float)$expenses];
    }
    
    echo json_encode(['members' => $members, 'financials' => $financial_summary]);
    $conn->close();
    exit;
}

// --- LÓGICA PARA VISUALIZAÇÃO DA PÁGINA ---

// -- Filtros da Tabela de Igrejas --
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

// -- Filtros e Paginação para Utilizadores Pendentes --
$search_term = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// --- Buscar dados das igrejas (com filtros) ---
$churches_data = [];
if ($user_role === 'master_admin') {
    $sql_churches_params = [$selected_month, $selected_year, $selected_month, $selected_year];
    $sql_churches_types = 'ssss';

    $sql_churches = "SELECT 
                        ch.id, ch.name, ch.balance, 
                        (SELECT COUNT(*) FROM users u WHERE u.church_id = ch.id) as member_count, 
                        (SELECT COALESCE(SUM(sr.total_offering), 0) FROM service_reports sr WHERE sr.church_id = ch.id AND MONTH(sr.service_date) = ? AND YEAR(sr.service_date) = ?) as monthly_income,
                        (SELECT COALESCE(SUM(ex.amount), 0) FROM expenses ex WHERE ex.church_id = ch.id AND MONTH(ex.transaction_date) = ? AND YEAR(ex.transaction_date) = ?) as monthly_expenses
                     FROM churches ch
                     ORDER BY ch.name ASC";
    $stmt_churches = $conn->prepare($sql_churches);
    $stmt_churches->bind_param($sql_churches_types, ...$sql_churches_params);
    $stmt_churches->execute();
    $result_churches = $stmt_churches->get_result();
    while($row = $result_churches->fetch_assoc()) {
        $churches_data[] = $row;
    }
    $stmt_churches->close();
}


// --- Buscar Utilizadores Pendentes (com filtros e paginação) ---
$pending_users = [];
$where_pending_conditions = ["u.is_approved = 0"];
$pending_params = [];
$pending_types = '';

if ($user_role === 'pastor_principal') {
    $where_pending_conditions[] = "u.church_id = ?";
    $pending_params[] = $church_id_session;
    $pending_types .= 'i';
}
if (!empty($search_term)) {
    $where_pending_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $like_term = "%{$search_term}%";
    $pending_params[] = $like_term;
    $pending_params[] = $like_term;
    $pending_types .= 'ss';
}

$where_pending_sql = "WHERE " . implode(" AND ", $where_pending_conditions);

// Contar total de registos para paginação
$sql_count = "SELECT COUNT(*) as total FROM users u {$where_pending_sql}";
$stmt_count = $conn->prepare($sql_count);
if(!empty($pending_params)) $stmt_count->bind_param($pending_types, ...$pending_params);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// Buscar registos da página atual
$sql_pending = "SELECT u.id, u.name, u.email, u.created_at, c.name as church_name 
                FROM users u 
                LEFT JOIN churches c ON u.church_id = c.id 
                {$where_pending_sql}
                ORDER BY u.created_at ASC
                LIMIT ? OFFSET ?";
$pending_params[] = $records_per_page;
$pending_params[] = $offset;
$pending_types .= 'ii';

$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param($pending_types, ...$pending_params);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
while ($row = $result_pending->fetch_assoc()) {
    $pending_users[] = $row;
}
$stmt_pending->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Painel do Administrador - Life Church</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: { colors: { primary: "#1976D2", secondary: "#BBDEFB" }, borderRadius: { button: "8px" } },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <style>
      body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
      .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; border-radius: 4px 0 0 4px; }
      #sidebar { transition: transform 0.3s ease-in-out; }
      .modal { transition: opacity 0.3s ease-in-out; }
      .modal-content { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="lg:flex">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 h-screen bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-40 transform -translate-x-full lg:translate-x-0">
            <div class="p-6 border-b border-gray-100"><span class="text-2xl font-['Pacifico'] text-primary">Life Church</span></div>
            <nav class="flex-1 overflow-y-auto py-4">
                 <div class="px-4 mb-6">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                    <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
                 </div>
                <div class="px-4">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Administração</p>
                    <a href="user_approval.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-shield-user-line ri-lg mr-3"></i><span>Painel Admin</span></a>
                    <?php if ($user_role === 'master_admin'): ?>
                    <a href="users.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-user-settings-line ri-lg mr-3"></i><span>Gerir Utilizadores</span></a>
                    <?php endif; ?>
                    <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                    <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col w-full">
            <header class="bg-white border-b border-gray-200 shadow-sm z-20 sticky top-0">
                 <div class="flex items-center justify-between h-16 px-6">
                    <h1 class="text-lg font-medium text-gray-800">Painel do Administrador</h1>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 space-y-8">
                <?php if ($message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if ($user_role === 'master_admin'): ?>
                <!-- Visão Geral das Igrejas (SOMENTE MASTER ADMIN) -->
                <div class="bg-white rounded-lg shadow-lg">
                    <div class="p-6 border-b">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                             <div>
                                <h2 class="text-lg font-medium text-gray-800">Visão Geral das Igrejas</h2>
                                <p class="text-sm text-gray-500">Estatísticas de todas as igrejas registadas no sistema.</p>
                             </div>
                             <!-- Filtro de Mês e Ano -->
                             <form method="GET" action="user_approval.php" class="flex items-center gap-3 mt-4 sm:mt-0">
                                <label for="month" class="text-sm font-medium text-gray-700">Período:</label>
                                <select name="month" id="month" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                    <?php for($m=1; $m<=12; $m++): $month_num = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $month_num; ?>" <?php if($month_num == $selected_month) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m, 1)); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select name="year" id="year" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm">
                                    <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php if($y == $selected_year) echo 'selected'; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-button hover:bg-blue-700 flex items-center gap-1">
                                    <i class="ri-filter-3-line"></i><span>Filtrar</span>
                                </button>
                             </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3">Igreja</th>
                                    <th class="px-6 py-3 text-center">Membros</th>
                                    <th class="px-6 py-3 text-right">Entradas do Mês</th>
                                    <th class="px-6 py-3 text-right">Saídas do Mês</th>
                                    <th class="px-6 py-3 text-right">Saldo Total</th>
                                    <th class="px-6 py-3 text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($churches_data)): ?>
                                    <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Nenhuma igreja encontrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($churches_data as $church): ?>
                                    <tr class="bg-white border-b hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($church['name']); ?></td>
                                        <td class="px-6 py-4 text-center"><?php echo $church['member_count']; ?></td>
                                        <td class="px-6 py-4 text-right text-green-600 font-medium"><?php echo number_format($church['monthly_income'], 2, ',', '.'); ?> MZN</td>
                                        <td class="px-6 py-4 text-right text-red-600 font-medium"><?php echo number_format($church['monthly_expenses'], 2, ',', '.'); ?> MZN</td>
                                        <td class="px-6 py-4 text-right font-bold text-gray-800"><?php echo number_format($church['balance'], 2, ',', '.'); ?> MZN</td>
                                        <td class="px-6 py-4 text-center">
                                            <button class="details-btn px-3 py-1 text-xs font-medium text-white bg-primary rounded-full hover:bg-blue-700" data-church-id="<?php echo $church['id']; ?>" data-church-name="<?php echo htmlspecialchars($church['name']); ?>">
                                                Ver Detalhes
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Aprovação de Utilizadores Pendentes -->
                <div class="bg-white rounded-lg shadow-lg">
                    <div class="p-6 border-b">
                         <h2 class="text-lg font-medium text-gray-800">Registos Pendentes</h2>
                         <p class="text-sm text-gray-500">Utilizadores que aguardam aprovação para aceder ao sistema.</p>
                         
                         <!-- Pesquisa de Utilizadores -->
                         <form method="GET" action="user_approval.php" class="mt-4">
                            <div class="relative">
                                <input type="text" name="search" placeholder="Pesquisar por nome ou email..." value="<?php echo htmlspecialchars($search_term); ?>" class="w-full pl-10 pr-4 py-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-primary">
                                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                             <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3">Utilizador</th>
                                    <th class="px-6 py-3 hidden md:table-cell">Igreja</th>
                                    <th class="px-6 py-3 hidden sm:table-cell">Data de Registo</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_users)): ?>
                                    <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">Nenhum utilizador pendente encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_users as $user): ?>
                                        <tr class="bg-white border-b hover:bg-gray-50">
                                            <td class="px-6 py-4 font-medium text-gray-900">
                                                <div><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 hidden md:table-cell"><?php echo htmlspecialchars($user['church_name'] ?: 'N/A'); ?></td>
                                            <td class="px-6 py-4 hidden sm:table-cell"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                            <td class="px-6 py-4 text-right">
                                                <form method="POST" action="user_approval.php" class="inline-flex items-center gap-2">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="approve" class="px-3 py-1 text-xs font-medium text-white bg-green-600 rounded-full hover:bg-green-700">Aprovar</button>
                                                    <button type="submit" name="reject" class="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded-full hover:bg-red-700" onclick="return confirm('Tem a certeza que deseja rejeitar e apagar este utilizador?');">Rejeitar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Controles de Paginação -->
                    <?php if ($total_pages > 1): ?>
                    <div class="p-4 border-t flex justify-center items-center gap-2 text-sm">
                        <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 rounded hover:bg-gray-200 <?php echo $page <= 1 ? 'pointer-events-none text-gray-400' : ''; ?>">&laquo; Anterior</a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 rounded <?php echo $i == $page ? 'bg-primary text-white' : 'hover:bg-gray-200'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 rounded hover:bg-gray-200 <?php echo $page >= $total_pages ? 'pointer-events-none text-gray-400' : ''; ?>">Próximo &raquo;</a>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal de Detalhes da Igreja -->
    <div id="detailsModal" class="modal fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center hidden p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-4xl transform scale-95 opacity-0">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 id="modalChurchName" class="text-xl font-medium text-gray-800"></h3>
                <button id="closeModalBtn" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100"><i class="ri-close-line ri-lg"></i></button>
            </div>
            <div id="modalBody" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 max-h-[80vh] overflow-y-auto">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Resumo Financeiro (Últimos 6 Meses)</h4>
                    <div id="financialChart" style="height: 300px;"></div>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Membros</h4>
                    <div id="memberList" class="space-y-2 max-h-[300px] overflow-y-auto pr-2 bg-gray-50 p-2 rounded-md"></div>
                </div>
            </div>
            <div id="modalLoading" class="p-6 text-center hidden">
                 <div class="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto"></div>
                <p class="mt-2 text-gray-500">A carregar detalhes...</p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const detailsModal = document.getElementById('detailsModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const modalBody = document.getElementById('modalBody');
        const modalLoading = document.getElementById('modalLoading');
        const modalChurchName = document.getElementById('modalChurchName');
        const memberListContainer = document.getElementById('memberList');
        let financialChart = null;

        document.querySelectorAll('.details-btn').forEach(button => {
            button.addEventListener('click', async () => {
                const churchId = button.dataset.churchId;
                const churchName = button.dataset.churchName;

                modalChurchName.textContent = `Detalhes de: ${churchName}`;
                modalBody.classList.add('hidden');
                modalLoading.classList.remove('hidden');
                detailsModal.classList.remove('hidden');
                setTimeout(() => detailsModal.querySelector('.modal-content').classList.add('opacity-100', 'scale-100'), 10);

                try {
                    const response = await fetch(`user_approval.php?fetch_details=1&church_id=${churchId}`);
                    const data = await response.json();
                    
                    if (data.error) {
                         throw new Error(data.error);
                    }

                    // Preencher lista de membros
                    memberListContainer.innerHTML = '';
                    if (data.members && data.members.length > 0) {
                        data.members.forEach(member => {
                            const memberDiv = document.createElement('div');
                            memberDiv.className = 'text-sm p-2 bg-white rounded-md border border-gray-200';
                            memberDiv.innerHTML = `<p class="font-medium">${member.name}</p><p class="text-xs text-gray-500">${member.email}</p>`;
                            memberListContainer.appendChild(memberDiv);
                        });
                    } else {
                        memberListContainer.innerHTML = '<p class="text-sm text-gray-500">Nenhum membro encontrado.</p>';
                    }

                    // Configurar e renderizar o gráfico financeiro
                    if (!financialChart) {
                        financialChart = echarts.init(document.getElementById('financialChart'));
                    }
                    const chartOption = {
                        tooltip: { trigger: 'axis' },
                        legend: { data: ['Entradas', 'Saídas'], bottom: 0 },
                        xAxis: { type: 'category', data: data.financials.map(f => f.month) },
                        yAxis: { type: 'value', name: 'MZN', axisLabel: { formatter: '{value}' } },
                        series: [
                            { name: 'Entradas', type: 'bar', data: data.financials.map(f => f.income), itemStyle: { color: '#22c55e'} },
                            { name: 'Saídas', type: 'bar', data: data.financials.map(f => f.expenses), itemStyle: { color: '#ef4444'} }
                        ],
                        grid: { left: '15%', right: '5%', top: '10%', bottom: '20%' }
                    };
                    financialChart.setOption(chartOption, true);
                    
                } catch (error) {
                    console.error('Erro ao buscar detalhes:', error);
                    modalBody.innerHTML = `<p class="text-red-500 p-4">${error.message}</p>`;
                } finally {
                    modalLoading.classList.add('hidden');
                    modalBody.classList.remove('hidden');
                    if(financialChart) setTimeout(() => financialChart.resize(), 50);
                }
            });
        });

        const hideModal = () => {
            detailsModal.querySelector('.modal-content').classList.remove('opacity-100', 'scale-100');
            setTimeout(() => detailsModal.classList.add('hidden'), 300);
        };

        closeModalBtn.addEventListener('click', hideModal);
        detailsModal.addEventListener('click', (e) => {
            if (e.target === detailsModal) hideModal();
        });
    });
    </script>
</body>
</html>
