<?php
declare(strict_types=1);
require __DIR__ . '/api/db.php';
// Já logado? Vai direto para o destino.
$dest = ($_GET['r'] ?? '') === 'admin.php' ? 'admin.php' : 'index.html';
if (!empty($_SESSION['usuario'])) { header("Location: $dest"); exit; }
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Entrar — MoCap Web CECAPE</title>
<link rel="icon" href="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png">
<style>
  :root { --bg:#0a0c10; --surface2:#1a1f2b; --border:rgba(255,255,255,0.1); --accent:#00e5a0; --text:#e8eaf0; --text2:#8890a4; --red:#ff4a6e; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body {
    background:#0a0c10 radial-gradient(ellipse at 30% 30%, rgba(29,78,216,.18) 0%, transparent 60%);
    color:var(--text); font-family:'Inter',system-ui,sans-serif;
    min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;
  }
  .card {
    width:100%; max-width:380px; background:var(--surface2);
    border:1px solid var(--border); border-radius:14px; padding:32px;
    display:flex; flex-direction:column; gap:14px;
  }
  .logos { display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:6px; }
  .logos img { height:38px; object-fit:contain; }
  .logos .div { width:1px; height:30px; background:rgba(255,255,255,0.15); }
  h1 { font-size:18px; text-align:center; font-weight:600; }
  h1 span { color:var(--accent); }
  p.sub { font-size:12px; color:var(--text2); text-align:center; margin-top:-6px; }
  label { font-size:12px; color:var(--text2); font-weight:600; }
  input {
    width:100%; padding:12px; border-radius:8px; margin-top:4px;
    border:1px solid var(--border); background:var(--bg); color:var(--text); font-size:14px;
  }
  input:focus { outline:2px solid rgba(0,229,160,0.4); border-color:var(--accent); }
  button {
    padding:13px; border:none; border-radius:8px; background:var(--accent);
    color:#0a0c10; font-weight:700; font-size:14px; cursor:pointer; margin-top:4px;
  }
  button:hover { filter:brightness(1.1); }
  button:disabled { opacity:0.5; cursor:wait; }
  .erro {
    display:none; padding:10px 12px; border-radius:8px; font-size:12.5px; line-height:1.5;
    background:rgba(255,74,110,0.12); border:1px solid rgba(255,74,110,0.3); color:var(--red);
  }
  footer { margin-top:8px; font-size:10.5px; color:var(--text2); text-align:center; line-height:1.6; }
</style>
</head>
<body>
  <form class="card" id="frm">
    <div class="logos">
      <img src="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-Prefeitura---SEEDUC.png" alt="SEEDUC" onerror="this.style.display='none'">
      <div class="div"></div>
      <img src="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png" alt="CECAPE" onerror="this.style.display='none'">
    </div>
    <h1>Mocap<span>Web</span> CECAPE</h1>
    <p class="sub">Sistema de captura de movimentos — acesso restrito</p>
    <div class="erro" id="erro"></div>
    <div>
      <label for="email">E-mail</label>
      <input id="email" type="email" autocomplete="username" required autofocus>
    </div>
    <div>
      <label for="senha">Senha</label>
      <input id="senha" type="password" autocomplete="current-password" required>
    </div>
    <button id="btn">Entrar</button>
    <footer>CECAPE — Centro de Capacitação dos Profissionais da Educação<br>Dra. Zilda Arns · São Caetano do Sul</footer>
  </form>
<script>
const frm = document.getElementById('frm');
const erro = document.getElementById('erro');
const btn = document.getElementById('btn');
const destino = <?php echo json_encode($dest); ?>;
frm.addEventListener('submit', async e => {
  e.preventDefault();
  erro.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Entrando...';
  try {
    const r = await fetch('api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: document.getElementById('email').value.trim(),
        senha: document.getElementById('senha').value
      })
    });
    const j = await r.json();
    if (r.ok && j.ok) { location.href = destino; return; }
    erro.textContent = j.erro || 'Falha no login.';
    erro.style.display = 'block';
  } catch(_) {
    erro.textContent = 'Não foi possível conectar ao servidor. Tente novamente.';
    erro.style.display = 'block';
  }
  btn.disabled = false; btn.textContent = 'Entrar';
});
</script>
</body>
</html>
