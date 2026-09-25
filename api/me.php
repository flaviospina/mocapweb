<?php
/** GET /api/me.php — quem está logado (401 se ninguém) e se precisa trocar a senha. */
declare(strict_types=1);
require __DIR__ . '/db.php';
$u = require_login();
// Relê do banco: a obrigação pode ter sido alterada pelo administrador
try {
  $st = $pdo->prepare('SELECT precisa_trocar_senha FROM usuarios WHERE id = ?');
  $st->execute([$u['id']]);
  $flag = (int)$st->fetchColumn();
  $_SESSION['usuario']['precisa_trocar_senha'] = $flag;
  $u['precisa_trocar_senha'] = $flag;
} catch (PDOException $e) { $u['precisa_trocar_senha'] = 0; }   // migração ainda não aplicada
json_out(['ok' => true, 'usuario' => $u, 'csrf' => csrf_token(), 'precisa_trocar_senha' => (int)$u['precisa_trocar_senha'] === 1]);
