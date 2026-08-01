<?php
/**
 * PRIMEIRO ACESSO — cria o primeiro administrador.
 * Só funciona enquanto a tabela `usuarios` estiver VAZIA.
 * Depois de criar o admin, APAGUE ESTE ARQUIVO do servidor.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

$total = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($total > 0) {
  http_response_code(403);
  die('Já existe usuário cadastrado. Por segurança, apague este arquivo (api/criar_admin.php) do servidor.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $nome  = trim((string)($_POST['nome'] ?? ''));
  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  $senha = (string)($_POST['senha'] ?? '');
  $erros = [];
  if ($nome === '' || mb_strlen($nome) > 120)          $erros[] = 'Informe o nome (até 120 caracteres).';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $erros[] = 'E-mail inválido.';
  if (strlen($senha) < 8)                              $erros[] = 'A senha precisa ter no mínimo 8 caracteres.';
  if (!$erros) {
    $st = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, papel) VALUES (?,?,?,\'admin\')');
    $st->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
    registrar_log($pdo, (int)$pdo->lastInsertId(), 'admin_criado', $email);
    die('<meta charset="utf-8"><body style="font-family:system-ui;background:#0a0c10;color:#e8eaf0;display:flex;align-items:center;justify-content:center;height:100vh;"><div style="max-width:480px;text-align:center;line-height:1.7;"><h2 style="color:#00e5a0;">✅ Administrador criado!</h2><p>Agora <b>apague o arquivo <code>api/criar_admin.php</code></b> do servidor e faça login em <a href="../login.php" style="color:#00e5a0;">login.php</a>.</p></div></body>');
  }
}
?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Criar administrador — MoCapWeb</title></head>
<body style="font-family:system-ui;background:#0a0c10;color:#e8eaf0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">
  <form method="post" style="background:#1a1f2b;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:28px;width:340px;display:flex;flex-direction:column;gap:12px;">
    <h2 style="margin:0;font-size:17px;">Primeiro acesso — criar administrador</h2>
    <?php if (!empty($erros)) foreach ($erros as $e) echo '<div style="color:#ff4a6e;font-size:13px;">• '.htmlspecialchars($e).'</div>'; ?>
    <input name="nome"  placeholder="Nome completo" required style="padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:#0a0c10;color:#e8eaf0;">
    <input name="email" type="email" placeholder="E-mail" required style="padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:#0a0c10;color:#e8eaf0;">
    <input name="senha" type="password" placeholder="Senha (mín. 8 caracteres)" required minlength="8" style="padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:#0a0c10;color:#e8eaf0;">
    <button style="padding:11px;border:none;border-radius:8px;background:#00e5a0;color:#0a0c10;font-weight:700;cursor:pointer;">Criar administrador</button>
    <small style="color:#8890a4;line-height:1.5;">Após criar, <b>apague este arquivo</b> (api/criar_admin.php) do servidor.</small>
  </form>
</body></html>
