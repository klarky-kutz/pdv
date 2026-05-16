<?php 
ob_start();
session_start();
include ("../_init.php");

// API simples para ativar/desativar loja (campo `status` da tabela `stores`).
// Usada pelo painel /conta (Visão Geral) para os botões "Ativar" / "Desativar".

header('Content-Type: application/json; charset=UTF-8');

try {
  // Verifica login
  if (!is_loggedin()) {
    throw new Exception(trans('error_login'));
  }

  // Permissão: aproveitamos a mesma permissão de atualizar loja
  if (user_group_id() != 1 && !has_permission('access', 'update_store')) {
    throw new Exception(trans('error_update_permission'));
  }

  // Valida dados
  $store_id = isset($request->post['store_id']) ? (int)$request->post['store_id'] : 0;
  $status   = isset($request->post['status']) ? (int)$request->post['status'] : null;

  if (!$store_id) {
    throw new Exception(trans('error_store_id'));
  }

  if ($status !== 0 && $status !== 1) {
    throw new Exception('Status inválido.');
  }

  // Garante que a loja pertence ao usuário (se não for admin)
  if (user_group_id() != 1) {
    $belongsStores = $user->getBelongsStore(user_id());
    $allowedIds = array();
    foreach ($belongsStores as $s) {
      $allowedIds[] = (int)$s['store_id'];
    }
    if (!in_array($store_id, $allowedIds, true)) {
      throw new Exception(trans('error_access_permission'));
    }
  }

  // Atualiza status na tabela stores
  $statement = db()->prepare("UPDATE `stores` SET `status` = ? WHERE `store_id` = ?");
  $statement->execute(array($status, $store_id));

  $msg = $status ? 'Loja ativada com sucesso.' : 'Loja desativada com sucesso.';

  echo json_encode(array('status' => 'success', 'msg' => $msg));
  exit();

} catch (Exception $e) {
  header('HTTP/1.1 422 Unprocessable Entity');
  echo json_encode(array('errorMsg' => $e->getMessage()));
  exit();
}
