<?php
/**
 * process_card_payment.php
 * 
 * Endpoint para processar pagamento com cartão via Stripe API
 * Recebe os dados do cartão e processa o pagamento no backend.
 * 
 * NOTA: Em produção, use Stripe.js no frontend para PCI compliance.
 * Este método é para ambientes de desenvolvimento/teste.
 */

// Inicia sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Carrega o _init.php do ModernPOS para ter acesso a db(), root_url(), etc.
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

// Lê o body JSON
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$orderId = isset($body['order_id']) ? (int)$body['order_id'] : 0;
$card = isset($body['card']) && is_array($body['card']) ? $body['card'] : [];

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido não informado.']);
    exit;
}

// Validação básica do cartão
$cardNumber = isset($card['number']) ? preg_replace('/\D/', '', (string)$card['number']) : '';
$cardExpMonth = isset($card['exp_month']) ? (int)$card['exp_month'] : 0;
$cardExpYear = isset($card['exp_year']) ? (int)$card['exp_year'] : 0;
$cardCvc = isset($card['cvc']) ? preg_replace('/\D/', '', (string)$card['cvc']) : '';
$cardName = isset($card['name']) ? trim((string)$card['name']) : '';

if (strlen($cardNumber) < 13) {
    echo json_encode(['success' => false, 'message' => 'Número do cartão inválido.']);
    exit;
}

if ($cardExpMonth < 1 || $cardExpMonth > 12) {
    echo json_encode(['success' => false, 'message' => 'Mês de validade inválido.']);
    exit;
}

if ($cardExpYear < (int)date('Y')) {
    echo json_encode(['success' => false, 'message' => 'Ano de validade inválido.']);
    exit;
}

if (strlen($cardCvc) < 3) {
    echo json_encode(['success' => false, 'message' => 'CVV inválido.']);
    exit;
}

// Conecta ao banco
if (!function_exists('db')) {
    echo json_encode(['success' => false, 'message' => 'Função db() não disponível.']);
    exit;
}

$pdo = db();

try {
    // Busca o pedido
    $stmtOrder = $pdo->prepare(
        "SELECT order_id, tenant_id, plan_id, amount, status, transaction_id 
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

    // Segurança: garante que há usuário logado e que o pedido pertence ao tenant da sessão
    if (!function_exists('user_id') || !user_id()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
        exit;
    }
    $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($sessionTid > 0 && (int)$order['tenant_id'] !== $sessionTid) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado a este pedido.']);
        exit;
    }

    // Já foi pago?
    if ($order['status'] === 'paid' || $order['status'] === 'completed') {
        echo json_encode(['success' => true, 'message' => 'Pagamento já processado.', 'already_processed' => true]);
        exit;
    }

    // Busca configuração do Stripe (usa o tenant "dono" das configurações, via landing_pages)
    $tenantId = (int)$order['tenant_id'];

    if (!class_exists('PaymentGatewayConfig')) {
        echo json_encode(['success' => false, 'message' => 'PaymentGatewayConfig não disponível.']);
        exit;
    }

    $gatewayTenantId = 1;
    try {
        $stmtG = $pdo->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
        if ($stmtG) {
            $tmp = (int)$stmtG->fetchColumn();
            if ($tmp > 0) $gatewayTenantId = $tmp;
        }
    } catch (Throwable $eG) {
        $gatewayTenantId = 1;
    }

    $gw = PaymentGatewayConfig::get($pdo, $gatewayTenantId, 'stripe');
    if (!$gw || empty($gw['enabled']) || empty($gw['stripe_secret_key'])) {
        echo json_encode(['success' => false, 'message' => 'Stripe não configurado.']);
        exit;
    }

    $publishableKey = !empty($gw['stripe_publishable_key']) ? (string)$gw['stripe_publishable_key'] : '';
    $returnUrl = function_exists('root_url')
        ? (rtrim((string)root_url(), '/') . '/conta/planos?payment_confirmed=1')
        : null;

    // Carrega Stripe SDK
    $stripeAutoload = realpath(__DIR__ . '/../../../saas/Stripe/vendor/autoload.php');
    if (!$stripeAutoload || !file_exists($stripeAutoload)) {
        echo json_encode(['success' => false, 'message' => 'Stripe SDK não instalado.']);
        exit;
    }
    require_once $stripeAutoload;

    $secretKey = (string)$gw['stripe_secret_key'];
    \Stripe\Stripe::setApiKey($secretKey);

    // Cria PaymentMethod com os dados do cartão
    try {
        $paymentMethod = \Stripe\PaymentMethod::create([
            'type' => 'card',
            'card' => [
                'number' => $cardNumber,
                'exp_month' => $cardExpMonth,
                'exp_year' => $cardExpYear,
                'cvc' => $cardCvc,
            ],
            'billing_details' => [
                'name' => $cardName,
            ],
        ]);
    } catch (\Stripe\Exception\CardException $e) {
        echo json_encode(['success' => false, 'message' => 'Cartão recusado: ' . $e->getMessage()]);
        exit;
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        echo json_encode(['success' => false, 'message' => 'Dados do cartão inválidos: ' . $e->getMessage()]);
        exit;
    }

    // Busca o PaymentIntent existente ou cria um novo
    $paymentIntentId = $order['transaction_id'];
    $paymentIntent = null;

    if (!empty($paymentIntentId) && strpos($paymentIntentId, 'pi_') === 0) {
        // Atualiza o PaymentIntent existente com o PaymentMethod
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status === 'succeeded') {
                // Já foi pago
                $pdo->beginTransaction();
                $stmtUpdate = $pdo->prepare(
                    "UPDATE saas_orders SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE order_id = ?"
                );
                $stmtUpdate->execute([$orderId]);
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Pagamento já processado.']);
                exit;
            }
            
            // Confirma o pagamento
            $confirmParams = [
                'payment_method' => $paymentMethod->id,
            ];
            if (!empty($returnUrl)) {
                $confirmParams['return_url'] = $returnUrl;
            }
            $paymentIntent = \Stripe\PaymentIntent::confirm($paymentIntentId, $confirmParams);
            
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // PaymentIntent não encontrado, vamos criar um novo
            $paymentIntent = null;
        }
    }

    // Se não tem PaymentIntent válido, cria um novo e confirma
    if (!$paymentIntent) {
        $amountCents = (int)round((float)$order['amount'] * 100);
        $currency = !empty($gw['stripe_currency']) ? strtolower((string)$gw['stripe_currency']) : 'brl';

        $params = [
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_method' => $paymentMethod->id,
            'confirm' => true,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'order_id' => (string)$orderId,
                'tenant_id' => (string)$tenantId,
            ],
        ];
        if (!empty($returnUrl)) {
            $params['return_url'] = $returnUrl;
        }

        $paymentIntent = \Stripe\PaymentIntent::create($params);

        // Garante que o pedido fique vinculado ao PaymentIntent
        try {
            $stmtUpdTx = $pdo->prepare("UPDATE saas_orders SET transaction_id = :tx, updated_at = NOW() WHERE order_id = :order_id");
            $stmtUpdTx->execute([
                ':tx' => $paymentIntent->id,
                ':order_id' => $orderId,
            ]);
        } catch (Throwable $eTx) {
            // ignore
        }
    }

    // Verifica resultado
    if ($paymentIntent->status === 'succeeded') {
        // Pagamento aprovado! Atualiza o pedido
        $pdo->beginTransaction();
        
        try {
            $stmtUpdate = $pdo->prepare(
                "UPDATE saas_orders 
                 SET status = 'paid', 
                     transaction_id = :tx_id,
                     paid_at = NOW(),
                     updated_at = NOW()
                 WHERE order_id = :order_id"
            );
            $stmtUpdate->execute([
                ':tx_id' => $paymentIntent->id,
                ':order_id' => $orderId
            ]);

            // Ativa o plano para o tenant
            $planId = (int)$order['plan_id'];
            if ($planId > 0 && $tenantId > 0) {
                // Busca dados do plano
                $stmtPlan = $pdo->prepare("SELECT plan_id, duration_months FROM plans WHERE plan_id = ? LIMIT 1");
                $stmtPlan->execute([$planId]);
                $planData = $stmtPlan->fetch(PDO::FETCH_ASSOC);
                
                $durationMonths = isset($planData['duration_months']) ? (int)$planData['duration_months'] : 1;
                if ($durationMonths <= 0) {
                    $durationMonths = 1;
                }
                
                // Calcula data de expiração
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationMonths} months"));
                
                // Atualiza o tenant com o novo plano
                $stmtTenant = $pdo->prepare(
                    "UPDATE tenants 
                     SET plan_id = :plan_id,
                         subscription_status = 'active',
                         subscription_expires_at = :expires_at,
                         updated_at = NOW()
                     WHERE tenant_id = :tenant_id"
                );
                $stmtTenant->execute([
                    ':plan_id' => $planId,
                    ':expires_at' => $expiresAt,
                    ':tenant_id' => $tenantId
                ]);
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Pagamento aprovado!',
                'order_id' => $orderId,
                'payment_intent_id' => $paymentIntent->id,
                'status' => 'paid'
            ]);
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($paymentIntent->status === 'requires_action') {
        echo json_encode([
            'success' => false,
            'message' => 'Este cartão requer autenticação adicional (3D Secure).',
            'requires_action' => true,
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent->id,
            'client_secret' => $paymentIntent->client_secret,
            'publishable_key' => $publishableKey,
        ]);
        exit;
        
    } elseif ($paymentIntent->status === 'requires_payment_method') {
        echo json_encode([
            'success' => false, 
            'message' => 'Pagamento recusado. Verifique os dados do cartão ou tente outro cartão.'
        ]);
        exit;
        
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Status do pagamento: ' . $paymentIntent->status
        ]);
        exit;
    }

} catch (\Stripe\Exception\CardException $e) {
    echo json_encode(['success' => false, 'message' => 'Cartão recusado: ' . $e->getMessage()]);
    exit;
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('[process_card_payment] Stripe API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro na comunicação com o Stripe: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    error_log('[process_card_payment] Erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento: ' . $e->getMessage()]);
    exit;
}
