<?php 
ob_start();
session_start();
include ("../_init.php");

// Check, if user logged in or not
// If user is not logged in then return error
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

// Check, if user has reading permission or not
// If user have not reading permission return error
if (user_group_id() != 1 && !has_permission('access', 'read_bank_account_sheet')) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_read_permission')));
  exit();
}

// LOAD BOX MODEL
$bank_account_model = registry()->get('loader')->model('bankaccount');

/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_BankAccountSheet');

$where_query = 'ba2s.store_id = ' . store_id();
 
// DB table to use
$table = "(SELECT
GROUP_CONCAT(DISTINCT `bank_accounts`.`id`) AS id,
GROUP_CONCAT(DISTINCT `bank_accounts`.`account_name`) AS account_name,
GROUP_CONCAT(DISTINCT `bank_accounts`.`account_details`) AS account_details,
GROUP_CONCAT(DISTINCT `bank_accounts`.`initial_balance`) AS initial_balance,
GROUP_CONCAT(DISTINCT `bank_accounts`.`account_no`) AS account_no,
GROUP_CONCAT(DISTINCT `bank_accounts`.`contact_person`) AS contact_person,
GROUP_CONCAT(DISTINCT `bank_accounts`.`phone_number`) AS phone_number,
GROUP_CONCAT(DISTINCT `bank_accounts`.`opening_date`) AS opening_date,
GROUP_CONCAT(DISTINCT `bank_accounts`.`url`) AS url,
GROUP_CONCAT(DISTINCT `bank_accounts`.`total_transfer_from_other`) AS total_transfer_from_other,
GROUP_CONCAT(DISTINCT `bank_accounts`.`total_transfer_to_other`) AS total_transfer_to_other,
GROUP_CONCAT(DISTINCT `bank_accounts`.`created_at`) AS created_at,
GROUP_CONCAT(DISTINCT `bank_accounts`.`updated_at`) AS updated_at,
GROUP_CONCAT(DISTINCT `ba2s`,`ba2s`) AS ba2s,
GROUP_CONCAT(DISTINCT `ba2s`,`store_id`) AS store_id,
GROUP_CONCAT(DISTINCT `ba2s`,`account_id`) AS account_id,
GROUP_CONCAT(DISTINCT `ba2s`,`deposit`) AS deposit,
GROUP_CONCAT(DISTINCT `ba2s`,`withdraw`) AS withdraw,
GROUP_CONCAT(DISTINCT `ba2s`,`transfer_from_other`) AS transfer_from_other,
GROUP_CONCAT(DISTINCT `ba2s`,`transfer_to_other`) AS transfer_to_other,
GROUP_CONCAT(DISTINCT `ba2s`,`status`) AS status,
GROUP_CONCAT(DISTINCT `ba2s`.`sort_order`) AS sort_order
FROM bank_accounts 
  LEFT JOIN bank_account_to_store ba2s ON (bank_accounts.id = ba2s.account_id) 
  WHERE $where_query GROUP BY id
  ) as bank_accounts";
 
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
  array( 
    'db' => 'account_name',   
    'dt' => 'account_name' ,
    'formatter' => function($d, $row) {
        return $row['account_name'];
    }
  ),
  array( 
    'db' => 'deposit',   
    'dt' => 'deposit' ,
    'formatter' => function($d, $row) {
        return currency_format($row['deposit']);
    }
  ),
  array( 
    'db' => 'withdraw',   
    'dt' => 'withdraw' ,
    'formatter' => function($d, $row) {
        return currency_format($row['withdraw']);
    }
  ),
  array( 
    'db' => 'transfer_to_other',   
    'dt' => 'transfer_to_other' ,
    'formatter' => function($d, $row) {
        return currency_format($row['transfer_to_other']);
    }
  ),
  array( 
    'db' => 'transfer_from_other',   
    'dt' => 'transfer_from_other' ,
    'formatter' => function($d, $row) {
        return currency_format($row['transfer_from_other']);
    }
  ),
  array( 
    'db' => 'id',   
    'dt' => 'balance' ,
    'formatter' => function($d, $row) {
        return currency_format(($row['deposit'] + $row['transfer_from_other']) - ($row['withdraw'] + $row['transfer_to_other']));
    }
  ),
); 

echo json_encode(
    SSP::simple($request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_BankAccountSheet');

/**
 *===================
 * END DATATABLE
 *===================
 */