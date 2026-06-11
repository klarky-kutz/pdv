<?php 
ob_start();
session_start();
include ("../_init.php");

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
if (user_group_id() != 1 && !has_permission('access', 'read_user')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

// LOAD USER MODEL
$user_model = registry()->get('loader')->model('user');

// =======================================================
// SaaS Limits Bridge (source of truth)
// =======================================================
if (!class_exists('SaasLimitsBridge')) {
  $saasLimitsPath = ROOT . '/../saas/includes/SaasLimitsBridge.php';
  if (file_exists($saasLimitsPath)) {
    require_once $saasLimitsPath;
  }
}

/**
 * Resolve tenant_id do usuário logado.
 * - Prioriza $_SESSION['tenant_id']
 * - Fallback: users.tenant_id
 */
function saas_current_tenant_id()
{
  static $tenantId = null;
  if ($tenantId !== null) {
    return (int)$tenantId;
  }

  $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
  $uid = function_exists('user_id') ? (int)user_id() : 0;

  if (class_exists('SaasLimitsBridge')) {
    $tenantId = SaasLimitsBridge::resolveTenantId(db(), $uid, $sessionTid > 0 ? $sessionTid : null);
    return (int)$tenantId;
  }

  // Fallback simples (sem bridge)
  if ($sessionTid > 0) {
    $tenantId = $sessionTid;
    return (int)$tenantId;
  }

  try {
    $st = db()->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $tmp = $st->fetchColumn();
    $tenantId = $tmp ? (int)$tmp : 0;
  } catch (Throwable $e) {
    $tenantId = 0;
  }

  return (int)$tenantId;
}

// Validate post data
function validate_request_data($request) 
{
  // Validate username
  if (!validateString($request->post['username'])) {
    throw new Exception(trans('error_user_name'));
  }

  // Validate customer date of birth
  if ($request->post['dob']) {
    if (!isItValidDate($request->post['dob'])) {
        throw new Exception(trans('error_date_of_birth'));
    }
  }

  // Validate customer email & mobile
  if (!validateEmail($request->post['email']) && empty($request->post['mobile'])) {
    throw new Exception(trans('error_user_email_or_mobile'));
  }

  // Validate user group id
  if(!validateInteger($request->post['group_id'])) {
    throw new Exception(trans('error_user_group'));
  } 

  if (!isset($request->post['user_store']) || empty($request->post['user_store'])) {
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

// Check, if exist or not
function validate_existance($request, $id = 0)
{
  // Check email address
  if ($request->post['email']) {
    $statement = db()->prepare("SELECT * FROM `users` WHERE `email` = ? && `id` != ?");
    $statement->execute(array($request->post['email'], $id));
    if ($statement->rowCount() > 0) {
      throw new Exception(trans('error_email_exist'));
    }
  }

  // Check mobile number
  if ($request->post['mobile']) {
    $statement = db()->prepare("SELECT * FROM `users` WHERE `mobile` = ? && `id` != ?");
    $statement->execute(array($request->post['mobile'], $id));
    if ($statement->rowCount() > 0) {
      throw new Exception(trans('error_mobile_exist'));
    } 
  }
}

// Create user
if ($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'CREATE')
{
  try {

     // Check create permission
    if (user_group_id() != 1 && !has_permission('access', 'create_user')) {
      throw new Exception(trans('error_read_permission'));
    }

    // Validate post data
    validate_request_data($request);

     // Validate existance
    validate_existance($request);

    // =======================================================
    // SaaS: enforce max_users + valida stores do tenant
    // Regra: conta usuários únicos com vínculo ativo (status=1) em qualquer loja do tenant
    // =======================================================
    $tenantId = saas_current_tenant_id();
    if ($tenantId > 0 && class_exists('SaasLimitsBridge')) {
      // Forçar tenant_id no payload (sanar dívida técnica para novos usuários)
      $request->post['tenant_id'] = (int)$tenantId;

      $storeIds = isset($request->post['user_store']) ? (array)$request->post['user_store'] : [];
      if (!SaasLimitsBridge::validateStoresBelongToTenant(db(), (int)$tenantId, $storeIds)) {
        throw new Exception('Selecione apenas lojas da sua conta (tenant).');
      }

      // Limite de usuários
      // Requisito: usuário desativado também conta no limite, então a quota deve ser aplicada
      // sempre na criação (status não influencia).
      $check = SaasLimitsBridge::canCreateUser(db(), (int)$tenantId);
      if (is_array($check) && isset($check[0]) && !$check[0]) {
        throw new Exception((string)($check[1] ?? 'Limite de usuários do seu plano atingido.'));
      }
    }

    // Validate password
    if(empty($request->post['password'])) {
      throw new Exception(trans('error_type_a_valid_password'));
    }

    // password matching
    if($request->post['password'] !== $request->post['password1']) {
      throw new Exception(trans('error_password_not_match'));
    }

    // Check password strongness
    if (($errMsg = checkPasswordStrongness($request->post['password'])) != 'ok') {
      throw new Exception($errMsg);
    }

    if (!$request->post['dob']) {
    	$request->post['dob'] = date('Y-m-d',strtotime("-20 years"));
    }

    $Hooks->do_action('Before_Create_User', $request);

    // Edit user
    $user_id = $user_model->addUser($request->post);

    // Garantir users.tenant_id para novas contas (caso o model não tenha inserido)
    if (isset($tenantId) && (int)$tenantId > 0) {
      try {
        $st = db()->prepare('UPDATE users SET tenant_id = ? WHERE id = ? LIMIT 1');
        $st->execute([(int)$tenantId, (int)$user_id]);
      } catch (Throwable $e) {
        // ignore
      }
    }

    // get user
    $the_user = $user_model->getUser($user_id);

    $Hooks->do_action('After_Create_User', $the_user);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_success'), 'id' => $user_id, 'user' => $the_user));
    exit();

  }  catch (Exception $e) { 

    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// Update user
if($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'UPDATE')
{
  try {

    // Check update permission
    if (user_group_id() != 1 && !has_permission('access', 'update_user') || DEMO) {
      throw new Exception(trans('error_update_permission'));
    }

    // Validate user id
    if (empty($request->post['id'])) {
      throw new Exception(trans('error_user_id'));
    }

    $id = $request->post['id'];

    if (DEMO && ($id == 1 || $id == 2 || $id == 3)) {
      throw new Exception(trans('error_update_permission'));
    }

    // Validate post data
    validate_request_data($request);

    // Validate existance
    validate_existance($request, $id);

    // =======================================================
    // SaaS: valida stores do tenant e enforce max_users quando ativar usuário
    // =======================================================
    $tenantId = saas_current_tenant_id();
    if ($tenantId > 0 && class_exists('SaasLimitsBridge')) {
      $request->post['tenant_id'] = (int)$tenantId;

      $storeIds = isset($request->post['user_store']) ? (array)$request->post['user_store'] : [];
      if (!SaasLimitsBridge::validateStoresBelongToTenant(db(), (int)$tenantId, $storeIds)) {
        throw new Exception('Selecione apenas lojas da sua conta (tenant).');
      }

      // IMPORTANTE:
      // Não aplicar quota em UPDATE/toggle de status. O limite é baseado em total de usuários
      // criados no tenant, então ativar/desativar não deve bloquear a atualização.
    }

    // for current user current store link can not remove
    if (user_id() == $id && !in_array(store_id(), $request->post['user_store'])) {
      throw new Exception(trans('error_active_store_not_remove'));
    }

    $Hooks->do_action('Before_Update_User', $request);

    // Edit esuer
    $the_user = $user_model->editUser($id, $request->post);

    $Hooks->do_action('After_Update_User', $the_user);

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

// Delete user
if($request->server['REQUEST_METHOD'] == 'POST' && isset($request->post['action_type']) && $request->post['action_type'] == 'DELETE') 
{
  try {

    // Check delete permission
    if (user_group_id() != 1 && !has_permission('access', 'delete_user') || DEMO) {
      throw new Exception(trans('error_delete_permission'));
    }

    // Validate user id
    if (!validateInteger($request->post['id'])) {
      throw new Exception(trans('error_user_id'));
    }

    $id = $request->post['id'];
    $new_user_id = $request->post['new_user_id'];

    if (DEMO && ($id == 1 || $id == 2 || $id == 3)) {
      throw new Exception(trans('error_delete_permission'));
    }

    if ($id == 1) {
      throw new Exception(trans('error_unable_to_delete'));
    }

    // Validate delete action
    if (empty($request->post['delete_action'])) {
      throw new Exception(trans('error_delete_action'));
    }

    if ($request->post['delete_action'] == 'insert_to' && empty($request->post['new_user_id'])) {
      throw new Exception(trans('error_user_name'));
    }

    $Hooks->do_action('Before_Delete_User', $request);

    $belongs_stores = $user_model->getBelongsStore($id);
    foreach ($belongs_stores as $the_store) {

      // Check if relationship exist or not
      $statement = db()->prepare("SELECT * FROM `user_to_store` WHERE `user_id` = ? AND `store_id` = ?");
      $statement->execute(array($new_user_id, $the_store['store_id']));
      if ($statement->rowCount() > 0) continue;

      // Create relationship
      $statement = db()->prepare("INSERT INTO `user_to_store` SET `user_id` = ?, `store_id` = ?");
      $statement->execute(array($new_user_id, (int)$the_store['store_id']));
    }

    if ($request->post['delete_action'] == 'insert_to') {

      $statement = db()->prepare("UPDATE `login_logs` SET `user_id` = ? WHERE `user_id` = ?");
      $statement->execute(array($new_user_id, $id));
      
      $statement = db()->prepare("UPDATE `selling_info` SET `ref_user_id` = ? WHERE `ref_user_id` = ?");
      $statement->execute(array($new_user_id, $id));

      $statement = db()->prepare("UPDATE `selling_info` SET `created_by` = ? WHERE `created_by` = ?");
      $statement->execute(array($new_user_id, $id));

      $statement = db()->prepare("UPDATE `purchase_info` SET `created_by` = ? WHERE `created_by` = ?");
      $statement->execute(array($new_user_id, $id));
    }
    
    // Delete user
    $the_user = $user_model->deleteUser($id);

    $Hooks->do_action('After_Delete_User', $the_user);

    header('Content-Type: application/json');
    echo json_encode(array('msg' => trans('text_delete_success')));
    exit();

  } catch (Exception $e) { 
    
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
    exit();
  }
}

// User create form
if (isset($request->get['action_type']) && $request->get['action_type'] == 'CREATE') 
{
  include 'template/user_create_form.php';
  exit();
}

// User edit form
if (isset($request->get['id']) && isset($request->get['action_type']) && $request->get['action_type'] == 'EDIT') {
  
  // Fetch user
  $the_user = $user_model->getUser($request->get['id']);
  include 'template/user_form.php';
  exit();
}

// User delete form
if (isset($request->get['id']) && isset($request->get['action_type']) && $request->get['action_type'] == 'DELETE') {
  
  // Fetch user
  $the_user = $user_model->getUser($request->get['id']);
  include 'template/user_del_form.php';
  exit();
}

/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_User_List');
 
// DB table to use
$where_query = 'u2s.store_id = ' . store_id();

// Restrição: a listagem deve exibir apenas o usuário logado.
// - Mantém compatibilidade: pode desabilitar apenas passando explicitamente only_me=0
$onlyMe = true;
if (isset($request->get['only_me'])) {
  $onlyMe = ((int)$request->get['only_me'] === 1);
}

if ($onlyMe) {
  $where_query .= ' AND users.id = ' . (int)user_id();
}
 
// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `users`.`id`) AS id,
GROUP_CONCAT(DISTINCT `users`.`group_id`) AS group_id,
GROUP_CONCAT(DISTINCT `users`.`username`) AS username,
GROUP_CONCAT(DISTINCT `users`.`email`) AS email,
GROUP_CONCAT(DISTINCT `users`.`mobile`) AS mobile,
GROUP_CONCAT(DISTINCT `users`.`dob`) AS dob,
GROUP_CONCAT(DISTINCT `users`.`sex`) AS sex,
GROUP_CONCAT(DISTINCT `users`.`password`) AS password,
GROUP_CONCAT(DISTINCT `users`.`pass_reset_code`) AS pass_reset_code,
GROUP_CONCAT(DISTINCT `users`.`reset_code_time`) AS reset_code_time,
GROUP_CONCAT(DISTINCT `users`.`login_try`) AS login_try,
GROUP_CONCAT(DISTINCT `users`.`last_login`) AS last_login,
GROUP_CONCAT(DISTINCT `users`.`ip`) AS ip,
GROUP_CONCAT(DISTINCT `users`.`address`) AS address,
GROUP_CONCAT(DISTINCT `users`.`preference`) AS preference,
GROUP_CONCAT(DISTINCT `users`.`user_image`) AS user_image,
GROUP_CONCAT(DISTINCT `users`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `users`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT `u2s`.`u2s_id`) AS u2s_id,
GROUP_CONCAT(DISTINCT `u2s`.`user_id`) AS user_id,
GROUP_CONCAT(DISTINCT `u2s`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `u2s`.`status`) AS status,
GROUP_CONCAT(DISTINCT `u2s`.`sort_order`) AS sort_orde 
FROM users 
  LEFT JOIN user_to_store u2s ON (users.id = u2s.user_id) 
  WHERE $where_query GROUP BY id
  ) as users";
 
// Table's primary key
$primaryKey = 'id';

$columns = array(
  array(
      'db' => 'id',
      'dt' => 'DT_RowId',
      'formatter' => function($d) {
          return 'row_'.$d;
      }
  ),
  array( 'db' => 'id', 'dt' => 'id' ),
  array( 
    'db' => 'username',   
    'dt' => 'username' ,
    'formatter' => function($d, $row) {
        return ucfirst($row['username']);
    }
  ),
  array( 'db' => 'email',  'dt' => 'email' ),
  array( 'db' => 'mobile',   'dt' => 'mobile' ),
  array( 'db' => 'group_id',   'dt' => 'group' ),
  array( 
    'db' => 'group_id',   
    'dt' => 'group' ,
    'formatter' => function($d, $row) {
        $statement = db()->prepare('SELECT name FROM `user_group` WHERE group_id = ?');
        $statement->execute(array($row['group_id']));
        $group = $statement->fetch(PDO::FETCH_ASSOC);
        return ucfirst($group['name']);
    }
  ),
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
        return $row['status'] 
          ? '<span class="label label-success">'.trans('text_active').'</span>' 
          : '<span class="label label-warning">' .trans('text_inactive').'</span>';
    }
  ),
  array(
      'db' => 'id',
      'dt' => 'btn_profile',
      'formatter' => function( $d, $row ) {
        return '<a href="user_profile.php?id='.$row['id'].'" id="sell-product" class="btn btn-sm btn-block btn-info" type="button" title="'.trans('button_view_profile').'"><i class="fa fa-user"></i></a>';
      }
  ),
  array( 
    'db' => 'id',   
    'dt' => 'btn_edit' ,
    'formatter' => function($d, $row) {
      if (DEMO && ($row['id'] == 2 || $row['id'] == 3 || $row['id'] == 1)) {
        return '<button class="btn btn-sm btn-block btn-default" type="button" disabled><i class="fa fa-fw fa-pencil"></i></button>';
      } 
      return '<button id="edit-user" class="btn btn-sm btn-block btn-primary" type="button" title="'.trans('button_edit').'"><i class="fa fa-fw fa-pencil"></i></button>';
    }
  ),
  array( 
    'db' => 'id',   
    'dt' => 'btn_delete' ,
    'formatter' => function($d, $row) {
        if ((DEMO && ($row['id'] == 2 || $row['id'] == 3)) || $row['id'] == 1 || $row['id'] == user_id()) {
          return '<button class="btn btn-sm btn-block btn-default" type="button" disabled><i class="fa fa-fw fa-trash"></i></button>';
        } 
        return '<button id="delete-user" class="btn btn-sm btn-block btn-danger" type="button" title="'.trans('button_delete').'"><i class="fa fa-fw fa-trash"></i></button>';
    }
  )
); 
 
echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_User_List');

/**
 *===================
 * END DATATABLE
 *===================
 */
