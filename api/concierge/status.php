<?php
ob_start();
session_start();
include('../../_init.php');

// Aumenta o tempo de execução para disparos longos de status
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_evolution.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_status_extract_token(): string
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

function concierge_status_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function concierge_status_forbid_if_no_permission(string $perm, bool $isTokenAuth): void
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
function concierge_status_decode_payload($payload): array
{
    if (is_array($payload)) {
        return $payload;
    }
    if (is_string($payload)) {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function concierge_status_media_list($value): array
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
                $urls = array_merge($urls, concierge_status_media_list($decoded));
            } elseif (strpos($value, ',') !== false) {
                $urls = array_merge($urls, concierge_status_media_list(array_map('trim', explode(',', $value))));
            } else {
                $urls[] = $value;
            }
        }
    }
    return array_values(array_unique($urls));
}

function concierge_status_resolve_url(string $raw): string
{
    return ai_resolve_storage_url($raw);
}

function concierge_status_collect_media_urls(array $input, array $payload): array
{
    $mediaUrls = [];
    $mediaUrls = array_merge($mediaUrls, concierge_status_media_list($input['media_url'] ?? ''));
    $mediaUrls = array_merge($mediaUrls, concierge_status_media_list($input['media_urls'] ?? []));
    $mediaUrls = array_merge($mediaUrls, concierge_status_media_list($payload['media_url'] ?? ''));
    $mediaUrls = array_merge($mediaUrls, concierge_status_media_list($payload['media_urls'] ?? []));
    return array_values(array_unique($mediaUrls));
}
function concierge_status_dispatch_now(int $tenantId, int $statusId, array $statusRow): array
{
    $mode = ai_groups_status_posting_mode();
    if ($mode === 'system') {
        $resp = ai_groups_dispatch_status_via_system($tenantId, $statusRow);
        $resp['dispatch_mode'] = 'system';
        return $resp;
    }

    $targetUrl = ai_groups_dispatch_webhook_url($tenantId, 'status');
    $token = ai_groups_store_token($tenantId);
    if ($targetUrl === '' || $token === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Webhook de disparo de status não configurado.',
            'json' => [],
            'raw' => '',
            'external_message_id' => '',
            'dispatch_mode' => 'n8n',
        ];
    }

    $payloadJson = concierge_status_decode_payload($statusRow['payload_json'] ?? null);
    $mediaUrls = concierge_status_collect_media_urls($statusRow, $payloadJson);
    
    $token = ai_groups_store_token($tenantId);
    $publicBase = ai_groups_get_public_base_url($tenantId);
    $callbackUrl = $publicBase . '/api/concierge/status_webhook.php?loja_id=' . $tenantId . '&status_id=' . $statusId . '&token=' . $token;

    $payload = [
        'source' => 'concierge_status_dispatch',
        'tenant_id' => $tenantId,
        'status_id' => $statusId,
        'status' => $statusRow['status'] ?? 'pending',
        'content' => (string)($statusRow['content'] ?? ''),
        'media_url' => (string)($statusRow['media_url'] ?? ''),
        'media_urls' => $mediaUrls,
        'scheduled_at' => $statusRow['scheduled_at'] ?? null,
        'payload_json' => $payloadJson,
        'callback' => [
            'url' => $callbackUrl,
            'method' => 'POST',
            'headers' => [
                'X-Concierge-Token' => $token,
                'ngrok-skip-browser-warning' => '1',
            ],
            'body_template' => [
                'tenant_id' => $tenantId,
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
        'X-Store-Id: ' . $tenantId,
    ]);

    return [
        'ok' => !empty($resp['ok']),
        'http_code' => (int)($resp['http_code'] ?? 0),
        'error' => trim((string)($resp['error'] ?? '')),
        'json' => (array)($resp['json'] ?? []),
        'raw' => (string)($resp['raw'] ?? ''),
        'external_message_id' => '',
        'dispatch_mode' => 'n8n',
    ];
}

function concierge_status_normalize_item(array $item): array
{
    $payload = concierge_status_decode_payload($item['payload_json'] ?? null);
    $mediaUrls = concierge_status_collect_media_urls($item, $payload);
    $mediaUrls = array_map('concierge_status_resolve_url', $mediaUrls);

    $item['id'] = (int)($item['id'] ?? 0);
    $item['tenant_id'] = (int)($item['tenant_id'] ?? 0);
    $item['product_id'] = (int)($item['product_id'] ?? 0);
    $item['payload_json'] = $payload;
    $item['media_urls'] = $mediaUrls;
    if (!empty($mediaUrls)) {
        $item['media_url'] = $mediaUrls[0];
    }

    if (!isset($item['scheduled_at']) && isset($payload['scheduled_at'])) {
        $item['scheduled_at'] = $payload['scheduled_at'];
    }

    return $item;
}

function concierge_status_get_one(int $tenantId, int $statusId): ?array
{
    if ($tenantId <= 0 || $statusId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return null;
    }

    try {
        $stmt = db()->prepare("SELECT * FROM concierge_status WHERE tenant_id = :tid AND id = :sid LIMIT 1");
        $stmt->execute([':tid' => $tenantId, ':sid' => $statusId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? concierge_status_normalize_item($row) : null;
    } catch (Throwable $e) {
        return null;
    }
}

try {
    $json = concierge_status_json_body();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? $json['tenant_id'] ?? 0);
    $token = concierge_status_extract_token();
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
        concierge_status_forbid_if_no_permission('concierge_groups_access', false);
    }

    if (!ai_groups_plan_is_enabled($tenantId)) {
        http_response_code(402);
        echo json_encode(ai_groups_response(true, 'Módulo de grupos indisponível no plano.', ['blocked' => true]));
        exit;
    }

    if ($method === 'GET') {
        $statusId = (int)($_GET['id'] ?? $_GET['status_id'] ?? 0);
        if ($statusId > 0) {
            $statusItem = concierge_status_get_one($tenantId, $statusId);
            if (!$statusItem) {
                http_response_code(404);
                echo json_encode(ai_groups_response(true, 'Postagem de status não encontrada.', null));
                exit;
            }
            echo json_encode(ai_groups_response(false, 'OK', ['status' => $statusItem]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
        ];
        $list = ai_get_concierge_statuses($tenantId, $filters, $page, $limit);
        if (!empty($list['items']) && is_array($list['items'])) {
            $list['items'] = array_map('concierge_status_normalize_item', $list['items']);
        }
        echo json_encode(ai_groups_response(false, 'OK', $list), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        concierge_status_forbid_if_no_permission('concierge_groups_manage', $isTokenAuth);
        $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? $json['action'] ?? '')));
        if ($action === 'cancel') {
            $statusId = (int)($json['status_id'] ?? $json['id'] ?? $_POST['status_id'] ?? $_GET['status_id'] ?? 0);
            if ($statusId <= 0) {
                throw new Exception('status_id não informado para cancelamento.');
            }
            $current = concierge_status_get_one($tenantId, $statusId);
            $payloadJson = concierge_status_decode_payload($current['payload_json'] ?? null);
            $payloadJson['canceled_at'] = date('Y-m-d H:i:s');
            $payloadJson['canceled_by'] = 'manual';
            $ok = ai_update_concierge_status($tenantId, $statusId, [
                'status' => 'error',
                'error_message' => 'Cancelado manualmente.',
                'payload_json' => $payloadJson,
            ]);
            if (!$ok) {
                throw new Exception('Falha ao cancelar postagem de status.');
            }
            $statusItem = concierge_status_get_one($tenantId, $statusId);
            echo json_encode(ai_groups_response(false, 'Postagem de status cancelada.', ['status' => $statusItem]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'send_now') {
            $statusId = (int)($json['status_id'] ?? $json['id'] ?? $_POST['status_id'] ?? $_GET['status_id'] ?? 0);
            if ($statusId > 0) {
                $current = concierge_status_get_one($tenantId, $statusId);
                if (!$current) {
                    throw new Exception('Postagem de status não encontrada.');
                }

                // Verifica conflito de 5 minutos antes de disparar agora
                $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, date('Y-m-d H:i:s'), 0, 5);
                if ($conflict && !($conflict['type'] === 'status' && (int)$conflict['id'] === $statusId)) {
                    $cTime = date('H:i', strtotime($conflict['scheduled_at']));
                    $type = $conflict['type'] === 'campaign' ? 'campanha' : 'status';
                    throw new Exception("Conflito: Já existe um(a) {$type} para as {$cTime}. Aguarde o intervalo de 5 minutos.");
                }

                // Marca como enviando para evitar duplicidade rápida
                ai_update_concierge_status($tenantId, $statusId, [
                    'status' => 'sending',
                    'attempt_count' => (int)($current['attempt_count'] ?? 0) + 1,
                    'error_message' => ''
                ]);

                // Libera a sessão para evitar travamento (Session Locking) em outras abas do sistema
                // já que o disparo da Evolution API pode demorar até 180 segundos.
                if (session_id()) {
                    session_write_close();
                }
                if (function_exists('ignore_user_abort')) {
                    @ignore_user_abort(true);
                }
                if (function_exists('set_time_limit')) {
                    @set_time_limit(0);
                }

                // Dispara via modo configurado (system/n8n)
                $resp = concierge_status_dispatch_now($tenantId, $statusId, $current);
                $payloadJson = concierge_status_decode_payload($current['payload_json'] ?? null);
                $dispatchMode = (string)($resp['dispatch_mode'] ?? 'system');
                $payloadJson['dispatch_mode'] = $dispatchMode . '_manual';
                $payloadJson['dispatch_response'] = [
                    'ok' => !empty($resp['ok']),
                    'http_code' => (int)($resp['http_code'] ?? 0),
                    'error' => is_array($resp['error'] ?? null) || is_object($resp['error'] ?? null)
                        ? (string)json_encode($resp['error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string)($resp['error'] ?? ''),
                    'external_message_id' => (string)($resp['external_message_id'] ?? ''),
                    'updated_at' => date('c'),
                ];

                if (!empty($resp['ok'])) {
                if ($dispatchMode === 'system') {
                    // Lógica de Repostagem (agora cuida de marcar como 'sent' e incrementar post_count)
                    ai_groups_handle_status_repost_logic($tenantId, $statusId, $current);
                } else {
                    ai_update_concierge_status($tenantId, $statusId, [
                        'status' => 'sending',
                        'error_message' => '',
                        'payload_json' => $payloadJson,
                    ]);
                }
                $statusItem = concierge_status_get_one($tenantId, $statusId);
                    $okMsg = $dispatchMode === 'system'
                        ? 'Status enviado com sucesso via Evolution API.'
                        : 'Status encaminhado ao fluxo N8N com sucesso.';
                    echo json_encode(ai_groups_response(false, $okMsg, ['status' => $statusItem, 'evolution' => $resp]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                } else {
                    $errRaw = $resp['error'] ?? '';
                    $err = trim(is_array($errRaw) || is_object($errRaw) ? (string)json_encode($errRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$errRaw);
                    if ($err === '') {
                        $err = 'Erro desconhecido no disparo via Evolution API (HTTP ' . (int)($resp['http_code'] ?? 0) . ').';
                    }
                    ai_update_concierge_status($tenantId, $statusId, [
                        'status' => 'error',
                        'error_message' => $err,
                        'sent_at' => null,
                        'payload_json' => $payloadJson,
                    ]);
                    $statusItem = concierge_status_get_one($tenantId, $statusId);
                    echo json_encode(ai_groups_response(true, 'Falha no envio: ' . $err, ['status' => $statusItem, 'evolution' => $resp]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
            }
        }
        if (!$isTokenAuth && !has_permission('access', 'concierge_groups_manage') && !has_permission('access', 'concierge_groups_ai_create')) {
            http_response_code(403);
            echo json_encode(ai_groups_response(true, 'Permissão insuficiente para criar postagem de status.', null));
            exit;
        }

        $content = trim((string)($json['content'] ?? $json['caption'] ?? $json['legenda'] ?? ''));
        $scheduledAt = trim((string)($json['scheduled_at'] ?? ''));
        $statusValue = trim((string)($json['status'] ?? ($scheduledAt !== '' ? 'pending' : 'sent')));
        if (!in_array($statusValue, ['pending', 'sending', 'sent', 'error', 'canceled'], true)) {
            $statusValue = 'pending';
        }
        if ($scheduledAt !== '' && $statusValue === 'sent') {
            $statusValue = 'pending';
        }

        $payloadJson = concierge_status_decode_payload($json['payload_json'] ?? null);
        $mediaUrls = concierge_status_collect_media_urls($json, $payloadJson);
        $payloadJson['media_urls'] = $mediaUrls;
        
        // Novos campos de repetição e intervalo
        if (isset($json['repeat_count'])) {
            $payloadJson['repeat_count'] = (int)$json['repeat_count'];
        }
        if (isset($json['repeat_interval'])) {
            $payloadJson['repeat_interval'] = (int)$json['repeat_interval'];
        }
        if (isset($json['repeat_days'])) {
            $payloadJson['repeat_days'] = trim((string)$json['repeat_days']);
        }

        if ($scheduledAt !== '') {
            $payloadJson['scheduled_at'] = $scheduledAt;
        }
        if (isset($json['media_type'])) {
            $payloadJson['media_type'] = (string)$json['media_type'];
        }
        if (isset($json['cta'])) {
            $payloadJson['cta'] = (string)$json['cta'];
        }

        $statusId = ai_create_concierge_status($tenantId, [
            'content' => $content,
            'product_id' => (int)($json['product_id'] ?? 0),
            'media_url' => $mediaUrls[0] ?? '',
            'status' => $statusValue,
            'scheduled_at' => $scheduledAt,
            'repeat_count' => (int)($json['repeat_count'] ?? 1),
            'repeat_interval' => (int)($json['repeat_interval'] ?? 1),
            'repeat_days' => trim((string)($json['repeat_days'] ?? '')),
            'payload_json' => $payloadJson,
        ]);

        $statusItem = concierge_status_get_one($tenantId, $statusId);

        // Se a ação for send_now, dispara imediatamente após criar
        if ($action === 'send_now' && $statusItem) {
            ai_update_concierge_status($tenantId, $statusId, [
                'status' => 'sending',
                'attempt_count' => 1,
                'error_message' => ''
            ]);

            // Libera a sessão para evitar travamento durante o disparo imediato na criação
            if (session_id()) {
                session_write_close();
            }
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $resp = concierge_status_dispatch_now($tenantId, $statusId, $statusItem);
            $pJson = concierge_status_decode_payload($statusItem['payload_json'] ?? null);
            $dispatchMode = (string)($resp['dispatch_mode'] ?? 'system');
            $pJson['dispatch_mode'] = $dispatchMode . '_create_now';
            $pJson['dispatch_response'] = [
                'ok' => !empty($resp['ok']),
                'http_code' => (int)($resp['http_code'] ?? 0),
                'error' => (string)($resp['error'] ?? ''),
                'external_message_id' => (string)($resp['external_message_id'] ?? ''),
                'updated_at' => date('c'),
            ];

            if (!empty($resp['ok'])) {
                if ($dispatchMode === 'system') {
                    // Lógica de Repostagem (agora cuida de marcar como 'sent' e incrementar post_count)
                    ai_groups_handle_status_repost_logic($tenantId, $statusId, $statusItem);
                } else {
                    ai_update_concierge_status($tenantId, $statusId, [
                        'status' => 'sending',
                        'error_message' => '',
                        'payload_json' => $pJson,
                    ]);
                }
            } else {
                ai_update_concierge_status($tenantId, $statusId, [
                    'status' => 'error',
                    'error_message' => (function ($v) {
                        if (is_array($v) || is_object($v)) {
                            $j = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            return is_string($j) ? $j : 'Falha no disparo imediato.';
                        }
                        $s = trim((string)$v);
                        return $s !== '' ? $s : 'Falha no disparo imediato.';
                    })($resp['error'] ?? ''),
                    'sent_at' => null,
                    'payload_json' => $pJson,
                ]);
            }
            $statusItem = concierge_status_get_one($tenantId, $statusId);
        }

        echo json_encode(ai_groups_response(false, 'Postagem de status processada.', ['status' => $statusItem]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'PATCH') {
        concierge_status_forbid_if_no_permission('concierge_groups_manage', $isTokenAuth);
        $statusId = (int)($json['id'] ?? $json['status_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
        if ($statusId <= 0) {
            throw new Exception('status_id não informado.');
        }

        // Busca o item atual para garantir que existe e pertence ao tenant
        $statusCurrent = concierge_status_get_one($tenantId, $statusId);
        if (!$statusCurrent) {
            throw new Exception('Postagem de status não encontrada ou sem permissão.');
        }

        $payload = [];
        // Campos diretos da tabela
        foreach (['content', 'status', 'error_message', 'sent_at', 'media_url', 'scheduled_at', 'product_id', 'repeat_count', 'repeat_interval', 'repeat_days'] as $key) {
            if (array_key_exists($key, $json)) {
                $payload[$key] = $json[$key];
            }
        }

        // Se estiver atualizando o agendamento, sincroniza com o payload_json e reseta status
        if (array_key_exists('scheduled_at', $json)) {
            $scheduledAt = trim((string)$json['scheduled_at']);
            $payloadJson = concierge_status_decode_payload($statusCurrent['payload_json'] ?? null);
            
            if ($scheduledAt !== '') {
                $payload['scheduled_at'] = $scheduledAt;
                $payloadJson['scheduled_at'] = $scheduledAt;
                
                // Se não passou status novo, volta para pending ao reagendar
                if (!isset($payload['status'])) {
                    $payload['status'] = 'pending';
                }
                
                // Limpa rastros de erros ou envios anteriores
                $payload['error_message'] = '';
                $payload['sent_at'] = null;
            } else {
                $payload['scheduled_at'] = null;
                unset($payloadJson['scheduled_at']);
            }
            $payload['payload_json'] = $payloadJson;
        }

        // Processamento de media_urls se houver
        if (array_key_exists('media_urls', $json)) {
            $payloadJson = isset($payload['payload_json']) ? $payload['payload_json'] : concierge_status_decode_payload($statusCurrent['payload_json'] ?? null);
            $urls = concierge_status_collect_media_urls(
                ['media_urls' => $json['media_urls'], 'media_url' => $json['media_url'] ?? ''],
                $payloadJson
            );
            $payloadJson['media_urls'] = $urls;
            $payload['payload_json'] = $payloadJson;
            if (!isset($payload['media_url'])) {
                $payload['media_url'] = $urls[0] ?? '';
            }
        }

        if (isset($payload['scheduled_at'])) {
            $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, (string)$payload['scheduled_at'], 0, 5);
            if ($conflict && !($conflict['type'] === 'status' && (int)$conflict['id'] === $statusId)) {
                $cTime = date('H:i', strtotime($conflict['scheduled_at']));
                $type = $conflict['type'] === 'campaign' ? 'campanha' : 'status';
                throw new Exception("Conflito: Já existe um(a) {$type} para as {$cTime}. Escolha um horário com intervalo de 5 minutos.");
            }
        }

        $ok = ai_update_concierge_status($tenantId, $statusId, $payload);
        if (!$ok) {
            throw new Exception('Falha ao atualizar postagem de status no banco de dados.');
        }

        $statusItem = concierge_status_get_one($tenantId, $statusId);
        echo json_encode(ai_groups_response(false, 'Postagem de status atualizada com sucesso.', ['status' => $statusItem]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        concierge_status_forbid_if_no_permission('concierge_groups_manage', $isTokenAuth);
        $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? $json['action'] ?? '')));
        
        if ($action === 'clear_history') {
            $stmt = db()->prepare("DELETE FROM concierge_status WHERE tenant_id = :tid");
            $stmt->execute([':tid' => $tenantId]);
            echo json_encode(ai_groups_response(false, 'Todo o histórico foi deletado com sucesso.', null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        $statusId = (int)($json['id'] ?? $json['status_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
        if ($statusId <= 0) {
            throw new Exception('id da postagem de status não informado.');
        }

        $ok = ai_delete_concierge_status($tenantId, $statusId);
        if (!$ok) {
            throw new Exception('Falha ao excluir postagem de status ou registro não encontrado.');
        }

        echo json_encode(ai_groups_response(false, 'Agendamento de status removido com sucesso.', null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(ai_groups_response(true, 'Método não suportado.', null));
} catch (Throwable $e) {
    $fallbackMethod = strtoupper((string)($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($fallbackMethod === 'GET' || (empty($method) && strtoupper($_SERVER['REQUEST_METHOD']) === 'GET')) {
        http_response_code(200);
        echo json_encode(ai_groups_response(false, 'OK', [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 20,
            'fallback' => true,
            'debug_error' => $e->getMessage(),
            'debug_file' => $e->getFile(),
            'debug_line' => $e->getLine(),
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
