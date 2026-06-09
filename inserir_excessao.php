<?php
    require_once('protect.php');
    include('conexao.php');
    header('Content-Type: application/json');

    verificarCSRF();

    $id_agenda = (int)$_POST['id_agenda'];

    if (!usuarioDono($mysqli, 'excessoes', $id_agenda, 'id_agenda')) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $id_excessao = isset($_POST['id_excessao']) ? (int)$_POST['id_excessao'] : 0;

    $data = (new DateTime($_POST['data']))->format('Y-m-d');
    $data_termino = !empty($_POST['data_termino'])
        ? (new DateTime($_POST['data_termino']))->format('Y-m-d')
        : null;

    $hora_inicio = (new DateTime($_POST['hora_inicio']))->format('H:i:s');
    $hora_termino = (new DateTime($_POST['hora_final']))->format('H:i:s');

    $domingo  = (int)($_POST['domingo'] ?? 0);
    $segunda  = (int)($_POST['segunda'] ?? 0);
    $terca    = (int)($_POST['terca'] ?? 0);
    $quarta   = (int)($_POST['quarta'] ?? 0);
    $quinta   = (int)($_POST['quinta'] ?? 0);
    $sexta    = (int)($_POST['sexta'] ?? 0);
    $sabado   = (int)($_POST['sabado'] ?? 0);

    if ($id_excessao > 0) {
        $stmt = $mysqli->prepare("UPDATE excessoes SET
                data=?, data_termino=?,
                hora_inicio=?, hora_termino=?,
                domingo=?, segunda=?, terca=?,
                quarta=?, quinta=?, sexta=?, sabado=?
                WHERE id=?");
        $stmt->bind_param('ssssiiiiiiii',
            $data, $data_termino,
            $hora_inicio, $hora_termino,
            $domingo, $segunda, $terca,
            $quarta, $quinta, $sexta, $sabado,
            $id_excessao
        );
    } else {
        $stmt = $mysqli->prepare("INSERT INTO excessoes
                (id_agenda,data,data_termino,hora_inicio,hora_termino,
                 domingo,segunda,terca,quarta,quinta,sexta,sabado)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issssiiiiiiii',
            $id_agenda,
            $data, $data_termino,
            $hora_inicio, $hora_termino,
            $domingo, $segunda, $terca,
            $quarta, $quinta, $sexta, $sabado
        );
    }

    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
