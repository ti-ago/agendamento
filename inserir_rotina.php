<?php
    require_once('protect.php');
    include('conexao.php');
    header('Content-Type: application/json');

    verificarCSRF();

    $id_agenda = (int)$_POST['id_agenda'];

    if (!usuarioDono($mysqli, 'rotinas', $id_agenda, 'id_agenda')) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $id_rotina = isset($_POST['id_rotina']) ? (int)$_POST['id_rotina'] : 0;
    $force = isset($_POST['force']) && $_POST['force'] === '1';

    $data_inicio = (new DateTime($_POST['data_inicio']))->format('Y-m-d');
    $data_termino = (new DateTime($_POST['data_final']))->format('Y-m-d');
    $hora_inicio = (new DateTime($_POST['hora_inicio']))->format('H:i:s');
    $hora_termino = (new DateTime($_POST['hora_final']))->format('H:i:s');
    $duracao = (int)$_POST['duracao'];
    $cor = $_POST['cor'] ?? '#3465a4';

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
    $stmt->bind_param('iissssiiiiiiii',
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
                duracao=?, cor=?,
                domingo=?, segunda=?, terca=?,
                quarta=?, quinta=?, sexta=?, sabado=?
                WHERE id=?");
        $stmt->bind_param('ssssisiiiiiiii',
            $data_inicio, $data_termino,
            $hora_inicio, $hora_termino,
            $duracao, $cor,
            $domingo, $segunda, $terca,
            $quarta, $quinta, $sexta, $sabado,
            $id_rotina
        );
    } else {
        $stmt = $mysqli->prepare("INSERT INTO rotinas
                (id_agenda,data_inicio,data_termino,hora_inicio,hora_termino,duracao,
                 domingo,segunda,terca,quarta,quinta,sexta,sabado,cor)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issssiiiiiiiii',
            $id_agenda,
            $data_inicio, $data_termino,
            $hora_inicio, $hora_termino,
            $duracao,
            $domingo, $segunda, $terca,
            $quarta, $quinta, $sexta, $sabado,
            $cor
        );
    }

    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
?>
