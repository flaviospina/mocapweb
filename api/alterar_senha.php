<?php
/**
 * POST /api/alterar_senha.php  { "senha_atual": "...", "nova_senha": "...", "confirmar": "..." }
 * Exige sessão + token CSRF. Grava a nova senha (bcrypt), zera a
 * obrigação de troca e registra data/hora. Resposta: { ok:true, redirecionar:'index.html' }.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['erro' => 'Método não permitido.'], 405);
$u = require_login();
require_csrf();

$b = corpo_json();
$atual  = (string)($b['senha_atual'] ?? '');
$nova   = (string)($b['nova_senha'] ?? '');
$conf   = (string)($b['confirmar'] ?? '');

if ($atual === '' || $nova === '' || $conf === '') json_out(['erro' => 'Preencha a senha atual, a nova senha e a confirmação.'], 400);
if ($nova !== $conf)                                json_out(['erro' => 'A confirmação não confere com a nova senha.'], 400);
if (strlen($nova) < 8)                              json_out(['erro' => 'A nova senha precisa ter pelo menos 8 caracteres.'], 400);
if (!preg_match('/[A-Za-z]/', $nova) || !preg_match('/\d/', $nova)) json_out(['erro' => 'A nova senha precisa ter letras e números.'], 400);
if (strtolower($nova) === 'scseduca')               json_out(['erro' => 'Escolha uma senha diferente da senha inicial.'], 400);
if ($nova === $atual)                               json_out(['erro' => 'A nova senha deve ser diferente da atual.'], 400);

$st = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ? AND ativo = 1');
$st->execute([$u['id']]);
$row = $st->fetch();
if (!$row) json_out(['erro' => 'Usuário não encontrado.'], 404);

usleep(250000);
if (!password_verify($atual, $row['senha_hash'])) {
  registrar_log($pdo, (int)$u['id'], 'troca_senha_falha', 'senha atual incorreta');
  json_out(['erro' => 'A senha atual está incorreta.'], 401);
}

$pdo->prepare('UPDATE usuarios SET senha_hash = ?, precisa_trocar_senha = 0, senha_alterada_em = NOW(), tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?')
    ->execute([password_hash($nova, PASSWORD_BCRYPT), $u['id']]);
$_SESSION['usuario']['precisa_trocar_senha'] = 0;
session_regenerate_id(true);
registrar_log($pdo, (int)$u['id'], 'troca_senha_ok');

json_out(['ok' => true, 'redirecionar' => 'index.html']);
