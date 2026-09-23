<?php
// index.php
// Entrada protegida do MocapWeb: o servidor verifica a sessão ANTES de entregar
// o index.html. Sem login, redireciona para login.php sem carregar nada da
// página principal. O index.html continua intacto; ele só é lido daqui.

declare(strict_types=1);

require __DIR__ . '/api/db.php';      // inicia a sessão MOCAPSESS (mesmo bootstrap da API)
require_once __DIR__ . '/api/guarda.php';

exigir_login_pagina('index.php');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$pagina = __DIR__ . '/index.html';
if (!is_file($pagina)) {
    http_response_code(500);
    echo 'Arquivo index.html não encontrado.';
    exit;
}
readfile($pagina);
