<?php
    session_start();
    include('conexao.php');

    $id_agenda = (int)$_POST['id_agenda'];
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

    $check_sql = "SELECT id FROM rotinas WHERE id_agenda = '$id_agenda'
                  AND id != '$id_rotina'
                  AND data_inicio <= '$data_termino' AND data_termino >= '$data_inicio'
                  AND hora_inicio < '$hora_termino' AND hora_termino > '$hora_inicio'
                  AND (
                      (domingo = 1 AND $domingo = 1) OR (segunda = 1 AND $segunda = 1) OR
                      (terca = 1 AND $terca = 1) OR (quarta = 1 AND $quarta = 1) OR
                      (quinta = 1 AND $quinta = 1) OR (sexta = 1 AND $sexta = 1) OR
                      (sabado = 1 AND $sabado = 1)
                  )";

    $conflitos = $mysqli->query($check_sql);

    if ($conflitos->num_rows > 0 && !$force) {
        echo json_encode(['success' => false, 'conflict' => true, 'message' => 'Esta rotina conflita com horários já existentes. Deseja continuar mesmo assim?']);
        exit;
    }

    if ($id_rotina > 0) {
        $sql = "UPDATE rotinas SET
                data_inicio='$data_inicio', data_termino='$data_termino',
                hora_inicio='$hora_inicio', hora_termino='$hora_termino',
                duracao='$duracao', cor='$cor',
                domingo='$domingo', segunda='$segunda', terca='$terca',
                quarta='$quarta', quinta='$quinta', sexta='$sexta', sabado='$sabado'
                WHERE id='$id_rotina'";
    } else {
        $sql = "INSERT INTO rotinas (id_agenda,data_inicio,data_termino,hora_inicio,hora_termino,duracao,domingo,segunda,terca,quarta,quinta,sexta,sabado,cor)
                VALUES ('$id_agenda','$data_inicio','$data_termino','$hora_inicio','$hora_termino','$duracao','$domingo','$segunda','$terca','$quarta','$quinta','$sexta','$sabado','$cor')";
    }

    $mysqli->query($sql);

    echo json_encode(['success' => true]);
    exit;
?>
