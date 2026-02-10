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

// --- Buscar estatísticas do mês atual ---
$stats = ['total_membros' => 0, 'taxa_presenca' => 0, 'encontros_mes' => 0];

// --- LÓGICA AJAX para buscar membros disponíveis ---
if (isset($_GET['action']) && $_GET['action'] === 'get_available_members') {
    header('Content-Type: application/json');
    $search_term = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
    
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
                    }
                }
                $stmt_check->close();
            }
        } else {
            $error = "Por favor, preencha todos os campos para criar a sua célula.";
        }
    }
    // --- Ação para REGISTAR um novo membro ---
    elseif ($action === 'register_member' && $user_role === 'lider') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $celula_id = (int)$_POST['celula_id'];

        if ($nome && $email) {
            $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
            $stmt_verify->bind_param("ii", $celula_id, $user_id);
            $stmt_verify->execute();
            $res_verify = $stmt_verify->get_result();
            
            if ($res_verify && $res_verify->num_rows > 0) {
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                $res_email = $stmt_check_email->get_result();
                
                if ($res_email->num_rows > 0) {
                    $error = "Este email já está registado no sistema.";
                } else {
                    $senha_padrao = password_hash('123456', PASSWORD_DEFAULT);
                    $stmt_insert = $conn->prepare("INSERT INTO users (name, email, phone, password, role, church_id, celula_id, status) VALUES (?, ?, ?, ?, 'membro', ?, ?, 'approved')");
                    $stmt_insert->bind_param("ssssii", $nome, $email, $telefone, $senha_padrao, $church_id, $celula_id);
                    
                    if ($stmt_insert->execute()) {
                        $message = "Membro '$nome' registado com sucesso! Senha padrão: 123456";
                    } else {
                        $error = "Erro ao registar o membro.";
                    }
                }
            } else {
                $error = "Você não tem permissão para adicionar membros a esta célula.";
            }
        } else {
            $error = "Nome e email são obrigatórios.";
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
                }
                $stmt_assign->close();
            }
        }
    }
    // --- Ação para REMOVER Membro da Célula ---
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
                }
                $stmt_remove->close();
            }
        }
    }
    // --- Ação para EDITAR/ATUALIZAR Membro ---
    elseif ($action === 'update_member' && $user_role === 'lider') {
        $member_id = (int)$_POST['member_id'];
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');

        if ($nome && $email && $member_id > 0) {
            // Verificar se o membro pertence à célula do líder
            $stmt_verify = $conn->prepare("SELECT u.id FROM users u JOIN celulas c ON u.celula_id = c.id WHERE u.id = ? AND c.lider_id = ?");
            $stmt_verify->bind_param("ii", $member_id, $user_id);
            $stmt_verify->execute();
            $res_verify = $stmt_verify->get_result();
            
            if ($res_verify && $res_verify->num_rows > 0) {
                // Verificar se o novo email não está em uso por outro utilizador
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt_check_email->bind_param("si", $email, $member_id);
                $stmt_check_email->execute();
                $res_email = $stmt_check_email->get_result();
                
                if ($res_email->num_rows > 0) {
                    $error = "Este email já está em uso por outro utilizador.";
                } else {
                    $stmt_update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt_update->bind_param("sssi", $nome, $email, $telefone, $member_id);
                    
                    if ($stmt_update->execute()) {
                        $message = "Dados do membro atualizados com sucesso!";
                    } else {
                        $error = "Erro ao atualizar dados do membro.";
                    }
                }
            } else {
                $error = "Não tem permissão para editar este membro.";
            }
        } else {
            $error = "Nome e email são obrigatórios.";
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
        }
        $stmt_find_celula->close();
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
    }
    
    if (!$celula) {
        $error = "Célula não encontrada.";
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
    }
}

// --- Calcular estatísticas do mês atual ---
if ($celula) {
    $stats['total_membros'] = count($membros_celula);
    $current_month = date('Y-m');
    $stmt_stats = $conn->prepare("SELECT participacoes_json FROM registo_atividades WHERE celula_id = ? AND DATE_FORMAT(data_registo, '%Y-%m') = ?");
    if ($stmt_stats) {
        $stmt_stats->bind_param("is", $celula['id'], $current_month);
        $stmt_stats->execute();
        $result_stats = $stmt_stats->get_result();
        $total_present = 0;
        $total_entries = 0;
        $meetings = 0;
        while ($row_s = $result_stats->fetch_assoc()) {
            $meetings++;
            $parts = json_decode($row_s['participacoes_json'], true) ?: [];
            foreach ($parts as $p) {
                $total_entries++;
                if (!empty($p['presente'])) $total_present++;
            }
        }
        $stats['encontros_mes'] = $meetings;
        $stats['taxa_presenca'] = $total_entries > 0 ? round(($total_present / $total_entries) * 100) : 0;
        $stmt_stats->close();
    }
}

if (isset($_GET['creation_success'])) {
    $message = "Sua célula foi criada com sucesso!";
}

$conn->close();

// Helper para gerar iniciais
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(mb_substr($word, 0, 1));
    }
    return $initials ?: 'U';
}

// Cores para avatares
$avatar_colors = [
    ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
    ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
    ['bg' => 'bg-green-100', 'text' => 'text-green-600'],
    ['bg' => 'bg-amber-100', 'text' => 'text-amber-600'],
    ['bg' => 'bg-pink-100', 'text' => 'text-pink-600'],
    ['bg' => 'bg-teal-100', 'text' => 'text-teal-600'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
    <title>Gestão de Células - Life Church</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: "#1d72e8", primaryLight: "#eef2ff" },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    boxShadow: {
                        'soft-xl': '0 20px 25px -5px rgba(0,0,0,0.05), 0 10px 10px -5px rgba(0,0,0,0.02)',
                        'card': '0 4px 20px rgba(0,0,0,0.04)',
                        'native': '0 8px 32px rgba(0,0,0,0.08)',
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; overscroll-behavior: none; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; display: inline-block; vertical-align: middle; }
        /* Ripple effect */
        .ripple { position: relative; overflow: hidden; }
        .ripple::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(29,114,232,0.15) 10%, transparent 10.01%); transform: scale(10); opacity: 0; transition: transform .5s, opacity .8s; }
        .ripple:active::after { transform: scale(0); opacity: 1; transition: 0s; }
        /* Tap bounce */
        .tap-bounce { transition: transform 0.1s ease; }
        .tap-bounce:active { transform: scale(0.96); }
        /* Bottom sheet */
        .bottom-sheet-backdrop { transition: opacity 0.3s ease; }
        .bottom-sheet-content { transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
        .bottom-sheet-content.active { transform: translateY(0); }
        /* Skeleton shimmer */
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 12px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        /* Slide animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .anim-fade-up { animation: fadeInUp 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) both; }
        .anim-scale-in { animation: scaleIn 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94) both; }
        .anim-delay-1 { animation-delay: 0.05s; }
        .anim-delay-2 { animation-delay: 0.1s; }
        .anim-delay-3 { animation-delay: 0.15s; }
        .anim-delay-4 { animation-delay: 0.2s; }
        /* FAB */
        .fab-menu { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .fab-item { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); transform: scale(0) translateY(20px); opacity: 0; }
        .fab-menu.open .fab-item { transform: scale(1) translateY(0); opacity: 1; }
        .fab-menu.open .fab-item:nth-child(1) { transition-delay: 0.05s; }
        .fab-menu.open .fab-item:nth-child(2) { transition-delay: 0.1s; }
        .fab-menu.open .fab-item:nth-child(3) { transition-delay: 0.15s; }
        .fab-rotate { transition: transform 0.3s ease; }
        .fab-menu.open .fab-rotate { transform: rotate(45deg); }
        /* Toast */
        .toast { animation: toastIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) both; }
        .toast.hide { animation: toastOut 0.3s cubic-bezier(0.4, 0, 0.2, 1) both; }
        @keyframes toastIn { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes toastOut { from { transform: translateY(0); opacity: 1; } to { transform: translateY(-100%); opacity: 0; } }
        /* Gauge */
        .gauge-ring { transition: stroke-dashoffset 1s cubic-bezier(0.4, 0, 0.2, 1); }
        /* Swipe card */
        .member-card-wrapper { transition: transform 0.2s ease; }
        /* Safe area padding for iOS */
        .safe-bottom { padding-bottom: max(env(safe-area-inset-bottom), 24px); }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row bg-[#f8fafc] antialiased text-[#1a1a1a]">

<?php if ($user_role === 'lider'): ?>
    <!-- ==================== SIDEBAR DESKTOP ==================== -->
    <aside class="hidden lg:flex w-72 bg-white border-r border-gray-100 flex-col h-screen sticky top-0">
        <div class="p-8 flex items-center gap-4">
            <span class="text-2xl font-bold italic text-primary">Life Church</span>
        </div>
        <nav class="flex-1 px-4 space-y-2">
            <a class="flex items-center gap-4 px-4 py-4 text-primary bg-primaryLight/50 font-semibold rounded-2xl" href="celulas.php">
                <span class="material-symbols-outlined">groups</span>
                <span>Célula</span>
            </a>
            <a class="flex items-center gap-4 px-4 py-4 text-gray-400 hover:text-primary hover:bg-gray-50 transition-colors font-medium rounded-2xl" href="celulas_presencas.php<?php if($celula) echo '?celula_id='.$celula['id']; ?>">
                <span class="material-symbols-outlined">assignment</span>
                <span>Atividades</span>
            </a>
            <a class="flex items-center gap-4 px-4 py-4 text-gray-400 hover:text-primary hover:bg-gray-50 transition-colors font-medium rounded-2xl" href="settings.php">
                <span class="material-symbols-outlined">settings</span>
                <span>Definições</span>
            </a>
        </nav>
        <div class="p-6 mt-auto border-t border-gray-50">
            <div class="flex items-center gap-4 p-2">
                <div class="w-12 h-12 rounded-2xl bg-primaryLight flex items-center justify-center text-primary font-bold text-lg">
                    <?php echo getInitials($user_name); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold truncate"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></p>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Líder</p>
                </div>
                <a href="logout.php" class="text-gray-300 hover:text-red-500 transition-colors">
                    <span class="material-symbols-outlined">logout</span>
                </a>
            </div>
        </div>
    </aside>
<?php endif; ?>

<?php if ($user_role === 'lider' && $celula): ?>
    <!-- ==================== CONTEÚDO PRINCIPAL (LÍDER COM CÉLULA) ==================== -->
    <main class="flex-1 w-full min-w-0 pb-28 lg:pb-12">
        <!-- Toast Container -->
        <div id="toast-container" class="fixed top-4 left-4 right-4 z-[100] flex flex-col items-center gap-2 pointer-events-none"></div>

        <?php if ($message): ?>
        <script>document.addEventListener('DOMContentLoaded',()=>showToast('<?php echo addslashes($message); ?>','success'));</script>
        <?php endif; ?>
        <?php if ($error): ?>
        <script>document.addEventListener('DOMContentLoaded',()=>showToast('<?php echo addslashes($error); ?>','error'));</script>
        <?php endif; ?>

        <!-- Hero Header -->
        <div class="bg-gradient-to-br from-primary via-blue-600 to-blue-700 text-white px-5 pt-6 pb-8 lg:px-10 lg:pt-8 lg:pb-10 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/3"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/4"></div>
            <div class="relative z-10 max-w-5xl mx-auto">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-blue-200 text-xs font-bold uppercase tracking-[0.2em] mb-1">Sua Célula</p>
                        <h1 class="text-2xl lg:text-3xl font-extrabold leading-tight"><?php echo htmlspecialchars($celula['nome'] ?? 'Célula'); ?></h1>
                    </div>
                    <div class="w-11 h-11 bg-white/15 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-white">verified</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-4">
                    <div class="flex items-center gap-1.5 bg-white/15 backdrop-blur-sm rounded-xl px-3 py-1.5 text-sm font-medium">
                        <i class="ri-calendar-line text-blue-200"></i>
                        <span><?php echo htmlspecialchars($celula['dia_encontro'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex items-center gap-1.5 bg-white/15 backdrop-blur-sm rounded-xl px-3 py-1.5 text-sm font-medium">
                        <i class="ri-time-line text-blue-200"></i>
                        <span><?php echo htmlspecialchars($celula['horario'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex items-center gap-1.5 bg-white/15 backdrop-blur-sm rounded-xl px-3 py-1.5 text-sm font-medium">
                        <i class="ri-map-pin-line text-blue-200"></i>
                        <span class="truncate max-w-[150px]"><?php echo htmlspecialchars($celula['endereco'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 lg:px-8 -mt-5 space-y-6">
            <!-- Dashboard Stats Cards -->
            <div class="grid grid-cols-3 gap-3 lg:gap-4 anim-fade-up">
                <!-- Total Membros -->
                <div class="bg-white rounded-2xl p-4 shadow-native border border-gray-50 text-center tap-bounce">
                    <div class="w-10 h-10 mx-auto bg-blue-50 rounded-xl flex items-center justify-center mb-2">
                        <i class="ri-group-line text-primary text-lg"></i>
                    </div>
                    <p class="text-2xl font-extrabold text-gray-900"><?php echo $stats['total_membros']; ?></p>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-0.5">Membros</p>
                </div>
                <!-- Taxa de Presença -->
                <div class="bg-white rounded-2xl p-4 shadow-native border border-gray-50 text-center tap-bounce">
                    <div class="w-10 h-10 mx-auto relative mb-2">
                        <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="#f0fdf4" stroke-width="3"/>
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" class="gauge-ring" stroke-dasharray="97.4" stroke-dashoffset="<?php echo 97.4 - (97.4 * $stats['taxa_presenca'] / 100); ?>"/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-green-600"><?php echo $stats['taxa_presenca']; ?>%</span>
                    </div>
                    <p class="text-2xl font-extrabold text-gray-900"><?php echo $stats['taxa_presenca']; ?>%</p>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-0.5">Presença</p>
                </div>
                <!-- Encontros do Mês -->
                <div class="bg-white rounded-2xl p-4 shadow-native border border-gray-50 text-center tap-bounce">
                    <div class="w-10 h-10 mx-auto bg-amber-50 rounded-xl flex items-center justify-center mb-2">
                        <i class="ri-calendar-check-line text-amber-500 text-lg"></i>
                    </div>
                    <p class="text-2xl font-extrabold text-gray-900"><?php echo $stats['encontros_mes']; ?></p>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-0.5">Encontros</p>
                </div>
            </div>

            <!-- Quick Action: Lançar Relatório -->
            <a href="celulas_presencas.php?celula_id=<?php echo $celula['id']; ?>" class="flex items-center gap-4 bg-white rounded-2xl p-4 shadow-card border border-gray-50 tap-bounce ripple anim-fade-up anim-delay-1">
                <div class="w-12 h-12 bg-gradient-to-br from-primary to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-primary/20 shrink-0">
                    <span class="material-symbols-outlined text-white text-xl">assignment_add</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-900">Lançar Relatório</h3>
                    <p class="text-xs text-gray-400">Registar presenças e atividades</p>
                </div>
                <span class="material-symbols-outlined text-gray-300">chevron_right</span>
            </a>

            <!-- Membros Section -->
            <section class="anim-fade-up anim-delay-2">
                <div class="flex items-center justify-between mb-4 px-1">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-extrabold text-gray-900">Membros</h3>
                        <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full"><?php echo count($membros_celula); ?></span>
                    </div>
                </div>

                <?php if (empty($membros_celula)): ?>
                    <div class="bg-white rounded-2xl p-8 shadow-card border border-gray-50 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gray-50 rounded-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-3xl text-gray-300">group_off</span>
                        </div>
                        <h4 class="font-bold text-gray-700 mb-1">Nenhum membro ainda</h4>
                        <p class="text-sm text-gray-400">Toque no botão <strong>+</strong> para adicionar.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($membros_celula as $index => $membro):
                            $color = $avatar_colors[$index % count($avatar_colors)];
                            $delay = min($index, 7);
                        ?>
                        <div class="bg-white rounded-2xl shadow-card border border-gray-50 overflow-hidden tap-bounce ripple anim-fade-up" style="animation-delay: <?php echo $delay * 0.04; ?>s">
                            <div class="flex items-center gap-4 p-4">
                                <div class="w-12 h-12 rounded-2xl <?php echo $color['bg']; ?> <?php echo $color['text']; ?> flex items-center justify-center font-extrabold text-base shrink-0">
                                    <?php echo getInitials($membro['name']); ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-sm text-gray-900 truncate"><?php echo htmlspecialchars($membro['name']); ?></h4>
                                    <div class="flex items-center gap-3 mt-0.5">
                                        <?php if (!empty($membro['phone'])): ?>
                                            <span class="text-xs text-gray-400 flex items-center gap-1"><i class="ri-phone-line"></i><?php echo htmlspecialchars($membro['phone']); ?></span>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-300 flex items-center gap-1 truncate"><i class="ri-mail-line"></i><?php echo htmlspecialchars($membro['email']); ?></span>
                                    </div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" onclick="openEditSheet(<?php echo $membro['id']; ?>, '<?php echo htmlspecialchars(addslashes($membro['name'])); ?>', '<?php echo htmlspecialchars(addslashes($membro['email'])); ?>', '<?php echo htmlspecialchars(addslashes($membro['phone'] ?? '')); ?>')" class="p-2.5 rounded-xl text-gray-300 hover:text-primary hover:bg-blue-50 transition-colors">
                                        <i class="ri-edit-line text-lg"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Remover este membro da célula?')">
                                        <input type="hidden" name="action" value="remove_member">
                                        <input type="hidden" name="member_id_remove" value="<?php echo $membro['id']; ?>">
                                        <button type="submit" class="p-2.5 rounded-xl text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors">
                                            <i class="ri-user-unfollow-line text-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Expandable FAB -->
        <div id="fab-menu" class="fab-menu fixed bottom-24 lg:bottom-8 right-4 lg:right-8 z-50 flex flex-col-reverse items-end gap-3">
            <!-- Main FAB -->
            <button onclick="toggleFAB()" class="w-14 h-14 bg-primary hover:bg-blue-700 text-white rounded-2xl shadow-2xl shadow-primary/40 flex items-center justify-center transition-all active:scale-90">
                <span class="material-symbols-outlined text-2xl fab-rotate">add</span>
            </button>
            <!-- FAB item: Novo Membro -->
            <div class="fab-item flex items-center gap-3">
                <span class="bg-gray-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-lg whitespace-nowrap">Novo Membro</span>
                <button onclick="openAddSheet(); closeFAB();" class="w-12 h-12 bg-green-500 hover:bg-green-600 text-white rounded-2xl shadow-lg flex items-center justify-center transition-all active:scale-90">
                    <i class="ri-user-add-line text-xl"></i>
                </button>
            </div>
            <!-- FAB item: Lançar Relatório -->
            <div class="fab-item flex items-center gap-3">
                <span class="bg-gray-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-lg whitespace-nowrap">Atividades</span>
                <a href="celulas_presencas.php?celula_id=<?php echo $celula['id']; ?>" class="w-12 h-12 bg-amber-500 hover:bg-amber-600 text-white rounded-2xl shadow-lg flex items-center justify-center transition-all active:scale-90">
                    <i class="ri-file-list-3-line text-xl"></i>
                </a>
            </div>
        </div>
        <!-- FAB Backdrop -->
        <div id="fab-backdrop" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40 hidden transition-opacity" onclick="closeFAB()"></div>
    </main>

    <!-- ==================== BOTTOM SHEET: Adicionar Membro ==================== -->
    <div id="addSheet" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm bottom-sheet-backdrop" onclick="closeAddSheet()"></div>
        <div class="absolute bottom-0 left-0 right-0 lg:bottom-auto lg:top-1/2 lg:left-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2 lg:max-w-md lg:w-full bg-white rounded-t-[28px] lg:rounded-[28px] bottom-sheet-content safe-bottom" id="addSheetContent">
            <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mt-3 mb-2 lg:hidden"></div>
            <div class="p-6 pt-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-extrabold text-center mb-5">Novo Membro</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="register_member">
                    <input type="hidden" name="celula_id" value="<?php echo $celula['id']; ?>">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Nome *</label>
                        <input type="text" name="nome" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-colors" placeholder="Nome completo">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Email *</label>
                        <input type="email" name="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-colors" placeholder="email@exemplo.com">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Telefone</label>
                        <input type="text" name="telefone" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-colors" placeholder="84 123 4567">
                    </div>
                    <p class="text-xs text-gray-400 bg-blue-50 p-3 rounded-xl"><i class="ri-information-line text-primary mr-1"></i>Senha padrão: <strong>123456</strong></p>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeAddSheet()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3.5 rounded-xl transition-colors">Cancelar</button>
                        <button type="submit" class="flex-1 bg-primary hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <i class="ri-user-add-line"></i> Adicionar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== BOTTOM SHEET: Editar Membro ==================== -->
    <div id="editSheet" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm bottom-sheet-backdrop" onclick="closeEditSheet()"></div>
        <div class="absolute bottom-0 left-0 right-0 lg:bottom-auto lg:top-1/2 lg:left-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2 lg:max-w-md lg:w-full bg-white rounded-t-[28px] lg:rounded-[28px] bottom-sheet-content safe-bottom" id="editSheetContent">
            <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mt-3 mb-2 lg:hidden"></div>
            <div class="p-6 pt-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-extrabold text-center mb-5">Editar Membro</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_member">
                    <input type="hidden" name="member_id" id="edit_member_id">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Nome *</label>
                        <input type="text" name="nome" id="edit_member_nome" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Email *</label>
                        <input type="email" name="email" id="edit_member_email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Telefone</label>
                        <input type="text" name="telefone" id="edit_member_telefone" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:bg-white transition-colors">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeEditSheet()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3.5 rounded-xl transition-colors">Cancelar</button>
                        <button type="submit" class="flex-1 bg-primary hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                            <i class="ri-save-line"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-xl border-t border-gray-100 flex justify-around py-2 safe-bottom z-40">
        <a class="flex flex-col items-center gap-0.5 py-2 px-4 text-primary" href="celulas.php">
            <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1">groups</span>
            <span class="text-[10px] font-bold uppercase tracking-wider">Célula</span>
        </a>
        <a class="flex flex-col items-center gap-0.5 py-2 px-4 text-gray-400 hover:text-primary transition-colors" href="celulas_presencas.php?celula_id=<?php echo $celula['id']; ?>">
            <span class="material-symbols-outlined text-2xl">assignment</span>
            <span class="text-[10px] font-bold uppercase tracking-wider">Atividades</span>
        </a>
        <a class="flex flex-col items-center gap-0.5 py-2 px-4 text-gray-400 hover:text-primary transition-colors" href="settings.php">
            <span class="material-symbols-outlined text-2xl">settings</span>
            <span class="text-[10px] font-bold uppercase tracking-wider">Definições</span>
        </a>
    </nav>

    <script>
    // Toast Notifications
    function showToast(msg, type='success') {
        const c = document.getElementById('toast-container');
        const colors = type==='success' ? 'bg-green-500' : 'bg-red-500';
        const icon = type==='success' ? 'ri-check-line' : 'ri-error-warning-line';
        const t = document.createElement('div');
        t.className = `toast pointer-events-auto flex items-center gap-3 ${colors} text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-medium max-w-sm w-full`;
        t.innerHTML = `<i class="${icon} text-lg"></i><span class="flex-1">${msg}</span><button onclick="this.parentElement.classList.add('hide');setTimeout(()=>this.parentElement.remove(),300)" class="opacity-70 hover:opacity-100"><i class="ri-close-line"></i></button>`;
        c.appendChild(t);
        setTimeout(()=>{t.classList.add('hide');setTimeout(()=>t.remove(),300);},4000);
    }
    // FAB
    function toggleFAB(){document.getElementById('fab-menu').classList.toggle('open');document.getElementById('fab-backdrop').classList.toggle('hidden');}
    function closeFAB(){document.getElementById('fab-menu').classList.remove('open');document.getElementById('fab-backdrop').classList.add('hidden');}
    // Bottom Sheets
    function openSheet(id,contentId){const s=document.getElementById(id);s.classList.remove('hidden');document.body.style.overflow='hidden';requestAnimationFrame(()=>document.getElementById(contentId).classList.add('active'));}
    function closeSheet(id,contentId){const c=document.getElementById(contentId);c.classList.remove('active');document.body.style.overflow='';setTimeout(()=>document.getElementById(id).classList.add('hidden'),350);}
    function openAddSheet(){openSheet('addSheet','addSheetContent');}
    function closeAddSheet(){closeSheet('addSheet','addSheetContent');}
    function openEditSheet(id,nome,email,tel){document.getElementById('edit_member_id').value=id;document.getElementById('edit_member_nome').value=nome;document.getElementById('edit_member_email').value=email;document.getElementById('edit_member_telefone').value=tel;openSheet('editSheet','editSheetContent');}
    function closeEditSheet(){closeSheet('editSheet','editSheetContent');}
    </script>

<?php elseif ($user_role === 'lider' && $show_create_form): ?>
    <!-- ==================== FORMULÁRIO DE CRIAÇÃO DE CÉLULA ==================== -->
    <main class="flex-1 max-w-lg mx-auto w-full px-4 pt-8 pb-10">
        <header class="mb-8 text-center">
            <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-4xl text-primary">add_circle</span>
            </div>
            <h2 class="text-2xl font-extrabold text-[#1a1a1a] mb-2">Criar Sua Célula</h2>
            <p class="text-gray-500 text-sm">Preencha os dados abaixo para iniciar a gestão da sua célula.</p>
        </header>

        <?php if ($error): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-2xl mb-6 flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-600">info</span>
                <span class="text-sm"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-[24px] shadow-card border border-gray-50 p-6 space-y-4">
            <input type="hidden" name="action" value="create_celula">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Célula</label>
                <input type="text" name="nome_celula" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Ex: Célula Vida Nova">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Dia do Encontro</label>
                <select name="dia_encontro" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary bg-white">
                    <option value="">Selecione...</option>
                    <option value="Segunda-feira">Segunda-feira</option>
                    <option value="Terça-feira">Terça-feira</option>
                    <option value="Quarta-feira">Quarta-feira</option>
                    <option value="Quinta-feira">Quinta-feira</option>
                    <option value="Sexta-feira">Sexta-feira</option>
                    <option value="Sábado">Sábado</option>
                    <option value="Domingo">Domingo</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Horário</label>
                <input type="time" name="horario" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Endereço / Local</label>
                <input type="text" name="endereco" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Ex: Rua da Igreja, 100">
            </div>
            
            <button type="submit" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-colors flex items-center justify-center gap-2 mt-4">
                <span class="material-symbols-outlined">church</span>
                Criar Célula
            </button>
        </form>
    </main>

<?php elseif ($user_role === 'master_admin'): ?>
    <!-- ==================== LAYOUT PARA MASTER ADMIN ==================== -->
    <main class="flex-1 max-w-6xl mx-auto w-full px-4 lg:px-8 pt-6 lg:pt-8 pb-10">
        <header class="flex justify-between items-center mb-8">
            <h2 class="text-2xl lg:text-3xl font-extrabold text-[#1a1a1a]">Todas as Células</h2>
            <a href="dashboard.php" class="text-primary hover:underline text-sm font-medium flex items-center gap-1">
                <span class="material-symbols-outlined text-lg">arrow_back</span> Voltar
            </a>
        </header>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-2xl mb-6"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($lista_celulas)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                <?php foreach ($lista_celulas as $cel): ?>
                    <a href="celulas.php?view_celula_id=<?php echo $cel['id']; ?>" class="bg-white rounded-[24px] p-6 shadow-card border border-gray-50 hover:border-primary/30 hover:shadow-lg transition-all block">
                        <h4 class="font-bold text-lg text-[#1a1a1a] mb-2"><?php echo htmlspecialchars($cel['nome']); ?></h4>
                        <p class="text-sm text-gray-500 mb-1">Líder: <?php echo htmlspecialchars($cel['lider_nome']); ?></p>
                        <p class="text-xs text-gray-400">Igreja: <?php echo htmlspecialchars($cel['church_name']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif ($celula): ?>
            <div class="bg-white rounded-[24px] p-6 shadow-card border border-gray-50 mb-6">
                <a href="celulas.php" class="text-primary text-sm font-medium hover:underline mb-4 inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> Voltar à lista
                </a>
                <h3 class="text-2xl font-extrabold mt-4 mb-2"><?php echo htmlspecialchars($celula['nome']); ?></h3>
                <p class="text-gray-500">Membros: <?php echo count($membros_celula); ?></p>
            </div>
            
            <?php if (!empty($membros_celula)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($membros_celula as $index => $membro): 
                        $color = $avatar_colors[$index % count($avatar_colors)];
                    ?>
                        <div class="bg-white rounded-[24px] p-5 shadow-card border border-gray-50 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl <?php echo $color['bg']; ?> <?php echo $color['text']; ?> flex items-center justify-center font-bold text-lg">
                                <?php echo getInitials($membro['name']); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-[#1a1a1a] truncate"><?php echo htmlspecialchars($membro['name']); ?></h4>
                                <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($membro['email']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-white rounded-[24px] p-8 shadow-card border border-gray-50 text-center">
                <p class="text-gray-500">Nenhuma célula encontrada.</p>
            </div>
        <?php endif; ?>
    </main>
<?php endif; ?>

</body>
</html>
