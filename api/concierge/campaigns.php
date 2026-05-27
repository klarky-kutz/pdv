<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');

function concierge_campaigns_extract_token(): string
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

function concierge_campaigns_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function concierge_campaigns_check_perm(string $perm, bool $isTokenAuth): void
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
function concierge_campaigns_decode_payload($payload): array
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

function concierge_campaigns_media_list($value): array
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
                $urls = array_merge($urls, concierge_campaigns_media_list($decoded));
            } elseif (strpos($value, ',') !== false) {
                $urls = array_merge($urls, concierge_campaigns_media_list(array_map('trim', explode(',', $value))));
            } else {
                $urls[] = $value;
            }
        }
    }
    return array_values(array_unique($urls));
}

function concierge_campaigns_resolve_url(string $raw): string
{
    return ai_resolve_storage_url($raw);
}

function concierge_campaigns_collect_media_urls(array $input, array $payload): array
{
    $mediaUrls = [];
    $mediaUrls = array_merge($mediaUrls, concierge_campaigns_media_list($input['media_url'] ?? ''));
    $mediaUrls = array_merge($mediaUrls, concierge_campaigns_media_list($input['media_urls'] ?? []));
    $mediaUrls = array_merge($mediaUrls, concierge_campaigns_media_list($payload['media_url'] ?? ''));
    $mediaUrls = array_merge($mediaUrls, concierge_campaigns_media_list($payload['media_urls'] ?? []));
    return array_values(array_unique($mediaUrls));
}

function concierge_campaigns_normalize_campaign(array $campaign): array
{
    $payload = concierge_campaigns_decode_payload($campaign['payload_json'] ?? null);
    $mediaUrls = concierge_campaigns_collect_media_urls($campaign, $payload);
    $mediaUrls = array_map('concierge_campaigns_resolve_url', $mediaUrls);

    $campaign['payload_json'] = $payload;
    $campaign['media_urls'] = $mediaUrls;
    if (!empty($mediaUrls)) {
        $campaign['media_url'] = $mediaUrls[0];
    }

    return $campaign;
}

try {
    $json = concierge_campaigns_json_body();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? $json['tenant_id'] ?? 0);
    $token = concierge_campaigns_extract_token();
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
        concierge_campaigns_check_perm('concierge_groups_access', false);
    }

    if (!ai_groups_plan_is_enabled($tenantId)) {
        http_response_code(402);
        echo json_encode(ai_groups_response(true, 'Módulo de grupos indisponível no plano.', ['blocked' => true]));
        exit;
    }
    $campaignPostingMode = ai_groups_campaign_posting_mode();

    if ($method === 'GET') {
        $campaignId = (int)($_GET['id'] ?? $_GET['campaign_id'] ?? 0);
        if ($campaignId > 0) {
            $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
            if (!$campaign) {
                http_response_code(404);
                echo json_encode(ai_groups_response(true, 'Campanha não encontrada.', null));
                exit;
            }
            $campaign = concierge_campaigns_normalize_campaign($campaign);
            echo json_encode(ai_groups_response(false, 'OK', ['campaign' => $campaign]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
            'search' => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to' => trim((string)($_GET['date_to'] ?? '')),
        ];
        $list = ai_get_concierge_campaigns($tenantId, $filters, $page, $limit);
        if (!empty($list['items']) && is_array($list['items'])) {
            $list['items'] = array_map('concierge_campaigns_normalize_campaign', $list['items']);
        }
        echo json_encode(ai_groups_response(false, 'OK', $list), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? $json['action'] ?? '')));
        if ($action === 'send_now') {
            concierge_campaigns_check_perm('concierge_groups_manage', $isTokenAuth);
            $campaignId = (int)($json['campaign_id'] ?? $_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);
            if ($campaignId <= 0) {
                throw new Exception('campaign_id não informado.');
            }
            if ($campaignPostingMode === 'system') {
                http_response_code(409);
                echo json_encode(ai_groups_response(true, 'Disparo imediato de campanhas via sistema está em preparação.', ['campaign_id' => $campaignId, 'posting_mode' => 'system']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            $targets = ai_get_campaign_targets($tenantId, $campaignId);
            
            // Verifica se há conflito de 5 minutos antes de disparar agora
            $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, date('Y-m-d H:i:s'), $campaignId, 5);
            if ($conflict) {
                $cTime = date('H:i', strtotime($conflict['scheduled_at']));
                throw new Exception("Conflito de agendamento: Já existe uma campanha para as {$cTime}. Aguarde o intervalo de 5 minutos.");
            }

            $limitCheck = ai_check_groups_broadcast_limit($tenantId, max(1, count($targets)));
            if (empty($limitCheck['ok'])) {
                http_response_code(402);
                echo json_encode(ai_groups_response(true, (string)($limitCheck['message'] ?? 'Limite mensal atingido.'), ['limit' => $limitCheck]));
                exit;
            }

            $ok = ai_dispatch_concierge_campaign_now($tenantId, $campaignId, (int)(function_exists('user_id') ? user_id() : 0));
            if (!$ok) {
                throw new Exception('Não foi possível iniciar o disparo.');
            }

            $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
            if (is_array($campaign)) {
                $campaign = concierge_campaigns_normalize_campaign($campaign);
            }
            echo json_encode(ai_groups_response(false, 'Disparo imediato solicitado.', ['campaign' => $campaign]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'cancel') {
            concierge_campaigns_check_perm('concierge_groups_manage', $isTokenAuth);
            $campaignId = (int)($json['campaign_id'] ?? $_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);
            if ($campaignId <= 0) {
                throw new Exception('campaign_id não informado.');
            }
            $ok = ai_update_concierge_campaign($tenantId, $campaignId, [
                'status' => 'canceled',
                'updated_by' => (int)(function_exists('user_id') ? user_id() : 0),
            ]);
            if (!$ok) {
                throw new Exception('Falha ao cancelar campanha.');
            }
            $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
            if (is_array($campaign)) {
                $campaign = concierge_campaigns_normalize_campaign($campaign);
            }
            echo json_encode(ai_groups_response(false, 'Campanha cancelada.', ['campaign' => $campaign]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (!$isTokenAuth && !has_permission('access', 'concierge_groups_manage') && !has_permission('access', 'concierge_groups_ai_create')) {
            http_response_code(403);
            echo json_encode(ai_groups_response(true, 'Permissão insuficiente para criar campanha.', null));
            exit;
        }
        $payloadJson = concierge_campaigns_decode_payload($json['payload_json'] ?? null);
        $mediaUrls = concierge_campaigns_collect_media_urls($json, $payloadJson);
        if (!empty($mediaUrls)) {
            $payloadJson['media_urls'] = $mediaUrls;
        }
        if (!empty($json['scheduled_at'])) {
            $payloadJson['scheduled_at'] = (string)$json['scheduled_at'];
        }

        $payload = [
            'title' => trim((string)($json['title'] ?? '')),
            'content' => trim((string)($json['content'] ?? '')),
            'status' => trim((string)($json['status'] ?? 'draft')),
            'product_id' => (int)($json['product_id'] ?? 0),
            'media_url' => $mediaUrls[0] ?? trim((string)($json['media_url'] ?? '')),
            'scheduled_at' => trim((string)($json['scheduled_at'] ?? '')),
            'group_ids' => is_array($json['group_ids'] ?? null) ? $json['group_ids'] : [],
            'created_by' => (int)(function_exists('user_id') ? user_id() : 0),
            'payload_json' => !empty($payloadJson) ? $payloadJson : null,
        ];

        $campaignId = ai_create_concierge_campaign($tenantId, $payload);
        if ($campaignId <= 0) {
            throw new Exception('Falha ao criar campanha.');
        }

        $groupIds = ai_groups_sanitize_group_ids($tenantId, $payload['group_ids']);
        if (!empty($groupIds)) {
            ai_upsert_campaign_broadcast_targets($tenantId, $campaignId, $groupIds);
        }

        if ($payload['scheduled_at'] !== '') {
            $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, $payload['scheduled_at'], $campaignId, 5);
            if ($conflict) {
                $cTime = date('H:i', strtotime($conflict['scheduled_at']));
                throw new Exception("Conflito de agendamento: Já existe uma campanha para as {$cTime}. Escolha um horário com intervalo de 5 minutos.");
            }
            ai_schedule_concierge_campaign($tenantId, $campaignId, $payload['scheduled_at'], (int)(function_exists('user_id') ? user_id() : 0));
        }

        $creationMessage = 'Campanha criada com sucesso.';
        if ((int)($json['send_now'] ?? 0) === 1) {
            if ($campaignPostingMode === 'system') {
                $creationMessage = 'Campanha criada. Disparo imediato via sistema está em preparação e não foi executado.';
            } else {
            $targets = ai_get_campaign_targets($tenantId, $campaignId);
            $limitCheck = ai_check_groups_broadcast_limit($tenantId, max(1, count($targets)));
            if (empty($limitCheck['ok'])) {
                http_response_code(402);
                echo json_encode(ai_groups_response(true, (string)($limitCheck['message'] ?? 'Limite mensal atingido.'), ['campaign_id' => $campaignId, 'limit' => $limitCheck]));
                exit;
            }
            ai_dispatch_concierge_campaign_now($tenantId, $campaignId, (int)(function_exists('user_id') ? user_id() : 0));
            }
        }

        $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
        if (is_array($campaign)) {
            $campaign = concierge_campaigns_normalize_campaign($campaign);
        }
        echo json_encode(ai_groups_response(false, $creationMessage, ['campaign' => $campaign]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'PATCH') {
        concierge_campaigns_check_perm('concierge_groups_manage', $isTokenAuth);
        $campaignId = (int)($json['id'] ?? $json['campaign_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
        if ($campaignId <= 0) {
            throw new Exception('id da campanha não informado.');
        }

        $payload = [];
        foreach (['title', 'content', 'status', 'media_url', 'scheduled_at', 'product_id', 'allow_requeue'] as $key) {
            if (array_key_exists($key, $json)) {
                $payload[$key] = $json[$key];
            }
        }
        if (array_key_exists('group_ids', $json) && is_array($json['group_ids'])) {
            $payload['group_ids'] = $json['group_ids'];
        }
        $currentCampaign = ai_get_concierge_campaign($tenantId, $campaignId);
        $currentPayload = concierge_campaigns_decode_payload(is_array($currentCampaign) ? ($currentCampaign['payload_json'] ?? null) : null);
        $incomingPayload = array_key_exists('payload_json', $json)
            ? concierge_campaigns_decode_payload($json['payload_json'])
            : $currentPayload;
        $mediaInput = [
            'media_url' => $json['media_url'] ?? (is_array($currentCampaign) ? ($currentCampaign['media_url'] ?? '') : ''),
            'media_urls' => $json['media_urls'] ?? [],
        ];
        $mediaUrls = concierge_campaigns_collect_media_urls($mediaInput, $incomingPayload);
        if (!empty($mediaUrls)) {
            $incomingPayload['media_urls'] = $mediaUrls;
            $payload['media_url'] = $mediaUrls[0];
        }
        if (array_key_exists('scheduled_at', $json) && trim((string)$json['scheduled_at']) !== '') {
            $incomingPayload['scheduled_at'] = trim((string)$json['scheduled_at']);
            
            // Verifica conflito de agendamento se estiver alterando o horário
            $conflict = ai_groups_find_campaign_schedule_conflict($tenantId, $incomingPayload['scheduled_at'], $campaignId, 5);
            if ($conflict) {
                $cTime = date('H:i', strtotime($conflict['scheduled_at']));
                throw new Exception("Conflito de agendamento: Já existe uma campanha para as {$cTime}. Escolha um horário com intervalo de 5 minutos.");
            }
        }
        if (array_key_exists('payload_json', $json) || array_key_exists('media_urls', $json) || array_key_exists('media_url', $json)) {
            $payload['payload_json'] = $incomingPayload;
        }

        $payload['updated_by'] = (int)(function_exists('user_id') ? user_id() : 0);

        $ok = ai_update_concierge_campaign($tenantId, $campaignId, $payload);
        if (!$ok) {
            throw new Exception('Falha ao atualizar campanha.');
        }

        if (($json['status'] ?? '') === 'canceled') {
            ai_update_concierge_campaign($tenantId, $campaignId, ['status' => 'canceled', 'updated_by' => $payload['updated_by']]);
        }

        $campaign = ai_get_concierge_campaign($tenantId, $campaignId);
        if (is_array($campaign)) {
            $campaign = concierge_campaigns_normalize_campaign($campaign);
        }
        echo json_encode(ai_groups_response(false, 'Campanha atualizada.', ['campaign' => $campaign]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        concierge_campaigns_check_perm('concierge_groups_manage', $isTokenAuth);
        $campaignId = (int)($json['id'] ?? $json['campaign_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
        if ($campaignId <= 0) {
            throw new Exception('id da campanha não informado.');
        }

        $ok = ai_delete_concierge_campaign($tenantId, $campaignId);
        if (!$ok) {
            throw new Exception('Falha ao excluir campanha ou campanha não encontrada.');
        }

        echo json_encode(ai_groups_response(false, 'Campanha excluída com sucesso.', null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(ai_groups_response(true, 'Método não suportado.', null));
} catch (Throwable $e) {
    $fallbackMethod = strtoupper((string)($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($fallbackMethod === 'GET') {
        http_response_code(200);
        echo json_encode(ai_groups_response(false, 'OK', [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 20,
            'fallback' => true,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
