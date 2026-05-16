<?php include ("../../../_init.php");?>
<div class="box-body">
	<div class="logo text-center mb-20">
		<img src="<?php echo root_url();?>assets/itsolution24/support_icon.jpg" alt="logo">
	</div>
	<div class="panel mb-20 b-2">
		<div class="panel-body">
			<p class="text-center"><b><?php echo trans('text_support_comment_box'); ?></b></p>
		</div>
	</div>
	<div class="row mb-20 m-r0">
		<div class="col-sm-4">
			<a class="btn btn-info btn-block" href="mailto:<?php echo trans('text_email_address'); ?>"><?php echo trans('text_email_us'); ?> &rarr;</a>
		</div>
		<div class="col-sm-4">
			<a class="btn btn-warning btn-block" href="<?php echo trans('text_codecanyon_follow_link'); ?>" target="_blank"><?php echo trans('text_follow_us'); ?> &rarr;</a>
		</div>
		<div class="col-sm-4">
			<a class="btn btn-success btn-block" href="<?php echo trans('text_contact_link'); ?>" target="_blank"><?php echo trans('text_contact_us'); ?> &rarr;</a>
		</div>	
	</div>
	<br>
	<p class="mt-20 text-center"><i><?php echo trans('text_thank_you_for_choose'); ?></i></p>
</div>
