<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$response = ['success' => false, 'message' => 'Ação inválida.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action']) || $_POST['action'] !== 'update_attendance') {
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Sessão expirada. Por favor, faça login novamente.';
    echo json_encode($response);
    exit;
}

$conn = connect_db();
$user_id = $_SESSION['user_id'];

// --- Dados do POST ---
$celula_id = (int)$_POST['celula_id'];
$mes = $_POST['mes'];
$member_id = (int)$_POST['member_id'];
$date = $_POST['date'];
$is_present = $_POST['is_present'] === 'sim' ? 'sim' : 'nao';

// --- Validação de Permissão ---
$can_edit = false;
if ($_SESSION['user_role'] === 'master_admin') {
    $can_edit = true;
} elseif ($_SESSION['user_role'] === 'lider') {
    $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
    $stmt_verify->bind_param("ii", $celula_id, $user_id);
    $stmt_verify->execute();
    if ($stmt_verify->get_result()->num_rows > 0) {
        $can_edit = true;
    }
    $stmt_verify->close();
}

if (!$can_edit) {
    $response['message'] = 'Não tem permissão para editar este relatório.';
    echo json_encode($response);
    exit;
}

// --- Lógica de Atualização ---
// 1. Verificar se já existe um relatório para este mês.
$stmt_find = $conn->prepare("SELECT id, participacoes_membros_json FROM celula_relatorios WHERE celula_id = ? AND mes_referencia = ?");
$stmt_find->bind_param("is", $celula_id, $mes);
$stmt_find->execute();
$result = $stmt_find->get_result();
$report = $result->fetch_assoc();
$stmt_find->close();

$participations = [];
$report_id = null;

if ($report) {
    // Relatório existe, carregar os dados
    $report_id = $report['id'];
    $participations = json_decode($report['participacoes_membros_json'], true) ?: [];
}

// 2. Encontrar ou criar a entrada para o membro
$member_found_index = -1;
foreach ($participations as $index => $p) {
    if ($p['membro_id'] == $member_id) {
        $member_found_index = $index;
        break;
    }
}

if ($member_found_index === -1) {
    // Membro não encontrado no relatório, adicionar uma nova entrada
    $stmt_member_name = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt_member_name->bind_param("i", $member_id);
    $stmt_member_name->execute();
    $member_info = $stmt_member_name->get_result()->fetch_assoc();
    $stmt_member_name->close();

    $new_participation = [
        'membro_id' => $member_id,
        'nome' => $member_info['name'],
        'presenca_datas' => [] // novo campo para presenças por data
    ];
    $participations[] = $new_participation;
    $member_found_index = count($participations) - 1;
}

// 3. Atualizar a presença para a data específica
if (!isset($participations[$member_found_index]['presenca_datas'])) {
    $participations[$member_found_index]['presenca_datas'] = [];
}
$participations[$member_found_index]['presenca_datas'][$date] = $is_present;

// 4. Guardar o JSON atualizado na base de dados
$updated_json = json_encode($participations, JSON_UNESCAPED_UNICODE);

if ($report_id) {
    // Atualizar relatório existente
    $stmt_update = $conn->prepare("UPDATE celula_relatorios SET participacoes_membros_json = ? WHERE id = ?");
    $stmt_update->bind_param("si", $updated_json, $report_id);
    if ($stmt_update->execute()) {
        $response['success'] = true;
        $response['message'] = 'Presença atualizada com sucesso.';
    } else {
        $response['message'] = 'Erro ao atualizar o relatório.';
    }
    $stmt_update->close();
} else {
    // Criar um novo relatório para o mês
    $stmt_create = $conn->prepare("INSERT INTO celula_relatorios (celula_id, lider_id, mes_referencia, participacoes_membros_json) VALUES (?, ?, ?, ?)");
    $stmt_create->bind_param("iiss", $celula_id, $user_id, $mes, $updated_json);
    if ($stmt_create->execute()) {
        $response['success'] = true;
        $response['message'] = 'Relatório criado e presença guardada.';
    } else {
        $response['message'] = 'Erro ao criar o relatório.';
    }
    $stmt_create->close();
}

$conn->close();
echo json_encode($response);
?>
