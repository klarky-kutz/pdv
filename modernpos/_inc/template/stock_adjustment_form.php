<form class="form-horizontal" id="product-form" action="stock_adjustment.php" method="post">
	<input type="hidden" id="action_type" name="action_type" value="UPDATE">
	<input type="hidden" id="p_id" name="p_id" value="<?php echo $product['p_id']; ?>">
	<div class="box-body">
		<div class="form-group">
			<label for="qty" class="col-sm-2 control-label">
				<?php echo trans('label_qty'); ?><i class="required">*</i>
			</label>
			<div class="col-sm-10">
				<input type="number" class="form-control" id="qty" name="qty" required>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-2 control-label"></label>
			<div class="col-sm-10">
				<button id="button-update-stock" data-form="#product-form" data-datatable="#product-product-list" class="btn btn-info" name="btn_edit_category" data-loading-text="Updating...">
					<span class="fa fa-fw fa-pencil"></span> <?php echo trans('button_update'); ?>
				</button>
			</div>
		</div>
	</div>
</form>