# MocapWeb • Tela de login com e-mail institucional (@scseduca.com.br)

Reconstrução da tela de login do MocapWeb com amarração ao domínio
**@scseduca.com.br**. Nada mais do projeto é alterado: `index.html`,
`admin.php`, `api/me.php`, `api/capturas.php` e `api/eventos.php` continuam iguais.

## Arquivos

| Arquivo | O que é |
|---|---|
| `login.php` | Tela de login completa (substitui a atual). Valida o domínio no HTML (`pattern`), no JavaScript e mostra as mensagens da API. Envia `{email, senha}` em JSON para `api/login.php` e, com sucesso, abre o destino (`?r=index.html` ou `admin.php`). |
| `api/dominio.php` | **Novo.** Regra do domínio em um único lugar (`MOCAP_DOMINIO_EMAIL`), com a função `exigir_email_institucional()` para o servidor recusar (HTTP 422) qualquer e-mail fora do domínio antes de consultar o banco. |

## Onde a regra é aplicada

1. **HTML**: o campo de e-mail tem `pattern` e `placeholder` do domínio; o navegador já marca em vermelho.
2. **JavaScript** (`login.php`): antes de chamar a API, o e-mail é normalizado (minúsculas, sem espaços) e testado contra `^[a-z0-9._%+-]+@scseduca\.com\.br$`. Fora do domínio, nem chega ao servidor.
3. **PHP** (`api/dominio.php`): a mesma regra no servidor, para o caso de alguém chamar `api/login.php` direto (sem passar pela tela).

## Como publicar (cPanel → Gerenciador de arquivos)

1. Envie `login.php` para a pasta do sistema (`public_html/mocapweb/`), substituindo o atual.
2. Envie `api/dominio.php` para `public_html/mocapweb/api/`.
3. Abra `api/login.php` no editor do cPanel e acrescente estas duas linhas logo
   depois de ler o e-mail do corpo da requisição (antes de qualquer consulta ao banco):

   ```php
   require_once __DIR__ . '/dominio.php';
   exigir_email_institucional($email); // use o nome da variável que já recebe o e-mail
   ```

   Se preferir, me envie o `api/login.php` atual e eu devolvo o arquivo completo com a
   regra integrada, sem edição manual.

## Comportamento da tela

- Se já houver sessão válida (`api/me.php` responde 200), redireciona direto para o destino.
- Parâmetro `?r=` aceita somente nomes simples de arquivo da mesma pasta (`index.html`, `admin.php`); qualquer outro valor cai em `index.html`.
- Mensagens tratadas: e-mail fora do domínio (422), e-mail ou senha inválidos (401), conta bloqueada por 15 min após 5 tentativas (423/429), servidor indisponível (5xx) e falha de conexão.
- "Esqueci minha senha" orienta a procurar o administrador do CECAPE, como no tutorial.
- Botão mostrar/ocultar senha, `autocomplete` para gerenciadores de senha, sem cache da página.

## Situação do servidor em 22/09/2026

`login.php`, `api/me.php` e `api/login.php` estão respondendo **HTTP 500** com a
mensagem "Configuração ausente: copie api/config.example.php para api/config.php e
preencha os dados do banco". Ou seja, o `api/config.php` não está no servidor.
Enquanto isso não for corrigido, nenhuma tela de login funciona; a tela nova mostra
"O servidor está indisponível no momento. Avise a equipe de TI do CECAPE."
