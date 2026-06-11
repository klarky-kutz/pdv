<br>
<div class="container">
    <div class="row">
	    <div class="col-sm-8 col-sm-offset-2">
	        <div class="panel panel-default header">
		        <div class="panel-heading text-center bg-database">
                    <h2><?php echo trans('text_congrats_almost_done'); ?></h2>
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
                            <a href="#" onClick="return false">
                                <span class="fa fa-check"></span> <?php echo trans('text_checklist'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" onClick="return false">
                                <span class="fa fa-check"></span> <?php echo trans('text_verification'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" onClick="return false">
                                <span class="fa fa-check"></span> <?php echo trans('text_database'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" onClick="return false">
					  			<span class="fa fa-check"> <?php echo trans('text_timezone'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" onClick="return false">
					  			<span class="fa fa-check"> <?php echo trans('text_site_config'); ?>
                            </a>
                        </li>
                        <li class="active">
                            <a href="#" onClick="return false"><?php echo trans('text_done'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
			    <div class="panel-body ins-bg-col">
			    	<div class="alert alert-warning">
			    		<p class="text-center">
			    			<b><?php echo trans('text_login_credentials'); ?></b>
			    		</p>
			    		<br>

						<table class="table table-striped">
			    			<thead>
			    				<tr class="active">
			    					<th><?php echo trans('text_role'); ?></th>
			    					<th><?php echo trans('text_username'); ?></th>
			    					<th><?php echo trans('text_password'); ?></th>
			    				</tr>
			    			</thead>
			    			<tbody>
			    				<tr class="success">
			    					<td><?php echo trans('text_admin'); ?></td>
			    					<td><?php echo $session->data['admin_username']; ?></td>
			    					<td><?php echo $session->data['password']; ?></td>
			    				</tr>
			    				<tr class="active">
			    					<td><?php echo trans('text_cashier'); ?></td>
			    					<td><?php echo $session->data['cashier_username']; ?></td>
			    					<td><?php echo $session->data['password']; ?></td>
			    				</tr>
			    				<tr class="info">
			    					<td><?php echo trans('text_salesman'); ?></td>
			    					<td><?php echo $session->data['salesman_username']; ?></td>
			    					<td><?php echo $session->data['password']; ?></td>
			    				</tr>
			    			</tbody>
			    		</table>
			    	</div>
					<div class="form-group mt-20">
						<div class="row">
				            <div class="col-sm-6 col-sm-offset-3">
				                <a class="btn btn-block btn-success" href="<?php echo root_url();?>index.php"><?php echo trans('text_login_now'); ?> &rarr;</a>
				            </div>
						</div>
			        </div>
			    </div>
			</div>
            <div class="text-center copyright">&#169; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, <?php echo trans('text_all_right_reserved'); ?></div>
		</div>
	</div>
</div>