<?php
require_once('conexao.php');
require_once('includes/helpers.php');

$agenda_id = (int)($_POST['agenda_id'] ?? $_GET['agenda'] ?? 0);
$data = $_POST['data'] ?? $_GET['data'] ?? '';
$inicio = $_POST['inicio'] ?? $_GET['inicio'] ?? '';
$fim = $_POST['fim'] ?? $_GET['fim'] ?? '';

if (!$agenda_id || !$data || !$inicio) {
    die('<h1>Parâmetros inválidos.</h1>');
}

$query = $mysqli->query("SELECT a.*, u.nome as profissional FROM agenda a INNER JOIN users u ON a.id_user = u.id WHERE a.id = '$agenda_id'");
$agenda = $query->fetch_assoc();
if (!$agenda) {
    die('<h1>Agenda não encontrada.</h1>');
}

$data_formatada = date('d/m/Y', strtotime($data));
$token_gerado = null;
$error = null;
$test_mode = isset($_POST['test']) || isset($_GET['test']);
$valor = $_GET['valor'] ?? $agenda['valor'] ?? 0;
if (is_numeric($valor)) $valor = number_format((float)$valor, 2, '.', '');
else $valor = '0.00';

// Process payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pagamento'])) {
    $cliente_nome = trim($_POST['cliente_nome'] ?? '');
    if (empty($cliente_nome)) {
        $error = 'Informe seu nome para confirmar o agendamento.';
    } else {
        // Check if this slot is already taken
        $check = $mysqli->query("SELECT id FROM agendamentos WHERE id_agenda = '$agenda_id' AND data = '$data' AND hora_inicio = '$inicio' AND status = 'confirmado'");
        if ($check && $check->num_rows > 0) {
            $error = 'Este horário já foi agendado por outra pessoa. Escolha outro horário.';
        } else {
            // Generate unique token
            do {
                $token = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
                $check_token = $mysqli->query("SELECT id FROM agendamentos WHERE token = '$token'");
            } while ($check_token && $check_token->num_rows > 0);

            $cliente_nome_safe = $mysqli->real_escape_string($cliente_nome);
            $stmt = $mysqli->query("INSERT INTO agendamentos (id_agenda, data, hora_inicio, hora_fim, cliente_nome, token, status) VALUES ('$agenda_id', '$data', '$inicio', '$fim', '$cliente_nome_safe', '$token', 'pendente')");

            if ($stmt) {
                $token_gerado = $token;
            } else {
                $error = 'Erro ao confirmar agendamento: ' . $mysqli->error;
            }
        }
    }
}

$prof_nome = $agenda['nome_profissional'] ?: $agenda['profissional'];
$chave_pix = $agenda['chave_pix'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilite — <?= htmlspecialchars($agenda['servico'] ?? '') ?></title>
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
        .container { width: 100%; max-width: 480px; }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.06);
        }
        h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
            border-bottom: 1px solid #f5f5f5;
        }
        .detail-row .label { color: #888; }
        .detail-row .value { font-weight: 500; }

        .pix-section {
            background: #f0f8ff;
            border: 1px solid #b3d9f2;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .pix-section .pix-icon { font-size: 2rem; margin-bottom: 8px; }
        .pix-section h3 { font-size: 1rem; color: #1a1a2e; margin-bottom: 4px; }
        .pix-section .pix-sub { font-size: 0.8rem; color: #888; margin-bottom: 12px; }
        .pix-key-display {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1a1a2e;
            word-break: break-all;
            margin-bottom: 8px;
            font-family: monospace;
        }
        .pix-section .btn-copy {
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            background: #4A90D9;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
            font-family: inherit;
        }
        .pix-section .btn-copy:hover { background: #357abd; }
        .pix-section .btn-copy.copied { background: #34a853; }
        #qrcode {
            display: inline-flex; align-items: center; justify-content: center;
            background: #fff; border-radius: 12px; padding: 12px;
            margin: 12px 0 8px; border: 1px solid #e0e0e0;
        }
        #qrcode img, #qrcode canvas { display: block; }

        .form-section { margin-top: 16px; }
        .form-section label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }
        .form-section input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: inherit;
            margin-bottom: 12px;
        }
        .form-section input:focus { border-color: #4A90D9; outline: none; }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s;
            font-family: inherit;
        }
        .btn-primary {
            background: #34a853;
            color: #fff;
            width: 100%;
        }
        .btn-primary:hover { background: #2d8f47; }
        .btn-primary:disabled { background: #ccc; cursor: not-allowed; }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            width: 100%;
            margin-top: 8px;
        }
        .btn-secondary:hover { background: #e0e0e0; }

        .success-section {
            text-align: center;
            padding: 10px 0;
        }
        .success-section .check-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .success-section h3 {
            font-size: 1.2rem;
            color: #34a853;
            margin-bottom: 6px;
        }
        .success-section p {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 6px;
        }
        .token-display {
            background: #fef9e7;
            border: 2px dashed #f39c12;
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
        }
        .token-display .token-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            font-weight: 600;
        }
        .token-display .token-value {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 4px;
            color: #e67e22;
            margin: 8px 0;
            font-family: monospace;
        }
        .token-display .token-msg {
            font-size: 0.8rem;
            color: #666;
        }
        .error-msg {
            background: #fce8e6;
            color: #c5221f;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.75rem;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align:center; margin-bottom:16px;">
            <img src="images/logo.jpg" alt="Facilite" height="48" style="height:48px;">
        </div>
        <div class="card">
            <?php if ($token_gerado): ?>
                <!-- Success / Token display -->
                <div class="success-section">
                    <div style="font-size:2.5rem; margin-bottom:8px; color:#34a853;">OK</div>
                    <h3>Agendamento Confirmado!</h3>
                    <p>Seu horário foi reservado com sucesso.</p>
                    <div style="margin:14px 0; text-align:left;">
                        <div class="detail-row">
                            <span class="label">Serviço</span>
                            <span class="value"><?= htmlspecialchars($agenda['servico']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Profissional</span>
                            <span class="value"><?= htmlspecialchars($prof_nome) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Data</span>
                            <span class="value"><?= $data_formatada ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Horário</span>
                            <span class="value"><?= $inicio ?> — <?= $fim ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Valor</span>
                            <span class="value">R$ <?= number_format((float)$valor, 2, ',', '.') ?></span>
                        </div>
                    </div>

                    <div class="token-display">
                        <div class="token-label">Token de Confirmação</div>
                        <div class="token-value"><?= htmlspecialchars($token_gerado) ?></div>
                        <div class="token-msg">Anote este token e apresente na clinica no dia do atendimento.</div>
                    </div>
                    <a href="agendar.php?id=<?= $agenda_id ?>" class="btn btn-secondary" style="margin-top:16px;">Agendar outro horario</a>
                </div>
            <?php else: ?>
                <!-- Payment / Confirmation form -->
                <h2>Pagamento via PIX</h2>

                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <?= exibirAvatarProfissional($prof_nome, $agenda['foto_profissional'] ?? '', 44) ?>
                    <div>
                        <div style="font-weight:600;font-size:1rem;"><?= htmlspecialchars($agenda['servico']) ?></div>
                        <div style="font-size:0.85rem;color:#888;">com <?= htmlspecialchars($prof_nome) ?></div>
                    </div>
                </div>

                <div class="detail-row">
                    <span class="label">Data</span>
                    <span class="value"><?= $data_formatada ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Horário</span>
                    <span class="value"><?= $inicio ?> — <?= $fim ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Valor</span>
                    <span class="value">R$ <?= number_format((float)$valor, 2, ',', '.') ?></span>
                </div>

                <?php if ($test_mode): ?>
                    <div class="pix-section" style="background:#e8f5e9; border-color:#34a853;">
                        <div style="font-size:1.8rem; font-weight:700; color:#34a853;">MODO TESTE</div>
                        <p class="pix-sub" style="color:#555;">Pagamento via PIX pulado para testes.</p>
                    </div>
                <?php else: ?>
                <div class="pix-section">
                    <div class="pix-icon" style="font-size:1.8rem; font-weight:700; color:#3465a4;">PIX</div>
                    <h3>Pague com PIX</h3>
                    <p class="pix-sub">Escaneie o QR Code ou copie a chave abaixo</p>
                    <?php if (!empty($chave_pix)): ?>
                        <div id="qrcode"></div>
                        <div class="pix-key-display" id="pixKey"><?= htmlspecialchars($chave_pix) ?></div>
                        <button class="btn-copy" id="btnCopyPix" onclick="copiarChavePix()">Copiar Chave PIX</button>
                    <?php else: ?>
                        <div class="pix-key-display" style="color:#888;">Chave PIX nao configurada</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="agenda_id" value="<?= $agenda_id ?>">
                    <input type="hidden" name="data" value="<?= htmlspecialchars($data) ?>">
                    <input type="hidden" name="inicio" value="<?= htmlspecialchars($inicio) ?>">
                    <input type="hidden" name="fim" value="<?= htmlspecialchars($fim) ?>">
                    <?php if ($test_mode): ?>
                    <input type="hidden" name="test" value="1">
                    <?php endif; ?>
                    <div class="form-section">
                        <label for="cliente_nome">Seu nome completo</label>
                        <input type="text" name="cliente_nome" id="cliente_nome" required placeholder="Ex: Joao Silva"
                               value="<?= htmlspecialchars($_POST['cliente_nome'] ?? '') ?>">
                    </div>
                    <p style="font-size:0.78rem; color:#888; margin-bottom:12px;">
                        Preencha seu nome e clique em "Confirmar Pagamento" para gerar seu token de atendimento.
                    </p>
                    <button type="submit" name="confirmar_pagamento" class="btn btn-primary" id="btnConfirmar">
                        Confirmar Pagamento
                    </button>
                </form>

                <?php if (!$test_mode): ?>
                    <p style="text-align:center; margin-top:16px; font-size:0.7rem;">
                        <a href="?agenda=<?= $agenda_id ?>&data=<?= urlencode($data) ?>&inicio=<?= urlencode($inicio) ?>&fim=<?= urlencode($fim) ?>&valor=<?= urlencode($valor) ?>&test=1" style="color:#888; text-decoration:none;">Modo Teste (pular PIX)</a>
                    </p>
                <?php endif; ?>
                <a href="agendar.php?id=<?= $agenda_id ?>" class="btn btn-secondary">Voltar e escolher outro horario</a>
            <?php endif; ?>
        </div>
        <div class="footer">Agende seu horário com <strong><?= htmlspecialchars($prof_nome) ?></strong></div>
        <div style="text-align:center; font-size:0.7rem; color:#bbb; margin-top:16px;">Facilite &mdash; 2026</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        function gerarPixPayload(chave, nome, cidade, valor, txid) {
            function add(tag, valor) {
                var s = String(valor);
                return tag + String(s.length).padStart(2, '0') + s;
            }
            var gui = 'BR.GOV.BCB.PIX';
            var acc = add('00', gui) + add('01', chave);
            var parts = [];
            parts.push(add('00', '01'));
            parts.push(add('01', '12'));
            parts.push(add('26', acc));
            parts.push(add('52', '0000'));
            parts.push(add('53', '986'));
            if (valor) parts.push(add('54', valor));
            parts.push(add('58', 'BR'));
            parts.push(add('59', nome.substring(0, 25) || 'Profissional'));
            parts.push(add('60', cidade.substring(0, 15) || 'Cidade'));
            var tx = add('05', txid || '***');
            parts.push(add('62', tx));
            var raw = parts.join('');
            var crc = calcCRC16(raw + '6304');
            return raw + '6304' + crc;
        }

        function calcCRC16(str) {
            var poly = 0x1021;
            var table = [];
            for (var i = 0; i < 256; i++) {
                var crc = i << 8;
                for (var j = 0; j < 8; j++) {
                    crc = (crc & 0x8000) ? ((crc << 1) ^ poly) : (crc << 1);
                }
                table[i] = crc & 0xFFFF;
            }
            var crc = 0xFFFF;
            for (var k = 0; k < str.length; k++) {
                var c = str.charCodeAt(k);
                if (c > 255) return '0000';
                crc = ((crc << 8) ^ table[((crc >> 8) ^ c) & 0xFF]) & 0xFFFF;
            }
            return crc.toString(16).toUpperCase().padStart(4, '0');
        }

        document.addEventListener('DOMContentLoaded', function() {
            var pixKey = document.getElementById('pixKey');
            if (pixKey) {
                var chave = pixKey.textContent.trim();
                var payload = gerarPixPayload(chave, '<?= htmlspecialchars($prof_nome) ?>', '', '<?= $valor ?>', '<?= $agenda_id ?>' + new Date().toISOString().slice(0,10).replace(/-/g,''));
                new QRCode(document.getElementById('qrcode'), {
                    text: payload,
                    width: 180,
                    height: 180,
                    colorDark: '#1a1a2e',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                // Store payload for copy-pix-coded
                pixKey.dataset.payload = payload;
            }
        });

        function copiarChavePix() {
            var keyEl = document.getElementById('pixKey');
            var btnEl = document.getElementById('btnCopyPix');
            if (!keyEl) return;
            var text = keyEl.dataset.payload || keyEl.textContent;
            navigator.clipboard.writeText(text).then(function() {
                btnEl.textContent = 'Copiado! ✅';
                btnEl.classList.add('copied');
                setTimeout(function() {
                    btnEl.textContent = 'Copiar Código PIX';
                    btnEl.classList.remove('copied');
                }, 2500);
            }).catch(function() {
                var range = document.createRange();
                range.selectNode(keyEl);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                btnEl.textContent = 'Copiado! ✅';
                setTimeout(function() { btnEl.textContent = 'Copiar Chave PIX'; }, 2000);
            });
        }

        document.getElementById('btnConfirmar').addEventListener('click', function(e) {
            var nome = document.getElementById('cliente_nome').value.trim();
            if (!nome) {
                alert('Informe seu nome para confirmar o agendamento.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>
