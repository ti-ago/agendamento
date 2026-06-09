<?php
    require_once('protect.php');
    include('conexao.php');
    header('Content-Type: application/json');

    verificarCSRF();

    $id = (int)($_POST['id'] ?? 0);

    if (!usuarioDono($mysqli, 'excessoes', $id)) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM excessoes WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
