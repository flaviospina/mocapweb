<?php
// api/guarda.php
// Guardas de acesso do MocapWeb (CECAPE). Não dependem de api/db.php nem do banco:
// leem a sessão MOCAPSESS criada pelo api/login.php.
//   - mocap_exigir_login_pagina(): páginas HTML que exigem usuário logado (index.php).
//   - mocap_exigir_admin_pagina(): páginas restritas ao perfil "admin" (admin.php).
//   - mocap_exigir_admin_api():    endpoints JSON restritos ao perfil "admin".
// Todas as funções têm prefixo "mocap_" para não colidir com as do api/db.php.

declare(strict_types=1);

const MOCAP_SESSAO_NOME  = 'MOCAPSESS';
const MOCAP_PAPEL_ADMIN  = 'admin';

/** Cabeçalhos de segurança e sem cache para páginas protegidas. */
function mocap_cabecalhos_protegidos(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

/**
 * Abre a sessão existente (se ainda não estiver aberta).
 * Só chama session_start() quando o navegador já enviou o cookie MOCAPSESS:
 * assim, um visitante anônimo nunca ganha um cookie de sessão daqui, e a
 * sessão criada pelo api/login.php continua sendo a única.
 * Retorna false quando não há cookie de sessão.
 */
function mocap_sessao_abrir(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    if (empty($_COOKIE[MOCAP_SESSAO_NOME])) {
        return false;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name(MOCAP_SESSAO_NOME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    return session_start();
}

/** Usuário logado (id, nome, email, papel) ou null. */
function mocap_usuario_sessao(): ?array
{
    if (!mocap_sessao_abrir()) {
        return null;
    }
    $u = $_SESSION['usuario'] ?? null;
    return (is_array($u) && !empty($u['id'])) ? $u : null;
}

/** Somente o papel "admin" é administrador. Qualquer outro papel, existente ou futuro, não é. */
function mocap_usuario_eh_admin(?array $u): bool
{
    return $u !== null && (($u['papel'] ?? '') === MOCAP_PAPEL_ADMIN);
}

/** Página HTML: sem login, redireciona para login.php (302) e volta ao destino depois. */
function mocap_exigir_login_pagina(string $destino = 'index.php'): array
{
    $u = mocap_usuario_sessao();
    if ($u === null) {
        mocap_cabecalhos_protegidos();
        header('Location: login.php?r=' . rawurlencode($destino), true, 302);
        exit;
    }
    return $u;
}

/** Página HTML restrita ao administrador: sem login vai ao login; logado sem ser admin recebe 403. */
function mocap_exigir_admin_pagina(): array
{
    $u = mocap_exigir_login_pagina('admin.php');
    if (!mocap_usuario_eh_admin($u)) {
        http_response_code(403);
        mocap_cabecalhos_protegidos();
        header('Content-Type: text/html; charset=utf-8');
        $nome = htmlspecialchars((string)($u['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Acesso restrito — MoCap Web · CECAPE</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;
         font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#e6ebf5;
         background:radial-gradient(ellipse at 30% 40%, rgba(29,78,216,.18) 0%, transparent 60%), #0b1220; }
  .card { max-width:420px; width:100%; background:#121a2b; border:1px solid #273042; border-radius:16px; padding:32px 28px; text-align:center; }
  h1 { font-size:20px; margin:0 0 10px; color:#ff4a6e; }
  p { color:#93a0b8; font-size:14px; line-height:1.5; margin:0 0 20px; }
  a { display:inline-block; padding:11px 18px; border-radius:10px; background:#00e5a0; color:#05261b; font-weight:700; text-decoration:none; }
</style>
</head>
<body>
  <div class="card">
    <h1>Acesso restrito</h1>
    <p>Olá, {$nome}. O painel administrativo é exclusivo do perfil <strong>administrador</strong>.<br>
       Se precisar de acesso, fale com a equipe de TI do CECAPE.</p>
    <a href="index.php">Voltar ao MocapWeb</a>
  </div>
</body>
</html>
HTML;
        exit;
    }
    return $u;
}

/** Endpoint JSON restrito ao administrador: 401 sem login, 403 sem ser admin. */
function mocap_exigir_admin_api(): array
{
    $u = mocap_usuario_sessao();
    if ($u === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Não autenticado. Faça login.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!mocap_usuario_eh_admin($u)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Acesso restrito ao administrador.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $u;
}
