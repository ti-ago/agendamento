<?php
require_once('protect.php');
require_once('conexao.php');
require_once('includes/helpers.php');

$id_agenda = (int)($_GET['id'] ?? 0);
if (!$id_agenda) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Agenda nao especificada.');
}

$id_usuario = (int)$_SESSION['id'];
$stmt = $mysqli->prepare("SELECT servico, nome_profissional FROM agenda WHERE id = ? AND id_user = ?");
$stmt->bind_param('ii', $id_agenda, $id_usuario);
$stmt->execute();
$agenda = $stmt->get_result()->fetch_assoc();
if (!$agenda) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Agenda nao encontrada.');
}

// Carregar todas as excessoes (bloqueios)
$bloqueios = [];
$stmt = $mysqli->prepare("SELECT * FROM excessoes WHERE id_agenda = ?");
$stmt->bind_param('i', $id_agenda);
$stmt->execute();
$exc = $stmt->get_result();
while ($e = $exc->fetch_assoc()) {
    $is_recurring = $e['domingo'] || $e['segunda'] || $e['terca'] || $e['quarta'] || $e['quinta'] || $e['sexta'] || $e['sabado'];

    if ($is_recurring && !empty($e['data_termino'])) {
        $dias_semana = [];
        if ($e['domingo']) $dias_semana[] = 0;
        if ($e['segunda']) $dias_semana[] = 1;
        if ($e['terca']) $dias_semana[] = 2;
        if ($e['quarta']) $dias_semana[] = 3;
        if ($e['quinta']) $dias_semana[] = 4;
        if ($e['sexta']) $dias_semana[] = 5;
        if ($e['sabado']) $dias_semana[] = 6;

        $d_ini = new DateTime($e['data']);
        $d_fim = new DateTime($e['data_termino']);
        $diff = $d_ini->diff($d_fim)->days;

        for ($i = 0; $i <= $diff; $i++) {
            $d = clone $d_ini;
            $d->modify("+$i days");
            if (!in_array((int)$d->format('w'), $dias_semana)) continue;

            $h_i = new DateTime($e['hora_inicio']);
            $h_f = new DateTime($e['hora_termino']);
            $dt_i = clone $d;
            $dt_i->setTime((int)$h_i->format('H'), (int)$h_i->format('i'));
            $dt_f = clone $d;
            $dt_f->setTime((int)$h_f->format('H'), (int)$h_f->format('i'));

            $chave = $dt_i->format('Y-m-d');
            $bloqueios[$chave][] = ['inicio' => $dt_i, 'fim' => $dt_f];
        }
    } else {
        $d = new DateTime($e['data']);
        $h_i = new DateTime($e['hora_inicio']);
        $h_f = new DateTime($e['hora_termino']);
        $dt_i = clone $d;
        $dt_i->setTime((int)$h_i->format('H'), (int)$h_i->format('i'));
        $dt_f = clone $d;
        $dt_f->setTime((int)$h_f->format('H'), (int)$h_f->format('i'));

        $chave = $dt_i->format('Y-m-d');
        $bloqueios[$chave][] = ['inicio' => $dt_i, 'fim' => $dt_f];
    }
}

function estaBloqueado(DateTime $inicio, DateTime $fim, array $bloqueios): bool {
    $chave = $inicio->format('Y-m-d');
    if (!isset($bloqueios[$chave])) return false;
    foreach ($bloqueios[$chave] as $b) {
        if ($inicio < $b['fim'] && $fim > $b['inicio']) return true;
    }
    return false;
}

$eventos = [];

$stmt = $mysqli->prepare("SELECT * FROM rotinas WHERE id_agenda = ?");
$stmt->bind_param('i', $id_agenda);
$stmt->execute();
$rotinas = $stmt->get_result();
while ($r = $rotinas->fetch_assoc()) {
    $data_inicio = new DateTime($r['data_inicio']);
    $data_final = new DateTime($r['data_termino']);
    $hora_inicio = new DateTime($r['hora_inicio']);
    $hora_final = new DateTime($r['hora_termino']);
    $intervalo_horas = $hora_inicio->diff($hora_final);
    $intervalo_minutos = $intervalo_horas->h * 60 + $intervalo_horas->i;
    $duracao = (int)$r['duracao'];
    $qtd = $duracao > 0 ? (int)(($intervalo_minutos - $duracao) / $duracao) : -1;
    $intervalo_dias = $data_inicio->diff($data_final)->days;

    $dias_semana = [];
    if ($r['domingo']) $dias_semana[] = 0;
    if ($r['segunda']) $dias_semana[] = 1;
    if ($r['terca']) $dias_semana[] = 2;
    if ($r['quarta']) $dias_semana[] = 3;
    if ($r['quinta']) $dias_semana[] = 4;
    if ($r['sexta']) $dias_semana[] = 5;
    if ($r['sabado']) $dias_semana[] = 6;

    for ($i = 0; $i <= $intervalo_dias; $i++) {
        $dia = clone $data_inicio;
        $dia->modify("+$i days");
        if (!in_array((int)$dia->format('w'), $dias_semana)) continue;

        for ($j = 0; $j <= $qtd; $j++) {
            $h_ini = clone $hora_inicio;
            $h_ini->modify('+' . ($duracao * $j) . ' minutes');
            $h_fim = clone $h_ini;
            $h_fim->modify("+$duracao minutes");

            $dt_inicio = clone $dia;
            $dt_inicio->setTime((int)$h_ini->format('H'), (int)$h_ini->format('i'));
            $dt_fim = clone $dia;
            $dt_fim->setTime((int)$h_fim->format('H'), (int)$h_fim->format('i'));

            if (estaBloqueado($dt_inicio, $dt_fim, $bloqueios)) continue;

            $eventos[] = [
                'uid' => 'rotina-' . $r['id'] . '-' . $dt_inicio->format('YmdHis'),
                'dtstart' => $dt_inicio->format('Ymd\THis'),
                'dtend' => $dt_fim->format('Ymd\THis'),
                'summary' => ($agenda['servico'] ?? 'Servico') . ' - ' . ($agenda['nome_profissional'] ?? 'Profissional'),
            ];
        }
    }
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="agenda-' . $id_agenda . '.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Facilite//Agenda//PT\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:" . ($agenda['servico'] ?? 'Agenda') . "\r\n";
echo "X-WR-TIMEZONE:America/Sao_Paulo\r\n";

foreach ($eventos as $ev) {
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $ev['uid'] . "@facilite\r\n";
    echo "DTSTART:" . $ev['dtstart'] . "\r\n";
    echo "DTEND:" . $ev['dtend'] . "\r\n";
    echo "SUMMARY:" . $ev['summary'] . "\r\n";
    echo "TRANSP:OPAQUE\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
