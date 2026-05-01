<?php
    session_start();
    include('conexao.php');

    $usuario = $_SESSION['nome'];
    $id_usuario = $_SESSION['id'];

    $id_agenda = (int)$_POST['id_agenda'];

    $data = new DateTime($_POST['data']);
    $data = $data->format('Y-m-d');

    $hora_inicio = new DateTime($_POST['hora_inicio']);
    $hora_inicio = $hora_inicio->format('H:i:s');
    $hora_termino = new DateTime($_POST['hora_final']);
    $hora_termino = $hora_termino->format('H:i:s');

    $sql = "INSERT INTO excessoes (id_agenda,data,hora_inicio,hora_termino) VALUES ('$id_agenda','$data','$hora_inicio','$hora_termino')";
    $mysqli->query($sql);

    header("Location: configurar_agenda.php");
?>