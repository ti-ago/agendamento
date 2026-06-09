<?php
function configurarSessao() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function exigirLogin() {
    configurarSessao();
    if (!isset($_SESSION['id'])) {
        die('<h2>Acesso negado.</h2><p><a href="index.php">Faca login</a></p>');
    }
}

function usuarioDono($mysqli, $tabela, $id_recurso, $coluna_id_recurso = 'id') {
    $id_recurso = (int)$id_recurso;
    $id_usuario = (int)$_SESSION['id'];
    if (!$id_recurso || !$id_usuario) return false;
    $tabela = preg_replace('/[^a-z_]/', '', $tabela);
    $coluna_id_recurso = preg_replace('/[^a-z_]/', '', $coluna_id_recurso);
    $res = $mysqli->prepare(
        "SELECT 1 FROM $tabela t
         INNER JOIN agenda a ON a.id = t.id_agenda
         WHERE t.$coluna_id_recurso = ? AND a.id_user = ?"
    );
    if (!$res) return false;
    $res->bind_param('ii', $id_recurso, $id_usuario);
    $res->execute();
    return $res->get_result()->num_rows > 0;
}

function gerarTokenCSRF() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function validarTokenCSRF($token) {
    if (empty($_SESSION['_csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function campoCSRF() {
    return '<input type="hidden" name="_csrf_token" value="' . gerarTokenCSRF() . '">';
}

function verificarCSRF() {
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validarTokenCSRF($token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Token CSRF invalido.']));
    }
}
