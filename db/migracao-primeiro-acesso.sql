-- ============================================================
-- MoCapWeb CECAPE — controle de primeiro acesso / troca de senha
-- cPanel → phpMyAdmin → banco itthri79_cecape_mocapweb → aba SQL → Executar.
-- Pode rodar mais de uma vez (só cria o que ainda não existe).
--
-- Colunas novas em `usuarios`:
--   precisa_trocar_senha  1 = ao entrar, é obrigado a definir nova senha
--   primeiro_login_em     data/hora do 1º login (NULL = nunca acessou)
--   senha_alterada_em     data/hora da última troca de senha
-- ============================================================
SET NAMES utf8mb4;

SET @c1 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'precisa_trocar_senha');
SET @s1 := IF(@c1 = 0, 'ALTER TABLE usuarios ADD COLUMN precisa_trocar_senha TINYINT(1) NOT NULL DEFAULT 1 AFTER ativo', 'SELECT ''precisa_trocar_senha já existe''');
PREPARE p1 FROM @s1; EXECUTE p1; DEALLOCATE PREPARE p1;

SET @c2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'primeiro_login_em');
SET @s2 := IF(@c2 = 0, 'ALTER TABLE usuarios ADD COLUMN primeiro_login_em DATETIME NULL AFTER ultimo_login', 'SELECT ''primeiro_login_em já existe''');
PREPARE p2 FROM @s2; EXECUTE p2; DEALLOCATE PREPARE p2;

SET @c3 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'senha_alterada_em');
SET @s3 := IF(@c3 = 0, 'ALTER TABLE usuarios ADD COLUMN senha_alterada_em DATETIME NULL AFTER primeiro_login_em', 'SELECT ''senha_alterada_em já existe''');
PREPARE p3 FROM @s3; EXECUTE p3; DEALLOCATE PREPARE p3;

-- Professores com a senha inicial precisam trocar; administradores não.
UPDATE usuarios SET precisa_trocar_senha = 1 WHERE papel = 'professor' AND senha_alterada_em IS NULL;
UPDATE usuarios SET precisa_trocar_senha = 0 WHERE papel = 'admin';

-- Conferência
SELECT id, nome, email, papel, precisa_trocar_senha,
       IF(primeiro_login_em IS NULL, 'nunca acessou', primeiro_login_em) AS primeiro_acesso,
       senha_alterada_em
  FROM usuarios ORDER BY papel, nome;
