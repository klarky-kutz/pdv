<?php
include __DIR__ . '/_init.php';
require_once DIR_HELPER . 'ai_evolution.php';

function qa_http_post_json(string $url, array $headers, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = null;
    if (is_string($raw) && $raw !== '') {
        $tmp = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $json = $tmp;
        } else {
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $candidate = substr($raw, $start, $end - $start + 1);
                $tmp2 = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp2)) {
                    $json = $tmp2;
                }
            }
        }
    }

    return [
        'http_code' => $code,
        'error' => $err,
        'raw' => is_string($raw) ? $raw : '',
        'json' => is_array($json) ? $json : null,
    ];
}

function qa_add_score(array &$scores, int $modelId, int $pts, string $reason): void
{
    if (!isset($scores[$modelId])) {
        $scores[$modelId] = ['score' => 0, 'reasons' => []];
    }
    $scores[$modelId]['score'] += $pts;
    $scores[$modelId]['reasons'][] = $reason . " (+{$pts})";
}

function qa_calc_scores(int $tenantId, string $query): array
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    if ($query === '') return [];

    $tokens = ai_evolution_tokenize_query($query);
    $colorTerms = ai_evolution_extract_color_terms($query, $tokens);
    $scores = [];

    foreach ($tokens as $token) {
        $tLike = '%' . $token . '%';

        $stSku = db()->prepare("
            SELECT m.id FROM ai_catalogo_models m
            WHERE m.tenant_id = :tid1 AND (LOWER(m.sku) = :t1 OR LOWER(m.sku) LIKE :tl1) AND m.is_active = 1
            UNION
            SELECT v.model_id FROM ai_catalogo_variants v
            WHERE v.tenant_id = :tid2 AND (LOWER(v.sku) = :t2 OR LOWER(v.sku) LIKE :tl2) AND v.is_active = 1
        ");
        $stSku->execute([
            ':tid1' => $tenantId,
            ':tid2' => $tenantId,
            ':t1' => $token,
            ':tl1' => $tLike,
            ':t2' => $token,
            ':tl2' => $tLike,
        ]);
        while ($mid = $stSku->fetchColumn()) qa_add_score($scores, (int)$mid, 15, "token '{$token}' em SKU");

        $stCat = db()->prepare("
            SELECT m.id FROM ai_catalogo_models m
            INNER JOIN categorys c ON c.category_id = m.category_id
            WHERE m.tenant_id = :tid1 AND m.is_active = 1 AND LOWER(c.category_name) LIKE :tl
        ");
        $stCat->execute([':tid1' => $tenantId, ':tl' => $tLike]);
        while ($mid = $stCat->fetchColumn()) qa_add_score($scores, (int)$mid, 12, "token '{$token}' em categoria");

        $stTags = db()->prepare("
            SELECT m.id FROM ai_catalogo_models m
            WHERE m.tenant_id = :tid1 AND m.is_active = 1 AND LOWER(m.tags) LIKE :tl
        ");
        $stTags->execute([':tid1' => $tenantId, ':tl' => $tLike]);
        while ($mid = $stTags->fetchColumn()) qa_add_score($scores, (int)$mid, 10, "token '{$token}' em tags");

        $stName = db()->prepare("
            SELECT m.id FROM ai_catalogo_models m
            WHERE m.tenant_id = :tid1 AND m.is_active = 1 AND LOWER(m.name) LIKE :tl
        ");
        $stName->execute([':tid1' => $tenantId, ':tl' => $tLike]);
        while ($mid = $stName->fetchColumn()) qa_add_score($scores, (int)$mid, 8, "token '{$token}' em nome");
    }

    $qLike = '%' . $query . '%';
    $stFull = db()->prepare("
        SELECT m.id FROM ai_catalogo_models m
        LEFT JOIN categorys c ON c.category_id = m.category_id
        WHERE m.tenant_id = :tid AND m.is_active = 1
          AND (LOWER(m.name) LIKE :ql1 OR LOWER(m.tags) LIKE :ql2 OR LOWER(c.category_name) LIKE :ql3)
    ");
    $stFull->execute([':tid' => $tenantId, ':ql1' => $qLike, ':ql2' => $qLike, ':ql3' => $qLike]);
    while ($mid = $stFull->fetchColumn()) qa_add_score($scores, (int)$mid, 25, "query completa");

    foreach ($colorTerms as $color) {
        $st = db()->prepare("
            SELECT DISTINCT v.model_id
            FROM ai_catalogo_variants v
            INNER JOIN ai_catalogo_models m ON m.id = v.model_id
            WHERE v.tenant_id = :tid AND m.tenant_id = :tid2
              AND m.is_active = 1 AND v.is_active = 1
              AND v.color_normalized = :color
            LIMIT 20
        ");
        $st->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':color' => $color]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) qa_add_score($scores, (int)$row['model_id'], 9, "cor normalizada '{$color}'");

        $st2 = db()->prepare("
            SELECT DISTINCT v.model_id
            FROM ai_catalogo_variants v
            INNER JOIN ai_catalogo_models m ON m.id = v.model_id
            WHERE v.tenant_id = :tid AND m.tenant_id = :tid2
              AND m.is_active = 1 AND v.is_active = 1
              AND LOWER(v.color) LIKE :ql
            LIMIT 20
        ");
        $st2->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':ql' => '%' . $color . '%']);
        while ($row = $st2->fetch(PDO::FETCH_ASSOC)) qa_add_score($scores, (int)$row['model_id'], 7, "cor comercial '{$color}'");
    }

    if (empty($scores)) return [];

    arsort($scores);
    $modelIds = array_keys($scores);
    $in = implode(',', array_fill(0, count($modelIds), '?'));
    $stModels = db()->prepare("SELECT id, name, sku, demand_count, tags FROM ai_catalogo_models WHERE tenant_id = ? AND id IN ($in)");
    $stModels->execute(array_merge([$tenantId], $modelIds));
    $map = [];
    foreach ($stModels->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $map[(int)$m['id']] = $m;
    }

    $out = [];
    foreach ($modelIds as $mid) {
        $mid = (int)$mid;
        $meta = $map[$mid] ?? ['id' => $mid, 'name' => null, 'sku' => null, 'demand_count' => null, 'tags' => null];
        $out[] = [
            'model_id' => $mid,
            'name' => $meta['name'],
            'sku' => $meta['sku'],
            'demand_count' => isset($meta['demand_count']) ? (int)$meta['demand_count'] : null,
            'score' => (int)$scores[$mid]['score'],
            'reasons' => $scores[$mid]['reasons'],
        ];
    }

    return $out;
}

$tenantId = isset($argv[1]) ? (int)$argv[1] : 347;

$stStore = db()->prepare("SELECT store_id, ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1");
$stStore->execute([':tid' => $tenantId]);
$store = $stStore->fetch(PDO::FETCH_ASSOC);

if (!$store || trim((string)($store['ai_webhook_token'] ?? '')) === '') {
    echo json_encode(['error' => true, 'message' => 'store/token não encontrado para tenant', 'tenant_id' => $tenantId], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$token = (string)$store['ai_webhook_token'];

$stSku = db()->prepare("
    SELECT v.sku
    FROM ai_catalogo_variants v
    INNER JOIN ai_catalogo_models m ON m.id = v.model_id
    WHERE v.tenant_id = :tid
      AND m.tenant_id = :tid2
      AND m.is_active = 1
      AND v.is_active = 1
      AND v.stock_qty > 0
      AND v.sku IS NOT NULL
      AND v.sku <> ''
    ORDER BY m.demand_count DESC, v.id DESC
    LIMIT 1
");
$stSku->execute([':tid' => $tenantId, ':tid2' => $tenantId]);
$exactSku = (string)$stSku->fetchColumn();
if ($exactSku === '') $exactSku = 'SKU_NAO_ENCONTRADO';

$queries = [
    'vestido',
    'azul',
    $exactSku,
    'vestido farm',
    'blusa azul G',
    'conjunto linho',
    'eu queria ver um vestido longo estampado para o verão',
    'vocês tem aquela blusa azul marinho de viscose?',
    'procuro algo elegante para festa tamanho 42',
];

$baseUrl = 'http://localhost/modernpos/api/concierge/webhook.php?action=buscar_produto&loja_id=' . $tenantId;
$headers = ['X-Concierge-Token: ' . $token];

$report = [
    'tenant_id' => $tenantId,
    'picked_exact_sku' => $exactSku,
    'scenarios' => [],
];

foreach ($queries as $q) {
    $endpoint = qa_http_post_json($baseUrl, $headers, ['query' => $q, 'limit' => 10]);
    $scoring = qa_calc_scores($tenantId, $q);
    $report['scenarios'][] = [
        'query' => $q,
        'tokens' => ai_evolution_tokenize_query($q),
        'color_terms' => ai_evolution_extract_color_terms($q, ai_evolution_tokenize_query($q)),
        'endpoint_http_code' => $endpoint['http_code'],
        'endpoint_error' => $endpoint['error'],
        'endpoint_found' => is_array($endpoint['json']) ? (bool)($endpoint['json']['found'] ?? false) : null,
        'endpoint_top_products' => is_array($endpoint['json']) ? array_map(
            static function ($item) {
                $product = $item['product'] ?? [];
                return [
                    'id' => $product['id'] ?? null,
                    'name' => $product['name'] ?? null,
                    'stock_qty' => $product['stock_qty'] ?? null,
                ];
            },
            array_slice((array)($endpoint['json']['results'] ?? []), 0, 5)
        ) : [],
        'score_top' => array_slice($scoring, 0, 8),
    ];
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
