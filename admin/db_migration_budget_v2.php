<?php
// Force localhost detection for CLI
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../includes/config.php';

$conn = connect_db();

// Adicionar coluna 'notes' se não existir
$check_col = $conn->query("SHOW COLUMNS FROM budget_items LIKE 'notes'");
if ($check_col->num_rows == 0) {
    $sql = "ALTER TABLE budget_items ADD COLUMN notes VARCHAR(255) DEFAULT NULL AFTER monthly_projection";
    if ($conn->query($sql)) {
        echo "Coluna 'notes' adicionada com sucesso!<br>";
    } else {
        echo "Erro ao adicionar coluna 'notes': " . $conn->error . "<br>";
    }
} else {
    echo "Coluna 'notes' já existe.<br>";
}

$conn->close();
?>
