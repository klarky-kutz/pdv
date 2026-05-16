<?php
/**
 * process_card_modal_payment.php
 *
 * Processa pagamento por cartão quando o checkout usa a modal do Cartão 3D.
 * Gateways suportados:
 * - Asaas (envia dados do cartão para a API Asaas)
 * - Mercado Pago (tokenização via SDK JS v2 -> envia token para a API MP)
 *
 * IMPORTANTE:
 * - Para Mercado Pago, NÃO recebemos número do cartão no backend (somente token).
 * - Para Asaas, o backend recebe dados do cartão (conforme implementação legada do SaaS).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Carrega o _init.php do ModernPOS
$initPath = dirname(__DIR__, 2) . '/_init.php';
if (file_exists($initPath)) {
    require_once $initPath;
}

// Carrega PaymentGatewayConfig (módulo Conta)
$pgcPath = dirname(__DIR__) . '/_inc/PaymentGatewayConfig.php';
if (file_exists($pgcPath)) {
    require_once $pgcPath;
}

// Verifica se é requisição AJAX
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
);

if (!$isAjax) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
    exit;
}

if (!function_exists('user_id') || !user_id()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

if (!function_exists('db')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Função db() não disponível.']);
    exit;
}

if (!class_exists('PaymentGatewayConfig')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PaymentGatewayConfig não disponível.']);
    exit;
}

// Body JSON
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$orderId = isset($body['order_id']) ? (int)$body['order_id'] : 0;
$gatewayCode = isset($body['gateway_code']) ? strtolower(trim((string)$body['gateway_code'])) : '';
$customer = isset($body['customer']) && is_array($body['customer']) ? $body['customer'] : [];
$card = isset($body['card']) && is_array($body['card']) ? $body['card'] : [];
$mp = isset($body['mp']) && is_array($body['mp']) ? $body['mp'] : [];

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'order_id inválido.']);
    exit;
}

if (!in_array($gatewayCode, ['asaas', 'mercado_pago'], true)) {
    echo json_encode(['success' => false, 'message' => 'gateway_code inválido.']);
    exit;
}

// Helpers
$findGatewayTenantId = function(PDO $pdo): int {
    $tid = 1;
    try {
        $stmt = $pdo->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
        if ($stmt) {
            $tmp = (int)$stmt->fetchColumn();
            if ($tmp > 0) $tid = $tmp;
        }
    } catch (Throwable $e) {
        // ignore
    }
    return $tid;
};

$resolveSessionTenantId = function(PDO $pdo): int {
    $tid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($tid > 0) return $tid;

    $uid = (int)user_id();
    $st = $pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
};

$activateTenantPlan = function(PDO $pdo, int $tenantId, int $planId): void {
    if ($tenantId <= 0 || $planId <= 0) return;

    // Busca duração do plano (fallback: 1 mês)
    $durationMonths = 1;
    try {
        $stmtPlan = $pdo->prepare('SELECT duration_months FROM plans WHERE plan_id = ? LIMIT 1');
        $stmtPlan->execute([$planId]);
        $tmp = (int)$stmtPlan->fetchColumn();
        if ($tmp > 0) $durationMonths = $tmp;
    } catch (Throwable $e) {
        $durationMonths = 1;
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $durationMonths . ' months'));

    // Verifica quais colunas existem na tabela tenants
    $hasSubscriptionStatus = false;
    $hasSubscriptionExpiresAt = false;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        $hasSubscriptionStatus = in_array('subscription_status', $cols, true);
        $hasSubscriptionExpiresAt = in_array('subscription_expires_at', $cols, true);
    } catch (Throwable $e) {
        // ignore
    }

    // Monta SQL dinamicamente baseado nas colunas existentes
    $setClauses = ['plan_id = :plan_id'];
    $params = [':plan_id' => $planId, ':tenant_id' => $tenantId];

    if ($hasSubscriptionStatus) {
        $setClauses[] = "subscription_status = 'active'";
    }
    if ($hasSubscriptionExpiresAt) {
        $setClauses[] = "subscription_expires_at = :expires_at";
        $params[':expires_at'] = $expiresAt;
    }

    // Verifica se updated_at existe
    try {
        $cols = $cols ?? $pdo->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('updated_at', $cols, true)) {
            $setClauses[] = "updated_at = NOW()";
        }
    } catch (Throwable $e) {
        // ignore
    }

    $sql = "UPDATE tenants SET " . implode(', ', $setClauses) . " WHERE tenant_id = :tenant_id";
    $stmtTenant = $pdo->prepare($sql);
    $stmtTenant->execute($params);
};

try {
    $pdo = db();

    $sessionTenantId = $resolveSessionTenantId($pdo);
    if ($sessionTenantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
        exit;
    }

    // Busca pedido
    $stmtOrder = $pdo->prepare(
        "SELECT order_id, tenant_id, plan_id, amount, status, transaction_id, reference_no
           FROM saas_orders
          WHERE order_id = ?
          LIMIT 1"
    );
    $stmtOrder->execute([$orderId]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    if ((int)$order['tenant_id'] !== $sessionTenantId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado a este pedido.']);
        exit;
    }

    $orderStatus = strtolower((string)($order['status'] ?? 'pending'));
    if (in_array($orderStatus, ['paid', 'completed'], true)) {
        echo json_encode(['success' => true, 'message' => 'Pedido já está pago.', 'already_paid' => true, 'order_id' => $orderId]);
        exit;
    }

    $tenantId = (int)$order['tenant_id'];
    $planId = (int)$order['plan_id'];
    $amount = (float)($order['amount'] ?? 0);
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor do pedido inválido.']);
        exit;
    }

    // Dados do cliente
    $name = trim((string)($customer['name'] ?? ''));
    $email = trim((string)($customer['email'] ?? ''));
    $document = trim((string)($customer['document'] ?? ''));
    $personType = strtolower(trim((string)($customer['person_type'] ?? '')));
    $phone = trim((string)($customer['phone'] ?? ''));
    $company = trim((string)($customer['company'] ?? ''));

    $documentDigits = preg_replace('/\D+/', '', $document);
    $documentType = in_array($personType, ['cpf', 'cnpj'], true) ? $personType : '';
    if ($documentType === '' && $documentDigits !== '') {
        $documentType = strlen($documentDigits) > 11 ? 'cnpj' : 'cpf';
    }

    // Nome para descrição
    $planName = '';
    try {
        $stmtPlan = $pdo->prepare('SELECT name FROM plans WHERE plan_id = ? LIMIT 1');
        $stmtPlan->execute([$planId]);
        $planName = (string)$stmtPlan->fetchColumn();
    } catch (Throwable $e) {
        $planName = '';
    }
    if ($planName === '') {
        $planName = !empty($order['reference_no']) ? (string)$order['reference_no'] : 'Plano';
    }

    $gatewayTenantId = $findGatewayTenantId($pdo);

    // =========================
    // ASAAS
    // =========================
    if ($gatewayCode === 'asaas') {
        $gw = PaymentGatewayConfig::get($pdo, $gatewayTenantId, 'asaas');
        
        // Log de debug
        error_log('[ASAAS_CARD_PAYMENT] Gateway config: ' . json_encode($gw));
        
        if (!$gw || empty($gw['enabled']) || empty($gw['asaas_api_key'])) {
            error_log('[ASAAS_CARD_PAYMENT] Asaas nao configurado. gw=' . json_encode($gw));
            echo json_encode(['success' => false, 'message' => 'Asaas não configurado.']);
            exit;
        }

        $apiKey = (string)$gw['asaas_api_key'];
        $environment = !empty($gw['environment']) ? (string)$gw['environment'] : 'sandbox';
        $baseUrl = $environment === 'production' ? 'https://api.asaas.com/v3' : 'https://sandbox.asaas.com/api/v3';
        
        // Verifica se assinatura recorrente está habilitada
        $asaasSubscriptionEnabled = !empty($gw['asaas_subscription_enabled']);
        error_log('[ASAAS_CARD_PAYMENT] Subscription enabled: ' . ($asaasSubscriptionEnabled ? 'SIM' : 'NAO'));

        $cardNumber = isset($card['number']) ? preg_replace('/\s+/', '', (string)$card['number']) : '';
        $cardExpiry = isset($card['expiry']) ? trim((string)$card['expiry']) : '';
        $cardCvv = isset($card['cvc']) ? preg_replace('/\D+/', '', (string)$card['cvc']) : '';
        $cardName = isset($card['name']) ? trim((string)$card['name']) : $name;

        if ($cardNumber === '' || $cardExpiry === '' || $cardCvv === '') {
            echo json_encode(['success' => false, 'message' => 'Dados do cartão incompletos.']);
            exit;
        }

        $expDigits = preg_replace('/\D+/', '', $cardExpiry);
        $expMonth = substr($expDigits, 0, 2);
        $expYear = strlen($expDigits) >= 4 ? substr($expDigits, -4) : substr($expDigits, -2);
        if (strlen($expYear) === 2) {
            $expYear = '20' . $expYear;
        }

        // Valida e sanitiza telefone do titular (ASAAS exige DDD)
        $phoneDigits = preg_replace('/\D+/', '', (string)$phone);
        if ($phoneDigits === '' || strlen($phoneDigits) < 10) {
            echo json_encode([
                'success' => false,
                'message' => 'Informe o número de contato com DDD do titular do cartão (ex.: 11999999999).'
            ]);
            exit;
        }

        // 1) Cria cliente no Asaas
        $customerPayload = [
            'name' => $name !== '' ? $name : ($company !== '' ? $company : 'Cliente'),
            'email' => $email !== '' ? $email : null,
            'cpfCnpj' => $documentDigits !== '' ? $documentDigits : null,
            'mobilePhone' => $phoneDigits,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/customers',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'access_token: ' . $apiKey,
                'User-Agent: ModernPOS/1.0',
            ],
            CURLOPT_POSTFIELDS => json_encode($customerPayload),
        ]);
        $customerResp = curl_exec($ch);
        $customerErr = curl_error($ch);
        $customerStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($customerResp === false) {
            throw new Exception('Erro de comunicação com o Asaas: ' . $customerErr);
        }
        if ($customerStatus < 200 || $customerStatus >= 300) {
            throw new Exception('Erro ao criar cliente no Asaas. HTTP ' . $customerStatus . ' Corpo: ' . $customerResp);
        }
        $customerJson = json_decode($customerResp, true);
        if (!is_array($customerJson) || empty($customerJson['id'])) {
            throw new Exception('Resposta inválida ao criar cliente no Asaas.');
        }
        $customerId = (string)$customerJson['id'];

        // 2) Cria pagamento OU assinatura (se asaas_subscription_enabled)
        $remoteIp = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
        
        $creditCardData = [
            'holderName' => $cardName,
            'number' => $cardNumber,
            'expiryMonth' => $expMonth,
            'expiryYear' => $expYear,
            'ccv' => $cardCvv,
        ];
        
        $creditCardHolderInfoData = [
            'name' => $cardName,
            'email' => $email !== '' ? $email : null,
            'cpfCnpj' => $documentDigits !== '' ? $documentDigits : null,
            'phone' => $phoneDigits,
            'mobilePhone' => $phoneDigits,
            // Placeholder enquanto não há coleta de endereço no checkout
            'postalCode' => '29142037',
            'addressNumber' => '1',
        ];
        
        $txId = '';
        $remoteStatus = '';
        $isPaid = false;
        
        if ($asaasSubscriptionEnabled) {
            // ============ CRIAR ASSINATURA RECORRENTE ============
            error_log('[ASAAS_CARD_PAYMENT] Criando ASSINATURA RECORRENTE para order_id=' . $orderId);
            
            $subscriptionPayload = [
                'customer' => $customerId,
                'billingType' => 'CREDIT_CARD',
                'value' => $amount,
                'nextDueDate' => date('Y-m-d'),
                'cycle' => 'MONTHLY',
                'description' => $planName,
                'externalReference' => (string)$orderId,
                'creditCard' => $creditCardData,
                'creditCardHolderInfo' => $creditCardHolderInfoData,
            ];
            
            error_log('[ASAAS_CARD_PAYMENT] Subscription payload: ' . json_encode($subscriptionPayload));
            
            if ($remoteIp !== '') {
                $subscriptionPayload['remoteIp'] = $remoteIp;
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/subscriptions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'access_token: ' . $apiKey,
                    'User-Agent: ModernPOS/1.0',
                ],
                CURLOPT_POSTFIELDS => json_encode($subscriptionPayload),
            ]);
            $subResp = curl_exec($ch);
            $subErr = curl_error($ch);
            $subStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log('[ASAAS_CARD_PAYMENT] Enviando request para: ' . $baseUrl . '/subscriptions');
            
            if ($subResp === false) {
                error_log('[ASAAS_CARD_PAYMENT] CURL error: ' . $subErr);
                throw new Exception('Erro de comunicação com o Asaas (assinatura): ' . $subErr);
            }
            
            error_log('[ASAAS_CARD_PAYMENT] HTTP Status: ' . $subStatus);
            error_log('[ASAAS_CARD_PAYMENT] Response: ' . $subResp);
            
            if ($subStatus < 200 || $subStatus >= 300) {
                // Extrai mensagem amigável
                $bodyArr = json_decode($subResp, true);
                $friendly = null;
                if (is_array($bodyArr) && !empty($bodyArr['errors'][0]['description'])) {
                    $friendly = trim((string)$bodyArr['errors'][0]['description']);
                }
                if (!empty($bodyArr['errors'][0]['code']) && $bodyArr['errors'][0]['code'] === 'invalid_action') {
                    $friendly = 'Transação não autorizada. Verifique seu cartão de crédito.';
                }
                error_log('[ASAAS_CARD_PAYMENT] Erro Asaas: ' . ($friendly ?: $subResp));
                throw new Exception($friendly ?: 'Erro ao criar assinatura no Asaas. HTTP ' . $subStatus);
            }
            $subJson = json_decode($subResp, true);
            if (!is_array($subJson) || empty($subJson['id'])) {
                error_log('[ASAAS_CARD_PAYMENT] Resposta invalida: ' . $subResp);
                throw new Exception('Resposta inválida ao criar assinatura no Asaas.');
            }
            
            $txId = (string)$subJson['id'];
            $remoteStatus = strtoupper((string)($subJson['status'] ?? ''));
            
            error_log('[ASAAS_CARD_PAYMENT] Assinatura criada! ID=' . $txId . ' Status=' . $remoteStatus);
            
            // Para assinaturas, status ACTIVE significa que foi criada com sucesso
            // A primeira cobrança é processada automaticamente e pode demorar alguns segundos
            // Consideramos sucesso se a assinatura foi criada (ACTIVE) ou se já tem pagamento confirmado
            $isPaid = in_array($remoteStatus, ['ACTIVE'], true);
            
        } else {
            // ============ CRIAR PAGAMENTO AVULSO ============
            $paymentPayload = [
                'customer' => $customerId,
                'billingType' => 'CREDIT_CARD',
                'value' => $amount,
                'dueDate' => date('Y-m-d'),
                'description' => $planName,
                'externalReference' => (string)$orderId,
                'creditCard' => $creditCardData,
                'creditCardHolderInfo' => $creditCardHolderInfoData,
            ];

            if ($remoteIp !== '') {
                $paymentPayload['remoteIp'] = $remoteIp;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $baseUrl . '/payments',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'access_token: ' . $apiKey,
                    'User-Agent: ModernPOS/1.0',
                ],
                CURLOPT_POSTFIELDS => json_encode($paymentPayload),
            ]);
            $paymentResp = curl_exec($ch);
            $paymentErr = curl_error($ch);
            $paymentStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($paymentResp === false) {
                throw new Exception('Erro de comunicação com o Asaas: ' . $paymentErr);
            }
            if ($paymentStatus < 200 || $paymentStatus >= 300) {
                throw new Exception('Erro ao criar pagamento no Asaas. HTTP ' . $paymentStatus . ' Corpo: ' . $paymentResp);
            }
            $paymentJson = json_decode($paymentResp, true);
            if (!is_array($paymentJson) || empty($paymentJson['id'])) {
                throw new Exception('Resposta inválida ao criar pagamento no Asaas.');
            }

            $txId = (string)$paymentJson['id'];
            $remoteStatus = strtoupper((string)($paymentJson['status'] ?? ''));
            $isPaid = in_array($remoteStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true);
        }

        $pdo->beginTransaction();
        $newStatus = $isPaid ? 'paid' : 'pending';
        $stmtUpd = $pdo->prepare(
            "UPDATE saas_orders
                SET transaction_id = :tx,
                    status = :status,
                    paid_at = CASE WHEN :status_check = 'paid' THEN NOW() ELSE paid_at END,
                    updated_at = NOW()
              WHERE order_id = :order_id"
        );
        $stmtUpd->execute([
            ':tx' => $txId,
            ':status' => $newStatus,
            ':status_check' => $newStatus,
            ':order_id' => $orderId,
        ]);

        if ($isPaid) {
            $activateTenantPlan($pdo, $tenantId, $planId);
        }

        $pdo->commit();

        if (!$isPaid) {
            echo json_encode([
                'success' => false,
                'message' => 'Pagamento não foi confirmado. Status: ' . $remoteStatus,
                'status' => $remoteStatus,
                'order_id' => $orderId,
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pagamento confirmado com sucesso!',
            'order_id' => $orderId,
            'status' => 'paid',
        ]);
        exit;
    }

    // =========================
    // MERCADO PAGO
    // =========================
    if ($gatewayCode === 'mercado_pago') {
        $gw = PaymentGatewayConfig::get($pdo, $gatewayTenantId, 'mercado_pago');
        if (!$gw || empty($gw['enabled']) || empty($gw['mp_access_token'])) {
            echo json_encode(['success' => false, 'message' => 'Mercado Pago não configurado.']);
            exit;
        }

        $accessToken = (string)$gw['mp_access_token'];

        $token = isset($mp['token']) ? trim((string)$mp['token']) : '';
        $installments = isset($mp['installments']) ? (int)$mp['installments'] : 1;
        $paymentMethodId = isset($mp['payment_method_id']) ? trim((string)$mp['payment_method_id']) : '';
        $issuerId = isset($mp['issuer_id']) ? trim((string)$mp['issuer_id']) : '';

        if ($token === '') {
            echo json_encode(['success' => false, 'message' => 'Token do cartão não recebido.']);
            exit;
        }

        if ($installments <= 0) {
            $installments = 1;
        }

        $cleanDoc = $documentDigits;
        $idType = 'CPF';
        if ($documentType === 'cnpj') {
            $idType = 'CNPJ';
        } elseif ($documentType === 'cpf') {
            $idType = 'CPF';
        } elseif ($cleanDoc !== '') {
            $idType = strlen($cleanDoc) > 11 ? 'CNPJ' : 'CPF';
        }

        $firstName = $name !== '' ? $name : 'Cliente';
        $lastName = '';
        if (strpos($firstName, ' ') !== false) {
            $parts = preg_split('/\s+/', $firstName);
            $firstName = (string)array_shift($parts);
            $lastName = trim(implode(' ', $parts));
        }

        $payload = [
            'transaction_amount' => (float)$amount,
            'description' => $planName,
            'installments' => $installments,
            'token' => $token,
            'payer' => [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'identification' => [
                    'type' => $idType,
                    'number' => $cleanDoc,
                ],
                'address' => [
                    'zip_code' => '01001-000',
                    'street_name' => 'Rua Ficticia',
                    'street_number' => '123',
                    'neighborhood' => 'Centro',
                    'city' => 'São Paulo',
                    'federal_unit' => 'SP',
                ],
            ],
            'external_reference' => (string)$orderId,
            'metadata' => [
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'plan_id' => $planId,
                'customer' => $name,
                'company' => $company,
                'document' => $document,
                'document_type' => $documentType,
                'document_digits' => $cleanDoc,
                'cpf' => $documentType === 'cpf' ? $cleanDoc : '',
                'cnpj' => $documentType === 'cnpj' ? $cleanDoc : '',
                'phone' => $phone,
                'selected_method' => 'card',
            ],
        ];

        // Alguns cenários de tokenização permitem enviar payment_method_id/issuer_id
        if ($paymentMethodId !== '') {
            $payload['payment_method_id'] = $paymentMethodId;
        }
        if ($issuerId !== '') {
            $payload['issuer_id'] = $issuerId;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new Exception('Erro de comunicação com o Mercado Pago: ' . $err);
        }

        $json = json_decode($resp, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($json)) {
            throw new Exception('Erro ao criar pagamento no Mercado Pago. HTTP ' . $statusCode . '. Corpo: ' . $resp);
        }

        $txId = !empty($json['id']) ? (string)$json['id'] : '';
        $remoteStatus = strtolower((string)($json['status'] ?? ''));

        $isPaid = ($remoteStatus === 'approved');

        $pdo->beginTransaction();
        if ($txId !== '') {
            $stmtUpdTx = $pdo->prepare('UPDATE saas_orders SET transaction_id = :tx, updated_at = NOW() WHERE order_id = :order_id');
            $stmtUpdTx->execute([':tx' => $txId, ':order_id' => $orderId]);
        }

        if ($isPaid) {
            $stmtPaid = $pdo->prepare("UPDATE saas_orders SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE order_id = :order_id");
            $stmtPaid->execute([':order_id' => $orderId]);
            $activateTenantPlan($pdo, $tenantId, $planId);
        }

        $pdo->commit();

        if (!$isPaid) {
            echo json_encode([
                'success' => false,
                'message' => 'Pagamento não foi confirmado. Status: ' . $remoteStatus,
                'status' => $remoteStatus,
                'order_id' => $orderId,
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pagamento confirmado com sucesso!',
            'order_id' => $orderId,
            'status' => 'paid',
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Gateway não suportado.']);
    exit;

} catch (Throwable $e) {
    error_log('[process_card_modal_payment] Erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento: ' . $e->getMessage()]);
    exit;
}
