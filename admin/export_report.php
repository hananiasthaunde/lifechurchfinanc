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
        $amount_formatted = number_format($tithe['amount'], 2, ',', '.');
        $name_formatted = htmlspecialchars($tithe['tither_name']);
        $label = ($index == 0) ? 'Dízimos:' : '';
        $tithesHTML .= "
        <tr>
            <td class='label-col'>{$label}</td>
            <td class='value-col'>{$amount_formatted}<span> Mt</span></td>
            <td class='desc-col'>{$name_formatted}</td>
        </tr>";
    }
}
// Adiciona linhas vazias para preencher o espaço
$empty_lines_count = max(0, 8 - count($tithes));
for ($i = 0; $i < $empty_lines_count; $i++) {
    $label = (count($tithes) == 0 && $i == 0) ? 'Dízimos:' : '';
    $tithesHTML .= "
    <tr>
        <td class='label-col'>{$label}</td>
        <td class='value-col'>&nbsp;</td>
        <td class='desc-col'>&nbsp;</td>
    </tr>";
}

// URL da imagem do cabeçalho
$logoDirectUrl = 'https://i.ibb.co/BHt8Rwzg/Captura-de-ecr-2025-06-20-034608.png'; 
$logoHtml = "<img src='{$logoDirectUrl}' class='logo' />";

// Estrutura principal do HTML que será convertida para PDF
$html = "
<html>
<head>
<style>
    body { font-family: 'Times New Roman', Times, serif; font-size: 13px; color: #000; }
    .logo { max-height: 50px; margin-bottom: 10px; }
    .header-info { text-align: center; margin-bottom: 25px; }
    .header-info h2 { margin: 0; font-size: 16px; font-weight: normal; }
    .header-info p { margin: 2px 0; font-size: 13px; }

    .info-block { text-align: left; margin-bottom: 25px; }
    .info-block p { margin: 10px 0; }
    .info-label { font-weight: normal; }
    .info-value { font-weight: bold; 
                  /* border-bottom: 1px solid #000; */ 
                  padding: 0 10px; 
                }
    
    .section { margin-top: 25px; page-break-inside: avoid; }
    .section-title { font-weight: bold; font-size: 14px; margin-bottom: 12px; }
    
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table td { padding: 4px; vertical-align: bottom; }
    .data-table .label-col { width: 25%; font-weight: bold; text-align: left; }
    .data-table .value-col { width: 15%; text-align: center; border-bottom: 1px solid #000; font-weight: bold; }
    .data-table .header-label { font-weight: bold; text-align: center; }

    .finance-table { width: 100%; margin-top: 8px; border-collapse: collapse; }
    .finance-table td { padding: 6px 4px; vertical-align: bottom; }
    .finance-table .label-col { width: 18%; font-weight: bold; text-align: left; }
    .finance-table .value-col { width: 22%; text-align: left; border-bottom: 1px solid #000; font-weight: bold; }
    .finance-table .desc-col { width: 60%; border-bottom: 1px solid #000; }
    .finance-table .total-row .value-col { font-size: 14px; border-bottom: 3px double #000; }
    .finance-table .desc-header { text-align:left; font-weight:bold; padding-left: 5px;}

    .comments-section { margin-top: 25px; }
    .comments-section .line { border-bottom: 1px solid #000; height: 25px; padding: 2px 5px; font-weight: normal; }
</style>
</head>
<body>
    <div class='header-info'>
        {$logoHtml}
        <h2>Comunidade de Vida Cristã - Life Church</h2>
        <p>MOÇAMBIQUE</p>
        <p><strong>Congregação de ".htmlspecialchars($church_name)."</strong></p>
    </div>

    <div class='info-block'>
        <p>
            <span class='info-label'>Celebração do culto referente ao dia</span>
            <span class='info-value'>{$day}</span>
            <span class='info-label'>de</span>
            <span class='info-value' style='padding: 0 40px;'>{$month}</span>
            <span class='info-label'>de 20</span>
            <span class='info-value'>".substr($year, 2)."</span>
        </p>
        <p>
            <span class='info-label'>Tema:</span>
            <span class='info-value' style='padding-right: 400px;'>".htmlspecialchars($report['theme'] ?: '&nbsp;')."</span>
        </p>
    </div>

    <div class='section'>
        <p class='section-title'>Detalhes de Participação</p>
        <table class='data-table'>
            <tr>
                <td class='label-col'>Congregação Geral:</td>
                <td class='value-col'>{$report['total_attendance']}</td>
                <td style='width: 10%;'></td>
                <td class='header-label' style='width: 18%;'>Membros</td>
                <td class='header-label' style='width: 18%;'>Visitantes</td>
                <td class='header-label' style='width: 18%;'>Salvos</td>
            </tr>
            <tr>
                <td class='label-col'>Adultos:</td>
                <td class='value-col'>".($report['adults_members'] + $report['adults_visitors'])."</td>
                <td></td>
                <td class='value-col'>{$report['adults_members']}</td>
                <td class='value-col'>{$report['adults_visitors']}</td>
                <td class='value-col'>{$report['adult_saved']}</td>
            </tr>
            <tr>
                <td class='label-col'>Crianças:</td>
                <td class='value-col'>".($report['children_members'] + $report['children_visitors'])."</td>
                <td></td>
                <td class='value-col'>{$report['children_members']}</td>
                <td class='value-col'>{$report['children_visitors']}</td>
                <td class='value-col'>{$report['child_saved']}</td>
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
        <p class='section-title'>Comentários:</p>
        <div class='line'>".(nl2br(htmlspecialchars($report['comments'] ?: '')))."</div>
        <div class='line'></div>
        <div class='line'></div>
    </div>

</body>
</html>
";

// 5. Instanciar o mPDF e gerar o PDF
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4',
        'margin_left' => 20,
        'margin_right' => 20,
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
