<?php 
ob_start();
session_start();
include '../_init.php';

// ========== INÍCIO: SaaS Limits Check ==========
include('../_inc/saas_limits_check.php');
$productLimitInfo = get_limit_info('products');
// ========== FIM: SaaS Limits Check ==========

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_product')) {
	redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Set Document Title
$document->setTitle(trans('title_product'));

// Add Script
$document->addScript('../assets/tinymce/tinymce.min.js');
$document->addScript('../assets/itsolution24/angular/modals/StockAdjustmentModal.js');
$document->addScript('../assets/itsolution24/angular/modals/ProductSelectorModal.js');
$document->addScript('../assets/itsolution24/angular/controllers/ProductController.js');

// Add Style - Product Selector Modal
$document->addStyle('../assets/itsolution24/css/product-selector-modal.css');

// Include Header and Footer
include("header.php"); 
include ("left_sidebar.php"); 
?>
<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="ProductController">

  	<!-- Content Header Start -->
	<section class="content-header">
		<h1>
			<?php echo trans('text_products'); ?>
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
			<li>
				<?php if (isset($request->get['location']) && $request->get['location']=='trash'): ?>
					<a href="product.php"><?php echo trans('text_products'); ?></a>	
				<?php else: ?>
					<?php echo trans('text_products'); ?>	
				<?php endif; ?>
			</li>
			<?php if (isset($request->get['location']) && $request->get['location']=='trash'): ?>
				<li class="active">
					<?php echo trans('text_trash'); ?>	
				</li>
			<?php endif; ?>
		</ol>
	</section>
  	<!-- Content Header End -->

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
	    
    <?php if (user_group_id() == 1 || has_permission('access', 'create_product')) : ?>
    
    <?php // ========== INÍCIO: Verificação de Limite SaaS ========== ?>
    <?php if ($productLimitInfo['is_saas'] && !$productLimitInfo['can_create'] && !$productLimitInfo['unlimited']): ?>
        
        <!-- Box de Limite Atingido -->
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="fa fa-fw fa-ban"></span> 
                    Limite de Produtos Atingido
                </h3>
            </div>
            <div class="box-body text-center" style="padding: 40px;">
                <div style="font-size: 64px; color: #dd4b39; margin-bottom: 20px;">
                    <i class="fa fa-cubes"></i>
                </div>
                <h4>Você atingiu o limite do seu plano</h4>
                <p style="color: #666; margin: 15px 0;">
                    Seu plano <strong><?php echo htmlspecialchars($productLimitInfo['plan_name']); ?></strong> 
                    permite até <strong><?php echo $productLimitInfo['limit']; ?> produtos</strong>.
                </p>
                <div class="well well-sm" style="display: inline-block; margin: 20px 0;">
                    <span class="text-danger" style="font-size: 18px; font-weight: 600;">
                        <?php echo $productLimitInfo['current']; ?> / <?php echo $productLimitInfo['limit']; ?> produtos utilizados
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
					<span class="fa fa-fw fa-plus"></span> <?php echo sprintf(trans('text_add_new'), trans('text_product')); ?>
				</h3>
				<button  type="button" class="btn btn-box-tool add-new-btn" data-widget="collapse" data-collapse="true">
					<i class="fa <?php echo !create_box_state() ? 'fa-minus' : 'fa-plus'; ?>"></i>
				</button>
	        </div>

	        <?php if (isset($error_message)): ?>
	        	<div class="alert alert-danger">
					<p>
						<span class="fa fa-warning"></span> 
						<?php echo $error_message; ?>
					</p>
	        	</div>
	        <?php elseif (isset($success_message)): ?>
	          <div class="alert alert-success">
				<p>
					<span class="fa fa-check"></span> 
					<?php echo $success_message; ?>
				</p>
	          </div>
	        <?php endif; ?>

	        <!-- Include Product Form -->
	        <?php include('../_inc/template/product_create_form.php'); ?>

	    </div>
    
    <?php endif; ?>
	    <?php endif; ?>

	    <div class="row">
		    <form action="product_bulk_action.php" method="post" enctype="multipart/form-data" id="product-list-form">
			    <div class="col-xs-12">
			        <div class="box box-success">
				        <div class="box-header">
				            <h3 class="box-title">
				            	<?php echo sprintf(trans('text_view_all'), trans('text_product')); ?>	
				            </h3>

				            <!--Box Tools End-->
				            <div class="box-tools pull-right">

				               <!-- Filter Product Supplier Wise -->
				               <?php include('../_inc/template/partials/product_filter.php'); ?>

					            <!-- Trash Box -->
				                <div class="btn-group">
					                <a type="button" class="btn btn-danger" href="product.php?location=trash">
					                  	<span class="fa fa-trash"></span> 
					                  	<?php echo trans('button_trash'); ?> 
					                  	<i class="badge badge-warning" id="total-trash">
					                  		<?php echo total_trash_product(); ?>
					                  	</i>
					                </a>
				                </div>

				                <!-- Bulk Action -->
			                	<?php if (user_group_id() == 1 || has_permission('access', 'product_bulk_action')) : ?>
				                <div class="btn-group">
					                <button type="button" class="btn btn-warning dropdown-toggle" data-toggle="dropdown">
					                	<?php echo trans('button_bulk'); ?>
					                    <span class="caret"></span>
					                </button>
					                <ul class="dropdown-menu" role="menu">
					                	<?php if (user_group_id() == 1 || has_permission('access', 'delete_all_product')) : ?>
						                    <li>
						                    	<a id="button-add-stock" href="#" data-form="#product-list-form" data-loading-text="Processing...">
						                    		<?php echo trans('button_add_stock'); ?>
						                    	</a>
						                    </li>
						                    <li>
						                    	<a id="delete-all" href="#" data-form="#product-list-form" data-loading-text="Deleting...">
						                    		<?php echo trans('button_delete_all'); ?>
						                    	</a>
						                    </li>
						                    <?php if(isset($request->get['location']) && $request->get['location'] == 'trash') : ?>
						                    <li>
						                    	<a id="restore-all" href="#" data-form="#product-list-form" data-datatable="product-product-list" data-loading-text="Restoring...">
						                      		<?php echo trans('button_restore_all'); ?>
						                    	</a>
						                    </li>
					                    <?php endif;?>
					                    <?php endif; ?>
					                 </ul>
				                </div>
					            <?php endif; ?>

				            </div>
				            <!--  Box Tools End-->

				        </div>
						<div class="box-body">
							<div class="table-responsive">
								<?php
									$print_columns = '2,3,4,5,6,7';
									if (user_group_id() != 1) {
										if (! has_permission('access', 'show_purchase_price')) {
											$print_columns = my_str_replace('6,', '', $print_columns);
										}
									}
									$hide_colums = "";
									if (user_group_id() != 1) {
										if (! has_permission('access', 'product_bulk_action')) {
											$hide_colums .= "0,";
										}
										if (! has_permission('access', 'show_purchase_price')) {
											$hide_colums .= "6,";
										}
										if (! has_permission('access', 'read_product')) {
											$hide_colums .= "8,";
										}
										if (! has_permission('access', 'update_product')) {
											$hide_colums .= "9,";
										}
										if (! has_permission('access', 'create_purchase_invoice')) {
											$hide_colums .= "10,";
										}
										if (! has_permission('access', 'print_barcode')) {
											$hide_colums .= "11,";
										}
										if (! has_permission('access', 'delete_product')) {
											$hide_colums .= "12,";
										}
									}

								?>  
								<table id="product-product-list" class="table table-bordered table-striped table-hover" data-hide-colums="<?php echo $hide_colums; ?>" data-print-columns="<?php echo $print_columns;?>">
								    <thead>
								        <tr class="bg-gray">
								            <th class="w-5 product-head text-center">
								            	<input type="checkbox" onclick="$('input[name*=\'select\']').prop('checked', this.checked);">
								            </th>
								            <th class="w-5">
								            	<?php echo sprintf(trans('label_image'),null); ?>
								            </th>
								            <th class="w-10">
								            	<?php echo sprintf(trans('label_pcode'),null); ?>
								            </th>
								            <th class="w-20">
								            	<?php echo sprintf(trans('label_name'),trans('text_product')); ?>
								            </th>
								            <th class="w-15">
								            	<?php echo trans('label_supplier'); ?>
								            </th>
								            <th class="w-10">
								            	<?php echo trans('label_stock'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_purchase_price'); ?>
								            </th>                        
								            <th class="w-5">
								            	<?php echo trans('label_selling_price'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_view'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_edit'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_purchase'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_print_barcode'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_delete'); ?>
								            </th>
								        </tr>
								    </thead>
								    <tfoot>
										<tr class="bg-gray">
											<th class="w-5 product-head text-center">
								            	<input type="checkbox" onclick="$('input[name*=\'select\']').prop('checked', this.checked);">
								            </th>
								            <th class="w-5">
								            	<?php echo sprintf(trans('label_image'),null); ?>
								            </th>
								            <th class="w-10">
								            	<?php echo sprintf(trans('label_pcode'),null); ?>
								            </th>
								            <th class="w-20">
								            	<?php echo sprintf(trans('label_name'),trans('text_product')); ?>
								            </th>
								            <th class="w-15">
								            	<?php echo trans('label_supplier'); ?>
								            </th>
								            <th class="w-10">
								            	<?php echo trans('label_stock'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_purchase_price'); ?>
								            </th>                        
								            <th class="w-5">
								            	<?php echo trans('label_selling_price'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_view'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_edit'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_purchase'); ?>
								            </th>
								            <th class="w-5">
								            	<?php echo trans('label_print_barcode'); ?>
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
			</form>
	    </div>

	</section>
  	<!-- Content end -->

</div>
<!--  Content Wrapper End -->

<script type="text/javascript">
$(document).ready(function() {
    "use strict";
	storeApp.intiTinymce();
});
</script>

<!-- SaaS Limit Modal -->
<?php include('../_inc/template/partials/limit_reached_modal.php'); ?>

<!-- SaaS Limit Info para JavaScript -->
<script>
var saasProductLimit = <?php echo json_encode($productLimitInfo); ?>;
</script>

<?php include ("footer.php"); ?>
