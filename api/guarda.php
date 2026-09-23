<?php
// api/guarda.php
// Guardas de acesso do MocapWeb (CECAPE).
//   - exigir_login_pagina(): páginas HTML que exigem usuário logado (index.php).
//   - exigir_admin_pagina(): páginas restritas ao perfil "admin" (admin.php).
//   - exigir_admin_api():    endpoints JSON restritos ao perfil "admin".
// Inclua depois de api/db.php (que já inicia a sessão). Se a sessão ainda não
// estiver ativa, abre a sessão MOCAPSESS com os mesmos atributos de segurança.

declare(strict_types=1);

const MOCAP_PAPEL_ADMIN = 'admin';

function sessao_garantir(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('MOCAPSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Usuário logado (id, nome, email, papel) ou null. */
function usuario_sessao(): ?array
{
    sessao_garantir();
    $u = $_SESSION['usuario'] ?? null;
    return (is_array($u) && !empty($u['id'])) ? $u : null;
}

/** Somente o perfil "admin" é administrador. Qualquer outro papel, existente ou futuro, não é. */
function usuario_eh_admin(?array $u): bool
{
    return $u !== null && (($u['papel'] ?? '') === MOCAP_PAPEL_ADMIN);
}

/** Página HTML: sem login, redireciona para login.php e volta ao destino depois. */
function exigir_login_pagina(string $destino = 'index.php'): array
{
    $u = usuario_sessao();
    if ($u === null) {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Location: login.php?r=' . rawurlencode($destino), true, 302);
        exit;
    }
    return $u;
}

/** Página HTML restrita ao administrador: sem login vai ao login; logado sem ser admin recebe 403. */
function exigir_admin_pagina(): array
{
    $u = exigir_login_pagina('admin.php');
    if (!usuario_eh_admin($u)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
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
function exigir_admin_api(): array
{
    $u = usuario_sessao();
    if ($u === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Não autenticado. Faça login.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!usuario_eh_admin($u)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Acesso restrito ao administrador.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $u;
}
