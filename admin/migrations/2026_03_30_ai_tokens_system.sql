-- 1. Adicionar saldo de tokens extras no tenant
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS ai_extra_tokens INT NOT NULL DEFAULT 0
        COMMENT 'Saldo de tokens extras comprados (não expira mensalmente)';

-- 2. Adicionar preço de token por plano
ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS ai_token_price_per_100 DECIMAL(8,2) NOT NULL DEFAULT 14.90
        COMMENT 'Preço de 100 tokens extras para este plano';

-- 3. Expandir ai_usage_log com distinção de cota-base vs tokens extras
ALTER TABLE ai_usage_log
    ADD COLUMN IF NOT EXISTS base_calls_used   INT NOT NULL DEFAULT 0
        COMMENT 'Chamadas consumidas da cota-base do plano neste mês',
    ADD COLUMN IF NOT EXISTS tokens_consumed   INT NOT NULL DEFAULT 0
        COMMENT 'Tokens extras consumidos neste mês';

-- 4. Pacotes de tokens disponíveis para compra
CREATE TABLE IF NOT EXISTS ai_token_packages (
    package_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,          -- ex: "Pacote 150 Tokens"
    tokens_qty  INT NOT NULL,                   -- quantidade de tokens no pacote
    price       DECIMAL(8,2) NOT NULL,          -- preço em BRL
    description VARCHAR(300) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (package_id),
    INDEX idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados iniciais sugeridos
INSERT IGNORE INTO ai_token_packages (name, tokens_qty, price, sort_order) VALUES
    ('Pacote Básico',    50,  9.90,  1),
    ('Pacote Médio',    150, 24.90,  2),
    ('Pacote Avançado', 500, 69.90,  3),
    ('Pacote Ilimitado',2000,199.90, 4);

-- 5. Histórico de compras de tokens por tenant
CREATE TABLE IF NOT EXISTS ai_token_purchases (
    purchase_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id      INT NOT NULL,
    package_id     INT UNSIGNED DEFAULT NULL,    -- NULL = compra personalizada
    tokens_qty     INT NOT NULL,
    amount_paid    DECIMAL(8,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,     -- pix, card, boleto
    payment_ref    VARCHAR(200) DEFAULT NULL,    -- ID do gateway (saas_orders ref)
    saas_order_id  INT UNSIGNED DEFAULT NULL,    -- FK -> saas_orders.order_id
    status         ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at        DATETIME DEFAULT NULL,
    PRIMARY KEY (purchase_id),
    INDEX idx_tenant    (tenant_id),
    INDEX idx_status    (tenant_id, status),
    INDEX idx_created   (tenant_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Log granular de demanda por produto
CREATE TABLE IF NOT EXISTS ai_demand_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id   INT NOT NULL,
    model_id    INT UNSIGNED NOT NULL,          -- FK -> ai_catalogo_models.id
    query_text  VARCHAR(300) DEFAULT NULL,      -- termo buscado pelo usuário final
    source      ENUM('webhook','catalog_search','photo_search') NOT NULL DEFAULT 'webhook',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_tenant_model (tenant_id, model_id),
    INDEX idx_tenant_date  (tenant_id, created_at DESC),
    INDEX idx_model_date   (model_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Adicionar order_type em saas_orders se existir
SET @dbname = DATABASE();
SET @tablename = "saas_orders";
SET @columnname = "order_type";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE saas_orders ADD COLUMN order_type ENUM('plan','tokens') NOT NULL DEFAULT 'plan' COMMENT 'Tipo do pedido: upgrade de plano ou compra de tokens'"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
