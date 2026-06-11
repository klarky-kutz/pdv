<?php
/**
 * API: Histórico de Cobranças
 * 
 * Retorna histórico de faturas/pedidos do tenant logado.
 * 
 * GET /conta/_ajax/billing_history.php?page=1&limit=10
 * 
 * Response:
 * {
 *   "success": true,
 *   "orders": [...],
 *   "pagination": {...},
 *   "summary": {...}
 * }
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// Caminho para o _init.php do modernpos
$initPath = dirname(__DIR__) . '/../_init.php';
if (!file_exists($initPath)) {
    echo json_encode(['success' => false, 'message' => 'Sistema não configurado corretamente.']);
    exit;
}

require_once $initPath;

// Verifica se o usuário está logado
if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

try {
    $pdo = db();
    
    // Obtém o tenant_id do usuário logado
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    if ($tenantId <= 0) {
        $userId = user_id();
        $stmtUser = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $tenantId = $userRow ? (int)$userRow['tenant_id'] : 0;
    }
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tenant não identificado.']);
        exit;
    }
    
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(5, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    
    // Filtro por status (opcional)
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $validStatuses = ['paid', 'pending', 'failed', 'refunded', 'cancelled'];
    
    // Conta total de registros
    $countSql = "SELECT COUNT(*) FROM saas_orders WHERE tenant_id = ?";
    $countParams = [$tenantId];
    
    if ($statusFilter && in_array($statusFilter, $validStatuses)) {
        $countSql .= " AND status = ?";
        $countParams[] = $statusFilter;
    }
    
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $totalRecords = (int)$stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    // Busca os pedidos com paginação
    $ordersSql = "
        SELECT 
            o.order_id,
            o.plan_id,
            o.reference_no,
            o.amount,
            o.payment_method,
            o.status,
            o.transaction_id,
            o.due_date,
            o.paid_at,
            o.created_at,
            o.updated_at,
            p.name as plan_name
        FROM saas_orders o
        LEFT JOIN plans p ON o.plan_id = p.plan_id
        WHERE o.tenant_id = ?
    ";
    $orderParams = [$tenantId];
    
    if ($statusFilter && in_array($statusFilter, $validStatuses)) {
        $ordersSql .= " AND o.status = ?";
        $orderParams[] = $statusFilter;
    }
    
    $ordersSql .= " ORDER BY o.created_at DESC, o.order_id DESC LIMIT ? OFFSET ?";
    $orderParams[] = $limit;
    $orderParams[] = $offset;
    
    $stmtOrders = $pdo->prepare($ordersSql);
    $stmtOrders->execute($orderParams);
    $ordersRaw = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);
    
    // Labels de status
    $statusLabels = [
        'paid' => ['label' => 'Pago', 'class' => 'success'],
        'pending' => ['label' => 'Pendente', 'class' => 'warning'],
        'failed' => ['label' => 'Falhou', 'class' => 'danger'],
        'refunded' => ['label' => 'Estornado', 'class' => 'info'],
        'cancelled' => ['label' => 'Cancelado', 'class' => 'secondary'],
    ];
    
    // Labels de método de pagamento
    $methodLabels = [
        'card' => 'Cartão de Crédito',
        'credit_card' => 'Cartão de Crédito',
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'stripe' => 'Stripe',
        '' => 'Não informado',
    ];
    
    // Processa os pedidos
    $orders = [];
    foreach ($ordersRaw as $order) {
        $status = $order['status'] ?? 'pending';
        $method = strtolower($order['payment_method'] ?? '');
        
        // Formata datas
        $createdAt = $order['created_at'] ? (new DateTime($order['created_at']))->format('d/m/Y H:i') : null;
        $dueDate = $order['due_date'] ? (new DateTime($order['due_date']))->format('d/m/Y') : null;
        $paidAt = $order['paid_at'] ? (new DateTime($order['paid_at']))->format('d/m/Y H:i') : null;
        
        $orders[] = [
            'order_id' => (int)$order['order_id'],
            'reference' => $order['reference_no'],
            'plan_id' => (int)$order['plan_id'],
            'plan_name' => $order['plan_name'] ?? 'Plano não identificado',
            'amount' => (float)$order['amount'],
            'amount_formatted' => 'R$ ' . number_format((float)$order['amount'], 2, ',', '.'),
            'payment_method' => $method,
            'payment_method_label' => $methodLabels[$method] ?? ucfirst($method),
            'status' => $status,
            'status_label' => $statusLabels[$status]['label'] ?? ucfirst($status),
            'status_class' => $statusLabels[$status]['class'] ?? 'secondary',
            'transaction_id' => $order['transaction_id'],
            'due_date' => $order['due_date'],
            'due_date_formatted' => $dueDate,
            'paid_at' => $order['paid_at'],
            'paid_at_formatted' => $paidAt,
            'created_at' => $order['created_at'],
            'created_at_formatted' => $createdAt,
            'can_pay' => ($status === 'pending'),
            'can_refund' => ($status === 'paid'),
        ];
    }
    
    // Calcula sumário
    $stmtSummary = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as total_pending,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as count_paid,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as count_pending,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as count_failed
        FROM saas_orders
        WHERE tenant_id = ?
    ");
    $stmtSummary->execute([$tenantId]);
    $summaryRow = $stmtSummary->fetch(PDO::FETCH_ASSOC);
    
    // Busca o último valor pago
    $stmtLastPaid = $pdo->prepare("
        SELECT amount 
        FROM saas_orders 
        WHERE tenant_id = ? AND status = 'paid' 
        ORDER BY paid_at DESC, order_id DESC 
        LIMIT 1
    ");
    $stmtLastPaid->execute([$tenantId]);
    $lastPaidRow = $stmtLastPaid->fetch(PDO::FETCH_ASSOC);
    $lastPaidAmount = $lastPaidRow ? (float)$lastPaidRow['amount'] : 0;
    
    // Monta resposta
    $response = [
        'success' => true,
        'payments' => $orders, // Compatível com o JS (espera 'payments')
        'orders' => $orders,   // Mantém para compatibilidade
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ],
        'summary' => [
            'total_paid' => (float)$summaryRow['total_paid'],
            'total_paid_formatted' => 'R$ ' . number_format((float)$summaryRow['total_paid'], 2, ',', '.'),
            'last_paid' => $lastPaidAmount,
            'last_paid_formatted' => 'R$ ' . number_format($lastPaidAmount, 2, ',', '.'),
            'total_pending' => (float)$summaryRow['total_pending'],
            'total_pending_formatted' => 'R$ ' . number_format((float)$summaryRow['total_pending'], 2, ',', '.'),
            'count_paid' => (int)$summaryRow['count_paid'],
            'count_pending' => (int)$summaryRow['count_pending'],
            'count_failed' => (int)$summaryRow['count_failed'],
        ],
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar histórico: ' . $e->getMessage()
    ]);
}
