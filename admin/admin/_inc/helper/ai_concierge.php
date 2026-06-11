<?php
/**
 * Helper: ai_concierge.php
 * Funções de consulta e manipulação do banco de dados — Módulo Moda IA
 */

// ─────────────────────────────────────────────────────────────────────────────
// TENANT HELPER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna o tenant_id ativo na sessão (= store_id do ModernPOS).
 */
function ai_tenant_id(): int
{
    return (int)(
        (function_exists('store_id') ? store_id() : null)
        ?? ($_SESSION['store_id'] ?? 1)
    );
}

/**
 * Resolve o tenant_id real usado pelo FileManager (SaaS), mantendo fallback seguro.
 * O módulo Moda IA usa store_id internamente, mas o FileManager isola arquivos por tenant_id.
 */
function ai_resolve_filemanager_tenant_id(int $store_or_tenant_id = 0): int
{
    if (!empty($_SESSION['tenant_id'])) {
        return (int)$_SESSION['tenant_id'];
    }
    // Mesma ordem do FileManager: tenta resolver pelo usuário autenticado.
    $uid = 0;
    if (function_exists('user_id')) {
        $uid = (int) user_id();
    }
    if ($uid <= 0 && !empty($_SESSION['id'])) {
        $uid = (int) $_SESSION['id'];
    }

    if ($uid > 0) {
        try {
            $stmt = db()->prepare("SELECT tenant_id FROM users WHERE id = :uid LIMIT 1");
            $stmt->execute([':uid' => $uid]);
            $tenant = (int) $stmt->fetchColumn();
            if ($tenant > 0) {
                $_SESSION['tenant_id'] = $tenant;
                return $tenant;
            }
        } catch (Exception $e) {
            // fallback abaixo
        }
    }

    // Fallback de compatibilidade: resolver tenant via store ativa.
    $sid = $store_or_tenant_id > 0 ? $store_or_tenant_id : ai_tenant_id();
    if ($sid > 0) {
        try {
            $stmt = db()->prepare("SELECT tenant_id FROM stores WHERE store_id = :sid LIMIT 1");
            $stmt->execute([':sid' => $sid]);
            $tenant = (int) $stmt->fetchColumn();
            if ($tenant > 0) {
                $_SESSION['tenant_id'] = $tenant;
                return $tenant;
            }
        } catch (Exception $e) {
            // fallback abaixo
        }
    }

    // Single-tenant/local: usa a raiz do FileManager (sem pasta por tenant).
    return 0;
}

/**
 * Caminho base de storage do FileManager.
 */
function ai_storage_products_base_path(): string
{
    $base = (defined('FILEMANAGERPATH') && FILEMANAGERPATH)
        ? FILEMANAGERPATH
        : (ROOT . '/storage/products/');
    return rtrim($base, "/\\");
}

/**
 * Retorna o diretório de mídia do Catálogo IA dentro do FileManager.
 * Ex: storage/products/{tenant_id}/MODA AI
 */
function ai_get_catalog_media_dir(int $store_or_tenant_id = 0): string
{
    $basePath = ai_storage_products_base_path();
    $tenantId = ai_resolve_filemanager_tenant_id($store_or_tenant_id);

    if ($tenantId > 0) {
        return $basePath . DIRECTORY_SEPARATOR . $tenantId . DIRECTORY_SEPARATOR . 'MODA AI';
    }

    return $basePath . DIRECTORY_SEPARATOR . 'MODA AI';
}

/**
 * Normaliza um caminho absoluto de storage e converte para relativo a DIR_STORAGE.
 */
function ai_storage_relative_path(string $absolutePath): string
{
    $absolute = str_replace('\\', '/', $absolutePath);
    $storage  = rtrim(str_replace('\\', '/', DIR_STORAGE), '/') . '/';

    if (strpos($absolute, $storage) === 0) {
        return ltrim(substr($absolute, strlen($storage)), '/');
    }

    return ltrim($absolute, '/');
}

/**
 * Resolve caminho absoluto em DIR_STORAGE para um caminho relativo.
 */
function ai_storage_absolute_path(string $relativePath): string
{
    $relative = ltrim(str_replace('\\', '/', $relativePath), '/');
    return rtrim(str_replace('\\', '/', DIR_STORAGE), '/') . '/' . $relative;
}

/**
 * Converte um caminho relativo do storage em uma URL absoluta acessível pelo navegador.
 */
function ai_resolve_storage_url(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }
    if (
        strpos($path, 'http://') === 0
        || strpos($path, 'https://') === 0
        || strpos($path, 'data:') === 0
        || strpos($path, '//') === 0
    ) {
        return $path;
    }

    // Se o caminho começar com o caminho base do site (ex: /modernpos/), removemos
    $rootUrl = (string)root_url();
    $parsedUrl = parse_url($rootUrl);
    $basePath = rtrim($parsedUrl['path'] ?? '', '/');
    
    if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
        $path = substr($path, strlen($basePath));
    }

    // Se já tiver /storage/ no início, não duplicamos
    $path = ltrim($path, '/');
    if (strpos($path, 'storage/') === 0) {
        $path = substr($path, 8);
    }

    return rtrim($rootUrl, '/') . '/storage/' . ltrim($path, '/');
}

/**
 * Calcula uso de storage (bytes) com a mesma regra do FileManager:
 * varredura real de arquivos em storage/products/{tenant_id}.
 */
function ai_get_storage_usage_bytes(int $store_or_tenant_id = 0): int
{
    $tenantId = ai_resolve_filemanager_tenant_id($store_or_tenant_id);
    $basePath = ai_storage_products_base_path();
    $scanPath = $tenantId > 0
        ? $basePath . DIRECTORY_SEPARATOR . $tenantId
        : $basePath;

    if (!is_dir($scanPath)) {
        return 0;
    }

    $usedBytes = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->isFile()) {
                $usedBytes += $file->getSize();
            }
        }
    } catch (Exception $e) {
        return 0;
    }

    return $usedBytes;
}

/**
 * Calcula uso de storage (MB) com a mesma regra do FileManager.
 */
function ai_get_storage_usage_mb(int $store_or_tenant_id = 0): float
{
    return round(ai_get_storage_usage_bytes($store_or_tenant_id) / (1024 * 1024), 2);
}

/**
 * Retorna o limite de storage (MB) do tenant corrente.
 * 0 = ilimitado ou indisponível.
 */
function ai_get_storage_limit_mb(int $store_or_tenant_id = 0): int
{
    $tenantId = ai_resolve_filemanager_tenant_id($store_or_tenant_id);
    if ($tenantId <= 0) {
        return 0;
    }

    try {
        $checkCol = db()->query("SHOW COLUMNS FROM plans LIKE 'storage_mb'");
        if (!$checkCol || $checkCol->rowCount() === 0) {
            return 0;
        }

        $stmt = db()->prepare("
            SELECT p.storage_mb
            FROM tenants t
            LEFT JOIN plans p ON t.plan_id = p.plan_id
            WHERE t.tenant_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tenantId]);
        return max(0, (int) $stmt->fetchColumn());
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Verifica se há espaço para novo upload considerando possível substituição.
 */
function ai_check_storage_capacity(int $incomingBytes, int $store_or_tenant_id = 0, int $replaceBytes = 0): array
{
    $incomingBytes = max(0, $incomingBytes);
    $replaceBytes  = max(0, $replaceBytes);

    $usedBytes  = ai_get_storage_usage_bytes($store_or_tenant_id);
    $limitMb    = ai_get_storage_limit_mb($store_or_tenant_id);
    $limitBytes = $limitMb > 0 ? ($limitMb * 1024 * 1024) : 0;

    if ($limitBytes <= 0) {
        return [
            'ok'         => true,
            'used_bytes' => $usedBytes,
            'limit_mb'   => 0,
            'unlimited'  => true,
        ];
    }

    $projectedBytes = max(0, $usedBytes - $replaceBytes) + $incomingBytes;
    if ($projectedBytes > $limitBytes) {
        $usedMb = round($usedBytes / (1024 * 1024), 2);
        return [
            'ok'          => false,
            'used_bytes'  => $usedBytes,
            'used_mb'     => $usedMb,
            'limit_mb'    => $limitMb,
            'incoming_mb' => round($incomingBytes / (1024 * 1024), 2),
            'unlimited'   => false,
            'message'     => "Armazenamento cheio! Você está usando {$usedMb} MB de {$limitMb} MB. Exclua arquivos ou faça upgrade do plano.",
        ];
    }

    return [
        'ok'          => true,
        'used_bytes'  => $usedBytes,
        'limit_mb'    => $limitMb,
        'incoming_mb' => round($incomingBytes / (1024 * 1024), 2),
        'unlimited'   => false,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// CATÁLOGO — MODELOS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna lista de modelos do catálogo IA da loja atual.
 *
 * @param  array $filters ['active' => 0|1|null, 'search' => string, 'order' => 'demand|name|created']
 * @return array
 */
function ai_get_catalogo_models(array $filters = []): array
{
    $tid    = ai_tenant_id();
    $where  = ['m.tenant_id = :tid'];
    $params = [':tid' => $tid];

    if (isset($filters['active']) && $filters['active'] !== null) {
        $where[]           = 'm.is_active = :active';
        $params[':active'] = (int)$filters['active'];
    }

    if (!empty($filters['search'])) {
        $where[]            = '(m.name LIKE :s1 OR m.tags LIKE :s2)';
        $params[':s1']      = '%' . $filters['search'] . '%';
        $params[':s2']      = '%' . $filters['search'] . '%';
    }

    $order = match ($filters['order'] ?? 'demand') {
        'name'    => 'm.name ASC',
        'created' => 'm.created_at DESC',
        default   => 'm.demand_count DESC, m.created_at DESC',
    };

    $sql = "
        SELECT m.*,
               COUNT(v.id)                          AS variant_count,
               COUNT(DISTINCT v.color)              AS color_count,
               COALESCE(SUM(v.stock_qty), 0)        AS total_stock,
               COALESCE(MIN(v.price), 0)            AS min_price,
               COALESCE(MAX(v.price), 0)            AS max_price
        FROM   ai_catalogo_models m
        LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
        WHERE  " . implode(' AND ', $where) . "
        GROUP BY m.id
        ORDER BY {$order}
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retorna um modelo pelo ID (verificando tenant).
 */
function ai_get_catalogo_model(int $id): ?array
{
    $tid  = ai_tenant_id();
    $stmt = db()->prepare(
        "SELECT m.*,
                COALESCE(SUM(v.stock_qty), 0) AS total_stock,
                COUNT(v.id) AS variant_count,
                COALESCE(MIN(v.price), 0) AS min_price,
                COALESCE(MAX(v.price), 0) AS max_price
         FROM   ai_catalogo_models m
         LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
         WHERE  m.id = :id AND m.tenant_id = :tid
         GROUP BY m.id"
    );
    $stmt->execute([':id' => $id, ':tid' => $tid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Busca todas as configurações de IA para o tenant atual.
 */
function ai_get_settings(int $tenant_id = 0): array
{
    $tid = $tenant_id ?: ai_tenant_id();
    $stmt = db()->prepare("SELECT key_name, value FROM ai_settings WHERE tenant_id = :tid");
    $stmt->execute([':tid' => $tid]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Retorna uma configuração específica.
 */
function ai_get_setting(string $key, $default = null, int $tenant_id = 0)
{
    $settings = ai_get_settings($tenant_id);
    return $settings[$key] ?? $default;
}

/**
 * Salva uma configuração de IA.
 */
function ai_save_setting(string $key, $value, int $tenant_id = 0): bool
{
    $tid = $tenant_id ?: ai_tenant_id();
    $stmt = db()->prepare("
        INSERT INTO ai_settings (tenant_id, key_name, value) 
        VALUES (:tid, :key, :val)
        ON DUPLICATE KEY UPDATE value = :val2
    ");
    if (!$stmt) {
        return false;
    }
    return (bool) $stmt->execute([
        ':tid' => $tid,
        ':key' => $key,
        ':val' => $value,
        ':val2' => $value,
    ]);
}

function ai_get_store_name(int $tenant_id = 0): string
{
    $tid = $tenant_id ?: ai_tenant_id();
    try {
        $st = db()->prepare("SELECT `name` FROM `stores` WHERE `store_id` = :tid LIMIT 1");
        $st->execute([':tid' => $tid]);
        $name = (string)$st->fetchColumn();
        return trim($name);
    } catch (Exception $e) {
        return '';
    }
}

function ai_apply_template_vars(string $text, array $vars): string
{
    if ($text === '' || empty($vars)) return $text;
    return strtr($text, $vars);
}

function ai_get_schedule(int $tenant_id = 0): array
{
    $tid = $tenant_id ?: ai_tenant_id();
    $raw = (string)ai_get_setting('ai_schedule_json', '', $tid);
    
    // Decodifica entidades HTML se houver (o Request do ModernPOS escapa tudo)
    if (strpos($raw, '&quot;') !== false) {
        $raw = html_entity_decode($raw, ENT_COMPAT, 'UTF-8');
    }
    
    $decoded = $raw !== '' ? json_decode($raw, true) : null;

    if (!is_array($decoded)) {
        return [
            'mon' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'all_day' => false],
            'tue' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'all_day' => false],
            'wed' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'all_day' => false],
            'thu' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'all_day' => false],
            'fri' => ['enabled' => true, 'start' => '09:00', 'end' => '18:00', 'all_day' => false],
            'sat' => ['enabled' => true, 'start' => '09:00', 'end' => '13:00', 'all_day' => false],
            'sun' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00', 'all_day' => false],
        ];
    }

    $out = [];
    foreach (['mon','tue','wed','thu','fri','sat','sun'] as $k) {
        $d = is_array($decoded[$k] ?? null) ? $decoded[$k] : [];
        $enabled = (string)($d['enabled'] ?? '1');
        $allDay = !empty($d['all_day']);
        $out[$k] = [
            'enabled' => $enabled === '1' || $enabled === 'true' || $enabled === 'on' || $enabled === 'yes' || $enabled === 'enabled',
            'all_day' => $allDay,
            'start' => (string)($d['start'] ?? '09:00'),
            'end' => (string)($d['end'] ?? '18:00'),
        ];
    }

    return $out;
}

function ai_is_within_schedule(int $tenant_id = 0, ?DateTime $now = null): bool
{
    $tid = $tenant_id ?: ai_tenant_id();
    
    // Se o modo 24h estiver ativo, ignora o cronograma
    $is24hMode = (string)ai_get_setting('ai_24h_mode', '0', $tid) === '1';
    if ($is24hMode) {
        return true;
    }

    $now = $now ?: new DateTime('now');
    $schedule = ai_get_schedule($tid);

    $dow = (int)$now->format('N');
    $key = $dow === 1 ? 'mon' : ($dow === 2 ? 'tue' : ($dow === 3 ? 'wed' : ($dow === 4 ? 'thu' : ($dow === 5 ? 'fri' : ($dow === 6 ? 'sat' : 'sun')))));

    $day = $schedule[$key] ?? null;
    if (!is_array($day) || empty($day['enabled'])) {
        return false;
    }

    // Se o dia específico estiver configurado como 24h
    if (!empty($day['all_day'])) {
        return true;
    }

    $start = (string)($day['start'] ?? '00:00');
    $end = (string)($day['end'] ?? '23:59');
    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
        return true;
    }

    $curMin = ((int)$now->format('H')) * 60 + (int)$now->format('i');
    $sMin = ((int)substr($start, 0, 2)) * 60 + (int)substr($start, 3, 2);
    $eMin = ((int)substr($end, 0, 2)) * 60 + (int)substr($end, 3, 2);

    if ($sMin === $eMin) return true;
    if ($sMin < $eMin) {
        return $curMin >= $sMin && $curMin <= $eMin;
    }

    return $curMin >= $sMin || $curMin <= $eMin;
}

/**
 * Conta quantas variantes (SKUs) ativas a loja tem no catálogo IA.
 */
function ai_count_catalog(int $tenant_id = 0): int
{
    $tid  = $tenant_id ?: ai_tenant_id();
    // Agora contamos as variantes (SKUs) em vez dos modelos
    $stmt = db()->prepare("
        SELECT COUNT(v.id) 
        FROM ai_catalogo_variants v
        JOIN ai_catalogo_models m ON v.model_id = m.id
        WHERE v.tenant_id = :tid AND m.is_active = 1 AND v.is_active = 1
    ");
    $stmt->execute([':tid' => $tid]);
    return (int)$stmt->fetchColumn();
}

/**
 * Retorna as categorias do catálogo IA da loja (utilizando a tabela nativa categorys).
 */
function ai_get_catalogo_categories(): array
{
    $tid = ai_tenant_id();
    $stmt = db()->prepare("SELECT c.category_id as id, c.category_name as name 
                           FROM categorys c
                           INNER JOIN category_to_store c2s ON c.category_id = c2s.ccategory_id
                           WHERE c2s.store_id = :tid AND c2s.status = 1
                           ORDER BY c.category_name ASC");
    $stmt->execute([':tid' => $tid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as &$row) {
        // Categorias que começam com [Global] ou ID 1 são consideradas protegidas
        $row['is_global'] = (strpos($row['name'], '[Global]') === 0 || $row['id'] == 1);
    }
    
    return $rows;
}

// ─────────────────────────────────────────────────────────────────────────────
// CATÁLOGO — VARIANTES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna variantes de um modelo.
 */
function ai_get_catalogo_variants(int $model_id): array
{
    $tid  = ai_tenant_id();
    $stmt = db()->prepare(
        "SELECT * FROM ai_catalogo_variants
         WHERE model_id = :mid AND tenant_id = :tid
         ORDER BY color, size"
    );
    $stmt->execute([':mid' => $model_id, ':tid' => $tid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retorna variantes disponíveis (stock_qty > 0) para consulta da IA.
 */
function ai_get_variants_available(int $model_id): array
{
    $tid  = ai_tenant_id();
    $stmt = db()->prepare(
        "SELECT * FROM ai_catalogo_variants
         WHERE model_id = :mid AND tenant_id = :tid AND stock_qty > 0 AND is_active = 1
         ORDER BY color, size"
    );
    $stmt->execute([':mid' => $model_id, ':tid' => $tid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Incrementa demand_count de um modelo (chamado quando IA consulta o catálogo).
 */
function ai_increment_demand(int $model_id, int $tenant_id = 0): void
{
    $tid = $tenant_id ?: ai_tenant_id();
    db()->prepare(
        "UPDATE ai_catalogo_models SET demand_count = demand_count + 1
         WHERE id = :id AND tenant_id = :tid"
    )->execute([':id' => $model_id, ':tid' => $tid]);
}

// ─────────────────────────────────────────────────────────────────────────────
// PEDIDOS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna pedidos agrupados por status (para o Kanban).
 *
 * @param  array $filters ['status' => string|null, 'date_from' => string, 'date_to' => string, 'search' => string]
 * @return array Indexado por status: ['pendente' => [...], 'pago' => [...], ...]
 */
function ai_get_orders_by_status(array $filters = []): array
{
    $tid    = ai_tenant_id();
    $where  = ['o.tenant_id = :tid'];
    $params = [':tid' => $tid];

    if (!empty($filters['status'])) {
        $where[]           = 'o.status = :status';
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $where[]              = 'DATE(o.created_at) >= :dfrom';
        $params[':dfrom']     = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[]            = 'DATE(o.created_at) <= :dto';
        $params[':dto']     = $filters['date_to'];
    }
    if (!empty($filters['search'])) {
        $where[]            = '(o.customer_name LIKE :s1 OR o.whatsapp_phone LIKE :s2 OR o.id = :sid)';
        $params[':s1']      = '%' . $filters['search'] . '%';
        $params[':s2']      = '%' . $filters['search'] . '%';
        $params[':sid']     = (int)$filters['search'];
    }

    $stmt = db()->prepare("
        SELECT o.*, p.name AS profile_name,
               GROUP_CONCAT(
                   CONCAT(oi.model_name, '|', oi.color, '|', oi.size, '|', oi.qty, '|', oi.unit_price)
                   ORDER BY oi.id SEPARATOR ';;'
               ) AS items_raw
        FROM   ai_orders o
        LEFT JOIN ai_order_items oi ON oi.order_id = o.id
        LEFT JOIN ai_chat_profiles p ON p.tenant_id = o.tenant_id AND p.whatsapp_phone = o.whatsapp_phone
        WHERE  " . implode(' AND ', $where) . "
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parsear items_raw em array
    foreach ($rows as &$row) {
        $row['items'] = [];
        if (!empty($row['items_raw'])) {
            foreach (explode(';;', $row['items_raw']) as $raw) {
                $parts = explode('|', $raw);
                $row['items'][] = [
                    'model_name' => $parts[0] ?? '',
                    'color'      => $parts[1] ?? '',
                    'size'       => $parts[2] ?? '',
                    'qty'        => (int)($parts[3] ?? 1),
                    'unit_price' => (float)($parts[4] ?? 0),
                ];
            }
        }
        unset($row['items_raw']);
    }
    unset($row);

    // Agrupar por status
    $grouped = ['pendente' => [], 'pago' => [], 'separando' => [], 'rota' => [], 'entregue' => [], 'cancelado' => []];
    foreach ($rows as $row) {
        $s = $row['status'];
        if (isset($grouped[$s])) {
            $grouped[$s][] = $row;
        }
    }
    return $grouped;
}

/**
 * Retorna um pedido completo pelo ID.
 */
function ai_get_order(int $id, int $tenant_id = 0): ?array
{
    $tid  = $tenant_id ?: ai_tenant_id();
    $stmt = db()->prepare(
        "SELECT o.*, p.name AS profile_name, p.usual_size AS profile_size, p.preferences_json
         FROM   ai_orders o
         LEFT JOIN ai_chat_profiles p ON p.tenant_id = o.tenant_id AND p.whatsapp_phone = o.whatsapp_phone
         WHERE  o.id = :id AND o.tenant_id = :tid"
    );
    $stmt->execute([':id' => $id, ':tid' => $tid]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return null;

    // Itens do pedido
    $stmt2 = db()->prepare(
        "SELECT oi.*, v.photo_webp FROM ai_order_items oi
         LEFT JOIN ai_catalogo_variants v ON v.id = oi.variant_id
         WHERE oi.order_id = :oid ORDER BY oi.id"
    );
    $stmt2->execute([':oid' => $id]);
    $order['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    return $order;
}

/**
 * Conta pedidos pendentes/pagos para o badge do menu.
 */
function ai_count_pending_orders(int $tenant_id = 0): int
{
    $tid  = $tenant_id ?: ai_tenant_id();
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM ai_orders
         WHERE tenant_id = :tid AND status IN ('pendente','pago')"
    );
    $stmt->execute([':tid' => $tid]);
    return (int)$stmt->fetchColumn();
}

/**
 * Atualiza o status de um pedido.
 *
 * @param  int    $id         ID do pedido
 * @param  string $newStatus  Novo status
 * @param  bool   $by_ia      true se o movimento foi feito pela IA
 * @param  int    $tenant_id  ID da loja (opcional se houver sessão)
 * @return bool
 */
function ai_update_order_status(int $id, string $newStatus, bool $by_ia = false, int $tenant_id = 0): bool
{
    $tid     = $tenant_id ?: ai_tenant_id();
    $allowed = ['pendente', 'pago', 'separando', 'rota', 'entregue', 'cancelado'];
    if (!in_array($newStatus, $allowed)) return false;

    $extra = '';
    if ($newStatus === 'pago') {
        $extra = ", paid_at = NOW()";
    } elseif ($newStatus === 'entregue') {
        $extra = ", delivered_at = NOW()";
    }

    $stmt = db()->prepare(
        "UPDATE ai_orders
         SET    status = :status, moved_by_ia = :ia{$extra}, updated_at = NOW()
         WHERE  id = :id AND tenant_id = :tid"
    );
    $stmt->execute([
        ':status' => $newStatus,
        ':ia'     => $by_ia ? 1 : 0,
        ':id'     => $id,
        ':tid'    => $tid,
    ]);
    
    // rowCount() pode ser 0 se o status já for o mesmo, mas como estamos atualizando updated_at = NOW(), 
    // ele deve ser 1. Se mesmo assim for 0, verificamos se o pedido existe.
    if ($stmt->rowCount() > 0) {
        return true;
    }
    
    // Fallback: Verificar se o pedido existe com esse status
    $check = db()->prepare("SELECT COUNT(*) FROM ai_orders WHERE id = :id AND tenant_id = :tid AND status = :status");
    $check->execute([':id' => $id, ':tid' => $tid, ':status' => $newStatus]);
    return (int)$check->fetchColumn() > 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// PLANOS E USO (SAAS BRIDGE)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna o plano ativo da loja (busca na tabela plans via saas_limits).
 * Lê de saas/includes/SaasLimitsBridge ou diretamente da tabela plans.
 *
 * @param  int $tenant_id
 * @return array|null
 */
function ai_get_active_plan(int $tenant_id = 0): ?array
{
    if (!$tenant_id) {
        $tenant_id = ai_tenant_id();
    }

    // Tentar via bridge do SAAS (se disponível)
    $bridge_file = realpath(__DIR__ . '/../../saas/includes/SaasLimitsBridge.php');
    if ($bridge_file && file_exists($bridge_file)) {
        // SAAS bridge disponível — delegar
        // (para futuro: instanciar a bridge aqui)
    }

    // Fallback: busca direto na tabela plans via store -> tenant -> plan
    try {
        $stmt = db()->prepare("
            SELECT p.* FROM plans p
            INNER JOIN tenants t ON t.plan_id = p.plan_id
            INNER JOIN stores s ON s.tenant_id = t.tenant_id
            WHERE s.store_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tenant_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        return $plan ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Retorna o uso do mês atual para a loja.
 *
 * @param  int    $tenant_id
 * @param  string $year_month  'YYYY-MM' (padrão: mês atual)
 * @return array  ['webhook_calls' => int, 'storage_mb_used' => float]
 */
function ai_get_usage(int $tenant_id = 0, string $year_month = ''): array
{
    if (!$tenant_id) $tenant_id = ai_tenant_id();
    if (!$year_month) $year_month = date('Y-m');

    $stmt = db()->prepare(
        "SELECT * FROM ai_usage_log WHERE tenant_id = :tid AND `year_month` = :ym LIMIT 1"
    );
    $stmt->execute([':tid' => $tenant_id, ':ym' => $year_month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'webhook_calls'    => (int)($row['webhook_calls'] ?? 0),
        // Storage alinhado com a mesma regra do FileManager (filesystem por tenant).
        'storage_mb_used'  => ai_get_storage_usage_mb($tenant_id),
    ];
}

/**
 * Incrementa o contador de chamadas ao webhook para o mês atual.
 */
function ai_increment_webhook_call(int $tenant_id = 0): void
{
    if (!$tenant_id) $tenant_id = ai_tenant_id();
    $ym = date('Y-m');

    db()->prepare("
        INSERT INTO ai_usage_log (tenant_id, `year_month`, webhook_calls)
        VALUES (:tid, :ym, 1)
        ON DUPLICATE KEY UPDATE webhook_calls = webhook_calls + 1
    ")->execute([':tid' => $tenant_id, ':ym' => $ym]);
}

/**
 * Adiciona MB de storage usado após upload de foto.
 */
function ai_add_storage_usage(float $mb, int $tenant_id = 0): void
{
    if (!$tenant_id) $tenant_id = ai_tenant_id();
    $ym = date('Y-m');

    db()->prepare("
        INSERT INTO ai_usage_log (tenant_id, `year_month`, storage_mb_used)
        VALUES (:tid, :ym, :mb)
        ON DUPLICATE KEY UPDATE storage_mb_used = storage_mb_used + :mb2
    ")->execute([':tid' => $tenant_id, ':ym' => $ym, ':mb' => $mb, ':mb2' => $mb]);
}

// ─────────────────────────────────────────────────────────────────────────────
// ESTOQUE IA
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna resumo do estoque do catálogo IA para a tela de Gestão de Estoque.
 */
function ai_get_stock_summary(): array
{
    $tid  = ai_tenant_id();
    $stmt = db()->prepare("
        SELECT
            COUNT(*)                              AS total_skus,
            SUM(CASE WHEN stock_qty = 0 THEN 1 ELSE 0 END)  AS zerados,
            SUM(CASE WHEN stock_qty > 0 AND stock_qty <= 3 THEN 1 ELSE 0 END) AS criticos,
            COALESCE(SUM(stock_qty * price), 0)   AS valor_total
        FROM ai_catalogo_variants
        WHERE tenant_id = :tid AND is_active = 1
    ");
    $stmt->execute([':tid' => $tid]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_skus'  => 0,
        'zerados'     => 0,
        'criticos'    => 0,
        'valor_total' => 0,
    ];
}

/**
 * Retorna variantes para a tela de estoque com info do modelo e demanda.
 */
function ai_get_stock_variants(array $filters = []): array
{
    $tid    = ai_tenant_id();
    $where  = ['v.tenant_id = :tid'];
    $params = [':tid' => $tid];

    if (isset($filters['status'])) {
        if ($filters['status'] === 'zerado') {
            $where[] = 'v.stock_qty = 0';
        } elseif ($filters['status'] === 'critico') {
            $where[] = 'v.stock_qty > 0 AND v.stock_qty <= 3';
        } elseif ($filters['status'] === 'ok') {
            $where[] = 'v.stock_qty > 3';
        }
    }

    if (!empty($filters['search'])) {
        $where[]         = '(m.name LIKE :s1 OR v.color LIKE :s2 OR v.size LIKE :s3 OR v.sku LIKE :s4)';
        $params[':s1']    = '%' . $filters['search'] . '%';
        $params[':s2']    = '%' . $filters['search'] . '%';
        $params[':s3']    = '%' . $filters['search'] . '%';
        $params[':s4']    = '%' . $filters['search'] . '%';
    }

    $stmt = db()->prepare("
        SELECT v.*, m.name AS model_name, m.demand_count,
               m.demand_count / GREATEST((SELECT MAX(demand_count) FROM ai_catalogo_models WHERE tenant_id = :tid2), 1) * 100 AS demand_pct
        FROM   ai_catalogo_variants v
        INNER JOIN ai_catalogo_models m ON m.id = v.model_id
        WHERE  " . implode(' AND ', $where) . "
        ORDER BY m.demand_count DESC, v.color, v.size
    ");
    $params[':tid2'] = $tid;
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Relatório de demanda reprimida — buscas sem resultado agrupadas.
 * @param string $period  'week' | 'month' | 'quarter'
 */
function ai_get_search_misses_report(
    int    $tenantId,
    string $period = 'month',
    int    $limit  = 20
): array {
    $intervals = [
        'week'    => 'INTERVAL 7 DAY',
        'month'   => 'INTERVAL 30 DAY',
        'quarter' => 'INTERVAL 90 DAY',
    ];
    $interval = $intervals[$period] ?? $intervals['month'];
    try {
        $stmt = db()->prepare("
            SELECT 
                query_original              AS query, 
                COUNT(*)                    AS total, 
                MAX(created_at)             AS last_search, 
                COUNT(DISTINCT session_phone) AS unique_clients
            FROM ai_search_misses
            WHERE tenant_id = :tid
              AND created_at >= NOW() - {$interval}
            GROUP BY query_original
            ORDER BY total DESC
            LIMIT {$limit}
        ");
        $stmt->execute([':tid' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}
