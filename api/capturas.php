<?php
/**
 * POST   /api/capturas.php                  — salva uma captura (JSON grande no campo `dados`)
 * GET    /api/capturas.php?evento_id=&data= — lista capturas (sem o campo `dados`, que é pesado)
 * GET    /api/capturas.php?id=N&dados=1     — baixa o JSON completo de uma captura
 * DELETE /api/capturas.php?id=N             — exclui (admin ou o professor que gravou)
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
$u = require_login();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'POST') {
  require_csrf();
  $b = corpo_json();

  $eventoId     = (int)($b['evento_id'] ?? 0);
  $participante = trim((string)($b['participante'] ?? ''));
  $duracao      = max(0, (int)($b['duracao_ms'] ?? 0));
  $quadros      = max(0, (int)($b['quadros'] ?? 0));
  $numPessoas   = min(6, max(1, (int)($b['num_pessoas'] ?? 1)));
  $modelo       = in_array(($b['modelo'] ?? ''), ['lite','full','heavy'], true) ? $b['modelo'] : 'lite';
  $sim          = $b['similaridade_media'] ?? null;
  $sim          = $sim === null ? null : min(100, max(0, (int)$sim));
  $exercicio    = in_array(($b['exercicio'] ?? ''), ['squat','jack','curl','raise'], true) ? $b['exercicio'] : null;
  $repeticoes   = $b['repeticoes'] ?? null;
  $repeticoes   = $repeticoes === null ? null : max(0, (int)$repeticoes);
  $dados        = $b['dados'] ?? null;

  if ($eventoId <= 0)                          json_out(['erro' => 'Selecione um evento.'], 400);
  if ($participante === '' || mb_strlen($participante) > 120) json_out(['erro' => 'Informe o participante (nome de exibição ou código, até 120 caracteres).'], 400);
  if (!is_array($dados) || empty($dados['frames'])) json_out(['erro' => 'Captura sem dados de quadros.'], 400);

  $st = $pdo->prepare('SELECT id FROM eventos WHERE id = ?');
  $st->execute([$eventoId]);
  if (!$st->fetch()) json_out(['erro' => 'Evento não encontrado.'], 404);

  $st = $pdo->prepare(
    'INSERT INTO capturas (evento_id, usuario_id, participante, iniciada_em, duracao_ms, quadros, num_pessoas, modelo, similaridade_media, exercicio, repeticoes, dados)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
  );
  $st->execute([
    $eventoId, $u['id'], $participante,
    date('Y-m-d H:i:s'),
    $duracao, $quadros, $numPessoas, $modelo,
    $sim, $exercicio, $repeticoes,
    json_encode($dados, JSON_UNESCAPED_UNICODE),
  ]);
  $id = (int)$pdo->lastInsertId();
  registrar_log($pdo, (int)$u['id'], 'captura_salva', "id $id · evento $eventoId · $participante");
  json_out(['ok' => true, 'id' => $id], 201);
}

if ($metodo === 'GET') {
  // Download do JSON completo de uma captura
  if (!empty($_GET['id']) && !empty($_GET['dados'])) {
    $id = (int)$_GET['id'];
    $st = $pdo->prepare('SELECT participante, dados FROM capturas WHERE id = ?');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) json_out(['erro' => 'Captura não encontrada.'], 404);
    $nome = preg_replace('/[^A-Za-z0-9_-]+/', '_', $c['participante']);
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"captura_{$id}_{$nome}.json\"");
    echo $c['dados'];
    exit;
  }

  // Listagem com filtros (sem o campo `dados`)
  $where = [];
  $params = [];
  if (!empty($_GET['evento_id'])) { $where[] = 'c.evento_id = ?'; $params[] = (int)$_GET['evento_id']; }
  if (!empty($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'])) {
    $where[] = 'e.data_evento = ?'; $params[] = $_GET['data'];
  }
  $sql = 'SELECT c.id, c.evento_id, c.participante, c.iniciada_em, c.duracao_ms, c.quadros,
                 c.num_pessoas, c.modelo, c.similaridade_media, c.exercicio, c.repeticoes,
                 e.titulo AS evento_titulo, e.data_evento, e.hora_inicio,
                 u.nome AS professor
            FROM capturas c
            JOIN eventos  e ON e.id = c.evento_id
            JOIN usuarios u ON u.id = c.usuario_id';
  if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' ORDER BY c.iniciada_em DESC LIMIT 1000';
  $st = $pdo->prepare($sql);
  $st->execute($params);
  json_out(['ok' => true, 'capturas' => $st->fetchAll()]);
}

if ($metodo === 'DELETE') {
  require_csrf();
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) json_out(['erro' => 'Captura inválida.'], 400);
  $st = $pdo->prepare('SELECT usuario_id FROM capturas WHERE id = ?');
  $st->execute([$id]);
  $c = $st->fetch();
  if (!$c) json_out(['erro' => 'Captura não encontrada.'], 404);
  if ($u['papel'] !== 'admin' && (int)$c['usuario_id'] !== (int)$u['id']) {
    json_out(['erro' => 'Sem permissão para excluir esta captura.'], 403);
  }
  $pdo->prepare('DELETE FROM capturas WHERE id = ?')->execute([$id]);
  registrar_log($pdo, (int)$u['id'], 'captura_excluida', "id $id");
  json_out(['ok' => true]);
}

json_out(['erro' => 'Método não permitido.'], 405);
