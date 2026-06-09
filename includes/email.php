<?php
require_once __DIR__ . '/../vendor/autoload.php';

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}

$apiKey = getenv('RESEND_API_KEY');

function enviarEmail($para, $assunto, $corpoTexto) {
    global $apiKey;
    if (!$apiKey) {
        error_log('Resend: RESEND_API_KEY nao configurada no .env');
        return false;
    }

    $resend = Resend::client($apiKey);

    try {
        $resend->emails->send([
            'from' => 'Facilite <onboarding@resend.dev>',
            'to' => [$para],
            'subject' => $assunto,
            'text' => $corpoTexto,
        ]);
        return true;
    } catch (\Exception $e) {
        error_log('Resend erro: ' . $e->getMessage());
        return false;
    }
}
