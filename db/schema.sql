-- ============================================================
-- MoCapWeb CECAPE — Esquema do banco de dados (MySQL 5.7+/8.0)
-- Importe este arquivo no phpMyAdmin (aba "Importar") ou:
--   mysql -u USUARIO -p NOME_DO_BANCO < db/schema.sql
--
-- LGPD: o esquema guarda o MÍNIMO de dados dos participantes
-- (apenas um nome de exibição ou código, ex.: "Aluno 07").
-- Nenhum vídeo é armazenado — apenas os pontos do esqueleto.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ------------------------------------------------------------
-- Usuários do sistema (professores e administradores)
-- Senhas SEMPRE com hash bcrypt (password_hash do PHP).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome           VARCHAR(120)  NOT NULL,
  instituicao    VARCHAR(160)  NULL,          -- escola/unidade do professor
  email          VARCHAR(190)  NOT NULL,
  senha_hash     VARCHAR(255)  NOT NULL,
  papel          ENUM('admin','professor') NOT NULL DEFAULT 'professor',
  ativo          TINYINT(1)    NOT NULL DEFAULT 1,
  tentativas_login TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate  DATETIME      NULL,
  ultimo_login   DATETIME      NULL,
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Eventos (aula, apresentação, oficina...) com dia e horário.
-- O professor seleciona o evento na página administrativa.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo       VARCHAR(160) NOT NULL,
  data_evento  DATE         NOT NULL,
  hora_inicio  TIME         NOT NULL,
  hora_fim     TIME         NULL,
  local        VARCHAR(160) NULL,
  descricao    TEXT         NULL,
  criado_por   INT UNSIGNED NOT NULL,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_eventos_data (data_evento, hora_inicio),
  CONSTRAINT fk_eventos_usuario FOREIGN KEY (criado_por)
    REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Capturas de movimento.
-- `participante`: apenas nome de exibição ou código (LGPD).
-- `dados`: JSON completo dos quadros (33 pontos por pessoa),
--          o mesmo formato do botão "Exportar JSON".
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS capturas (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento_id          INT UNSIGNED NOT NULL,
  usuario_id         INT UNSIGNED NOT NULL,     -- professor que gravou
  participante       VARCHAR(120) NOT NULL,     -- ex.: "Aluno 07" / "Turma A"
  iniciada_em        DATETIME     NOT NULL,
  duracao_ms         INT UNSIGNED NOT NULL DEFAULT 0,
  quadros            INT UNSIGNED NOT NULL DEFAULT 0,
  num_pessoas        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  modelo             VARCHAR(20)  NOT NULL DEFAULT 'lite',
  similaridade_media TINYINT UNSIGNED NULL,     -- 0-100 (modo espelho), NULL se não usado
  exercicio          VARCHAR(30)  NULL,         -- squat/jack/curl/raise, NULL se não usado
  repeticoes         SMALLINT UNSIGNED NULL,
  dados              LONGTEXT     NOT NULL,     -- JSON dos quadros
  criado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_capturas_evento (evento_id),
  KEY idx_capturas_data (iniciada_em),
  CONSTRAINT fk_capturas_evento FOREIGN KEY (evento_id)
    REFERENCES eventos(id) ON DELETE CASCADE,
  CONSTRAINT fk_capturas_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Registro de acessos (auditoria de segurança)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs_acesso (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NULL,
  acao        VARCHAR(60)  NOT NULL,   -- login_ok, login_falha, logout, captura_salva...
  detalhe     VARCHAR(190) NULL,
  ip          VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PRIMEIRO ACESSO:
-- Não insira senhas em texto puro aqui. Após importar o esquema,
-- abra no navegador:  api/criar_admin.php
-- Ele cria o primeiro administrador com senha segura (bcrypt) e
-- só funciona enquanto a tabela `usuarios` estiver vazia.
-- Depois de criar o admin, APAGUE o arquivo criar_admin.php.
-- ------------------------------------------------------------
