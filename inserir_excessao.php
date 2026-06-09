<?php
    session_start();
    include('conexao.php');

    $id_agenda = (int)$_POST['id_agenda'];
    $id_excessao = isset($_POST['id_excessao']) ? (int)$_POST['id_excessao'] : 0;

    $data = new DateTime($_POST['data']);
    $data = $data->format('Y-m-d');

    $data_termino = !empty($_POST['data_termino'])
        ? (new DateTime($_POST['data_termino']))->format('Y-m-d')
        : null;

    $hora_inicio = (new DateTime($_POST['hora_inicio']))->format('H:i:s');
    $hora_termino = (new DateTime($_POST['hora_final']))->format('H:i:s');

    $domingo  = (int)($_POST['domingo'] ?? 0);
    $segunda  = (int)($_POST['segunda'] ?? 0);
    $terca    = (int)($_POST['terca'] ?? 0);
    $quarta   = (int)($_POST['quarta'] ?? 0);
    $quinta   = (int)($_POST['quinta'] ?? 0);
    $sexta    = (int)($_POST['sexta'] ?? 0);
    $sabado   = (int)($_POST['sabado'] ?? 0);

    $data_termino_val = $data_termino ? "'$data_termino'" : "NULL";

    if ($id_excessao > 0) {
        $sql = "UPDATE excessoes SET
                data='$data', data_termino=$data_termino_val,
                hora_inicio='$hora_inicio', hora_termino='$hora_termino',
                domingo='$domingo', segunda='$segunda', terca='$terca',
                quarta='$quarta', quinta='$quinta', sexta='$sexta', sabado='$sabado'
                WHERE id='$id_excessao'";
    } else {
        $sql = "INSERT INTO excessoes
                (id_agenda,data,data_termino,hora_inicio,hora_termino,domingo,segunda,terca,quarta,quinta,sexta,sabado)
                VALUES ('$id_agenda','$data',$data_termino_val,'$hora_inicio','$hora_termino',
                '$domingo','$segunda','$terca','$quarta','$quinta','$sexta','$sabado')";
    }

    $mysqli->query($sql);

    echo json_encode(['success' => true]);
    exit;
?>