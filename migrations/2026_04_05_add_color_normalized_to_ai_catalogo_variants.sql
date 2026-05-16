-- Migração: adicionar color_normalized + índice para busca por cor semântica
-- Idempotente para evitar falhas em reexecução.

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ai_catalogo_variants'
      AND COLUMN_NAME = 'color_normalized'
);

SET @sql_add_col := IF(
    @col_exists = 0,
    "ALTER TABLE ai_catalogo_variants ADD COLUMN color_normalized VARCHAR(50) NULL DEFAULT NULL COMMENT 'Cor base padronizada para busca semântica. Ex: azul, rosa, preto. Separado do nome comercial.' AFTER color",
    "SELECT 1"
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'ai_catalogo_variants'
      AND INDEX_NAME = 'idx_color_normalized'
);

SET @sql_add_idx := IF(
    @idx_exists = 0,
    "CREATE INDEX idx_color_normalized ON ai_catalogo_variants (tenant_id, color_normalized, is_active)",
    "SELECT 1"
);
PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;

UPDATE ai_catalogo_variants
SET color_normalized = LOWER(TRIM(SUBSTRING_INDEX(color, ' ', 1)))
WHERE color_normalized IS NULL
  AND color IS NOT NULL
  AND color <> '';
