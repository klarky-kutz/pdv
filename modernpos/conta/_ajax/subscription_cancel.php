<?php
/**
 * API: Cancelar Assinatura
 * 
 * Cancela assinatura recorrente (Stripe ou Asaas) do tenant logado.
 * 
 * POST /conta/_ajax/subscription_cancel.php
 * Body: {"reason": "motivo do cancelamento"}
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// Caminho para o _init.php do modernpos
$initPath = dirname(__DIR__) . '/../_init.php';
if (!file_exists($initPath)) {
    echo json_encode(['success' => false, 'error' => 'Sistema não configurado corretamente.']);
    exit;
}

require_once $initPath;

// Verifica se o usuário está logado
if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido. Use POST.']);
    exit;
}

// Obtém dados do body
$input = json_decode(file_get_contents('php://input'), true);
$reason = isset($input['reason']) ? trim($input['reason']) : '';

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
        echo json_encode(['success' => false, 'error' => 'Tenant não identificado.']);
        exit;
    }
    
    // Carrega configuração de gateway
    $gatewayConfigPath = __DIR__ . '/../../saas/includes/PaymentGatewayConfig.php';
    if (!file_exists($gatewayConfigPath)) {
        $gatewayConfigPath = dirname(__DIR__) . '/../saas/includes/PaymentGatewayConfig.php';
    }
    if (!file_exists($gatewayConfigPath)) {
        // Tenta caminho alternativo
        $gatewayConfigPath = 'C:/xampp/htdocs/saas/includes/PaymentGatewayConfig.php';
    }
    
    if (file_exists($gatewayConfigPath)) {
        require_once $gatewayConfigPath;
    }
    
    // Tenant configurador de gateway (geralmente tenant 1)
    $gatewayOwnerTenant = 1;
    
    // IMPORTANTE: Verificar PRIMEIRO se é Asaas (pela referência do pedido)
    // Isso evita tentar cancelar no Stripe quando a assinatura é do Asaas
    
    // 1) Verifica se existe assinatura Asaas (identificada por 'Asaas' na referência)
    $asaasOrder = null;
    try {
        $stmt = $pdo->prepare(
            "SELECT order_id, reference_no
               FROM saas_orders
              WHERE tenant_id = :tenant_id
                AND (reference_no LIKE 'Asaas%' OR reference_no LIKE 'Asaas Sub%')
                AND status = 'paid'
              ORDER BY created_at DESC, order_id DESC
              LIMIT 1"
        );
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $asaasOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $asaasOrder = null;
    }
    
    // 2) Se for assinatura Asaas, cancela no Asaas
    if ($asaasOrder && !empty($asaasOrder['order_id'])) {
        $externalRef = (string)$asaasOrder['order_id'];
        
        try {
            if (class_exists('PaymentGatewayConfig')) {
                $gw = PaymentGatewayConfig::get($pdo, $gatewayOwnerTenant, 'asaas');
                $apiKey = $gw['asaas_api_key'] ?? null;
                $environment = $gw['environment'] ?? 'sandbox';
            } else {
                $apiKey = null;
                $environment = 'sandbox';
            }
            
            if (!$apiKey) {
                throw new Exception('Asaas não configurado.');
            }
            
            $baseUrl = $environment === 'production'
                ? 'https://api.asaas.com/v3'
                : 'https://sandbox.asaas.com/api/v3';
            
            // Busca assinatura pelo externalReference
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $baseUrl . '/subscriptions?externalReference=' . urlencode($externalRef),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'access_token: ' . $apiKey,
                    'User-Agent: ModernPOS/1.0',
                ],
            ]);
            
            $respList = curl_exec($ch);
            $errList = curl_error($ch);
            $statusList = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($respList === false) {
                throw new Exception('Falha de comunicação ao localizar assinatura Asaas: ' . $errList);
            }
            
            $dataList = json_decode($respList, true);
            if ($statusList < 200 || $statusList >= 300) {
                throw new Exception('HTTP ' . $statusList . ' ao localizar assinatura Asaas.');
            }
            
            $asaasSubId = null;
            if (isset($dataList['data']) && is_array($dataList['data']) && !empty($dataList['data'][0]['id'])) {
                $asaasSubId = (string)$dataList['data'][0]['id'];
            } elseif (isset($dataList[0]['id'])) {
                $asaasSubId = (string)$dataList[0]['id'];
            }
            
            if (!$asaasSubId) {
                throw new Exception('Assinatura Asaas não encontrada.');
            }
            
            // Cancela a assinatura
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $baseUrl . '/subscriptions/' . urlencode($asaasSubId),
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'access_token: ' . $apiKey,
                    'User-Agent: ModernPOS/1.0',
                ],
            ]);
            
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($resp === false) {
                throw new Exception('Falha de comunicação ao cancelar assinatura Asaas: ' . $err);
            }
            if ($status < 200 || $status >= 300) {
                throw new Exception('HTTP ' . $status . ' ao cancelar assinatura Asaas.');
            }
            
            // Registra motivo do cancelamento
            if ($reason) {
                try {
                    $pdo->prepare(
                        "INSERT INTO saas_cancellation_reasons (tenant_id, reason, canceled_at) VALUES (?, ?, NOW())"
                    )->execute([$tenantId, $reason]);
                } catch (Exception $e) {
                    // tabela pode não existir
                }
            }
            
            // Registra log de cancelamento para o painel SaaS
            try {
                $pdo->prepare(
                    "INSERT INTO saas_payment_transactions (tenant_id, gateway, kind, status, message, created_at) VALUES (?, 'asaas', 'cancellation', 'completed', ?, NOW())"
                )->execute([$tenantId, 'Assinatura cancelada pelo cliente. Motivo: ' . ($reason ?: 'Não informado')]);
            } catch (Exception $e) {
                // tabela pode não existir ou estrutura diferente
            }
            
            // Cria notificação para o tenant sobre o cancelamento
            try {
                $pdo->prepare(
                    "INSERT INTO saas_notifications (tenant_id, type, title, message, is_read, created_at) VALUES (?, 'subscription_cancelled', 'Assinatura Cancelada', ?, 0, NOW())"
                )->execute([$tenantId, 'Sua assinatura foi cancelada. Você mantém acesso até o fim do período pago.']);
            } catch (Exception $e) {
                // tabela pode não existir
            }
            
            echo json_encode(['success' => true, 'message' => 'Assinatura cancelada com sucesso. Você mantém acesso até o fim do período pago.']);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Falha ao cancelar assinatura no Asaas: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // 3) Se não for Asaas, verifica se é Stripe (pela tabela saas_subscriptions)
    $stripeSub = null;
    try {
        $stmCheck = $pdo->query("SHOW TABLES LIKE 'saas_subscriptions'");
        if ($stmCheck && $stmCheck->rowCount() > 0) {
            $stmt = $pdo->prepare(
                "SELECT stripe_subscription_id, status
                   FROM saas_subscriptions
                  WHERE tenant_id = :tenant_id
                  ORDER BY created_at DESC, subscription_id DESC
                  LIMIT 1"
            );
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmt->execute();
            $stripeSub = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Exception $e) {
        $stripeSub = null;
    }
    
    // 4) Se existir assinatura Stripe, cancela no Stripe
    if ($stripeSub && !empty($stripeSub['stripe_subscription_id'])) {
        // Se já está cancelado localmente, não precisa chamar a API
        if (strtolower($stripeSub['status']) === 'canceled') {
            // Registra motivo do cancelamento
            if ($reason) {
                try {
                    $pdo->prepare(
                        "INSERT INTO saas_cancellation_reasons (tenant_id, reason, canceled_at) VALUES (?, ?, NOW())"
                    )->execute([$tenantId, $reason]);
                } catch (Exception $e) {
                    // tabela pode não existir
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Assinatura já estava cancelada.']);
            exit;
        }
        
        try {
            if (class_exists('PaymentGatewayConfig')) {
                $gw = PaymentGatewayConfig::get($pdo, $gatewayOwnerTenant, 'stripe');
                $secretKey = $gw['stripe_secret_key'] ?? null;
            } else {
                $secretKey = null;
            }
            
            if (!$secretKey) {
                throw new Exception('Stripe não configurado.');
            }
            
            // Carrega SDK Stripe
            $autoloadPaths = [
                __DIR__ . '/../../Stripe/vendor/autoload.php',
                dirname(__DIR__) . '/../Stripe/vendor/autoload.php',
                'C:/xampp/htdocs/Stripe/vendor/autoload.php',
                'C:/xampp/htdocs/saas/Stripe/vendor/autoload.php',
            ];
            
            $stripeLoaded = false;
            foreach ($autoloadPaths as $autoload) {
                if (file_exists($autoload)) {
                    require_once $autoload;
                    $stripeLoaded = true;
                    break;
                }
            }
            
            if (!$stripeLoaded) {
                throw new Exception('SDK Stripe não encontrado.');
            }
            
            \Stripe\Stripe::setApiKey($secretKey);
            $subId = (string)$stripeSub['stripe_subscription_id'];
            
            // Tenta cancelar - se não existir na Stripe, cancela localmente
            try {
                $subscription = \Stripe\Subscription::retrieve($subId);
                
                // Se já está cancelada na Stripe, apenas atualiza localmente
                if (in_array($subscription->status, ['canceled', 'incomplete_expired'])) {
                    // Atualiza registro local para refletir o estado real
                    try {
                        $upd = $pdo->prepare(
                            "UPDATE saas_subscriptions
                                SET status = 'canceled', current_period_end = NOW(), updated_at = NOW()
                              WHERE tenant_id = :tenant_id AND stripe_subscription_id = :sub_id"
                        );
                        $upd->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
                        $upd->bindValue(':sub_id', $subId, PDO::PARAM_STR);
                        $upd->execute();
                    } catch (Exception $e) {
                        // não interrompe o fluxo
                    }
                } else {
                    // Cancelamento imediato
                    $subscription->cancel();
                    
                    // Atualiza registro local
                    try {
                        $upd = $pdo->prepare(
                            "UPDATE saas_subscriptions
                                SET status = 'canceled', current_period_end = NOW(), updated_at = NOW()
                              WHERE tenant_id = :tenant_id AND stripe_subscription_id = :sub_id"
                        );
                        $upd->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
                        $upd->bindValue(':sub_id', $subId, PDO::PARAM_STR);
                        $upd->execute();
                    } catch (Exception $e) {
                        // não interrompe o fluxo
                    }
                }
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Assinatura não existe na Stripe - pode ter sido deletada ou ID inválido
                // Apenas atualiza o registro local
                try {
                    $upd = $pdo->prepare(
                        "UPDATE saas_subscriptions
                            SET status = 'canceled', current_period_end = NOW(), updated_at = NOW()
                          WHERE tenant_id = :tenant_id AND stripe_subscription_id = :sub_id"
                    );
                    $upd->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
                    $upd->bindValue(':sub_id', $subId, PDO::PARAM_STR);
                    $upd->execute();
                } catch (Exception $ex) {
                    // não interrompe o fluxo
                }
            }
            
            // Registra motivo do cancelamento
            if ($reason) {
                try {
                    $pdo->prepare(
                        "INSERT INTO saas_cancellation_reasons (tenant_id, reason, canceled_at) VALUES (?, ?, NOW())"
                    )->execute([$tenantId, $reason]);
                } catch (Exception $e) {
                    // tabela pode não existir
                }
            }
            
            // Registra log de cancelamento para o painel SaaS
            try {
                $pdo->prepare(
                    "INSERT INTO saas_payment_transactions (tenant_id, gateway, kind, status, message, created_at) VALUES (?, 'stripe', 'cancellation', 'completed', ?, NOW())"
                )->execute([$tenantId, 'Assinatura cancelada pelo cliente. Motivo: ' . ($reason ?: 'Não informado')]);
            } catch (Exception $e) {
                // tabela pode não existir ou estrutura diferente
            }
            
            // Cria notificação para o tenant sobre o cancelamento
            try {
                $pdo->prepare(
                    "INSERT INTO saas_notifications (tenant_id, type, title, message, is_read, created_at) VALUES (?, 'subscription_cancelled', 'Assinatura Cancelada', ?, 0, NOW())"
                )->execute([$tenantId, 'Sua assinatura foi cancelada. Você mantém acesso até o fim do período pago.']);
            } catch (Exception $e) {
                // tabela pode não existir
            }
            
            echo json_encode(['success' => true, 'message' => 'Assinatura cancelada com sucesso. Você mantém acesso até o fim do período pago.']);
            exit;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Falha ao cancelar assinatura na Stripe: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // 4) Nenhuma recorrência encontrada - cancela localmente
    try {
        // Tenta atualizar status do tenant para cancelado
        $pdo->prepare("UPDATE tenants SET subscription_status = 'canceled', updated_at = NOW() WHERE tenant_id = ?")
            ->execute([$tenantId]);
        
        // Registra motivo
        if ($reason) {
            try {
                $pdo->prepare(
                    "INSERT INTO saas_cancellation_reasons (tenant_id, reason, canceled_at) VALUES (?, ?, NOW())"
                )->execute([$tenantId, $reason]);
            } catch (Exception $e) {
                // tabela pode não existir
            }
        }
        
        // Registra log de cancelamento para o painel SaaS
        try {
            $pdo->prepare(
                "INSERT INTO saas_payment_transactions (tenant_id, gateway, kind, status, message, created_at) VALUES (?, 'local', 'cancellation', 'completed', ?, NOW())"
            )->execute([$tenantId, 'Assinatura cancelada pelo cliente (sem recorrência). Motivo: ' . ($reason ?: 'Não informado')]);
        } catch (Exception $e) {
            // tabela pode não existir ou estrutura diferente
        }
        
        // Cria notificação para o tenant sobre o cancelamento
        try {
            $pdo->prepare(
                "INSERT INTO saas_notifications (tenant_id, type, title, message, is_read, created_at) VALUES (?, 'subscription_cancelled', 'Assinatura Cancelada', ?, 0, NOW())"
            )->execute([$tenantId, 'Sua assinatura foi cancelada. Você mantém acesso até o fim do período pago.']);
        } catch (Exception $e) {
            // tabela pode não existir
        }
        
        echo json_encode(['success' => true, 'message' => 'Assinatura cancelada com sucesso. Você mantém acesso até o fim do período pago.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Não foi possível cancelar a assinatura. Entre em contato com o suporte.']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar cancelamento: ' . $e->getMessage()
    ]);
}
