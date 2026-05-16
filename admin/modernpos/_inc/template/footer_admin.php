<?php
// Este arquivo é o rodapé universal do painel admin.
?>

        <footer class="main-footer">
            © <?php echo date('Y'); ?> PDV Loja, v<?php echo settings('version');?>
        </footer>

    </div> </div> <script src="<?php echo root_url(); ?>/assets/jquery/jquery.min.js" type="text/javascript"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo root_url(); ?>/assets/toastr/toastr.min.js" type="text/javascript"></script>
<script src="<?php echo root_url(); ?>/assets/itsolution24/js/common.js"></script>
<script src="<?php echo root_url(); ?>/assets/itsolution24/js/login.js"></script> 

<script src="<?php echo root_url(); ?>/assets/itsolution24/angular/angular.min.js"></script>
<script src="<?php echo root_url(); ?>/assets/itsolution24/angular/app.js"></script>

<noscript>You need to have javascript enabled in order to use <strong><?php echo store('name');?></strong>.</noscript>
</body>
</html>