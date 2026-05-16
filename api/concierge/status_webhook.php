<?php
ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_status_webhook_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function concierge_status_webhook_extract_token(): string
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
function concierge_status_webhook_get_one(int $tenantId, int $statusId): ?array
{
    if ($tenantId <= 0 || $statusId <= 0 || !ai_groups_table_exists('concierge_status')) {
        return null;
    }

    try {
        $stmt = db()->prepare("SELECT * FROM concierge_status WHERE tenant_id = :tid AND id = :sid LIMIT 1");
        $stmt->execute([':tid' => $tenantId, ':sid' => $statusId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

try {
    ai_groups_ensure_status_schema();
    $json = concierge_status_webhook_json_body();

    $tenantId = (int)($json['tenant_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_GET['loja_id'] ?? 0);
    if ($tenantId <= 0) {
        throw new Exception('tenant_id não informado.');
    }

    $storedToken = ai_groups_store_token($tenantId);
    if ($storedToken === '') {
        throw new Exception('Token da loja não configurado.');
    }

    $incomingToken = concierge_status_webhook_extract_token();
    if ($incomingToken === '' || !hash_equals($storedToken, $incomingToken)) {
        http_response_code(401);
        throw new Exception('Token inválido.');
    }

    $statusId = (int)($json['status_id'] ?? $json['id'] ?? $_GET['status_id'] ?? $_GET['id'] ?? 0);
    if ($statusId <= 0) {
        throw new Exception('status_id não informado.');
    }

    $statusValue = strtolower(trim((string)($json['status'] ?? 'sent')));
    if (!in_array($statusValue, ['pending', 'sending', 'sent', 'error', 'canceled'], true)) {
        throw new Exception('status inválido para atualização.');
    }

    $payload = [
        'status' => $statusValue,
    ];

    if (array_key_exists('error_message', $json)) {
        $payload['error_message'] = trim((string)$json['error_message']);
    }
    if (array_key_exists('content', $json)) {
        $payload['content'] = (string)$json['content'];
    }
    if (array_key_exists('media_url', $json)) {
        $payload['media_url'] = (string)$json['media_url'];
    }
    if (array_key_exists('payload_json', $json)) {
        $payload['payload_json'] = $json['payload_json'];
    }

    if ($statusValue === 'sent') {
        $payload['sent_at'] = trim((string)($json['sent_at'] ?? ''));
        if ($payload['sent_at'] === '') {
            $payload['sent_at'] = date('Y-m-d H:i:s');
        }
        $payload['error_message'] = '';
    }

    $ok = ai_update_concierge_status($tenantId, $statusId, $payload);
    if (!$ok) {
        throw new Exception('Falha ao atualizar status da postagem.');
    }

    $row = concierge_status_webhook_get_one($tenantId, $statusId);

    // Lógica de Repostagem se o status for 'sent'
    if ($statusValue === 'sent' && $row) {
        ai_groups_handle_status_repost_logic($tenantId, $statusId, $row);
        // Recarrega a linha se ela foi alterada pela lógica de repostagem
        $row = concierge_status_webhook_get_one($tenantId, $statusId);
    }

    echo json_encode(ai_groups_response(false, 'Status da postagem atualizado.', ['status' => $row]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
