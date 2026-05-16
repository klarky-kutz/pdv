<?php
// =======================================================
// ARQUIVO: _inc/helper/report.php (COMPLETO E CORRIGIDO)
// =======================================================

function selling_price($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getSellingPrice($from, $to);
}

function sell_purchase_price($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchasePriceOfSell($from, $to);
}

function discount_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getDiscountAmount($from, $to);
}

function purchase_discount_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseDiscountAmount($from, $to);
}

function shipping_charge($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getShippingCharge($from, $to);
}

function purchase_shipping_charge($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseShippingCharge($from, $to);
}

function others_charge($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getOthersCharge($from, $to);
}

function purchase_others_charge($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseOthersCharge($from, $to);
}

function purchase_price($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchasePrice($from, $to);
}

function selling_return_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getSellingReturnAmount($from, $to);
}

function tax_return_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTaxReturnAmount($from, $to);
}

function gst_return_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getGSTReturnAmount($from, $to);
}

function purchase_return_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseReturnAmount($from, $to);
}

function purchase_tax_return_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseTaxReturnAmount($from, $to);
}

function purchase_gst_return_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseGSTReturnAmount($from, $to);
}

function due_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getDueAmount($from, $to);
}

function purchase_due_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getpurchaseDueAmount($from, $to);
}

function due_collection_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getDueCollectionAmount($from, $to);
}

function anotherday_due_collection_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getAnothrDayDueCollectionAmount($from, $to);
}

function anotherday_due_paid_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getAnothrDayDuePaidAmount($from, $to);
}

function purchase_due_paid_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getpurchaseDuePaidAmount($from, $to);
}

function received_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getReceivedAmount($from, $to);
}

function sell_received_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getSellReceivedAmount($from, $to);
}

function purchase_total_paid($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getPurchaseTotalPaidAmount($from, $to);
}

function sourcewise_profit_amount($source_id,$from=null, $to=null) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getSourcewiseProfitAmount($source_id,$from, $to);
}

function get_tax($type, $from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTax($type, $from, $to);
}

function get_in_or_exclusive_tax($type, $from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getInOrExclusiveTax($type, $from, $to);
}

function get_purchase_tax($type, $from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getpurchaseTax($type, $from, $to);
}

function get_in_or_exclusive_purchase_tax($type, $from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getInOrExclusivepurchaseTax($type, $from, $to);
}

function selling_price_daywise($year, $month = null, $day = null) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getSellingPriceDaywise($year, $month, $day);
}

function received_amount_daywise($year, $month = null, $day = null) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getReceivedAmountDaywise($year, $month, $day);
}

function profit_amount_daywise($year, $month = null, $day = null) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getProfitAmountDaywise($year, $month, $day);
}

function tax_amount_daywise($year, $month = null, $day = null) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTaxAmountDaywise($year, $month, $day);
}

function expense_amount($from, $to) 
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getExpenseAmount($from, $to);
}

function purchase_in_year($year) 
{
	$totalPurchase = [];
	for ($i=1; $i < 12; $i++) { 
		$totalPurchase[$i] = purchase_price($year, $i);
	}
	return $totalPurchase;
}

function total_out_of_stock()
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->totalOutOfStock();
}

function total_expired()
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->totalExpired();
}

function get_balance($customer_id, $index = null) 
{	
	$customer_model = registry()->get('loader')->model('customer');
	return $customer_model->getBalance($customer_id, $index);
}

function get_quantity_in_stock($p_id, $store_id = null)
{
	$store_id = $store_id ? $store_id : store_id();
	$product_model = registry()->get('loader')->model('product');
	return $product_model->getQtyInStock($p_id, $store_id);
}

function top_products($from, $to, $limit = 3)
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTopProducts($from, $to, $limit);
}

function top_customers($from, $to, $limit = 3)
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTopCustomers($from, $to, $limit);
}

function top_suppliers($from, $to, $limit = 3)
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTopSuppliers($from, $to, $limit);
}

function top_brands($from, $to, $limit = 3)
{	
	$report_model = registry()->get('loader')->model('report');
	return $report_model->getTopBrands($from, $to, $limit);
}


/* ============================================== */
/* NOVAS FUNÇÕES PARA O PAINEL DE ENTRADA (STORE_SELECT) */
/* (CORRIGIDAS COM O SQL CORRETO E SEM O <?php extra) */
/* ============================================== */

/**
 * Pega o VALOR TOTAL (R$) vendido hoje para UMA loja específica.
 */
function get_total_sales_today_by_store($store_id) {
    
    // CORREÇÃO: Faz um JOIN para pegar o 'paid_amount' da 'selling_price'
    // e a data/status da 'selling_info' (baseado no seu modernpos (2).sql)
    $sql = "SELECT SUM(T1.`paid_amount`) as `total` 
            FROM `selling_price` T1
            LEFT JOIN `selling_info` T2 ON (T1.`invoice_id` = T2.`invoice_id`)
            WHERE T2.`store_id` = ? AND T2.`status` = ? AND DATE(T2.`created_at`) = CURDATE()";
            
    $statement = db()->prepare($sql);
    $statement->execute(array((int)$store_id, 1)); // 1 = status 'paid'
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ? (float)$row['total'] : 0;
}

/**
 * Pega a CONTAGEM TOTAL (número) de vendas hoje para UMA loja específica.
 */
function get_total_invoice_today_by_store($store_id) {
    
    // Pega da 'selling_info' (onde o status e data estão)
    $sql = "SELECT COUNT(`invoice_id`) as `total` FROM `selling_info` 
            WHERE `store_id` = ? AND `status` = ? AND DATE(`created_at`) = CURDATE()";
            
    $statement = db()->prepare($sql);
    $statement->execute(array((int)$store_id, 1));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ? (int)$row['total'] : 0;
}

/**
 * Pega o VALOR TOTAL (R$) vendido ONTEM para UMA loja específica.
 */
function get_total_sales_yesterday_by_store($store_id) {
    // SQL corrigido com JOIN, mas agora para 'CURDATE() - INTERVAL 1 DAY'
    $sql = "SELECT SUM(T1.`paid_amount`) as `total` 
            FROM `selling_price` T1
            LEFT JOIN `selling_info` T2 ON (T1.`invoice_id` = T2.`invoice_id`)
            WHERE T2.`store_id` = ? AND T2.`status` = ? AND DATE(T2.`created_at`) = CURDATE() - INTERVAL 1 DAY";
            
    $statement = db()->prepare($sql);
    $statement->execute(array((int)$store_id, 1)); // 1 = status 'paid'
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ? (float)$row['total'] : 0;
}

/**
 * Pega a CONTAGEM TOTAL (número) de vendas ONTEM para UMA loja específica.
 */
function get_total_invoice_yesterday_by_store($store_id) {
    $sql = "SELECT COUNT(`invoice_id`) as `total` FROM `selling_info` 
            WHERE `store_id` = ? AND `status` = ? AND DATE(`created_at`) = CURDATE() - INTERVAL 1 DAY";
            
    $statement = db()->prepare($sql);
    $statement->execute(array((int)$store_id, 1));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row['total'] ? (int)$row['total'] : 0;
}


/**
 * Pega o VALOR TOTAL (R$) vendido hoje de TODAS as lojas.
 */
function get_total_sales_all_stores_today() {
    $total = 0;
    $stores = get_stores(); // Pega todas as lojas do usuário
    if (!empty($stores)) {
        foreach ($stores as $store) {
            // Chama a nova função corrigida
            $total += get_total_sales_today_by_store($store['store_id']);
        }
    }
    return (float)$total;
}

/**
 * Pega a CONTAGEM TOTAL (número) de vendas hoje de TODAS as lojas.
 */
function get_total_invoice_all_stores_today() {
    $total = 0;
    $stores = get_stores();
    if (!empty($stores)) {
        foreach ($stores as $store) {
            // Chama a nova função corrigida
            $total += get_total_invoice_today_by_store($store['store_id']);
        }
    }
    return (int)$total;
}

/**
 * Pega o VALOR TOTAL (R$) vendido ONTEM de TODAS as lojas.
 */
function get_total_sales_all_stores_yesterday() {
    $total = 0;
    $stores = get_stores();
    if (!empty($stores)) {
        foreach ($stores as $store) {
            $total += get_total_sales_yesterday_by_store($store['store_id']);
        }
    }
    return (float)$total;
}

/**
 * Pega a CONTAGEM TOTAL (número) de vendas ONTEM de TODAS as lojas.
 */
function get_total_invoice_all_stores_yesterday() {
    $total = 0;
    $stores = get_stores();
    if (!empty($stores)) {
        foreach ($stores as $store) {
            $total += get_total_invoice_yesterday_by_store($store['store_id']);
        }
    }
    return (int)$total;
}


/**
 * Pega a HORA da última venda de hoje para UMA loja específica.
 */
function get_last_sale_time_today_by_store($store_id) {
    $sql = "SELECT `created_at` FROM `selling_info` 
            WHERE `store_id` = ? AND `status` = ? AND DATE(`created_at`) = CURDATE()
            ORDER BY `created_at` DESC 
            LIMIT 1"; // Pega só o mais recente
            
    $statement = db()->prepare($sql);
    $statement->execute(array((int)$store_id, 1));
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['created_at']) {
        return date('H:i', strtotime($row['created_at']));
    }
    return 'N/A'; // Retorna "Não Aplicável" se não houver vendas
}

/**
 * Wrapper para o painel de conta: consolida resumo por meio de pagamento
 * para uma lista de lojas (multi-loja / SaaS).
 */
function account_payment_summary($from, $to, $store_ids = array())
{
    $report_model = registry()->get('loader')->model('report');
    return $report_model->getPaymentSummaryByStores($from, $to, $store_ids);
}
