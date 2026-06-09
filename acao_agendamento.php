<?php
require_once('conexao.php');
session_start();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';

if (!$id || !in_array($acao, ['realizar', 'cancelar', 'apagar', 'reverter', 'confirmar'])) {
    echo json_encode(['success' => false, 'message' => 'Parametros invalidos.']);
    exit;
}

$ag = $mysqli->query("SELECT id_agenda, data, hora_inicio, hora_fim, status FROM agendamentos WHERE id = '$id'");
$dados = $ag->fetch_assoc();
if (!$dados) {
    echo json_encode(['success' => false, 'message' => 'Agendamento nao encontrado.']);
    exit;
}

$id_agenda = (int)$dados['id_agenda'];
$data = $mysqli->real_escape_string($dados['data']);
$inicio = $dados['hora_inicio'];
$fim = $dados['hora_fim'];

if ($acao === 'realizar') {
    $mysqli->query("UPDATE agendamentos SET status = 'realizado' WHERE id = '$id'");
    echo json_encode(['success' => true, 'message' => 'Marcado como realizado.']);
} elseif ($acao === 'cancelar') {
    $mysqli->query("UPDATE agendamentos SET status = 'cancelado' WHERE id = '$id'");
    echo json_encode(['success' => true, 'message' => 'Marcado como cancelado.']);
} elseif ($acao === 'reverter') {
    $mysqli->query("UPDATE agendamentos SET status = 'confirmado' WHERE id = '$id'");
    echo json_encode(['success' => true, 'message' => 'Revertido para confirmado.']);
} elseif ($acao === 'confirmar') {
    $mysqli->query("UPDATE agendamentos SET status = 'confirmado' WHERE id = '$id'");
    $mysqli->query("INSERT INTO excessoes (id_agenda, data, hora_inicio, hora_termino, tipo) VALUES ('$id_agenda', '$data', '$inicio', '$fim', 'reservado')");
    echo json_encode(['success' => true, 'message' => 'Pagamento confirmado e horario reservado.']);
} elseif ($acao === 'apagar') {
    $mysqli->query("DELETE FROM agendamentos WHERE id = '$id'");
    $mysqli->query("DELETE FROM excessoes WHERE id_agenda = '$id_agenda' AND data = '$data' AND hora_inicio = '$inicio' AND hora_termino = '$fim' AND tipo = 'reservado'");
    echo json_encode(['success' => true, 'message' => 'Agendamento apagado e horario liberado.']);
}
