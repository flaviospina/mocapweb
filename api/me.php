<?php
/** GET /api/me.php — quem está logado (401 se ninguém). */
declare(strict_types=1);
require __DIR__ . '/db.php';
$u = require_login();
json_out(['ok' => true, 'usuario' => $u, 'csrf' => csrf_token()]);
