<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = connect_db();

// --- LÓGICA AJAX PARA ATUALIZAR PRESENÇA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_attendance') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Ação inválida.'];
    
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'Sessão expirada. Por favor, faça login novamente.';
        echo json_encode($response);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $celula_id = (int)$_POST['celula_id'];
    $mes = $_POST['mes'];
    $member_id = (int)$_POST['member_id'];
    $date = $_POST['date'];
    $is_present = $_POST['is_present'] === 'sim' ? 'sim' : 'nao';

    $can_edit = false;
    if ($_SESSION['user_role'] === 'master_admin') {
        $can_edit = true;
    } elseif ($_SESSION['user_role'] === 'lider') {
        $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
        $stmt_verify->bind_param("ii", $celula_id, $user_id);
        $stmt_verify->execute();
        if ($stmt_verify->get_result()->num_rows > 0) $can_edit = true;
        $stmt_verify->close();
    }

    if (!$can_edit) {
        $response['message'] = 'Não tem permissão para editar este relatório.';
        echo json_encode($response);
        $conn->close();
        exit;
    }

    $stmt_find = $conn->prepare("SELECT id, participacoes_membros_json FROM celula_relatorios WHERE celula_id = ? AND mes_referencia = ?");
    $stmt_find->bind_param("is", $celula_id, $mes);
    $stmt_find->execute();
    $report = $stmt_find->get_result()->fetch_assoc();
    $stmt_find->close();

    $participations = [];
    $report_id = null;
    if ($report) {
        $report_id = $report['id'];
        $participations = json_decode($report['participacoes_membros_json'], true) ?: [];
    }

    $member_found_index = -1;
    foreach ($participations as $index => $p) {
        if (isset($p['membro_id']) && $p['membro_id'] == $member_id) {
            $member_found_index = $index;
            break;
        }
    }

    if ($member_found_index === -1) {
        $stmt_member_name = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt_member_name->bind_param("i", $member_id);
        $stmt_member_name->execute();
        $member_info = $stmt_member_name->get_result()->fetch_assoc();
        $stmt_member_name->close();

        $new_participation = ['membro_id' => $member_id, 'nome' => $member_info['name'], 'presenca_datas' => []];
        $participations[] = $new_participation;
        $member_found_index = count($participations) - 1;
    }

    if (!isset($participations[$member_found_index]['presenca_datas'])) {
        $participations[$member_found_index]['presenca_datas'] = [];
    }
    $participations[$member_found_index]['presenca_datas'][$date] = $is_present;

    $updated_json = json_encode($participations, JSON_UNESCAPED_UNICODE);

    if ($report_id) {
        $stmt_update = $conn->prepare("UPDATE celula_relatorios SET participacoes_membros_json = ? WHERE id = ?");
        $stmt_update->bind_param("si", $updated_json, $report_id);
        if ($stmt_update->execute()) {
            $response = ['success' => true, 'message' => 'Presença atualizada.'];
        } else {
            $response['message'] = 'Erro ao atualizar o relatório.';
        }
        $stmt_update->close();
    } else {
        $stmt_create = $conn->prepare("INSERT INTO celula_relatorios (celula_id, lider_id, mes_referencia, participacoes_membros_json) VALUES (?, ?, ?, ?)");
        $stmt_create->bind_param("iiss", $celula_id, $user_id, $mes, $updated_json);
        if ($stmt_create->execute()) {
            $response = ['success' => true, 'message' => 'Relatório criado e presença guardada.'];
        } else {
            $response['message'] = 'Erro ao criar o relatório.';
        }
        $stmt_create->close();
    }
    
    $conn->close();
    echo json_encode($response);
    exit; // Termina o script para não renderizar o HTML
}

// --- LÓGICA PARA RENDERIZAR A PÁGINA ---
if (!in_array($_SESSION['user_role'], ['lider', 'master_admin'])) {
    $_SESSION['error_message'] = 'Acesso restrito.';
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
$message = '';
$error = '';
$celula = null;
$celula_id_a_gerir = null;
$membros_celula = [];
$report_data = null;
$meeting_dates = [];
$dia_encontro_nome_pt = '';

if ($user_role === 'lider') {
    $stmt_find_celula = $conn->prepare("SELECT id, nome, dia_encontro FROM celulas WHERE lider_id = ?");
    $stmt_find_celula->bind_param("i", $user_id);
    $stmt_find_celula->execute();
    $celula = $stmt_find_celula->get_result()->fetch_assoc();
    if ($celula) {
        $celula_id_a_gerir = $celula['id'];
    } else {
        $error = "Você não é líder de nenhuma célula. Crie uma célula primeiro.";
    }
    $stmt_find_celula->close();
} elseif ($user_role === 'master_admin' && isset($_GET['celula_id'])) {
    $celula_id_a_gerir = (int)$_GET['celula_id'];
    $stmt_celula = $conn->prepare("SELECT c.id, c.nome, c.dia_encontro, u.name as lider_nome FROM celulas c JOIN users u ON c.lider_id = u.id WHERE c.id = ?");
    $stmt_celula->bind_param("i", $celula_id_a_gerir);
    $stmt_celula->execute();
    $celula = $stmt_celula->get_result()->fetch_assoc();
    $user_name = $celula['lider_nome'] ?? 'N/A';
    $stmt_celula->close();
}

if ($celula) {
    $stmt_membros = $conn->prepare("SELECT id, name FROM users WHERE celula_id = ? ORDER BY name ASC");
    $stmt_membros->bind_param("i", $celula['id']);
    $stmt_membros->execute();
    $result_membros = $stmt_membros->get_result();
    while ($row = $result_membros->fetch_assoc()) { $membros_celula[] = $row; }
    $stmt_membros->close();

    $selected_month = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

    $stmt_report = $conn->prepare("SELECT * FROM celula_relatorios WHERE celula_id = ? AND mes_referencia = ?");
    $stmt_report->bind_param("is", $celula['id'], $selected_month);
    $stmt_report->execute();
    $result_report = $stmt_report->get_result();
    if ($result_report->num_rows > 0) {
        $report_data = $result_report->fetch_assoc();
        $report_data['participacoes_membros_json'] = json_decode($report_data['participacoes_membros_json'], true);
    }
    $stmt_report->close();
    
    $dia_encontro_en = $celula['dia_encontro'] ?? 'Saturday';
    $dias_pt = ["Sunday"=>"Domingo", "Monday"=>"Segunda-feira", "Tuesday"=>"Terça-feira", "Wednesday"=>"Quarta-feira", "Thursday"=>"Quinta-feira", "Friday"=>"Sexta-feira", "Saturday"=>"Sábado"];
    $dia_encontro_nome_pt = $dias_pt[$dia_encontro_en];

    $year = date('Y', strtotime($selected_month));
    $month = date('m', strtotime($selected_month));
    $num_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($day = 1; $day <= $num_days; $day++) {
        $date = "$year-$month-$day";
        if (date('l', strtotime($date)) == $dia_encontro_en) {
            $meeting_dates[] = $date;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registar_relatorio') {
    $celula_id_post = (int)$_POST['celula_id'];
    $mes_referencia = $_POST['mes_referencia'];
    
    $stmt_fetch_existing = $conn->prepare("SELECT participacoes_membros_json FROM celula_relatorios WHERE celula_id = ? AND mes_referencia = ?");
    $stmt_fetch_existing->bind_param("is", $celula_id_post, $mes_referencia);
    $stmt_fetch_existing->execute();
    $existing_data = $stmt_fetch_existing->get_result()->fetch_assoc();
    $stmt_fetch_existing->close();
    $participacoes_membros_json = $existing_data['participacoes_membros_json'] ?? '[]';

    $visitantes = []; if (isset($_POST['visitante_nome'])) { foreach ($_POST['visitante_nome'] as $index => $nome) { if (!empty($nome)) { $visitantes[] = ['nome' => $nome, 'endereco' => $_POST['visitante_endereco'][$index], 'outros_dados' => $_POST['visitante_outros_dados'][$index]]; } } }
    $candidatos = []; if (isset($_POST['candidato_nome'])) { foreach ($_POST['candidato_nome'] as $index => $nome) { if (!empty($nome)) { $candidatos[] = ['nome' => $nome, 'tipo' => 'Batismo']; } } }
    $eventos = []; if (isset($_POST['evento_data'])) { foreach ($_POST['evento_data'] as $index => $data) { if (!empty($data)) { $eventos[] = [ 'data' => $data, 'ceia' => isset($_POST['evento_ceia'][$index]), 'oracao' => isset($_POST['evento_oracao'][$index]), 'confraternizacao' => isset($_POST['evento_confraternizacao'][$index]), 'intercessao' => isset($_POST['evento_intercessao'][$index]), 'servindo' => isset($_POST['evento_servindo'][$index]), ]; } } }

    $visitantes_json = json_encode($visitantes, JSON_UNESCAPED_UNICODE);
    $candidatos_json = json_encode($candidatos, JSON_UNESCAPED_UNICODE);
    $eventos_json = json_encode($eventos, JSON_UNESCAPED_UNICODE);
    
    $stmt_insert = $conn->prepare("REPLACE INTO celula_relatorios (celula_id, lider_id, mes_referencia, participacoes_membros_json, visitantes_json, candidatos_batismo_json, eventos_celula_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt_insert === false) { $error = "Erro CRÍTICO: " . $conn->error; }
    else {
        $stmt_insert->bind_param("iisssss", $celula_id_post, $user_id, $mes_referencia, $participacoes_membros_json, $visitantes_json, $candidatos_json, $eventos_json);
        if ($stmt_insert->execute()) { $message = "Dados adicionais do relatório guardados com sucesso!"; }
        else { $error = "Erro ao guardar dados adicionais: " . $stmt_insert->error; }
        $stmt_insert->close();
    }
     header("Location: " . $_SERVER['PHP_SELF'] . "?celula_id=" . $celula_id_a_gerir . "&mes=" . $mes_referencia);
     exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Relatório Mensal da Célula - Life Church</title>
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
      .table-responsive-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .attendance-cell { cursor: pointer; transition: background-color 0.2s; }
      .attendance-cell.present { background-color: #BBDEFB; font-weight: bold; }
      .attendance-cell:hover { background-color: #E3F2FD; }
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
                <div class="px-4 mb-6">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Menu Principal</p>
                    <a href="dashboard.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-home-5-line ri-lg mr-3"></i><span>Página Inicial</span></a>
                    <?php if (in_array($_SESSION['user_role'], ['lider', 'master_admin'])): ?>
                        <a href="celulas.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1"><i class="ri-group-2-line ri-lg mr-3"></i><span>Minha Célula</span></a>
                        <a href="celulas_presencas.php<?php if($user_role === 'master_admin' && $celula_id_a_gerir) echo '?celula_id='.$celula_id_a_gerir; ?>" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 active"><i class="ri-file-list-3-line ri-lg mr-3"></i><span>Lançar Relatório</span></a>
                    <?php endif; ?>
                </div>
                <div class="px-4">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Sistema</p>
                    <a href="settings.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg mb-1 hover:bg-gray-100"><i class="ri-settings-3-line ri-lg mr-3"></i><span>Definições</span></a>
                    <a href="logout.php" class="sidebar-item relative flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100"><i class="ri-logout-box-line ri-lg mr-3"></i><span>Sair</span></a>
                </div>
             </nav>
        </aside>

        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden"></div>

        <div class="flex-1 flex flex-col w-full min-w-0">
            <header class="bg-white border-b border-gray-200 shadow-sm z-20 sticky top-0">
                <div class="flex items-center justify-between h-16 px-6">
                     <div class="flex items-center">
                         <button id="open-sidebar-btn" class="lg:hidden mr-4 text-gray-600 hover:text-primary"><i class="ri-menu-line ri-lg"></i></button>
                         <h1 class="text-lg font-medium text-gray-800">Relatório Mensal da Célula</h1>
                     </div>
                 </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 space-y-6 overflow-y-auto">
                <?php if ($message): ?><div id="alert-message" class="bg-blue-500 border-l-4 border-blue-700 text-white p-4 rounded-md shadow flex justify-between items-center"><span><?php echo $message; ?></span><button onclick="this.parentElement.style.display='none'"><i class="ri-close-line"></i></button></div><?php endif; ?>
                <?php if ($error): ?><div id="alert-error" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow flex justify-between items-center"><span><?php echo htmlspecialchars($error); ?></span><button onclick="this.parentElement.style.display='none'"><i class="ri-close-line"></i></button></div><?php endif; ?>
                
                <?php if ($celula): ?>
                    <div class="bg-white p-4 rounded-lg shadow-md flex items-center space-x-4">
                        <form method="GET" action="celulas_presencas.php" class="flex items-center space-x-3">
                             <?php if($user_role === 'master_admin'): ?>
                                <input type="hidden" name="celula_id" value="<?php echo $celula_id_a_gerir; ?>">
                            <?php endif; ?>
                            <label for="mes" class="text-sm font-medium text-gray-700">Ver Relatório de:</label>
                            <input type="month" id="mes" name="mes" value="<?php echo $selected_month; ?>" class="mt-1 block px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent rounded-button shadow-sm font-medium text-white bg-primary hover:bg-blue-700">Ver Mês</button>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Registo de Participação de <?php echo date('F \d\e Y', strtotime($selected_month)); ?></h3>
                        <p class="text-sm text-gray-600 mb-4">Dia de encontro da célula: <strong><?php echo $dia_encontro_nome_pt; ?></strong>. Clique numa célula de presença para marcar 'X'.</p>
                        <div class="table-responsive-container">
                            <table id="attendance-table" class="w-full text-sm text-left border-collapse border border-gray-400">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th rowspan="2" class="px-2 py-1 border border-gray-400 text-center font-medium">Nº</th>
                                        <th rowspan="2" class="px-2 py-1 border border-gray-400 font-medium">Nome Completo</th>
                                        <th colspan="<?php echo count($meeting_dates); ?>" class="px-2 py-1 border border-gray-400 text-center font-medium">CÉLULA - Datas de Encontro</th>
                                    </tr>
                                    <tr>
                                        <?php if (empty($meeting_dates)): ?>
                                            <th class="px-2 py-1 border border-gray-400 text-center font-medium">N/A</th>
                                        <?php else: foreach ($meeting_dates as $date): ?>
                                            <th class="px-2 py-1 border border-gray-400 text-center font-medium"><?php echo date('d', strtotime($date)); ?></th>
                                        <?php endforeach; endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($membros_celula)): ?>
                                        <tr><td colspan="<?php echo 2 + count($meeting_dates); ?>" class="text-center py-4">Nenhum membro nesta célula.</td></tr>
                                    <?php else: foreach ($membros_celula as $index => $membro): 
                                        $member_attendances = [];
                                        if ($report_data && isset($report_data['participacoes_membros_json'])) {
                                            foreach ($report_data['participacoes_membros_json'] as $participation) {
                                                if ($participation['membro_id'] == $membro['id']) {
                                                    $member_attendances = $participation;
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                    <tr class="border-b border-gray-300">
                                        <td class="px-2 py-1 border border-gray-400 text-center"><?php echo $index + 1; ?></td>
                                        <td class="px-2 py-1 border border-gray-400"><?php echo htmlspecialchars($membro['name']); ?></td>
                                        <?php if (empty($meeting_dates)): ?>
                                            <td class="px-2 py-1 border border-gray-400 text-center">-</td>
                                        <?php else: foreach ($meeting_dates as $date): 
                                            $is_present = isset($member_attendances['presenca_datas'][$date]) && $member_attendances['presenca_datas'][$date] === 'sim';
                                        ?>
                                            <td class="px-2 py-1 border border-gray-400 text-center font-bold attendance-cell <?php echo $is_present ? 'present' : ''; ?>" 
                                                data-member-id="<?php echo $membro['id']; ?>" data-date="<?php echo $date; ?>">
                                                <?php echo $is_present ? 'X' : ''; ?>
                                            </td>
                                        <?php endforeach; endif; ?>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <button type="button" id="show-form-btn" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-button text-white bg-primary hover:bg-blue-700">
                            Lançar/Editar Relatório Completo
                        </button>
                    </div>

                    <div id="report-form-container" class="hidden">
                         <form method="POST" action="celulas_presencas.php?celula_id=<?php echo $celula_id_a_gerir; ?>&mes=<?php echo $selected_month; ?>" class="space-y-8">
                            <input type="hidden" name="action" value="registar_relatorio">
                            <input type="hidden" name="celula_id" value="<?php echo $celula_id_a_gerir; ?>">
                            <input type="hidden" name="mes_referencia" value="<?php echo $selected_month; ?>">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                <div class="bg-white p-6 rounded-lg shadow-md"><h3 class="text-lg font-medium text-gray-900 mb-4">Visitantes</h3><div id="visitantes-container"><div class="grid grid-cols-3 gap-4 items-end"><label class="text-sm font-medium col-span-1">Nome</label><label class="text-sm font-medium col-span-1">Endereço</label><label class="text-sm font-medium col-span-1">Outros dados</label></div><div class="grid grid-cols-3 gap-4 items-center mt-2"><input type="text" name="visitante_nome[]" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"><input type="text" name="visitante_endereco[]" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"><input type="text" name="visitante_outros_dados[]" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"></div></div><button type="button" id="add-visitante" class="mt-4 text-sm text-primary hover:underline">+ Adicionar visitante</button></div>
                                <div class="bg-white p-6 rounded-lg shadow-md"><h3 class="text-lg font-medium text-gray-900 mb-4">Candidatos a Batismo</h3><div id="candidatos-container"><label class="text-sm font-medium">Nome do Candidato</label><div class="items-center mt-2"><input type="text" name="candidato_nome[]" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"></div></div><button type="button" id="add-candidato" class="mt-4 text-sm text-primary hover:underline">+ Adicionar candidato</button></div>
                            </div>
                            <div class="bg-white p-6 rounded-lg shadow-md"><h3 class="text-lg font-medium text-gray-900 mb-4">Eventos da Célula no Mês</h3><div id="eventos-container" class="table-responsive-container"><div class="grid grid-cols-6 gap-x-4 gap-y-2 items-end text-center min-w-[600px]"><label class="text-sm font-medium">Data</label><label class="text-sm font-medium">Ceia</label><label class="text-sm font-medium">Oração</label><label class="text-sm font-medium">Confrat.</label><label class="text-sm font-medium">Intercessão</label><label class="text-sm font-medium">Servindo</label></div><div class="grid grid-cols-6 gap-x-4 gap-y-2 items-center mt-2 min-w-[600px]"><input type="date" name="evento_data[]" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"><input type="checkbox" name="evento_ceia[0]" class="h-5 w-5 rounded border-gray-400 text-primary focus:ring-primary justify-self-center"><input type="checkbox" name="evento_oracao[0]" class="h-5 w-5 rounded border-gray-400 text-primary focus:ring-primary justify-self-center"><input type="checkbox" name="evento_confraternizacao[0]" class="h-5 w-5 rounded border-gray-400 text-primary focus:ring-primary justify-self-center"><input type="checkbox" name="evento_intercessao[0]" class="h-5 w-5 rounded border-gray-400 text-primary focus:ring-primary justify-self-center"><input type="checkbox" name="evento_servindo[0]" class="h-5 w-5 rounded border-gray-400 text-primary focus:ring-primary justify-self-center"></div></div><button type="button" id="add-evento" class="mt-4 text-sm text-primary hover:underline">+ Adicionar evento</button></div>
                            <div class="flex justify-end pt-4"><button type="submit" class="w-full sm:w-auto inline-flex justify-center py-3 px-8 border border-transparent rounded-button shadow-sm font-medium text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">Guardar Dados Adicionais</button></div>
                        </form>
                    </div>

                <?php elseif(!$error): ?><div class="text-center py-10"><p class="text-gray-600">A carregar dados da célula...</p></div><?php endif; ?>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.getElementById('sidebar'), openBtn = document.getElementById('open-sidebar-btn'), closeBtn = document.getElementById('close-sidebar-btn'), overlay = document.getElementById('sidebar-overlay');
        const showSidebar = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
        const hideSidebar = () => { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); };
        if(openBtn) openBtn.addEventListener('click', showSidebar);
        if(closeBtn) closeBtn.addEventListener('click', hideSidebar);
        if(overlay) overlay.addEventListener('click', hideSidebar);

        const showFormBtn = document.getElementById('show-form-btn');
        const formContainer = document.getElementById('report-form-container');
        if (showFormBtn && formContainer) {
            showFormBtn.addEventListener('click', () => {
                formContainer.classList.toggle('hidden');
            });
        }
        
        const attendanceCells = document.querySelectorAll('.attendance-cell');
        attendanceCells.forEach(cell => {
            cell.addEventListener('click', function() {
                const memberId = this.dataset.memberId;
                const date = this.dataset.date;
                const isPresent = !this.classList.contains('present');
                
                this.classList.toggle('present');
                this.textContent = isPresent ? 'X' : '';
                
                const formData = new FormData();
                formData.append('action', 'update_attendance');
                formData.append('celula_id', '<?php echo $celula_id_a_gerir; ?>');
                formData.append('mes', '<?php echo $selected_month; ?>');
                formData.append('member_id', memberId);
                formData.append('date', date);
                formData.append('is_present', isPresent ? 'sim' : 'nao');

                fetch('celulas_presencas.php', { // Aponta para o próprio ficheiro
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Erro ao guardar a presença: ' + data.message);
                        this.classList.toggle('present');
                        this.textContent = this.classList.contains('present') ? 'X' : '';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Ocorreu um erro de comunicação. Tente novamente.');
                    this.classList.toggle('present');
                    this.textContent = this.classList.contains('present') ? 'X' : '';
                });
            });
        });
    });
    </script>
</body>
</html>
