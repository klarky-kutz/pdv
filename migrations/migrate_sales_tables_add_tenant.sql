-- =====================================================
-- SCRIPT DE MIGRAÇÃO - Tabelas de Vendas (SaaS Multi-Tenant)
-- Data: 2026-02-06
-- =====================================================
-- IMPORTANTE: FAÇA BACKUP DO BANCO DE DADOS ANTES DE EXECUTAR!
-- =====================================================

-- Desabilitar verificações de chave estrangeira temporariamente
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- =====================================================
-- PARTE 1: ADICIONAR COLUNA tenant_id NAS TABELAS
-- =====================================================

-- Tabela: selling_info
-- Adiciona tenant_id para suportar multi-tenancy
ALTER TABLE `selling_info` 
ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL 
AFTER `info_id`,
ADD INDEX IF NOT EXISTS `idx_tenant_selling` (`tenant_id`, `store_id`, `created_at`);

-- Tabela: selling_item
-- Adiciona tenant_id para suportar multi-tenancy
ALTER TABLE `selling_item` 
ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL 
AFTER `id`,
ADD INDEX IF NOT EXISTS `idx_tenant_selling_item` (`tenant_id`, `store_id`, `invoice_id`);

-- Tabela: customers
-- Adiciona tenant_id para suportar multi-tenancy
ALTER TABLE `customers` 
ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL 
AFTER `id`;

-- Adicionar índice para customers (apenas tenant_id)
ALTER TABLE `customers`
ADD INDEX IF NOT EXISTS `idx_tenant_customer` (`tenant_id`);

-- Tabela: products
-- Adiciona tenant_id para suportar multi-tenancy
ALTER TABLE `products` 
ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL 
AFTER `id`;

-- Adicionar índice para products (apenas tenant_id)
ALTER TABLE `products`
ADD INDEX IF NOT EXISTS `idx_tenant_product` (`tenant_id`);

-- =====================================================
-- PARTE 2: POPULAR tenant_id COM DADOS EXISTENTES
-- (Atribui tenant_id = 1 para todos os registros atuais)
-- =====================================================

-- Atualizar selling_info
UPDATE `selling_info` 
SET `tenant_id` = 1 
WHERE `tenant_id` IS NULL;

-- Atualizar selling_item
UPDATE `selling_item` 
SET `tenant_id` = 1 
WHERE `tenant_id` IS NULL;

-- Atualizar customers
UPDATE `customers` 
SET `tenant_id` = 1 
WHERE `tenant_id` IS NULL;

-- Atualizar products
UPDATE `products` 
SET `tenant_id` = 1 
WHERE `tenant_id` IS NULL;

-- =====================================================
-- PARTE 3: ADICIONAR RESTRIÇÕES (OPCIONAL - DESCOMENTE SE NECESSÁRIO)
-- =====================================================

-- Adicionar chave estrangeira para tenant_id (se existir tabela tenants)
-- ALTER TABLE `selling_info` 
-- ADD CONSTRAINT `fk_selling_info_tenant` 
-- FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE;

-- ALTER TABLE `selling_item` 
-- ADD CONSTRAINT `fk_selling_item_tenant` 
-- FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE;

-- ALTER TABLE `customers` 
-- ADD CONSTRAINT `fk_customers_tenant` 
-- FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE;

-- ALTER TABLE `products` 
-- ADD CONSTRAINT `fk_products_tenant` 
-- FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE;

-- Reabilitar verificações de chave estrangeira
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRAÇÃO CONCLUÍDA COM SUCESSO!
-- =====================================================
-- Todas as tabelas de vendas agora suportam multi-tenancy
-- Dados existentes foram preservados com tenant_id = 1
-- =====================================================
