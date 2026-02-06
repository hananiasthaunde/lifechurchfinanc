<?php
session_start();

// --- Segurança e Configuração ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Caminho para o autoload do mPDF

// --- Verificações de Acesso ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

if (!isset($_GET['celula_id']) || !isset($_GET['mes'])) {
    die("ID da célula ou mês não fornecido.");
}

$celula_id = (int)$_GET['celula_id'];
$mes = $_GET['mes'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$conn = connect_db();

// --- Validação de Permissão ---
if ($user_role !== 'master_admin') {
    $stmt_verify = $conn->prepare("SELECT id FROM celulas WHERE id = ? AND lider_id = ?");
    $stmt_verify->bind_param("ii", $celula_id, $user_id);
    $stmt_verify->execute();
    if ($stmt_verify->get_result()->num_rows === 0) {
        die("Acesso negado.");
    }
}

// --- Buscar Dados da Célula e do Líder ---
$stmt_celula = $conn->prepare("SELECT c.nome as celula_nome, c.dia_encontro, u.name as lider_nome FROM celulas c JOIN users u ON c.lider_id = u.id WHERE c.id = ?");
$stmt_celula->bind_param("i", $celula_id);
$stmt_celula->execute();
$celula_info = $stmt_celula->get_result()->fetch_assoc();
if (!$celula_info) {
    die("Célula não encontrada.");
}

// --- Buscar todos os registos do mês ---
$stmt_records = $conn->prepare("SELECT * FROM registo_atividades WHERE celula_id = ? AND DATE_FORMAT(data_registo, '%Y-%m') = ? ORDER BY data_registo ASC");
$stmt_records->bind_param("is", $celula_id, $mes); // CORRIGIDO: Adicionado $celula_id
$stmt_records->execute();
$records_result = $stmt_records->get_result();
$records = [];
while($row = $records_result->fetch_assoc()){
    $records[] = $row;
}

if (empty($records)) {
    die("Nenhum registo encontrado para este mês para gerar o relatório.");
}

// --- Buscar todos os membros da célula ---
$membros_celula = [];
$stmt_membros = $conn->prepare("SELECT id, name FROM users WHERE celula_id = ? ORDER BY name ASC");
$stmt_membros->bind_param("i", $celula_id);
$stmt_membros->execute();
$result_membros = $stmt_membros->get_result();
while ($row = $result_membros->fetch_assoc()) {
    $membros_celula[$row['id']] = $row['name'];
}

$conn->close();

// --- Processamento e Agregação de Dados ---
$participation_map = [];
$all_visitors = [];
$all_candidates = [];
$all_events = [
    'ceia' => [], 'oracao' => [], 'confraternizacao' => [], 'servindo' => []
];
$dates_with_activity = [];

foreach ($records as $record) {
    $record_date = $record['data_registo'];
    $dates_with_activity[] = $record_date;

    $participacoes = json_decode($record['participacoes_json'], true) ?: [];
    foreach($participacoes as $p) {
        $member_id = $p['member_id'];
        if (!isset($participation_map[$member_id])) {
            $participation_map[$member_id] = [];
        }
        $participation_map[$member_id][$record_date] = $p;
    }

    $all_visitors = array_merge($all_visitors, json_decode($record['visitantes_json'], true) ?: []);
    $all_candidates = array_merge($all_candidates, json_decode($record['candidatos_json'], true) ?: []);
    
    $event_data = json_decode($record['eventos_json'], true) ?: [];
    foreach($event_data as $type => $date) {
        if (!empty($date) && isset($all_events[$type])) {
            $all_events[$type][] = $date;
        }
    }
}
$dates_with_activity = array_unique($dates_with_activity);
sort($dates_with_activity);

// --- Geração do HTML para o PDF ---
$html = "
<html>
<head>
<style>
    body { font-family: 'dejavusans', sans-serif; font-size: 9px; }
    .header { text-align: center; margin-bottom: 10px; }
    .header h2 { margin: 0; font-size: 14px; }
    .info-bar { width: 100%; margin-bottom: 15px; font-size: 10px; }
    .main-table { width: 100%; border-collapse: collapse; border: 1px solid black; }
    .main-table th, .main-table td { border: 1px solid black; padding: 4px; text-align: center; height: 20px; }
    .main-table th { font-weight: bold; background-color: #f2f2f2; }
    .main-table .member-name { text-align: left; padding-left: 5px;}
    .sub-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .sub-table th, .sub-table td { border: 1px solid black; padding: 4px; text-align: center; height: 20px; }
    .sub-table th { font-weight: bold; background-color: #f2f2f2; }
    .section-title { font-weight: bold; text-align: center; margin-top: 15px; }
</style>
</head>
<body>
    <div class='header'>
        <h2>Igreja Comunidade de Vida Cristã de Moçambique</h2>
        <p>Registo de Participação Células, Cultos e Outros de: ".htmlspecialchars($celula_info['celula_nome'])."</p>
    </div>
    <table class='info-bar'>
        <tr>
            <td><strong>Líder:</strong> ".htmlspecialchars($celula_info['lider_nome'])."</td>
            <td style='text-align:right;'><strong>Mês:</strong> ".date('F, Y', strtotime($mes))."</td>
        </tr>
    </table>

    <table class='main-table'>
        <thead>
            <tr>
                <th rowspan='2' style='width: 5%;'>Guiões Nº</th>
                <th rowspan='2' style='width: 30%;'>Nome Completo</th>
                <th colspan='2'>CÉLULA</th>
                <th colspan='3'>OUTRAS PARTICIPAÇÕES</th>
            </tr>
            <tr>
                <th>Presença</th>
                <th>Razões dos Ausentes</th>
                <th>CULTO</th>
                <th>Discipulado</th>
                <th>Pastoral</th>
            </tr>
        </thead>
        <tbody>";

$member_count = 0;
foreach ($membros_celula as $id => $nome) {
    $member_count++;
    if ($member_count > 12) break;
    
    $presenca_datas = [];
    $culto_datas = [];
    $discipulado_datas = [];
    $pastoral_datas = [];
    $razoes = [];

    foreach($dates_with_activity as $date) {
        if (isset($participation_map[$id][$date])) {
            $p_data = $participation_map[$id][$date];
            $record_type = array_values(array_filter($records, fn($r) => $r['data_registo'] == $date))[0]['tipo_registo'] ?? 'celula';

            if (isset($p_data['presente']) && $p_data['presente']) {
                if ($record_type == 'celula') {
                    $presenca_datas[] = date('d', strtotime($date));
                } else { // culto
                    $culto_datas[] = date('d', strtotime($date));
                }
            }
            if (isset($p_data['ausente']) && $p_data['ausente'] && !empty($p_data['motivo'])) {
                $razoes[] = htmlspecialchars($p_data['motivo']) . ' (' . date('d', strtotime($date)) . ')';
            }
            if (isset($p_data['discipulado']) && $p_data['discipulado']) {
                $discipulado_datas[] = date('d', strtotime($date));
            }
            if (isset($p_data['pastoral']) && $p_data['pastoral']) {
                $pastoral_datas[] = date('d', strtotime($date));
            }
        }
    }

    $html .= "<tr>
        <td>{$member_count}</td>
        <td class='member-name'>".htmlspecialchars($nome)."</td>
        <td>".implode(', ', $presenca_datas)."</td>
        <td>".implode('; ', array_unique($razoes))."</td>
        <td>".implode(', ', $culto_datas)."</td>
        <td>".implode(', ', $discipulado_datas)."</td>
        <td>".implode(', ', $pastoral_datas)."</td>
    </tr>";
}
for ($i = $member_count + 1; $i <= 12; $i++) {
    $html .= "<tr><td>{$i}</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
}

$html .= "
            <tr>
                <td colspan='2' class='member-name'><strong>Datas</strong></td>
                <td colspan='5'>".implode(', ', array_map(fn($d) => date('d', strtotime($d)), $dates_with_activity))."</td>
            </tr>
        </tbody>
    </table>";

// Tabelas inferiores (agregado)
$html .= "<table style='width:100%; border:none; margin-top:10px;'><tr><td style='width:48%; vertical-align:top;'>";

$html .= "
<p class='section-title'>Visitantes</p>
<table class='sub-table'><thead><tr><th>Nome</th><th>Endereço</th><th>Outros dados</th></tr></thead><tbody>";
$visitor_rows = max(3, count($all_visitors));
for ($i = 0; $i < $visitor_rows; $i++) {
    $v = $all_visitors[$i] ?? null;
    $html .= "<tr><td>".($v ? htmlspecialchars($v['nome']) : '&nbsp;')."</td><td>".($v ? htmlspecialchars($v['contacto']) : '&nbsp;')."</td><td>".($v ? htmlspecialchars($v['outros']) : '&nbsp;')."</td></tr>";
}
$html .= "</tbody></table>";

$html .= "</td><td style='width:4%;'></td><td style='width:48%; vertical-align:top;'>";

$html .= "
<p class='section-title'>Candidatos a Batismo e Salvação</p>
<table class='sub-table'><thead><tr><th rowspan='2'>Nome</th><th rowspan='2'>Nova Salvação</th><th colspan='2'>Candidatos ao Batismo</th></tr><tr><th>De Água</th><th>Do Espírito</th></tr></thead><tbody>";
$candidate_rows = max(3, count($all_candidates));
for ($i = 0; $i < $candidate_rows; $i++) {
    $c = $all_candidates[$i] ?? null;
    $nome_candidato = ($c && isset($membros_celula[$c['member_id']])) ? $membros_celula[$c['member_id']] : '&nbsp;';
    $html .= "<tr><td>".$nome_candidato."</td><td>".($c && $c['salvacao'] ? 'X' : '&nbsp;')."</td><td>".($c && $c['batismo_agua'] ? 'X' : '&nbsp;')."</td><td>".($c && $c['batismo_espirito'] ? 'X' : '&nbsp;')."</td></tr>";
}
$html .= "</tbody></table>";

$html .= "</td></tr></table>";

$html .= "<table style='width:100%; border:none; margin-top:10px;'><tr><td style='width:48%; vertical-align:top;'>";

$html .= "
<p class='section-title'>Tornar-se visitante a participante</p>
<table class='sub-table'><thead><tr><th>Nome</th><th>Endereço</th><th>Outros dados</th></tr></thead><tbody><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table>";

$html .= "</td><td style='width:4%;'></td><td style='width:48%; vertical-align:top;'>";

// Formata as datas dos eventos para exibição
$ceia_datas_str = isset($all_events['ceia']) ? implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_unique($all_events['ceia']))) : '&nbsp;';
$oracao_datas_str = isset($all_events['oracao']) ? implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_unique($all_events['oracao']))) : '&nbsp;';
$confraternizacao_datas_str = isset($all_events['confraternizacao']) ? implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_unique($all_events['confraternizacao']))) : '&nbsp;';
$servindo_datas_str = isset($all_events['servindo']) ? implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_unique($all_events['servindo']))) : '&nbsp;';

$html .= "
<p class='section-title'>Data de Eventos da Célula</p>
<table class='sub-table'><thead><tr><th>Ceia</th><th>Oração de intersecção</th><th>Confraternização</th><th>Servindo a comunidade</th></tr></thead><tbody><tr>
<td>".$ceia_datas_str."</td>
<td>".$oracao_datas_str."</td>
<td>".$confraternizacao_datas_str."</td>
<td>".$servindo_datas_str."</td>
</tr></tbody></table>";

$html .= "</td></tr></table></body></html>";

// --- Instanciação e Geração do PDF ---
try {
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => __DIR__ . '/../vendor/mpdf/mpdf/tmp']);
    $mpdf->WriteHTML($html);
    $filename = "relatorio_mensal_".$mes."_".preg_replace('/[^A-Za-z0-9\-]/', '', $celula_info['celula_nome']).".pdf";
    $mpdf->Output($filename, 'I');
} catch (\Mpdf\MpdfException $e) {
    die("Erro ao gerar o PDF: " . $e->getMessage());
}

exit;
