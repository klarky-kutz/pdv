<br>
<div class="container">
    <div class="row">
        <div class="col-sm-8 col-sm-offset-2">
            <div class="panel panel-default header">
                <div class="panel-heading text-center">
                    <h2><?php echo trans('text_verify_purchase_code'); ?></h2>
                    <p><?php echo trans('text_running_step_2_of_6'); ?></p>
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
                        <li class="active">
                            <span class="fa fa-check"></span> <a href="purchase_code.php"> <?php echo trans('text_verification'); ?></a>
                        </li>
                        <li>
                            <a href="#"><?php echo trans('text_database'); ?></a>
                        </li>
                        <li>
                            <a href="#" onClick="return false"><?php echo trans('text_timezone'); ?></a>
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

                    <?php if($errors['internet_connection']) : ?>
                        <div class="alert alert-danger">
                            <p><?php echo $errors['internet_connection']; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if($errors['config_error']) : ?>
                        <div class="alert alert-danger">
                            <p><?php echo $errors['config_error']; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form id="purchaseCodeForm" class="form-horizontal" role="form" action="purchae_code.php" method="post">
                        <?php 
                        if($errors['purchase_username']) 
                            echo "<div class='form-group has-error' >";
                        else     
                            echo "<div class='form-group' >";
                        ?>
                            <label for="purchase_username" class="col-sm-3 control-label">
                                <?php echo trans('text_envato_username'); ?> <span class="text-aqua">*</span>
                            </label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control" id="purchase_username" name="purchase_username" value="<?php echo isset($request->post['purchase_username']) ? $request->post['purchase_username'] : null; ?>" autocomplete="off">

                                <p class="control-label">
                                    <?php echo $errors['purchase_username']; ?>
                                </p>
                            </div>
                        </div>

                        <?php 
                        if($errors['purchase_code']) 
                            echo "<div class='form-group has-error' >";
                        else     
                            echo "<div class='form-group' >";
                        ?>
                            <label for="purchase_code" class="col-sm-3 control-label">
                                <?php echo trans('text_purchase_code'); ?> <span class="text-aqua">*</span>
                            </label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control" id="purchase_code" name="purchase_code" value="<?php echo isset($request->post['purchase_code']) ? $request->post['purchase_code'] : null; ?>" autocomplete="off">

                                <p class="control-label">
                                    <?php echo $errors['purchase_code']; ?>
                                </p>
                            </div>
                        </div>

                        <br>

                        <div class="form-group">
                            <div class="col-sm-6 text-right">
                                <a href="index.php" class="btn btn-default">&larr; <?php echo trans('text_prev_step'); ?></a>
                            </div>
                            <div class="col-sm-6 text-left">
                                <button class="btn btn-success ajaxcall" data-form="purchaseCodeForm" data-loading-text="Checking..."><?php echo trans('text_next_step'); ?> &rarr;</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center copyright">&#169; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, <?php echo trans('text_all_right_reserved'); ?></div>
        </div>
    </div>
</div>