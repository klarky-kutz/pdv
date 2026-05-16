<?php
/**
 * API: Cancelar Ticket (pelo cliente)
 * POST /modernpos/conta/_ajax/ticket_cancel.php
 * 
 * Body JSON: { ticket_id: int, reason?: string }
 */

session_start();
require_once __DIR__ . '/../../_init.php';

// Suprime erros/warnings que podem corromper o JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

// Ler dados do corpo da requisição
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
$reason = isset($input['reason']) ? trim((string)$input['reason']) : '';

if ($ticketId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do ticket inválido.']);
    exit;
}

try {
    $pdo = db();
    
    // Obter user_id (sempre necessário)
    $userId = user_id();
    
    // Obter tenant_id da sessão ou do usuário
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    if ($tenantId <= 0) {
        $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $tenantId = (int)$stmt->fetchColumn();
    }
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Tenant não identificado.']);
        exit;
    }
    
    // Verificar se o ticket existe e pertence ao tenant
    $stmt = $pdo->prepare("
        SELECT id, code, subject, status, tenant_id 
        FROM support_tickets 
        WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmt->execute([$ticketId, $tenantId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'error' => 'Ticket não encontrado ou acesso negado.']);
        exit;
    }
    
    // Verificar se o ticket pode ser cancelado (não está fechado/resolvido)
    $status = strtolower(str_replace(' ', '_', $ticket['status']));
    if (in_array($status, ['closed', 'resolved', 'cancelled'])) {
        echo json_encode(['success' => false, 'error' => 'Este ticket já está fechado e não pode ser cancelado.']);
        exit;
    }
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    try {
        // Guardar status anterior para o histórico
        $oldStatus = $ticket['status'];
        
        // Atualizar status do ticket para "cancelled"
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET status = 'cancelled', 
                closed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$ticketId]);
        
        // Registrar no histórico de atividades
        try {
            $stmt = $pdo->prepare("
                INSERT INTO support_ticket_history 
                (ticket_id, tenant_id, actor_user_id, action_type, from_value, to_value, description, created_at)
                VALUES (?, ?, ?, 'status_change', ?, 'cancelled', ?, NOW())
            ");
            $historyDesc = 'Ticket cancelado pelo cliente';
            if (!empty($reason)) {
                $historyDesc .= ': ' . mb_substr($reason, 0, 200);
            }
            $stmt->execute([$ticketId, $tenantId, $userId, $oldStatus, $historyDesc]);
        } catch (Exception $eHist) {
            error_log('[ticket_cancel] Aviso: Não foi possível registrar no histórico: ' . $eHist->getMessage());
        }
        
        // Adicionar mensagem de sistema indicando o cancelamento
        $cancelMessage = "🔴 Ticket cancelado pelo cliente";
        if (!empty($reason)) {
            $cancelMessage .= "\n\nMotivo: " . $reason;
        }
        
        // Inserir mensagem na tabela correta (support_ticket_messages)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO support_ticket_messages (ticket_id, user_id, message, is_staff, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$ticketId, $userId, $cancelMessage]);
            
            // Atualizar contador de mensagens e last_message_at
            $stmt = $pdo->prepare("
                UPDATE support_tickets 
                SET messages_count = messages_count + 1,
                    last_message_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
        } catch (Exception $eMsgs) {
            // Se falhar ao inserir mensagem, apenas log - não impede o cancelamento
            error_log('[ticket_cancel] Aviso: Não foi possível inserir mensagem de cancelamento: ' . $eMsgs->getMessage());
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Ticket #' . $ticket['code'] . ' foi cancelado com sucesso.',
            'ticket_id' => $ticketId,
            'ticket_code' => $ticket['code']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('[ticket_cancel] Erro: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao cancelar ticket: ' . $e->getMessage()]);
}
