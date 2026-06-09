<?php
include('conexao.php');

$erro = '';

if(isset($_POST['email']) || isset($_POST['senha'])) {
    if(strlen($_POST['email']) == 0) {
        $erro = 'Preencha seu e-mail';
    } else if(strlen($_POST['senha']) == 0) {
        $erro = 'Preencha sua senha';
    } else {
        $email = $mysqli->real_escape_string($_POST['email']);
        $sql_code = "SELECT * FROM users WHERE email = '$email'";
        $sql_query = $mysqli->query($sql_code) or die("Falha na execucao do codigo SQL: " . $mysqli->error);

        if($sql_query->num_rows == 1) {
            $usuario = $sql_query->fetch_assoc();

            if ($usuario['email_confirmado'] == 0 && $usuario['confirmacao_token'] !== null) {
                $erro = 'Confirme seu e-mail antes de fazer login. Verifique sua caixa de entrada.';
            } elseif (password_verify($_POST['senha'], $usuario['senha_hash'])) {
                if(!isset($_SESSION)) session_start();
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
    <title>Facilite - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1a1a2e;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 2px 24px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .logo {
            margin-bottom: 24px;
        }
        .logo img { height: 60px; }
        h1 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            border-color: #3465a4;
            outline: none;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #3465a4;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s;
            margin-top: 4px;
        }
        .btn-login:hover { background: #2a528a; }
        .links {
            margin-top: 18px;
            font-size: 0.82rem;
        }
        .links a {
            color: #3465a4;
            text-decoration: none;
            display: block;
            margin-bottom: 6px;
        }
        .links a:hover { text-decoration: underline; }
        .erro {
            background: #fce8e6;
            color: #c5221f;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 16px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <img src="images/logo.jpg" alt="Facilite">
        </div>
        <h1>Entrar</h1>
        <p class="subtitle">Acesse sua conta de agendamento</p>

        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" placeholder="Sua senha" required>
            </div>
            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="links">
            <a href="recuperar_senha.php">Esqueceu a senha?</a>
            <a href="nova_conta.php">Criar uma conta</a>
        </div>
    </div>
    <footer style="margin-top:20px; font-size:0.75rem; color:#aaa; text-align:center;">
        Facilite &mdash; 2026
    </footer>
</body>
</html>
