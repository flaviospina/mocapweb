<?php
// api/db.php
// Bootstrap da API do MocapWeb (CECAPE): configuração, conexão PDO, sessão e
// funções auxiliares usadas por login.php, me.php, eventos.php, capturas.php,
// usuarios.php e admin.php.
//
// Funções disponíveis após o require:
//   json_out(array $dados, int $codigo = 200)         -> responde JSON e encerra
//   corpo_json(): array                               -> corpo da requisição (JSON)
//   csrf_token(): string                              -> token CSRF da sessão
//   exigir_csrf(): void                               -> valida o header X-CSRF-Token (escritas)
//   exigir_login(): array                             -> usuário logado ou 401 JSON
//   registrar_log(PDO $pdo, ?int $usuarioId, string $acao, string $detalhe = ''): void
//   $pdo                                              -> conexão PDO (exceções ativadas)

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Erros: nunca expor detalhes ao navegador; registrar no log do PHP.
// ---------------------------------------------------------------------------
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    error_log('[MocapWeb] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['erro' => 'Erro interno no servidor. Avise a equipe de TI do CECAPE.'], JSON_UNESCAPED_UNICODE);
    exit;
});

// ---------------------------------------------------------------------------
// Configuração (api/config.php)
// ---------------------------------------------------------------------------
$configArquivo = __DIR__ . '/config.php';
if (!is_file($configArquivo)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Configuração ausente: copie api/config.example.php para api/config.php e preencha os dados do banco.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $configArquivo;
if (!is_array($cfg)) {
    // Compatibilidade com config.php antigo baseado em constantes.
    $cfg = [
        'host'    => defined('DB_HOST') ? DB_HOST : 'localhost',
        'db'      => defined('DB_NAME') ? DB_NAME : '',
        'user'    => defined('DB_USER') ? DB_USER : '',
        'pass'    => defined('DB_PASS') ? DB_PASS : '',
        'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
    ];
}

// ---------------------------------------------------------------------------
// Conexão PDO
// ---------------------------------------------------------------------------
$dsn = $cfg['dsn'] ?? sprintf('mysql:host=%s;dbname=%s;charset=%s',
    $cfg['host'] ?? 'localhost', $cfg['db'] ?? '', $cfg['charset'] ?? 'utf8mb4');

$pdo = new PDO($dsn, (string)($cfg['user'] ?? ''), (string)($cfg['pass'] ?? ''), [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
    // Somente para testes locais: SQLite não tem NOW().
    $pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
}

// ---------------------------------------------------------------------------
// Sessão (cookie MOCAPSESS: HttpOnly, SameSite=Lax, Secure sob HTTPS)
// ---------------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
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

// ---------------------------------------------------------------------------
// Auxiliares
// ---------------------------------------------------------------------------
function json_out(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function corpo_json(): array
{
    $bruto = file_get_contents('php://input');
    if ($bruto === false || trim($bruto) === '') {
        return [];
    }
    $dados = json_decode($bruto, true);
    return is_array($dados) ? $dados : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Exige o header X-CSRF-Token igual ao token da sessão (toda escrita: POST/PUT/DELETE). */
function exigir_csrf(): void
{
    $enviado = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $sessao  = (string)($_SESSION['csrf'] ?? '');
    if ($sessao === '' || $enviado === '' || !hash_equals($sessao, $enviado)) {
        json_out(['erro' => 'Sessão expirada ou token inválido. Recarregue a página e tente novamente.'], 403);
    }
}

/** Usuário logado (id, nome, email, papel) ou responde 401 JSON. */
function exigir_login(): array
{
    $u = $_SESSION['usuario'] ?? null;
    if (!is_array($u) || empty($u['id'])) {
        json_out(['erro' => 'Não autenticado. Faça login.'], 401);
    }
    return $u;
}

/** Grava uma linha em logs_acesso (quem, quando, o quê). Nunca interrompe o fluxo. */
function registrar_log(PDO $pdo, ?int $usuarioId, string $acao, string $detalhe = ''): void
{
    try {
        $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $st = $pdo->prepare('INSERT INTO logs_acesso (usuario_id, acao, detalhe, ip, user_agent, criado_em) VALUES (?, ?, ?, ?, ?, NOW())');
        $st->execute([$usuarioId, mb_substr($acao, 0, 40), mb_substr($detalhe, 0, 255), mb_substr($ip, 0, 45), $ua]);
    } catch (Throwable $e) {
        error_log('[MocapWeb] falha ao registrar log: ' . $e->getMessage());
    }
}
