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

$Hooks->do_action('Before_Showing_Loss_List');

$where_query = "returnable='no' AND status=1";

$from = from();
$to = to();

// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `expenses`.`id`) AS id,
GROUP_CONCAT(DISTINCT `expenses`.`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `expenses`.`reference_no`) AS reference_no,
GROUP_CONCAT(DISTINCT `expenses`.`category_id`) AS category_id,
GROUP_CONCAT(DISTINCT `expenses`.`title`) AS title,
GROUP_CONCAT(DISTINCT `expenses`.`amount`) AS amount,
GROUP_CONCAT(DISTINCT `expenses`.`returnable`) AS returnable,
GROUP_CONCAT(DISTINCT `expenses`.`note`) AS note,
GROUP_CONCAT(DISTINCT `expenses`.`attachment`) AS attachment,
GROUP_CONCAT(DISTINCT `expenses`.`status`) AS status,
GROUP_CONCAT(DISTINCT `expenses`.`created_by`) AS created_by,
GROUP_CONCAT(DISTINCT `expenses`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `expenses`.`updated_at`) AS updated_at
FROM expenses 
  WHERE $where_query GROUP BY category_id
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
  array( 'db' => 'id', 'dt' => 'serial_no' ),
  array( 
    'db' => 'category_id',   
    'dt' => 'title',
    'formatter' => function($d, $row) {
        $parent = '';
        $category = get_the_expense_category($row['category_id']);
        if ($category['parent_id']) {
            $parent = get_the_expense_category($category['parent_id']);
            $parent = $parent['category_name'] .  ' > ';
        }
        $category = get_the_expense_category($row['category_id']);
        return $parent . $category['category_name'];
    }
  ),
  array( 
    'db' => 'amount',   
    'dt' => 'this_month',
    'formatter' => function($d, $row) use($from,$to) {
      $year = $from ? date('Y', strtotime($from)) : year();
      $month = $from ? date('m', strtotime($from)) : month();
      $days_in_month = get_total_day_in_month();
      $from = date('Y-m-d',strtotime($year.'-'.$month.'-1'));
      $to = $year.'-'.$month.'-'.$days_in_month;
      $total = get_total_category_expense($row['category_id'],$from, $to,store_id(),'no');
      return currency_format($total);
    }
  ),
  array( 
    'db' => 'amount',   
    'dt' => 'this_year',
    'formatter' => function($d, $row) use($from,$to) {
      $year = $from ? date('Y', strtotime($from)) : year();
      $from = date('Y-m-d',strtotime($year.'-1-1'));
      $to = $year.'-12-31';
      $total = get_total_category_expense($row['category_id'],$from, $to,store_id(),'no');
      return currency_format($total);
    }
  ),
  array( 
    'db' => 'amount',   
    'dt' => 'till_now',
    'formatter' => function($d, $row) {
      $total = get_total_category_expense($row['category_id'],null,null,store_id(),'no');
      return currency_format($total);
    }
  ),
); 

echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_Loss_List');

/**
 *===================
 * END DATATABLE
 *===================
 */