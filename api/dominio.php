<?php
// api/dominio.php
// Regra de e-mail institucional do MocapWeb (CECAPE).
// Único ponto de verdade para o domínio aceito no login.
// Não depende de banco nem de sessão: pode ser incluído em qualquer arquivo.

declare(strict_types=1);

const MOCAP_DOMINIO_EMAIL = 'scseduca.com.br';

/**
 * Normaliza o e-mail digitado (espaços e maiúsculas).
 */
function email_institucional_normalizar(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

/**
 * Verifica se o e-mail é válido e pertence ao domínio institucional.
 * Aceita somente "alguem@scseduca.com.br" (subdomínios não são aceitos).
 */
function email_institucional_valido(string $email): bool
{
    $email = email_institucional_normalizar($email);
    if ($email === '' || strlen($email) > 254) {
        return false;
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    $arroba = strrpos($email, '@');
    if ($arroba === false) {
        return false;
    }
    return substr($email, $arroba + 1) === MOCAP_DOMINIO_EMAIL;
}

/**
 * Guarda para os endpoints da API (api/login.php).
 * Se o e-mail não for institucional, responde 422 em JSON e encerra,
 * antes de qualquer consulta ao banco de dados.
 */
function exigir_email_institucional(?string $email): void
{
    if ($email === null || !email_institucional_valido($email)) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['erro' => 'Use seu e-mail institucional @' . MOCAP_DOMINIO_EMAIL . '.'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}
