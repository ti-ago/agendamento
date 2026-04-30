<?php
include('protect.php');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel</title>
</head>
<body>
    Bem vindo ao Painel, <?php echo $_SESSION['nome']; ?>
    <p>
        <a href="nova_agenda.php">Cadastrar Nova Agenda</a>
    </p>
    <p>
        <a href="configurar_agenda.php">Configurar Agenda</a>
    </p>
    <p>
        <a href="logout.php">Sair</a>
    </p>
</body>
</html>