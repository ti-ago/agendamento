<?php
    session_start();
    include('conexao.php');

    $usuario = $_SESSION['nome'];
    $id_usuario = $_SESSION['id'];

    if(isset($_POST['servico'])){
        if(strlen(trim($_POST['servico'])) == 0) {
            echo "Preencha o serviço";
        } else {
            $novo_servico = $mysqli->real_escape_string($_POST['servico']);
            $sql = "SELECT * FROM agenda WHERE servico = '$novo_servico' AND id_user = '$id_usuario'";
            $consulta = $mysqli->query($sql);
            if($mysqli->affected_rows > 0) {
                echo "Já existe uma entrada com esse nome";
            } else {
                $sucesso = $mysqli->query("INSERT INTO agenda (id_user, servico) VALUES ('$id_usuario','$novo_servico')");
                if($sucesso){
                    echo "sucesso";
                }
            }
        }
    }


    $sql = "SELECT agenda.servico FROM agenda 
        INNER JOIN users ON agenda.id_user = users.id 
        WHERE users.nome = '$usuario'";

        $resultado = $mysqli->query($sql);

        $listaServicos = [];

        if ($resultado) {
            while ($linha = $resultado->fetch_assoc()) {
                // Adicionamos cada serviço ao final do array
                $listaServicos[] = $linha['servico'];
            }
        }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Agenda</title>
</head>
<body>
    <p>
        <?php
            echo $usuario;
        ?>
    </p>
    <form action="" method="POST">
        <p>
            <label for="servico">Escolha um serviço ou digite um novo:</label>
            <input list="servicos" id="servico" name="servico" placeholder="Selecione ou digite..." autocomplete="off">
            <datalist id="servicos">
                <?php 
                    foreach ($listaServicos as $servico):
                ?>
                    <option value="<?php echo htmlspecialchars($servico); ?>">
                <?php 
                    endforeach; 
                ?>  
            </datalist>
        </p>
        <p>
            <button type="submit">Confirmar</button>
        </p>
        <div>
            <a href="painel.php">Voltar ao Painel</a>
        </div>
    </form>   
</body>
</html>
