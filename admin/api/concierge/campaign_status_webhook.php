<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_groups_helper.php';

ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');

function concierge_status_json_raw(): string
{
    $raw = file_get_contents('php://input');
    return is_string($raw) ? $raw : '';
}

function concierge_status_json_decode(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function concierge_status_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}
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

function concierge_status_check_replay(int $tenantId, string $nonce, int $ts): bool
{
    if ($nonce === '') {
        return false;
    }
    $file = rtrim((string)DIR_LOG, '/\\') . DIRECTORY_SEPARATOR . 'concierge_callback_nonce_' . $tenantId . '.json';
    $map = [];
    if (is_file($file)) {
        $loaded = json_decode((string)@file_get_contents($file), true);
        if (is_array($loaded)) {
            $map = $loaded;
        }
    }

    $now = time();
    foreach ($map as $n => $when) {
        if ((int)$when < ($now - 900)) {
            unset($map[$n]);
        }
    }

    if (isset($map[$nonce])) {
        return false;
    }
    $map[$nonce] = $ts;
    @file_put_contents($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return true;
}

function concierge_status_append_log(array $entry): void
{
    $file = rtrim((string)DIR_LOG, '/\\') . DIRECTORY_SEPARATOR . 'concierge_campaign_status.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND);
}

try {
    $raw = concierge_status_json_raw();
    $json = concierge_status_json_decode($raw);

    $tenantId = (int)($json['tenant_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_GET['loja_id'] ?? 0);
    if ($tenantId <= 0) {
        throw new Exception('tenant_id não informado.');
    }

    $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
    $stmt->execute([':tid' => $tenantId]);
    $token = (string)$stmt->fetchColumn();
    if ($token === '') {
        throw new Exception('Token da loja não configurado.');
    }
    $simpleToken = concierge_status_extract_token();
    $isSimpleAuth = $simpleToken !== '' && hash_equals($token, $simpleToken);

    if (!$isSimpleAuth) {
        $timestamp = (int)concierge_status_header('X-Concierge-Timestamp');
        $nonce = concierge_status_header('X-Concierge-Nonce');
        $signature = concierge_status_header('X-Concierge-Signature');
        if (stripos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }

        if ($timestamp <= 0 || $nonce === '' || $signature === '') {
            http_response_code(401);
            throw new Exception('Headers de segurança ausentes.');
        }
        if (abs(time() - $timestamp) > 300) {
            http_response_code(401);
            throw new Exception('Timestamp fora da janela permitida.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $raw, $token);
        if (!hash_equals($expected, $signature)) {
            http_response_code(401);
            throw new Exception('Assinatura inválida.');
        }

        if (!concierge_status_check_replay($tenantId, $nonce, $timestamp)) {
            http_response_code(409);
            throw new Exception('Callback duplicado (replay detectado).');
        }
    }

    $campaignId = (int)($json['campaign_id'] ?? 0);
    $groupId = (int)($json['group_id'] ?? 0);
    $status = (string)($json['status'] ?? 'pending');
    $externalMessageId = trim((string)($json['external_message_id'] ?? ''));
    $errorMessage = trim((string)($json['error_message'] ?? ''));

    if ($campaignId <= 0 || $groupId <= 0) {
        throw new Exception('campaign_id/group_id inválidos.');
    }

    $ok = ai_mark_broadcast_status($tenantId, $campaignId, $groupId, $status, $externalMessageId, $errorMessage);
    if (!$ok) {
        throw new Exception('Falha ao persistir status de broadcast.');
    }

    $summary = ai_get_campaign_delivery_summary($tenantId, $campaignId);
    $campaignStatus = null;
    if (($summary['total'] ?? 0) > 0) {
        $sent = (int)($summary['sent'] ?? 0);
        $error = (int)($summary['error'] ?? 0);
        $skipped = (int)($summary['skipped'] ?? 0);
        $sending = (int)($summary['sending'] ?? 0);
        $queued = (int)($summary['queued'] ?? 0);
        $pending = (int)($summary['pending'] ?? 0);

        if (($sent + $error + $skipped) >= (int)$summary['total']) {
            $campaignStatus = $sent > 0 ? 'sent' : 'failed';
            $sql = "UPDATE concierge_campaigns SET status = :status, sent_at = CASE WHEN :status2 = 'sent' THEN NOW() ELSE sent_at END, updated_at = NOW() WHERE tenant_id = :tid AND id = :cid LIMIT 1";
            db()->prepare($sql)->execute([':status' => $campaignStatus, ':status2' => $campaignStatus, ':tid' => $tenantId, ':cid' => $campaignId]);
        } elseif ($sending > 0) {
            $campaignStatus = 'sending';
            db()->prepare("UPDATE concierge_campaigns SET status = 'sending', updated_at = NOW() WHERE tenant_id = :tid AND id = :cid LIMIT 1")
                ->execute([':tid' => $tenantId, ':cid' => $campaignId]);
        }
    }

    concierge_status_append_log([
        'tenant_id' => $tenantId,
        'campaign_id' => $campaignId,
        'group_id' => $groupId,
        'status' => $status,
        'external_message_id' => $externalMessageId,
        'campaign_status' => $campaignStatus,
        'summary' => $summary,
    ]);

    echo json_encode(ai_groups_response(false, 'Status processado.', [
        'campaign_id' => $campaignId,
        'group_id' => $groupId,
        'summary' => $summary,
        'campaign_status' => $campaignStatus,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    concierge_status_append_log([
        'error' => true,
        'message' => $e->getMessage(),
        'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
