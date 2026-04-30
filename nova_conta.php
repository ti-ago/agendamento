<?php
include('conexao.php');

if(isset($_POST['email']) && isset($_POST['nome']) && isset($_POST['senha']) && isset($_POST['confirmacao'])){

if(strlen(trim($_POST['email'])) == 0) {
    echo "Preencha seu e-mail";
    } else if(strlen(trim($_POST['nome'])) == 0) {
        echo "Preencha seu nome";
    } else if(strlen(trim($_POST['senha'])) == 0) {
        echo "Preencha sua senha";
    } else if((trim($_POST['confirmacao'])) !=  (trim($_POST['senha']))){
        echo "A confirmação deve ser igual a senha";
    } else{
        $email = $mysqli->real_escape_string($_POST['email']);
        $nome = $mysqli->real_escape_string($_POST['nome']);
        $senha = $mysqli->real_escape_string($_POST['senha']);

        $result = $mysqli->query("SELECT * FROM users WHERE email = '$email'");
        
        if($result->num_rows > 0){
            echo "email já cadastrado";
            
        } else {
            $sql_code = "INSERT INTO users (email,nome,senha) VALUES('$email','$nome','$senha')";
            $result = $mysqli->query($sql_code);

            if($mysqli->affected_rows > 0) {
            echo "Usuário criado com sucesso";
            } else {
                echo "Falha ao criar novo usuário";
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
    <title>Criar conta</title>
</head>
<body >
    <img src="images/logo.jpg">
    <form action="" method="POST">
        <p>
            <input type="email" name="email" placeholder="email" required>
        </p>
        <p>
            <input type="nome" name="nome" placeholder="nome" required>
        </p>
        <p>
            <input type="password" name="senha" placeholder="senha" required>
        </p>
        <p>
            <input type="password" name="confirmacao" placeholder="repita a senha" required>
        </p>
        <p>
            <button type="submit">Criar</button>
        </p>
        <p>
            <a href="index.php">Voltar para login</a>
        </p>
    </form>
</body>
</html>

