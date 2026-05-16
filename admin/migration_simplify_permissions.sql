-- =====================================================
-- MIGRATION: Simplificação da Arquitetura de Permissões
-- Data: 2026-01-29
-- Objetivo: Remover grupos automáticos e implementar modelo Capabilities ∩ Roles
-- =====================================================

-- BACKUP FEITO: backup_modernpos_20260129_161444.sql (2.09 MB)

-- =====================================================
-- PASSO 1: Adicionar coluna is_owner na tabela users
-- =====================================================
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_owner TINYINT(1) DEFAULT 0 COMMENT 'Indica se o usuário é owner do tenant' 
AFTER group_id;

-- =====================================================
-- PASSO 2: Marcar owners existentes (user_id = tenant_id)
-- =====================================================
UPDATE users 
SET is_owner = 1 
WHERE id = tenant_id AND tenant_id IS NOT NULL;

-- =====================================================
-- PASSO 3: Backup dos grupos que serão removidos
-- =====================================================
CREATE TABLE IF NOT EXISTS user_group_backup_20260129 AS
SELECT * FROM user_group
WHERE slug LIKE 'plan_%'
   OR (tenant_id IS NOT NULL AND group_id NOT IN (SELECT group_id FROM users));

-- =====================================================
-- PASSO 4: Remover grupos automáticos antigos
-- =====================================================
-- Remover grupos que seguem o padrão plan_X_*
DELETE FROM user_group 
WHERE slug LIKE 'plan_1_%' 
   OR slug LIKE 'plan_2_%'
   OR slug LIKE 'plan_3_%'
   OR slug LIKE 'plan_4_%'
   OR slug LIKE 'plan_5_%';

-- =====================================================
-- PASSO 5: Criar índice para performance
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_users_is_owner ON users(is_owner);
CREATE INDEX IF NOT EXISTS idx_user_group_tenant ON user_group(tenant_id);

-- =====================================================
-- PASSO 6: Validação (mostrar quantos registros foram afetados)
-- =====================================================
SELECT 
    'Owners marcados' AS operacao,
    COUNT(*) AS total
FROM users 
WHERE is_owner = 1

UNION ALL

SELECT 
    'Grupos removidos (backup)' AS operacao,
    COUNT(*) AS total
FROM user_group_backup_20260129

UNION ALL

SELECT 
    'Grupos ativos restantes' AS operacao,
    COUNT(*) AS total
FROM user_group;

-- =====================================================
-- ROLLBACK (caso necessário)
-- =====================================================
-- Para reverter esta migração:
-- 1. ALTER TABLE users DROP COLUMN is_owner;
-- 2. INSERT INTO user_group SELECT * FROM user_group_backup_20260129;
-- 3. DROP TABLE user_group_backup_20260129;
-- =====================================================
