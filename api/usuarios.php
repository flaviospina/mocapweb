<?php
/**
 * Controle de acesso dos usuários (somente administradores).
 *
 * GET  /api/usuarios.php
 *   → { ok, usuarios:[{ id, nome, instituicao, email, papel, ativo, precisa_trocar_senha,
 *        primeiro_login_em, ultimo_login, senha_alterada_em, situacao }], resumo:{...} }
 *   situacao: 'nunca_acessou' | 'pendente_troca' | 'ok'
 *
 * POST /api/usuarios.php  { id, acao }   (exige token CSRF)
 *   acao = 'forcar_troca'    → obriga o usuário a definir nova senha no próximo login
 *   acao = 'redefinir_senha' → volta para a senha inicial "scseduca" e obriga a troca
 *   acao = 'ativar' | 'desativar'
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

const SENHA_INICIAL = 'scseduca';
$u = exigir_papel('admin');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
  $st = $pdo->query(
    'SELECT id, nome, instituicao, email, papel, ativo, precisa_trocar_senha,
            primeiro_login_em, ultimo_login, senha_alterada_em, criado_em
       FROM usuarios
      ORDER BY papel, nome'
  );
  $lista = [];
  $resumo = ['total' => 0, 'nunca_acessou' => 0, 'pendente_troca' => 0, 'ok' => 0];
  foreach ($st->fetchAll() as $r) {
    $r['ativo'] = (int)$r['ativo'];
    $r['precisa_trocar_senha'] = (int)$r['precisa_trocar_senha'];
    if ($r['primeiro_login_em'] === null)          $r['situacao'] = 'nunca_acessou';
    elseif ($r['precisa_trocar_senha'] === 1)      $r['situacao'] = 'pendente_troca';
    else                                           $r['situacao'] = 'ok';
    $resumo['total']++;
    $resumo[$r['situacao']]++;
    $lista[] = $r;
  }
  json_out(['ok' => true, 'usuarios' => $lista, 'resumo' => $resumo]);
}

if ($metodo === 'POST') {
  require_csrf();
  $b = corpo_json();
  $id   = (int)($b['id'] ?? 0);
  $acao = (string)($b['acao'] ?? '');
  if ($id <= 0) json_out(['erro' => 'Usuário inválido.'], 400);

  $st = $pdo->prepare('SELECT id, nome, email, papel FROM usuarios WHERE id = ?');
  $st->execute([$id]);
  $alvo = $st->fetch();
  if (!$alvo) json_out(['erro' => 'Usuário não encontrado.'], 404);

  switch ($acao) {
    case 'forcar_troca':
      $pdo->prepare('UPDATE usuarios SET precisa_trocar_senha = 1 WHERE id = ?')->execute([$id]);
      registrar_log($pdo, (int)$u['id'], 'admin_forcar_troca', $alvo['email']);
      json_out(['ok' => true, 'mensagem' => $alvo['nome'] . ' terá de definir uma nova senha no próximo acesso.']);

    case 'redefinir_senha':
      $pdo->prepare('UPDATE usuarios SET senha_hash = ?, precisa_trocar_senha = 1, senha_alterada_em = NULL,
                        tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?')
          ->execute([password_hash(SENHA_INICIAL, PASSWORD_BCRYPT), $id]);
      registrar_log($pdo, (int)$u['id'], 'admin_redefinir_senha', $alvo['email']);
      json_out(['ok' => true, 'mensagem' => 'Senha de ' . $alvo['nome'] . ' voltou para a senha inicial; a troca será exigida no próximo acesso.']);

    case 'ativar':
    case 'desativar':
      if ($id === (int)$u['id']) json_out(['erro' => 'Você não pode desativar a sua própria conta.'], 400);
      $ativo = $acao === 'ativar' ? 1 : 0;
      $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')->execute([$ativo, $id]);
      registrar_log($pdo, (int)$u['id'], 'admin_' . $acao, $alvo['email']);
      json_out(['ok' => true, 'mensagem' => $alvo['nome'] . ($ativo ? ' reativado(a).' : ' desativado(a): não consegue mais entrar.')]);

    default:
      json_out(['erro' => 'Ação desconhecida.'], 400);
  }
}

json_out(['erro' => 'Método não permitido.'], 405);
