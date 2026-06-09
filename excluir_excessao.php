<?php
    session_start();
    include('conexao.php');

    $id = (int)$_GET['id'];
    $sql = "DELETE FROM excessoes WHERE id = '$id'";
    $mysqli->query($sql);

    echo json_encode(['success' => true]);
    exit;
?>