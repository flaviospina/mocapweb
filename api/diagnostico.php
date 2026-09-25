<?php
/**
 * DIAGNÓSTICO DA CONEXÃO — abra no navegador: https://seusite/mocapweb/api/diagnostico.php
 * Mostra o que está faltando SEM expor a senha. APAGUE este arquivo depois de resolver.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

$linhas = [];
// closures com "use (&$linhas)": arrow functions capturariam a lista por cópia e ela ficaria vazia
$ok   = function (string $t) use (&$linhas): void { $linhas[] = ['ok', $t]; };
$erro = function (string $t) use (&$linhas): void { $linhas[] = ['erro', $t]; };
$info = function (string $t) use (&$linhas): void { $linhas[] = ['info', $t]; };

$info('PHP ' . PHP_VERSION . ' · extensão pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'presente' : 'AUSENTE (ative no cPanel → Select PHP Version)'));

// 1) Arquivo de configuração
$configFile = __DIR__ . '/config.php';
$envFile = dirname(__DIR__) . '/.env';
$cfg = [];
if (is_file($configFile)) {
  $lido = require $configFile;
  if (is_array($lido)) { $cfg = $lido; $ok('api/config.php encontrado e retorna um array.'); }
  else $erro('api/config.php encontrado, mas NÃO retorna um array (precisa começar com "return [" ... "];").');
} elseif (is_file($envFile)) {
  foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $line, 2)); $cfg[$k] = trim($v, "\"'");
  }
  $ok('.env encontrado na raiz do sistema.');
} else {
  $erro('Nenhuma configuração: falta api/config.php (copie de api/config.example.php).');
}

$pega = function (array $chaves) use ($cfg): string {
  foreach ($chaves as $k) if (isset($cfg[$k]) && trim((string)$cfg[$k]) !== '') return trim((string)$cfg[$k]);
  return '';
};
$host = $pega(['host', 'DB_HOST']) ?: 'localhost';
$db   = $pega(['dbname', 'db', 'database', 'DB_NAME', 'banco']);
$user = $pega(['user', 'usuario', 'DB_USER', 'username']);
$pass = $pega(['pass', 'senha', 'password', 'DB_PASS']);

$info('Chaves presentes no arquivo: ' . (count($cfg) ? implode(', ', array_keys($cfg)) : 'nenhuma'));
$info("host = '{$host}'");
if ($db === '') $erro("Nome do banco VAZIO → é exatamente isso que causa o erro 1046 \"No database selected\". Use a chave 'dbname' com o nome completo do cPanel, ex.: itthri79_mocapweb.");
else { $ok("dbname = '{$db}'"); if (!str_contains($db, '_')) $erro("O nome '{$db}' não tem o prefixo do cPanel. No HostGator o banco chama-se prefixo_nome (ex.: itthri79_{$db})."); }
if ($user === '') $erro("Usuário do banco VAZIO (chave 'user')."); else { $ok("user = '{$user}'"); if (!str_contains($user, '_')) $erro("O usuário '{$user}' não tem o prefixo do cPanel (ex.: itthri79_{$user})."); }
$info('senha: ' . ($pass === '' ? 'VAZIA' : str_repeat('•', min(12, strlen($pass))) . ' (' . strlen($pass) . ' caracteres)'));

// 2) Conexão
if ($db !== '' && $user !== '' && extension_loaded('pdo_mysql')) {
  try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sel = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $ok('Conexão com o MySQL aberta.');
    if ($sel === '') $erro('Conectou, mas NENHUM banco ficou selecionado (SELECT DATABASE() vazio). Confira o nome do banco.');
    else $ok("Banco selecionado: '{$sel}'.");
    // 3) Tabelas
    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['usuarios', 'eventos', 'capturas', 'logs_acesso'] as $t) {
      in_array($t, $tabelas, true) ? $ok("Tabela '{$t}' existe.") : $erro("Tabela '{$t}' NÃO existe → importe db/schema.sql no phpMyAdmin (selecione o banco '{$db}' antes de importar).");
    }
    if (in_array('usuarios', $tabelas, true)) {
      $n = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
      $n > 0 ? $ok("{$n} usuário(s) cadastrado(s).") : $erro('Nenhum usuário cadastrado → abra api/criar_admin.php para criar o primeiro administrador.');
    }
  } catch (PDOException $e) {
    $codigo = (int)($e->errorInfo[1] ?? 0);
    $erro("Falha na conexão [código {$codigo}]: " . htmlspecialchars($e->getMessage()));
    if (in_array($codigo, [1044, 1045], true)) $erro('Usuário/senha incorretos OU o usuário não foi adicionado ao banco (cPanel → MySQL Databases → "Add User To Database" → ALL PRIVILEGES).');
    if ($codigo === 1049) $erro("O banco '{$db}' não existe. Veja o nome exato em cPanel → MySQL Databases → Current Databases.");
  }
}
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Diagnóstico — MoCapWeb</title>
<style>body{font-family:system-ui;background:#0a0c10;color:#e8eaf0;padding:30px;max-width:900px;margin:auto;line-height:1.6}
h1{color:#00e5a0;font-size:20px}.l{padding:8px 12px;border-radius:8px;margin:6px 0;font-size:14px}
.ok{background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.3)}.erro{background:rgba(255,74,110,.12);border:1px solid rgba(255,74,110,.35);color:#ffb3c1}
.info{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#8890a4}
.aviso{margin-top:24px;padding:12px;border:1px solid #ffc947;border-radius:8px;color:#ffc947;font-size:13px}</style></head>
<body><h1>Diagnóstico da conexão — MoCapWeb</h1>
<?php foreach ($linhas as [$tipo, $txt]) echo "<div class=\"l {$tipo}\">" . ($tipo === 'ok' ? '✅ ' : ($tipo === 'erro' ? '❌ ' : 'ℹ️ ')) . $txt . "</div>"; ?>
<div class="aviso">⚠️ Depois de resolver, <b>apague este arquivo</b> (api/diagnostico.php) do servidor.</div>
</body></html>
