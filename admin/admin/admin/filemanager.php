<?php 
ob_start();
session_start();
include ("../_init.php");
require_once DIR_HELPER . 'ai_concierge.php';

// FILEMANAGER MODAL WINDOW FOR AJAX CALLING
if(isset($request->get['ajax'])) 
{
  if (!is_loggedin()) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => trans('error_login')));
    exit();
  }
  
  if (DEMO) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => trans('text_disable_in_demo')));
    exit();
  }

  // check, if user has reading permission or not
  // if user have not reading permission return error
  if (user_group_id() != 1 && !has_permission('access', 'read_filemanager')) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => trans('error_read_permission')));
    exit();
  }

	include('../_inc/template/partials/filemanager_ajax.php');
	exit();
}

if (DEMO) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}  

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_filemanager')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Set Document Title
$document->setTitle(trans('title_filemanager'));

// ADD BODY CLASS
$document->setBodyClass('sidebar-collapse');

// Add Modern Filemanager CSS
$document->addStyle(root_url().'assets/itsolution24/css/filemanager-modern.css');

// Include Header and Footer
include ("header.php");
include ("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper">

  <!-- Content Start -->
  <section class="content">

    <?php if(DEMO) : ?>
    <div class="box">
      <div class="box-body">
        <div class="alert alert-info mb-0">
          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
  	<div class="filemanger-width">
  		<?php
        include('../_inc/template/partials/filemanager_modern.php');
      ?>
  	</div>
  </section>
  <!-- Content End -->
</div>
<!-- Content Wrapper End -->

<!-- Modern Filemanager JS -->
<script>
// Define URL do FileManager isolada por tenant
window.FILEMANAGERURL = '<?php 
    $tenantId = function_exists('ai_resolve_filemanager_tenant_id')
        ? (int)ai_resolve_filemanager_tenant_id()
        : (isset($_SESSION["tenant_id"]) ? (int)$_SESSION["tenant_id"] : 0);
    if ($tenantId > 0) {
        echo root_url() . "storage/products/" . $tenantId;
    } else {
        echo FILEMANAGERURL ? FILEMANAGERURL : root_url() . "storage/products";
    }
?>';
</script>
<script src="<?php echo root_url(); ?>assets/itsolution24/js/filemanager-modern.js"></script>
    
<?php include ("footer.php"); ?>
