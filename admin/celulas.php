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
            // Verificar se o líder é dono desta célula
            $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
            $stmt_verify->bind_param("ii", $celula_id, $user_id);
            $stmt_verify->execute();
            $res_verify = $stmt_verify->get_result();
            
            if ($res_verify && $res_verify->num_rows > 0) {
                // Verificar se email já existe
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                $res_email = $stmt_check_email->get_result();
                
                if ($res_email->num_rows > 0) {
                    $error = "Este email já está registado no sistema.";
                } else {
                    // Criar novo membro com senha padrão
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
                } else {
                    $error = "Erro ao adicionar membro à célula.";
                }
                $stmt_assign->close();
            }
        } else {
            $error = "Não tem permissão para adicionar membros a esta célula.";
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
                } else {
                    $error = "Erro ao remover o membro da célula.";
                }
                $stmt_remove->close();
            }
        } else {
            $error = "Não tem permissão para remover este membro.";
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
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Gestão de Células - Life Church</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1d72e8",
                        primaryLight: "#eef2ff",
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft-xl': '0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02)',
                        'card': '0 4px 20px rgba(0, 0, 0, 0.04)',
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-[#f8fafc] antialiased text-[#1a1a1a]">

<?php if ($user_role === 'lider' && $celula): ?>
    <!-- ==================== LAYOUT PARA LÍDER COM CÉLULA ==================== -->
    <main class="flex-1 max-w-2xl mx-auto w-full px-4 pt-6 pb-32">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-6">
            <div class="flex flex-col">
                <span class="text-gray-400 text-sm font-medium leading-none mb-1">Bem-vindo,</span>
                <h2 class="text-2xl lg:text-3xl font-extrabold text-[#1a1a1a] leading-tight">Gestão de Células</h2>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-2xl mb-6 flex items-center gap-3">
                <span class="material-symbols-outlined text-green-600">check_circle</span>
                <span class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl mb-6 flex items-center gap-3">
                <span class="material-symbols-outlined text-red-600">error</span>
                <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Card do Líder -->
        <div class="bg-white rounded-[32px] shadow-soft-xl border border-gray-50 p-6 lg:p-8 mb-8 relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-primary text-[10px] font-black uppercase tracking-[0.2em]">Líder da Célula</span>
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">verified</span>
                    </div>
                </div>
                <h3 class="text-2xl lg:text-3xl font-extrabold text-[#1a1a1a] mb-5"><?php echo htmlspecialchars($user_name); ?></h3>
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 px-4 py-2 bg-[#f8fafc] rounded-xl border border-gray-100">
                        <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                        <span class="text-gray-600 text-sm font-semibold"><?php echo htmlspecialchars($celula['dia_encontro'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-[#f8fafc] rounded-xl border border-gray-100">
                        <span class="material-symbols-outlined text-primary text-xl">schedule</span>
                        <span class="text-gray-600 text-sm font-semibold"><?php echo htmlspecialchars($celula['horario'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-[#f8fafc] rounded-xl border border-gray-100">
                        <span class="material-symbols-outlined text-primary text-xl">location_on</span>
                        <span class="text-gray-600 text-sm font-semibold"><?php echo htmlspecialchars($celula['endereco'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secção de Membros -->
        <section>
            <div class="flex items-baseline justify-between mb-5 px-1">
                <div class="flex items-center gap-3">
                    <h3 class="text-xl font-extrabold text-[#1a1a1a]">Membros</h3>
                    <span class="bg-gray-100 text-[#1a1a1a] text-xs font-bold px-3 py-1 rounded-full"><?php echo count($membros_celula); ?></span>
                </div>
            </div>

            <?php if (empty($membros_celula)): ?>
                <div class="bg-white rounded-[24px] p-8 shadow-card border border-gray-50 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-3xl text-gray-400">group_off</span>
                    </div>
                    <h4 class="font-bold text-gray-700 mb-2">Nenhum membro ainda</h4>
                    <p class="text-sm text-gray-500">Clique no botão abaixo para adicionar o primeiro membro à sua célula.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($membros_celula as $index => $membro): 
                        $color = $avatar_colors[$index % count($avatar_colors)];
                    ?>
                        <div class="bg-white rounded-[24px] p-5 shadow-card border border-gray-50 flex items-center gap-4 relative">
                            <div class="w-14 h-14 rounded-2xl <?php echo $color['bg']; ?> <?php echo $color['text']; ?> flex items-center justify-center font-extrabold text-lg shrink-0">
                                <?php echo getInitials($membro['name']); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-base text-[#1a1a1a] mb-1 truncate"><?php echo htmlspecialchars($membro['name']); ?></h4>
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2 text-gray-400">
                                        <span class="material-symbols-outlined text-base">mail</span>
                                        <span class="text-xs truncate"><?php echo htmlspecialchars($membro['email']); ?></span>
                                    </div>
                                    <?php if (!empty($membro['phone'])): ?>
                                        <div class="flex items-center gap-2 text-gray-400">
                                            <span class="material-symbols-outlined text-base">call</span>
                                            <span class="text-xs"><?php echo htmlspecialchars($membro['phone']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2 text-gray-300">
                                            <span class="material-symbols-outlined text-base">cancel</span>
                                            <span class="text-xs italic">Sem telefone</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" class="shrink-0" onsubmit="return confirm('Tem certeza que deseja remover este membro?')">
                                <input type="hidden" name="action" value="remove_member">
                                <input type="hidden" name="member_id_remove" value="<?php echo $membro['id']; ?>">
                                <button type="submit" class="flex items-center justify-center text-gray-300 hover:text-red-500 transition-colors p-2 rounded-xl hover:bg-red-50">
                                    <span class="material-symbols-outlined text-xl">person_remove</span>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Botão Flutuante de Adicionar Membro -->
        <div class="fixed bottom-24 right-4 z-40">
            <button onclick="openAddMemberModal()" class="bg-primary hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-2xl shadow-2xl shadow-primary/40 transition-all flex items-center gap-3 transform hover:scale-105 active:scale-95">
                <span class="material-symbols-outlined text-2xl">add</span>
                <span class="text-sm">Membro</span>
            </button>
        </div>
    </main>

    <!-- Modal Adicionar Membro -->
    <div id="addMemberModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAddMemberModal()"></div>
        <div class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[32px] p-6 pb-10 max-h-[85vh] overflow-y-auto transform transition-transform">
            <div class="w-12 h-1.5 bg-gray-300 rounded-full mx-auto mb-6"></div>
            <h3 class="text-xl font-bold text-center mb-6">Adicionar Membro</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="register_member">
                <input type="hidden" name="celula_id" value="<?php echo $celula['id']; ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome completo *</label>
                    <input type="text" name="nome" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Ex: João Silva">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="email@exemplo.com">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
                    <input type="text" name="telefone" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary" placeholder="84 123 4567">
                </div>
                
                <p class="text-xs text-gray-500 bg-gray-50 p-3 rounded-xl">
                    <span class="font-semibold">Nota:</span> O membro será criado com a senha padrão <strong>123456</strong>. Ele deverá alterar após o primeiro login.
                </p>
                
                <button type="submit" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">person_add</span>
                    Adicionar Membro
                </button>
            </form>
        </div>
    </div>

    <!-- Navegação Inferior -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 flex justify-around py-4 pb-8 z-40">
        <a class="flex flex-col items-center gap-1 text-primary" href="celulas.php">
            <span class="material-symbols-outlined text-2xl">groups</span>
            <span class="text-[10px] font-bold uppercase tracking-wider">Célula</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition-colors" href="celulas_presencas.php?celula_id=<?php echo $celula['id']; ?>">
            <span class="material-symbols-outlined text-2xl">assignment</span>
            <span class="text-[10px] font-bold uppercase tracking-wider">Atividades</span>
        </a>
        <a class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition-colors" href="settings.php">
            <span class="material-symbols-outlined text-2xl">settings</span>
            <span class="text-[10px] font-bold uppercase tracking-wider">Definições</span>
        </a>
    </nav>

    <script>
    function openAddMemberModal() {
        document.getElementById('addMemberModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeAddMemberModal() {
        document.getElementById('addMemberModal').classList.add('hidden');
        document.body.style.overflow = '';
    }
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
    <main class="flex-1 max-w-4xl mx-auto w-full px-4 pt-6 pb-10">
        <header class="flex justify-between items-center mb-8">
            <h2 class="text-2xl lg:text-3xl font-extrabold text-[#1a1a1a]">Todas as Células</h2>
        </header>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-2xl mb-6"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($lista_celulas)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($lista_celulas as $cel): ?>
                    <a href="celulas.php?view_celula_id=<?php echo $cel['id']; ?>" class="bg-white rounded-[24px] p-6 shadow-card border border-gray-50 hover:border-primary/30 hover:shadow-lg transition-all block">
                        <h4 class="font-bold text-lg text-[#1a1a1a] mb-2"><?php echo htmlspecialchars($cel['nome']); ?></h4>
                        <p class="text-sm text-gray-500 mb-1">Líder: <?php echo htmlspecialchars($cel['lider_nome']); ?></p>
                        <p class="text-xs text-gray-400">Igreja: <?php echo htmlspecialchars($cel['church_name']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif ($celula): ?>
            <!-- Visualização de uma célula específica pelo admin -->
            <div class="bg-white rounded-[24px] p-6 shadow-card border border-gray-50 mb-6">
                <a href="celulas.php" class="text-primary text-sm font-medium hover:underline mb-4 inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> Voltar à lista
                </a>
                <h3 class="text-2xl font-extrabold mt-4 mb-2"><?php echo htmlspecialchars($celula['nome']); ?></h3>
                <p class="text-gray-500">Membros: <?php echo count($membros_celula); ?></p>
            </div>
            
            <?php if (!empty($membros_celula)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
