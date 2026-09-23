<?php
/**
 * GET /api/me.php
 * Devolve o usuário da sessão e o token CSRF. Sem sessão: 401.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

$u = exigir_login();
json_out(['usuario' => $u, 'csrf' => csrf_token()]);
