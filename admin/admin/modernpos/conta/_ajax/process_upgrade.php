<?php
/**
 * API: Processar Upgrade / Checkout Interno
 *
 * POST /conta/_ajax/process_upgrade.php
 * Body (JSON):
 * {
 *   "plan_id": 2,
 *   "billing": "monthly" | "yearly",
 *   "payment_method": "card" | "pix" | "boleto",
 *   "customer": {
 *     "name": "...",
 *     "email": "...",
 *     "document": "...",
 *     "person_type": "cpf" | "cnpj",
 *     "phone": "...",
 *     "company": "..."
 *   }
 * }
 *
 * Response:
 * { "success": true, "order_id": 123, "redirect_url": "..." }
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

$initPath = dirname(__DIR__) . '/../_init.php';
if (!file_exists($initPath)) {
    echo json_encode(['success' => false, 'message' => 'Sistema não configurado corretamente.']);
    exit;
}

require_once $initPath;

if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Util: lê JSON (fallback para $_POST)
$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) {
        $input = $tmp;
    }
}
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

$planId = isset($input['plan_id']) ? (int)$input['plan_id'] : 0;
$billing = isset($input['billing']) ? strtolower(trim((string)$input['billing'])) : 'monthly';
$paymentMethod = isset($input['payment_method']) ? strtolower(trim((string)$input['payment_method'])) : 'card';

$customer = isset($input['customer']) && is_array($input['customer']) ? $input['customer'] : [];
$name = trim((string)($customer['name'] ?? ''));
$email = trim((string)($customer['email'] ?? ''));
$document = trim((string)($customer['document'] ?? ''));
$personTypeRaw = strtolower(trim((string)($customer['person_type'] ?? '')));
$phone = trim((string)($customer['phone'] ?? ''));
$company = trim((string)($customer['company'] ?? ''));

$documentDigits = preg_replace('/\D+/', '', $document);
$documentType = in_array($personTypeRaw, ['cpf', 'cnpj'], true) ? $personTypeRaw : '';
if ($documentType === '' && $documentDigits !== '') {
    $documentType = strlen($documentDigits) > 11 ? 'cnpj' : 'cpf';
}

$validBilling = ['monthly', 'yearly'];
if (!in_array($billing, $validBilling, true)) {
    $billing = 'monthly';
}

$validMethods = ['card', 'pix', 'boleto'];
if (!in_array($paymentMethod, $validMethods, true)) {
    $paymentMethod = 'card';
}

// Helpers
function finx_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function finx_find_gateway_tenant_id(PDO $pdo): int
{
    $tenantId = 1;
    try {
        $stmt = $pdo->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
        if ($stmt) {
            $tmp = (int)$stmt->fetchColumn();
            if ($tmp > 0) {
                $tenantId = $tmp;
            }
        }
    } catch (Throwable $e) {
        // fallback
    }
    return $tenantId;
}

function finx_mark_order_failed(PDO $pdo, int $orderId): void
{
    try {
        $st = $pdo->prepare("UPDATE saas_orders SET status = 'failed', updated_at = NOW() WHERE order_id = :id");
        $st->bindValue(':id', $orderId, PDO::PARAM_INT);
        $st->execute();
    } catch (Throwable $e) {
        // ignore
    }
}

try {
    $pdo = db();

    // Resolve tenant do usuário logado
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($tenantId <= 0) {
        $userId = (int)user_id();
        $stmtUser = $pdo->prepare("SELECT tenant_id, username, email, mobile, cpf FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userRow) {
            $tenantId = (int)($userRow['tenant_id'] ?? 0);
            // Prefill se não veio do form
            if ($name === '' && !empty($userRow['username'])) $name = (string)$userRow['username'];
            if ($email === '' && !empty($userRow['email'])) $email = (string)$userRow['email'];
            if ($phone === '' && !empty($userRow['mobile'])) $phone = (string)$userRow['mobile'];
            if ($documentDigits === '' && !empty($userRow['cpf'])) {
                $documentDigits = preg_replace('/\D+/', '', (string)$userRow['cpf']);
                $document = $documentDigits;
                if ($documentType === '' && $documentDigits !== '') {
                    $documentType = strlen($documentDigits) > 11 ? 'cnpj' : 'cpf';
                }
            }
        }
    }

    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
        exit;
    }

    if ($planId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Plano inválido.']);
        exit;
    }

    // Carrega plano (compat: algumas instalações não possuem price_yearly ou is_active)
    $hasPriceYearly = false;
    $hasIsActive = true;

    try {
        $stCol = $pdo->query("SHOW COLUMNS FROM plans LIKE 'price_yearly'");
        $hasPriceYearly = $stCol && $stCol->rowCount() > 0;
    } catch (Throwable $eCol) {
        $hasPriceYearly = false;
    }

    try {
        $stCol = $pdo->query("SHOW COLUMNS FROM plans LIKE 'is_active'");
        $hasIsActive = $stCol && $stCol->rowCount() > 0;
    } catch (Throwable $eCol) {
        $hasIsActive = false;
    }

    $sqlPlan = 'SELECT plan_id, name, price_monthly'
        . ($hasPriceYearly ? ', price_yearly' : ', NULL AS price_yearly')
        . ($hasIsActive ? ', is_active' : ', 1 AS is_active')
        . ' FROM plans WHERE plan_id = ? LIMIT 1';

    $stmtPlan = $pdo->prepare($sqlPlan);
    $stmtPlan->execute([$planId]);
    $planRow = $stmtPlan->fetch(PDO::FETCH_ASSOC);

    if (!$planRow || (int)($planRow['is_active'] ?? 1) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Plano não encontrado ou inativo.']);
        exit;
    }

    $planName = (string)($planRow['name'] ?? 'Plano');
    $priceMonthly = (float)($planRow['price_monthly'] ?? 0);
    $priceYearly = isset($planRow['price_yearly']) && $planRow['price_yearly'] !== null && $planRow['price_yearly'] !== ''
        ? (float)$planRow['price_yearly']
        : null;

    // Mesma regra do front: se não houver price_yearly, usa 10x (2 meses grátis)
    $amount = $billing === 'yearly'
        ? (float)($priceYearly !== null ? $priceYearly : ($priceMonthly * 10))
        : (float)$priceMonthly;

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Este plano não requer pagamento.']);
        exit;
    }

    $amountCents = (int)round($amount * 100);

    // Resolve tenant "dono" das configs de gateway (mesma lógica da landing)
    $gatewayTenantId = finx_find_gateway_tenant_id($pdo);

    // Carrega PaymentGatewayConfig
    $pgcPath = realpath(__DIR__ . '/../_inc/PaymentGatewayConfig.php');
    if (!$pgcPath || !file_exists($pgcPath)) {
        throw new Exception('PaymentGatewayConfig não encontrado.');
    }
    require_once $pgcPath;

    // Resolve gateway default pelo tipo
    $gw = null;
    if ($paymentMethod === 'card') {
        $gw = PaymentGatewayConfig::getDefaultCardGateway($pdo, $gatewayTenantId);
    } elseif ($paymentMethod === 'pix') {
        $gw = PaymentGatewayConfig::getDefaultPixGateway($pdo, $gatewayTenantId);
    } elseif ($paymentMethod === 'boleto') {
        $gw = PaymentGatewayConfig::getDefaultBoletoGateway($pdo, $gatewayTenantId);
    }

    // Fallback adicional: se o tenant não tem colunas de default_*_gateway ou não configurou,
    // tentamos encontrar o primeiro gateway habilitado compatível.
    if (!$gw || empty($gw['enabled'])) {
        $candidates = [];
        if ($paymentMethod === 'card') {
            $candidates = ['stripe', 'asaas', 'mercado_pago'];
        } elseif ($paymentMethod === 'pix') {
            // PIX Manual tem prioridade quando habilitado (não depende de API externa)
            $candidates = ['pix_manual', 'asaas', 'mercado_pago'];
        } elseif ($paymentMethod === 'boleto') {
            $candidates = ['asaas', 'mercado_pago'];
        }

        foreach ($candidates as $cand) {
            $try = PaymentGatewayConfig::get($pdo, $gatewayTenantId, $cand);
            if ($try && !empty($try['enabled'])) {
                $gw = $try;
                break;
            }
        }
    }

    if (!$gw || empty($gw['enabled'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Gateway de pagamento não configurado. Verifique as configurações em saas_payment_gateways (tenant_id=' . $gatewayTenantId . ').'
        ]);
        exit;
    }

    $gatewayCode = (string)($gw['gateway_code'] ?? '');

    // Limpeza best-effort: marca tentativas pendentes antigas sem transaction_id como failed
    try {
        $stmtCleanup = $pdo->prepare(
            "UPDATE saas_orders
                SET status = 'failed', updated_at = NOW()
              WHERE tenant_id = :tenant_id
                AND status = 'pending'
                AND (transaction_id IS NULL OR transaction_id = '')"
        );
        $stmtCleanup->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmtCleanup->execute();
    } catch (Throwable $eClean) {
        // ignore
    }

    $orderId = 0;
    $base = finx_base_url();
    $saasBase = rtrim($base, '/') . '/saas';

    // ============
    // PIX MANUAL
    // ============
    if ($gatewayCode === 'pix_manual') {
        if ($paymentMethod !== 'pix') {
            echo json_encode(['success' => false, 'message' => 'Gateway Pix manual disponível apenas para Pix.']);
            exit;
        }

        $referenceNo = substr('Pix · ' . $planName . ($billing === 'yearly' ? ' (anual)' : ' (mensal)'), 0, 50);
        $dueDate = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        $stmtOrder = $pdo->prepare(
            "INSERT INTO saas_orders
                (tenant_id, plan_id, reference_no, amount, payment_method, status, due_date, created_at)
             VALUES
                (:tenant_id, :plan_id, :reference_no, :amount, 'pix', 'pending', :due_date, NOW())"
        );
        $stmtOrder->execute([
            ':tenant_id' => $tenantId,
            ':plan_id' => $planId,
            ':reference_no' => $referenceNo,
            ':amount' => $amount,
            ':due_date' => $dueDate,
        ]);
        $orderId = (int)$pdo->lastInsertId();
        $pdo->commit();

        $redirectUrl = root_url() . 'conta/pagamento.php?order_id=' . $orderId . '&gateway=pix_manual';
        echo json_encode(['success' => true, 'order_id' => $orderId, 'redirect_url' => $redirectUrl]);
        exit;
    }

    // ============
    // ASAAS (PIX/BOLETO)
    // ============
    if ($gatewayCode === 'asaas') {
        if (empty($gw['asaas_api_key'])) {
            echo json_encode(['success' => false, 'message' => 'Asaas não configurado.']);
            exit;
        }

        if (!in_array($paymentMethod, ['pix', 'boleto', 'card'], true)) {
            echo json_encode(['success' => false, 'message' => 'Método de pagamento não suportado no Asaas neste checkout.']);
            exit;
        }

        $apiKey = (string)$gw['asaas_api_key'];
        $environment = !empty($gw['environment']) ? (string)$gw['environment'] : 'sandbox';
        $baseUrl = $environment === 'production' ? 'https://api.asaas.com/v3' : 'https://sandbox.asaas.com/api/v3';

        $referenceNo = substr('Asaas · ' . $planName . ($billing === 'yearly' ? ' (anual)' : ' (mensal)'), 0, 50);
        $dueDate = date('Y-m-d H:i:s');

        // Cartão (checkout transparente via modal 3D):
        // Aqui criamos apenas o pedido local. O pagamento (API Asaas) é processado
        // em uma segunda etapa via /conta/_ajax/process_card_modal_payment.php.
        if ($paymentMethod === 'card') {
            try {
                $pdo->beginTransaction();
                $stmtOrder = $pdo->prepare(
                    "INSERT INTO saas_orders
                        (tenant_id, plan_id, reference_no, amount, payment_method, status, due_date, created_at)
                     VALUES
                        (:tenant_id, :plan_id, :reference_no, :amount, 'card', 'pending', :due_date, NOW())"
                );
                $stmtOrder->execute([
                    ':tenant_id' => $tenantId,
                    ':plan_id' => $planId,
                    ':reference_no' => $referenceNo,
                    ':amount' => $amount,
                    ':due_date' => $dueDate,
                ]);
                $orderId = (int)$pdo->lastInsertId();
                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'order_id' => $orderId,
                    'gateway_code' => 'asaas',
                    'flow_mode' => 'modal',
                    'plan_name' => $planName,
                    'amount' => $amount,
                ]);
                exit;
            } catch (Throwable $eCard) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Erro ao iniciar checkout Asaas (cartão): ' . $eCard->getMessage()]);
                exit;
            }
        }

        try {
            $pdo->beginTransaction();

            $stmtOrder = $pdo->prepare(
                "INSERT INTO saas_orders
                    (tenant_id, plan_id, reference_no, amount, payment_method, status, due_date, created_at)
                 VALUES
                    (:tenant_id, :plan_id, :reference_no, :amount, :payment_method, 'pending', :due_date, NOW())"
            );
            $stmtOrder->execute([
                ':tenant_id' => $tenantId,
                ':plan_id' => $planId,
                ':reference_no' => $referenceNo,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':due_date' => $dueDate,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Cria cliente no Asaas
            $customerPayload = [
                'name' => $name !== '' ? $name : ($company !== '' ? $company : 'Cliente'),
                'email' => $email !== '' ? $email : null,
                'cpfCnpj' => $documentDigits !== '' ? $documentDigits : null,
                'mobilePhone' => $phone !== '' ? $phone : null,
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

            $billingType = $paymentMethod === 'pix' ? 'PIX' : 'BOLETO';

            $paymentPayload = [
                'customer' => $customerId,
                'billingType' => $billingType,
                'value' => $amountCents / 100,
                'dueDate' => date('Y-m-d'),
                'description' => $planName,
                'externalReference' => (string)$orderId,
            ];

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

            // transaction_id
            $txId = (string)$paymentJson['id'];
            $stmtUpd = $pdo->prepare("UPDATE saas_orders SET transaction_id = :tx WHERE order_id = :order_id");
            $stmtUpd->execute([
                ':tx' => $txId,
                ':order_id' => $orderId,
            ]);

            $pdo->commit();

            $redirectUrl = root_url() . 'conta/pagamento.php?order_id=' . $orderId . '&gateway=asaas';

            echo json_encode(['success' => true, 'order_id' => $orderId, 'redirect_url' => $redirectUrl]);
            exit;
        } catch (Throwable $eAsaas) {
            if ($pdo->inTransaction()) {
                if (!empty($orderId)) {
                    finx_mark_order_failed($pdo, $orderId);
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            }
            echo json_encode(['success' => false, 'message' => $eAsaas->getMessage()]);
            exit;
        }
    }

    // ============
    // MERCADO PAGO (PIX/BOLETO)
    // ============
    if ($gatewayCode === 'mercado_pago') {
        if (empty($gw['mp_access_token'])) {
            echo json_encode(['success' => false, 'message' => 'Mercado Pago não configurado.']);
            exit;
        }

        if (!in_array($paymentMethod, ['pix', 'boleto', 'card'], true)) {
            echo json_encode(['success' => false, 'message' => 'Método de pagamento não suportado no Mercado Pago neste checkout.']);
            exit;
        }

        $accessToken = (string)$gw['mp_access_token'];

        $referenceNo = substr('MP · ' . $planName . ($billing === 'yearly' ? ' (anual)' : ' (mensal)'), 0, 50);
        $dueDate = date('Y-m-d H:i:s');

        // Cartão (checkout transparente via modal 3D):
        // Criamos o pedido local e devolvemos a chave pública para tokenização no frontend.
        if ($paymentMethod === 'card') {
            $publicKey = !empty($gw['mp_public_key']) ? (string)$gw['mp_public_key'] : '';
            if ($publicKey === '') {
                echo json_encode(['success' => false, 'message' => 'Mercado Pago não configurado (mp_public_key ausente).']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                $stmtOrder = $pdo->prepare(
                    "INSERT INTO saas_orders
                        (tenant_id, plan_id, reference_no, amount, payment_method, status, due_date, created_at)
                     VALUES
                        (:tenant_id, :plan_id, :reference_no, :amount, 'card', 'pending', :due_date, NOW())"
                );
                $stmtOrder->execute([
                    ':tenant_id' => $tenantId,
                    ':plan_id' => $planId,
                    ':reference_no' => $referenceNo,
                    ':amount' => $amount,
                    ':due_date' => $dueDate,
                ]);
                $orderId = (int)$pdo->lastInsertId();
                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'order_id' => $orderId,
                    'gateway_code' => 'mercado_pago',
                    'flow_mode' => 'modal',
                    'plan_name' => $planName,
                    'amount' => $amount,
                    'mp_public_key' => $publicKey,
                ]);
                exit;
            } catch (Throwable $eCard) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'message' => 'Erro ao iniciar checkout Mercado Pago (cartão): ' . $eCard->getMessage()]);
                exit;
            }
        }

        try {
            $pdo->beginTransaction();

            $stmtOrder = $pdo->prepare(
                "INSERT INTO saas_orders
                    (tenant_id, plan_id, reference_no, amount, payment_method, status, due_date, created_at)
                 VALUES
                    (:tenant_id, :plan_id, :reference_no, :amount, :payment_method, 'pending', :due_date, NOW())"
            );
            $stmtOrder->execute([
                ':tenant_id' => $tenantId,
                ':plan_id' => $planId,
                ':reference_no' => $referenceNo,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':due_date' => $dueDate,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $cleanDoc = preg_replace('/\D+/', '', $document);
            $idType = 'CPF';
            if ($documentType === 'cnpj') {
                $idType = 'CNPJ';
            } elseif ($documentType === 'cpf') {
                $idType = 'CPF';
            } elseif ($cleanDoc !== '') {
                $idType = strlen($cleanDoc) > 11 ? 'CNPJ' : 'CPF';
            }

            // Mercado Pago exige first_name e last_name
            $firstName = $name !== '' ? $name : 'Cliente';
            $lastName = '';
            if (strpos($firstName, ' ') !== false) {
                $parts = preg_split('/\s+/', $firstName);
                $firstName = (string)array_shift($parts);
                $lastName = trim(implode(' ', $parts));
            }

            $paymentMethodId = $paymentMethod === 'pix' ? 'pix' : 'bolbradesco';

            $payload = [
                'transaction_amount' => $amountCents / 100,
                'description' => $planName,
                'installments' => 1,
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
                'payment_method_id' => $paymentMethodId,
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
                    'selected_method' => $paymentMethod,
                ],
            ];

            // notification_url só se o host não for localhost/127.0.0.1
            $hostNot = strtolower(parse_url($base, PHP_URL_HOST) ?: '');
            $isLocal = in_array($hostNot, ['localhost', '127.0.0.1'], true);
            if (!$isLocal && $hostNot !== '') {
                $payload['notification_url'] = $saasBase . '/webhooks/mercadopago.php';
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
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false) {
                throw new Exception('Erro de comunicação com o Mercado Pago: ' . $err);
            }

            $json = json_decode($resp, true);
            if ($status < 200 || $status >= 300 || !is_array($json)) {
                throw new Exception('Erro ao criar pagamento no Mercado Pago. HTTP ' . $status . '. Corpo: ' . $resp);
            }

            if (!empty($json['id'])) {
                $stmtUpd = $pdo->prepare("UPDATE saas_orders SET transaction_id = :tx WHERE order_id = :order_id");
                $stmtUpd->execute([
                    ':tx' => (string)$json['id'],
                    ':order_id' => $orderId,
                ]);
            }

            $pdo->commit();

            $redirectUrl = root_url() . 'conta/pagamento.php?order_id=' . $orderId . '&gateway=mercadopago';

            echo json_encode(['success' => true, 'order_id' => $orderId, 'redirect_url' => $redirectUrl]);
            exit;
        } catch (Throwable $eMp) {
            if ($pdo->inTransaction()) {
                if (!empty($orderId)) {
                    finx_mark_order_failed($pdo, $orderId);
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            }
            echo json_encode(['success' => false, 'message' => $eMp->getMessage()]);
            exit;
        }
    }

    // ============
    // STRIPE (CARD)
    // ============
    if ($gatewayCode === 'stripe') {
        if ($paymentMethod !== 'card') {
            echo json_encode(['success' => false, 'message' => 'Stripe disponível apenas para cartão.']);
            exit;
        }

        if (empty($gw['stripe_secret_key'])) {
            echo json_encode(['success' => false, 'message' => 'Stripe não configurado.']);
            exit;
        }

        $stripeAutoload = realpath(__DIR__ . '/../../../saas/Stripe/vendor/autoload.php');
        if (!$stripeAutoload || !file_exists($stripeAutoload)) {
            echo json_encode(['success' => false, 'message' => 'Stripe não está instalado neste ambiente.']);
            exit;
        }
        require_once $stripeAutoload;

        $secretKey = (string)$gw['stripe_secret_key'];
        $publishableKey = !empty($gw['stripe_publishable_key']) ? (string)$gw['stripe_publishable_key'] : '';
        $currency = !empty($gw['stripe_currency']) ? (string)$gw['stripe_currency'] : 'BRL';

        // Fluxo visual: checkout (redireciona para Stripe) ou transparent (formulário embutido)
        $flowMode = !empty($gw['stripe_flow_mode']) ? (string)$gw['stripe_flow_mode'] : 'checkout';
        if (!in_array($flowMode, ['checkout', 'transparent'], true)) {
            $flowMode = 'checkout';
        }
        $isTransparentMode = ($flowMode === 'transparent');

        // Tipo de cobrança: one_off (pagamento único) ou subscription (recorrente)
        $billingMode = !empty($gw['stripe_billing_mode']) ? (string)$gw['stripe_billing_mode'] : 'one_off';
        if (!in_array($billingMode, ['one_off', 'subscription'], true)) {
            $billingMode = 'one_off';
        }
        $isSubscriptionMode = ($billingMode === 'subscription');

        $referenceNo = substr('Stripe · ' . $planName . ($billing === 'yearly' ? ' (anual)' : ' (mensal)'), 0, 50);
        $dueDate = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            $stmtOrder = $pdo->prepare(
                "INSERT INTO saas_orders
                    (tenant_id, plan_id, reference_no, amount, payment_method, status, due_date, created_at)
                 VALUES
                    (:tenant_id, :plan_id, :reference_no, :amount, 'card', 'pending', :due_date, NOW())"
            );
            $stmtOrder->execute([
                ':tenant_id' => $tenantId,
                ':plan_id' => $planId,
                ':reference_no' => $referenceNo,
                ':amount' => $amount,
                ':due_date' => $dueDate,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            \Stripe\Stripe::setApiKey($secretKey);

            $metadata = [
                'tenant_id' => (string)$tenantId,
                'order_id' => (string)$orderId,
                'plan_id' => (string)$planId,
                'customer' => $name,
                'company' => $company,
                'document' => $document,
                'document_type' => $documentType,
                'document_digits' => $documentDigits,
                'cpf' => $documentType === 'cpf' ? $documentDigits : '',
                'cnpj' => $documentType === 'cnpj' ? $documentDigits : '',
                'phone' => $phone,
                'billing' => $billing,
            ];

            // ============================================
            // CHECKOUT TRANSPARENTE (Stripe Elements)
            // ============================================
            if ($isTransparentMode && !$isSubscriptionMode) {
                // Cria PaymentIntent para checkout transparente (apenas pagamento único)
                $paymentIntent = \Stripe\PaymentIntent::create([
                    'amount' => $amountCents,
                    'currency' => strtolower($currency),
                    'description' => $planName,
                    'metadata' => $metadata,
                    'automatic_payment_methods' => [
                        'enabled' => true,
                    ],
                ]);

                // Salva o PaymentIntent ID como transaction_id
                $stmtUpd = $pdo->prepare("UPDATE saas_orders SET transaction_id = :tx WHERE order_id = :order_id");
                $stmtUpd->execute([
                    ':tx' => $paymentIntent->id,
                    ':order_id' => $orderId,
                ]);

                $pdo->commit();

                // Retorna dados para o checkout transparente
                echo json_encode([
                    'success' => true,
                    'order_id' => $orderId,
                    'flow_mode' => 'transparent',
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => $publishableKey,
                    'redirect_url' => root_url() . 'conta/pagamento_stripe.php?order_id=' . $orderId,
                    'plan_name' => $planName,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);
                exit;
            }

            // ============================================
            // CHECKOUT STRIPE (Redireciona para página Stripe)
            // ============================================
            $successUrl = root_url() . 'conta/pagamento.php?gateway=stripe&order_id=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl  = root_url() . 'conta/planos?checkout_cancel=1';

            $priceData = [
                'currency' => strtolower($currency),
                'unit_amount' => $amountCents,
                'product_data' => [
                    'name' => $planName,
                    'description' => $planName,
                ],
            ];

            $interval = ($billing === 'yearly') ? 'year' : 'month';

            $sessionParams = [
                'mode' => $isSubscriptionMode ? 'subscription' : 'payment',
                'payment_method_types' => ['card'],
                'customer_email' => $email !== '' ? $email : null,
                'metadata' => $metadata,
                'line_items' => [[
                    'price_data' => $isSubscriptionMode
                        ? $priceData + ['recurring' => ['interval' => $interval, 'interval_count' => 1]]
                        : $priceData,
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ];

            if (!$isSubscriptionMode) {
                $sessionParams['payment_intent_data'] = [
                    'metadata' => $metadata,
                ];
            } else {
                $sessionParams['subscription_data'] = [
                    'metadata' => $metadata,
                ];
            }

            $session = \Stripe\Checkout\Session::create($sessionParams);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'flow_mode' => 'checkout',
                'redirect_url' => $session->url,
            ]);
            exit;
        } catch (Throwable $eStripe) {
            if ($pdo->inTransaction()) {
                if (!empty($orderId)) {
                    finx_mark_order_failed($pdo, $orderId);
                    $pdo->commit();
                } else {
                    $pdo->rollBack();
                }
            }
            echo json_encode(['success' => false, 'message' => 'Erro ao iniciar checkout Stripe: ' . $eStripe->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Gateway não suportado: ' . $gatewayCode]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao iniciar checkout: ' . $e->getMessage()]);
    exit;
}
