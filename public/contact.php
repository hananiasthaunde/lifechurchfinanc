<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$message_type = ''; // 'success' or 'error'

if ($_POST) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $msg = $_POST['message'] ?? '';
    
    if ($name && $email && $subject && $msg) {
        // Aqui você pode implementar o envio de email ou salvar no banco de dados.
        // Exemplo: mail('hananiasthaunde@gmail.com', $subject, $msg, 'From: ' . $email);
        
        $message = 'Mensagem enviada com sucesso! Entraremos em contacto em breve.';
        $message_type = 'success';
    } else {
        $message = 'Por favor, preencha todos os campos obrigatórios.';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contacto - Life Church</title>
    
    <!-- Tailwind CSS with Custom Theme -->
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: { 
                    primary: "#1a73e8",
                    secondary: "#f2b900" // Cor secundária para contraste
                },
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    pacifico: ['Pacifico', 'cursive'],
                },
                 borderRadius: {
                    'button': '8px',
                    'card': '16px',
                 },
            },
        },
    };
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Remixicon for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">

    <style>
      body {
        font-family: 'Inter', sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }
      .page-header {
        background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://placehold.co/1920x500/1a73e8/ffffff?text=Fale+Conosco');
        background-size: cover;
        background-position: center;
      }
    </style>
</head>
<body class="bg-gray-50">

    <!-- ========== HEADER ========== -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <a href="../index.html" class="text-2xl font-['Pacifico'] text-primary">Life Church</a>
            
            <nav class="hidden md:flex items-center space-x-8">
                <a href="../index.html" class="text-gray-600 hover:text-primary font-medium">Home</a>
                <a href="about.php" class="text-gray-600 hover:text-primary font-medium">Sobre</a>
                <a href="gallery.php" class="text-gray-600 hover:text-primary font-medium">Galeria</a>
                <a href="contact.php" class="text-primary font-semibold">Contacto</a>
            </nav>

            <div class="flex items-center space-x-4">
                <a href="login.php" class="bg-primary text-white px-5 py-2 !rounded-button whitespace-nowrap hover:bg-primary/90 transition-colors">
                    Aceder ao Portal
                </a>
                <button class="md:hidden w-10 h-10 flex items-center justify-center text-gray-700" id="mobile-menu-button">
                    <i class="ri-menu-line ri-xl"></i>
                </button>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div class="hidden" id="mobile-menu">
            <nav class="flex flex-col p-4 bg-white border-t border-gray-100">
                <a href="index.php" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Home</a>
                <a href="about.php" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Sobre</a>
                <a href="gallery.php" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Galeria</a>
                <a href="contact.php" class="py-2 px-4 text-primary bg-blue-50 font-medium rounded-md">Contacto</a>
            </nav>
        </div>
    </header>

    <!-- ========== MAIN CONTENT ========== -->
    <main>
        <!-- Page Header -->
        <section class="page-header py-24 md:py-32 flex items-center justify-center text-white">
            <div class="text-center px-4">
                <h1 class="text-4xl md:text-5xl font-extrabold">Entre em Contacto</h1>
                <p class="text-lg mt-4 max-w-2xl mx-auto opacity-90">Estamos aqui para ajudar. Envie-nos uma mensagem e retornaremos em breve.</p>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="py-24 bg-white">
            <div class="container mx-auto px-4">
                <div class="grid lg:grid-cols-2 gap-16 items-start">
                    <!-- Contact Form -->
                    <div class="bg-white p-8 rounded-card shadow-lg border border-gray-100">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">Envie sua Mensagem</h2>

                        <?php if ($message): ?>
                            <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="contact.php" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                                <input type="text" id="name" name="name" required class="w-full px-4 py-3 bg-gray-50 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                             <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Seu Email</label>
                                <input type="email" id="email" name="email" required class="w-full px-4 py-3 bg-gray-50 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                             <div>
                                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Assunto</label>
                                <input type="text" id="subject" name="subject" required class="w-full px-4 py-3 bg-gray-50 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50">
                            </div>
                             <div>
                                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Mensagem</label>
                                <textarea id="message" name="message" rows="5" required class="w-full px-4 py-3 bg-gray-50 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 resize-y"></textarea>
                            </div>
                            <div>
                                <button type="submit" class="w-full bg-primary text-white font-bold py-4 px-6 !rounded-button hover:bg-primary/90 transition-all duration-300 shadow-lg shadow-primary/30 hover:shadow-xl">
                                    Enviar Mensagem
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Contact Info -->
                    <div class="space-y-8 lg:mt-12">
                         <h2 class="text-2xl font-bold text-gray-900 mb-6">Informações de Contacto</h2>
                        <div class="flex items-start">
                             <div class="flex-shrink-0 w-12 h-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center">
                                <i class="ri-map-pin-2-line text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Nosso Endereço</h3>
                                <p class="text-gray-600">Cidade de Tete, Bairro Chingodzi</p>
                            </div>
                        </div>
                         <div class="flex items-start">
                            <div class="flex-shrink-0 w-12 h-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center">
                                <i class="ri-mail-send-line text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Email</h3>
                                <p class="text-gray-600">hananiasthaunde@gmail.com</p>
                            </div>
                        </div>
                         <div class="flex items-start">
                           <div class="flex-shrink-0 w-12 h-12 bg-primary/10 text-primary rounded-lg flex items-center justify-center">
                                <i class="ri-phone-line text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-800">Telefone</h3>
                                <p class="text-gray-600">+258 84 216 3212 / +258 87 929 9196</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ========== FOOTER ========== -->
    <footer class="bg-gray-900 text-white pt-16 pb-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                <div>
                    <h3 class="text-xl font-['Pacifico'] text-white mb-6">Life Church</h3>
                    <p class="text-gray-400 mb-6">
                        Simplificando a gestão, potencializando o ministério.
                    </p>
                     <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors"><i class="ri-facebook-fill"></i></a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors"><i class="ri-instagram-fill"></i></a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors"><i class="ri-youtube-fill"></i></a>
                    </div>
                </div>
                <div>
                     <h3 class="text-lg font-semibold mb-6">Navegação</h3>
                    <ul class="space-y-3">
                        <li><a href="index.php" class="text-gray-400 hover:text-white">Home</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white">Sobre</a></li>
                        <li><a href="gallery.php" class="text-gray-400 hover:text-white">Galeria</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white">Contacto</a></li>
                    </ul>
                </div>
                <div>
                     <h3 class="text-lg font-semibold mb-6">Portal do Membro</h3>
                    <ul class="space-y-3">
                        <li><a href="login.php" class="text-gray-400 hover:text-white">Login</a></li>
                        <li><a href="register.php" class="text-gray-400 hover:text-white">Registar</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Minha Conta</a></li>
                         <li><a href="#" class="text-gray-400 hover:text-white">Agenda</a></li>
                    </ul>
                </div>
                <div>
                     <h3 class="text-lg font-semibold mb-6">Contacto</h3>
                     <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start">
                           <i class="ri-map-pin-line mr-3 mt-1"></i>
                           <span>Cidade de Tete, Bairro Chingodzi</span>
                        </li>
                         <li class="flex items-center">
                           <i class="ri-phone-line mr-3"></i>
                           <span>+258 84 216 3212</span>
                        </li>
                         <li class="flex items-center">
                           <i class="ri-mail-line mr-3"></i>
                           <span>hananiasthaunde@gmail.com</span>
                        </li>
                     </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-gray-800 text-center">
                <p class="text-gray-500">&copy; <?php echo date('Y'); ?> Life Church. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
      const menuButton = document.getElementById("mobile-menu-button");
      const mobileMenu = document.getElementById("mobile-menu");

      if (menuButton && mobileMenu) {
        menuButton.addEventListener("click", function () {
          mobileMenu.classList.toggle("hidden");
        });
      }
    });
    </script>
</body>
</html>
