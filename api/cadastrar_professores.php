<?php
/**
 * CADASTRO EM LOTE DOS PROFESSORES — uso único, pelo navegador.
 * Abra logado como ADMINISTRADOR:  https://seusite/mocapweb/api/cadastrar_professores.php
 * Alternativa ao db/professores-2026-09.sql (phpMyAdmin). Faz a mesma coisa:
 * cria/atualiza os 10 professores com a senha inicial "scseduca" (hash bcrypt).
 * APAGUE este arquivo depois de usar.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['papel'] ?? '') !== 'admin') {
  http_response_code(403);
  die('<meta charset="utf-8"><body style="font-family:system-ui;background:#0a0c10;color:#ff4a6e;padding:40px;">Acesso negado: entre no sistema como <b>administrador</b> e abra esta página de novo.</body>');
}

$SENHA_INICIAL = 'scseduca';
$PROFESSORES = [
  ['EMEI Telma Silvia de Aguiar Brito',  'Adriana Barbosa Ferreira',      'adriana.ferreira@scseduca.com.br'],
  ['NAEI',                               'Adriana Gomes da Fonseca',      'adrianagomes@scseduca.com.br'],
  ['EMEF Laura Lopes',                   'Barbara da Silva Borges',       'barbaraborges@scseduca.com.br'],
  ['EMI Maria Simonetti Thomé',          'Daniela Mattos Tarrataca',      'danielatarrataca@scseduca.com.br'],
  ['EMEF Santina Lorenzini Auricchio',   'Daniele Fernando da Silva',     'danielesilva@scseduca.com.br'],
  ['EMI Gastão Vidigal Neto',            'Diyony Uiara Sampaio Marsilli', 'diyonymarsilli@scseduca.com.br'],
  ['EMEF Sylvio Romero',                 'Elio Rodrigues Junior',         'eliojunior@scseduca.com.br'],
  ['EMEF Bartolomeu Bueno da Silva',     'Rosileide Queiroz de Oliveira', 'rosileideoliveira@scseduca.com.br'],
  ['EME Profª. Alcina Dantas Feijão',    'Shirley Monteiro Maciel',       'educacaoespecialalcina@scseduca.com.br'],
  ['EMEI José Auricchio',                'Simone Mendes Maldegan',        'simonemaldegan@scseduca.com.br'],
];

$linhas = [];
try {
  // coluna "instituicao" (escola/unidade), se ainda não existir
  $tem = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'instituicao'")->fetchColumn();
  if (!$tem) { $pdo->exec('ALTER TABLE usuarios ADD COLUMN instituicao VARCHAR(160) NULL AFTER nome'); $linhas[] = ['ok', 'Coluna "instituicao" criada na tabela usuarios.']; }

  $hash = password_hash($SENHA_INICIAL, PASSWORD_BCRYPT);
  $st = $pdo->prepare('INSERT INTO usuarios (nome, instituicao, email, senha_hash, papel, ativo) VALUES (?,?,?,?,\'professor\',1)
    ON DUPLICATE KEY UPDATE nome = VALUES(nome), instituicao = VALUES(instituicao), senha_hash = VALUES(senha_hash), papel = \'professor\', ativo = 1, tentativas_login = 0, bloqueado_ate = NULL');
  foreach ($PROFESSORES as [$inst, $nome, $email]) {
    $email = strtolower(trim($email));
    $st->execute([$nome, $inst, $email, $hash]);
    $linhas[] = ['ok', "{$nome} — {$email} ({$inst})"];
  }
  registrar_log($pdo, (int)$_SESSION['usuario']['id'], 'professores_cadastrados', count($PROFESSORES) . ' contas');
  $total = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE papel = 'professor' AND ativo = 1")->fetchColumn();
  $linhas[] = ['info', "Total de professores ativos no sistema: {$total}."];
} catch (Throwable $e) {
  $linhas[] = ['erro', 'Falha: ' . htmlspecialchars($e->getMessage())];
}
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Cadastro de professores — MoCapWeb</title>
<style>body{font-family:system-ui;background:#0a0c10;color:#e8eaf0;padding:30px;max-width:820px;margin:auto;line-height:1.6}
h1{color:#00e5a0;font-size:20px}.l{padding:8px 12px;border-radius:8px;margin:6px 0;font-size:14px}
.ok{background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.3)}.erro{background:rgba(255,74,110,.12);border:1px solid rgba(255,74,110,.35);color:#ffb3c1}
.info{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#8890a4}
.aviso{margin-top:24px;padding:12px;border:1px solid #ffc947;border-radius:8px;color:#ffc947;font-size:13px}</style></head>
<body><h1>Cadastro de professores</h1>
<?php foreach ($linhas as [$t, $txt]) echo "<div class=\"l {$t}\">" . ($t === 'ok' ? '✅ ' : ($t === 'erro' ? '❌ ' : 'ℹ️ ')) . htmlspecialchars($txt) . "</div>"; ?>
<div class="aviso">⚠️ Senha inicial de todos: <b><?php echo htmlspecialchars($SENHA_INICIAL); ?></b>. Oriente a troca no primeiro acesso. Depois, <b>apague este arquivo</b> (api/cadastrar_professores.php).</div>
</body></html>
