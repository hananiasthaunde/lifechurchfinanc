<?php
session_start();

// 1. Segurança e Configuração
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 2. Obter ID do Relatório da URL
$report_id = $_GET['id'] ?? null;
if (!$report_id) {
    die('ID do relatório não fornecido.');
}
$report_id = (int)$report_id;
$church_id = $_SESSION['church_id'];

// 3. Buscar os dados do banco de dados
$conn = connect_db();
$report = null;
$tithes = [];
$church_name = '';

// Buscar detalhes do relatório
$stmt_report = $conn->prepare("SELECT * FROM service_reports WHERE id = ? AND church_id = ?");
$stmt_report->bind_param("ii", $report_id, $church_id);
$stmt_report->execute();
$result_report = $stmt_report->get_result();
if ($result_report->num_rows > 0) {
    $report = $result_report->fetch_assoc();
} else {
    $conn->close();
    die('Relatório não encontrado ou acesso não permitido.');
}

// Buscar dízimos associados
$stmt_tithes = $conn->prepare("SELECT tither_name, amount FROM tithes WHERE report_id = ?");
$stmt_tithes->bind_param("i", $report_id);
$stmt_tithes->execute();
$tithes_result = $stmt_tithes->get_result();
while ($tithe_row = $tithes_result->fetch_assoc()) {
    $tithes[] = $tithe_row;
}

// Buscar nome da igreja
$stmt_church = $conn->prepare("SELECT name FROM churches WHERE id = ?");
$stmt_church->bind_param("i", $church_id);
$stmt_church->execute();
$church_res = $stmt_church->get_result()->fetch_assoc();
$church_name = $church_res['name'] ?? 'Congregação';
$conn->close();

// 4. Gerar o conteúdo HTML para o PDF
$serviceDate = new DateTime($report['service_date']);
$day = $serviceDate->format('d');
$monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
$month = $monthNames[(int)$serviceDate->format('m') - 1];
$year = $serviceDate->format('Y');

// Monta as linhas da tabela de dízimos
$tithesHTML = '';
if (count($tithes) > 0) {
    foreach ($tithes as $index => $tithe) {
        $amount_formatted = number_format($tithe['amount'], 2, ',', '.') . ' Mt';
        $name_formatted = htmlspecialchars($tithe['tither_name']);
        // O rótulo "Dízimos:" só aparece na primeira linha
        $label = ($index == 0) ? 'Dízimos:' : '';
        $tithesHTML .= "
        <tr>
            <td class='label-col'>{$label}</td>
            <td class='value-col'>{$amount_formatted}</td>
            <td class='desc-col'>{$name_formatted}</td>
        </tr>";
    }
}
// Adiciona linhas vazias para se assemelhar ao formulário, caso necessário
$empty_lines_count = max(0, 8 - count($tithes)); // Limita o número de linhas para caber na página
for ($i = 0; $i < $empty_lines_count; $i++) {
    $label = (count($tithes) == 0 && $i == 0) ? 'Dízimos:' : ''; // Adiciona rótulo se não houver dízimos
    $tithesHTML .= "
    <tr>
        <td class='label-col'>{$label}</td>
        <td class='value-col'>&nbsp;</td>
        <td class='desc-col'>&nbsp;</td>
    </tr>";
}


// INSTRUÇÃO: Coloque o caminho para a sua imagem de logo aqui.
$logoPath = ''; // Ex: 'C:/xampp/htdocs/lifechurch/assets/logo.png'
$logoHtml = '';
if (!empty($logoPath) && file_exists($logoPath)) {
    $logoHtml = "<img src='{$logoPath}' class='logo' />";
}

// Estrutura principal do HTML que será convertida para PDF
$html = "
<html>
<head>
<style>
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
    .header { text-align: center; margin-bottom: 20px; }
    .logo { max-height: 70px; margin-bottom: 10px; }
    .header h2 { margin: 0; font-size: 16px; font-weight: bold; }
    .header p { margin: 2px 0; font-size: 13px; }
    .info-line { border-bottom: 1px solid #555; display: inline-block; padding: 0 5px; font-weight: bold; }
    .section { margin-top: 15px; }
    .section-title { font-weight: bold; font-size: 14px; margin-bottom: 8px; background-color: #EAEAEA; padding: 6px; border-radius: 4px; }
    
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table td { padding: 5px; }
    .data-table .label { font-weight: bold; }
    .data-table .value { text-align: center; border-bottom: 1px solid #555; }

    .finance-table { width: 100%; margin-top: 5px; border-collapse: collapse; }
    .finance-table td { padding: 5px 4px; vertical-align: bottom; }
    .finance-table .label-col { width: 20%; font-weight: bold; }
    .finance-table .value-col { width: 25%; font-weight: bold; border-bottom: 1px solid #555; }
    .finance-table .desc-col { width: 55%; border-bottom: 1px solid #555; }
    .finance-table .total-row .value-col { font-size: 14px; border-bottom: 2px double #333; }
    .finance-table .desc-header { text-align:center; font-weight:bold; border-bottom: none; }

    .comments-section { margin-top: 15px; }
    .comments-section .line { border-bottom: 1px solid #555; height: 22px; padding-left: 5px; }
    .footer { text-align: center; font-style: italic; color: #555; font-size: 11px; margin-top: 25px; border-top: 1px solid #ccc; padding-top: 8px; }

</style>
</head>
<body>
    <div class='header'>
        {$logoHtml}
        <h2>Comunidade de Vida Cristã - Life Church</h2>
        <p>MOÇAMBIQUE</p>
        <p><strong>Congregação de <span class='info-line'>".htmlspecialchars($church_name)."</span></strong></p>
    </div>

    <div class='section'>
        <p>
            Celebração do culto referente ao dia <span class='info-line'>{$day}</span>
            de <span class='info-line'>{$month}</span>
            de <span class='info-line'>{$year}</span>
        </p>
        <p style='margin-top: 8px;'>
            Tema: <span class='info-line' style='padding-right: 200px;'>".htmlspecialchars($report['theme'] ?: '&nbsp;')."</span>
        </p>
    </div>

    <div class='section'>
        <p class='section-title'>Detalhes de Participação</p>
        <table class='data-table'>
            <tr>
                <td style='width: 35%;'><span class='label'>Congregação Geral:</span></td>
                <td class='value' style='width: 15%;'>{$report['total_attendance']}</td>
                <td style='width: 5%;'></td>
                <td class='label' style='width: 15%; text-align:center;'>Membros</td>
                <td class='label' style='width: 15%; text-align:center;'>Visitantes</td>
                <td class='label' style='width: 15%; text-align:center;'>Salvos</td>
            </tr>
            <tr>
                <td><span class='label'>Adultos:</span></td>
                <td class='value'>".($report['adults_members'] + $report['adults_visitors'])."</td>
                <td></td>
                <td class='value'>{$report['adults_members']}</td>
                <td class='value'>{$report['adults_visitors']}</td>
                <td class='value'>{$report['adult_saved']}</td>
            </tr>
            <tr>
                <td><span class='label'>Crianças:</span></td>
                <td class='value'>".($report['children_members'] + $report['children_visitors'])."</td>
                <td></td>
                <td class='value'>{$report['children_members']}</td>
                <td class='value'>{$report['children_visitors']}</td>
                <td class='value'>{$report['child_saved']}</td>
            </tr>
        </table>
    </div>

    <div class='section'>
        <p class='section-title'>Ofertas e Dízimos</p>
        <table class='finance-table'>
             <tr>
                <td></td>
                <td></td>
                <td class='desc-header'>Descrições</td>
            </tr>
            <tr>
                <td class='label-col'>Ofertório:</td>
                <td class='value-col'>".number_format($report['offering'], 2, ',', '.')." Mt</td>
                <td class='desc-col'>&nbsp;</td>
            </tr>
            <!-- Linhas de Dízimos são inseridas aqui -->
            {$tithesHTML}
             <tr>
                <td class='label-col'>Of. Especial:</td>
                <td class='value-col'>".($report['special_offering'] > 0 ? number_format($report['special_offering'], 2, ',', '.') . ' Mt' : '&nbsp;')."</td>
                <td class='desc-col'>&nbsp;</td>
            </tr>
             <tr class='total-row'>
                <td class='label-col' style='padding-top: 10px;'>Total:</td>
                <td class='value-col' style='padding-top: 10px;'>".number_format($report['total_offering'], 2, ',', '.')." Mt</td>
                <td class='desc-col' style='border:none;'></td>
            </tr>
        </table>
    </div>

    <div class='section comments-section'>
        <p class='section-title'>Comentários</p>
        <div class='line'>".(nl2br(htmlspecialchars($report['comments'] ?: '')))."</div>
        <div class='line'></div>
        <div class='line'></div>
    </div>

    <div class='footer'>
        Documento processado por Life Church Finanças
    </div>
</body>
</html>
";

// 5. Instanciar o mPDF e gerar o PDF
try {
    // Configura margens para ajudar a manter em uma página
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    
    $mpdf->SetTitle('Relatório do Culto - ' . $serviceDate->format('d-m-Y'));
    $mpdf->SetAuthor('Sistema Life Church');
    $mpdf->WriteHTML($html);
    
    $filename = 'relatorio_culto_' . $report['service_date'] . '.pdf';
    $mpdf->Output($filename, 'I');

} catch (\Mpdf\MpdfException $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}

exit;
