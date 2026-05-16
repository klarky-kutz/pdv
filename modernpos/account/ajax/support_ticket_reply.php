<?php
/**
 * ModernPOS - Módulo de Suporte (Cliente)
 * AJAX: Responder ticket
 */

@session_start();
require_once(__DIR__ . '/../../_init.php');

// Suprime erros/warnings que podem corromper o JSON
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
    $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
    
    // Validações
    if ($ticketId <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Ticket inválido.']);
        exit;
    }
    
    if ($message === '') {
        echo json_encode(['success' => false, 'msg' => 'Digite sua resposta.']);
        exit;
    }
    
    $pdo = db();
    
    // Verifica se o ticket existe e pertence ao tenant
    $stmtTicket = $pdo->prepare("
        SELECT id, status, tenant_id
          FROM support_tickets
         WHERE id = :id
           AND tenant_id = :tenant_id
           AND deleted_at IS NULL
         LIMIT 1
    ");
    $stmtTicket->bindValue(':id', $ticketId, PDO::PARAM_INT);
    $stmtTicket->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmtTicket->execute();
    $ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'msg' => 'Ticket não encontrado.']);
        exit;
    }
    
    // Verifica se o ticket pode receber respostas
    $closedStatuses = ['closed', 'resolved'];
    $status = strtolower(str_replace(' ', '_', $ticket['status']));
    
    if (in_array($status, $closedStatuses, true)) {
        echo json_encode(['success' => false, 'msg' => 'Este ticket está fechado e não aceita novas respostas.']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Insere mensagem
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
    
    // Atualiza contadores no ticket
    $pdo->prepare("
        UPDATE support_tickets
           SET messages_count = messages_count + 1,
               last_message_at = NOW(),
               status = CASE 
                   WHEN status = 'waiting_client' THEN 'open'
                   ELSE status
               END
         WHERE id = :id
    ")->execute([':id' => $ticketId]);
    
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
            'message_added',
            'Resposta enviada pelo cliente.',
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
        'msg' => 'Resposta enviada com sucesso!',
        'message_id' => $messageId
    ]);
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'msg' => 'Erro ao enviar a resposta.',
        'error' => $e->getMessage()
    ]);
    exit;
}
