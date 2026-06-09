<?php
require_once('protect.php');
include('conexao.php');
header('Content-Type: application/json');

verificarCSRF();

$id = (int)($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';

if (!$id || !in_array($acao, ['realizar', 'cancelar', 'apagar', 'reverter', 'confirmar'])) {
    echo json_encode(['success' => false, 'message' => 'Parametros invalidos.']);
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT a.id_agenda, a.data, a.hora_inicio, a.hora_fim, a.status
     FROM agendamentos a
     INNER JOIN agenda ag ON a.id_agenda = ag.id
     WHERE a.id = ? AND ag.id_user = ?"
);
$id_user = (int)$_SESSION['id'];
$stmt->bind_param('ii', $id, $id_user);
$stmt->execute();
$dados = $stmt->get_result()->fetch_assoc();

if (!$dados) {
    echo json_encode(['success' => false, 'message' => 'Agendamento nao encontrado ou acesso negado.']);
    exit;
}

$id_agenda = (int)$dados['id_agenda'];
$data = $dados['data'];
$inicio = $dados['hora_inicio'];
$fim = $dados['hora_fim'];

if ($acao === 'realizar') {
    $stmt = $mysqli->prepare("UPDATE agendamentos SET status = 'realizado' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Marcado como realizado.']);
} elseif ($acao === 'cancelar') {
    $stmt = $mysqli->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Marcado como cancelado.']);
} elseif ($acao === 'reverter') {
    $stmt = $mysqli->prepare("UPDATE agendamentos SET status = 'confirmado' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Revertido para confirmado.']);
} elseif ($acao === 'confirmar') {
    $stmt = $mysqli->prepare("UPDATE agendamentos SET status = 'confirmado' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt = $mysqli->prepare("INSERT INTO excessoes (id_agenda, data, hora_inicio, hora_termino, tipo) VALUES (?, ?, ?, ?, 'reservado')");
    $stmt->bind_param('isss', $id_agenda, $data, $inicio, $fim);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Pagamento confirmado e horario reservado.']);
} elseif ($acao === 'apagar') {
    $stmt = $mysqli->prepare("DELETE FROM agendamentos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt = $mysqli->prepare("DELETE FROM excessoes WHERE id_agenda = ? AND data = ? AND hora_inicio = ? AND hora_termino = ? AND tipo = 'reservado'");
    $stmt->bind_param('isss', $id_agenda, $data, $inicio, $fim);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Agendamento apagado e horario liberado.']);
}
