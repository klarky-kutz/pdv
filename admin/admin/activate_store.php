<?php
// Arquivo: activate_store.php
// Função: Ativa a loja na sessão e redireciona para o dashboard ou PDV.

ob_start();
session_start();

// Include robusto (evita depender do CWD)
$__initPath = __DIR__ . '/../_init.php';
if (file_exists($__initPath)) {
  include($__initPath);
} else {
  include ("_init.php");
}

// VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!$user->isLogged()) {
  redirect(root_url().'index.php');
  exit();
}

// Pega o ID da loja que veio da URL
if (isset($request->get['active_store_id'])) {
    
    $store_id = $request->get['active_store_id'];
    
    // VERIFICA SE O USUÁRIO TEM PERMISSÃO PARA ACESSAR ESTA LOJA
    // (A função get_stores() já faz isso, pois só retorna as lojas do usuário)
    $found = false;
    foreach (get_stores() as $store) {
        if ($store['store_id'] == $store_id) {
            $found = true;
            break;
        }
    }
    
    // Se o usuário tem permissão
    if ($found) {
        
        // SUCESSO! Define a loja ativa na sessão
        $session->data['store_id'] = $store_id;

        // Compatibilidade: garante que a loja ativada tenha um cliente padrão
        try {
          $defaults_path = ROOT . '/../saas/includes/ModernposDefaults.php';
          if (file_exists($defaults_path)) {
            require_once $defaults_path;
            if (class_exists('ModernposDefaults')) {
              ModernposDefaults::ensureDefaultCustomerForStore(db(), (int)$store_id, 'Cliente Balcão');
            }
          }
        } catch (Throwable $eDefaultsCustomer) {
          if (function_exists('error_log')) {
            error_log('[activate_store] Falha ao garantir cliente padrão para loja ' . $store_id . ': ' . $eDefaultsCustomer->getMessage());
          }
        }
        
        // Verifica se o usuário quer ir para o PDV (baseado no link)
        if (isset($request->get['redirect_to']) && $request->get['redirect_to'] == 'pos') {
            redirect(root_url().ADMINDIRNAME.'/pos.php');
        } else {
            // Se não, envia para o dashboard (Gestão), que é o padrão
            redirect(root_url().ADMINDIRNAME.'/dashboard.php');
        }
        exit();
    }
}

// Se não achou a loja ou não veio um ID, manda de volta para a seleção
redirect(root_url().'store_select.php');
exit();
?>