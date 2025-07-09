<?php
/**
 * Funções Gerais da Aplicação
 *
 * Este arquivo contém funções de ajuda que podem ser usadas em várias partes do site.
 * A função connect_db() foi REMOVIDA deste arquivo para evitar o erro de "rededeclaração",
 * uma vez que ela agora existe exclusivamente no arquivo config.php.
 */

// Função para buscar todos os membros
function get_all_members() {
    // A função connect_db() é chamada a partir de config.php
    $conn = connect_db();
    $members = [];
    $sql = "SELECT id, name, email, phone, address, birthdate, membership_date, status FROM members ORDER BY name ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
    $conn->close();
    return $members;
}

// Função para buscar todas as categorias
function get_all_categories() {
    $conn = connect_db();
    $categories = [];
    $sql = "SELECT id, name FROM categories ORDER BY name ASC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    $conn->close();
    return $categories;
}

// Adicione aqui outras funções gerais que sua aplicação possa precisar.
// Exemplo:
function get_user_info($user_id) {
    $conn = connect_db();
    $user = null;
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }
    $conn->close();
    return $user;
}

?>
