<?php
ob_start();
session_start();
include ("../_init.php");

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

if (user_group_id() != 1 && !has_permission('access', 'read_sell_list')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Buscar totais por forma de pagamento
$from = from() ? from() : date('Y-m-d');
$to = to() ? to() : date('Y-m-d');

$store_id = store_id();
$date_filter = "AND DATE(p.created_at) BETWEEN '$from' AND '$to'";

// Query para buscar faturamento por método de pagamento
$sql = "SELECT 
    pm.pmethod_id,
    pm.name as method_name,
    pm.code_name,
    COALESCE(SUM(p.amount), 0) as total_amount,
    COUNT(p.id) as total_transactions
FROM pmethods pm
LEFT JOIN pmethod_to_store pts ON pm.pmethod_id = pts.ppmethod_id AND pts.store_id = '$store_id'
LEFT JOIN payments p ON pm.pmethod_id = p.pmethod_id AND p.store_id = '$store_id' $date_filter
WHERE pts.status = 1
GROUP BY pm.pmethod_id
ORDER BY total_amount DESC
LIMIT 4";

$statement = db()->prepare($sql);
$statement->execute();
$payment_stats = $statement->fetchAll(PDO::FETCH_ASSOC);

// Total geral
$sql_total = "SELECT COALESCE(SUM(amount), 0) as total FROM payments p WHERE p.store_id = '$store_id' $date_filter";
$stmt_total = db()->prepare($sql_total);
$stmt_total->execute();
$total_geral = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

// Cores e ícones para cada método
$method_styles = [
    'pix' => ['bg' => 'bg-aqua', 'icon' => 'fa-qrcode'],
    'cash' => ['bg' => 'bg-green', 'icon' => 'fa-money'],
    'cod' => ['bg' => 'bg-green', 'icon' => 'fa-money'],
    'card_credit' => ['bg' => 'bg-purple', 'icon' => 'fa-credit-card'],
    'card_debit' => ['bg' => 'bg-blue', 'icon' => 'fa-credit-card-alt'],
    'credit' => ['bg' => 'bg-yellow', 'icon' => 'fa-user'],
    'gift_card' => ['bg' => 'bg-red', 'icon' => 'fa-gift'],
    'bkash' => ['bg' => 'bg-maroon', 'icon' => 'fa-mobile'],
];
$default_style = ['bg' => 'bg-gray', 'icon' => 'fa-dollar'];

$document->setTitle(trans('title_invoice'));
$document->addScript('../assets/itsolution24/angular/modals/InstallmentPaymentModal.js');
$document->addScript('../assets/itsolution24/angular/modals/InstallmentViewModal.js');
$document->addScript('../assets/itsolution24/angular/controllers/InvoiceController.js');
$document->setBodyClass('sidebar-collapse');

include("header.php"); 
include ("left_sidebar.php");
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="InvoiceController">
	
	<!-- Content Header Start -->
	<section class="content-header">
		<?php include ("../_inc/template/partials/apply_filter.php"); ?>
		<h1>
		    <?php echo trans('text_sell_list_title'); ?>
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
		    	<?php echo trans('text_sell_list_title'); ?>
		    </li>
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

	    <!-- Cards de Faturamento por Forma de Pagamento -->
	    <div class="row">
	    	<?php 
	    	$count = 0;
	    	foreach ($payment_stats as $stat): 
	    		if ($count >= 4) break;
	    		$code = strtolower($stat['code_name']);
	    		$style = isset($method_styles[$code]) ? $method_styles[$code] : $default_style;
	    		$percentage = $total_geral > 0 ? round(($stat['total_amount'] / $total_geral) * 100, 1) : 0;
	    	?>
	    	<div class="col-lg-3 col-md-6 col-sm-6">
	    		<div class="small-box <?php echo $style['bg']; ?>">
	    			<div class="inner">
	    				<h3><?php echo get_currency_symbol() . currency_format($stat['total_amount']); ?></h3>
	    				<p><?php echo $stat['method_name']; ?></p>
	    				<p style="font-size: 12px; opacity: 0.8;">
	    					<?php echo $stat['total_transactions']; ?> transações (<?php echo $percentage; ?>%)
	    				</p>
	    			</div>
	    			<div class="icon">
	    				<i class="fa <?php echo $style['icon']; ?>"></i>
	    			</div>
	    			<a href="report_sell_payment.php" class="small-box-footer">
	    				Ver Relatório <i class="fa fa-arrow-circle-right"></i>
	    			</a>
	    		</div>
	    	</div>
	    	<?php 
	    	$count++;
	    	endforeach; 

	    	// Se houver menos de 4 métodos, preencher com cards vazios ou total
	    	if ($count < 4):
	    	?>
	    	<div class="col-lg-3 col-md-6 col-sm-6">
	    		<div class="small-box bg-gray">
	    			<div class="inner">
	    				<h3><?php echo get_currency_symbol() . currency_format($total_geral); ?></h3>
	    				<p>Total Geral</p>
	    				<p style="font-size: 12px; opacity: 0.8;">Todas as formas</p>
	    			</div>
	    			<div class="icon">
	    				<i class="fa fa-calculator"></i>
	    			</div>
	    			<a href="report_sell_payment.php" class="small-box-footer">
	    				Ver Relatório <i class="fa fa-arrow-circle-right"></i>
	    			</a>
	    		</div>
	    	</div>
	    	<?php endif; ?>
	    </div>
	    <!-- Fim Cards de Faturamento -->
	    
		<div class="row">
		    <div class="col-xs-12">
		      	<div class="box box-info">
		      		<div class="box-header">
				        <h3 class="box-title">
				        	<?php echo trans('text_invoices'); ?>
				        </h3>
				        <div class="box-tools pull-right">
				        	<div class="btn-group max-w280">
				                <div class="input-group">
				                  <div class="input-group-addon no-print filter-btn-wrapper">
				                    <i class="fa fa-users" id="addIcon"></i>
				                  </div>
				                  <select id="customer_id" class="form-control" name="customer_id" >
				                    <option value=""><?php echo trans('text_select'); ?></option>
				                    <?php foreach (get_customers() as $the_customer) : ?>
				                      <option value="<?php echo $the_customer['customer_id'];?>">
				                      <?php echo $the_customer['customer_name'];?>
				                    </option>
				                  <?php endforeach;?>
				                  </select>
				                  <div class="input-group-addon no-print search-icon-wrapper">
				                    <i class="fa fa-search" id="addIcon"></i>
				                  </div>
				                </div>
				            </div>
			                <div class="btn-group">
				                <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown">
				                	<span class="fa fa-fw fa-filter"></span> 
				                  	<?php if(isset($request->get['type'])) : ?>
				                  		<?php echo trans('text_'.$request->get['type']); ?>
					                <?php else : ?>
					                	<?php echo trans('button_filter'); ?>
					                <?php endif; ?>
				                    &nbsp;<span class="caret"></span>
				                </button>
				                <ul class="dropdown-menu" role="menu">
				                	<li>
				                    	<a href="invoice.php<?php echo $query_string ? $query_string.'&' : '?';?>">
				                    		<?php echo trans('button_today_invoice'); ?>
				                    	</a>
				                    </li>
				                    <li>
				                    	<a href="invoice.php<?php echo $query_string ? $query_string.'&' : '?';?>type=all_invoice">
				                    		<?php echo trans('button_all_invoice'); ?>
				                    	</a>
				                    </li>
				                    <li>
				                    	<a href="invoice.php<?php echo $query_string ? $query_string.'&' : '?';?>type=due">
				                    		<?php echo trans('button_due_invoice'); ?>
				                    	</a>
				                    </li>
				                    <li>
				                    	<a href="invoice.php<?php echo $query_string ? $query_string.'&' : '?';?>type=all_due">
				                    		<?php echo trans('button_all_due_invoice'); ?>
				                    	</a>
				                    </li>
				                    <li>
				                    	<a href="invoice.php<?php echo $query_string ? $query_string.'&' : '?';?>type=paid">
				                    		<?php echo trans('button_paid_invoice'); ?>
				                    	</a>
				                    </li>
				                    <li>
				                    	<a href="invoice.php<?php echo $query_string ? $query_string.'&' : '?';?>type=inactive">
				                    		<?php echo trans('button_inactive_invoice'); ?>
				                    	</a>
				                    </li>
				                 </ul>
			                </div>
			            </div>
				     </div>
			      	<div class='box-body'>  
						<div class="table-responsive"> 
						<?php
				            $hide_colums = "";
				            if (user_group_id() != 1) {
            if (! has_permission('access', 'sell_payment')) {
				                $hide_colums .= "6,";
				              }
				              if (! has_permission('access', 'create_sell_return')) {
				                $hide_colums .= "7,";
				              }
				               if (! has_permission('access', 'read_sell_invoice')) {
				                $hide_colums .= "8,";
				              }
				              if (! has_permission('access', 'update_sell_invoice_info')) {
				                $hide_colums .= "9,";
				              }
				              if (! has_permission('access', 'delete_sell_invoice')) {
				                $hide_colums .= "10,";
				              }
				            }
				          ?>  

						  <table id="invoice-invoice-list"  class="table table-bordered table-striped table-hover" data-hide-colums="<?php echo $hide_colums; ?>">
						    <thead>
						      	<tr class="bg-gray">
							        <th class="w-20">
							        	<?php echo trans('label_invoice_id'); ?>
							        </th>
							        <th class="w-20">
							        	<?php echo trans('label_datetime'); ?>
							        </th>
							        <th class="w-20">
							        	<?php echo trans('label_customer_name'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_status'); ?>
							        </th>
							        <th class="w-10">
							        	<?php echo trans('label_paid_amount'); ?>
							        </th>
							        <th class="w-10">
							        	<?php echo trans('label_payable_amount'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_pay'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_return'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_view'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_edit'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_delete'); ?>
							        </th>
						      	</tr>
						    </thead>
						     <tfoot>
			               		<tr class="bg-gray">
							        <th class="w-20">
							        	<?php echo trans('label_invoice_id'); ?>
							        </th>
							        <th class="w-20">
							        	<?php echo trans('label_datetime'); ?>
							        </th>
							        <th class="w-20">
							        	<?php echo trans('label_customer_name'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_status'); ?>
							        </th>
							        <th class="w-10">
							        	<?php echo trans('label_paid_amount'); ?>
							        </th>
							        <th class="w-10">
							        	<?php echo trans('label_payable_amount'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_pay'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_return'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_view'); ?>
							        </th>
							        <th class="w-7">
							        	<?php echo trans('label_edit'); ?>
							        </th>
							        <th class="w-7">
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

<?php include ("footer.php"); ?>