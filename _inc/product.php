<?php 
ob_start();
session_start();
include ("../_init.php");

// ========== INÍCIO: SaaS Limits Check ==========
include(__DIR__ . '/saas_limits_check.php');
// ========== FIM: SaaS Limits Check ==========

// Check, if user logged in or not
// If user is not logged in then return an alert message
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

// Check, if user has reading permission or not
// If user have not reading permission return an alert message
// NOTE: allow NEXT_PCODE endpoint even if user doesn't have read_product
$is_next_pcode = ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'NEXT_PCODE');
if (!$is_next_pcode && user_group_id() != 1 && !has_permission('access', 'read_product')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

// LOAD PRODUCT MODEL
$product_model = registry()->get('loader')->model('product');

// Validate post data
function validate_request_data($request) 
{
  // Validate product type
  if (!validateString($request->post['p_type'])) {
    throw new Exception(trans('error_p_type'));
  }

  // Validate product name
  if (!validateString($request->post['p_name'])) {
    throw new Exception(trans('error_product_name'));
  }

  // Validate product code
  if (empty($request->post['p_code'])) {
    throw new Exception(trans('error_product_code'));
  }

  // Validate barcode symbology
  if (!validateString($request->post['barcode_symbology'])) {
    throw new Exception(trans('error_barcode_symbology'));
  }

  // Validate category id
  if (!validateInteger($request->post['category_id'])) {
    throw new Exception(trans('error_category_name'));
  }

  // Validate sell price
  if (!validateFloat($request->post['sell_price']) || $request->post['sell_price'] <= 0) {
    throw new Exception(trans('error_product_price'));
  }

  if ($request->post['p_type'] == 'service') 
  {
    // Validate sell price
    if (!validateFloat($request->post['purchase_price']) && $request->post['purchase_price'] < 0) {
      throw new Exception(trans('error_product_cost'));
    }
  }

  if ($request->post['p_type'] != 'service') 
  {
    // Validate unit id
    if (!validateInteger($request->post['unit_id'])) {
      throw new Exception(trans('error_unit_name'));
    }

    // Validate supplier id
    if (!validateInteger($request->post['sup_id'])) {
      throw new Exception(trans('error_supplier_name'));
    }

    if (get_preference('expiry_yes'))
    {
      // Validate expired date
      if (!isItValidDate($request->post['e_date'])) {
        throw new Exception(trans('error_expired_date'));
      }

      // expired date must be greater than today
      if (!validateExpireDate($request->post['e_date'])) {
        throw new Exception(trans('error_expired_date_below'));
      }
    }
  }
  
  // Validate product tax
  if (!validateInteger($request->post['taxrate_id'])) {
    throw new Exception(trans('error_product_tax'));
  } 

  // Validate store
  if (!isset($request->post['product_store']) || empty($request->post['product_store'])) {
    throw new Exception(trans('error_store'));
  }

  // Validate status
  if (!is_numeric($request->post['status'])) {
    throw new Exception(trans('error_status'));
  }

  // Validate sort order
  if (!is_numeric($request->post['sort_order'])) {
    throw new Exception(trans('error_sort_order'));
  }
}

// Check product existance by id (filtrado por tenant/lojas)
function validate_existance($request, $p_id = 0)
{
  $store_ids = get_tenant_store_ids();
  
  if (!empty($store_ids)) {
    // Modo SaaS: verificar apenas produtos das lojas do tenant
    $placeholders = implode(',', array_fill(0, count($store_ids), '?'));
    $sql = "SELECT DISTINCT p.p_id FROM `products` p 
            INNER JOIN `product_to_store` p2s ON p.p_id = p2s.product_id 
            WHERE p.`p_name` = ? AND p.`p_id` != ? AND p2s.`store_id` IN ($placeholders)";
    $params = array_merge(array($request->post['p_name'], $p_id), $store_ids);
    $statement = db()->prepare($sql);
    $statement->execute($params);
  } else {
    // Modo legacy: validação global
    $statement = db()->prepare("SELECT * FROM `products` WHERE `p_name` = ? AND `p_id` != ?");
    $statement->execute(array($request->post['p_name'], $p_id));
  }
  
  if ($statement->rowCount() > 0) {
    throw new Exception(trans('error_product_exist'));
  }
}

// Check product code (filtrado por tenant/lojas)
function validate_product_code($request, $p_id = NULL)
{
  $store_ids = get_tenant_store_ids();
  
  if (!empty($store_ids)) {
    // Modo SaaS: verificar apenas produtos das lojas do tenant
    $placeholders = implode(',', array_fill(0, count($store_ids), '?'));
    
    if ($p_id) {
      $sql = "SELECT DISTINCT p.p_id FROM `products` p 
              INNER JOIN `product_to_store` p2s ON p.p_id = p2s.product_id 
              WHERE p.`p_code` = ? AND p.`p_id` != ? AND p2s.`store_id` IN ($placeholders)";
      $params = array_merge(array($request->post['p_code'], $p_id), $store_ids);
    } else {
      $sql = "SELECT DISTINCT p.p_id FROM `products` p 
              INNER JOIN `product_to_store` p2s ON p.p_id = p2s.product_id 
              WHERE p.`p_code` = ? AND p2s.`store_id` IN ($placeholders)";
      $params = array_merge(array($request->post['p_code']), $store_ids);
    }
    $statement = db()->prepare($sql);
    $statement->execute($params);
  } else {
    // Modo legacy: validação global
    if ($p_id) {
      $statement = db()->prepare("SELECT * FROM `products` WHERE `p_code` = ? AND `p_id` != ?");
      $statement->execute(array($request->post['p_code'], $p_id));
    } else {
      $statement = db()->prepare("SELECT * FROM `products` WHERE `p_code` = ?");
      $statement->execute(array($request->post['p_code']));
    }
  }
  
  if ($statement->rowCount() > 0) {
    throw new Exception(trans('error_product_code_exist'));
  }
}

// Generate next product code (sequential or random with prefix)
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['action_type']) && $request->get['action_type'] == 'NEXT_PCODE')
{
  try {
    // Check permission (must be able to create product)
    if (user_group_id() != 1 && !has_permission('access', 'create_product')) {
      throw new Exception(trans('error_create_permission'));
    }

    $mode = isset($request->get['mode']) ? strtolower($request->get['mode']) : 'seq';
    $prefix = isset($request->get['prefix']) ? trim($request->get['prefix']) : '7898';

    // Keep digits only
    $prefix = preg_replace('/\D+/', '', $prefix);
    if ($prefix === '') {
      $prefix = '7898';
    }

    // Default length (EAN13-like)
    $target_len = 13;
    if (strlen($prefix) >= $target_len) {
      // If prefix is already long, just keep it
      $target_len = strlen($prefix);
    }

    if ($mode === 'random') {
      $tries = 0;
      $max_tries = 30;
      $candidate = null;

      while ($tries < $max_tries) {
        $tries++;
        $remain = $target_len - strlen($prefix);
        $rand = '';
        for ($i = 0; $i < $remain; $i++) {
          $rand .= (string)random_int(0, 9);
        }
        $candidate = $prefix . $rand;

        $check = db()->prepare("SELECT 1 FROM `products` WHERE `p_code` = ? LIMIT 1");
        $check->execute(array($candidate));
        if (!$check->fetchColumn()) {
          break;
        }
        $candidate = null;
      }

      if (!$candidate) {
        // Fallback to sequential if random couldn't find an available code
        $mode = 'seq';
      } else {
        header('Content-Type: application/json');
        echo json_encode(array('mode' => 'random', 'p_code' => $candidate, 'prefix' => $prefix, 'tries' => $tries));
        exit();
      }
    }

    // Sequential mode: find last numeric code starting with prefix, then +1
    $like = $prefix . '%';
    $stmt = db()->prepare("SELECT MAX(CAST(`p_code` AS UNSIGNED)) AS max_code FROM `products` WHERE `p_code` REGEXP '^[0-9]+$' AND `p_code` LIKE ?");
    $stmt->execute(array($like));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_code = isset($row['max_code']) ? (int)$row['max_code'] : 0;

    if ($max_code <= 0) {
      // Start at prefix + zeros (EAN13-like)
      $base = $prefix . str_repeat('0', max(0, $target_len - strlen($prefix)));
      $next = (int)$base + 1;
    } else {
      $next = $max_code + 1;
    }

    header('Content-Type: application/json');
    echo json_encode(array('mode' => 'seq', 'p_code' => (string)$next, 'prefix' => $prefix, 'max_code' => (string)$max_code));
    exit();

  } catch (Exception $e) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Get products for selector modal
if ($request->server['REQUEST_METHOD'] == 'GET' && isset($request->get['get_products_for_selector']))
{
  try {
    $where_clauses = array();
    $params = array();
    
    // Filtro por categoria
    if (!empty($request->get['category_id'])) {
      $where_clauses[] = 'p.category_id = ?';
      $params[] = $request->get['category_id'];
    }
    
    // Filtro por fornecedor
    if (!empty($request->get['sup_id'])) {
      $where_clauses[] = 'p2s.sup_id = ?';
      $params[] = $request->get['sup_id'];
    }
    
    // Filtro por store
    $where_clauses[] = 'p2s.store_id = ?';
    $params[] = store_id();
    
    // Apenas produtos ativos e não na lixeira
    $where_clauses[] = 'p2s.status = 1';
    $where_clauses[] = '(p.is_deleted IS NULL OR p.is_deleted = 0)';
    
    $where = implode(' AND ', $where_clauses);
    
    $sql = "SELECT 
              p.p_id, 
              p.p_code, 
              p.p_name, 
              p.p_image,
              p.category_id,
              p2s.sup_id,
              p2s.quantity,
              p2s.purchase_price,
              p2s.sell_price
            FROM products p
            INNER JOIN product_to_store p2s ON p.p_id = p2s.product_id
            WHERE $where
            ORDER BY p.p_name ASC
            LIMIT 500";
    
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $products = $statement->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(array(
      'success' => true,
      'data' => $products,
      'total' => count($products)
    ));
    exit();
    
  } catch (Exception $e) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Quick Create product (simplified - for POS)
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'QUICKCREATE')
{
  try {

    // Check create permission
    if (user_group_id() != 1 && !has_permission('access', 'create_product')) {
      throw new Exception(trans('error_create_permission'));
    }
    
    // ========== INÍCIO: Verificação de Limite SaaS ==========
    if (function_exists('can_create_product') && !can_create_product()) {
      $limitInfo = function_exists('get_limit_info') ? get_limit_info('products') : array('limit' => 'ilimitado');
      throw new Exception('Limite de produtos atingido! Seu plano permite até ' . $limitInfo['limit'] . ' produtos. Faça upgrade do plano para continuar.');
    }
    // ========== FIM: Verificação de Limite SaaS ==========

    // Validate product name
    if (!validateString($request->post['p_name'])) {
      throw new Exception(trans('error_product_name'));
    }

    // Validate product code
    if (empty($request->post['p_code'])) {
      throw new Exception(trans('error_product_code'));
    }

    // Validate category id
    if (!validateInteger($request->post['category_id'])) {
      throw new Exception(trans('error_category_name'));
    }

    // Validate sell price
    if (!validateFloat($request->post['sell_price']) || $request->post['sell_price'] <= 0) {
      throw new Exception(trans('error_product_price'));
    }

    // Check product code doesn't already exist
    validate_product_code($request);
    
    // Check product name doesn't already exist
    validate_existance($request);

    // Prepare data with defaults
    $post_data = array(
      'p_name' => $request->post['p_name'],
      'p_code' => $request->post['p_code'],
      'p_type' => isset($request->post['p_type']) ? $request->post['p_type'] : 'standard',
      'category_id' => $request->post['category_id'],
      'sell_price' => $request->post['sell_price'],
      'purchase_price' => isset($request->post['purchase_price']) && !empty($request->post['purchase_price']) 
        ? $request->post['purchase_price'] 
        : $request->post['sell_price'],
      'unit_id' => isset($request->post['unit_id']) && !empty($request->post['unit_id']) 
        ? $request->post['unit_id'] 
        : get_default_unit_id(),
      'sup_id' => isset($request->post['sup_id']) && !empty($request->post['sup_id']) 
        ? $request->post['sup_id'] 
        : get_default_supplier_id(),
      'barcode_symbology' => isset($request->post['barcode_symbology']) ? $request->post['barcode_symbology'] : 'code128',
      'taxrate_id' => isset($request->post['taxrate_id']) && !empty($request->post['taxrate_id']) 
        ? $request->post['taxrate_id'] 
        : get_default_taxrate_id(),
      'tax_method' => isset($request->post['tax_method']) ? $request->post['tax_method'] : 'inclusive',
      'e_date' => date('Y-m-d', strtotime('+1 year')),
      'product_store' => isset($request->post['product_store']) ? $request->post['product_store'] : array(store_id()),
      'status' => 1,
      'sort_order' => 0,
      'quantity' => 0
    );

    $Hooks->do_action('Before_Create_Product', $request);
  
    // Insert product into database    
    $product_id = $product_model->addProduct($post_data);

    // get product info
    $product = $product_model->getProduct($product_id);

    $Hooks->do_action('After_Create_Product', $product);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => 'Produto cadastrado com sucesso!', 'id' => $product_id, 'product' => $product));
    exit();

  } catch (Exception $e) {
    
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Create product
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'CREATE')
{
  try {

    // Check create permission
    if (user_group_id() != 1 && !has_permission('access', 'create_product')) {
      throw new Exception(trans('error_create_permission'));
    }
    
    // ========== INÍCIO: Verificação de Limite SaaS ==========
    if (!can_create_product()) {
      $limitInfo = get_limit_info('products');
      throw new Exception('Limite de produtos atingido! Seu plano permite até ' . $limitInfo['limit'] . ' produtos. Faça upgrade do plano para continuar.');
    }
    // ========== FIM: Verificação de Limite SaaS ==========

    // Validate post data
    validate_request_data($request);

    // Validate product code
    validate_product_code($request);
    
    // Validate existance
    validate_existance($request);

    $Hooks->do_action('Before_Create_Product', $request);
  
    // Insert product into database    
    $product_id = $product_model->addProduct($request->post);

    // get product info
    $product = $product_model->getProduct($product_id);

    $Hooks->do_action('After_Create_Product', $product);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_product_created'), 'id' => $product_id, 'product' => $product));
    exit();

  } catch (Exception $e) {
    
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
} 

// Update product
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'UPDATE')
{
  try {

    // Check update permission
    if (user_group_id() != 1 && !has_permission('access', 'update_product')) {
      throw new Exception(trans('error_update_permission'));
    }

    // Validate product id
    if (!validateInteger($request->post['p_id'])) {
      throw new Exception(trans('error_product_id'));
    }

    // Validate sell price
    if (!validateFloat($request->post['sell_price']) || $request->post['sell_price'] <= 0) {
      throw new Exception(trans('error_product_price'));
    }

    $p_id = $request->post['p_id'];

    // Validate post data
    validate_request_data($request);

    // Validate product code
    validate_product_code($request, $p_id);

    // Validate existance
    validate_existance($request, $p_id);

    $Hooks->do_action('Before_Update_Product', $p_id);
    
    // Edit product        
    $product_model->editProduct($p_id, $request->post);

    $Hooks->do_action('After_Update_Product', $p_id);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_product_updated'), 'id' => $p_id));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
} 

// Delete product
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'DELETE')
{
  try {

    // Check delete product permission
    if (user_group_id() != 1 && !has_permission('access', 'delete_product')) {
      throw new Exception(trans('error_delete_permission'));
    }

    // Validate product id
    if (!validateInteger($request->post['p_id'])) {
      throw new Exception(trans('error_product_id'));
    }

    $p_id = $request->post['p_id'];

    // validte delete action
    if (empty($request->post['delete_action'])) {
      throw new Exception(trans('error_delete_action'));
    }

    if ($request->post['delete_action'] == 'insert_to' && empty($request->post['p_id'])) {
      throw new Exception(trans('error_delete_product_name'));
    }

    // Fetch product by id
    $product = $product_model->getProduct($p_id);

    // Check product exist or not
    if (!isset($product['p_id'])) {
      throw new Exception(trans('text_not_found'));
    }

    $Hooks->do_action('Before_Delete_Product', $request);

    $action_type = $request->post['delete_action'];

    switch ($action_type) {
      case 'soft_delete':

        $product_model->updateStatus($p_id, 0);
        $message = trans('text_soft_delete');

        break;
      case 'delete_all':

        // SaaS: Obter apenas as lojas do tenant atual
        $tenant_store_ids = get_tenant_store_ids();
        $belongs_stores = $product_model->getBelongsStore($p_id);
        
        // Filtrar apenas lojas que pertencem ao tenant (ou usar todas se não for SaaS)
        $stores_to_check = array();
        foreach ($belongs_stores as $the_store) {
          if (empty($tenant_store_ids) || in_array($the_store['store_id'], $tenant_store_ids)) {
            $stores_to_check[] = $the_store;
          }
        }
        
        // Verificar estoque apenas nas lojas do tenant
        foreach ($stores_to_check as $the_store) {
          $store_id = $the_store['store_id'];
          $stock_status = $product_model->isStockAvailable($p_id, $store_id);
          if ($stock_status) {
            throw new Exception("Não é possível excluir. Há estoque disponível na loja: " . store_field('name', $store_id));
          }
        }
        
        // SaaS: Se produto pertence a outras lojas fora do tenant, remover apenas associação
        $other_stores = array();
        foreach ($belongs_stores as $the_store) {
          if (!empty($tenant_store_ids) && !in_array($the_store['store_id'], $tenant_store_ids)) {
            $other_stores[] = $the_store['store_id'];
          }
        }
        
        if (!empty($other_stores)) {
          // Produto pertence a outras lojas, remover apenas das lojas do tenant
          foreach ($stores_to_check as $the_store) {
            $stmt = db()->prepare("DELETE FROM `product_to_store` WHERE `product_id` = ? AND `store_id` = ?");
            $stmt->execute(array($p_id, $the_store['store_id']));
          }
          $message = trans('text_delete') . ' (removido das suas lojas)';
        } else {
          // Produto pertence apenas às lojas do tenant, pode deletar completamente
          $product_model->deleteProduct($p_id); 
          $message = trans('text_delete');
        }

        break;
    }

    $Hooks->do_action('After_Delete_Product', $product);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => $message, 'id' => $p_id, 'action_type' => $action_type));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Product create form
if (isset($request->get['action_type']) && $request->get['action_type'] == 'CREATE') 
{
  include 'template/product_create_form.php';
  exit();
}

// Product edit form
if (isset($request->get['p_id']) AND isset($request->get['action_type']) && $request->get['action_type'] == 'EDIT') 
{
  $product = $product_model->getProduct($request->get['p_id']);
  $preference = valid_unserialize($product['preference']);
  include 'template/product_form.php';
  exit();
}

// Product delete form
if (isset($request->get['p_id']) AND isset($request->get['action_type']) && $request->get['action_type'] == 'DELETE') 
{
  $product = $product_model->getProduct($request->get['p_id']);
  include 'template/product_del_form.php';
  exit();
}

// Product view template
if (isset($request->get['p_id']) AND isset($request->get['action_type']) && $request->get['action_type'] == 'VIEW') 
{
  $product = $product_model->getProduct($request->get['p_id']);
  include 'template/product_view_form.php';
  exit();
}

/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_Product_List');

$where_query = 'p2s.store_id = ' . store_id();
 
// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `products`.`p_id`) AS p_id,
GROUP_CONCAT(DISTINCT `products`.`p_type`) AS p_type,
GROUP_CONCAT(DISTINCT `products`.`p_code`) AS p_code,
GROUP_CONCAT(DISTINCT `products`.`hsn_code`) AS hsn_code,
GROUP_CONCAT(DISTINCT `products`.`barcode_symbology`) AS barcode_symbology,
GROUP_CONCAT(DISTINCT `products`.`p_name`) AS p_name,
GROUP_CONCAT(DISTINCT `products`.`category_id`) AS category_id,
GROUP_CONCAT(DISTINCT `products`.`unit_id`) AS unit_id,
GROUP_CONCAT(DISTINCT `products`.`p_image`) AS p_image,
GROUP_CONCAT(DISTINCT `products`.`description`) AS description,
GROUP_CONCAT(DISTINCT `p2s`.`id`) AS id,
GROUP_CONCAT(DISTINCT `p2s`.`product_id`) AS product_id,
GROUP_CONCAT(DISTINCT `p2s`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `p2s`.`purchase_price`) AS purchase_price,
GROUP_CONCAT(DISTINCT `p2s`.`sell_price`) AS sell_price,
GROUP_CONCAT(DISTINCT `p2s`.`quantity_in_stock`) AS quantity_in_stock,
GROUP_CONCAT(DISTINCT `p2s`.`alert_quantity`) AS alert_quantity,
GROUP_CONCAT(DISTINCT `p2s`.`sup_id`) AS sup_id,
GROUP_CONCAT(DISTINCT `p2s`.`brand_id`) AS brand_id,
GROUP_CONCAT(DISTINCT `p2s`.`box_id`) AS box_id,
GROUP_CONCAT(DISTINCT `p2s`.`taxrate_id`) AS taxrate_id,
GROUP_CONCAT(DISTINCT `p2s`.`tax_method`) AS tax_method,
GROUP_CONCAT(DISTINCT `p2s`.`preference`) AS preference,
GROUP_CONCAT(DISTINCT `p2s`.`e_date`) AS e_date,
GROUP_CONCAT(DISTINCT `p2s`.`p_date`) AS p_date,
GROUP_CONCAT(DISTINCT `p2s`.`status`) AS status,
GROUP_CONCAT(DISTINCT `p2s`.`sort_order`) AS sort_order,
GROUP_CONCAT(DISTINCT suppliers.sup_mobile) AS sup_mobile, GROUP_CONCAT(DISTINCT suppliers.sup_name) AS supplier, GROUP_CONCAT(DISTINCT boxes.box_name) AS box_name FROM products 
  LEFT JOIN product_to_store p2s ON (products.p_id = p2s.product_id) 
  LEFT JOIN suppliers ON (p2s.sup_id = suppliers.sup_id) 
  LEFT JOIN boxes ON (p2s.box_id = boxes.box_id) 
  WHERE $where_query GROUP BY p_id
  ORDER BY p_date DESC
  ) as products";
 
// Table's primary key
$primaryKey = 'p_id';
$columns = array(
  array(
    'db' => 'p_id',
    'dt' => 'DT_RowId',
    'formatter' => function( $d ) {
        return 'row_'.$d;
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'select' ,
    'formatter' => function($d, $row) {
        return '<input type="checkbox" name="selected[]" value="' . $row['p_id'] . '">';
    }
  ),
  array( 'db' => 'p_id', 'dt' => 'p_id' ),
  array( 
    'db' => 'p_image',   
    'dt' => 'p_image' ,
    'formatter' => function($d, $row) {

      $img = '';
      if (isset($row['p_image']) && ((FILEMANAGERPATH && is_file(FILEMANAGERPATH.$row['p_image']) && file_exists(FILEMANAGERPATH.$row['p_image'])) || (is_file(DIR_STORAGE . 'products' . $row['p_image']) && file_exists(DIR_STORAGE . 'products' . $row['p_image'])))) {
        $root_url = FILEMANAGERURL ? FILEMANAGERURL : root_url();
        $img .= '<img  src="'.$root_url.'/'.$row['p_image'].'" width="80" height="80">';
      } else {

        $img .= '<img src="../assets/itsolution24/img/noimage.jpg" width="80" height="80">';
      }
      return $img;
    }
  ),
  array( 'db' => 'p_type', 'dt' => 'p_type' ),
  array( 'db' => 'p_code', 'dt' => 'p_code' ),
  array( 
    'db' => 'p_name',   
    'dt' => 'p_name' ,
    'formatter' => function($d, $row) {
        return html_entity_decode($row['p_name']);
    }
  ),
  array( 'db' => 'category_id',  'dt' => 'category_id' ),
  array( 'db' => 'sup_id',  'dt' => 'sup_id' ),
  array( 
    'db' => 'supplier',   
    'dt' => 'supplier' ,
    'formatter' => function($d, $row) {
        return "<a href=\"supplier_profile.php?sup_id=" . $row['sup_id'] . "\">" . $row['supplier'] . "</a>";
    }
  ),
  array( 'db' => 'sup_mobile',  'dt' => 'supplier_mobile' ),
  array( 'db' => 'box_id',  'dt' => 'box_id' ),
  array( 
    'db' => 'box_name',   
    'dt' => 'box' ,
    'formatter' => function($d, $row) {
        return "<a href=\"box.php?box_id=" . $row['box_id'] . "&box_name=" . $row['box_name'] . "\">" . $row['box_name'] . "</a>";
    }
  ),
  array( 
    'db' => 'category_id',   
    'dt' => 'category_name' ,
    'formatter' => function($d, $row) {
        return get_the_category($row['category_id'], 'category_name');
    }
  ),
  array( 
    'db' => 'unit_id',   
    'dt' => 'unit' ,
    'formatter' => function($d, $row) {
        return get_the_unit($row['unit_id'], 'unit_name');
    }
  ),
  array( 
    'db' => 'quantity_in_stock',   
    'dt' => 'quantity_in_stock' ,
    'formatter' => function($d, $row) {
      if ($row['p_type'] == 'service') {
        return '-';
      }
      return currency_format($row['quantity_in_stock']) . ' ' . get_the_unit($row['unit_id'], 'unit_name');
    }
  ),
  array( 
    'db' => 'purchase_price',   
    'dt' => 'purchase_price' ,
    'formatter' => function($d, $row) {
      if ($row['p_type'] == 'service') {
        return '-';
      }
      return currency_format($row['purchase_price']);
    }
  ),
  array( 
    'db' => 'sell_price',   
    'dt' => 'sell_price' ,
    'formatter' => function($d, $row) {
      return currency_format($row['sell_price']);
    }
  ),
  array( 'db' => 'tax_method',   'dt' => 'tax_method' ),
  array( 
    'db' => 'taxrate_id',   
    'dt' => 'taxrate' ,
    'formatter' => function($d, $row) {
      $taxrate = get_the_taxrate($row['taxrate_id']);
      if ($taxrate) {
        return $taxrate['taxrate'];
      }
      return 0;
    }
  ),
  array( 
    'db' => 'taxrate_id',   
    'dt' => 'purchase_tax_amount' ,
    'formatter' => function($d, $row) {
      $taxrate = get_the_taxrate($row['taxrate_id']);
      if ($taxrate) {
        return currency_format(($taxrate['taxrate'] / 100) * $row['purchase_price']);
      }
      return '0.00';
    }
  ),
  array( 
    'db' => 'e_date',   
    'dt' => 'e_date' ,
    'formatter' => function($d, $row) {
      return $row['e_date'];
    }
  ),
  array( 
    'db' => 'status',   
    'dt' => 'status',
    'formatter' => function($d, $row) {
      return $row['status'] 
        ? '<span class="label label-success">'.trans('text_active').'</span>' 
        : '<span class="label label-warning">' .trans('text_inactive').'</span>';
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'danger_stock',
    'formatter' => function($d, $row) {
      return "<span class=\"label label-warning\">" . currency_format($row['quantity_in_stock']) . "</span>";
    }
  ),
  array( 
    'db' => 'sup_id',   
    'dt' => 'btn_supplier_view',
    'formatter' => function($d, $row) {
      return "<a href=\"supplier_profile.php?sup_id=" . $row['sup_id'] . "&sup_name=" . $row['supplier'] . "&purchase=1\" class=\"btn btn-sm btn-block btn-info\"><i class=\"fa fa-plus\"></i></a>";
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'view_btn' ,
    'formatter' => function($d, $row) {
      return '<a class="btn btn-sm btn-block btn-info" title="'.trans('button_view').'" href="product_details.php?p_id='.$row['p_id'].'"><i class="fa fa-eye"></i></a>';
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'edit_btn' ,
    'formatter' => function($d, $row) {
      // Bloquear edição de itens globais/demo
      if ((DEMO && $row['p_id'] == 1) || !$row['status'] || is_global_item($row['p_name'], 'product')) {          
        return '<button class="btn btn-sm btn-block btn-default" type="button" disabled title="Item padrão - não editável"><i class="fa fa-pencil"></i></button>';
      }
      if ($row['status']) {
        return '<button class="btn btn-sm btn-block btn-primary edit-product" type="button" title="'.trans('button_edit').'"><i class="fa fa-pencil"></i></button>';
      }
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'purchase_btn' ,
    'formatter' => function($d, $row) {
      if ($row['status'] && $row['p_type'] != 'service') {
        $o = '<a href="purchase.php?box_state=open&sup_id='.$row['sup_id'].'&p_code='.$row['p_code'].'" class="btn btn-block btn-sm btn-success purchase-product" title="'.trans('button_purchase_product').'"><i class="fa fa-shopping-cart"></i></a>';
        $o .= '<a href="javascript:;" class="btn btn-block btn-sm btn-warning button-stock-adjustment" title="'.trans('button_stock_adjustment').'"><i class="fa fa-plus"></i></a>';
        return $o;
      }
      return '<button class="btn btn-sm btn-block btn-default" type="button" disabled><i class="fa fa-shopping-cart"></i></button>';
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'barcode_btn' ,
    'formatter' => function($d, $row) {
      if ($row['status']) {
        return '<a href="barcode_print.php?p_code='.$row['p_code'].'" class="btn btn-sm btn-block btn-primary print-barcode" type="a" title="'.trans('button_barcode').'"><i class="fa fa-barcode"></i></a>';
      }

      return '<button class="btn btn-sm btn-block btn-default" type="button" disabled><i class="fa fa-barcode"></i></button>';
    }
  ),
  array( 
    'db' => 'p_id',   
    'dt' => 'delete_btn' ,
    'formatter' => function($d, $row) {
      // Bloquear exclusão de itens globais/demo
      if ((DEMO && $row['p_id'] == 1) || !$row['status'] || is_global_item($row['p_name'], 'product')) {          
        return '<button class="btn btn-sm btn-block btn-default" type="button" disabled title="Item padrão - não removível"><i class="fa fa-trash"></i></button>';
      }
      return '<button class="btn btn-sm btn-block btn-danger product-delete" type="button" title="'.trans('button_delete').'"><i class="fa fa-trash"></i></button>';
    }
  ),
);
 
$where_query = '1=1';
if (isset($request->get['location']) && $request->get['location'] == 'trash') {
  $location = (int)$request->get['location'];
  $where_query .= ' AND status = 0';
} else {
  $where_query .= ' AND status = 1';
}
if (isset($request->get['sup_id']) && $request->get['sup_id'] != 'null') {
  $sup_id = (int)$request->get['sup_id'];
  $where_query .= ' AND sup_id = ' . $sup_id;
}
if (isset($request->get['stock_query']) && $request->get['stock_query']) {
  $where_query .= " AND p_type = 'standard' AND (quantity_in_stock <= alert_quantity)";
}
if (isset($request->get['expired_query']) && $request->get['expired_query'] != 'null') {
  if (isset($request->get['type']) && $request->get['type'] == 'expiring_soon' && get_preference('expiry_yes') && get_preference('expiring_soon_alert_days') > 0) {
    $day = (int)get_preference('expiring_soon_alert_days');
    $date = date('Y-m-d', strtotime(date('Y-m-d').' + '.$day.' days'));
    $where_query .= " AND e_date > '".date('Y-m-d')."' AND e_date < '".$date."'";
  } else {
    $where_query .= ' AND e_date <= NOW()';
  }
}

echo json_encode(
  SSP::complex($request->get, $sql_details, $table, $primaryKey, $columns, null, $where_query)
);

$Hooks->do_action('After_Showing_Product_List');

/**
 *===================
 * END DATATABLE
 *===================
 */