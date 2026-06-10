<?php
require_once('conexao.php');
require_once('includes/security.php');
configurarSessao();

$erro = '';

if(isset($_POST['email']) || isset($_POST['senha'])) {
    if (!isset($_POST['_csrf_token']) || !validarTokenCSRF($_POST['_csrf_token'])) {
        $erro = 'Token CSRF invalido.';
    } elseif(strlen($_POST['email']) == 0) {
        $erro = 'Preencha seu e-mail';
    } elseif(strlen($_POST['senha']) == 0) {
        $erro = 'Preencha sua senha';
    } else {
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
        $email = trim($_POST['email']);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $sql_query = $stmt->get_result();

        if($sql_query->num_rows == 1) {
            $usuario = $sql_query->fetch_assoc();

            if (password_verify($_POST['senha'], $usuario['senha_hash'])) {
                session_regenerate_id(true);
                $_SESSION['id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                header("Location: painel.php");
                exit;
            } else {
                $erro = 'Falha ao logar! E-mail ou senha incorretos';
            }
        } else {
            $erro = 'Falha ao logar! E-mail ou senha incorretos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilite — Login</title>
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
        .login-box {
            background: #fff; border-radius: 12px;
            padding: 36px 32px; width: 100%; max-width: 360px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            text-align: center;
        }
        .login-box img { height: 60px; margin-bottom: 16px; }
        .login-box h2 { font-size: 1.1rem; margin-bottom: 20px; font-weight: 500; color: #333; }
        .login-box label { display: block; text-align: left; font-size: 0.78rem; font-weight: 500; color: #555; margin-bottom: 4px; }
        .login-box input {
            width: 100%; padding: 10px 12px; font-size: 0.85rem;
            border: 1px solid #ccc; border-radius: 6px; margin-bottom: 14px;
            outline: none; transition: border-color 0.2s;
        }
        .login-box input:focus { border-color: #3465a4; }
        .login-box button {
            width: 100%; padding: 10px; font-size: 0.85rem; font-weight: 600;
            background: #3465a4; color: #fff; border: none; border-radius: 6px;
            cursor: pointer; transition: background 0.2s;
        }
        .login-box button:hover { background: #2b5490; }
        .login-box .erro { font-size: 0.78rem; color: #d93025; margin-bottom: 12px; }
        .login-box .links { margin-top: 16px; font-size: 0.78rem; }
        .login-box .links a { color: #3465a4; text-decoration: none; }
        .login-box .links a:hover { text-decoration: underline; }
        footer { margin-top: 20px; font-size: 0.7rem; color: #bbb; }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="images/logo.jpg" alt="Facilite">
        <h2>Entrar</h2>
        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="POST">
            <?= campoCSRF() ?>
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" required autocomplete="email">
            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" required autocomplete="current-password">
            <button type="submit">Entrar</button>
        </form>
        <div class="links">
            <a href="nova_conta.php">Criar conta</a> &middot;
            <a href="recuperar_senha.php">Esqueci a senha</a>
        </div>
    </div>
    <footer>Facilite &mdash; 2026</footer>
</body>
</html>
