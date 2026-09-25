<?php
/**
 * MoCapWeb CECAPE — configuração do banco de dados.
 *
 * 1. Copie este arquivo para  api/config.php
 * 2. Preencha com os dados do cPanel (MySQL® Databases)
 * 3. NUNCA envie o config.php para repositórios públicos.
 *
 * ATENÇÃO (HostGator/cPanel): o nome do banco e o nome do usuário
 * levam o prefixo da conta, ex.:  itthri79_mocapweb  e  itthri79_mocap
 * O usuário precisa estar adicionado ao banco com ALL PRIVILEGES
 * (cPanel → MySQL Databases → Add User To Database).
 *
 * As chaves abaixo devem ficar EXATAMENTE com estes nomes.
 * Se 'dbname' ficar vazio, o MySQL responde "1046 No database selected".
 */
return [
  'host'   => 'localhost',
  'dbname' => 'itthri79_mocapweb',      // nome COMPLETO, com prefixo
  'user'   => 'itthri79_mocap',         // usuário COMPLETO, com prefixo
  'pass'   => 'SENHA_DO_USUARIO_DO_BANCO',
];
