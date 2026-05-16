<?php 
ob_start();
session_start();
include ("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'barcode_print')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Set Document Title
$document->setTitle(trans('title_barcode'));

// Add Style
$document->addStyle('../assets/itsolution24/css/barcode.css', 'stylesheet', 'all');

// Add Script
$document->addScript('../assets/itsolution24/angular/controllers/BarcodePrintController.js');

// ADD BODY CLASS
$document->setBodyClass('sidebar-collapse');

// Include Header and Footer
include("header.php"); 
include ("left_sidebar.php") ;
?>

<!-- Custom Styles for Barcode Page -->
<style>
.barcode-search-box {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.barcode-search-box .search-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}
.barcode-search-box .search-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
}
.barcode-search-box .search-title h4 {
    margin: 0 0 3px 0;
    color: #333;
    font-size: 16px;
    font-weight: 600;
}
.barcode-search-box .search-title span {
    color: #888;
    font-size: 13px;
}
.barcode-search-input {
    position: relative;
}
.barcode-search-input input {
    width: 100%;
    padding: 15px 20px 15px 50px;
    font-size: 16px;
    border: 2px solid #ddd;
    border-radius: 10px;
    transition: all 0.3s ease;
    background: #fff;
}
.barcode-search-input input:focus {
    outline: none;
    border-color: #00a65a;
    box-shadow: 0 0 0 3px rgba(0,166,90,0.1);
}
.barcode-search-input .search-icon-input {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #00a65a;
    font-size: 20px;
}
.barcode-search-input .search-hint {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: #e9ecef;
    padding: 5px 12px;
    border-radius: 5px;
    font-size: 12px;
    color: #666;
}
#product-table {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
#product-table thead tr {
    background: linear-gradient(135deg, #343a40 0%, #212529 100%) !important;
}
#product-table thead th {
    color: #fff !important;
    font-weight: 600;
    padding: 12px 15px;
    border: none !important;
}
#product-table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
}
#product-table tbody tr:hover {
    background-color: #f0fff4 !important;
}
#product-table .quantity {
    border: 2px solid #ddd;
    border-radius: 6px;
    padding: 8px;
    font-weight: 600;
    max-width: 100px;
    margin: 0 auto;
}
#product-table .quantity:focus {
    border-color: #00a65a;
    box-shadow: 0 0 0 2px rgba(0,166,90,0.1);
}
#product-table .remove {
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s ease;
}
#product-table .remove:hover {
    transform: scale(1.2);
}
.options-panel {
    background: #fff;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}
.options-panel .panel-title {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.options-panel .panel-title i {
    color: #00a65a;
}
.fields-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}
.field-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.2s ease;
    cursor: pointer;
}
.field-checkbox:hover {
    border-color: #00a65a;
    background: #f0fff4;
}
.field-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.field-checkbox label {
    margin: 0;
    cursor: pointer;
    font-weight: 500;
    color: #333;
}
.btn-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
}
.btn-actions .btn {
    min-width: 180px;
    padding: 12px 25px;
    font-weight: 600;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-generate {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
    border: none !important;
    color: #fff !important;
}
.btn-generate:hover {
    box-shadow: 0 4px 15px rgba(23,162,184,0.4);
    transform: translateY(-2px);
}
.btn-reset {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
    border: none !important;
    color: #fff !important;
}
.btn-reset:hover {
    box-shadow: 0 4px 15px rgba(220,53,69,0.4);
    transform: translateY(-2px);
}
.empty-table-msg {
    text-align: center;
    padding: 40px 20px;
    color: #888;
}
.empty-table-msg i {
    font-size: 50px;
    color: #ddd;
    margin-bottom: 15px;
}
.empty-table-msg h5 {
    margin: 0 0 5px 0;
    color: #666;
}
.empty-table-msg p {
    margin: 0;
    font-size: 13px;
}
.layout-select {
    padding: 12px 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.layout-select:focus {
    border-color: #00a65a;
    box-shadow: 0 0 0 3px rgba(0,166,90,0.1);
}

/* Barcode Preview Styles */
.barcode-preview-container {
    background: #f5f5f5;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    margin-top: 25px;
}
.barcode-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}
.barcode-preview-header h4 {
    margin: 0;
    color: #333;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.barcode-preview-header h4 i {
    color: #ff9800;
}
.btn-print-barcode {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%) !important;
    border: none !important;
    color: #fff !important;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(255,152,0,0.3);
}
.btn-print-barcode:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255,152,0,0.4);
    color: #fff;
}
.barcode-preview-area {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    max-height: 600px;
    overflow-y: auto;
}

/* Enhanced Barcode Item Styles */
.barcode .item-inner {
    display: table-cell;
    vertical-align: middle;
    border: 2px solid #333;
    border-radius: 6px;
    padding: 8px;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.barcode .barcode_site {
    font-weight: 700;
    font-size: 11px;
    color: #333;
    margin-bottom: 3px;
    text-transform: uppercase;
}
.barcode .barcode_name {
    font-size: 10px;
    color: #555;
    margin-bottom: 3px;
    line-height: 1.2;
    max-height: 24px;
    overflow: hidden;
}
.barcode .barcode_image {
    margin: 5px 0;
}
.barcode .barcode_image img.bcimg {
    max-width: 100%;
    height: auto;
}
.barcode .barcode_image .text-center {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    margin-top: 3px;
}
.barcode .product_image img {
    border-radius: 4px;
    border: 1px solid #eee;
}

/* Print specific improvements */
@media print {
    .barcode-preview-container {
        background: none !important;
        border: none !important;
        padding: 0 !important;
    }
    .barcode-preview-header {
        display: none !important;
    }
    .barcode .item-inner {
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }
}
</style>

<!-- Content Wrapper Start -->
<div class="content-wrapper" ng-controller="BarcodePrintController">

  <!-- Content Header Start -->
  <section class="content-header">
    <h1>
      <?php echo trans('text_barcode_title'); ?>
      <small>
        <?php echo store('name'); ?>
      </small>
    </h1>
    <ol class="breadcrumb">
      <li>
        <a href="dashboard.php">
          <i class="fa fa-dashboard"></i> 
          <?php echo trans('text_dashboard'); ?>
        </a>
      </li>
      <li>
        <?php if (isset($request->get['box_state']) && $request->get['box_state']=='open'): ?>
          <a href="barcode_print.php"><?php echo trans('text_barcode_title'); ?></a>  
        <?php else: ?>
          <?php echo trans('text_barcode_title'); ?>  
        <?php endif; ?>
      </li>
      <?php if (isset($request->get['box_state']) && $request->get['box_state']=='open'): ?>
        <li class="active">
          <?php echo trans('text_add'); ?> 
        </li>
      <?php endif; ?>
    </ol>
  </section>
  <!-- Content Header end -->

  <!-- Content Start -->
  <section class="content">

    <?php if(DEMO) : ?>
    <div class="box">
      <div class="box-body">
        <div class="alert alert-info mb-0">
          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-xs-12">
        <div class="box box-success">
          <div class="box-header">
            <h3 class="box-title">
              <i class="fa fa-barcode"></i> <?php echo trans('text_barcode_generate_title'); ?>
            </h3>
          </div>
          <div class='box-body'> 
            <form id="form-barcode-generate" class="form-horizontal" action="barcode_print.php#barcode-con" method="post">
              
              <!-- Search Box -->
              <div class="barcode-search-box">
                <div class="search-header">
                  <div class="search-icon">
                    <i class="fa fa-search"></i>
                  </div>
                  <div class="search-title">
                    <h4><?php echo trans('label_add_product'); ?></h4>
                    <span>Digite o nome ou código do produto para adicionar</span>
                  </div>
                </div>
                <div class="barcode-search-input">
                  <i class="fa fa-barcode search-icon-input"></i>
                  <input type="text" name="add_item" value="" class="autocomplete-product" id="add_item" data-type="p_name" onkeypress="return event.keyCode != 13;" onclick="this.select();" placeholder="<?php echo trans('placeholder_search_product'); ?>" autocomplete="off" tabindex="1">
                  <span class="search-hint"><i class="fa fa-keyboard-o"></i> Digite para buscar</span>
                </div>
              </div>

              <!-- Products Table -->
              <div class="table-responsive">
                <table id="product-table" class="table table-striped table-bordered">
                  <thead>
                    <tr>
                      <th class="text-center" style="width:50%">
                        <i class="fa fa-cube"></i> <?php echo trans('label_product_name_with_code'); ?>
                      </th>
                      <th class="text-center" style="width:15%">
                        <i class="fa fa-cubes"></i> <?php echo trans('label_available'); ?>
                      </th>
                      <th class="text-center" style="width:20%">
                        <i class="fa fa-print"></i> <?php echo trans('label_quantity'); ?>
                      </th>
                      <th class="text-center" style="width:15%">
                        <i class="fa fa-trash"></i> <?php echo trans('label_delete'); ?>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                          <?php if (isset($request->post['products']) && !empty($request->post['products'])) : 
                          foreach ($request->post['products'] as $item): $product = get_the_product($item['item_id']); ?>
                            <tr id="<?php echo $product['p_id'];?>" class="<?php echo $product['p_id'];?> success" data-item-id="<?php echo $product['p_id'];?>">
                              <td class="text-center"  data-title="Product Name">
                                <input name="products[<?php echo $product['p_id'];?>][item_id]" type="hidden" class="item-id" value="<?php echo $product['p_id'];?>">
                                <input name="products[<?php echo $product['p_id'];?>][item_name]" type="hidden" class="item-name" value="<?php echo $product['p_name'];?>">
                                <span class="name" id="name-<?php echo $product['p_id'];?>"><?php echo $product['p_name'];?>-<?php echo $product['p_code'];?></span>
                              </td>
                              <td class="text-center" data-title="Available"><?php echo format_input_number($product['quantity_in_stock']);?></td>
                              <td data-title="Quantity">
                                <input class="form-control input-sm text-center quantity" name="products[<?php echo $product['p_id'];?>][quantity]" type="number" value="<?php echo $item['quantity'];?>" data-id="<?php echo $product['p_id'];?>" id="quantity-<?php echo $product['p_id'];?>" onclick="this.select();" onkeyup="if(this.value<=0){this.value=1;}">
                              </td>
                              <td class="text-center">
                                <i class="fa fa-close text-red pointer remove" data-id="<?php echo $product['p_id'];?>" title="Remove"></i>
                              </td>
                            </tr>
                    <?php endforeach;?>
                    <?php endif;?>
                    <?php if (!isset($request->post['products']) || empty($request->post['products'])) : ?>
                    <tr id="empty-row">
                      <td colspan="4">
                        <div class="empty-table-msg">
                          <i class="fa fa-inbox"></i>
                          <h5>Nenhum produto adicionado</h5>
                          <p>Use a busca acima para adicionar produtos</p>
                        </div>
                      </td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Options Panel -->
              <div class="options-panel">
                <div class="row">
                  <div class="col-md-6">
                    <div class="panel-title">
                      <i class="fa fa-file-text-o"></i> <?php echo trans('label_page_layout'); ?>
                    </div>
                    <select name="per_page" class="form-control layout-select" id="per_page">
                      <option value=""><?php echo trans('text_select');?></option>
                      <option value="40" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 40 ? 'selected' : 'selected';?>>40 por folha (A4) - 1.799" x 1.003"</option>
                      <option value="30" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 30 ? 'selected' : null;?>>30 por folha - 2.625" x 1"</option>
                      <option value="24" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 24 ? 'selected' : null;?>>24 por folha (A4) - 2.48" x 1.334"</option>
                      <option value="20" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 20 ? 'selected' : null;?>>20 por folha - 4" x 1"</option>
                      <option value="18" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 18 ? 'selected' : null;?>>18 por folha (A4) - 2.5" x 1.835"</option>
                      <option value="14" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 14 ? 'selected' : null;?>>14 por folha - 4" x 1.33"</option>
                      <option value="12" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 12 ? 'selected' : null;?>>12 por folha (A4) - 2.5" x 2.834"</option>
                      <option value="10" <?php echo isset($request->post['per_page']) && $request->post['per_page'] == 10 ? 'selected' : null;?>>10 por folha - 4" x 2"</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <div class="panel-title">
                      <i class="fa fa-check-square-o"></i> <?php echo trans('label_fields');?>
                    </div>
                    <div class="fields-grid">
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[site_name]" id="field_site_name" value="1" 
                          <?php if(isset($request->post['fields']['site_name']) && $request->post['fields']['site_name']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo 'checked';
                          }?>>
                        <label for="field_site_name">Nome da Loja</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[product_name]" id="field_product_name" value="1" 
                          <?php if(isset($request->post['fields']['product_name']) && $request->post['fields']['product_name']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo 'checked';
                          }?>>
                        <label for="field_product_name">Nome Produto</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[p_code]" id="field_p_code" value="1" 
                          <?php if(isset($request->post['fields']['p_code']) && $request->post['fields']['p_code']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo 'checked';
                          }?>>
                        <label for="field_p_code">Código</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[price]" id="field_price" value="1" 
                          <?php if(isset($request->post['fields']['price']) && $request->post['fields']['price']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo 'checked';
                          }?>>
                        <label for="field_price">Preço</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[currency]" id="field_currency" value="1" 
                          <?php if(isset($request->post['fields']['currency']) && $request->post['fields']['currency']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo 'checked';
                          }?>>
                        <label for="field_currency">Moeda</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[unit]" id="field_unit" value="1" 
                          <?php if(isset($request->post['fields']['unit']) && $request->post['fields']['unit']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo '';
                          }?>>
                        <label for="field_unit">Unidade</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[category]" id="field_category" value="1" 
                          <?php if(isset($request->post['fields']['category']) && $request->post['fields']['category']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo '';
                          }?>>
                        <label for="field_category">Categoria</label>
                      </div>
                      <div class="field-checkbox">
                        <input type="checkbox" name="fields[product_image]" id="field_product_image" value="1" 
                          <?php if(isset($request->post['fields']['product_image']) && $request->post['fields']['product_image']) {
                            echo 'checked';
                          } elseif (isset($request->post['fields'])) {
                            echo '';
                          } else {
                            echo 'checked';
                          }?>>
                        <label for="field_product_image">Imagem</label>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="btn-actions">
                  <button id="barcode-generate" class="btn btn-generate" data-form="#form-barcode-generate" name="submit" data-loading-text="Gerando...">
                    <i class="fa fa-cog"></i>
                    <?php echo trans('button_generate'); ?>
                  </button>
                  <a href="barcode_print.php" class="btn btn-reset">
                    <i class="fa fa-refresh"></i>
                    <?php echo trans('button_reset'); ?>
                  </a>
                </div>
              </div>
            </form>
            <div id="barcode-con">
              <?php 
              if(isset($request->post['products'])):
                $per_page = $request->post['per_page'];
                if (!$per_page) {
                  redirect(root_url().'admin/barcode_print.php');
                }
                $page_layout = '';
                switch ($per_page) {
                  case '10':
                    $page_layout = '';
                    break;
                  case '12':
                    $page_layout = 'a4';
                    break;
                  case '14':
                    $page_layout = '';
                    break;
                  case '18':
                    $page_layout = 'a4';
                    break;
                  case '20':
                    $page_layout = '';
                    break;
                  case '24':
                    $page_layout = 'a4';
                    break;
                  case '30':
                    $page_layout = '';
                    break;
                  case '40':
                    $page_layout = 'a4';
                    break;
                  default:
                    $page_layout = '';
                    break;
                }
                // Barcode
                $generator = barcode_generator();
                
                // Count total barcodes
                $total_barcodes = 0;
                foreach ($request->post['products'] as $prod) {
                  $total_barcodes += $prod['quantity'];
                }
                ?>

                <div class="barcode-preview-container">
                  <div class="barcode-preview-header">
                    <h4>
                      <i class="fa fa-eye"></i> 
                      Pré-visualização dos Códigos de Barras
                      <span class="badge" style="background:#17a2b8;margin-left:10px;"><?php echo $total_barcodes; ?> etiquetas</span>
                    </h4>
                    <button class="btn btn-print-barcode" onClick="window.printContent('barcode-print-area', {title:'<?php echo trans('text_barcode_print');?>',screenSize:'fullScreen', cssLink:'<link type=\'text/css\' href=\'../assets/itsolution24/css/barcode.css\' rel=\'stylesheet\'>'});">
                      <i class="fa fa-print"></i> <?php echo trans('button_print');?>
                    </button>
                  </div>
                  
                  <div class="barcode-preview-area" id="barcode-print-area">
                    <div class="barcode barcode<?php echo $page_layout;?>">
                  <?php $inc=1;foreach ($request->post['products'] as $prod): $product = get_the_product($prod['item_id']);
                  $symbology = $product['barcode_symbology'] ? $product['barcode_symbology'] : 'code39';
                  $symbology = barcode_symbology($generator, $symbology);?>
                    <?php for ($i=0; $i < $prod['quantity']; $i++): ?>
                      <div class="item style<?php echo $per_page;?>">
                        <div class="item-inner">
                          <?php if (isset($request->post['fields']['product_image']) && $request->post['fields']['product_image']):?>
                            <span class="product_image">
                                <?php if (isset($product['p_image']) && ((FILEMANAGERPATH && is_file(FILEMANAGERPATH.$product['p_image']) && file_exists(FILEMANAGERPATH.$product['p_image'])) || (is_file(DIR_STORAGE . 'products' . $product['p_image']) && file_exists(DIR_STORAGE . 'products' . $product['p_image'])))) : ?>
                                <img  src="<?php echo FILEMANAGERURL ? FILEMANAGERURL : root_url().'storage/products'; ?>/<?php echo $product['p_image']; ?>" alt="product img">
                              <?php else : ?>
                                <img src="../assets/itsolution24/img/noimage.jpg" alt="default img">
                              <?php endif; ?>
                            </span>
                          <?php endif;?>
                          <?php if (isset($request->post['fields']['site_name']) && $request->post['fields']['site_name']):?>
                            <div>
                              <span class="barcode_site"><?php echo store('name');?></span>
                            </div>
                          <?php endif;?>
                          <?php if (isset($request->post['fields']['product_name']) && $request->post['fields']['product_name']):?>
                            <div >
                              <span  class="barcode_name"><?php echo $product['p_name'];?></span>
                            </div>
                          <?php endif;?>
                          <?php if (isset($request->post['fields']['unit']) && $request->post['fields']['unit']):?>
                            <span class="barcode_unit"><?php echo trans('label_unit');?>: <?php echo get_the_unit($product['unit_id'],'unit_name');?></span>, 
                          <?php endif;?>
                          <?php if (isset($request->post['fields']['category']) && $request->post['fields']['category']):?>
                            <span class="barcode_category"><?php echo trans('label_category');?>: <?php echo get_the_category($product['category_id'],'category_name');?></span> 
                          <?php endif;?>
                          <?php if (isset($request->post['fields']['price']) && $request->post['fields']['price']):?>
                            <div>
                              <?php if (isset($request->post['fields']['currency']) && $request->post['fields']['currency']):?>
                              <?php echo get_currency_code();?> 
                              <?php endif;?>
                                <span><?php echo currency_format($product['sell_price']);?></span>
                            </div>
                          <?php endif;?>
                          <span class="barcode_image">
                              <img src="data:image/png;base64,<?php echo encode_data($generator->getBarcode($product['p_code'], $symbology, 1));?>" alt="</php echo $product['p_code'];?>" class="bcimg">
                              <?php if (isset($request->post['fields']['p_code']) && $request->post['fields']['p_code']):?>
                                <div class="text-center">
                                  <?php echo $product['p_code'];?>
                                </div>
                              <?php endif;?>
                          </span>
                        </div>
                      </div>
                    <?php 
                    if (($inc % $per_page) == 0):?>
                        </div>
                        <div class="barcode barcode<?php echo $page_layout;?>">
                    <?php endif;
                    $inc++;endfor;?>
                  <?php endforeach;?>
                    </div>
                  </div>
                  
                  <div style="text-align:center;margin-top:20px;">
                    <button class="btn btn-print-barcode" onClick="window.printContent('barcode-print-area', {title:'<?php echo trans('text_barcode_print');?>',screenSize:'fullScreen', cssLink:'<link type=\'text/css\' href=\'../assets/itsolution24/css/barcode.css\' rel=\'stylesheet\'>'});">
                      <i class="fa fa-print"></i> <?php echo trans('button_print');?>
                    </button>
                  </div>
                </div>

              <?php endif;?>
            </div>
          </div>
          <!-- .box-body -->
        </div>
      </div>
    </div>
  </section>
</div>
<!-- Content Wrapper End -->

<?php include ("footer.php"); ?>