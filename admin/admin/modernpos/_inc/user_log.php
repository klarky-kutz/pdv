<?php 
// Garantir resposta JSON válida para DataTables (evitar HTML/warnings quebrando o JSON)
ob_start();
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
@session_start();
@include ("../_init.php");
ob_end_clean();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

$draw = isset($request->get['draw']) ? (int)$request->get['draw'] : 0;
$dtError = function (string $msg) use ($draw) {
  echo json_encode([
    'draw' => $draw,
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => [],
    'error' => $msg,
  ]);
  exit();
};

// Check, if user logged in or not
if (!is_loggedin()) {
  $dtError(trans('error_login'));
}

// Permissões:
// - Admin (group_id=1) pode ver.
// - Quem tem permissão read_user_log pode ver.
// - Sem permissão, ainda pode ver o PRÓPRIO user_id.
$requestedUserId = isset($request->get['user_id']) ? (int)$request->get['user_id'] : 0;
if ($requestedUserId <= 0) {
  $dtError(trans('error_user_id'));
}

if (
  user_group_id() != 1
  && !has_permission('access', 'read_user_log')
  && (int)$requestedUserId !== (int)user_id()
) {
  $dtError(trans('error_read_permission'));
}

/**
 *===================
 * START DATATABLE
 *===================
 */

$Hooks->do_action('Before_Showing_User_Log');

$user_id = $requestedUserId;
$where_query = "user_id = '{$user_id}'";

$fromRaw = isset($request->get['from']) ? (string)$request->get['from'] : '';
if ($fromRaw !== '' && $fromRaw !== 'null' && $fromRaw !== 'undefined') {
    $from = from();
    $to = to();
    $where_query .= date_range_filter_logs($from, $to);
}


// DB table to use
$table = "(SELECT login_logs.* FROM login_logs WHERE $where_query) as login_logs";
 
// Table's primary key
$primaryKey = 'id';

$columns = array(
    array(
        'db' => 'id',
        'dt' => 'DT_RowId',
        'formatter' => function( $d) {
            return 'row_'.$d;
        }
    ),
    array( 'db' => 'id', 'dt' => 'serial' ),
    array( 'db' => 'username', 'dt' => 'username' ),
    array( 'db' => 'ip', 'dt' => 'ip' ),
    array( 
      'db' => 'created_at',   
      'dt' => 'time' ,
      'formatter' => function($d, $row) {
          return format_date($row['created_at']);
      }
    ),
);
 
echo json_encode(
  SSP::simple( $request->get, $sql_details, $table, $primaryKey, $columns)
);

$Hooks->do_action('After_Showing_User_Log');

/**
 *===================
 * END DATATABLE
 *===================
 */