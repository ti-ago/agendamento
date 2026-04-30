<?php

$db_usuario = 'admin';
$db_senha = 'admin';
$database = 'login';
$host = 'localhost';

$mysqli = new mysqli($host, $db_usuario, $db_senha, $database);

if($mysqli->error) {
    die("Falha ao conectar ao banco de dados: " . $mysqli->error);
}