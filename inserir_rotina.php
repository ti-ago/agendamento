<?php
    require_once('protect.php');
    include('conexao.php');
    header('Content-Type: application/json');

    verificarCSRF();

    $id_agenda = (int)$_POST['id_agenda'];
    $uid = (int)$_SESSION['id'];
    $check = $mysqli->prepare("SELECT 1 FROM agenda WHERE id = ? AND id_user = ?");
    $check->bind_param('ii', $id_agenda, $uid);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $id_rotina = isset($_POST['id_rotina']) ? (int)$_POST['id_rotina'] : 0;
    $force = isset($_POST['force']) && $_POST['force'] === '1';

    $data_inicio = (new DateTime($_POST['data_inicio']))->format('Y-m-d');
    $data_termino = (new DateTime($_POST['data_final']))->format('Y-m-d');

    if ($data_inicio > $data_termino) {
        echo json_encode(['success' => false, 'message' => 'Data inicial nao pode ser maior que a data final.']);
        exit;
    }

    $hora_inicio = (new DateTime($_POST['hora_inicio']))->format('H:i:s');
    $hora_termino = (new DateTime($_POST['hora_final']))->format('H:i:s');

    if ($hora_inicio >= $hora_termino) {
        echo json_encode(['success' => false, 'message' => 'Horario inicial deve ser anterior ao horario final.']);
        exit;
    }

    $duracao = (int)$_POST['duracao'];
    $cor = $_POST['cor'] ?? '#3465a4';
    $intervalo_sessoes = (int)($_POST['intervalo_sessoes'] ?? 0);

    if ($duracao < 1) {
        echo json_encode(['success' => false, 'message' => 'Duracao deve ser maior que zero.']);
        exit;
    }

    $domingo = (int)($_POST['domingo'] ?? 0);
    $segunda = (int)($_POST['segunda'] ?? 0);
    $terca = (int)($_POST['terca'] ?? 0);
    $quarta = (int)($_POST['quarta'] ?? 0);
    $quinta = (int)($_POST['quinta'] ?? 0);
    $sexta = (int)($_POST['sexta'] ?? 0);
    $sabado = (int)($_POST['sabado'] ?? 0);

    $check_sql = "SELECT id FROM rotinas WHERE id_agenda = ?
                  AND id != ?
                  AND data_inicio <= ? AND data_termino >= ?
                  AND hora_inicio < ? AND hora_termino > ?
                  AND (
                      (domingo = 1 AND ? = 1) OR (segunda = 1 AND ? = 1) OR
                      (terca = 1 AND ? = 1) OR (quarta = 1 AND ? = 1) OR
                      (quinta = 1 AND ? = 1) OR (sexta = 1 AND ? = 1) OR
                      (sabado = 1 AND ? = 1)
                  )";

    $stmt = $mysqli->prepare($check_sql);
    $stmt->bind_param('iissssiiiiiii',
        $id_agenda, $id_rotina, $data_termino, $data_inicio,
        $hora_termino, $hora_inicio,
        $domingo, $segunda, $terca, $quarta, $quinta, $sexta, $sabado
    );
    $stmt->execute();
    $conflitos = $stmt->get_result();

    if ($conflitos->num_rows > 0 && !$force) {
        echo json_encode(['success' => false, 'conflict' => true, 'message' => 'Esta rotina conflita com horarios ja existentes. Deseja continuar mesmo assim?']);
        exit;
    }

    if ($id_rotina > 0) {
        $stmt = $mysqli->prepare("UPDATE rotinas SET
                data_inicio=?, data_termino=?,
                hora_inicio=?, hora_termino=?,
                duracao=?, intervalo_sessoes=?, cor=?,
                domingo=?, segunda=?, terca=?,
                quarta=?, quinta=?, sexta=?, sabado=?
                WHERE id=?");
        $stmt->bind_param('ssssiisiiiiiiii',
            $data_inicio, $data_termino,
            $hora_inicio, $hora_termino,
            $duracao, $intervalo_sessoes, $cor,
            $domingo, $segunda, $terca,
            $quarta, $quinta, $sexta, $sabado,
            $id_rotina
        );
    } else {
        $stmt = $mysqli->prepare("INSERT INTO rotinas
                (id_agenda,data_inicio,data_termino,hora_inicio,hora_termino,duracao,
                 intervalo_sessoes,domingo,segunda,terca,quarta,quinta,sexta,sabado,cor)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issssiiiiiiiiis',
            $id_agenda,
            $data_inicio, $data_termino,
            $hora_inicio, $hora_termino,
            $duracao, $intervalo_sessoes,
            $domingo, $segunda, $terca,
            $quarta, $quinta, $sexta, $sabado,
            $cor
        );
    }

    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
?>
