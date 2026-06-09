<?php
require_once('conexao.php');
require_once('includes/helpers.php');

$id_agenda = (int)($_GET['id'] ?? 0);
if (!$id_agenda) {
    die('<h1>Agenda não especificada.</h1>');
}

$stmt_agenda = $mysqli->prepare("SELECT a.*, u.nome as profissional FROM agenda a INNER JOIN users u ON a.id_user = u.id WHERE a.id = ?");
$stmt_agenda->bind_param('i', $id_agenda);
$stmt_agenda->execute();
$result_agenda = $stmt_agenda->get_result();
$agenda = $result_agenda->fetch_assoc();
if (!$agenda) {
    die('<h1>Agenda não encontrada.</h1>');
}

if (isset($_GET['action']) && $_GET['action'] === 'slots' && isset($_GET['data'])) {
    header('Content-Type: application/json');
    echo json_encode(getAvailableSlots($mysqli, $id_agenda, $_GET['data']));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'month' && isset($_GET['mes'], $_GET['ano'])) {
    header('Content-Type: application/json');
    echo json_encode(getMonthAvailability($mysqli, $id_agenda, (int)$_GET['mes'], (int)$_GET['ano']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'], $_POST['inicio'], $_POST['fim'])) {
    $data = $_POST['data'];
    $inicio = $_POST['inicio'];
    $fim = $_POST['fim'];
    $valor = $agenda['valor'] ?? 0;
    header("Location: pagamento.php?agenda=$id_agenda&data=$data&inicio=$inicio&fim=$fim&valor=$valor");
    exit;
}

function getAvailableSlots($mysqli, $id_agenda, $data) {
    $slots = [];
    $data_dt = new DateTime($data);
    $dia_semana = (int)$data_dt->format('w');
    $data_str = $data_dt->format('Y-m-d');

    $excecoes = [];

    $exc_query = $mysqli->query("SELECT hora_inicio, hora_termino FROM excessoes WHERE id_agenda = '$id_agenda' AND data = '$data_str'");
    while ($e = $exc_query->fetch_assoc()) {
        $excecoes[] = $e;
    }

    $exc_rec = $mysqli->query("SELECT hora_inicio, hora_termino, domingo, segunda, terca, quarta, quinta, sexta, sabado, data as d_ini, data_termino FROM excessoes WHERE id_agenda = '$id_agenda' AND data_termino IS NOT NULL");
    while ($e = $exc_rec->fetch_assoc()) {
        $dias = [];
        if ($e['domingo']) $dias[] = 0;
        if ($e['segunda']) $dias[] = 1;
        if ($e['terca']) $dias[] = 2;
        if ($e['quarta']) $dias[] = 3;
        if ($e['quinta']) $dias[] = 4;
        if ($e['sexta']) $dias[] = 5;
        if ($e['sabado']) $dias[] = 6;
        if (in_array($dia_semana, $dias) && $data_str >= $e['d_ini'] && $data_str <= $e['data_termino']) {
            $excecoes[] = ['hora_inicio' => $e['hora_inicio'], 'hora_termino' => $e['hora_termino']];
        }
    }

    $rot_query = $mysqli->query("SELECT * FROM rotinas WHERE id_agenda = '$id_agenda'");
    while ($r = $rot_query->fetch_assoc()) {
        if ($data_str < $r['data_inicio'] || $data_str > $r['data_termino']) continue;
        $dias_rotina = [];
        if ($r['domingo']) $dias_rotina[] = 0;
        if ($r['segunda']) $dias_rotina[] = 1;
        if ($r['terca']) $dias_rotina[] = 2;
        if ($r['quarta']) $dias_rotina[] = 3;
        if ($r['quinta']) $dias_rotina[] = 4;
        if ($r['sexta']) $dias_rotina[] = 5;
        if ($r['sabado']) $dias_rotina[] = 6;
        if (!in_array($dia_semana, $dias_rotina)) continue;

        $h_ini = new DateTime($r['hora_inicio']);
        $h_fim = new DateTime($r['hora_termino']);
        $dur = (int)$r['duracao'];
        $diff = $h_ini->diff($h_fim);
        $total_min = $diff->h * 60 + $diff->i;
        $qtd = $dur > 0 ? (int)(($total_min - $dur) / $dur) : -1;

        for ($j = 0; $j <= $qtd; $j++) {
            $s_ini = clone $h_ini;
            $s_ini->modify('+' . ($dur * $j) . ' minutes');
            $s_fim = clone $s_ini;
            $s_fim->modify("+$dur minutes");
            $bloq = false;
            foreach ($excecoes as $exc) {
                $e_ini = new DateTime($exc['hora_inicio']);
                $e_fim = new DateTime($exc['hora_termino']);
                if (($s_ini >= $e_ini && $s_ini < $e_fim) || ($s_fim > $e_ini && $s_fim <= $e_fim)) {
                    $bloq = true;
                    break;
                }
            }
            if (!$bloq) {
                $slots[] = ['inicio' => $s_ini->format('H:i'), 'fim' => $s_fim->format('H:i')];
            }
        }
    }

    usort($slots, fn($a, $b) => strcmp($a['inicio'], $b['inicio']));
    return $slots;
}

function getMonthAvailability($mysqli, $id_agenda, $mes, $ano) {
    $rotinas = [];
    $rq = $mysqli->query("SELECT * FROM rotinas WHERE id_agenda = '$id_agenda'");
    while ($r = $rq->fetch_assoc()) $rotinas[] = $r;

    $excecoes_avulsas = [];
    $excecoes_rec = [];
    $eq = $mysqli->query("SELECT * FROM excessoes WHERE id_agenda = '$id_agenda'");
    while ($e = $eq->fetch_assoc()) {
        if ($e['data_termino']) $excecoes_rec[] = $e;
        else $excecoes_avulsas[] = $e;
    }

    if (!$rotinas) return [];

    $available = [];
    $dias_no_mes = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));
    $today = new DateTime('today');

    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_str = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $data_dt = new DateTime($data_str);
        if ($data_dt < $today) continue;

        $dia_semana = (int)$data_dt->format('w');

        foreach ($rotinas as $r) {
            if ($data_str < $r['data_inicio'] || $data_str > $r['data_termino']) continue;

            $dias_r = [];
            if ($r['domingo']) $dias_r[] = 0;
            if ($r['segunda']) $dias_r[] = 1;
            if ($r['terca']) $dias_r[] = 2;
            if ($r['quarta']) $dias_r[] = 3;
            if ($r['quinta']) $dias_r[] = 4;
            if ($r['sexta']) $dias_r[] = 5;
            if ($r['sabado']) $dias_r[] = 6;
            if (!in_array($dia_semana, $dias_r)) continue;

            $h_ini = new DateTime($r['hora_inicio']);
            $h_fim = new DateTime($r['hora_termino']);
            $dur = (int)$r['duracao'];
            $diff = $h_ini->diff($h_fim);
            $total_min = $diff->h * 60 + $diff->i;
            $qtd = $dur > 0 ? (int)(($total_min - $dur) / $dur) : -1;

            for ($j = 0; $j <= $qtd; $j++) {
                $s_ini = clone $h_ini;
                $s_ini->modify('+' . ($dur * $j) . ' minutes');
                $s_fim = clone $s_ini;
                $s_fim->modify("+$dur minutes");

                $bloq = false;

                foreach ($excecoes_avulsas as $e) {
                    if ($e['data'] != $data_str) continue;
                    $e_ini = new DateTime($e['hora_inicio']);
                    $e_fim_dt = new DateTime($e['hora_termino']);
                    if (($s_ini >= $e_ini && $s_ini < $e_fim_dt) || ($s_fim > $e_ini && $s_fim <= $e_fim_dt)) {
                        $bloq = true;
                        break;
                    }
                }
                if ($bloq) continue;

                foreach ($excecoes_rec as $e) {
                    $dias_e = [];
                    if ($e['domingo']) $dias_e[] = 0;
                    if ($e['segunda']) $dias_e[] = 1;
                    if ($e['terca']) $dias_e[] = 2;
                    if ($e['quarta']) $dias_e[] = 3;
                    if ($e['quinta']) $dias_e[] = 4;
                    if ($e['sexta']) $dias_e[] = 5;
                    if ($e['sabado']) $dias_e[] = 6;
                    if (!in_array($dia_semana, $dias_e)) continue;
                    if ($data_str < $e['data'] || $data_str > $e['data_termino']) continue;

                    $e_ini = new DateTime($e['hora_inicio']);
                    $e_fim_dt = new DateTime($e['hora_termino']);
                    if (($s_ini >= $e_ini && $s_ini < $e_fim_dt) || ($s_fim > $e_ini && $s_fim <= $e_fim_dt)) {
                        $bloq = true;
                        break;
                    }
                }

                if (!$bloq) {
                    $available[] = $data_str;
                    break 2;
                }
            }
        }
    }

    return $available;
}

$mes_atual = (int)date('n');
$ano_atual = (int)date('Y');
$disponiveis_iniciais = getMonthAvailability($mysqli, $id_agenda, $mes_atual, $ano_atual);

// Compute min/max month from rotinas for navigation boundaries
$range = $mysqli->query("SELECT MIN(data_inicio) as dmin, MAX(data_termino) as dmax FROM rotinas WHERE id_agenda = '$id_agenda'")->fetch_assoc();
if ($range && $range['dmin']) {
    $dmin = new DateTime($range['dmin']);
    $dmax = new DateTime($range['dmax']);
    $minMes = (int)$dmin->format('n');
    $minAno = (int)$dmin->format('Y');
    $maxMes = (int)$dmax->format('n');
    $maxAno = (int)$dmax->format('Y');
} else {
    $minMes = $minAno = $maxMes = $maxAno = null;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilite — <?= htmlspecialchars($agenda['servico']) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1a1a2e;
        }
        .container { width: 100%; max-width: 520px; }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        .service-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        .service-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 24px;
        }
        .step-label {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #999;
            margin-bottom: 4px;
        }
        .step-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 14px;
            color: #1a1a2e;
        }
        .calendar-section { margin-bottom: 28px; }

        /* Calendar header */
        .cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .cal-header .month-year {
            font-size: 1.05rem;
            font-weight: 600;
            color: #1a1a2e;
        }
        .cal-nav {
            background: none;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.9rem;
            color: #555;
            transition: all 0.15s;
            font-family: inherit;
        }
        .cal-nav:hover { background: #f0f4ff; border-color: #4A90D9; color: #4A90D9; }
        .cal-nav:disabled { opacity: 0.35; cursor: not-allowed; }
        .cal-nav:disabled:hover { background: none; border-color: #e0e0e0; color: #555; }

        /* Day-of-week header */
        .cal-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            margin-bottom: 6px;
        }
        .cal-days span {
            font-size: 0.72rem;
            font-weight: 600;
            color: #999;
            padding: 4px 0;
            text-transform: uppercase;
        }

        /* Date cells grid */
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }
        .cal-cell {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            cursor: default;
            font-size: 0.85rem;
            font-weight: 500;
            color: #bbb;
            background: transparent;
            transition: all 0.12s ease;
            position: relative;
            user-select: none;
        }
        .cal-cell .day-num { line-height: 1; }
        .cal-cell .day-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            margin-top: 3px;
            transition: all 0.12s;
        }

        /* Past */
        .cal-cell.past { color: #e0e0e0; }
        .cal-cell.past .day-dot { background: transparent; }

        /* Available */
        .cal-cell.available {
            color: #1a1a2e;
            cursor: pointer;
        }
        .cal-cell.available .day-dot { background: #34a853; }
        .cal-cell.available:hover { background: #e8f5e9; }

        /* Unavailable (future but no slots) */
        .cal-cell.unavailable { color: #d0d0d0; }
        .cal-cell.unavailable .day-dot { background: transparent; }

        /* Today */
        .cal-cell.today { border: 2px solid #4A90D9; }
        .cal-cell.today.available { background: #f0f5ff; }

        /* Selected */
        .cal-cell.selected {
            background: #4A90D9 !important;
            color: #fff !important;
            border-color: #4A90D9 !important;
        }
        .cal-cell.selected .day-dot { background: #fff !important; }

        /* Slots */
        .slots-section { margin-top: 8px; }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
            margin-bottom: 20px;
            min-height: 50px;
        }
        .slot-btn {
            padding: 12px 8px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            background: #fafafa;
            font-size: 0.9rem;
            font-weight: 500;
            color: #1a1a2e;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: center;
            font-family: inherit;
        }
        .slot-btn:hover { border-color: #4A90D9; background: #f0f5ff; }
        .slot-btn.selected { border-color: #34a853; background: #e8f5e9; color: #1e7e34; font-weight: 600; }
        .slots-empty {
            color: #999;
            font-size: 0.85rem;
            text-align: center;
            padding: 16px 0;
            grid-column: 1 / -1;
        }
        .loading {
            text-align: center;
            padding: 16px 0;
            color: #888;
            font-size: 0.85rem;
            grid-column: 1 / -1;
        }
        .btn-confirm {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #4A90D9;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-family: inherit;
            margin-top: 4px;
        }
        .btn-confirm:hover { background: #357abd; }
        .btn-confirm:disabled { background: #ccc; cursor: not-allowed; }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.75rem;
            color: #aaa;
        }
        .footer strong { color: #666; }

        .selected-date-label {
            font-size: 0.85rem;
            color: #4A90D9;
            font-weight: 500;
            margin-bottom: 10px;
        }

        @media (max-width: 480px) {
            .card { padding: 24px 18px; }
            .slots-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); }
            .cal-cell { font-size: 0.78rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php $prof_nome = $agenda['nome_profissional'] ?: $agenda['profissional']; ?>
            <div style="display:flex; flex-direction:column; align-items:center; gap:8px; margin-bottom:18px; text-align:center;">
                <?= exibirAvatarProfissional($prof_nome, $agenda['foto_profissional'] ?? '', 120) ?>
                <div>
                    <div class="service-name"><?= htmlspecialchars($agenda['servico']) ?></div>
                    <div class="service-meta">com <?= htmlspecialchars($prof_nome) ?></div>
                </div>
            </div>

            <form id="formAgendar" method="POST" action="">
                <input type="hidden" name="data" id="hiddenData">
                <input type="hidden" name="inicio" id="hiddenInicio">
                <input type="hidden" name="fim" id="hiddenFim">

                <div class="calendar-section">
                    <div class="step-label">Passo 1</div>
                    <div class="step-title">Escolha uma data</div>

                    <div class="cal-header">
                        <button type="button" class="cal-nav" id="prevMonth">&lt;</button>
                        <span class="month-year" id="monthYearLabel"></span>
                        <button type="button" class="cal-nav" id="nextMonth">&gt;</button>
                    </div>

                    <div class="cal-days">
                        <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
                    </div>
                    <div class="cal-grid" id="calGrid"></div>
                </div>

                <div class="slots-section" id="slotsSection" style="display:none;">
                    <div class="step-label">Passo 2</div>
                    <div class="step-title">Escolha um horário</div>
                    <div class="selected-date-label" id="selectedDateLabel"></div>
                    <div class="slots-grid" id="slotsContainer">
                        <div class="slots-empty">Selecione uma data no calendário</div>
                    </div>
                </div>

                <button type="submit" class="btn-confirm" id="btnConfirm" disabled>Confirmar Agendamento</button>
            </form>
        </div>
        <div class="footer">
            <img src="images/logo.jpg" alt="Facilite" height="32" style="height:32px; vertical-align:middle; margin-right:4px;">
            Agende seu horario com <strong><?= htmlspecialchars($prof_nome) ?></strong>
        </div>
        <div style="text-align:center; font-size:0.7rem; color:#bbb; margin-top:8px;">Facilite &mdash; 2026</div>
    </div>

    <script>
        const idAgenda = <?= $id_agenda ?>;
        const monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        const initialAvailability = <?= json_encode($disponiveis_iniciais) ?>;
        const minMes = <?= $minMes ?? 'null' ?>;
        const minAno = <?= $minAno ?? 'null' ?>;
        const maxMes = <?= $maxMes ?? 'null' ?>;
        const maxAno = <?= $maxAno ?? 'null' ?>;

        let currentMonth = <?= $mes_atual ?>;
        let currentYear = <?= $ano_atual ?>;
        let availableDates = initialAvailability;
        let selectedDate = null;
        let selectedSlot = null;

        const calGrid = document.getElementById('calGrid');
        const monthYearLabel = document.getElementById('monthYearLabel');
        const slotsContainer = document.getElementById('slotsContainer');
        const slotsSection = document.getElementById('slotsSection');
        const selectedDateLabel = document.getElementById('selectedDateLabel');
        const btnConfirm = document.getElementById('btnConfirm');
        const hiddenData = document.getElementById('hiddenData');
        const hiddenInicio = document.getElementById('hiddenInicio');
        const hiddenFim = document.getElementById('hiddenFim');

        function formatDateBR(str) {
            const [y,m,d] = str.split('-');
            return `${d}/${m}/${y}`;
        }

        function renderCalendar() {
            monthYearLabel.textContent = `${monthNames[currentMonth-1]} ${currentYear}`;

            const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
            const todayStr = new Date().toISOString().split('T')[0];

            let html = '';
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="cal-cell"></div>';
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${currentYear}-${String(currentMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const isToday = dateStr === todayStr;
                const isPast = dateStr < todayStr;
                const isAvailable = availableDates.includes(dateStr);
                const isSelected = selectedDate === dateStr;

                let classes = 'cal-cell';
                if (isPast) classes += ' past';
                else if (isAvailable) classes += ' available';
                else if (!isAvailable) classes += ' unavailable';
                if (isToday) classes += ' today';
                if (isSelected) classes += ' selected';

                html += `<div class="${classes}" data-date="${dateStr}">
                    <span class="day-num">${d}</span>
                    ${!isPast && isAvailable ? '<span class="day-dot"></span>' : '<span class="day-dot"></span>'}
                </div>`;
            }

            calGrid.innerHTML = html;

            // Disable nav buttons at boundaries
            const prevBtn = document.getElementById('prevMonth');
            const nextBtn = document.getElementById('nextMonth');
            prevBtn.disabled = minMes !== null && isMonthBefore(currentMonth - 1, currentYear, minMes, minAno);
            nextBtn.disabled = maxMes !== null && isMonthAfter(currentMonth + 1, currentYear, maxMes, maxAno);

            calGrid.querySelectorAll('.cal-cell.available').forEach(cell => {
                cell.addEventListener('click', function() {
                    const date = this.dataset.date;
                    selectDate(date);
                });
            });
        }

        function selectDate(date) {
            selectedDate = date;
            selectedSlot = null;
            btnConfirm.disabled = true;
            hiddenData.value = date;

            calGrid.querySelectorAll('.cal-cell').forEach(c => c.classList.remove('selected'));
            const cell = calGrid.querySelector(`.cal-cell[data-date="${date}"]`);
            if (cell) cell.classList.add('selected');

            const [y,m,d] = date.split('-');
            selectedDateLabel.textContent = `${d}/${m}/${y}`;
            slotsSection.style.display = 'block';
            slotsContainer.innerHTML = '<div class="loading">Carregando horários...</div>';

            fetch(`agendar.php?action=slots&id=${idAgenda}&data=${date}`)
                .then(r => r.json())
                .then(slots => {
                    if (!slots.length) {
                        slotsContainer.innerHTML = '<div class="slots-empty">Nenhum horário disponível nesta data.</div>';
                        return;
                    }
                    slotsContainer.innerHTML = '';
                    slots.forEach(s => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'slot-btn';
                        btn.textContent = `${s.inicio} — ${s.fim}`;
                        btn.dataset.inicio = s.inicio;
                        btn.dataset.fim = s.fim;
                        btn.addEventListener('click', function() {
                            slotsContainer.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                            this.classList.add('selected');
                            selectedSlot = this.dataset;
                            hiddenInicio.value = this.dataset.inicio;
                            hiddenFim.value = this.dataset.fim;
                            btnConfirm.disabled = false;
                        });
                        slotsContainer.appendChild(btn);
                    });
                })
                .catch(() => {
                    slotsContainer.innerHTML = '<div class="slots-empty" style="color:#d93025;">Erro ao carregar horários.</div>';
                });
        }

        function isMonthBefore(m1, a1, m2, a2) { return a1 < a2 || (a1 === a2 && m1 < m2); }
        function isMonthAfter(m1, a1, m2, a2) { return a1 > a2 || (a1 === a2 && m1 > m2); }

        function fetchMonth(mes, ano) {
            // Clamp navigation to available range
            if (minMes !== null) {
                if (isMonthBefore(mes, ano, minMes, minAno)) { mes = minMes; ano = minAno; }
            }
            if (maxMes !== null) {
                if (isMonthAfter(mes, ano, maxMes, maxAno)) { mes = maxMes; ano = maxAno; }
            }
            currentMonth = mes;
            currentYear = ano;

            monthYearLabel.textContent = `${monthNames[mes-1]} ${ano}`;
            calGrid.innerHTML = '<div class="loading" style="grid-column:1/-1;padding:20px;">Carregando...</div>';

            fetch(`agendar.php?action=month&id=${idAgenda}&mes=${mes}&ano=${ano}`)
                .then(r => r.json())
                .then(dates => {
                    availableDates = dates;
                    renderCalendar();
                })
                .catch(() => {
                    calGrid.innerHTML = '<div class="loading" style="grid-column:1/-1;padding:20px;color:#d93025;">Erro ao carregar calendário.</div>';
                });
        }

        document.getElementById('prevMonth').addEventListener('click', () => fetchMonth(currentMonth - 1, currentYear));
        document.getElementById('nextMonth').addEventListener('click', () => fetchMonth(currentMonth + 1, currentYear));

        renderCalendar();

        if (initialAvailability.length > 0) {
            selectDate(initialAvailability[0]);
        } else if (minMes !== null) {
            // Auto-navigate to first available month
            fetchMonth(minMes, minAno);
        }
    </script>
</body>
</html>
