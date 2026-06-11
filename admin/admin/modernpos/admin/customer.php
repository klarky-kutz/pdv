<?php 
ob_start();
session_start();
include ("../_init.php");

// ========== INÍCIO: SaaS Limits Check ==========
include('../_inc/saas_limits_check.php');
$customerLimitInfo = get_limit_info('customers');
// ========== FIM: SaaS Limits Check ==========

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_customer')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Set Document Title
$document->setTitle(trans('text_customer_list_title'));

// Add Script
$document->addScript('../assets/itsolution24/angular/controllers/CustomerController.js');

// Include Header and Footer
include("header.php"); 
include ("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="CustomerController">

  <!-- Content Header Start -->
  <section class="content-header">
    <h1>
      <?php echo trans('text_customer_title'); ?>
      <small>
        <?php echo store('name'); ?>
      </small>
    </h1>
    <ol class="breadcrumb">
      <li>
        <a href="dashboard.php">
          <i class="fa fa-dashboard"></i> 
          <?php echo trans('text_dashboard'); ?>
        </a>
      </li>
      <li class="active">
        <?php echo trans('text_customer_title'); ?>
      </li>
    </ol>
  </section>
  <!-- Content Header Start -->

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
    
    <?php if (user_group_id() == 1 || has_permission('access', 'create_customer')) : ?>
    
    <?php // ========== INÍCIO: Verificação de Limite SaaS ========== ?>
    <?php if ($customerLimitInfo['is_saas'] && !$customerLimitInfo['can_create'] && !$customerLimitInfo['unlimited']): ?>
        
        <!-- Box de Limite Atingido -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="fa fa-fw fa-ban"></span> 
                    Limite de Clientes Atingido
                </h3>
            </div>
            <div class="box-body text-center" style="padding: 40px;">
                <div style="font-size: 64px; color: #dd4b39; margin-bottom: 20px;">
                    <i class="fa fa-users"></i>
                </div>
                <h4>Você atingiu o limite do seu plano</h4>
                <p style="color: #666; margin: 15px 0;">
                    Seu plano <strong><?php echo htmlspecialchars($customerLimitInfo['plan_name']); ?></strong> 
                    permite até <strong><?php echo $customerLimitInfo['limit']; ?> clientes</strong>.
                </p>
                <div class="well well-sm" style="display: inline-block; margin: 20px 0;">
                    <span class="text-danger" style="font-size: 18px; font-weight: 600;">
                        <?php echo $customerLimitInfo['current']; ?> / <?php echo $customerLimitInfo['limit']; ?> clientes utilizados
                    </span>
                </div>
                <br>
                <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-success btn-lg">
                    <i class="fa fa-arrow-up"></i> Fazer Upgrade do Plano
                </a>
            </div>
        </div>
        
    <?php else: ?>
    <?php // ========== FIM: Verificação de Limite SaaS ========== ?>
      
      <div class="box box-info<?php echo create_box_state(); ?>">
        <div class="box-header with-border">
          <h3 class="box-title">
            <span class="fa fa-fw fa-plus"></span> <?php echo trans('text_new_customer_title'); ?>
          </h3>
          <button type="button" class="btn btn-box-tool add-new-btn" data-widget="collapse" data-collapse="true">
            <i class="fa <?php echo !create_box_state() ? 'fa-minus' : 'fa-plus'; ?>"></i>
          </button>
        </div>

        <?php if (isset($error_message)): ?>
          <div class="alert alert-danger">
            <p>
              <span class="fa fa-warning"></span> 
              <?php echo $error_message ; ?>
            </p>
          </div>
        <?php elseif (isset($success_message)): ?>
          <div class="alert alert-success">
            <p>
              <span class="fa fa-check"></span> 
              <?php echo $success_message ; ?>
            </p>
          </div>
        <?php endif; ?>
        
        <!-- Add Customer Create Form -->
        <?php include('../_inc/template/customer_create_form.php'); ?>

      </div>
      
    <?php endif; ?>
    <?php endif; ?>

    <div class="row">
      <div class="col-xs-12">
        <div class="box box-success">
          <div class="box-header">
            <h3 class="box-title">
              <?php echo trans('text_customer_list_title'); ?>
            </h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">  
              <?php
                $hide_colums = "";
                if (user_group_id() != 1) {
                  if (!has_permission('access', 'create_sell_invoice')) {
                    $hide_colums .= "5,";
                  }
                  if (!has_permission('access', 'read_customer_profile')) {
                    $hide_colums .= "6,";
                  }
                  if (!has_permission('access', 'update_customer')) {
                    $hide_colums .= "7,";
                  }
                  if (!has_permission('access', 'delete_customer')) {
                    $hide_colums .= "8,";
                  }
                }
              ?> 
              <!-- Customer List Start -->
              <table id="customer-customer-list" class="table table-bordered table-striped table-hover" data-hide-colums="<?php echo $hide_colums; ?>">
                <thead>
                  <tr class="bg-gray">
                    <th class="w-10">
                      <?php echo sprintf(trans('label_id'), null); ?>
                    </th>
                    <th class="w-30">
                      <?php echo sprintf(trans('label_name'), null); ?>
                    </th>
                    <th class="w-15">
                      <?php echo sprintf(trans('label_phone'), null); ?>
                    </th>
                    <th class="w-5">
                      <?php echo sprintf(trans('label_sex'), null); ?>
                    </th>
                    <th class="w-20">
                      <?php echo trans('label_balance'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_sell'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_view'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_edit'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_delete'); ?>
                    </th>
                  </tr>
                </thead>
                <tfoot>
                  <tr class="bg-gray">
                    <th class="w-10">
                      <?php echo sprintf(trans('label_id'), null); ?>
                    </th>
                    <th class="w-30">
                      <?php echo sprintf(trans('label_name'), null); ?>
                    </th>
                    <th class="w-15">
                      <?php echo sprintf(trans('label_phone'), null); ?>
                    </th>
                    <th class="w-5">
                      <?php echo sprintf(trans('label_sex'), null); ?>
                    </th>
                    <th class="w-20">
                      <?php echo trans('label_balance'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_sell'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_view'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_edit'); ?>
                    </th>
                    <th class="w-5">
                      <?php echo trans('label_delete'); ?>
                    </th>
                  </tr>
                </tfoot>
              </table> 
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Content End -->
</div>
<!-- Content Wrapper End -->

<!-- SaaS Limit Modal -->
<?php include('../_inc/template/partials/limit_reached_modal.php'); ?>

<!-- SaaS Limit Info para JavaScript -->
<script>
var saasCustomerLimit = <?php echo json_encode($customerLimitInfo); ?>;
</script>

<?php include ("footer.php"); ?>
