<?php
include __DIR__ . '/_init.php';

$tenantId = isset($argv[1]) ? (int)$argv[1] : 347;

function seed_model_with_variant(
    int $tenantId,
    int $categoryId,
    string $name,
    string $skuModel,
    string $tags,
    string $description,
    float $price,
    string $mainColor,
    int $demandCount,
    string $skuVariant,
    string $color,
    ?string $colorNormalized,
    string $size,
    float $variantPrice,
    int $stockQty
): array {
    $check = db()->prepare("
        SELECT v.id AS variant_id, v.model_id
        FROM ai_catalogo_variants v
        INNER JOIN ai_catalogo_models m ON m.id = v.model_id
        WHERE v.tenant_id = :tid AND m.tenant_id = :tid2 AND v.sku = :sku
        LIMIT 1
    ");
    $check->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':sku' => $skuVariant]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return ['created' => false, 'model_id' => (int)$existing['model_id'], 'variant_id' => (int)$existing['variant_id'], 'sku' => $skuVariant];
    }

    $insModel = db()->prepare("
        INSERT INTO ai_catalogo_models
            (tenant_id, name, sku, tags, description, cover_webp, main_price, main_color, is_active, demand_count, category_id)
        VALUES
            (:tid, :name, :sku, :tags, :description, '', :price, :main_color, 1, :demand_count, :category_id)
    ");
    $insModel->execute([
        ':tid' => $tenantId,
        ':name' => $name,
        ':sku' => $skuModel,
        ':tags' => $tags,
        ':description' => $description,
        ':price' => $price,
        ':main_color' => $mainColor,
        ':demand_count' => $demandCount,
        ':category_id' => $categoryId,
    ]);
    $modelId = (int)db()->lastInsertId();

    $insVar = db()->prepare("
        INSERT INTO ai_catalogo_variants
            (model_id, tenant_id, sku, color, color_normalized, size, price, stock_qty, photo_webp, is_active)
        VALUES
            (:mid, :tid, :sku, :color, :color_normalized, :size, :price, :stock_qty, '', 1)
    ");
    $insVar->execute([
        ':mid' => $modelId,
        ':tid' => $tenantId,
        ':sku' => $skuVariant,
        ':color' => $color,
        ':color_normalized' => $colorNormalized,
        ':size' => $size,
        ':price' => $variantPrice,
        ':stock_qty' => $stockQty,
    ]);

    return ['created' => true, 'model_id' => $modelId, 'variant_id' => (int)db()->lastInsertId(), 'sku' => $skuVariant];
}

$seed = [];
$seed[] = seed_model_with_variant(
    $tenantId,
    38,
    'Blusa Viscose Marinho QA',
    'QA-BLUSA-MARINHO',
    'blusa viscose elegante festa',
    'Produto de teste para busca semântica: blusa azul marinho de viscose tamanho G.',
    129.90,
    'Marinho',
    55,
    'QA-BLUSA-NAVY-G',
    'Azul Marinho',
    'navy',
    'G',
    129.90,
    3
);
$seed[] = seed_model_with_variant(
    $tenantId,
    41,
    'Conjunto Linho Areia QA',
    'QA-CONJ-LINHO',
    'conjunto linho elegante',
    'Produto de teste para busca semântica: conjunto de linho.',
    239.90,
    'Bege',
    45,
    'QA-CONJ-LINHO-42',
    'Bege Areia',
    'bege',
    '42',
    239.90,
    4
);
$seed[] = seed_model_with_variant(
    $tenantId,
    2,
    'Vestido Longo Estampado Verão QA',
    'QA-VEST-ESTAMPA',
    'vestido longo estampado verão festa elegante',
    'Produto de teste para busca semântica: vestido longo estampado para verão e festa, tamanho 42.',
    299.90,
    'Estampado',
    65,
    'QA-VEST-ESTAMP-42',
    'Floral Tropical',
    'estampado',
    '42',
    299.90,
    5
);

echo json_encode(['tenant_id' => $tenantId, 'seed_results' => $seed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
