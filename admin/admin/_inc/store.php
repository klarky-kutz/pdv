<?php 
ob_start();
session_start();
include ("../_init.php");

// Check, if your logged in or not
// If user is not logged in then return an alert message
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

// Check, if user has reading permission or not
// If user have not reading permission return an alert message
if (user_group_id() != 1 && !has_permission('access', 'read_store')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

// LOAD STORE MODEL
$store_model = registry()->get('loader')->model('store');

// Validate post data
function validate_request_data($request) 
{

  // Validate store name
  if (!validateString($request->post['name'])) {
    throw new Exception(trans('error_name'));
  }

  // Validate store code name
  if (!validateString($request->post['code_name'])) {
    throw new Exception(trans('error_code_name'));
  }

  // Validate store mobile number 
  if (empty($request->post['mobile']) || !valdateMobilePhone($request->post['mobile'])) {
    throw new Exception(trans('error_mobile'));
  }

  // Validate store email
  if (!validateEmail($request->post['email'])) {
    throw new Exception(trans('error_email'));
  }

  // Validate store country
  if (!validateString($request->post['country'])) {
    throw new Exception(trans('error_country'));
  }

    // Validate store zip code
  if (empty($request->post['zip_code'])) {
    throw new Exception(trans('error_zip_code'));
  }

    // Validate store cashiar name
  if (!validateInteger($request->post['cashier_id'])) {
    throw new Exception(trans('error_cashier_name'));
  }

    // Validate store address
  if (!validateString($request->post['address'])) {
    throw new Exception(trans('error_addreess'));
  }

  // Validate store sort_order
  if (is_null($request->post['sort_order'])) {
    throw new Exception(trans('error_position'));
  }

  // Validate timezone
  if (!isset($request->post['preference']['timezone']) || !validateString($request->post['preference']['timezone'])) {
    throw new Exception(trans('error_preference_timezone'));
  }

  // Validate receipt printer
  if ($request->post['remote_printing'] == 1 && !$request->post['receipt_printer']) {
    throw new Exception(trans('error_receipt_printer'));
  }

  // Validate invoice edit lifespan
  if ($request->post['preference']['invoice_edit_lifespan'] < 0 || !is_numeric($request->post['preference']['invoice_edit_lifespan'])) {
    throw new Exception(trans('error_preference_invoice_edit_lifespan'));
  }

  // Validate invoice delete lifespan
  if ($request->post['preference']['invoice_delete_lifespan'] < 0 || !is_numeric($request->post['preference']['invoice_delete_lifespan'])) {
    throw new Exception(trans('error_preference_invoice_delete_lifespan'));
  }

  // Validate invoice edit lifespan unit
  if (!isset($request->post['preference']['invoice_edit_lifespan_unit']) || !validateString($request->post['preference']['invoice_edit_lifespan_unit'])) {
    throw new Exception(trans('error_preference_invoice_edit_lifespan_unit'));
  }

  // Validate after sell page
  if (!validateString($request->post['preference']['after_sell_page'])) {
    throw new Exception(trans('error_preference_after_sell_page'));
  }

  // Validate tax
  if (!is_numeric($request->post['preference']['tax']) || $request->post['preference']['tax'] < 0) {
    throw new Exception(trans('error_preference_tax'));
  }

  // Validate datatable item limit
  if (!is_numeric($request->post['preference']['datatable_item_limit']) || $request->post['preference']['datatable_item_limit'] < 0) {
    throw new Exception(trans('error_preference_datatable_item_limit'));
  }

  // Validate receipt tempala
  if (isset($request->post['preference']['receipt_template']) && !validateString($request->post['preference']['receipt_template'])) {
    throw new Exception(trans('error_preference_receipt_template'));
  }
}

// Check store existance by id
function validate_existance($request, $id = 0)
{
  // Check, if store name exist or not
  $statement = db()->prepare("SELECT * FROM `stores` WHERE `name` = ? AND `store_id` != ?");
  $statement->execute(array($request->post['name'], $id));
  if ($statement->rowCount() > 0) {
    throw new Exception(trans('error_store_exist'));
  }
}

// Create store
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'CREATE')
{
  try {

    // Check create permission
    if (user_group_id() != 1 && !has_permission('access', 'create_store')) {
      throw new Exception(trans('error_read_permission'));
    }
    
    // Validate post data
    validate_request_data($request);

    // Validate logo url
    if (!isset($request->post['logo']) || !validateString($request->post['logo'])) {
      $request->post['logo'] = null;
    }

    // Validate favicon url
    if (!isset($request->post['favicon']) || !validateString($request->post['favicon'])) {
      $request->post['favicon'] = null;
    }

    // Validate currency
    if (empty($request->post['currency'])) {
      throw new Exception(trans('error_currency'));
    }

    // Se nenhum método de pagamento foi enviado (ex.: criação via /conta/lojas),
    // aplica um conjunto padrão de pmethods baseado em code_name.
    if (empty($request->post['pmethod']) && function_exists('get_default_pmethod_ids')) {
      $request->post['pmethod'] = get_default_pmethod_ids();
    }

    // Validate payment method (garante que, mesmo se não houver defaults, não segue vazio)
    if (empty($request->post['pmethod'])) {
      throw new Exception(trans('error_pmethod'));
    }

    // Validate existance
    validate_existance($request);

    $Hooks->do_action('Before_Create_Store', $request);

    // Insert new store into database
    $store_id = $store_model->addStore($request->post);
    $store_model->editPreference($store_id, $request->post['preference']);

    // Add product to store
    if (!empty($request->post['product'])) {
      foreach ($request->post['product'] as $product_id) {

        // Fetch product info
        $product_info = get_the_product($product_id);

        //--- Category to store ---//

          $statement = db()->prepare("SELECT * FROM `category_to_store` WHERE `store_id` = ? AND `ccategory_id` = ?");
          $statement->execute(array($store_id, $product_info['category_id']));
          $category = $statement->fetch(PDO::FETCH_ASSOC);
          if (!$category) {
             $statement = db()->prepare("INSERT INTO `category_to_store` SET `ccategory_id` = ?, `store_id` = ?");
              $statement->execute(array((int)$product_info['category_id'], (int)$store_id));
          } 

        //--- Box to store ---//

          $statement = db()->prepare("SELECT * FROM `box_to_store` WHERE `store_id` = ? AND `box_id` = ?");
          $statement->execute(array($store_id, $product_info['box_id']));
          $box = $statement->fetch(PDO::FETCH_ASSOC);
          if (!$box) {
             $statement = db()->prepare("INSERT INTO `box_to_store` SET `box_id` = ?, `store_id` = ?");
              $statement->execute(array((int)$product_info['box_id'], (int)$store_id));
          } 

      //--- Supplier to store ---//

          $statement = db()->prepare("SELECT * FROM `supplier_to_store` WHERE `store_id` = ? AND `sup_id` = ?");
          $statement->execute(array($store_id, $product_info['sup_id']));
          $supplier = $statement->fetch(PDO::FETCH_ASSOC);
          if (!$supplier) {
            $statement = db()->prepare("INSERT INTO `supplier_to_store` SET `sup_id` = ?, `store_id` = ?");
            $statement->execute(array((int)$product_info['sup_id'], (int)$store_id));
          }

        //--- Create product link ---//

          $statement = db()->prepare("INSERT INTO `product_to_store` SET `product_id` = ?, `store_id` = ?, `sup_id` = ?, `box_id` = ?, `e_date` = ?, `p_date` = ?");
          $statement->execute(array((int)$product_id, (int)$store_id, (int)$product_info['sup_id'], (int)$product_info['box_id'], $product_info['e_date'], date('Y-m-d')));

      }
    }

    // Add account to store
    $statement = db()->prepare("INSERT INTO `bank_account_to_store` SET `account_id` = ?, `store_id` = ?");
    $statement->execute(array(1, $store_id));
    $statement = db()->prepare("UPDATE `stores` SET `deposit_account_id` = ? WHERE `store_id` = ?");
    $statement->execute(array(1, $store_id));

    // Add postemplate to store
    foreach ($request->post['postemplate'] as $template_id) {
      $statement = db()->prepare("INSERT INTO `pos_template_to_store` SET `store_id` = ?, `ttemplate_id` = ?, `is_active` = ?");
      $statement->execute(array((int)$store_id, (int)$template_id, 1));
    }

    // Add printer to store
    foreach ($request->post['printer'] as $printer_id) {
      $statement = db()->prepare("INSERT INTO `printer_to_store` SET `store_id` = ?, `pprinter_id` = ?");
      $statement->execute(array((int)$store_id, (int)$printer_id));
    }

    // Add currency to store
    foreach ($request->post['currency'] as $currency_id) {
      $statement = db()->prepare("INSERT INTO `currency_to_store` SET `currency_id` = ?, `store_id` = ?");
      $statement->execute(array((int)$currency_id, (int)$store_id));
    }

    // Add payment method to store
    // Garante que todos os métodos canônicos sejam vinculados à nova loja.
    // Regras de status padrão:
    //  - cod (1) e bkash (2) iniciam desativados (status = 0)
    //  - os demais (3,4,5,6,7,9) iniciam ativos (status = 1)
    // Ordem padrão (sort_order) para exibição no painel:
    //  7 = Dinheiro, 5 = Cartão Crédito, 9 = Cartão Débito, 6 = Pix,
    //  4 = Credit, 3 = Gift card, 1 = Cash on Delivery, 2 = Bkash
    $defaultSortOrder = array(
      7 => 1,
      5 => 2,
      9 => 3,
      6 => 4,
      4 => 5,
      3 => 6,
      1 => 7,
      // 2 (bkash) não é mais usada por padrão
    );

    foreach ($request->post['pmethod'] as $pmethod_id) {
      $pmethod_id = (int)$pmethod_id;

      // Status padrão: cod, bkash, gift_card e credit desativados; demais ativos
      $status = in_array($pmethod_id, array(1, 2, 3, 4)) ? 0 : 1;
      $sort_order = isset($defaultSortOrder[$pmethod_id]) ? (int)$defaultSortOrder[$pmethod_id] : 0;

      $statement = db()->prepare("INSERT INTO `pmethod_to_store` SET `ppmethod_id` = ?, `store_id` = ?, `status` = ?, `sort_order` = ?");
      $statement->execute(array($pmethod_id, (int)$store_id, $status, $sort_order));
    }

    // Garantir cliente padrão (balcão) exclusivo por loja
    // - Cria em customers
    // - Vincula em customer_to_store
    // - Salva walkin_customer_id no stores.preference
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
        error_log('[store.php] Falha ao criar cliente padrão para loja ' . $store_id . ': ' . $eDefaultsCustomer->getMessage());
      }
    }

    // Add cashier to the store
    $statement = db()->prepare("INSERT INTO `user_to_store` SET `user_id` = ?, `store_id` = ?");
    $statement->execute(array($request->post['cashier_id'], $store_id));

    // Add admin to the store
    $statement = db()->prepare("INSERT INTO `user_to_store` SET `user_id` = ?, `store_id` = ?");
    $statement->execute(array(1, $store_id));

    // Add current user to the store
    $statement = db()->prepare("INSERT INTO `user_to_store` SET `user_id` = ?, `store_id` = ?");
    $statement->execute(array(user_id(), $store_id));

    $Hooks->do_action('After_Create_Store', $store_id);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_create_success'), 'id' => $store_id));
    exit();

  } catch(Exception $e) {
    
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
} 

// Update store
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'UPDATE')
{
  try {

    // Check update permission
    if (user_group_id() != 1 && !has_permission('access', 'update_store')) {
      throw new Exception(trans('error_update_permission'));
    }

    // Validate store id
    if (!validateInteger($request->post['store_id'])) {
      throw new Exception(trans('error_store_id'));
    }

    $id = $request->post['store_id'];

    if (DEMO && $id == 1) {
      throw new Exception(trans('error_update_permission'));
    }

    // Validate post data
    validate_request_data($request);

    // Validate existance
    validate_existance($request, $id);

    $Hooks->do_action('Before_Update_Store', $request);
    
    // Edit store
    $store_model->editStore($id, $request->post);
    $the_store = $store_model->editPreference($id, $request->post['preference']);

    $Hooks->do_action('After_Update_Store', $the_store);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_update_success'), 'id' => $id));
    exit();

  } catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
} 

// Delete store
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'DELETE') 
{
  try {

    // Check delete permission
    if (user_group_id() != 1 && !has_permission('access', 'delete_store') || DEMO) {
      throw new Exception(trans('error_delete_permission'));
    }

    // Validate store id
    if (!validateInteger($request->post['store_id'])) {
      throw new Exception(trans('error_store_id'));
    }

    $id = $request->post['store_id'];
    $new_store_id = $request->post['new_store_id'];

    if (DEMO && $id == 1) {
      throw new Exception(trans('error_delete_permission'));
    }

    // Store id 1 can not be deleted
    if ($id == 1) {
      throw new Exception(trans('error_store_delete'));
    }

    // Active store can not be deleted
    // Verifica de forma segura se há loja ativa na sessão antes de comparar
    $currentStoreId = null;
    try {
      // IMPORTANTE: Verificar se $store global existe e está inicializado
      // antes de chamar store_id(), pois pode causar Fatal Error se $store for null
      global $store;
      if (isset($store) && is_object($store) && function_exists('store_id')) {
        $currentStoreId = store_id();
      }
    } catch (Throwable $e) {
      $currentStoreId = null;
    }
    
    // Se não há loja ativa ou o objeto store não está inicializado, permite exclusão
    // Apenas bloqueia se a loja sendo excluída é realmente a loja ativa atual
    if ($currentStoreId && (int)$currentStoreId === (int)$id) {
      throw new Exception(trans('error_active_store_delete'));
    }

    // Validate delete action
    if ($request->post['delete_action'] == 'insert_to' && !validateInteger($new_store_id)) {
      throw new Exception(trans('error_store_name'));
    }

    $Hooks->do_action('Before_Delete_Store', $request);

    $action_type = $request->post['delete_action'];

    switch ($action_type) {
      case 'delete':

        foreach (get_all_tables() as $table) 
        {
          $the_table = $table[0];
          if ($the_table == 'stores') {
            continue;
          }
          if ($the_table == 'transfers') {
            $statement = db()->prepare("DELETE FROM {$the_table} WHERE `from_store_id` = ? || `to_store_id` = ?");
            $statement->execute(array($id, $id));
            continue;
          }
          if ($the_table == 'customer_to_store') {
            $statement = db()->prepare("SELECT `customer_id` FROM `customer_to_store` WHERE `store_id` = ?");
            $statement->execute(array($id));
            $customers = $statement->fetchAll(PDO::FETCH_ASSOC);

            // GIFT CARD
            foreach ($customers as $customer) 
            {
              $statement = db()->prepare("SELECT `id` FROM `gift_cards` WHERE `customer_id` = ?");
              $statement->execute(array($customer['customer_id']));
              $card = $statement->fetch(PDO::FETCH_ASSOC);

              // Só tenta deletar gift_card_topups se existir um card
              if ($card && isset($card['id'])) {
                $statement = db()->prepare("DELETE FROM `gift_card_topups` WHERE `card_id` = ?");
                $statement->execute(array($card['id']));
              }

              $statement = db()->prepare("DELETE FROM `gift_cards` WHERE `customer_id` = ?");
              $statement->execute(array($customer['customer_id']));
            }
          }
          if (count(db()->query("SHOW COLUMNS FROM `{$the_table}` LIKE 'store_id'")->fetchAll())) {
            $statement = db()->prepare("DELETE FROM `{$the_table}` WHERE `store_id` = ?");
            $statement->execute(array($id));
          }
        }

        break;

      case 'insert_to':

        foreach (get_all_tables() as $table) 
        {
          $the_table = $table[0];
          if ($the_table == 'stores') {
            continue;
          }
          if ($the_table == 'transfers') 
          {
            $statement = db()->prepare("UPDATE `{$the_table}` SET `from_store_id` = ? WHERE `from_store_id` = ?");
            $statement->execute(array($new_store_id, $id));

            $statement = db()->prepare("UPDATE `{$the_table}` SET `to_store_id` = ? WHERE `to_store_id` = ?");
            $statement->execute(array($new_store_id, $id));
            continue;
          }
          if (count(db()->query("SHOW COLUMNS FROM `{$the_table}` LIKE 'store_id'")->fetchAll())) {
            $statement = db()->prepare("UPDATE `{$the_table}` SET `store_id` = ? WHERE `store_id` = ?");
            $statement->execute(array($new_store_id, $id));
          }
        }
        
        break;
    }

    // Delete store
    $the_store = $store_model->deleteStore($id);

    $Hooks->do_action('After_Delete_Store', $the_store);
    
    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_delete_success')));
    exit();

  } catch (Throwable $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Store delete form
if (isset($request->get['store_id']) AND isset($request->get['action_type']) && $request->get['action_type'] == 'DELETE') {
    // Fetch store
    $store_info = $store_model->getStore($request->get['store_id']);
    $Hooks->do_action('Before_Store_Delete_Form', $store_info);
    include 'template/store_del_form.php';
    $Hooks->do_action('After_Store_Delete_Form', $store_info);
    exit();
}

/**
 *===================
 * START DATATABLE
 *===================
 */
 
 $Hooks->do_action('Before_Showing_Store_List');

// DB table to use
$where_query = '1=1';

if (!is_admin()) {
  $where_query = 'u2s.user_id = ' . user_id();
}
 
// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `stores`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `stores`.`name`) AS name,
GROUP_CONCAT(DISTINCT `stores`.`code_name`) AS code_name,
GROUP_CONCAT(DISTINCT `stores`.`mobile`) AS mobile,
GROUP_CONCAT(DISTINCT `stores`.`email`) AS email,
GROUP_CONCAT(DISTINCT `stores`.`country`) AS country,
GROUP_CONCAT(DISTINCT `stores`.`zip_code`) AS zip_code,
GROUP_CONCAT(DISTINCT `stores`.`currency`) AS currency,
GROUP_CONCAT(DISTINCT `stores`.`vat_reg_no`) AS vat_reg_no,
GROUP_CONCAT(DISTINCT `stores`.`cashier_id`) AS cashier_id,
GROUP_CONCAT(DISTINCT `stores`.`address`) AS address,
GROUP_CONCAT(DISTINCT `stores`.`receipt_printer`) AS receipt_printer,
GROUP_CONCAT(DISTINCT `stores`.`cash_drawer_codes`) AS cash_drawer_codes,
GROUP_CONCAT(DISTINCT `stores`.`char_per_line`) AS char_per_line,
GROUP_CONCAT(DISTINCT `stores`.`remote_printing`) AS remote_printing,
GROUP_CONCAT(DISTINCT `stores`.`printer`) AS printer,
GROUP_CONCAT(DISTINCT `stores`.`order_printers`) AS order_printers,
GROUP_CONCAT(DISTINCT `stores`.`auto_print`) AS auto_print,
GROUP_CONCAT(DISTINCT `stores`.`local_printers`) AS local_printers,
GROUP_CONCAT(DISTINCT `stores`.`logo`) AS logo,
GROUP_CONCAT(DISTINCT `stores`.`favicon`) AS favicon,
GROUP_CONCAT(DISTINCT `stores`.`preference`) AS preference,
GROUP_CONCAT(DISTINCT `stores`.`sound_effect`) AS sound_effect,
GROUP_CONCAT(DISTINCT `stores`.`sort_order`) AS sort_order,
GROUP_CONCAT(DISTINCT `stores`.`feedback_status`) AS feedback_status,
GROUP_CONCAT(DISTINCT `stores`.`feedback_at`) AS feedback_at,
GROUP_CONCAT(DISTINCT `stores`.`deposit_account_id`) AS deposit_account_id,
GROUP_CONCAT(DISTINCT `stores`.`thumbnail`) AS thumbnail,
GROUP_CONCAT(DISTINCT `stores`.`status`) AS status,
GROUP_CONCAT(DISTINCT `stores`.`created_at`) AS created_at
FROM stores
  LEFT JOIN user_to_store u2s ON (stores.store_id = u2s.store_id) 
  WHERE $where_query GROUP BY stores.store_id
  ) as stores";
 
// Table's primary key
$primaryKey = 'store_id';
 
$columns = array(
  array(
      'db' => 'store_id',
      'dt' => 'DT_RowId',
      'formatter' => function( $d ) {
        return 'row_'.$d;
      }
  ),
  array( 'db' => 'store_id', 'dt' => 'store_id' ),
  array( 
    'db' => 'name',   
    'dt' => 'name' ,
    'formatter' => function($d, $row) {
        return $row['name'];
    }
  ),
  array( 'db' => 'country', 'dt' => 'country' ),
  array( 'db' => 'address', 'dt' => 'address' ),
  array( 'db' => 'sort_order', 'dt' => 'sort_order' ),
  array( 'db' => 'created_at', 'dt' => 'created_at' ),
  array( 
    'db' => 'created_at',   
    'dt' => 'created_at' ,
    'formatter' => function($d, $row) {
        return $row['created_at'];
    }
  ),
  array( 
    'db' => 'status',   
    'dt' => 'status' ,
    'formatter' => function($d, $row) {
      if ($row['status'] == 1) {
        return  '<span class="label label-info">'.trans('text_active').'</span>';
      }
      return '<span class="label label-warning">'.trans('text_inactivate').'</span>';
    }
  ),
  array(
    'db' => 'status',   
    'dt' => 'btn_edit' ,
    'formatter' => function($d, $row) {
      if (DEMO && $row['store_id'] == 1) {          
        return'<button class="btn btn-sm btn-block btn-default" type="button" disabled><i class="fa fa-pencil"></i></button>';
      }
      return '<a id="edit-store" class="btn btn-sm btn-block btn-primary" href="store_single.php?store_id='.$row['store_id'].'" title="'.trans('button_edit').'"><i class="fa fa-fw fa-pencil"></i></a>';
    }
  ),
  array( 
    'db' => 'status',   
    'dt' => 'btn_delete' ,
    'formatter' => function($d, $row) {

      if ((DEMO && $row['store_id'] == 2) || $row['store_id'] == 1 || store_id() == $row['store_id']) {
        return '<button class="btn btn-sm btn-block btn-default" type="button" title="'.trans('button_delete').'" disabled><i class="fa fa-fw fa-trash"></i></button>';
      }

      return '<button id="delete-store" class="btn btn-sm btn-block btn-danger" type="button" title="'.trans('button_delete').'"><i class="fa fa-fw fa-trash"></i></button>';
    }
  ),
  array( 
    'db' => 'status',   
    'dt' => 'btn_action' ,
    'formatter' => function($d, $row) {
      $store_id = $row['store_id'];
      if (store_id() ==  $store_id) {
        return '<button class="btn btn-sm btn-block btn-success" type="button" title="'.trans('button_activated').'" disabled><i class="fa fa-fw fa-check"></i></button>';
      } else {
        return '<a class="btn btn-sm btn-block btn-info activate-store" href="store.php?active_store_id='.$store_id.'" title="'.trans('button_activate').'"><i class="fa fa-fw fa-check"></i> '.trans('button_activate').'</button>';
      }
    }
  )
);

echo json_encode(
  SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_Store_List');



