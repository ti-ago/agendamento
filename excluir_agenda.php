<?php
require_once('conexao.php');
session_start();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$usuario = $_SESSION['nome'] ?? '';

if (!$id || !$usuario) {
    echo json_encode(['success' => false, 'message' => 'Parametros invalidos.']);
    exit;
}

$ag = $mysqli->query("SELECT id FROM agenda WHERE id = '$id' AND id_user = (SELECT id FROM users WHERE nome = '$usuario')");
if (!$ag || $ag->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Agenda nao encontrada ou sem permissao.']);
    exit;
}

$mysqli->query("DELETE FROM excessoes WHERE id_agenda = '$id'");
$mysqli->query("DELETE FROM rotinas WHERE id_agenda = '$id'");
$mysqli->query("DELETE FROM agendamentos WHERE id_agenda = '$id'");
$mysqli->query("DELETE FROM agenda WHERE id = '$id'");

echo json_encode(['success' => true, 'message' => 'Agenda excluida com sucesso.']);
