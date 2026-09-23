<?php
// api/config_normalizar.php
// Lê o api/config.php em qualquer um dos formatos já usados no projeto e devolve
// sempre ['host','db','user','pass','charset','dsn'].
//
// Formatos aceitos:
//   1) return ['host'=>..., 'db'=>..., 'user'=>..., 'pass'=>...]            (atual)
//      com chaves alternativas: dbname/database/name/banco, username/usuario, password/senha
//   2) define('DB_HOST', ...); define('DB_NAME'|'DB_DATABASE'|'DB_DBNAME', ...); DB_USER|DB_USERNAME; DB_PASS|DB_PASSWORD
//   3) variáveis: $db_host, $db_name|$db_database|$dbname, $db_user|$db_username, $db_pass|$db_password
//   4) 'dsn' => 'mysql:host=...;dbname=...' (avançado; usado nos testes com SQLite)

declare(strict_types=1);

/**
 * @param array $bruto  array retornado pelo config.php OU variáveis definidas por ele
 * @return array{host:string,db:string,user:string,pass:string,charset:string,dsn:string}
 */
function mocap_config_normalizar(array $bruto): array
{
    // Índice com chaves em minúsculas, para aceitar "DB_NAME", "dbName", "dbname"...
    $mapa = [];
    foreach ($bruto as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $mapa[strtolower((string)$k)] = $v === null ? '' : (string)$v;
        }
    }
    // Um config antigo pode aninhar em ['db' => [...]] ou ['database' => [...]].
    foreach (['db', 'database', 'mysql', 'banco'] as $grupo) {
        if (isset($bruto[$grupo]) && is_array($bruto[$grupo])) {
            foreach ($bruto[$grupo] as $k => $v) {
                if (is_scalar($v)) {
                    $mapa[strtolower((string)$k)] = (string)$v;
                }
            }
        }
    }

    $pegar = static function (array $chaves, array $constantes, string $padrao = '') use ($mapa): string {
        foreach ($chaves as $c) {
            if (isset($mapa[$c]) && $mapa[$c] !== '') {
                return $mapa[$c];
            }
        }
        foreach ($constantes as $c) {
            if (defined($c) && (string)constant($c) !== '') {
                return (string)constant($c);
            }
        }
        return $padrao;
    };

    $host    = $pegar(['host', 'db_host', 'dbhost', 'servidor', 'hostname'], ['DB_HOST', 'DB_SERVER', 'DB_HOSTNAME'], 'localhost');
    $db      = $pegar(['db', 'dbname', 'db_name', 'database', 'db_database', 'name', 'banco', 'nome_banco'], ['DB_NAME', 'DB_DATABASE', 'DB_DBNAME', 'DB_DB', 'DB_BANCO']);
    $user    = $pegar(['user', 'username', 'db_user', 'db_username', 'dbuser', 'usuario'], ['DB_USER', 'DB_USERNAME', 'DB_USUARIO']);
    $pass    = $pegar(['pass', 'password', 'db_pass', 'db_password', 'dbpass', 'senha'], ['DB_PASS', 'DB_PASSWORD', 'DB_SENHA']);
    $charset = $pegar(['charset', 'db_charset'], ['DB_CHARSET'], 'utf8mb4');
    $dsn     = $pegar(['dsn', 'db_dsn'], ['DB_DSN']);

    // Se o array trouxe 'db' como sub-array (formato aninhado), $db pode ter virado vazio: já tratado acima.
    if ($dsn === '') {
        if ($db === '') {
            throw new RuntimeException('api/config.php não informa o nome do banco (use a chave "db" ou a constante DB_NAME).');
        }
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $db, $charset);
    }

    return ['host' => $host, 'db' => $db, 'user' => $user, 'pass' => $pass, 'charset' => $charset, 'dsn' => $dsn];
}
