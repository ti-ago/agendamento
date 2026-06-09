<?php
    require_once('protect.php');
    include('conexao.php');
    header('Content-Type: application/json');

    verificarCSRF();

    $id = (int)($_POST['id'] ?? 0);

    if (!usuarioDono($mysqli, 'rotinas', $id)) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM rotinas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
