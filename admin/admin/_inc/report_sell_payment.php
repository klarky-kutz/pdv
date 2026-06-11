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
if (user_group_id() != 1 && !has_permission('access', 'read_sell_payment_report')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

$store_id = store_id();

/**
 *===================
 * START DATATABLE
 *===================
 */

$where_query = "payments.store_id = '$store_id' AND `is_hide` = 0";
if (from()) {
  $from = from();
  $to = to();
  $where_query .= date_range_sell_payments_filter($from, $to);
}

// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `payments`.`id`) AS id,
GROUP_CONCAT(DISTINCT `payments`.`type`) AS type,
GROUP_CONCAT(DISTINCT `payments`.`is_profit`) AS is_profit,
GROUP_CONCAT(DISTINCT `payments`.`is_hide`) AS is_hide,
GROUP_CONCAT(DISTINCT `payments`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `payments`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `payments`.`reference_no`) AS reference_no,
GROUP_CONCAT(DISTINCT `payments`.`pmethod_id`) AS pmethod_id,
GROUP_CONCAT(DISTINCT `payments`.`transaction_id`) AS transaction_id,
GROUP_CONCAT(DISTINCT `payments`.`capital`) AS capital,
GROUP_CONCAT(DISTINCT `payments`.`details`) AS details,
GROUP_CONCAT(DISTINCT `payments`.`attachment`) AS attachment,
GROUP_CONCAT(DISTINCT `payments`.`note`) AS note,
GROUP_CONCAT(DISTINCT `payments`.`total_paid`) AS total_paid,
GROUP_CONCAT(DISTINCT `payments`.`pos_balance`) AS pos_balance,
GROUP_CONCAT(DISTINCT `payments`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `payments`.`created_at`) AS created_at,
SUM(amount) as total FROM payments 
        WHERE $where_query GROUP BY `invoice_id`) as payments";

// Table's primary key
$primaryKey = 'id';
$columns = array(
  array( 'db' => 'id', 'dt' => 'id' ),
  array( 'db' => 'created_at', 'dt' => 'created_at' ),
  array( 
      'db' => 'type',  
      'dt' => 'type',
      'formatter' => function( $d, $row ) {
        return '<span class="label label-warning">'.ucfirst(my_str_replace('_',' ',$row['type'])).'</span>';
      }
    ),
  array( 'db' => 'invoice_id', 'dt' => 'ref_no' ),
  array( 'db' => 'details', 'dt' => 'details' ),
  array( 
    'db' => 'pmethod_id',   
    'dt' => 'pmethod_name' ,
    'formatter' => function($d, $row) {
      $o = '<b>'.get_the_pmethod($row['pmethod_id'], 'name').'</b>';
      $details = valid_unserialize($row['details']);
      if (!empty($details)) {
        $o .= '<ul>';
        foreach ($details as $key => $value) {
          $o .= '<li>'. my_str_replace('_',' ', utf8_strtoupper($key)) . ' = '.$value.'</li>';
        }
        $o .= '</ul>';
      }
      return $o;
    }
  ),
  array( 
      'db' => 'note',  
      'dt' => 'note',
      'formatter' => function( $d, $row ) {
        return $row['note'];
      }
    ),
  array( 
      'db' => 'total',  
      'dt' => 'amount',
      'formatter' => function( $d, $row ) {
        return currency_format($row['total']);
      }
    ),
);
 
echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

/**
 *===================
 * END DATATABLE
 *===================
 */