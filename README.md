# MoCapWeb — CECAPE

Sistema de captura de movimentos corporais e faciais 100% no navegador (webcam única), desenvolvido para o CECAPE Dra. Zilda Arns — São Caetano do Sul.

## Funcionalidades

- **Captura multi-pessoa com dois motores** (seletor "Motor de detecção"):
  - **MediaPipe Pose Landmarker** — 1 a 6 pessoas, com pontos 3D (permite exportar BVH). É um modelo de uma pessoa que o Google estende repetindo o detector; ele **suprime pessoas muito próximas** ([issue #4681](https://github.com/google-ai-edge/mediapipe/issues/4681)) e não garante mais do que poucas pessoas ([issue #5842](https://github.com/google-ai-edge/mediapipe/issues/5842)) — por isso "6 configuradas, 3 detectadas".
  - **YOLOv8-pose** (`models/yolov8n-pose.onnx` e `yolov8s-pose.onnx`, via ONNX Runtime Web) — **até 20 pessoas**: modelo de estágio único que detecta todas as pessoas do quadro de uma vez, sem a supressão de vizinhos e com custo quase constante. Usa **WebGPU** quando disponível (Chrome/Edge) e cai para CPU (WASM) automaticamente. Acima de 6 pessoas o sistema troca para este motor sozinho.
- **Identidade estável (P1, P2…)** — um rastreador casa cada detecção com a pessoa do quadro anterior (posição prevista + tamanho do tronco) e mantém a identidade por ~2 s quando a pessoa some (ex.: passa atrás de outra), religando o mesmo número ao reaparecer. Cada pessoa tem esqueleto em cor própria e etiqueta P1, P2…
- **Grade de calibração** (botão no painel Visualização) — mostra o limite da câmera, a **zona segura** e a grade A/B/C × 1/2/3 para marcar o chão com fita; veja `docs/Guia-Marcacao-do-Piso.pdf`
- **Malha facial** (boca, olhos, sobrancelhas, expressões) para vários rostos (Face Landmarker)
- **Tela 100% sem rolagem** — o vídeo usa `object-fit: contain`, mostrando o **corpo inteiro** sem cortes; botão **⛶ Tela cheia** (tecla `F` ou duplo clique) maximiza o vídeo no monitor sem bordas
- **Gravação contínua em segundo plano** — a gravação **não para ao trocar de aba** (ex.: abrir o YouTube para imitar movimentos de um vídeo):
  - o loop de detecção é comandado por um **Web Worker**, imune ao throttling que o navegador aplica a abas ocultas;
  - o vídeo é capturado com `captureStream(0)` + `requestFrame()` explícito a cada quadro;
  - uma **janela flutuante sempre visível** (Document Picture-in-Picture, Chrome/Edge 116+) mostra a prévia da câmera, o cronômetro e o botão **⏹ Parar gravação** por cima de qualquer aba (fallback: popup em navegadores sem a API)
- **Ângulos articulares** em tempo real (joelhos e cotovelos) com gráfico histórico, por pessoa selecionável
- **Modo espelho (professor/aluno)** — use um **arquivo de vídeo** ou uma **aba do YouTube** (captura de aba autorizada pelo usuário via `getDisplayMedia`) como referência lado a lado; o sistema detecta **todas as pessoas do vídeo e da câmera**, confere se as quantidades batem (Vídeo N · Câmera M ✓/✗), pareia as pessoas por posição esquerda→direita e dá uma **pontuação de semelhança (0–100%)** em tempo real — exibida também na janela flutuante durante a gravação, tolerante a espelhamento esquerda/direita
- **Contador de repetições** — agachamento, polichinelo, flexão de cotovelo e elevação de braços, contados automaticamente pelos ângulos articulares
- **Reprodução da captura** — reveja o esqueleto gravado quadro a quadro, com play/pause e barra de avanço (scrubbing)
- **Exportação**: vídeo MP4/WebM (câmera + esqueletos), CSV e JSON com os 33 keypoints por pessoa e por frame, e **BVH** (esqueleto animado importável no Blender: File → Import → Motion Capture .bvh)

## Desempenho (movimento fluido, sem "picote")

- Modelo **lite** por padrão (~3× mais rápido; seletor Rápido/Equilibrado/Preciso na barra lateral)
- Loop de detecção via `requestAnimationFrame` (60 Hz, sincronizado com o monitor) quando a aba está visível + Web Worker quando oculta
- Canvas só é redimensionado quando o tamanho muda (redimensionar realoca o buffer a cada quadro)
- Painéis laterais (keypoints, ângulos, gráfico) atualizados a ~6 Hz em vez de a cada quadro
- Malha facial processada a cada 2 quadros
- Se ainda estiver lento: reduza o **máx. de pessoas**, desligue **Rosto**, ou use um navegador com aceleração de GPU ativada
- No motor YOLO: em computadores **sem WebGPU** a análise roda na CPU (~2–6 fps); use "Resolução de análise" 320/416 px e o modelo **nano**. Com WebGPU (Chrome/Edge em Windows/macOS) roda a 20–60 fps mesmo com 20 pessoas. O seletor **Processamento** (Automático / Placa de vídeo / Processador) força um modo; se a placa de vídeo travar, o sistema muda para CPU sozinho.
- **Arquivos do YOLO servidos pelo próprio site** (sem depender de CDN): `models/yolov8n-pose.onnx` (13.486.313 bytes) e opcionalmente `models/yolov8s-pose.onnx` (46.912.414 bytes); `lib/ort/` com o ONNX Runtime 1.29.0 — `ort.webgpu.min.js`, `ort-wasm-simd-threaded.asyncify.mjs` e `ort-wasm-simd-threaded.asyncify.wasm` (25.749.873 bytes). Os `.htaccess` dessas pastas definem os tipos MIME corretos (`text/javascript` para `.mjs`, `application/wasm` para `.wasm`), obrigatórios para o navegador aceitar os módulos.
- **Autoteste**: `yolo-teste.html` verifica passo a passo (WebGPU, arquivos e tamanhos, MIME, carregamento do motor, inferência na CPU e na GPU, Web Worker) e gera um relatório para copiar. Link "🔧 Testar o motor YOLO" no painel.

## Login, banco de dados e página administrativa (opcional)

O sistema funciona de dois modos:

- **Autônomo** — só o `index.html`, sem servidor: tudo roda local, sem login (os endpoints `api/` simplesmente não existem).
- **Com servidor (PHP 8 + MySQL)** — adiciona tela de login, salvamento das capturas por evento e página administrativa com tabela dinâmica.

### Instalação do servidor (hospedagem comum, ex.: cPanel)

1. Envie todos os arquivos para a pasta do site (ex.: `public_html/mocapweb/`).
2. Crie um banco MySQL no painel da hospedagem e importe **`db/schema.sql`** (phpMyAdmin → Importar).
3. Copie `api/config.example.php` para **`api/config.php`** e preencha host, banco, usuário e senha.
4. Abra **`api/criar_admin.php`** no navegador e crie o primeiro administrador (só funciona com a tabela de usuários vazia). **Depois apague esse arquivo do servidor.**
5. Acesse `login.php`, entre, e pronto: o `index.html` passa a exigir login e mostra o botão **☁ Salvar no servidor** após cada gravação.
6. Na página **`admin.php`**: crie eventos (dia e horário), filtre as capturas por evento/data/busca, ordene qualquer coluna clicando no cabeçalho, veja o resumo (nº de capturas, participantes, tempo total, semelhança média), baixe o JSON de cada captura ou exporte a tabela em CSV.

### Primeiro acesso e troca de senha

- `db/migracao-primeiro-acesso.sql` adiciona a `usuarios` as colunas `precisa_trocar_senha` (1 = obrigado a definir senha ao entrar), `primeiro_login_em` (NULL = nunca acessou) e `senha_alterada_em`.
- No login, quem tem `precisa_trocar_senha = 1` é levado a **`alterar-senha.php`** (o `index.html` e o `admin.php` também verificam e redirecionam); ao salvar a nova senha (mín. 8 caracteres, letras e números, diferente da inicial) o usuário volta ao sistema. Link **Alterar senha** no cabeçalho para trocas voluntárias.
- **Controle de acesso** (painel no `admin.php`, só para administradores; API `api/usuarios.php`): tabela com todos os usuários e a situação **Nunca acessou / Pendente (trocar senha) / Acessou · senha própria**, datas do primeiro e do último acesso e da troca de senha, e ações **Exigir troca**, **Redefinir** (volta para a senha inicial e obriga a troca — serve para "esqueci minha senha") e **Desativar/Ativar**.
- Pelo SQL, para obrigar alguém a trocar de novo: `UPDATE usuarios SET precisa_trocar_senha = 1 WHERE email = '...'`.

### Segurança implementada

- Senhas com **bcrypt** (`password_hash`), nunca em texto puro
- **Consultas preparadas (PDO)** em todas as queries — proteção contra SQL injection
- Sessão com cookie **HttpOnly + SameSite=Lax** (+ Secure sob HTTPS) e `session_regenerate_id` no login
- **Proteção CSRF** (token por sessão exigido em todas as escritas)
- **Bloqueio de conta por 15 min após 5 tentativas** de login erradas + registro de acessos (`logs_acesso`)
- Escapamento de HTML nas páginas e cabeçalhos `X-Frame-Options`/`nosniff`

### LGPD (dados de crianças)

- Grave o participante apenas com **nome de exibição ou código** (ex.: "Aluno 07") — o campo orienta isso.
- **Nenhum vídeo é enviado ao servidor** — somente os pontos numéricos do esqueleto (33 keypoints), que não identificam a criança.
- Colete o consentimento dos responsáveis pela escola antes dos eventos e use **HTTPS** na hospedagem.

## Como usar

1. Abra o `index.html` em um servidor HTTPS (ou `localhost`) — a câmera exige contexto seguro.
2. Aguarde o carregamento dos modelos (primeira vez pode demorar).
3. Clique em **Iniciar câmera**, ajuste o **máx. de pessoas** e clique em **Iniciar gravação**.
4. Troque de aba à vontade — o botão flutuante de parar continua visível.

## Requisitos

- Navegador recomendado: **Google Chrome ou Microsoft Edge 116+** (janela flutuante Document PiP).
- Funciona também em Firefox/Safari recentes (o botão flutuante vira um popup comum).
- Conexão com a internet no primeiro carregamento (modelos MediaPipe via CDN).
