<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sobre Nós - Life Church</title>
    
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
        background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://placehold.co/1920x500/1a73e8/ffffff?text=Nossa+Visão');
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
                <a href="about.php" class="text-primary font-semibold">Sobre</a>
                <a href="gallery.php" class="text-gray-600 hover:text-primary font-medium">Galeria</a>
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
                <a href="../index.html" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Home</a>
                <a href="about.php" class="py-2 px-4 text-primary bg-blue-50 font-medium rounded-md">Sobre</a>
                <a href="gallery.php" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Galeria</a>
                <a href="contact.php" class="py-2 px-4 text-gray-700 hover:text-primary font-medium rounded-md hover:bg-gray-50">Contacto</a>
            </nav>
        </div>
    </header>

    <!-- ========== MAIN CONTENT ========== -->
    <main>
        <!-- Page Header -->
        <section class="page-header py-24 md:py-32 flex items-center justify-center text-white">
            <div class="text-center px-4">
                <h1 class="text-4xl md:text-5xl font-extrabold">Nossa Visão, Missão e Valores</h1>
                <p class="text-lg mt-4 max-w-3xl mx-auto opacity-90">O coração da Life Church: o que nos move e no que acreditamos.</p>
            </div>
        </section>

        <!-- Vision Section -->
        <section class="py-24 bg-white">
            <div class="container mx-auto px-4">
                <div class="text-center max-w-4xl mx-auto">
                    <span class="text-primary font-semibold uppercase tracking-wider">A Visão da Life Church</span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-6">Guiados por um Propósito Divino</h2>
                    <p class="text-gray-600 mb-8 leading-relaxed">Acreditamos que é essencial ter uma visão na Igreja. Em Provérbios 29:18 lemos: 'Onde não há visão, o povo perece.' Na Life Church somos pessoas com visão, temos um propósito, um sentido e estamos focados.</p>
                    <blockquote class="bg-primary/10 border-l-4 border-primary p-6 rounded-r-lg text-left">
                        <p class="text-xl italic text-gray-800 leading-relaxed">"Somos uma Igreja baseada em grupos célulares, amando a Jesus, servindo e discipulando pessoas, transformando comunidades e mudando as nações; uma vida de cada vez."</p>
                        <cite class="block text-right mt-4 not-italic text-gray-600">— Isaías 60:22</cite>
                    </blockquote>
                </div>
            </div>
        </section>
        
        <!-- Detailed Vision Explanation -->
        <section class="py-24 bg-gray-50">
            <div class="container mx-auto px-4 grid md:grid-cols-2 gap-12 lg:gap-16 items-center">
                 <div class="prose lg:prose-lg max-w-none text-gray-700">
                    <h3>Uma Igreja Familiar Baseada em Células</h3>
                    <p>Na Life Church reconhecemos que a igreja não é um edifício que frequentamos uma vez por semana, mas sim um povo que vive em comunidade, como uma família. Enfatizamos os Cultos de Celebração nos fins de semana e os nossos grupos de células, que se reúnem em vários bairros durante a semana.</p>

                    <h3>Amando a Jesus, Servindo e Discipulando Pessoas</h3>
                    <p>Amar a Jesus Cristo significa amar o que Ele ama: o seu povo e a sua Igreja. Seguimos o exemplo de Jesus, servindo todos ao nosso redor e dedicando-nos a discipular aqueles que caminham conosco. É a nossa responsabilidade alcançar e ensinar a próxima geração.</p>
                    
                    <h3>Transformando Comunidades e Mudando Nações</h3>
                    <p>Jesus nos chama para ser sal e luz (Mateus 5:13-16). Buscamos a transformação em todas as comunidades onde vivemos e atuamos. Para mudar uma nação, é preciso transformar uma comunidade, uma vida de cada vez.</p>
                </div>
                <div class="text-center">
                    <img src="https://placehold.co/500x600/e8f0fe/1a73e8?text=Propósito" alt="Pessoas unidas em comunidade" class="rounded-card shadow-xl w-full h-auto object-cover">
                </div>
            </div>
        </section>

        <!-- Values Section -->
        <section class="py-24 bg-white">
            <div class="container mx-auto px-4">
                 <div class="text-center mb-16 max-w-3xl mx-auto">
                    <span class="text-primary font-semibold uppercase tracking-wider">Nossos Valores</span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">Os Pilares da Nossa Fé</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="bg-gray-50 p-6 rounded-card border border-gray-100">
                        <i class="ri-book-open-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Baseado na Bíblia</h3>
                        <p class="text-gray-600">Somos inspirados pela Palavra de Deus e acreditamos em forte ensino bíblico.</p>
                    </div>
                     <div class="bg-gray-50 p-6 rounded-card border border-gray-100">
                        <i class="ri-user-follow-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Discipulado</h3>
                        <p class="text-gray-600">Ajudamos cada membro em sua jornada para que possam discipular outros.</p>
                    </div>
                     <div class="bg-gray-50 p-6 rounded-card border border-gray-100">
                        <i class="ri-home-heart-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Amamos a Família</h3>
                        <p class="text-gray-600">Focamos em relacionamentos fortes e em deixar uma herança divina.</p>
                    </div>
                     <div class="bg-gray-50 p-6 rounded-card border border-gray-100">
                        <i class="ri-fire-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Disciplinas Espirituais</h3>
                        <p class="text-gray-600">Vivemos uma vida apaixonada, dirigida pelo Espírito, através da oração, adoração e jejum.</p>
                    </div>
                     <div class="bg-gray-50 p-6 rounded-card border border-gray-100">
                        <i class="ri-global-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Multi-Nacional</h3>
                        <p class="text-gray-600">Somos uma igreja diversificada, multiétnica e inclusiva, com espaço para todos.</p>
                    </div>
                     <div class="bg-gray-50 p-6 rounded-card border border-gray-100">
                        <i class="ri-earth-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Missional</h3>
                        <p class="text-gray-600">Trazemos a transformação do amor de Deus para a nossa comunidade e além.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statement of Beliefs -->
        <section class="py-24 bg-gray-50">
             <div class="container mx-auto px-4">
                <div class="text-center mb-16 max-w-3xl mx-auto">
                    <span class="text-primary font-semibold uppercase tracking-wider">Declaração de Crenças</span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mt-2 mb-4">No Que Acreditamos</h2>
                    <p class="text-gray-600">Estes são os fundamentos da nossa fé, baseados na Palavra de Deus.</p>
                </div>
                 <div class="bg-white p-8 md:p-12 rounded-card shadow-lg prose max-w-none lg:prose-lg text-gray-700 columns-1 md:columns-2 lg:columns-3 gap-x-12">
                     <ul class="space-y-4">
                        <li>Que a Bíblia é a palavra inspirada e infalível de Deus.</li>
                        <li>Que existe um só Deus, Criador de tudo, em três pessoas: Pai, Filho e Espírito Santo.</li>
                        <li>Na completa divindade e humanidade de nosso Senhor Jesus Cristo.</li>
                        <li>No poder santificador do Espírito Santo.</li>
                        <li>No batismo do Espírito Santo, que capacita o crente para a vida, adoração e serviço.</li>
                        <li>Que todas as pessoas são criadas à imagem de Deus e importam profundamente para Ele.</li>
                        <li>Que todos pecaram e são salvos unicamente pela fé no sangue de Cristo.</li>
                        <li>No batismo dos crentes por imersão total na água.</li>
                        <li>Na observância regular da Ceia do Senhor.</li>
                        <li>Na ressurreição dos salvos para a vida eterna e dos não salvos para a condenação eterna.</li>
                        <li>Que Jesus Cristo é o único caminho para o céu.</li>
                        <li>No sacerdócio de todos os crentes.</li>
                        <li>Que Deus continua realizando milagres, inclusive curando os enfermos.</li>
                        <li>Na igreja local como a expressão visível da igreja universal.</li>
                        <li>Na família e no casamento heterossexual como a unidade básica da comunidade.</li>
                        <li>Que as missões estão no coração de Deus.</li>
                     </ul>
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
