<?php
/**
 * AJAX: _inc/ai_token_purchase.php
 * Inicia o processo de compra de um pacote de tokens.
 */
ob_start();
session_start();
include('../_init.php');

header('Content-Type: application/json; charset=UTF-8');

require_once DIR_HELPER . 'ai_tokens.php';

if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
    exit;
}

$packageId = (int)($_POST['package_id'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'pix';

if ($packageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Pacote inválido.']);
    exit;
}

try {
    $pdo = db();
    
    // 1. Carregar pacote
    $stmt = $pdo->prepare("SELECT * FROM ai_token_packages WHERE package_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$packageId]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        throw new Exception("Pacote não encontrado.");
    }
    
    $amount = (float)$package['price'];
    $tokensQty = (int)$package['tokens_qty'];
    $referenceNo = "Compra de {$tokensQty} Tokens IA";
    
    $pdo->beginTransaction();
    
    // 2. Criar pedido em saas_orders (FASE T1 adicionou order_type)
    $stmtOrder = $pdo->prepare("
        INSERT INTO saas_orders 
            (tenant_id, reference_no, amount, payment_method, status, order_type, created_at)
        VALUES 
            (:tid, :ref, :amount, :method, 'pending', 'tokens', NOW())
    ");
    $stmtOrder->execute([
        ':tid'    => $tenantId,
        ':ref'    => $referenceNo,
        ':amount' => $amount,
        ':method' => $paymentMethod
    ]);
    $orderId = (int)$pdo->lastInsertId();
    
    // 3. Criar registro em ai_token_purchases
    $stmtPurchase = $pdo->prepare("
        INSERT INTO ai_token_purchases 
            (tenant_id, package_id, tokens_qty, amount_paid, payment_method, saas_order_id, status, created_at)
        VALUES 
            (:tid, :pkid, :qty, :amount, :method, :oid, 'pending', NOW())
    ");
    $stmtPurchase->execute([
        ':tid'    => $tenantId,
        ':pkid'   => $packageId,
        ':qty'    => $tokensQty,
        ':amount' => $amount,
        ':method' => $paymentMethod,
        ':oid'    => $orderId
    ]);
    $purchaseId = (int)$pdo->lastInsertId();
    
    $pdo->commit();
    
    // 4. Retornar URL de pagamento (página padrão do sistema)
    $redirectUrl = ROOT_URL . "conta/pagamento.php?order_id=" . $orderId;
    
    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'purchase_id'  => $purchaseId,
        'redirect_url' => $redirectUrl
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
