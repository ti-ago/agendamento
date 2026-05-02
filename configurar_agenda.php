<?php
    session_start();
    include('conexao.php');

    $usuario = $_SESSION['nome'];
    $id_usuario = $_SESSION['id'];

    $eventos=[];
    $lista_excessoes=[];

    $agenda_selecionada = "";
    $id_final = "";

    if(isset($_POST['agenda'])){
        if(strlen(trim($_POST['agenda'])) == 0) {
            echo "Selecione uma agenda";
        } else {
            $agenda_selecionada = $mysqli->real_escape_string($_POST['agenda']);
            $query_id = $mysqli->query("SELECT id FROM agenda WHERE servico = '$agenda_selecionada'");
            

            if ($query_id) {
                $dados = $query_id->fetch_assoc();
                if ($dados) {
                    $id_final = $dados['id'];
                } else {
                    echo "Serviço não encontrado.";
                }
            }
        }
    }
    $sql_rotinas = "SELECT * FROM rotinas WHERE id_agenda = '$id_final'";
    $query_rotinas = $mysqli->query($sql_rotinas);

    $sql_excessoes = "SELECT * FROM excessoes WHERE id_agenda = '$id_final'";
    $query_excessoes = $mysqli->query($sql_excessoes);

    

    if($query_excessoes) {
        while($resposta = $query_excessoes->fetch_assoc()){
            $data = new DateTime($resposta['data']);
            $data_time = $data ->format('Y-m-d');
            $inicio = new DateTime($resposta['hora_inicio']);
            $inicio_time = $inicio ->format('H:i:s');
            $final = new DateTime($resposta['hora_termino']);
            $final_time = $final ->format('H:i:s');
            $lista_excessoes[] = [
                'data' => $data_time,
                'inicio' => $inicio_time ,
                'final' => $final_time
            ];
        }
        foreach ($lista_excessoes as $excessao){
            $eventos[]=[
                'title' => 'BLOQUEADO',
                'start' => $excessao['data']."T".$excessao['inicio'],
                'end' => $excessao['data']."T".$excessao['final'],
                'backgroundColor' => '#ff0000',
                'borderColor'=> '#cc0000'
            ];
        }
    }
   
    if($query_rotinas) {
        while($resposta = $query_rotinas->fetch_assoc()){
        
            $data_inicio = new DateTime($resposta['data_inicio']);
            $data_final = new DateTime($resposta['data_termino']);

            $hora_inicio = new DateTime($resposta['hora_inicio']);
            $hora_final = new DateTime($resposta['hora_termino']);

            $intervalo_horas = $hora_inicio->diff($hora_final);
            $intervalo_datas = $data_inicio->diff($data_final);

            $intervalo_minutos = $intervalo_horas->h*60 + $intervalo_horas->i;
            $intervalo_dias = $intervalo_datas->days;

            $duracao_atendimentos_minutos = $resposta['duracao'];
            $qtd_atendimentos = (int)($intervalo_minutos - $duracao_atendimentos_minutos) / $duracao_atendimentos_minutos;
            
            $dias_semana = [];

            if($resposta['domingo'] == "1"){
                $dias_semana[] = "0"; 
            };
            
            if($resposta['segunda'] == "1"){
                $dias_semana[] = "1"; 
            };

            if($resposta['terca'] == "1"){
                $dias_semana[] = "2"; 
            };

            if($resposta['quarta'] == "1"){
                $dias_semana[] = "3"; 
            };

            if($resposta['quinta'] == "1"){
                $dias_semana[] = "4"; 
            };

            if($resposta['sexta'] == "1"){
                $dias_semana[] = "5"; 
            };

            if($resposta['sabado'] == "1"){
                $dias_semana[] = "6"; 
            };
            

            for ($i = 0; $i <= $intervalo_dias; $i++) {
                $clone_data_inicio = clone $data_inicio;
                $dia = $clone_data_inicio->modify("+$i days");
                if(in_array($dia->format('w'),$dias_semana)){
                    for($j = 0; $j <= $qtd_atendimentos; $j++){
                        $clone_hora_inicio = clone $hora_inicio;
                        $minutos_soma = $duracao_atendimentos_minutos*$j;
                        $hora_inicio_atendimento = $clone_hora_inicio->modify("+ $minutos_soma minutes");
                        $hora_final_atendimento = clone $hora_inicio_atendimento;
                        $hora_final_atendimento->modify("+ $duracao_atendimentos_minutos minutes");

                        $horario_bloqueado = false; 
                        foreach ($lista_excessoes as $excessao){
                            if($dia->format("Y-m-d")==$excessao['data'] && ($hora_inicio_atendimento>=new DateTime($excessao['inicio'])) && ($hora_inicio_atendimento<new DateTime($excessao['final']))){
                                $horario_bloqueado=true;
                                break;
                            }
                            if($dia->format("Y-m-d")==$excessao['data'] && ($hora_final_atendimento>new DateTime($excessao['inicio'])) && ($hora_final_atendimento<=new DateTime($excessao['final']))){
                                $horario_bloqueado=true;
                                break;
                            }
                        }
                        
                        if(!$horario_bloqueado){
                            $eventos[] = [
                            'title' => 'Disponivel',
                            'start' => $dia->format('Y-m-d')."T".$hora_inicio_atendimento->format('H:i:s'),
                            'end' => $dia->format('Y-m-d')."T".$hora_final_atendimento->format('H:i:s'),
                            ];
                        }
                        $horario_bloqueado = false;   
                    }         
                }; 
            };
        }
    }

    $sql = "SELECT agenda.servico FROM agenda 
        INNER JOIN users ON agenda.id_user = users.id 
        WHERE users.nome = '$usuario'";

        $resultado = $mysqli->query($sql);

        $listaServicos = [];

        if ($resultado) {
            while ($linha = $resultado->fetch_assoc()) {
                $listaServicos[] = $linha['servico'];
            }
        }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Agenda</title>
    <style>
    #calendar {
      max-width: 700px;
      width: 100%;
      margin: 0 auto;
    }
  </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script>

    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');

        var eventosJs = <?php echo json_encode($eventos);?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            plugins: [ 
            ],
            initialView: 'timeGridWeek',
            locale:'pt-br',
            events: eventosJs
        });
        calendar.render();
      });

    </script>
<body>
    <p>
        <?php
            echo $usuario;
        ?>
    </p>
    
    <form action="" method="POST">
        <p>
            <label for="agenda">Escolha uma agenda:</label>
            <select name="agenda" id="agenda">
                    <option value="" disabled selected>Selecione uma opção...</option>
                <?php
                    foreach ($listaServicos as $agenda):
                ?>
                    <option value="<?php echo htmlspecialchars($agenda); ?>"><?php echo htmlspecialchars($agenda); ?></option>
                <?php 
                    endforeach; 
                ?>  
            </select>
        </p>
        <p>
            <button type="submit">Confirmar</button>
        </p>
    </form>
        <h2>
            <?php echo $agenda_selecionada ? "Você está editando a agenda: " . htmlspecialchars($agenda_selecionada) : "Selecione uma agenda acima"; ?>
        </h2>
    <div>
        <form action="inserir_rotina.php" method="POST">
            <input type="hidden" name="id_agenda" value="<?php echo $id_final?>">

            <input type="date" id="data_inicio" name="data_inicio">
            <label for="data_inicio">Data inicial</label>

            <input type="date" id="data_final" name="data_final">
            <label for="data_final">Data final</label>
    
            <input type="time" id="hora_inicio" name="hora_inicio">
            <label for="hora_inicio">Hora inicial</label>
    
            <input type="time" id="hora_final" name="hora_final">
            <label for="hora_final">Hora final</label>
    
            <input type="number" id="duracao" min="1" max="1440" name="duracao">
            <label for="duracao">Duração</label>
    
            <input type="checkbox" id="domingo" name="domingo" value="1">
            <label for="domingo">DOM</label>

            <input type="checkbox" id="segunda" name="segunda" value="1">
            <label for="segunda">SEG</label>
        
            <input type="checkbox" id="terca" name="terca" value="1">
            <label for="terca">TER</label>
    
            <input type="checkbox" id="quarta" name="quarta" value="1">
            <label for="quarta">QUA</label>

            <input type="checkbox" id="quinta" name="quinta"  value="1">
            <label for="quinta">QUI</label>
    
            <input type="checkbox" id="sexta" name="sexta" value="1">
            <label for="sexta">SEX</label>
    
            <input type="checkbox" id="sabado" name="sabado" value="1">
            <label for="sabado">SÁB</label>
        
            <p>
                <button type="submit">Confirmar rotina</button>
            </p>
        </form>
    </div>

    
   <div>
       <form action="inserir_excessao.php" method="POST">
            <input type="hidden" name="id_agenda" value="<?php echo $id_final?>">

            <input type="date" id="data" name="data">
            <label for="data">Data</label>
            
            <input type="time" id="hora_inicio" name="hora_inicio">
            <label for="hora_inicio">Hora inicial</label>
           
            <input type="time" id="hora_final" name="hora_final">
            <label for="hora_final">Hora final</label>
            
            <!--<input type="checkbox" id="repetir" name="repetir" value="1">
            <label for="repetir">Repetir</label>-->
                        
            <p>
                <button type="submit">Confirmar excessão</button>
            </p>
        </form>
    </div>
    <div id='calendar'></div>
    <div>
        <a href="painel.php">Voltar ao Painel</a>
    </div>
</body>
</html>