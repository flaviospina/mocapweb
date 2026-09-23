# MoCap Web — CECAPE Dra. Zilda Arns

Sistema de captura de movimentos corporais e faciais 100% no navegador (webcam única),
com login obrigatório, capturas salvas por evento e painel administrativo.
Backend em PHP 8 + MySQL para hospedagem cPanel (HostGator). Este repositório é o
**projeto completo**: basta publicar a pasta.

## Estrutura

```
mocapweb/
├── index.php            ← o sistema (só é entregue a quem tem sessão)
├── index.html           ← apenas redireciona para index.php
├── login.php            ← tela de login (somente @scseduca.com.br)
├── admin.php            ← painel do administrador (perfil "admin")
├── .htaccess            ← reforços: página inicial, bloqueio de arquivos internos
├── api/
│   ├── config.example.php  ← copie para config.php e preencha o banco
│   ├── db.php           ← bootstrap: config, PDO, sessão, json_out, csrf, logs
│   ├── dominio.php      ← regra do e-mail institucional
│   ├── guarda.php       ← guardas de página/API (login e admin)
│   ├── login.php        ← POST {email, senha}
│   ├── logout.php       ← encerra a sessão
│   ├── me.php           ← usuário da sessão + token CSRF
│   ├── eventos.php      ← lista (logado) · criar/excluir (admin)
│   ├── capturas.php     ← salvar (logado) · listar/baixar/excluir (admin)
│   ├── usuarios.php     ← gestão de usuários (admin)
│   └── criar_admin.php  ← cria o primeiro admin; apagar depois
├── db/schema.sql        ← tabelas (idempotente)
├── models/              ← yolov8n-pose.onnx e yolov8s-pose.onnx (motor YOLO)  *não está no Git*
├── docs/                ← Guia-Marcacao-do-Piso.pdf                            *não está no Git*
├── apresentacao.html    ← tutorial/apresentação                                *não está no Git*
├── n8n/                 ← automações de e-mail (ver n8n/LEIA-ME.md)
└── LEIA-ME.md
```

Os arquivos marcados como *não está no Git* (modelos ONNX, PDF do guia e `apresentacao.html`)
fazem parte do sistema publicado, mas não foram versionados aqui. Restaure-os do backup para
`models/`, `docs/` e a raiz. Sem `models/`, o motor YOLO (acima de 6 pessoas) não funciona;
o MediaPipe continua funcionando.

## Instalação (cPanel, sem terminal)

1. **Banco**: em *MySQL® Databases* crie o banco e o usuário e dê todos os privilégios.
   No phpMyAdmin, selecione o banco e importe `db/schema.sql`. Pode ser reimportado sem perder dados.
2. **Arquivos**: envie a pasta inteira para `public_html/mocapweb/`. No Gerenciador de Arquivos,
   ative **Mostrar arquivos ocultos** para confirmar que o `.htaccess` subiu.
3. **Configuração**: copie `api/config.example.php` para `api/config.php` e preencha host,
   banco, usuário e senha. (Um `api/config.php` antigo com `define('DB_HOST', ...)` também é aceito.)
4. **Primeiro administrador**: abra `https://cecapescs.com.br/mocapweb/api/criar_admin.php`,
   crie a conta (e-mail `@scseduca.com.br`) e **apague o arquivo** em seguida.
   Se a tabela `usuarios` já tiver contas, o arquivo se recusa a funcionar.
5. Entre em `login.php`. No painel **Admin → Usuários**, cadastre os professores.

## Segurança e regras de acesso

- **Nenhuma página do sistema sem login.** O `index.php` verifica a sessão antes de enviar
  qualquer byte; `index.html` só redireciona. O JavaScript não tem "modo autônomo": se
  `api/me.php` não confirmar a sessão, volta ao login.
- **Somente e-mails `@scseduca.com.br`** entram e são cadastrados (HTML, JavaScript e PHP).
- **Painel admin só para o papel `admin`.** Qualquer outro papel (professor ou outro que
  venha a existir) recebe 403 "Acesso restrito" no `admin.php` e nas APIs administrativas.
  O link Admin só aparece no sistema para administradores.
- Senhas com bcrypt; bloqueio de 15 minutos após 5 tentativas; registro em `logs_acesso`.
- Sessão `MOCAPSESS` com cookie HttpOnly + SameSite=Lax (+ Secure em HTTPS) e
  `session_regenerate_id` no login; token CSRF em toda escrita (`X-CSRF-Token`).
- Consultas preparadas (PDO) em tudo; erros internos viram JSON genérico e vão para o log do PHP.
- `.htaccess` bloqueia acesso direto a `config.php`, `db.php`, `dominio.php`, `guarda.php`
  e a arquivos `.md/.csv/.json/.sql`.
- Botão **Sair** no sistema e no painel (`api/logout.php`).

## LGPD

- Nenhum vídeo vai para o servidor: só os pontos numéricos do esqueleto (33 keypoints).
- Salve o participante por **código ou apelido** (ex.: "Aluno 07"), nunca nome completo.
- Colete o consentimento dos responsáveis antes dos eventos e use HTTPS.

## Papéis

| Papel | Pode |
|---|---|
| `professor` | entrar no sistema, capturar, listar eventos e salvar capturas |
| `admin` | tudo do professor + painel: eventos, capturas (listar, baixar JSON, excluir, CSV), usuários (criar, senha, ativar/desativar, papel) |

## Contratos da API (resumo)

| Endpoint | Método | Quem | Corpo / resposta |
|---|---|---|---|
| `api/login.php` | POST | todos | `{email, senha}` → `{ok, usuario, csrf}`; 401/422/423 |
| `api/logout.php` | POST/GET | logado | `{ok}` / redireciona |
| `api/me.php` | GET | logado | `{usuario, csrf}`; 401 |
| `api/eventos.php` | GET | logado | `{eventos:[{id,titulo,data_evento,hora_inicio,hora_fim,local,total_capturas}]}` |
| `api/eventos.php` | POST | admin | `{acao:"criar",titulo,data_evento,hora_inicio,hora_fim?,local?}` · `{acao:"excluir",id}` |
| `api/capturas.php` | POST | logado | `{evento_id,participante,duracao_ms,quadros,num_pessoas,modelo,similaridade_media,exercicio,repeticoes,dados}` → `{ok,id}` |
| `api/capturas.php` | GET | admin | filtros `evento_id, de, ate, busca` → `{capturas:[...]}`; `?id=N&download=1` baixa o JSON |
| `api/capturas.php` | POST | admin | `{acao:"excluir",id}` |
| `api/usuarios.php` | GET/POST | admin | `{acao:"criar"|"senha"|"ativo"|"papel", ...}` |

Toda escrita (POST) exige o header `X-CSRF-Token` com o valor de `csrf` retornado por `api/me.php`.

## Testes de aceitação

- Aba anônima em `/mocapweb/`, `/mocapweb/index.html` e `/mocapweb/index.php`: cai no login sem mostrar a tela de carregamento.
- E-mail `@gmail.com`: barrado na tela e na API (422).
- Professor: entra, grava, salva captura; sem link Admin; `admin.php` responde "Acesso restrito".
- Admin: link Admin visível; painel abre; cria evento e usuário; baixa JSON; exporta CSV.
- **Sair**: volta ao login e `index.php` volta a exigir login.

## Automação de e-mails (n8n)

Os workflows de aviso de conta e de retorno aos formadores estão em `n8n/` (ver `n8n/LEIA-ME.md`).
