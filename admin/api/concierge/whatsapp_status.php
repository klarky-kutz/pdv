<?php
ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_evolution.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_whatsapp_status_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function concierge_whatsapp_status_extract_token(): string
{
    $token = trim((string)($_SERVER['HTTP_X_CONCIERGE_TOKEN'] ?? ''));
    if ($token === '') {
        $token = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    }
    if (stripos($token, 'Bearer ') === 0) {
        $token = trim((string)substr($token, 7));
    }
    if ($token === '') {
        $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    }
    return $token;
}
function concierge_whatsapp_status_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'sim', 'on'], true);
}
function concierge_whatsapp_status_base_url_invalid(string $baseUrl): bool
{
    $normalized = strtolower(trim($baseUrl));
    if ($normalized === '') {
        return false;
    }
    return strpos($normalized, 'evolution_webhook.php') !== false
        || strpos($normalized, '{loja_id}') !== false
        || strpos($normalized, '%7bloja_id%7d') !== false;
}

try {
    $json = concierge_whatsapp_status_json_body();
    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? $json['tenant_id'] ?? 0);
    $token = concierge_whatsapp_status_extract_token();
    $includeQr = concierge_whatsapp_status_bool($_GET['include_qrcode'] ?? $_POST['include_qrcode'] ?? $json['include_qrcode'] ?? 0);
    $isTokenAuth = false;

    if ($tenantId > 0 && $token !== '') {
        $storedToken = ai_groups_store_token($tenantId);
        if ($storedToken !== '' && hash_equals($storedToken, $token)) {
            $isTokenAuth = true;
        } else {
            http_response_code(401);
            throw new Exception('Token inválido.');
        }
    }

    if (!$isTokenAuth) {
        if (!is_loggedin()) {
            http_response_code(401);
            throw new Exception('Sessão inválida.');
        }
        $tenantId = ai_tenant_id();
    }

    if ($tenantId <= 0) {
        throw new Exception('tenant_id inválido.');
    }

    $connection = ai_evolution_get_connection($tenantId);
    $baseUrl = trim((string)($connection['base_url'] ?? ''));
    $instanceName = trim((string)($connection['instance_name'] ?? ''));
    $authToken = trim((string)($connection['global_token'] ?? ''));

    if (concierge_whatsapp_status_base_url_invalid($baseUrl)) {
        throw new Exception('URL Base da Evolution inválida. Configure a URL do servidor Evolution no SaaS.');
    }

    $status = trim((string)($connection['status_label'] ?? 'Desconectado'));
    if ($status === '') {
        $status = 'Desconectado';
    }
    $qrcode = (string)($connection['last_qrcode'] ?? '');
    $stateRaw = '';
    $stateHttpCode = 0;
    $stateError = '';
    $configured = ($baseUrl !== '' && $instanceName !== '' && $authToken !== '');

    if ($configured) {
        $stateResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connectionState/' . rawurlencode($instanceName), $authToken);
        $stateHttpCode = (int)($stateResult['http_code'] ?? 0);

        if (!empty($stateResult['ok'])) {
            $stateRaw = (string)(ai_evolution_array_path($stateResult['json'], ['instance', 'state']) ?? '');
            $status = ai_evolution_status_label($stateRaw);
            if ($status === 'Conectado') {
                $qrcode = '';
            }
        } elseif ($stateHttpCode === 404) {
            $status = 'Desconectado';
            $qrcode = '';
            $stateError = 'Instância não encontrada na Evolution.';
        } else {
            $stateError = trim((string)($stateResult['error'] ?? ''));
            if ($stateError === '') {
                $stateError = 'Falha ao consultar status na Evolution (HTTP ' . $stateHttpCode . ').';
            }
        }

        if ($includeQr && $status !== 'Conectado') {
            $connectResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connect/' . rawurlencode($instanceName), $authToken);
            if (!empty($connectResult['ok'])) {
                $freshQr = ai_evolution_extract_qrcode((array)($connectResult['json'] ?? []));
                if ($freshQr !== '') {
                    $qrcode = $freshQr;
                    $status = 'Aguardando leitura';
                }
            }
        }
    }

    ai_evolution_save_connection([
        'ai_evolution_status' => $status,
        'ai_evolution_last_qrcode' => $qrcode,
    ], $tenantId);

    echo json_encode([
        'error' => false,
        'message' => 'Status do WhatsApp consultado com sucesso.',
        'data' => [
            'tenant_id' => $tenantId,
            'instance_name' => $instanceName,
            'configured' => $configured,
            'requires_n8n' => false,
            'status' => $status,
            'state_raw' => $stateRaw,
            'connected' => $status === 'Conectado',
            'qrcode' => $qrcode,
            'state_http_code' => $stateHttpCode,
            'state_error' => $stateError,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
