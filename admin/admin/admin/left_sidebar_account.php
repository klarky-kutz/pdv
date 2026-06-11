<?php
// Sidebar para o Painel da Conta / Organização (Visão Geral das Lojas, etc.)
// Usa o mesmo estilo e user panel do ModernPOS, mas com menu específico de conta.
?>
<aside class="main-sidebar">
  <section class="sidebar">

    <!-- Sidebar User Panel (igual ao sistema) -->
    <div class="user-panel">
      <div class="pull-left image">
        <?php if (get_the_user(user_id(), 'user_image') && ((FILEMANAGERPATH && is_file(FILEMANAGERPATH.get_the_user(user_id(), 'user_image')) && file_exists(FILEMANAGERPATH.get_the_user(user_id(), 'user_image'))) || (is_file(DIR_STORAGE . 'users' . get_the_user(user_id(), 'user_image')) && file_exists(DIR_STORAGE . 'users' . get_the_user(user_id(), 'user_image'))))) : ?>
          <div class="user-thumbnail">
            <a href="admin/user_profile.php?id=<?php echo user_id();?>">
              <img src="<?php echo FILEMANAGERURL ? FILEMANAGERURL : root_url().'storage/users'; ?>/<?php echo get_the_user(user_id(), 'user_image'); ?>" class="max-hw100" alt="user image">
            </a>
          </div>
        <?php else : ?>
          <svg class="svg-icon"><use href="#icon-avatar"></svg>
        <?php endif; ?>
      </div>
      <div class="pull-left info">
        <p class="username" title="<?php echo $user->getUserName(); ?>">
          <?php echo ucfirst(limit_char($user->getUserName(), 15)); ?>
        </p>
        <a href="admin/user_profile.php?id=<?php echo user_id();?>">
          <i class="fa fa-circle user-status-dot"></i>
          <?php echo limit_char($user->getRole(), 14); ?>
        </a>
      </div>
    </div>

    <!-- Menu lateral da Conta / Organização -->
    <ul class="sidebar-menu">
      <li class="header"><?php echo strtoupper(trans('text_account_panel')); ?></li>

      <!-- Visão Geral (página atual: store_select.php) -->
      <li class="active">
        <a href="<?php echo root_url(); ?>/store_select.php">
          <svg class="svg-icon"><use href="#icon-dashboard"></svg>
          <span>Visão Geral</span>
        </a>
      </li>

      <!-- Lojas -->
      <li class="treeview menu-open">
        <a href="#">
          <svg class="svg-icon"><use href="#icon-store"></svg>
          <span>Lojas</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="treeview-menu">
          <li class="active">
            <a href="<?php echo root_url(); ?>/store_select.php">
              <svg class="svg-icon"><use href="#icon-eye"></svg>
              <span>Visão geral</span>
            </a>
          </li>
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-list"></svg>
              <span>Gerenciar lojas</span>
            </a>
          </li>
        </ul>
      </li>

      <!-- Assinatura & Planos -->
      <li class="treeview">
        <a href="#">
          <svg class="svg-icon"><use href="#icon-money"></svg>
          <span>Assinatura &amp; Planos</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="treeview-menu">
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-eye"></svg>
              <span>Plano atual</span>
            </a>
          </li>
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-graph"></svg>
              <span>Upgrade / Downgrade</span>
            </a>
          </li>
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-report"></svg>
              <span>Histórico de cobrança</span>
            </a>
          </li>
        </ul>
      </li>

      <!-- Usuários da conta -->
      <li class="treeview">
        <a href="#">
          <svg class="svg-icon"><use href="#icon-group"></svg>
          <span>Usuários da conta</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="treeview-menu">
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-group"></svg>
              <span>Usuários com acesso às lojas</span>
            </a>
          </li>
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-settings"></svg>
              <span>Permissões gerais</span>
            </a>
          </li>
        </ul>
      </li>

      <!-- Relatórios Consolidados -->
      <li class="treeview">
        <a href="#">
          <svg class="svg-icon"><use href="#icon-report"></svg>
          <span>Relatórios Consolidados</span>
          <i class="fa fa-angle-left pull-right"></i>
        </a>
        <ul class="treeview-menu">
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-money"></svg>
              <span>Vendas consolidadas</span>
            </a>
          </li>
          <li>
            <a href="#">
              <svg class="svg-icon"><use href="#icon-graph"></svg>
              <span>Comparativo de lojas</span>
            </a>
          </li>
        </ul>
      </li>

      <!-- Voltar para painel de loja (usa o fluxo padrão do sistema) -->
      <li>
        <a href="<?php echo root_url().ADMINDIRNAME; ?>/dashboard.php">
          <svg class="svg-icon"><use href="#icon-dashboard"></svg>
          <span>Ir para uma loja</span>
        </a>
      </li>

      <li id="sidebar-bottom"></li>
    </ul>

  </section>
</aside>
