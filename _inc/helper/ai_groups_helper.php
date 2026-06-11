<?php
/**
 * Helper: ai_groups_helper.php
 * Centraliza consultas e lógica de negócio para o módulo de Grupos IA.
 */

if (!function_exists('ai_get_setting')) {
    require_once __DIR__ . '/ai_concierge.php';
}

/**
 * Calcula o próximo horário estimado de disparo com base nas configurações do grupo.
 */
function ai_groups_calculate_next_dispatch_time(array $group, int $sentToday, ?string $lastSentAt = null, ?string $scheduledAt = null, bool $hasBeenSent = false): ?array
{
    $settings = ai_groups_decode_json($group['settings_json'] ?? null);
    $dailyLimit = max(0, (int)($group['daily_limit'] ?? 0));
    $intervalMinutes = max(0, (int)($settings['dispatch_interval_minutes'] ?? $settings['interval_between_dispatches'] ?? 0));
    $startTime = trim((string)($settings['start_time'] ?? '09:00'));
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        $startTime = '09:00';
    }
    $repeatDaysStr = trim((string)($settings['repeat_days'] ?? ''));
    $repeatDays = [];
    if ($repeatDaysStr !== '') {
        $repeatDays = array_map('intval', array_filter(explode(',', $repeatDaysStr)));
    }

    $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));

    // 1) Honra agendamento manual futuro, mas não deixa retornar passado
    $isScheduledByUser = !$hasBeenSent && !empty($scheduledAt);
    if ($isScheduledByUser) {
        try {
            $userDt = new DateTime($scheduledAt);
            if ($userDt < $now) {
                $userDt = clone $now;
            }
            return [
                'datetime' => $userDt->format('Y-m-d H:i:s'),
                'type' => 'user',
                'label' => 'Agendado por você',
            ];
        } catch (Throwable $e) {
            // Se a data manual for inválida, cai para o fluxo automático
        }
    }

    // 2) Base para cálculo automático: último envio ou agora, o que for mais recente
    $baseTime = clone $now;
    if (!empty($lastSentAt)) {
        try {
            $last = new DateTime($lastSentAt);
            if ($last > $baseTime) {
                $baseTime = $last;
            }
        } catch (Throwable $e) {
            // mantém $baseTime = now
        }
    }

    // 3) Se já bateu o limite diário, empurra para o próximo dia válido no horário de início
    if ($dailyLimit > 0 && $sentToday >= $dailyLimit) {
        $nextDay = (clone $baseTime)->modify('+1 day');
        $validNextDay = ai_groups_find_next_valid_day($nextDay, $repeatDays);
        [$startHour, $startMin] = explode(':', $startTime);
        $validNextDay->setTime((int)$startHour, (int)$startMin, 0);
        if ($validNextDay < $now) {
            $validNextDay = $now;
        }
        return [
            'datetime' => $validNextDay->format('Y-m-d H:i:s'),
            'type' => 'system',
            'label' => 'Próximo dia útil',
        ];
    }

    // 4) Gera próximo intervalo bruto com base no último envio (ou agora)
    if ($intervalMinutes > 0) {
        $nextInterval = (clone $baseTime)->modify("+{$intervalMinutes} minutes");
    } else {
        $nextInterval = clone $baseTime;
    }

    // 5) Força sempre para frente em relação a "agora" para evitar datas expiradas
    if ($nextInterval < $now) {
        $nextInterval = clone $now;
    }

    // 6) Itera até achar um dia/hora válido, parando se não encontrar nada razoável
    $attempts = 0;
    $maxAttempts = 7 * 24 * 60; // até 7 dias, em passos de 1 minuto
    $nextTime = null;

    while ($attempts < $maxAttempts) {
        $validDay = ai_groups_find_next_valid_day($nextInterval, $repeatDays);

        [$startHour, $startMin] = explode(':', $startTime);
        $startOfDay = (clone $validDay)->setTime((int)$startHour, (int)$startMin, 0);
        $endOfDay = (clone $validDay)->setTime(23, 59, 59);

        if ($validDay < $startOfDay) {
            $candidate = $startOfDay;
        } else {
            $candidate = $validDay;
        }

        if ($candidate < $now) {
            // ainda no passado: avança 1 minuto e tenta de novo
            $nextInterval = (clone $candidate)->modify('+1 minute');
            $attempts++;
            continue;
        }

        if ($candidate > $endOfDay) {
            // passou do fim do dia: pula para o próximo dia
            $nextInterval = (clone $endOfDay)->modify('+1 day');
            $attempts++;
            continue;
        }

        $nextTime = $candidate;
        break;
    }

    if ($nextTime === null) {
        // fallback defensivo: agora
        $nextTime = clone $now;
    }

    return [
        'datetime' => $nextTime->format('Y-m-d H:i:s'),
        'type' => 'system',
        'label' => 'Fila de produtos',
    ];
}

function ai_groups_find_next_valid_day(DateTime $date, array $repeatDays): DateTime
{
    $result = (clone $date);
    $checkDays = 0;
    while ($checkDays < 365) {
        $dayOfWeek = (int)$result->format('w');
        if (empty($repeatDays) || in_array($dayOfWeek, $repeatDays, true)) {
            return $result;
        }
        $result->modify('+1 day');
        $checkDays++;
    }
    return $date;
}

/**
 * Verifica se uma tabela necessária para o módulo existe.
 */
function ai_groups_table_exists(string $tableName): bool
{
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return (bool)$cache[$tableName];
    }

    try {
        $quoted = db()->quote($tableName);
        $stmt = db()->query("SHOW TABLES LIKE {$quoted}");
        $cache[$tableName] = $stmt ? (bool)$stmt->fetchColumn() : false;
    } catch (Throwable $e) {
        $cache[$tableName] = false;
    }

    return (bool)$cache[$tableName];
}
function ai_groups_column_exists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $cacheKey = $tableName . '::' . $columnName;
    if (array_key_exists($cacheKey, $cache)) {
        return (bool)$cache[$cacheKey];
    }

    if (!ai_groups_table_exists($tableName)) {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $stmt = db()->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE :col");
        $stmt->execute([':col' => $columnName]);
        $cache[$cacheKey] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return (bool)$cache[$cacheKey];
}
function ai_groups_index_exists(string $tableName, string $indexName): bool
{
    static $cache = [];
    $cacheKey = $tableName . '::idx::' . $indexName;
    if (array_key_exists($cacheKey, $cache)) {
        return (bool)$cache[$cacheKey];
    }

    if (!ai_groups_table_exists($tableName)) {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $stmt = db()->prepare("SHOW INDEX FROM `{$tableName}` WHERE Key_name = :idx");
        $stmt->execute([':idx' => $indexName]);
        $cache[$cacheKey] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return (bool)$cache[$cacheKey];
}
function ai_groups_store_token(int $tenantId): string
{
    if ($tenantId <= 0) {
        return '';
    }

    try {
        $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
        $stmt->execute([':tid' => $tenantId]);
        return trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}
function ai_groups_decode_json($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}
function ai_groups_media_list($value): array
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
            if (strpos($value, '[') === 0) {
                $decoded = json_decode($value, true);
            }
            if (is_array($decoded)) {
                $urls = array_merge($urls, ai_groups_media_list($decoded));
            } elseif (strpos($value, ',') !== false) {
                $urls = array_merge($urls, ai_groups_media_list(array_map('trim', explode(',', $value))));
            } else {
                $urls[] = $value;
            }
        }
    }
    return array_values(array_unique($urls));
}
function ai_groups_collect_status_media_urls(array $row): array
{
    $payload = ai_groups_decode_json($row['payload_json'] ?? null);
    $urls = [];
    $urls = array_merge($urls, ai_groups_media_list($row['media_url'] ?? ''));
    $urls = array_merge($urls, ai_groups_media_list($row['media_urls'] ?? []));
    $urls = array_merge($urls, ai_groups_media_list($payload['media_url'] ?? ''));
    $urls = array_merge($urls, ai_groups_media_list($payload['media_urls'] ?? []));
    return array_values(array_unique($urls));
}
function ai_groups_get_active_dispatch(int $tenantId): array
{
    $result = [
        'busy' => false,
        'type' => '',
        'id' => 0,
        'status' => '',
        'scheduled_at' => null,
        'updated_at' => null,
    ];
    if ($tenantId <= 0) {
        return $result;
    }

    try {
        if (ai_groups_table_exists('concierge_status')) {
            $stmt = db()->prepare("
                SELECT id, status, scheduled_at, updated_at
                FROM concierge_status
                WHERE tenant_id = :tid
                  AND status = 'sending'
                  AND updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ORDER BY updated_at DESC
                LIMIT 1
            ");
            $stmt->execute([':tid' => $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['id'])) {
                $result['busy'] = true;
                $result['type'] = 'status';
                $result['id'] = (int)$row['id'];
                $result['status'] = (string)($row['status'] ?? 'sending');
                $result['scheduled_at'] = $row['scheduled_at'] ?? null;
                $result['updated_at'] = $row['updated_at'] ?? null;
                return $result;
            }
        }
    } catch (Throwable $e) {
    }

    try {
        if (ai_groups_table_exists('concierge_campaigns')) {
            $stmt = db()->prepare("
                SELECT id, status, scheduled_at, updated_at
                FROM concierge_campaigns
                WHERE tenant_id = :tid
                  AND status = 'sending'
                  AND updated_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY updated_at DESC
                LIMIT 1
            ");
            $stmt->execute([':tid' => $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['id'])) {
                $result['busy'] = true;
                $result['type'] = 'campaign';
                $result['id'] = (int)$row['id'];
                $result['status'] = (string)($row['status'] ?? 'sending');
                $result['scheduled_at'] = $row['scheduled_at'] ?? null;
                $result['updated_at'] = $row['updated_at'] ?? null;
                return $result;
            }
        }
    } catch (Throwable $e) {
    }

    return $result;
}
function ai_groups_find_status_schedule_conflict(int $tenantId, string $scheduledAt, int $excludeStatusId = 0, int $minimumGapMinutes = 5): ?array
{
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return null;
    }

    $scheduledAt = trim($scheduledAt);
    if ($scheduledAt === '') {
        return null;
    }

    try {
        $targetDate = new DateTime($scheduledAt);
    } catch (Throwable $e) {
        return null;
    }

    $target = $targetDate->format('Y-m-d H:i:s');
    $gapSeconds = max(60, min(3600, $minimumGapMinutes * 60));

    try {
        $sql = "
            SELECT id, status, scheduled_at
            FROM concierge_status
            WHERE tenant_id = :tid
              AND status IN ('pending','sending')
              AND scheduled_at IS NOT NULL
        ";
        $params = [
            ':tid' => $tenantId,
            ':target' => $target,
            ':gap' => $gapSeconds,
        ];
        if ($excludeStatusId > 0) {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeStatusId;
        }
        $sql .= "
              AND ABS(TIMESTAMPDIFF(SECOND, scheduled_at, :target)) < :gap
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, scheduled_at, :target)) ASC
            LIMIT 1
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) && !empty($row['id']) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}
function ai_groups_find_campaign_schedule_conflict(int $tenantId, string $scheduledAt, int $excludeCampaignId = 0, int $minimumGapMinutes = 5): ?array
{
    if ($tenantId <= 0) return null;

    $scheduledAt = trim($scheduledAt);
    if ($scheduledAt === '') return null;

    try {
        $targetDate = new DateTime($scheduledAt);
        $target = $targetDate->format('Y-m-d H:i:s');
    } catch (Throwable $e) { return null; }

    $gapSeconds = max(60, min(3600, $minimumGapMinutes * 60));

    // 1. Verifica Campanhas
    if (ai_groups_table_exists('concierge_campaigns')) {
        try {
            $sql = "SELECT id, status, scheduled_at, 'campaign' as type FROM concierge_campaigns WHERE tenant_id = :tid AND status IN ('scheduled','queued','sending','pending') AND scheduled_at IS NOT NULL";
            $params = [':tid' => $tenantId, ':target' => $target, ':gap' => $gapSeconds];
            if ($excludeCampaignId > 0) { $sql .= " AND id <> :exclude_id"; $params[':exclude_id'] = $excludeCampaignId; }
            $sql .= " AND ABS(TIMESTAMPDIFF(SECOND, scheduled_at, :target)) < :gap ORDER BY ABS(TIMESTAMPDIFF(SECOND, scheduled_at, :target)) ASC LIMIT 1";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        } catch (Throwable $e) {}
    }

    // 2. Verifica Status (Postagens)
    if (ai_groups_table_exists('concierge_status')) {
        try {
            $sql = "SELECT id, status, scheduled_at, 'status' as type FROM concierge_status WHERE tenant_id = :tid AND status IN ('scheduled','sending','pending') AND scheduled_at IS NOT NULL";
            $params = [':tid' => $tenantId, ':target' => $target, ':gap' => $gapSeconds];
            $sql .= " AND ABS(TIMESTAMPDIFF(SECOND, scheduled_at, :target)) < :gap ORDER BY ABS(TIMESTAMPDIFF(SECOND, scheduled_at, :target)) ASC LIMIT 1";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;
        } catch (Throwable $e) {}
    }

    return null;
}
function ai_groups_ensure_status_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (!ai_groups_table_exists('concierge_status')) {
            db()->exec("
                CREATE TABLE IF NOT EXISTS `concierge_status` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tenant_id` int(11) NOT NULL,
                  `product_id` int(11) DEFAULT NULL,
                  `content` text NOT NULL,
                  `media_url` varchar(255) DEFAULT NULL,
                  `status` enum('pending','sending','sent','error','canceled') NOT NULL DEFAULT 'pending',
                  `scheduled_at` datetime DEFAULT NULL,
                  `payload_json` longtext DEFAULT NULL,
                  `error_message` text DEFAULT NULL,
                  `sent_at` datetime DEFAULT NULL,
                  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_tenant_status` (`tenant_id`,`status`),
                  KEY `idx_tenant_scheduled` (`tenant_id`,`scheduled_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            return;
        }

        // Força a adição das colunas ignorando erros de "coluna já existe"
        $cols = [
            'repeat_count' => "INT(11) NOT NULL DEFAULT 1",
            'repeat_interval' => "INT(11) NOT NULL DEFAULT 1",
            'post_count' => "INT(11) NOT NULL DEFAULT 0",
            'attempt_count' => "INT(11) NOT NULL DEFAULT 0",
            'seen_count' => "INT(11) NOT NULL DEFAULT 0",
            'repeat_days' => "VARCHAR(255) DEFAULT NULL",
            'success_history_json' => "LONGTEXT DEFAULT NULL",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($cols as $col => $definition) {
            try {
                db()->exec("ALTER TABLE `concierge_status` ADD COLUMN `{$col}` {$definition}");
            } catch (Throwable $e) {
                // Coluna provavelmente já existe, ignora
            }
        }

        if (!ai_groups_column_exists('concierge_status', 'scheduled_at')) {
            db()->exec("ALTER TABLE `concierge_status` ADD COLUMN `scheduled_at` DATETIME DEFAULT NULL AFTER `status`");
        }
        if (!ai_groups_column_exists('concierge_status', 'payload_json')) {
            db()->exec("ALTER TABLE `concierge_status` ADD COLUMN `payload_json` LONGTEXT DEFAULT NULL AFTER `scheduled_at`");
        }
        if (!ai_groups_column_exists('concierge_status', 'updated_at')) {
            db()->exec("ALTER TABLE `concierge_status` ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
        }

        try {
            db()->exec("ALTER TABLE `concierge_status` MODIFY COLUMN `status` ENUM('pending','sending','sent','error','canceled') NOT NULL DEFAULT 'pending'");
        } catch (Throwable $e) {
        }

        if (!ai_groups_index_exists('concierge_status', 'idx_tenant_scheduled')) {
            try {
                db()->exec("ALTER TABLE `concierge_status` ADD INDEX `idx_tenant_scheduled` (`tenant_id`,`scheduled_at`)");
            } catch (Throwable $e) {
            }
        }
    } catch (Throwable $e) {
    }
}
function ai_groups_ensure_broadcast_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!ai_groups_table_exists('concierge_broadcasts')) {
        return;
    }

    try {
        db()->exec("ALTER TABLE `concierge_broadcasts` MODIFY COLUMN `status` ENUM('pending','queued','sending','sent','error','skipped') NOT NULL DEFAULT 'pending'");
    } catch (Throwable $e) {
    }

    if (!ai_groups_index_exists('concierge_broadcasts', 'uk_campaign_group')) {
        try {
            db()->exec("ALTER TABLE `concierge_broadcasts` ADD UNIQUE KEY `uk_campaign_group` (`campaign_id`,`group_id`)");
        } catch (Throwable $e) {
        }
    }
}

function ai_groups_ensure_suggestions_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (!ai_groups_table_exists('concierge_ai_suggestions')) {
            db()->exec("
                CREATE TABLE IF NOT EXISTS `concierge_ai_suggestions` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tenant_id` int(11) NOT NULL,
                  `product_id` int(11) DEFAULT NULL,
                  `suggestion_payload_json` longtext DEFAULT NULL,
                  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
                  `batch_id` varchar(64) DEFAULT NULL,
                  `source_webhook` varchar(255) DEFAULT NULL,
                  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  `resolved_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_tenant_status` (`tenant_id`,`status`),
                  KEY `idx_tenant_batch` (`tenant_id`,`batch_id`),
                  KEY `idx_tenant_product` (`tenant_id`,`product_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
    } catch (Throwable $e) {
    }
}
function ai_groups_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 120): array
{
    $url = trim($url);
    if ($url === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Webhook de destino não configurado.',
            'raw' => '',
            'json' => [],
        ];
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $body = '{}';
    }

    $ch = curl_init($url);
    $mergedHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $mergedHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, max(5, $timeout));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    if (stripos($url, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return [
        'ok' => $err === '' && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'error' => $err,
        'raw' => is_string($raw) ? $raw : '',
        'json' => $json,
    ];
}
function ai_groups_dispatch_webhook_url(int $tenantId, string $kind = 'campaign'): string
{
    return $kind === 'status'
        ? trim((string)ai_get_setting('ai_status_dispatch_webhook_url', '', $tenantId))
        : trim((string)ai_get_setting('ai_groups_dispatch_webhook_url', '', $tenantId));
}
function ai_groups_status_posting_mode(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = 'n8n';
    try {
        if (!ai_groups_table_exists('saas_config_global')) {
            return $cached;
        }
        $stmt = db()->prepare("SELECT key_value FROM saas_config_global WHERE key_name = 'ai_status_posting_mode' LIMIT 1");
        $stmt->execute();
        $mode = strtolower(trim((string)$stmt->fetchColumn()));
        if (in_array($mode, ['n8n', 'system'], true)) {
            $cached = $mode;
        }
    } catch (Throwable $e) {
    }

    return $cached;
}
function ai_groups_campaign_posting_mode(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = 'n8n';
    try {
        if (!ai_groups_table_exists('saas_config_global')) {
            return $cached;
        }
        $stmt = db()->prepare("SELECT key_value FROM saas_config_global WHERE key_name = 'ai_campaign_posting_mode' LIMIT 1");
        $stmt->execute();
        $mode = strtolower(trim((string)$stmt->fetchColumn()));
        if (in_array($mode, ['n8n', 'system'], true)) {
            $cached = $mode;
        }
    } catch (Throwable $e) {
    }

    return $cached;
}
function ai_groups_status_payload_bool($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return $default;
    }
    $v = strtolower(trim((string)$value));
    if ($v === '') {
        return $default;
    }
    return in_array($v, ['1', 'true', 'yes', 'sim', 'on'], true);
}
function ai_groups_status_payload_jid_list($value): array
{
    $list = [];
    if (is_array($value)) {
        foreach ($value as $item) {
            $jid = trim((string)$item);
            if ($jid !== '') {
                $list[] = $jid;
            }
        }
    } elseif (is_string($value)) {
        $raw = trim($value);
        if ($raw !== '') {
            $decoded = null;
            if (strpos($raw, '[') === 0) {
                $decoded = json_decode($raw, true);
            }
            if (is_array($decoded)) {
                $list = array_merge($list, ai_groups_status_payload_jid_list($decoded));
            } elseif (strpos($raw, ',') !== false) {
                $list = array_merge($list, ai_groups_status_payload_jid_list(array_map('trim', explode(',', $raw))));
            } else {
                $list[] = $raw;
            }
        }
    }
    return array_values(array_unique(array_filter($list, static function ($v) {
        return (string)$v !== '';
    })));
}
function ai_groups_status_guess_media_type(string $mediaUrl): string
{
    $path = parse_url($mediaUrl, PHP_URL_PATH);
    $ext = strtolower((string)pathinfo((string)$path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true)) {
        return 'image';
    }
    if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm'], true)) {
        return 'video';
    }
    if (in_array($ext, ['mp3', 'ogg', 'wav', 'm4a', 'aac'], true)) {
        return 'audio';
    }
    return 'image';
}
function ai_groups_status_public_media_base_url(int $tenantId): string
{
    $globalBase = '';
    try {
        if (ai_groups_table_exists('saas_config_global')) {
            $stmt = db()->prepare("SELECT key_value FROM saas_config_global WHERE key_name = 'ai_modernpos_base_url' LIMIT 1");
            $stmt->execute();
            $globalBase = rtrim(trim((string)$stmt->fetchColumn()), '/');
        }
    } catch (Throwable $e) {}

    $candidates = [
        rtrim(trim((string)ai_get_setting('ai_status_media_public_base_url', '', $tenantId)), '/'),
        rtrim(trim((string)ai_get_setting('ai_public_media_base_url', '', $tenantId)), '/'),
        $globalBase,
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $parts = @parse_url($candidate);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            continue;
        }
        return rtrim($candidate, '/');
    }

    return '';
}
function ai_groups_status_normalize_media_url(string $mediaUrl, int $tenantId = 0): string
{
    $mediaUrl = trim($mediaUrl);
    if ($mediaUrl === '') {
        return '';
    }

    if (function_exists('ai_resolve_storage_url')) {
        $mediaUrl = (string)ai_resolve_storage_url($mediaUrl);
    }

    $parts = @parse_url($mediaUrl);
    $host = strtolower((string)($parts['host'] ?? ''));
    
    // Se o host for local, forçamos a troca para a URL da Ngrok fornecida pelo usuário
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        $publicBase = ai_groups_status_public_media_base_url($tenantId);
        $path = (string)($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
        
        if ($publicBase !== '' && $path !== '') {
            // Garante que o path comece com /
            if ($path[0] !== '/') $path = '/' . $path;

            $baseParts = @parse_url($publicBase);
            $basePath = '';
            if (is_array($baseParts)) {
                $basePath = rtrim((string)($baseParts['path'] ?? ''), '/');
            }
            if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
                $path = substr($path, strlen($basePath));
                if ($path === '') {
                    $path = '/';
                }
            }

            $mediaUrl = rtrim($publicBase, '/') . $path . $query;
        }
    }

    $parts = @parse_url($mediaUrl);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $mediaUrl;
    }

    $normalizedPath = (string)($parts['path'] ?? '');
    if ($normalizedPath !== '') {
        $segments = explode('/', $normalizedPath);
        $segments = array_map(static function ($seg) {
            return rawurlencode(rawurldecode((string)$seg));
        }, $segments);
        $normalizedPath = implode('/', $segments);
    }
    $normalizedQueryRaw = (string)($parts['query'] ?? '');
    if (strpos(strtolower((string)$parts['host']), 'ngrok-free.dev') !== false) {
        parse_str($normalizedQueryRaw, $qs);
        if (!is_array($qs)) {
            $qs = [];
        }
        if (!array_key_exists('ngrok-skip-browser-warning', $qs)) {
            $qs['ngrok-skip-browser-warning'] = '1';
        }
        $normalizedQueryRaw = http_build_query($qs);
    }
    $normalizedQuery = $normalizedQueryRaw !== '' ? ('?' . $normalizedQueryRaw) : '';
    $normalizedPort = isset($parts['port']) ? (':' . (int)$parts['port']) : '';

    return $parts['scheme'] . '://' . $parts['host'] . $normalizedPort . $normalizedPath . $normalizedQuery;
}
function ai_groups_status_probe_media_url(string $mediaUrl, int $timeout = 12): array
{
    $url = trim($mediaUrl);
    if ($url === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'content_type' => '',
            'error' => 'URL de mídia vazia.',
        ];
    }

    // Se for localhost ou estiver em ambiente local, relaxamos a verificação
    // para evitar erros 404 causados por loopback ou ngrok offline temporariamente
    $isLocal = false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (in_array($host, ['localhost', '127.0.0.1'], true) || strpos($url, 'ngrok-free.dev') !== false) {
        $isLocal = true;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, max(5, $timeout));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['ngrok-skip-browser-warning: 1']);

    curl_exec($ch);
    $err = trim((string)curl_error($ch));
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = strtolower(trim((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));
    curl_close($ch);

    $ok = $err === '' && $httpCode >= 200 && $httpCode < 400;
    
    // Se falhou mas é local/ngrok, vamos permitir com um aviso, pois o WhatsApp (Evolution)
    // pode conseguir acessar a URL mesmo que o servidor local não consiga fazer o loopback
    if (!$ok && $isLocal) {
        return [
            'ok' => true, 
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'error' => '',
            'warning' => 'Mídia local detectada. Ignorando erro de probe: ' . ($err ?: "HTTP $httpCode")
        ];
    }

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'error' => $err,
    ];
}
function ai_groups_status_extract_response_error(array $resp): string
{
    $parts = [];

    $err = trim((string)($resp['error'] ?? ''));
    if ($err !== '') {
        $parts[] = $err;
    }

    $json = (array)($resp['json'] ?? []);
    $messages = [];
    $walker = static function ($value) use (&$messages, &$walker): void {
        if (is_array($value)) {
            foreach ($value as $vv) {
                $walker($vv);
            }
            return;
        }
        if (is_scalar($value)) {
            $txt = trim((string)$value);
            if ($txt !== '') {
                $messages[] = $txt;
            }
        }
    };
    $walker($json['response']['message'] ?? null);
    $walker($json['message'] ?? null);
    $walker($json['error'] ?? null);
    if (!empty($messages)) {
        $parts[] = implode(' | ', array_values(array_unique($messages)));
    }

    $raw = trim((string)($resp['raw'] ?? ''));
    if ($raw !== '') {
        $parts[] = $raw;
    }

    return trim(implode(' | ', array_values(array_unique(array_filter($parts)))));
}
function ai_groups_status_build_base64_content(string $mediaUrl): string
{
    $mediaUrl = trim($mediaUrl);
    if ($mediaUrl === '') {
        return '';
    }

    $fileData = '';
    $localPath = '';
    try {
        $urlParts = parse_url($mediaUrl);
        $urlPath = urldecode((string)($urlParts['path'] ?? ''));

        if (defined('DIR_STORAGE')) {
            $search = '/storage/';
            $pos = strpos($urlPath, $search);
            if ($pos !== false) {
                $sub = substr($urlPath, $pos + strlen($search));
                $candidate = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
                if (file_exists($candidate)) {
                    $localPath = $candidate;
                }
            }
        }
        if ($localPath === '' && defined('ROOT')) {
            $relativePath = '';
            if (strpos($urlPath, '/storage/') !== false) {
                $relativePath = substr($urlPath, strpos($urlPath, '/storage/'));
            } elseif (strpos($urlPath, '/modernpos/') !== false) {
                $relativePath = substr($urlPath, strpos($urlPath, '/modernpos/') + 10);
            }
            if ($relativePath !== '') {
                $candidate = rtrim(ROOT, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
                if (file_exists($candidate)) {
                    $localPath = $candidate;
                }
            }
        }

        if ($localPath !== '') {
            $fileData = (string)@file_get_contents($localPath);
        }
        if ($fileData === '') {
            $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "ngrok-skip-browser-warning: 1\r\nUser-Agent: Mozilla/5.0\r\n"]]);
            $fileData = (string)@file_get_contents($mediaUrl, false, $ctx);
        }
    } catch (Throwable $e) {
        $fileData = '';
    }
    if ($fileData === '') {
        return '';
    }

    $mime = 'image/jpeg';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = trim((string)$finfo->buffer($fileData));
        if ($detected !== '') {
            $mime = $detected;
        }
    }

    if ($mime === 'image/webp' && function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $img = @imagecreatefromstring($fileData);
        if ($img !== false) {
            ob_start();
            @imagejpeg($img, null, 86);
            $converted = (string)ob_get_clean();
            @imagedestroy($img);
            if ($converted !== '') {
                $fileData = $converted;
                $mime = 'image/jpeg';
            }
        }
    }

    return 'data:' . $mime . ';base64,' . base64_encode($fileData);
}
function ai_groups_dispatch_status_via_system(int $tenantId, array $row): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    
    if (!function_exists('ai_evolution_get_connection') || !function_exists('ai_evolution_http_request')) {
        if (defined('DIR_HELPER') && file_exists(DIR_HELPER . 'ai_evolution.php')) {
            require_once DIR_HELPER . 'ai_evolution.php';
        }
    }

    if (!function_exists('ai_evolution_get_connection') || !function_exists('ai_evolution_http_request')) {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Helper da Evolution indisponível para disparo via sistema.',
            'json' => [],
            'raw' => '',
            'external_message_id' => '',
        ];
    }

    $connection = ai_evolution_get_connection($tenantId);
    $baseUrl = trim((string)($connection['base_url'] ?? ''));
    $instanceName = trim((string)($connection['instance_name'] ?? ''));
    $apiKey = trim((string)($connection['global_token'] ?? ''));

    if ($baseUrl === '' || $instanceName === '' || $apiKey === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Conexão Evolution incompleta para disparo de status.',
            'json' => [],
            'raw' => '',
            'external_message_id' => '',
        ];
    }

    $payloadJson = ai_groups_decode_json($row['payload_json'] ?? null);
    $content = trim((string)($row['content'] ?? ''));
    $mediaUrls = ai_groups_collect_status_media_urls($row);
    $mediaUrl = trim((string)($mediaUrls[0] ?? $row['media_url'] ?? ''));
    $mediaUrl = ai_groups_status_normalize_media_url($mediaUrl, $tenantId);

    $type = strtolower(trim((string)($payloadJson['media_type'] ?? $payloadJson['type'] ?? '')));
    if ($type === '') {
        $type = $mediaUrl !== '' ? ai_groups_status_guess_media_type($mediaUrl) : 'text';
    }
    if (!in_array($type, ['text', 'image', 'video', 'audio'], true)) {
        $type = $mediaUrl !== '' ? ai_groups_status_guess_media_type($mediaUrl) : 'text';
    }

    if ($type !== 'text' && $mediaUrl === '') {
        $type = 'text';
    }

    if ($type === 'text' && $content === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Conteúdo da postagem de status está vazio.',
            'json' => [],
            'raw' => '',
            'external_message_id' => '',
        ];
    }
    if ($type !== 'text' && $mediaUrl !== '') {
        $mediaProbe = ai_groups_status_probe_media_url($mediaUrl);
        if (empty($mediaProbe['ok'])) {
            $probeErr = trim((string)($mediaProbe['error'] ?? ''));
            if ($probeErr === '') {
                $probeErr = 'HTTP ' . (int)($mediaProbe['http_code'] ?? 0);
            }
            return [
                'ok' => false,
                'http_code' => (int)($mediaProbe['http_code'] ?? 0),
                'error' => 'Mídia de status inacessível publicamente: ' . $probeErr,
                'json' => [],
                'raw' => '',
                'external_message_id' => '',
            ];
        }
    }

    $statusJidList = ai_groups_status_payload_jid_list($payloadJson['status_jid_list'] ?? $payloadJson['statusJidList'] ?? []);
    $allContactsRaw = $payloadJson['allContacts'] ?? $payloadJson['all_contacts'] ?? null;
    if ($allContactsRaw === null || trim((string)$allContactsRaw) === '') {
        $allContacts = empty($statusJidList);
    } else {
        $allContacts = ai_groups_status_payload_bool($allContactsRaw, empty($statusJidList));
    }
    if (!$allContacts && empty($statusJidList)) {
        $fallbackNumber = preg_replace('/\D+/', '', (string)ai_get_setting('ai_whatsapp_number', '', $tenantId));
        if ($fallbackNumber !== '') {
            if (strpos($fallbackNumber, '55') !== 0) {
                $fallbackNumber = '55' . $fallbackNumber;
            }
            $statusJidList[] = $fallbackNumber . '@s.whatsapp.net';
        }
    }
    $backgroundColor = trim((string)($payloadJson['backgroundColor'] ?? $payloadJson['background_color'] ?? '#008000'));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $backgroundColor)) {
        $backgroundColor = '#008000';
    }
    $font = (int)($payloadJson['font'] ?? 1);
    if ($font < 1 || $font > 5) {
        $font = 1;
    }

    $payload = [
        'type' => $type,
        'allContacts' => $allContacts,
    ];

    $base64Data = '';
    if ($type === 'text') {
        $payload['content'] = $content;
        $payload['backgroundColor'] = $backgroundColor;
        $payload['font'] = (int)$font;
    } else {
        $payload['content'] = $mediaUrl;
        if ($content !== '' && in_array($type, ['image', 'video'], true)) {
            $payload['caption'] = $content;
        }
    }
    if (!$allContacts && !empty($statusJidList)) {
        $payload['statusJidList'] = $statusJidList;
    }

    // Debug Log
    $debugMsg = sprintf("[%s] Dispatching Status ID %d | Type: %s | Base64: %s | URL: %s\n", 
        date('Y-m-d H:i:s'), $row['id'], $type, (($base64Data !== '' && $type !== 'text') ? 'YES' : 'NO'), $mediaUrl);
    @file_put_contents(DIR_LOG . 'status_dispatch.log', $debugMsg, FILE_APPEND);

    // Evolution API: prioriza endpoint moderno e cai para legado em 404/405.
    $statusTimeout = 120;
    $typeSuffix = 'sendText';
    if ($type === 'image') $typeSuffix = 'sendImage';
    elseif ($type === 'video') $typeSuffix = 'sendVideo';
    elseif ($type === 'audio') $typeSuffix = 'sendAudio';

    $urlV2 = rtrim($baseUrl, '/') . '/status/' . $typeSuffix . '/' . rawurlencode($instanceName);
    $urlLegacy = rtrim($baseUrl, '/') . '/message/sendStatus/' . rawurlencode($instanceName);
    
    $resp = ai_evolution_http_request('POST', $urlV2, $apiKey, $payload, $statusTimeout);
    $code = (int)($resp['http_code'] ?? 0);
    if ($code === 404 || $code === 405) {
        $resp = ai_evolution_http_request('POST', $urlLegacy, $apiKey, $payload, $statusTimeout);
    }

    $shouldRetryBase64 = false;
    if ($type !== 'text' && $base64Data === '') {
        $responseErrorText = strtolower(ai_groups_status_extract_response_error($resp));
        if ($responseErrorText !== '' && strpos($responseErrorText, 'media upload failed on all hosts') !== false) {
            $shouldRetryBase64 = true;
        }
    }
    if ($shouldRetryBase64) {
        $base64Data = ai_groups_status_build_base64_content($mediaUrl);
        if ($base64Data !== '') {
            $payloadRetry = $payload;
            $payloadRetry['content'] = $base64Data;
            $resp = ai_evolution_http_request('POST', $urlV2, $apiKey, $payloadRetry, $statusTimeout);
            $code = (int)($resp['http_code'] ?? 0);
            if ($code === 404 || $code === 405) {
                $resp = ai_evolution_http_request('POST', $urlLegacy, $apiKey, $payloadRetry, $statusTimeout);
            }
            $retryMsg = sprintf("[%s] Status ID %d retry with Base64 after media host upload failure.\n", date('Y-m-d H:i:s'), $row['id']);
            @file_put_contents(DIR_LOG . 'status_dispatch.log', $retryMsg, FILE_APPEND);
        }
    }

    $externalMessageId = '';
    if (function_exists('ai_evolution_array_path')) {
        $jsonBody = (array)($resp['json'] ?? []);
        $externalMessageId = (string)(
            ai_evolution_array_path($jsonBody, ['key', 'id'])
            ?? ai_evolution_array_path($jsonBody, ['response', 'key', 'id'])
            ?? ai_evolution_array_path($jsonBody, ['data', 'key', 'id'])
            ?? ai_evolution_array_path($jsonBody, ['id'])
            ?? ''
        );
        
        // Se a resposta contém 'key' ou 'messageId', a Evolution processou o comando.
        if ($externalMessageId === '' && (isset($jsonBody['key']) || isset($jsonBody['response']['key']) || isset($jsonBody['messageId']))) {
            $externalMessageId = 'evolution_processed_' . time();
        }
    }

    // Critério estrito de sucesso:
    // - HTTP 2xx, ou
    // - ID externo real retornado pela Evolution.
    // Não assumimos sucesso em timeout/HTTP 0.
    $httpCode = (int)($resp['http_code'] ?? 0);
    $isOk = !empty($resp['ok']) || ($httpCode >= 200 && $httpCode < 300) || $externalMessageId !== '';

    $responseError = trim((string)($resp['error'] ?? ''));
    if (!$isOk) {
        if ($responseError === '') {
            $rawBody = trim((string)($resp['raw'] ?? ''));
            if ($rawBody !== '') {
                $responseError = $rawBody;
            } elseif ($httpCode === 0) {
                $responseError = 'Sem confirmação da Evolution API (timeout/conexão). Verifique URL pública da mídia (ngrok) e disponibilidade da instância.';
            } else {
                $responseError = 'Falha no envio para Evolution API (HTTP ' . $httpCode . ').';
            }
        }
    } else {
        $responseError = '';
    }

    if (strlen($responseError) > 240) {
        $responseError = substr($responseError, 0, 240) . '...';
    }

    return [
        'ok' => $isOk,
        'http_code' => (int)($resp['http_code'] ?? 0),
        'error' => $isOk ? '' : $responseError,
        'json' => (array)($resp['json'] ?? []),
        'raw' => (string)($resp['raw'] ?? ''),
        'external_message_id' => $externalMessageId,
    ];
}
function ai_get_due_concierge_statuses(int $tenantId = 0, int $limit = 50): array
{
    ai_groups_ensure_status_schema();
    if (!ai_groups_table_exists('concierge_status')) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $where = "status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
    $params = [];

    if ($tenantId > 0) {
        $where .= ' AND tenant_id = :tid';
        $params[':tid'] = $tenantId;
    }

    try {
        $sql = "SELECT * FROM concierge_status WHERE {$where} ORDER BY COALESCE(scheduled_at, created_at) ASC LIMIT {$limit}";
        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
function ai_groups_handle_status_repost_logic(int $tenantId, int $statusId, array $row): bool
{
    // Força o fuso horário para garantir que a data gravada no JSON bata com o local do usuário
    $tz = ai_get_setting('timezone', 'America/Sao_Paulo', $tenantId);
    try {
        $dtNow = new DateTime('now', new DateTimeZone($tz));
        $today = $dtNow->format('Y-m-d');
    } catch (Throwable $e) {
        $today = date('Y-m-d');
    }

    // Busca o histórico mais recente diretamente do banco para evitar dados obsoletos no $row
    $currentHistory = [];
    try {
        $st = db()->prepare("SELECT success_history_json, post_count FROM concierge_status WHERE id = :sid LIMIT 1");
        $st->execute([':sid' => $statusId]);
        $latest = $st->fetch(PDO::FETCH_ASSOC);
        if ($latest) {
            $currentHistory = ai_groups_decode_json($latest['success_history_json'] ?? null);
            $currentPostCount = (int)($latest['post_count'] ?? 0);
        } else {
            $currentHistory = ai_groups_decode_json($row['success_history_json'] ?? null);
            $currentPostCount = (int)($row['post_count'] ?? 0);
        }
    } catch (Throwable $e) {
        $currentHistory = ai_groups_decode_json($row['success_history_json'] ?? null);
        $currentPostCount = (int)($row['post_count'] ?? 0);
    }

    $repeatCount = (int)($row['repeat_count'] ?? 1);
    $repeatDays = trim((string)($row['repeat_days'] ?? ''));

    $alreadySentToday = isset($currentHistory[$today]) && (string)$currentHistory[$today] === 'sent';
    $newPostCount = $currentPostCount + ($alreadySentToday ? 0 : 1);
    $currentHistory[$today] = 'sent';

    // Atualiza o contador de sucessos e o histórico no registro atual
    ai_update_concierge_status($tenantId, $statusId, [
        'post_count' => $newPostCount,
        'status' => 'sent',
        'sent_at' => date('Y-m-d H:i:s'),
        'error_message' => '',
        'success_history_json' => $currentHistory
    ]);

    // Calcula o total planejado: Dias da semana selecionados x Número de Ciclos
    $daysArr = explode(',', $repeatDays);
    $daysArr = array_filter($daysArr, function($d) { return $d !== '' && is_numeric($d); });
    $daysInWeek = max(1, count($daysArr));
    $totalTarget = $daysInWeek * $repeatCount;

    if ($newPostCount < $totalTarget && $repeatDays !== '') {
        $nextDate = ai_groups_calculate_next_scheduled_date($row['scheduled_at'] ?? $row['created_at'], $repeatDays);
        if ($nextDate) {
            ai_update_concierge_status($tenantId, $statusId, [
                'status' => 'pending',
                'scheduled_at' => $nextDate,
                'error_message' => '',
            ]);
            return true;
        }
    }
    return false;
}

function ai_groups_calculate_next_scheduled_date($currentScheduledAt, $repeatDaysStr)
{
    $days = explode(',', $repeatDaysStr);
    $days = array_filter($days, function($d) { return $d !== '' && is_numeric($d); });
    if (empty($days)) return null;

    try {
        $baseDate = $currentScheduledAt ? new DateTime($currentScheduledAt) : new DateTime();
    } catch (Throwable $e) {
        $baseDate = new DateTime();
    }
    
    $time = $baseDate->format('H:i:s');
    
    // Tenta encontrar o próximo dia da semana na lista (nos próximos 7 dias)
    for ($i = 1; $i <= 7; $i++) {
        $nextDate = clone $baseDate;
        $nextDate->modify("+$i day");
        $dayOfWeek = (int)$nextDate->format('w'); // 0 (Dom) a 6 (Sáb)
        if (in_array($dayOfWeek, $days)) {
            return $nextDate->format('Y-m-d') . ' ' . $time;
        }
    }
    return null;
}

function ai_groups_generate_intelligent_statuses(int $tenantId, ?array &$debug = null): int
{
    $debugData = [
        'blocked_reason_code' => '',
        'blocked_reason_message' => '',
        'target_daily' => 0,
        'already_scheduled_or_sent_today' => 0,
        'needed' => 0,
        'days_to_reuse' => 0,
        'interval_hours' => 0,
        'eligible_products_count' => 0,
        'selected_products_count' => 0,
        'excluded_recent_count' => 0,
        'excluded_missing_media_count' => 0,
    ];

    if ($tenantId <= 0) {
        $debugData['blocked_reason_code'] = 'invalid_tenant';
        $debugData['blocked_reason_message'] = 'Tenant inválido para geração automática.';
        $debug = $debugData;
        return 0;
    }

    $enabled = (int)ai_get_setting('mia_status_auto_enable', 0, $tenantId);
    if ($enabled !== 1) {
        $debugData['blocked_reason_code'] = 'auto_disabled';
        $debugData['blocked_reason_message'] = 'Automação de status está desativada.';
        $debug = $debugData;
        return 0;
    }

    $target = (int)ai_get_setting('mia_status_auto_count', 4, $tenantId);
    $debugData['target_daily'] = max(0, $target);
    if ($target <= 0) {
        $debugData['blocked_reason_code'] = 'invalid_daily_target';
        $debugData['blocked_reason_message'] = 'Meta diária de posts inválida.';
        $debug = $debugData;
        return 0;
    }

    // Obtém as novas configurações personalizadas
    $daysToReuse = max(1, (int)ai_get_setting('mia_status_auto_days', 3, $tenantId));
    $intervalHours = max(1, (int)ai_get_setting('mia_status_auto_interval', 1, $tenantId));
    $debugData['days_to_reuse'] = $daysToReuse;
    $debugData['interval_hours'] = $intervalHours;

    // Posts hoje
    $tz = ai_get_setting('timezone', 'America/Sao_Paulo', $tenantId);
    try {
        $dtNow = new DateTime('now', new DateTimeZone($tz));
    } catch (Throwable $e) {
        $dtNow = new DateTime();
    }
    $today = $dtNow->format('Y-m-d');

    // Conta quantos já foram enviados ou estão pendentes para hoje
    $count = 0;
    try {
        $st = db()->prepare("
            SELECT COUNT(*) 
            FROM concierge_status 
            WHERE tenant_id = :tid 
              AND status IN ('sent', 'pending', 'sending') 
              AND DATE(COALESCE(sent_at, scheduled_at, created_at)) = :today
        ");
        $st->execute([':tid' => $tenantId, ':today' => $today]);
        $count = (int)$st->fetchColumn();
    } catch (Throwable $e) {
    }
    $debugData['already_scheduled_or_sent_today'] = $count;

    if ($count >= $target) {
        $debugData['blocked_reason_code'] = 'daily_target_reached';
        $debugData['blocked_reason_message'] = 'Meta diária já atingida para hoje.';
        $debugData['needed'] = 0;
        $debug = $debugData;
        return 0;
    }

    $needed = max(0, $target - $count);
    $debugData['needed'] = $needed;
    $created = 0;

    // Busca produtos que não foram postados recentemente (usando a configuração de dias)
    // Prioriza produtos com maior demand_count (mais procurados pela IA)
    try {
        $stMissingMedia = db()->prepare("
            SELECT COUNT(*)
            FROM ai_catalogo_models
            WHERE tenant_id = :tid
              AND is_active = 1
              AND (cover_webp IS NULL OR cover_webp = '')
        ");
        $stMissingMedia->execute([':tid' => $tenantId]);
        $debugData['excluded_missing_media_count'] = (int)$stMissingMedia->fetchColumn();

        $stRecentProducts = db()->prepare("
            SELECT COUNT(DISTINCT product_id)
            FROM concierge_status
            WHERE tenant_id = :tid
              AND product_id > 0
              AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stRecentProducts->bindValue(':tid', $tenantId, PDO::PARAM_INT);
        $stRecentProducts->bindValue(':days', $daysToReuse, PDO::PARAM_INT);
        $stRecentProducts->execute();
        $debugData['excluded_recent_count'] = (int)$stRecentProducts->fetchColumn();

        $stEligibleCount = db()->prepare("
            SELECT COUNT(*)
            FROM ai_catalogo_models m
            WHERE m.tenant_id = :tid1
              AND m.is_active = 1
              AND (m.cover_webp IS NOT NULL AND m.cover_webp != '')
              AND m.id NOT IN (
                  SELECT product_id
                  FROM concierge_status
                  WHERE tenant_id = :tid2
                    AND product_id > 0
                    AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
              )
        ");
        $stEligibleCount->bindValue(':tid1', $tenantId, PDO::PARAM_INT);
        $stEligibleCount->bindValue(':tid2', $tenantId, PDO::PARAM_INT);
        $stEligibleCount->bindValue(':days', $daysToReuse, PDO::PARAM_INT);
        $stEligibleCount->execute();
        $debugData['eligible_products_count'] = (int)$stEligibleCount->fetchColumn();

        // Verifica se demand_count existe
        $hasDemandCount = ai_groups_column_exists('ai_catalogo_models', 'demand_count');
        $orderBy = $hasDemandCount ? 'ORDER BY m.demand_count DESC, RAND()' : 'ORDER BY RAND()';

        $sql = "
            SELECT m.id, m.name, m.cover_webp, m.description, COALESCE(MIN(v.price), 0) AS min_price
            FROM ai_catalogo_models m
            LEFT JOIN ai_catalogo_variants v ON v.model_id = m.id AND v.tenant_id = m.tenant_id
            WHERE m.tenant_id = :tid1 
              AND m.is_active = 1
              AND (m.cover_webp IS NOT NULL AND m.cover_webp != '')
              AND m.id NOT IN (
                  SELECT product_id FROM concierge_status 
                  WHERE tenant_id = :tid2 AND product_id > 0 
                    AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
              )
            GROUP BY m.id
            $orderBy
            LIMIT :needed
        ";
        $stProds = db()->prepare($sql);
        $stProds->bindValue(':tid1', $tenantId, PDO::PARAM_INT);
        $stProds->bindValue(':tid2', $tenantId, PDO::PARAM_INT);
        $stProds->bindValue(':days', $daysToReuse, PDO::PARAM_INT);
        $stProds->bindValue(':needed', $needed, PDO::PARAM_INT);
        $stProds->execute();
        $prods = $stProds->fetchAll(PDO::FETCH_ASSOC);
        $debugData['selected_products_count'] = is_array($prods) ? count($prods) : 0;

        // Prepara o horário base para agendamento
        $baseScheduleTime = clone $dtNow;

        foreach ($prods as $index => $p) {
            // Agenda para hoje, com intervalo de 10 minutos entre eles
            // Para o primeiro produto, usa o horário base, para os próximos, incrementa o intervalo
            $currentScheduleTime = clone $baseScheduleTime;
            $currentScheduleTime->modify('+' . ($index * 10) . ' minute');
            $scheduledAt = $currentScheduleTime->format('Y-m-d H:i:s');

            $caption = trim((string)($p['description'] ?: $p['name']));
            if ($p['min_price'] > 0) {
                $caption .= "\n\n💰 Valor: R$ " . number_format($p['min_price'], 2, ',', '.');
            }
            $caption .= "\n\nPeça já o seu! 🚀";

            ai_create_concierge_status($tenantId, [
                'content' => $caption,
                'product_id' => $p['id'],
                'media_url' => ai_resolve_storage_url($p['cover_webp']),
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
                'repeat_count' => 3,
                'repeat_interval' => 1,
                'repeat_days' => '1,2,3,4,5',
                'payload_json' => [
                    'source' => 'ai_auto_suggestion',
                    'product_name' => $p['name'],
                    'auto_generated' => 1
                ]
            ]);
            $created++;
        }

        if ($created > 0) {
            if ($created < $needed) {
                $debugData['blocked_reason_code'] = 'partial_generation';
                $debugData['blocked_reason_message'] = 'Nem todos os posts necessários foram gerados por falta de produtos elegíveis.';
            } else {
                $debugData['blocked_reason_code'] = 'generated';
                $debugData['blocked_reason_message'] = 'Postagens automáticas geradas com sucesso.';
            }
        } else {
            $debugData['blocked_reason_code'] = 'no_eligible_products';
            $debugData['blocked_reason_message'] = 'Nenhum produto elegível para geração automática no momento.';
        }
    } catch (Throwable $e) {
        // Loga o erro para debug
        error_log('Erro ao gerar status inteligentes: ' . $e->getMessage());
        $debugData['blocked_reason_code'] = 'internal_error';
        $debugData['blocked_reason_message'] = 'Falha interna ao selecionar produtos para automação.';
    }

    $debug = $debugData;
    return $created;
}

function ai_groups_get_ai_bar_stats(int $tenantId): array
{
    $stats = [
        'catalog_count' => 0,
        'pending_campaigns' => 0,
        'suggestions_ready' => 0
    ];
    if ($tenantId <= 0) return $stats;

    try {
        // Conta produtos no catálogo IA
        $st = db()->prepare("SELECT COUNT(*) FROM ai_catalogo_models WHERE tenant_id = :tid AND is_active = 1");
        $st->execute([':tid' => $tenantId]);
        $stats['catalog_count'] = (int)$st->fetchColumn();

        // Campanhas que precisam de aprovação
        $st2 = db()->prepare("SELECT COUNT(*) FROM concierge_campaigns WHERE tenant_id = :tid AND status = 'needs_approval'");
        $st2->execute([':tid' => $tenantId]);
        $stats['pending_campaigns'] = (int)$st2->fetchColumn();
        
        $stats['suggestions_ready'] = $stats['pending_campaigns'];
    } catch (Throwable $e) {}

    return $stats;
}

function ai_groups_get_public_base_url(int $tenantId): string
{
    $publicUrl = rtrim(trim((string)ai_get_setting('ai_status_public_base_url', '', $tenantId)), '/');
    if ($publicUrl === '') {
        $publicUrl = rtrim(trim((string)ai_get_setting('ai_public_media_base_url', '', $tenantId)), '/');
    }

    if ($publicUrl === '' || stripos($publicUrl, 'localhost') !== false) {
        try {
            if (ai_groups_table_exists('saas_config_global')) {
                $stmt = db()->prepare("SELECT key_value FROM saas_config_global WHERE key_name = 'ai_modernpos_base_url' LIMIT 1");
                $stmt->execute();
                $publicUrl = rtrim(trim((string)$stmt->fetchColumn()), '/');
            }
        } catch (Throwable $e) {}
    }

    return rtrim($publicUrl, '/');
}

function ai_process_due_concierge_statuses(int $tenantId = 0, int $limit = 25): array
{
    ai_groups_ensure_status_schema();
    
    $generatedCount = 0;
    $generationDebug = [
        'blocked_reason_code' => '',
        'blocked_reason_message' => '',
        'target_daily' => 0,
        'already_scheduled_or_sent_today' => 0,
        'needed' => 0,
        'days_to_reuse' => 0,
        'interval_hours' => 0,
        'eligible_products_count' => 0,
        'selected_products_count' => 0,
        'excluded_recent_count' => 0,
        'excluded_missing_media_count' => 0,
    ];
    // Automação Inteligente: se habilitado, gera novas postagens se faltarem para hoje
    if ($tenantId > 0) {
        $generatedCount = ai_groups_generate_intelligent_statuses($tenantId, $generationDebug);
    }
    
    // Cleanup: removido para não marcar timeouts como erro (postagens são bem-sucedidas mesmo com timeout)

    // Evita concorrência: se já houver algo sendo enviado para este tenant, pula para não encavalar
    if ($tenantId > 0) {
        try {
            $stCheck = db()->prepare("SELECT id FROM concierge_status WHERE tenant_id = :tid AND status = 'sending' LIMIT 1");
            $stCheck->execute([':tid' => $tenantId]);
            if ($stCheck->fetch()) {
                return [
                    'due' => 0,
                    'dispatched' => 0,
                    'failed' => 0,
                    'items' => [],
                    'generated' => $generatedCount,
                    'generation_debug' => $generationDebug,
                    'message' => 'Já existe um disparo de status em andamento para este tenant.'
                ];
            }
        } catch (Throwable $e) {}
    }

    $statusPostingMode = ai_groups_status_posting_mode();
    $result = [
        'due' => 0,
        'dispatched' => 0,
        'failed' => 0,
        'items' => [],
        'generated' => $generatedCount,
        'generation_debug' => $generationDebug,
    ];

    if (!ai_groups_table_exists('concierge_status')) {
        return $result;
    }

    $due = ai_get_due_concierge_statuses($tenantId, $limit);
    $result['due'] = count($due);

    foreach ($due as $row) {
        $tid = (int)($row['tenant_id'] ?? 0);
        $statusId = (int)($row['id'] ?? 0);
        if ($tid <= 0 || $statusId <= 0) {
            continue;
        }
        if ($statusPostingMode === 'system') {
            ai_update_concierge_status($tid, $statusId, [
                'status' => 'sending',
                'attempt_count' => (int)($row['attempt_count'] ?? 0) + 1,
                'error_message' => '',
            ]);

            $resp = ai_groups_dispatch_status_via_system($tid, $row);
            $payloadJson = ai_groups_decode_json($row['payload_json'] ?? null);
            $payloadJson['dispatch_mode'] = 'system';
            $payloadJson['dispatch_response'] = [
                'ok' => !empty($resp['ok']),
                'http_code' => (int)($resp['http_code'] ?? 0),
                'error' => (string)($resp['error'] ?? ''),
                'external_message_id' => (string)($resp['external_message_id'] ?? ''),
                'updated_at' => date('c'),
            ];

            if (!empty($resp['ok'])) {
                // Lógica de Repostagem (agora cuida de marcar como 'sent' e incrementar post_count)
                ai_groups_handle_status_repost_logic($tid, $statusId, $row);

                $result['dispatched']++;
                $result['items'][] = [
                    'tenant_id' => $tid,
                    'status_id' => $statusId,
                    'mode' => 'system',
                    'ok' => true,
                    'http_code' => (int)($resp['http_code'] ?? 0),
                    'external_message_id' => (string)($resp['external_message_id'] ?? ''),
                ];
                continue;
            }

            $errRaw = $resp['error'] ?? '';
            $err = trim(is_array($errRaw) || is_object($errRaw) ? (string)json_encode($errRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$errRaw);
            
            // Verifica se é timeout — não marca como erro, deixa como 'sending' para o webhook atualizar depois
            $isTimeout = (stripos($err, 'timeout') !== false || (int)($resp['http_code'] ?? 0) === 0);
            if ($isTimeout) {
                // Apenas atualiza o payload com a resposta, mantém status 'sending'
                ai_update_concierge_status($tid, $statusId, [
                    'payload_json' => $payloadJson,
                ]);
                $result['items'][] = [
                    'tenant_id' => $tid,
                    'status_id' => $statusId,
                    'mode' => 'system',
                    'ok' => true, // Considera como "disparado" para não repetir
                    'http_code' => (int)($resp['http_code'] ?? 0),
                    'error' => $err,
                ];
                continue;
            }
            
            if ($err === '') {
                $err = 'Falha no disparo direto de status (HTTP ' . (int)($resp['http_code'] ?? 0) . ').';
            }
            ai_update_concierge_status($tid, $statusId, [
                'status' => 'error',
                'error_message' => $err,
                'sent_at' => null,
                'payload_json' => $payloadJson,
            ]);
            $result['failed']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'status_id' => $statusId,
                'mode' => 'system',
                'ok' => false,
                'http_code' => (int)($resp['http_code'] ?? 0),
                'error' => $err,
            ];
            continue;
        }

        $targetUrl = ai_groups_dispatch_webhook_url($tid, 'status');
        $token = ai_groups_store_token($tid);
        if ($targetUrl === '' || $token === '') {
            ai_update_concierge_status($tid, $statusId, [
                'status' => 'error',
                'error_message' => 'Webhook de disparo de status não configurado.',
            ]);
            $result['failed']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'status_id' => $statusId,
                'mode' => 'n8n',
                'ok' => false,
                'error' => 'Webhook de disparo de status não configurado.',
            ];
            continue;
        }

        ai_update_concierge_status($tid, $statusId, [
            'status' => 'sending',
            'attempt_count' => (int)($row['attempt_count'] ?? 0) + 1,
            'error_message' => '',
        ]);

        $payloadJson = ai_groups_decode_json($row['payload_json'] ?? null);
        $mediaUrls = ai_groups_collect_status_media_urls($row);
        
        $token = ai_groups_store_token($tid);
        $publicBase = ai_groups_get_public_base_url($tid);
        $callbackUrl = $publicBase . '/api/concierge/status_webhook.php?loja_id=' . $tid . '&status_id=' . $statusId . '&token=' . $token;

        $payload = [
            'source' => 'concierge_status_dispatch',
            'tenant_id' => $tid,
            'status_id' => $statusId,
            'status' => $row['status'] ?? 'pending',
            'content' => (string)($row['content'] ?? ''),
            'media_url' => (string)($row['media_url'] ?? ''),
            'media_urls' => $mediaUrls,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'payload_json' => $payloadJson,
            'callback' => [
                'url' => $callbackUrl,
                'method' => 'POST',
                'headers' => [
                    'X-Concierge-Token' => $token,
                    'ngrok-skip-browser-warning' => '1',
                ],
                'body_template' => [
                    'tenant_id' => $tid,
                    'status_id' => $statusId,
                    'status' => '{{status}}',
                    'sent_at' => '{{sent_at}}',
                    'error_message' => '{{error_message}}',
                    'payload_json' => '{{payload_json}}',
                ],
            ],
        ];

        $resp = ai_groups_http_post_json($targetUrl, $payload, [
            'X-Concierge-Token: ' . $token,
            'X-Store-Id: ' . $tid,
        ]);

        if (!empty($resp['ok'])) {
            $result['dispatched']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'status_id' => $statusId,
                'mode' => 'n8n',
                'ok' => true,
                'http_code' => (int)($resp['http_code'] ?? 0),
            ];
            continue;
        }

        $errRaw = $resp['error'] ?? '';
        $err = trim(is_array($errRaw) || is_object($errRaw) ? (string)json_encode($errRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$errRaw);
        
        // Verifica se é timeout — não marca como erro, deixa como 'sending' para o webhook atualizar depois
        $isTimeout = (stripos($err, 'timeout') !== false || (int)($resp['http_code'] ?? 0) === 0);
        if ($isTimeout) {
            // Apenas mantém status 'sending'
            $result['dispatched']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'status_id' => $statusId,
                'mode' => 'n8n',
                'ok' => true,
                'http_code' => (int)($resp['http_code'] ?? 0),
            ];
            continue;
        }
        
        if ($err === '') {
            $err = 'Falha ao chamar webhook N8N de status (HTTP ' . (int)($resp['http_code'] ?? 0) . ').';
        }

        ai_update_concierge_status($tid, $statusId, [
            'status' => 'error',
            'error_message' => $err,
            'sent_at' => null,
        ]);

        $result['failed']++;
        $result['items'][] = [
            'tenant_id' => $tid,
            'status_id' => $statusId,
            'mode' => 'n8n',
            'ok' => false,
            'http_code' => (int)($resp['http_code'] ?? 0),
            'error' => $err,
        ];
    }

    return $result;
}
function ai_process_due_concierge_campaigns(int $tenantId = 0, int $limit = 25): array
{
    ai_groups_ensure_broadcast_schema();
    
    // Cleanup: Campanhas travadas em 'sending'
    try {
        $stCleanup = db()->prepare("
            UPDATE concierge_campaigns 
            SET status = 'failed', 
                last_error = 'Timeout: O sistema não recebeu confirmação da API após 15 minutos.' 
            WHERE status = 'sending' 
              AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stCleanup->execute();
    } catch (Throwable $e) {}

    // Evita concorrência: se já houver algo sendo enviado para este tenant, pula para não encavalar
    if ($tenantId > 0) {
        try {
            $stCheck = db()->prepare("SELECT id FROM concierge_campaigns WHERE tenant_id = :tid AND status = 'sending' LIMIT 1");
            $stCheck->execute([':tid' => $tenantId]);
            if ($stCheck->fetch()) {
                return [
                    'due' => 0,
                    'dispatched' => 0,
                    'failed' => 0,
                    'items' => [],
                    'message' => 'Já existe um disparo de campanha em andamento para este tenant.'
                ];
            }
        } catch (Throwable $e) {}
    }

    $result = [
        'due' => 0,
        'dispatched' => 0,
        'failed' => 0,
        'items' => [],
    ];
    $campaignPostingMode = ai_groups_campaign_posting_mode();

    if (!ai_groups_table_exists('concierge_campaigns')) {
        return $result;
    }

    $due = ai_get_due_concierge_campaigns($tenantId, $limit);
    $result['due'] = count($due);

    foreach ($due as $campaign) {
        $tid = (int)($campaign['tenant_id'] ?? 0);
        $campaignId = (int)($campaign['id'] ?? 0);
        if ($tid <= 0 || $campaignId <= 0) {
            continue;
        }

        // Reset broadcasts to pending for new cycle
        if (ai_groups_table_exists('concierge_broadcasts')) {
            $resetBroadcasts = db()->prepare("
                UPDATE concierge_broadcasts
                SET status = 'pending',
                    error_message = NULL,
                    sent_at = NULL,
                    external_message_id = NULL,
                    updated_at = NOW()
                WHERE tenant_id = :tid
                  AND campaign_id = :cid
            ");
            $resetBroadcasts->execute([':tid' => $tid, ':cid' => $campaignId]);
        }

        if ($campaignPostingMode === 'system') {
            $stubMsg = 'Disparo de campanhas via sistema está em preparação. Altere para N8N para executar envios.';
            ai_update_concierge_campaign($tid, $campaignId, [
                'status' => 'failed',
                'last_error' => $stubMsg,
                'updated_by' => 0,
            ]);
            $result['failed']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'campaign_id' => $campaignId,
                'mode' => 'system',
                'ok' => false,
                'error' => $stubMsg,
            ];
            continue;
        }

        $targets = ai_get_campaign_targets($tid, $campaignId);
        if (empty($targets)) {
            ai_update_concierge_campaign($tid, $campaignId, [
                'status' => 'failed',
                'last_error' => 'Campanha sem grupos alvo para disparo.',
                'updated_by' => 0,
            ]);
            $result['failed']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'campaign_id' => $campaignId,
                'ok' => false,
                'error' => 'Campanha sem grupos alvo para disparo.',
            ];
            continue;
        }

        $targetUrl = ai_groups_dispatch_webhook_url($tid, 'campaign');
        $token = ai_groups_store_token($tid);
        if ($targetUrl === '' || $token === '') {
            ai_update_concierge_campaign($tid, $campaignId, [
                'status' => 'failed',
                'last_error' => 'Webhook de disparo de campanhas não configurado.',
                'updated_by' => 0,
            ]);
            $result['failed']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'campaign_id' => $campaignId,
                'ok' => false,
                'error' => 'Webhook de disparo de campanhas não configurado.',
            ];
            continue;
        }

        ai_update_concierge_campaign($tid, $campaignId, [
            'status' => 'sending',
            'webhook_requested_at' => date('Y-m-d H:i:s'),
            'last_error' => '',
            'updated_by' => 0,
        ]);

        $payloadJson = ai_groups_decode_json($campaign['payload_json'] ?? null);
        $callbackUrl = rtrim((string)ROOT_URL, '/') . '/api/concierge/campaign_status_webhook.php?loja_id=' . $tid;
        $ctaMap = [
            'chama' => '📲 Chama no privado!',
            'manda' => '💬 Me manda mensagem!',
            'reserva' => '🛍️ Quer reservar?',
            'quero' => '🙋‍♂️ Eu Quero!',
            'corre' => '⚡ Corre! Últimas unidades!',
        ];
        $ctaKey = trim((string)($payloadJson['cta'] ?? ''));
        $ctaText = trim((string)($payloadJson['cta_text'] ?? ''));
        if ($ctaText === '' && $ctaKey !== '') {
            $ctaText = $ctaMap[$ctaKey] ?? $ctaKey;
        }
        $mainCtaLink = (string)($payloadJson['main_cta_link'] ?? '');
        $welcomeMessage = (string)($payloadJson['welcome_message'] ?? '');
        $productVariations = [];
        if (!empty($payloadJson['product_variations']) && is_array($payloadJson['product_variations'])) {
            $productVariations = array_values(array_filter($payloadJson['product_variations'], function ($variation) {
                return is_array($variation);
            }));
        }
        $mediaUrls = (array)($payloadJson['media_urls'] ?? []);
        $mediaCount = count(array_filter($mediaUrls, function($url) { return trim((string)$url) !== ''; }));
        if ($mediaCount === 0 && !empty(trim((string)($campaign['media_url'] ?? '')))) {
            $mediaCount = 1;
        }
        $instanceNumber = preg_replace('/[^0-9]/', '', (string)ai_get_setting('ai_whatsapp_number', '', $tid));
        $payload = [
            'source' => 'concierge_campaign_dispatch',
            'tenant_id' => $tid,
            'campaign_id' => $campaignId,
            'campaign' => [
                'id' => $campaignId,
                'title' => (string)($campaign['title'] ?? ''),
                'content' => (string)($campaign['content'] ?? ''),
                'media_url' => (string)($campaign['media_url'] ?? ''),
                'scheduled_at' => $campaign['scheduled_at'] ?? null,
                'payload_json' => $payloadJson,
                'cta' => $ctaText,
                'cta_key' => $ctaKey,
                'main_cta_link' => $mainCtaLink,
                'welcome_message' => $welcomeMessage,
                'media_count' => $mediaCount,
                'media_urls' => $mediaUrls,
                'product_variations' => $productVariations,
                'instance_number' => $instanceNumber,
            ],
            'targets' => $targets,
            'callback' => [
                'url' => $callbackUrl,
                'method' => 'POST',
                'headers' => [
                    'X-Concierge-Token' => $token,
                    'ngrok-skip-browser-warning' => '1',
                ],
                'body_template' => [
                    'tenant_id' => $tid,
                    'campaign_id' => $campaignId,
                    'group_id' => '{{group_id}}',
                    'status' => '{{status}}',
                    'external_message_id' => '{{external_message_id}}',
                    'error_message' => '{{error_message}}',
                ],
            ],
        ];

        $resp = ai_groups_http_post_json($targetUrl, $payload, [
            'X-Concierge-Token: ' . $token,
            'X-Store-Id: ' . $tid,
        ]);

        if (!empty($resp['ok'])) {
            $result['dispatched']++;
            $result['items'][] = [
                'tenant_id' => $tid,
                'campaign_id' => $campaignId,
                'ok' => true,
                'http_code' => (int)($resp['http_code'] ?? 0),
            ];

            $executionId = trim((string)($resp['json']['execution_id'] ?? $resp['json']['id'] ?? ''));
            if ($executionId !== '') {
                ai_update_concierge_campaign($tid, $campaignId, [
                    'n8n_execution_id' => $executionId,
                    'updated_by' => 0,
                ]);
            }
            continue;
        }

        $errRaw = $resp['error'] ?? '';
        $err = trim(is_array($errRaw) || is_object($errRaw) ? (string)json_encode($errRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$errRaw);
        if ($err === '') {
            $err = 'Falha ao chamar webhook N8N de campanhas (HTTP ' . (int)($resp['http_code'] ?? 0) . ').';
        }
        ai_update_concierge_campaign($tid, $campaignId, [
            'status' => 'failed',
            'last_error' => $err,
            'updated_by' => 0,
        ]);
        $result['failed']++;
        $result['items'][] = [
            'tenant_id' => $tid,
            'campaign_id' => $campaignId,
            'ok' => false,
            'http_code' => (int)($resp['http_code'] ?? 0),
            'error' => $err,
        ];
    }

    return $result;
}

/**
 * Agenda campanha para data/hora específica.
 */
function ai_schedule_concierge_campaign(int $tenantId, int $campaignId, string $scheduledAt, int $userId = 0): bool
{
    if ($tenantId <= 0 || $campaignId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return false;
    }

    $scheduledAt = trim($scheduledAt);
    if ($scheduledAt === '') {
        return false;
    }

    // Verifica conflito de horário (gap de 5 min)
    $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, $scheduledAt, $campaignId, 5);
    if ($conflict) {
        return false;
    }

    try {
        $stmt = db()->prepare("
            UPDATE concierge_campaigns
            SET status = 'scheduled',
                scheduled_at = :scheduled_at,
                updated_by = :uid,
                updated_at = NOW()
            WHERE tenant_id = :tid
              AND id = :cid
              AND status <> 'canceled'
              AND (
                    status NOT IN ('sent', 'completed')
                    OR allow_requeue = 1
              )
            LIMIT 1
        ");
        $stmt->execute([
            ':scheduled_at' => $scheduledAt,
            ':uid' => $userId,
            ':tid' => $tenantId,
            ':cid' => $campaignId,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Retorna a lista de grupos do WhatsApp vinculados ao tenant.
 */
function ai_get_concierge_groups(int $tenantId, bool $onlyActive = true): array
{
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_groups')) {
        return [];
    }

    try {
        $sql = "SELECT * FROM concierge_groups WHERE tenant_id = :tid";
        if ($onlyActive) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY name ASC";

        $stmt = db()->prepare($sql);
        $stmt->bindValue(':tid', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Retorna o resumo de estatísticas de grupos para o dashboard.
 */
function ai_get_groups_stats(int $tenantId): array
{
    $stats = [
        'total_groups' => 0,
        'total_members' => 0,
        'total_broadcasts' => 0,
    ];
    if ($tenantId <= 0) {
        return $stats;
    }

    if (ai_groups_table_exists('concierge_groups')) {
        try {
            $stmt = db()->prepare("
                SELECT
                    COUNT(*) as total_groups,
                    COALESCE(SUM(member_count), 0) as total_members
                FROM concierge_groups
                WHERE tenant_id = :tid AND is_active = 1
            ");
            $stmt->bindValue(':tid', $tenantId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $stats['total_groups'] = (int)($row['total_groups'] ?? 0);
                $stats['total_members'] = (int)($row['total_members'] ?? 0);
            }
        } catch (Throwable $e) {
        }
    }

    if (ai_groups_table_exists('concierge_broadcasts') && ai_groups_table_exists('concierge_campaigns')) {
        try {
            $stmtB = db()->prepare("
                SELECT COUNT(*) as total_broadcasts
                FROM concierge_broadcasts b
                JOIN concierge_campaigns c ON b.campaign_id = c.id
                WHERE c.tenant_id = :tid AND b.status = 'sent'
            ");
            $stmtB->bindValue(':tid', $tenantId, PDO::PARAM_INT);
            $stmtB->execute();
            $stats['total_broadcasts'] = (int)$stmtB->fetchColumn();
        } catch (Throwable $e) {
        }
    }

    return $stats;
}

/**
 * Salva ou atualiza um grupo vindo da Evolution API.
 */
function ai_sync_evolution_group(int $tenantId, array $groupData): int
{
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_groups')) {
        return 0;
    }

    $remoteJid = trim((string)($groupData['id'] ?? $groupData['jid'] ?? $groupData['groupJid'] ?? $groupData['remote_jid'] ?? ''));
    if ($remoteJid === '') {
        return 0;
    }

    $name = trim((string)($groupData['subject'] ?? $groupData['name'] ?? $groupData['groupName'] ?? $groupData['title'] ?? $groupData['pushName'] ?? 'Grupo sem nome'));
    if ($name === '') {
        $name = 'Grupo sem nome';
    }

    $participants = $groupData['participants'] ?? $groupData['members'] ?? $groupData['participantsData'] ?? [];
    $memberCount = is_array($participants) ? count($participants) : 0;
    if ($memberCount <= 0) {
        $memberCount = max(
            0,
            (int)($groupData['participants_count']
                ?? $groupData['participantsCount']
                ?? $groupData['participantCount']
                ?? $groupData['size']
                ?? $groupData['memberCount']
                ?? $groupData['membersCount']
                ?? 0)
        );
    }

    try {
        $stmt = db()->prepare("SELECT id FROM concierge_groups WHERE tenant_id = :tid AND remote_jid = :jid");
        $stmt->execute([':tid' => $tenantId, ':jid' => $remoteJid]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $stmt = db()->prepare("
                UPDATE concierge_groups
                SET name = :name, member_count = :count, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':count' => $memberCount,
                ':id' => $existing
            ]);
            return (int)$existing;
        }

        $stmt = db()->prepare("
            INSERT INTO concierge_groups (tenant_id, store_id, remote_jid, name, member_count)
            VALUES (:tid, :sid, :jid, :name, :count)
        ");
        $stmt->execute([
            ':tid' => $tenantId,
            ':sid' => $tenantId,
            ':jid' => $remoteJid,
            ':name' => $name,
            ':count' => $memberCount
        ]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Atualiza grupos alvo com estratégia de upsert.
 */
function ai_upsert_campaign_broadcast_targets(int $tenantId, int $campaignId, array $groupIds): int
{
    return ai_replace_campaign_groups($tenantId, $campaignId, $groupIds);
}

/**
 * Mapeia status de UI/API para enum persistido no banco.
 */
function ai_groups_normalize_campaign_status(string $status): string
{
    $status = strtolower(trim($status));
    $map = [
        'draft' => 'draft',
        'rascunho' => 'draft',
        'needs_approval' => 'needs_approval',
        'aprovacao' => 'needs_approval',
        'scheduled' => 'scheduled',
        'agendado' => 'scheduled',
        'pending' => 'pending',
        'queued' => 'queued',
        'sending' => 'sending',
        'enviando' => 'sending',
        'sent' => 'sent',
        'enviado' => 'sent',
        'completed' => 'completed',
        'finalizado' => 'completed',
        'error' => 'error',
        'failed' => 'failed',
        'erro' => 'error',
        'canceled' => 'canceled',
        'cancelado' => 'canceled',
    ];

    return $map[$status] ?? $status;
}

/**
 * Mapeia status de broadcast.
 */
function ai_groups_normalize_broadcast_status(string $status): string
{
    $status = strtolower(trim($status));
    $allowed = ['pending', 'queued', 'sending', 'sent', 'error', 'skipped'];
    return in_array($status, $allowed, true) ? $status : 'pending';
}

/**
 * Sanitiza IDs de grupos enviados pelo front/API.
 */
function ai_groups_sanitize_group_ids(int $tenantId, array $groupIds): array
{
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_groups')) {
        return [];
    }

    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static function ($v) {
        return $v > 0;
    })));

    if (empty($groupIds)) {
        return [];
    }

    try {
        $in = implode(',', array_fill(0, count($groupIds), '?'));
        $stmt = db()->prepare("SELECT id FROM concierge_groups WHERE tenant_id = ? AND id IN ($in)");
        $stmt->execute(array_merge([$tenantId], $groupIds));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Padroniza payload de retorno para consumo no front/API.
 */
function ai_groups_response(bool $error, string $message = '', $data = null): array
{
    return [
        'error' => $error,
        'message' => $message,
        'data' => $data,
    ];
}

/**
 * Lista campanhas com paginação e resumo de entrega.
 */
function ai_get_concierge_campaigns(int $tenantId, array $filters = [], int $page = 1, int $limit = 20): array
{
    $result = [
        'items' => [],
        'total' => 0,
        'page' => max(1, $page),
        'limit' => max(1, min(100, $limit)),
    ];

    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return $result;
    }

    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;

    $where = ['c.tenant_id = :tid'];
    $params = [':tid' => $tenantId];

    if (!empty($filters['status'])) {
        $statusStr = trim((string)$filters['status']);
        $statusList = array_map('ai_groups_normalize_campaign_status', array_filter(array_map('trim', explode(',', $statusStr))));
        if (count($statusList) > 1) {
            $placeholders = [];
            foreach ($statusList as $i => $s) {
                $key = ':status' . $i;
                $placeholders[] = $key;
                $params[$key] = $s;
            }
            $where[] = 'c.status IN (' . implode(',', $placeholders) . ')';
        } else {
            $status = ai_groups_normalize_campaign_status((string)$filters['status']);
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }
    }

    if (!empty($filters['search'])) {
        $where[] = '(c.title LIKE :q OR c.content LIKE :q)';
        $params[':q'] = '%' . trim((string)$filters['search']) . '%';
    }

    if (!empty($filters['date_from'])) {
        $where[] = 'DATE(c.created_at) >= :dfrom';
        $params[':dfrom'] = (string)$filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = 'DATE(c.created_at) <= :dto';
        $params[':dto'] = (string)$filters['date_to'];
    }

    $whereSql = implode(' AND ', $where);

    try {
        $stmtTotal = db()->prepare("SELECT COUNT(*) FROM concierge_campaigns c WHERE $whereSql");
        $stmtTotal->execute($params);
        $total = (int)$stmtTotal->fetchColumn();
        $result['total'] = $total;

        if ($total <= 0) {
            return $result;
        }

        $sql = "
            SELECT
                c.*,
                COUNT(b.id) AS total_targets,
                SUM(CASE WHEN b.status = 'sent' THEN 1 ELSE 0 END) AS sent_targets,
                SUM(CASE WHEN b.status IN ('error', 'skipped') THEN 1 ELSE 0 END) AS failed_targets,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name ASC SEPARATOR '||') AS group_names_csv
            FROM concierge_campaigns c
            LEFT JOIN concierge_broadcasts b ON b.campaign_id = c.id
            LEFT JOIN concierge_groups g ON g.id = b.group_id
            WHERE $whereSql
            GROUP BY c.id
            ORDER BY
                CASE c.status
                    WHEN 'sending' THEN 1
                    WHEN 'scheduled' THEN 2
                    WHEN 'needs_approval' THEN 3
                    ELSE 4
                END,
                c.created_at DESC
            LIMIT $limit OFFSET $offset
        ";

        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['tenant_id'] = (int)$item['tenant_id'];
            $item['product_id'] = (int)($item['product_id'] ?? 0);
            $item['created_by'] = (int)($item['created_by'] ?? 0);
            $item['updated_by'] = (int)($item['updated_by'] ?? 0);
            $item['allow_requeue'] = (int)($item['allow_requeue'] ?? 1);
            $item['total_targets'] = (int)($item['total_targets'] ?? 0);
            $item['sent_targets'] = (int)($item['sent_targets'] ?? 0);
            $item['failed_targets'] = (int)($item['failed_targets'] ?? 0);
            $groupNamesCsv = trim((string)($item['group_names_csv'] ?? ''));
            $item['group_names'] = $groupNamesCsv !== ''
                ? array_values(array_filter(array_map('trim', explode('||', $groupNamesCsv)), static function ($name) {
                    return $name !== '';
                }))
                : [];
            unset($item['group_names_csv']);
        }
        unset($item);

        $result['items'] = $items;
        return $result;
    } catch (Throwable $e) {
        return $result;
    }
}

/**
 * Retorna detalhe de uma campanha com grupos e resumo de status.
 */
function ai_get_concierge_campaign(int $tenantId, int $campaignId): ?array
{
    if ($tenantId <= 0 || $campaignId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return null;
    }

    try {
        $stmt = db()->prepare("SELECT * FROM concierge_campaigns WHERE tenant_id = :tid AND id = :cid LIMIT 1");
        $stmt->execute([':tid' => $tenantId, ':cid' => $campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            return null;
        }

        $campaign['id'] = (int)$campaign['id'];
        $campaign['tenant_id'] = (int)$campaign['tenant_id'];
        $campaign['product_id'] = (int)($campaign['product_id'] ?? 0);
        $campaign['created_by'] = (int)($campaign['created_by'] ?? 0);
        $campaign['updated_by'] = (int)($campaign['updated_by'] ?? 0);
        $campaign['allow_requeue'] = (int)($campaign['allow_requeue'] ?? 1);
        $campaign['targets'] = ai_get_campaign_targets($tenantId, $campaignId);
        $campaign['summary'] = ai_get_campaign_delivery_summary($tenantId, $campaignId);

        return $campaign;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Cria campanha.
 */
function ai_create_concierge_campaign(int $tenantId, array $payload): int
{
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return 0;
    }

    $title = trim((string)($payload['title'] ?? 'Campanha sem título'));
    $content = trim((string)($payload['content'] ?? ''));
    if ($content === '') {
        $content = 'Conteúdo não informado.';
    }

    $status = ai_groups_normalize_campaign_status((string)($payload['status'] ?? 'draft'));
    $productId = (int)($payload['product_id'] ?? 0);
    $mediaUrl = trim((string)($payload['media_url'] ?? ''));
    $scheduledAt = trim((string)($payload['scheduled_at'] ?? ''));
    $createdBy = (int)($payload['created_by'] ?? 0);
    $payloadJson = $payload['payload_json'] ?? null;
    $payloadJson = is_array($payloadJson) ? json_encode($payloadJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$payloadJson;
    $allowRequeue = (int)($payload['allow_requeue'] ?? 1);

    try {
        $stmt = db()->prepare("
            INSERT INTO concierge_campaigns
            (tenant_id, product_id, title, content, media_url, status, scheduled_at, created_by, updated_by, payload_json, allow_requeue, created_at, updated_at)
            VALUES
            (:tid, :pid, :title, :content, :media, :status, :scheduled_at, :created_by, :updated_by, :payload_json, :allow_requeue, NOW(), NOW())
        ");

        $stmt->execute([
            ':tid' => $tenantId,
            ':pid' => $productId > 0 ? $productId : null,
            ':title' => $title,
            ':content' => $content,
            ':media' => $mediaUrl !== '' ? $mediaUrl : null,
            ':status' => $status,
            ':scheduled_at' => $scheduledAt !== '' ? $scheduledAt : null,
            ':created_by' => $createdBy,
            ':updated_by' => $createdBy,
            ':payload_json' => $payloadJson !== '' ? $payloadJson : null,
            ':allow_requeue' => $allowRequeue,
        ]);

        $campaignId = (int)db()->lastInsertId();
        if ($campaignId > 0 && !empty($payload['group_ids']) && is_array($payload['group_ids'])) {
            ai_replace_campaign_groups($tenantId, $campaignId, $payload['group_ids']);
        }

        return $campaignId;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Atualiza campanha existente.
 */
function ai_update_concierge_campaign(int $tenantId, int $campaignId, array $payload): bool
{
    if ($tenantId <= 0 || $campaignId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return false;
    }

    $sets = [];
    $params = [':tid' => $tenantId, ':cid' => $campaignId];

    if (array_key_exists('title', $payload)) {
        $sets[] = 'title = :title';
        $params[':title'] = trim((string)$payload['title']);
    }

    if (array_key_exists('content', $payload)) {
        $sets[] = 'content = :content';
        $params[':content'] = trim((string)$payload['content']);
    }

    if (array_key_exists('media_url', $payload)) {
        $sets[] = 'media_url = :media';
        $media = trim((string)$payload['media_url']);
        $params[':media'] = $media !== '' ? $media : null;
    }

    if (array_key_exists('product_id', $payload)) {
        $sets[] = 'product_id = :pid';
        $pid = (int)$payload['product_id'];
        $params[':pid'] = $pid > 0 ? $pid : null;
    }

    if (array_key_exists('status', $payload)) {
        $sets[] = 'status = :status';
        $params[':status'] = ai_groups_normalize_campaign_status((string)$payload['status']);
    }
    if (array_key_exists('last_error', $payload)) {
        $sets[] = 'last_error = :last_error';
        $params[':last_error'] = trim((string)$payload['last_error']);
    }

    if (array_key_exists('scheduled_at', $payload)) {
        $sets[] = 'scheduled_at = :scheduled_at';
        $sch = trim((string)$payload['scheduled_at']);
        $params[':scheduled_at'] = $sch !== '' ? $sch : null;
    }

    if (array_key_exists('updated_by', $payload)) {
        $sets[] = 'updated_by = :updated_by';
        $params[':updated_by'] = (int)$payload['updated_by'];
    }
    if (array_key_exists('webhook_requested_at', $payload)) {
        $sets[] = 'webhook_requested_at = :webhook_requested_at';
        $requestedAt = trim((string)$payload['webhook_requested_at']);
        $params[':webhook_requested_at'] = $requestedAt !== '' ? $requestedAt : null;
    }
    if (array_key_exists('n8n_execution_id', $payload)) {
        $sets[] = 'n8n_execution_id = :n8n_execution_id';
        $executionId = trim((string)$payload['n8n_execution_id']);
        $params[':n8n_execution_id'] = $executionId !== '' ? $executionId : null;
    }

    if (array_key_exists('payload_json', $payload)) {
        $sets[] = 'payload_json = :payload_json';
        $pj = $payload['payload_json'];
        $pj = is_array($pj) ? json_encode($pj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$pj;
        $params[':payload_json'] = $pj !== '' ? $pj : null;
    }

    if (array_key_exists('allow_requeue', $payload)) {
        $sets[] = 'allow_requeue = :allow_requeue';
        $params[':allow_requeue'] = (int)$payload['allow_requeue'];
    }

    if (empty($sets) && !array_key_exists('group_ids', $payload)) {
        return true;
    }

    try {
        if (!empty($sets)) {
            $sql = 'UPDATE concierge_campaigns SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE tenant_id = :tid AND id = :cid LIMIT 1';
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
        }

        if (array_key_exists('group_ids', $payload) && is_array($payload['group_ids'])) {
            ai_replace_campaign_groups($tenantId, $campaignId, $payload['group_ids']);
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Define grupos alvo da campanha em concierge_broadcasts.
 */
function ai_replace_campaign_groups(int $tenantId, int $campaignId, array $groupIds): int
{
    if (
        $tenantId <= 0
        || $campaignId <= 0
        || !ai_groups_table_exists('concierge_campaigns')
        || !ai_groups_table_exists('concierge_broadcasts')
    ) {
        return 0;
    }

    $groupIds = ai_groups_sanitize_group_ids($tenantId, $groupIds);

    try {
        db()->beginTransaction();

        $del = db()->prepare("DELETE FROM concierge_broadcasts WHERE tenant_id = :tid AND campaign_id = :cid");
        $del->execute([':tid' => $tenantId, ':cid' => $campaignId]);

        $inserted = 0;
        if (!empty($groupIds)) {
            $ins = db()->prepare("
                INSERT INTO concierge_broadcasts (tenant_id, campaign_id, group_id, status, created_at, updated_at)
                VALUES (:tid, :cid, :gid, 'pending', NOW(), NOW())
            ");
            foreach ($groupIds as $gid) {
                $ins->execute([':tid' => $tenantId, ':cid' => $campaignId, ':gid' => $gid]);
                $inserted++;
            }
        }

        db()->commit();
        return $inserted;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return 0;
    }
}

/**
 * Retorna grupos vinculados à campanha.
 */
function ai_get_campaign_targets(int $tenantId, int $campaignId): array
{
    if (
        $tenantId <= 0
        || $campaignId <= 0
        || !ai_groups_table_exists('concierge_broadcasts')
        || !ai_groups_table_exists('concierge_groups')
    ) {
        return [];
    }

    try {
        $instanceNumber = preg_replace('/[^0-9]/', '', (string)ai_get_setting('ai_whatsapp_number', '', $tenantId));
        $stmt = db()->prepare("
            SELECT
                b.id,
                b.group_id,
                b.status,
                b.external_message_id,
                b.error_message,
                b.sent_at,
                g.name AS group_name,
                g.remote_jid,
                g.member_count
            FROM concierge_broadcasts b
            INNER JOIN concierge_groups g ON g.id = b.group_id
            WHERE b.tenant_id = :tid AND b.campaign_id = :cid
            ORDER BY g.name ASC
        ");
        $stmt->execute([':tid' => $tenantId, ':cid' => $campaignId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['group_id'] = (int)$row['group_id'];
            $row['member_count'] = (int)($row['member_count'] ?? 0);
            $row['instance_number'] = $instanceNumber;
        }
        unset($row);
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Resumo de entrega por campanha.
 */
function ai_get_campaign_delivery_summary(int $tenantId, int $campaignId): array
{
    $summary = [
        'total' => 0,
        'pending' => 0,
        'queued' => 0,
        'sending' => 0,
        'sent' => 0,
        'error' => 0,
        'skipped' => 0,
    ];

    if ($tenantId <= 0 || $campaignId <= 0 || !ai_groups_table_exists('concierge_broadcasts')) {
        return $summary;
    }

    try {
        $stmt = db()->prepare("
            SELECT status, COUNT(*) AS qty
            FROM concierge_broadcasts
            WHERE tenant_id = :tid AND campaign_id = :cid
            GROUP BY status
        ");
        $stmt->execute([':tid' => $tenantId, ':cid' => $campaignId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = ai_groups_normalize_broadcast_status((string)$row['status']);
            $qty = (int)$row['qty'];
            $summary[$status] = $qty;
            $summary['total'] += $qty;
        }
    } catch (Throwable $e) {
    }

    return $summary;
}

/**
 * Atualiza status de disparo para campanha/grupo.
 */
function ai_mark_broadcast_status(
    int $tenantId,
    int $campaignId,
    int $groupId,
    string $status,
    string $externalMessageId = '',
    string $errorMessage = ''
): bool {
    if ($tenantId <= 0 || $campaignId <= 0 || $groupId <= 0 || !ai_groups_table_exists('concierge_broadcasts')) {
        return false;
    }

    $status = ai_groups_normalize_broadcast_status($status);
    $externalMessageId = trim($externalMessageId);
    $errorMessage = trim($errorMessage);

    // Usa timezone da loja para registrar o dia no histórico
    $tz = ai_get_setting('timezone', 'America/Sao_Paulo', $tenantId);
    try {
        $dtNow = new DateTime('now', new DateTimeZone($tz));
        $today = $dtNow->format('Y-m-d');
    } catch (Throwable $e) {
        $today = date('Y-m-d');
    }

    try {
        if ($externalMessageId !== '') {
            $chk = db()->prepare("
                SELECT id, status
                FROM concierge_broadcasts
                WHERE tenant_id = :tid
                  AND campaign_id = :cid
                  AND group_id = :gid
                  AND external_message_id = :mid
                LIMIT 1
            ");
            $chk->execute([
                ':tid' => $tenantId,
                ':cid' => $campaignId,
                ':gid' => $groupId,
                ':mid' => $externalMessageId,
            ]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);
            if ($existing && ($existing['status'] ?? '') === $status && $errorMessage === '') {
                return true;
            }
        }
        $sql = "
            INSERT INTO concierge_broadcasts
            (tenant_id, campaign_id, group_id, status, external_message_id, error_message, sent_at, updated_at, created_at)
            VALUES
            (:tid, :cid, :gid, :status, :mid, :err, CASE WHEN :status_sent = 1 THEN NOW() ELSE NULL END, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                external_message_id = CASE
                    WHEN VALUES(external_message_id) IS NULL OR VALUES(external_message_id) = '' THEN external_message_id
                    ELSE VALUES(external_message_id)
                END,
                error_message = VALUES(error_message),
                sent_at = CASE
                    WHEN VALUES(status) IN ('sent','sending') THEN COALESCE(sent_at, NOW())
                    ELSE sent_at
                END,
                updated_at = NOW()
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':tid' => $tenantId,
            ':cid' => $campaignId,
            ':gid' => $groupId,
            ':status' => $status,
            ':mid' => $externalMessageId,
            ':err' => $errorMessage,
            ':status_sent' => in_array($status, ['sent', 'sending'], true) ? 1 : 0,
        ]);

        // Atualiza o success_history_json do grupo se o status for 'sent'
        if ($status === 'sent' && ai_groups_table_exists('concierge_groups')) {
            try {
                // Obtém o success_history_json atual do grupo
                $stmtGroup = db()->prepare("
                    SELECT settings_json
                    FROM concierge_groups
                    WHERE id = :gid AND tenant_id = :tid
                    LIMIT 1
                ");
                $stmtGroup->execute([':gid' => $groupId, ':tid' => $tenantId]);
                $groupRow = $stmtGroup->fetch(PDO::FETCH_ASSOC);

                $settings = [];
                if ($groupRow && !empty($groupRow['settings_json'])) {
                    $decoded = ai_groups_decode_json($groupRow['settings_json']);
                    if (is_array($decoded)) {
                        $settings = $decoded;
                    }
                }

                // Normaliza histórico existente (string simples -> formato rico)
                $successHistory = $settings['success_history_json'] ?? [];
                if (!is_array($successHistory)) {
                    $successHistory = [];
                }
                foreach ($successHistory as $d => $val) {
                    if (!is_array($val)) {
                        $successHistory[$d] = [
                            'sent' => 1,
                            'planned' => max(1, (int)($successHistory[$d]['planned'] ?? 1)),
                            'failed' => 0,
                        ];
                    } else {
                        $sent = max(0, (int)($val['sent'] ?? 0));
                        $planned = max($sent, (int)($val['planned'] ?? $sent));
                        $failed = max(0, (int)($val['failed'] ?? 0));
                        $successHistory[$d] = [
                            'sent' => $sent,
                            'planned' => $planned,
                            'failed' => $failed,
                        ];
                    }
                }

                // Incrementa o dia atual
                if (!isset($successHistory[$today]) || !is_array($successHistory[$today])) {
                    $successHistory[$today] = [
                        'sent' => 1,
                        'planned' => 1,
                        'failed' => 0,
                    ];
                } else {
                    $successHistory[$today]['sent'] = max(0, (int)($successHistory[$today]['sent'] ?? 0)) + 1;
                    $successHistory[$today]['planned'] = max(
                        $successHistory[$today]['sent'],
                        (int)($successHistory[$today]['planned'] ?? $successHistory[$today]['sent'])
                    );
                }
                $settings['success_history_json'] = $successHistory;

                // Salva as configurações atualizadas no grupo
                $updateStmt = db()->prepare("
                    UPDATE concierge_groups
                    SET settings_json = :settings, updated_at = NOW()
                    WHERE id = :gid AND tenant_id = :tid
                ");
                $updateStmt->execute([
                    ':gid' => $groupId,
                    ':tid' => $tenantId,
                    ':settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]);
            } catch (Throwable $e) {
                // Ignora erro ao atualizar o grupo, pois o principal é o broadcast
            }
        }

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Marca campanha para disparo imediato.
 */
function ai_dispatch_concierge_campaign_now(int $tenantId, int $campaignId, int $userId = 0): bool
{
    if ($tenantId <= 0 || $campaignId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return false;
    }

    // Verifica conflito de horário (gap de 5 min)
    $now = date('Y-m-d H:i:s');
    $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, $now, $campaignId, 5);
    if ($conflict) {
        // Se houver conflito, não podemos disparar agora.
        // O ideal seria retornar uma mensagem, mas a assinatura da função é booleana.
        return false;
    }

    try {
        $stmt = db()->prepare("
            UPDATE concierge_campaigns
            SET status = 'scheduled',
                scheduled_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE),
                updated_by = :uid,
                webhook_requested_at = NOW(),
                last_error = '',
                n8n_execution_id = NULL,
                sent_at = NULL,
                updated_at = NOW()
            WHERE tenant_id = :tid
              AND id = :cid
              AND status <> 'canceled'
              AND (
                    status NOT IN ('sent', 'completed')
                    OR allow_requeue = 1
              )
            LIMIT 1
        ");
        $stmt->execute([
            ':tid' => $tenantId,
            ':cid' => $campaignId,
            ':uid' => $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            ai_process_due_concierge_campaigns($tenantId, 1);
            $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
            if (!is_array($campaign)) {
                return false;
            }
            $status = strtolower(trim((string)($campaign['status'] ?? '')));
            return in_array($status, ['sending', 'sent', 'completed'], true);
        }

        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Retorna campanhas prontas para execução (cron).
 */
function ai_get_due_concierge_campaigns(int $tenantId = 0, int $limit = 50): array
{
    if (!ai_groups_table_exists('concierge_campaigns')) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $where = "status IN ('scheduled','pending','queued') AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()";
    $params = [];

    if ($tenantId > 0) {
        $where .= ' AND tenant_id = :tid';
        $params[':tid'] = $tenantId;
    }

    try {
        $sql = "SELECT * FROM concierge_campaigns WHERE {$where} ORDER BY scheduled_at ASC LIMIT $limit";
        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
function ai_groups_get_group_dispatch_queue(int $tenantId, int $groupId, int $nextCount = 3): array
{
    $result = [
        'group_id' => (int)$groupId,
        'daily_limit' => 0,
        'interval_minutes' => 0,
        'start_time' => '09:00',
        'sent_today' => 0,
        'remaining_today' => 0,
        'last_sent_item' => null,
        'next_items' => [],
        'total_items' => 0,
    ];

    if (
        $tenantId <= 0
        || $groupId <= 0
        || !ai_groups_table_exists('concierge_groups')
        || !ai_groups_table_exists('concierge_campaigns')
        || !ai_groups_table_exists('concierge_broadcasts')
    ) {
        return $result;
    }

    $group = null;
    try {
        $stGroup = db()->prepare("SELECT * FROM concierge_groups WHERE tenant_id = :tid AND id = :gid LIMIT 1");
        $stGroup->execute([':tid' => $tenantId, ':gid' => $groupId]);
        $group = $stGroup->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $group = null;
    }

    if (!$group) {
        return $result;
    }

    $settings = ai_groups_decode_json($group['settings_json'] ?? null);
    $dailyLimit = max(0, (int)($group['daily_limit'] ?? 0));
    $intervalMinutesRaw = max(0, (int)($settings['dispatch_interval_minutes'] ?? $settings['interval_between_dispatches'] ?? 0));
    $defaultIntervalMinutes = max(1, (int)ai_get_setting('mia_group_delay', 5, $tenantId));
    $intervalMinutes = $intervalMinutesRaw > 0 ? $intervalMinutesRaw : $defaultIntervalMinutes;
    $startTime = trim((string)($settings['start_time'] ?? '09:00'));
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        $startTime = '09:00';
    }

    $result['daily_limit'] = $dailyLimit;
    $result['interval_minutes'] = $intervalMinutes;
    $result['start_time'] = $startTime;

    $campaignRows = [];
    $sentToday = 0;
    try {
        $stCampaigns = db()->prepare("
            SELECT
                c.id,
                c.title,
                c.content,
                c.media_url,
                c.status,
                c.scheduled_at,
                c.created_at,
                c.allow_requeue,
                MAX(CASE WHEN b.status IN ('sent','completed') THEN COALESCE(b.sent_at, b.updated_at, b.created_at) END) AS last_sent_at,
                SUM(CASE WHEN b.status IN ('sent','completed') THEN 1 ELSE 0 END) AS total_sent_count,
                SUM(CASE WHEN b.status IN ('sent','completed') AND DATE(COALESCE(b.sent_at, b.updated_at, b.created_at)) = CURDATE() THEN 1 ELSE 0 END) AS sent_today_count
            FROM concierge_broadcasts b
            INNER JOIN concierge_campaigns c
                ON c.id = b.campaign_id
            WHERE b.tenant_id = ?
              AND b.group_id = ?
              AND c.tenant_id = ?
              AND c.status IN ('pending','queued','scheduled','sending','sent','completed')
              AND b.status IN ('pending','queued','scheduled','sending','sent','completed')
            GROUP BY c.id
            ORDER BY c.scheduled_at ASC, c.id ASC
        ");
        $stCampaigns->execute([$tenantId, $groupId, $tenantId]);
        $campaignRows = $stCampaigns->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        foreach ($campaignRows as $row) {
            $sentToday += max(0, (int)($row['sent_today_count'] ?? 0));
        }
    } catch (Throwable $e) {
        $campaignRows = [];
        $sentToday = 0;
    }

    if (empty($campaignRows)) {
        $result['remaining_today'] = $dailyLimit > 0 ? $dailyLimit : 0;
        return $result;
    }

    $result['sent_today'] = $sentToday;
    $result['remaining_today'] = $dailyLimit > 0 ? max(0, $dailyLimit - $sentToday) : 0;
    $result['total_items'] = count($campaignRows);

    $orderedItems = [];
    foreach ($campaignRows as $row) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $thumb = trim((string)($row['media_url'] ?? ''));
        $hasBeenSent = (int)($row['total_sent_count'] ?? 0) > 0;
        $campaignStatus = strtolower(trim((string)($row['status'] ?? 'pending')));
        $scheduledAt = trim((string)($row['scheduled_at'] ?? ''));
        $nextDispatch = null;

        if ($scheduledAt !== '' && in_array($campaignStatus, ['pending', 'queued', 'scheduled', 'sending'], true)) {
            try {
                $scheduledDt = new DateTime($scheduledAt, new DateTimeZone(date_default_timezone_get()));
                $nextDispatch = [
                    'datetime' => $scheduledDt->format('Y-m-d H:i:s'),
                    'type' => $hasBeenSent ? 'system' : 'user',
                    'label' => $hasBeenSent ? 'Fila de produtos' : 'Agendado por você',
                ];
            } catch (Throwable $e) {
                $nextDispatch = null;
            }
        }

        if ($nextDispatch === null) {
            $nextDispatch = ai_groups_calculate_next_dispatch_time(
                $group,
                $sentToday,
                $row['last_sent_at'] ?? null,
                $row['scheduled_at'] ?? null,
                $hasBeenSent
            );
        }
        $orderedItems[] = [
            'id' => $cid,
            'product_id' => $cid,
            'name' => trim((string)($row['title'] ?? ('Campanha #' . $cid))),
            'thumbnail' => $thumb !== '' ? ai_resolve_storage_url($thumb) : '',
            'first_seen_at' => $row['scheduled_at'] ?? $row['created_at'] ?? null,
            'last_sent_at' => $row['last_sent_at'] ?? null,
            'total_sent_count' => max(0, (int)($row['total_sent_count'] ?? 0)),
            'sent_today_count' => max(0, (int)($row['sent_today_count'] ?? 0)),
            'next_dispatch_at' => $nextDispatch['datetime'] ?? null,
            'next_dispatch_type' => $nextDispatch['type'] ?? 'system',
            'next_dispatch_label' => $nextDispatch['label'] ?? 'Fila de produtos',
            'status' => $campaignStatus !== '' ? $campaignStatus : 'pending',
            'campaign_status_global' => $campaignStatus !== '' ? $campaignStatus : 'pending',
            'queue_display_status' => $campaignStatus !== '' ? $campaignStatus : 'pending',
            'allow_requeue' => max(0, (int)($row['allow_requeue'] ?? 1)),
            'requeue_enabled' => max(0, (int)($row['allow_requeue'] ?? 1)),
        ];
    }

    if (empty($orderedItems)) {
        return $result;
    }

    $lastSentItem = null;
    $lastSentAt = '';
    foreach ($orderedItems as $it) {
        $candidate = trim((string)($it['last_sent_at'] ?? ''));
        if ($candidate !== '' && ($lastSentAt === '' || strcmp($candidate, $lastSentAt) > 0)) {
            $lastSentAt = $candidate;
            $lastSentItem = $it;
        }
    }
    if ($lastSentItem) {
        $lastSentItem['queue_display_status'] = 'sent';
        $result['last_sent_item'] = $lastSentItem;
    }

    // Sort ALL items by next_dispatch_at (the actual time they will be sent)
    usort($orderedItems, static function (array $a, array $b): int {
        $aNext = $a['next_dispatch_at'] ?? '';
        $bNext = $b['next_dispatch_at'] ?? '';
        
        // If either doesn't have next_dispatch_at, use first_seen_at as fallback
        if ($aNext === '' || $bNext === '') {
            $aFallback = $a['first_seen_at'] ?? '';
            $bFallback = $b['first_seen_at'] ?? '';
            return strcmp($aFallback, $bFallback);
        }
        
        return strcmp($aNext, $bNext);
    });

    $queue = $orderedItems;

    $nextCount = max(1, min(10, $nextCount));
    if ($lastSentItem && count($queue) > 1) {
        $queue = array_values(array_filter($queue, static function (array $item) use ($lastSentItem): bool {
            return (int)($item['id'] ?? 0) !== (int)($lastSentItem['id'] ?? 0);
        }));
    }
    $nextItems = array_slice($queue, 0, $nextCount);
    $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));

    $result['next_items'] = array_map(static function (array $item) use ($now): array {
        $status = strtolower(trim((string)($item['status'] ?? 'pending')));
        $allowRequeue = (int)($item['requeue_enabled'] ?? $item['allow_requeue'] ?? 1) === 1;
        $alreadySent = (int)($item['total_sent_count'] ?? 0) > 0;
        $queueDisplayStatus = $status;

        if ($status === 'sending') {
            $queueDisplayStatus = 'sending';
        } elseif ($allowRequeue && $alreadySent && in_array($status, ['sent', 'completed'], true)) {
            $queueDisplayStatus = 'scheduled';
        } elseif ($status === '') {
            $queueDisplayStatus = 'pending';
        }

        // Marca como "overdue" se o próximo disparo previsto já estiver no passado
        $nextAtRaw = trim((string)($item['next_dispatch_at'] ?? ''));
        if ($nextAtRaw !== '') {
            try {
                $nextAt = new DateTime($nextAtRaw, new DateTimeZone(date_default_timezone_get()));
                if ($nextAt < $now && !in_array($queueDisplayStatus, ['sending', 'sent', 'completed'], true)) {
                    $queueDisplayStatus = 'overdue';
                    // Label mais explícito para frontend
                    $item['next_dispatch_label'] = 'Atrasado · aguardando próxima janela';
                }
            } catch (Throwable $e) {
                // ignora erro de parse e mantém status calculado
            }
        }

        $item['campaign_status_global'] = trim((string)($item['campaign_status_global'] ?? $status));
        $item['queue_display_status'] = $queueDisplayStatus;
        $item['requeue_enabled'] = $allowRequeue ? 1 : 0;
        return $item;
    }, $nextItems);

    return $result;
}

/**
 * Conta disparos enviados no mês para limite de plano.
 */
function ai_count_monthly_group_broadcasts(int $tenantId, string $yearMonth = ''): int
{
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_broadcasts')) {
        return 0;
    }

    $yearMonth = trim($yearMonth);
    if ($yearMonth === '') {
        $yearMonth = date('Y-m');
    }

    try {
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM concierge_broadcasts
            WHERE tenant_id = :tid
              AND status = 'sent'
              AND DATE_FORMAT(COALESCE(sent_at, created_at), '%Y-%m') = :ym
        ");
        $stmt->execute([':tid' => $tenantId, ':ym' => $yearMonth]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Valida limite mensal de disparos em grupos no plano.
 */
function ai_check_groups_broadcast_limit(int $tenantId, int $newMessages = 1): array
{
    $newMessages = max(1, $newMessages);

    if ($tenantId <= 0 || !function_exists('ai_get_active_plan')) {
        return ['ok' => true, 'used' => 0, 'limit' => 0, 'remaining' => 0];
    }

    try {
        $plan = ai_get_active_plan($tenantId);
        $limit = (int)($plan['ai_broadcast_monthly_limit'] ?? 0);
        $used = ai_count_monthly_group_broadcasts($tenantId);

        if ($limit > 0 && ($used + $newMessages) > $limit) {
            return [
                'ok' => false,
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'message' => "Limite mensal de disparos em grupos atingido ({$used}/{$limit}).",
            ];
        }

        return [
            'ok' => true,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : 0,
        ];
    } catch (Throwable $e) {
        return ['ok' => true, 'used' => 0, 'limit' => 0, 'remaining' => 0];
    }
}

/**
 * Lista postagens de status com paginação.
 */
function ai_get_concierge_statuses(int $tenantId, array $filters = [], int $page = 1, int $limit = 20): array
{
    ai_groups_ensure_status_schema();
    $result = [
        'items' => [],
        'total' => 0,
        'page' => max(1, $page),
        'limit' => max(1, min(100, $limit)),
    ];

    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return $result;
    }

    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;

    $where = ['tenant_id = :tid'];
    $params = [':tid' => $tenantId];

    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = (string)$filters['status'];
    }

    $whereSql = implode(' AND ', $where);

    try {
        $stmtTotal = db()->prepare("SELECT COUNT(*) FROM concierge_status WHERE $whereSql");
        $stmtTotal->execute($params);
        $result['total'] = (int)$stmtTotal->fetchColumn();

        if ($result['total'] <= 0) {
            return $result;
        }

        // Tenta buscar com created_at, se falhar tenta sem ordenação (caso a coluna não exista por algum motivo)
        try {
            $sql = "SELECT * FROM concierge_status WHERE $whereSql ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $stmt = db()->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $result['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $sql = "SELECT * FROM concierge_status WHERE $whereSql LIMIT $limit OFFSET $offset";
            $stmt = db()->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $result['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        
        return $result;
    } catch (Throwable $e) {
        throw new Exception('Erro ao buscar status no banco: ' . $e->getMessage());
    }
}

/**
 * Cria uma nova postagem de status.
 */
function ai_create_concierge_status(int $tenantId, array $payload): int
{
    ai_groups_ensure_status_schema();
    if ($tenantId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return 0;
    }

    $content = trim((string)($payload['content'] ?? ''));
    $productId = (int)($payload['product_id'] ?? 0);
    $mediaUrl = trim((string)($payload['media_url'] ?? ''));
    $status = in_array(($payload['status'] ?? 'pending'), ['pending', 'sending', 'sent', 'error', 'canceled']) ? $payload['status'] : 'pending';
    $scheduledAt = trim((string)($payload['scheduled_at'] ?? ''));
    $payloadJson = $payload['payload_json'] ?? null;
    $hasScheduledAt = ai_groups_column_exists('concierge_status', 'scheduled_at');
    $hasPayloadJson = ai_groups_column_exists('concierge_status', 'payload_json');

    try {
        $columns = ['tenant_id', 'product_id', 'content', 'media_url', 'status'];
        $params = [
            ':tid' => $tenantId,
            ':pid' => $productId > 0 ? $productId : null,
            ':content' => $content,
            ':media' => $mediaUrl !== '' ? $mediaUrl : null,
            ':status' => $status,
        ];
        $holders = [':tid', ':pid', ':content', ':media', ':status'];

        if (isset($payload['repeat_count'])) {
            $columns[] = 'repeat_count';
            $holders[] = ':rep';
            $params[':rep'] = (int)$payload['repeat_count'];
        }
        if (isset($payload['repeat_interval'])) {
            $columns[] = 'repeat_interval';
            $holders[] = ':inter';
            $params[':inter'] = (int)$payload['repeat_interval'];
        }
        if (isset($payload['post_count'])) {
            $columns[] = 'post_count';
            $holders[] = ':pc';
            $params[':pc'] = (int)$payload['post_count'];
        } else {
            $columns[] = 'post_count';
            $holders[] = ':pc';
            $params[':pc'] = 0;
        }
        if (isset($payload['repeat_days'])) {
            $columns[] = 'repeat_days';
            $holders[] = ':rd';
            $params[':rd'] = trim((string)$payload['repeat_days']);
        }

        if ($hasScheduledAt) {
            $columns[] = 'scheduled_at';
            $holders[] = ':sched';
            $params[':sched'] = $scheduledAt !== '' ? $scheduledAt : null;
        }
        if ($hasPayloadJson) {
            $columns[] = 'payload_json';
            $holders[] = ':json';
            $params[':json'] = is_array($payloadJson) ? json_encode($payloadJson) : (is_string($payloadJson) ? $payloadJson : null);
        }

        if (ai_groups_column_exists('concierge_status', 'created_at')) {
            $columns[] = 'created_at';
            $holders[] = 'NOW()';
        }

        $sql = 'INSERT INTO concierge_status (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $holders) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        throw new Exception('Erro ao inserir status no banco: ' . $e->getMessage());
    }
}

/**
 * Exclui uma campanha e seus registros de disparo.
 */
function ai_delete_concierge_campaign(int $tenantId, int $campaignId): bool
{
    if ($tenantId <= 0 || $campaignId <= 0 || !ai_groups_table_exists('concierge_campaigns')) {
        return false;
    }

    try {
        db()->beginTransaction();

        // Remove alvos/broadcasts vinculados
        if (ai_groups_table_exists('concierge_broadcasts')) {
            $stmtB = db()->prepare("DELETE FROM concierge_broadcasts WHERE tenant_id = :tid AND campaign_id = :cid");
            $stmtB->execute([':tid' => $tenantId, ':cid' => $campaignId]);
        }

        // Remove a campanha
        $stmtC = db()->prepare("DELETE FROM concierge_campaigns WHERE tenant_id = :tid AND id = :cid LIMIT 1");
        $stmtC->execute([':tid' => $tenantId, ':cid' => $campaignId]);

        db()->commit();
        return $stmtC->rowCount() > 0;
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        return false;
    }
}

/**
 * Exclui um registro de status agendado.
 */
function ai_delete_concierge_status(int $tenantId, int $statusId): bool
{
    if ($tenantId <= 0 || $statusId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return false;
    }

    try {
        $stmt = db()->prepare("DELETE FROM concierge_status WHERE tenant_id = :tid AND id = :sid LIMIT 1");
        $stmt->execute([':tid' => $tenantId, ':sid' => $statusId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Atualiza uma postagem de status.
 */
function ai_update_concierge_status(int $tenantId, int $statusId, array $payload): bool
{
    ai_groups_ensure_status_schema();
    if ($tenantId <= 0 || $statusId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return false;
    }

    $sets = [];
    $params = [':tid' => $tenantId, ':sid' => $statusId];
    $stringify = static function ($v): string {
        if (is_array($v) || is_object($v)) {
            $j = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($j) ? $j : '';
        }
        return (string)$v;
    };
    
    $fields = [
        'content', 'status', 'scheduled_at', 'error_message', 'sent_at', 
        'media_url', 'product_id', 'repeat_count', 'repeat_interval', 
        'post_count', 'seen_count', 'repeat_days', 'success_history_json', 
        'attempt_count', 'payload_json'
    ];

    foreach ($fields as $field) {
        if (array_key_exists($field, $payload)) {
            $val = $payload[$field];
            if ($field === 'scheduled_at' || $field === 'sent_at' || $field === 'media_url' || $field === 'product_id') {
                $params[':' . $field] = (trim((string)$val) === '') ? null : $val;
            } elseif ($field === 'post_count' || $field === 'seen_count' || $field === 'repeat_count' || $field === 'repeat_interval' || $field === 'attempt_count') {
                $params[':' . $field] = (int)$val;
            } elseif ($field === 'success_history_json' || $field === 'payload_json') {
                $params[':' . $field] = $stringify($val);
            } else {
                $params[':' . $field] = trim($stringify($val));
            }
            $sets[] = "`{$field}` = :{$field}";
        }
    }

    if (empty($sets)) return true;

    try {
        $sql = "UPDATE concierge_status SET " . implode(', ', $sets);
        if (ai_groups_column_exists('concierge_status', 'updated_at')) {
            $sql .= ", updated_at = NOW()";
        }
        $sql .= " WHERE tenant_id = :tid AND id = :sid LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
