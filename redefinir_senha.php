<?php
include('conexao.php');
require_once('includes/security.php');
configurarSessao();

$mensagem = '';
$erro = '';
$token_valido = false;
$token = '';

if (isset($_GET['token'])) {
    $token = $mysqli->real_escape_string($_GET['token']);
    $query = $mysqli->query("SELECT id_user FROM reset_tokens WHERE token = '$token' AND usado = 0 AND expira_em > NOW()");

    if ($query && $query->num_rows == 1) {
        $token_valido = true;
    } else {
        $erro = 'Token invalido ou expirado. Solicite uma nova recuperacao.';
    }
}

if (isset($_POST['token']) && isset($_POST['senha']) && isset($_POST['confirmacao'])) {
    if (!validarTokenCSRF($_POST['_csrf_token'] ?? '')) {
        $erro = 'Token CSRF invalido.';
    } else {
        $token = $mysqli->real_escape_string($_POST['token']);
        $senha = $_POST['senha'];
        $confirmacao = $_POST['confirmacao'];

        if (strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres';
        } elseif ($senha !== $confirmacao) {
            $erro = 'As senhas nao conferem';
        } else {
            $query = $mysqli->query("SELECT id_user FROM reset_tokens WHERE token = '$token' AND usado = 0 AND expira_em > NOW()");

            if ($query && $query->num_rows == 1) {
                $row = $query->fetch_assoc();
                $id_user = (int)$row['id_user'];
                $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

                $stmt = $mysqli->prepare("UPDATE users SET senha_hash = ?, senha = '' WHERE id = ?");
                $stmt->bind_param('si', $senha_hash, $id_user);
                $stmt->execute();

                $stmt = $mysqli->prepare("UPDATE reset_tokens SET usado = 1 WHERE token = ?");
                $stmt->bind_param('s', $token);
                $stmt->execute();

                $mensagem = 'Senha redefinida com sucesso! Faca login com sua nova senha.';
                $token_valido = false;
            } else {
                $erro = 'Token invalido ou expirado.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilite - Redefinir Senha</title>
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
        <h1>Redefinir Senha</h1>
        <p class="subtitle">Escolha uma nova senha para sua conta</p>

        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($mensagem): ?>
            <div class="sucesso"><?= htmlspecialchars($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($token_valido): ?>
        <form action="" method="POST">
            <?= campoCSRF() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="form-group">
                <label for="senha">Nova Senha</label>
                <input type="password" name="senha" id="senha" placeholder="Minimo 6 caracteres" required>
            </div>
            <div class="form-group">
                <label for="confirmacao">Confirmar Senha</label>
                <input type="password" name="confirmacao" id="confirmacao" placeholder="Repita a senha" required>
            </div>
            <button type="submit" class="btn-primary">Redefinir Senha</button>
        </form>
        <?php elseif (!$erro && !$mensagem): ?>
            <p style="color:#888;font-size:0.9rem;">Token nao informado.</p>
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
