<?php
/**
 * /api/capturas.php
 *   POST (usuário logado, X-CSRF-Token)   -> salva uma captura
 *        { evento_id, participante, duracao_ms, quadros, num_pessoas, modelo,
 *          similaridade_media, exercicio, repeticoes, dados }  => { ok, id }
 *   GET  (somente admin)                  -> lista { capturas: [...] }
 *        filtros opcionais: evento_id, de (AAAA-MM-DD), ate (AAAA-MM-DD), busca
 *   GET  ?id=N&download=1 (somente admin) -> JSON completo da captura (arquivo)
 *   POST {acao:"excluir", id} (admin)     -> exclui a captura
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require_once __DIR__ . '/guarda.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------- GET (admin)
if ($metodo === 'GET') {
    mocap_exigir_admin_api();

    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0 && !empty($_GET['download'])) {
        $st = $pdo->prepare('SELECT c.*, e.titulo AS evento_titulo, e.data_evento FROM capturas c JOIN eventos e ON e.id = c.evento_id WHERE c.id = ?');
        $st->execute([$id]);
        $c = $st->fetch();
        if (!$c) {
            json_out(['erro' => 'Captura não encontrada.'], 404);
        }
        $nome = sprintf('captura_%d_%s.json', $id, preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$c['participante']));
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        // "dados" já está em JSON no banco; monta o envelope sem decodificar o conteúdo grande.
        $meta = [
            'id' => (int)$c['id'], 'evento' => $c['evento_titulo'], 'data_evento' => $c['data_evento'],
            'participante' => $c['participante'], 'duracao_ms' => (int)$c['duracao_ms'], 'quadros' => (int)$c['quadros'],
            'num_pessoas' => (int)$c['num_pessoas'], 'modelo' => $c['modelo'],
            'similaridade_media' => $c['similaridade_media'] === null ? null : (int)$c['similaridade_media'],
            'exercicio' => $c['exercicio'], 'repeticoes' => $c['repeticoes'] === null ? null : (int)$c['repeticoes'],
            'criado_em' => $c['criado_em'],
        ];
        echo '{"captura":' . json_encode($meta, JSON_UNESCAPED_UNICODE) . ',"dados":' . ($c['dados'] ?: 'null') . '}';
        exit;
    }

    $where  = [];
    $params = [];
    $evento = (int)($_GET['evento_id'] ?? 0);
    if ($evento > 0) { $where[] = 'c.evento_id = ?'; $params[] = $evento; }
    foreach (['de' => '>=', 'ate' => '<='] as $chave => $op) {
        $v = trim((string)($_GET[$chave] ?? ''));
        if ($v !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $v);
            if ($d && $d->format('Y-m-d') === $v) {
                $where[]  = "e.data_evento $op ?";
                $params[] = $v;
            }
        }
    }
    $busca = trim((string)($_GET['busca'] ?? ''));
    if ($busca !== '') {
        $where[]  = '(c.participante LIKE ? OR e.titulo LIKE ? OR u.nome LIKE ?)';
        $like     = '%' . $busca . '%';
        array_push($params, $like, $like, $like);
    }
    $sql = 'SELECT c.id, c.evento_id, e.titulo AS evento_titulo, e.data_evento, e.hora_inicio,
                   c.usuario_id, u.nome AS usuario_nome, c.participante, c.duracao_ms, c.quadros,
                   c.num_pessoas, c.modelo, c.similaridade_media, c.exercicio, c.repeticoes, c.criado_em
              FROM capturas c
              JOIN eventos e ON e.id = c.evento_id
         LEFT JOIN usuarios u ON u.id = c.usuario_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY c.criado_em DESC, c.id DESC LIMIT 2000';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    json_out(['capturas' => $st->fetchAll()]);
}

if ($metodo !== 'POST') {
    json_out(['erro' => 'Método não permitido.'], 405);
}

$u = exigir_login();
exigir_csrf();
$body = corpo_json();

// ------------------------------------------------------- POST excluir (admin)
if (($body['acao'] ?? '') === 'excluir') {
    $admin = mocap_exigir_admin_api();
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        json_out(['erro' => 'Captura inválida.'], 400);
    }
    $st = $pdo->prepare('DELETE FROM capturas WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        json_out(['erro' => 'Captura não encontrada.'], 404);
    }
    registrar_log($pdo, (int)$admin['id'], 'captura_excluir', "id $id");
    json_out(['ok' => true]);
}

// ------------------------------------------------- POST salvar (usuário logado)
$eventoId     = (int)($body['evento_id'] ?? 0);
$participante = trim((string)($body['participante'] ?? ''));
$duracaoMs    = max(0, (int)($body['duracao_ms'] ?? 0));
$quadros      = max(0, (int)($body['quadros'] ?? 0));
$numPessoas   = max(1, min(20, (int)($body['num_pessoas'] ?? 1)));
$modelo       = mb_substr(trim((string)($body['modelo'] ?? '')), 0, 40);
$simMedia     = isset($body['similaridade_media']) && $body['similaridade_media'] !== null && $body['similaridade_media'] !== ''
              ? max(0, min(100, (int)$body['similaridade_media'])) : null;
$exercicio    = isset($body['exercicio']) && $body['exercicio'] !== null && $body['exercicio'] !== ''
              ? mb_substr(trim((string)$body['exercicio']), 0, 40) : null;
$repeticoes   = isset($body['repeticoes']) && $body['repeticoes'] !== null && $body['repeticoes'] !== ''
              ? max(0, (int)$body['repeticoes']) : null;
$dados        = $body['dados'] ?? null;

if ($eventoId <= 0) {
    json_out(['erro' => 'Selecione um evento.'], 400);
}
if ($participante === '' || mb_strlen($participante) > 120) {
    json_out(['erro' => 'Informe o participante (até 120 caracteres).'], 400);
}
if ($quadros <= 0 || !is_array($dados) || empty($dados['frames']) || !is_array($dados['frames'])) {
    json_out(['erro' => 'Captura vazia: grave antes de salvar.'], 400);
}
$st = $pdo->prepare('SELECT id FROM eventos WHERE id = ?');
$st->execute([$eventoId]);
if (!$st->fetch()) {
    json_out(['erro' => 'Evento não encontrado.'], 404);
}

$dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($dadosJson === false) {
    json_out(['erro' => 'Dados da captura inválidos.'], 400);
}
$limite = 48 * 1024 * 1024; // LONGTEXT/JSON no MySQL suporta mais; limite prático de upload
if (strlen($dadosJson) > $limite) {
    json_out(['erro' => 'Captura muito grande para salvar no servidor (limite 48 MB). Exporte em JSON/CSV localmente.'], 413);
}

$st = $pdo->prepare(
    'INSERT INTO capturas (evento_id, usuario_id, participante, duracao_ms, quadros, num_pessoas, modelo,
                           similaridade_media, exercicio, repeticoes, dados, criado_em)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$st->execute([$eventoId, (int)$u['id'], $participante, $duracaoMs, $quadros, $numPessoas, $modelo,
              $simMedia, $exercicio, $repeticoes, $dadosJson]);
$id = (int)$pdo->lastInsertId();
registrar_log($pdo, (int)$u['id'], 'captura_salvar', "id $id evento $eventoId participante $participante");
json_out(['ok' => true, 'id' => $id], 201);
