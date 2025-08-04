<?php

/**
 * Configurações do Banco de Dados e da Aplicação
 *
 * Este arquivo contém as configurações para a conexão com o banco de dados e
 * a URL base da aplicação, essencial para o funcionamento correto dos links e redirecionamentos.
 */

// PASSO 1: DEFINA A URL BASE DA SUA APLICAÇÃO LOCAL
// Substitua pelo URL que você usa no seu navegador para aceder ao projeto.
// Certifique-se de que NÃO termina com uma barra (/).
// Exemplo: http://localhost/lifechurch  ou  http://localhost:8080/lifechurch
define('BASE_URL', 'http://localhost/lifechurchfinanc');



// PASSO 2: DEFINA O CAMINHO RAIZ DO PROJETO NO SERVIDOR
// Esta linha geralmente não precisa de ser alterada.
define('ROOT_PATH', realpath(__DIR__ . '/../'));


// --- Configurações do Banco de Dados para Ambiente Local ---

// Servidor do banco de dados (geralmente 'localhost' ou '127.0.0.1')
define('DB_SERVER', 'localhost');

// Nome de usuário do banco de dados ('root' é o padrão no XAMPP/WAMP)
define('DB_USERNAME', 'root');

// Senha do banco de dados (padrão é '' no XAMPP e 'root' no MAMP)
define('DB_PASSWORD', '');

// Nome do banco de dados que você criou
define('DB_NAME', 'lifechurchfinanc_lifechurch_db');


/**
 * Função para conectar ao banco de dados.
 * Não é necessário alterar esta função.
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
