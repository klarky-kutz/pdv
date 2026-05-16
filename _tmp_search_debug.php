<?php
include __DIR__ . '/_init.php';
require_once DIR_HELPER . 'ai_evolution.php';

$tid = 347;
$queries = [
    'vestido inspiração farm',
    'vestido inspiracao farm',
    'LCF-791',
    'vestido azul',
];

$st = db()->prepare("
    SELECT id, name, sku, tags, is_active, main_color, main_price
    FROM ai_catalogo_models
    WHERE tenant_id = :tid
      AND (sku = :sku OR name LIKE :farm OR tags LIKE :farm)
    ORDER BY id DESC
    LIMIT 20
");
$st->execute([
    ':tid'  => $tid,
    ':sku'  => 'LCF-791',
    ':farm' => '%farm%',
]);
$models = $st->fetchAll(PDO::FETCH_ASSOC);

$variants = [];
if (!empty($models)) {
    $modelIds = array_map(static fn($m) => (int)$m['id'], $models);
    $in = implode(',', array_fill(0, count($modelIds), '?'));
    $sv = db()->prepare("
        SELECT id, model_id, sku, color, color_normalized, size, stock_qty, is_active
        FROM ai_catalogo_variants
        WHERE tenant_id = ?
          AND model_id IN ($in)
        ORDER BY model_id, id
    ");
    $sv->execute(array_merge([$tid], $modelIds));
    $variants = $sv->fetchAll(PDO::FETCH_ASSOC);
}

$tests = [];
foreach ($queries as $q) {
    $tests[] = [
        'query'   => $q,
        'tokens'  => ai_evolution_tokenize_query($q),
        'colors'  => ai_evolution_extract_color_terms($q, ai_evolution_tokenize_query($q)),
        'results' => ai_evolution_search_catalog_variants($tid, $q, 10),
    ];
}

echo json_encode([
    'tenant_id' => $tid,
    'models'    => $models,
    'variants'  => $variants,
    'tests'     => $tests,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
