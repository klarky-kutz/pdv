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
if (user_group_id() != 1 && !has_permission('access', 'read_purchase_report')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

/**
 *===================
 * START DATATABLE
 *===================
 */

$where_query = "purchase_info.inv_type != 'expense' AND purchase_item.store_id = " . store_id();
$from = from();
$to = to();
$where_query .= date_range_filter2($from, $to);

// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `purchase_info`.`info_id`) AS info_id,
GROUP_CONCAT(DISTINCT `purchase_info`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `purchase_info`.`inv_type`) AS inv_type,
GROUP_CONCAT(DISTINCT `purchase_info`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `purchase_info`.`sup_id`) AS sup_id,
GROUP_CONCAT(DISTINCT `purchase_info`.`total_item`) AS total_item,
GROUP_CONCAT(DISTINCT `purchase_info`.`status`) AS status,
GROUP_CONCAT(DISTINCT `purchase_info`.`total_sell`) AS total_sell,
GROUP_CONCAT(DISTINCT `purchase_info`.`purchase_note`) AS purchase_note,
GROUP_CONCAT(DISTINCT `purchase_info`.`attachment`) AS attachment,
GROUP_CONCAT(DISTINCT `purchase_info`.`is_visible`) AS is_visible,
GROUP_CONCAT(DISTINCT `purchase_info`.`payment_status`) AS payment_status,
GROUP_CONCAT(DISTINCT `purchase_info`.`checkout_status`) AS checkout_status,
GROUP_CONCAT(DISTINCT `purchase_info`.`shipping_status`) AS shipping_status,
GROUP_CONCAT(DISTINCT `purchase_info`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `purchase_info`.`purchase_date`) AS purchase_date,
GROUP_CONCAT(DISTINCT `purchase_info`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `purchase_info`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT categorys.category_name) AS category_name, GROUP_CONCAT(DISTINCT purchase_item.id) AS id, GROUP_CONCAT(DISTINCT purchase_item.category_id) AS category_id, GROUP_CONCAT(DISTINCT purchase_item.item_quantity) AS item_quantity,
SUM(purchase_item.item_total) as purchase_price, SUM(purchase_item.item_quantity) as total_stock, SUM(purchase_price.paid_amount) as paid_amount FROM purchase_item 
      LEFT JOIN categorys ON (purchase_item.category_id = categorys.category_id)
      LEFT JOIN purchase_info ON (purchase_item.invoice_id = purchase_info.invoice_id)
      LEFT JOIN purchase_price ON (purchase_item.invoice_id = purchase_price.invoice_id)
      WHERE $where_query
      GROUP BY purchase_item.category_id
      ORDER BY total_stock DESC) as purchase_item";

// Table's primary key
$primaryKey = 'id';
$columns = array(
    array( 'db' => 'category_id', 'dt' => 'category_id' ),
    array( 
      'db' => 'created_at',  
      'dt' => 'created_at',
      'formatter' => function( $d, $row ) {
        return date('Y-m-d', strtotime($row['created_at']));
      }
    ),
    array( 
      'db' => 'category_name',  
      'dt' => 'category_name',
      'formatter' => function( $d, $row ) {
        return $row['category_name'];
      }
    ),
    array( 
      'db' => 'total_stock',  
      'dt' => 'total_item',
      'formatter' => function( $d, $row ) {
        return currency_format($row['total_stock']);
      }
    ),
    array( 
      'db' => 'purchase_price',  
      'dt' => 'purchase_price',
      'formatter' => function( $d, $row ) {
        $total = $row['purchase_price'];
        return currency_format($total);
      }
    ),
    array( 
      'db' => 'paid_amount',  
      'dt' => 'paid_amount',
      'formatter' => function( $d, $row ) {
        $total = $row['paid_amount'];
        return currency_format($total);
      }
    )
);
 
echo json_encode(
    SSP::simple( $request->get, $sql_details, $table, $primaryKey, $columns )
);

/**
 *===================
 * END DATATABLE
 *===================
 */