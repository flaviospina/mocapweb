<?php
/**
 * MoCapWeb CECAPE — conexão, sessão segura e utilitários.
 * Todos os endpoints da API incluem este arquivo.
 */
declare(strict_types=1);

// Sessão endurecida: cookie inacessível ao JavaScript (HttpOnly),
// enviado só no mesmo site (SameSite=Lax) e via HTTPS quando houver.
session_name('MOCAPSESS');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'secure'   => !empty($_SERVER['HTTPS']),
  'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  die(json_encode(['erro' => 'Configuração ausente: copie api/config.example.php para api/config.php e preencha os dados do banco.'], JSON_UNESCAPED_UNICODE));
}
$cfg = require $configFile;

try {
  $pdo = new PDO(
    "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
    $cfg['user'],
    $cfg['pass'],
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,   // consultas preparadas reais
    ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  die(json_encode(['erro' => 'Falha na conexão com o banco de dados.'], JSON_UNESCAPED_UNICODE));
}

function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function corpo_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || strlen($raw) > 60 * 1024 * 1024) {  // limite 60 MB
    json_out(['erro' => 'Corpo da requisição inválido ou grande demais.'], 413);
  }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function require_login(): array {
  if (empty($_SESSION['usuario'])) {
    json_out(['erro' => 'Não autenticado. Faça login.'], 401);
  }
  return $_SESSION['usuario'];
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function require_csrf(): void {
  $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if ($t === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
    json_out(['erro' => 'Token de segurança (CSRF) inválido. Recarregue a página.'], 403);
  }
}

function registrar_log(PDO $pdo, ?int $usuarioId, string $acao, ?string $detalhe = null): void {
  try {
    $st = $pdo->prepare('INSERT INTO logs_acesso (usuario_id, acao, detalhe, ip, user_agent) VALUES (?,?,?,?,?)');
    $st->execute([
      $usuarioId,
      substr($acao, 0, 60),
      $detalhe !== null ? substr($detalhe, 0, 190) : null,
      substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
      substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
  } catch (Throwable $e) { /* log nunca derruba a operação principal */ }
}
