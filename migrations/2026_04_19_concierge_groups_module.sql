-- =====================================================================
-- MIGRATION: Concierge Grupos (Moda IA)
-- Data: 2026-04-19
-- Descrição:
--   1) Cria tabelas de grupos, campanhas e disparos por grupo
--   2) Garante colunas de plano para limites de grupos
--   3) Define índices e constraints para operação em produção
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) Grupos sincronizados da Evolution
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `concierge_groups` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`        INT NOT NULL,
    `store_id`         INT NOT NULL,
    `remote_jid`       VARCHAR(100) NOT NULL COMMENT 'ID do grupo na Evolution/WhatsApp',
    `name`             VARCHAR(255) NOT NULL,
    `member_count`     INT NOT NULL DEFAULT 0,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `category`         VARCHAR(60) DEFAULT 'Geral',
    `daily_limit`      INT NOT NULL DEFAULT 3 COMMENT 'Limite de disparos por dia para o grupo',
    `settings_json`    LONGTEXT DEFAULT NULL COMMENT 'Configuração avançada por grupo',
    `last_synced_at`   DATETIME DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenant_remote_jid` (`tenant_id`, `remote_jid`),
    KEY `idx_tenant_active` (`tenant_id`, `is_active`),
    KEY `idx_tenant_category` (`tenant_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) Campanhas do módulo de grupos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `concierge_campaigns` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`            INT NOT NULL,
    `product_id`           INT UNSIGNED DEFAULT NULL,
    `title`                VARCHAR(255) NOT NULL,
    `content`              LONGTEXT NOT NULL,
    `media_url`            VARCHAR(500) DEFAULT NULL,
    `status`               ENUM('draft','needs_approval','scheduled','sending','sent','failed','canceled') NOT NULL DEFAULT 'draft',
    `scheduled_at`         DATETIME DEFAULT NULL,
    `sent_at`              DATETIME DEFAULT NULL,
    `last_error`           VARCHAR(255) DEFAULT NULL,
    `payload_json`         LONGTEXT DEFAULT NULL COMMENT 'Payload de referência enviado ao n8n',
    `n8n_execution_id`     VARCHAR(120) DEFAULT NULL,
    `webhook_requested_at` DATETIME DEFAULT NULL,
    `created_by`           INT NOT NULL DEFAULT 0,
    `updated_by`           INT NOT NULL DEFAULT 0,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_status_schedule_created` (`tenant_id`, `status`, `scheduled_at`, `created_at`),
    KEY `idx_tenant_created` (`tenant_id`, `created_at`),
    KEY `idx_tenant_product` (`tenant_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) Disparos por grupo (Campanha x Grupo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `concierge_broadcasts` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`            INT NOT NULL,
    `campaign_id`          INT UNSIGNED NOT NULL,
    `group_id`             INT UNSIGNED NOT NULL,
    `status`               ENUM('pending','queued','sending','sent','error','skipped') NOT NULL DEFAULT 'pending',
    `external_message_id`  VARCHAR(190) DEFAULT NULL COMMENT 'ID da mensagem na Evolution/n8n',
    `error_message`        VARCHAR(255) DEFAULT NULL,
    `queued_at`            DATETIME DEFAULT NULL,
    `sent_at`              DATETIME DEFAULT NULL,
    `delivered_at`         DATETIME DEFAULT NULL,
    `read_at`              DATETIME DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_campaign_group` (`campaign_id`, `group_id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_campaign_status_sent_at` (`campaign_id`, `status`, `sent_at`),
    KEY `idx_external_message` (`external_message_id`),
    CONSTRAINT `fk_concierge_broadcast_campaign`
        FOREIGN KEY (`campaign_id`) REFERENCES `concierge_campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_concierge_broadcast_group`
        FOREIGN KEY (`group_id`) REFERENCES `concierge_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) Campos de plano para habilitação/limites do módulo de grupos
-- ---------------------------------------------------------------------
ALTER TABLE `plans`
    ADD COLUMN IF NOT EXISTS `ai_groups_enabled` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Habilita módulo de automação de grupos' AFTER `ai_catalog_limit`,
    ADD COLUMN IF NOT EXISTS `ai_groups_limit` INT NOT NULL DEFAULT 0
        COMMENT 'Quantidade máxima de grupos ativos permitidos (0 = ilimitado)' AFTER `ai_groups_enabled`,
    ADD COLUMN IF NOT EXISTS `ai_broadcast_monthly_limit` INT NOT NULL DEFAULT 0
        COMMENT 'Quantidade máxima de disparos mensais em grupos (0 = ilimitado)' AFTER `ai_groups_limit`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 5) Backfill opcional (primeiro deploy)
-- ---------------------------------------------------------------------
-- Exemplo de uso:
-- 1) Execute o sync via endpoint api/concierge/groups.php?action=sync para cada tenant;
-- 2) Ou rode um script operacional que chame ai_evolution_fetch_groups + ai_sync_evolution_group.
-- Este passo é opcional e seguro para ambientes sem Evolution ativa.

-- ---------------------------------------------------------------------
-- 6) Rollback seguro (executar manualmente se necessário)
-- ---------------------------------------------------------------------
-- DROP TABLE IF EXISTS `concierge_broadcasts`;
-- DROP TABLE IF EXISTS `concierge_campaigns`;
-- DROP TABLE IF EXISTS `concierge_groups`;
-- ALTER TABLE `plans`
--   DROP COLUMN `ai_broadcast_monthly_limit`,
--   DROP COLUMN `ai_groups_limit`,
--   DROP COLUMN `ai_groups_enabled`;

