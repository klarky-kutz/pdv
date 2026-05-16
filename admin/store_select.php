<?php
ob_start();
session_start();
include ("_init.php"); // Carrega o _init.php para sabermos quem é o usuário

// =======================================================
// LÓGICA DO "ROTEADOR" DE PAINEL
// =======================================================

// Verifica se o usuário está logado
if (!$user->isLogged()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// DEBUG: Verificar group_id do usuário
$current_group_id = user_group_id();
$user_info = [
    'user_id' => user_id(),
    'username' => $user->getUsername(),
    'group_id' => $current_group_id,
    'tenant_id' => isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 'N/A'
];

// DEBUG: Desativado - descomentar linha abaixo se precisar debugar novamente
// die('<pre>DEBUG INFO:\\n' . print_r($user_info, true) . '\\n\\nEsperado: group_id = 1 OU permissão account.view_overview\\n</pre>');

// Verifica se o usuário é um Administrador (group_id = 1) OU Owner do tenant
// OU se tem a permissão account.view_overview (RBAC)
$isTenantOwner = (function_exists('is_tenant_owner') && is_tenant_owner());

if ($current_group_id == 1 || $isTenantOwner || has_permission('access', 'account.view_overview')) {
    
    // --- O USUÁRIO PODE ACESSAR O PAINEL COMPLETO ---

    // 1. Limpa a sessão da loja (para forçar a ver o painel da conta)
    if (isset($session->data['store_id'])) {
        unset($session->data['store_id']);
    }

    // 2. Carrega o NOVO Painel de Admin (AdminLTE 4)
    include('store_select_admin.php');

} else {
    
    // --- O USUÁRIO É CAIXA, VENDEDORA, ETC. ---
    
    // SaaS Multi-Tenant: Verificar permissão para trocar de loja
    // NOTA: Admins (group_id = 1) sempre podem acessar
    if (user_group_id() != 1 && !has_permission('access', 'switch_store')) {
        // Usuário sem permissão para trocar de loja
        // Redirecionar para dashboard da loja atual (se houver) ou exibir erro
        if (isset($session->data['store_id']) && $session->data['store_id'] > 0) {
            // Tem loja ativa, redirecionar para o dashboard
            redirect(root_url() . ADMINDIRNAME . '/dashboard.php');
        } else {
            // Não tem loja ativa, exibir erro e fazer logout
            $session->data['error'] = 'Você não tem permissão para acessar a página de seleção de lojas.';
            redirect(root_url() . 'logout.php');
        }
        exit();
    }
    
    // 1. Carrega a lista BÁSICA (o arquivo original do sistema)
    include('store_select_basic.php');
}

// Garante que o script pare aqui
exit();

?>