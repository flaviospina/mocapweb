<?php
declare(strict_types=1);
require __DIR__ . '/api/db.php';
if (empty($_SESSION['usuario'])) { header('Location: login.php?r=alterar-senha.php'); exit; }
$usuario = $_SESSION['usuario'];
$csrf = csrf_token();
// Obrigatório (primeiro acesso) ou voluntário (link "Alterar senha")
$obrigatorio = false;
try {
  $st = $pdo->prepare('SELECT precisa_trocar_senha FROM usuarios WHERE id = ?');
  $st->execute([$usuario['id']]);
  $obrigatorio = (int)$st->fetchColumn() === 1;
} catch (PDOException $e) { /* migração pendente: tratar como voluntário */ }
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Alterar senha — MoCap Web CECAPE</title>
<link rel="icon" href="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png">
<style>
  :root { --bg:#0a0c10; --surface2:#1a1f2b; --border:rgba(255,255,255,0.1); --accent:#00e5a0; --text:#e8eaf0; --text2:#8890a4; --red:#ff4a6e; --yellow:#ffc947; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:#0a0c10 radial-gradient(ellipse at 30% 30%, rgba(29,78,216,.18) 0%, transparent 60%); color:var(--text); font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .card { width:100%; max-width:420px; background:var(--surface2); border:1px solid var(--border); border-radius:14px; padding:32px; display:flex; flex-direction:column; gap:14px; }
  .logos { display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:4px; }
  .logos img { height:38px; object-fit:contain; }
  h1 { font-size:18px; text-align:center; font-weight:600; }
  h1 span { color:var(--accent); }
  p.sub { font-size:12.5px; color:var(--text2); text-align:center; line-height:1.6; }
  .primeiro { padding:10px 12px; border-radius:8px; font-size:12.5px; line-height:1.55; background:rgba(255,201,71,0.1); border:1px solid rgba(255,201,71,0.35); color:var(--yellow); }
  label { font-size:12px; color:var(--text2); font-weight:600; }
  .campo { position:relative; }
  input { width:100%; padding:12px 64px 12px 12px; border-radius:8px; margin-top:4px; border:1px solid var(--border); background:var(--bg); color:var(--text); font-size:14px; }
  input:focus { outline:2px solid rgba(0,229,160,0.4); border-color:var(--accent); }
  .mostrar { position:absolute; right:12px; top:50%; transform:translateY(-10%); background:none; border:none; color:var(--accent); font-size:12px; font-weight:600; cursor:pointer; }
  .regras { font-size:11.5px; color:var(--text2); line-height:1.6; padding-left:16px; }
  .regras li.ok { color:var(--accent); }
  button.enviar { padding:13px; border:none; border-radius:8px; background:var(--accent); color:#0a0c10; font-weight:700; font-size:14px; cursor:pointer; margin-top:4px; }
  button.enviar:hover { filter:brightness(1.1); }
  button.enviar:disabled { opacity:0.5; cursor:wait; }
  .msg { display:none; padding:10px 12px; border-radius:8px; font-size:12.5px; line-height:1.5; }
  .msg.erro { background:rgba(255,74,110,0.12); border:1px solid rgba(255,74,110,0.3); color:var(--red); }
  .msg.ok { background:rgba(0,229,160,0.1); border:1px solid rgba(0,229,160,0.3); color:var(--accent); }
  a.voltar { font-size:12px; color:var(--text2); text-align:center; text-decoration:none; }
  a.voltar:hover { color:var(--accent); }
  footer { margin-top:6px; font-size:10.5px; color:var(--text2); text-align:center; line-height:1.6; }
</style>
</head>
<body>
  <form class="card" id="frm" autocomplete="off" novalidate>
    <div class="logos">
      <img src="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png" alt="CECAPE" onerror="this.style.display='none'">
    </div>
    <h1>Mocap<span>Web</span> · Alterar senha</h1>
    <p class="sub">Olá, <b><?php echo htmlspecialchars($usuario['nome']); ?></b> (<?php echo htmlspecialchars($usuario['email']); ?>)</p>
    <?php if ($obrigatorio): ?>
    <div class="primeiro">🔐 <b>Primeiro acesso:</b> por segurança, defina uma senha pessoal antes de usar o sistema. Na "senha atual", digite a senha inicial que você recebeu.</div>
    <?php endif; ?>
    <div class="msg erro" id="erro"></div>
    <div class="msg ok" id="okmsg"></div>
    <div>
      <label for="atual">Senha atual</label>
      <div class="campo"><input id="atual" type="password" autocomplete="current-password" required autofocus><button type="button" class="mostrar" data-alvo="atual">mostrar</button></div>
    </div>
    <div>
      <label for="nova">Nova senha</label>
      <div class="campo"><input id="nova" type="password" autocomplete="new-password" required minlength="8"><button type="button" class="mostrar" data-alvo="nova">mostrar</button></div>
    </div>
    <div>
      <label for="conf">Confirmar nova senha</label>
      <div class="campo"><input id="conf" type="password" autocomplete="new-password" required minlength="8"><button type="button" class="mostrar" data-alvo="conf">mostrar</button></div>
    </div>
    <ul class="regras">
      <li id="r1">Pelo menos 8 caracteres</li>
      <li id="r2">Letras e números</li>
      <li id="r3">Diferente da senha inicial</li>
      <li id="r4">Confirmação igual à nova senha</li>
    </ul>
    <button class="enviar" id="btn">Salvar nova senha</button>
    <?php if (!$obrigatorio): ?><a class="voltar" href="index.html">← Voltar ao sistema sem alterar</a><?php endif; ?>
    <footer>CECAPE — Centro de Capacitação dos Profissionais da Educação<br>Dra. Zilda Arns · São Caetano do Sul</footer>
  </form>
<script>
const CSRF = <?php echo json_encode($csrf); ?>;
const $ = id => document.getElementById(id);
document.querySelectorAll('.mostrar').forEach(b => b.addEventListener('click', () => {
  const i = $(b.dataset.alvo); const v = i.type === 'password'; i.type = v ? 'text' : 'password'; b.textContent = v ? 'ocultar' : 'mostrar';
}));
function regras() {
  const n = $('nova').value, c = $('conf').value;
  const ok = [n.length >= 8, /[A-Za-z]/.test(n) && /\d/.test(n), n !== '' && n.toLowerCase() !== 'scseduca', n !== '' && n === c];
  ok.forEach((v, i) => $('r' + (i+1)).className = v ? 'ok' : '');
  return ok.every(Boolean);
}
['nova', 'conf'].forEach(id => $(id).addEventListener('input', regras));
$('frm').addEventListener('submit', async e => {
  e.preventDefault();
  $('erro').style.display = 'none'; $('okmsg').style.display = 'none';
  if (!$('atual').value) { $('erro').textContent = 'Informe a senha atual.'; $('erro').style.display = 'block'; $('atual').focus(); return; }
  if (!regras()) { $('erro').textContent = 'A nova senha ainda não atende às regras abaixo.'; $('erro').style.display = 'block'; return; }
  const btn = $('btn'); btn.disabled = true; btn.textContent = 'Salvando...';
  try {
    const r = await fetch('api/alterar_senha.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ senha_atual: $('atual').value, nova_senha: $('nova').value, confirmar: $('conf').value })
    });
    if (r.status === 401 && !(await r.clone().json().catch(() => ({}))).erro) { location.href = 'login.php?r=alterar-senha.php'; return; }
    const j = await r.json();
    if (r.ok && j.ok) {
      $('okmsg').textContent = '✅ Senha alterada! Entrando no sistema...'; $('okmsg').style.display = 'block';
      setTimeout(() => { location.href = j.redirecionar || 'index.html'; }, 1200);
      return;
    }
    $('erro').textContent = j.erro || 'Não foi possível alterar a senha.'; $('erro').style.display = 'block';
  } catch(_) {
    $('erro').textContent = 'Não foi possível conectar ao servidor. Tente novamente.'; $('erro').style.display = 'block';
  }
  btn.disabled = false; btn.textContent = 'Salvar nova senha';
});
</script>
</body>
</html>
