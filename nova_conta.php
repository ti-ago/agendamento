<?php
include('conexao.php');
require_once('includes/security.php');
configurarSessao();

$mensagem = '';
$erro = '';

if(isset($_POST['email']) && isset($_POST['nome']) && isset($_POST['senha']) && isset($_POST['confirmacao'])){

    if (!validarTokenCSRF($_POST['_csrf_token'] ?? '')) {
        $erro = 'Token CSRF invalido.';
    } elseif(strlen(trim($_POST['email'])) == 0) {
        $erro = 'Preencha seu e-mail';
    } elseif(strlen(trim($_POST['nome'])) == 0) {
        $erro = 'Preencha seu nome';
    } elseif(strlen(trim($_POST['senha'])) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres';
    } elseif(trim($_POST['confirmacao']) != trim($_POST['senha'])) {
        $erro = 'A confirmacao deve ser igual a senha';
    } else {
        $email = $mysqli->real_escape_string(trim($_POST['email']));
        $nome = $mysqli->real_escape_string(trim($_POST['nome']));
        $senha_hash = password_hash($_POST['senha'], PASSWORD_BCRYPT);

        $result = $mysqli->query("SELECT * FROM users WHERE email = '$email'");

        if($result && $result->num_rows > 0) {
            $erro = 'E-mail ja cadastrado';
        } else {
            $sql_code = "INSERT INTO users (email, nome, senha, senha_hash, email_confirmado) VALUES('$email','$nome','','$senha_hash', 1)";
            $result = $mysqli->query($sql_code);

            if($mysqli->affected_rows > 0) {
                $mensagem = 'Conta criada! Voce ja pode fazer login.';
            } else {
                $erro = 'Falha ao criar novo usuario';
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
    <title>Facilite - Criar Conta</title>
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
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 2px 24px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 380px;
            text-align: center;
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
            transition: border-color 0.15s;
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
        <h1>Criar Conta</h1>
        <p class="subtitle">Cadastre-se para gerenciar suas agendas</p>

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
                <label for="nome">Nome</label>
                <input type="text" name="nome" id="nome" placeholder="Seu nome" required>
            </div>
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" name="senha" id="senha" placeholder="Minimo 6 caracteres" required>
            </div>
            <div class="form-group">
                <label for="confirmacao">Confirmar Senha</label>
                <input type="password" name="confirmacao" id="confirmacao" placeholder="Repita a senha" required>
            </div>
            <button type="submit" class="btn-primary">Criar Conta</button>
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
