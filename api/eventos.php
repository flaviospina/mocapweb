<?php
/**
 * /api/eventos.php
 *   GET                        -> lista de eventos (qualquer usuário logado)
 *                                 { eventos: [{id, titulo, data_evento, hora_inicio, hora_fim, local, total_capturas}] }
 *   POST {acao:"criar", ...}   -> cria evento (somente admin, exige X-CSRF-Token)
 *   POST {acao:"excluir", id}  -> exclui evento e suas capturas (somente admin)
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require_once __DIR__ . '/guarda.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    exigir_login();
    $st = $pdo->query(
        'SELECT e.id, e.titulo, e.data_evento, e.hora_inicio, e.hora_fim, e.local,
                (SELECT COUNT(*) FROM capturas c WHERE c.evento_id = e.id) AS total_capturas
           FROM eventos e
          ORDER BY e.data_evento DESC, e.hora_inicio DESC, e.id DESC'
    );
    json_out(['eventos' => $st->fetchAll()]);
}

if ($metodo !== 'POST') {
    json_out(['erro' => 'Método não permitido.'], 405);
}

$admin = mocap_exigir_admin_api();
exigir_csrf();
$body = corpo_json();
$acao = (string)($body['acao'] ?? 'criar');

if ($acao === 'excluir') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        json_out(['erro' => 'Evento inválido.'], 400);
    }
    $pdo->prepare('DELETE FROM capturas WHERE evento_id = ?')->execute([$id]);
    $st = $pdo->prepare('DELETE FROM eventos WHERE id = ?');
    $st->execute([$id]);
    if ($st->rowCount() === 0) {
        json_out(['erro' => 'Evento não encontrado.'], 404);
    }
    registrar_log($pdo, (int)$admin['id'], 'evento_excluir', "id $id");
    json_out(['ok' => true]);
}

if ($acao !== 'criar') {
    json_out(['erro' => 'Ação desconhecida.'], 400);
}

$titulo = trim((string)($body['titulo'] ?? ''));
$data   = trim((string)($body['data_evento'] ?? ''));
$hIni   = trim((string)($body['hora_inicio'] ?? ''));
$hFim   = trim((string)($body['hora_fim'] ?? ''));
$local  = trim((string)($body['local'] ?? ''));

$erros = [];
if ($titulo === '' || mb_strlen($titulo) > 150) {
    $erros[] = 'Informe o título (até 150 caracteres).';
}
$dt = DateTime::createFromFormat('Y-m-d', $data);
if (!$dt || $dt->format('Y-m-d') !== $data) {
    $erros[] = 'Informe a data no formato AAAA-MM-DD.';
}
foreach (['hora_inicio' => $hIni, 'hora_fim' => $hFim] as $campo => $valor) {
    if ($valor !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $valor)) {
        $erros[] = "Horário inválido em $campo (use HH:MM).";
    }
}
if ($hIni === '') {
    $erros[] = 'Informe o horário de início.';
}
if (mb_strlen($local) > 150) {
    $erros[] = 'Local com mais de 150 caracteres.';
}
if ($erros) {
    json_out(['erro' => implode(' ', $erros)], 400);
}

$hIni = substr($hIni . ':00', 0, 8);
$hFim = $hFim === '' ? null : substr($hFim . ':00', 0, 8);

$st = $pdo->prepare('INSERT INTO eventos (titulo, data_evento, hora_inicio, hora_fim, local, criado_por, criado_em) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$st->execute([$titulo, $data, $hIni, $hFim, $local === '' ? null : $local, (int)$admin['id']]);
$id = (int)$pdo->lastInsertId();
registrar_log($pdo, (int)$admin['id'], 'evento_criar', "id $id: $titulo");
json_out(['ok' => true, 'id' => $id], 201);
