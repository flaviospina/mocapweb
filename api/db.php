<?php
/**
 * MoCapWeb CECAPE — conexão, sessão segura e utilitários.
 * Todos os endpoints da API incluem este arquivo.
 *
 * Configuração aceita (a primeira encontrada vence):
 *   1. api/config.php  → return ['host'=>..,'dbname'=>..,'user'=>..,'pass'=>..];
 *   2. .env na raiz do sistema (DB_HOST, DB_NAME, DB_USER, DB_PASS)
 * No HostGator, banco e usuário levam o prefixo do cPanel: itthri79_nome.
 */
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// Log de erros do PHP em arquivo próprio (fora do alcance do navegador via .htaccess)
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');
error_reporting(E_ALL);

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

function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function log_mocap(string $msg): void {
  error_log('[MocapWeb] ' . $msg);
}

/* ── Leitura da configuração ─────────────────────────────
   Aceita variações de nome de chave (dbname / db / database /
   DB_NAME) para o sistema nunca conectar "sem banco" em silêncio. */
function mocap_carregar_config(): array {
  $cfg = [];
  $configFile = __DIR__ . '/config.php';
  if (is_file($configFile)) {
    $lido = require $configFile;
    if (is_array($lido)) $cfg = $lido;
  } else {
    $envFile = dirname(__DIR__) . '/.env';
    if (is_file($envFile)) {
      foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $cfg[$k] = trim($v, "\"'");
      }
    }
  }
  $pega = function (array $chaves) use ($cfg): string {
    foreach ($chaves as $k) {
      if (isset($cfg[$k]) && trim((string)$cfg[$k]) !== '') return trim((string)$cfg[$k]);
    }
    return '';
  };
  return [
    'encontrado' => is_file($configFile) ? 'api/config.php' : (is_file(dirname(__DIR__) . '/.env') ? '.env' : ''),
    'host'   => $pega(['host', 'DB_HOST', 'db_host']) ?: 'localhost',
    'dbname' => $pega(['dbname', 'db', 'database', 'DB_NAME', 'db_name', 'banco']),
    'user'   => $pega(['user', 'usuario', 'DB_USER', 'db_user', 'username']),
    'pass'   => $pega(['pass', 'senha', 'password', 'DB_PASS', 'db_pass']),
  ];
}

$cfg = mocap_carregar_config();

if ($cfg['encontrado'] === '') {
  log_mocap('Configuração ausente: nem api/config.php nem .env foram encontrados.');
  json_out(['erro' => 'Configuração do banco ausente. Copie api/config.example.php para api/config.php e preencha os dados do banco.'], 500);
}
if ($cfg['dbname'] === '') {
  // É exatamente esta situação que gera "1046 No database selected".
  log_mocap("Nome do banco vazio em {$cfg['encontrado']} — preencha 'dbname' (no HostGator: itthri79_nome_do_banco).");
  json_out(['erro' => 'Nome do banco de dados não configurado. Avise a equipe de TI do CECAPE (api/config.php → dbname).'], 500);
}
if ($cfg['user'] === '') {
  log_mocap("Usuário do banco vazio em {$cfg['encontrado']}.");
  json_out(['erro' => 'Usuário do banco de dados não configurado. Avise a equipe de TI do CECAPE.'], 500);
}

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
  // Garante que o banco foi realmente selecionado (alguns servidores
  // aceitam a conexão mesmo com dbname inválido).
  $selecionado = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
  if ($selecionado === '') {
    log_mocap("Conexão aberta, mas nenhum banco selecionado (dbname='{$cfg['dbname']}'). Confira o nome com prefixo do cPanel.");
    json_out(['erro' => 'Banco de dados não selecionado. Avise a equipe de TI do CECAPE (nome do banco em api/config.php).'], 500);
  }
} catch (PDOException $e) {
  // Mensagens específicas por código do MySQL, sem expor credenciais
  $codigo = (int)($e->errorInfo[1] ?? 0);
  $dica = match ($codigo) {
    1044, 1045 => 'usuário ou senha do banco incorretos, ou usuário sem permissão no banco (cPanel → MySQL Databases → Add User To Database, ALL PRIVILEGES)',
    1049       => "banco '{$cfg['dbname']}' não existe (no HostGator o nome leva o prefixo do cPanel, ex.: itthri79_mocapweb)",
    2002       => "não foi possível conectar ao host '{$cfg['host']}' (no HostGator use localhost)",
    default    => $e->getMessage(),
  };
  log_mocap("PDOException [{$codigo}] em " . __FILE__ . ": {$dica}");
  json_out(['erro' => 'Erro interno no servidor. Avise a equipe de TI do CECAPE.', 'detalhe' => 'Falha na conexão com o banco de dados.'], 500);
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
