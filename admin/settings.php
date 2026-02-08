<?php
session_start();

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
                $_SESSION['user_name'] = $name;
                $user_name = $name;
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
                
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if ($user_data && password_verify($current_password_input, $user_data['password'])) {
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

// Buscar dados do usuário
$conn = connect_db();
$stmt = $conn->prepare("SELECT name, email, phone, city FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Buscar célula do líder para navegação
$celula_id = null;
if ($user_role === 'lider') {
    $stmt_cel = $conn->prepare("SELECT id FROM celulas WHERE lider_id = ?");
    $stmt_cel->bind_param("i", $user_id);
    $stmt_cel->execute();
    $res_cel = $stmt_cel->get_result();
    $cel_data = $res_cel->fetch_assoc();
    if ($cel_data) $celula_id = $cel_data['id'];
}
$conn->close();

// Helper para iniciais
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(mb_substr($word, 0, 1));
    }
    return $initials ?: 'U';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Definições - Life Church</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
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
<body class="min-h-screen flex flex-col lg:flex-row bg-[#f8fafc] antialiased text-[#1a1a1a]">

<?php if ($user_role === 'lider'): ?>
    <!-- ==================== SIDEBAR DESKTOP ==================== -->
    <aside class="hidden lg:flex w-72 bg-white border-r border-gray-100 flex-col h-screen sticky top-0">
        <div class="p-8 flex items-center gap-4">
            <span class="text-2xl font-bold italic text-primary">Life Church</span>
        </div>
        <nav class="flex-1 px-4 space-y-2">
            <a class="flex items-center gap-4 px-4 py-4 text-gray-400 hover:text-primary hover:bg-gray-50 transition-colors font-medium rounded-2xl" href="celulas.php">
                <span class="material-symbols-outlined">groups</span>
                <span>Célula</span>
            </a>
            <a class="flex items-center gap-4 px-4 py-4 text-gray-400 hover:text-primary hover:bg-gray-50 transition-colors font-medium rounded-2xl" href="celulas_presencas.php<?php if($celula_id) echo '?celula_id='.$celula_id; ?>">
                <span class="material-symbols-outlined">assignment</span>
                <span>Atividades</span>
            </a>
            <a class="flex items-center gap-4 px-4 py-4 text-primary bg-primaryLight/50 font-semibold rounded-2xl" href="settings.php">
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

    <!-- ==================== CONTEÚDO PRINCIPAL ==================== -->
    <main class="flex-1 max-w-2xl mx-auto w-full px-4 lg:px-8 pt-6 lg:pt-8 pb-32 lg:pb-12">
        
        <!-- Header -->
        <header class="flex items-center gap-4 mb-6 lg:mb-8">
            <?php if ($user_role !== 'lider'): ?>
            <a href="dashboard.php" class="p-2 rounded-full hover:bg-gray-100 transition-colors">
                <span class="material-symbols-outlined text-2xl text-gray-600">arrow_back</span>
            </a>
            <?php endif; ?>
            <h1 class="text-xl lg:text-2xl font-bold text-[#1a1a1a]">Definições</h1>
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

        <!-- Profile Card -->
        <div class="bg-white rounded-[24px] shadow-card border border-gray-50 p-6 flex flex-col items-center text-center relative overflow-hidden mb-6">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary/10 rounded-full blur-2xl"></div>
            <div class="w-24 h-24 rounded-full bg-blue-100 flex items-center justify-center mb-4 border-4 border-white shadow-sm">
                <span class="text-3xl font-bold text-primary"><?php echo getInitials($user_name); ?></span>
            </div>
            <h2 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($user_name); ?></h2>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                <?php echo ucfirst($user_role); ?>
            </span>
        </div>

        <!-- Update Profile Form -->
        <div class="bg-white rounded-[24px] shadow-card border border-gray-50 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">person</span>
                Atualizar Perfil
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required 
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required 
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cidade</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-primary hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">save</span>
                    Atualizar Perfil
                </button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="bg-white rounded-[24px] shadow-card border border-gray-50 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">lock</span>
                Alterar Senha
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Senha Atual</label>
                    <input type="password" name="current_password" required 
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nova Senha</label>
                        <input type="password" name="new_password" required 
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmar Nova Senha</label>
                        <input type="password" name="confirm_password" required 
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">key</span>
                    Alterar Senha
                </button>
            </form>
        </div>

        <?php if ($user_role === 'lider'): ?>
            <!-- PWA Install Section -->
            <div class="bg-white rounded-[24px] shadow-card border border-gray-50 p-6 mb-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">install_mobile</span>
                    Instalar Aplicação
                </h3>
                
                <!-- PWA Install Button (Android/Chrome) -->
                <button id="pwa-install-btn" onclick="installPWA()" style="display: none;" 
                    class="w-full bg-gradient-to-r from-primary to-blue-600 text-white font-bold py-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg mb-4">
                    <span class="material-symbols-outlined">download</span>
                    Instalar Aplicação
                </button>
                
                <!-- iOS Install Instructions -->
                <div id="ios-install-hint" style="display: none;" class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
                    <div class="flex items-center justify-center mb-2">
                        <span class="material-symbols-outlined text-2xl text-primary mr-2">smartphone</span>
                        <span class="font-bold text-gray-800">Instalar no iPhone/iPad</span>
                    </div>
                    <p class="text-sm text-gray-600">
                        Toque no ícone <strong>Partilhar</strong> (📤) e depois em <strong>"Adicionar ao Ecrã inicial"</strong>
                    </p>
                </div>
                
                <div id="already-installed" style="display: none;" class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                    <span class="material-symbols-outlined text-green-600 text-2xl mb-2">verified</span>
                    <p class="text-sm text-green-700 font-medium">Aplicação já instalada!</p>
                </div>
                
                <div id="desktop-hint" class="hidden lg:block bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
                    <span class="material-symbols-outlined text-gray-500 text-2xl mb-2">computer</span>
                    <p class="text-sm text-gray-600">A instalação PWA está disponível apenas em dispositivos móveis.</p>
                </div>
            </div>

            <!-- Logout Button (Mobile only - desktop has in sidebar) -->
            <a href="logout.php" class="lg:hidden w-full bg-red-50 text-red-600 font-bold py-4 rounded-xl border-2 border-red-100 hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">logout</span>
                Sair
            </a>

            <script>
            let deferredPrompt = null;
            
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                document.getElementById('pwa-install-btn').style.display = 'flex';
                document.getElementById('desktop-hint').style.display = 'none';
            });

            function installPWA() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            document.getElementById('pwa-install-btn').style.display = 'none';
                            document.getElementById('already-installed').style.display = 'block';
                        }
                        deferredPrompt = null;
                    });
                }
            }

            // Detect iOS
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            
            if (isStandalone) {
                document.getElementById('already-installed').style.display = 'block';
                document.getElementById('desktop-hint').style.display = 'none';
            } else if (isIOS) {
                document.getElementById('ios-install-hint').style.display = 'block';
                document.getElementById('desktop-hint').style.display = 'none';
            }
            </script>
        <?php endif; ?>

    </main>

    <?php if ($user_role === 'lider'): ?>
        <!-- Mobile Bottom Navigation for Leaders -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 flex justify-around py-4 pb-8 z-50">
            <a class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition-colors" href="celulas.php">
                <span class="material-symbols-outlined text-2xl">groups</span>
                <span class="text-[10px] font-bold uppercase tracking-wider">Célula</span>
            </a>
            <a class="flex flex-col items-center gap-1 text-gray-400 hover:text-primary transition-colors" href="celulas_presencas.php<?php if($celula_id) echo '?celula_id='.$celula_id; ?>">
                <span class="material-symbols-outlined text-2xl">assignment</span>
                <span class="text-[10px] font-bold uppercase tracking-wider">Atividades</span>
            </a>
            <a class="flex flex-col items-center gap-1 text-primary" href="settings.php">
                <span class="material-symbols-outlined text-2xl">settings</span>
                <span class="text-[10px] font-bold uppercase tracking-wider">Definições</span>
            </a>
        </nav>
    <?php endif; ?>

</body>
</html>
