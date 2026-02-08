<?php
/**
 * Migração para criar tabelas do módulo de Budget
 * Execute este ficheiro uma vez para criar as tabelas necessárias
 */

session_start();

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'master_admin') {
    die('<h1>Acesso Negado</h1><p>Apenas administradores podem executar migrações.</p>');
}

require_once __DIR__ . '/../includes/config.php';
$conn = connect_db();

$messages = [];

// 1. Criar tabela 'budgets'
$sql_budgets = "
CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    church_id INT NOT NULL,
    year INT NOT NULL,
    status ENUM('draft', 'active', 'closed') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_church_year (church_id, year),
    FOREIGN KEY (church_id) REFERENCES churches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_budgets)) {
    $messages[] = "✅ Tabela 'budgets' criada/verificada com sucesso!";
} else {
    $messages[] = "❌ Erro ao criar tabela 'budgets': " . $conn->error;
}

// 2. Criar tabela 'budget_items'
$sql_budget_items = "
CREATE TABLE IF NOT EXISTS budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    category_id INT NOT NULL,
    monthly_projection DECIMAL(15,2) DEFAULT 0.00,
    notes VARCHAR(255) DEFAULT NULL,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_budget_category (budget_id, category_id),
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_budget_items)) {
    $messages[] = "✅ Tabela 'budget_items' criada/verificada com sucesso!";
} else {
    $messages[] = "❌ Erro ao criar tabela 'budget_items': " . $conn->error;
}

// 3. Criar tabela 'budget_logs'
$sql_budget_logs = "
CREATE TABLE IF NOT EXISTS budget_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql_budget_logs)) {
    $messages[] = "✅ Tabela 'budget_logs' criada/verificada com sucesso!";
} else {
    $messages[] = "❌ Erro ao criar tabela 'budget_logs': " . $conn->error;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração de Base de Dados - Budget</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-lg w-full">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="text-3xl">🗄️</span> Migração de Base de Dados
        </h1>
        
        <div class="space-y-3 mb-6">
            <?php foreach($messages as $msg): ?>
                <div class="p-3 rounded-lg <?php echo strpos($msg, '✅') !== false ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                    <?php echo $msg; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="border-t pt-4">
            <p class="text-gray-500 text-sm mb-4">Migração concluída. Você pode agora aceder à página de Budget.</p>
            <a href="budget.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                Ir para Budget →
            </a>
        </div>
    </div>
</body>
</html>
