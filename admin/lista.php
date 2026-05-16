<?php 
ob_start();
session_start();
include ("../_init.php"); // Sobe um nível para o _init.php

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_store')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// O bug de redirecionamento que estava no store.php original FOI REMOVIDO.
// Não precisamos do bloco "if (isset($request->get['active_store_id']))"

// Set Document Title
$document->setTitle(trans('title_store'));

// Add Script (Essencial para a tabela funcionar)
$document->addScript('../assets/itsolution24/angular/controllers/StoreController.js');

// =======================================================
// INÍCIO DO HTML (Novo Layout)
// =======================================================
include ("../_inc/template/header_admin.php"); // Usa o NOVO header (com ../)
include ("../_inc/template/sidebar_admin.php"); // Usa a NOVA sidebar (com ../)
?>

<div class="main-content" ng-controller="StoreController">

    <div class="page-header">
        <div class="welcome-text">
            <h1><?php echo trans('text_store_list_title'); ?></h1>
            <p>Gerencie todas as lojas cadastradas no sistema.</p>
        </div>
        <div class="header-actions">
            <a href="store_create.php" class="btn btn-brand">
                <i class="bi bi-plus-lg"></i> Adicionar Nova Loja
            </a>
        </div>
    </div>

    <?php if(DEMO) : ?>
    <div class="alert alert-info" role="alert">
        <h5 class="alert-heading">Modo Demonstração</h5>
        <p><?php echo $demo_text; ?></p>
        <hr>
        <p class="mb-0">A funcionalidade de apagar lojas está desabilitada no modo DEMO.</p>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-shop-window me-2"></i>
                <?php echo trans('text_store_list_title'); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">  
                <?php
                    $hide_colums = "";
                    if (user_group_id() != 1) {
                      if (! has_permission('access', 'update_store')) {
                        $hide_colums .= "5,";
                      }
                      if (! has_permission('access', 'delete_store')) {
                        $hide_colums .= "6,";
                      }
                      if (! has_permission('access', 'activate_store')) {
                        $hide_colums .= "7,";
                      }
                    }
                ?>  
              
                <table id="store-store-list" class="table table-hover align-middle" data-hide-colums="<?php echo $hide_colums; ?>">
                    <thead>
                      <tr class="table-dark">
                        <th class="w-5">
                          <?php echo sprintf(trans('label_serial_no'), null); ?>
                        </th>
                        <th class="w-20">
                          <?php echo sprintf(trans('label_name'), null); ?>
                        </th>
                        <th class="w-15">
                          <?php echo trans('label_country'); ?>
                        </th>
                        <th class="w-25">
                          <?php echo trans('label_address'); ?>
                        </th>
                        <th class="w-20">
                          <?php echo trans('label_created_at'); ?>
                        </th>
                        <th class="w-5 text-center">
                          <?php echo trans('label_edit'); ?>
                        </th>
                        <th class="w-5 text-center">
                          <?php echo trans('label_delete'); ?>
                        </th>
                        <th class="w-5 text-center">
                          <?php echo trans('label_action'); ?>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                        </tbody>
                    <tfoot>
                      <tr class="table-dark">
                        <th class="w-5"><?php echo sprintf(trans('label_serial_no'), null); ?></th>
                        <th class="w-20"><?php echo sprintf(trans('label_name'), null); ?></th>
                        <th class="w-15"><?php echo trans('label_country'); ?></th>
                        <th class="w-25"><?php echo trans('label_address'); ?></th>
                        <th class="w-20"><?php echo trans('label_created_at'); ?></th>
                        <th class="w-5 text-center"><?php echo trans('label_edit'); ?></th>
                        <th class="w-5 text-center"><?php echo trans('label_delete'); ?></th>
                        <th class="w-5 text-center"><?php echo trans('label_action'); ?></th>
                      </tr>
                    </tfoot>
                </table>
            </div>
          </div>
        </div>
      </div>
    </div>

</div>
<?php
// 3. Inclui o Rodapé (Fecha o HTML, carrega os JS)
include ("../_inc/template/footer_admin.php"); 
?>