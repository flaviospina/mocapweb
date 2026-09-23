# MocapWeb • Tela de login com e-mail institucional (@scseduca.com.br)

Reconstrução da tela de login do MocapWeb com amarração ao domínio
**@scseduca.com.br**. Nada mais do projeto é alterado: `index.html`,
`admin.php`, `api/me.php`, `api/capturas.php` e `api/eventos.php` continuam iguais.

## Arquivos

| Arquivo | O que é |
|---|---|
| `login.php` | Tela de login completa (substitui a atual). Valida o domínio no HTML (`pattern`), no JavaScript e mostra as mensagens da API. Envia `{email, senha}` em JSON para `api/login.php` e, com sucesso, abre o destino (`?r=index.html` ou `admin.php`). |
| `api/dominio.php` | **Novo.** Regra do domínio em um único lugar (`MOCAP_DOMINIO_EMAIL`), com a função `email_institucional_valido()` usada pelo servidor. |
| `api/login.php` | Endpoint de autenticação original, com **uma verificação a mais**: e-mail fora do domínio recebe HTTP 422 antes de qualquer consulta ao banco. Todo o resto (bcrypt, bloqueio 15 min, logs, sessão, CSRF) permanece igual. |

## Login obrigatório antes de carregar a página principal

| Arquivo | O que faz |
|---|---|
| `index.php` | **Nova entrada do sistema.** Verifica a sessão no servidor e, sem login, redireciona (302) para `login.php?r=index.php` sem entregar nada da página principal. Logado, entrega o conteúdo do `index.html`. |
| `.htaccess` | Define `index.php` como página inicial da pasta e faz quem pedir `index.html` diretamente ser atendido por `index.php`. Também bloqueia acesso direto a `config.php`, `db.php`, `dominio.php` e `guarda.php`. Se já existir um `.htaccess` na pasta `mocapweb/`, junte o conteúdo em vez de substituir. |
| `index.html` | Mesma página publicada em 10/09/2026, com duas mudanças: o redirecionamento de fallback aponta para `index.php`, e o link **Admin** ao lado do nome só aparece para o perfil `admin`. |

Os endereços continuam os mesmos: `https://cecapescs.com.br/mocapweb/` abre o login se não houver sessão e o sistema se houver.

## Painel admin somente para o perfil `admin`

| Arquivo | O que faz |
|---|---|
| `api/guarda.php` | **Novo.** Funções `exigir_login_pagina()`, `exigir_admin_pagina()` e `exigir_admin_api()`. Só o papel `admin` é administrador; qualquer outro papel, existente ou criado depois (professor, formador, coordenador…), recebe **403 "Acesso restrito"** com botão para voltar ao MocapWeb. |

Para ativar no `admin.php`, acrescente logo após o `require` do `api/db.php`:

```php
require_once __DIR__ . '/api/guarda.php';
$usuarioLogado = exigir_admin_pagina();
```

Nos endpoints da API que só o administrador pode usar (por exemplo criar/excluir eventos
em `api/eventos.php`, listar/excluir capturas em `api/capturas.php`), use após o `require` do `db.php`:

```php
require_once __DIR__ . '/guarda.php';
exigir_admin_api();
```

Se me enviar `admin.php`, `api/eventos.php`, `api/capturas.php` e `api/db.php`, devolvo os
arquivos completos com as guardas já integradas.

## Onde a regra é aplicada

1. **HTML**: o campo de e-mail tem `pattern` e `placeholder` do domínio; o navegador já marca em vermelho.
2. **JavaScript** (`login.php`): antes de chamar a API, o e-mail é normalizado (minúsculas, sem espaços) e testado contra `^[a-z0-9._%+-]+@scseduca\.com\.br$`. Fora do domínio, nem chega ao servidor.
3. **PHP** (`api/login.php` + `api/dominio.php`): a mesma regra no servidor, para o caso de alguém chamar `api/login.php` direto (sem passar pela tela). Resposta: HTTP 422 com `{"erro":"Use seu e-mail institucional @scseduca.com.br."}`.

## Como publicar (cPanel → Gerenciador de arquivos)

1. Na pasta do sistema (`public_html/mocapweb/`): envie `login.php`, `index.php`, `index.html` e
   `.htaccess` (substituindo `login.php` e `index.html`; `index.php` e `.htaccess` são novos).
2. Em `public_html/mocapweb/api/`: envie `dominio.php` e `guarda.php` (novos) e `login.php` (substituindo).
3. Acrescente a guarda no `admin.php` (duas linhas, ver seção acima) ou me envie o arquivo.
4. Teste sem estar logado: abrir `https://cecapescs.com.br/mocapweb/` deve ir direto para o login,
   sem mostrar a tela escura de carregamento.
5. Teste com e-mail `@gmail.com` (barrado na tela), depois com um `@scseduca.com.br` de perfil
   professor: o link **Admin** não aparece e `admin.php` responde "Acesso restrito". Com perfil
   `admin`, o painel abre normalmente.

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
