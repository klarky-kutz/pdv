-- =====================================================================
-- MIGRATION: Adiciona category_id em ai_catalogo_models
-- Data: 2026-03-30
-- Descrição:
--   Vincula cada modelo do catálogo IA a uma categoria nativa do
--   ModernPOS (tabela categorys), permitindo busca por categoria no
--   webhook da Evolution/n8n (ai_evolution_search_catalog_variants).
--
-- Prioridade de busca após esta migration:
--   1. SKU exato         (ai_catalogo_variants.sku)
--   2. Categoria nativa  (categorys.category_name LIKE)
--   3. Tags              (ai_catalogo_models.tags LIKE)
--   4. Nome              (ai_catalogo_models.name LIKE)
--   5. Fallback          (v.color / v.size LIKE)
-- =====================================================================
-- EXECUTE NO BANCO: modernpos
-- =====================================================================

ALTER TABLE `ai_catalogo_models`
    ADD COLUMN IF NOT EXISTS `category_id` INT DEFAULT NULL
        COMMENT 'FK -> categorys.category_id (categoria nativa ModernPOS)' AFTER `tags`,
    ADD INDEX IF NOT EXISTS `idx_category` (`tenant_id`, `category_id`);

-- =====================================================================
-- MIGRATION CONCLUÍDA
-- =====================================================================
