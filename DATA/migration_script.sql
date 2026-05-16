-- =====================================================
-- SCRIPT DE MIGRAÇÃO SEGURA - ModernPOS
-- Executa APÓS fazer backup do banco de dados!
-- =====================================================

-- Desabilitar verificações de chave estrangeira temporariamente
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- PARTE 1: ADICIONAR NOVAS COLUNAS NAS TABELAS EXISTENTES
-- =====================================================

-- Adicionar tenant_id na tabela bank_accounts (se não existir)
ALTER TABLE `bank_accounts` 
ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL AFTER `id`;

-- Adicionar tenant_id na tabela bank_transaction_info (se não existir)
ALTER TABLE `bank_transaction_info` 
ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL AFTER `info_id`;

-- =====================================================
-- PARTE 2: CRIAR NOVAS TABELAS (SE NÃO EXISTIREM)
-- =====================================================

-- Tabela acessos
CREATE TABLE IF NOT EXISTS `acessos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `chave` varchar(50) NOT NULL,
  `grupo` int(11) NOT NULL,
  `pagina` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Inserir dados padrão de acessos (apenas se a tabela estiver vazia)
INSERT IGNORE INTO `acessos` (`id`, `nome`, `chave`, `grupo`, `pagina`) VALUES
(1, 'Home', 'home', 0, 'Sim'),
(2, 'Configurações', 'configuracoes', 0, 'Não'),
(3, 'Usuários', 'usuarios', 1, 'Sim'),
(4, 'Acessos', 'acessos', 2, 'Sim'),
(5, 'Grupos Acesso', 'grupo_acessos', 2, 'Sim'),
(8, 'Funcionários', 'funcionarios', 1, 'Sim'),
(9, 'Fornecedores', 'fornecedores', 1, 'Sim'),
(10, 'Formas de Pagamento', 'formas_pgto', 2, 'Sim'),
(11, 'Cargos', 'cargos', 2, 'Sim'),
(12, 'Frequências', 'frequencias', 2, 'Sim'),
(13, 'Contas à Receber', 'receber', 4, 'Sim'),
(14, 'Contas à Pagar', 'pagar', 4, 'Sim'),
(15, 'Clientes', 'clientes', 1, 'Sim'),
(16, 'Relatório Financeiro', 'rel_financeiro', 4, 'Não'),
(17, 'Relatório Sintético Despesas', 'rel_sintetico_despesas', 4, 'Não'),
(18, 'Relatório Sintético Receber', 'rel_sintetico_receber', 4, 'Não'),
(19, 'Relatório Balanço Anual', 'rel_balanco', 4, 'Não'),
(21, 'Grupos RBAC', 'grupos_rbac', 2, 'Sim'),
(22, 'Migração RBAC', 'migracao_rbac', 2, 'Sim');

-- Tabela app_cache
CREATE TABLE IF NOT EXISTS `app_cache` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(190) NOT NULL,
  `value` longtext NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `hits` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela arquivos
CREATE TABLE IF NOT EXISTS `arquivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) DEFAULT NULL,
  `descricao` varchar(100) DEFAULT NULL,
  `arquivo` varchar(100) DEFAULT NULL,
  `data_cad` date NOT NULL,
  `registro` varchar(50) DEFAULT NULL,
  `id_reg` int(11) NOT NULL,
  `usuario` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- =====================================================
-- PARTE 3: ATUALIZAR tenant_id NOS DADOS EXISTENTES
-- (Define tenant_id = 1 para todos os registros antigos)
-- =====================================================

UPDATE `bank_accounts` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;
UPDATE `bank_transaction_info` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL;

-- Reabilitar verificações de chave estrangeira
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRAÇÃO CONCLUÍDA!
-- Seus dados de vendas foram preservados.
-- =====================================================
