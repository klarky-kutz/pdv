-- ========================================
-- Tabela para controlar sequência de códigos EAN-13
-- Execute este SQL manualmente no seu banco de dados
-- ========================================

CREATE TABLE IF NOT EXISTS `product_code_sequence` (
  `store_id` INT UNSIGNED NOT NULL,
  `last_sequence` BIGINT UNSIGNED NOT NULL DEFAULT 18057090,
  `prefix` VARCHAR(4) NOT NULL DEFAULT '7898',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- INSTRUÇÕES:
-- 1. Conecte-se ao seu banco de dados MySQL
-- 2. Execute este SQL completo
-- 3. Verifique se a tabela foi criada com: SHOW TABLES LIKE 'product_code_sequence';
-- ========================================
