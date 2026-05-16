-- Tabela de notificações do SaaS para usuários
-- Armazena notificações como cancelamento de assinatura, pendências, etc.

CREATE TABLE IF NOT EXISTS `saas_notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'Tipo: subscription_cancelled, payment_pending, etc',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_tenant_read` (`tenant_id`, `is_read`),
  KEY `idx_tenant_type` (`tenant_id`, `type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
