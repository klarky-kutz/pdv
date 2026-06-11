<br>
<div class="container">
    <div class="row">
        <div class="col-sm-8 col-sm-offset-2">
            <div class="panel panel-default header">
                <div class="panel-heading text-center bg-database">
                    <h2><?php echo trans('text_pre_installation_checklist'); ?></h2>
                    <p><?php echo trans('text_running_step_1_of_6'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-8 col-sm-offset-2">   
            <div class="panel panel-default menubar">
                <div class="panel-heading bg-white">
                    <ul class="nav nav-pills">
                        <li class="active">
                            <a href="index.php">
                                <span class="fa fa-check"></span> <?php echo trans('text_checklist'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                               <?php echo trans('text_verification'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#"><?php echo trans('text_database'); ?>
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
                <div class="panel-body ins-bg-col mtb-10">
                	<?php  

                		foreach ($success as $succ) {
                		 	echo "<div class=\"alert alert-success\"><span class=\"fa fa-check-circle\"></span> ". $succ ."</div>";	
                		}

                		foreach ($errors as $er) {
                		 	echo "<div class=\"alert alert-danger\"><span class=\"fa fa-exclamation-circle\"></span> ". $er ."</div>";
                		}
                	?>

                    <?php if(empty($errors)) : ?>
                        <div class="col-sm-4 col-sm-offset-4 text-center mt-10">
                            <a href="purchase_code.php" class="btn btn-block btn-success"><?php echo trans('text_next_step'); ?> &rarr;</a>
                        </div>
                    <?php else : ?>
                        
                        <div class="alert alert-warning">
                            <?php echo trans('text_installation_instruction'); ?>
                        </div>
                    
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center copyright">&#169; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, <?php echo trans('text_all_right_reserved'); ?></div>
        </div>
    </div>
</div>