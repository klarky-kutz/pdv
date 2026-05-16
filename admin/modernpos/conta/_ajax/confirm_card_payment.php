<?php
/**
 * confirm_card_payment.php
 * 
 * Endpoint para confirmar o pagamento de cartão processado via Stripe.js
 * Este endpoint verifica o status do PaymentIntent e atualiza o pedido.
 * 
 * Os dados do cartão NUNCA passam por este servidor - são processados
 * diretamente pelo Stripe.js no frontend (PCI compliant).
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
$paymentIntentId = isset($body['payment_intent_id']) ? trim((string)$body['payment_intent_id']) : '';

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido não informado.']);
    exit;
}

if (empty($paymentIntentId) || strpos($paymentIntentId, 'pi_') !== 0) {
    echo json_encode(['success' => false, 'message' => 'ID do PaymentIntent inválido.']);
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

    // Verifica se o PaymentIntent corresponde ao pedido
    if (!empty($order['transaction_id']) && $order['transaction_id'] !== $paymentIntentId) {
        echo json_encode(['success' => false, 'message' => 'PaymentIntent não corresponde ao pedido.']);
        exit;
    }

    // Já foi processado?
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

    // Carrega Stripe SDK
    $stripeAutoload = realpath(__DIR__ . '/../../../saas/Stripe/vendor/autoload.php');
    if (!$stripeAutoload || !file_exists($stripeAutoload)) {
        echo json_encode(['success' => false, 'message' => 'Stripe SDK não instalado.']);
        exit;
    }
    require_once $stripeAutoload;

    $secretKey = (string)$gw['stripe_secret_key'];
    \Stripe\Stripe::setApiKey($secretKey);

    // Verifica o status do PaymentIntent no Stripe
    try {
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        echo json_encode(['success' => false, 'message' => 'PaymentIntent não encontrado no Stripe.']);
        exit;
    }

    if ($paymentIntent->status !== 'succeeded') {
        echo json_encode([
            'success' => false, 
            'message' => 'Pagamento não foi confirmado. Status: ' . $paymentIntent->status
        ]);
        exit;
    }

    // Atualiza o pedido para "paid"
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
            ':tx_id' => $paymentIntentId,
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
            'message' => 'Pagamento confirmado com sucesso!',
            'order_id' => $orderId,
            'status' => 'paid'
        ]);
        exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Throwable $e) {
    error_log('[confirm_card_payment] Erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao confirmar pagamento: ' . $e->getMessage()]);
    exit;
}
