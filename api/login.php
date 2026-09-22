<?php
/**
 * POST /api/login.php  { "email": "...", "senha": "..." }
 * Aceita somente e-mails institucionais @scseduca.com.br (api/dominio.php).
 * Bloqueia a conta por 15 minutos após 5 tentativas erradas.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require_once __DIR__ . '/dominio.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_out(['erro' => 'Método não permitido.'], 405);
}

$body  = corpo_json();
$email = strtolower(trim((string)($body['email'] ?? '')));
$senha = (string)($body['senha'] ?? '');

if ($email === '' || $senha === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_out(['erro' => 'Informe e-mail e senha válidos.'], 400);
}

// Amarração ao domínio institucional: fora do @scseduca.com.br não consulta o banco.
if (!email_institucional_valido($email)) {
  json_out(['erro' => 'Use seu e-mail institucional @' . MOCAP_DOMINIO_EMAIL . '.'], 422);
}

// pequena pausa contra ataques de força bruta automatizados
usleep(300000);

$st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
$st->execute([$email]);
$u = $st->fetch();

$msgGenerica = 'E-mail ou senha incorretos.';   // não revela qual campo errou

if (!$u || !$u['ativo']) {
  registrar_log($pdo, null, 'login_falha', $email);
  json_out(['erro' => $msgGenerica], 401);
}

if ($u['bloqueado_ate'] !== null && strtotime($u['bloqueado_ate']) > time()) {
  json_out(['erro' => 'Conta temporariamente bloqueada por excesso de tentativas. Tente novamente em alguns minutos.'], 423);
}

if (!password_verify($senha, $u['senha_hash'])) {
  $tent = (int)$u['tentativas_login'] + 1;
  $bloq = $tent >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
  $pdo->prepare('UPDATE usuarios SET tentativas_login = ?, bloqueado_ate = ? WHERE id = ?')
      ->execute([$bloq ? 0 : $tent, $bloq, $u['id']]);
  registrar_log($pdo, (int)$u['id'], 'login_falha', $bloq ? 'conta bloqueada 15min' : "tentativa $tent");
  json_out(['erro' => $bloq ? 'Conta bloqueada por 15 minutos após 5 tentativas.' : $msgGenerica], 401);
}

// Sucesso: renova o ID da sessão (contra fixação de sessão)
session_regenerate_id(true);
$_SESSION['usuario'] = [
  'id'    => (int)$u['id'],
  'nome'  => $u['nome'],
  'email' => $u['email'],
  'papel' => $u['papel'],
];
$pdo->prepare('UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL, ultimo_login = NOW() WHERE id = ?')
    ->execute([$u['id']]);
registrar_log($pdo, (int)$u['id'], 'login_ok');

json_out(['ok' => true, 'usuario' => $_SESSION['usuario'], 'csrf' => csrf_token()]);
