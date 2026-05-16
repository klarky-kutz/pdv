<?php
/**
 * Helper de Controle de Acesso para a Página /conta
 * 
 * Fornece funções para validar permissões granulares de acesso
 * às seções e abas da interface de gerenciamento da conta.
 * 
 * @package ModernPOS
 * @subpackage Account
 * @version 1.0.0
 * @since 2026-01-28
 */

// Evitar acesso direto
if (!defined('ROOT')) {
    exit('Direct access not allowed');
}

/**
 * Verifica se o usuário atual tem permissão para acessar uma seção da página /conta
 * 
 * @param string $section Nome da seção (overview, stores, plans, users, reports)
 * @param string|null $tab Nome da aba específica (opcional)
 * @return bool True se tem acesso, False caso contrário
 */
function can_access_account_section(string $section, ?string $tab = null): bool
{
    // Superadmin (group_id = 1) sempre tem acesso total
    if (function_exists('user_group_id') && user_group_id() == 1) {
        return true;
    }
    
    // Owner do tenant sempre tem acesso ao painel da conta
    if (function_exists('is_tenant_owner') && is_tenant_owner()) {
        return true;
    }
    
    // Mapear seção para permission key
    $permissionKey = 'account.view_' . $section;
    
    // Verificar permissão da seção
    // NOTA: Ignoramos o $tab por enquanto - se tem acesso à seção, tem acesso a todas as tabs
    // Permissões granulares de tab podem ser adicionadas futuramente se necessário
    if (function_exists('has_permission')) {
        return (bool)has_permission('access', $permissionKey);
    }
    
    // Fallback: se sistema de permissões não estiver disponível, permitir acesso
    // (modo compatibilidade para instalações antigas)
    return true;
}

/**
 * Retorna lista de seções restritas para o usuário atual
 * 
 * @return array Array com nomes das seções que o usuário NÃO pode acessar
 */
function get_restricted_sections_for_user(): array
{
    $sections = ['overview', 'stores', 'plans', 'users', 'reports', 'support'];
    $restricted = [];
    
    foreach ($sections as $section) {
        if (!can_access_account_section($section)) {
            $restricted[] = $section;
        }
    }
    
    return $restricted;
}

/**
 * Verifica se o usuário pode gerenciar grupos RBAC
 * 
 * @return bool
 */
function can_manage_rbac_groups(): bool
{
    if (function_exists('user_group_id') && user_group_id() == 1) {
        return true;
    }
    
    if (function_exists('has_permission')) {
        return has_permission('access', 'account.manage_groups');
    }
    
    return false;
}

/**
 * Verifica se o usuário pode alterar o plano de assinatura
 * 
 * @return bool
 */
function can_change_subscription_plan(): bool
{
    if (function_exists('user_group_id') && user_group_id() == 1) {
        return true;
    }
    
    if (function_exists('has_permission')) {
        return has_permission('access', 'account.change_plan');
    }
    
    return false;
}

/**
 * Renderiza mensagem de acesso negado padronizada
 * 
 * @param string $section Nome da seção que foi bloqueada
 * @return void
 */
function render_access_denied_message(string $section = ''): void
{
    $sectionName = $section !== '' ? ucfirst($section) : 'esta seção';
    
    echo '<div class="app-content">';
    echo '<div class="container-fluid">';
    echo '<div class="alert alert-danger mt-4" role="alert">';
    echo '<div class="d-flex align-items-center">';
    echo '<i class="bi bi-shield-exclamation me-3" style="font-size: 2rem;"></i>';
    echo '<div>';
    echo '<h4 class="alert-heading mb-2">Acesso Negado</h4>';
    echo '<p class="mb-0">';
    echo 'Você não tem permissão para acessar ' . htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8') . '. ';
    echo 'Entre em contato com o administrador da conta para solicitar acesso.';
    echo '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Verifica se todas as seções estão bloqueadas (usuário sem acesso algum)
 * 
 * @return bool True se não tem acesso a nenhuma seção
 */
function has_no_account_access(): bool
{
    $sections = ['overview', 'stores', 'plans', 'users', 'reports', 'support'];
    
    foreach ($sections as $section) {
        if (can_access_account_section($section)) {
            return false; // Tem acesso a pelo menos uma seção
        }
    }
    
    return true; // Não tem acesso a nada
}

/**
 * Obtém a primeira seção acessível para o usuário (para redirect)
 * 
 * @return string|null Nome da primeira seção acessível ou null se nenhuma
 */
function get_first_accessible_section(): ?string
{
    $sections = ['overview', 'stores', 'plans', 'users', 'reports', 'support'];
    
    foreach ($sections as $section) {
        if (can_access_account_section($section)) {
            return $section;
        }
    }
    
    return null;
}

/**
 * Mapa de seções para suas permissões específicas
 * Útil para documentação e debug
 * 
 * @return array
 */
function get_account_permissions_map(): array
{
    return [
        'overview' => [
            'main' => 'account.view_overview',
            'description' => 'Visão Geral das Lojas'
        ],
        'stores' => [
            'main' => 'account.view_stores',
            'description' => 'Gerenciar Lojas',
            'sub' => [
                'store_settings' => 'account.view_store_settings'
            ]
        ],
        'plans' => [
            'main' => 'account.view_plans',
            'description' => 'Assinatura & Planos',
            'sub' => [
                'change_plan' => 'account.change_plan',
                'view_billing' => 'account.view_billing'
            ]
        ],
        'users' => [
            'main' => 'account.view_users',
            'description' => 'Usuários da Conta',
            'sub' => [
                'view_permissions' => 'account.view_permissions',
                'manage_groups' => 'account.manage_groups'
            ]
        ],
        'reports' => [
            'main' => 'account.view_reports',
            'description' => 'Relatórios Consolidados'
        ],
        'support' => [
            'main' => 'account.view_support',
            'description' => 'Suporte - Meus Tickets',
            'sub' => [
                'create_ticket' => 'account.create_ticket',
                'reply_ticket' => 'account.reply_ticket'
            ]
        ]
    ];
}
