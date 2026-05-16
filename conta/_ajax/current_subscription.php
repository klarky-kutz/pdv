<?php
/**
 * API: Dados da Assinatura Atual
 * 
 * Retorna informações detalhadas da assinatura do tenant logado.
 * 
 * GET /conta/_ajax/current_subscription.php
 * 
 * Response:
 * {
 *   "success": true,
 *   "subscription": {...},
 *   "current_plan": {...},
 *   "next_billing": {...}
 * }
 */

session_start();

// Header antes de qualquer saída
header('Content-Type: application/json; charset=utf-8');

// Caminho para o _init.php do modernpos
$initPath = dirname(__DIR__) . '/../_init.php';
if (!file_exists($initPath)) {
    echo json_encode(['success' => false, 'message' => 'Sistema não configurado corretamente.']);
    exit;
}

require_once $initPath;

// Suprime erros/warnings que podem corromper o JSON
error_reporting(0);
ini_set('display_errors', 0);

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
    
    // Verifica se as novas colunas existem na tabela tenants
    $hasNewTenantColumns = false;
    try {
        $checkCol = $pdo->query("SELECT cancellation_scheduled_at FROM tenants LIMIT 1");
        $hasNewTenantColumns = true;
    } catch (PDOException $e) {
        $hasNewTenantColumns = false;
    }
    
    // Verifica se a tabela plans tem as colunas clients_limit e storage_mb
    $hasClientsLimit = false;
    $hasStorageMb = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM plans LIKE 'clients_limit'");
        $hasClientsLimit = $checkCol && $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasClientsLimit = false;
    }
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM plans LIKE 'storage_mb'");
        $hasStorageMb = $checkCol && $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $hasStorageMb = false;
    }
    
    // Busca informações completas do tenant e plano
    $selectExtra = '';
    if ($hasClientsLimit) {
        $selectExtra .= ", p.clients_limit";
    } else {
        $selectExtra .= ", 0 as clients_limit";
    }
    if ($hasStorageMb) {
        $selectExtra .= ", p.storage_mb";
    } else {
        $selectExtra .= ", 0 as storage_mb";
    }
    
    if ($hasNewTenantColumns) {
        $sqlTenant = "
            SELECT 
                t.tenant_id,
                t.company_name,
                t.plan_id,
                t.subscription_status,
                t.trial_ends_at,
                t.cancellation_scheduled_at,
                t.cancellation_reason,
                t.default_payment_method,
                t.card_last_four,
                t.card_brand,
                t.created_at as tenant_created_at,
                p.name as plan_name,
                p.price_monthly,
                p.max_stores,
                p.max_users,
                p.max_products,
                p.products_unlimited,
                p.priority_support
                $selectExtra
            FROM tenants t
            LEFT JOIN plans p ON t.plan_id = p.plan_id
            WHERE t.tenant_id = ?
            LIMIT 1
        ";
    } else {
        // Fallback sem as novas colunas
        $sqlTenant = "
            SELECT 
                t.tenant_id,
                t.company_name,
                t.plan_id,
                t.subscription_status,
                t.trial_ends_at,
                NULL as cancellation_scheduled_at,
                NULL as cancellation_reason,
                NULL as default_payment_method,
                NULL as card_last_four,
                NULL as card_brand,
                t.created_at as tenant_created_at,
                p.name as plan_name,
                p.price_monthly,
                p.max_stores,
                p.max_users,
                p.max_products,
                p.products_unlimited,
                p.priority_support
                $selectExtra
            FROM tenants t
            LEFT JOIN plans p ON t.plan_id = p.plan_id
            WHERE t.tenant_id = ?
            LIMIT 1
        ";
    }
    
    $stmtTenant = $pdo->prepare($sqlTenant);
    $stmtTenant->execute([$tenantId]);
    $tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenantData) {
        echo json_encode(['success' => false, 'message' => 'Tenant não encontrado.']);
        exit;
    }
    
    // Verifica se há assinatura na tabela saas_subscriptions (Stripe)
    $stripeSubscription = null;
    try {
        $stmtStripeSub = $pdo->prepare("
            SELECT 
                subscription_id,
                stripe_subscription_id,
                stripe_customer_id,
                status,
                current_period_start,
                current_period_end,
                cancel_at_period_end,
                created_at
            FROM saas_subscriptions
            WHERE tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmtStripeSub->execute([$tenantId]);
        $stripeSubscription = $stmtStripeSub->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Tabela pode não existir
    }
    
    // Busca última fatura paga
    $lastPaidOrder = null;
    try {
        $stmtLastPaid = $pdo->prepare("
            SELECT 
                order_id,
                reference_no,
                amount,
                payment_method,
                status,
                paid_at,
                due_date
            FROM saas_orders
            WHERE tenant_id = ? AND status = 'paid'
            ORDER BY paid_at DESC, order_id DESC
            LIMIT 1
        ");
        $stmtLastPaid->execute([$tenantId]);
        $lastPaidOrder = $stmtLastPaid->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Pode não haver pedidos
    }
    
    // Busca próxima fatura pendente
    $nextPendingOrder = null;
    try {
        $stmtNextPending = $pdo->prepare("
            SELECT 
                order_id,
                reference_no,
                amount,
                payment_method,
                status,
                due_date
            FROM saas_orders
            WHERE tenant_id = ? AND status = 'pending'
            ORDER BY due_date ASC
            LIMIT 1
        ");
        $stmtNextPending->execute([$tenantId]);
        $nextPendingOrder = $stmtNextPending->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Pode não haver pedidos pendentes
    }
    
    // Conta lojas ativas do tenant
    $storesCount = 0;
    try {
        $stmtStores = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE tenant_id = ?");
        $stmtStores->execute([$tenantId]);
        $storesCount = (int)$stmtStores->fetchColumn();
    } catch (Exception $e) {
        // Ignora erro
    }
    
    // Conta usuários do tenant (todas as lojas)
    $usersCount = 0;
    try {
        $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
        $stmtUsers->execute([$tenantId]);
        $usersCount = (int)$stmtUsers->fetchColumn();
    } catch (Exception $e) {
        // Ignora erro
    }
    
    // Busca todas as lojas do tenant (usado para contar produtos, clientes e storage)
    $storeIds = [];
    try {
        $stmtStoreIds = $pdo->prepare("SELECT store_id FROM stores WHERE tenant_id = ?");
        $stmtStoreIds->execute([$tenantId]);
        $storeIds = $stmtStoreIds->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $storeIds = [];
    }
    
    // Conta produtos total do tenant
    // Os produtos são vinculados às lojas via tabela product_to_store
    $productsCount = 0;
    $productsError = null;
    try {
        // Método 1: Conta produtos distintos via product_to_store (tabela de relacionamento produto-loja)
        if (!empty($storeIds)) {
            $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
            $stmtProducts = $pdo->prepare("
                SELECT COUNT(DISTINCT product_id) 
                FROM product_to_store 
                WHERE store_id IN ($placeholders) AND status = 1
            ");
            $stmtProducts->execute($storeIds);
            $productsCount = (int)$stmtProducts->fetchColumn();
        }
        
        // Método 2 (fallback): Se product_to_store não retornou nada, tenta direto na tabela products
        if ($productsCount === 0) {
            // Verifica se a coluna tenant_id existe na tabela products
            $checkProductsCol = $pdo->query("SHOW COLUMNS FROM products LIKE 'tenant_id'");
            $hasProductsTenantId = $checkProductsCol && $checkProductsCol->rowCount() > 0;
            
            if ($hasProductsTenantId) {
                $stmtProducts = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ?");
                $stmtProducts->execute([$tenantId]);
                $productsCount = (int)$stmtProducts->fetchColumn();
            }
        }
    } catch (Exception $e) {
        $productsError = $e->getMessage();
        error_log('[current_subscription] Erro ao contar produtos: ' . $e->getMessage());
    }
    
    // Conta clientes (customers) do tenant
    // Os clientes são vinculados às lojas via tabela customer_to_store
    $clientsCount = 0;
    $clientsError = null;
    try {
        // Método 1: Conta clientes distintos via customer_to_store (tabela de relacionamento cliente-loja)
        if (!empty($storeIds)) {
            $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
            $stmtCustomers = $pdo->prepare("
                SELECT COUNT(DISTINCT customer_id) 
                FROM customer_to_store 
                WHERE store_id IN ($placeholders)
            ");
            $stmtCustomers->execute($storeIds);
            $clientsCount = (int)$stmtCustomers->fetchColumn();
        }
        
        // Método 2 (fallback): Se customer_to_store não retornou nada, tenta direto na tabela customers
        if ($clientsCount === 0) {
            // Verifica se a coluna tenant_id existe na tabela customers
            $checkCustomersCol = $pdo->query("SHOW COLUMNS FROM customers LIKE 'tenant_id'");
            $hasCustomersTenantId = $checkCustomersCol && $checkCustomersCol->rowCount() > 0;
            
            if ($hasCustomersTenantId) {
                $stmtCustomers = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE tenant_id = ?");
                $stmtCustomers->execute([$tenantId]);
                $clientsCount = (int)$stmtCustomers->fetchColumn();
            }
        }
    } catch (Exception $e) {
        $clientsError = $e->getMessage();
        error_log('[current_subscription] Erro ao contar clientes: ' . $e->getMessage());
    }
    
    // Calcula o armazenamento usado em MB (arquivos no filemanager)
    // ISOLADO POR TENANT: Usa pasta storage/products/{tenant_id}/
    $storageUsedMb = 0;
    try {
        // Usa pasta isolada do tenant
        $basePath = realpath(__DIR__ . '/../../storage/products');
        $storagePath = $basePath . DIRECTORY_SEPARATOR . $tenantId;
        
        if ($storagePath && is_dir($storagePath)) {
            $totalBytes = 0;
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storagePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $totalBytes += $file->getSize();
                }
            }
            $storageUsedMb = round($totalBytes / (1024 * 1024), 2);
        }
        
        // Fallback: Se não encontrou arquivos no filemanager, tenta na tabela tenant_storage_usage
        if ($storageUsedMb == 0) {
            $hasStorageTable = false;
            try {
                $checkTable = $pdo->query("SHOW TABLES LIKE 'tenant_storage_usage'");
                $hasStorageTable = $checkTable && $checkTable->rowCount() > 0;
            } catch (Exception $e) {
                $hasStorageTable = false;
            }
            
            if ($hasStorageTable) {
                $stmtStorage = $pdo->prepare("SELECT COALESCE(SUM(size_bytes), 0) FROM tenant_storage_usage WHERE tenant_id = ?");
                $stmtStorage->execute([$tenantId]);
                $storageUsedMb = round((float)$stmtStorage->fetchColumn() / (1024 * 1024), 2);
            }
        }
    } catch (Exception $e) {
        error_log('[current_subscription] Erro ao calcular storage: ' . $e->getMessage());
    }
    
    // Calcula dias restantes do trial
    $isInTrial = false;
    $trialDaysRemaining = 0;
    $trialEndsFormatted = null;
    
    if (!empty($tenantData['trial_ends_at'])) {
        $trialEnds = new DateTime($tenantData['trial_ends_at']);
        $now = new DateTime();
        if ($trialEnds > $now) {
            $isInTrial = true;
            $trialDaysRemaining = $now->diff($trialEnds)->days;
            $trialEndsFormatted = $trialEnds->format('d/m/Y');
        }
    }
    
    // Determina próxima data de cobrança
    $nextBillingDate = null;
    $nextBillingAmount = 0;
    
    if ($stripeSubscription && !empty($stripeSubscription['current_period_end'])) {
        $nextBillingDate = $stripeSubscription['current_period_end'];
        $nextBillingAmount = (float)$tenantData['price_monthly'];
    } elseif ($nextPendingOrder) {
        $nextBillingDate = $nextPendingOrder['due_date'];
        $nextBillingAmount = (float)$nextPendingOrder['amount'];
    } elseif ($isInTrial) {
        $nextBillingDate = $tenantData['trial_ends_at'];
        $nextBillingAmount = (float)$tenantData['price_monthly'];
    }
    
    // Formata data de próxima cobrança
    $nextBillingFormatted = null;
    if ($nextBillingDate) {
        $nextBillingDt = new DateTime($nextBillingDate);
        $nextBillingFormatted = $nextBillingDt->format('d/m/Y');
    }
    
    // Monta status legível
    $statusLabels = [
        'active' => 'Ativa',
        'pending' => 'Pendente',
        'trial' => 'Período de Teste',
        'trialing' => 'Período de Teste',
        'canceled' => 'Cancelada',
        'cancelled' => 'Cancelada',
        'past_due' => 'Pagamento Atrasado',
        'suspended' => 'Suspensa',
        'expired' => 'Expirada',
    ];
    
    $currentStatus = $tenantData['subscription_status'] ?? 'unknown';
    if ($isInTrial && in_array($currentStatus, ['pending', 'unknown', ''])) {
        $currentStatus = 'trial';
    }
    
    // Monta resposta
    $response = [
        'success' => true,
        'subscription' => [
            'status' => $currentStatus,
            'status_label' => $statusLabels[$currentStatus] ?? 'Desconhecido',
            'is_active' => in_array($currentStatus, ['active', 'trial', 'trialing']),
            'is_in_trial' => $isInTrial,
            'trial_days_remaining' => $trialDaysRemaining,
            'trial_ends_at' => $tenantData['trial_ends_at'],
            'trial_ends_formatted' => $trialEndsFormatted,
            'cancellation_scheduled' => !empty($tenantData['cancellation_scheduled_at']),
            'cancellation_date' => $tenantData['cancellation_scheduled_at'],
            'cancellation_reason' => $tenantData['cancellation_reason'],
            'stripe_subscription' => $stripeSubscription ? [
                'id' => $stripeSubscription['stripe_subscription_id'],
                'status' => $stripeSubscription['status'],
                'period_start' => $stripeSubscription['current_period_start'],
                'period_end' => $stripeSubscription['current_period_end'],
                'cancel_at_period_end' => (bool)$stripeSubscription['cancel_at_period_end'],
            ] : null,
        ],
        'current_plan' => [
            'plan_id' => (int)$tenantData['plan_id'],
            'name' => $tenantData['plan_name'],
            'price_monthly' => (float)($tenantData['price_monthly'] ?? 0),
            'price_yearly' => isset($tenantData['price_yearly']) && $tenantData['price_yearly'] ? (float)$tenantData['price_yearly'] : null,
            'limits' => [
                'stores' => (int)($tenantData['max_stores'] ?? 1),
                'users' => (int)($tenantData['max_users'] ?? 1),
                'products' => ($tenantData['products_unlimited'] ?? false) ? -1 : (int)($tenantData['max_products'] ?? 100),
                'clients' => (int)($tenantData['clients_limit'] ?? 0),
                'storage_mb' => (int)($tenantData['storage_mb'] ?? 0),
            ],
            'priority_support' => (bool)($tenantData['priority_support'] ?? false),
        ],
        'usage' => [
            'stores_used' => $storesCount,
            'stores_limit' => (int)$tenantData['max_stores'],
            'stores_remaining' => max(0, (int)$tenantData['max_stores'] - $storesCount),
            'users_used' => $usersCount,
            'users_limit' => (int)($tenantData['max_users'] ?? 1),
            'products_used' => $productsCount,
            'products_limit' => ($tenantData['products_unlimited'] ?? false) ? -1 : (int)($tenantData['max_products'] ?? 100),
            'clients_used' => $clientsCount,
            'clients_limit' => (int)($tenantData['clients_limit'] ?? 0),
            'storage_used_mb' => $storageUsedMb,
            'storage_limit_mb' => (int)($tenantData['storage_mb'] ?? 0),
        ],
        'billing' => [
            'next_billing_date' => $nextBillingDate,
            'next_billing_formatted' => $nextBillingFormatted,
            'next_billing_amount' => $nextBillingAmount,
            'payment_method' => $tenantData['default_payment_method'],
            'card_last_four' => $tenantData['card_last_four'],
            'card_brand' => $tenantData['card_brand'],
            'last_payment' => $lastPaidOrder ? [
                'order_id' => (int)$lastPaidOrder['order_id'],
                'amount' => (float)$lastPaidOrder['amount'],
                'paid_at' => $lastPaidOrder['paid_at'],
                'method' => $lastPaidOrder['payment_method'],
            ] : null,
        ],
        'tenant' => [
            'tenant_id' => $tenantId,
            'company_name' => $tenantData['company_name'],
            'created_at' => $tenantData['tenant_created_at'],
        ],
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar assinatura: ' . $e->getMessage()
    ]);
}
