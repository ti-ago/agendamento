<?php
function getFotoUrl($foto) {
    if (empty($foto)) return null;
    $path = __DIR__ . '/../images/' . $foto;
    return file_exists($path) ? 'images/' . $foto : null;
}

function exibirFotoProfissional($foto, $tamanho = 48, $classes = '') {
    $url = getFotoUrl($foto);
    if ($url) {
        $style = "width:{$tamanho}px;height:{$tamanho}px;border-radius:50%;object-fit:cover;" . ($classes ?: '');
        return '<img src="' . htmlspecialchars($url) . '" alt="Foto" style="' . $style . '">';
    }
    return '';
}

function exibirAvatarProfissional($nome, $foto, $tamanho = 48) {
    $url = getFotoUrl($foto);
    if ($url) {
        $style = "width:{$tamanho}px;height:{$tamanho}px;border-radius:50%;object-fit:cover;";
        return '<img src="' . htmlspecialchars($url) . '" alt="Foto" style="' . $style . '">';
    }
    $iniciais = '';
    if (!empty($nome)) {
        $partes = explode(' ', trim($nome));
        foreach ($partes as $p) {
            if (!empty($p)) $iniciais .= strtoupper($p[0]);
        }
        $iniciais = mb_substr($iniciais, 0, 2);
    } else {
        $iniciais = '?';
    }
    $style = "width:{$tamanho}px;height:{$tamanho}px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:" . ($tamanho * 0.4) . "px;color:#fff;background:#3465a4;flex-shrink:0;";
    return '<div style="' . $style . '">' . htmlspecialchars($iniciais) . '</div>';
}
