<?php
session_start();

// Inclui o ficheiro de configuração para aceder à BASE_URL
require_once __DIR__ . '/../includes/config.php';

// Destrói todos os dados da sessão
session_destroy();

// Redireciona para a página de login usando a BASE_URL
header('Location: ' . BASE_URL . '/public/login.php');
exit;
?>
