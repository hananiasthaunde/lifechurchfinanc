<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Galeria - Life Church</title>
    
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
        background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://placehold.co/1920x500/1a73e8/ffffff?text=Nossos+Momentos');
        background-size: cover;
        background-position: center;
      }
      .gallery-item {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
      }
      .gallery-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
      }
      .lightbox {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
        justify-content: center;
        align-items: center;
      }
      .lightbox-content {
        max-width: 90%;
        max-height: 80%;
      }
      .lightbox-close {
        position: absolute;
        top: 20px;
        right: 30px;
        color: #fff;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
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
                <a href="gallery.php" class="text-primary font-semibold">Galeria</a>
                <a href="contact.php" class="text-gray-600 hover:text-primary font-medium">Contacto</a>
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
                <a href="gallery.php" class="py-2 px-4 text-primary bg-blue-50 font-medium rounded-md">Galeria</a>
                <a href="contact.php" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Contacto</a>
            </nav>
        </div>
    </header>

    <!-- ========== MAIN CONTENT ========== -->
    <main>
        <!-- Page Header -->
        <section class="page-header py-24 md:py-32 flex items-center justify-center text-white">
            <div class="text-center px-4">
                <h1 class="text-4xl md:text-5xl font-extrabold">Galeria de Momentos</h1>
                <p class="text-lg mt-4 max-w-2xl mx-auto opacity-90">Reveja os momentos especiais da nossa comunidade, cultos e eventos.</p>
            </div>
        </section>

        <!-- Gallery Section -->
        <section class="py-24 bg-white">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900">A Vida na Life Church</h2>
                    <p class="text-gray-600 max-w-2xl mx-auto mt-4">Aqui você pode ver fotos das nossas igrejas, celebrações e da nossa amada comunidade em ação.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Gallery Item 1 -->
                    <div class="gallery-item bg-white rounded-lg overflow-hidden shadow-md cursor-pointer group">
                        <img src="https://placehold.co/600x400/e8f0fe/1a73e8?text=Culto+de+Domingo" alt="Culto de Domingo" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800">Culto de Domingo</h3>
                            <p class="text-gray-600 text-sm">Celebração e adoração em comunidade.</p>
                        </div>
                    </div>
                    <!-- Gallery Item 2 -->
                    <div class="gallery-item bg-white rounded-lg overflow-hidden shadow-md cursor-pointer group">
                        <img src="https://placehold.co/600x400/e8f0fe/1a73e8?text=Batismos" alt="Batismos" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800">Dia de Batismos</h3>
                            <p class="text-gray-600 text-sm">Um novo começo na jornada da fé.</p>
                        </div>
                    </div>
                    <!-- Gallery Item 3 -->
                    <div class="gallery-item bg-white rounded-lg overflow-hidden shadow-md cursor-pointer group">
                        <img src="https://placehold.co/600x400/e8f0fe/1a73e8?text=Ação+Social" alt="Ação Social" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800">Ação Social</h3>
                            <p class="text-gray-600 text-sm">Servindo e amando a nossa cidade.</p>
                        </div>
                    </div>
                     <!-- Gallery Item 4 -->
                    <div class="gallery-item bg-white rounded-lg overflow-hidden shadow-md cursor-pointer group">
                        <img src="https://placehold.co/600x400/e8f0fe/1a73e8?text=Grupo+de+Jovens" alt="Grupo de Jovens" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800">Encontro de Jovens</h3>
                            <p class="text-gray-600 text-sm">Energia e paixão pela nova geração.</p>
                        </div>
                    </div>
                     <!-- Gallery Item 5 -->
                    <div class="gallery-item bg-white rounded-lg overflow-hidden shadow-md cursor-pointer group">
                        <img src="https://placehold.co/600x400/e8f0fe/1a73e8?text=Conferência" alt="Conferência" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800">Conferência Anual</h3>
                            <p class="text-gray-600 text-sm">Aprendendo e crescendo juntos.</p>
                        </div>
                    </div>
                     <!-- Gallery Item 6 -->
                    <div class="gallery-item bg-white rounded-lg overflow-hidden shadow-md cursor-pointer group">
                        <img src="https://placehold.co/600x400/e8f0fe/1a73e8?text=Comunhão" alt="Comunhão" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-105">
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800">Momento de Comunhão</h3>
                            <p class="text-gray-600 text-sm">Fortalecendo laços como família.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Lightbox Modal -->
    <div id="lightbox" class="lightbox">
        <span class="lightbox-close" id="lightbox-close">&times;</span>
        <img class="lightbox-content" id="lightbox-img">
    </div>

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
      // Mobile menu toggle
      const menuButton = document.getElementById("mobile-menu-button");
      const mobileMenu = document.getElementById("mobile-menu");

      if (menuButton && mobileMenu) {
        menuButton.addEventListener("click", function () {
          mobileMenu.classList.toggle("hidden");
        });
      }

      // Lightbox functionality
      const lightbox = document.getElementById('lightbox');
      const lightboxImg = document.getElementById('lightbox-img');
      const closeBtn = document.getElementById('lightbox-close');
      const galleryItems = document.querySelectorAll('.gallery-item');

      galleryItems.forEach(item => {
          item.addEventListener('click', () => {
              const imgSrc = item.querySelector('img').src;
              lightboxImg.src = imgSrc;
              lightbox.style.display = 'flex';
          });
      });

      if(closeBtn) {
          closeBtn.addEventListener('click', () => {
              lightbox.style.display = 'none';
          });
      }

      if(lightbox) {
          lightbox.addEventListener('click', (e) => {
              if (e.target === lightbox) {
                  lightbox.style.display = 'none';
              }
          });
      }
    });
    </script>
</body>
</html>
