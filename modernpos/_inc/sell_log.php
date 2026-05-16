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
if (user_group_id() != 1 && !has_permission('access', 'read_sell_log')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

$store_id = store_id();
$user_id = user_id();

// View transaction
if (isset($request->get['id']) && isset($request->get['action_type']) && $request->get['action_type'] == 'VIEW') 
{
    $id = $request->get['id'];
    $statement = db()->prepare("SELECT * FROM `sell_logs` WHERE `id` = ?");
    $statement->execute(array($id));
    $transaction = $statement->fetch(PDO::FETCH_ASSOC);
    include 'template/sell_log_view.php';
    exit();
}

/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_Sell_Log_List');

$where_query = "store_id = {$store_id}";
if (isset($request->get['customer_id']) && $request->get['customer_id'] != 'null') {
  $where_query .= " AND sell_logs.customer_id=".$request->get['customer_id'];
}

if (from()) {
  $from = from();
  $to = to();
  $where_query .= date_range_sell_log_filter($from, $to);
}

// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `id`) AS id,
GROUP_CONCAT(DISTINCT `customer_id`) AS customer_id,
GROUP_CONCAT(DISTINCT `reference_no`) AS reference_no,
GROUP_CONCAT(DISTINCT `ref_invoice_id`) AS ref_invoice_id,
GROUP_CONCAT(DISTINCT `type`) AS type,
GROUP_CONCAT(DISTINCT `pmethod_id`) AS pmethod_id,
GROUP_CONCAT(DISTINCT `description`) AS description,
GROUP_CONCAT(DISTINCT `amount`) AS amount,
GROUP_CONCAT(DISTINCT `store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `updated_at`) AS updated_at
FROM sell_logs 
  WHERE $where_query GROUP BY id
  ) as expenses";
 
// Table's primary key
$primaryKey = 'id';

$columns = array(
  array(
      'db' => 'id',
      'dt' => 'DT_RowId',
      'formatter' => function( $d ) {
          return 'row_'.$d;
      }
  ),
  array( 'db' => 'id', 'dt' => 'id' ),
  array( 'db' => 'reference_no', 'dt' => 'reference_no' ),
  array( 
    'db' => 'type',   
    'dt' => 'type',
    'formatter' => function($d, $row) {
      if ($row['type'] == 'due') {
        return '<span class="label label-danger">'.my_str_replace('_', ' ', ucfirst($row['type'])).'</span>';
      } elseif ($row['type'] == 'due_paid') {
        return '<span class="label label-success">'.my_str_replace('_', ' ', ucfirst($row['type'])).'</span>';
      } else {
        return '<span class="label label-warning">'.my_str_replace('_', ' ', ucfirst($row['type'])).'</span>';
      }
    }
  ),
  array( 
    'db' => 'customer_id',   
    'dt' => 'customer_name',
    'formatter' => function($d, $row) {
        return get_the_customer($row['customer_id'], 'customer_name');
    }
  ),
  array( 
    'db' => 'pmethod_id',   
    'dt' => 'pmethod',
    'formatter' => function($d, $row) {
      return get_the_pmethod($row['pmethod_id'], 'name');
    }
  ),
  array( 
    'db' => 'amount',   
    'dt' => 'amount',
    'formatter' => function($d, $row) {
      return currency_format($row['amount']);
    }
  ),
  array( 
    'db' => 'created_by',   
    'dt' => 'created_by',
    'formatter' => function($d, $row) {
     return get_the_user($row['created_by'], 'username');
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
    'db'        => 'id',
    'dt'        => 'btn_view',
    'formatter' => function() {
      return '<button id="view-transaction-btn" class="btn btn-sm btn-block btn-info" type="button" title="'.trans('button_view').'"><i class="fa fa-fw fa-eye"></i></button>';
    }
  ),
); 

echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_Sell_Log_List');

/**
 *===================
 * END DATATABLE
 *===================
 */