<?php
declare(strict_types=1);
require __DIR__ . '/api/db.php';
if (empty($_SESSION['usuario'])) { header('Location: login.php?r=admin.php'); exit; }
$usuario = $_SESSION['usuario'];
// Primeiro acesso: troca de senha antes de qualquer página
try {
  $stTs = $pdo->prepare('SELECT precisa_trocar_senha FROM usuarios WHERE id = ?');
  $stTs->execute([$usuario['id']]);
  if ((int)$stTs->fetchColumn() === 1) { header('Location: alterar-senha.php'); exit; }
} catch (PDOException $e) { /* migração pendente */ }
$csrf = csrf_token();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Administração — MoCap Web CECAPE</title>
<link rel="icon" href="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png">
<style>
  :root { --bg:#0a0c10; --surface2:#1a1f2b; --border:rgba(255,255,255,0.1); --accent:#00e5a0; --text:#e8eaf0; --text2:#8890a4; --red:#ff4a6e; --yellow:#ffc947; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:var(--bg); color:var(--text); font-family:'Inter',system-ui,sans-serif; min-height:100vh; }
  header {
    display:flex; align-items:center; gap:12px; padding:10px 24px;
    background:#0b243d; border-bottom:1px solid var(--border); flex-wrap:wrap;
  }
  header img { height:30px; object-fit:contain; }
  header h1 { font-size:15px; font-weight:600; }
  header h1 span { color:var(--accent); }
  header .user { margin-left:auto; font-size:12px; color:var(--text2); display:flex; align-items:center; gap:12px; }
  .btn {
    padding:8px 14px; border-radius:8px; border:1px solid var(--border);
    background:var(--surface2); color:var(--text); font-size:12.5px; font-weight:600; cursor:pointer;
  }
  .btn:hover { background:rgba(255,255,255,0.08); }
  .btn.primary { background:var(--accent); color:#0a0c10; border:none; }
  .btn.danger { color:var(--red); border-color:rgba(255,74,110,0.35); background:rgba(255,74,110,0.08); }
  main { max-width:1200px; margin:0 auto; padding:20px 24px 60px; display:flex; flex-direction:column; gap:18px; }
  .panel { background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:18px; }
  .panel h2 { font-size:13px; text-transform:uppercase; letter-spacing:0.8px; color:var(--text2); margin-bottom:12px; }
  .filters { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
  .field { display:flex; flex-direction:column; gap:4px; }
  .field label { font-size:11px; color:var(--text2); font-weight:600; }
  .field input, .field select {
    padding:9px 10px; border-radius:8px; border:1px solid var(--border);
    background:var(--bg); color:var(--text); font-size:13px; min-width:160px;
  }
  .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-top:12px; }
  .stat { background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:12px; text-align:center; }
  .stat .v { font-size:22px; font-weight:800; color:var(--accent); }
  .stat .l { font-size:10px; color:var(--text2); text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
  .table-wrap { overflow-x:auto; margin-top:10px; }
  table { width:100%; border-collapse:collapse; font-size:12.5px; min-width:900px; }
  th, td { padding:9px 10px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
  th { color:var(--text2); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; cursor:pointer; user-select:none; }
  th:hover { color:var(--accent); }
  th .arrow { font-size:9px; margin-left:3px; }
  tr:hover td { background:rgba(255,255,255,0.03); }
  .pill { padding:2px 9px; border-radius:100px; font-size:11px; font-weight:700; }
  .pill.good { background:rgba(0,229,160,0.14); color:var(--accent); }
  .pill.mid  { background:rgba(255,201,71,0.14); color:var(--yellow); }
  .pill.bad  { background:rgba(255,74,110,0.14); color:var(--red); }
  .pill.na   { background:rgba(255,255,255,0.06); color:var(--text2); }
  .row-actions { display:flex; gap:6px; }
  .row-actions .btn { padding:5px 9px; font-size:11px; }
  .msg { display:none; padding:10px 14px; border-radius:8px; font-size:12.5px; margin-top:10px; }
  .msg.ok  { background:rgba(0,229,160,0.1); border:1px solid rgba(0,229,160,0.3); color:var(--accent); }
  .msg.err { background:rgba(255,74,110,0.1); border:1px solid rgba(255,74,110,0.3); color:var(--red); }
  .grid-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; align-items:end; }
  .empty { text-align:center; color:var(--text2); padding:26px; font-size:13px; }
</style>
</head>
<body>
<header>
  <img src="https://cecapescs.com.br/proj/cecape-palavras/assets/img/Logo-CECAPE.png" alt="CECAPE" onerror="this.style.display='none'">
  <h1>Mocap<span>Web</span> · Administração</h1>
  <div class="user">
    <span>👤 <?php echo htmlspecialchars($usuario['nome']); ?> (<?php echo htmlspecialchars($usuario['papel']); ?>)</span>
    <a class="btn" href="alterar-senha.php">🔑 Alterar senha</a>
    <a class="btn" href="index.html">🎥 Ir para a captura</a>
    <button class="btn danger" onclick="sair()">Sair</button>
  </div>
</header>

<main>
  <!-- Criar evento -->
  <div class="panel">
    <h2>➕ Criar evento (dia e horário)</h2>
    <form id="frmEvento" class="grid-form">
      <div class="field"><label>Título*</label><input id="evTitulo" required maxlength="160" placeholder="Ex.: Oficina de dança — 5º ano"></div>
      <div class="field"><label>Data*</label><input id="evData" type="date" required></div>
      <div class="field"><label>Início*</label><input id="evIni" type="time" required></div>
      <div class="field"><label>Fim</label><input id="evFim" type="time"></div>
      <div class="field"><label>Local</label><input id="evLocal" maxlength="160" placeholder="Ex.: Quadra CECAPE"></div>
      <div class="field"><button type="submit" class="btn primary" style="padding:10px;">Criar evento</button></div>
    </form>
    <div class="msg" id="msgEvento"></div>
  </div>

  <!-- Filtros + tabela dinâmica -->
  <div class="panel">
    <h2>📊 Capturas realizadas</h2>
    <div class="filters">
      <div class="field">
        <label>Evento (dia · horário)</label>
        <select id="fEvento"><option value="">— todos os eventos —</option></select>
      </div>
      <div class="field">
        <label>Data</label>
        <input id="fData" type="date">
      </div>
      <div class="field">
        <label>Busca (participante / professor)</label>
        <input id="fBusca" type="search" placeholder="Digite para filtrar...">
      </div>
      <button class="btn" onclick="carregarCapturas()">🔄 Atualizar</button>
      <button class="btn" onclick="exportarTabelaCSV()">⬇ Exportar tabela (CSV)</button>
    </div>

    <div class="cards" id="resumo"></div>

    <div class="table-wrap">
      <table id="tbl">
        <thead><tr id="thead"></tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="empty" id="vazio" style="display:none;">Nenhuma captura encontrada com os filtros atuais.</div>
    <div class="msg" id="msgTabela"></div>
  </div>

<?php if ($usuario['papel'] === 'admin'): ?>
  <!-- Controle de acesso (somente admin) -->
  <div class="panel" id="painelUsuarios">
    <h2>🔐 Controle de acesso dos usuários</h2>
    <p style="font-size:12px;color:var(--text2);line-height:1.5;">
      Quem <b>nunca acessou</b> ainda está com a senha inicial. No primeiro acesso o sistema obriga a criar uma senha própria
      (<b>pendente</b> = já entrou mas ainda não concluiu a troca). Use <b>Redefinir</b> se alguém esquecer a senha:
      ela volta para a inicial e a troca é exigida de novo.
    </p>
    <div class="cards" id="resumoUsuarios"></div>
    <div class="table-wrap">
      <table id="tblUsuarios">
        <thead><tr>
          <th>Nome</th><th>Instituição</th><th>E-mail</th><th>Perfil</th><th>Situação</th>
          <th>Primeiro acesso</th><th>Último acesso</th><th>Senha alterada em</th><th>Ações</th>
        </tr></thead>
        <tbody id="tbodyUsuarios"></tbody>
      </table>
    </div>
    <div class="msg" id="msgUsuarios"></div>
  </div>
<?php endif; ?>
</main>

<script>
const CSRF = <?php echo json_encode($csrf); ?>;
let eventos = [], capturas = [], ordem = { col: 'iniciada_em', dir: -1 };

const COLS = [
  { k:'id',                 t:'#' },
  { k:'iniciada_em',        t:'Data/hora' },
  { k:'evento_titulo',      t:'Evento' },
  { k:'participante',       t:'Participante' },
  { k:'professor',          t:'Professor' },
  { k:'duracao_ms',         t:'Duração' },
  { k:'quadros',            t:'Quadros' },
  { k:'num_pessoas',        t:'Pessoas' },
  { k:'similaridade_media', t:'Semelhança' },
  { k:'repeticoes',         t:'Repetições' },
  { k:'_acoes',             t:'Ações' },
];

function fmtDur(ms){ const s=Math.round(ms/1000); return Math.floor(s/60)+'m'+String(s%60).padStart(2,'0')+'s'; }
function fmtDataHora(s){ const d=new Date(s.replace(' ','T')); return d.toLocaleDateString('pt-BR')+' '+d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); }
function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function mostrarMsg(id, texto, ok){ const el=document.getElementById(id); el.textContent=texto; el.className='msg '+(ok?'ok':'err'); el.style.display='block'; setTimeout(()=>el.style.display='none', 6000); }

async function api(url, opts={}) {
  opts.headers = Object.assign({ 'X-CSRF-Token': CSRF }, opts.headers||{});
  const r = await fetch(url, opts);
  if (r.status === 401) { location.href = 'login.php?r=admin.php'; throw new Error('sessão expirada'); }
  return r;
}

async function carregarEventos() {
  const r = await api('api/eventos.php');
  const j = await r.json();
  eventos = j.eventos || [];
  const sel = document.getElementById('fEvento');
  const atual = sel.value;
  sel.innerHTML = '<option value="">— todos os eventos —</option>' + eventos.map(e =>
    `<option value="${e.id}">${esc(e.titulo)} · ${new Date(e.data_evento+'T00:00').toLocaleDateString('pt-BR')} ${e.hora_inicio.slice(0,5)} (${e.total_capturas} capt.)</option>`
  ).join('');
  if (atual && eventos.find(e=>String(e.id)===atual)) sel.value = atual;
}

async function carregarCapturas() {
  const p = new URLSearchParams();
  const ev = document.getElementById('fEvento').value;
  const dt = document.getElementById('fData').value;
  if (ev) p.set('evento_id', ev);
  if (dt) p.set('data', dt);
  const r = await api('api/capturas.php?' + p.toString());
  const j = await r.json();
  capturas = j.capturas || [];
  desenharTabela();
}

function linhasFiltradas() {
  const q = document.getElementById('fBusca').value.trim().toLowerCase();
  let rows = capturas;
  if (q) rows = rows.filter(c =>
    (c.participante||'').toLowerCase().includes(q) ||
    (c.professor||'').toLowerCase().includes(q) ||
    (c.evento_titulo||'').toLowerCase().includes(q));
  const { col, dir } = ordem;
  rows = [...rows].sort((a,b)=>{
    let va=a[col], vb=b[col];
    if (va==null) return 1; if (vb==null) return -1;
    if (typeof va==='number'&&typeof vb==='number') return (va-vb)*dir;
    return String(va).localeCompare(String(vb),'pt-BR')*dir;
  });
  return rows;
}

function desenharTabela() {
  const thead = document.getElementById('thead');
  thead.innerHTML = COLS.map(c =>
    `<th data-k="${c.k}">${c.t}${ordem.col===c.k?`<span class="arrow">${ordem.dir>0?'▲':'▼'}</span>`:''}</th>`).join('');
  thead.querySelectorAll('th').forEach(th=>{
    th.onclick=()=>{ const k=th.dataset.k; if(k==='_acoes')return;
      ordem = ordem.col===k ? {col:k,dir:-ordem.dir} : {col:k,dir:1}; desenharTabela(); };
  });

  const rows = linhasFiltradas();
  document.getElementById('vazio').style.display = rows.length ? 'none' : 'block';
  document.getElementById('tbody').innerHTML = rows.map(c => {
    const sim = c.similaridade_media;
    const pill = sim==null ? '<span class="pill na">—</span>'
      : `<span class="pill ${sim>=80?'good':sim>=55?'mid':'bad'}">${sim}%</span>`;
    const reps = c.repeticoes!=null ? `${c.repeticoes}${c.exercicio?' ('+esc(c.exercicio)+')':''}` : '—';
    return `<tr>
      <td>${c.id}</td>
      <td>${fmtDataHora(c.iniciada_em)}</td>
      <td>${esc(c.evento_titulo)}</td>
      <td><b>${esc(c.participante)}</b></td>
      <td>${esc(c.professor)}</td>
      <td>${fmtDur(c.duracao_ms)}</td>
      <td>${c.quadros}</td>
      <td>${c.num_pessoas}</td>
      <td>${pill}</td>
      <td>${reps}</td>
      <td class="row-actions">
        <a class="btn" href="api/capturas.php?id=${c.id}&dados=1" title="Baixar JSON completo">⬇ JSON</a>
        <button class="btn danger" onclick="excluirCaptura(${c.id})">🗑</button>
      </td>
    </tr>`;
  }).join('');

  // Resumo
  const n = rows.length;
  const durTotal = rows.reduce((s,c)=>s+(c.duracao_ms||0),0);
  const sims = rows.filter(c=>c.similaridade_media!=null);
  const simMed = sims.length ? Math.round(sims.reduce((s,c)=>s+c.similaridade_media,0)/sims.length) : null;
  const parts = new Set(rows.map(c=>c.participante)).size;
  document.getElementById('resumo').innerHTML = `
    <div class="stat"><div class="v">${n}</div><div class="l">capturas</div></div>
    <div class="stat"><div class="v">${parts}</div><div class="l">participantes</div></div>
    <div class="stat"><div class="v">${fmtDur(durTotal)}</div><div class="l">tempo total</div></div>
    <div class="stat"><div class="v">${simMed==null?'—':simMed+'%'}</div><div class="l">semelhança média</div></div>`;
}

async function excluirCaptura(id) {
  if (!confirm('Excluir a captura #'+id+'? Esta ação não pode ser desfeita.')) return;
  const r = await api('api/capturas.php?id='+id, { method:'DELETE' });
  const j = await r.json();
  if (j.ok) { mostrarMsg('msgTabela','Captura #'+id+' excluída.',true); carregarCapturas(); carregarEventos(); }
  else mostrarMsg('msgTabela', j.erro||'Falha ao excluir.', false);
}

function exportarTabelaCSV() {
  const rows = linhasFiltradas();
  if (!rows.length) return;
  const head = ['id','data_hora','evento','participante','professor','duracao_ms','quadros','pessoas','similaridade_media','exercicio','repeticoes'];
  const csv = [head.join(';')].concat(rows.map(c =>
    [c.id,c.iniciada_em,c.evento_titulo,c.participante,c.professor,c.duracao_ms,c.quadros,c.num_pessoas,c.similaridade_media??'',c.exercicio??'',c.repeticoes??'']
      .map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(';'))).join('\n');
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8'}));
  a.download='capturas_mocapweb.csv'; a.click();
}

document.getElementById('frmEvento').addEventListener('submit', async e=>{
  e.preventDefault();
  const r = await api('api/eventos.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      titulo: document.getElementById('evTitulo').value.trim(),
      data_evento: document.getElementById('evData').value,
      hora_inicio: document.getElementById('evIni').value,
      hora_fim: document.getElementById('evFim').value,
      local: document.getElementById('evLocal').value.trim(),
    })
  });
  const j = await r.json();
  if (j.ok) { mostrarMsg('msgEvento','Evento criado!',true); e.target.reset(); carregarEventos(); }
  else mostrarMsg('msgEvento', j.erro||'Falha ao criar evento.', false);
});

document.getElementById('fEvento').addEventListener('change', carregarCapturas);
document.getElementById('fData').addEventListener('change', carregarCapturas);
document.getElementById('fBusca').addEventListener('input', desenharTabela);

async function sair() {
  try { await api('api/logout.php', { method:'POST' }); } catch(_){}
  location.href = 'login.php';
}

// ---------- Controle de acesso (somente admin) ----------
const SIT = {
  nunca_acessou:  { t:'Nunca acessou', c:'bad' },
  pendente_troca: { t:'Pendente (trocar senha)', c:'mid' },
  ok:             { t:'Acessou · senha própria', c:'good' },
};
function fmtOpt(s){ return s ? fmtDataHora(s) : '—'; }

async function carregarUsuarios() {
  if (!document.getElementById('painelUsuarios')) return;
  const r = await api('api/usuarios.php');
  const j = await r.json();
  if (!j.ok) { mostrarMsg('msgUsuarios', j.erro||'Falha ao carregar usuários.', false); return; }
  const rs = j.resumo;
  document.getElementById('resumoUsuarios').innerHTML = `
    <div class="stat"><div class="v">${rs.total}</div><div class="l">usuários</div></div>
    <div class="stat"><div class="v" style="color:var(--red)">${rs.nunca_acessou}</div><div class="l">nunca acessaram</div></div>
    <div class="stat"><div class="v" style="color:var(--yellow)">${rs.pendente_troca}</div><div class="l">pendentes de troca</div></div>
    <div class="stat"><div class="v">${rs.ok}</div><div class="l">com senha própria</div></div>`;
  document.getElementById('tbodyUsuarios').innerHTML = j.usuarios.map(u => {
    const s = SIT[u.situacao] || SIT.ok;
    const inativo = !u.ativo;
    return `<tr${inativo?' style="opacity:.5"':''}>
      <td><b>${esc(u.nome)}</b>${inativo?' <span class="pill na">inativo</span>':''}</td>
      <td>${esc(u.instituicao||'—')}</td>
      <td>${esc(u.email)}</td>
      <td>${esc(u.papel)}</td>
      <td><span class="pill ${s.c}">${s.t}</span></td>
      <td>${fmtOpt(u.primeiro_login_em)}</td>
      <td>${fmtOpt(u.ultimo_login)}</td>
      <td>${fmtOpt(u.senha_alterada_em)}</td>
      <td class="row-actions">
        <button class="btn" title="Exigir nova senha no próximo acesso" onclick="acaoUsuario(${u.id},'forcar_troca','${esc(u.nome)}')">🔑 Exigir troca</button>
        <button class="btn" title="Voltar para a senha inicial (scseduca) e exigir troca" onclick="acaoUsuario(${u.id},'redefinir_senha','${esc(u.nome)}')">↺ Redefinir</button>
        <button class="btn ${inativo?'':'danger'}" onclick="acaoUsuario(${u.id},'${inativo?'ativar':'desativar'}','${esc(u.nome)}')">${inativo?'✔ Ativar':'⛔ Desativar'}</button>
      </td>
    </tr>`;
  }).join('');
}

async function acaoUsuario(id, acao, nome) {
  const perg = {
    forcar_troca:    `Exigir que ${nome} defina uma nova senha no próximo acesso?`,
    redefinir_senha: `Redefinir a senha de ${nome} para a senha inicial (scseduca)?\nA pessoa terá de criar uma nova senha ao entrar.`,
    desativar:       `Desativar a conta de ${nome}? A pessoa não conseguirá mais entrar.`,
    ativar:          `Reativar a conta de ${nome}?`,
  }[acao];
  if (!confirm(perg)) return;
  const r = await api('api/usuarios.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, acao }) });
  const j = await r.json();
  mostrarMsg('msgUsuarios', j.ok ? j.mensagem : (j.erro||'Falha na ação.'), !!j.ok);
  if (j.ok) carregarUsuarios();
}

carregarEventos().then(carregarCapturas);
carregarUsuarios();
</script>
</body>
</html>
