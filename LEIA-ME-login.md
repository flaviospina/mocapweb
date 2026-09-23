# MocapWeb • Login obrigatório, e-mail institucional e painel só para admin

## Como funciona agora (sem brechas conhecidas)

| Arquivo | Papel |
|---|---|
| `index.php` | **O sistema inteiro está aqui.** O PHP verifica a sessão `MOCAPSESS` antes de enviar qualquer byte; sem login responde 302 para `login.php?r=index.php`. Não depende de `.htaccess`, de `DirectoryIndex` nem de `api/db.php`. |
| `index.html` | **Só redireciona** para `index.php` (não contém mais o sistema). Mesmo que o servidor sirva o arquivo estático, o visitante cai no `index.php` e, sem sessão, no login. |
| `login.php` | Tela de login. Aceita somente `@scseduca.com.br` (HTML + JavaScript). Envia `{email, senha}` para `api/login.php`; com sucesso abre o destino (`index.php` ou `admin.php`). |
| `api/login.php` | Autenticação original + regra do domínio no servidor (422 fora do `@scseduca.com.br`). |
| `api/dominio.php` | Regra do domínio (uma fonte só). |
| `api/guarda.php` | Guardas `mocap_exigir_login_pagina()`, `mocap_exigir_admin_pagina()` e `mocap_exigir_admin_api()`. Só o papel `admin` é administrador; qualquer outro papel recebe 403 "Acesso restrito". Nomes com prefixo `mocap_` para não colidir com o `api/db.php`. |
| `api/logout.php` | Botão **Sair** do sistema: destrói a sessão e apaga o cookie. Não depende do banco. |
| `.htaccess` | Reforço: `index.php` como página inicial, `index.html` atendido pelo `index.php`, bloqueio de acesso direto a `config.php`, `db.php`, `dominio.php`, `guarda.php` e a arquivos `.md/.csv/.json/.sql`. |

Dentro do `index.php`, o JavaScript também mudou:

- **Não existe mais "modo autônomo"**: se `api/me.php` não responder 200 com usuário, volta ao login.
- O link **Admin** só aparece para `papel === 'admin'` (o servidor bloqueia `admin.php` de qualquer forma).
- Novo link **Sair** ao lado do nome.

## Brechas encontradas e fechadas

1. **`index.html` estático era o sistema.** O Apache entrega arquivos `.html` sem passar pelo PHP, e o `DirectoryIndex` padrão do cPanel prefere `index.html` a `index.php`. Bastava abrir `index.html` (ou a pasta) para carregar tudo; o login só era pedido depois, pelo JavaScript. Agora o sistema só existe dentro do `index.php`.
2. **"Modo autônomo" no JavaScript.** Se a chamada a `api/me.php` falhasse (rede, bloqueio, 404), o sistema seguia funcionando sem login. Removido.
3. **Link Admin para todos e `admin.php` sem verificação de papel.** O link só aparece para admin, e `admin.php` precisa da guarda (abaixo).
4. **Sem botão de sair.** Em computador compartilhado a sessão ficava aberta. Adicionado `Sair` + `api/logout.php`.
5. **Dependência de arquivo oculto.** O `.htaccess` não aparece no Gerenciador de Arquivos do cPanel sem "Mostrar arquivos ocultos", e um envio incompleto deixava tudo aberto. A proteção agora não depende dele.
6. **Colisão de nomes com o `api/db.php`.** As funções da guarda ganharam prefixo `mocap_`.

## Painel admin: o que falta no servidor

O `admin.php` **não está neste repositório** e no servidor ele está respondendo 404.
Para o painel voltar restrito ao administrador, o arquivo original precisa começar com:

```php
<?php
require __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/guarda.php';
$usuarioLogado = mocap_exigir_admin_pagina();
```

e os endpoints que só o administrador pode usar (`api/eventos.php` para criar/excluir eventos,
`api/capturas.php` para listar/excluir capturas) devem chamar, logo após o `require` do `db.php`:

```php
require_once __DIR__ . '/guarda.php';
mocap_exigir_admin_api();
```

**Me envie `admin.php`, `api/db.php`, `api/me.php`, `api/eventos.php` e `api/capturas.php`
(ou um .zip da pasta original) e eu devolvo todos completos e integrados.**

## Situação do servidor em 23/09/2026

A pasta `mocapweb/` no servidor está com o conteúdo deste repositório e **sem** vários
arquivos originais: `admin.php`, `api/me.php`, `api/eventos.php`, `api/capturas.php`,
`apresentacao.html`, `models/` e `docs/` respondem 404. Tudo que passa pelo `api/db.php`
(`index.php`, `api/login.php`) responde 500 em branco, o que indica erro no `api/db.php`
ou no `api/config.php` (por exemplo, dados do banco incorretos).

**Este repositório não é o projeto completo.** Ele contém só a área de login e a entrada
protegida. Os demais arquivos do MocapWeb precisam voltar para a pasta a partir do
backup/original.

## Como publicar (cPanel → Gerenciador de Arquivos)

1. Em **Configurações** do Gerenciador de Arquivos, marque **Mostrar arquivos ocultos** (para o `.htaccess`).
2. Restaure os arquivos originais que faltam (lista acima) a partir do backup.
3. Na raiz de `public_html/mocapweb/`: envie `index.php`, `index.html`, `login.php` e `.htaccess`, substituindo os atuais.
4. Em `public_html/mocapweb/api/`: envie `login.php`, `dominio.php`, `guarda.php` e `logout.php`.
5. Acrescente as linhas da guarda no `admin.php` (ou me envie o arquivo).
6. Confira o `api/config.php` (host, banco, usuário, senha); enquanto ele estiver errado, o login responde 500.

## Testes de aceitação

- Aba anônima em `https://cecapescs.com.br/mocapweb/`, `…/index.html` e `…/index.php`: cai no login **sem** mostrar a tela escura de carregamento.
- E-mail `@gmail.com`: barrado na tela. E-mail `@scseduca.com.br` de professor: entra; sem link Admin; `…/admin.php` responde "Acesso restrito".
- Perfil `admin`: link Admin aparece e o painel abre.
- Botão **Sair**: volta ao login e `…/index.php` volta a exigir login.
