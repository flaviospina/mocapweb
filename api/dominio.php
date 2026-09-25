<?php
/**
 * MoCapWeb CECAPE — regra do e-mail institucional.
 * Somente e-mails do domínio abaixo podem entrar no sistema.
 * Para permitir mais de um domínio, acrescente na lista MOCAP_DOMINIOS_EMAIL.
 */
declare(strict_types=1);

if (!defined('MOCAP_DOMINIO_EMAIL'))  define('MOCAP_DOMINIO_EMAIL', 'scseduca.com.br');
if (!defined('MOCAP_DOMINIOS_EMAIL')) define('MOCAP_DOMINIOS_EMAIL', [MOCAP_DOMINIO_EMAIL]);

if (!function_exists('email_institucional_valido')) {
  function email_institucional_valido(string $email): bool {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $dominio = substr($email, strrpos($email, '@') + 1);
    return in_array($dominio, array_map('strtolower', MOCAP_DOMINIOS_EMAIL), true);
  }
}
