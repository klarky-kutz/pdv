    <!-- Scrolling Sidebar Start -->
    <aside class="scrolling-sidebar scrolling-sidebar-dark">
        <h2 class="scrolling-sidebar-title r-0"><?php echo trans('text_reports');?></h2>
        <?php 
        $statement = $db->prepare("SELECT * FROM `shortcut_links` WHERE `type` = ? AND `status` = ? ORDER BY `sort_order` ASC");
        $statement->execute(array('report', 1));
        $shortcut_links = $statement->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="searchbox searchbox-m text-center">
          <input ng-model="searchList" class="form-control r-50" type="search" name="search" placeholder="<?php echo trans('placeholder_search_here');?>" autocomplete="off">
        </div>
        <ul filter-list="searchList" class="list-group search-list-wrapper">
        <?php $inc=0;foreach ($shortcut_links as $link) : $btnColor=$inc % 2 == 0 ? 'success' : 'success'?>
          <?php if (user_group_id() == 1 || has_permission('access', $link['permission_slug'])) :?>
            <li class="list-group-item">
                <a class="btn btn-<?php echo $btnColor;?> btn-block" href="<?php echo root_url().$link['href'];?>"><span class="fa fa-fw <?php echo $link['icon'];?>"></span> <?php echo $link['title'];?></a>
            </li>
          <?php endif;?>
        <?php $inc++;endforeach;?>
        </ul>                    
    </aside>
    <div class="scrolling-sidebar-bg"></div>
    <div class="scrolling-sidebar-mask"></div>
    <!-- Scrolling Sidebar End -->

    <footer class="main-footer">
    	<div class="pull-right hidden-xs">
            <?php echo trans('text_version'); ?>
            <?php echo settings('version'); ?>
    	</div>
    	<div class="copyright">Copyright © <?php echo date('Y'); ?> <a href="https://itsolution24.com">ITsolution24.com</a>, All rights reserved.</div>
    </footer>
</div>
<!-- End Wrapper -->

<!-- Start Filter Box -->
<div id="filter-box" class="text-center">
    <div class="jumbotron">
        <div class="container">
            <form action="" method="get">
                <div class="row">
                    <?php if (!empty($request->get)) : ?>
                        <?php foreach ($request->get as $key => $value) : ?>
                          <?php if (!in_array($key, array('from', 'to'))) : ?>
                            <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>">
                          <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="col-md-4 col-md-offset-2 form-group-lg">
                        <input class="form-control date" type="date" name="from" value="<?php echo isset($request->get['from']) ? $request->get['from'] : null;?>" placeholder="From" readonly>
                    </div>
                    <div class="col-md-4 form-group-lg">
                        <input class="form-control date" type="date" name="to" value="<?php echo isset($request->get['to']) ? $request->get['to'] : null;?>" placeholder="To" readonly>
                    </div>
                </div>
                <div class="well r-50">
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-md-2">
                            <a href="<?php echo relative_url();?><?php echo $query_string ? $query_string.'&' : '?';?>ftype=today&from=<?php echo date('Y-m-d');?>&to=<?php echo date('Y-m-d');?>" class="btn btn-primary btn-block r-50" <?php echo isset($ftype) && $ftype == 'today' ? 'style="border:3px solid blue;"' : '';?>><?php echo trans('button_today');?></a>
                        </div>
                         <div class="col-md-2">
                            <a href="<?php echo relative_url();?><?php echo $query_string ? $query_string.'&' : '?';?>ftype=week&from=<?php echo date('Y-m-d', strtotime("-7 days", time()));?>&to=<?php echo date('Y-m-d');?>" class="btn btn-success btn-block r-50" <?php echo isset($ftype) && $ftype == 'week' ? 'style="border:3px solid green;"' : '';?>><?php echo trans('button_last_7_days');?></a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo relative_url();?><?php echo $query_string ? $query_string.'&' : '?';?>ftype=month&from=<?php echo date('Y-m-d', strtotime("-30 days", time()));?>&to=<?php echo date('Y-m-d');?>" class="btn btn-warning btn-block r-50" <?php echo isset($ftype) && $ftype == 'month' ? 'style="border:3px solid yellow;"' : '';?>><?php echo trans('button_last_30_days');?></a>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo relative_url();?><?php echo $query_string ? $query_string.'&' : '?';?>ftype=year&from=<?php echo date('Y-m-d', strtotime("-365 days", time()));?>&to=<?php echo date('Y-m-d');?>" class="btn btn-info btn-block r-50" <?php echo isset($ftype) && $ftype == 'year' ? 'style="border:3px solid blue;"' : '';?>><?php echo trans('button_last_365_days');?></a>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 col-md-offset-4">
                        <button class="btn btn-block btn-lg btn-danger" type="submit">
                            <span class="fa fa-search"></span> <?php echo trans('button_filter');?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div id="close-filter-box">
        <span class="fa fa-angle-up" title="Close"></span>
    </div>
</div>
<!-- End Filter Box -->

<script type="text/javascript">
var from = "<?php echo from() ? format_date(from()) : format_date(date('Y/m/d')); ?>";
var to = "<?php echo to() ? format_date(to()) : format_date(date('Y/m/d')); ?>";
</script>

<script type="text/javascript">
(function(){
    var miaBadgeTimer = null;
    function updateModaIABadge(){
        var el = document.getElementById('mia-orders-badge');
        if (!el) return;
        fetch('../_inc/ai_pedidos_actions.php?action=badge', { credentials: 'same-origin' })
            .then(function(r){
                if (!r.ok) {
                    if (r.status === 401 || r.status === 403) {
                        el.style.display = 'none';
                        if (miaBadgeTimer) {
                            clearInterval(miaBadgeTimer);
                            miaBadgeTimer = null;
                        }
                    }
                    return null;
                }
                return r.json();
            })
            .then(function(d){
                if (!d || d.error) return;
                var n = parseInt(d.pending || 0, 10) || 0;
                if (n > 0) {
                    el.textContent = n > 99 ? '99+' : String(n);
                    el.style.display = 'inline-block';
                } else {
                    el.style.display = 'none';
                }
            })
            .catch(function(){});
    }
    document.addEventListener('DOMContentLoaded', function(){
        updateModaIABadge();
        miaBadgeTimer = setInterval(updateModaIABadge, 30000);
    });
})();
</script>

<!-- Runtime JS -->
<?php foreach ($scripts as $script) : ?>
<script src="<?php echo $script; ?>" type="text/javascript"></script>
<?php endforeach; ?>

<!-- Product Code Generator for EAN-13 -->
<script src="<?php echo root_url(); ?>assets/itsolution24/js/product-code-generator.js" type="text/javascript"></script>

<noscript>
    <div class="global-site-notice noscript">
        <div class="notice-inner">
            <p><strong>JavaScript seems to be disabled in your browser.</strong><br>You must have JavaScript enabled in
                your browser to utilize the functionality of #MODERN POS.</p>
        </div>
    </div>
</noscript>

</body>
</html>
