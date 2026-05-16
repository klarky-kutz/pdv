<?php 
ob_start();
session_start();
include ("../_init.php"); // Sobe um nível para o _init.php

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'create_store')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Set Document Title
$document->setTitle(trans('title_create_store'));

// =======================================================
// INÍCIO DO HTML
// =======================================================
include '../_inc/template/header_admin.php'; // Inclui o NOVO header
include '../_inc/template/sidebar_admin.php'; // Inclui a NOVA sidebar
?>

<div class="main-content" ng-controller="StoreActionController">

    <div class="page-header">
        <div class="welcome-text">
            <h1><?php echo trans('text_create_store_title'); ?></h1>
            <p>Preencha os dados para cadastrar uma nova loja.</p>
        </div>
        <div class="header-actions">
            <a href="store.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i> Cancelar e Voltar
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

    <form id="store-form" class="form-horizontal" action="store.php" method="post">
        <input type="hidden" name="action_type" value="CREATE">

        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs" id="store-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true"><?php echo trans('text_general'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="currency-tab" data-bs-toggle="tab" data-bs-target="#currency-setting" type="button" role="tab" aria-controls="currency-setting" aria-selected="false"><?php echo trans('text_currency'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment-method-setting" type="button" role="tab" aria-controls="payment-method-setting" aria-selected="false"><?php echo trans('text_payment_method'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="product-tab" data-bs-toggle="tab" data-bs-target="#product-setting" type="button" role="tab" aria-controls="product-setting" aria-selected="false"><?php echo trans('text_product'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="receipt-tab" data-bs-toggle="tab" data-bs-target="#receipt-template" type="button" role="tab" aria-controls="receipt-template" aria-selected="false"><?php echo trans('text_receipt_template'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="printer-tab" data-bs-toggle="tab" data-bs-target="#printer" type="button" role="tab" aria-controls="printer" aria-selected="false"><?php echo trans('text_printer'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-setting" type="button" role="tab" aria-controls="email-setting" aria-selected="false"><?php echo trans('text_email_setting'); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ftp-tab" data-bs-toggle="tab" data-bs-target="#ftp-setting" type="button" role="tab" aria-controls="ftp-setting" aria-selected="false"><?php echo trans('text_ftp_setting'); ?></button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="store-tabs-content">

                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        
                        <?php include('../_inc/template/partials/alert.php'); ?>

                        <div class="mb-3 row">
                            <label for="name" class="col-sm-3 col-form-label"><?php echo sprintf(trans('label_name'), null); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="name" value="<?php echo isset($request->post['name']) ? $request->post['name'] : null; ?>" name="name" ng-model="storeName">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="code_name" class="col-sm-3 col-form-label"><?php echo trans('label_code_name'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="code_name" value="{{ storeName | strReplace:' ':'_' | lowercase }}" name="code_name" required>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="store-country" class="col-sm-3 col-form-label"><?php echo trans('label_country'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <?php echo countrySelector(isset($request->post['country']) ? $request->post['country'] : null, 'store-country', 'country'); ?>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="mobile" class="col-sm-3 col-form-label"><?php echo trans('label_mobile'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="mobile" value="<?php echo isset($request->post['mobile']) ? $request->post['mobile'] : null; ?>" name="mobile">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="email" class="col-sm-3 col-form-label"><?php echo trans('label_email'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="email" class="form-control" id="email" value="<?php echo isset($request->post['email']) ? $request->post['email'] : null; ?>" name="email">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="zip_code" class="col-sm-3 col-form-label"><?php echo trans('label_zip_code'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="zip_code" value="<?php echo isset($request->post['zip_code']) ? $request->post['zip_code'] : null; ?>" name="zip_code">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="address" class="col-sm-3 col-form-label"><?php echo trans('label_address'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="address" name="address"><?php echo isset($request->post['address']) ? $request->post['address'] : null; ?></textarea>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="vat_reg_no" class="col-sm-3 col-form-label"><?php echo trans('label_vat_reg_no'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="vat_reg_no" value="<?php echo isset($request->post['vat_reg_no']) ? $request->post['vat_reg_no'] : null; ?>" name="vat_reg_no">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="cashier_id" class="col-sm-3 col-form-label"><?php echo trans('label_cashier_name'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <select id="cashier_id" class="form-select" name="cashier_id"> 
                                    <option value=""><?php echo trans('text_select'); ?></option>
                                    <?php foreach (get_cashiers() as $cashier) : ?>
                                        <option value="<?php echo $cashier['id']; ?>">
                                            <?php echo $cashier['username']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="timezone" class="col-sm-3 col-form-label"><?php echo trans('label_timezone'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <select class="form-select" name="preference[timezone]" id="timezone">
                                    <option selected="selected" disabled hidden value=""><?php echo trans('text_select'); ?></option>
                                    <?php include('../_inc/helper/timezones.php'); ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="invoice_edit_lifespan" class="col-sm-3 col-form-label"><?php echo trans('label_invoice_edit_lifespan'); ?><i class="required">*</i></label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" id="invoice_edit_lifespan" value="10" name="preference[invoice_edit_lifespan]">
                            </div>
                            <div class="col-sm-3">
                                <select class="form-select" name="preference[invoice_edit_lifespan_unit]" id="invoice_edit_lifespan_unit">
                                    <option value="minute" selected>Minuto(s)</option>
                                    <option value="second">Segundo(s)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="invoice_delete_lifespan" class="col-sm-3 col-form-label"><?php echo trans('label_invoice_delete_lifespan'); ?><i class="required">*</i></label>
                            <div class="col-sm-6">
                                <input type="number" class="form-control" id="invoice_delete_lifespan" value="10" name="preference[invoice_delete_lifespan]">
                            </div>
                            <div class="col-sm-3">
                                <select class="form-select" name="preference[invoice_delete_lifespan_unit]" id="invoice_delete_lifespan_unit">
                                    <option value="minute" selected>Minuto(s)</option>
                                    <option value="second">Segundo(s)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="after_sell_page" class="col-sm-3 col-form-label"><?php echo trans('label_after_sell_page'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <select class="form-select" name="preference[after_sell_page]" id="after_sell_page">
                                    <option value="pos" selected>PDV</option>
                                    <option value="invoice">Fatura</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="remote_printing" class="col-sm-3 col-form-label"><?php echo trans('label_pos_printing'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <select class="form-select" name="remote_printing" id="remote_printing">
                                    <option value="0" selected>Web Browser</option>
                                    <option value="1">PHP Server</option>
                                </select>
                                <div class="card card-body bg-light mt-3 p-3">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label for="receipt_printer" class="col-form-label"><?php echo trans('label_receipt_printer'); ?></label>
                                            <select class="form-select" name="receipt_printer" id="receipt_printer">
                                                <option value=""><?php echo trans('text_select');?></option>
                                                <?php foreach (get_printers() as $printer) : ?>
                                                    <option value="<?php echo $printer['printer_id'];?>">
                                                        <?php echo $printer['title'];?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="auto_print_receipt" class="col-form-label"><?php echo trans('label_auto_print_receipt'); ?></label>
                                            <select class="form-select" name="auto_print" id="auto_print_receipt">
                                                <option value="1">Sim</option>
                                                <option value="0" selected>Não</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="tax" class="col-sm-3 col-form-label"><?php echo trans('label_tax'); ?></label>
                            <div class="col-sm-9">
                                <input type="number" class="form-control" id="tax" name="preference[tax]" value="0" onClick="this.select()" onKeyUp="if(this.value<0){this.value='0';}else if(this.value>99){this.value='99';}">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="stock_alert_quantity" class="col-sm-3 col-form-label"><?php echo trans('label_stock_alert_quantity'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="number" class="form-control" id="stock_alert_quantity" name="preference[stock_alert_quantity]" value="10" onClick="this.select()" onKeyUp="if(this.value<0){this.value='0';}">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="datatable_item_limit" class="col-sm-3 col-form-label"><?php echo trans('label_datatable_item_limit'); ?><i class="required">*</i></label>
                            <div class="col-sm-9">
                                <input type="number" class="form-control" id="datatable_item_limit" name="preference[datatable_item_limit]" value="25" onClick="this.select()" onKeyUp="if(this.value<0){this.value='0';}">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="invoice_footer_text" class="col-sm-3 col-form-label"><?php echo trans('label_invoice_footer_text'); ?></label>
                            <div class="col-sm-9">
                                <textarea class="form-control" id="invoice_footer_text" name="preference[invoice_footer_text]">Obrigado por comprar conosco!</textarea>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="sound_effect" class="col-sm-3 col-form-label"><?php echo trans('label_sound_effect'); ?></label>
                            <div class="col-sm-9">
                                <select id="sound_effect" class="form-select" name="sound_effect">
                                    <option value="1" selected><?php echo trans('text_active'); ?></option>
                                    <option value="0"><?php echo trans('text_in_active'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="email-setting" role="tabpanel" aria-labelledby="email-tab">
                        <div class="mb-3 row">
                            <label for="email_from" class="col-sm-3 col-form-label"><?php echo trans('label_email_from'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="email_from" value="<?php echo get_preference('email_from'); ?>" name="preference[email_from]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="email_address" class="col-sm-3 col-form-label"><?php echo trans('label_email_address'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="email_address" value="<?php echo get_preference('email_address'); ?>" name="preference[email_address]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="email_driver" class="col-sm-3 col-form-label"><?php echo trans('label_email_driver'); ?></label>
                            <div class="col-sm-9">
                                <select id="email_driver" class="form-select" name="preference[email_driver]">
                                    <option value="mail_function">PHP mail()</option>
                                    <option value="smtp_server" selected>Servidor SMTP</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="smtp_host" class="col-sm-3 col-form-label"><?php echo trans('label_smtp_host'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="smtp_host" value="<?php echo get_preference('smtp_host'); ?>" name="preference[smtp_host]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="smtp_username" class="col-sm-3 col-form-label"><?php echo trans('label_smtp_username'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="smtp_username" value="<?php echo get_preference('smtp_username'); ?>" name="preference[smtp_username]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="smtp_password" class="col-sm-3 col-form-label"><?php echo trans('label_smtp_password'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="smtp_password" value="<?php echo get_preference('smtp_password'); ?>" name="preference[smtp_password]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="smtp_port" class="col-sm-3 col-form-label"><?php echo trans('label_smtp_port'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="smtp_port" value="<?php echo get_preference('smtp_port'); ?>" name="preference[smtp_port]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="ssl_tls" class="col-sm-3 col-form-label"><?php echo trans('label_ssl_tls'); ?></label>
                            <div class="col-sm-9">
                                <select id="ssl_tls" class="form-select" name="preference[ssl_tls]">
                                    <option value="" selected>None</option>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="currency-setting" role="tabpanel" aria-labelledby="currency-tab">
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Moedas</label>
                            <div class="col-sm-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" onclick="$('input[name*=\'currency\']').prop('checked', this.checked);">
                                    <label class="form-check-label">Selecionar / Desselecionar Tudo</label>
                                </div>
                                <hr>
                                <div class="well-sm overflow-auto" style="height: 200px; border: 1px solid #ddd; padding: 10px;">
                                    <?php foreach(get_currencies() as $the_currency) : ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="currency[]" value="<?php echo $the_currency['currency_id']; ?>">
                                        <label class="form-check-label"><?php echo $the_currency['title']; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="payment-method-setting" role="tabpanel" aria-labelledby="payment-tab">
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Métodos de Pagamento</label>
                            <div class="col-sm-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" onclick="$('input[name*=\'pmethod\']').prop('checked', this.checked);">
                                    <label class="form-check-label">Selecionar / Desselecionar Tudo</label>
                                </div>
                                <hr>
                                <div class="well-sm overflow-auto" style="height: 200px; border: 1px solid #ddd; padding: 10px;">
                                    <?php foreach(get_pmethods() as $the_pmethod) : ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pmethod[]" value="<?php echo $the_pmethod['pmethod_id']; ?>">
                                        <label class="form-check-label"><?php echo $the_pmethod['name']; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="product-setting" role="tabpanel" aria-labelledby="product-tab">
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Produtos</label>
                            <div class="col-sm-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" onclick="$('input[name*=\'product\']').prop('checked', this.checked);">
                                    <label class="form-check-label">Selecionar / Desselecionar Tudo</label>
                                </div>
                                <input ng-model="search_product" class="form-control my-2" type="text" id="search_product" placeholder="<?php echo trans('search'); ?>">
                                <div class="well-sm overflow-auto" style="height: 200px; border: 1px solid #ddd; padding: 10px;" filter-list="search_product">
                                    <?php foreach(get_products() as $the_product) : ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="product[]" value="<?php echo $the_product['p_id']; ?>">
                                        <label class="form-check-label"><?php echo $the_product['p_name']; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="receipt-template" role="tabpanel" aria-labelledby="receipt-tab">
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Templates de Recibo</label>
                            <div class="col-sm-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" onclick="$('input[name*=\'postemplate\']').prop('checked', this.checked);">
                                    <label class="form-check-label">Selecionar / Desselecionar Tudo</label>
                                </div>
                                <input ng-model="search_postemplate" class="form-control my-2" type="text" id="search_postemplate" placeholder="<?php echo trans('search'); ?>">
                                <div class="well-sm overflow-auto" style="height: 200px; border: 1px solid #ddd; padding: 10px;" filter-list="search_postemplate">
                                    <?php $inc=1;foreach(get_postemplates() as $the_template) : ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="postemplate[]" value="<?php echo $the_template['template_id']; ?>" <?php echo $inc==1 ? 'checked' : null;?>>
                                        <label class="form-check-label"><?php echo $the_template['template_name']; ?></label>
                                    </div>
                                    <?php $inc++;endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="printer" role="tabpanel" aria-labelledby="printer-tab">
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Impressoras</label>
                            <div class="col-sm-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" onclick="$('input[name*=\'printer\']').prop('checked', this.checked);">
                                    <label class="form-check-label">Selecionar / Desselecionar Tudo</label>
                                </div>
                                <input ng-model="search_printer" class="form-control my-2" type="text" id="search_printer" placeholder="<?php echo trans('search'); ?>">
                                <div class="well-sm overflow-auto" style="height: 200px; border: 1px solid #ddd; padding: 10px;" filter-list="search_printer">
                                    <?php $inc=1;foreach(get_printers() as $the_printer) : ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="printer[]" value="<?php echo $the_printer['printer_id']; ?>" <?php echo $inc==1 ? 'checked' : null;?>>
                                        <label class="form-check-label"><?php echo $the_printer['title']; ?></label>
                                    </div>
                                    <?php $inc++;endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="ftp-setting" role="tabpanel" aria-labelledby="ftp-tab">
                        <div class="mb-3 row">
                            <label for="ftp_hostname" class="col-sm-3 col-form-label"><?php echo trans('label_ftp_hostname'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="ftp_hostname" value="<?php echo get_preference('ftp_hostname'); ?>" name="preference[ftp_hostname]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="ftp_username" class="col-sm-3 col-form-label"><?php echo trans('label_ftp_username'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="ftp_username" value="<?php echo get_preference('ftp_username'); ?>" name="preference[ftp_username]">
                            </div>
                        </div>
                        <div class="mb-3 row">
                            <label for="ftp_password" class="col-sm-3 col-form-label"><?php echo trans('label_ftp_password'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" id="ftp_password" value="<?php echo get_preference('ftp_password'); ?>" name="preference[ftp_password]">
                            </div>
                        </div>
                    </div>
                    </div> </div> <div class="card-footer text-end">
                <?php if (user_group_id() == 1 || has_permission('access', 'create_store')) : ?>
                    <a id="back-btn" class="btn btn-outline-secondary" href="store.php">
                        <i class="bi bi-arrow-left"></i>
                        <?php echo trans('button_back'); ?>
                    </a>

                    <button id="create-store-btn" class="btn btn-brand" type="button" data-form="#store-form" data-loading-text="Salvando...">
                        <i class="bi bi-save"></i>
                        <?php echo trans('button_save'); ?>
                    </button>
                <?php endif; ?>
            </div>

        </div> </form>
</div>
<?php
// 3. Inclui o Rodapé (Fecha o HTML, carrega os JS)
include '../_inc/template/footer_admin.php'; 
?>

<script src="../assets/itsolution24/angular/controllers/StoreActionController.js"></script>
<script src="../assets/itsolution24/js/upload.js"></script>