<br>
<div class="container">
    <div class="row">
	    <div class="col-sm-8 col-sm-offset-2">
	        <div class="panel panel-default header">
		        <div class="panel-heading text-center bg-database">
                    <h2><?php echo trans('text_timezone_setup'); ?></h2>
                    <p><?php echo trans('text_step_4_of_6'); ?></p>
                </div>
	        </div>
	    </div>
    </div>
	<div class="row">
		<div class="col-sm-8 col-sm-offset-2">    
		    <div class="panel panel-default menubar">
		        <div class="panel-heading bg-white">
					<ul class="nav nav-pills">
					  	<li>
					  		<a href="index.php">
					  			<span class="fa fa-check"></span> <?php echo trans('text_checklist'); ?>
					  		</a>
					  	</li>
					  	<li>
                            <a href="purchase_code.php" >
                            	<span class="fa fa-check"></span> <?php echo trans('text_verification'); ?>
                            </a>
                        </li>
					  	<li>
					  		<a href="database.php">
					  			<span class="fa fa-check"></span> <?php echo trans('text_database'); ?>
					  		</a>
					  	</li>
					  	<li class="active">
					  		<a href="timezone"><?php echo trans('text_timezone'); ?>
					  		</a>
					  	</li>
					  	<li>
					  		<a href="site.php" onClick="return false"><?php echo trans('text_site_config'); ?>
					  		</a>
					  	</li>
					  	<li>
					  		<a href="#" onClick="return false"><?php echo trans('text_done'); ?>
					  		</a>
					  	</li>
					</ul>
			    </div>
			    <div class="panel-body ins-bg-col">

			    	<form id="timezoneForm" class="form-horizontal" action="timezone.php" method="post">
						<?php if($errors['timezone'])  
						    echo "<div class='form-group has-error' >";
						else     
						    echo "<div class='form-group' >";
						?>
							<label for="sname" class="col-sm-3 control-label">
                                <?php echo trans('text_timezone'); ?> <span class="text-aqua">*</span>
							</label>
							<div class="col-sm-6">
							    <select class="form-control" name="timezone" id="timezone">
									<option selected="selected" disabled hidden value="">
                                        <?php echo trans('text_select_timezone'); ?>
									</option>
									<?php include('../_inc/helper/timezones.php'); ?>
								</select>
								<p class="control-label">
									<?php echo $errors['timezone']; ?>
								</p>
							</div>
						</div>

						<br>

						<div class="form-group">
				            <div class="col-sm-4 col-sm-offset-3">
				                <button class="btn btn-success btn-block ajaxcall" data-form="timezoneForm" data-loading-text="Saving..."> <?php echo trans('text_next_step'); ?> &rarr;</button>
				            </div>
				        </div>
					</form>
			    </div>
			</div>
            <div class="text-center copyright">&#169; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, <?php echo trans('text_all_right_reserved'); ?></div>
		</div>
	</div>
</div>