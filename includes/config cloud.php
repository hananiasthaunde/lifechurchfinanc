<?php

// PASSO 3 DO GUIA: DEFINA A URL BASE DA SUA APLICAÇÃO AQUI
// Isto garante que todos os links e redirecionamentos funcionem corretamente no servidor.
define('BASE_URL', 'https://lifechurchfinance.aplicweb.com');

define('ROOT_PATH', realpath(__DIR__ . '/../'));

/**
 * Configurações do Banco de Dados para o Servidor de Produção (cPanel)
 *
 * PASSO 2 DO GUIA: Substitua os valores abaixo pelas credenciais que você
 * criou no seu cPanel.
 */

// Servidor do banco de dados (geralmente 'localhost')
define('DB_SERVER', 'localhost');

// Nome de utilizador do banco de dados (ex: 'aplicweb_lifechurch_user')
define('DB_USERNAME', 'lifechurchfinanc_lf_db');

// Senha do banco de dados
define('DB_PASSWORD', 'm6aqpIg9R0Zkpx4%');

// Nome do banco de dados (ex: 'aplicweb_lifechurch_db')
define('DB_NAME', 'lifechurchfinanc_lifechurch_db1');


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
            // Em produção, seria melhor registrar este erro num log em vez de usar 'die()'.
            die("Falha na conexão com o banco de dados: " . $conn->connect_error);
        }

        // Define o conjunto de caracteres para UTF-8 para suportar acentuação e caracteres especiais.
        $conn->set_charset("utf8");

        // Retorna o objeto de conexão para ser usado em outras partes do código.
        return $conn;
    }
}

?>
