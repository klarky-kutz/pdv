-- Migração: criar tabela de buscas sem resultado (demanda reprimida)
-- Idempotente por meio de CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS ai_search_misses (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT NOT NULL,
    query_original VARCHAR(500) NOT NULL,
    tokens_json    TEXT NULL
                  COMMENT 'JSON array dos tokens extraídos',
    colors_json    TEXT NULL
                  COMMENT 'JSON array dos termos de cor isolados',
    session_phone  VARCHAR(20) NULL
                  COMMENT 'Telefone do cliente, se disponível',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_date (tenant_id, created_at),
    KEY idx_tenant_query (tenant_id, query_original(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Buscas que retornaram found=false — relatório de demanda reprimida';
