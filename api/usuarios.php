<?php
/**
 * /api/usuarios.php  (somente admin; escritas exigem X-CSRF-Token)
 *   GET                                        -> { usuarios: [...] }
 *   POST {acao:"criar", nome, email, papel, senha}
 *   POST {acao:"senha", id, senha}             -> redefine a senha e desbloqueia
 *   POST {acao:"ativo", id, ativo: true|false} -> ativa/desativa (não permite desativar a si mesmo)
 *   POST {acao:"papel", id, papel}             -> muda o papel (não permite tirar o próprio admin)
 * E-mails aceitos: somente @scseduca.com.br (api/dominio.php).
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require_once __DIR__ . '/guarda.php';
require_once __DIR__ . '/dominio.php';

const PAPEIS_VALIDOS = ['admin', 'professor'];

$admin  = mocap_exigir_admin_api();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $st = $pdo->query('SELECT id, nome, email, papel, ativo, tentativas_login, bloqueado_ate, ultimo_login, criado_em FROM usuarios ORDER BY nome');
    json_out(['usuarios' => $st->fetchAll()]);
}
if ($metodo !== 'POST') {
    json_out(['erro' => 'Método não permitido.'], 405);
}

exigir_csrf();
$body = corpo_json();
$acao = (string)($body['acao'] ?? '');

function validar_senha(string $senha): void
{
    if (strlen($senha) < 8 || strlen($senha) > 72) {
        json_out(['erro' => 'A senha deve ter entre 8 e 72 caracteres.'], 400);
    }
}

if ($acao === 'criar') {
    $nome  = trim((string)($body['nome'] ?? ''));
    $email = email_institucional_normalizar((string)($body['email'] ?? ''));
    $papel = (string)($body['papel'] ?? 'professor');
    $senha = (string)($body['senha'] ?? '');

    if ($nome === '' || mb_strlen($nome) > 120) {
        json_out(['erro' => 'Informe o nome (até 120 caracteres).'], 400);
    }
    if (!email_institucional_valido($email)) {
        json_out(['erro' => 'Use um e-mail institucional @' . MOCAP_DOMINIO_EMAIL . '.'], 422);
    }
    if (!in_array($papel, PAPEIS_VALIDOS, true)) {
        json_out(['erro' => 'Papel inválido.'], 400);
    }
    validar_senha($senha);

    $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) {
        json_out(['erro' => 'Já existe usuário com este e-mail.'], 409);
    }
    $st = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, papel, ativo, tentativas_login, bloqueado_ate, criado_em) VALUES (?, ?, ?, ?, 1, 0, NULL, NOW())');
    $st->execute([$nome, $email, password_hash($senha, PASSWORD_BCRYPT), $papel]);
    $id = (int)$pdo->lastInsertId();
    registrar_log($pdo, (int)$admin['id'], 'usuario_criar', "id $id $email ($papel)");
    json_out(['ok' => true, 'id' => $id], 201);
}

$id = (int)($body['id'] ?? 0);
if ($id <= 0) {
    json_out(['erro' => 'Usuário inválido.'], 400);
}
$st = $pdo->prepare('SELECT id, email, papel FROM usuarios WHERE id = ?');
$st->execute([$id]);
$alvo = $st->fetch();
if (!$alvo) {
    json_out(['erro' => 'Usuário não encontrado.'], 404);
}

if ($acao === 'senha') {
    $senha = (string)($body['senha'] ?? '');
    validar_senha($senha);
    $pdo->prepare('UPDATE usuarios SET senha_hash = ?, tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?')
        ->execute([password_hash($senha, PASSWORD_BCRYPT), $id]);
    registrar_log($pdo, (int)$admin['id'], 'usuario_senha', "id $id {$alvo['email']}");
    json_out(['ok' => true]);
}

if ($acao === 'ativo') {
    $ativo = !empty($body['ativo']) ? 1 : 0;
    if ($id === (int)$admin['id'] && $ativo === 0) {
        json_out(['erro' => 'Você não pode desativar a própria conta.'], 400);
    }
    $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')->execute([$ativo, $id]);
    registrar_log($pdo, (int)$admin['id'], $ativo ? 'usuario_ativar' : 'usuario_desativar', "id $id {$alvo['email']}");
    json_out(['ok' => true]);
}

if ($acao === 'papel') {
    $papel = (string)($body['papel'] ?? '');
    if (!in_array($papel, PAPEIS_VALIDOS, true)) {
        json_out(['erro' => 'Papel inválido.'], 400);
    }
    if ($id === (int)$admin['id'] && $papel !== 'admin') {
        json_out(['erro' => 'Você não pode remover o próprio perfil de administrador.'], 400);
    }
    $pdo->prepare('UPDATE usuarios SET papel = ? WHERE id = ?')->execute([$papel, $id]);
    registrar_log($pdo, (int)$admin['id'], 'usuario_papel', "id $id {$alvo['email']} -> $papel");
    json_out(['ok' => true]);
}

json_out(['erro' => 'Ação desconhecida.'], 400);
