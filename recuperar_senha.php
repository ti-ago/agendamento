<?php
include('conexao.php');
require_once('includes/security.php');
configurarSessao();

$mensagem = '';
$erro = '';

if (isset($_POST['email'])) {
    if (!validarTokenCSRF($_POST['_csrf_token'] ?? '')) {
        $erro = 'Token CSRF invalido.';
    } else {
        $email = $mysqli->real_escape_string(trim($_POST['email']));
        $query = $mysqli->query("SELECT id, nome FROM users WHERE email = '$email'");

        if ($query && $query->num_rows == 1) {
            $user = $query->fetch_assoc();
            $token = bin2hex(random_bytes(32));
            $id_user = (int)$user['id'];
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $mysqli->query("INSERT INTO reset_tokens (id_user, token, expira_em) VALUES ($id_user, '$token', '$expira')");

            $link_redefinir = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/agendamento/redefinir_senha.php?token=' . $token;
            $assunto = 'Recuperacao de Senha - Facilite';
            $corpo = "Ola {$user['nome']},\n\nRecebemos uma solicitacao de recuperacao de senha.\nClique no link abaixo para redefinir sua senha:\n$link_redefinir\n\nEste link e valido por 1 hora.\n\nSe voce nao solicitou esta recuperacao, ignore este e-mail.\n\nAtenciosamente,\nEquipe Facilite";

            require_once 'includes/email.php';
            if (enviarEmail($email, $assunto, $corpo)) {
                $mensagem = 'Enviamos um link de recuperacao para seu e-mail.';
            } else {
                $erro = 'Erro ao enviar e-mail. Tente novamente.';
            }
        } else {
            $erro = 'E-mail nao encontrado em nossa base.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilite - Recuperar Senha</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5; min-height: 100vh;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 20px; color: #1a1a2e;
        }
        .card {
            background: #fff; border-radius: 16px; padding: 40px 36px;
            box-shadow: 0 2px 24px rgba(0,0,0,0.06);
            width: 100%; max-width: 380px; text-align: center;
        }
        .logo { margin-bottom: 24px; }
        .logo img { height: 60px; }
        h1 { font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }
        .subtitle { font-size: 0.85rem; color: #888; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label {
            display: block; font-size: 0.78rem; font-weight: 600;
            color: #555; margin-bottom: 4px;
        }
        .form-group input {
            width: 100%; padding: 10px 12px; border: 1px solid #d0d0d0;
            border-radius: 8px; font-size: 0.9rem; font-family: inherit;
        }
        .form-group input:focus { border-color: #3465a4; outline: none; }
        .btn-primary {
            width: 100%; padding: 12px; background: #3465a4; color: #fff;
            border: none; border-radius: 8px; font-size: 0.95rem;
            font-weight: 600; cursor: pointer; font-family: inherit;
            transition: background 0.15s; margin-top: 4px;
        }
        .btn-primary:hover { background: #2a528a; }
        .links { margin-top: 18px; font-size: 0.82rem; }
        .links a { color: #3465a4; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .erro {
            background: #fce8e6; color: #c5221f; padding: 10px 14px;
            border-radius: 8px; font-size: 0.82rem; margin-bottom: 16px; text-align: left;
        }
        .sucesso {
            background: #e8f5e9; color: #1e7e34; padding: 10px 14px;
            border-radius: 8px; font-size: 0.82rem; margin-bottom: 16px; text-align: left;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <img src="images/logo.jpg" alt="Facilite">
        </div>
        <h1>Recuperar Senha</h1>
        <p class="subtitle">Informe seu e-mail para receber o link de recuperacao</p>

        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($mensagem): ?>
            <div class="sucesso"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if (!$mensagem): ?>
        <form action="" method="POST">
            <?= campoCSRF() ?>
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" required>
            </div>
            <button type="submit" class="btn-primary">Enviar Link</button>
        </form>
        <?php endif; ?>

        <div class="links">
            <a href="index.php">Voltar para login</a>
        </div>
    </div>
    <footer style="margin-top:20px; font-size:0.75rem; color:#aaa; text-align:center;">
        Facilite &mdash; 2026
    </footer>
</body>
</html>
