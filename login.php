<?php
// login.php
// Tela de login do MocapWeb (CECAPE Dra. Zilda Arns).
// Aceita somente e-mails institucionais @scseduca.com.br.
// Autenticação continua sendo feita por api/login.php (sessão MOCAPSESS + CSRF);
// esta página valida o domínio antes de enviar e exibe as mensagens da API.

declare(strict_types=1);

require __DIR__ . '/api/dominio.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Destino após o login: só nomes simples de arquivo da mesma pasta (ex.: index.html, admin.php).
$destino = 'index.html';
if (isset($_GET['r']) && is_string($_GET['r']) && preg_match('/^[A-Za-z0-9_\-]+\.(html|php)$/', $_GET['r'])) {
    $destino = $_GET['r'];
}

$dominio = MOCAP_DOMINIO_EMAIL;
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b1220">
<meta name="color-scheme" content="dark">
<title>Entrar — MoCap Web · CECAPE</title>
<link rel="icon" type="image/png" href="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png">
<style>
  :root {
    --bg: #0b1220;
    --surface: #121a2b;
    --surface2: #1a1f2b;
    --border: #273042;
    --text: #e6ebf5;
    --muted: #93a0b8;
    --accent: #00e5a0;
    --accent-dark: #00b37d;
    --blue: #1d4ed8;
    --red: #ff4a6e;
    --yellow: #ffc947;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: var(--text);
    background:
      radial-gradient(ellipse at 30% 40%, rgba(29,78,216,.18) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 80%, rgba(0,229,160,.10) 0%, transparent 55%),
      var(--bg);
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
  }
  .card {
    width: 100%; max-width: 420px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px 28px 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,.45);
  }
  .brand { text-align: center; margin-bottom: 22px; }
  .brand img { height: 56px; width: auto; object-fit: contain; }
  .brand h1 { font-size: 22px; margin: 12px 0 4px; letter-spacing: .3px; }
  .brand h1 span { color: var(--accent); }
  .brand p { margin: 0; color: var(--muted); font-size: 13px; }

  label { display: block; font-size: 13px; color: var(--muted); margin: 14px 0 6px; }
  .field { position: relative; }
  input {
    width: 100%; padding: 12px 14px;
    background: var(--surface2); color: var(--text);
    border: 1px solid var(--border); border-radius: 10px;
    font-size: 15px; outline: none;
    transition: border-color .15s, box-shadow .15s;
  }
  input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,229,160,.15); }
  input:invalid:not(:placeholder-shown) { border-color: var(--red); }
  input[type="password"], input[type="text"].senha { padding-right: 48px; }
  .toggle {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: transparent; border: 0; color: var(--muted); cursor: pointer;
    font-size: 13px; padding: 6px 8px; border-radius: 6px;
  }
  .toggle:hover { color: var(--text); background: rgba(255,255,255,.06); }
  .hint { font-size: 12px; color: var(--muted); margin-top: 6px; }
  .hint strong { color: var(--accent); font-weight: 600; }

  .btn {
    width: 100%; margin-top: 22px; padding: 13px;
    background: var(--accent); color: #05261b;
    border: 0; border-radius: 10px; font-size: 15px; font-weight: 700;
    cursor: pointer; transition: background .15s, transform .05s;
  }
  .btn:hover { background: var(--accent-dark); color: #fff; }
  .btn:active { transform: translateY(1px); }
  .btn[disabled] { opacity: .6; cursor: wait; }

  .msg {
    display: none; margin-top: 16px; padding: 12px 14px;
    border-radius: 10px; font-size: 14px; line-height: 1.45;
  }
  .msg.erro  { display: block; background: rgba(255,74,110,.10); border: 1px solid rgba(255,74,110,.35); color: var(--red); }
  .msg.aviso { display: block; background: rgba(255,201,71,.10); border: 1px solid rgba(255,201,71,.35); color: var(--yellow); }
  .msg.ok    { display: block; background: rgba(0,229,160,.10); border: 1px solid rgba(0,229,160,.35); color: var(--accent); }

  .links { display: flex; justify-content: space-between; gap: 12px; margin-top: 18px; font-size: 13px; }
  .links a, .links button { color: var(--muted); background: none; border: 0; padding: 0; cursor: pointer; font-size: 13px; text-decoration: none; }
  .links a:hover, .links button:hover { color: var(--accent); text-decoration: underline; }

  .foot { text-align: center; color: var(--muted); font-size: 11px; margin-top: 22px; line-height: 1.5; }
  noscript .msg { display: block; }
</style>
</head>
<body>
  <main class="card" role="main">
    <div class="brand">
      <img src="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png" alt="CECAPE">
      <h1>MoCap <span>Web</span></h1>
      <p>Sistema de captura de movimentos · CECAPE Dra. Zilda Arns</p>
    </div>

    <form id="formLogin" method="post" action="api/login.php" novalidate autocomplete="on">
      <label for="email">E-mail institucional</label>
      <div class="field">
        <input type="email" id="email" name="email" required
               placeholder="nome@<?= $e($dominio) ?>"
               pattern="[A-Za-z0-9._%+\-]+@<?= $e(str_replace('.', '\\.', $dominio)) ?>"
               title="Use seu e-mail institucional @<?= $e($dominio) ?>"
               autocomplete="username" inputmode="email" spellcheck="false" autofocus>
      </div>
      <div class="hint">Somente e-mails <strong>@<?= $e($dominio) ?></strong> têm acesso ao sistema.</div>

      <label for="senha">Senha</label>
      <div class="field">
        <input type="password" id="senha" name="senha" required minlength="1"
               placeholder="Sua senha" autocomplete="current-password">
        <button type="button" class="toggle" id="btnVerSenha" aria-label="Mostrar senha">mostrar</button>
      </div>

      <button type="submit" class="btn" id="btnEntrar">Entrar</button>

      <div id="msg" class="msg" role="alert" aria-live="polite"></div>
    </form>

    <div class="links">
      <button type="button" id="btnEsqueci">Esqueci minha senha</button>
      <a href="apresentacao.html" target="_blank" rel="noopener">Tutorial de uso</a>
    </div>

    <noscript>
      <div class="msg aviso">Este sistema precisa de JavaScript ativado para entrar.</div>
    </noscript>

    <div class="foot">
      CECAPE - Centro de Capacitação de Profissionais da Educação<br>
      Secretaria Municipal de Educação de São Caetano do Sul
    </div>
  </main>

<script>
(function () {
  'use strict';

  var DOMINIO = <?= json_encode($dominio, JSON_UNESCAPED_UNICODE) ?>;
  var DESTINO = <?= json_encode($destino, JSON_UNESCAPED_UNICODE) ?>;

  var form   = document.getElementById('formLogin');
  var email  = document.getElementById('email');
  var senha  = document.getElementById('senha');
  var btn    = document.getElementById('btnEntrar');
  var msg    = document.getElementById('msg');

  function mostrar(texto, tipo) {
    msg.textContent = texto;
    msg.className = 'msg ' + (tipo || 'erro');
  }
  function limpar() { msg.textContent = ''; msg.className = 'msg'; }

  // Validação do domínio institucional (mesma regra de api/dominio.php).
  function emailInstitucional(valor) {
    valor = (valor || '').trim().toLowerCase();
    if (!valor) return false;
    var re = new RegExp('^[a-z0-9._%+\\-]+@' + DOMINIO.replace(/\./g, '\\.') + '$');
    return re.test(valor);
  }

  // Já logado? Vai direto para o destino (mesma checagem que o index.html faz).
  fetch('api/me.php', { cache: 'no-store' })
    .then(function (r) { if (r.ok) location.replace(DESTINO); })
    .catch(function () { /* sem servidor ou sem sessão: fica na tela de login */ });

  // Mostrar / ocultar senha
  document.getElementById('btnVerSenha').addEventListener('click', function () {
    var visivel = senha.type === 'text';
    senha.type = visivel ? 'password' : 'text';
    senha.classList.toggle('senha', !visivel);
    this.textContent = visivel ? 'mostrar' : 'ocultar';
    this.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
    senha.focus();
  });

  document.getElementById('btnEsqueci').addEventListener('click', function () {
    mostrar('Procure o administrador do sistema no CECAPE: ele pode cadastrar uma nova senha para você.', 'aviso');
  });

  email.addEventListener('input', function () {
    if (msg.classList.contains('erro')) limpar();
  });

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    limpar();

    var valorEmail = email.value.trim().toLowerCase();
    email.value = valorEmail;

    if (!emailInstitucional(valorEmail)) {
      mostrar('Use seu e-mail institucional @' + DOMINIO + ' para entrar.', 'erro');
      email.focus();
      return;
    }
    if (!senha.value) {
      mostrar('Digite sua senha.', 'erro');
      senha.focus();
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Entrando…';
    try {
      var r = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        cache: 'no-store',
        body: JSON.stringify({ email: valorEmail, senha: senha.value })
      });
      var j = null;
      try { j = await r.json(); } catch (_) { j = null; }

      if (r.ok && j && j.ok !== false && !j.erro) {
        mostrar('Acesso autorizado. Abrindo o sistema…', 'ok');
        location.replace(DESTINO);
        return;
      }

      var texto = (j && j.erro) ? j.erro : '';
      if (!texto) {
        if (r.status === 401)      texto = 'E-mail ou senha inválidos.';
        else if (r.status === 422) texto = 'Use seu e-mail institucional @' + DOMINIO + '.';
        else if (r.status === 423 || r.status === 429) texto = 'Conta bloqueada temporariamente por excesso de tentativas. Aguarde 15 minutos e tente de novo.';
        else if (r.status >= 500)  texto = 'O servidor está indisponível no momento. Avise a equipe de TI do CECAPE.';
        else                       texto = 'Não foi possível entrar. Tente novamente.';
      }
      mostrar(texto, (r.status === 423 || r.status === 429) ? 'aviso' : 'erro');
      senha.value = '';
      senha.focus();
    } catch (_) {
      mostrar('Erro de conexão com o servidor. Verifique a internet e tente novamente.', 'erro');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Entrar';
    }
  });
})();
</script>
</body>
</html>
