<?php

/**
 * Configurações do Banco de Dados e da Aplicação para o Servidor Online
 */

// PASSO 1: URL BASE DA APLICAÇÃO NO SERVIDOR
// Este é o endereço do seu site.
define('BASE_URL', 'https://lifechurchfinance.aplicweb.com');

// PASSO 2: CAMINHO RAIZ DO PROJETO NO SERVIDOR
// Esta linha geralmente não precisa de ser alterada.
define('ROOT_PATH', realpath(__DIR__ . '/../'));


// --- Configurações do Banco de Dados para o Servidor (cPanel) ---

// Servidor do banco de dados (geralmente 'localhost')
define('DB_SERVER', 'localhost');

// Nome de usuário do banco de dados do cPanel (CORRIGIDO)
define('DB_USERNAME', 'lifechurchfinanc_lifechurchfinan');

// Senha do banco de dados do cPanel
define('DB_PASSWORD', 'm6aqpIg9R0Zkpx4%');

// Nome do banco de dados do cPanel (CORRIGIDO)
define('DB_NAME', 'lifechurchfinanc_lifechurch_db');


/**
 * Função para conectar ao banco de dados.
 */
if (!function_exists('connect_db')) {
    function connect_db() {
        // Cria uma nova conexão usando as constantes definidas acima.
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Verifica se ocorreu algum erro na conexão.
        if ($conn->connect_error) {
            // Em caso de erro, exibe uma mensagem clara e termina a execução do script.
            die("Falha na conexão com o banco de dados: " . $conn->connect_error);
        }

        // Define o conjunto de caracteres para UTF-8.
        $conn->set_charset("utf8");

        // Retorna o objeto de conexão.
        return $conn;
    }
}

?>
