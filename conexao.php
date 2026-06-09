<?php

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}

$db_usuario = getenv('DB_USER') ?: 'admin';
$db_senha = getenv('DB_PASS') ?: 'admin';
$database = getenv('DB_NAME') ?: 'login';
$host = getenv('DB_HOST') ?: 'localhost';

$mysqli = new mysqli($host, $db_usuario, $db_senha, $database);

if ($mysqli->error) {
    error_log("Falha ao conectar ao banco de dados: " . $mysqli->error);
    die("Ocorreu um erro interno. Tente novamente mais tarde.");
}
