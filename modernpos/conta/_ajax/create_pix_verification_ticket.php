<?php
/**
 * ModernPOS - Conta do Cliente
 * AJAX: Criar ticket de verificação de pagamento PIX Manual
 * 
 * Este endpoint cria automaticamente um ticket de suporte para
 * verificação de pagamento PIX manual com upload de comprovante.
 */

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
    
    // Obtém dados do formulário
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
    
    // Validações
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'msg' => 'ID do pedido inválido.']);
        exit;
    }
    
    $pdo = db();
    
    // Verifica se o pedido pertence ao tenant e é PIX
    $stmtOrder = $pdo->prepare("
        SELECT order_id, reference_no, amount, payment_method, status, created_at
          FROM saas_orders
         WHERE order_id = :order_id
           AND tenant_id = :tenant_id
         LIMIT 1
    ");
    $stmtOrder->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmtOrder->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtOrder->execute();
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'msg' => 'Pedido não encontrado.']);
        exit;
    }
    
    if (strtolower($order['payment_method']) !== 'pix') {
        echo json_encode(['success' => false, 'msg' => 'Este pedido não é PIX.']);
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
    $stmtCheck->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtCheck->bindValue(':subject_pattern', '%Pedido #' . $orderId . '%', PDO::PARAM_STR);
    $stmtCheck->execute();
    $existingTicket = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existingTicket) {
        echo json_encode([
            'success' => false,
            'msg' => 'Já existe um ticket aberto para este pedido (Ticket #' . $existingTicket['code'] . ').',
            'existing_ticket' => $existingTicket['code']
        ]);
        exit;
    }
    
    // Define a categoria (Verificação de Pagamento ou Financeiro)
    $stmtCat = $pdo->prepare("
        SELECT id
          FROM support_categories
         WHERE (tenant_id = :tenant_id OR tenant_id = 0)
           AND is_active = 1
           AND (name LIKE '%Pagamento%' OR name LIKE '%Financeiro%')
         ORDER BY tenant_id DESC, 
                  CASE WHEN name LIKE '%Pagamento%' THEN 1 ELSE 2 END
         LIMIT 1
    ");
    $stmtCat->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtCat->execute();
    $categoryId = (int)$stmtCat->fetchColumn();
    
    // Processa upload do comprovante (se houver)
    $proofFileName = null;
    $proofFilePath = null;
    
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/payment_proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($fileExt, $allowedExts)) {
            echo json_encode(['success' => false, 'msg' => 'Formato de arquivo não permitido. Use JPG, PNG ou PDF.']);
            exit;
        }
        
        if ($_FILES['proof']['size'] > 5 * 1024 * 1024) { // 5MB
            echo json_encode(['success' => false, 'msg' => 'Arquivo muito grande. Tamanho máximo: 5MB.']);
            exit;
        }
        
        $proofFileName = 'proof_' . $tenantId . '_' . $orderId . '_' . time() . '.' . $fileExt;
        $proofFilePath = $uploadDir . $proofFileName;
        
        if (!move_uploaded_file($_FILES['proof']['tmp_name'], $proofFilePath)) {
            echo json_encode(['success' => false, 'msg' => 'Erro ao fazer upload do comprovante.']);
            exit;
        }
    }
    
    // Monta o subject e mensagem
    $subject = 'Verificação de Pagamento PIX - Pedido #' . $orderId;
    
    $message = "**Solicitação de Verificação de Pagamento PIX**\n\n";
    $message .= "**Pedido:** #" . $orderId . "\n";
    $message .= "**Referência:** " . ($order['reference_no'] ?: '-') . "\n";
    $message .= "**Valor:** R$ " . number_format($order['amount'], 2, ',', '.') . "\n";
    $message .= "**Data do Pedido:** " . date('d/m/Y H:i', strtotime($order['created_at'])) . "\n";
    $message .= "**Status Atual:** " . ucfirst($order['status']) . "\n\n";
    
    if ($description !== '') {
        $message .= "**Informações Adicionais:**\n" . $description . "\n\n";
    }
    
    if ($proofFileName) {
        $message .= "**Comprovante:** [Ver arquivo anexado]\n";
    } else {
        $message .= "**Comprovante:** Cliente não anexou comprovante\n";
    }
    
    $message .= "\n---\n";
    $message .= "*Este ticket foi criado automaticamente para verificação de pagamento PIX manual.*";
    
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
        'open',
        'high',
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
    
    $messageId = (int)$pdo->lastInsertId();
    
    // Se houver comprovante, salva referência na mensagem
    if ($proofFileName && $messageId > 0) {
        try {
            // Tenta criar tabela de anexos se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS support_ticket_attachments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT NOT NULL,
                    message_id INT DEFAULT NULL,
                    tenant_id INT NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_size INT DEFAULT NULL,
                    mime_type VARCHAR(100) DEFAULT NULL,
                    uploaded_by_user_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ticket (ticket_id),
                    INDEX idx_message (message_id)
                )
            ");
            
            $stmtAttach = $pdo->prepare("
                INSERT INTO support_ticket_attachments (
                    ticket_id, message_id, tenant_id, file_name, file_path, 
                    file_size, mime_type, uploaded_by_user_id, created_at
                ) VALUES (
                    :ticket_id, :message_id, :tenant_id, :file_name, :file_path,
                    :file_size, :mime_type, :uploaded_by_user_id, NOW()
                )
            ");
            
            $mimeType = mime_content_type($proofFilePath);
            $fileSize = filesize($proofFilePath);
            
            $stmtAttach->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmtAttach->bindValue(':message_id', $messageId, PDO::PARAM_INT);
            $stmtAttach->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmtAttach->bindValue(':file_name', $_FILES['proof']['name'], PDO::PARAM_STR);
            $stmtAttach->bindValue(':file_path', 'uploads/payment_proofs/' . $proofFileName, PDO::PARAM_STR);
            $stmtAttach->bindValue(':file_size', $fileSize, PDO::PARAM_INT);
            $stmtAttach->bindValue(':mime_type', $mimeType, PDO::PARAM_STR);
            $stmtAttach->bindValue(':uploaded_by_user_id', $userId, PDO::PARAM_INT);
            $stmtAttach->execute();
        } catch (Exception $e) {
            // Ignora erro de anexo, continua com o ticket
            error_log('[PIX Verification] Erro ao salvar anexo: ' . $e->getMessage());
        }
    }
    
    // Atualiza saas_orders com link do comprovante
    if ($proofFileName) {
        $stmtUpdOrder = $pdo->prepare("
            UPDATE saas_orders 
               SET proof_file = :proof_file,
                   updated_at = NOW()
             WHERE order_id = :order_id
        ");
        $stmtUpdOrder->bindValue(':proof_file', 'uploads/payment_proofs/' . $proofFileName, PDO::PARAM_STR);
        $stmtUpdOrder->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmtUpdOrder->execute();
    }
    
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
    $stmtHist->bindValue(':description', 'Ticket de verificação de pagamento PIX criado automaticamente para o pedido #' . $orderId, PDO::PARAM_STR);
    $stmtHist->execute();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'msg' => 'Ticket criado com sucesso! Nossa equipe verificará seu pagamento em breve.',
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
    
    // Remove arquivo se foi feito upload mas houve erro
    if (isset($proofFilePath) && file_exists($proofFilePath)) {
        @unlink($proofFilePath);
    }
    
    echo json_encode([
        'success' => false,
        'msg' => 'Erro ao criar o ticket.',
        'error' => $e->getMessage()
    ]);
    exit;
}
