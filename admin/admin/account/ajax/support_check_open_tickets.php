<?php
/**
 * ModernPOS - Módulo de Suporte (Cliente)
 * AJAX: Verificar tickets abertos do tenant
 */

@session_start();
require_once(__DIR__ . '/../../_init.php');

// Suprime erros/warnings que podem corromper o JSON (DEPOIS do _init.php)
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    // Verifica autenticação
    if (!$user->isLogged()) {
        echo json_encode(['success' => false, 'msg' => 'Usuário não autenticado.']);
        exit;
    }
    
    // Obtém tenant_id da sessão
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'msg' => 'Tenant não identificado.']);
        exit;
    }
    
    $pdo = db();
    
    // Busca tickets abertos (status: open, waiting_client, on_hold)
    // Tickets considerados "em andamento" que contam no limite
    $stmt = $pdo->prepare("
        SELECT id, code, subject, status, created_at
          FROM support_tickets
         WHERE tenant_id = :tenant_id
           AND deleted_at IS NULL
           AND status IN ('open', 'waiting_client', 'on_hold')
         ORDER BY created_at DESC
    ");
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $openTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($openTickets);
    
    // Define limite máximo
    $maxOpenTickets = 2;
    
    // Traduz status para exibição
    $statusLabels = [
        'open' => 'Aberto',
        'waiting_client' => 'Aguardando',
        'on_hold' => 'Em Espera'
    ];
    
    // Formata tickets para resposta
    $formattedTickets = [];
    foreach ($openTickets as $t) {
        $statusKey = strtolower(str_replace(' ', '_', $t['status']));
        $formattedTickets[] = [
            'id' => (int)$t['id'],
            'code' => $t['code'],
            'subject' => $t['subject'],
            'status' => $t['status'],
            'status_label' => $statusLabels[$statusKey] ?? ucfirst($t['status']),
            'created_at' => date('d/m/Y H:i', strtotime($t['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'max' => $maxOpenTickets,
        'can_create' => ($count < $maxOpenTickets),
        'has_warning' => ($count > 0 && $count < $maxOpenTickets),
        'is_blocked' => ($count >= $maxOpenTickets),
        'tickets' => $formattedTickets
    ]);
    exit;
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'msg' => 'Erro ao verificar tickets.',
        'error' => $e->getMessage()
    ]);
    exit;
}
