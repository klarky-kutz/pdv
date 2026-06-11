-- =====================================================================
-- MIGRATION: Evolution WhatsApp Bridge (ModernPOS + n8n)
-- Data: 2026-03-29
-- Descrição:
--   1) Tabela de logs de webhook Evolution
--   2) Tabela de status de atendimento (Ativo/Manual)
--   3) Views de compatibilidade para nomenclatura ia_*
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `ai_evolution_webhook_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`       INT NOT NULL,
    `instance_name`   VARCHAR(120) DEFAULT NULL,
    `event_name`      VARCHAR(80) DEFAULT NULL,
    `remote_jid`      VARCHAR(80) DEFAULT NULL,
    `push_name`       VARCHAR(180) DEFAULT NULL,
    `message_type`    VARCHAR(40) DEFAULT NULL,
    `status`          ENUM('Sucesso','Erro','Ignorado') NOT NULL DEFAULT 'Sucesso',
    `error_message`   VARCHAR(255) DEFAULT NULL,
    `payload_json`    LONGTEXT DEFAULT NULL,
    `response_json`   LONGTEXT DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tenant_created` (`tenant_id`, `created_at` DESC),
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_remote_jid` (`tenant_id`, `remote_jid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_status_atendimento` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`           INT NOT NULL,
    `remote_jid`          VARCHAR(80) NOT NULL,
    `status`              ENUM('Ativo','Manual') NOT NULL DEFAULT 'Ativo',
    `takeover_by_user_id` INT DEFAULT NULL,
    `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenant_remote_jid` (`tenant_id`, `remote_jid`),
    INDEX `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Views de compatibilidade para nomenclatura solicitada no n8n:
--   ia_customer_memory
--   ia_catalog_variants
--   ia_status_atendimento
-- ---------------------------------------------------------------------

CREATE OR REPLACE VIEW `ia_customer_memory` AS
SELECT
    p.id,
    p.tenant_id AS loja_id,
    p.whatsapp_phone AS remoteJid,
    p.name AS pushName,
    p.usual_size,
    p.preferences_json,
    p.total_interactions,
    p.last_interaction,
    p.updated_at
FROM ai_chat_profiles p;

CREATE OR REPLACE VIEW `ia_catalog_variants` AS
SELECT
    v.id,
    v.tenant_id AS loja_id,
    v.model_id,
    m.name AS product_name,
    v.color,
    v.size,
    v.price,
    v.stock_qty,
    v.is_active,
    v.photo_webp
FROM ai_catalogo_variants v
INNER JOIN ai_catalogo_models m ON m.id = v.model_id;

CREATE OR REPLACE VIEW `ia_status_atendimento` AS
SELECT
    s.id,
    s.tenant_id AS loja_id,
    s.remote_jid,
    s.status,
    s.takeover_by_user_id,
    s.updated_at
FROM ai_status_atendimento s;

SET FOREIGN_KEY_CHECKS = 1;
