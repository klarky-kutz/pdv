<?php
/**
 * Helper: ai_evolution.php
 * Integração Evolution API v2.3 para o módulo Moda IA (ModernPOS).
 */

if (!function_exists('ai_tenant_id')) {
    require_once __DIR__ . '/ai_concierge.php';
}
if (!function_exists('ai_log_demand')) {
    require_once __DIR__ . '/ai_tokens.php';
}

if (!class_exists('Encryption')) {
    $encFile = defined('DIR_LIBRARY') ? (rtrim(DIR_LIBRARY, '/\\') . DIRECTORY_SEPARATOR . 'encryption.php') : '';
    if ($encFile && file_exists($encFile)) {
        require_once $encFile;
    }
}

/**
 * Gera chave de criptografia por tenant e contexto.
 */
function ai_evolution_secret_key(int $tenantId, string $context): string
{
    return 'ai_evolution_' . $context . '_' . $tenantId;
}

/**
 * Criptografa secrets para persistir em ai_settings.
 */
function ai_evolution_encrypt_secret(string $plain, int $tenantId, string $context): string
{
    if ($plain === '') {
        return '';
    }

    try {
        $encryption = new Encryption();
        return $encryption->encrypt(ai_evolution_secret_key($tenantId, $context), $plain);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Descriptografa secrets armazenados em ai_settings.
 */
function ai_evolution_decrypt_secret(string $cipher, int $tenantId, string $context): string
{
    if ($cipher === '') {
        return '';
    }

    try {
        $encryption = new Encryption();
        return $encryption->decrypt(ai_evolution_secret_key($tenantId, $context), $cipher);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Valida se um arquivo de imagem existe no storage e retorna a URL completa ou null.
 */
function ai_evolution_get_image_url(?string $path): ?string
{
    if (!$path || trim((string)$path) === '') {
        return null;
    }

    // Normaliza caminho para Windows/Linux
    $pathOnDb = ltrim(str_replace('\\', '/', (string)$path), '/');
    
    // Identifica se o caminho já começa com "storage/"
    if (stripos($pathOnDb, 'storage/') === 0) {
        $relativePath = substr($pathOnDb, 8); // Remove "storage/"
    } else {
        $relativePath = $pathOnDb;
    }

    // DIR_STORAGE termina em /
    $fullPathOnDisk = defined('DIR_STORAGE') ? DIR_STORAGE . $relativePath : ROOT . '/storage/' . $relativePath;

    if (!file_exists($fullPathOnDisk)) {
        // Se o arquivo não existir fisicamente, não retornamos a URL
        return null;
    }

    return rtrim(ROOT_URL, '/') . '/storage/' . $relativePath;
}

/**
 * Retorna as configurações globais da Evolution API definidas no SaaS.
 */
function ai_evolution_normalize_api_token(string $token): string
{
    $normalized = trim($token);
    if ($normalized === '') {
        return '';
    }

    if (stripos($normalized, 'Bearer ') === 0) {
        $normalized = trim((string)substr($normalized, 7));
    }

    if (
        (strlen($normalized) >= 2)
        && (
            (($normalized[0] === '"' && substr($normalized, -1) === '"'))
            || (($normalized[0] === "'" && substr($normalized, -1) === "'"))
        )
    ) {
        $normalized = trim(substr($normalized, 1, -1));
    }

    return $normalized;
}
function ai_evolution_global_config(): array
{
    try {
        $st = db()->query("SELECT key_name, key_value FROM saas_config_global WHERE key_name IN ('ai_evolution_base_url', 'ai_evolution_global_token', 'ai_evolution_webhook_inbound', 'ai_status_posting_mode', 'ai_campaign_posting_mode')");
        $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $baseUrl = rtrim((string)($rows['ai_evolution_base_url'] ?? ''), '/');
        $webhookInbound = trim((string)($rows['ai_evolution_webhook_inbound'] ?? ''));
        $globalToken = ai_evolution_normalize_api_token((string)($rows['ai_evolution_global_token'] ?? ''));
        $statusPostingMode = strtolower(trim((string)($rows['ai_status_posting_mode'] ?? 'n8n')));
        $campaignPostingMode = strtolower(trim((string)($rows['ai_campaign_posting_mode'] ?? 'n8n')));
        if (!in_array($statusPostingMode, ['n8n', 'system'], true)) {
            $statusPostingMode = 'n8n';
        }
        if (!in_array($campaignPostingMode, ['n8n', 'system'], true)) {
            $campaignPostingMode = 'n8n';
        }
        return [
            'base_url'        => $baseUrl,
            'global_token'    => $globalToken,
            'webhook_inbound' => $webhookInbound,
            'status_posting_mode' => $statusPostingMode,
            'campaign_posting_mode' => $campaignPostingMode,
        ];
    } catch (Exception $e) {
        return [
            'base_url'        => '',
            'global_token'    => '',
            'webhook_inbound' => '',
            'status_posting_mode' => 'n8n',
            'campaign_posting_mode' => 'n8n',
        ];
    }
}

/**
 * Resolve placeholders dinâmicos da URL de webhook inbound global.
 */
function ai_evolution_resolve_webhook_inbound(string $url, int $tenantId, string $storeToken = ''): string
{
    $resolved = trim($url);
    if ($resolved === '') {
        return '';
    }

    $replace = [
        '{loja_id}'      => (string)$tenantId,
        '{{loja_id}}'    => (string)$tenantId,
        '%7Bloja_id%7D'  => (string)$tenantId,
        '%7BLOJA_ID%7D'  => (string)$tenantId,
        '%7bloja_id%7d'  => (string)$tenantId,
        '%7bLOJA_ID%7d'  => (string)$tenantId,
    ];

    if ($storeToken !== '') {
        $replace['{token}'] = $storeToken;
        $replace['{{token}}'] = $storeToken;
        $replace['%7Btoken%7D'] = $storeToken;
        $replace['%7BTOKEN%7D'] = $storeToken;
        $replace['%7btoken%7d'] = $storeToken;
        $replace['%7bTOKEN%7d'] = $storeToken;
        $replace['{store_token}'] = $storeToken;
        $replace['{{store_token}}'] = $storeToken;
        $replace['%7Bstore_token%7D'] = $storeToken;
        $replace['%7BSTORE_TOKEN%7D'] = $storeToken;
        $replace['%7bstore_token%7d'] = $storeToken;
        $replace['%7bSTORE_TOKEN%7d'] = $storeToken;
    }

    $resolved = strtr($resolved, $replace);
    $resolved = (string)preg_replace('/%7b%7bloja_id%7d%7d/i', (string)$tenantId, $resolved);
    $resolved = (string)preg_replace('/%7bloja_id%7d/i', (string)$tenantId, $resolved);
    $resolved = (string)preg_replace_callback('/\{\{?loja_id\}?\}/i', static function () use ($tenantId) {
        return (string)$tenantId;
    }, $resolved);

    if ($storeToken !== '') {
        $resolved = (string)preg_replace_callback('/%7b%7b(token|store_token)%7d%7d/i', static function () use ($storeToken) {
            return $storeToken;
        }, $resolved);
        $resolved = (string)preg_replace_callback('/%7b(token|store_token)%7d/i', static function () use ($storeToken) {
            return $storeToken;
        }, $resolved);
        $resolved = (string)preg_replace_callback('/\{\{?(token|store_token)\}?\}/i', static function () use ($storeToken) {
            return $storeToken;
        }, $resolved);
    }

    $decodedOnce = rawurldecode($resolved);
    if ($decodedOnce !== $resolved) {
        $decodedOnce = (string)preg_replace_callback('/\{\{?loja_id\}?\}/i', static function () use ($tenantId) {
            return (string)$tenantId;
        }, $decodedOnce);
        if ($storeToken !== '') {
            $decodedOnce = (string)preg_replace_callback('/\{\{?(token|store_token)\}?\}/i', static function () use ($storeToken) {
                return $storeToken;
            }, $decodedOnce);
        }
        $resolved = $decodedOnce;
    }

    return $resolved;
}
/**
 * Retorna configuração consolidada da conexão Evolution da loja.
 */
function ai_evolution_get_connection(int $tenantId = 0): array
{
    $tid = $tenantId ?: ai_tenant_id();
    $global = ai_evolution_global_config();
    $globalToken = (string)($global['global_token'] ?? '');

    $instanceName         = trim((string) ai_get_setting('ai_evolution_instance_name', ai_get_setting('ai_instance_name', ''), $tid));
    $webhookConversation  = trim((string) ai_get_setting('ai_webhook_conversation_url', '', $tid));
    if ($webhookConversation === '') {
        $webhookConversation = trim((string) ai_get_setting('ai_webhook_target_url', '', $tid));
    }
    $statusLabel          = trim((string) ai_get_setting('ai_evolution_status', 'Desconectado', $tid));
    $lastQrCode           = trim((string) ai_get_setting('ai_evolution_last_qrcode', '', $tid));
    $storeToken           = ai_evolution_store_token($tid);
    $webhookInbound       = ai_evolution_resolve_webhook_inbound($global['webhook_inbound'], $tid, $storeToken);

    if ($webhookInbound === '') {
        $webhookInbound = rtrim((string)ROOT_URL, '/') . '/api/concierge/evolution_webhook.php?loja_id=' . $tid . '&token=' . $storeToken;
    }

    return [
        'tenant_id'               => $tid,
        'base_url'                => $global['base_url'],
        'instance_name'           => $instanceName,
        'webhook_inbound_url'     => $webhookInbound,
        'webhook_inbound_raw'     => (string)$global['webhook_inbound'],
        'webhook_conversation_url'=> $webhookConversation,
        'webhook_target_url'      => $webhookConversation,
        'status_label'            => $statusLabel ?: 'Desconectado',
        'last_qrcode'             => $lastQrCode,
        'global_token'            => $globalToken,
        'has_global_token'        => ($globalToken !== ''),
    ];
}

/**
 * Persiste configuração da conexão Evolution.
 */
function ai_evolution_save_connection(array $data, int $tenantId = 0): void
{
    $tid = $tenantId ?: ai_tenant_id();

    $allowed = [
        'ai_evolution_base_url',
        'ai_evolution_instance_name',
        'ai_evolution_webhook_url',
        'ai_evolution_status',
        'ai_evolution_last_qrcode',
        'ai_evolution_global_token_enc',
        'ai_evolution_instance_token_enc',
        'ai_instance_url',
        'ai_instance_name',
        'ai_api_key',
        'ai_whatsapp_provider',
        'ai_whatsapp_number',
        'ai_webhook_conversation_url',
        'ai_webhook_target_url',
        'ai_groups_dispatch_webhook_url',
        'ai_status_dispatch_webhook_url',
    ];

    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            ai_save_setting($key, (string) $data[$key], $tid);
        }
    }
}

/**
 * Máscara para exibir tokens sem vazar segredo.
 */
function ai_evolution_mask_secret(string $secret): string
{
    $len = strlen($secret);
    if ($len <= 6) {
        return str_repeat('*', $len);
    }
    return substr($secret, 0, 3) . str_repeat('*', $len - 6) . substr($secret, -3);
}

/**
 * Faz chamadas HTTP à Evolution API.
 */
function ai_evolution_http_exec(string $method, string $url, array $headers, ?array $body = null, int $timeout = 45): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    // Adiciona header de bypass do ngrok globalmente
    $headers[] = 'ngrok-skip-browser-warning: 1';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, max(15, $timeout));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $start = microtime(true);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($ch);
    curl_close($ch);
    $duration = round(microtime(true) - $start, 3);

    // Log de performance para requisições longas ou com erro
    if ($duration > 5 || $httpCode >= 400 || $err !== '') {
        $logFile = DIR_LOG . 'evolution_performance.log';
        $logMsg = sprintf("[%s] %s %s | Code: %d | Time: %ss | Err: %s | Total: %s | Upload: %s\nResponse: %s\n", 
            date('Y-m-d H:i:s'), strtoupper($method), $url, $httpCode, $duration, $err, 
            $info['total_time'] ?? 0, $info['size_upload'] ?? 0, is_string($raw) ? $raw : 'empty');
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
    }

    $json = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = $decoded;
        }
    }

    // Log de debug para 401
    if ($httpCode === 401) {
        $logFile = DIR_LOG . 'evolution_debug.log';
        $logMsg = sprintf("[%s] 401 Unauthorized\nURL: %s\nMethod: %s\nHeaders: %s\nBody: %s\nResponse: %s\n\n", 
            date('Y-m-d H:i:s'), $url, $method, json_encode($headers), json_encode($body), is_string($raw) ? $raw : 'empty');
        @file_put_contents($logFile, $logMsg, FILE_APPEND);
    }

    $errorMessage = $err;
    if ($errorMessage === '' && is_array($json)) {
        if (isset($json['response']['message'])) {
            $msg = $json['response']['message'];
            if (is_array($msg)) {
                $errorMessage = implode(', ', array_map(function ($m) {
                    if (is_array($m) || is_object($m)) {
                        $j = json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        return is_string($j) ? $j : '';
                    }
                    return (string)$m;
                }, $msg));
            } else {
                $errorMessage = (string)$msg;
            }
        } elseif (isset($json['message'])) {
            $errorMessage = is_array($json['message']) ? json_encode($json['message']) : (string)$json['message'];
        } elseif (isset($json['error'])) {
            $errorMessage = (string)$json['error'];
        }
    }

    return [
        'ok'        => $err === '' && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'error'     => $errorMessage,
        'raw'       => is_string($raw) ? $raw : '',
        'json'      => is_array($json) ? $json : [],
    ];
}
function ai_evolution_http_request(string $method, string $url, string $apiKey, ?array $body = null, int $timeout = 45): array
{
    $apiKey = ai_evolution_normalize_api_token($apiKey);
    $method = strtoupper($method);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($apiKey !== '') {
        $headers[] = 'apikey: ' . $apiKey;
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
    $result = ai_evolution_http_exec($method, $url, $headers, $body, $timeout);

    // Algumas instalações aceitam apenas o header "apikey".
    if ($apiKey !== '' && (int)$result['http_code'] === 401) {
        $retryHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'apikey: ' . $apiKey,
        ];
        $retry = ai_evolution_http_exec($method, $url, $retryHeaders, $body, $timeout);
        if ($retry['ok'] || (int)$retry['http_code'] !== 401) {
            return $retry;
        }
    }

    return $result;
}
/**
 * Extrai Base64 de QR code da resposta da Evolution.
 */
function ai_evolution_extract_qrcode(array $payload): string
{
    if (isset($payload['base64']) && is_string($payload['base64']) && $payload['base64'] !== '') {
        return $payload['base64'];
    }

    if (isset($payload['qrcode']) && is_string($payload['qrcode']) && $payload['qrcode'] !== '') {
        return $payload['qrcode'];
    }

    if (isset($payload['qr']) && is_string($payload['qr']) && $payload['qr'] !== '') {
        return $payload['qr'];
    }

    foreach ($payload as $value) {
        if (is_array($value)) {
            $found = ai_evolution_extract_qrcode($value);
            if ($found !== '') {
                return $found;
            }
        }
    }

    return '';
}

/**
 * Normaliza estado de conexão recebido pela Evolution.
 */
function ai_evolution_status_label(string $state): string
{
    $state = strtolower(trim($state));
    return match ($state) {
        'open', 'connected'  => 'Conectado',
        'close', 'closed'    => 'Desconectado',
        'connecting', 'qr'   => 'Aguardando leitura',
        default              => 'Aguardando leitura',
    };
}

/**
 * Retorna token da loja para autenticação dos webhooks.
 */
function ai_evolution_store_token(int $tenantId): string
{
    $token = trim((string)ai_get_setting('ai_evolution_store_token', '', $tenantId));
    if ($token !== '') {
        return $token;
    }

    try {
        $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
        $stmt->execute([':tid' => $tenantId]);
        $legacy = (string)$stmt->fetchColumn();
        if ($legacy !== '') {
            ai_save_setting('ai_evolution_store_token', $legacy, $tenantId);
            return $legacy;
        }
    } catch (Exception $e) {
    }

    $token = bin2hex(random_bytes(32));
    ai_save_setting('ai_evolution_store_token', $token, $tenantId);

    try {
        $up = db()->prepare('UPDATE stores SET ai_webhook_token = :tok WHERE store_id = :tid');
        $up->execute([':tok' => $token, ':tid' => $tenantId]);
    } catch (Exception $e) {
    }

    return $token;
}

/**
 * Extrai event name de payloads variados da Evolution.
 */
function ai_evolution_extract_event_name(array $payload): string
{
    $keys = ['event', 'eventName', 'type'];
    foreach ($keys as $key) {
        if (!empty($payload[$key]) && is_string($payload[$key])) {
            return trim($payload[$key]);
        }
    }
    if (!empty($payload['data']) && is_array($payload['data'])) {
        foreach ($keys as $key) {
            if (!empty($payload['data'][$key]) && is_string($payload['data'][$key])) {
                return trim($payload['data'][$key]);
            }
        }
    }
    return '';
}

/**
 * Extrai nome da instância de payloads variados da Evolution.
 */
function ai_evolution_extract_instance_name(array $payload): string
{
    $paths = [
        ['instance'],
        ['instanceName'],
        ['data', 'instance'],
        ['data', 'instanceName'],
        ['sender', 'instance'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

/**
 * Extrai remoteJid.
 */
function ai_evolution_extract_remote_jid(array $payload): string
{
    $paths = [
        ['data', 'key', 'remoteJid'],
        ['key', 'remoteJid'],
        ['data', 'remoteJid'],
        ['remoteJid'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

/**
 * Extrai pushName.
 */
function ai_evolution_extract_push_name(array $payload): string
{
    $paths = [
        ['data', 'pushName'],
        ['pushName'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

/**
 * Detecta se a mensagem foi enviada pela própria instância (fromMe).
 */
function ai_evolution_is_from_me(array $payload): bool
{
    $paths = [
        ['data', 'key', 'fromMe'],
        ['key', 'fromMe'],
        ['fromMe'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if ($value !== null) {
            return (bool)$value;
        }
    }
    return false;
}

/**
 * Extrai texto da mensagem.
 */
function ai_evolution_extract_message_text(array $payload): string
{
    $paths = [
        ['data', 'message', 'conversation'],
        ['message', 'conversation'],
        ['data', 'message', 'extendedTextMessage', 'text'],
        ['message', 'extendedTextMessage', 'text'],
        ['data', 'message', 'imageMessage', 'caption'],
        ['message', 'imageMessage', 'caption'],
        ['data', 'message', 'videoMessage', 'caption'],
        ['message', 'videoMessage', 'caption'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

/**
 * Detecta tipo de mensagem para roteamento.
 */
function ai_evolution_detect_message_type(array $payload): string
{
    $paths = [
        ['data', 'messageType'],
        ['messageType'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    if (ai_evolution_array_path($payload, ['data', 'message', 'imageMessage']) !== null || ai_evolution_array_path($payload, ['message', 'imageMessage']) !== null) {
        return 'image';
    }
    if (ai_evolution_array_path($payload, ['data', 'message', 'videoMessage']) !== null || ai_evolution_array_path($payload, ['message', 'videoMessage']) !== null) {
        return 'video';
    }
    return 'text';
}

/**
 * Extrai o número do WhatsApp da própria instância a partir de payloads da Evolution.
 */
function ai_evolution_extract_instance_number(array $payload): string
{
    $paths = [
        ['data', 'user', 'id'],
        ['data', 'owner'],
        ['data', 'number'],
        ['owner'],
        ['number'],
        ['instance', 'owner'],
        ['instance', 'number'],
        ['instance', 'instanceNumber'],
        ['instance', 'ownerJid'],
    ];
    foreach ($paths as $path) {
        $value = ai_evolution_array_path($payload, $path);
        if (is_string($value) && trim($value) !== '') {
            return ai_evolution_number_from_jid($value);
        }
    }
    return '';
}

/**
 * Tenta resolver um LID para um número de telefone se disponível no payload.
 */
function ai_evolution_resolve_lid_to_phone(string $remoteJid, array $payload): string
{
    // Se já for um s.whatsapp.net, retorna como está
    if (strpos($remoteJid, '@s.whatsapp.net') !== false) {
        return $remoteJid;
    }

    // Se for um LID, tenta buscar o participante alternativo (telefone real)
    if (strpos($remoteJid, '@lid') !== false) {
        $paths = [
            ['data', 'key', 'participantAlt'],
            ['key', 'participantAlt'],
            ['data', 'participantAlt'],
            ['participantAlt'],
        ];
        foreach ($paths as $path) {
            $value = ai_evolution_array_path($payload, $path);
            if (is_string($value) && trim($value) !== '' && strpos($value, '@s.whatsapp.net') !== false) {
                return trim($value);
            }
        }
    }

    return $remoteJid;
}

/**
 * Retorna telefone limpo a partir de remoteJid.
 */
function ai_evolution_number_from_jid(string $remoteJid): string
{
    $number = $remoteJid;
    if (strpos($remoteJid, '@') !== false) {
        $number = strstr($remoteJid, '@', true);
    }
    return preg_replace('/\D+/', '', $number ?? '');
}

/**
 * Lê caminho em array sem warnings.
 */
function ai_evolution_array_path(array $arr, array $path)
{
    $current = $arr;
    foreach ($path as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return null;
        }
        $current = $current[$segment];
    }
    return $current;
}

/**
 * Registra logs de chamadas de webhook da Evolution.
 */
function ai_evolution_log_webhook(int $tenantId, array $data): void
{
    try {
        $stmt = db()->prepare("
            INSERT INTO ai_evolution_webhook_logs
            (tenant_id, instance_name, event_name, remote_jid, push_name, message_type, status, error_message, payload_json, response_json, created_at)
            VALUES
            (:tenant_id, :instance_name, :event_name, :remote_jid, :push_name, :message_type, :status, :error_message, :payload_json, :response_json, NOW())
        ");
        $stmt->execute([
            ':tenant_id'     => $tenantId,
            ':instance_name' => (string)($data['instance_name'] ?? ''),
            ':event_name'    => (string)($data['event_name'] ?? ''),
            ':remote_jid'    => (string)($data['remote_jid'] ?? ''),
            ':push_name'     => (string)($data['push_name'] ?? ''),
            ':message_type'  => (string)($data['message_type'] ?? ''),
            ':status'        => (string)($data['status'] ?? 'Sucesso'),
            ':error_message' => (string)($data['error_message'] ?? ''),
            ':payload_json'  => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':response_json' => isset($data['response']) ? json_encode($data['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Exception $e) {
        // Evita quebra de fluxo em caso de tabela ainda não migrada.
    }
}

/**
 * Busca os últimos logs de webhook para exibição na UI.
 */
function ai_evolution_get_recent_logs(int $tenantId, int $limit = 5): array
{
    try {
        $limit = max(1, min($limit, 20));
        $stmt = db()->prepare("
            SELECT id, instance_name, event_name, remote_jid, push_name, message_type, status, error_message, created_at
            FROM ai_evolution_webhook_logs
            WHERE tenant_id = :tid
            ORDER BY id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([':tid' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Consulta status de atendimento (manual/ativo) por contato.
 */
function ai_evolution_get_atendimento_status(int $tenantId, string $remoteJid): array
{
    try {
        ai_evolution_ensure_status_table();
        $stmt = db()->prepare("
            SELECT status, takeover_by_user_id, updated_at
            FROM ai_status_atendimento
            WHERE tenant_id = :tid AND remote_jid = :jid
            LIMIT 1
        ");
        $stmt->execute([
            ':tid' => $tenantId,
            ':jid' => $remoteJid,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'status'              => $row['status'] ?: 'Ativo',
                'takeover_by_user_id' => (int)($row['takeover_by_user_id'] ?? 0),
                'updated_at'          => $row['updated_at'] ?? null,
            ];
        }
    } catch (Exception $e) {
        // fallback padrão abaixo
    }

    return [
        'status'              => 'Ativo',
        'takeover_by_user_id' => 0,
        'updated_at'          => null,
    ];
}

function ai_evolution_get_atendimento_map(int $tenantId, array $remoteJids): array
{
    $remoteJids = array_values(array_filter(array_map('strval', $remoteJids)));
    if (empty($remoteJids)) return [];
    $remoteJids = array_values(array_unique($remoteJids));

    try {
        ai_evolution_ensure_status_table();
        $in = implode(',', array_fill(0, count($remoteJids), '?'));
        $stmt = db()->prepare("
            SELECT remote_jid, status, takeover_by_user_id, updated_at
            FROM ai_status_atendimento
            WHERE tenant_id = ?
              AND remote_jid IN ($in)
        ");
        $stmt->execute(array_merge([$tenantId], $remoteJids));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['remote_jid']] = $r;
        }
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

function ai_evolution_send_text(int $tenantId, string $toNumber, string $text): array
{
    $connection = ai_evolution_get_connection($tenantId);
    $instance = trim((string)$connection['instance_name']);
    $baseUrl = trim((string)$connection['base_url']);
    $token = trim((string)$connection['global_token']);

    $toNumber = preg_replace('/\D+/', '', $toNumber);
    $text = trim($text);

    if ($toNumber === '' || $text === '' || $instance === '' || $baseUrl === '' || $token === '') {
        return ['ok' => false, 'http_code' => 0, 'error' => 'config_missing', 'raw' => '', 'json' => []];
    }

    return ai_evolution_http_request(
        'POST',
        $baseUrl . '/message/sendText/' . rawurlencode($instance),
        $token,
        [
            'number' => $toNumber,
            'text'   => $text,
        ]
    );
}

/**
 * Normaliza payload de grupos retornado pela Evolution para formato padrão.
 */
function ai_evolution_normalize_groups_payload(array $payload): array
{
    $candidateBuckets = [];
    $pushBucket = static function ($bucket) use (&$candidateBuckets): void {
        if (!is_array($bucket) || empty($bucket)) {
            return;
        }

        if (isset($bucket[0]) && is_array($bucket[0])) {
            $candidateBuckets[] = $bucket;
            return;
        }

        $values = array_values($bucket);
        if (isset($values[0]) && is_array($values[0])) {
            $candidateBuckets[] = $values;
        }
    };

    $pushBucket($payload);
    $pushBucket($payload['groups'] ?? null);
    $pushBucket($payload['data'] ?? null);
    $pushBucket($payload['result'] ?? null);
    $pushBucket($payload['response'] ?? null);

    if (isset($payload['data']) && is_array($payload['data'])) {
        $pushBucket($payload['data']['groups'] ?? null);
        $pushBucket($payload['data']['result'] ?? null);
        $pushBucket($payload['data']['response'] ?? null);
    }
    if (isset($payload['result']) && is_array($payload['result'])) {
        $pushBucket($payload['result']['groups'] ?? null);
        $pushBucket($payload['result']['data'] ?? null);
    }
    if (isset($payload['response']) && is_array($payload['response'])) {
        $pushBucket($payload['response']['groups'] ?? null);
        $pushBucket($payload['response']['data'] ?? null);
    }

    $out = [];
    $seen = [];
    foreach ($candidateBuckets as $bucket) {
        foreach ($bucket as $group) {
            if (!is_array($group)) {
                continue;
            }

            $jid = trim((string)(
                $group['id']
                ?? $group['jid']
                ?? $group['remoteJid']
                ?? $group['groupId']
                ?? ''
            ));
            if ($jid === '' || isset($seen[$jid])) {
                continue;
            }

            $subject = trim((string)(
                $group['subject']
                ?? $group['name']
                ?? $group['groupName']
                ?? $group['title']
                ?? $group['pushName']
                ?? 'Grupo sem nome'
            ));

            $participants = $group['participants'] ?? $group['members'] ?? $group['participantsData'] ?? [];
            if (!is_array($participants)) {
                $participants = [];
            }

            $participantsCount = count($participants);
            if ($participantsCount <= 0) {
                $participantsCount = max(
                    0,
                    (int)($group['participantsCount']
                        ?? $group['participantCount']
                        ?? $group['participants_count']
                        ?? $group['participantsDataCount']
                        ?? $group['totalParticipants']
                        ?? $group['size']
                        ?? $group['memberCount']
                        ?? $group['membersCount']
                        ?? 0)
                );
            }

            $seen[$jid] = true;
            $out[] = [
                'id' => $jid,
                'subject' => $subject !== '' ? $subject : 'Grupo sem nome',
                'participants' => $participants,
                'participants_count' => $participantsCount,
                'raw' => $group,
            ];
        }
    }

    return $out;
}

/**
 * Busca grupos da instância Evolution da loja.
 */
function ai_evolution_fetch_groups(int $tenantId): array
{
    $connection = ai_evolution_get_connection($tenantId);
    $instance = trim((string)($connection['instance_name'] ?? ''));
    $baseUrl = trim((string)($connection['base_url'] ?? ''));
    $token = trim((string)($connection['global_token'] ?? ''));

    if ($instance === '' || $baseUrl === '' || $token === '') {
        return [
            'ok' => false,
            'error' => 'Configuração Evolution incompleta para sincronizar grupos.',
            'http_code' => 0,
            'groups' => [],
            'raw' => [],
        ];
    }

    $basePaths = [
        '/group/fetchAllGroups/' . rawurlencode($instance),
        '/group/fetchAll/' . rawurlencode($instance),
        '/group/getGroups/' . rawurlencode($instance),
    ];
    $paths = [];
    foreach ($basePaths as $basePath) {
        $paths[] = $basePath . '?getParticipants=false';
        $paths[] = $basePath . '?getParticipants=true';
        $paths[] = $basePath;
    }
    $paths = array_values(array_unique($paths));

    $last = ['ok' => false, 'http_code' => 0, 'error' => 'Sem resposta válida', 'json' => [], 'raw' => ''];
    $lastSuccessful = null;
    foreach ($paths as $path) {
        $resp = ai_evolution_http_request('GET', $baseUrl . $path, $token);
        $last = $resp;

        if (!empty($resp['ok'])) {
            $json = is_array($resp['json'] ?? null) ? $resp['json'] : [];
            $groups = ai_evolution_normalize_groups_payload($json);
            $lastSuccessful = [
                'ok' => true,
                'error' => '',
                'http_code' => (int)($resp['http_code'] ?? 200),
                'groups' => $groups,
                'raw' => $json,
            ];
            if (!empty($groups)) {
                return $lastSuccessful;
            }
        }
    }

    if (is_array($lastSuccessful)) {
        return $lastSuccessful;
    }

    return [
        'ok' => false,
        'error' => (string)($last['error'] ?? 'Falha ao buscar grupos na Evolution'),
        'http_code' => (int)($last['http_code'] ?? 0),
        'groups' => [],
        'raw' => is_array($last['json'] ?? null) ? $last['json'] : [],
    ];
}

/**
 * Solicita saída da instância atual de um grupo WhatsApp.
 * Compatível com variações de rota da Evolution API.
 */
function ai_evolution_leave_group(int $tenantId, string $groupJid): array
{
    $groupJid = trim($groupJid);
    if ($groupJid === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'groupJid não informado.',
            'raw' => [],
        ];
    }
    if (stripos($groupJid, '@g.us') === false) {
        $groupJid .= '@g.us';
    }

    $connection = ai_evolution_get_connection($tenantId);
    $instance = trim((string)($connection['instance_name'] ?? ''));
    $baseUrl = trim((string)($connection['base_url'] ?? ''));
    $token = trim((string)($connection['global_token'] ?? ''));
    if ($instance === '' || $baseUrl === '' || $token === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Configuração Evolution incompleta.',
            'raw' => [],
        ];
    }

    $instanceEnc = rawurlencode($instance);
    $groupEnc = rawurlencode($groupJid);
    $attempts = [
        ['method' => 'DELETE', 'path' => '/group/leaveGroup/' . $instanceEnc . '?groupJid=' . $groupEnc, 'body' => null],
        ['method' => 'DELETE', 'path' => '/group/leave/' . $instanceEnc . '?groupJid=' . $groupEnc, 'body' => null],
        ['method' => 'POST', 'path' => '/group/leaveGroup/' . $instanceEnc, 'body' => ['groupJid' => $groupJid]],
        ['method' => 'POST', 'path' => '/group/leave/' . $instanceEnc, 'body' => ['groupJid' => $groupJid]],
    ];

    $last = ['ok' => false, 'http_code' => 0, 'error' => 'Sem resposta da Evolution', 'json' => []];
    foreach ($attempts as $attempt) {
        $resp = ai_evolution_http_request(
            (string)$attempt['method'],
            $baseUrl . (string)$attempt['path'],
            $token,
            is_array($attempt['body']) ? $attempt['body'] : null
        );
        $last = $resp;
        if (!empty($resp['ok'])) {
            return [
                'ok' => true,
                'http_code' => (int)($resp['http_code'] ?? 200),
                'error' => '',
                'raw' => is_array($resp['json'] ?? null) ? $resp['json'] : [],
            ];
        }
    }

    return [
        'ok' => false,
        'http_code' => (int)($last['http_code'] ?? 0),
        'error' => (string)($last['error'] ?? 'Falha ao sair do grupo na Evolution'),
        'raw' => is_array($last['json'] ?? null) ? $last['json'] : [],
    ];
}

/**
 * Busca detalhes da instância na Evolution API e salva o número do dono.
 */
function ai_evolution_fetch_and_save_instance_number(int $tenantId = 0): array
{
    $tid = $tenantId ?: ai_tenant_id();
    $connection = ai_evolution_get_connection($tid);
    $instance = trim((string)$connection['instance_name'] ?? '');
    $baseUrl = trim((string)$connection['base_url'] ?? '');
    $token = trim((string)$connection['global_token'] ?? '');

    if ($instance === '' || $baseUrl === '' || $token === '') {
        return ['ok' => false, 'error' => 'Configuração Evolution incompleta'];
    }

    $result = ai_evolution_http_request(
        'GET',
        $baseUrl . '/instance/fetchInstance/' . rawurlencode($instance),
        $token
    );

    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Erro ao buscar instância'];
    }

    $json = $result['json'] ?? [];
    $ownerNumber = ai_evolution_extract_instance_number($json);

    if ($ownerNumber !== '') {
        ai_save_setting('ai_whatsapp_number', $ownerNumber, $tid);
        return ['ok' => true, 'number' => $ownerNumber];
    }

    return ['ok' => false, 'error' => 'Não foi possível extrair o número da instância'];
}

/**
 * Envia notificação WhatsApp para o CLIENTE sobre andamento do pedido.
 * Respeita as configurações de notificação da loja.
 * Estágios suportados: separando, rota, entregue, pago.
 * O primeiro estágio (pendente/Pedido) nunca dispara.
 */
function ai_notify_customer_status_change(int $tenantId, int $orderId, string $newStatus): bool
{
    try {
        $stagesSupported = ['separando', 'rota', 'entregue', 'pago'];
        if (!in_array($newStatus, $stagesSupported, true)) return false;

        // Verificar toggle mestre
        $masterEnabled = (string)ai_get_setting('ai_notify_customer_enabled', '1', $tenantId);
        if ($masterEnabled !== '1') return false;

        // Verificar se este estágio está habilitado
        $stageEnabled = (string)ai_get_setting('ai_notify_stage_' . $newStatus, '1', $tenantId);
        if ($stageEnabled !== '1') return false;

        // Buscar pedido com nome do perfil atualizado
        $stmt = db()->prepare("
            SELECT o.id, o.whatsapp_phone, o.total_amount,
                   COALESCE(p.name, o.customer_name) AS resolved_name
            FROM ai_orders o
            LEFT JOIN ai_chat_profiles p
                   ON p.tenant_id = o.tenant_id AND p.whatsapp_phone = o.whatsapp_phone
            WHERE o.id = :id AND o.tenant_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':id' => $orderId, ':tid' => $tenantId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return false;

        $phone = preg_replace('/\D+/', '', (string)($order['whatsapp_phone'] ?? ''));
        if ($phone === '') return false;

        $name  = trim((string)($order['resolved_name'] ?: 'Cliente'));
        $total = 'R$ ' . number_format((float)($order['total_amount'] ?? 0), 2, ',', '.');

        // Mensagens padrão por estágio
        $defaults = [
            'separando' => "✅ Olá, {{nome}}! Seu pedido #{{pedido}} está sendo separado. Em breve estará a caminho! 🛍️",
            'rota'      => "🛵 {{nome}}, seu pedido #{{pedido}} saiu para entrega! Total: {{total}}. Aguarde, estamos a caminho! 😊",
            'entregue'  => "🎉 {{nome}}, seu pedido #{{pedido}} foi entregue com sucesso! Obrigada pela compra! 💜",
            'pago'      => "✅ {{nome}}, seu pagamento Pix foi confirmado! Pedido #{{pedido}} no valor de {{total}}. Já estamos preparando! 🛍️",
        ];

        $msg = (string)ai_get_setting('ai_notify_msg_' . $newStatus, $defaults[$newStatus], $tenantId);
        if (trim($msg) === '') $msg = $defaults[$newStatus];

        // Substituir shortcodes
        $msg = str_replace(
            ['{{nome}}', '{{pedido}}', '{{total}}'],
            [$name, (string)$orderId, $total],
            $msg
        );

        $result = ai_evolution_send_text($tenantId, $phone, $msg);
        
        if (!$result['ok']) {
            error_log("Moda IA: Erro ao enviar notificação para {$phone}: " . ($result['error'] ?? 'unknown'));
        }

        return (bool)($result['ok'] ?? false);
    } catch (Exception $e) {
        error_log("Moda IA: Exceção em ai_notify_customer_status_change: " . $e->getMessage());
        return false;
    }
}

function ai_notify_store_new_order(int $tenantId, int $orderId): void
{
    try {
        $notifyNewOrder = (string)ai_get_setting('ai_notify_new_order', '1', $tenantId);
        if ($notifyNewOrder !== '1') {
            return;
        }

        $primaryEnabled = (string)ai_get_setting('ai_notify_store_primary_enabled', '1', $tenantId) === '1';
        $secondaryEnabled = (string)ai_get_setting('ai_notify_store_secondary_enabled', '1', $tenantId) === '1';
        $to1 = trim((string)ai_get_setting('ai_whatsapp_number', '', $tenantId));
        $to2 = trim((string)ai_get_setting('ai_whatsapp_number_2', '', $tenantId));
        
        $recipients = [];
        if ($primaryEnabled && $to1 !== '') {
            foreach (explode(',', $to1) as $num) {
                $num = preg_replace('/\D+/', '', trim($num));
                if ($num !== '') $recipients[] = $num;
            }
        }
        if ($secondaryEnabled && $to2 !== '') {
            foreach (explode(',', $to2) as $num) {
                $num = preg_replace('/\D+/', '', trim($num));
                if ($num !== '') $recipients[] = $num;
            }
        }
        $recipients = array_unique($recipients);
        if (empty($recipients)) return;

        $stmt = db()->prepare("
            SELECT id, customer_name, whatsapp_phone, total_amount, status, created_at
            FROM ai_orders
            WHERE tenant_id = :tid AND id = :id
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tenantId, ':id' => $orderId]);
        $o = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$o) return;

        $name = trim((string)($o['customer_name'] ?? 'Cliente'));
        $phone = preg_replace('/\D+/', '', (string)($o['whatsapp_phone'] ?? ''));
        $total = (float)($o['total_amount'] ?? 0);
        $msg = "🛍️ Novo pedido via Moda IA\n"
            . "Pedido #{$orderId}\n"
            . ($name !== '' ? "Cliente: {$name}\n" : '')
            . ($phone !== '' ? "WhatsApp: {$phone}\n" : '')
            . "Total: R$ " . number_format($total, 2, ',', '.') . "\n"
            . "Status: " . strtoupper((string)($o['status'] ?? '')) . "\n"
            . rtrim(ROOT_URL, '/') . "/admin/concierge_pedidos.php";

        foreach ($recipients as $to) {
            ai_evolution_send_text($tenantId, $to, $msg);
        }
    } catch (Exception $e) {
    }
}

function ai_notify_store_payment_confirmed(int $tenantId, int $orderId): void
{
    try {
        $notifyPayment = (string)ai_get_setting('ai_notify_payment_confirmed', '1', $tenantId);
        if ($notifyPayment !== '1') {
            return;
        }

        $primaryEnabled = (string)ai_get_setting('ai_notify_store_primary_enabled', '1', $tenantId) === '1';
        $secondaryEnabled = (string)ai_get_setting('ai_notify_store_secondary_enabled', '1', $tenantId) === '1';
        $to1 = trim((string)ai_get_setting('ai_whatsapp_number', '', $tenantId));
        $to2 = trim((string)ai_get_setting('ai_whatsapp_number_2', '', $tenantId));

        $recipients = [];
        if ($primaryEnabled && $to1 !== '') {
            foreach (explode(',', $to1) as $num) {
                $num = preg_replace('/\D+/', '', trim($num));
                if ($num !== '') $recipients[] = $num;
            }
        }
        if ($secondaryEnabled && $to2 !== '') {
            foreach (explode(',', $to2) as $num) {
                $num = preg_replace('/\D+/', '', trim($num));
                if ($num !== '') $recipients[] = $num;
            }
        }
        $recipients = array_unique($recipients);
        if (empty($recipients)) return;

        $stmt = db()->prepare("
            SELECT id, customer_name, whatsapp_phone, total_amount, payment_method, paid_at
            FROM ai_orders
            WHERE tenant_id = :tid AND id = :id
            LIMIT 1
        ");
        $stmt->execute([':tid' => $tenantId, ':id' => $orderId]);
        $o = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$o) return;

        $name = trim((string)($o['customer_name'] ?? 'Cliente'));
        $phone = preg_replace('/\D+/', '', (string)($o['whatsapp_phone'] ?? ''));
        $total = (float)($o['total_amount'] ?? 0);
        $method = strtoupper((string)($o['payment_method'] ?? 'PIX'));

        $msg = "✅ Pagamento confirmado (Moda IA)\n"
            . "Pedido #{$orderId}\n"
            . ($name !== '' ? "Cliente: {$name}\n" : '')
            . ($phone !== '' ? "WhatsApp: {$phone}\n" : '')
            . "Total: R$ " . number_format($total, 2, ',', '.') . "\n"
            . "Método: {$method}\n"
            . rtrim(ROOT_URL, '/') . "/admin/concierge_pedidos.php";

        foreach ($recipients as $to) {
            ai_evolution_send_text($tenantId, $to, $msg);
        }
    } catch (Exception $e) {
    }
}

/**
 * Verifica se um contato possui pedido ativo (não entregue / não cancelado).
 * Usado para decidir se o status 'Manual' ainda é relevante.
 */
function ai_evolution_has_active_order(int $tenantId, string $phoneOrJid): bool
{
    $candidates = ai_evolution_phone_candidates($phoneOrJid);
    if (empty($candidates)) {
        return false;
    }
    $in = implode(',', array_fill(0, count($candidates), '?'));
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM ai_orders
            WHERE tenant_id = ?
              AND whatsapp_phone IN ($in)
              AND status IN ('pendente','pago','separando','rota')
            LIMIT 1
        ");
        $stmt->execute(array_merge([$tenantId], $candidates));
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function ai_evolution_set_atendimento_status(int $tenantId, string $remoteJid, string $status, int $userId = 0): void
{
    ai_evolution_ensure_status_table();
    $status = $status === 'Manual' ? 'Manual' : 'Ativo';
    $stmt = db()->prepare("
        INSERT INTO ai_status_atendimento (tenant_id, remote_jid, status, takeover_by_user_id, updated_at)
        VALUES (:tid, :jid, :st, :uid, NOW())
        ON DUPLICATE KEY UPDATE status = VALUES(status), takeover_by_user_id = VALUES(takeover_by_user_id), updated_at = NOW()
    ");
    $stmt->execute([
        ':tid' => $tenantId,
        ':jid' => $remoteJid,
        ':st'  => $status,
        ':uid' => $userId,
    ]);
}

function ai_evolution_ensure_status_table(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS ai_status_atendimento (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                remote_jid VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'Ativo',
                takeover_by_user_id INT NOT NULL DEFAULT 0,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_tenant_jid (tenant_id, remote_jid),
                KEY idx_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
    }
}

/**
 * Resume perfil do cliente para envio ao n8n.
 */
function ai_evolution_get_customer_memory(int $tenantId, string $remoteJid): ?array
{
    $phone = ai_evolution_number_from_jid($remoteJid);
    if ($phone === '') {
        return null;
    }

    try {
        $candidates = ai_evolution_phone_candidates($remoteJid);
        if (empty($candidates)) {
            return null;
        }

        $in = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = db()->prepare("
            SELECT id, whatsapp_phone, name, usual_size, preferences_json, interesse_duvida, conversation_summary, total_interactions, last_interaction
            FROM ai_chat_profiles
            WHERE tenant_id = ?
              AND whatsapp_phone IN ($in)
            ORDER BY
              (CASE WHEN TRIM(COALESCE(conversation_summary, '')) <> '' THEN 1 ELSE 0 END +
               CASE WHEN TRIM(COALESCE(preferences_json, '')) <> '' AND TRIM(COALESCE(preferences_json, '')) <> '{}' THEN 1 ELSE 0 END +
               CASE WHEN TRIM(COALESCE(usual_size, '')) <> '' THEN 1 ELSE 0 END +
               CASE WHEN TRIM(COALESCE(interesse_duvida, '')) <> '' THEN 1 ELSE 0 END) DESC,
              last_interaction DESC,
              id DESC
            LIMIT 1
        ");
        $stmt->execute(array_merge([$tenantId], $candidates));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $prefs = [];
        if (!empty($row['preferences_json'])) {
            $decoded = json_decode($row['preferences_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $prefs = $decoded;
            }
        }

        return [
            'id'                 => (int)$row['id'],
            'remoteJid'          => $row['whatsapp_phone'],
            'pushName'           => $row['name'],
            'usual_size'         => $row['usual_size'],
            'interesse_duvida'   => (string)($row['interesse_duvida'] ?? ''),
            'conversation_summary' => (string)($row['conversation_summary'] ?? ''),
            'preferences'        => $prefs,
            'total_interactions' => (int)($row['total_interactions'] ?? 0),
            'last_interaction'   => $row['last_interaction'],
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Gera candidatos de telefone/JID para localizar registros no banco.
 */
function ai_evolution_phone_candidates(string $phoneOrJid): array
{
    $raw = trim($phoneOrJid);
    $digits = ai_evolution_number_from_jid($raw);
    if ($digits === '') {
        return [];
    }

    $candidates = [
        $digits,
        '+' . $digits,
        $digits . '@s.whatsapp.net',
        $digits . '@c.us',
    ];

    if (strpos($digits, '55') !== 0) {
        $candidates[] = '55' . $digits;
        $candidates[] = '+55' . $digits;
        $candidates[] = '55' . $digits . '@s.whatsapp.net';
        $candidates[] = '55' . $digits . '@c.us';
    } else {
        $withoutCountry = substr($digits, 2);
        if ($withoutCountry !== '') {
            $candidates[] = $withoutCountry;
            $candidates[] = '+' . $withoutCountry;
            $candidates[] = $withoutCountry . '@s.whatsapp.net';
            $candidates[] = $withoutCountry . '@c.us';
        }
    }

    if ($raw !== '') {
        $candidates[] = $raw;
    }

    return array_values(array_unique(array_filter($candidates, static fn($v) => (string)$v !== '')));
}

/**
 * Dicionário de termos para normalização obrigatória (Glossário).
 * Converte variações, erros comuns e termos informais para nomes canônicos.
 */
function ai_evolution_term_normalization_map(): array
{
    return [
        // Cropped
        'croppet' => 'Cropped',
        'cropet' => 'Cropped',
        'croopet' => 'Cropped',
        'croppie' => 'Cropped',
        'crop' => 'Cropped',
        'cropped' => 'Cropped',
        'blusa cropped' => 'Cropped',
        'cropedt' => 'Cropped',
        
        // Vestidos
        'vestio' => 'Vestidos',
        'vestico' => 'Vestidos',
        'vestido' => 'Vestidos',
        
        // Calças
        'calca' => 'Calças',
        'calças' => 'Calças',
        'calca jeans' => 'Calças',
        'calça' => 'Calças',
        
        // Shorts
        'short' => 'Shorts',
        'shorts' => 'Shorts',
        'bermudinha' => 'Shorts',
        'bermuda' => 'Shorts',
        
        // Saias
        'saia' => 'Saias',
        'sainha' => 'Saias',
        'saiote' => 'Saias',
        
        // Blusas
        'blusa' => 'Blusas',
        'blusinha' => 'Blusas',
        'blusinhas' => 'Blusas',
        
        // Conjuntos
        'conjunto' => 'Conjuntos',
        'conjuntinho' => 'Conjuntos',
        'look' => 'Conjuntos',
        
        // Macacão
        'macacao' => 'Macacão',
        'macacão' => 'Macacão',
        'macacaozinho' => 'Macacão',
        
        // Body
        'body' => 'Body',
        'bodinho' => 'Body',
        
        // Maio
        'maiô' => 'Maio',
        'maio' => 'Maio',
        'maiozinho' => 'Maio',
        
        // Biquinis
        'biquini' => 'Biquinis',
        'biquíni' => 'Biquinis',
        'biquin' => 'Biquinis',
        
        // Jaquetas
        'jaqueta' => 'Jaquetas',
        'jaketinha' => 'Jaquetas',
        'jaquetinha' => 'Jaquetas',
        
        // Blazer
        'blazer' => 'Blazer',
        'blazerzinho' => 'Blazer',
        
        // Moletom
        'moletom' => 'Moletom',
        'moleton' => 'Moletom',
        'moletonzinho' => 'Moletom',
        
        // Leggings
        'legging' => 'Leggings',
        'leggin' => 'Leggings',
        'legin' => 'Leggings',
        
        // Regatas
        'regata' => 'Regatas',
        'regatinha' => 'Regatas',
        
        // Cardigan
        'cardigan' => 'Cardigan',
        'cardigam' => 'Cardigan',
        'cardi' => 'Cardigan',
        
        // Kimono
        'kimono' => 'Kimono',
        'quimono' => 'Kimono',
        
        // Tops
        'top' => 'Tops',
        'topinho' => 'Tops',
        'topzinho' => 'Tops',
        
        // Tricot
        'tricot' => 'Tricot',
        'trico' => 'Tricot',
        'tricô' => 'Tricot',
        'blusa de trico' => 'Tricot',
        'blusa de tricô' => 'Tricot',
    ];
}

/**
 * Remove acentos e caracteres especiais de uma string para comparação.
 */
function ai_evolution_remove_accents(string $str): string
{
    $from = 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ';
    $to   = 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY';
    return strtr($str, $from, $to);
}

/**
 * Normaliza o termo de busca usando o glossário e heurísticas inteligentes.
 */
function ai_evolution_normalize_query_term(string $query): string
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    if ($query === '') return '';

    $map = ai_evolution_term_normalization_map();
    
    // 1. Verificação Exata (case-insensitive já que normalizamos para lower)
    $lowerMap = [];
    foreach ($map as $key => $val) {
        $lowerMap[mb_strtolower($key, 'UTF-8')] = $val;
    }

    if (isset($lowerMap[$query])) {
        return $lowerMap[$query];
    }

    // 1.1 Tenta remover o plural comum (s) se não houver match direto
    if (mb_strlen($query, 'UTF-8') > 3 && mb_substr($query, -1) === 's') {
        $singular = mb_substr($query, 0, -1);
        if (isset($lowerMap[$singular])) {
            return $lowerMap[$singular];
        }
    }

    // 2. Verificação de sub-strings (ex: "quero um cropped" -> contém "cropped")
    // Ordenar chaves por tamanho descendente para pegar termos mais específicos primeiro
    $keys = array_keys($lowerMap);
    usort($keys, function($a, $b) {
        return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
    });

    foreach ($keys as $key) {
        if (preg_match('/\b' . preg_quote($key, '/') . '\b/u', $query)) {
            return $lowerMap[$key];
        }
    }

    // 3. Busca Inteligente: Distância de Levenshtein para erros de digitação leves
    if (mb_strlen($query, 'UTF-8') > 3) {
        $bestMatch = null;
        $shortestDistance = -1;
        
        $queryClean = ai_evolution_remove_accents($query);

        foreach ($lowerMap as $key => $canonical) {
            if (mb_strlen($key, 'UTF-8') <= 3) continue;

            $keyClean = ai_evolution_remove_accents($key);
            $dist = levenshtein($queryClean, $keyClean);
            
            // Limite de tolerância: 1 erro para cada 4 caracteres, max 2
            $tolerance = min(2, floor(mb_strlen($keyClean, 'UTF-8') / 4));
            
            if ($dist <= $tolerance && ($shortestDistance === -1 || $dist < $shortestDistance)) {
                $bestMatch = $canonical;
                $shortestDistance = $dist;
            }
        }

        if ($bestMatch !== null) {
            return $bestMatch;
        }
    }

    return $query;
}

/**
 * Tokeniza uma query de busca em termos únicos e aplica normalização em cada termo.
 */
function ai_evolution_tokenize_query(string $query): array
{
    $query = mb_substr(trim($query), 0, 300, 'UTF-8');
    if ($query === '') return [];

    // Normalização básica para evitar problemas com regex Unicode
    $query = mb_strtolower($query, 'UTF-8');
    
    // Remove caracteres especiais e divide por espaços
    $clean = preg_replace('/[^\w\s]/u', ' ', $query);
    $tokens = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    if (!is_array($tokens)) return [];

    // Normalização de cada token individualmente (Inteligência extra)
    $normalizedTokens = [];
    foreach ($tokens as $token) {
        $normalizedTokens[] = ai_evolution_normalize_query_term($token);
    }

    $tokens = array_slice(array_values(array_unique($normalizedTokens)), 0, 12);
    return $tokens;
}
/**
 * Expansão semântica simples para termos de tags/estilo.
 * Mantém o termo original e adiciona sinônimos relevantes.
 */
function ai_evolution_semantic_expand(string $term): array
{
    $term = mb_strtolower(trim($term), 'UTF-8');
    if ($term === '') return [];

    $term = (string)preg_replace('/\s+/u', ' ', $term);

    $groups = [
        ['elegante', 'chique', 'sofisticado', 'social', 'refinado'],
        ['festa', 'balada', 'evento', 'noite'],
        ['casual', 'dia a dia', 'conforto', 'básico', 'basico'],
        ['moderno', 'fashion', 'tendência', 'tendencia', 'estiloso'],
        ['romântico', 'romantico', 'delicado', 'feminino'],
        ['executivo', 'trabalho', 'office', 'alfaiataria'],
        ['verão', 'verao', 'leve', 'fresco'],
        ['inverno', 'quente', 'tricô', 'trico'],
        ['praia', 'resort', 'veraneio'],
    ];

    $expanded = [$term];
    foreach ($groups as $group) {
        if (in_array($term, $group, true)) {
            $expanded = array_merge($expanded, $group);
        }
    }

    return array_slice(array_values(array_unique($expanded)), 0, 20);
}
/**
 * Mapeamento de sinônimos de cores para uma base canônica.
 */
function ai_evolution_color_alias_map(): array
{
    return [
        'azul'            => 'azul',
        'azul claro'      => 'azul',
        'azul royal'      => 'azul',
        'azul turquesa'   => 'azul',
        'azul piscina'    => 'azul',
        'rosa'            => 'rosa',
        'pink'            => 'pink',
        'fucsia'          => 'pink',
        'fúcsia'          => 'pink',
        'vermelho'        => 'vermelho',
        'verde'           => 'verde',
        'verde militar'   => 'oliva',
        'oliva'           => 'oliva',
        'amarelo'         => 'amarelo',
        'laranja'         => 'laranja',
        'branco'          => 'branco',
        'off white'       => 'branco',
        'preto'           => 'preto',
        'cinza'           => 'cinza',
        'mescla'          => 'cinza',
        'bege'            => 'bege',
        'nude'            => 'bege',
        'caramelo'        => 'bege',
        'marrom'          => 'marrom',
        'chocolate'       => 'marrom',
        'vinho'           => 'vinho',
        'bordo'           => 'vinho',
        'bordô'           => 'vinho',
        'marsala'         => 'vinho',
        'navy'            => 'navy',
        'marinho'         => 'navy',
        'azul marinho'    => 'navy',
        'azul escuro'     => 'navy',
        'roxo'            => 'roxo',
        'lilas'           => 'roxo',
        'lilás'           => 'roxo',
        'dourado'         => 'dourado',
        'gold'            => 'dourado',
        'prata'           => 'prata',
        'silver'          => 'prata',
        'floral'          => 'floral',
        'estampado'       => 'estampado',
        'listrado'        => 'listrado',
        'animal print'    => 'animal',
        'animal'          => 'animal',
        'xadrez'          => 'xadrez',
        'tie dye'         => 'tie dye',
        'tiedye'          => 'tie dye',
    ];
}

/**
 * Normaliza um termo de cor para a base canônica usada na busca.
 */
function ai_evolution_normalize_color_term(string $term): string
{
    $term = mb_strtolower(trim($term), 'UTF-8');
    if ($term === '') return '';
    $term = (string)preg_replace('/\s+/u', ' ', $term);
    $map = ai_evolution_color_alias_map();
    if (isset($map[$term])) return $map[$term];
    return $term;
}

/**
 * Extrai termos de cor relevantes da query/tokens já normalizados.
 */
function ai_evolution_extract_color_terms(string $query, array $tokens = []): array
{
    $query = mb_strtolower(trim($query), 'UTF-8');
    $query = (string)preg_replace('/\s+/u', ' ', $query);
    $map = ai_evolution_color_alias_map();
    $found = [];

    foreach ($map as $alias => $normalized) {
        if ($query !== '' && mb_stripos($query, $alias) !== false) {
            $found[] = $normalized;
        }
    }

    foreach ($tokens as $token) {
        $normalized = ai_evolution_normalize_color_term((string)$token);
        if (isset($map[$normalized]) || in_array($normalized, $map, true)) {
            $found[] = isset($map[$normalized]) ? $map[$normalized] : $normalized;
        }
    }

    return array_values(array_unique(array_filter($found, static fn($v) => (string)$v !== '')));
}

/**
 * Snapshot das categorias em estoque. Cache 5 min em ai_settings.
 */
function ai_evolution_catalog_snapshot(int $tenantId): array
{
    $cacheKey = 'ai_catalog_snapshot_cache';
    $ttl      = 300;

    $cached = ai_get_setting($cacheKey, '', $tenantId);
    if ($cached !== '') {
        $data = json_decode($cached, true);
        if (is_array($data) 
            && isset($data['ts'], $data['snapshot']) 
            && (time() - (int)$data['ts']) < $ttl 
        ) {
            return (array)$data['snapshot'];
        }
    }

    try {
        // Contabilizar apenas VARIANTES com estoque > 0
        $stmt = db()->prepare("
            SELECT c.category_name, COUNT(DISTINCT v.id) AS total
            FROM ai_catalogo_variants v
            INNER JOIN ai_catalogo_models m ON m.id = v.model_id
            INNER JOIN categorys c ON c.category_id = m.category_id
            INNER JOIN category_to_store c2s ON c2s.ccategory_id = c.category_id
            WHERE m.tenant_id = :tid
              AND c2s.store_id = :tid2
              AND c2s.status = 1
              AND m.is_active = 1
              AND v.is_active = 1
              AND v.stock_qty > 0
            GROUP BY c.category_name
            ORDER BY total DESC
        ");
        $stmt->execute([':tid' => $tenantId, ':tid2' => $tenantId]);
        $snapshot = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $snapshot[$row['category_name']] = (int)$row['total'];
        }
    } catch (\Throwable $e) {
        error_log('ai_catalog_snapshot: ' . $e->getMessage());
        $snapshot = [];
    }

    try {
        ai_save_setting($cacheKey, json_encode([
            'ts'       => time(),
            'snapshot' => $snapshot,
        ], JSON_UNESCAPED_UNICODE), $tenantId);
    } catch (\Throwable $e) {}

    return $snapshot;
}

/**
 * Invalida cache do snapshot. Chamar após salvar produto ou estoque.
 */
function ai_evolution_invalidate_snapshot_cache(int $tenantId): void
{
    try {
        ai_save_setting('ai_catalog_snapshot_cache', '', $tenantId);
    } catch (\Throwable $e) {}
}

/**
 * Registra buscas sem resultado. Falha silenciosa — nunca trava atendimento.
 */
function ai_evolution_log_search_miss(
    int    $tenantId,
    string $queryOriginal,
    array  $tokens     = [],
    array  $colorTerms = [],
    string $phone      = ''
): void {
    try {
        $stmt = db()->prepare("
            INSERT INTO ai_search_misses 
                (tenant_id, query_original, tokens_json, colors_json, 
                 session_phone, created_at)
            VALUES (:tid, :query, :tokens, :colors, :phone, NOW())
        ");
        $stmt->execute([
            ':tid'    => $tenantId,
            ':query'  => mb_substr(trim($queryOriginal), 0, 500, 'UTF-8'),
            ':tokens' => !empty($tokens) 
                ? json_encode($tokens, JSON_UNESCAPED_UNICODE) : null,
            ':colors' => !empty($colorTerms) 
                ? json_encode($colorTerms, JSON_UNESCAPED_UNICODE) : null,
            ':phone'  => $phone !== '' 
                ? preg_replace('/\D+/', '', $phone) : null,
        ]);
    } catch (\Throwable $e) {
        error_log('ai_search_miss: ' . $e->getMessage());
    }
}

/**
 * Retorna o último pedido relacionado ao contato.
 */
function ai_evolution_get_latest_order_context(int $tenantId, string $phoneOrJid, bool $includeItems = true): ?array
{
    $candidates = ai_evolution_phone_candidates($phoneOrJid);
    if (empty($candidates)) {
        return null;
    }

    $in = implode(',', array_fill(0, count($candidates), '?'));

    try {
        $sql = "
            SELECT id, whatsapp_phone, customer_name, status, total_amount, payment_method, payment_ref, payment_link, paid_at, notes, created_at, updated_at
            FROM ai_orders
            WHERE tenant_id = ?
              AND whatsapp_phone IN ($in)
              AND (
                  status IN ('pendente','pago','separando','rota') 
                  OR updated_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
              )
            ORDER BY (status IN ('pendente','pago','separando','rota')) DESC, updated_at DESC, id DESC
            LIMIT 1
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $candidates));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }

        $order['id'] = (int)($order['id'] ?? 0);
        $order['total_amount'] = (float)($order['total_amount'] ?? 0);
        $order['items'] = [];

        if ($includeItems) {
            $itemsStmt = db()->prepare("
                SELECT oi.id, oi.variant_id, oi.model_name, oi.color, oi.size, oi.qty, oi.unit_price, oi.subtotal, v.sku, v.photo_webp
                FROM ai_order_items oi
                LEFT JOIN ai_catalogo_variants v ON v.id = oi.variant_id
                WHERE oi.order_id = :oid
                ORDER BY oi.id ASC
            ");
            $itemsStmt->execute([':oid' => $order['id']]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as &$it) {
                $it['id'] = (int)($it['id'] ?? 0);
                $it['variant_id'] = (int)($it['variant_id'] ?? 0);
                $it['qty'] = (int)($it['qty'] ?? 0);
                $it['unit_price'] = (float)($it['unit_price'] ?? 0);
                $it['subtotal'] = (float)($it['subtotal'] ?? 0);
                $it['photo_url'] = ai_evolution_get_image_url($it['photo_webp'] ?? '');
            }
            unset($it);

            $order['items'] = $items;
        }

        return $order;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Monta resumo de conversa para enviar ao n8n.
 */
function ai_evolution_build_conversation_summary(?array $orderContext, ?array $profile): string
{
    $stored = trim((string)($orderContext['notes'] ?? ''));
    if ($stored !== '') {
        return $stored;
    }

    $parts = [];

    if (!empty($orderContext['id'])) {
        $status = (string)($orderContext['status'] ?? 'pendente');
        $parts[] = 'Pedido #' . (int)$orderContext['id'] . ' em status ' . $status . '.';

        if ($status === 'pendente' && !empty($orderContext['payment_link'])) {
            $parts[] = 'Link de checkout ativo: ' . $orderContext['payment_link'];
        }

        $items = is_array($orderContext['items'] ?? null) ? $orderContext['items'] : [];
        if (!empty($items)) {
            $snap = [];
            foreach (array_slice($items, 0, 3) as $it) {
                $name = trim((string)($it['model_name'] ?? 'Item'));
                $size = trim((string)($it['size'] ?? ''));
                $color = trim((string)($it['color'] ?? ''));
                $qty = (int)($it['qty'] ?? 1);
                $line = $name;
                if ($size !== '') $line .= ' tam ' . $size;
                if ($color !== '') $line .= ' cor ' . $color;
                $line .= ' x' . $qty;
                $snap[] = $line;
            }
            if (!empty($snap)) {
                $parts[] = 'Itens recentes: ' . implode(', ', $snap) . '.';
            }
        }

        $parts[] = 'Total atual R$ ' . number_format((float)($orderContext['total_amount'] ?? 0), 2, ',', '.') . '.';
    }

    if (is_array($profile)) {
        $size = trim((string)($profile['usual_size'] ?? ''));
        if ($size !== '') {
            $parts[] = 'Tamanho habitual: ' . $size . '.';
        }

        $prefs = $profile['preferences'] ?? [];
        if (is_array($prefs) && !empty($prefs)) {
            $flatten = [];
            array_walk_recursive($prefs, static function ($value) use (&$flatten): void {
                if (is_scalar($value)) {
                    $text = trim((string)$value);
                    if ($text !== '') $flatten[] = $text;
                }
            });
            $flatten = array_values(array_unique($flatten));
            if (!empty($flatten)) {
                $parts[] = 'Preferências: ' . implode(', ', array_slice($flatten, 0, 4)) . '.';
            }
        }
    }

    if (empty($parts)) {
        return '';
    }

    return trim(implode(' ', $parts));
}

/**
 * Contexto consolidado da conversa para uso no webhook e nas APIs de tools.
 */
function ai_evolution_get_conversation_context(int $tenantId, string $phoneOrJid): array
{
    $digits = ai_evolution_number_from_jid($phoneOrJid);
    $remoteJid = strpos($phoneOrJid, '@') !== false
        ? trim($phoneOrJid)
        : ($digits !== '' ? $digits . '@s.whatsapp.net' : '');

    $order = ai_evolution_get_latest_order_context($tenantId, $phoneOrJid, true);

    if ($remoteJid === '' && is_array($order) && !empty($order['whatsapp_phone'])) {
        $orderDigits = ai_evolution_number_from_jid((string)$order['whatsapp_phone']);
        if ($orderDigits !== '') {
            $digits = $orderDigits;
            $remoteJid = $orderDigits . '@s.whatsapp.net';
        }
    }

    $profileLookup = $remoteJid !== '' ? $remoteJid : ($digits !== '' ? $digits : $phoneOrJid);
    $profile = $profileLookup !== '' ? ai_evolution_get_customer_memory($tenantId, $profileLookup) : null;
    $atendimento = $remoteJid !== '' ? ai_evolution_get_atendimento_status($tenantId, $remoteJid) : [
        'status' => 'Ativo',
        'takeover_by_user_id' => 0,
        'updated_at' => null,
    ];
    $summary = ai_evolution_build_conversation_summary($order, $profile);

    return [
        'remote_jid' => $remoteJid,
        'phone_digits' => $digits,
        'profile' => $profile,
        'atendimento' => $atendimento,
        'order' => $order,
        'summary' => $summary,
    ];
}

/**
 * Heurística básica para identificar se uma string parece SKU.
 */
function ai_evolution_is_likely_sku_value(string $value): bool
{
    $value = trim($value);
    if ($value === '') return false;
    if (mb_strlen($value, 'UTF-8') < 4 || mb_strlen($value, 'UTF-8') > 60) return false;
    if (strpos($value, ' ') !== false) return false;
    if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) return false;
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $value);
}

/**
 * Localiza SKU exato (variante ou modelo) dentro de um texto livre.
 */
function ai_evolution_find_exact_sku_value(int $tenantId, string $queryText): ?string
{
    $queryText = trim($queryText);
    if ($queryText === '') return null;

    $candidates = [$queryText];
    if (preg_match_all('/[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+/', $queryText, $matches)) {
        foreach (($matches[0] ?? []) as $match) {
            $candidates[] = (string)$match;
        }
    }

    $normalizedCandidates = [];
    foreach ($candidates as $candidate) {
        $clean = trim((string)$candidate, " \t\n\r\0\x0B,.;:!?()[]{}\"'");
        if ($clean !== '') {
            $normalizedCandidates[$clean] = true;
        }
    }

    foreach (array_keys($normalizedCandidates) as $candidate) {
        if (!ai_evolution_is_likely_sku_value($candidate)) {
            continue;
        }
        try {
            $stVariant = db()->prepare("
                SELECT v.sku
                FROM ai_catalogo_variants v
                INNER JOIN ai_catalogo_models m ON m.id = v.model_id
                WHERE v.tenant_id = :tid
                  AND m.tenant_id = :tid2
                  AND m.is_active = 1
                  AND v.is_active = 1
                  AND UPPER(v.sku) = UPPER(:sku)
                LIMIT 1
            ");
            $stVariant->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':sku' => $candidate]);
            $foundVariant = $stVariant->fetchColumn();
            if (is_string($foundVariant) && trim($foundVariant) !== '') {
                return trim($foundVariant);
            }

            $stModel = db()->prepare("
                SELECT m.sku
                FROM ai_catalogo_models m
                WHERE m.tenant_id = :tid
                  AND m.is_active = 1
                  AND m.sku IS NOT NULL
                  AND m.sku <> ''
                  AND UPPER(m.sku) = UPPER(:sku)
                LIMIT 1
            ");
            $stModel->execute([':tid' => $tenantId, ':sku' => $candidate]);
            $foundModel = $stModel->fetchColumn();
            if (is_string($foundModel) && trim($foundModel) !== '') {
                return trim($foundModel);
            }
        } catch (\Throwable $e) {
            error_log('ai_find_exact_sku: '.$e->getMessage());
            return null;
        }
    }

    return null;
}

/**
 * Normaliza termo de tamanho (P/M/G/GG/42/etc).
 */
function ai_evolution_normalize_size_term(string $term): ?string
{
    $term = mb_strtolower(trim($term), 'UTF-8');
    if ($term === '') return null;
    $term = (string)preg_replace('/[^\p{L}\p{N}]/u', '', $term);
    if ($term === '') return null;

    $map = [
        'pp' => 'PP',
        'p' => 'P',
        'm' => 'M',
        'g' => 'G',
        'gg' => 'GG',
        'xg' => 'XG',
        'xgg' => 'XGG',
        'u' => 'U',
        'unico' => 'ÚNICO',
        'único' => 'ÚNICO',
        'onesize' => 'U',
    ];

    if (isset($map[$term])) {
        return $map[$term];
    }

    if (preg_match('/^\d{2,3}$/', $term)) {
        return $term;
    }

    return null;
}

/**
 * Extrai tamanho de query/tokens.
 */
function ai_evolution_extract_size_term(string $query, array $tokens = []): ?string
{
    if (empty($tokens)) {
        $tokens = ai_evolution_tokenize_query($query);
    }

    foreach ($tokens as $token) {
        $size = ai_evolution_normalize_size_term((string)$token);
        if ($size !== null) {
            return $size;
        }
    }

    if (preg_match('/\b(?:tam(?:anho)?\s*)?(pp|p|m|g|gg|xg|xgg|u|unico|único|\d{2,3})\b/ui', $query, $m)) {
        $size = ai_evolution_normalize_size_term((string)($m[1] ?? ''));
        if ($size !== null) {
            return $size;
        }
    }

    return null;
}

/**
 * Lista termos de categorias ativas para heurística da query legacy.
 */
function ai_evolution_get_active_category_terms(int $tenantId): array
{
    static $cache = [];
    if (isset($cache[$tenantId])) {
        return $cache[$tenantId];
    }

    $terms = [];
    try {
        $st = db()->prepare("
            SELECT DISTINCT c.category_name
            FROM categorys c
            INNER JOIN category_to_store c2s ON c2s.ccategory_id = c.category_id
            INNER JOIN ai_catalogo_models m ON m.category_id = c.category_id
            WHERE c2s.store_id = :tid
              AND c2s.status = 1
              AND m.tenant_id = :tid2
              AND m.is_active = 1
        ");
        $st->execute([':tid' => $tenantId, ':tid2' => $tenantId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $term = mb_strtolower(trim((string)$name), 'UTF-8');
            $term = (string)preg_replace('/\s+/u', ' ', $term);
            if ($term === '') continue;
            $terms[$term] = true;
            if (mb_strlen($term, 'UTF-8') > 3) {
                if (mb_substr($term, -1, 1, 'UTF-8') === 's') {
                    $singular = trim((string)preg_replace('/s$/u', '', $term));
                    if ($singular !== '') {
                        $terms[$singular] = true;
                    }
                } else {
                    $terms[$term . 's'] = true;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('ai_category_terms: '.$e->getMessage());
    }

    $cache[$tenantId] = array_keys($terms);
    return $cache[$tenantId];
}

/**
 * Converte query textual antiga em campos estruturados.
 */
function ai_evolution_parse_legacy_search_query(int $tenantId, string $legacyQuery): array
{
    $raw = trim($legacyQuery);
    $parsed = [
        'q' => null,
        'color' => null,
        'size' => null,
        'tags' => null,
        'sku' => null,
    ];
    if ($raw === '') {
        return $parsed;
    }

    $sku = ai_evolution_find_exact_sku_value($tenantId, $raw);
    if ($sku !== null) {
        $parsed['sku'] = $sku;
        return $parsed;
    }

    $normalized = mb_strtolower($raw, 'UTF-8');
    $normalized = (string)preg_replace('/\s+/u', ' ', $normalized);
    $tokens = ai_evolution_tokenize_query($normalized);
    if (empty($tokens)) {
        $parsed['q'] = $raw;
        return $parsed;
    }

    $parsed['size'] = ai_evolution_extract_size_term($normalized, $tokens);
    $colors = ai_evolution_extract_color_terms($normalized, $tokens);
    if (!empty($colors)) {
        $parsed['color'] = (string)$colors[0];
    }

    $categoryTerms = ai_evolution_get_active_category_terms($tenantId);
    $categorySet = array_fill_keys($categoryTerms, true);

    $stopwords = array_fill_keys([
        'de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'na', 'no',
        'e', 'ou', 'um', 'uma', 'uns', 'umas', 'tem', 'tenho', 'quero',
        'procuro', 'procurando', 'mostrar', 'mostra', 'me', 'pra', 'por',
        'favor', 'quais', 'qual', 'cores', 'cor', 'tamanho', 'tam', 'modelo',
    ], true);

    $colorTokenSet = [];
    foreach (ai_evolution_color_alias_map() as $alias => $canonical) {
        foreach (ai_evolution_tokenize_query($alias) as $aliasToken) {
            $colorTokenSet[$aliasToken] = true;
        }
        foreach (ai_evolution_tokenize_query($canonical) as $canonicalToken) {
            $colorTokenSet[$canonicalToken] = true;
        }
    }

    $contentTokens = [];
    foreach ($tokens as $token) {
        $token = trim((string)$token);
        if ($token === '') continue;
        if (isset($stopwords[$token])) continue;
        if (ai_evolution_normalize_size_term($token) !== null) continue;
        if (isset($colorTokenSet[$token])) continue;
        if (isset($colorTokenSet[ai_evolution_normalize_color_term($token)])) continue;
        $contentTokens[] = $token;
    }
    $contentTokens = array_values(array_unique($contentTokens));

    if (empty($contentTokens)) {
        if (!empty($parsed['color']) || !empty($parsed['size'])) {
            return $parsed;
        }
        $parsed['tags'] = $normalized;
        return $parsed;
    }

    if (count($contentTokens) === 1) {
        $single = $contentTokens[0];
        // Sempre preferimos q para termo único, pois q busca em categoria + nome + tags.
        // Isso resolve o problema de categorias (ex: Vestidos) serem movidas para tags e falharem.
        $parsed['q'] = $single;
        return $parsed;
    }

    $matchedCategory = null;
    $matchedCategoryLen = 0;
    foreach ($categoryTerms as $categoryTerm) {
        if ($categoryTerm === '') continue;
        if (mb_stripos($normalized, $categoryTerm) === false) continue;
        $len = mb_strlen($categoryTerm, 'UTF-8');
        if ($len > $matchedCategoryLen) {
            $matchedCategory = $categoryTerm;
            $matchedCategoryLen = $len;
        }
    }

    if ($matchedCategory !== null) {
        $parsed['q'] = $matchedCategory;
        $categoryTokens = ai_evolution_tokenize_query($matchedCategory);
        foreach ($categoryTokens as $catToken) {
            $idx = array_search($catToken, $contentTokens, true);
            if ($idx !== false) {
                unset($contentTokens[$idx]);
            }
        }
        $contentTokens = array_values($contentTokens);
        if (!empty($contentTokens)) {
            $parsed['tags'] = implode(' ', $contentTokens);
        }
        return $parsed;
    }

    if (!empty($parsed['color']) || !empty($parsed['size'])) {
        $parsed['q'] = array_shift($contentTokens);
        if (!empty($contentTokens)) {
            $parsed['tags'] = implode(' ', $contentTokens);
        }
        return $parsed;
    }

    $parsed['tags'] = implode(' ', $contentTokens);
    return $parsed;
}

/**
 * Executa busca estruturada com fallback automático para query legacy.
 */
function ai_evolution_search_legacy_query(int $tenantId, string $query, int $limit = 5): array
{
    $parsed = ai_evolution_parse_legacy_search_query($tenantId, $query);
    $result = ai_evolution_search_structured(
        $tenantId,
        $parsed['q'],
        $parsed['color'],
        $parsed['size'],
        $parsed['tags'],
        $parsed['sku'],
        $limit
    );

    if (
        !$result['found']
        && empty($parsed['sku'])
        && !empty($parsed['q'])
        && empty($parsed['color'])
        && empty($parsed['size'])
        && empty($parsed['tags'])
    ) {
        $retry = ai_evolution_search_structured(
            $tenantId,
            null,
            null,
            null,
            $parsed['q'],
            null,
            $limit
        );
        if ($retry['found']) {
            return $retry;
        }
    }

    return $result;
}

/**
 * Busca estruturada com campos separados e regressão automática.
 *
 * NÃO tokeniza a query — recebe campos já extraídos pela IA.
 * Usa expansão semântica apenas no campo $tags.
 *
 * Sequência de busca regressiva:
 *  1. sku exato (se informado) → retorna imediatamente
 *  2. q + color + size + tags  → search_level = "full"
 *  3. q + color + tags         → search_level = "sem_tamanho"
 *  4. q + tags                 → search_level = "sem_cor"
 *  5. q apenas                 → search_level = "categoria_apenas"
 *  6. nada encontrado          → search_level = "not_found"
 *
 * @param int         $tenantId
 * @param string|null $q        Categoria ou nome. Ex: "Blusas"
 * @param string|null $color    Cor. Ex: "azul", "azul marinho"
 * @param string|null $size     Tamanho. Ex: "G", "GG", "40"
 * @param string|null $tags     Tags/estilo em texto livre. Ex: "elegante festa"
 * @param string|null $sku      SKU exato para busca direta
 * @param int         $limit    Máximo de produtos (padrão 5)
 * @return array                Estrutura completa conforme contrato JSON
 */
function ai_evolution_search_structured(
    int     $tenantId,
    ?string $q      = null,
    ?string $color  = null,
    ?string $size   = null,
    ?string $tags   = null,
    ?string $sku    = null,
    int     $limit  = 5
): array {
    $limit = max(1, min($limit, 20));

    $paramsReceived = [
        'q'     => $q     ? mb_substr(trim($q),     0, 100, 'UTF-8') : null,
        'color' => $color ? mb_substr(trim($color),  0, 50,  'UTF-8') : null,
        'size'  => $size  ? mb_substr(trim($size),   0, 10,  'UTF-8') : null,
        'tags'  => $tags  ? mb_substr(trim($tags),   0, 200, 'UTF-8') : null,
        'sku'   => $sku   ? mb_substr(trim($sku),    0, 50,  'UTF-8') : null,
    ];
    if (
        empty($paramsReceived['sku'])
        && !empty($paramsReceived['q'])
        && empty($paramsReceived['color'])
        && empty($paramsReceived['size'])
        && empty($paramsReceived['tags'])
    ) {
        $detectedSku = ai_evolution_find_exact_sku_value($tenantId, (string)$paramsReceived['q']);
        if ($detectedSku !== null) {
            $paramsReceived['sku'] = $detectedSku;
            $paramsReceived['q'] = null;
        }
    }

    $snapshot = ai_evolution_catalog_snapshot($tenantId);

    // ── CASO ESPECIAL: SKU exato ──────────────────────────────────────────
    if (!empty($paramsReceived['sku'])) {
        $results = ai_evolution_search_by_sku($tenantId, $paramsReceived['sku'], $limit);
        if (!empty($results)) {
            return ai_evolution_build_response(
                $results, 'sku_exact', $paramsReceived, 
                ['sku' => $paramsReceived['sku']], [], $snapshot, null
            );
        }
        return ai_evolution_build_response(
            [], 'not_found', $paramsReceived, 
            ['sku' => $paramsReceived['sku']], ['sku'], $snapshot,
            'SKU '.$paramsReceived['sku'].' não encontrado. '.ai_evolution_snapshot_to_text($snapshot)
        );
    }

    if (empty($paramsReceived['q']) && empty($paramsReceived['color']) && empty($paramsReceived['size']) && empty($paramsReceived['tags'])) {
        return ai_evolution_build_response(
            [], 'not_found', $paramsReceived, [], [], $snapshot,
            'Nenhum critério de busca informado. ' . ai_evolution_snapshot_to_text($snapshot)
        );
    }

    // Expandir tags via glossário semântico
    $expandedTags = [];
    if (!empty($paramsReceived['tags'])) {
        $rawTags = preg_split('/[\s,]+/', mb_strtolower($paramsReceived['tags'], 'UTF-8'));
        foreach ($rawTags as $t) {
            $t = trim($t);
            if ($t === '') continue;
            foreach (ai_evolution_semantic_expand($t) as $exp) {
                $expandedTags[] = $exp;
            }
        }
        $expandedTags = array_values(array_unique($expandedTags));
    }

    // ── BUSCA REGRESSIVA ─────────────────────────────────────────────────
    // Tentativa 1: todos os campos informados
    $results = ai_evolution_run_search(
        $tenantId, $paramsReceived['q'], $paramsReceived['color'],
        $paramsReceived['size'], $expandedTags, $limit, $paramsReceived['tags']
    );
    if (!empty($results)) {
        return ai_evolution_build_response(
            $results, 'full', $paramsReceived, 
            ['q' => $paramsReceived['q'], 'color' => $paramsReceived['color'], 'size' => $paramsReceived['size'], 'tags' => $expandedTags],
            [], $snapshot, null
        );
    }

    // Tentativa 2: sem size
    if (!empty($paramsReceived['size'])) {
        $results = ai_evolution_run_search(
            $tenantId, $paramsReceived['q'], $paramsReceived['color'],
            null, $expandedTags, $limit, $paramsReceived['tags']
        );
        if (!empty($results)) {
            $msg = ai_evolution_regression_message(
                $paramsReceived, ['size'], $results
            );
            return ai_evolution_build_response(
                $results, 'sem_tamanho', $paramsReceived,
                ['q' => $paramsReceived['q'], 'color' => $paramsReceived['color'], 'size' => null, 'tags' => $expandedTags],
                ['size'], $snapshot, $msg
            );
        }
    }

    // Tentativa 3: sem color
    if (!empty($paramsReceived['color'])) {
        $results = ai_evolution_run_search(
            $tenantId, $paramsReceived['q'], null,
            null, $expandedTags, $limit, $paramsReceived['tags']
        );
        if (!empty($results)) {
            $removed = ['color'];
            if (!empty($paramsReceived['size'])) {
                $removed[] = 'size';
            }
            $msg = ai_evolution_regression_message(
                $paramsReceived, $removed, $results
            );
            return ai_evolution_build_response(
                $results, 'sem_cor', $paramsReceived,
                ['q' => $paramsReceived['q'], 'color' => null, 'size' => null, 'tags' => $expandedTags],
                $removed, $snapshot, $msg
            );
        }
    }

    // Tentativa 4: só categoria (se q informado)
    if (!empty($paramsReceived['q'])) {
        $results = ai_evolution_run_search(
            $tenantId, $paramsReceived['q'], null, null, [], $limit, null
        );
        if (!empty($results)) {
            $removed = [];
            foreach (['color', 'size', 'tags'] as $field) {
                if (!empty($paramsReceived[$field])) {
                    $removed[] = $field;
                }
            }
            $msg = ai_evolution_regression_message(
                $paramsReceived, $removed, $results
            );
            return ai_evolution_build_response(
                $results, 'categoria_apenas', $paramsReceived,
                ['q' => $paramsReceived['q'], 'color' => null, 'size' => null, 'tags' => []],
                $removed, $snapshot, $msg
            );
        }
    }

    // Nada encontrado
    return ai_evolution_build_response(
        [], 'not_found', $paramsReceived, [], ['q', 'color', 'size', 'tags'], $snapshot,
        ai_evolution_not_found_message($paramsReceived, $snapshot)
    );
}

/**
 * Executa a query no banco com os campos fornecidos.
 * Retorna array de produtos montados com score.
 * Usada internamente pela busca regressiva.
 */
function ai_evolution_run_search(
    int     $tenantId,
    ?string $q,
    ?string $color,
    ?string $size,
    array   $expandedTags,
    int     $limit,
    ?string $originalTags = null
): array {
    $scores = [];
    $criteriaSets = [];
    $hasQ = !empty($q);
    $hasColor = !empty($color);
    $hasSize = !empty($size);
    $hasTags = !empty($expandedTags);
    $hasOriginalTags = !empty($originalTags);

    $addScore = static function (int $modelId, int $pts) use (&$scores): void {
        if ($modelId <= 0) return;
        $scores[$modelId] = ($scores[$modelId] ?? 0) + $pts;
    };
    $normalizeIdSet = static function (array $ids): array {
        $set = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }
        return array_keys($set);
    };

    // ── 1. Busca por categoria/nome (campo q) ────────────────────────────────
    if ($hasQ) {
        $qMatches = [];
        $qLower = mb_strtolower(trim((string)$q), 'UTF-8');
        try {
            // 1.1 SKU de variante exato
            $stSku = db()->prepare("
                SELECT DISTINCT v.model_id
                FROM ai_catalogo_variants v
                INNER JOIN ai_catalogo_models m ON m.id = v.model_id
                WHERE v.tenant_id = :tid
                  AND m.tenant_id = :tid2
                  AND m.is_active = 1
                  AND v.is_active = 1
                  AND UPPER(v.sku) = UPPER(:q)
            ");
            $stSku->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':q' => $q]);
            while ($mid = $stSku->fetchColumn()) {
                $mid = (int)$mid;
                if ($mid <= 0) continue;
                $qMatches[$mid] = true;
                $addScore($mid, 100);
            }

            // 1.2 SKU de modelo pai exato
            $stSkuParent = db()->prepare("
                SELECT id
                FROM ai_catalogo_models
                WHERE tenant_id = :tid
                  AND is_active = 1
                  AND UPPER(sku) = UPPER(:q)
            ");
            $stSkuParent->execute([':tid' => $tenantId, ':q' => $q]);
            while ($mid = $stSkuParent->fetchColumn()) {
                $mid = (int)$mid;
                if ($mid <= 0) continue;
                $qMatches[$mid] = true;
                $addScore($mid, 90);
            }

            // 1.3 Por categoria nativa
            $st = db()->prepare("
                SELECT DISTINCT m.id
                FROM ai_catalogo_models m
                INNER JOIN categorys c ON c.category_id = m.category_id
                INNER JOIN category_to_store c2s ON c2s.ccategory_id = c.category_id
                WHERE m.tenant_id = :tid AND c2s.store_id = :tid2
                  AND c2s.status = 1 AND m.is_active = 1
                  AND LOWER(c.category_name) LIKE :q
            ");
            $st->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':q' => '%'.$qLower.'%']);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $mid = (int)$r['id'];
                if ($mid <= 0) continue;
                $qMatches[$mid] = true;
                $addScore($mid, 30);
            }

            // 1.4 Por nome do produto ou tags (Busca inclusive para q)
            $st2 = db()->prepare("
                SELECT id FROM ai_catalogo_models
                WHERE tenant_id = :tid AND is_active = 1 
                  AND (LOWER(name) LIKE :q_name OR LOWER(tags) LIKE :q_tags)
            ");
            $qLike = '%'.$qLower.'%';
            $st2->execute([':tid' => $tenantId, ':q_name' => $qLike, ':q_tags' => $qLike]);
            while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
                $mid = (int)$r['id'];
                if ($mid <= 0) continue;
                $qMatches[$mid] = true;
                $addScore($mid, 20);
            }
        } catch (\Throwable $e) {
            error_log('ai_search_q: '.$e->getMessage());
        }

        $qMatches = $normalizeIdSet(array_keys($qMatches));
        if (empty($qMatches)) return [];
        $criteriaSets[] = $qMatches;
    }

    // ── 2. Busca por cor (Independente de q) ─────────────────────────
    if ($hasColor) {
        $colorMatches = [];
        $colorLower = mb_strtolower(trim((string)$color), 'UTF-8');
        $colorNormalized = ai_evolution_normalize_color_term($colorLower);
        try {
            $st = db()->prepare("
                SELECT DISTINCT v.model_id
                FROM ai_catalogo_variants v
                INNER JOIN ai_catalogo_models m ON m.id = v.model_id
                WHERE v.tenant_id = :tid AND m.tenant_id = :tid2
                  AND m.is_active = 1 AND v.is_active = 1
                  AND (
                        LOWER(v.color_normalized) = :cn
                     OR LOWER(v.color) = :cl
                     OR LOWER(v.color) LIKE :clk
                  )
            ");
            $st->execute([
                ':tid' => $tenantId,
                ':tid2' => $tenantId,
                ':cn' => $colorNormalized,
                ':cl' => $colorLower,
                ':clk' => '%'.$colorLower.'%',
            ]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $mid = (int)$r['model_id'];
                if ($mid <= 0) continue;
                $colorMatches[$mid] = true;
                $addScore($mid, 40);
            }
        } catch (\Throwable $e) {
            error_log('ai_search_color: '.$e->getMessage());
        }

        $colorMatches = $normalizeIdSet(array_keys($colorMatches));
        if (empty($colorMatches)) return [];
        $criteriaSets[] = $colorMatches;
    }

    // ── 3. Busca por tags (Com priorização de string exata e filtro de ruído) ──
    if ($hasTags) {
        $tagMatches = [];
        $noise = [];
        if ($hasQ) {
            $qClean = mb_strtolower(trim($q), 'UTF-8');
            $noise[] = $qClean;
            $noise[] = rtrim($qClean, 's'); // remove plural simples
            // Categorias comuns que costumam poluir tags
            $noise = array_merge($noise, ['vestido', 'vestidos', 'blusa', 'blusas', 'calça', 'calças', 'short', 'shorts', 'saia', 'saias']);
            $noise = array_unique($noise);
        }

        // 3.1 Priorização de String Exata (Frase composta)
        if ($hasOriginalTags) {
            $origLower = mb_strtolower(trim($originalTags), 'UTF-8');
            if (mb_strlen($origLower, 'UTF-8') >= 3) {
                try {
                    $stOrig = db()->prepare("
                        SELECT id FROM ai_catalogo_models
                        WHERE tenant_id = :tid AND is_active = 1 
                          AND (LOWER(tags) LIKE :tag_like OR LOWER(name) LIKE :name_like)
                    ");
                    $tagLike = '%'.$origLower.'%';
                    $stOrig->execute([':tid' => $tenantId, ':tag_like' => $tagLike, ':name_like' => $tagLike]);
                    while ($r = $stOrig->fetch(PDO::FETCH_ASSOC)) {
                        $mid = (int)$r['id'];
                        $tagMatches[$mid] = true;
                        $addScore($mid, 80); // Pontuação alta para frase exata
                    }
                } catch (\Throwable $e) {}
            }
        }

        // 3.2 Busca por tags individuais expandidas
        foreach ($expandedTags as $tag) {
            $tagLower = mb_strtolower($tag, 'UTF-8');
            if (mb_strlen($tagLower, 'UTF-8') < 2) continue;
            
            // Filtro de ruído: pula se for termo de categoria já buscado ou genérico demais
            if (in_array($tagLower, $noise)) continue;

            try {
                $st = db()->prepare("
                    SELECT m.id FROM ai_catalogo_models m
                    LEFT JOIN categorys c ON c.category_id = m.category_id
                    WHERE m.tenant_id = :tid AND m.is_active = 1 
                      AND (
                            LOWER(m.tags) LIKE :tag_like 
                         OR LOWER(m.name) LIKE :name_like
                         OR LOWER(c.category_name) LIKE :cat_like
                      )
                ");
                $tagLike = '%'.$tagLower.'%';
                $st->execute([
                    ':tid' => $tenantId, 
                    ':tag_like' => $tagLike, 
                    ':name_like' => $tagLike,
                    ':cat_like' => $tagLike
                ]);
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $mid = (int)$r['id'];
                    if ($mid <= 0) continue;
                    $tagMatches[$mid] = true;
                    $addScore($mid, 15);
                }
            } catch (\Throwable $e) {
                error_log('ai_search_tag: '.$e->getMessage());
            }
        }

        $tagMatches = $normalizeIdSet(array_keys($tagMatches));
        if (empty($tagMatches)) return [];
        $criteriaSets[] = $tagMatches;
    }

    // ── 4. Busca por tamanho ─────────────────────────────────────────────────
    if ($hasSize) {
        $sizeMatches = [];
        $sizeLower = mb_strtolower(trim((string)$size), 'UTF-8');
        try {
            $st = db()->prepare("
                SELECT DISTINCT v.model_id
                FROM ai_catalogo_variants v
                INNER JOIN ai_catalogo_models m ON m.id = v.model_id
                WHERE v.tenant_id = :tid AND m.tenant_id = :tid2
                  AND m.is_active = 1 AND v.is_active = 1
                  AND (LOWER(v.size) = :s OR LOWER(v.size) LIKE :sl)
            ");
            $st->execute([':tid' => $tenantId, ':tid2' => $tenantId, ':s' => $sizeLower, ':sl' => '%'.$sizeLower.'%']);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $mid = (int)$r['model_id'];
                if ($mid <= 0) continue;
                $sizeMatches[$mid] = true;
                $addScore($mid, 25);
            }
        } catch (\Throwable $e) {
            error_log('ai_search_size: '.$e->getMessage());
        }

        $sizeMatches = $normalizeIdSet(array_keys($sizeMatches));
        if (empty($sizeMatches)) return [];
        $criteriaSets[] = $sizeMatches;
    }

    if (empty($criteriaSets)) return [];

    $candidateSet = array_flip($criteriaSets[0]);
    for ($i = 1; $i < count($criteriaSets); $i++) {
        $candidateSet = array_intersect_key($candidateSet, array_flip($criteriaSets[$i]));
        if (empty($candidateSet)) {
            return [];
        }
    }

    $modelIds = array_map('intval', array_keys($candidateSet));
    if (empty($modelIds)) return [];

    // ── 5. Ordenar por score + bonus de demanda ─────────────────────────────
    try {
        $in = implode(',', array_fill(0, count($modelIds), '?'));
        $st = db()->prepare(
            "SELECT id, demand_count FROM ai_catalogo_models WHERE id IN ($in) AND tenant_id = ?"
        );
        $st->execute(array_merge($modelIds, [$tenantId]));
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $bonus = min(10, (int)(log10(max(1, (int)$r['demand_count'])) * 2));
            $scores[(int)$r['id']] = ($scores[(int)$r['id']] ?? 0) + $bonus;
        }
    } catch (\Throwable $e) {}
    $candidateScores = [];
    foreach ($modelIds as $mid) {
        $candidateScores[$mid] = (int)($scores[$mid] ?? 0);
    }
    arsort($candidateScores);
    $topIds = array_slice(array_keys($candidateScores), 0, $limit);

    $requestedColorNormalized = $hasColor ? ai_evolution_normalize_color_term((string)$color) : '';
    $requestedColorRaw = $hasColor ? mb_strtolower(trim((string)$color), 'UTF-8') : '';
    $requestedSize = $hasSize ? mb_strtolower(trim((string)$size), 'UTF-8') : '';

    // Detecção se o q é um SKU para filtrar variante (Melhoria de Busca Exata)
    $qAsSku = null;
    if ($hasQ && ai_evolution_is_likely_sku_value((string)$q)) {
        $qAsSku = strtoupper(trim((string)$q));
    }

    // ── 6. Montar produtos ──────────────────────
    $results = [];
    foreach ($topIds as $mid) {
        try {
            $stPai = db()->prepare("
                SELECT id, name, description, tags, cover_webp, main_price, main_color
                FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid
            ");
            $stPai->execute([':id' => $mid, ':tid' => $tenantId]);
            $pai = $stPai->fetch(PDO::FETCH_ASSOC);
            if (!$pai) continue;

            $stVars = db()->prepare("
                SELECT v.id, v.sku, v.color, v.color_normalized, v.size,
                       v.price, v.stock_qty, v.photo_webp,
                       COALESCE(s.name, 'Loja') AS store_name
                FROM ai_catalogo_variants v
                LEFT JOIN stores s ON s.store_id = v.tenant_id
                WHERE v.model_id = :mid AND v.tenant_id = :tid AND v.is_active = 1
                ORDER BY v.stock_qty DESC
            ");
            $stVars->execute([':mid' => $mid, ':tid' => $tenantId]);

            $allVariants = [];
            $allVariantsInStock = [];
            $matchedVariants = [];
            $matchedVariantsInStock = [];
            $totalStock = 0;

            while ($v = $stVars->fetch(PDO::FETCH_ASSOC)) {
                $vStock = (int)$v['stock_qty'];
                $totalStock += $vStock;
                $vData = [
                    'id'               => (int)$v['id'],
                    'sku'              => $v['sku'],
                    'color'            => $v['color'],
                    'color_normalized' => $v['color_normalized'],
                    'size'             => $v['size'],
                    'price'            => (float)$v['price'],
                    'stock_qty'        => $vStock,
                    'available'        => $vStock > 0,
                    'photo_url'        => $v['photo_webp'] ? ROOT_URL . 'storage/' . $v['photo_webp'] : null,
                    'store_name'       => $v['store_name'],
                ];
                $allVariants[] = $vData;
                if ($vData['available']) {
                    $allVariantsInStock[] = $vData;
                }

                $variantColorNormalized = ai_evolution_normalize_color_term((string)($vData['color_normalized'] ?? ''));
                if ($variantColorNormalized === '') {
                    $variantColorNormalized = ai_evolution_normalize_color_term((string)($vData['color'] ?? ''));
                }
                $variantColorRaw = mb_strtolower(trim((string)($vData['color'] ?? '')), 'UTF-8');
                $variantSize = mb_strtolower(trim((string)($vData['size'] ?? '')), 'UTF-8');

                $colorMatched = !$hasColor
                    || $variantColorNormalized === $requestedColorNormalized
                    || ($requestedColorRaw !== '' && mb_stripos($variantColorRaw, $requestedColorRaw) !== false);
                $sizeMatched = !$hasSize
                    || $variantSize === $requestedSize
                    || ($requestedSize !== '' && mb_stripos($variantSize, $requestedSize) !== false);
                
                // Se q for SKU, filtra apenas essa variante (Fix Busca Exata)
                $skuMatched = ($qAsSku === null) || (strtoupper(trim((string)$v['sku'])) === $qAsSku);

                if ($colorMatched && $sizeMatched && $skuMatched) {
                    $matchedVariants[] = $vData;
                    if ($vData['available']) {
                        $matchedVariantsInStock[] = $vData;
                    }
                }
            }

            if (empty($allVariants)) {
                continue;
            }

            $applyVariantFilter = $hasColor || $hasSize || ($qAsSku !== null);
            // Retornamos apenas o que está em estoque e corresponde aos filtros
            $variantsPayload = $applyVariantFilter ? $matchedVariantsInStock : $allVariantsInStock;
            
            if (empty($variantsPayload)) {
                continue;
            }

            $otherColorsAvailable = [];
            if (!empty($allVariantsInStock)) {
                $selectedColors = [];
                foreach ($variantsPayload as $vv) {
                    $colorName = trim((string)($vv['color'] ?? ''));
                    if ($colorName !== '') {
                        $selectedColors[$colorName] = true;
                    }
                }
                foreach ($allVariantsInStock as $vv) {
                    $colorName = trim((string)($vv['color'] ?? ''));
                    if ($colorName === '' || isset($selectedColors[$colorName])) continue;
                    $otherColorsAvailable[$colorName] = true;
                }
            }

            $reasonParts = [];
            if ($hasQ && $q !== null && trim($q) !== '') $reasonParts[] = 'q: ' . $q;
            if ($hasColor && $color !== null && trim($color) !== '') $reasonParts[] = 'cor: ' . $color;
            if ($hasSize && $size !== null && trim($size) !== '') $reasonParts[] = 'tamanho: ' . $size;
            if ($hasTags) $reasonParts[] = 'tags';

            $results[] = [
                'score'        => (int)($candidateScores[$mid] ?? 0),
                'match_reason' => !empty($reasonParts)
                    ? implode(', ', $reasonParts)
                    : 'Relevância: ' . (int)($candidateScores[$mid] ?? 0),
                'other_colors_available' => array_values(array_keys($otherColorsAvailable)),
                'product'      => [
                    'id'          => (int)$pai['id'],
                    'name'        => $pai['name'],
                    'description' => $pai['description'],
                    'tags'        => $pai['tags'],
                    'cover_url'   => ai_evolution_get_image_url($pai['cover_webp']),
                ],
                'variants'          => $variantsPayload,
            ];

            // Log de demanda para o produto encontrado
            ai_log_demand($tenantId, (int)$pai['id'], (string)$q, 'webhook');
        } catch (\Throwable $e) {
            error_log('ai_search_assemble: '.$e->getMessage());
        }
    }

    return $results;
}

/**
 * Busca por SKU exato. Retorna o modelo pai com TODAS as variantes.
 * Usado quando cliente envia imagem ou referência direta.
 */
function ai_evolution_search_by_sku(
    int    $tenantId,
    string $sku,
    int    $limit = 5
): array {
    $sku = trim($sku);
    if ($sku === '') return [];
    try {
        // 1. Tentar SKU de variante primeiro (mais específico)
        $st = db()->prepare("
            SELECT v.model_id, v.id AS variant_id
            FROM ai_catalogo_variants v
            INNER JOIN ai_catalogo_models m ON m.id = v.model_id
            WHERE v.tenant_id = :tid AND m.tenant_id = :tid2
              AND m.is_active = 1 AND v.is_active = 1
              AND (v.sku = :skuExact OR UPPER(v.sku) = UPPER(:skuCi))
            LIMIT 1
        ");
        $st->execute([
            ':tid'      => $tenantId,
            ':tid2'     => $tenantId,
            ':skuExact' => $sku,
            ':skuCi'    => $sku,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Encontrou uma variante específica
            return ai_evolution_run_search_by_id($tenantId, (int)$row['model_id'], $limit, (int)$row['variant_id']);
        }

        // 2. Tentar SKU de modelo pai
        $st2 = db()->prepare("
            SELECT id FROM ai_catalogo_models
            WHERE tenant_id = :tid AND is_active = 1 AND sku = :sku LIMIT 1
        ");
        $st2->execute([':tid' => $tenantId, ':sku' => $sku]);
        $row2 = $st2->fetch(PDO::FETCH_ASSOC);
        
        if ($row2) {
            // Encontrou o modelo pai, retorna ele com todas as variantes
            return ai_evolution_run_search_by_id($tenantId, (int)$row2['id'], $limit);
        }

        return [];

    } catch (\Throwable $e) {
        error_log('ai_search_sku: '.$e->getMessage());
        return [];
    }
}

/**
 * Busca interna por ID direto para SKU exact.
 * Se matchedVariantId for informado, marca ela como a solicitada.
 */
function ai_evolution_run_search_by_id(int $tenantId, int $modelId, int $limit, int $matchedVariantId = 0): array
{
    try {
        $stPai = db()->prepare("
            SELECT id, name, description, tags, cover_webp, main_price, main_color
            FROM ai_catalogo_models WHERE id = :id AND tenant_id = :tid
        ");
        $stPai->execute([':id' => $modelId, ':tid' => $tenantId]);
        $pai = $stPai->fetch(PDO::FETCH_ASSOC);
        if (!$pai) return [];

        $stVars = db()->prepare("
            SELECT v.id, v.sku, v.color, v.color_normalized, v.size,
                   v.price, v.stock_qty, v.photo_webp,
                   COALESCE(s.name, 'Loja') AS store_name
            FROM ai_catalogo_variants v
            LEFT JOIN stores s ON s.store_id = v.tenant_id
            WHERE v.model_id = :mid AND v.tenant_id = :tid AND v.is_active = 1
            ORDER BY v.stock_qty DESC
        ");
        $stVars->execute([':mid' => $modelId, ':tid' => $tenantId]);

        $allVariants = [];
        $variantsInStock = [];
        $totalStock = 0;
        $matchedVariantInfo = null;

        while ($v = $stVars->fetch(PDO::FETCH_ASSOC)) {
            $vStock = (int)$v['stock_qty'];
            $totalStock += $vStock;
            $vData = [
                'id'               => (int)$v['id'],
                'sku'              => $v['sku'],
                'color'            => $v['color'],
                'color_normalized' => $v['color_normalized'],
                'size'             => $v['size'],
                'price'            => (float)$v['price'],
                'stock_qty'        => $vStock,
                'available'        => $vStock > 0,
                'is_requested'     => ($matchedVariantId > 0 && (int)$v['id'] === $matchedVariantId),
                'photo_url'        => ai_evolution_get_image_url($v['photo_webp']),
                'store_name'       => $v['store_name'],
            ];
            
            // Se for busca por SKU de variante específico, filtramos as listas para a IA
            if ($matchedVariantId > 0) {
                if ((int)$v['id'] === $matchedVariantId) {
                    if ($vData['available']) $variantsInStock[] = $vData;
                    $matchedVariantInfo = $vData;
                }
            } else {
                if ($vData['available']) $variantsInStock[] = $vData;
            }
        }

        // Se não houver variantes disponíveis para o SKU solicitado, retorna vazio
        if (empty($variantsInStock)) {
            return [];
        }

        // Match reason mais clara para a IA
        $reason = $matchedVariantId > 0 ? "SKU Variante exato ({$matchedVariantInfo['sku']})" : "SKU Pai exato";

        // Adicionar informações de "Outras Cores Disponíveis" para a IA
        $otherColors = [];
        if ($matchedVariantId > 0) {
             $stOthers = db()->prepare("
                SELECT DISTINCT color FROM ai_catalogo_variants 
                WHERE model_id = :mid AND id != :vid AND is_active = 1 AND stock_qty > 0
             ");
             $stOthers->execute([':mid' => $modelId, ':vid' => $matchedVariantId]);
             $otherColors = $stOthers->fetchAll(PDO::FETCH_COLUMN);
        }

        $res = [
            'score'             => 100,
            'match_reason'      => $reason,
            'other_colors_available' => $otherColors,
            'product'           => [
                'id'          => (int)$pai['id'],
                'name'        => $pai['name'],
                'description' => $pai['description'],
                'tags'        => $pai['tags'],
                'cover_url'   => ai_evolution_get_image_url($pai['cover_webp']),
            ],
            'variants'          => $variantsInStock,
        ];

        // Log de demanda (SKU exact)
        ai_log_demand($tenantId, (int)$pai['id'], $matchedVariantId > 0 ? "SKU: {$matchedVariantInfo['sku']}" : "SKU Pai", 'sku_search');

        return [$res];
    } catch (\Throwable $e) {
        error_log('ai_search_id: '.$e->getMessage());
        return [];
    }
}


/**
 * Remove variantes duplicadas do payload final.
 */
function ai_evolution_deduplicate_variants_payload(array $variants): array
{
    if (empty($variants)) return [];
    $seen = [];
    $out = [];

    foreach ($variants as $variant) {
        if (!is_array($variant)) continue;
        $id = (int)($variant['id'] ?? 0);
        $sku = mb_strtolower(trim((string)($variant['sku'] ?? '')), 'UTF-8');
        $color = mb_strtolower(trim((string)($variant['color'] ?? '')), 'UTF-8');
        $size = mb_strtolower(trim((string)($variant['size'] ?? '')), 'UTF-8');
        $key = $id > 0
            ? 'id:' . $id
            : 'key:' . $sku . '|' . $color . '|' . $size;

        if ($key === 'key:||') {
            $key = 'hash:' . md5(json_encode($variant, JSON_UNESCAPED_UNICODE));
        }

        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $variant;
    }

    return $out;
}

/**
 * Remove produtos duplicados no payload final.
 */
function ai_evolution_deduplicate_results_payload(array $results): array
{
    if (empty($results)) return [];
    $seen = [];
    $out = [];

    foreach ($results as $item) {
        if (!is_array($item)) continue;

        if (isset($item['variants']) && is_array($item['variants'])) {
            $item['variants'] = ai_evolution_deduplicate_variants_payload($item['variants']);
        }
        if (isset($item['other_colors_available']) && is_array($item['other_colors_available'])) {
            $item['other_colors_available'] = array_values(array_unique(array_filter($item['other_colors_available'])));
        }

        $productId = (int)($item['product']['id'] ?? 0);
        $key = $productId > 0
            ? 'product:' . $productId
            : 'hash:' . md5(json_encode($item['product'] ?? $item, JSON_UNESCAPED_UNICODE));

        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $item;
    }

    return $out;
}

/**
 * Monta o array de resposta limpo e organizado conforme o novo esquema solicitado.
 */
function ai_evolution_build_response(
    array   $results,
    string  $searchLevel,
    array   $paramsReceived,
    array   $paramsUsed,
    array   $removedFields,
    array   $snapshot,
    ?string $suggestion = null
): array {
    $results = ai_evolution_deduplicate_results_payload($results);
    
    // Contador que considera as variações (variants) e não apenas o produto PAI
    $totalVariants = 0;
    foreach ($results as $res) {
        if (isset($res['variants']) && is_array($res['variants'])) {
            $totalVariants += count($res['variants']);
        }
    }

    return [
        'success'                => true,
        'found'                  => count($results) > 0,
        'search_level'           => $searchLevel,
        'search_params_received' => $paramsReceived,
        'search_params_used'     => $paramsUsed,
        'removed_fields'         => $removedFields,
        'total'                  => $totalVariants, // Contador de variações
        'total_models'           => count($results), // Mantemos o contador de modelos pais por referência
        'results'                => $results,
        'catalog_snapshot'       => $snapshot,
        'suggestion_context'     => $suggestion
    ];
}

/**
 * Gera suggestion_context para casos de regressão (casos 2 e 3).
 */
function ai_evolution_regression_message(
    array  $paramsReceived,
    array  $removedFields,
    array  $results
): string {
    $q     = (string)($paramsReceived['q']     ?? '');
    $color = (string)($paramsReceived['color'] ?? '');
    $size  = (string)($paramsReceived['size']  ?? '');

    // Coletar cores e tamanhos disponíveis nos resultados
    $availColors = [];
    $availSizes  = [];
    foreach ($results as $r) {
        foreach ($r['variants'] ?? [] as $v) {
            if ($v['color'] ?? '') $availColors[] = $v['color'];
            if ($v['size']  ?? '') $availSizes[]  = $v['size'];
        }
    }
    $availColors = array_slice(array_unique($availColors), 0, 4);
    $availSizes  = array_slice(array_unique($availSizes),  0, 6);

    $parts = [];
    if ($q !== '')    $parts[] = $q;
    if ($color !== '') $parts[] = $color;
    if ($size !== '')  $parts[] = $size;
    $asked = implode(' ', $parts);

    $msg = "{$asked} não encontrei exatamente.";
    if (in_array('size', $removedFields) && !empty($availSizes)) {
        $msg .= ' Tenho nos tamanhos: '.implode(', ', $availSizes).'.';
    }
    if (in_array('color', $removedFields) && !empty($availColors)) {
        $msg .= ' Disponível em: '.implode(', ', $availColors).'.';
    }
    return $msg;
}

/**
 * Gera suggestion_context para caso 4 (not_found).
 */
function ai_evolution_not_found_message(
    array $paramsReceived,
    array $snapshot
): string {
    $q = (string)($paramsReceived['q'] ?? 'O item solicitado');
    if (empty($snapshot)) {
        return "{$q} não encontrado e catálogo sem estoque no momento.";
    }
    $avail = [];
    foreach ($snapshot as $cat => $count) {
        $avail[] = "{$cat} ({$count} ".($count === 1 ? 'peça' : 'peças').")";
    }
    return "{$q} não temos no catálogo. "
         . "Disponível agora: ".implode(', ', $avail).". "
         . "Sugira SOMENTE estas categorias.";
}

/**
 * Converte catalog_snapshot em texto legível.
 */
function ai_evolution_snapshot_to_text(array $snapshot): string
{
    if (empty($snapshot)) return 'Catálogo sem estoque.';
    $parts = [];
    foreach ($snapshot as $cat => $count) {
        $parts[] = "{$cat} ({$count})";
    }
    return 'Disponível: '.implode(', ', $parts).'.';
}

/**
 * Busca variantes relevantes no catálogo com base no texto recebido.
 * @deprecated Use ai_evolution_search_structured()
 */
function ai_evolution_search_catalog_variants(int $tenantId, string $query, int $limit = 5): array
{
    // Fallback legado: converte query textual para campos estruturados
    return ai_evolution_search_legacy_query($tenantId, $query, $limit)['results'];
}
