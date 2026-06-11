<?php
ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_products_extract_token(): string
{
    $token = trim((string)($_SERVER['HTTP_X_CONCIERGE_TOKEN'] ?? ''));
    if ($token === '') {
        $token = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    }
    if (stripos($token, 'Bearer ') === 0) {
        $token = trim(substr($token, 7));
    }
    if ($token === '') {
        $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    }
    return $token;
}

function concierge_products_forbid_if_no_permission(string $perm, bool $isTokenAuth): void
{
    if ($isTokenAuth || user_group_id() == 1) {
        return;
    }
    if (!has_permission('access', $perm)) {
        http_response_code(403);
        echo json_encode(ai_groups_response(true, 'Permissão insuficiente.', null));
        exit;
    }
}

function concierge_products_media_list($value): array
{
    $urls = [];
    if (is_array($value)) {
        foreach ($value as $item) {
            $url = trim((string)$item);
            if ($url !== '') {
                $urls[] = $url;
            }
        }
    } elseif (is_string($value)) {
        $value = trim($value);
        if ($value !== '') {
            $decoded = null;
            if (strpos($value, '[') === 0 || strpos($value, '{') === 0) {
                $decoded = json_decode($value, true);
            }
            if (is_array($decoded)) {
                if (isset($decoded['media_urls'])) {
                    $urls = array_merge($urls, concierge_products_media_list($decoded['media_urls']));
                } else {
                    $urls = array_merge($urls, concierge_products_media_list(array_values($decoded)));
                }
            } elseif (strpos($value, ',') !== false) {
                $urls = array_merge($urls, concierge_products_media_list(array_map('trim', explode(',', $value))));
            } else {
                $urls[] = $value;
            }
        }
    }

    return array_values(array_unique($urls));
}

function concierge_products_resolve_url(string $raw): string
{
    return ai_resolve_storage_url($raw);
}

function concierge_products_pick_main_image(array $row): string
{
    $candidates = [
        'cover_webp',
        'cover_image_webp',
        'photo_webp',
        'thumb_url',
        'thumbnail_url',
        'image_url',
        'image',
        'photo_url',
        'main_image',
    ];

    foreach ($candidates as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            return concierge_products_resolve_url($value);
        }
    }

    $extraMedia = [];
    foreach (['photos_json', 'payload_json', 'media_urls', 'media_url'] as $key) {
        $extraMedia = array_merge($extraMedia, concierge_products_media_list($row[$key] ?? []));
    }
    if (!empty($extraMedia)) {
        return concierge_products_resolve_url((string)$extraMedia[0]);
    }

    return '';
}
function concierge_products_table_exists(string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        try {
            $quoted = db()->quote($table);
            $stmt = db()->query("SHOW TABLES LIKE $quoted");
            $cache[$table] = $stmt ? (bool)$stmt->fetch() : false;
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
    }
    return $cache[$table];
}

function concierge_products_table_columns(string $table): array
{
    if (!concierge_products_table_exists($table)) {
        return [];
    }
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        $cache[$table] = [];
        return $cache[$table];
    }
    try {
        $stmt = db()->query("SHOW COLUMNS FROM `{$table}`");
        $fields = [];
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
            $name = trim((string)($row['Field'] ?? ''));
            if ($name !== '') {
                $fields[] = $name;
            }
        }
        $cache[$table] = $fields;
    } catch (Throwable $e) {
        $cache[$table] = [];
    }
    return $cache[$table];
}
function concierge_products_table_has_column(string $table, string $column): bool
{
    $cols = concierge_products_table_columns($table);
    return in_array($column, $cols, true);
}

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
    $token = concierge_products_extract_token();
    $isTokenAuth = false;

    if ($tenantId > 0 && $token !== '') {
        $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
        $stmt->execute([':tid' => $tenantId]);
        $storedToken = (string)$stmt->fetchColumn();
        if ($storedToken !== '' && hash_equals($storedToken, $token)) {
            $isTokenAuth = true;
        } else {
            http_response_code(401);
            echo json_encode(ai_groups_response(true, 'Token inválido.', null));
            exit;
        }
    }

    if (!$isTokenAuth) {
        if (!is_loggedin()) {
            http_response_code(401);
            echo json_encode(ai_groups_response(true, 'Sessão inválida.', null));
            exit;
        }
        $tenantId = ai_tenant_id();
        if ($tenantId <= 0) {
            throw new Exception('Tenant inválido.');
        }
        concierge_products_forbid_if_no_permission('concierge_groups_access', false);
    }

    if (!ai_groups_plan_is_enabled($tenantId)) {
        http_response_code(402);
        echo json_encode(ai_groups_response(true, 'Módulo de grupos indisponível no plano.', ['blocked' => true]));
        exit;
    }

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(ai_groups_response(true, 'Método não suportado.', null));
        exit;
    }

    $action = strtolower(trim((string)($_GET['action'] ?? '')));
    if ($action === 'categories') {
        $categories = ai_get_catalogo_categories();
        $items = array_map(static function ($row) {
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
            ];
        }, is_array($categories) ? $categories : []);
        echo json_encode(ai_groups_response(false, 'OK', ['items' => $items]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $productId = (int)($_GET['id'] ?? 0);
    if ($productId > 0) {
        $row = ai_get_catalogo_model($productId);
        if (!$row) {
            throw new Exception('Produto não encontrado.');
        }

        // Busca variantes completas (com cor, preço, mídia)
        $variants = [];
        $sizes = [];
        $colors = [];
        $variantPhotos = [];
        try {
            $stv = db()->prepare("SELECT id, sku, size, color, price, photo_webp FROM ai_catalogo_variants WHERE model_id = ? AND tenant_id = ? AND is_active = 1 ORDER BY color, size");
            $stv->execute([$productId, $tenantId]);
            foreach ($stv->fetchAll(PDO::FETCH_ASSOC) as $v) {
                if (!empty($v['size']) && !in_array($v['size'], $sizes)) $sizes[] = $v['size'];
                if (!empty($v['color']) && !in_array($v['color'], $colors)) $colors[] = $v['color'];
                $variantMedia = '';
                if (!empty($v['photo_webp'])) {
                    $variantMedia = concierge_products_resolve_url($v['photo_webp']);
                    if (!in_array($variantMedia, $variantPhotos)) $variantPhotos[] = $variantMedia;
                }
                $variants[] = [
                    'id' => (int)$v['id'],
                    'sku' => (string)($v['sku'] ?? ''),
                    'size' => (string)$v['size'],
                    'color' => (string)$v['color'],
                    'price' => (float)$v['price'],
                    'media_url' => $variantMedia
                ];
            }
        } catch (Throwable $e) {}

        $mediaUrls = concierge_products_media_list($row['photos_json'] ?? $row['media_urls'] ?? []);
        foreach ($variantPhotos as $vp) {
            if (!in_array($vp, $mediaUrls)) $mediaUrls[] = $vp;
        }

        $items = [[
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? 'Produto'),
            'description' => (string)($row['description'] ?? ''),
            'sku' => (string)($row['sku'] ?? $row['code'] ?? $row['reference'] ?? ''),
            'price' => (float)($row['min_price'] > 0 ? $row['min_price'] : ($row['main_price'] ?? $row['price'] ?? 0)),
            'max_price' => (float)($row['max_price'] > 0 ? $row['max_price'] : ($row['main_price'] ?? $row['price'] ?? 0)),
            'stock' => (int)($row['total_stock'] ?? 0),
            'variant_count' => (int)($row['variant_count'] ?? 0),
            'image' => concierge_products_pick_main_image($row),
            'media_urls' => $mediaUrls,
            'variants' => $variants,
            'metadata' => [
                'sizes' => $sizes,
                'colors' => $colors,
                'photo_count' => count($mediaUrls)
            ]
        ]];
        echo json_encode(ai_groups_response(false, 'OK', ['items' => $items, 'total' => 1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 24)));
    $search = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));

    $models = ai_get_catalogo_models([
        'active' => 1,
        'search' => $search,
        'order' => 'demand',
    ]);

    $models = is_array($models) ? $models : [];
    $total = count($models);
    $offset = ($page - 1) * $limit;
    $slice = array_slice($models, $offset, $limit);
    $variantMediaByModel = [];
    $modelIds = array_values(array_unique(array_map(static function (array $row): int {
        return (int)($row['id'] ?? 0);
    }, $slice)));
    $modelIds = array_values(array_filter($modelIds, static function (int $id): bool {
        return $id > 0;
    }));

    if (!empty($modelIds)) {
        $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
        $variantMediaFields = array_values(array_filter([
            'photo_webp',
            'media_url',
            'media_urls',
            'photos_json',
            'payload_json',
            'image_url',
            'cover_webp',
        ], static function (string $field): bool {
            return concierge_products_table_has_column('ai_catalogo_variants', $field);
        }));
        $selectCols = array_merge(['model_id'], $variantMediaFields);
        if (concierge_products_table_has_column('ai_catalogo_variants', 'id')) {
            $selectCols[] = 'id';
        }
        $sql = "
            SELECT " . implode(', ', $selectCols) . "
            FROM ai_catalogo_variants
            WHERE tenant_id = ? AND model_id IN ($placeholders)
            " . (in_array('id', $selectCols, true) ? 'ORDER BY id DESC' : '') . "
        ";
        $stmt = db()->prepare($sql);
        $params = array_merge([$tenantId], $modelIds);
        $stmt->execute($params);
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $variant) {
            $modelId = (int)($variant['model_id'] ?? 0);
            if ($modelId <= 0) {
                continue;
            }
            if (!isset($variantMediaByModel[$modelId])) {
                $variantMediaByModel[$modelId] = [];
            }
            $bucket = [];
            foreach ($variantMediaFields as $field) {
                $bucket = array_merge($bucket, concierge_products_media_list($variant[$field] ?? []));
            }
            foreach ($bucket as $rawUrl) {
                $resolved = concierge_products_resolve_url((string)$rawUrl);
                if ($resolved !== '' && !in_array($resolved, $variantMediaByModel[$modelId], true)) {
                    $variantMediaByModel[$modelId][] = $resolved;
                }
            }
        }
    }

    $items = array_map(static function (array $row) use ($variantMediaByModel): array {
        $modelId = (int)($row['id'] ?? 0);
        $mediaUrls = [];
        foreach (['photos_json', 'payload_json', 'media_urls', 'media_url'] as $key) {
            $mediaUrls = array_merge($mediaUrls, concierge_products_media_list($row[$key] ?? []));
        }
        if (!empty($variantMediaByModel[$modelId])) {
            $mediaUrls = array_merge($mediaUrls, $variantMediaByModel[$modelId]);
        }
        $mediaUrls = array_values(array_unique(array_map('concierge_products_resolve_url', $mediaUrls)));
        $image = concierge_products_pick_main_image($row);
        if ($image !== '' && !in_array($image, $mediaUrls, true)) {
            array_unshift($mediaUrls, $image);
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? 'Produto'),
            'sku' => (string)($row['sku'] ?? $row['code'] ?? $row['reference'] ?? ''),
            'price' => (float)($row['min_price'] ?? $row['price'] ?? 0),
            'max_price' => (float)($row['max_price'] ?? $row['price'] ?? 0),
            'stock' => (int)($row['total_stock'] ?? 0),
            'variant_count' => (int)($row['variant_count'] ?? 0),
            'demand_count' => (int)($row['demand_count'] ?? 0),
            'image' => $image,
            'media_urls' => $mediaUrls,
        ];
    }, $slice);

    echo json_encode(ai_groups_response(false, 'OK', [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
