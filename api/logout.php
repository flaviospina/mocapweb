<?php
// api/logout.php
// Encerra a sessão do MocapWeb. Aceita POST (pelo botão "Sair" do sistema) e GET
// (acesso direto pelo navegador). Não depende do banco nem do api/db.php.
// Resposta: JSON {"ok":true} para POST; redirecionamento para login.php para GET.

declare(strict_types=1);

require_once __DIR__ . '/guarda.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Bloqueia chamadas iniciadas por outro site (quando o navegador informa a origem).
$fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($fetchSite === 'cross-site') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Origem não permitida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mocap_sessao_abrir()) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'] ?: '/',
            'domain'   => $p['domain'] ?? '',
            'secure'   => (bool)($p['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}
// Garante a remoção do cookie mesmo se a sessão já estava expirada no servidor.
setcookie(MOCAP_SESSAO_NOME, '', ['expires' => time() - 42000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

mocap_cabecalhos_protegidos();

if ($metodo === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Location: ../login.php', true, 302);
exit;
