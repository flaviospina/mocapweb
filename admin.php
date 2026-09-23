<?php
// admin.php — Painel administrativo do MocapWeb (CECAPE Dra. Zilda Arns)
// Exclusivo do perfil "admin": eventos, capturas salvas e usuários.
// Sem login: vai para login.php. Logado sem ser admin: 403 "Acesso restrito".
declare(strict_types=1);
require __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/guarda.php';
require_once __DIR__ . '/api/dominio.php';

$usuarioLogado = mocap_exigir_admin_pagina();
mocap_cabecalhos_protegidos();
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark">
<title>Admin — MoCap Web · CECAPE</title>
<link rel="icon" type="image/png" href="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png">
<style>
  :root { --bg:#0b1220; --surface:#121a2b; --surface2:#1a1f2b; --border:#273042; --text:#e6ebf5; --muted:#93a0b8;
          --accent:#00e5a0; --accent-dark:#00b37d; --red:#ff4a6e; --yellow:#ffc947; --blue:#4a9eff; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:var(--text); font-size:14px;
         background:radial-gradient(ellipse at 20% 0%, rgba(29,78,216,.16) 0%, transparent 55%), var(--bg); }
  header { position:sticky; top:0; z-index:5; display:flex; align-items:center; gap:14px; padding:12px 20px;
           background:rgba(11,18,32,.92); backdrop-filter:blur(6px); border-bottom:1px solid var(--border); }
  header h1 { font-size:18px; margin:0; } header h1 span { color:var(--accent); }
  header .sp { flex:1; } header .who { color:var(--muted); font-size:12px; }
  header a { color:var(--accent); text-decoration:none; font-size:13px; } header a.sair { color:var(--red); }
  nav.tabs { display:flex; gap:6px; padding:12px 20px 0; flex-wrap:wrap; }
  nav.tabs button { background:var(--surface2); color:var(--muted); border:1px solid var(--border); border-radius:10px 10px 0 0;
                    padding:10px 16px; cursor:pointer; font-size:14px; }
  nav.tabs button.on { color:var(--text); background:var(--surface); border-bottom-color:var(--surface); }
  main { padding:0 20px 40px; } section { display:none; background:var(--surface); border:1px solid var(--border); border-radius:0 12px 12px 12px; padding:18px; }
  section.on { display:block; }
  h2 { font-size:16px; margin:0 0 12px; } h3 { font-size:14px; margin:18px 0 8px; color:var(--muted); }
  .row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
  .f { display:flex; flex-direction:column; gap:4px; min-width:140px; flex:1; }
  .f label { font-size:12px; color:var(--muted); }
  input, select { padding:9px 11px; background:var(--surface2); color:var(--text); border:1px solid var(--border); border-radius:8px; font-size:14px; width:100%; }
  input:focus, select:focus { outline:none; border-color:var(--accent); }
  .btn { padding:9px 14px; border:0; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; background:var(--accent); color:#05261b; white-space:nowrap; }
  .btn:hover { background:var(--accent-dark); color:#fff; } .btn[disabled] { opacity:.6; cursor:wait; }
  .btn.sec { background:var(--surface2); color:var(--text); border:1px solid var(--border); }
  .btn.danger { background:transparent; color:var(--red); border:1px solid rgba(255,74,110,.4); }
  .btn.sm { padding:5px 9px; font-size:12px; }
  .tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:10px; margin:12px 0; }
  .tile { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:12px; }
  .tile .v { font-size:22px; font-weight:700; color:var(--accent); } .tile .l { font-size:11px; color:var(--muted); text-transform:uppercase; }
  .tw { overflow:auto; margin-top:10px; border:1px solid var(--border); border-radius:10px; }
  table { width:100%; border-collapse:collapse; font-size:13px; min-width:640px; }
  th, td { padding:8px 10px; border-bottom:1px solid var(--border); text-align:left; vertical-align:middle; white-space:nowrap; }
  th { background:var(--surface2); color:var(--muted); font-size:11px; text-transform:uppercase; cursor:pointer; user-select:none; position:sticky; top:0; }
  th.sorted::after { content:' ▲'; color:var(--accent); } th.sorted.desc::after { content:' ▼'; }
  tr:hover td { background:rgba(255,255,255,.025); }
  td.acts { text-align:right; } td.acts .btn { margin-left:4px; }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; background:var(--surface2); border:1px solid var(--border); }
  .badge.admin { color:var(--yellow); border-color:rgba(255,201,71,.4); } .badge.off { color:var(--red); border-color:rgba(255,74,110,.4); }
  .msg { display:none; margin:10px 0; padding:10px 12px; border-radius:8px; font-size:13px; }
  .msg.ok { display:block; background:rgba(0,229,160,.1); border:1px solid rgba(0,229,160,.35); color:var(--accent); }
  .msg.erro { display:block; background:rgba(255,74,110,.1); border:1px solid rgba(255,74,110,.35); color:var(--red); }
  .muted { color:var(--muted); } .empty { padding:18px; text-align:center; color:var(--muted); }
  @media (max-width:640px) { header { padding:10px 12px; } main, nav.tabs { padding-left:12px; padding-right:12px; } .f { min-width:100%; } }
</style>
</head>
<body>
<header>
  <h1>MoCap <span>Web</span> · Admin</h1>
  <div class="sp"></div>
  <span class="who">👤 <?= $e((string)$usuarioLogado['nome']) ?> (admin)</span>
  <a href="index.php">Sistema</a>
  <a href="api/logout.php" class="sair" id="btnSair">Sair</a>
</header>

<nav class="tabs">
  <button class="on" data-tab="capturas">Capturas</button>
  <button data-tab="eventos">Eventos</button>
  <button data-tab="usuarios">Usuários</button>
</nav>

<main>
  <!-- ═════════════ CAPTURAS ═════════════ -->
  <section id="tab-capturas" class="on">
    <h2>Capturas salvas</h2>
    <div class="row">
      <div class="f"><label>Evento</label><select id="fEvento"><option value="">Todos</option></select></div>
      <div class="f"><label>De</label><input type="date" id="fDe"></div>
      <div class="f"><label>Até</label><input type="date" id="fAte"></div>
      <div class="f"><label>Busca (participante, evento, professor)</label><input id="fBusca" placeholder="Ex.: Aluno 07"></div>
      <button class="btn" id="btnFiltrar">Filtrar</button>
      <button class="btn sec" id="btnLimpar">Limpar</button>
      <button class="btn sec" id="btnCsv">Exportar CSV</button>
    </div>
    <div class="tiles">
      <div class="tile"><div class="v" id="tCap">0</div><div class="l">Capturas</div></div>
      <div class="tile"><div class="v" id="tPart">0</div><div class="l">Participantes</div></div>
      <div class="tile"><div class="v" id="tTempo">0 min</div><div class="l">Tempo total</div></div>
      <div class="tile"><div class="v" id="tSim">—</div><div class="l">Semelhança média</div></div>
    </div>
    <div class="msg" id="msgCap"></div>
    <div class="tw"><table id="tabCap">
      <thead><tr>
        <th data-k="id">#</th><th data-k="criado_em">Salvo em</th><th data-k="evento_titulo">Evento</th><th data-k="data_evento">Data</th>
        <th data-k="participante">Participante</th><th data-k="usuario_nome">Professor(a)</th><th data-k="num_pessoas">Pessoas</th>
        <th data-k="duracao_ms">Duração</th><th data-k="quadros">Quadros</th><th data-k="modelo">Modelo</th>
        <th data-k="similaridade_media">Semelh.</th><th data-k="exercicio">Exercício</th><th data-k="repeticoes">Rep.</th><th></th>
      </tr></thead>
      <tbody></tbody>
    </table></div>
  </section>

  <!-- ═════════════ EVENTOS ═════════════ -->
  <section id="tab-eventos">
    <h2>Eventos (dia e horário das capturas)</h2>
    <form id="formEvento" class="row" autocomplete="off">
      <div class="f" style="flex:2"><label>Título</label><input id="evTitulo" required maxlength="150" placeholder="Ex.: Oficina de dança · 5º ano A"></div>
      <div class="f"><label>Data</label><input type="date" id="evData" required></div>
      <div class="f"><label>Início</label><input type="time" id="evIni" required></div>
      <div class="f"><label>Fim (opcional)</label><input type="time" id="evFim"></div>
      <div class="f"><label>Local (opcional)</label><input id="evLocal" maxlength="150" placeholder="Ex.: Sala A · CECAPE"></div>
      <button class="btn" type="submit" id="btnEvento">Criar evento</button>
    </form>
    <div class="msg" id="msgEv"></div>
    <div class="tw"><table id="tabEv">
      <thead><tr><th>#</th><th>Título</th><th>Data</th><th>Horário</th><th>Local</th><th>Capturas</th><th></th></tr></thead>
      <tbody></tbody>
    </table></div>
  </section>

  <!-- ═════════════ USUÁRIOS ═════════════ -->
  <section id="tab-usuarios">
    <h2>Usuários</h2>
    <p class="muted">Somente e-mails <strong>@<?= $e(MOCAP_DOMINIO_EMAIL) ?></strong>. O perfil <strong>admin</strong> é o único que acessa este painel.</p>
    <form id="formUsuario" class="row" autocomplete="off">
      <div class="f" style="flex:2"><label>Nome</label><input id="usNome" required maxlength="120"></div>
      <div class="f" style="flex:2"><label>E-mail institucional</label><input id="usEmail" type="email" required placeholder="nome@<?= $e(MOCAP_DOMINIO_EMAIL) ?>"></div>
      <div class="f"><label>Perfil</label><select id="usPapel"><option value="professor">professor</option><option value="admin">admin</option></select></div>
      <div class="f"><label>Senha inicial (mín. 8)</label><input id="usSenha" type="text" required minlength="8" maxlength="72"></div>
      <button class="btn" type="submit" id="btnUsuario">Criar usuário</button>
    </form>
    <div class="msg" id="msgUs"></div>
    <div class="tw"><table id="tabUs">
      <thead><tr><th>#</th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Situação</th><th>Último login</th><th></th></tr></thead>
      <tbody></tbody>
    </table></div>
  </section>
</main>

<script>
(function () {
  'use strict';
  const CSRF = <?= json_encode($csrf) ?>;
  const EU   = <?= json_encode((int)$usuarioLogado['id']) ?>;
  const DOMINIO = <?= json_encode(MOCAP_DOMINIO_EMAIL) ?>;

  const $ = (s, el = document) => el.querySelector(s);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const fmtData = d => d ? new Date(d + 'T00:00').toLocaleDateString('pt-BR') : '';
  const fmtDataHora = s => s ? new Date(s.replace(' ', 'T')).toLocaleString('pt-BR') : '';
  const fmtDur = ms => { const s = Math.round((ms||0)/1000); return `${Math.floor(s/60)}m ${String(s%60).padStart(2,'0')}s`; };
  const msg = (id, texto, ok) => { const el = $('#'+id); el.textContent = texto; el.className = 'msg ' + (ok ? 'ok' : 'erro'); if (ok) setTimeout(() => { el.className = 'msg'; }, 4000); };

  async function api(url, corpo) {
    const opt = corpo ? { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify(corpo) }
                      : { headers:{ 'X-CSRF-Token': CSRF } };
    opt.cache = 'no-store'; opt.credentials = 'same-origin';
    const r = await fetch(url, opt);
    if (r.status === 401) { location.replace('login.php?r=admin.php'); throw new Error('sessão'); }
    let j = null; try { j = await r.json(); } catch (_) {}
    if (!r.ok) throw new Error((j && j.erro) || `Erro ${r.status}`);
    return j;
  }

  // ── abas ──
  document.querySelectorAll('nav.tabs button').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('nav.tabs button').forEach(x => x.classList.toggle('on', x === b));
    document.querySelectorAll('main section').forEach(s => s.classList.toggle('on', s.id === 'tab-' + b.dataset.tab));
  }));

  $('#btnSair').addEventListener('click', async ev => {
    ev.preventDefault();
    try { await fetch('api/logout.php', { method:'POST', credentials:'same-origin', headers:{ 'X-CSRF-Token': CSRF } }); } catch (_) {}
    location.replace('login.php');
  });

  // ── eventos ──
  let eventos = [];
  async function carregarEventos() {
    const j = await api('api/eventos.php');
    eventos = j.eventos || [];
    const sel = $('#fEvento'); const atual = sel.value;
    sel.innerHTML = '<option value="">Todos</option>' + eventos.map(e => `<option value="${e.id}">${esc(e.titulo)} · ${fmtData(e.data_evento)}</option>`).join('');
    sel.value = atual;
    const tb = $('#tabEv tbody');
    tb.innerHTML = eventos.length ? eventos.map(e => `<tr>
      <td>${e.id}</td><td>${esc(e.titulo)}</td><td>${fmtData(e.data_evento)}</td>
      <td>${esc((e.hora_inicio||'').slice(0,5))}${e.hora_fim ? ' – ' + esc(e.hora_fim.slice(0,5)) : ''}</td>
      <td>${esc(e.local||'')}</td><td>${e.total_capturas}</td>
      <td class="acts"><button class="btn sm danger" data-del-ev="${e.id}" data-n="${e.total_capturas}">Excluir</button></td></tr>`).join('')
      : '<tr><td colspan="7" class="empty">Nenhum evento. Crie o primeiro acima.</td></tr>';
  }
  $('#formEvento').addEventListener('submit', async ev => {
    ev.preventDefault();
    const b = $('#btnEvento'); b.disabled = true;
    try {
      await api('api/eventos.php', { acao:'criar', titulo:$('#evTitulo').value.trim(), data_evento:$('#evData').value,
                                     hora_inicio:$('#evIni').value, hora_fim:$('#evFim').value, local:$('#evLocal').value.trim() });
      msg('msgEv', 'Evento criado.', true); ev.target.reset(); await carregarEventos();
    } catch (e) { msg('msgEv', e.message, false); } finally { b.disabled = false; }
  });
  $('#tabEv').addEventListener('click', async ev => {
    const b = ev.target.closest('[data-del-ev]'); if (!b) return;
    const n = +b.dataset.n;
    if (!confirm(`Excluir o evento #${b.dataset.delEv}${n ? ` e suas ${n} captura(s)` : ''}? Esta ação não pode ser desfeita.`)) return;
    try { await api('api/eventos.php', { acao:'excluir', id:+b.dataset.delEv }); msg('msgEv', 'Evento excluído.', true); await carregarEventos(); await carregarCapturas(); }
    catch (e) { msg('msgEv', e.message, false); }
  });

  // ── capturas ──
  let capturas = [], ordem = { k:'criado_em', desc:true };
  async function carregarCapturas() {
    const q = new URLSearchParams();
    if ($('#fEvento').value) q.set('evento_id', $('#fEvento').value);
    if ($('#fDe').value) q.set('de', $('#fDe').value);
    if ($('#fAte').value) q.set('ate', $('#fAte').value);
    if ($('#fBusca').value.trim()) q.set('busca', $('#fBusca').value.trim());
    try { const j = await api('api/capturas.php' + (q.toString() ? '?' + q : '')); capturas = j.capturas || []; }
    catch (e) { msg('msgCap', e.message, false); capturas = []; }
    desenharCapturas();
  }
  function desenharCapturas() {
    const k = ordem.k, dir = ordem.desc ? -1 : 1;
    const num = ['id','num_pessoas','duracao_ms','quadros','similaridade_media','repeticoes'].includes(k);
    const lista = [...capturas].sort((a, b) => {
      const x = a[k], y = b[k];
      if (x == null && y == null) return 0; if (x == null) return 1; if (y == null) return -1;
      return (num ? (+x - +y) : String(x).localeCompare(String(y), 'pt-BR')) * dir;
    });
    document.querySelectorAll('#tabCap th[data-k]').forEach(th => { th.classList.toggle('sorted', th.dataset.k === k); th.classList.toggle('desc', th.dataset.k === k && ordem.desc); });
    $('#tabCap tbody').innerHTML = lista.length ? lista.map(c => `<tr>
      <td>${c.id}</td><td>${fmtDataHora(c.criado_em)}</td><td>${esc(c.evento_titulo)}</td><td>${fmtData(c.data_evento)}</td>
      <td>${esc(c.participante)}</td><td>${esc(c.usuario_nome||'')}</td><td>${c.num_pessoas}</td><td>${fmtDur(c.duracao_ms)}</td>
      <td>${c.quadros}</td><td>${esc(c.modelo||'')}</td><td>${c.similaridade_media == null ? '—' : c.similaridade_media + '%'}</td>
      <td>${esc(c.exercicio||'')}</td><td>${c.repeticoes == null ? '' : c.repeticoes}</td>
      <td class="acts"><a class="btn sm sec" href="api/capturas.php?id=${c.id}&download=1">JSON</a><button class="btn sm danger" data-del-cap="${c.id}">Excluir</button></td></tr>`).join('')
      : '<tr><td colspan="14" class="empty">Nenhuma captura encontrada.</td></tr>';
    $('#tCap').textContent = lista.length;
    $('#tPart').textContent = new Set(lista.map(c => c.evento_id + '|' + c.participante.toLowerCase())).size;
    $('#tTempo').textContent = Math.round(lista.reduce((s, c) => s + (+c.duracao_ms||0), 0) / 60000) + ' min';
    const sims = lista.filter(c => c.similaridade_media != null);
    $('#tSim').textContent = sims.length ? Math.round(sims.reduce((s, c) => s + +c.similaridade_media, 0) / sims.length) + '%' : '—';
  }
  document.querySelectorAll('#tabCap th[data-k]').forEach(th => th.addEventListener('click', () => {
    ordem = { k: th.dataset.k, desc: ordem.k === th.dataset.k ? !ordem.desc : false }; desenharCapturas();
  }));
  $('#btnFiltrar').addEventListener('click', carregarCapturas);
  $('#fBusca').addEventListener('keydown', ev => { if (ev.key === 'Enter') carregarCapturas(); });
  $('#btnLimpar').addEventListener('click', () => { $('#fEvento').value=''; $('#fDe').value=''; $('#fAte').value=''; $('#fBusca').value=''; carregarCapturas(); });
  $('#tabCap').addEventListener('click', async ev => {
    const b = ev.target.closest('[data-del-cap]'); if (!b) return;
    if (!confirm(`Excluir a captura #${b.dataset.delCap}? Esta ação não pode ser desfeita.`)) return;
    try { await api('api/capturas.php', { acao:'excluir', id:+b.dataset.delCap }); msg('msgCap', 'Captura excluída.', true); await carregarCapturas(); await carregarEventos(); }
    catch (e) { msg('msgCap', e.message, false); }
  });
  $('#btnCsv').addEventListener('click', () => {
    const cols = ['id','criado_em','evento_titulo','data_evento','participante','usuario_nome','num_pessoas','duracao_ms','quadros','modelo','similaridade_media','exercicio','repeticoes'];
    const cel = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const csv = '﻿' + [cols.join(';'), ...capturas.map(c => cols.map(k => cel(c[k])).join(';'))].join('\r\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type:'text/csv;charset=utf-8' }));
    a.download = 'capturas_mocapweb.csv'; a.click();
  });

  // ── usuários ──
  async function carregarUsuarios() {
    const j = await api('api/usuarios.php'); const lista = j.usuarios || [];
    $('#tabUs tbody').innerHTML = lista.length ? lista.map(u => {
      const bloq = u.bloqueado_ate && new Date(u.bloqueado_ate.replace(' ','T')) > new Date();
      return `<tr>
      <td>${u.id}</td><td>${esc(u.nome)}</td><td>${esc(u.email)}</td>
      <td><span class="badge ${u.papel === 'admin' ? 'admin' : ''}">${esc(u.papel)}</span></td>
      <td>${+u.ativo ? '<span class="badge">ativo</span>' : '<span class="badge off">inativo</span>'}${bloq ? ' <span class="badge off">bloqueado</span>' : ''}</td>
      <td>${fmtDataHora(u.ultimo_login)}</td>
      <td class="acts">
        <button class="btn sm sec" data-us="senha" data-id="${u.id}" data-nome="${esc(u.nome)}">Nova senha</button>
        <button class="btn sm sec" data-us="papel" data-id="${u.id}" data-papel="${u.papel === 'admin' ? 'professor' : 'admin'}" ${u.id === EU ? 'disabled' : ''}>${u.papel === 'admin' ? 'Tornar professor' : 'Tornar admin'}</button>
        <button class="btn sm ${+u.ativo ? 'danger' : ''}" data-us="ativo" data-id="${u.id}" data-ativo="${+u.ativo ? 0 : 1}" ${u.id === EU ? 'disabled' : ''}>${+u.ativo ? 'Desativar' : 'Ativar'}</button>
      </td></tr>`; }).join('') : '<tr><td colspan="7" class="empty">Nenhum usuário.</td></tr>';
  }
  $('#formUsuario').addEventListener('submit', async ev => {
    ev.preventDefault();
    const email = $('#usEmail').value.trim().toLowerCase();
    if (!new RegExp('^[a-z0-9._%+\\-]+@' + DOMINIO.replace(/\./g,'\\.') + '$').test(email)) { msg('msgUs', 'Use um e-mail institucional @' + DOMINIO + '.', false); return; }
    const b = $('#btnUsuario'); b.disabled = true;
    try {
      await api('api/usuarios.php', { acao:'criar', nome:$('#usNome').value.trim(), email, papel:$('#usPapel').value, senha:$('#usSenha').value });
      msg('msgUs', 'Usuário criado. Informe a senha inicial por e-mail e peça para trocá-la no primeiro acesso.', true); ev.target.reset(); await carregarUsuarios();
    } catch (e) { msg('msgUs', e.message, false); } finally { b.disabled = false; }
  });
  $('#tabUs').addEventListener('click', async ev => {
    const b = ev.target.closest('[data-us]'); if (!b || b.disabled) return;
    const id = +b.dataset.id; let corpo = null;
    if (b.dataset.us === 'senha') {
      const s = prompt(`Nova senha para ${b.dataset.nome} (mínimo 8 caracteres):`); if (s === null) return;
      corpo = { acao:'senha', id, senha:s };
    } else if (b.dataset.us === 'papel') {
      if (!confirm(`Mudar o perfil do usuário #${id} para "${b.dataset.papel}"?`)) return;
      corpo = { acao:'papel', id, papel:b.dataset.papel };
    } else {
      corpo = { acao:'ativo', id, ativo: b.dataset.ativo === '1' };
    }
    try { await api('api/usuarios.php', corpo); msg('msgUs', 'Atualizado.', true); await carregarUsuarios(); }
    catch (e) { msg('msgUs', e.message, false); }
  });

  // ── início ──
  (async () => {
    try { await carregarEventos(); await carregarCapturas(); await carregarUsuarios(); }
    catch (e) { msg('msgCap', e.message, false); }
  })();
})();
</script>
</body>
</html>
