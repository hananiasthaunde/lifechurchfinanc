<?php
/**
 * FICHEIRO: public/login.php
 * VERSÃO FINAL SEM DEPENDÊNCIA DE .HTACCESS
 *
 * O que foi corrigido:
 * 1. Utiliza a constante BASE_URL do config.php para redirecionamentos, garantindo que os caminhos funcionem sem .htaccess.
 * 2. O formulário submete os dados para o próprio `login.php` para processamento.
 */

// 1. Iniciar a sessão é a PRIMEIRA COISA a ser feita.
session_start();

// 2. Incluir os ficheiros essenciais (o config.php DEVE ser incluído primeiro)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// --- Redirecionar se o utilizador já estiver logado ---
if (isset($_SESSION['user_id'])) {
    // Redireciona para o dashboard usando a BASE_URL para garantir o caminho correto.
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

// 3. Ativar a exibição de erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializa as variáveis
$error = '';
$success_message = '';

// Verifica se há uma mensagem de sucesso vinda da página de registo
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Processa o formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        $conn = connect_db();
        
        if (!$conn) {
            $error = "Erro crítico no sistema. Tente novamente mais tarde.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, password, role, church_id, is_approved FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    if ($user['is_approved'] != 1) {
                        $error = 'A sua conta ainda não foi aprovada por um administrador.';
                    } else {
                        // Login bem-sucedido
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['church_id'] = $user['church_id'];
                        
                        // Redirecionamento seguro usando a BASE_URL
                        header('Location: ' . BASE_URL . '/admin/dashboard.php');
                        exit; 
                    }
                } else {
                    $error = 'Email ou senha incorretos.';
                }
            } else {
                $error = 'Email ou senha incorretos.';
            }
            $stmt->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Life Church - Login</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: { primary: "#1A73E8", secondary: "#4285F4" },
            borderRadius: { button: "8px" },
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css" rel="stylesheet"/>
    <style>
      body { font-family: 'Roboto', sans-serif; background: linear-gradient(135deg, #F8FAFF 0%, #EEF2FF 100%); min-height: 100vh; }
      .floating-label { position: absolute; pointer-events: none; left: 40px; top: 18px; transition: 0.2s ease all; }
      .input-field:focus ~ .floating-label, .input-field:not(:placeholder-shown) ~ .floating-label { top: 8px; font-size: 0.75rem; color: #1A73E8; }
      .input-field:focus { border-color: #1A73E8; }
      .login-card { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); animation: fadeIn 0.5s ease-out; }
      @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
      input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus, input:-webkit-autofill:active {
          -webkit-box-shadow: 0 0 0 30px white inset !important;
          box-shadow: 0 0 0 30px white inset !important;
      }
    </style>
  </head>
  <body class="flex items-center justify-center p-4">
    <div class="login-card bg-white rounded-2xl w-full max-w-md p-8">
      <div class="text-center mb-8">
        <h1 class="font-['Pacifico'] text-3xl text-primary mb-2">Life Church</h1>
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">Bem-vindo(a)</h2>
        <p class="text-gray-500">Faça login para continuar</p>
      </div>
        
      <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>
      
      <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
      <?php endif; ?>

      <form class="space-y-6" method="POST" action="login.php">
        <div class="relative"><i class="ri-mail-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="email" id="email" name="email" class="input-field w-full h-14 pl-10 pr-3 pt-6 pb-2 bg-white border border-gray-300 rounded focus:outline-none" placeholder=" " required /><label for="email" class="floating-label text-gray-500 text-sm">Email</label></div>
        <div class="relative"><i class="ri-lock-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="password" id="password" name="password" class="input-field w-full h-14 pl-10 pr-3 pt-6 pb-2 bg-white border border-gray-300 rounded focus:outline-none" placeholder=" " required /><label for="password" class="floating-label text-gray-500 text-sm">Senha</label></div>
        <button type="submit" class="w-full h-12 bg-primary text-white font-medium rounded-button whitespace-nowrap flex items-center justify-center hover:bg-blue-700">Entrar</button>
      </form>

      <div class="mt-8 text-center"><p class="text-gray-600 text-sm">Não tem uma conta? <a href="register.php" class="text-primary font-medium hover:underline">Registe-se</a></p></div>
    </div>
  </body>
</html>
