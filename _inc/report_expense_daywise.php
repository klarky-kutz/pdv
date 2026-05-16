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

$store_id = store_id();
$user_id = user_id();

/**
 *===================
 * START DATATABLE
 *===================
 */

$where_query = "bank_transaction_info.store_id = $store_id AND bank_transaction_info.transaction_type IN ('withdraw') AND bank_transaction_info.is_hide != 1";
if (isset($request->get['account_id']) && $request->get['account_id'] != 'null') {
  $account_id = $request->get['account_id'];
  $where_query .= " AND bank_transaction_info.account_id = $account_id";
}

$from = from();
$to = to();
$where_query .= date_range_accounting_filter($from, $to);

// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`info_id`) AS info_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`transaction_type`) AS transaction_type,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`is_substract`) AS is_substract,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`is_hide`) AS is_hide,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`account_id`) AS account_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`source_id`) AS source_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`exp_category_id`) AS exp_category_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`ref_no`) AS ref_no,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`invoice_id`) AS invoice_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`title`) AS title,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`details`) AS details,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`from_account_id`) AS from_account_id,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`status`) AS status,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`image`) AS image,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `bank_transaction_info`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT expense_categorys.category_slug) AS category_slug, GROUP_CONCAT(DISTINCT bank_transaction_price.price_id) AS price_id, SUM(bank_transaction_price.amount) amount 
  FROM bank_transaction_info 
  JOIN expense_categorys ON bank_transaction_info.exp_category_id = expense_categorys.category_id
  JOIN bank_transaction_price ON bank_transaction_info.info_id = bank_transaction_price.info_id
  WHERE $where_query GROUP BY exp_category_id) as bank_transaction_info";
 
// Table's primary key
$primaryKey = 'info_id';

// indexes
$columns = array(
    array(
        'db' => 'ref_no',
        'dt' => 'DT_RowId',
        'formatter' => function( $d ) {
            return 'row_'.$d;
        }
    ),
    array( 'db' => 'price_id', 'dt' => 'serial_no' ),
     array( 'db' => 'category_slug', 'dt' => 'category_slug' ),
    array( 
      'db' => 'exp_category_id',   
      'dt' => 'title',
      'formatter' => function($d, $row) {
          $parent = '';
          $category = get_the_expense_category($row['exp_category_id']);
          if ($category['parent_id']) {
              $parent = get_the_expense_category($category['parent_id']);
              $parent = $parent['category_name'] .  ' > ';
          }
          $category = get_the_expense_category($row['exp_category_id']);

          if ($row['category_slug'] == 'product_purchase') {
            return '<a href="report_overview.php?type=purchase">'.$parent . $category['category_name'].'</a>';
          }
          return $parent . $category['category_name'];
      }
    ),
    array( 
      'db' => 'amount',   
      'dt' => 'amount',
      'formatter' => function($d, $row) use($from,$to) {
        $total = get_total_category_expense($row['exp_category_id'], $from, $to);
        return currency_format($total);
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