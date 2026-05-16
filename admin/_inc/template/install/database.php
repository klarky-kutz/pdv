<br>
<div class="container">
    <div class="row">
	    <div class="col-sm-8 col-sm-offset-2">
	        <div class="panel panel-default header">
		        <div class="panel-heading text-center bg-database">
                    <h2><?php echo trans('text_database_conf'); ?></h2>
                    <p><?php echo trans('text_step_3_of_6'); ?></p>
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
                            <a href="purchase_code.php">
                            	<span class="fa fa-check"></span> <?php echo trans('text_verification'); ?>
                            </a>
                        </li>
					  	<li class="active">
					  		<a href="database.php"><?php echo trans('text_database'); ?>
					  		</a>
					  	</li>
					  	<li>
					  		<a href="#" onClick="return false"><?php echo trans('text_timezone'); ?>
					  		</a>
					  	</li>
					  	<li>
					  		<a href="#" onClick="return false"><?php echo trans('text_site_config'); ?></a>
					  	</li>
					  	<li>
					  		<a href="#" onClick="return false"><?php echo trans('text_done'); ?></a>
					  	</li>
					</ul>
			    </div>
			    <div class="panel-body ins-bg-col">

			    	<?php if(isset($errors['database_import'])) : ?>
				    	<div class="alert alert-danger">
				    		<p><?php echo $errors['database_import']; ?></p>
				    	</div>
				    <?php endif; ?>
			    	
			    	<form id="databaseForm" class="form-horizontal" action="database.php" method="post">
						<?php 
						if(isset($errors['host'])) 
						    echo "<div class='form-group has-error' >";
						else     
						    echo "<div class='form-group' >";
						?>
							<label for="host" class="col-sm-3 control-label">
                                <?php echo trans('text_hostname'); ?> <span class="text-aqua">*</span>
							</label>
							<div class="col-sm-8">
							    <input type="text" class="form-control" id="host" name="host" value="<?php echo isset($request->post['host']) ? $request->post['host'] : 'localhost'; ?>" required>

							    <p class="control-label">
							    	<?php echo isset($errors['host']) ? $errors['host'] : ''; ?>
							    </p>
							</div>
						</div>

						<?php 
						if(isset($errors['database']))
						    echo "<div class='form-group has-error' >";
						else
						    echo "<div class='form-group' >";
						?>
							<label for="database" class="col-sm-3 control-label">
                                <?php echo trans('text_database'); ?> <span class="text-aqua">*</span>
							</label>
							<div class="col-sm-8">
							    <input type="text" class="form-control" id="database" name="database" value="<?php echo isset($request->post['database']) ? $request->post['database'] : null; ?>" required>

							    <p class="control-label">
							    	<?php echo isset($errors['database']) ? $errors['database'] : ''; ?>
							    </p>
							</div>
						</div>

						<?php 
						if(isset($errors['user'])) 
						    echo "<div class='form-group has-error' >";
						else     
						    echo "<div class='form-group' >";
						?>
							<label for="user" class="col-sm-3 control-label">
                                <?php echo trans('text_username'); ?> <span class="text-aqua">*</span>
							</label>
							<div class="col-sm-8">
							    <input type="text" class="form-control" id="user" name="user" value="<?php echo isset($request->post['user']) ? $request->post['user'] : 'root'; ?>" required>

							    <p class="control-label">
							    	<?php echo isset($errors['user']) ? $errors['user'] : ''; ?>
							    </p>
							</div>
						</div>

						<?php 
						if(isset($errors['password'])) 
						    echo "<div class='form-group has-error' >";
						else     
						    echo "<div class='form-group' >";
						?>
							<label for="password" class="col-sm-3 control-label">
                                <?php echo trans('text_password'); ?>
							</label>
							<div class="col-sm-8">
							    <input type="password" class="form-control" id="password" name="password" value="<?php echo isset($request->post['password']) ? $request->post['password'] : null; ?>" required>

							    <p class="control-label">
							    	<?php echo isset($errors['password']) ? $errors['password'] : ''; ?>
							    </p>
							</div>
						</div>

						<?php 
						if(isset($errors['port'])) 
						    echo "<div class='form-group has-error' >";
						else     
						    echo "<div class='form-group' >";
						?>
							<label for="port" class="col-sm-3 control-label">
                                <?php echo trans('text_port_3306'); ?> <span class="text-aqua">*</span>
							</label>
							<div class="col-sm-8">
							    <input type="number" class="form-control" id="port" name="port" value="<?php echo isset($request->post['port']) ? $request->post['port'] : 3306; ?>" required>
							    <p class="control-label">
							    	<?php echo isset($errors['port']) ? $errors['port'] : ''; ?>
							    </p>
							</div>
						</div>

						<div class="alert alert-info highlight-text">
							<p><?php echo trans('text_install_instruction'); ?> </p>
						</div>

				        <div class="form-group mt-20">
							<div class="col-sm-6 text-right">
				                <a href="purchase_code.php" class="btn btn-default">&larr; <?php echo trans('text_prev_step'); ?></a>
				            </div>
				            <div class="col-sm-6 text-left">
				                <button class="btn btn-success ajaxcall" data-form="databaseForm" data-loading-text="Processing..."><?php echo trans('text_next_step'); ?> &rarr;</button>
				            </div>
				        </div>
					</form>
			    </div>
			</div>
		    <div class="text-center copyright">&#169; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, <?php echo trans('text_all_right_reserved'); ?></div>
		</div>
	</div>
</div>

<script type="text/javascript">
    "use strict";
function databaseFormSuccessCallback(res)
{
	console.log(res);
	$("#loader-status").show();
	$("#loader-status .progress").show();
    $("#loader-status .text").text("Processing...");

	$("#loader-status .progress-bar").attr("aria-valuenow", 0);
    $("#loader-status .progress-bar").css("width", "0%");
    
    next(res["next"]);
}

function next(url) {
    $.ajax({
      url: url,
      dataType: "json",
      success: function(json) {
        
        if (json["error"]) {
          	toastr.error(json["error"]);
          	$("#loader-status").css('display','none');
          	$("body").removeClass("overlay-loader");
          	$("#loader-status").remove();
			$(".btn").removeAttr("disabled");
			$(".form-control").removeAttr("disabled", "disabled");
			$('.btn').button("reset");
        }
        
        if (json["success"]) {
        	toastr.success(json["success"]);
          	window.location = 'timezone.php';
        }
        
        if (json["total"]) {
        	$("#loader-status .text").text( json["total"]+"%");
          	$("#loader-status .progress-bar").attr("aria-valuenow", json["total"]);
          	$("#loader-status .progress-bar").css("width", json["total"] + "%");
        }
        
        if (json["next"]) {
          next(json["next"]);
        }
      },
      error: function(xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  }
</script>