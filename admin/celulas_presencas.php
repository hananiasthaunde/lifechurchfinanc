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
$user_role = $_SESSION['user_role'];
$error_message_display = '';

// --- Verificação e Criação da Tabela `registo_atividades` ---
$table_check = $conn->query("SHOW TABLES LIKE 'registo_atividades'");
if ($table_check->num_rows == 0) {
    $sql_create_table = "
    CREATE TABLE registo_atividades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        celula_id INT NOT NULL,
        lider_id INT NOT NULL,
        data_registo DATE NOT NULL,
        tipo_registo ENUM('celula', 'culto') NOT NULL,
        participacoes_json JSON,
        visitantes_json JSON,
        candidatos_json JSON,
        eventos_json JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (celula_id) REFERENCES celulas(id) ON DELETE CASCADE,
        FOREIGN KEY (lider_id) REFERENCES users(id) ON DELETE CASCADE
    );";
    if (!$conn->query($sql_create_table)) {
        $error_message_display = "Erro fatal: Não foi possível criar a tabela 'registo_atividades'. Verifique as permissões da base de dados.";
    }
}


// --- LÓGICA AJAX ---
if (isset($_REQUEST['action'])) {
    error_reporting(0); // Suprime notices para garantir uma resposta JSON limpa
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Ação inválida.'];
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
        exit;
    }
    
    $celula_id = (int)($_REQUEST['celula_id'] ?? 0);

    // Validação de permissão
    if ($user_role === 'lider') {
        $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
        if ($stmt_verify) {
            $stmt_verify->bind_param("ii", $celula_id, $user_id);
            $stmt_verify->execute();
            if ($stmt_verify->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
                exit;
            }
        }
    }

    $action = $_REQUEST['action'];

    if ($action === 'save_activity' || $action === 'update_activity') {
        $data_registo = $_POST['data_registo'];
        $tipo_registo = $_POST['tipo_registo'];
        $participacoes = json_decode($_POST['participacoes'], true);
        $visitantes = json_decode($_POST['visitantes'], true);
        $candidatos = json_decode($_POST['candidatos'], true);
        $eventos = json_decode($_POST['eventos'], true);

        $json_participacoes = json_encode($participacoes);
        $json_visitantes = json_encode($visitantes);
        $json_candidatos = json_encode($candidatos);
        $json_eventos = json_encode($eventos);

        if ($action === 'save_activity') {
            $stmt = $conn->prepare("INSERT INTO registo_atividades (celula_id, lider_id, data_registo, tipo_registo, participacoes_json, visitantes_json, candidatos_json, eventos_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $celula_id, $user_id, $data_registo, $tipo_registo, $json_participacoes, $json_visitantes, $json_candidatos, $json_eventos);
        } else { // update_activity
            $record_id = (int)$_POST['record_id'];
            $stmt = $conn->prepare("UPDATE registo_atividades SET data_registo=?, tipo_registo=?, participacoes_json=?, visitantes_json=?, candidatos_json=?, eventos_json=? WHERE id=? AND celula_id=?");
            $stmt->bind_param("ssssssii", $data_registo, $tipo_registo, $json_participacoes, $json_visitantes, $json_candidatos, $json_eventos, $record_id, $celula_id);
        }
        
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Registo guardado com sucesso!'];
        } else {
            $response['message'] = 'Erro ao guardar o registo: ' . $conn->error;
        }
    } elseif ($action === 'delete_activity') {
        $record_id = (int)$_POST['record_id'];
        $stmt = $conn->prepare("DELETE FROM registo_atividades WHERE id = ? AND celula_id = ?");
        $stmt->bind_param("ii", $record_id, $celula_id);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Registo apagado com sucesso!'];
        } else {
            $response['message'] = 'Erro ao apagar o registo.';
        }
    } elseif ($action === 'get_activity_details') {
        $record_id = (int)$_GET['record_id'];
        $stmt = $conn->prepare("SELECT * FROM registo_atividades WHERE id = ? AND celula_id = ?");
        $stmt->bind_param("ii", $record_id, $celula_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $record = $result->fetch_assoc();
            // Assegura que os campos JSON são retornados como arrays
            $record['participacoes_json'] = json_decode($record['participacoes_json'], true) ?: [];
            $record['visitantes_json'] = json_decode($record['visitantes_json'], true) ?: [];
            $record['candidatos_json'] = json_decode($record['candidatos_json'], true) ?: [];
            $record['eventos_json'] = json_decode($record['eventos_json'], true) ?: [];
            $response = ['success' => true, 'data' => $record];
        } else {
            $response['message'] = 'Registo não encontrado.';
        }
    }

    echo json_encode($response);
    $conn->close();
    exit;
}


// --- LÓGICA PARA RENDERIZAR A PÁGINA ---
$celula = null;
$membros_celula = [];
$registos_do_mes = [];
$currentPage = basename($_SERVER['SCRIPT_NAME']); // Para o menu ativo
$selected_month = $_GET['mes'] ?? date('Y-m'); // Filtro de Mês

if ($user_role === 'lider') {
    $stmt_find_celula = $conn->prepare("SELECT id, nome FROM celulas WHERE lider_id = ?");
    if ($stmt_find_celula) {
        $stmt_find_celula->bind_param("i", $user_id);
        $stmt_find_celula->execute();
        $result = $stmt_find_celula->get_result();
        $celula = $result ? $result->fetch_assoc() : null;
        $stmt_find_celula->close();
    }
} elseif ($user_role === 'master_admin' && isset($_GET['celula_id'])) {
    $stmt_find_celula = $conn->prepare("SELECT id, nome FROM celulas WHERE id = ?");
    if ($stmt_find_celula) {
        $stmt_find_celula->bind_param("i", $_GET['celula_id']);
        $stmt_find_celula->execute();
        $result = $stmt_find_celula->get_result();
        $celula = $result ? $result->fetch_assoc() : null;
        $stmt_find_celula->close();
    }
}

if ($celula) {
    $stmt_membros = $conn->prepare("SELECT id, name FROM users WHERE celula_id = ? ORDER BY name ASC");
    if ($stmt_membros) {
        $stmt_membros->bind_param("i", $celula['id']);
        $stmt_membros->execute();
        $result_membros = $stmt_membros->get_result();
        if ($result_membros) {
            while ($row = $result_membros->fetch_assoc()) {
                $membros_celula[] = $row;
            }
        }
        $stmt_membros->close();
    }
    
    // Buscar registos do mês selecionado
    $stmt_registos = $conn->prepare("SELECT * FROM registo_atividades WHERE celula_id = ? AND DATE_FORMAT(data_registo, '%Y-%m') = ? ORDER BY data_registo DESC");
    if ($stmt_registos) {
        $stmt_registos->bind_param("is", $celula['id'], $selected_month);
        $stmt_registos->execute();
        $result_registos = $stmt_registos->get_result();
        if ($result_registos) {
            while ($row = $result_registos->fetch_assoc()) {
                $registos_do_mes[] = $row;
            }
        }
        $stmt_registos->close();
    } else {
        $error_message_display = "Erro ao buscar registos do mês. Verifique se a tabela 'registo_atividades' existe e está correta.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require_once __DIR__ . '/../includes/pwa_head.php'; ?>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registo de Atividades da Célula - Life Church</title>
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script>
      tailwind.config = { theme: { extend: { colors: { primary: "#1976D2", secondary: "#BBDEFB" }, borderRadius: { button: "8px" } } } };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Pacifico&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css"/>
    <style>
      body { font-family: 'Roboto', sans-serif; background-color: #f9fafb; }
      .sidebar-item.active { background-color: #E3F2FD; color: #1976D2; font-weight: 500; }
      .sidebar-item.active::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background-color: #1976D2; }
      .form-section { border-left: 3px solid #1976D2; }
      .notification { transition: opacity 0.5s, transform 0.5s; }
      .modal { transition: opacity 0.3s ease; }
      .modal-content { transition: transform 0.3s ease, opacity 0.3s ease; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="lg:flex">
        <aside id="sidebar" class="w-64 h-screen bg-white border-r border-gray-200 flex-shrink-0 flex flex-col fixed lg:sticky top-0 z-40 transform -translate-x-full lg:translate-x-0">
             <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                 <span class="text-2xl font-['Pacifico'] text-primary">Life Church</span>
                  <button id="close-sidebar-btn" class="lg:hidden text-gray-500 hover:text-gray-800"><i class="ri-close-line ri-xl"></i></button>
             </div>
             <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-4 mb-6"><p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                    <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['lider', 'master_admin'])): ?>
                        <a href="celulas.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-group-2-line ri-lg mr-3"></i><span>Minha Célula</span></a>
                        <a href="celulas_presencas.php<?php if($user_role === 'master_admin' && $celula) echo '?celula_id='.$celula['id']; ?>" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 <?php echo $currentPage === 'celulas_presencas.php' ? 'active' : ''; ?>"><i class="ri-file-list-3-line ri-lg mr-3"></i><span>Registar Atividades</span></a>
                    <?php endif; ?>
                </div>
                <div class="px-4"><p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
                    <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                    <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
                </div>
             </nav>
        </aside>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="bg-white border-b border-gray-200 shadow-sm z-20 sticky top-0"><div class="flex items-center justify-between h-16 px-6"><div class="flex items-center"><?php if($user_role !== 'lider'): ?><button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary"><i class="ri-menu-line ri-lg"></i></button><?php endif; ?><h1 class="text-lg font-medium text-gray-800">Registo de Atividades da Célula</h1></div></div></header>

            <main class="flex-1 p-4 sm:p-6 space-y-6 overflow-y-auto">
                <div id="notification-container" class="fixed top-20 right-6 z-50 space-y-2"></div>
                
                <?php if ($error_message_display): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow">
                        <p><?php echo htmlspecialchars($error_message_display); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!$celula): ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md shadow">
                        <p>Nenhuma célula selecionada. Se você é um líder, certifique-se de que sua célula está registada. Se é administrador, selecione uma célula na página de gestão de células.</p>
                    </div>
                <?php else: ?>
                    <form id="activity-form" class="pb-20 lg:pb-0">
                        <input type="hidden" name="celula_id" value="<?php echo $celula['id']; ?>">
                        <input type="hidden" name="record_id" id="record_id" value="">

                        <!-- Stepper Header -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <h2 id="step-title" class="text-xl font-bold text-gray-800">Passo 1: Detalhes</h2>
                                <span class="text-sm font-medium text-gray-500" id="step-counter">1 de 3</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div id="progress-bar" class="bg-primary h-2.5 rounded-full transition-all duration-300" style="width: 33%"></div>
                            </div>
                        </div>
                        
                        <!-- Step 1: Configuração -->
                        <div id="step-1" class="step-content space-y-6">
                            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Configuração da Reunião</h3>
                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Registo</label>
                                        <div class="grid grid-cols-2 gap-4">
                                            <label class="relative cursor-pointer group">
                                                <input type="radio" name="tipo_registo" value="celula" class="peer sr-only" checked>
                                                <div class="p-4 rounded-xl border-2 border-gray-100 bg-white hover:bg-gray-50 peer-checked:border-primary peer-checked:bg-blue-50 transition-all text-center">
                                                    <div class="w-10 h-10 mx-auto bg-blue-100 text-primary rounded-full flex items-center justify-center mb-2">
                                                        <i class="ri-group-line ri-lg"></i>
                                                    </div>
                                                    <div class="font-bold text-gray-700 peer-checked:text-primary">Célula</div>
                                                    <div class="text-xs text-gray-500">Semanal</div>
                                                </div>
                                            </label>
                                            <label class="relative cursor-pointer group">
                                                <input type="radio" name="tipo_registo" value="culto" class="peer sr-only">
                                                <div class="p-4 rounded-xl border-2 border-gray-100 bg-white hover:bg-gray-50 peer-checked:border-primary peer-checked:bg-blue-50 transition-all text-center">
                                                    <div class="w-10 h-10 mx-auto bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-2">
                                                        <i class="ri-church-line ri-lg"></i>
                                                    </div>
                                                    <div class="font-bold text-gray-700 peer-checked:text-primary">Culto</div>
                                                    <div class="text-xs text-gray-500">Domingo</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="data_registo" class="block text-sm font-medium text-gray-700 mb-2">Data da Atividade</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="ri-calendar-event-line text-gray-400"></i>
                                            </div>
                                            <input type="date" id="data_registo" name="data_registo" value="<?php echo date('Y-m-d'); ?>" required class="pl-10 block w-full px-4 py-3 bg-white border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-gray-700">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Participação -->
                        <div id="step-2" class="step-content hidden space-y-6">
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                                <div class="p-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center sticky top-0 z-10">
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-800">Chamada</h3>
                                        <p class="text-xs text-gray-500"><?php echo count($membros_celula); ?> Membros Listados</p>
                                    </div>
                                    <button type="button" id="mark-all-present-btn" class="text-sm bg-white border border-gray-200 text-primary font-medium py-1.5 px-3 rounded-lg hover:bg-gray-50 shadow-sm transition-colors">
                                        <i class="ri-check-double-line mr-1"></i> Todos Presentes
                                    </button>
                                </div>
                                <div id="participation-container" class="divide-y divide-gray-100">
                                    <?php 
                                    $colors = ['bg-red-100 text-red-600', 'bg-blue-100 text-blue-600', 'bg-green-100 text-green-600', 'bg-yellow-100 text-yellow-600', 'bg-purple-100 text-purple-600', 'bg-pink-100 text-pink-600', 'bg-indigo-100 text-indigo-600'];
                                    foreach($membros_celula as $index => $membro): 
                                        $initials = strtoupper(substr($membro['name'], 0, 2));
                                        $colorClass = $colors[$index % count($colors)];
                                    ?>
                                    <div class="p-4 bg-white hover:bg-gray-50 transition-colors member-card" data-member-id="<?php echo $membro['id']; ?>">
                                        <div class="flex items-center gap-4 mb-3">
                                            <div class="h-12 w-12 rounded-full <?php echo $colorClass; ?> flex items-center justify-center font-bold text-lg shadow-sm border border-white">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900 text-base"><?php echo htmlspecialchars($membro['name']); ?></p>
                                                <p class="text-xs text-gray-500 flex items-center gap-1"><i class="ri-user-line"></i> Membro</p>
                                            </div>
                                        </div>
                                        
                                        <div class="pl-[4rem]"> <!-- Indent alignments with name -->
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                <label class="cursor-pointer">
                                                    <input type="checkbox" data-type="presente" class="peer sr-only">
                                                    <div class="px-4 py-2 rounded-full border border-gray-200 text-gray-600 text-sm font-medium peer-checked:bg-green-500 peer-checked:text-white peer-checked:border-green-600 transition-all shadow-sm">
                                                        Presente
                                                    </div>
                                                </label>
                                                <label class="cursor-pointer">
                                                    <input type="checkbox" data-type="ausente" class="peer sr-only">
                                                    <div class="px-4 py-2 rounded-full border border-gray-200 text-gray-600 text-sm font-medium peer-checked:bg-red-500 peer-checked:text-white peer-checked:border-red-600 transition-all shadow-sm">
                                                        Ausente
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="extra-fields hidden space-y-3 animate-fade-in-down">
                                                 <input type="text" data-type="motivo" placeholder="Motivo da ausência..." class="w-full text-sm px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent bg-gray-50">
                                            </div>
                                            
                                            <div class="flex gap-4 mt-2">
                                                <label class="inline-flex items-center cursor-pointer group">
                                                    <div class="relative">
                                                        <input type="checkbox" data-type="discipulado" class="sr-only peer">
                                                        <div class="w-5 h-5 border-2 border-gray-300 rounded peer-checked:bg-primary peer-checked:border-primary transition-colors"></div>
                                                        <i class="ri-check-line text-white absolute top-0 left-0 w-full h-full flex items-center justify-center opacity-0 peer-checked:opacity-100 text-xs"></i>
                                                    </div>
                                                    <span class="ml-2 text-sm text-gray-600 group-hover:text-gray-900 transition-colors">Discipulado</span>
                                                </label>
                                                <label class="inline-flex items-center cursor-pointer group">
                                                    <div class="relative">
                                                        <input type="checkbox" data-type="pastoral" class="sr-only peer">
                                                        <div class="w-5 h-5 border-2 border-gray-300 rounded peer-checked:bg-primary peer-checked:border-primary transition-colors"></div>
                                                        <i class="ri-check-line text-white absolute top-0 left-0 w-full h-full flex items-center justify-center opacity-0 peer-checked:opacity-100 text-xs"></i>
                                                    </div>
                                                    <span class="ml-2 text-sm text-gray-600 group-hover:text-gray-900 transition-colors">Pastoral</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Conclusão -->
                        <div id="step-3" class="step-content hidden space-y-6">
                            
                            <!-- Visitantes -->
                            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-4">
                                     <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                        <div class="bg-pink-100 p-2 rounded-full text-pink-500">
                                            <i class="ri-user-heart-line text-xl"></i>
                                        </div>
                                        Visitantes
                                     </h3>
                                     <button type="button" id="add-visitor-btn" onclick="addVisitor()" class="text-sm font-bold text-pink-600 bg-pink-50 hover:bg-pink-100 px-4 py-2 rounded-lg transition-colors flex items-center gap-1">
                                        <i class="ri-add-line text-lg"></i> Adicionar
                                     </button>
                                </div>
                                <div id="visitors-container" class="space-y-3"></div>
                                <p id="no-visitors-msg" class="text-sm text-gray-400 text-center py-6 bg-gray-50 rounded-xl border-2 border-dashed border-gray-100 italic">Nenhum visitante registado hoje.</p>
                            </div>

                            <!-- Candidatos -->
                            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-4">
                                     <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                        <div class="bg-green-100 p-2 rounded-full text-green-500">
                                            <i class="ri-seedling-line text-xl"></i>
                                        </div>
                                        Candidatos
                                     </h3>
                                     <button type="button" id="add-candidate-btn" onclick="addCandidate()" class="text-sm font-bold text-green-600 bg-green-50 hover:bg-green-100 px-4 py-2 rounded-lg transition-colors flex items-center gap-1">
                                        <i class="ri-add-line text-lg"></i> Adicionar
                                     </button>
                                </div>
                                <div id="candidates-container" class="space-y-3"></div>
                                <p id="no-candidates-msg" class="text-sm text-gray-400 text-center py-6 bg-gray-50 rounded-xl border-2 border-dashed border-gray-100 italic">Nenhum candidato registado hoje.</p>
                            </div>
                            
                            <!-- Eventos -->
                            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                                <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                                    <div class="bg-blue-100 p-2 rounded-full text-blue-500">
                                        <i class="ri-calendar-todo-line text-xl"></i>
                                    </div>
                                    Eventos Realizados
                                </h3>
                                <div id="events-container" class="space-y-4">
                                    <?php
                                    $events = [
                                        'ceia' => ['label' => 'Santa Ceia', 'desc' => 'Celebração da comunhão', 'icon' => 'ri-goblet-line', 'color' => 'text-red-500', 'bg' => 'bg-red-50'],
                                        'oracao' => ['label' => 'Oração', 'desc' => 'Intercessão e clamor', 'icon' => 'ri-hand-heart-line', 'color' => 'text-yellow-600', 'bg' => 'bg-yellow-50'],
                                        'confraternizacao' => ['label' => 'Confraternização', 'desc' => 'Momento de comunhão', 'icon' => 'ri-cake-2-line', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50']
                                    ];
                                    foreach ($events as $key => $event): ?>
                                    <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 hover:border-primary/30 transition-colors bg-white shadow-sm">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-full <?php echo $event['bg']; ?> flex items-center justify-center">
                                                <i class="<?php echo $event['icon']; ?> <?php echo $event['color']; ?> text-xl"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-800"><?php echo $event['label']; ?></p>
                                                <p class="text-xs text-gray-500"><?php echo $event['desc']; ?></p>
                                            </div>
                                        </div>
                                        <div class="relative">
                                             <input type="date" name="eventos[<?php echo $key; ?>]" class="event-input block w-40 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:bg-white transition-colors text-right text-gray-600">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Form Navigation Buttons - Only shows within form context -->
                        <div id="form-nav-buttons" class="mt-6 pb-24 lg:pb-0 flex justify-center items-center gap-3">
                             <button type="button" id="prev-step-btn" class="hidden text-gray-600 font-medium py-2.5 px-5 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200 bg-white">
                                 <i class="ri-arrow-left-line mr-1"></i> Voltar
                             </button>
                             <button type="button" id="next-step-btn" class="bg-primary text-white font-bold py-2.5 px-6 rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/30 text-center">
                                 Próximo Passo <i class="ri-arrow-right-line ml-1"></i>
                             </button>
                             <button type="submit" id="submit-btn" class="hidden bg-green-600 text-white font-bold py-2.5 px-6 rounded-xl hover:bg-green-700 transition-all shadow-lg shadow-green-500/30 items-center justify-center">
                                <i class="ri-check-double-line mr-2"></i> Finalizar
                            </button>
                        </div>
                    </form>
                    



                    <style>
                        .animate-fade-in-down { animation: fadeInDown 0.3s ease-out; }
                        @keyframes fadeInDown {
                            from { opacity: 0; transform: translateY(-10px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                    </style>

                    <div class="bg-white p-6 rounded-lg shadow-lg mb-24 lg:mb-0">
                        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
                            <h3 class="text-xl font-bold text-gray-800">Registos de <?php echo date('F, Y', strtotime($selected_month)); ?></h3>
                            <form method="get" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                <?php if($user_role === 'master_admin'): ?>
                                    <input type="hidden" name="celula_id" value="<?php echo $celula['id']; ?>">
                                <?php endif; ?>
                                <div class="flex items-center gap-2">
                                    <label for="mes" class="text-sm font-medium whitespace-nowrap">Mês:</label>
                                    <input type="month" id="mes" name="mes" value="<?php echo $selected_month; ?>" class="p-2 border rounded-md flex-1">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="bg-primary text-white py-2 px-4 rounded-button hover:bg-blue-700 flex-1 sm:flex-none">Filtrar</button>
                                    <a href="export_celula_report.php?celula_id=<?php echo $celula['id']; ?>&mes=<?php echo $selected_month; ?>" target="_blank" class="bg-gray-600 text-white py-2 px-4 rounded-button hover:bg-gray-700 text-sm font-medium flex items-center justify-center gap-2 flex-1 sm:flex-none">
                                        <i class="ri-file-pdf-2-line"></i>
                                        <span>Exportar</span>
                                    </a>
                                </div>
                            </form>
                        </div>
                        <div id="monthly-records-container" class="space-y-3">
                            <?php if(empty($registos_do_mes)): ?>
                                <p class="text-center text-gray-500 py-4" id="no-records-message">Nenhum registo para este mês.</p>
                            <?php else: ?>
                                <?php foreach($registos_do_mes as $registo): ?>
                                <div class="border rounded-lg p-4 flex flex-col sm:flex-row justify-between items-center" data-record-id="<?php echo $registo['id']; ?>">
                                    <div>
                                        <p class="font-bold"><?php echo date('d/m/Y', strtotime($registo['data_registo'])); ?> - <span class="capitalize"><?php echo $registo['tipo_registo']; ?></span></p>
                                    </div>
                                    <div class="flex gap-2 items-center mt-2 sm:mt-0">
                                        <button class="details-btn text-blue-600 hover:underline text-sm" data-id="<?php echo $registo['id']; ?>">Ver Detalhes</button>
                                        <button class="edit-btn text-green-600 hover:text-green-800 p-2 rounded-full hover:bg-green-50" title="Editar" data-id="<?php echo $registo['id']; ?>"><i class="ri-pencil-line"></i></button>
                                        <button class="delete-btn text-red-600 hover:text-red-800 p-2 rounded-full hover:bg-red-50" title="Apagar" data-id="<?php echo $registo['id']; ?>"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Modal para Detalhes do Registo -->
    <div id="details-modal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-semibold">Detalhes do Registo</h3>
                <button class="close-modal-btn text-gray-500 hover:text-gray-800"><i class="ri-close-line ri-xl"></i></button>
            </div>
            <div id="details-modal-body" class="p-6 max-h-[70vh] overflow-y-auto">
                <!-- Conteúdo dinâmico aqui -->
            </div>
        </div>
    </div>

    <!-- Modal para Confirmar Apagar -->
    <div id="delete-confirmation-modal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Confirmar Exclusão</h3>
                <p class="mt-2 text-sm text-gray-600">Tem a certeza que deseja apagar este registo? A ação é irreversível. Para confirmar, digite <strong class="text-red-600">lifechurch</strong> abaixo.</p>
                <input type="text" id="delete-confirmation-key" class="mt-4 w-full p-2 border border-gray-300 rounded-md" placeholder="Escreva a chave de confirmação">
                <p id="delete-error-msg" class="text-red-500 text-sm mt-1 hidden">Chave de confirmação incorreta.</p>
            </div>
            <div class="flex justify-end items-center p-4 border-t bg-gray-50 rounded-b-lg space-x-3">
                <button type="button" class="close-modal-btn bg-gray-200 text-gray-800 py-2 px-4 rounded-button hover:bg-gray-300">Cancelar</button>
                <button id="confirm-delete-btn" class="bg-red-600 text-white py-2 px-4 rounded-button hover:bg-red-700 disabled:opacity-50" disabled>Apagar Registo</button>
            </div>
        </div>
    </div>

    <script>
    // --- FUNÇÕES GLOBAIS PARA ADICIONAR VISITANTES E CANDIDATOS ---
    const membrosDaCelulaGlobal = <?php echo json_encode($membros_celula ?? []); ?>;
    
    function addVisitor() {
        const container = document.getElementById('visitors-container');
        if (!container) return;
        
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 mb-2 animate-fade-in-down';
        row.innerHTML = `
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 flex-grow visitor-row">
                <input type="text" placeholder="Nome" class="p-2 border rounded-md visitor-name">
                <input type="text" placeholder="Endereço/Contacto" class="p-2 border rounded-md visitor-contact">
                <input type="text" placeholder="Outros dados" class="p-2 border rounded-md visitor-other">
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-red-500 p-2 hover:bg-red-50 rounded-full">
                <i class="ri-delete-bin-line"></i>
            </button>
        `;
        container.appendChild(row);
        
        // Ocultar mensagem "Nenhum visitante"
        const noVisitorMsg = document.getElementById('no-visitors-msg');
        if(noVisitorMsg) noVisitorMsg.style.display = 'none';
    }
    
    function addCandidate() {
        const container = document.getElementById('candidates-container');
        if (!container) return;
        
        let options = '<option value="">Selecione um membro</option>';
        if (Array.isArray(membrosDaCelulaGlobal)) {
            membrosDaCelulaGlobal.forEach(m => {
                options += `<option value="${m.id}">${m.name}</option>`;
            });
        }
        
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 mb-2 animate-fade-in-down';
        row.innerHTML = `
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 flex-grow candidate-row items-center">
                <select class="p-2 border rounded-md candidate-name">${options}</select>
                <label class="flex items-center text-xs">
                    <input type="checkbox" class="h-4 w-4 rounded candidate-salvation">
                    <span class="ml-1">Salvação</span>
                </label>
                <label class="flex items-center text-xs">
                    <input type="checkbox" class="h-4 w-4 rounded candidate-water">
                    <span class="ml-1">Bat. Água</span>
                </label>
                <label class="flex items-center text-xs">
                    <input type="checkbox" class="h-4 w-4 rounded candidate-spirit">
                    <span class="ml-1">Bat. Espírito</span>
                </label>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-red-500 p-2 hover:bg-red-50 rounded-full">
                <i class="ri-delete-bin-line"></i>
            </button>
        `;
        container.appendChild(row);
        
        // Ocultar mensagem "Nenhum candidato"
        const noCandidateMsg = document.getElementById('no-candidates-msg');
        if(noCandidateMsg) noCandidateMsg.style.display = 'none';
    }
    </script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.getElementById('sidebar');
        const openBtn = document.getElementById('open-sidebar-btn');
        const closeBtn = document.getElementById('close-sidebar-btn');
        const overlay = document.getElementById('sidebar-overlay');
        const showSidebar = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
        const hideSidebar = () => { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); };
        if(openBtn) openBtn.addEventListener('click', showSidebar);
        if(closeBtn) closeBtn.addEventListener('click', hideSidebar);
        if(overlay) overlay.addEventListener('click', hideSidebar);

        const activityForm = document.getElementById('activity-form');
        if (!activityForm) return;

        const celulaId = activityForm.querySelector('[name="celula_id"]').value;
        const notificationContainer = document.getElementById('notification-container');
        const participationContainer = document.getElementById('participation-container');
        const formTitle = document.getElementById('form-title');
        const recordIdInput = document.getElementById('record_id');
        const cancelEditBtn = document.getElementById('cancel-edit-btn');
        const submitBtn = document.getElementById('submit-btn');
        const membrosDaCelula = <?php echo json_encode($membros_celula); ?>;
        
        // --- SISTEMA DE NOTIFICAÇÃO ---
        function showNotification(message, type = 'success') {
            const bgColor = type === 'success' ? 'bg-green-100' : 'bg-red-100';
            const borderColor = type === 'success' ? 'border-green-500' : 'border-red-500';
            const textColor = type === 'success' ? 'text-green-700' : 'text-red-700';

            const notification = document.createElement('div');
            notification.className = `notification ${bgColor} ${borderColor} ${textColor} border-l-4 p-4 rounded-md shadow-lg flex justify-between items-center transform translate-x-full opacity-0`;
            notification.innerHTML = `<span>${message}</span><button class="close-notification"><i class="ri-close-line"></i></button>`;
            notificationContainer.appendChild(notification);
            
            setTimeout(() => { notification.classList.remove('translate-x-full', 'opacity-0'); }, 100);
            const close = () => {
                notification.classList.add('opacity-0');
                setTimeout(() => notification.remove(), 500);
            };
            notification.querySelector('.close-notification').addEventListener('click', close);
            setTimeout(close, 5000);
        }

        // --- LÓGICA DO WIZARD (PASSOS) ---
        let currentStep = 1;
        const totalSteps = 3;
        const prevBtn = document.getElementById('prev-step-btn');
        const nextBtn = document.getElementById('next-step-btn');
        // submitBtn já definido na linha 581
        const stepTitle = document.getElementById('step-title');
        const stepCounter = document.getElementById('step-counter');
        const progressBar = document.getElementById('progress-bar');
        const titles = ['Detalhes', 'Chamada', 'Conclusão'];

        function updateWizard() {
            try {
                // Mostrar/Ocultar Passos
                for(let i=1; i<=totalSteps; i++) {
                    const el = document.getElementById(`step-${i}`);
                    if(el) {
                        if(i === currentStep) {
                            el.classList.remove('hidden');
                            el.classList.add('animate-fade-in-down');
                        } else {
                            el.classList.add('hidden');
                            el.classList.remove('animate-fade-in-down');
                        }
                    }
                }
                
                // Atualizar Títulos e Progresso
                if(stepTitle) stepTitle.textContent = `Passo ${currentStep}: ${titles[currentStep-1]}`;
                if(stepCounter) stepCounter.textContent = `${currentStep} de ${totalSteps}`;
                if(progressBar) progressBar.style.width = `${(currentStep / totalSteps) * 100}%`;

                // Atualizar Botões
                if(prevBtn) prevBtn.classList.toggle('hidden', currentStep === 1);
                
                if(nextBtn) {
                     if (currentStep === totalSteps) {
                        nextBtn.classList.add('hidden');
                     } else {
                        nextBtn.classList.remove('hidden');
                     }
                }

                if(submitBtn) {
                    if (currentStep === totalSteps) {
                        submitBtn.classList.remove('hidden');
                    } else {
                        submitBtn.classList.add('hidden');
                    }
                }
                
                // Scroll para o topo
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                console.error("Erro no Wizard:", e);
            }
        }

        if(nextBtn) nextBtn.addEventListener('click', () => {
             // Validação Passo 1
             if(currentStep === 1) {
                 const dateInput = document.getElementById('data_registo');
                 if(dateInput && !dateInput.value) {
                     showNotification('Por favor, selecione a data.', 'error');
                     return;
                 }
             }
             if(currentStep < totalSteps) {
                 currentStep++;
                 updateWizard();
             }
        });

        if(prevBtn) prevBtn.addEventListener('click', () => {
            if(currentStep > 1) {
                currentStep--;
                updateWizard();
            }
        });

        // "Todos Presentes"
        const markAllPresentBtn = document.getElementById('mark-all-present-btn');
        if(markAllPresentBtn) {
            markAllPresentBtn.addEventListener('click', () => {
                const checkboxes = participationContainer.querySelectorAll('input[data-type="presente"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                   if (!allChecked) {
                       cb.checked = true;
                       // Desmarcar ausente
                       const row = cb.closest('.member-card');
                       const ausenteCb = row.querySelector('input[data-type="ausente"]');
                       const motivo = row.querySelector('input[data-type="motivo"]');
                       if(ausenteCb) ausenteCb.checked = false;
                       if(motivo) motivo.classList.add('hidden');
                   } else {
                       cb.checked = false;
                   }
                });
                
                markAllPresentBtn.innerHTML = !allChecked 
                    ? '<i class="ri-close-line mr-1"></i> Desmarcar Todos' 
                    : '<i class="ri-check-double-line mr-1"></i> Todos Presentes';
            });
        }

        // Inicializar Wizard
        updateWizard();

        // --- LÓGICA DO FORMULÁRIO ---
        function resetForm() {
            if(activityForm) activityForm.reset();
            if(recordIdInput) recordIdInput.value = '';
            if(formTitle) formTitle.textContent = `Novo Registo para a Célula "<?php echo htmlspecialchars($celula['nome'] ?? ''); ?>"`;
            if(submitBtn) submitBtn.textContent = 'Guardar Registo';
            if(cancelEditBtn) cancelEditBtn.classList.add('hidden');
            const visitorsContainer = document.getElementById('visitors-container');
            const candidatesContainer = document.getElementById('candidates-container');
            if(visitorsContainer) visitorsContainer.innerHTML = '';
            if(candidatesContainer) candidatesContainer.innerHTML = '';
            document.querySelectorAll('input[data-type="motivo"]').forEach(input => input.classList.add('hidden'));
            const tipoRegistoInput = document.querySelector('input[name="tipo_registo"][value="celula"]');
            if(tipoRegistoInput) tipoRegistoInput.dispatchEvent(new Event('change'));
        }

        if(cancelEditBtn) cancelEditBtn.addEventListener('click', resetForm);

        // --- Lógica para Presente vs Ausente ---
        if(participationContainer) {
            participationContainer.addEventListener('change', (e) => {
                const memberRow = e.target.closest('[data-member-id]');
                if (!memberRow) return;

                const presenteCheckbox = memberRow.querySelector('input[data-type="presente"]');
                const ausenteCheckbox = memberRow.querySelector('input[data-type="ausente"]');
                const motivoInput = memberRow.querySelector('input[data-type="motivo"]');

                if (e.target.dataset.type === 'ausente' && e.target.checked) {
                    if (presenteCheckbox) presenteCheckbox.checked = false;
                    if(motivoInput) motivoInput.classList.remove('hidden');
                } else if (e.target.dataset.type === 'ausente' && !e.target.checked) {
                    if(motivoInput) motivoInput.classList.add('hidden');
                } else if (e.target.dataset.type === 'presente' && e.target.checked) {
                    if (ausenteCheckbox) ausenteCheckbox.checked = false;
                    if(motivoInput) motivoInput.classList.add('hidden');
                }
            });
        }

        // --- Lógica para alternar formulário Célula/Culto ---
        document.querySelectorAll('input[name="tipo_registo"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const isCelula = this.value === 'celula';
                document.querySelectorAll('.celula-options').forEach(el => {
                    el.style.display = isCelula ? 'flex' : 'none';
                });
            });
        });
        document.querySelector('input[name="tipo_registo"]:checked').dispatchEvent(new Event('change'));

        // --- Lógica para Adicionar Campos Dinâmicos ---
        function addRemovableRow(container, htmlContent) {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 mb-2';
            row.innerHTML = htmlContent;
            row.querySelector('.remove-row-btn').addEventListener('click', () => row.remove());
            container.appendChild(row);
        }

        const visitorsContainer = document.getElementById('visitors-container');
        document.getElementById('add-visitor-btn').addEventListener('click', () => {
            const content = `<div class="grid grid-cols-1 sm:grid-cols-3 gap-2 flex-grow visitor-row"><input type="text" placeholder="Nome" class="p-2 border rounded-md visitor-name"><input type="text" placeholder="Endereço/Contacto" class="p-2 border rounded-md visitor-contact"><input type="text" placeholder="Outros dados" class="p-2 border rounded-md visitor-other"></div><button type="button" class="remove-row-btn text-red-500 p-2 hover:bg-red-50 rounded-full"><i class="ri-delete-bin-line"></i></button>`;
            addRemovableRow(visitorsContainer, content);
        });

        const candidatesContainer = document.getElementById('candidates-container');
        document.getElementById('add-candidate-btn').addEventListener('click', () => {
            let options = '<option value="">Selecione um membro</option>';
            membrosDaCelula.forEach(m => {
                options += `<option value="${m.id}">${m.name}</option>`;
            });
            const content = `<div class="grid grid-cols-1 sm:grid-cols-4 gap-2 flex-grow candidate-row items-center"><select class="p-2 border rounded-md candidate-name">${options}</select><label class="flex items-center text-xs"><input type="checkbox" class="h-4 w-4 rounded candidate-salvation"><span class="ml-1">Salvação</span></label><label class="flex items-center text-xs"><input type="checkbox" class="h-4 w-4 rounded candidate-water"><span class="ml-1">Bat. Água</span></label><label class="flex items-center text-xs"><input type="checkbox" class="h-4 w-4 rounded candidate-spirit"><span class="ml-1">Bat. Espírito</span></label></div><button type="button" class="remove-row-btn text-red-500 p-2 hover:bg-red-50 rounded-full"><i class="ri-delete-bin-line"></i></button>`;
            addRemovableRow(candidatesContainer, content);
        });

        // --- Lógica para Guardar/Atualizar o Formulário ---
        activityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('=== FORM SUBMIT TRIGGERED ===');
            
            const isEditing = !!recordIdInput.value;
            console.log('Is Editing:', isEditing);

            const participations = Array.from(document.querySelectorAll('#participation-container > div[data-member-id]')).map(row => ({
                member_id: row.dataset.memberId,
                presente: row.querySelector('input[data-type="presente"]').checked,
                ausente: row.querySelector('input[data-type="ausente"]').checked,
                motivo: row.querySelector('input[data-type="motivo"]')?.value || '',
                discipulado: row.querySelector('input[data-type="discipulado"]')?.checked || false,
                pastoral: row.querySelector('input[data-type="pastoral"]')?.checked || false,
            }));
            console.log('Participations:', participations);
            
            const visitors = Array.from(document.querySelectorAll('.visitor-row')).map(row => ({
                nome: row.querySelector('.visitor-name')?.value || '',
                contacto: row.querySelector('.visitor-contact')?.value || '',
                outros: row.querySelector('.visitor-other')?.value || ''
            })).filter(v => v.nome);
            console.log('Visitors:', visitors);

            const candidates = Array.from(document.querySelectorAll('.candidate-row')).map(row => ({
                member_id: row.querySelector('.candidate-name')?.value || '',
                salvacao: row.querySelector('.candidate-salvation')?.checked || false,
                batismo_agua: row.querySelector('.candidate-water')?.checked || false,
                batismo_espirito: row.querySelector('.candidate-spirit')?.checked || false
            })).filter(c => c.member_id);
            console.log('Candidates:', candidates);
            
            const events = {};
            document.querySelectorAll('.event-input').forEach(input => {
                if (input.value) events[input.dataset.type] = input.value;
            });
            console.log('Events:', events);

            const formData = new FormData();
            formData.append('action', isEditing ? 'update_activity' : 'save_activity');
            if(isEditing) formData.append('record_id', recordIdInput.value);
            formData.append('celula_id', celulaId);
            formData.append('data_registo', document.getElementById('data_registo').value);
            formData.append('tipo_registo', document.querySelector('input[name="tipo_registo"]:checked').value);
            formData.append('participacoes', JSON.stringify(participations));
            formData.append('visitantes', JSON.stringify(visitors));
            formData.append('candidatos', JSON.stringify(candidates));
            formData.append('eventos', JSON.stringify(events));
            
            console.log('Celula ID:', celulaId);
            console.log('Data Registo:', document.getElementById('data_registo').value);
            console.log('Tipo Registo:', document.querySelector('input[name="tipo_registo"]:checked')?.value);
            console.log('Sending data to server...');

            fetch('celulas_presencas.php', { method: 'POST', body: formData })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Ocorreu um erro de comunicação.', 'error');
            });
        });

        // --- LÓGICA DOS BOTÕES DA LISTA DE REGISTOS ---
        const monthlyRecordsContainer = document.getElementById('monthly-records-container');
        const deleteModal = document.getElementById('delete-confirmation-modal');
        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        const deleteKeyInput = document.getElementById('delete-confirmation-key');
        let recordIdToDelete = null;

        monthlyRecordsContainer.addEventListener('click', function(e){
            const target = e.target;
            const recordRow = target.closest('[data-record-id]');
            if (!recordRow) return;
            const recordId = recordRow.dataset.recordId;

            if (target.closest('.delete-btn')) {
                recordIdToDelete = recordId;
                deleteModal.classList.remove('hidden');
            } else if (target.closest('.edit-btn')) {
                fetch(`celulas_presencas.php?action=get_activity_details&record_id=${recordId}&celula_id=${celulaId}`)
                    .then(res => res.json())
                    .then(response => {
                        if(response.success){
                            const record = response.data;
                            resetForm();
                            formTitle.textContent = `Editando Registo de ${new Date(record.data_registo + 'T00:00:00').toLocaleDateString('pt-BR')}`;
                            recordIdInput.value = record.id;
                            submitBtn.textContent = 'Atualizar Registo';
                            document.getElementById('data_registo').value = record.data_registo;
                            document.querySelector(`input[name="tipo_registo"][value="${record.tipo_registo}"]`).checked = true;
                            document.querySelector(`input[name="tipo_registo"][value="${record.tipo_registo}"]`).dispatchEvent(new Event('change'));

                            record.participacoes_json.forEach(p => {
                                const memberRow = participationContainer.querySelector(`[data-member-id="${p.member_id}"]`);
                                if(memberRow) {
                                    memberRow.querySelector('[data-type="presente"]').checked = p.presente;
                                    memberRow.querySelector('[data-type="ausente"]').checked = p.ausente;
                                    const motivoInput = memberRow.querySelector('[data-type="motivo"]');
                                    motivoInput.value = p.motivo;
                                    motivoInput.classList.toggle('hidden', !p.ausente);
                                    memberRow.querySelector('[data-type="discipulado"]').checked = p.discipulado;
                                    memberRow.querySelector('[data-type="pastoral"]').checked = p.pastoral;
                                }
                            });
                            
                            record.visitantes_json.forEach(v => {
                                document.getElementById('add-visitor-btn').click();
                                const newRow = visitorsContainer.lastChild;
                                newRow.querySelector('.visitor-name').value = v.nome;
                                newRow.querySelector('.visitor-contact').value = v.contacto;
                                newRow.querySelector('.visitor-other').value = v.outros;
                            });
                             record.candidatos_json.forEach(c => {
                                document.getElementById('add-candidate-btn').click();
                                const newRow = candidatesContainer.lastChild;
                                newRow.querySelector('.candidate-name').value = c.member_id;
                                newRow.querySelector('.candidate-salvation').checked = c.salvacao;
                                newRow.querySelector('.candidate-water').checked = c.batismo_agua;
                                newRow.querySelector('.candidate-spirit').checked = c.batismo_espirito;
                            });
                            for (const [type, date] of Object.entries(record.eventos_json)) {
                                const eventInput = document.querySelector(`.event-input[data-type="${type}"]`);
                                if (eventInput) eventInput.value = date;
                            }

                            cancelEditBtn.classList.remove('hidden');
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        } else {
                            showNotification(response.message, 'error');
                        }
                    });
            } else if (target.closest('.details-btn')) {
                 fetch(`celulas_presencas.php?action=get_activity_details&record_id=${recordId}&celula_id=${celulaId}`)
                    .then(res => res.json())
                    .then(response => {
                        if(response.success){
                            const record = response.data;
                            const modalBody = document.getElementById('details-modal-body');
                            let html = `<div class="space-y-6">`;
                            
                            html += `<div class="p-4 bg-gray-50 rounded-lg"><p><strong>Data:</strong> ${new Date(record.data_registo + 'T00:00:00').toLocaleDateString('pt-BR')}</p>`;
                            html += `<p><strong>Tipo:</strong> <span class="capitalize font-medium text-primary">${record.tipo_registo}</span></p></div>`;
                            
                            html += '<div><h4 class="text-md font-semibold text-gray-700 border-b pb-2 mb-2 flex items-center gap-2"><i class="ri-group-line"></i>Participação</h4><div class="space-y-2">';
                            record.participacoes_json.forEach(p => {
                                const membro = membrosDaCelula.find(m => m.id == p.member_id);
                                if (membro) {
                                    let statusHtml = p.ausente 
                                        ? `<span class="text-red-600">Ausente (${p.motivo || 'sem motivo'})</span>` 
                                        : '<span class="text-green-600">Presente</span>';
                                    
                                    let extrasHtml = '';
                                    if (record.tipo_registo === 'celula' && !p.ausente) {
                                        let extras = [];
                                        if (p.discipulado) extras.push('Discipulado');
                                        if (p.pastoral) extras.push('Pastoral');
                                        if (extras.length > 0) {
                                            extrasHtml = ` <span class="text-gray-500 text-xs">(${extras.join(', ')})</span>`;
                                        }
                                    }
                                    html += `<div class="text-sm flex justify-between items-center p-2 rounded-md hover:bg-gray-50"><span>${membro.name}</span> <span class="font-medium">${statusHtml}${extrasHtml}</span></div>`;
                                }
                            });
                            html += '</div></div>';

                            if(record.visitantes_json.length > 0) {
                                html += '<div><h4 class="text-md font-semibold text-gray-700 border-b pb-2 mb-2 flex items-center gap-2"><i class="ri-user-add-line"></i>Visitantes</h4><div class="space-y-2">';
                                record.visitantes_json.forEach(v => {
                                    html += `<div class="text-sm p-2 bg-gray-50 rounded-md"><strong>${v.nome}</strong> - ${v.contacto || 's/ contacto'} (${v.outros || 's/ dados'})</div>`;
                                });
                                html += '</div></div>';
                            }

                            if(record.candidatos_json.length > 0) {
                                html += '<div><h4 class="text-md font-semibold text-gray-700 border-b pb-2 mb-2 flex items-center gap-2"><i class="ri-user-heart-line"></i>Candidatos</h4><div class="space-y-2">';
                                record.candidatos_json.forEach(c => {
                                    const membro = membrosDaCelula.find(m => m.id == c.member_id);
                                    if(membro){
                                        let items = [];
                                        if (c.salvacao) items.push("Salvação");
                                        if (c.batismo_agua) items.push("Bat. Água");
                                        if (c.batismo_espirito) items.push("Bat. Espírito");
                                        html += `<div class="text-sm flex justify-between items-center p-2 rounded-md hover:bg-gray-50"><span>${membro.name}</span> <span class="font-medium">${items.join(', ')}</span></div>`;
                                    }
                                });
                                html += '</div></div>';
                            }
                            
                            if(Object.keys(record.eventos_json).length > 0) {
                                html += '<div><h4 class="text-md font-semibold text-gray-700 border-b pb-2 mb-2 flex items-center gap-2"><i class="ri-calendar-todo-line"></i>Eventos</h4><div class="grid grid-cols-2 gap-2 text-sm">';
                                for (const [type, date] of Object.entries(record.eventos_json)) {
                                     html += `<div class="p-2 bg-gray-50 rounded-md"><strong class="capitalize">${type}:</strong> ${new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')}</div>`;
                                }
                                html += '</div></div>';
                            }

                            html += `</div>`;
                            modalBody.innerHTML = html; 
                            document.getElementById('details-modal').classList.remove('hidden');
                        }
                    });
            }
        });

        // --- Lógica dos Modais ---
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.closest('.close-modal-btn')) {
                    modal.classList.add('hidden');
                }
            });
        });
        
        deleteKeyInput.addEventListener('input', () => {
            confirmDeleteBtn.disabled = deleteKeyInput.value.toLowerCase() !== 'lifechurch';
        });

        confirmDeleteBtn.addEventListener('click', () => {
            const formData = new FormData();
            formData.append('action', 'delete_activity');
            formData.append('record_id', recordIdToDelete);
            formData.append('celula_id', celulaId);
            fetch('celulas_presencas.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        showNotification(data.message, 'success');
                        document.querySelector(`[data-record-id="${recordIdToDelete}"]`).remove();
                    } else {
                        showNotification(data.message, 'error');
                    }
                    deleteModal.classList.add('hidden');
                    deleteKeyInput.value = '';
                    confirmDeleteBtn.disabled = true;
                });
        });
    });
    </script>
    <!-- Mobile Bottom Navigation -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around items-center h-16 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
        <a href="celulas.php" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary hover:bg-gray-50 transition-colors">
            <div class="mb-1"><i class="ri-group-2-line text-xl"></i></div>
            <span class="text-[10px] font-medium leading-none">Célula</span>
        </a>
        <a href="celulas_presencas.php<?php if($user_role === 'master_admin' && $celula) echo '?celula_id='.$celula['id']; ?>" class="flex flex-col items-center justify-center w-full h-full text-primary bg-blue-50 border-t-2 border-primary transition-colors">
            <div class="mb-1"><i class="ri-file-list-3-line text-xl"></i></div>
            <span class="text-[10px] font-medium leading-none">Atividades</span>
        </a>
        <a href="settings.php" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary hover:bg-gray-50 transition-colors">
            <div class="mb-1"><i class="ri-settings-3-line text-xl"></i></div>
            <span class="text-[10px] font-medium leading-none">Definições</span>
        </a>
    </nav>
</body>
</html>
