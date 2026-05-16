<?php 
ob_start();
session_start();
include '../_init.php';

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_store')) {
	redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// LOAD STORE MODEL
$store_model = registry()->get('loader')->model('store');

$store_id = isset($request->get['store_id']) ? $request->get['store_id'] : store_id();
$timezone = get_preference('timezone');
if (!$store_model->getStore($store_id)) {
	redirect(root_url().ADMINDIRNAME.'/store.php');
}

// Set Document Title
$document->setTitle(trans('title_settings'));

// Add Script
$document->addScript('../assets/itsolution24/angular/controllers/StoreActionController.js');
$document->addScript('../assets/itsolution24/js/upload.js');

// Include Header and Footer
include ("header.php");
include ("left_sidebar.php");

$store->setStore($store_id);

$tab_active = isset($request->get['tab']) ? $request->get['tab'] : 'general';
?>

<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="StoreActionController">

	<!-- Content Header Start-->
	<section class="content-header">
		<h1>
			<?php echo trans('text_settings'); ?>
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
				<a href="store.php">
					<?php echo trans('title_store'); ?>
				</a>
			</li>
			<li class="active">
				<?php echo store('name'); ?>
			</li>
		</ol>
	</section>
	<!-- Content Header End-->

	<!-- Content Start-->
	<section class="content">

		<?php if(DEMO) : ?>
	    <div class="box">
	      <div class="box-body">
	        <div class="alert alert-info mb-0">
	          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
	        </div>
	        <div class="alert alert-warning mb-0">
	          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo trans('text_disabled_in_demo'); ?></p>
	        </div>
	      </div>
	    </div>
	    <?php endif; ?>
	    
		<form id="store-form" class="form-horizontal" action="store.php" method="post">
			<input type="hidden" name="action_type" value="UPDATE">
			<input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
			<div class="box box-success box-no-border">
				<div class="nav-tabs-custom">
			        <ul class="nav nav-tabs store-m15">
			        	<li class="<?php echo $tab_active == 'general' ? 'active' : null;?>">
			          		<a href="#general" data-toggle="tab" aria-expanded="false">
			          		<?php echo trans('text_general'); ?>
			       			</a>
			       		</li>
			       		<li class="<?php echo $tab_active == 'pos-setting' ? 'active' : null;?>">
			          		<a href="#pos-setting" data-toggle="tab" aria-expanded="false">
			          		<?php echo trans('text_pos_setting'); ?>
			       			</a>
			       		</li>
			        </ul>
			        <div class="tab-content">

			        <!-- General Setting Start -->
			        <div class="tab-pane<?php echo $tab_active == 'general' ? ' active' : null;?>" id="general">
			          	<?php if (isset($error)) : ?>
			              <div class="alert alert-danger">
			                <p>
			                	<span class="fa fa-fw fa-warning"></span> 
			                	<?php echo $error; ?>
			                </p>
			              </div>
			            <?php endif; ?>
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
						<div class="form-group">
					      <label for="deposit_account_id" class="col-sm-3 control-label">
					        <?php echo trans('label_deposit_account'); ?><i class="required">*</i>
					      </label>
					      <div class="col-sm-7">
					      	<div class="input-group">
						        <select id="deposit_account_id" class="form-control" name="deposit_account_id" >
						          <option value="">
						            <?php echo trans('text_select'); ?>
						          </option>
						          <?php foreach (get_bank_accounts() as $account) : ?>
						            <option value="<?php echo $account['id'];?>" <?php echo store('deposit_account_id') == $account['id'] ? 'selected' : null;?>>
						              <?php echo $account['account_name']; ?> (<?php echo currency_format(get_the_account_balance($account['id']));?>)
						            </option>
						          <?php endforeach; ?>
						        </select>
						        <a class="input-group-addon" href="bank_account.php?box_state=open">
				                  <i class="fa fa-plus"></i>
				                </a>
						    </div>
					      </div>
					    </div>
						<div class="form-group">
							<label for="name" class="col-sm-3 control-label">
								<?php echo sprintf(trans('label_name'), null); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="name" ng-init="storeName='<?php echo store('name'); ?>'" value="<?php echo store('name'); ?>" name="name" ng-model="storeName">
							</div>
						</div>
						<div class="form-group">
							<label for="code_name" class="col-sm-3 control-label">
								<?php echo sprintf(trans('label_code_name'), null); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="code_name" name="code_name"  value="<?php echo store('code_name') ? store('code_name') : "{{ storeName | strReplace:' ':'_' | lowercase }}"; ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="store-country" class="col-sm-3 control-label">
								<?php echo trans('label_country'); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<?php echo countrySelector(store('country'), 'store-country', 'country'); ?>
							</div>
						</div>
						<div class="form-group">
							<label for="mobile" class="col-sm-3 control-label">
								<?php echo trans('label_mobile'); ?>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="mobile" value="<?php echo store('mobile'); ?>" name="mobile">
							</div>
						</div>
						<div class="form-group">
							<label for="email" class="col-sm-3 control-label">
								<?php echo trans('label_email'); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<input type="email" class="form-control" id="email" value="<?php echo store('email'); ?>" name="email">
							</div>
						</div>
						<div class="form-group">
							<label for="zip_code" class="col-sm-3 control-label">
								<?php echo trans('label_zip_code'); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="zip_code" value="<?php echo store('zip_code'); ?>" name="zip_code">
							</div>
						</div>
						<div class="form-group">
							<label for="address" class="col-sm-3 control-label">
								<?php echo trans('label_address'); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<textarea class="form-control" id="address" name="address"><?php echo store('address'); ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="gst_reg_no" class="col-sm-3 control-label">
								<?php echo trans('label_gst_reg_no'); ?>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="gst_reg_no" name="preference[gst_reg_no]" value="<?php echo get_preference('gst_reg_no'); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="vat_reg_no" class="col-sm-3 control-label">
								<?php echo trans('label_vat_reg_no'); ?>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="vat_reg_no" value="<?php echo store('vat_reg_no'); ?>" name="vat_reg_no">
							</div>
						</div>
						<div class="form-group">
							<label for="cashier_id" class="col-sm-3 control-label">
								<?php echo trans('label_cashier_name'); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<div class="input-group">
									<select id="cashier_id" name="cashier_id"> 
										<?php foreach (get_cashiers() as $cashier) : ?>
											<option value="<?php echo $cashier['id']; ?>" <?php echo store('cashier_id') == $cashier['id'] ? 'selected' : null; ?>>
												<?php echo $cashier['username']; ?>
											</option>
										<?php endforeach; ?>
									</select>
									<a class="input-group-addon" href="user.php?box_state=open">
					                  <i class="fa fa-plus"></i>
					                </a>
					            </div>
							</div>
						</div>
						<div class="form-group">
							<label for="timezone" class="col-sm-3 control-label">
								<?php echo trans('label_timezone'); ?><i class="required">*</i>	
							</label>
							<div class="col-sm-7">
								<select class="form-control" name="preference[timezone]" id="timezone">
									<option selected="selected" disabled hidden value="">
										<?php echo trans('text_select'); ?>
									</option>
								<?php include('../_inc/helper/timezones.php'); ?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="invoice_edit_lifespan" class="col-sm-3 control-label">
								<?php echo trans('label_invoice_edit_lifespan'); ?><i class="required">*</i>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_invoice_edit_lifespan'); ?>">
								</span>
							</label>
							<div class="col-sm-4">
								<input type="text" class="form-control" id="invoice_edit_lifespan" value="<?php echo get_preference('invoice_edit_lifespan'); ?>" name="preference[invoice_edit_lifespan]" onclick="this.select();" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" onKeyUp="if(this.value<0){this.value='1';}">
							</div>
							<div class="col-sm-3">
								<select class="form-control" name="preference[invoice_edit_lifespan_unit]" id="invoice_edit_lifespan_unit">
									<option selected="selected" disabled hidden value="">
										<?php echo trans('text_select'); ?>
									</option>
									<option value="minute" <?php echo get_preference('invoice_edit_lifespan_unit') == 'minute' ? 'selected' : null; ?>><?php echo trans('text_minute'); ?></option>
									<option value="second" <?php echo get_preference('invoice_edit_lifespan_unit') == 'second' ? 'selected' : null; ?>><?php echo trans('text_second'); ?></option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="invoice_delete_lifespan" class="col-sm-3 control-label">
								<?php echo trans('label_invoice_delete_lifespan'); ?><i class="required">*</i>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_invoice_delete_lifespan'); ?>">
								</span>
							</label>
							<div class="col-sm-4">
								<input type="text" class="form-control" id="invoice_delete_lifespan" value="<?php echo get_preference('invoice_delete_lifespan'); ?>" name="preference[invoice_delete_lifespan]" onclick="this.select();" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" onKeyUp="if(this.value<0){this.value='1';}">
							</div>
							<div class="col-sm-3">
								<select class="form-control" name="preference[invoice_delete_lifespan_unit]" id="invoice_delete_lifespan_unit">
									<option selected="selected" disabled hidden value="">
										<?php echo trans('text_select'); ?>
									</option>
									<option value="minute" <?php echo get_preference('invoice_delete_lifespan_unit') == 'minute' ? 'selected' : null; ?>><?php echo trans('text_minute'); ?></option>
									<option value="second" <?php echo get_preference('invoice_delete_lifespan_unit') == 'second' ? 'selected' : null; ?>><?php echo trans('text_second'); ?></option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="tax" class="col-sm-3 control-label">
								<?php echo trans('label_tax'); ?>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_tax'); ?>">
								</span>
							</label>
							<div class="col-sm-7">
							  <input type="number" class="form-control" id="tax" name="preference[tax]" value="<?php echo get_preference('tax'); ?>" onClick="this.select()" onKeyUp="if(this.value<0){this.value='0';}else if(this.value>99){this.value='99';}">
							</div>
						</div>
						<div class="form-group">
							<label for="sms_gateway" class="col-sm-3 control-label">
								<?php echo trans('label_sms_gateway'); ?>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_sms_gateway'); ?>">
								</span>
							</label>
							<div class="col-sm-7">
							  <select id="sms_gateway" name="preference[sms_gateway]"> 
									<option value="Twilio" <?php echo get_preference('sms_gateway') == 'Twilio' ? 'selected' : null; ?>>
										Twilio
									</option>
									<option value="Msg91" <?php echo get_preference('sms_gateway') == 'Msg91' ? 'selected' : null; ?>>
										Msg91
									</option>
									<option value="Mimsms" <?php echo get_preference('sms_gateway') == 'Mimsms' ? 'selected' : null; ?>>
										MimSMS
									</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="sms_alert" class="col-sm-3 control-label">
								<?php echo trans('label_sms_alert'); ?>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_sms_alert'); ?>">
								</span>
							</label>
							<div class="col-sm-7">
							  <select id="sms_alert" name="preference[sms_alert]"> 
									<option value="1" <?php echo get_preference('sms_alert') ? 'selected' : null; ?>>
										<?php echo trans('text_yes'); ?>
									</option>
									<option value="0" <?php echo !get_preference('sms_alert') ? 'selected' : null; ?>>
										<?php echo trans('text_no'); ?>
									</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">
								<?php echo trans('label_auto_sms'); ?>
							</label>
							<div class="col-sm-7">
								<div class="well well-sm mb-0">
									<div class="checkbox">
										<label>
											<input type="checkbox" name="preference[invoice_auto_sms]" value="1" <?php echo get_preference('invoice_auto_sms') == '1' ? 'checked' : null; ?>> <?php echo trans('text_sms_after_creating_invoice'); ?>
											</label>
									</div>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label">
								<?php echo trans('label_expiration_system'); ?>
							</label>
							<div class="col-sm-7">
								<div class="well well-sm mb-0">
									<div class="checkbox">
										<label>
											<input type="checkbox" name="preference[expiry_yes]" value="1" <?php echo get_preference('expiry_yes') == '1' ? 'checked' : null; ?>> <?php echo trans('text_yes'); ?>
										</label>
									</div>
									<div>
										<span for="preference[expiring_soon_alert_days]">Alert before</span>
										<input class="text-center" type="text" name="preference[expiring_soon_alert_days]" value="<?php echo get_preference('expiring_soon_alert_days');?>" onclick="this.select();" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" onKeyUp="if(this.value<0){this.value='1';}"> days of expiring
									</div>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label for="datatable_item_limit" class="col-sm-3 control-label">
								<?php echo trans('label_datatable_item_limit'); ?><i class="required">*</i>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_datatable_item_limit'); ?>"></span>
							</label>
							<div class="col-sm-7">
							  <input type="text" class="form-control" id="datatable_item_limit" name="preference[datatable_item_limit]" value="<?php echo get_preference('datatable_item_limit'); ?>" onClick="this.select()" onKeyUp="if(this.value<0){this.value='0';}">
							</div>
						</div>
						<div class="form-group hidden">
							<label for="sort_order" class="col-sm-3 control-label">
								<?php echo trans('label_sort_order'); ?>
							</label>
							<div class="col-sm-7">
								<input type="number" class="form-control" id="sort_order" value="<?php echo store('sort_order'); ?>" name="sort_order">
							</div>
						</div>
						<div class="form-group hidden">
							<label for="status" class="col-sm-3 control-label">
								<?php echo trans('label_status'); ?>
							</label>
							<div class="col-sm-7">
								<select id="status" name="status"> 
									<option value="">
										<?php echo trans('text_select'); ?>
									</option>
									<option value="1" <?php echo store('status') ? 'selected' : null; ?>>
										<?php echo trans('text_active'); ?>
									</option>
									<option value="0" <?php echo !store('status') ? 'selected' : null; ?>>
										<?php echo trans('text_inactive'); ?>
									</option>
								</select>
							</div>
						</div>
					</div> 
					<!-- General Setting End -->

					<!-- POS Setting Start -->
					<div class="tab-pane<?php echo $tab_active == 'pos-setting' ? ' active' : null;?>" id="pos-setting">
						<div class="form-group">
							<label for="reference_format" class="col-sm-3 control-label">
								<?php echo trans('label_reference_format'); ?>
							</label>
							<div class="col-sm-7">
							  	<select name="preference[reference_format]" class="form-control tip" required="required" id="reference_format">
								<option value="year_sequence" <?php echo get_preference('reference_format') == 'year_sequence' ? 'selected' : null; ?>>YEAR/Sequence Number (SL/2014/001)</option>
								<option value="year_month_sequence" <?php echo get_preference('reference_format') == 'year_month_sequence' ? 'selected' : null; ?>>YEAR/MONTH/Sequence Number (SL/2014/08/001)</option>
								<option value="sequence" <?php echo get_preference('reference_format') == 'sequence' ? 'selected' : null; ?>>Sequence Number</option>
								<option value="random" <?php echo get_preference('reference_format') == 'random' ? 'selected' : null; ?>>Random Number</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="sales_reference_prefix" class="col-sm-3 control-label">
								<?php echo trans('label_sales_reference_prefix'); ?>
							</label>
							<div class="col-sm-7">
							  <input type="text" class="form-control" id="sales_reference_prefix" name="preference[sales_reference_prefix]" value="<?php echo get_preference('sales_reference_prefix'); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="receipt_template" class="col-sm-3 control-label">
								<?php echo trans('label_receipt_template'); ?>
							</label>
							<div class="col-sm-7">
							  <select id="receipt_template" name="preference[receipt_template]"> 
							  	<?php foreach (get_postemplates() as $template) :?>
							  		<option value="<?php echo $template['template_id'];?>" <?php echo get_preference('receipt_template') == $template['template_id'] ? 'selected' : null; ?>>
										<?php echo $template['template_name']; ?>
									</option>
							  	<?php endforeach;?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="remote_printing" class="col-sm-3 control-label">
								<?php echo trans('label_pos_printing'); ?><i class="required">*</i>
							</label>
							<div class="col-sm-7">
								<select class="form-control" name="remote_printing" id="remote_printing">
								  	<option value="0" <?php echo store('remote_printing') == 0 ? 'selected' : null; ?>>
								  		Web Browser
								  	</option>
								  	<option value="1" <?php echo store('remote_printing') == 1 ? 'selected' : null; ?>>
								  		PHP Server
								  	</option>
								</select>
								<div class="well well-sm mb-0">
                                    <i><?php echo trans('text_installation_info');?></i>
								</div>
								<div class="well well-sm mb-0">
									<div class="form-group">
										<div class="col-sm-6">
											<label for="receipt_printer" class="control-label">
												<?php echo trans('label_receipt_printer'); ?>
											</label>
											<div>
											  	<select class="form-control" name="receipt_printer" id="receipt_printer">
											  		<option value=""><?php echo trans('text_select');?></option>
											  		<?php foreach (get_printers() as $printer) : ?>
											  			<option value="<?php echo $printer['printer_id'];?>" <?php echo store('receipt_printer') == $printer['printer_id'] ? 'selected' : null; ?>>
											  				<?php echo $printer['title'];?>
												  		</option>
											  		<?php endforeach; ?>
												</select>
											</div>
										</div>
										<div class="col-sm-6">
											<label for="auto_print_receipt" class="control-label">
												<?php echo trans('label_auto_print_receipt'); ?>
											</label>
											<div>
											  	<select class="form-control" name="auto_print" id="auto_print_receipt">
												  	<option value="1" <?php echo store('auto_print') == 1 ? 'selected' : null; ?>>Yes
												  	</option>
												  	<option value="0" <?php echo store('auto_print') == 0 ? 'selected' : null; ?>>No
												  	</option>
												</select>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label for="invoice_view" class="col-sm-3 control-label">
								<?php echo trans('label_invoice_view'); ?>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_invoice_view'); ?>">
								</span>
							</label>
							<div class="col-sm-3">
							  <select id="invoice_view" name="preference[invoice_view]"> 
									<option value="standard" <?php echo get_preference('invoice_view') == 'standard' ? 'selected' : null; ?>>
										<?php echo trans('text_standard'); ?>
									</option>
									<option value="tax_invoice" <?php echo get_preference('invoice_view') == 'tax_invoice' ? 'selected' : null; ?>>
										<?php echo trans('text_tax_invoice'); ?>
									</option>
									<option value="indian_gst" <?php echo get_preference('invoice_view') == 'indian_gst' ? 'selected' : null; ?>>
										<?php echo trans('text_indian_gst'); ?>
									</option>
								</select>
							</div>
							<div ng-show="indianGST" class="col-sm-4">
								<?php echo stateSelector(get_preference('business_state'), 'business_state', 'preference[business_state]'); ?>
							</div>
						</div>
						<div class="form-group">
							<label for="change_item_price_while_billing" class="col-sm-3 control-label">
								<?php echo trans('label_change_item_price_while_billing'); ?>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_change_item_price_while_billing'); ?>">
								</span>
							</label>
							<div class="col-sm-7">
							  <select id="change_item_price_while_billing" name="preference[change_item_price_while_billing]"> 
									<option value="1" <?php echo get_preference('change_item_price_while_billing') ? 'selected' : null; ?>>
										<?php echo trans('text_yes'); ?>
									</option>
									<option value="0" <?php echo !get_preference('change_item_price_while_billing') ? 'selected' : null; ?>>
										<?php echo trans('text_no'); ?>
									</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="pos_product_display_limit" class="col-sm-3 control-label">
								<?php echo trans('label_pos_product_display_limit'); ?><i class="required">*</i>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_pos_product_display_limit'); ?>"></span>
							</label>
							<div class="col-sm-7">
							  <input type="number" class="form-control" id="pos_product_display_limit" name="preference[pos_product_display_limit]" value="<?php echo get_preference('pos_product_display_limit'); ?>" onClick="this.select()" onKeyUp="if(this.value<0){this.value='0';}">
							</div>
						</div>
						<div class="form-group">
							<label for="after_sell_page" class="col-sm-3 control-label">
								<?php echo trans('label_after_sell_page'); ?><i class="required">*</i>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_after_sell_page'); ?>">
								</span>
							</label>
							<div class="col-sm-7">
								<select class="form-control" name="preference[after_sell_page]" id="after_sell_page">
								  	<option value="pos" <?php echo get_preference('after_sell_page') == 'pos' ? 'selected' : null; ?>>
								  		Point of Sell (POS)
								  	</option>
								  	<option value="receipt_in_new_window" <?php echo get_preference('after_sell_page') == 'receipt_in_new_window' ? 'selected' : null; ?>>
								  		Open Receipt in New Window
								  	</option>
								  	<option value="receipt_in_popup" <?php echo get_preference('after_sell_page') == 'receipt_in_popup' ? 'selected' : null; ?>>
								  		Open Receipt in Popup
								  	</option>
								  	<option value="toastr_msg" <?php echo get_preference('after_sell_page') == 'toastr_msg' ? 'selected' : null; ?>>
								  		Toastr Message
								  	</option>
								  	<option value="sweet_alert_msg" <?php echo get_preference('after_sell_page') == 'sweet_alert_msg' ? 'selected' : null; ?>>
								  		Sweet Alert Message
								  	</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="invoice_footer_text" class="col-sm-3 control-label">
								<?php echo trans('label_invoice_footer_text'); ?>
								<span data-toggle="tooltip" title="" data-original-title="<?php echo trans('hint_invoice_footer_text'); ?>"></span>
							</label>
							<div class="col-sm-7">
								<input type="text" class="form-control" id="invoice_footer_text" name="preference[invoice_footer_text]" value="<?php echo get_preference('invoice_footer_text'); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="sound_effect" class="col-sm-3 control-label">
								<?php echo trans('label_sound_effect'); ?>
							</label>
							<div class="col-sm-7">
								<select id="sound_effect" name="sound_effect"> 
									<option value="">
										<?php echo trans('text_select'); ?>
									</option>
									<option value="1" <?php echo store('sound_effect') ? 'selected' : null; ?>>
										<?php echo trans('text_active'); ?>
									</option>
									<option value="0" <?php echo !store('sound_effect') ? 'selected' : null; ?>>
										<?php echo trans('text_inactive'); ?>
									</option>
								</select>
							</div>
						</div>
					</div>
					<!-- POS Setting End -->
				</div>
					<?php if (user_group_id() == 1 || has_permission('access', 'update_store')) : ?>
					<div class="form-group">
						<div class="col-sm-3 col-sm-offset-3">
							<a id="back-btn" class="btn btn-block btn-danger" href="store.php">
								<span class="fa fa-fw fa-angle-left"></span> 
								<?php echo trans('button_back'); ?>
							</a>
						</div>
						<div class="div.col-sm-1"></div>
						<div class="col-sm-4">
							<button id="update-store-btn" class="btn btn-block btn-info pull-right" type="button" data-form="#store-form" data-loading-text="Updating...">
								<span class="fa fa-fw fa-pencil"></span> 
								<?php echo trans('button_update'); ?>
							</button>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</form>

		<div class="box box-info">
			<div class="box-header with-border">
				<h3 class="box-title">
					<?php echo trans('text_logo'); ?>
				</h3>
				<button  type="button" class="btn btn-box-tool add-new-btn" data-widget="collapse" data-collapse="true">
					<i class="fa <?php echo !create_box_state() ? 'fa-minus' : 'fa-minus'; ?>"></i>
				</button>
			</div>
			<div class="box-body">
				<form id="uploadlogo" class="upload-form" action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
					<div class="form-group">
						<label class="col-sm-3 control-label">&nbsp;</label>
						<div class="col-sm-2 text-center">	            
							<div id="logo_preview">
								<?php if (store('logo')): ?>
									<img id="logo" src="../assets/itsolution24/img/logo-favicons/<?php echo store('logo'); ?>">
								<?php else: ?>
									<img id="logo" src="../assets/itsolution24/img/logo-favicons/nologo.png">
								<?php endif; ?>
							</div>
							<p>
								Max. 100 kb
							</p>
						</div>
						<?php if (user_group_id() == 1 || has_permission('access', 'upload_logo')) : ?>
						    <div class="col-sm-4">	            
								<div class="upload-field" id="selectImage">
									<input class="file-field" type="file" name="file" id="file" required>
									<div class="message"></div>
									<button type="submit" class="btn btn-sm btn-warning btn-logo-upload" data-loading-text="Uploading...">
										<span class="fa fa-fw fa-upload"></span> 
										<?php echo trans('button_upload'); ?>
									</button>
									<img class="loader logo-loader" src="../assets/itsolution24/img/loading.gif">
								</div>
						    </div>
						<?php endif; ?>
						<div class="clearfix"></div>
					</div>
				</form>
			</div>
	    </div>
	    <div class="box box-info">
			<div class="box-header with-border">
				<h3 class="box-title">
					<?php echo trans('text_favicon'); ?>
				</h3>
				<button  type="button" class="btn btn-box-tool add-new-btn" data-widget="collapse" data-collapse="true">
					<i class="fa <?php echo !create_box_state() ? 'fa-minus' : 'fa-minus'; ?>"></i>
				</button>
			</div>
			<div class="box-body">
				<form id="uploadFavicon" class="upload-form" action="" method="post" enctype="multipart/form-data">
					<input type="hidden" name="store_id" value="<?php echo $store_id; ?>">
					<div class="form-group">
						<label class="col-sm-3 control-label">&nbsp;</label>
						<div class="col-sm-2 text-center">	            
							<div id="favicon_preview">
								<?php if (store('favicon')): ?>
									<img id="favicon" src="../assets/itsolution24/img/logo-favicons/<?php echo store('favicon'); ?>">
								<?php else: ?>
									<img id="favicon" src="../assets/itsolution24/img/logo-favicons/nofavicon.png">
								<?php endif; ?>
							</div>
							<p>
								Max. 50 kb
							</p>
						</div>
						<?php if (user_group_id() == 1 || has_permission('access', 'upload_favicon')) : ?>
							<div class="col-sm-4">	            
								<div class="upload-field" id="selectFavicon">
									<input class="file-field" type="file" name="faviconFile" id="faviconFile" required>
									<div class="message"></div>
									<button type="submit" class="btn btn-sm btn-warning btn-favicon-upload" data-loading-text="Uploading...">
										<span class="fa fa-fw fa-upload"></span> 
										<?php echo trans('button_upload'); ?>
									</button>
									<img class="loader favicon-loader" src="../assets/itsolution24/img/loading.gif">
								</div>
							</div>
						<?php endif; ?>
						<div class="clearfix"></div>
					</div>
			  	</form>
			</div>
		</div>
	</section>
	<!-- Content End-->

</div>
<!-- Content Wrapper End -->

<?php include ("footer.php"); ?>