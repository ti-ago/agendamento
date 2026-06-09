<?php
include('conexao.php');

$mensagem = '';
$erro = '';

if (isset($_GET['token'])) {
    $token = $mysqli->real_escape_string($_GET['token']);
    $query = $mysqli->query("SELECT id FROM users WHERE confirmacao_token = '$token' AND email_confirmado = 0");

    if ($query && $query->num_rows == 1) {
        $user = $query->fetch_assoc();
        $id = (int)$user['id'];
        $mysqli->query("UPDATE users SET email_confirmado = 1, confirmacao_token = NULL WHERE id = $id");

        if ($mysqli->affected_rows > 0) {
            $mensagem = 'E-mail confirmado com sucesso! Voce ja pode fazer login.';
        } else {
            $erro = 'Erro ao confirmar e-mail. Tente novamente.';
        }
    } else {
        $erro = 'Token invalido ou e-mail ja confirmado.';
    }
} else {
    $erro = 'Token nao informado.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilite - Confirmar E-mail</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 20px; color: #1a1a2e;
        }
        .card {
            background: #fff; border-radius: 16px; padding: 40px 36px;
            box-shadow: 0 2px 24px rgba(0,0,0,0.06);
            width: 100%; max-width: 400px; text-align: center;
        }
        .logo { margin-bottom: 24px; }
        .logo img { height: 60px; }
        .erro {
            background: #fce8e6; color: #c5221f; padding: 10px 14px;
            border-radius: 8px; font-size: 0.82rem; margin-bottom: 16px; text-align: left;
        }
        .sucesso {
            background: #e8f5e9; color: #1e7e34; padding: 10px 14px;
            border-radius: 8px; font-size: 0.82rem; margin-bottom: 16px; text-align: left;
        }
        .btn {
            display: inline-block; padding: 12px 24px; background: #3465a4;
            color: #fff; border: none; border-radius: 8px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; text-decoration: none;
            font-family: inherit; margin-top: 8px;
        }
        .btn:hover { background: #2a528a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <img src="images/logo.jpg" alt="Facilite">
        </div>
        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($mensagem): ?>
            <div class="sucesso"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>
        <a href="index.php" class="btn">Ir para o Login</a>
    </div>
    <footer style="margin-top:20px; font-size:0.75rem; color:#aaa; text-align:center;">
        Facilite &mdash; 2026
    </footer>
</body>
</html>
