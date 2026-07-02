# MoCapWeb — CECAPE

Sistema de captura de movimentos corporais e faciais 100% no navegador (webcam única), desenvolvido para o CECAPE Dra. Zilda Arns — São Caetano do Sul.

## Funcionalidades

- **Captura multi-pessoa** — detecta até **6 pessoas simultaneamente** (MediaPipe Tasks Vision · Pose Landmarker), cada uma com esqueleto em cor própria e etiqueta P1, P2…
- **Malha facial** (boca, olhos, sobrancelhas, expressões) para vários rostos (Face Landmarker)
- **Tela 100% sem rolagem** — o vídeo usa `object-fit: contain`, mostrando o **corpo inteiro** sem cortes; botão **⛶ Tela cheia** (tecla `F` ou duplo clique) maximiza o vídeo no monitor sem bordas
- **Gravação contínua em segundo plano** — a gravação **não para ao trocar de aba** (ex.: abrir o YouTube para imitar movimentos de um vídeo):
  - o loop de detecção é comandado por um **Web Worker**, imune ao throttling que o navegador aplica a abas ocultas;
  - o vídeo é capturado com `captureStream(0)` + `requestFrame()` explícito a cada quadro;
  - uma **janela flutuante sempre visível** (Document Picture-in-Picture, Chrome/Edge 116+) mostra a prévia da câmera, o cronômetro e o botão **⏹ Parar gravação** por cima de qualquer aba (fallback: popup em navegadores sem a API)
- **Ângulos articulares** em tempo real (joelhos e cotovelos) com gráfico histórico, por pessoa selecionável
- **Exportação**: vídeo MP4/WebM (câmera + esqueletos), CSV e JSON com os 33 keypoints por pessoa e por frame

## Como usar

1. Abra o `index.html` em um servidor HTTPS (ou `localhost`) — a câmera exige contexto seguro.
2. Aguarde o carregamento dos modelos (primeira vez pode demorar).
3. Clique em **Iniciar câmera**, ajuste o **máx. de pessoas** e clique em **Iniciar gravação**.
4. Troque de aba à vontade — o botão flutuante de parar continua visível.

## Requisitos

- Navegador recomendado: **Google Chrome ou Microsoft Edge 116+** (janela flutuante Document PiP).
- Funciona também em Firefox/Safari recentes (o botão flutuante vira um popup comum).
- Conexão com a internet no primeiro carregamento (modelos MediaPipe via CDN).
