-- =====================================================
-- SCRIPT DE CORREÇÃO - Problema de Login
-- Corrige: "Usuário dono não encontrado"
-- Data: 06/02/2026
-- =====================================================

USE modernpos;

-- 1. Definir usuário 296 (KLARKY) como owner
UPDATE users 
SET is_owner = 1, 
    tenant_id = 1,
    status = 1
WHERE id = 296;

-- 2. Atualizar tenant_id de todos os usuários
UPDATE users 
SET tenant_id = 1 
WHERE tenant_id IS NULL;

-- 3. Atualizar tenant_id das lojas principais
UPDATE stores 
SET tenant_id = 1 
WHERE store_id IN (1, 4, 308, 311) AND tenant_id IS NULL;

-- 4. Garantir acesso do owner às lojas principais
INSERT IGNORE INTO user_to_store (user_id, store_id, status, sort_order) 
VALUES 
    (296, 1, 1, 0),   -- Loja Gloria
    (296, 4, 1, 0),   -- Loja Campo Grande
    (296, 335, 1, 0); -- Loja Leone Moda

-- 5. Verificar configurações
SELECT 
    'Usuário Owner' as verificacao,
    id, username, email, is_owner, tenant_id
FROM users 
WHERE is_owner = 1;

SELECT 
    'Lojas Disponíveis' as verificacao,
    store_id, name, tenant_id
FROM stores 
WHERE tenant_id = 1;

SELECT 
    'Acesso às Lojas' as verificacao,
    u.username, s.name as loja
FROM user_to_store uts
JOIN users u ON u.id = uts.user_id
JOIN stores s ON s.store_id = uts.store_id
WHERE uts.user_id = 296 AND uts.status = 1;

-- =====================================================
-- CORREÇÃO CONCLUÍDA!
-- =====================================================
-- Agora você pode fazer login com:
-- Usuário: klarkykutz@hotmail.com (ou KLARKY STREY SILVA KUTZ)
-- Senha: (sua senha atual)
-- =====================================================
