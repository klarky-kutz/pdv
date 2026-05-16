<?php
/**
 * ModernPOS - Módulo de Suporte (Cliente)
 * AJAX: Criar novo ticket
 */

@session_start();
require_once(__DIR__ . '/../../_init.php');

// Suprime erros/warnings que podem corromper o JSON (DEPOIS do _init.php)
error_reporting(0);
ini_set('display_errors', 0);

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
    $subject = isset($_POST['subject']) ? trim((string)$_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
    $priority = isset($_POST['priority']) ? trim((string)$_POST['priority']) : '';

    // Categoria (vinda do SaaS) - esperamos category_id (numérico).
    // Mantém fallback para implementações antigas que enviavam 'category' como string.
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $categoryLegacy = isset($_POST['category']) ? trim((string)$_POST['category']) : '';
    if ($categoryId <= 0 && $categoryLegacy !== '' && ctype_digit($categoryLegacy)) {
        $categoryId = (int)$categoryLegacy;
    }
    
    // Validações
    if ($subject === '') {
        echo json_encode(['success' => false, 'msg' => 'Informe o assunto do ticket.']);
        exit;
    }
    
    if ($message === '') {
        echo json_encode(['success' => false, 'msg' => 'Descreva o problema.']);
        exit;
    }
    
    // Normaliza prioridade
    // Observação: no banco `support_tickets.priority` é NOT NULL.
    // Então, se vier vazio, usamos um default seguro ('low'), e o atendente pode ajustar depois.
    $priority = strtolower(trim($priority));
    $allowedPriorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($priority, $allowedPriorities, true)) {
        $priority = 'low';
    }
    
    $pdo = db();

    // Valida categoria (se informada) para evitar salvar ID inválido/de outro tenant
    $categoryIdToSave = null;
    if ($categoryId > 0) {
        try {
            $stmtCat = $pdo->prepare("
                SELECT id
                  FROM support_categories
                 WHERE id = :id
                   AND (tenant_id = :tenant_id OR tenant_id = 0)
                   AND is_active = 1
                 LIMIT 1
            ");
            $stmtCat->bindValue(':id', $categoryId, PDO::PARAM_INT);
            $stmtCat->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmtCat->execute();
            $foundCatId = (int)$stmtCat->fetchColumn();
            if ($foundCatId > 0) {
                $categoryIdToSave = $foundCatId;
            }
        } catch (Exception $e) {
            $categoryIdToSave = null;
        }
    }
    
    // Verifica limite de tickets abertos (máximo 2 por tenant)
    $maxOpenTickets = 2;
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) 
          FROM support_tickets 
         WHERE tenant_id = :tenant_id 
           AND deleted_at IS NULL
           AND status IN ('open', 'waiting_client', 'on_hold')
    ");
    $stmtCount->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtCount->execute();
    $openTicketsCount = (int)$stmtCount->fetchColumn();
    
    if ($openTicketsCount >= $maxOpenTickets) {
        echo json_encode([
            'success' => false, 
            'msg' => 'Você já possui ' . $openTicketsCount . ' tickets abertos. Aguarde a resolução de um ticket antes de abrir outro.',
            'error_type' => 'limit_exceeded'
        ]);
        exit;
    }
    
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
        
        $stmtCheck = $pdo->prepare("SELECT 1 FROM support_tickets WHERE code = :code LIMIT 1");
        $stmtCheck->bindValue(':code', $candidate, PDO::PARAM_STR);
        $stmtCheck->execute();
        if (!$stmtCheck->fetchColumn()) {
            $code = $candidate;
            break;
        }
    }
    
    if ($code === null) {
        $code = 'T' . $tenantId . '-' . date('ymdHis');
    }
    
    // Busca dados do requester (nome da empresa/conta)
    $requesterName = null;
    $requesterEmail = null;
    
    try {
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
    } catch (Exception $e) {
        // ignora erro ao buscar dados do tenant
    }
    
    // Se não encontrou nome, usa o username
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
        0,
        NOW()
    )";
    
    $stmt = $pdo->prepare($sqlTicket);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->bindValue(':code', $code, PDO::PARAM_STR);
    $stmt->bindValue(':created_by_user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':requester_name', $requesterName, $requesterName !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':requester_email', $requesterEmail, $requesterEmail !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':category_id', $categoryIdToSave, $categoryIdToSave !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindValue(':initial_message', $message, PDO::PARAM_STR);
    $stmt->bindValue(':priority', $priority, PDO::PARAM_STR);
    $stmt->execute();
    
    $ticketId = (int)$pdo->lastInsertId();
    
    // Insere primeira mensagem
    $sqlMsg = "INSERT INTO support_ticket_messages (
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
    )";
    
    $stmtMsg = $pdo->prepare($sqlMsg);
    $stmtMsg->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmtMsg->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtMsg->bindValue(':author_user_id', $userId, PDO::PARAM_INT);
    $stmtMsg->bindValue(':body', $message, PDO::PARAM_STR);
    $stmtMsg->execute();
    
    $messageId = (int)$pdo->lastInsertId();
    
    // Upload de anexo (se houver)
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($file['size'] <= $maxSize) {
            $uploadDir = __DIR__ . '/../../uploads/support/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            
            $origName = basename((string)$file['name']);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $safeExt = $ext ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
            $storedName = 't' . $ticketId . '_m' . $messageId . '_' . time() . $safeExt;
            $destPath = $uploadDir . $storedName;
            $relPath = 'uploads/support/' . $storedName;
            
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $stmtAtt = $pdo->prepare("
                    INSERT INTO support_ticket_attachments (
                        ticket_id,
                        message_id,
                        tenant_id,
                        uploaded_by_user_id,
                        file_name,
                        file_path,
                        mime_type,
                        file_size,
                        created_at
                    ) VALUES (
                        :ticket_id,
                        :message_id,
                        :tenant_id,
                        :user_id,
                        :file_name,
                        :file_path,
                        :mime_type,
                        :file_size,
                        NOW()
                    )
                ");
                $stmtAtt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                $stmtAtt->bindValue(':message_id', $messageId, PDO::PARAM_INT);
                $stmtAtt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
                $stmtAtt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmtAtt->bindValue(':file_name', $origName, PDO::PARAM_STR);
                $stmtAtt->bindValue(':file_path', $relPath, PDO::PARAM_STR);
                $stmtAtt->bindValue(':mime_type', $file['type'] ?? null, PDO::PARAM_STR);
                $stmtAtt->bindValue(':file_size', $file['size'] ?? 0, PDO::PARAM_INT);
                $stmtAtt->execute();
            }
        }
    }
    
    // Atualiza contador de mensagens
    $pdo->prepare("UPDATE support_tickets SET messages_count = 1, last_message_at = NOW() WHERE id = :id")
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
            'Ticket criado pelo cliente.',
            NOW()
        )
    ");
    $stmtHist->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    $stmtHist->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtHist->bindValue(':actor_user_id', $userId, PDO::PARAM_INT);
    $stmtHist->execute();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'msg' => 'Ticket criado com sucesso!',
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
        'msg' => 'Erro ao criar o ticket.',
        'error' => $e->getMessage()
    ]);
    exit;
}
