<?php
/**
 * api/criar_admin.php — cria o PRIMEIRO administrador do MocapWeb.
 * Só funciona enquanto a tabela usuarios estiver vazia.
 * Depois de usar, APAGUE este arquivo do servidor.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require_once __DIR__ . '/dominio.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');

$total = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$msg = '';
$ok  = false;

if ($total === 0 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nome  = trim((string)($_POST['nome'] ?? ''));
    $email = email_institucional_normalizar((string)($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    if ($nome === '' || mb_strlen($nome) > 120) {
        $msg = 'Informe o nome.';
    } elseif (!email_institucional_valido($email)) {
        $msg = 'Use um e-mail institucional @' . MOCAP_DOMINIO_EMAIL . '.';
    } elseif (strlen($senha) < 8 || strlen($senha) > 72) {
        $msg = 'A senha deve ter entre 8 e 72 caracteres.';
    } else {
        $st = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, papel, ativo, tentativas_login, bloqueado_ate, criado_em) VALUES (?, ?, ?, ?, 1, 0, NULL, NOW())');
        $st->execute([$nome, $email, password_hash($senha, PASSWORD_BCRYPT), 'admin']);
        registrar_log($pdo, (int)$pdo->lastInsertId(), 'admin_inicial', $email);
        $ok  = true;
        $msg = 'Administrador criado. Apague este arquivo (api/criar_admin.php) do servidor agora e entre em login.php.';
        $total = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Criar administrador — MoCap Web · CECAPE</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;
         font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#e6ebf5;
         background:radial-gradient(ellipse at 30% 40%, rgba(29,78,216,.18) 0%, transparent 60%), #0b1220; }
  .card { width:100%; max-width:420px; background:#121a2b; border:1px solid #273042; border-radius:16px; padding:28px; }
  h1 { font-size:20px; margin:0 0 6px; } p { color:#93a0b8; font-size:13px; line-height:1.5; }
  label { display:block; font-size:13px; color:#93a0b8; margin:12px 0 6px; }
  input { width:100%; box-sizing:border-box; padding:11px 13px; background:#1a1f2b; color:#e6ebf5; border:1px solid #273042; border-radius:10px; font-size:15px; }
  button { width:100%; margin-top:18px; padding:12px; background:#00e5a0; color:#05261b; border:0; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; }
  .msg { margin-top:14px; padding:12px; border-radius:10px; font-size:14px; }
  .erro { background:rgba(255,74,110,.1); border:1px solid rgba(255,74,110,.35); color:#ff4a6e; }
  .ok   { background:rgba(0,229,160,.1); border:1px solid rgba(0,229,160,.35); color:#00e5a0; }
  a { color:#00e5a0; }
</style>
</head>
<body>
<div class="card">
  <h1>Criar administrador</h1>
<?php if ($total > 0 && !$ok): ?>
  <p class="msg erro">Já existe usuário cadastrado. Este arquivo não pode mais ser usado: apague <code>api/criar_admin.php</code> do servidor.</p>
  <p><a href="../login.php">Ir para o login</a></p>
<?php else: ?>
  <?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'erro' ?>"><?= $e($msg) ?></p><?php endif; ?>
  <?php if (!$ok): ?>
  <p>Primeiro acesso: crie a conta do administrador. Só e-mails <strong>@<?= $e(MOCAP_DOMINIO_EMAIL) ?></strong>.</p>
  <form method="post" autocomplete="off">
    <label for="nome">Nome</label>
    <input id="nome" name="nome" required maxlength="120" value="<?= $e((string)($_POST['nome'] ?? '')) ?>">
    <label for="email">E-mail institucional</label>
    <input id="email" name="email" type="email" required placeholder="nome@<?= $e(MOCAP_DOMINIO_EMAIL) ?>" value="<?= $e((string)($_POST['email'] ?? '')) ?>">
    <label for="senha">Senha (mínimo 8 caracteres)</label>
    <input id="senha" name="senha" type="password" required minlength="8" maxlength="72">
    <button type="submit">Criar administrador</button>
  </form>
  <?php else: ?>
  <p><a href="../login.php">Ir para o login</a></p>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
