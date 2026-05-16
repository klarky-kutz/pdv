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
if (user_group_id() != 1 && !has_permission('access', 'read_purchase_payment_report')) {
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

$where_query = "purchase_payments.store_id = '$store_id' AND is_hide != 1";
$from = from();
$to = to();
$where_query .= date_range_purchase_payments_filter($from, $to);

// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `purchase_payments`.`id`) AS id,
GROUP_CONCAT(DISTINCT `purchase_payments`.`type`) AS type,
GROUP_CONCAT(DISTINCT `purchase_payments`.`is_hide`) AS is_hide,
GROUP_CONCAT(DISTINCT `purchase_payments`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `purchase_payments`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `purchase_payments`.`reference_no`) AS reference_no,
GROUP_CONCAT(DISTINCT `purchase_payments`.`pmethod_id`) AS pmethod_id,
GROUP_CONCAT(DISTINCT `purchase_payments`.`transaction_id`) AS transaction_id,
GROUP_CONCAT(DISTINCT `purchase_payments`.`details`) AS details,
GROUP_CONCAT(DISTINCT `purchase_payments`.`attachment`) AS attachment,
GROUP_CONCAT(DISTINCT `purchase_payments`.`note`) AS note,
GROUP_CONCAT(DISTINCT `purchase_payments`.`amount`) AS amount,
GROUP_CONCAT(DISTINCT `purchase_payments`.`total_paid`) AS total_paid,
GROUP_CONCAT(DISTINCT `purchase_payments`.`balance`) AS balance,
GROUP_CONCAT(DISTINCT `purchase_payments`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `purchase_payments`.`created_at`) AS created_at,
SUM(amount) as totalAmount FROM purchase_payments 
        WHERE $where_query GROUP BY `invoice_id`) as purchase_payments";

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
      'db' => 'totalAmount',  
      'dt' => 'amount',
      'formatter' => function( $d, $row ) {
        return currency_format($row['totalAmount']);
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