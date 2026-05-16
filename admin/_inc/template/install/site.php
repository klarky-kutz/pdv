<br>
<div class="container">
    <div class="row">
	    <div class="col-sm-8 col-sm-offset-2">
	        <div class="panel panel-default header">
		        <div class="panel-heading text-center bg-database">
                    <h2><?php echo trans('text_store_configuration'); ?></h2>
                    <p><?php echo trans('text_step_5_of_6'); ?></p>
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
                            <a href="index.php"><?php echo trans('text_checklist'); ?></a>
                        </li>
                        <li>
                            <a href="purchase_code.php"> <?php echo trans('text_verification'); ?></a>
                        </li>
                        <li>
                            <a href="database.php"><?php echo trans('text_database'); ?></a>
                        </li>
                        <li>
                            <a href="timezone.php" onClick="return false"><?php echo trans('text_timezone'); ?></a>
                        </li>
                        <li class="active">
                            <span class="fa fa-check"></span><a href="site.php" onClick="return false"><?php echo trans('text_site_config'); ?></a>
                        </li>
                        <li>
                            <a href="#" onClick="return false"><?php echo trans('text_done'); ?></a>
                        </li>
                    </ul>
			    </div>
			    <div class="panel-body ins-bg-col">
			    	<form id="siteForm" class="form-horizontal" action="site.php" method="post">
						<?php if(isset($errors['store_name'])) 
						    echo "<div class='form-group has-error' >";
						else     
						    echo "<div class='form-group' >";
						?>
							<label for="store_name" class="col-sm-3 control-label">
                                <?php echo trans('text_store_name'); ?> <span class="text-aqua">*</span>
							</label>
							<div class="col-sm-8">
							    <input type="text" class="form-control" id="store_name" name="store_name" value="<?php echo isset($request->post['store_name']) ? $request->post['store_name'] : null; ?>" >

							    <?php if (isset($errors['store_name'])):?>
							    <p class="control-label">
							    	<?php echo $errors['store_name']; ?>
							    </p>
								<?php endif;?>
							</div>
						</div>

						<?php 
						    if(isset($errors['phone'])) 
						        echo "<div class='form-group has-error' >";
						    else     
						        echo "<div class='form-group' >";
						?>
						    <label for="phone" class="col-sm-3 control-label">
                                <?php echo trans('text_phone'); ?> <span class="text-aqua">*</span>
						    </label>
						    <div class="col-sm-8">
						        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo isset($request->post['phone']) ? $request->post['phone'] : null; ?>" >

						        <?php if (isset($errors['phone'])):?>
						        <p class="control-label">
						        	<?php echo $errors['phone']; ?>
						        </p>
							    <?php endif;?>
						    </div>
						</div>

						<?php 
						    if(isset($errors['email'])) 
						        echo "<div class='form-group has-error' >";
						    else     
						        echo "<div class='form-group' >";
						?>
						    <label for="email" class="col-sm-3 control-label">
                                <?php echo trans('text_email'); ?><span class="text-aqua">*</span>
						    </label>
						    <div class="col-sm-8">
						        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($request->post['email']) ? $request->post['email'] : null; ?>" required>

						        <?php if (isset($errors['email'])):?>
						        <p class="control-label">
						        	<?php echo $errors['email']; ?>
						        </p>
						        <?php endif;?>
						    </div>
						</div>

						<?php 
						    if(isset($errors['password'])) 
						        echo "<div class='form-group has-error' >";
						    else     
						        echo "<div class='form-group' >";
						?>
						    <label for="password" class="col-sm-3 control-label">
                                <?php echo trans('text_password'); ?> <span class="text-aqua">*</span>
						    </label>
						    <div class="col-sm-8">
						        <input type="password" class="form-control" id="password" name="password" value="<?php echo isset($request->post['password']) ? $request->post['password'] : null; ?>" required>

						        <?php if (isset($errors['password'])):?>
						        <p class="control-label">
						        	<?php echo $errors['password']; ?>
						        </p>
						        <?php endif;?>
						    </div>
						</div>

						<?php 
						    if(isset($errors['address'])) 
						        echo "<div class='form-group has-error' >";
						    else     
						        echo "<div class='form-group' >";
						?>
						    <label for="address" class="col-sm-3 control-label">
                                <?php echo trans('text_address'); ?> <span class="text-aqua">*</span>
						    </label>
						    <div class="col-sm-8">
						        <textarea name="address" class="form-control" id="address" required><?php echo isset($request->post['address']) ? $request->post['address'] : null; ?>Earth</textarea>

						        <?php if (isset($errors['address'])):?>
						        <p class="control-label">
						        	<?php echo $errors['address']; ?>
						        </p>
						        <?php endif;?>
						    </div>
						</div>

						<br>

						<div class="form-group">
							<div class="col-sm-6 text-right">
				                <a href="timezone.php" class="btn btn-default">&larr; <?php echo trans('text_prev_step'); ?></a>
				            </div>
				            <div class="col-sm-6 text-left">
				                <button class="btn btn-success ajaxcall" data-form="siteForm" data-loading-text="Saving..."><?php echo trans('text_next_step'); ?> &rarr;</button>
				            </div>
				        </div>
					</form>
			    </div>
			</div>
            <div class="text-center copyright">&#169; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, <?php echo trans('text_all_right_reserved'); ?></div>
		</div>
	</div>
</div>