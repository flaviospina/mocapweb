<?php
/**
 * GET  /api/eventos.php            — lista eventos (mais recentes primeiro)
 * POST /api/eventos.php            — cria evento { titulo, data_evento, hora_inicio, hora_fim?, local?, descricao? }
 * DELETE /api/eventos.php?id=N     — exclui evento (admin ou quem criou; capturas vão junto)
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
$u = require_login();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
  $st = $pdo->query(
    'SELECT e.id, e.titulo, e.data_evento, e.hora_inicio, e.hora_fim, e.local, e.descricao,
            u.nome AS criado_por_nome,
            (SELECT COUNT(*) FROM capturas c WHERE c.evento_id = e.id) AS total_capturas
       FROM eventos e
       JOIN usuarios u ON u.id = e.criado_por
      ORDER BY e.data_evento DESC, e.hora_inicio DESC
      LIMIT 500'
  );
  json_out(['ok' => true, 'eventos' => $st->fetchAll()]);
}

if ($metodo === 'POST') {
  require_csrf();
  $b = corpo_json();
  $titulo = trim((string)($b['titulo'] ?? ''));
  $data   = (string)($b['data_evento'] ?? '');
  $hini   = (string)($b['hora_inicio'] ?? '');
  $hfim   = trim((string)($b['hora_fim'] ?? ''));
  $local  = trim((string)($b['local'] ?? ''));
  $desc   = trim((string)($b['descricao'] ?? ''));

  if ($titulo === '' || mb_strlen($titulo) > 160)                json_out(['erro' => 'Título obrigatório (até 160 caracteres).'], 400);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data))               json_out(['erro' => 'Data inválida (use o seletor de data).'], 400);
  if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hini))            json_out(['erro' => 'Hora de início inválida.'], 400);
  if ($hfim !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hfim)) json_out(['erro' => 'Hora de fim inválida.'], 400);

  $st = $pdo->prepare('INSERT INTO eventos (titulo, data_evento, hora_inicio, hora_fim, local, descricao, criado_por) VALUES (?,?,?,?,?,?,?)');
  $st->execute([
    $titulo, $data, $hini,
    $hfim !== '' ? $hfim : null,
    $local !== '' ? mb_substr($local, 0, 160) : null,
    $desc  !== '' ? $desc : null,
    $u['id'],
  ]);
  registrar_log($pdo, (int)$u['id'], 'evento_criado', $titulo);
  json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

if ($metodo === 'DELETE') {
  require_csrf();
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) json_out(['erro' => 'Evento inválido.'], 400);
  $st = $pdo->prepare('SELECT criado_por FROM eventos WHERE id = ?');
  $st->execute([$id]);
  $ev = $st->fetch();
  if (!$ev) json_out(['erro' => 'Evento não encontrado.'], 404);
  if ($u['papel'] !== 'admin' && (int)$ev['criado_por'] !== (int)$u['id']) {
    json_out(['erro' => 'Sem permissão para excluir este evento.'], 403);
  }
  $pdo->prepare('DELETE FROM eventos WHERE id = ?')->execute([$id]);
  registrar_log($pdo, (int)$u['id'], 'evento_excluido', "id $id");
  json_out(['ok' => true]);
}

json_out(['erro' => 'Método não permitido.'], 405);
