<?php
/**
 * AJAX: ai_evolution_actions.php
 * Gerencia conexão Evolution API na tela de Configurações IA.
 */
ob_start();
session_start();
include('../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_evolution.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_loggedin()) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Não logado.']);
    exit;
}

function ai_evolution_json_response(array $payload, int $statusCode = 200): void
{
    // Descartar qualquer output acumulado (warnings/notices do PHP) antes de enviar JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ai_evolution_request_value($request, string $key, $default = '')
{
    if (isset($request->post[$key])) {
        return $request->post[$key];
    }
    if (isset($request->get[$key])) {
        return $request->get[$key];
    }
    return $default;
}
function ai_evolution_base_url_is_invalid(string $baseUrl): bool
{
    $normalized = strtolower(trim($baseUrl));
    if ($normalized === '') {
        return false;
    }

    return strpos($normalized, 'evolution_webhook.php') !== false
        || strpos($normalized, '{loja_id}') !== false
        || strpos($normalized, '%7bloja_id%7d') !== false;
}
function ai_evolution_validate_base_url(string $baseUrl): void
{
    if (!ai_evolution_base_url_is_invalid($baseUrl)) {
        return;
    }

    throw new Exception('URL Base da Evolution inválida. Use a URL do servidor Evolution (ex: https://seu-dominio-evolution.com), não o endpoint /api/concierge/evolution_webhook.php.');
}

try {
    $tid = ai_tenant_id();
    $action = (string) ai_evolution_request_value($request, 'action', '');
    $connection = ai_evolution_get_connection($tid);

    $storeToken = ai_evolution_store_token($tid);
    $defaultInboundWebhook = rtrim(ROOT_URL, '/') . '/api/concierge/evolution_webhook.php?loja_id=' . $tid . '&token=' . $storeToken;

    // Lista completa de eventos para o Webhook (Evolution API v2)
    $webhookEvents = [
        'CHATS_DELETE',
        'CHATS_SET',
        'CHATS_UPDATE',
        'CHATS_UPSERT',
        'CONNECTION_UPDATE',
        'CONTACTS_SET',
        'CONTACTS_UPDATE',
        'CONTACTS_UPSERT',
        'GROUP_PARTICIPANTS_UPDATE',
        'GROUP_UPDATE',
        'GROUPS_UPSERT',
        'LABELS_ASSOCIATION',
        'LABELS_EDIT',
        'LOGOUT_INSTANCE',
        'MESSAGES_DELETE',
        'MESSAGES_SET',
        'MESSAGES_UPDATE',
        'MESSAGES_UPSERT',
        'PRESENCE_UPDATE',
        'QRCODE_UPDATED',
        'REMOVE_INSTANCE',
        'SEND_MESSAGE',
        'APPLICATION_STARTUP'
    ];

    switch ($action) {
        case 'get_config': {
            $statusLabel = (string)$connection['status_label'];
            $lastQrCode = (string)$connection['last_qrcode'];
            $baseUrl = (string)$connection['base_url'];
            $instanceName = (string)$connection['instance_name'];
            $authToken = (string)$connection['global_token'];

            if ($baseUrl !== '' && $instanceName !== '' && $authToken !== '' && !ai_evolution_base_url_is_invalid($baseUrl)) {
                $stateResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connectionState/' . rawurlencode($instanceName), $authToken);
                if ($stateResult['ok']) {
                    $stateRaw = (string) (ai_evolution_array_path($stateResult['json'], ['instance', 'state']) ?? '');
                    $statusLabel = $stateRaw !== '' ? ai_evolution_status_label($stateRaw) : 'Desconectado';
                    if ($statusLabel === 'Conectado') {
                        $lastQrCode = '';
                    }
                    
                    $updateData = [
                        'ai_evolution_status'      => $statusLabel,
                        'ai_evolution_last_qrcode' => $lastQrCode,
                    ];

                    ai_evolution_save_connection($updateData, $tid);
                } elseif (in_array((int)$stateResult['http_code'], [401, 404], true)) {
                    $statusLabel = 'Desconectado';
                    $lastQrCode = '';
                    ai_evolution_save_connection([
                        'ai_evolution_status'      => $statusLabel,
                        'ai_evolution_last_qrcode' => $lastQrCode,
                    ], $tid);
                }
            }
            $storeToken = ai_evolution_store_token($tid);
            $webhookInboundResolved = ai_evolution_resolve_webhook_inbound(
                $connection['webhook_inbound_raw'] ?? $connection['webhook_inbound_url'],
                $tid,
                $storeToken
            );

            ai_evolution_json_response([
                'error' => false,
                'config' => [
                    'base_url'              => $connection['base_url'],
                    'instance_name'         => $connection['instance_name'],
                    'webhook_inbound_url'   => $webhookInboundResolved,
                    'webhook_inbound_raw'   => $connection['webhook_inbound_raw'] ?? '',
                    'webhook_target_url'    => $connection['webhook_target_url'],
                    'status_label'          => $statusLabel,
                    'last_qrcode'           => $lastQrCode,
                    'has_global_token'      => $connection['has_global_token'],
                    'base_url_invalid'      => ai_evolution_base_url_is_invalid((string)$connection['base_url']),
                ],
            ]);
            break;
        }

        case 'create_instance': {
            $instanceName = trim((string) ai_evolution_request_value($request, 'instance_name', $connection['instance_name']));
            $webhookN8n   = trim((string) ai_evolution_request_value($request, 'webhook_target_url', $connection['webhook_target_url']));
            $phoneNumber  = trim((string) ai_evolution_request_value($request, 'phone_number', ''));

            $baseUrl    = $connection['base_url'];
            $authToken  = $connection['global_token'];
            $webhookUrl = $connection['webhook_inbound_url'];
            ai_evolution_validate_base_url($baseUrl);

            if ($baseUrl === '' || $instanceName === '' || $authToken === '' || $webhookUrl === '') {
                throw new Exception('Configurações globais da Evolution não definidas no SaaS ou nome da instância ausente.');
            }

            $storeToken = ai_evolution_store_token($tid);
            $webhookUrl = ai_evolution_resolve_webhook_inbound((string)$webhookUrl, $tid, $storeToken);

            if (
                strpos($webhookUrl, '{loja_id}') !== false ||
                strpos($webhookUrl, '{token}')   !== false ||
                strpos($webhookUrl, '%7B')       !== false ||
                strpos($webhookUrl, 'loja_id}')  !== false
            ) {
                throw new Exception(
                    'URL do webhook contém placeholder não resolvido: "' .
                    htmlspecialchars($webhookUrl) . '". ' .
                    'Acesse o SaaS → Moda IA Config e verifique se a ' .
                    '"URL Base do ModernPOS" está preenchida corretamente.'
                );
            }

            $createPayload = [
                'instanceName' => $instanceName,
                'qrcode'       => true,
                'integration'  => 'WHATSAPP-BAILEYS',
                'webhook'      => [
                    'url'      => $webhookUrl,
                    'byEvents' => false,
                    'base64'   => true,
                    'headers'  => [
                        'authorization' => $storeToken,
                        'x-store-id'    => (string)$tid,
                    ],
                    'events'   => $webhookEvents,
                ],
            ];
            if ($phoneNumber !== '') {
                $createPayload['number'] = preg_replace('/\D+/', '', $phoneNumber);
            }

            $createResult = ai_evolution_http_request('POST', $baseUrl . '/instance/create', $authToken, $createPayload);
            // 409 = conflito (instância já existe), 403 com "already in use" = mesmo caso na v2
            $alreadyExists = $createResult['http_code'] === 409
                || ($createResult['http_code'] === 403
                    && strpos((string)($createResult['raw'] ?? ''), 'already in use') !== false);
            
            if (!$createResult['ok'] && !$alreadyExists) {
                $msg = $createResult['error'] ?: ('Falha ao criar instância na Evolution (HTTP ' . $createResult['http_code'] . ')');
                if (!empty($createResult['raw'])) {
                    $msg .= ' - ' . mb_substr($createResult['raw'], 0, 200);
                }
                throw new Exception($msg);
            }

            // Se já existe, garante que o webhook esteja correto
            if ($alreadyExists) {
                $webhookPayload = [
                    'enabled'         => true,
                    'url'             => $webhookUrl,
                    'webhookByEvents' => false,
                    'webhookBase64'   => true,
                    'headers'         => [
                        'authorization' => $storeToken,
                        'x-store-id'    => (string)$tid,
                    ],
                    'events'          => $webhookEvents,
                ];
                ai_evolution_http_request('POST', $baseUrl . '/webhook/set/' . rawurlencode($instanceName), $authToken, $webhookPayload);
            }

            $connectResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connect/' . rawurlencode($instanceName), $authToken);
            $stateResult   = ai_evolution_http_request('GET', $baseUrl . '/instance/connectionState/' . rawurlencode($instanceName), $authToken);

            $qrCode = ai_evolution_extract_qrcode($connectResult['json']);
            if ($qrCode === '') {
                $qrCode = ai_evolution_extract_qrcode($createResult['json']);
            }

            $stateRaw = (string) (ai_evolution_array_path($stateResult['json'], ['instance', 'state']) ?? '');
            $status = ai_evolution_status_label($stateRaw !== '' ? $stateRaw : ($qrCode !== '' ? 'qr' : 'close'));

            $saveData = [
                'ai_whatsapp_provider'          => 'Evolution API (self-hosted)',
                'ai_evolution_instance_name'    => $instanceName,
                'ai_evolution_status'           => $status,
                'ai_evolution_last_qrcode'      => $qrCode,
                // Limpar campos antigos que agora são globais
                'ai_evolution_base_url'         => '',
                'ai_evolution_global_token_enc' => '',
                'ai_instance_url'               => '',
                'ai_api_key'                    => '',
            ];

            if ($webhookN8n !== '') {
                $saveData['ai_webhook_conversation_url'] = $webhookN8n;
                $saveData['ai_webhook_target_url'] = $webhookN8n;
            }

            ai_evolution_save_connection($saveData, $tid);

            ai_evolution_json_response([
                'error' => false,
                'message' => 'Instância criada/atualizada com sucesso.',
                'status' => $status,
                'qrcode' => $qrCode,
                'instance_name' => $instanceName,
                'webhook_inbound_url' => $webhookUrl,
            ]);
            break;
        }

        case 'get_status': {
            $baseUrl = $connection['base_url'];
            $instanceName = $connection['instance_name'];
            $authToken = $connection['global_token'];
            ai_evolution_validate_base_url($baseUrl);

            if ($baseUrl === '' || $instanceName === '' || $authToken === '') {
                throw new Exception('Configure a conexão Evolution antes de consultar status.');
            }

            $stateResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connectionState/' . rawurlencode($instanceName), $authToken);
            
            // Se a instância não existe na Evolution (404), reseta o status local
            if ($stateResult['http_code'] === 404) {
                ai_evolution_save_connection([
                    'ai_evolution_status' => 'Desconectado',
                    'ai_evolution_instance_name' => '',
                    'ai_instance_name' => '',
                ], $tid);
                throw new Exception('Instância não encontrada no servidor Evolution. Ela pode ter sido removida manualmente.');
            }

            if (!$stateResult['ok']) {
                $msg = 'Não foi possível consultar o status da instância (HTTP ' . $stateResult['http_code'] . ').';
                if ($stateResult['http_code'] === 401) {
                    $msg .= ' O Token Global configurado no SaaS parece estar incorreto ou expirado.';
                }
                throw new Exception($msg . ' - ' . $stateResult['raw']);
            }

            $stateRaw = (string) (ai_evolution_array_path($stateResult['json'], ['instance', 'state']) ?? '');
            $qrCode = $connection['last_qrcode'];
            $status = ai_evolution_status_label($stateRaw !== '' ? $stateRaw : ($qrCode !== '' ? 'qr' : 'close'));

            if ($status !== 'Conectado') {
                $connectResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connect/' . rawurlencode($instanceName), $authToken);
                $freshQr = ai_evolution_extract_qrcode($connectResult['json']);
                if ($freshQr !== '') {
                    $qrCode = $freshQr;
                    $status = 'Aguardando leitura';
                }
            } else {
                $qrCode = '';
            }

            $updateData = [
                'ai_evolution_status'      => $status,
                'ai_evolution_last_qrcode' => $qrCode,
            ];

            ai_evolution_save_connection($updateData, $tid);

            ai_evolution_json_response([
                'error' => false,
                'status' => $status,
                'state_raw' => $stateRaw,
                'qrcode' => $qrCode,
                'instance_name' => $instanceName,
            ]);
            break;
        }

        case 'refresh_qrcode': {
            $baseUrl = $connection['base_url'];
            $instanceName = $connection['instance_name'];
            $authToken = $connection['global_token'];
            ai_evolution_validate_base_url($baseUrl);

            if ($baseUrl === '' || $instanceName === '' || $authToken === '') {
                throw new Exception('Configure a conexão Evolution antes de gerar QR Code.');
            }

            $connectResult = ai_evolution_http_request('GET', $baseUrl . '/instance/connect/' . rawurlencode($instanceName), $authToken);
            if (!$connectResult['ok']) {
                throw new Exception('Não foi possível gerar QR Code (HTTP ' . $connectResult['http_code'] . ').');
            }

            $qrCode = ai_evolution_extract_qrcode($connectResult['json']);
            if ($qrCode === '') {
                throw new Exception('A Evolution não retornou QR Code nesta tentativa.');
            }

            ai_evolution_save_connection([
                'ai_evolution_status'      => 'Aguardando leitura',
                'ai_evolution_last_qrcode' => $qrCode,
            ], $tid);

            ai_evolution_json_response([
                'error' => false,
                'status' => 'Aguardando leitura',
                'qrcode' => $qrCode,
                'instance_name' => $instanceName,
            ]);
            break;
        }

        case 'update_webhook': {
            $baseUrl = $connection['base_url'];
            $instanceName = $connection['instance_name'];
            $authToken = $connection['global_token'];
            $webhookUrl = trim((string) ai_evolution_request_value($request, 'webhook_url', $connection['webhook_inbound_url'] ?: $defaultInboundWebhook));
            ai_evolution_validate_base_url($baseUrl);

            if ($baseUrl === '' || $instanceName === '' || $authToken === '') {
                throw new Exception('Configure a conexão Evolution antes de atualizar webhook.');
            }

            $storeToken = ai_evolution_store_token($tid);
            $webhookUrl = ai_evolution_resolve_webhook_inbound($webhookUrl, $tid, $storeToken);
            if ($webhookUrl === '') {
                throw new Exception('Webhook de entrada inválido.');
            }
            $payload = [
                'enabled'         => true,
                'url'             => $webhookUrl,
                'webhookByEvents' => false,
                'webhookBase64'   => true,
                'headers'         => [
                    'authorization' => $storeToken,
                    'x-store-id'    => (string)$tid,
                ],
                'events'          => $webhookEvents,
            ];
            $result = ai_evolution_http_request('POST', $baseUrl . '/webhook/set/' . rawurlencode($instanceName), $authToken, $payload);
            if (!$result['ok']) {
                $msg = 'Falha ao atualizar webhook na Evolution (HTTP ' . $result['http_code'] . ').';
                if (!empty($result['raw'])) {
                    $msg .= ' - ' . mb_substr((string)$result['raw'], 0, 200);
                }
                throw new Exception($msg);
            }

            ai_evolution_save_connection([
                'ai_evolution_webhook_url' => $webhookUrl,
            ], $tid);

            ai_evolution_json_response([
                'error' => false,
                'message' => 'Webhook atualizado com sucesso.',
                'webhook_url' => $webhookUrl,
            ]);
            break;
        }

        case 'delete_instance': {
            $baseUrl = $connection['base_url'];
            $instanceName = $connection['instance_name'];
            $authToken = $connection['global_token'];
            ai_evolution_validate_base_url($baseUrl);

            if ($baseUrl === '' || $instanceName === '' || $authToken === '') {
                throw new Exception('Instância não configurada ou dados globais ausentes.');
            }

            $deleteResult = ai_evolution_http_request('DELETE', $baseUrl . '/instance/delete/' . rawurlencode($instanceName), $authToken);
            if (!$deleteResult['ok'] && $deleteResult['http_code'] !== 404) {
                $msg = 'Falha ao deletar instância na Evolution (HTTP ' . $deleteResult['http_code'] . ').';
                if (!empty($deleteResult['raw'])) {
                    $msg .= ' - ' . mb_substr((string)$deleteResult['raw'], 0, 200);
                }
                throw new Exception($msg);
            }

            ai_evolution_save_connection([
                'ai_evolution_status'           => 'Desconectado',
                'ai_evolution_last_qrcode'      => '',
                'ai_evolution_instance_name'    => '',
                'ai_evolution_instance_token_enc' => '',
                'ai_instance_name'              => '',
            ], $tid);

            ai_evolution_json_response([
                'error' => false,
                'message' => 'Instância removida com sucesso.',
            ]);
            break;
        }

        case 'get_logs': {
            // Limpa logs antigos antes de retornar os novos (mantém apenas o último)
            try {
                $limit = (int) ai_evolution_request_value($request, 'limit', 1);
                $stDel = db()->prepare("DELETE FROM ai_evolution_webhook_logs WHERE tenant_id = :tid AND id NOT IN (SELECT id FROM (SELECT id FROM ai_evolution_webhook_logs WHERE tenant_id = :tid2 ORDER BY id DESC LIMIT :lim) x)");
                $stDel->bindValue(':tid', $tid, PDO::PARAM_INT);
                $stDel->bindValue(':tid2', $tid, PDO::PARAM_INT);
                $stDel->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stDel->execute();
            } catch (Exception $e) {}

            $logs = ai_evolution_get_recent_logs($tid, 1);
            ai_evolution_json_response([
                'error' => false,
                'logs' => $logs,
            ]);
            break;
        }

        default:
            throw new Exception('Ação inválida.');
    }
} catch (\Throwable $e) {
    ai_evolution_json_response([
        'error' => true,
        'message' => $e->getMessage(),
    ], 200); // Retorna 200 para que o JS possa processar a mensagem de erro sem erro de console 422
}
