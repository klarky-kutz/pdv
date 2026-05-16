<?php 
ob_start();
session_start();
include ("../_init.php");
if (!is_loggedin()) {
  header('HTTP/1.1 422 Unprocessable Entity');
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(array('errorMsg' => trans('error_login')));
  exit();
}

if(isset($_FILES["file"]["type"]))
{
	if (DEMO) {
      echo trans('text_disabled_in_demo');
      exit();
    }
    
	// Check Permission
	if (user_group_id() != 1 && !has_permission('access', 'upload_logo')) {
      echo trans('error_upload_logo_permission');
      exit();
    }

    // Validate store id
    if (!validateInteger($request->post['store_id'])) {
    	echo trans('error_store_id');
    	 exit();
    }
    
    $store_id = (int)$request->post['store_id'];

    // Segurança extra (SaaS): se houver tenant_id, impede upload em loja de outro tenant
    try {
      $uid = function_exists('user_id') ? (int)user_id() : 0;
      if ($uid > 0) {
        $hasTenantUsers = false;
        $hasTenantStores = false;

        $st = db()->prepare("SHOW COLUMNS FROM `users` LIKE 'tenant_id'");
        $st->execute();
        $hasTenantUsers = $st->rowCount() > 0;

        $st = db()->prepare("SHOW COLUMNS FROM `stores` LIKE 'tenant_id'");
        $st->execute();
        $hasTenantStores = $st->rowCount() > 0;

        if ($hasTenantUsers && $hasTenantStores) {
          $st = db()->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
          $st->execute([$uid]);
          $tenantId = (int)$st->fetchColumn();

          if ($tenantId > 0) {
            $st = db()->prepare("SELECT tenant_id FROM stores WHERE store_id = ? LIMIT 1");
            $st->execute([$store_id]);
            $storeTenantId = (int)$st->fetchColumn();

            if ($storeTenantId !== $tenantId) {
              echo trans('error_upload_logo_permission');
              exit();
            }
          }
        }
      }
    } catch (Throwable $e) {
      // ignore (fallback para comportamento antigo)
    }

    $Hooks->do_action('Before_Upload_Logo', $request);
    
	$validextensions = array("jpeg", "jpg", "png");
	$temporary = explode(".", $_FILES["file"]["name"]);
	$file_extension = end($temporary);
	if (((	$_FILES["file"]["type"] == "image/png") || ($_FILES["file"]["type"] == "image/jpg") || ($_FILES["file"]["type"] == "image/jpeg")
	) && ($_FILES["file"]["size"] < 2097152) // 2 MB
	&& in_array($file_extension, $validextensions)) {

		if ($_FILES["file"]["error"] > 0) {
			echo "Return Code: " . $_FILES["file"]["error"] . "<br/><br/>";
			exit();
		} else {

			$temp = explode(".", $_FILES["file"]["name"]);
			$newfilename = $store_id . '_logo.' . end($temp);
			$sourcePath = $_FILES["file"]["tmp_name"];
			$targetPath = "../assets/itsolution24/img/logo-favicons/".$newfilename;
			if(move_uploaded_file($sourcePath,$targetPath)) {
				$statement = db()->prepare("UPDATE `stores` SET `logo` = ? WHERE `store_id` = ?");
				$statement->execute(array($newfilename, $store_id));
			}
			echo "<span class='success'>Logo Successfully Uploaded...!!</span><br/>";
		}
		$Hooks->do_action('After_Upload_Logo', $request);

	} else {

		echo "<span class='invalid'>***Invalid file Size or Type***<span>";
	}
}