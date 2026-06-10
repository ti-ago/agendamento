<?php
    require_once('protect.php');
    include('conexao.php');
    require_once('includes/helpers.php');

    $usuario = $_SESSION['nome'];
    $id_usuario = $_SESSION['id'];
    $eventos=[];
    $lista_excessoes=[];
    $lista_excessoes_formatada=[];
    $lista_excessoes_raw=[];
    $lista_rotinas=[];

    $agenda_selecionada = "";
    $id_final = "";
    $agenda_config = ['servico' => '', 'nome_profissional' => '', 'foto_profissional' => '', 'chave_pix' => '', 'valor' => ''];

    // Save agenda config
    if (isset($_POST['salvar_config']) && isset($_POST['id_agenda_config'])) {
        verificarCSRF();
        $id_ac = (int)$_POST['id_agenda_config'];
        $novo_servico = $mysqli->real_escape_string($_POST['servico_nome']);
        $novo_prof = $mysqli->real_escape_string($_POST['nome_profissional']);
        $nova_chave_pix = $mysqli->real_escape_string($_POST['chave_pix'] ?? '');
        $novo_valor = str_replace(',', '.', $_POST['valor'] ?? '');
        $novo_valor = is_numeric($novo_valor) ? (float)$novo_valor : 'NULL';

        $sql_up = "UPDATE agenda SET servico='$novo_servico', nome_profissional='$novo_prof', chave_pix='$nova_chave_pix', valor=$novo_valor";
        $nova_mensagem = $mysqli->real_escape_string($_POST['mensagem_confirmacao'] ?? '');
        $novo_link = $mysqli->real_escape_string($_POST['link_confirmacao'] ?? '');
        $sql_up .= ", mensagem_confirmacao='$nova_mensagem', link_confirmacao='$novo_link'";
        if (!empty($_FILES['foto_profissional']['name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['foto_profissional']['tmp_name']);
            finfo_close($finfo);
            $mimes_validos = ['image/jpeg', 'image/png', 'image/webp'];
            $tamanho_max = 2 * 1024 * 1024;
            if (in_array($mime, $mimes_validos) && $_FILES['foto_profissional']['size'] <= $tamanho_max) {
                $ext = match($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                };
                $nome_foto = 'prof_' . $id_ac . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['foto_profissional']['tmp_name'], 'images/' . $nome_foto);
                $sql_up .= ", foto_profissional='$nome_foto'";
            }
        }
        $sql_up .= " WHERE id='$id_ac'";
        $mysqli->query($sql_up);
        // Re-select same agenda after save
        $agenda_selecionada = $novo_servico;
        $id_final = $id_ac;
    }

    if(isset($_POST['agenda'])){
        verificarCSRF();
        if(strlen(trim($_POST['agenda'])) == 0) {
            echo "Selecione uma agenda";
        } else {
            $agenda_selecionada = $mysqli->real_escape_string($_POST['agenda']);
            $query_id = $mysqli->query("SELECT * FROM agenda WHERE servico = '$agenda_selecionada'");

            if ($query_id) {
                $dados = $query_id->fetch_assoc();
                if ($dados) {
                    $id_final = $dados['id'];
                    $agenda_config = $dados;
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
            $hora_inicio_exc = new DateTime($resposta['hora_inicio']);
            $hora_final_exc = new DateTime($resposta['hora_termino']);

            $is_recurring = $resposta['domingo'] || $resposta['segunda'] ||
                            $resposta['terca'] || $resposta['quarta'] ||
                            $resposta['quinta'] || $resposta['sexta'] ||
                            $resposta['sabado'];

            if ($is_recurring && !empty($resposta['data_termino'])) {
                $data_inicio_exc = new DateTime($resposta['data']);
                $data_fim_exc = new DateTime($resposta['data_termino']);
                $dias_semana_exc = [];
                $dias_semana_str = '';
                if($resposta['domingo']) { $dias_semana_exc[] = 0; $dias_semana_str .= 'DOM '; }
                if($resposta['segunda']) { $dias_semana_exc[] = 1; $dias_semana_str .= 'SEG '; }
                if($resposta['terca'])   { $dias_semana_exc[] = 2; $dias_semana_str .= 'TER '; }
                if($resposta['quarta'])  { $dias_semana_exc[] = 3; $dias_semana_str .= 'QUA '; }
                if($resposta['quinta'])  { $dias_semana_exc[] = 4; $dias_semana_str .= 'QUI '; }
                if($resposta['sexta'])   { $dias_semana_exc[] = 5; $dias_semana_str .= 'SEX '; }
                if($resposta['sabado'])  { $dias_semana_exc[] = 6; $dias_semana_str .= 'SÁB '; }

                $intervalo_dias = $data_inicio_exc->diff($data_fim_exc)->days;

                for ($i = 0; $i <= $intervalo_dias; $i++) {
                    $clone_dia = clone $data_inicio_exc;
                    $dia = $clone_dia->modify("+$i days");
                    if (in_array($dia->format('w'), $dias_semana_exc)) {
                        $lista_excessoes[] = [
                            'id'    => $resposta['id'],
                            'data'  => $dia->format('Y-m-d'),
                            'inicio'=> $hora_inicio_exc->format('H:i:s'),
                            'final' => $hora_final_exc->format('H:i:s'),
                        ];
                        $eventos[] = [
                            'title'           => 'Bloqueado',
                            'start'           => $dia->format('Y-m-d').'T'.$hora_inicio_exc->format('H:i:s'),
                            'end'             => $dia->format('Y-m-d').'T'.$hora_final_exc->format('H:i:s'),
                            'backgroundColor' => '#fce8e6',
                            'borderColor'     => '#d93025',
                            'textColor'       => '#c5221f',
                            'classNames'      => ['event-blocked'],
                            'extendedProps'   => [
                                'tipo'     => 'bloqueio',
                                'origem'   => 'Exceção #'.$resposta['id'],
                                'recorrente' => true,
                                'dias'     => trim($dias_semana_str),
                            ],
                        ];
                    }
                }

                $lista_excessoes_formatada[] = [
                    'id'          => $resposta['id'],
                    'data'        => $data_inicio_exc->format('d-m-Y'),
                    'data_termino'=> $data_fim_exc->format('d-m-Y'),
                    'inicio'      => $hora_inicio_exc->format('H:i'),
                    'final'       => $hora_final_exc->format('H:i'),
                    'dias_semana' => trim($dias_semana_str),
                    'recorrente'  => true,
                ];
            } else {
                $data_exc = new DateTime($resposta['data']);
                $data_time = $data_exc->format('Y-m-d');

                $lista_excessoes[] = [
                    'id'    => $resposta['id'],
                    'data'  => $data_time,
                    'inicio'=> $hora_inicio_exc->format('H:i:s'),
                    'final' => $hora_final_exc->format('H:i:s'),
                ];
                $eventos[] = [
                    'title'           => 'Bloqueado',
                    'start'           => $data_time.'T'.$hora_inicio_exc->format('H:i:s'),
                    'end'             => $data_time.'T'.$hora_final_exc->format('H:i:s'),
                    'backgroundColor' => '#fce8e6',
                    'borderColor'     => '#d93025',
                    'textColor'       => '#c5221f',
                    'classNames'      => ['event-blocked'],
                    'extendedProps'   => [
                        'tipo'       => 'bloqueio',
                        'origem'     => 'Exceção #'.$resposta['id'],
                        'recorrente' => false,
                    ],
                ];
                $lista_excessoes_formatada[] = [
                    'id'          => $resposta['id'],
                    'data'        => $data_exc->format('d-m-Y'),
                    'data_termino'=> null,
                    'inicio'      => $hora_inicio_exc->format('H:i'),
                    'final'       => $hora_final_exc->format('H:i'),
                    'dias_semana' => null,
                    'recorrente'  => false,
                ];
            }
            $lista_excessoes_raw[] = [
                'id' => $resposta['id'],
                'data' => $resposta['data'],
                'data_termino' => $resposta['data_termino'],
                'hora_inicio' => $hora_inicio_exc->format('H:i'),
                'hora_final' => $hora_final_exc->format('H:i'),
                'domingo' => $resposta['domingo'],
                'segunda' => $resposta['segunda'],
                'terca' => $resposta['terca'],
                'quarta' => $resposta['quarta'],
                'quinta' => $resposta['quinta'],
                'sexta' => $resposta['sexta'],
                'sabado' => $resposta['sabado'],
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
            $passo = $duracao_atendimentos_minutos + (int)($resposta['intervalo_sessoes'] ?? 0);
            $qtd_atendimentos = $passo > 0 ? (int)($intervalo_minutos - $duracao_atendimentos_minutos) / $passo : 0;
            
            $dias_semana = [];
            $dias_semana_string = "";

            if($resposta['domingo'] == "1"){
                $dias_semana[] = "0";
                $dias_semana_string .= "DOM "; 
            };
            
            if($resposta['segunda'] == "1"){
                $dias_semana[] = "1";
                $dias_semana_string .= "SEG ";
            };

            if($resposta['terca'] == "1"){
                $dias_semana[] = "2";
                $dias_semana_string .= "TER ";
            };

            if($resposta['quarta'] == "1"){
                $dias_semana[] = "3";
                $dias_semana_string .= "QUA ";
            };

            if($resposta['quinta'] == "1"){
                $dias_semana[] = "4";
                $dias_semana_string .= "QUI ";
            };

            if($resposta['sexta'] == "1"){
                $dias_semana[] = "5";
                $dias_semana_string .= "SEX ";
            };

            if($resposta['sabado'] == "1"){
                $dias_semana[] = "6";
                $dias_semana_string .= "SÁB ";
            };

            $cor_rotina = $resposta['cor'] ?? '#3465a4';
            $lista_rotinas[] = [
                'id'=>$resposta['id'],
                'data_inicio' => $data_inicio->format("d-m-Y"),
                'data_final' => $data_final->format("d-m-Y"),
                'hora_inicio' => $hora_inicio->format("H:i") ,
                'hora_final' => $hora_final->format("H:i"),
                'duracao' => $duracao_atendimentos_minutos,
                'dias_semana' => $dias_semana_string,
                'cor' => $cor_rotina,
                'intervalo_sessoes' => (int)($resposta['intervalo_sessoes'] ?? 0)
            ];
            

            for ($i = 0; $i <= $intervalo_dias; $i++) {
                $clone_data_inicio = clone $data_inicio;
                $dia = $clone_data_inicio->modify("+$i days");
                if(in_array($dia->format('w'),$dias_semana)){
                    for($j = 0; $j <= $qtd_atendimentos; $j++){
                        $clone_hora_inicio = clone $hora_inicio;
                        $minutos_soma = $passo*$j;
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
                            $cor_disponivel = $cor_rotina;
                            $eventos[] = [
                            'title'           => 'Disponível',
                            'start'           => $dia->format('Y-m-d')."T".$hora_inicio_atendimento->format('H:i:s'),
                            'end'             => $dia->format('Y-m-d')."T".$hora_final_atendimento->format('H:i:s'),
                            'backgroundColor' => $cor_disponivel . '44',
                            'borderColor'     => $cor_disponivel,
                            'textColor'       => $cor_disponivel,
                            'classNames'      => ['event-available'],
                            'extendedProps'   => [
                                'tipo'   => 'disponivel',
                                'origem' => 'Rotina #'.$resposta['id'],
                                'cor'    => $cor_disponivel,
                            ],
                            ];
                        }
                        $horario_bloqueado = false;   
                    }         
                }; 
            };
        }
    }

    // Se for uma requisição AJAX pedindo dados, retorna apenas o JSON e encerra
    if (isset($_GET['ajax_load'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'eventos' => $eventos,
            'rotinas' => $lista_rotinas,
            'excessoes' => $lista_excessoes_formatada,
            'excessoes_raw' => $lista_excessoes_raw
        ]);
        exit;
    }

    $json_rotinas = json_encode($lista_rotinas);
    $json_excessoes = json_encode($lista_excessoes_formatada);
    $json_excessoes_raw = json_encode($lista_excessoes_raw);

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
    <title>Facilite — Configurar Agenda</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/estilo.css">
    <style>
    /* configurar_agenda-specific overrides */
  </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div style="margin-bottom: 12px;">
                <img src="images/logo.jpg" alt="Facilite" height="44" style="height:44px;">
            </div>
            <div style="margin-bottom: 20px;">
                <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                    <?php if (!empty($agenda_config['foto_profissional'])): ?>
                        <?php $url = \getFotoUrl($agenda_config['foto_profissional']); if ($url): ?>
                            <img src="<?= htmlspecialchars($url) ?>" alt="Foto" style="width:160px;height:160px;object-fit:cover;border-radius:8px;">
                        <?php endif; ?>
                    <?php endif; ?>
                    <div style="text-align:center;">
                        <strong style="font-size:0.85rem;"><?php echo htmlspecialchars($usuario); ?></strong><br>
                        <a href="logout.php" style="font-size:0.8rem;">Sair</a>
                    </div>
                </div>
            </div>

            <form action="" method="POST" id="formSelecionarAgenda">
                <?= campoCSRF() ?>
                <label for="agenda">Agenda:</label>
                <select name="agenda" id="agenda" onchange="this.form.submit()">
                    <option value="" disabled <?php if(!$agenda_selecionada) echo 'selected'; ?>>Selecione...</option>
                    <?php foreach ($listaServicos as $agenda): ?>
                        <option value="<?php echo htmlspecialchars($agenda); ?>" <?php if($agenda_selecionada == $agenda) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($agenda); ?>
                        </option>
                    <?php endforeach; ?>  
                </select>
            </form>

            <?php if($id_final):
                $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                $public_link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/agendamento/agendar.php?id=' . $id_final;
            ?>
                <button onclick="abrirModalRotina()" style="width:100%; margin-top:20px;">+ Nova Rotina</button>
                <button onclick="abrirModalExcessao()" style="width:100%; margin-top:10px;">+ Novo Bloqueio</button>

                <div style="margin-top: 24px; padding: 12px; background: #f0f4f8; border-radius: 8px;">
                    <label style="font-size:0.8rem; font-weight:600; display:block; margin-bottom:4px;">Link Público</label>
                    <p style="font-size:0.75rem; color:#666; margin-bottom:8px;">Compartilhe este link para clientes agendarem.</p>
                    <div style="display:flex; gap:4px;">
                        <input type="text" id="publicLink" value="<?= htmlspecialchars($public_link) ?>" readonly
                               style="flex:1; font-size:0.75rem; padding:6px 8px; border:1px solid #ccc; border-radius:4px; background:#fff;">
                        <button onclick="copiarLink()" style="padding:6px 10px; font-size:0.75rem; margin:0; white-space:nowrap;">Copiar</button>
                    </div>
                </div>

                <!-- Configurações da Agenda -->
                <div style="margin-top:24px; padding:12px; background:#fff8e1; border-radius:8px;">
                    <label style="font-size:0.8rem; font-weight:600; display:block; margin-bottom:8px;">Configuracoes da Agenda</label>
                    <form method="POST" enctype="multipart/form-data" id="formConfigAgenda">
                        <input type="hidden" name="salvar_config" value="1">
                        <input type="hidden" name="id_agenda_config" value="<?= $id_final ?>">
                        <?= campoCSRF() ?>
                        <label style="font-size:0.75rem; font-weight:500;">Nome do Serviço</label>
                        <input type="text" name="servico_nome" value="<?= htmlspecialchars($agenda_config['servico'] ?? '') ?>"
                               style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:500;">Nome do Profissional</label>
                        <input type="text" name="nome_profissional" value="<?= htmlspecialchars($agenda_config['nome_profissional'] ?? '') ?>"
                               style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:500;">Chave PIX (para pagamento)</label>
                        <input type="text" name="chave_pix" value="<?= htmlspecialchars($agenda_config['chave_pix'] ?? '') ?>"
                               style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:500;">Mensagem de Confirmação <small>(exibida após agendamento)</small></label>
                        <textarea name="mensagem_confirmacao" rows="3" style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.8rem; margin-bottom:8px; font-family:inherit; resize:vertical;"><?= htmlspecialchars($agenda_config['mensagem_confirmacao'] ?? '') ?></textarea>
                        <label style="font-size:0.75rem; font-weight:500;">Link Personalizado <small>(Google Forms, etc.)</small></label>
                        <input type="url" name="link_confirmacao" value="<?= htmlspecialchars($agenda_config['link_confirmacao'] ?? '') ?>" placeholder="https://forms.google.com/..."
                               style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:500;">Valor do Serviço (R$)</label>
                        <input type="text" name="valor" value="<?= htmlspecialchars($agenda_config['valor'] ?? '') ?>" placeholder="Ex: 79,90"
                               style="width:100%; padding:6px 8px; border:1px solid #ccc; border-radius:4px; font-size:0.8rem; margin-bottom:8px;">
                        <label style="font-size:0.75rem; font-weight:500;">Foto do Profissional</label>
                        <input type="file" name="foto_profissional" accept="image/jpeg,image/png,image/webp"
                               style="width:100%; font-size:0.75rem; margin-bottom:6px;">
                        <button type="submit" style="padding:6px 12px; font-size:0.75rem; margin:0;">Salvar</button>
                    </form>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="painel.php">Voltar ao Painel</a>
            </div>
        </div>

        <div class="main-content">
            <h1 style="color: #3465a4; margin-top: 0; font-size: 1.5rem;">
                <?php echo $agenda_selecionada ? "Visualizando: " . htmlspecialchars($agenda_selecionada) : "Selecione uma agenda na lateral"; ?>
            </h1>

            <h2>Rotinas Ativas</h2>
            <table>
                <thead><tr><th>ID</th><th>Início</th><th>Fim</th><th>Hora</th><th>Dias</th><th>Ações</th></tr></thead>
                <tbody id="tabela_rotinas"></tbody>
            </table>

            <h2>Exceções / Bloqueios</h2>
            <table>
                <thead><tr><th>ID</th><th>Data Início</th><th>Data Fim</th><th>Dias</th><th>Horário</th><th>Ações</th></tr></thead>
                <tbody id="tabela_excessoes"></tbody>
            </table>

            <div id='calendar'></div>
        </div>
    </div>

    <!-- Modal Rotina -->
    <div id="modalRotina" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalRotinaTitle">Nova Rotina</h2>
                <span class="close-modal" onclick="fecharModais()">&times;</span>
            </div>
            <form id="formRotina" onsubmit="enviarFormulario(event, 'inserir_rotina.php')">
                <input type="hidden" name="id_agenda" value="<?php echo $id_final?>">
                <input type="hidden" name="id_rotina" id="edit_id_rotina" value="0">
                
                <label>Período</label>
                <div style="display:flex; gap:5px;">
                    <input type="date" name="data_inicio" id="edit_data_inicio" required>
                    <input type="date" name="data_final" id="edit_data_final" required>
                </div>
                <label>Horário</label>
                <div style="display:flex; gap:5px;">
                    <input type="time" name="hora_inicio" id="edit_hora_inicio" required>
                    <input type="time" name="hora_final" id="edit_hora_final" required>
                </div>
                <label>Duração (min)</label>
                <input type="number" name="duracao" id="edit_duracao" value="60" required>
                <label>Intervalo entre Sessões <small>(min)</small></label>
                <input type="number" name="intervalo_sessoes" id="edit_intervalo_sessoes" value="0" min="0" style="width:100%; padding:6px 8px; border:1px solid #bbb; border-radius:4px; font-size:0.85rem;">
                <label>Dias da Semana</label>
                <div class="checkbox-grid">
                    <label><input type="checkbox" name="segunda" id="chk_seg" value="1"> SEG</label>
                    <label><input type="checkbox" name="terca" id="chk_ter" value="1"> TER</label>
                    <label><input type="checkbox" name="quarta" id="chk_qua" value="1"> QUA</label>
                    <label><input type="checkbox" name="quinta" id="chk_qui" value="1"> QUI</label>
                    <label><input type="checkbox" name="sexta" id="chk_sex" value="1"> SEX</label>
                    <label><input type="checkbox" name="sabado" id="chk_sab" value="1"> SAB</label>
                    <label><input type="checkbox" name="domingo" id="chk_dom" value="1"> DOM</label>
                </div>
                <label>Cor de Identificação</label>
                <input type="hidden" name="cor" id="cor_rotina" value="#3465a4">
                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px;" id="colorPicker">
                    <?php
                    $cores = ['#3465a4','#34a853','#d93025','#f09d00','#8855dd','#e91e8a','#00acc1','#fdd835','#795548','#607d8b','#3f51b5','#009688'];
                    foreach ($cores as $c):
                    ?>
                    <div class="color-swatch" data-color="<?= $c ?>" style="width:28px;height:28px;border-radius:50%;background:<?= $c ?>;cursor:pointer;border:3px solid transparent;transition:0.1s;" title="<?= $c ?>"></div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" style="width: 100%;">Confirmar</button>
            </form>
        </div>
    </div>

    <!-- Modal Exceção -->
    <div id="modalExcessao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalExcessaoTitle">Novo Bloqueio / Exceção</h2>
                <span class="close-modal" onclick="fecharModais()">&times;</span>
            </div>
            <form id="formExcessao" onsubmit="enviarFormulario(event, 'inserir_excessao.php')">
                <input type="hidden" name="id_agenda" value="<?php echo $id_final?>">
                <input type="hidden" name="id_excessao" id="edit_id_excessao" value="0">
                <label>Data Início</label>
                <input type="date" name="data" id="edit_exc_data" required>
                <label>Data Fim <small>(opcional — para recorrência)</small></label>
                <input type="date" name="data_termino" id="edit_exc_data_termino">
                <label>Horário</label>
                <div style="display:flex; gap:5px;">
                    <input type="time" name="hora_inicio" id="edit_exc_inicio" required>
                    <input type="time" name="hora_final" id="edit_exc_final" required>
                </div>
                <label>Dias da Semana <small>(deixe em branco para data única)</small></label>
                <div class="checkbox-grid">
                    <label><input type="checkbox" name="segunda" id="exc_chk_seg" value="1"> SEG</label>
                    <label><input type="checkbox" name="terca" id="exc_chk_ter" value="1"> TER</label>
                    <label><input type="checkbox" name="quarta" id="exc_chk_qua" value="1"> QUA</label>
                    <label><input type="checkbox" name="quinta" id="exc_chk_qui" value="1"> QUI</label>
                    <label><input type="checkbox" name="sexta" id="exc_chk_sex" value="1"> SEX</label>
                    <label><input type="checkbox" name="sabado" id="exc_chk_sab" value="1"> SÁB</label>
                    <label><input type="checkbox" name="domingo" id="exc_chk_dom" value="1"> DOM</label>
                </div>
                <button type="submit" style="width: 100%;">Confirmar Bloqueio</button>
            </form>
        </div>
    </div>

    <!-- Modal Detalhes do Evento -->
    <div id="modalEvento" class="modal">
        <div class="modal-content" style="max-width:420px;">
            <div class="modal-header">
                <h2>Detalhes</h2>
                <span class="close-modal" onclick="fecharModais()">&times;</span>
            </div>
            <div id="eventoInfo" style="line-height:1.8; padding:10px 0;"></div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
<script>
    let calendar;
    const agendaAtual = <?php echo json_encode($agenda_selecionada, JSON_HEX_TAG | JSON_HEX_AMP); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'pt-br',
            allDaySlot: false,
            height: 600,
            expandRows: true,
            handleWindowResize: true,
            headerToolbar: { 
                left: 'prev,next today', 
                center: 'title', 
                right: 'multiMonthYear,dayGridMonth,timeGridWeek,timeGridDay' 
            },
            views: {
                dayGridMonth: {
                    dayMaxEventEntries: 3
                }
            },
            eventDisplay: 'block',
            events: <?php echo json_encode($eventos); ?>,
            eventClick: function(info) {
                const props = info.event.extendedProps;
                const start = info.event.start;
                const end = info.event.end;
                const tipoIcon = props.tipo === 'disponivel' ? 'Livre' : 'Bloq';
                const tipoLabel = props.tipo === 'disponivel' ? 'Disponivel' : 'Bloqueado';
                let html = `
                    <p><strong>Tipo:</strong> ${tipoIcon} ${tipoLabel}</p>
                    <p><strong>Data:</strong> ${start.toLocaleDateString('pt-BR')}</p>
                    <p><strong>Horário:</strong> ${start.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'})} — ${end.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'})}</p>
                    <p><strong>Origem:</strong> ${props.origem || '—'}</p>`;
                if (props.recorrente) {
                    html += `<p><strong>Recorrência:</strong> ${props.dias || '—'}</p>`;
                }
                document.getElementById('eventoInfo').innerHTML = html;
                document.getElementById('modalEvento').style.display = 'block';
            },
            eventDidMount: function(info) {
                const props = info.event.extendedProps;
                info.el.title = `${props.tipo === 'disponivel' ? 'Disponível' : 'Bloqueado'} — ${props.origem || ''}`;
            }
        });
        calendar.render();
        
        renderizarTabelas(<?php echo $json_rotinas; ?>, <?php echo $json_excessoes; ?>, <?php echo $json_excessoes_raw; ?>);
    });

    function abrirModalRotina(dados = null) {
        const form = document.getElementById('formRotina');
        form.reset();
        document.getElementById('edit_id_rotina').value = "0";
        document.getElementById('modalRotinaTitle').innerText = "Nova Rotina";

        if (dados) {
            document.getElementById('modalRotinaTitle').innerText = "Editar Rotina";
            document.getElementById('edit_id_rotina').value = dados.id;
            // Preencher campos (precisamos converter dd-mm-yyyy para yyyy-mm-dd para o input date)
            document.getElementById('edit_data_inicio').value = converterData(dados.data_inicio);
            document.getElementById('edit_data_final').value = converterData(dados.data_final);
            document.getElementById('edit_hora_inicio').value = dados.hora_inicio;
            document.getElementById('edit_hora_final').value = dados.hora_final;
            document.getElementById('edit_duracao').value = dados.duracao;
            document.getElementById('edit_intervalo_sessoes').value = dados.intervalo_sessoes || 0;

            // Checkboxes
            document.getElementById('chk_seg').checked = dados.dias_semana.includes('SEG');
            document.getElementById('chk_ter').checked = dados.dias_semana.includes('TER');
            document.getElementById('chk_qua').checked = dados.dias_semana.includes('QUA');
            document.getElementById('chk_qui').checked = dados.dias_semana.includes('QUI');
            document.getElementById('chk_sex').checked = dados.dias_semana.includes('SEX');
            document.getElementById('chk_sab').checked = dados.dias_semana.includes('SÁB');
            document.getElementById('chk_dom').checked = dados.dias_semana.includes('DOM');

            // Color
            if (dados.cor) {
                document.getElementById('cor_rotina').value = dados.cor;
                document.querySelectorAll('.color-swatch').forEach(el => {
                    el.classList.toggle('selected', el.dataset.color === dados.cor);
                });
            }
        }
        document.getElementById('modalRotina').style.display = 'block';
    }

    // Color picker
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.color-swatch').forEach(el => {
            el.addEventListener('click', function() {
                document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('cor_rotina').value = this.dataset.color;
            });
        });
        // Select default
        const defSwatch = document.querySelector('.color-swatch[data-color="#3465a4"]');
        if (defSwatch) defSwatch.classList.add('selected');
    });

    function converterData(d) { return d.split('-').reverse().join('-'); }

    function abrirModalExcessao(dados = null) {
        const form = document.getElementById('formExcessao');
        form.reset();
        document.getElementById('edit_id_excessao').value = "0";
        document.getElementById('modalExcessaoTitle').innerText = "Novo Bloqueio / Exceção";

        if (dados) {
            document.getElementById('modalExcessaoTitle').innerText = "Editar Bloqueio / Exceção";
            document.getElementById('edit_id_excessao').value = dados.id;
            document.getElementById('edit_exc_data').value = dados.data;
            document.getElementById('edit_exc_data_termino').value = dados.data_termino || '';
            document.getElementById('edit_exc_inicio').value = dados.hora_inicio;
            document.getElementById('edit_exc_final').value = dados.hora_final;
            document.getElementById('exc_chk_seg').checked = dados.segunda == 1;
            document.getElementById('exc_chk_ter').checked = dados.terca == 1;
            document.getElementById('exc_chk_qua').checked = dados.quarta == 1;
            document.getElementById('exc_chk_qui').checked = dados.quinta == 1;
            document.getElementById('exc_chk_sex').checked = dados.sexta == 1;
            document.getElementById('exc_chk_sab').checked = dados.sabado == 1;
            document.getElementById('exc_chk_dom').checked = dados.domingo == 1;
        }
        document.getElementById('modalExcessao').style.display = 'block';
    }

    function fecharModais() { document.querySelectorAll('.modal').forEach(m => m.style.display = 'none'); }

    async function enviarFormulario(event, url) {
        event.preventDefault();
        const formData = new FormData(event.target);
        
        try {
            const response = await fetch(url, { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                fecharModais();
                await recarregarDados();
            } else if (result.conflict) {
                if (confirm(result.message)) {
                    formData.set('force', '1');
                    const retry = await fetch(url, { method: 'POST', body: formData });
                    const retryResult = await retry.json();
                    if (retryResult.success) {
                        fecharModais();
                        await recarregarDados();
                    } else {
                        alert(retryResult.message || 'Erro ao salvar.');
                    }
                }
            } else if (result.message) {
                alert(result.message);
            }
        } catch (error) {
            console.error("Erro ao salvar:", error);
        }
    }

    async function recarregarDados() {
        if (!agendaAtual) return;
        
        const params = new URLSearchParams({'agenda': agendaAtual});
        const response = await fetch(`configurar_agenda.php?ajax_load=1`, {
            method: 'POST',
            body: params
        });
        
        const data = await response.json();
        
        calendar.removeAllEvents();
        calendar.addEventSource(data.eventos);
        
        renderizarTabelas(data.rotinas, data.excessoes, data.excessoes_raw);
    }

    window.rotinasAtuais = [];
    window.excessoesAtuais = [];
    function renderizarTabelas(rotinas, excessoes, excessoesRaw) {
        window.rotinasAtuais = rotinas;
        window.excessoesAtuais = excessoesRaw || [];
        let htmlRotinas = '';
        rotinas.forEach((row, index) => {
            htmlRotinas += `<tr>
                <td><strong>#${row.id}</strong></td>
                <td>${row.data_inicio}</td><td>${row.data_final}</td>
                <td>${row.hora_inicio} - ${row.hora_final}</td>
                <td>${row.dias_semana}</td>
                <td class="btn-group">
                    <button onclick="abrirModalRotina(window.rotinasAtuais[${index}])">Editar</button>
                    <button class="btn-delete" onclick="excluirItem(${row.id}, 'rotina')">Excluir</button>
                </td>
            </tr>`;
        });
        document.getElementById('tabela_rotinas').innerHTML = htmlRotinas || '<tr><td colspan="6">Nenhuma rotina cadastrada.</td></tr>';

        let htmlExcessoes = '';
        excessoes.forEach((row, index) => {
            const dataFim = row.data_termino || '—';
            const dias = row.dias_semana || '—';
            htmlExcessoes += `<tr>
                <td><strong>#${row.id}</strong></td>
                <td>${row.data}</td>
                <td>${dataFim}</td>
                <td>${dias}</td>
                <td>${row.inicio} - ${row.final}</td>
                <td class="btn-group">
                    <button onclick="abrirModalExcessao(window.excessoesAtuais[${index}])">Editar</button>
                    <button class="btn-delete" onclick="excluirItem(${row.id}, 'excessao')">Excluir</button>
                </td>
            </tr>`;
        });
        document.getElementById('tabela_excessoes').innerHTML = htmlExcessoes || '<tr><td colspan="6">Nenhuma exceção cadastrada.</td></tr>';
    }

    async function excluirItem(id, tipo) {
        if(!confirm(`Excluir esta ${tipo}?`)) return;
        const url = tipo === 'rotina' ? `excluir_rotina.php?id=${id}` : `excluir_excessao.php?id=${id}`;
        try {
            const response = await fetch(url);
            const result = await response.json();
            if (result.success) await recarregarDados();
        } catch (error) {
            console.error("Erro ao excluir:", error);
        }
    }

    function copiarLink() {
        const input = document.getElementById('publicLink');
        if (!input) return;
        navigator.clipboard.writeText(input.value).then(() => {
            const btn = input.nextElementSibling;
            const txt = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(() => btn.textContent = txt, 1500);
        }).catch(() => {
            input.select();
            document.execCommand('copy');
        });
    }

    window.onclick = function(event) {
        if (event.target.className === 'modal') fecharModais();
    }
</script>
<div style="text-align:center; padding:6px 20px; font-size:0.7rem; color:#aaa; border-top:1px solid #eee; clear:both; margin-top:20px;">
    Facilite &mdash; 2026
</div>
</body>
</html>