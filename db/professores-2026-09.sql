-- ============================================================
-- MoCapWeb CECAPE — cadastro dos professores (setembro/2026)
-- Como usar: cPanel → phpMyAdmin → selecione o banco
-- itthri79_cecape_mocapweb → aba "SQL" → cole tudo → Executar.
-- Pode ser executado mais de uma vez: quem já existe é atualizado.
--
-- Senha inicial de todos: scseduca   (hash bcrypt abaixo — a senha
-- nunca é gravada em texto puro). Recomende a troca no 1º acesso.
-- ============================================================

SET NAMES utf8mb4;

-- Campo "instituicao" (escola/unidade) — só cria se ainda não existir
SET @tem_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'instituicao');
SET @sql := IF(@tem_col = 0,
  'ALTER TABLE usuarios ADD COLUMN instituicao VARCHAR(160) NULL AFTER nome',
  'SELECT ''coluna instituicao já existe''');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

INSERT INTO usuarios (nome, instituicao, email, senha_hash, papel, ativo) VALUES
  ('Adriana Barbosa Ferreira',        'EMEI Telma Silvia de Aguiar Brito',   'adriana.ferreira@scseduca.com.br',        '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Adriana Gomes da Fonseca',        'NAEI',                                'adrianagomes@scseduca.com.br',            '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Barbara da Silva Borges',         'EMEF Laura Lopes',                    'barbaraborges@scseduca.com.br',           '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Daniela Mattos Tarrataca',        'EMI Maria Simonetti Thomé',           'danielatarrataca@scseduca.com.br',        '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Daniele Fernando da Silva',       'EMEF Santina Lorenzini Auricchio',    'danielesilva@scseduca.com.br',            '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Diyony Uiara Sampaio Marsilli',   'EMI Gastão Vidigal Neto',             'diyonymarsilli@scseduca.com.br',          '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Elio Rodrigues Junior',           'EMEF Sylvio Romero',                  'eliojunior@scseduca.com.br',              '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Rosileide Queiroz de Oliveira',   'EMEF Bartolomeu Bueno da Silva',      'rosileideoliveira@scseduca.com.br',       '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Shirley Monteiro Maciel',         'EME Profª. Alcina Dantas Feijão',     'educacaoespecialalcina@scseduca.com.br',  '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1),
  ('Simone Mendes Maldegan',          'EMEI José Auricchio',                 'simonemaldegan@scseduca.com.br',          '$2y$10$we6r6tp6duRHXwdpu3zhzuRYzjQ7Nx6PMMRWSHrxG3SqA0aIokWy6', 'professor', 1)
ON DUPLICATE KEY UPDATE
  nome        = VALUES(nome),
  instituicao = VALUES(instituicao),
  senha_hash  = VALUES(senha_hash),
  papel       = 'professor',
  ativo       = 1,
  tentativas_login = 0,
  bloqueado_ate    = NULL;

-- Conferência: deve listar os 10 professores
SELECT id, nome, instituicao, email, papel, ativo FROM usuarios WHERE papel = 'professor' ORDER BY nome;
