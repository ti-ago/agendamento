<?php
require_once('conexao.php');
require_once('includes/security.php');
exigirLogin();

header('Content-Type: application/json');

$agenda_id = isset($_GET['agenda_id']) ? (int)$_GET['agenda_id'] : 0;
$ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;

if (!$agenda_id) {
    echo json_encode(['success' => false, 'error' => 'Agenda nao informada.']);
    exit;
}

if (!usuarioDono($agenda_id, $mysqli)) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id, cliente_nome, data, hora_inicio, token, status FROM agendamentos WHERE id_agenda = ? AND id > ? ORDER BY id ASC");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Erro interno.']);
    exit;
}
$stmt->bind_param("ii", $agenda_id, $ultimo_id);
$stmt->execute();
$result = $stmt->get_result();

$novos = [];
while ($row = $result->fetch_assoc()) {
    $novos[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'novos' => $novos,
    'total' => count($novos)
]);
