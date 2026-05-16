<?php 
ob_start();
session_start();
include ("../_init.php");

// Incluir funções SaaS
if (file_exists(__DIR__ . '/saas_limits_check.php')) {
    include_once(__DIR__ . '/saas_limits_check.php');
}

// Check, if user logged in or not
// If user is not logged in then return an alert message
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

// LOAD PRODUCT MODEL
$product_model = registry()->get('loader')->model('product');

if($request->server['REQUEST_METHOD'] == 'POST' && isset($request->get['action'])) 
{
  try {

    // Check permission
    if (user_group_id() != 1 && !has_permission('access', 'product_bulk_action') || DEMO) {
      throw new Exception(trans('error_bulk_permission'));
    }

    $action = $request->get['action'];

    // Check, if there has selected item or not
    if (!isset($request->post['selected']) || empty($request->post['selected'])) {
      throw new Exception(trans('error_no_selected'));
    }

    $Hooks->do_action('After_Product_Bulk_Action', $action);

    $ids = $request->post['selected'];
    if (!is_array($ids)) {
      $ids = array($ids);
    }
    
    $id_length = count($ids);

    // SaaS: Obter lojas do tenant atual
    $tenant_store_ids = function_exists('get_tenant_store_ids') ? get_tenant_store_ids() : array();
    
    switch ($action) {
      case 'delete':

          if (user_group_id() != 1 && !has_permission('access', 'delete_all_product')) {
            throw new Exception(sprintf(trans('error_delete_permission'), trans('text_product')));
          }
          
          $deleted_count = 0;
          $skipped_products = array();
          
          for ($i=0; $i < $id_length; $i++) { 
            $id = $ids[$i];
            $belongs_stores = $product_model->getBelongsStore($id);
            
            // SaaS: Filtrar apenas lojas do tenant
            $stores_to_check = array();
            $other_stores = array();
            
            foreach ($belongs_stores as $the_store) {
              if (empty($tenant_store_ids) || in_array($the_store['store_id'], $tenant_store_ids)) {
                $stores_to_check[] = $the_store;
              } else {
                $other_stores[] = $the_store['store_id'];
              }
            }
            
            // Verificar estoque apenas nas lojas do tenant
            $has_stock = false;
            foreach ($stores_to_check as $the_store) {
              $store_id = $the_store['store_id'];
              $stock_status = $product_model->isStockAvailable($id, $store_id);
              if ($stock_status) {
                $product_info = $product_model->getProduct($id);
                $skipped_products[] = ($product_info['p_name'] ?? "ID:$id") . " (estoque em " . store_field('name', $store_id) . ")";
                $has_stock = true;
                break;
              }
            }
            
            if ($has_stock) {
              continue; // Pular este produto, mas continuar com os outros
            }
            
            // SaaS: Se produto pertence a outras lojas fora do tenant, remover apenas associação
            if (!empty($other_stores)) {
              foreach ($stores_to_check as $the_store) {
                $stmt = db()->prepare("DELETE FROM `product_to_store` WHERE `product_id` = ? AND `store_id` = ?");
                $stmt->execute(array($id, $the_store['store_id']));
              }
            } else {
              $product_model->deleteProduct($id);
            }
            $deleted_count++;
          }
          
          if (!empty($skipped_products) && $deleted_count == 0) {
            throw new Exception("Não foi possível excluir. Produtos com estoque: " . implode(", ", array_slice($skipped_products, 0, 3)));
          }
          
          $success_message = trans('success_delete_all');
          if (!empty($skipped_products)) {
            $success_message .= " (" . count($skipped_products) . " produtos ignorados por terem estoque)";
          }

        break;
      case 'restore':
          
          // Check product restore permission
          if (user_group_id() != 1 && !has_permission('access', 'restore_all_product')) {
            throw new Exception(sprintf(trans('error_restore_permission'), trans('text_product')));
          }

          for ($i=0; $i < $id_length; $i++) { 
            $id = $ids[$i];

            if (DEMO && $id == 1) {
              continue;
            }

            // Update product status
            $product_model->updateStatus($id, 1, store_id());
          }

          $success_message = trans('success_restore_all');
        break;
      default:
        # code...
        break;
    }

    $Hooks->do_action('After_Product_Bulk_Action', $action);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => $success_message));
    exit();

  } catch (Exception $e) {

    $error_message = $e->getMessage();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $error_message));
    exit();
  }
}
