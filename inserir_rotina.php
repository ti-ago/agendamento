<?php
    session_start();
    include('conexao.php');

    $usuario = $_SESSION['nome'];
    $id_usuario = $_SESSION['id'];

    $id_agenda = (int)$_POST['id_agenda'];

    $data_inicio = new DateTime($_POST['data_inicio'])->format('Y-m-d');
    $data_termino = new DateTime($_POST['data_final'])->format('Y-m-d');

    $hora_inicio = new DateTime($_POST['hora_inicio'])->format('H:i:s');
    $hora_termino = new DateTime($_POST['hora_final'])->format('H:i:s');

    $duracao = (int)$_POST['duracao'];

    $domingo = (int)$_POST['domingo'];
    $segunda = (int)$_POST['segunda'];
    $terca = (int)$_POST['terca'];
    $quarta = (int)$_POST['quarta'];
    $quinta = (int)$_POST['quinta'];
    $sexta = (int)$_POST['sexta'];
    $sabado = (int)$_POST['sabado'];

    $sql = "INSERT INTO rotinas (id_agenda,data_inicio,data_termino,hora_inicio,hora_termino,duracao,domingo,segunda,terca,quarta,quinta,sexta,sabado) VALUES ('$id_agenda','$data_inicio','$data_termino','$hora_inicio','$hora_termino','$duracao','$domingo','$segunda','$terca','$quarta','$quinta','$sexta','$sabado')";
    $mysqli->query($sql);

    header("Location: configurar_agenda.php");
?>