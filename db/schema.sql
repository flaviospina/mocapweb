-- db/schema.sql — MocapWeb (CECAPE Dra. Zilda Arns)
-- Importar no phpMyAdmin (aba Importar). Idempotente: pode ser executado mais de uma vez.
-- Banco: utf8mb4. Tabelas: usuarios, logs_acesso, eventos, capturas.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome             VARCHAR(120)  NOT NULL,
  email            VARCHAR(254)  NOT NULL,
  senha_hash       VARCHAR(255)  NOT NULL,
  papel            VARCHAR(20)   NOT NULL DEFAULT 'professor',   -- 'admin' | 'professor'
  ativo            TINYINT(1)    NOT NULL DEFAULT 1,
  tentativas_login TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate    DATETIME      NULL DEFAULT NULL,
  ultimo_login     DATETIME      NULL DEFAULT NULL,
  criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_email (email),
  KEY ix_usuarios_papel (papel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_acesso (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED    NULL DEFAULT NULL,
  acao       VARCHAR(40)     NOT NULL,
  detalhe    VARCHAR(255)    NULL DEFAULT NULL,
  ip         VARCHAR(45)     NULL DEFAULT NULL,
  user_agent VARCHAR(255)    NULL DEFAULT NULL,
  criado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_logs_usuario (usuario_id),
  KEY ix_logs_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo      VARCHAR(150) NOT NULL,
  data_evento DATE         NOT NULL,
  hora_inicio TIME         NOT NULL,
  hora_fim    TIME         NULL DEFAULT NULL,
  local       VARCHAR(150) NULL DEFAULT NULL,
  criado_por  INT UNSIGNED NULL DEFAULT NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_eventos_data (data_evento),
  CONSTRAINT fk_eventos_usuario FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capturas (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  evento_id          INT UNSIGNED  NOT NULL,
  usuario_id         INT UNSIGNED  NULL DEFAULT NULL,
  participante       VARCHAR(120)  NOT NULL,      -- código/apelido, nunca nome completo (LGPD)
  duracao_ms         INT UNSIGNED  NOT NULL DEFAULT 0,
  quadros            INT UNSIGNED  NOT NULL DEFAULT 0,
  num_pessoas        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  modelo             VARCHAR(40)   NULL DEFAULT NULL,
  similaridade_media TINYINT UNSIGNED NULL DEFAULT NULL,   -- 0..100
  exercicio          VARCHAR(40)   NULL DEFAULT NULL,
  repeticoes         INT UNSIGNED  NULL DEFAULT NULL,
  dados              LONGTEXT      NOT NULL,      -- JSON com metadata + frames (33 keypoints por pessoa)
  criado_em          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_capturas_evento (evento_id),
  KEY ix_capturas_usuario (usuario_id),
  KEY ix_capturas_criado (criado_em),
  CONSTRAINT fk_capturas_evento  FOREIGN KEY (evento_id)  REFERENCES eventos  (id) ON DELETE CASCADE,
  CONSTRAINT fk_capturas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
