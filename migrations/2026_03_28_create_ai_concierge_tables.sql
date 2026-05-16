-- =====================================================================
-- MIGRATION: Módulo Moda IA (Concierge IA)
-- Data: 2026-03-28
-- Versão: 1.0
-- Descrição: Cria todas as tabelas do módulo Moda IA e altera tabelas
--            existentes para suporte ao Concierge IA
-- =====================================================================
-- EXECUTE NO BANCO: modernpos
-- FAÇA BACKUP ANTES DE EXECUTAR!
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- =====================================================================
-- TABELA 1: ai_catalogo_models
-- O Modelo (produto-raiz do catálogo IA, separado do products)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ai_catalogo_models` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT NOT NULL COMMENT 'Isolamento multi-tenant (= store_id do ModernPOS)',
    `name`         VARCHAR(150) NOT NULL COMMENT 'Ex: Vestido Midi Floral',
    `description`  TEXT,
    `tags`         VARCHAR(500) DEFAULT NULL COMMENT 'Ex: midi,floral,feminino,casual',
    `cover_webp`   VARCHAR(300) DEFAULT NULL COMMENT 'Caminho da foto principal (WebP)',
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `demand_count` INT NOT NULL DEFAULT 0 COMMENT 'Métrica de desejo — incrementa a cada consulta IA',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tenant`        (`tenant_id`),
    INDEX `idx_active`        (`tenant_id`, `is_active`),
    INDEX `idx_demand`        (`tenant_id`, `demand_count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABELA 2: ai_catalogo_variants
-- A Variante (cor + tamanho + estoque + preço)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ai_catalogo_variants` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `model_id`    INT UNSIGNED NOT NULL COMMENT 'FK -> ai_catalogo_models.id',
    `tenant_id`   INT NOT NULL,
    `color`       VARCHAR(80) DEFAULT NULL COMMENT 'Ex: Rosa, Preto, Off-White',
    `color_hex`   VARCHAR(7) DEFAULT NULL COMMENT 'Ex: #f9a8d4',
    `size`        VARCHAR(20) DEFAULT NULL COMMENT 'Ex: P, M, G, GG, 34, 38',
    `price`       DECIMAL(10,2) NOT NULL DEFAULT 0,
    `stock_qty`   INT NOT NULL DEFAULT 0,
    `photo_webp`  VARCHAR(300) DEFAULT NULL COMMENT 'Foto específica da variante (WebP)',
    `sku`         VARCHAR(80) DEFAULT NULL COMMENT 'Código interno opcional',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_model`   (`model_id`),
    INDEX `idx_tenant`  (`tenant_id`),
    INDEX `idx_stock`   (`tenant_id`, `stock_qty`),
    INDEX `idx_active`  (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABELA 3: ai_chat_profiles
-- Memória de perfil do cliente no WhatsApp (long-term memory)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ai_chat_profiles` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`           INT NOT NULL,
    `whatsapp_phone`      VARCHAR(30) NOT NULL COMMENT 'Formato: +5511999999999',
    `name`                VARCHAR(150) DEFAULT NULL,
    `usual_size`          VARCHAR(20) DEFAULT NULL COMMENT 'Tamanho habitual (P/M/G)',
    `preferences_json`    JSON DEFAULT NULL COMMENT '{"cores":["rose","pastel"],"estilos":["midi"]}',
    `last_interaction`    DATETIME DEFAULT NULL,
    `total_interactions`  INT NOT NULL DEFAULT 0,
    `is_blocked`          TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bloquear cliente do atendimento IA',
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_phone_tenant` (`tenant_id`, `whatsapp_phone`),
    INDEX `idx_tenant`          (`tenant_id`),
    INDEX `idx_last_interaction` (`tenant_id`, `last_interaction` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABELA 4: ai_orders
-- Pedidos originados via WhatsApp
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ai_orders` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`        INT NOT NULL,
    `profile_id`       INT UNSIGNED DEFAULT NULL COMMENT 'FK -> ai_chat_profiles.id',
    `whatsapp_phone`   VARCHAR(30) NOT NULL,
    `customer_name`    VARCHAR(150) DEFAULT NULL,
    `status`           ENUM('pendente','pago','separando','rota','entregue','cancelado') NOT NULL DEFAULT 'pendente',
    `total_amount`     DECIMAL(10,2) NOT NULL DEFAULT 0,
    `payment_method`   VARCHAR(50) DEFAULT NULL COMMENT 'pix, stripe, mercadopago',
    `payment_link`     VARCHAR(500) DEFAULT NULL COMMENT 'Link enviado ao cliente',
    `payment_ref`      VARCHAR(200) DEFAULT NULL COMMENT 'ID de referência do gateway',
    `uber_proof_path`  VARCHAR(300) DEFAULT NULL COMMENT 'Foto comprovante Uber Flash',
    `notes`            TEXT DEFAULT NULL,
    `moved_by_ia`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = último movimento feito pela IA',
    `paid_at`          DATETIME DEFAULT NULL,
    `delivered_at`     DATETIME DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_tenant_date`   (`tenant_id`, `created_at` DESC),
    INDEX `idx_phone`         (`tenant_id`, `whatsapp_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABELA 5: ai_order_items
-- Itens de cada pedido (snapshot de preço no momento da venda)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ai_order_items` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`     INT UNSIGNED NOT NULL COMMENT 'FK -> ai_orders.id',
    `variant_id`   INT UNSIGNED NOT NULL COMMENT 'FK -> ai_catalogo_variants.id',
    `model_name`   VARCHAR(150) DEFAULT NULL COMMENT 'Snapshot do nome no momento da venda',
    `color`        VARCHAR(80) DEFAULT NULL,
    `size`         VARCHAR(20) DEFAULT NULL,
    `qty`          INT NOT NULL DEFAULT 1,
    `unit_price`   DECIMAL(10,2) NOT NULL COMMENT 'Preço no momento da venda',
    `subtotal`     DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_order`   (`order_id`),
    INDEX `idx_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- TABELA 6: ai_usage_log
-- Contador de uso por loja/mês (controle de limites do plano SaaS)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ai_usage_log` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`        INT NOT NULL,
    `year_month`       CHAR(7) NOT NULL COMMENT 'Formato: 2026-03',
    `webhook_calls`    INT NOT NULL DEFAULT 0 COMMENT 'Chamadas ao n8n neste mês',
    `storage_mb_used`  DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'MB de fotos armazenadas',
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenant_month` (`tenant_id`, `year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ALTERAÇÃO 1: Adicionar ai_webhook_token na tabela stores
-- Token único por loja para autenticar chamadas do n8n
-- =====================================================================
ALTER TABLE `stores`
    ADD COLUMN IF NOT EXISTS `ai_webhook_token` VARCHAR(64) DEFAULT NULL
        COMMENT 'Token de autenticação para API do Moda IA (n8n)' AFTER `store_id`;

-- Gerar token automático para lojas existentes (64 chars hex)
UPDATE `stores`
SET `ai_webhook_token` = LOWER(SHA2(CONCAT(NOW(), RAND()), 256))
WHERE `ai_webhook_token` IS NULL OR `ai_webhook_token` = '';

-- =====================================================================
-- ALTERAÇÃO 2: Adicionar colunas AI na tabela plans (banco modernpos)
-- Caso o SAAS use o mesmo banco — se o plano for numa tabela local
-- =====================================================================
ALTER TABLE `plans`
    ADD COLUMN IF NOT EXISTS `ai_concierge_enabled` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Habilita módulo Moda IA para o plano' AFTER `features`,
    ADD COLUMN IF NOT EXISTS `ai_webhook_calls`     INT NOT NULL DEFAULT 500
        COMMENT 'Limite mensal de chamadas ao webhook n8n (0 = ilimitado)' AFTER `ai_concierge_enabled`,
    ADD COLUMN IF NOT EXISTS `ai_catalog_limit`     INT NOT NULL DEFAULT 200
        COMMENT 'Limite de produtos no catálogo IA (0 = ilimitado)' AFTER `ai_webhook_calls`;

-- Habilitar AI para planos Professional e Enterprise
UPDATE `plans`
SET   `ai_concierge_enabled` = 1,
      `ai_webhook_calls`     = 500,
      `ai_catalog_limit`     = 1000
WHERE LOWER(`name`) LIKE '%professional%'
   OR LOWER(`name`) LIKE '%profissional%';

UPDATE `plans`
SET   `ai_concierge_enabled` = 1,
      `ai_webhook_calls`     = 0,
      `ai_catalog_limit`     = 0
WHERE LOWER(`name`) LIKE '%enterprise%'
   OR LOWER(`name`) LIKE '%empresarial%';

-- =====================================================================
-- CRIAR diretórios de storage (referência — criar manualmente ou via PHP)
-- storage/concierge/catalogo/   → fotos dos modelos (WebP)
-- storage/concierge/uber/       → comprovantes Uber Flash
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- MIGRATION CONCLUÍDA
-- Tabelas criadas:
--   1. ai_catalogo_models   (catálogo curado para IA)
--   2. ai_catalogo_variants (variantes: cor + tamanho + preço + estoque)
--   3. ai_chat_profiles     (memória de perfil WhatsApp)
--   4. ai_orders            (pedidos via WhatsApp)
--   5. ai_order_items       (itens dos pedidos)
--   6. ai_usage_log         (contador de uso por plano)
-- Tabelas alteradas:
--   7. stores               (+ ai_webhook_token)
--   8. plans                (+ ai_concierge_enabled, ai_webhook_calls, ai_catalog_limit)
-- =====================================================================
