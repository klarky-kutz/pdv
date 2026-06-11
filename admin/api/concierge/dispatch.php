<?php
ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_evolution.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_dispatch_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
function concierge_dispatch_extract_token(): string
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

try {
    $json = concierge_dispatch_json_body();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? $json['tenant_id'] ?? 0);
    $action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? $json['action'] ?? 'run')));
    $limit = max(1, min(200, (int)($_GET['limit'] ?? $_POST['limit'] ?? $json['limit'] ?? 25)));

    $token = concierge_dispatch_extract_token();
    $isTokenAuth = false;
    if ($tenantId > 0 && $token !== '') {
        $stored = ai_groups_store_token($tenantId);
        if ($stored !== '' && hash_equals($stored, $token)) {
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
        if (user_group_id() != 1 && !has_permission('access', 'concierge_groups_manage')) {
            http_response_code(403);
            throw new Exception('Permissão insuficiente para executar disparos.');
        }
        if ($tenantId <= 0) {
            $tenantId = ai_tenant_id();
        }
    }

    if ($tenantId > 0 && !ai_groups_plan_is_enabled($tenantId)) {
        http_response_code(402);
        echo json_encode(ai_groups_response(true, 'Módulo de grupos indisponível no plano.', ['blocked' => true]));
        exit;
    }

    // Libera a sessão IMEDIATAMENTE após as verificações para não travar o sistema
    // O disparo da Evolution API pode demorar e travar outras abas/requisições do usuário
    if (session_id()) {
        session_write_close();
    }
    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    // Se for um "lazy cron" do front-end, podemos opcionalmente fechar a conexão rápido
    // Mas por enquanto vamos apenas garantir que a sessão foi liberada.

    $result = [
        'action' => $action,
        'tenant_id' => $tenantId,
        'limit' => $limit,
        'campaigns' => null,
        'statuses' => null,
    ];

    if ($action === 'status' || $action === 'statuses') {
        $result['statuses'] = ai_process_due_concierge_statuses($tenantId, $limit);
    } elseif ($action === 'campaign' || $action === 'campaigns' || $action === 'disparos') {
        $result['campaigns'] = ai_process_due_concierge_campaigns($tenantId, $limit);
    } else {
        $result['campaigns'] = ai_process_due_concierge_campaigns($tenantId, $limit);
        $result['statuses'] = ai_process_due_concierge_statuses($tenantId, $limit);
    }

    echo json_encode(ai_groups_response(false, 'Execução concluída.', $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code($method === 'GET' ? 422 : 422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
