<?php
/**
 * ModernPOS - Conta do Cliente
 * AJAX: Criar ticket de contestação de cobrança
 * 
 * Este endpoint cria automaticamente um ticket de suporte na categoria
 * "Financeiro / Cobrança" para contestações de pagamentos.
 */

// Suprime erros/warnings que podem corromper o JSON
error_reporting(0);
ini_set('display_errors', 0);

@session_start();
require_once(__DIR__ . '/../../_init.php');

header('Content-Type: application/json; charset=utf-8');

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'msg' => 'Método inválido.']);
        exit;
    }
    
    // Verifica autenticação
    if (!$user->isLogged()) {
        echo json_encode(['success' => false, 'msg' => 'Usuário não autenticado.']);
        exit;
    }
    
    // Obtém dados do usuário/tenant
    $userId = (int)user_id();
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Tenant não identificado.']);
        exit;
    }
    
    // Obtém dados do formulário (suporta JSON e form-data)
    $input = $_POST;
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $jsonInput = json_decode($rawInput, true);
        if (is_array($jsonInput)) {
            $input = array_merge($input, $jsonInput);
        }
    }
    
    $orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $orderRef = isset($input['order_ref']) ? trim((string)$input['order_ref']) : '';
    $contestType = isset($input['contest_type']) ? trim((string)$input['contest_type']) : '';
    $title = isset($input['title']) ? trim((string)$input['title']) : '';
    $description = isset($input['description']) ? trim((string)$input['description']) : '';
    
    // Validações
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'msg' => 'ID do pedido inválido.']);
        exit;
    }
    
    if ($contestType === '') {
        echo json_encode(['success' => false, 'msg' => 'Selecione o tipo de contestação.']);
        exit;
    }
    
    if ($title === '') {
        echo json_encode(['success' => false, 'msg' => 'Informe o título da contestação.']);
        exit;
    }
    
    if ($description === '') {
        echo json_encode(['success' => false, 'msg' => 'Descreva o motivo da contestação.']);
        exit;
    }
    
    $pdo = db();
    
    // Verifica se o pedido pertence ao tenant
    // Primeiro tenta em saas_orders (pedidos de planos/assinatura)
    $stmtOrder = $pdo->prepare("
        SELECT order_id as id, reference_no as order_number, amount as total, status, payment_method, created_at
          FROM saas_orders
         WHERE order_id = :order_id
           AND tenant_id = :tenant_id
         LIMIT 1
    ");
    $stmtOrder->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmtOrder->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtOrder->execute();
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou em saas_orders, tenta em orders (pedidos de vendas)
    if (!$order) {
        $stmtOrder2 = $pdo->prepare("
            SELECT id, order_number, total, status, created_at
              FROM orders
             WHERE id = :order_id
               AND tenant_id = :tenant_id
             LIMIT 1
        ");
        $stmtOrder2->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmtOrder2->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmtOrder2->execute();
        $order = $stmtOrder2->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$order) {
        echo json_encode(['success' => false, 'msg' => 'Pedido não encontrado.']);
        exit;
    }
    
    // Verifica se já existe um ticket aberto para este pedido
    $stmtCheck = $pdo->prepare("
        SELECT id, code
          FROM support_tickets
         WHERE tenant_id = :tenant_id
           AND deleted_at IS NULL
           AND status NOT IN ('closed', 'resolved')
           AND subject LIKE :subject_pattern
         LIMIT 1
    ");
    $orderNumber = $order['order_number'] ?: $orderId;
    $stmtCheck->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtCheck->bindValue(':subject_pattern', '%#' . $orderNumber . '%', PDO::PARAM_STR);
    $stmtCheck->execute();
    $existingTicket = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existingTicket) {
        echo json_encode([
            'success' => false,
            'msg' => 'Já existe uma contestação aberta para este pedido (Ticket #' . $existingTicket['code'] . ').',
            'existing_ticket' => $existingTicket['code']
        ]);
        exit;
    }
    
    // Define a categoria financeira (busca dinamicamente)
    $stmtCat = $pdo->prepare("
        SELECT id
          FROM support_categories
         WHERE (tenant_id = :tenant_id OR tenant_id = 0)
           AND is_active = 1
           AND name LIKE '%Financeiro%'
         ORDER BY tenant_id DESC
         LIMIT 1
    ");
    $stmtCat->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtCat->execute();
    $categoryId = (int)$stmtCat->fetchColumn();
    
    // Define prioridade baseada no tipo de contestação
    $priorityMap = [
        'cobranca_indevida' => 'high',
        'valor_incorreto' => 'medium',
        'nao_reconheco' => 'high',
        'problema_servico' => 'medium',
        'solicitar_estorno' => 'high',
        'outro' => 'low'
    ];
    $priority = $priorityMap[$contestType] ?? 'medium';
    
    // Monta o subject com referência ao pedido
    $subject = $title . ' - Pedido #' . $orderNumber;
    
    // Monta a mensagem com informações do pedido
    $contestTypeLabels = [
        'cobranca_indevida' => 'Cobrança Indevida',
        'valor_incorreto' => 'Valor Incorreto',
        'nao_reconheco' => 'Não Reconheço esta Cobrança',
        'problema_servico' => 'Problema com o Serviço',
        'solicitar_estorno' => 'Solicitar Estorno',
        'outro' => 'Outro'
    ];
    
    $message = "**Contestação de Cobrança**\n\n";
    $message .= "**Tipo:** " . ($contestTypeLabels[$contestType] ?? $contestType) . "\n";
    $message .= "**Pedido:** #" . $orderNumber . "\n";
    $message .= "**Valor:** R$ " . number_format($order['total'], 2, ',', '.') . "\n";
    $message .= "**Data:** " . date('d/m/Y H:i', strtotime($order['created_at'])) . "\n\n";
    $message .= "**Descrição do Cliente:**\n" . $description;
    
    // Gera código único do ticket
    $code = null;
    $maxAttempts = 5;
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $alphaLen = strlen($alphabet);
    
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $rand = '';
        for ($i = 0; $i < 6; $i++) {
            $rand .= $alphabet[random_int(0, $alphaLen - 1)];
        }
        $candidate = 'T' . $tenantId . '-' . $rand;
        
        $stmtCheckCode = $pdo->prepare("SELECT 1 FROM support_tickets WHERE code = :code LIMIT 1");
        $stmtCheckCode->bindValue(':code', $candidate, PDO::PARAM_STR);
        $stmtCheckCode->execute();
        if (!$stmtCheckCode->fetchColumn()) {
            $code = $candidate;
            break;
        }
    }
    
    if ($code === null) {
        $code = 'T' . $tenantId . '-' . date('ymdHis');
    }
    
    // Busca dados do requester
    $requesterName = null;
    $requesterEmail = null;
    
    $stmtTenant = $pdo->prepare("
        SELECT t.company_name, u.email
          FROM tenants t
     LEFT JOIN users u ON u.id = t.owner_user_id
         WHERE t.tenant_id = :tenant_id
         LIMIT 1
    ");
    $stmtTenant->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtTenant->execute();
    $rowTenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if ($rowTenant) {
        $requesterName = !empty($rowTenant['company_name']) ? $rowTenant['company_name'] : null;
        $requesterEmail = !empty($rowTenant['email']) ? $rowTenant['email'] : null;
    }
    
    if (empty($requesterName)) {
        $requesterName = $user->getUsername();
    }
    
    $pdo->beginTransaction();
    
    // Insere ticket
    $sqlTicket = "INSERT INTO support_tickets (
        tenant_id,
        code,
        created_by_user_id,
        requester_name,
        requester_email,
        category_id,
        subject,
        initial_message,
        status,
        priority,
        source,
        messages_count,
        created_at
    ) VALUES (
        :tenant_id,
        :code,
        :created_by_user_id,
        :requester_name,
        :requester_email,
        :category_id,
        :subject,
        :initial_message,
        'on_hold',
        :priority,
        'panel',
        1,
        NOW()
    )";
    
    $stmt = $pdo->prepare($sqlTicket);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->bindValue(':code', $code, PDO::PARAM_STR);
    $stmt->bindValue(':created_by_user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':requester_name', $requesterName, $requesterName !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':requester_email', $requesterEmail, $requesterEmail !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':category_id', $categoryId > 0 ? $categoryId : null, $categoryId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindValue(':initial_message', $message, PDO::PARAM_STR);
    $stmt->bindValue(':priority', $priority, PDO::PARAM_STR);
    $stmt->execute();
    
    $ticketId = (int)$pdo->lastInsertId();
    
    // Insere primeira mensagem
    $stmtMsg = $pdo->prepare("
        INSERT INTO support_ticket_messages (
            ticket_id,
            tenant_id,
            author_user_id,
            is_from_client,
            body,
            internal_visibility,
            created_at
        ) VALUES (
            :ticket_id,
            :tenant_id,
            :author_user_id,
            1,
            :body,
            'public',
            NOW()
        )
    ");
    $stmtMsg->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmtMsg->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtMsg->bindValue(':author_user_id', $userId, PDO::PARAM_INT);
    $stmtMsg->bindValue(':body', $message, PDO::PARAM_STR);
    $stmtMsg->execute();
    
    // Atualiza last_message_at
    $pdo->prepare("UPDATE support_tickets SET last_message_at = NOW() WHERE id = :id")
        ->execute([':id' => $ticketId]);
    
    // Registra histórico
    $stmtHist = $pdo->prepare("
        INSERT INTO support_ticket_history (
            ticket_id,
            tenant_id,
            actor_user_id,
            action_type,
            description,
            created_at
        ) VALUES (
            :ticket_id,
            :tenant_id,
            :actor_user_id,
            'field_change',
            :description,
            NOW()
        )
    ");
    $stmtHist->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmtHist->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtHist->bindValue(':actor_user_id', $userId, PDO::PARAM_INT);
    $stmtHist->bindValue(':description', 'Contestação de cobrança criada automaticamente para o pedido #' . $orderNumber, PDO::PARAM_STR);
    $stmtHist->execute();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'msg' => 'Contestação enviada com sucesso! Nosso time financeiro analisará sua solicitação.',
        'ticket_id' => $ticketId,
        'ticket' => [
            'id' => $ticketId,
            'code' => $code
        ]
    ]);
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'msg' => 'Erro ao criar a contestação.',
        'error' => $e->getMessage()
    ]);
    exit;
}
