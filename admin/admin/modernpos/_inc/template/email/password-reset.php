Dear <?php echo $recipient_name; ?>! 
<br/>
<?php echo trans('text_password_reset_body_text'); ?>
<br/><br/>
<?php echo trans('text_to_reset_password_follow_this_link'); ?>
<a href="<?php echo $reset_pass_link; ?>"><?php echo $reset_pass_link; ?></a>
<br/><br/>
Regards,
<br/>
<?php echo $from_name; ?>