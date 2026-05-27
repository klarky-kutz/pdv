<?php
/**
 * API: suggestions.php
 * API para gerenciar sugestões de campanhas IA.
 */
ob_start();
session_start();
include '../../_init.php';
require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_groups_helper.php';
require_once DIR_HELPER . 'ai_tokens.php';
header('Content-Type: application/json; charset=UTF-8');

function concierge_get_suggestions_processing_state(int $tenantId): array
{
    $stmt = db()->prepare("SELECT * FROM ai_settings WHERE tenant_id = :tid AND key_name = 'mia_suggestions_processing_state' LIMIT 1");
    $stmt->execute([':tid' => $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nowTs = time();
    
    if (!$row) {
        return [
            'is_processing' => false,
            'started_at' => null,
            'expires_at' => null,
            'last_batch_id' => null,
            'used_this_generation' => 0,
            'started_at_ts' => null,
            'expires_at_ts' => null,
            'now_ts' => $nowTs,
            'ttl_seconds' => 0,
        ];
    }
    
    $state = ai_groups_decode_json($row['value'] ?? null) ?: [];
    $isProcessing = (bool)($state['is_processing'] ?? false);
    $expiresAt = $state['expires_at'] ?? null;
    $startedAt = $state['started_at'] ?? null;
    $startedAtTs = !empty($startedAt) ? strtotime((string)$startedAt) : false;
    $expiresAtTs = !empty($expiresAt) ? strtotime((string)$expiresAt) : false;
    if ($expiresAtTs === false && $isProcessing && $startedAtTs !== false) {
        $expiresAtTs = $startedAtTs + 180;
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);
        $state['expires_at'] = $expiresAt;
    }
    $isExpired = false;
    if ($isProcessing) {
        if (!empty($expiresAtTs) && $expiresAtTs !== false) {
            if ($nowTs > (int)$expiresAtTs) {
                $isExpired = true;
            }
        } elseif (!empty($expiresAt)) {
            try {
                $now = new DateTime();
                $expiresAtDt = new DateTime((string)$expiresAt);
                if ($now > $expiresAtDt) {
                    $isExpired = true;
                }
            } catch (Throwable $e) {
                $isExpired = true;
            }
        }
    }
    if ($isExpired) {
        $state['is_processing'] = false;
        concierge_set_suggestions_processing_state($tenantId, $state);
    }
    $effectiveIsProcessing = $isExpired ? false : $isProcessing;
    $effectiveStartedAtTs = ($startedAtTs !== false) ? (int)$startedAtTs : null;
    $effectiveExpiresAtTs = ($expiresAtTs !== false) ? (int)$expiresAtTs : null;
    $ttlSeconds = ($effectiveIsProcessing && $effectiveExpiresAtTs !== null) ? max(0, $effectiveExpiresAtTs - $nowTs) : 0;
    return [
        'is_processing' => $effectiveIsProcessing,
        'started_at' => $state['started_at'] ?? null,
        'expires_at' => $state['expires_at'] ?? null,
        'last_batch_id' => $state['last_batch_id'] ?? null,
        'used_this_generation' => (int)($state['used_this_generation'] ?? 0),
        'started_at_ts' => $effectiveStartedAtTs,
        'expires_at_ts' => $effectiveExpiresAtTs,
        'now_ts' => $nowTs,
        'ttl_seconds' => $ttlSeconds,
    ];
}

function concierge_set_suggestions_processing_state(int $tenantId, array $state): void
{
    $jsonValue = json_encode($state, JSON_UNESCAPED_UNICODE);
    ai_save_setting('mia_suggestions_processing_state', $jsonValue, $tenantId);
}

function concierge_get_token_stats(int $tid): array
{
    $ym = date('Y-m');
    $plan = ai_get_active_plan($tid);
    $usage = ai_get_usage($tid, $ym);
    $tokenBalance = ai_get_token_balance($tid);
    
    $callLimit = (int)($plan['ai_webhook_calls'] ?? 0);
    $usedCalls = (int)($usage['webhook_calls'] ?? 0);
    $baseRemaining = $callLimit === 0 ? 999999 : max(0, $callLimit - $usedCalls);
    
    return [
        'used_month' => $usedCalls,
        'monthly_limit' => $callLimit,
        'extra_tokens_balance' => $tokenBalance,
        'base_remaining' => $baseRemaining,
        'total_available' => $baseRemaining + $tokenBalance,
        'is_unlimited' => $callLimit === 0,
        'used_this_generation' => 0
    ];
}

function concierge_suggestions_extract_token(): string
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
function concierge_suggestions_media_list($value): array
{
    $urls = [];
    if (is_array($value)) {
        if (empty($value)) {
            return [];
        }
        $isAssoc = array_keys($value) !== range(0, max(count($value) - 1, 0));
        if ($isAssoc) {
            foreach ([
                'media_urls',
                'media_url',
                'image',
                'image_url',
                'photo_url',
                'photo_webp',
                'thumb_url',
                'thumbnail_url',
                'cover_webp',
                'cover_image_webp',
                'main_image',
                'url',
                'src'
            ] as $key) {
                if (array_key_exists($key, $value)) {
                    $urls = array_merge($urls, concierge_suggestions_media_list($value[$key]));
                }
            }
        } else {
            foreach ($value as $item) {
                $urls = array_merge($urls, concierge_suggestions_media_list($item));
            }
        }
    } elseif (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        if (strpos($value, '[') === 0 || strpos($value, '{') === 0) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $urls = array_merge($urls, concierge_suggestions_media_list($decoded));
                return array_values(array_unique(array_filter($urls)));
            }
        }
        if (strpos($value, ',') !== false) {
            foreach (array_map('trim', explode(',', $value)) as $item) {
                if ($item !== '') {
                    $urls[] = $item;
                }
            }
        } else {
            $urls[] = $value;
        }
    }
    return array_values(array_unique(array_filter($urls)));
}
function concierge_suggestions_get_public_base_url(int $tenantId): string
{
    static $cache = [];
    $tenantId = max(0, (int)$tenantId);
    if (array_key_exists($tenantId, $cache)) {
        return $cache[$tenantId];
    }
    $baseUrl = '';
    if ($tenantId > 0 && function_exists('ai_groups_get_public_base_url')) {
        $baseUrl = rtrim((string)ai_groups_get_public_base_url($tenantId), '/');
    }
    $cache[$tenantId] = $baseUrl;
    return $baseUrl;
}
function concierge_suggestions_replace_localhost_url(string $url, int $tenantId = 0): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $parsed = @parse_url($url);
    if (!is_array($parsed)) {
        return $url;
    }
    $host = strtolower((string)($parsed['host'] ?? ''));
    if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return $url;
    }
    $publicBase = concierge_suggestions_get_public_base_url($tenantId);
    if ($publicBase === '' || stripos($publicBase, 'localhost') !== false) {
        return $url;
    }
    $publicParsed = @parse_url($publicBase);
    if (!is_array($publicParsed)) {
        return $url;
    }
    $publicScheme = (string)($publicParsed['scheme'] ?? 'https');
    $publicHost = (string)($publicParsed['host'] ?? '');
    if ($publicHost === '') {
        return $url;
    }
    $publicPort = isset($publicParsed['port']) ? (int)$publicParsed['port'] : 0;
    $path = (string)($parsed['path'] ?? '');
    if ($path === '') {
        $path = '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    $encodedPath = '/' . ltrim($encodedPath, '/');
    $query = isset($parsed['query']) && $parsed['query'] !== '' ? ('?' . $parsed['query']) : '';
    $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? ('#' . $parsed['fragment']) : '';
    $origin = $publicScheme . '://' . $publicHost . ($publicPort > 0 ? (':' . $publicPort) : '');
    return $origin . $encodedPath . $query . $fragment;
}

function concierge_suggestions_get_product_media_urls(array $product, int $tenantId = 0): array
{
    $mediaUrls = [];
    foreach (['media_urls', 'media_url', 'photos_json', 'payload_json'] as $key) {
        $mediaUrls = array_merge($mediaUrls, concierge_suggestions_media_list($product[$key] ?? []));
    }
    $candidates = [
        'cover_webp', 'cover_image_webp', 'photo_webp', 'thumb_url', 'thumbnail_url',
        'image_url', 'image', 'photo_url', 'main_image'
    ];
    foreach ($candidates as $key) {
        $mediaUrls = array_merge($mediaUrls, concierge_suggestions_media_list($product[$key] ?? ''));
    }
    $resolved = [];
    foreach ($mediaUrls as $url) {
        $resolvedUrl = trim((string)ai_resolve_storage_url((string)$url));
        $resolvedUrl = concierge_suggestions_replace_localhost_url($resolvedUrl, $tenantId);
        if ($resolvedUrl !== '') {
            $resolved[] = $resolvedUrl;
        }
    }
    return array_values(array_unique($resolved));
}

function concierge_suggestions_product_from_catalog(int $tenantId, int $productId): array
{
    if ($tenantId <= 0 || $productId <= 0) {
        return [];
    }
    try {
        $stmt = db()->prepare("SELECT * FROM ai_catalogo_models WHERE id = :pid AND tenant_id = :tid LIMIT 1");
        $stmt->execute([':pid' => $productId, ':tid' => $tenantId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return [];
        }
        $name = trim((string)($product['name'] ?? 'Produto'));
        $description = trim((string)($product['description'] ?? ''));
        $price = (float)($product['min_price'] ?? $product['main_price'] ?? $product['price'] ?? 0);
        $sku = trim((string)($product['sku'] ?? $product['code'] ?? $product['reference'] ?? ''));
        $mediaUrls = concierge_suggestions_get_product_media_urls($product, $tenantId);
        return [
            'id' => (int)$product['id'],
            'nome' => $name,
            'name' => $name,
            'descricao' => $description,
            'description' => $description,
            'preco' => $price,
            'price' => $price,
            'sku' => $sku,
            'media_urls' => $mediaUrls,
            'media_url' => $mediaUrls[0] ?? '',
            'image' => $mediaUrls[0] ?? '',
        ];
    } catch (Throwable $e) {
        return [];
    }
}

function concierge_suggestions_hydrate_product(int $tenantId, int $productId, $productPayload): array
{
    $product = is_array($productPayload) ? $productPayload : [];
    $resolvedProductId = $productId > 0 ? $productId : (int)($product['id'] ?? 0);
    $catalogProduct = concierge_suggestions_product_from_catalog($tenantId, $resolvedProductId);
    if ($resolvedProductId <= 0) {
        $resolvedProductId = (int)($catalogProduct['id'] ?? 0);
    }

    $name = trim((string)($product['nome'] ?? $product['name'] ?? $catalogProduct['nome'] ?? $catalogProduct['name'] ?? ''));
    if ($name === '') {
        $name = $resolvedProductId > 0 ? ('Produto ' . $resolvedProductId) : 'Produto';
    }
    $description = trim((string)($product['descricao'] ?? $product['description'] ?? $catalogProduct['descricao'] ?? $catalogProduct['description'] ?? ''));
    $rawPrice = $product['preco'] ?? $product['price'] ?? $catalogProduct['preco'] ?? $catalogProduct['price'] ?? 0;
    $price = is_numeric($rawPrice) ? (float)$rawPrice : 0.0;
    $sku = trim((string)($product['sku'] ?? $catalogProduct['sku'] ?? ''));
    $mediaUrls = concierge_suggestions_get_product_media_urls($product, $tenantId);
    if (empty($mediaUrls) && !empty($catalogProduct['media_urls']) && is_array($catalogProduct['media_urls'])) {
        $mediaUrls = array_values(array_unique(array_filter(array_map(static function ($url) use ($tenantId) {
            return concierge_suggestions_replace_localhost_url((string)$url, $tenantId);
        }, $catalogProduct['media_urls']))));
    }

    $hydrated = $product;
    $hydrated['id'] = $resolvedProductId;
    $hydrated['nome'] = $name;
    $hydrated['name'] = $name;
    $hydrated['descricao'] = $description;
    $hydrated['description'] = $description;
    $hydrated['preco'] = $price;
    $hydrated['price'] = $price;
    $hydrated['sku'] = $sku;
    $hydrated['media_urls'] = $mediaUrls;
    $hydrated['media_url'] = $mediaUrls[0] ?? '';
    $hydrated['image'] = $mediaUrls[0] ?? '';
    return $hydrated;
}
function concierge_suggestions_sku_base(string $sku): string
{
    $sku = strtoupper(trim($sku));
    if ($sku === '') return '';
    return preg_replace('/-\d+$/', '', $sku) ?: $sku;
}
function concierge_suggestions_group_key(array $suggestion, array $payload): string
{
    $product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
    $skuBase = concierge_suggestions_sku_base((string)($product['sku'] ?? ''));
    if ($skuBase !== '') return 'sku:' . $skuBase;
    $productId = (int)($suggestion['product_id'] ?? 0);
    if ($productId > 0) return 'product:' . $productId;
    return 'suggestion:' . (int)($suggestion['id'] ?? 0);
}
function concierge_suggestions_list_grouped_pending(int $tenantId, int $maxGroups = 2): array
{
    $maxGroups = max(1, $maxGroups);
    $stmt = db()->prepare("SELECT * FROM concierge_ai_suggestions WHERE tenant_id = ? AND status = 'pending' ORDER BY id ASC LIMIT 300");
    $stmt->execute([$tenantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($rows)) return [];

    $allowedGroups = [];
    $result = [];
    foreach ($rows as $row) {
        $payload = ai_groups_decode_json($row['suggestion_payload_json'] ?? null);
        $productId = (int)($row['product_id'] ?? 0);
        $payload['product'] = concierge_suggestions_hydrate_product($tenantId, $productId, $payload['product'] ?? []);
        $groupKey = concierge_suggestions_group_key($row, $payload);
        if (!isset($allowedGroups[$groupKey]) && count($allowedGroups) >= $maxGroups) {
            continue;
        }
        $allowedGroups[$groupKey] = true;
        $result[] = [
            'id' => (int)$row['id'],
            'product_id' => $productId,
            'payload' => $payload,
            'status' => (string)($row['status'] ?? 'pending'),
            'created_at' => $row['created_at'] ?? null,
        ];
    }
    return $result;
}
function concierge_suggestions_parse_ids($raw): array
{
    if (is_array($raw)) {
        return array_values(array_unique(array_filter(array_map('intval', $raw), function ($id) { return $id > 0; })));
    }
    $raw = trim((string)$raw);
    if ($raw === '') return [];
    return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), function ($id) { return $id > 0; })));
}
function concierge_suggestions_default_cta_text(): string
{
    return '📲 Chama no privado!';
}
function concierge_suggestions_detect_cta_key(string $ctaText): string
{
    $ctaText = trim($ctaText);
    if ($ctaText === '') return '';
    $map = [
        'chama' => '📲 Chama no privado!',
        'manda' => '💬 Me manda mensagem!',
        'reserva' => '🛍️ Quer reservar?',
        'quero' => '🙋‍♂️ Eu Quero!',
        'corre' => '⚡ Corre! Últimas unidades!',
    ];
    foreach ($map as $key => $label) {
        if (mb_strtolower(trim($label), 'UTF-8') === mb_strtolower($ctaText, 'UTF-8')) return $key;
    }
    return '';
}
function concierge_suggestions_build_whatsapp_link(string $number, string $ctaText, string $sku = ''): string
{
    $number = preg_replace('/[^0-9]/', '', $number);
    if ($number === '') $number = '5511999999999';
    $text = trim($ctaText);
    if ($text === '') $text = concierge_suggestions_default_cta_text();
    if (trim($sku) !== '') $text .= ' - SKU: ' . trim($sku);
    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');

// Autenticação: se for action=webhook, usar token; senão, login
$tid = 0;
if ($action === 'webhook') {
    $token = concierge_suggestions_extract_token();
    $tenantIdParam = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
    if ($tenantIdParam > 0 && $token !== '') {
        $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
        $stmt->execute([':tid' => $tenantIdParam]);
        $storedToken = (string)$stmt->fetchColumn();
        if ($storedToken !== '' && hash_equals($storedToken, $token)) {
            $tid = $tenantIdParam;
        }
    }
    if ($tid <= 0) {
        http_response_code(401);
        echo json_encode(['errorMsg' => 'Token ou tenant inválido']);
        exit;
    }
} else {
    if (!is_loggedin()) {
        http_response_code(401);
        echo json_encode(['errorMsg' => 'Não logado']);
        exit;
    }
    $tid = ai_tenant_id();
}

ai_groups_ensure_suggestions_schema();
$shouldResetProcessingOnError = false;
$webhookDispatchAttempted = false;
try {
    if ($action === 'webhook') {
        $body = file_get_contents('php://input');
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new Exception('Payload JSON inválido');
        }
        $processingStateBeforeWebhook = concierge_get_suggestions_processing_state($tid);
        $suggestionsFromWebhook = (array)($payload['suggestions'] ?? []);
        if (empty($suggestionsFromWebhook)) {
            throw new Exception('Nenhuma sugestão no payload');
        }
        
        $webhookUrl = trim((string)($payload['source_url'] ?? ''));
        $batchId = trim((string)($payload['batch_id'] ?? ''));
        if ($batchId === '') {
            $batchId = uniqid('batch_', true);
        }
        
        $formattedSuggestions = [];
        foreach ($suggestionsFromWebhook as $sug) {
            $incomingProduct = is_array($sug['product'] ?? null) ? $sug['product'] : [];
            $productId = (int)($sug['product_id'] ?? $incomingProduct['id'] ?? 0);
            if ($productId <= 0) continue;
            $hydratedProduct = concierge_suggestions_hydrate_product($tid, $productId, $incomingProduct);
            
            $payloadToSave = [
                'texto_card' => (string)($sug['texto_card'] ?? $sug['card_text'] ?? ''),
                'texto_boas_vindas' => (string)($sug['texto_boas_vindas'] ?? $sug['welcome_text'] ?? ''),
                'cta' => (string)($sug['cta'] ?? ''),
                'modo' => (string)($sug['modo'] ?? $sug['mode'] ?? 'single'),
                'product' => $hydratedProduct,
                'token_cost' => (int)($processingStateBeforeWebhook['used_this_generation'] ?? 0),
                'token_consumed' => true,
            ];
            
            $stmtInsert = db()->prepare("INSERT INTO concierge_ai_suggestions (tenant_id, product_id, suggestion_payload_json, status, batch_id, source_webhook, created_at) VALUES (:tid, :pid, :payload, 'pending', :batch, :source, NOW())");
            $stmtInsert->execute([
                ':tid' => $tid,
                ':pid' => $productId,
                ':payload' => json_encode($payloadToSave, JSON_UNESCAPED_UNICODE),
                ':batch' => $batchId,
                ':source' => $webhookUrl,
            ]);
            
            $formattedSuggestions[] = [
                'id' => (int)db()->lastInsertId(),
                'product_id' => $productId,
                'payload' => $payloadToSave,
                'status' => 'pending',
            ];
        }
        
        concierge_set_suggestions_processing_state($tid, [
            'is_processing' => false,
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => null,
            'last_batch_id' => $batchId,
            'used_this_generation' => (int)($processingStateBeforeWebhook['used_this_generation'] ?? 0),
        ]);
        echo json_encode(['ok' => true, 'suggestions' => $formattedSuggestions]);
        exit;
    }
    if ($action === 'generate') {
        // First, check if we're already processing
        $processingState = concierge_get_suggestions_processing_state($tid);
        if ($processingState['is_processing']) {
            $tokenStats = concierge_get_token_stats($tid);
            $tokenStats['used_this_generation'] = (int)($processingState['used_this_generation'] ?? 0);
            echo json_encode([
                'ok' => true, 
                'suggestions' => concierge_suggestions_list_grouped_pending($tid, 2), 
                'token_stats' => $tokenStats,
                'processing' => $processingState
            ]);
            exit;
        }
        
        $webhookUrl = trim((string)ai_get_setting('ai_groups_suggestions_webhook_url', '', $tid));
        if ($webhookUrl === '') {
            throw new Exception('Webhook de sugestões não configurado.');
        }
        // Check if there are pending suggestions
        $stmtCheck = db()->prepare("SELECT COUNT(*) FROM concierge_ai_suggestions WHERE tenant_id = ? AND status = 'pending' LIMIT 1");
        $stmtCheck->execute([$tid]);
        $pendingCount = (int)$stmtCheck->fetchColumn();
        if ($pendingCount > 0) {
            $tokenStats = concierge_get_token_stats($tid);
            
            // Get token_cost from existing pending suggestions
            $stmtPending = db()->prepare("SELECT suggestion_payload_json FROM concierge_ai_suggestions WHERE tenant_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
            $stmtPending->execute([$tid]);
            $pendingPayload = $stmtPending->fetch(PDO::FETCH_ASSOC);
            if ($pendingPayload) {
                $payload = ai_groups_decode_json($pendingPayload['suggestion_payload_json'] ?? null);
                $tokenStats['used_this_generation'] = (int)($payload['token_cost'] ?? 0);
            }
            if ($tokenStats['used_this_generation'] <= 0) {
                $tokenStats['used_this_generation'] = (int)($processingState['used_this_generation'] ?? 0);
            }
            
            echo json_encode(['ok' => true, 'suggestions' => concierge_suggestions_list_grouped_pending($tid, 2), 'token_stats' => $tokenStats, 'processing' => $processingState]);
            exit;
        }
        // Get eligible products from ai_catalogo_models:
        // - Active products
        // - In allowed categories
        // - Not already used in any campaign
        // - Not in pending suggestions
        $allowedCatsJson = ai_get_setting('mia_allowed_categories', '', $tid);
        $allowedCats = ai_groups_decode_json($allowedCatsJson);
        
        // Get products that are NOT in pending suggestions or campaigns
        $excludedProductIds = [];
        
        // Exclude products already in pending suggestions
        $stmtExcludeSug = db()->prepare("SELECT product_id FROM concierge_ai_suggestions WHERE tenant_id = :tid AND status = 'pending'");
        $stmtExcludeSug->execute([':tid' => $tid]);
        $excludedProductIds = array_merge($excludedProductIds, array_filter(array_column($stmtExcludeSug->fetchAll(PDO::FETCH_ASSOC), 'product_id')));
        
        // Exclude products already used in any campaign (manual ou automática)
        if (ai_groups_table_exists('concierge_campaigns')) {
            $stmtExcludeCamp = db()->prepare("SELECT product_id FROM concierge_campaigns WHERE tenant_id = :tid AND product_id IS NOT NULL AND product_id > 0");
            $stmtExcludeCamp->execute([':tid' => $tid]);
            $excludedProductIds = array_merge($excludedProductIds, array_filter(array_column($stmtExcludeCamp->fetchAll(PDO::FETCH_ASSOC), 'product_id')));
        }
        
        $excludedProductIds = array_unique(array_map('intval', $excludedProductIds));
        
        // Get products from ai_catalogo_models using ai_get_catalogo_models helper
        $products = ai_get_catalogo_models(['active' => 1]);
        $products = is_array($products) ? $products : [];
        
        // Filter products: apply allowed categories, exclude product IDs, and exclude products with 0 stock
        $filteredProducts = [];
        foreach ($products as $product) {
            $prodId = (int)($product['id'] ?? 0);
            if ($prodId <= 0) continue;
            if (in_array($prodId, $excludedProductIds, true)) continue;
            
            $catId = (int)($product['category_id'] ?? 0);
            if (!empty($allowedCats) && !in_array($catId, array_map('intval', $allowedCats), true)) continue;
            
            // Check if product has at least 1 active variant with stock (if stock column exists)
            // First check if stock column exists in ai_catalogo_variants
            $hasStockColumn = ai_groups_column_exists('ai_catalogo_variants', 'stock') || ai_groups_column_exists('ai_catalogo_variants', 'quantity') || ai_groups_column_exists('ai_catalogo_variants', 'qty');
            $hasEnoughStock = true;
            
            if ($hasStockColumn) {
                // Try to get total stock for product's active variants
                $stockCol = ai_groups_column_exists('ai_catalogo_variants', 'stock') ? 'stock' : (ai_groups_column_exists('ai_catalogo_variants', 'quantity') ? 'quantity' : 'qty');
                $stmtStock = db()->prepare("SELECT COALESCE(SUM($stockCol), 0) as total_stock FROM ai_catalogo_variants WHERE model_id = :pid AND tenant_id = :tid AND is_active = 1");
                $stmtStock->execute([':pid' => $prodId, ':tid' => $tid]);
                $totalStock = (int)$stmtStock->fetchColumn();
                if ($totalStock <= 0) {
                    $hasEnoughStock = false;
                }
            }
            
            if (!$hasEnoughStock) continue;
            
            $filteredProducts[] = $product;
        }
        
        // Shuffle and take 2
        shuffle($filteredProducts);
        $selectedProducts = array_slice($filteredProducts, 0, 2);
        
        if (empty($selectedProducts)) {
            throw new Exception('Nenhum produto elegível encontrado para sugestões.');
        }
        
        // Calculate token cost: 3 tokens per active variation
        $totalCost = 0;
        foreach ($selectedProducts as $product) {
            $productId = (int)($product['id'] ?? 0);
            $stmtCountVariants = db()->prepare("SELECT COUNT(*) FROM ai_catalogo_variants WHERE model_id = :pid AND tenant_id = :tid AND is_active = 1");
            $stmtCountVariants->execute([':pid' => $productId, ':tid' => $tid]);
            $variantCount = (int)$stmtCountVariants->fetchColumn();
            $cost = $variantCount * 3;
            $totalCost += $cost;
        }
        
        // Check if we have enough tokens/calls
        $tokenStats = concierge_get_token_stats($tid);
        $totalAvailable = $tokenStats['total_available'];
        if ($totalCost > $totalAvailable) {
            throw new Exception('Saldo insuficiente. Custo necessário: ' . $totalCost . ', saldo disponível: ' . $totalAvailable . '.');
        }
        $batchId = uniqid('batch_', true);
        concierge_set_suggestions_processing_state($tid, [
            'is_processing' => true,
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + 180),
            'last_batch_id' => $batchId,
            'used_this_generation' => $totalCost,
        ]);
        $shouldResetProcessingOnError = true;
        for ($i = 0; $i < $totalCost; $i++) {
            $consume = ai_consume_call($tid);
            if (empty($consume['allowed'])) {
                throw new Exception('Saldo insuficiente para concluir a geração desta rodada.');
            }
        }
        
        // Prepare payload for webhook
        $payloadWebhook = [
            'event' => 'generate_suggestions',
            'tenant_id' => $tid,
            'batch_id' => $batchId,
            'products' => [],
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        foreach ($selectedProducts as $product) {
            $productId = (int)($product['id'] ?? 0);
            $productMediaUrls = concierge_suggestions_get_product_media_urls($product, $tid);
            $productMainMediaUrl = (string)($productMediaUrls[0] ?? '');
            $productData = [
                'id' => $productId,
                'nome' => (string)($product['name'] ?? 'Produto'),
                'preco' => (float)($product['min_price'] ?? $product['main_price'] ?? $product['price'] ?? 0),
                'descricao' => (string)($product['description'] ?? ''),
                'categoria' => [
                    'id' => (int)($product['category_id'] ?? 0),
                    'nome' => ''
                ],
                'sku' => (string)($product['sku'] ?? $product['code'] ?? $product['reference'] ?? ''),
                'media_url' => $productMainMediaUrl,
            ];
            // Get category name
            if (!empty($productData['categoria']['id'])) {
                $stmtCat = db()->prepare("SELECT c.category_name as name 
                                          FROM categorys c
                                          INNER JOIN category_to_store c2s ON c.category_id = c2s.ccategory_id
                                          WHERE c2s.store_id = :tid AND c2s.status = 1 AND c.category_id = :cid
                                          LIMIT 1");
                $stmtCat->execute([':cid' => $productData['categoria']['id'], ':tid' => $tid]);
                $catName = $stmtCat->fetchColumn();
                $productData['categoria']['nome'] = (string)$catName;
            }
            // Get variants (size, color, media) from ai_catalogo_variants
            $stmtVariants = db()->prepare("SELECT id, sku, size, color, price, photo_webp FROM ai_catalogo_variants WHERE model_id = :pid AND tenant_id = :tid AND is_active = 1 ORDER BY color, size LIMIT 20");
            $stmtVariants->execute([':pid' => $productId, ':tid' => $tid]);
            $variantRows = $stmtVariants->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $variants = [];
            foreach ($variantRows as $variant) {
                $variantMediaRaw = concierge_suggestions_media_list($variant['photo_webp'] ?? '');
                $variantMediaUrl = '';
                foreach ($variantMediaRaw as $mediaRaw) {
                    $resolved = trim((string)ai_resolve_storage_url((string)$mediaRaw));
                    $resolved = concierge_suggestions_replace_localhost_url($resolved, $tid);
                    if ($resolved !== '') {
                        $variantMediaUrl = $resolved;
                        break;
                    }
                }
                if ($variantMediaUrl === '') {
                    $variantMediaUrl = $productMainMediaUrl;
                }
                $variantSize = trim((string)($variant['size'] ?? ''));
                $variantColor = trim((string)($variant['color'] ?? ''));
                $variantDescription = trim((string)($productData['descricao'] ?? ''));
                if ($variantDescription === '') {
                    $variantDescription = trim((string)($productData['nome'] ?? 'Produto'));
                    if ($variantSize !== '') {
                        $variantDescription .= ', tamanho ' . $variantSize;
                    }
                    if ($variantColor !== '') {
                        $variantDescription .= ', cor ' . $variantColor;
                    }
                }
                $variants[] = [
                    'id' => (int)($variant['id'] ?? 0),
                    'sku' => (string)($variant['sku'] ?? ''),
                    'size' => $variantSize,
                    'color' => $variantColor,
                    'price' => (float)($variant['price'] ?? 0),
                    'descricao' => $variantDescription,
                    'media_url' => $variantMediaUrl,
                ];
            }
            $productData['modo'] = count($variants) > 1 ? 'multiple' : 'single';
            $productData['variants'] = $variants;
            $payloadWebhook['products'][] = $productData;
        }
        
        $webhookDispatchAttempted = true;
        $resultPost = ai_groups_http_post_json($webhookUrl, $payloadWebhook, [], 180);
        if (empty($resultPost['ok'])) {
            throw new Exception("Falha ao chamar webhook de sugestões: " . ($resultPost['error'] ?? 'Erro desconhecido'));
        }
        $responseJson = (array)($resultPost['json'] ?? []);
        $suggestionsFromWebhook = (array)($responseJson['suggestions'] ?? []);
        
        if (empty($suggestionsFromWebhook)) {
            $tokenStats = concierge_get_token_stats($tid);
            $tokenStats['used_this_generation'] = $totalCost;
            $processingState = concierge_get_suggestions_processing_state($tid);
            echo json_encode([
                'ok' => true,
                'suggestions' => concierge_suggestions_list_grouped_pending($tid, 2),
                'token_stats' => $tokenStats,
                'processing' => $processingState
            ]);
            exit;
        }
        
        $formattedSuggestions = [];
        foreach ($suggestionsFromWebhook as $idx => $sug) {
            $baseProduct = is_array($payloadWebhook['products'][$idx] ?? null) ? $payloadWebhook['products'][$idx] : [];
            $incomingProduct = is_array($sug['product'] ?? null) ? $sug['product'] : [];
            $productData = array_merge($baseProduct, $incomingProduct);
            $productId = (int)($sug['product_id'] ?? $productData['id'] ?? $selectedProducts[$idx]['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $productData = concierge_suggestions_hydrate_product($tid, $productId, $productData);
            $payloadToSave = [
                'texto_card' => (string)($sug['texto_card'] ?? $sug['card_text'] ?? ''),
                'texto_boas_vindas' => (string)($sug['texto_boas_vindas'] ?? $sug['welcome_text'] ?? ''),
                'cta' => (string)($sug['cta'] ?? ''),
                'modo' => (string)($sug['modo'] ?? $sug['mode'] ?? $productData['modo'] ?? 'single'),
                'product' => $productData,
                'token_cost' => $totalCost,
                'token_consumed' => true,
            ];
            $stmtInsert = db()->prepare("INSERT INTO concierge_ai_suggestions (tenant_id, product_id, suggestion_payload_json, status, batch_id, source_webhook, created_at) VALUES (:tid, :pid, :payload, 'pending', :batch, :source, NOW())");
            $stmtInsert->execute([
                ':tid' => $tid,
                ':pid' => $productId,
                ':payload' => json_encode($payloadToSave, JSON_UNESCAPED_UNICODE),
                ':batch' => $batchId,
                ':source' => $webhookUrl,
            ]);
            $formattedSuggestions[] = [
                'id' => (int)db()->lastInsertId(),
                'product_id' => $productId,
                'payload' => $payloadToSave,
                'status' => 'pending',
            ];
        }
        
        // Get updated token stats
        $tokenStats = concierge_get_token_stats($tid);
        $tokenStats['used_this_generation'] = $totalCost;
        concierge_set_suggestions_processing_state($tid, [
            'is_processing' => false,
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => null,
            'last_batch_id' => $batchId,
            'used_this_generation' => $totalCost,
        ]);
        $shouldResetProcessingOnError = false;
        
        // Get final processing state
        $finalProcessingState = concierge_get_suggestions_processing_state($tid);
        
        echo json_encode(['ok' => true, 'suggestions' => $formattedSuggestions, 'token_stats' => $tokenStats, 'processing' => $finalProcessingState]);
        exit;
    } elseif ($action === 'list') {
        $tokenStats = concierge_get_token_stats($tid);
        $processingState = concierge_get_suggestions_processing_state($tid);
        
        // Check pending suggestions to get used_this_generation
        $stmtPending = db()->prepare("SELECT suggestion_payload_json FROM concierge_ai_suggestions WHERE tenant_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $stmtPending->execute([$tid]);
        $pendingPayload = $stmtPending->fetch(PDO::FETCH_ASSOC);
        $hasPendingSuggestions = $pendingPayload !== false;
        
        if ($hasPendingSuggestions) {
            $payload = ai_groups_decode_json($pendingPayload['suggestion_payload_json'] ?? null);
            $tokenStats['used_this_generation'] = (int)($payload['token_cost'] ?? 0);
        } else {
            // If no pending suggestions, reset used_this_generation to 0
            $tokenStats['used_this_generation'] = 0;
        }
        
        echo json_encode(['ok' => true, 'suggestions' => concierge_suggestions_list_grouped_pending($tid, 2), 'token_stats' => $tokenStats, 'processing' => $processingState]);
        exit;
    } elseif ($action === 'accept' || $action === 'accept_group') {
        $suggestionIds = concierge_suggestions_parse_ids($_POST['suggestion_ids'] ?? ($_POST['suggestion_id'] ?? ''));
        if (empty($suggestionIds)) {
            throw new Exception('ID da sugestão inválido.');
        }
        $rejectIds = concierge_suggestions_parse_ids($_POST['reject_ids'] ?? '');
        $overridesRaw = trim((string)($_POST['overrides_json'] ?? ''));
        $overrides = [];
        if ($overridesRaw !== '') {
            $decodedOverrides = json_decode($overridesRaw, true);
            if (!is_array($decodedOverrides)) {
                throw new Exception('overrides_json inválido.');
            }
            $overrides = $decodedOverrides;
        }
        $ph = implode(',', array_fill(0, count($suggestionIds), '?'));
        $stmt = db()->prepare("SELECT * FROM concierge_ai_suggestions WHERE tenant_id = ? AND status = 'pending' AND id IN ($ph) ORDER BY id ASC");
        $stmt->execute(array_merge([$tid], $suggestionIds));
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($suggestions)) {
            throw new Exception('Sugestão não encontrada.');
        }
        
        // Check and consume tokens if needed
        $firstSuggestion = $suggestions[0];
        $firstPayload = ai_groups_decode_json($firstSuggestion['suggestion_payload_json'] ?? null);
        $tokenCost = (int)($firstPayload['token_cost'] ?? 0);
        $tokenConsumed = (bool)($firstPayload['token_consumed'] ?? false);
        
        if ($tokenCost > 0 && !$tokenConsumed) {
            // Consume the tokens
            for ($i = 0; $i < $tokenCost; $i++) {
                ai_consume_call($tid);
            }
            
            // Update all suggestions in this batch to mark tokens as consumed
            $batchId = $firstSuggestion['batch_id'];
            if ($batchId) {
                $stmtBatch = db()->prepare("SELECT id, suggestion_payload_json FROM concierge_ai_suggestions WHERE tenant_id = :tid AND batch_id = :batch");
                $stmtBatch->execute([':tid' => $tid, ':batch' => $batchId]);
                $batchSuggestions = $stmtBatch->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($batchSuggestions as $bsug) {
                    $bsugPayload = ai_groups_decode_json($bsug['suggestion_payload_json'] ?? null);
                    $bsugPayload['token_consumed'] = true;
                    $stmtUpdatePayload = db()->prepare("UPDATE concierge_ai_suggestions SET suggestion_payload_json = :payload WHERE id = :id");
                    $stmtUpdatePayload->execute([
                        ':payload' => json_encode($bsugPayload, JSON_UNESCAPED_UNICODE),
                        ':id' => (int)$bsug['id']
                    ]);
                }
            }
        }

        $instanceNumber = preg_replace('/[^0-9]/', '', (string)ai_get_setting('ai_whatsapp_number', '', $tid));
        if ($instanceNumber === '') $instanceNumber = '5511999999999';

        $firstPayload = ai_groups_decode_json($suggestions[0]['suggestion_payload_json'] ?? null);
        $firstProductId = (int)($suggestions[0]['product_id'] ?? 0);
        $firstProduct = concierge_suggestions_hydrate_product($tid, $firstProductId, $firstPayload['product'] ?? []);
        $productId = (int)($firstProduct['id'] ?? $firstProductId);
        if ($productId <= 0) {
            throw new Exception('Produto da sugestão inválido.');
        }

        $groupIdsRaw = $firstPayload['group_ids'] ?? $firstPayload['grupos_ids'] ?? [];
        $groupIds = is_array($groupIdsRaw) ? array_map('intval', $groupIdsRaw) : [];
        if (empty($groupIds)) {
            $activeGroups = ai_get_concierge_groups($tid, true);
            $groupIds = array_values(array_filter(array_map('intval', array_column($activeGroups, 'id'))));
        }
        $groupIds = ai_groups_sanitize_group_ids($tid, $groupIds);

        $mediaUrls = [];
        $individualMessages = [];
        $individualLinks = [];
        $welcomeMessage = '';
        $ctaText = '';
        $approvedSuggestionIds = [];
        foreach ($suggestions as $sug) {
            $sid = (int)($sug['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $approvedSuggestionIds[] = $sid;
            $payload = ai_groups_decode_json($sug['suggestion_payload_json'] ?? null);
            $pid = (int)($sug['product_id'] ?? 0);
            $product = concierge_suggestions_hydrate_product($tid, $pid, $payload['product'] ?? []);
            $override = [];
            if (isset($overrides[$sid]) && is_array($overrides[$sid])) {
                $override = $overrides[$sid];
            } elseif (isset($overrides[(string)$sid]) && is_array($overrides[(string)$sid])) {
                $override = $overrides[(string)$sid];
            }
            $overrideProductName = trim((string)($override['product_name'] ?? ''));
            if ($overrideProductName !== '') {
                $product['nome'] = $overrideProductName;
                $product['name'] = $overrideProductName;
            }
            $sku = trim((string)($product['sku'] ?? ''));

            $cardText = trim((string)($override['texto_card'] ?? $payload['texto_card'] ?? $payload['card_text'] ?? ''));
            if ($cardText === '') {
                $name = trim((string)($product['nome'] ?? $product['name'] ?? 'Produto'));
                $price = (float)($product['preco'] ?? $product['price'] ?? 0);
                $cardText = '✨ ' . ($name !== '' ? $name : 'Produto') . "\n\n" . 'Disponível por R$ ' . number_format($price, 2, ',', '.') . '.';
            }
            $individualMessages[] = $cardText;

            $itemCta = trim((string)($override['cta'] ?? $payload['cta'] ?? ''));
            if ($ctaText === '' && $itemCta !== '') $ctaText = $itemCta;
            $linkCtaText = $itemCta !== '' ? $itemCta : ($ctaText !== '' ? $ctaText : concierge_suggestions_default_cta_text());
            $individualLinks[] = concierge_suggestions_build_whatsapp_link($instanceNumber, $linkCtaText, $sku);

            $welcome = trim((string)($override['texto_boas_vindas'] ?? $payload['texto_boas_vindas'] ?? $payload['welcome_text'] ?? ''));
            if ($welcomeMessage === '' && $welcome !== '') $welcomeMessage = $welcome;

            $productMedia = concierge_suggestions_get_product_media_urls($product, $tid);
            $mainMedia = trim((string)($productMedia[0] ?? $product['media_url'] ?? $product['image'] ?? ''));
            if ($mainMedia !== '' && !in_array($mainMedia, $mediaUrls, true)) $mediaUrls[] = $mainMedia;
        }

        $mediaUrls = array_slice(array_values($mediaUrls), 0, 4);
        if (empty($mediaUrls)) {
            $mediaUrls = array_slice(array_values(array_unique(array_filter(concierge_suggestions_get_product_media_urls($firstProduct, $tid)))), 0, 4);
        }
        $mediaUrl = trim((string)($mediaUrls[0] ?? $firstProduct['media_url'] ?? $firstProduct['image'] ?? ''));
        if ($ctaText === '') $ctaText = concierge_suggestions_default_cta_text();
        $firstApprovedId = !empty($approvedSuggestionIds) ? (int)$approvedSuggestionIds[0] : 0;
        $firstOverride = [];
        if ($firstApprovedId > 0) {
            if (isset($overrides[$firstApprovedId]) && is_array($overrides[$firstApprovedId])) {
                $firstOverride = $overrides[$firstApprovedId];
            } elseif (isset($overrides[(string)$firstApprovedId]) && is_array($overrides[(string)$firstApprovedId])) {
                $firstOverride = $overrides[(string)$firstApprovedId];
            }
            $firstOverrideProductName = trim((string)($firstOverride['product_name'] ?? ''));
            if ($firstOverrideProductName !== '') {
                $firstProduct['nome'] = $firstOverrideProductName;
                $firstProduct['name'] = $firstOverrideProductName;
            }
        }

        $title = trim((string)($firstProduct['nome'] ?? $firstProduct['name'] ?? 'Campanha IA'));
        if ($title === '') $title = 'Campanha IA';
        if (count($individualMessages) > 1) $title .= ' · Carrossel';

        $content = trim((string)($individualMessages[0] ?? ($firstPayload['texto_card'] ?? $firstPayload['card_text'] ?? '')));
        if ($content === '') $content = 'Conteúdo não informado.';

        $campaignPayloadJson = [
            'welcome_message' => $welcomeMessage,
            'cta' => concierge_suggestions_detect_cta_key($ctaText),
            'cta_text' => $ctaText,
            'msg_mode' => count($mediaUrls) > 1 ? 'individual' : 'single',
            'individual_messages' => $individualMessages,
            'individual_links' => $individualLinks,
            'group_ids' => $groupIds,
            'media_urls' => $mediaUrls,
            'product' => $firstProduct,
            'source' => 'suggestion_accept_group',
        ];

        $campaignId = ai_create_concierge_campaign($tid, [
            'title' => $title,
            'content' => $content,
            'status' => 'pending',
            'product_id' => $productId,
            'media_url' => $mediaUrl,
            'group_ids' => $groupIds,
            'created_by' => (int)(function_exists('user_id') ? user_id() : 0),
            'payload_json' => $campaignPayloadJson,
        ]);
        if ($campaignId <= 0) {
            throw new Exception('Não foi possível criar a campanha da sugestão aprovada.');
        }

        if (!empty($groupIds)) {
            ai_upsert_campaign_broadcast_targets($tid, $campaignId, $groupIds);
        }

        if (!empty($approvedSuggestionIds)) {
            $approvedPh = implode(',', array_fill(0, count($approvedSuggestionIds), '?'));
            $stmtUpdate = db()->prepare("UPDATE concierge_ai_suggestions SET status = 'accepted', resolved_at = NOW() WHERE tenant_id = ? AND id IN ($approvedPh)");
            $stmtUpdate->execute(array_merge([$tid], $approvedSuggestionIds));
        }
        $effectiveRejectIds = array_values(array_unique(array_filter(array_map('intval', $rejectIds), function ($id) use ($approvedSuggestionIds) {
            return $id > 0 && !in_array($id, $approvedSuggestionIds, true);
        })));
        if (!empty($effectiveRejectIds)) {
            $rejectPh = implode(',', array_fill(0, count($effectiveRejectIds), '?'));
            $stmtReject = db()->prepare("UPDATE concierge_ai_suggestions SET status = 'rejected', resolved_at = NOW() WHERE tenant_id = ? AND status = 'pending' AND id IN ($rejectPh)");
            $stmtReject->execute(array_merge([$tid], $effectiveRejectIds));
        }

        $campaign = ai_get_concierge_campaign($tid, $campaignId);
        echo json_encode([
            'ok' => true,
            'campaign_id' => $campaignId,
            'campaign' => $campaign,
            'rejected_ids' => $effectiveRejectIds,
        ]);
        exit;
    } elseif ($action === 'reject' || $action === 'reject_group') {
        $suggestionIds = concierge_suggestions_parse_ids($_POST['suggestion_ids'] ?? ($_POST['suggestion_id'] ?? ''));
        if (empty($suggestionIds)) {
            throw new Exception('ID da sugestão inválido.');
        }
        $ph = implode(',', array_fill(0, count($suggestionIds), '?'));
        $stmt = db()->prepare("SELECT id FROM concierge_ai_suggestions WHERE tenant_id = ? AND status = 'pending' AND id IN ($ph) LIMIT 1");
        $stmt->execute(array_merge([$tid], $suggestionIds));
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exists) {
            throw new Exception('Sugestão não encontrada.');
        }
        $stmtUpdate = db()->prepare("UPDATE concierge_ai_suggestions SET status = 'rejected', resolved_at = NOW() WHERE tenant_id = ? AND id IN ($ph)");
        $stmtUpdate->execute(array_merge([$tid], $suggestionIds));
        echo json_encode(['ok' => true]);
        exit;
    } else {
        throw new Exception('Ação inválida.');
    }
} catch (Throwable $e) {
    if ($action === 'generate' && $shouldResetProcessingOnError && !$webhookDispatchAttempted) {
        concierge_set_suggestions_processing_state($tid, [
            'is_processing' => false,
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => null,
            'last_batch_id' => null,
            'used_this_generation' => 0,
        ]);
    }
    http_response_code(422);
    echo json_encode(['errorMsg' => $e->getMessage()]);
}
?>