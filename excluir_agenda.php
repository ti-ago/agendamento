<?php
require_once('protect.php');
include('conexao.php');
header('Content-Type: application/json');

verificarCSRF();

$id = (int)($_POST['id'] ?? 0);
$id_usuario = (int)$_SESSION['id'];

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Parametros invalidos.']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id FROM agenda WHERE id = ? AND id_user = ?");
$stmt->bind_param('ii', $id, $id_usuario);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Agenda nao encontrada ou sem permissao.']);
    exit;
}

$mysqli->query("DELETE FROM excessoes WHERE id_agenda = '$id'");
$mysqli->query("DELETE FROM rotinas WHERE id_agenda = '$id'");
$mysqli->query("DELETE FROM agendamentos WHERE id_agenda = '$id'");
$mysqli->query("DELETE FROM agenda WHERE id = '$id'");

echo json_encode(['success' => true, 'message' => 'Agenda excluida com sucesso.']);
