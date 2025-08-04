<?php
// Ativa a exibição de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

$user_id = $_SESSION['user_id'];
$church_id = $_SESSION['church_id'];

if (!isset($_GET['id'])) {
    die("ID da despesa não fornecido.");
}

$expense_id = (int)$_GET['id'];
$expense = null;
$items = [];

try {
    $conn = connect_db();

    $stmt = $conn->prepare("SELECT e.*, c.name as category_name, ch.name as church_name FROM expenses e JOIN categories c ON e.category_id = c.id JOIN churches ch ON e.church_id = ch.id WHERE e.id = ? AND e.church_id = ?");
    if (!$stmt) throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    
    $stmt->bind_param("ii", $expense_id, $church_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense = $result->fetch_assoc();
    $stmt->close();
    
    if (!$expense) {
        throw new Exception("Saída não encontrada ou não pertence a esta congregação.");
    }

    $items = json_decode($expense['description'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $items = [['description' => $expense['description'], 'quantity' => 1, 'unit_price' => $expense['amount'], 'total' => $expense['amount']]];
    }

    $conn->close();

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// Usar a URL absoluta para a imagem, definida no config.php
$logoUrl = BASE_URL . '/assets/images/Church-1.png';

// Criar instância do mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);

// Conteúdo HTML para o PDF
$html = '
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 12px; }
    .header { text-align: center; margin-bottom: 20px; }
    .header h2 { margin: 0; font-size: 18px; }
    .header p { margin: 0; font-size: 12px; }
    .title { background-color: #eee; padding: 5px; text-align: center; font-weight: bold; margin-bottom: 15px; }
    .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .details-table td { padding: 8px 0; }
    .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .items-table th { background-color: #f2f2f2; }
    .total-row td { font-weight: bold; }
    .footer { margin-top: 40px; }
    .signatures { margin-top: 80px; width: 100%; }
    .signatures td { width: 50%; text-align: center; font-size: 12px; }
    .line { border-top: 1px solid #000; width: 200px; margin: 0 auto; }
</style>
</head>
<body>
    <div class="header">
        <img src="' . $logoUrl . '" alt="Logo" style="width: 80px; height: auto; margin-bottom: 10px;">
        <h2>Igreja Comunidade de Vida Cristã</h2>
        <p>'.htmlspecialchars($expense['church_name'] ?? '').'</p>
    </div>

    <div class="title">RECIBO DE PAGAMENTO</div>

    <table class="details-table">
        <tr>
            <td width="20%"><strong>Compra feita no(a):</strong></td>
            <td>'.htmlspecialchars($expense['paid_to'] ?? '').'</td>
        </tr>
        <tr>
            <td><strong>Irmão responsável:</strong></td>
            <td>'.htmlspecialchars($expense['received_by'] ?? '').'</td>
        </tr>
        <tr>
            <td><strong>Data:</strong></td>
            <td>'.date('d/m/Y', strtotime($expense['transaction_date'])).'</td>
        </tr>
    </table>

    <div class="title">DETALHES DO PAGAMENTO</div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Produto / Descrição</th>
                <th width="15%" style="text-align:center;">Preço Unitário</th>
                <th width="10%" style="text-align:center;">Qtd</th>
                <th width="15%" style="text-align:right;">Total (MT)</th>
            </tr>
        </thead>
        <tbody>';
            if (is_array($items)) {
                foreach ($items as $item) {
                    $html .= '<tr>
                        <td>'.htmlspecialchars($item['description'] ?? '').'</td>
                        <td style="text-align:center;">'.number_format($item['unit_price'] ?? 0, 2, ',', '.').'</td>
                        <td style="text-align:center;">'.($item['quantity'] ?? 1).'</td>
                        <td style="text-align:right;">'.number_format($item['total'] ?? 0, 2, ',', '.').'</td>
                    </tr>';
                }
            }
        $html .= '
            <tr class="total-row">
                <td colspan="3" style="text-align:right;"><strong>Total Pago:</strong></td>
                <td style="text-align:right;"><strong>'.number_format($expense['amount'] ?? 0, 2, ',', '.').' MZN</strong></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <strong>Comentários:</strong>
        <p>'.nl2br(htmlspecialchars($expense['comments'] ?? 'Nenhum comentário.')).'</p>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="line"></div>
                <p>Assinatura do Tesoureiro</p>
            </td>
            <td>
                <div class="line"></div>
                <p>'.htmlspecialchars($expense['received_by'] ?? '').'</p>
            </td>
        </tr>
    </table>

</body>
</html>
';

$mpdf->WriteHTML($html);
$mpdf->Output('recibo_saida_'.$expense_id.'.pdf', 'I');
exit;
