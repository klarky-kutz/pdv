<h4 class="sub-title">
  Excluir Produto
</h4>
<form class="form-horizontal" id="product-del-form" action="product.php" method="post">
  <input type="hidden" id="action_type" name="action_type" value="DELETE">
  <input type="hidden" id="p_id" name="p_id" value="<?php echo $product['p_id']; ?>">
  <h4 class="box-title text-center">
    O que deseja fazer com este produto?
  </h4>
  <div class="box-body">
    <div class="form-group">
      <div class="col-sm-8 col-sm-offset-2">
          <label for="delete_all">
            <input type="radio" id="delete_all" value="delete_all" name="delete_action" checked> &nbsp;
            <i class="fa fa-trash text-danger"></i> Excluir Permanentemente
         </label>
      </div>
    </div>
    <div class="form-group">
      <div class="col-sm-8 col-sm-offset-2">
        <label for="soft_delete">
          <input type="radio" id="soft_delete" value="soft_delete" name="delete_action"> &nbsp;
          <i class="fa fa-recycle text-warning"></i> Enviar para Lixeira
        </label>
      </div>
    </div>
    <div class="form-group">
      <div class="col-sm-8 col-sm-offset-2">
        <button id="product-delete-submit" data-form="#product-del-form" data-datatable="#product-product-list" class="btn btn-danger" name="submit" data-loading-text="Deleting...">
          <span class="fa fa-fw fa-trash"></span>
          <?php echo trans('button_delete'); ?>
        </button>
      </div>
    </div>
  </div>
</form>