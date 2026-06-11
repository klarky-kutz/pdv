<?php
// ARQUIVO: store_select_basic.php
// (Este arquivo é chamado pelo store_select.php se o usuário NÃO for Admin)

// O _init.php e session_start() JÁ FORAM CHAMADOS pelo roteador.

// =======================================================
// VERIFICA STATUS DE TRIAL PARA EXIBIR ALERTA
// =======================================================
$trialAlertShowBasic = false;
$trialDaysRemainingBasic = 0;

try {
    $pdoBasic = db();
    $currentTenantIdBasic = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    // Fallback: buscar tenant_id do usuário se não estiver na sessão
    if ($currentTenantIdBasic <= 0 && function_exists('user_id') && user_id() > 0) {
        $stmtUserBasic = $pdoBasic->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmtUserBasic->execute([user_id()]);
        $userRowBasic = $stmtUserBasic->fetch(PDO::FETCH_ASSOC);
        if ($userRowBasic && !empty($userRowBasic['tenant_id'])) {
            $currentTenantIdBasic = (int)$userRowBasic['tenant_id'];
        }
    }
    
    if ($currentTenantIdBasic > 0) {
        $stmtTenantBasic = $pdoBasic->prepare("
            SELECT subscription_status, trial_ends_at 
            FROM tenants 
            WHERE tenant_id = ? 
            LIMIT 1
        ");
        $stmtTenantBasic->execute([$currentTenantIdBasic]);
        $tenantDataBasic = $stmtTenantBasic->fetch(PDO::FETCH_ASSOC);
        
        if ($tenantDataBasic) {
            $subscriptionStatusBasic = $tenantDataBasic['subscription_status'] ?? '';
            
            if ($subscriptionStatusBasic === 'trial' && !empty($tenantDataBasic['trial_ends_at'])) {
                $trialEndsAtBasic = new DateTime($tenantDataBasic['trial_ends_at']);
                $nowBasic = new DateTime();
                $diffBasic = $nowBasic->diff($trialEndsAtBasic);
                
                if ($nowBasic < $trialEndsAtBasic) {
                    $trialDaysRemainingBasic = (int)$diffBasic->days;
                    $trialAlertShowBasic = true;
                } else {
                    $trialDaysRemainingBasic = 0;
                    $trialAlertShowBasic = true;
                }
            }
        }
    }
} catch (Exception $e) {
    // Silencioso
}
?>
<!DOCTYPE html>
<html lang="<?php echo $document->langTag($active_lang);?>">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Select Store<?php echo store('name') ? ' | ' . store('name') : null; ?></title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

  <?php if ($store->get('favicon')): ?>
      <link rel="shortcut icon" href="assets/itsolution24/img/logo-favicons/<?php echo $store->get('favicon'); ?>">
  <?php else: ?>
      <link rel="shortcut icon" href="assets/itsolution24/img/logo-favicons/nofavicon.png">
  <?php endif; ?>

  <?php if (DEMO || USECOMPILEDASSET) : ?>
    <link type="text/css" href="assets/itsolution24/cssmin/login.css" rel="stylesheet">
  <?php else : ?>
    <link type="text/css" href="assets/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link type="text/css" href="assets/perfectScroll/css/perfect-scrollbar.css" rel="stylesheet">
    <link type="text/css" href="assets/toastr/toastr.min.css" rel="stylesheet">
    <link type="text/css" href="assets/itsolution24/css/theme.css" rel="stylesheet">
    <link type="text/css" href="assets/itsolution24/css/login.css" rel="stylesheet">
  <?php endif; ?>

  <script type="text/javascript">
    var baseUrl = "<?php echo root_url(); ?>";
    var adminDir = "<?php echo ADMINDIRNAME; ?>";
    var refUrl = "<?php echo isset($session->data['ref_url']) ? $session->data['ref_url'] : ''?>";
  </script>

  <?php if (DEMO || USECOMPILEDASSET) : ?>
    <script src="assets/itsolution24/jsmin/login.js"></script>
  <?php else : ?>
    <script src="assets/jquery/jquery.min.js" type="text/javascript"></script>
    <script src="assets/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="assets/perfectScroll/js/perfect-scrollbar.jquery.min.js" type="text/javascript"></script>
    <script src="assets/toastr/toastr.min.js" type="text/javascript"></script>
    <script src="assets/itsolution24/js/common.js"></script>
    <script src="assets/itsolution24/js/login.js"></script>
  <?php endif; ?>
</head>
<body class="login-page">
<div class="hidden"><?php include('assets/itsolution24/img/iconmin/membership/membership.svg');?></div>

<?php // Banner de Trial (Período de Teste) ?>
<?php if ($trialAlertShowBasic): ?>
<div class="trial-alert-banner" style="
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
">
    <i class="fa fa-clock-o" style="font-size: 1.2rem;"></i>
    <span style="font-size: 0.9rem; text-align: center;">
        <strong>🎉 Período de Teste</strong>
        <?php if ($trialDaysRemainingBasic > 0): ?>
            - Restam <strong style="background: rgba(255,255,255,0.25); padding: 2px 8px; border-radius: 4px;"><?php echo $trialDaysRemainingBasic; ?> dia<?php echo $trialDaysRemainingBasic > 1 ? 's' : ''; ?></strong>
        <?php else: ?>
            - <span style="background: rgba(255,59,59,0.4); padding: 2px 8px; border-radius: 4px;">Expirado</span>
        <?php endif; ?>
    </span>
    <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-light btn-xs" style="font-weight: 600; font-size: 0.8rem; padding: 4px 12px;">
        Ver Planos &rarr;
    </a>
</div>
<style>
  .login-page { padding-top: 50px; }
</style>
<?php endif; ?>

  <section class="login-box">
    <div class="login-logo">
      <div class="text">
        <p><strong><?php echo trans('text_select_store'); ?></strong></p>
      </div>
    </div>
    <?php if (isset($error_message)) { ?>
      <div class="alert alert-danger">
          <p class=""><span class="fa fa-fw fa-warning"></span> <?php echo $error_message ; ?></p>
      </div>
      <br>
    <?php } ?>
    
    <div id="store-launcher" class="login-box-body" ng-controller="StoreController">
      <ul class="list-unstyled list-group store-list">
        <?php foreach (get_stores() as $the_store): ?>
          <li class="list-group-item">
            <a class="activate-store" href="<?php echo root_url();?><?php echo ADMINDIRNAME;?>/store.php?active_store_id=<?php echo $the_store['store_id']; ?>">
              <div class="store-icon">
                <svg class="svg-icon"><use href="#icon-store"></svg>
              </div>
              <div class="store-name">
                <?php echo $the_store['name']; ?>
                <span class="pull-right">&rarr;</span>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="copyright text-center">
      <p>&copy; <a href="<?php echo trans('text_footer_link'); ?>"><?php echo trans('text_footer_link_text'); ?></a>, v<?php echo settings('version'); ?></p>
    </div>
  </section>

<script type="text/javascript">
$(document).ready(function() {
    "use strict";
  $(".store-list").perfectScrollbar();
});
</script>

<noscript>You need to have javascript enabled in order to use <strong><?php echo store('name');?></strong>.</noscript>
</body>
</html>