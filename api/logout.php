<?php
/** POST /api/logout.php — encerra a sessão. */
declare(strict_types=1);
require __DIR__ . '/db.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['erro' => 'Método não permitido.'], 405);
$u = require_login();
require_csrf();
registrar_log($pdo, (int)$u['id'], 'logout');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}
session_destroy();
json_out(['ok' => true]);
