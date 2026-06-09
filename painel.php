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
            $tamanho_max = 2 * 1024 * 1024; // 2MB
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
        $agenda_selecionada = $novo_servico;
        $id_final = $id_ac;
        // Re-fetch agenda config so sidebar displays updated values immediately
        $refresh = $mysqli->query("SELECT * FROM agenda WHERE id='$id_ac'");
        if ($refresh) $agenda_config = $refresh->fetch_assoc();
    }

    if (isset($_POST['nova_agenda'])) {
        verificarCSRF();
        $novo_servico = $mysqli->real_escape_string(trim($_POST['novo_servico_nome']));
        if (strlen($novo_servico) > 0) {
            $check = $mysqli->query("SELECT id FROM agenda WHERE servico='$novo_servico' AND id_user='$id_usuario'");
            if ($check && $check->num_rows == 0) {
                $mysqli->query("INSERT INTO agenda (id_user, servico) VALUES ('$id_usuario', '$novo_servico')");
                $agenda_selecionada = $novo_servico;
                $id_final = $mysqli->insert_id;
                // Re-fetch agenda config for the new agenda
                $refresh = $mysqli->query("SELECT * FROM agenda WHERE id='$id_final'");
                if ($refresh) $agenda_config = $refresh->fetch_assoc();
            }
        }
    }

    if(isset($_POST['agenda'])){
        verificarCSRF();
        if(strlen(trim($_POST['agenda'])) == 0) {
            echo "Selecione uma agenda";
        } else {
            $agenda_selecionada = $mysqli->real_escape_string($_POST['agenda']);
            $stmt_sel = $mysqli->prepare("SELECT * FROM agenda WHERE servico = ?");
            $stmt_sel->bind_param('s', $agenda_selecionada);
            $stmt_sel->execute();
            $query_id = $stmt_sel->get_result();
            
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

            $tipo_exc = $resposta['tipo'] ?? 'bloqueado';

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
                            'tipo'  => $tipo_exc,
                        ];
                        if ($tipo_exc === 'reservado') {
                            $titulo = 'Reservado';
                            $bg = '#fff3cd';
                            $border = '#f0b400';
                            $color = '#856404';
                        } else {
                            $titulo = 'Bloqueado';
                            $bg = '#fce8e6';
                            $border = '#d93025';
                            $color = '#c5221f';
                        }
                        $eventos[] = [
                            'title'           => $titulo,
                            'start'           => $dia->format('Y-m-d').'T'.$hora_inicio_exc->format('H:i:s'),
                            'end'             => $dia->format('Y-m-d').'T'.$hora_final_exc->format('H:i:s'),
                            'backgroundColor' => $bg,
                            'borderColor'     => $border,
                            'textColor'       => $color,
                            'classNames'      => ['event-blocked'],
                            'extendedProps'   => [
                                'tipo'     => $tipo_exc,
                                'origem'   => 'Exceção #'.$resposta['id'],
                                'recorrente' => true,
                                'dias'     => trim($dias_semana_str),
                            ],
                        ];
                    }
                }

                if ($tipo_exc !== 'reservado') {
                    $lista_excessoes_formatada[] = [
                        'id'          => $resposta['id'],
                        'data'        => $data_inicio_exc->format('d-m-Y'),
                        'data_termino'=> $data_fim_exc->format('d-m-Y'),
                        'inicio'      => $hora_inicio_exc->format('H:i'),
                        'final'       => $hora_final_exc->format('H:i'),
                        'dias_semana' => trim($dias_semana_str),
                        'recorrente'  => true,
                    ];
                }
            } else {
                $data_exc = new DateTime($resposta['data']);
                $data_time = $data_exc->format('Y-m-d');

                $lista_excessoes[] = [
                    'id'    => $resposta['id'],
                    'data'  => $data_time,
                    'inicio'=> $hora_inicio_exc->format('H:i:s'),
                    'final' => $hora_final_exc->format('H:i:s'),
                    'tipo'  => $tipo_exc,
                ];
                if ($tipo_exc === 'reservado') {
                    $titulo = 'Reservado';
                    $bg = '#fff3cd';
                    $border = '#f0b400';
                    $color = '#856404';
                } else {
                    $titulo = 'Bloqueado';
                    $bg = '#fce8e6';
                    $border = '#d93025';
                    $color = '#c5221f';
                }
                $eventos[] = [
                    'title'           => $titulo,
                    'start'           => $data_time.'T'.$hora_inicio_exc->format('H:i:s'),
                    'end'             => $data_time.'T'.$hora_final_exc->format('H:i:s'),
                    'backgroundColor' => $bg,
                    'borderColor'     => $border,
                    'textColor'       => $color,
                    'classNames'      => ['event-blocked'],
                    'extendedProps'   => [
                        'tipo'       => $tipo_exc,
                        'origem'     => 'Exceção #'.$resposta['id'],
                        'recorrente' => false,
                    ],
                ];
                if ($tipo_exc !== 'reservado') {
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
            }
            if ($tipo_exc !== 'reservado') {
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
                    'tipo' => $tipo_exc,
                ];
            }
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
                'cor' => $cor_rotina
            ];


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

    $agendamentos_lista = [];
    if ($id_final) {
        $q_ag = $mysqli->query("SELECT a.*, ag.servico FROM agendamentos a INNER JOIN agenda ag ON a.id_agenda = ag.id WHERE a.id_agenda = '$id_final' ORDER BY a.data ASC, a.hora_inicio ASC");
        while ($ag = $q_ag->fetch_assoc()) {
            $agendamentos_lista[] = $ag;
        }
    }

    if (isset($_GET['ajax_load'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'eventos' => $eventos,
            'rotinas' => $lista_rotinas,
            'excessoes' => $lista_excessoes_formatada,
            'excessoes_raw' => $lista_excessoes_raw,
            'agendamentos' => $agendamentos_lista,
        ]);
        exit;
    }

    $json_rotinas = json_encode($lista_rotinas);
    $json_excessoes = json_encode($lista_excessoes_formatada);
    $json_excessoes_raw = json_encode($lista_excessoes_raw);
    $json_agendamentos = json_encode($agendamentos_lista);

    $sql = "SELECT agenda.* FROM agenda 
        INNER JOIN users ON agenda.id_user = users.id 
        WHERE users.nome = '$usuario'";

        $resultado = $mysqli->query($sql);

        $listaServicos = [];

        if ($resultado) {
            while ($linha = $resultado->fetch_assoc()) {
                $listaServicos[] = $linha;
            }
        }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Agendamento</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/estilo.css">
    <style>
        * { box-sizing: border-box; }
        body { overflow: hidden; height: 100vh; display: flex; }
        .dashboard { width: 100%; }

        .sidebar { width: 540px; display: flex; flex-direction: column; padding: 0; }
        .sidebar-top { padding: 16px 20px 12px; border-bottom: 1px solid #dcdcdc; }
        .sidebar-tabs { display: flex; border-bottom: 1px solid #dcdcdc; background: #f8f9fa; }
        .sidebar-tab {
            flex: 1; padding: 10px; text-align: center; cursor: pointer;
            font-size: 0.8rem; font-weight: 500; color: #666;
            border-bottom: 2px solid transparent; transition: all 0.15s;
        }
        .sidebar-tab:hover { color: #3465a4; background: #eef3f9; }
        .sidebar-tab.active { color: #3465a4; border-bottom-color: #3465a4; background: #fff; }
        .sidebar-body { flex: 1; overflow-y: auto; padding: 16px 20px; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .sidebar label { font-size: 0.75rem; font-weight: 600; color: #555; margin-bottom: 4px; }
        .sidebar select, .sidebar input[type="text"], .sidebar input[type="file"] {
            width: 100%; padding: 7px 8px; font-size: 0.8rem;
            border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px;
        }
        .sidebar .btn-sm {
            width: 100%; padding: 8px; font-size: 0.8rem; margin: 4px 0;
        }

        .main-content {
            display: flex; flex-direction: column; padding: 16px 20px;
            height: 100vh; overflow: hidden;
        }
        .main-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 8px; flex-shrink: 0;
        }
        .main-header h1 { margin: 0; font-size: 1.2rem; color: #3465a4; }
        .main-header .btn-new-agenda { font-size: 0.8rem; padding: 6px 14px; margin: 0; }

        .tables-grid {
            display: flex; gap: 10px; flex-shrink: 0; margin-bottom: 8px;
        }
        .table-wrap {
            flex: 1; background: #fff; border: 1px solid #dcdcdc;
            border-radius: 6px; overflow: hidden; display: flex; flex-direction: column;
        }
        .table-wrap h3 {
            margin: 0; padding: 6px 10px; font-size: 0.8rem; color: #3465a4;
            background: #f8f9fa; border-bottom: 1px solid #dcdcdc;
        }
        .table-wrap .table-scroll {
            max-height: 130px; overflow-y: auto;
        }
        .table-wrap table { margin: 0; font-size: 0.72rem; border: none; }
        .table-wrap table th { font-size: 0.7rem; padding: 4px 6px; white-space: nowrap; position: sticky; top: 0; }
        .table-wrap table td { padding: 3px 6px; }
        .table-wrap .btn-group button { font-size: 0.65rem; padding: 2px 8px; margin: 0; }
        .status-realizado { background: #e8f5e9 !important; }
        .status-realizado td:first-child { color: #2e7d32; font-weight:600; }
        .status-cancelado { background: #fce8e6 !important; }
        .status-cancelado td { color: #c62828; }
        .status-pendente { background: #fff8e1 !important; }
        .status-pendente td { color: #e65100; }
        .btn-sm-ag { font-size:0.6rem; padding:2px 6px; margin:1px; border:none; border-radius:3px; cursor:pointer; }

        #calendar {
            flex: 1; min-height: 0; background: #fff; border: 1px solid #dcdcdc;
            border-radius: 8px; padding: 10px;
        }
        .fc { font-size: 0.78em; height: 100%; }
        .fc .fc-toolbar-title { font-size: 1em; }
        .fc .fc-button { font-size: 0.75em; padding: 3px 8px; }
        .fc .fc-toolbar { margin-bottom: 6px; }

        .config-section { margin-bottom: 12px; }
        .config-section label { font-size: 0.75rem; font-weight: 600; color: #555; display: block; margin-bottom: 3px; }
        .config-section input { width: 100%; padding: 7px 8px; font-size: 0.8rem; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 8px; }
        .config-section .btn-save { width: 100%; padding: 8px; font-size: 0.8rem; }

        .modal-content { width: 480px; }
        .modal-content label { font-size: 0.78rem; }
        .modal-content input, .modal-content select, .modal-content textarea { font-size: 0.85rem; padding: 8px 10px; border:1px solid #bbb; border-radius:4px; }
        .modal-content .checkbox-grid label { font-size: 0.7rem; }

        .color-swatch.selected { border-color: #333 !important; transform: scale(1.15); }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-top">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="images/logo.jpg" alt="Facilite" height="44" style="height:44px;">
                    <span style="font-size:0.75rem; color:#888; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></span>
                </div>
                <hr style="border:none; border-top:1px solid #dcdcdc; margin:12px 0 10px;">
                <?php if ($id_final): ?>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <?= exibirAvatarProfissional($agenda_config['nome_profissional'] ?? '', $agenda_config['foto_profissional'] ?? '', 76) ?>
                        <div style="flex:1; min-width:0;">
                            <strong style="font-size:0.85rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($agenda_config['servico'] ?? '') ?></strong>
                            <span style="font-size:0.72rem; color:#666; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= !empty($agenda_config['nome_profissional']) ? htmlspecialchars($agenda_config['nome_profissional']) : 'Profissional nao definido' ?>
                            </span>
                            <button onclick="deletarAgenda(<?= $id_final ?>)" title="Excluir agenda" style="margin-top:6px; background:#d93025; color:#fff; border:none; border-radius:4px; padding:4px 12px; cursor:pointer; font-size:0.7rem;">Excluir Agenda</button>
                        </div>
                        <a href="logout.php" style="font-size:0.7rem; flex-shrink:0; align-self:flex-start;">Sair</a>
                    </div>
                <?php else: ?>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:15px;color:#fff;background:#ccc;flex-shrink:0;">?</div>
                        <div>
                            <strong style="font-size:0.85rem;">Selecione uma agenda</strong><br>
                            <a href="logout.php" style="font-size:0.7rem;">Sair</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar-tabs">
                <div class="sidebar-tab active" data-tab="rotinas">Rotinas e Exceções</div>
                <div class="sidebar-tab" data-tab="agendamentos">Agendamentos</div>
                <div class="sidebar-tab" data-tab="config">Configurações</div>
            </div>

            <div class="sidebar-body">
                <!-- Tab: Rotinas e Exceções -->
                <div class="tab-panel active" id="panel-rotinas">
                    <form action="" method="POST" id="formSelecionarAgenda">
                        <?= campoCSRF() ?>
                        <label for="agenda">Selecione a Agenda:</label>
                        <select name="agenda" id="agenda" onchange="this.form.submit()">
                            <option value="" disabled <?php if(!$agenda_selecionada) echo 'selected'; ?>>Selecione...</option>
                            <?php foreach ($listaServicos as $agenda): ?>
                                <option value="<?= htmlspecialchars($agenda['servico']); ?>" <?php if($agenda_selecionada == $agenda['servico']) echo 'selected'; ?>>
                                    <?= htmlspecialchars($agenda['servico']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if($id_final): ?>
                        <button onclick="abrirModalRotina()" class="btn-sm">+ Nova Rotina</button>
                        <button onclick="abrirModalExcessao()" class="btn-sm">+ Novo Bloqueio</button>

                        <?php
                            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                            $public_link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/agendamento/agendar.php?id=' . $id_final;
                        ?>
                        <div style="margin-top:12px; padding:10px; background:#f0f4f8; border-radius:6px;">
                            <label style="font-size:0.7rem; font-weight:600; margin-bottom:3px;">Link Público</label>
                            <p style="font-size:0.7rem; color:#666; margin:0 0 6px;">Compartilhe para clientes agendarem.</p>
                            <div style="display:flex; gap:4px;">
                                <input type="text" id="publicLink" value="<?= htmlspecialchars($public_link) ?>" readonly
                                       style="flex:1; font-size:0.7rem; padding:5px 6px; border:1px solid #ccc; border-radius:3px; background:#fff; margin:0;">
                            <button onclick="copiarLink()" style="padding:4px 8px; font-size:0.7rem; margin:0; white-space:nowrap;">Copiar</button>
                        </div>
                    </div>

                    <a href="exportar_ics.php?id=<?= $id_final ?>" class="btn-sm" style="display:block; text-align:center; text-decoration:none; margin-top:10px; background:#34a853;">Exportar para Google Agenda</a>
                    <?php endif; ?>
                </div>

                <!-- Tab: Agendamentos -->
                <div class="tab-panel" id="panel-agendamentos">
                    <?php if($id_final): ?>
                        <div class="table-scroll" style="max-height:500px;">
                            <table style="width:100%; font-size:0.72rem;">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Data</th>
                                        <th>Horario</th>
                                        <th>Token</th>
                                        <th>Status</th>
                                        <th>Acoes</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela_agendamentos"></tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="font-size:0.8rem; color:#888;">Selecione uma agenda primeiro.</p>
                    <?php endif; ?>
                </div>

                <!-- Tab: Configurações -->
                <div class="tab-panel" id="panel-config">
                    <?php if($id_final): ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="salvar_config" value="1">
                            <input type="hidden" name="id_agenda_config" value="<?= $id_final ?>">
                            <?= campoCSRF() ?>

                            <div class="config-section">
                                <label>Nome do Serviço</label>
                                <input type="text" name="servico_nome" class="config-input" value="<?= htmlspecialchars($agenda_config['servico'] ?? '') ?>">
                            </div>

                            <div class="config-section">
                                <label>Nome do Profissional</label>
                                <input type="text" name="nome_profissional" class="config-input" value="<?= htmlspecialchars($agenda_config['nome_profissional'] ?? '') ?>">
                            </div>

                            <div class="config-section">
                                <label>Chave PIX (para pagamento)</label>
                                <input type="text" name="chave_pix" class="config-input" value="<?= htmlspecialchars($agenda_config['chave_pix'] ?? '') ?>">
                            </div>

                            <div class="config-section">
                                <label>Mensagem de Confirmação <small>(exibida após agendamento)</small></label>
                                <textarea name="mensagem_confirmacao" class="config-input" rows="3" style="resize:vertical; font-family:inherit;"><?= htmlspecialchars($agenda_config['mensagem_confirmacao'] ?? '') ?></textarea>
                            </div>

                            <div class="config-section">
                                <label>Link Personalizado <small>(Google Forms, etc.)</small></label>
                                <input type="url" name="link_confirmacao" class="config-input" value="<?= htmlspecialchars($agenda_config['link_confirmacao'] ?? '') ?>" placeholder="https://forms.google.com/...">
                            </div>

                            <div class="config-section">
                                <label>Valor do Serviço (R$)</label>
                                <input type="text" name="valor" class="config-input" value="<?= htmlspecialchars($agenda_config['valor'] ?? '') ?>" placeholder="Ex: 79,90">
                            </div>

                            <div class="config-section">
                                <label>Foto do Profissional</label>
                                <input type="file" name="foto_profissional" class="config-input-file" accept="image/jpeg,image/png,image/webp">
                                <?php if (!empty($agenda_config['foto_profissional'])): ?>
                                    <div style="margin-top:4px; margin-bottom:6px;">
                                        <?= exibirFotoProfissional($agenda_config['foto_profissional'], 50) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn-save" id="btnSalvarConfig">Salvar Configurações</button>
                        </form>
                    <?php else: ?>
                        <p style="font-size:0.8rem; color:#888;">Selecione uma agenda primeiro.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div id="poll-notification" style="display:none; align-items:center; gap:12px; background:#e8f5e9; border:1px solid #34a853; border-radius:8px; padding:10px 16px; margin-bottom:12px; font-size:0.85rem;">
                <span style="flex:1;" class="poll-msg"></span>
                <button onclick="recarregarDados(); this.parentElement.style.display='none';" style="background:#34a853; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:0.8rem; cursor:pointer; font-family:inherit;">Atualizar</button>
            </div>
            <div class="main-header">
                <h1><?= $agenda_selecionada ? htmlspecialchars($agenda_selecionada) : 'Selecione uma agenda' ?></h1>
                <button onclick="abrirModalNovaAgenda()" class="btn-new-agenda">+ Nova Agenda</button>
            </div>

            <div class="tables-grid">
                <div class="table-wrap">
                    <h3>Rotinas</h3>
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>ID</th><th>Início</th><th>Fim</th><th>Hora</th><th>Dias</th><th>Ações</th></tr></thead>
                            <tbody id="tabela_rotinas"></tbody>
                        </table>
                    </div>
                </div>
                <div class="table-wrap">
                    <h3>Exceções / Bloqueios</h3>
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>ID</th><th>Data</th><th>Fim</th><th>Dias</th><th>Horário</th><th>Ações</th></tr></thead>
                            <tbody id="tabela_excessoes"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id='calendar'></div>
        </div>
    </div>

    <!-- Modal Nova Agenda -->
    <div id="modalNovaAgenda" class="modal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h2>Nova Agenda</h2>
                <span class="close-modal" onclick="fecharModais()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="nova_agenda" value="1">
                <label>Nome do Serviço</label>
                <input type="text" name="novo_servico_nome" required placeholder="Ex: Corte de Cabelo">
                <button type="submit" style="width:100%;">Criar Agenda</button>
            </form>
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
                <?= campoCSRF() ?>

                <label>Período</label>
                <div style="display:flex; gap:5px;">
                    <input type="date" name="data_inicio" id="edit_data_inicio" required style="flex:1; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                    <input type="date" name="data_final" id="edit_data_final" required style="flex:1; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                </div>
                <label>Horário</label>
                <div style="display:flex; gap:5px;">
                    <input type="time" name="hora_inicio" id="edit_hora_inicio" required step="900" style="flex:1; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                    <input type="time" name="hora_final" id="edit_hora_final" required step="900" style="flex:1; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                </div>
                <label>Duração (min)</label>
                <input type="number" name="duracao" id="edit_duracao" value="60" required>
                <label>Dias da Semana</label>
                <div class="checkbox-grid">
                    <label><input type="checkbox" name="segunda" id="chk_seg" value="1"> SEG</label>
                    <label><input type="checkbox" name="terca" id="chk_ter" value="1"> TER</label>
                    <label><input type="checkbox" name="quarta" id="chk_qua" value="1"> QUA</label>
                    <label><input type="checkbox" name="quinta" id="chk_qui" value="1"> QUI</label>
                    <label><input type="checkbox" name="sexta" id="chk_sex" value="1"> SEX</label>
                    <label><input type="checkbox" name="sabado" id="chk_sab" value="1"> SÁB</label>
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
                <?= campoCSRF() ?>
                <label>Data Início</label>
                <input type="date" name="data" id="edit_exc_data" required style="width:100%; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                <label>Data Fim <small>(opcional — para recorrência)</small></label>
                <input type="date" name="data_termino" id="edit_exc_data_termino" style="width:100%; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                <label>Horário</label>
                <div style="display:flex; gap:5px;">
                    <input type="time" name="hora_inicio" id="edit_exc_inicio" required step="900" style="flex:1; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
                    <input type="time" name="hora_final" id="edit_exc_final" required step="900" style="flex:1; padding:8px; font-size:0.85rem; border:1px solid #bbb; border-radius:4px;">
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
    const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';
    const agendaAtual = <?php echo json_encode($agenda_selecionada, JSON_HEX_TAG | JSON_HEX_AMP); ?>;

    // Tab switching
    document.querySelectorAll('.sidebar-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('panel-' + this.dataset.tab).classList.add('active');
            if (this.dataset.tab === 'config') {
                reiniciarMonitorConfig();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'pt-br',
            allDaySlot: false,
            height: '100%',
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
                const tipoIcon = props.tipo === 'disponivel' ? 'Livre' : props.tipo === 'reservado' ? 'Res' : 'Bloq';
                const tipoLabel = props.tipo === 'disponivel' ? 'Disponivel' : props.tipo === 'reservado' ? 'Reservado' : 'Bloqueado';
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
                const label = props.tipo === 'disponivel' ? 'Disponivel' : props.tipo === 'reservado' ? 'Reservado' : 'Bloqueado';
                info.el.title = `${label} — ${props.origem || ''}`;
            }
        });
        calendar.render();

        renderizarTabelas(<?php echo $json_rotinas; ?>, <?php echo $json_excessoes; ?>, <?php echo $json_excessoes_raw; ?>, <?php echo $json_agendamentos; ?>);

        // Polling para novos agendamentos
        let ultimoIdAgendamento = 0;
        const agendaIdPoll = <?= (int)($id_final ?? 0) ?>;
        if (agendaIdPoll) {
            // Get initial max id
            const linhas = <?php echo $json_agendamentos; ?>;
            for (const ag of linhas) {
                if (ag.id > ultimoIdAgendamento) ultimoIdAgendamento = ag.id;
            }
            const pollNotification = document.getElementById('poll-notification');
            setInterval(async function() {
                try {
                    const resp = await fetch(`verificar_novos_agendamentos.php?agenda_id=${agendaIdPoll}&ultimo_id=${ultimoIdAgendamento}`);
                    const data = await resp.json();
                    if (data.success && data.total > 0) {
                        ultimoIdAgendamento = data.novos[data.total - 1].id;
                        if (pollNotification) {
                            pollNotification.style.display = 'flex';
                            pollNotification.querySelector('.poll-msg').textContent = data.total + ' novo(s) agendamento(s) recebido(s)!';
                        }
                    }
                } catch (e) {
                    // silent
                }
            }, 15000);
        }
    });

    function abrirModalNovaAgenda() {
        document.getElementById('modalNovaAgenda').style.display = 'block';
    }

    function abrirModalRotina(dados = null) {
        const form = document.getElementById('formRotina');
        form.reset();
        document.getElementById('edit_id_rotina').value = "0";
        document.getElementById('modalRotinaTitle').innerText = "Nova Rotina";

        if (dados) {
            document.getElementById('modalRotinaTitle').innerText = "Editar Rotina";
            document.getElementById('edit_id_rotina').value = dados.id;
            document.getElementById('edit_data_inicio').value = converterData(dados.data_inicio);
            document.getElementById('edit_data_final').value = converterData(dados.data_final);
            document.getElementById('edit_hora_inicio').value = dados.hora_inicio;
            document.getElementById('edit_hora_final').value = dados.hora_final;
            document.getElementById('edit_duracao').value = dados.duracao;

            document.getElementById('chk_seg').checked = dados.dias_semana.includes('SEG');
            document.getElementById('chk_ter').checked = dados.dias_semana.includes('TER');
            document.getElementById('chk_qua').checked = dados.dias_semana.includes('QUA');
            document.getElementById('chk_qui').checked = dados.dias_semana.includes('QUI');
            document.getElementById('chk_sex').checked = dados.dias_semana.includes('SEX');
            document.getElementById('chk_sab').checked = dados.dias_semana.includes('SÁB');
            document.getElementById('chk_dom').checked = dados.dias_semana.includes('DOM');

            if (dados.cor) {
                document.getElementById('cor_rotina').value = dados.cor;
                document.querySelectorAll('.color-swatch').forEach(el => {
                    el.classList.toggle('selected', el.dataset.color === dados.cor);
                });
            }
        }
        document.getElementById('modalRotina').style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.color-swatch').forEach(el => {
            el.addEventListener('click', function() {
                document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('cor_rotina').value = this.dataset.color;
            });
        });
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

        const params = new URLSearchParams();
        params.append('agenda', agendaAtual);
        params.append('_csrf_token', csrfToken);
        const response = await fetch(`painel.php?ajax_load=1`, {
            method: 'POST',
            body: params
        });

        const data = await response.json();

        calendar.removeAllEvents();
        calendar.addEventSource(data.eventos);

        renderizarTabelas(data.rotinas, data.excessoes, data.excessoes_raw, data.agendamentos);
    }

    window.rotinasAtuais = [];
    window.excessoesAtuais = [];
    function renderizarTabelas(rotinas, excessoes, excessoesRaw, agendamentos) {
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
        document.getElementById('tabela_rotinas').innerHTML = htmlRotinas || '<tr><td colspan="6" style="text-align:center;color:#888;">Nenhuma rotina cadastrada.</td></tr>';

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
        document.getElementById('tabela_excessoes').innerHTML = htmlExcessoes || '<tr><td colspan="6" style="text-align:center;color:#888;">Nenhuma exceção cadastrada.</td></tr>';

        let htmlAg = '';
        (agendamentos || []).forEach(function(ag) {
            const d = new Date(ag.data + 'T12:00:00');
            const dataFmt = d.toLocaleDateString('pt-BR');
            const statusClass = ag.status === 'realizado' ? 'status-realizado' : ag.status === 'cancelado' ? 'status-cancelado' : ag.status === 'pendente' ? 'status-pendente' : 'status-confirmado';
            const statusLabel = ag.status === 'realizado' ? 'Realizado' : ag.status === 'cancelado' ? 'Cancelado' : ag.status === 'pendente' ? 'Pendente' : 'Confirmado';
            htmlAg += `<tr class="${statusClass}">
                <td>${ag.cliente_nome || '—'}</td>
                <td>${dataFmt}</td>
                <td>${ag.hora_inicio ? ag.hora_inicio.substring(0,5) : '—'} - ${ag.hora_fim ? ag.hora_fim.substring(0,5) : '—'}</td>
                <td style="font-family:monospace; font-size:0.65rem;">${ag.token || '—'}</td>
                <td style="font-size:0.7rem;"><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-weight:600; color:#fff; background:${statusClass === 'status-confirmado' ? '#34a853' : statusClass === 'status-realizado' ? '#1a73e8' : statusClass === 'status-pendente' ? '#e65100' : '#c62828'};">${statusLabel}</span></td>
                <td style="white-space:nowrap;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px;">
                        ${ag.status === 'pendente' ? `<button class="btn-sm-ag" onclick="acaoAgendamento(${ag.id},'confirmar')" style="background:#34a853;color:#fff;width:26px;height:26px;padding:0;border:none;border-radius:4px;font-size:12px;cursor:pointer;" title="Confirmar Pagamento"><i class="fas fa-thumbs-up"></i></button>` : ''}
                        ${ag.status === 'pendente' ? `<button class="btn-sm-ag" onclick="acaoAgendamento(${ag.id},'cancelar')" style="background:#d93025;color:#fff;width:26px;height:26px;padding:0;border:none;border-radius:4px;font-size:12px;cursor:pointer;" title="Recusar"><i class="fas fa-hand"></i></button>` : ''}
                        ${ag.status !== 'realizado' && ag.status !== 'pendente' ? `<button class="btn-sm-ag" onclick="acaoAgendamento(${ag.id},'realizar')" style="background:#34a853;color:#fff;width:26px;height:26px;padding:0;border:none;border-radius:4px;font-size:12px;cursor:pointer;" title="Marcar como Realizado"><i class="fas fa-check"></i></button>` : ''}
                        ${ag.status !== 'cancelado' && ag.status !== 'pendente' ? `<button class="btn-sm-ag" onclick="acaoAgendamento(${ag.id},'cancelar')" style="background:#f0b400;color:#fff;width:26px;height:26px;padding:0;border:none;border-radius:4px;font-size:12px;cursor:pointer;" title="Cancelar Agendamento"><i class="fas fa-ban"></i></button>` : ''}
                        ${ag.status === 'realizado' || ag.status === 'cancelado' ? `<button class="btn-sm-ag" onclick="acaoAgendamento(${ag.id},'reverter')" style="background:#3465a4;color:#fff;width:26px;height:26px;padding:0;border:none;border-radius:4px;font-size:12px;cursor:pointer;" title="Reverter"><i class="fas fa-undo"></i></button>` : ''}
                        <button class="btn-sm-ag" onclick="acaoAgendamento(${ag.id},'apagar')" style="background:#d93025;color:#fff;width:26px;height:26px;padding:0;border:none;border-radius:4px;font-size:12px;cursor:pointer;" title="Apagar Agendamento"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        });
        document.getElementById('tabela_agendamentos').innerHTML = htmlAg || '<tr><td colspan="6" style="text-align:center;color:#888;">Nenhum agendamento.</td></tr>';
    }

    async function excluirItem(id, tipo) {
        if(!confirm(`Excluir esta ${tipo}?`)) return;
        const params = new URLSearchParams();
        params.append('id', id);
        params.append('_csrf_token', csrfToken);
        const url = tipo === 'rotina' ? 'excluir_rotina.php' : 'excluir_excessao.php';
        try {
            const response = await fetch(url, { method: 'POST', body: params });
            const result = await response.json();
            if (result.success) await recarregarDados();
        } catch (error) {
            console.error("Erro ao excluir:", error);
        }
    }

    async function acaoAgendamento(id, acao) {
        const msgs = { realizar: 'Marcar como realizado?', cancelar: 'Cancelar agendamento?', apagar: 'Apagar agendamento? O horario sera liberado.', confirmar: 'Confirmar pagamento? O horario sera reservado.' };
        if (!confirm(msgs[acao])) return;

        const params = new URLSearchParams();
        params.append('id', id);
        params.append('acao', acao);
        params.append('_csrf_token', csrfToken);

        try {
            const response = await fetch('acao_agendamento.php', { method: 'POST', body: params });
            const result = await response.json();
            if (result.success) {
                await recarregarDados();
            } else {
                alert(result.message || 'Erro ao executar acao.');
            }
        } catch (error) {
            console.error("Erro:", error);
        }
    }

    async function deletarAgenda(id) {
        if (!confirm('Tem certeza que deseja excluir esta agenda? Todos os dados (rotinas, excecoes, agendamentos) serao perdidos.')) return;

        const params = new URLSearchParams();
        params.append('id', id);
        params.append('_csrf_token', csrfToken);

        try {
            const response = await fetch('excluir_agenda.php', { method: 'POST', body: params });
            const result = await response.json();
            if (result.success) {
                window.location.href = 'painel.php';
            } else {
                alert(result.message || 'Erro ao excluir agenda.');
            }
        } catch (error) {
            console.error("Erro:", error);
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

    function verificarMudancasConfig() {
        var altered = false;
        document.querySelectorAll('.config-input').forEach(function(el) {
            if (el.value !== el.dataset.initial) altered = true;
        });
        var cfgInput = document.querySelector('.config-input-file');
        if (cfgInput && cfgInput.files.length > 0) altered = true;
        var btn = document.getElementById('btnSalvarConfig');
        if (btn) {
            btn.disabled = !altered;
            btn.style.background = altered ? '#34a853' : '#ccc';
            btn.style.color = altered ? '#fff' : '#888';
            btn.style.cursor = altered ? 'pointer' : 'not-allowed';
        }
    }

    function reiniciarMonitorConfig() {
        document.querySelectorAll('.config-input').forEach(function(el) {
            el.dataset.initial = el.value;
            el.removeEventListener('input', verificarMudancasConfig);
            el.removeEventListener('change', verificarMudancasConfig);
            el.addEventListener('input', verificarMudancasConfig);
            el.addEventListener('change', verificarMudancasConfig);
        });
        var cfgFileInput = document.querySelector('.config-input-file');
        if (cfgFileInput) {
            cfgFileInput.removeEventListener('change', verificarMudancasConfig);
            cfgFileInput.addEventListener('change', verificarMudancasConfig);
        }
        verificarMudancasConfig();
    }
    reiniciarMonitorConfig();
</script>
<div style="text-align:center; padding:6px 20px; font-size:0.7rem; color:#aaa; border-top:1px solid #eee; background:#fff; position:fixed; bottom:0; left:540px; right:0;">
    Facilite &mdash; 2026
</div>
</body>
</html>
