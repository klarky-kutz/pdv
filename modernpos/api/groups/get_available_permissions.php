<?php
/**
 * API: Listar Permissões Disponíveis
 * 
 * Retorna todas as permissões disponíveis filtradas pelas capabilities do plano
 * Usado ao criar/editar grupos para mostrar apenas o que o plano permite
 * 
 * IMPORTANTE: Esta API carrega o catálogo COMPLETO de permissões do ModernPOS
 * e filtra baseado nas features do plano configuradas no SaaS.
 */

// PRIMEIRO: Iniciar buffer para capturar qualquer output
ob_start();

// Suprimir TODOS os erros de exibição
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Iniciar sessão com supressão de warnings
@session_start();

// Carregar _init.php com supressão de warnings
@require_once dirname(__DIR__, 2) . '/_init.php';

// Limpar TODO o output que possa ter sido gerado
ob_end_clean();

// Agora sim, enviar header JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Verificar se está logado
if (!$user->isLogged()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificar se é Administrador (Owner)
if (!function_exists('is_tenant_owner') || !is_tenant_owner()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Apenas Administradores podem acessar permissões']);
    exit;
}

// Pegar tenant_id do usuário
$userId = user_id();
$userData = get_the_user($userId);
$tenantId = $userData['tenant_id'] ?? null;

if (!$tenantId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tenant não identificado']);
    exit;
}

try {
    // Buscar capabilities do plano via SaasLimitsBridge
    $saasLimitsPath = dirname(__DIR__, 2) . '/../saas/includes/SaasLimitsBridge.php';
    if (!file_exists($saasLimitsPath)) {
        throw new Exception('SaasLimitsBridge não encontrado: ' . $saasLimitsPath);
    }
    require_once $saasLimitsPath;
    
    $planCapabilities = SaasLimitsBridge::getPlanFeatures(db(), $tenantId);
    $isPermissive = in_array('*', $planCapabilities, true);
    
    // Buscar informações do plano
    $planInfo = SaasLimitsBridge::getTenantPlan(db(), $tenantId);
    
    // =========================================================
    // CARREGAR CATÁLOGO COMPLETO DE PERMISSÕES DO MODERNPOS
    // =========================================================
    
    // Método 1: Carregar do arquivo user_group_form.php (fonte de verdade)
    $posCatalogFile = dirname(__DIR__, 2) . '/_inc/template/user_group_form.php';
    $posCatalog = [];
    
    if (file_exists($posCatalogFile)) {
        // Flag para modo catálogo (não renderiza HTML)
        $GLOBALS['SAAS_PERMISSION_CATALOG_ONLY'] = true;
        $posCatalog = @include $posCatalogFile;
        unset($GLOBALS['SAAS_PERMISSION_CATALOG_ONLY']);
        
        if (!is_array($posCatalog)) {
            $posCatalog = [];
        }
    }
    
    // Converter nomes de categoria para português
    $categoryTranslations = [
        'report' => 'Relatórios',
        'accounting' => 'Contabilidade',
        'quotation' => 'Orçamentos',
        'installment' => 'Parcelamentos',
        'expenditure' => 'Despesas',
        'sell' => 'Vendas',
        'purchase' => 'Compras',
        'transfer' => 'Transferências',
        'giftcard' => 'Cartões Presente',
        'product' => 'Produtos',
        'supplier' => 'Fornecedores',
        'brand' => 'Marcas',
        'storebox' => 'Caixas da Loja',
        'unit' => 'Unidades',
        'taxrate' => 'Taxas/Impostos',
        'loan' => 'Empréstimos',
        'customer' => 'Clientes',
        'user' => 'Usuários',
        'usergroup' => 'Grupos de Usuário',
        'currency' => 'Moedas',
        'filemanager' => 'Gerenciador de Arquivos',
        'payment_method' => 'Formas de Pagamento',
        'store' => 'Lojas',
        'printer' => 'Impressoras',
        'sms' => 'SMS e E-mail',
        'langauge' => 'Idiomas',
        'settings' => 'Configurações',
    ];
    
    // Permissões adicionais do Painel da Conta
    $accountPermissions = [
        'Painel da Conta' => [
            'account.view_overview' => 'Ver Visão Geral',
            'account.view_stores' => 'Ver Lojas',
            'account.view_store_settings' => 'Config. de Lojas',
            'account.view_plans' => 'Ver Planos',
            'account.change_plan' => 'Alterar Plano',
            'account.view_billing' => 'Ver Cobrança',
            'account.view_users' => 'Ver Usuários',
            'account.view_permissions' => 'Ver Permissões',
            'account.manage_groups' => 'Gerenciar Grupos',
            'account.view_reports' => 'Relatórios Conta',
        ],
        'Sistema de Acesso' => [
            'switch_store' => 'Trocar de Loja',
        ],
    ];
    
    // Montar lista completa de permissões
    $allPermissions = [];
    
    // Primeiro: adicionar permissões do Painel da Conta
    foreach ($accountPermissions as $category => $perms) {
        $allPermissions[$category] = $perms;
    }
    
    // Depois: adicionar permissões do ModernPOS
    foreach ($posCatalog as $categoryKey => $perms) {
        if (!is_array($perms)) continue;
        
        // Traduzir nome da categoria
        $categoryName = $categoryTranslations[$categoryKey] ?? ucfirst(str_replace('_', ' ', $categoryKey));
        
        if (!isset($allPermissions[$categoryName])) {
            $allPermissions[$categoryName] = [];
        }
        
        foreach ($perms as $key => $label) {
            $allPermissions[$categoryName][$key] = $label;
        }
    }
    
    // =========================================================
    // FILTRAR PERMISSÕES BASEADO NAS CAPABILITIES DO PLANO
    // =========================================================
    
    $availablePermissions = [];
    
    if ($isPermissive) {
        // Plano permissivo (*): todas as permissões disponíveis
        $availablePermissions = $allPermissions;
    } else {
        // Filtrar apenas permissões presentes nas capabilities do plano
        foreach ($allPermissions as $category => $permissions) {
            $filtered = [];
            foreach ($permissions as $key => $label) {
                if (in_array($key, $planCapabilities, true)) {
                    $filtered[$key] = $label;
                }
            }
            if (!empty($filtered)) {
                $availablePermissions[$category] = $filtered;
            }
        }
    }
    
    // Contar total de permissões
    $totalAll = 0;
    foreach ($allPermissions as $perms) {
        $totalAll += count($perms);
    }
    
    $totalAvailable = 0;
    foreach ($availablePermissions as $perms) {
        $totalAvailable += count($perms);
    }
    
    echo json_encode([
        'success' => true,
        'plan' => [
            'id' => $planInfo['plan_id'] ?? null,
            'name' => $planInfo['name'] ?? 'Desconhecido',
            'is_permissive' => $isPermissive,
            'total_capabilities' => count($planCapabilities)
        ],
        'permissions' => $availablePermissions,
        'total_available' => $totalAvailable,
        'total_system' => $totalAll,
        'debug' => [
            'catalog_loaded' => !empty($posCatalog),
            'catalog_categories' => count($posCatalog),
            'capabilities_count' => count($planCapabilities),
            'is_permissive' => $isPermissive
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao buscar permissões: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar permissões: ' . $e->getMessage()]);
}
