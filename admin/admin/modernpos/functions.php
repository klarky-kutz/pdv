<?php
global $Hooks;

/*
 * Auto Callback for Hook
 *
 * Configuration
 *
 * config.php HOOK = 1
 * config.php Log = 1
 */
function hook_action_auto_callback($args) 
{
	$for =  my_str_replace(array('After_','Before_', '_'), array('','',' '), $args['for']);
	unset($args['for']);
	$data = isset($args[0]) ? json_encode($args[0]) : '';
	$msg = date('Y-m-d G:i:s') . ' - ' . $for . ': ' . $data . ' by ' . get_the_user(user_id(),'username');

	$handle = fopen(DIR_STORAGE.'activity-logs/'.date('Y-m-d').'.txt', 'a');
	fwrite($handle, $msg . "\n");
}

/*
Available Hooks and how to use.
*** This is very helpful comment for customer for use of hooks
------------------------------------------------
$Hooks->do_action('Before_Bank_Withdraw', $ref_no);
$Hooks->do_action('After_Bank_Withdraw', array('type' => 'bank_withdraw', 'id' => $ref_no, 'amount' => $withdraw_amount));
$Hooks->do_action('Before_Bank_Deposit', $ref_no);
$Hooks->do_action('After_Bank_Deposit', array('type' => 'bank_deposit'));
$Hooks->do_action('Before_Bank_Transfer', $ref_no);
$Hooks->do_action('After_Bank_Transfer', array('type' => 'bank_transfer', 'id' => $ref_no, 'amount' => $transfer_amount));
$Hooks->do_action('Before_Showing_Bank_Transaction_list');
$Hooks->do_action('After_Showing_Bank_Transaction_list');
$Hooks->do_action('Before_Create_Bank_Account');
$Hooks->do_action('After_Create_Bank_Account', $bank_account);
$Hooks->do_action('Before_Update_Bank_Account', $request);
$Hooks->do_action('After_Update_Bank_Account', $bank_account);
$Hooks->do_action('Before_Delete_Bank_Account', $request);
$Hooks->do_action('After_Delete_Bank_Account', $bank_account);
$Hooks->do_action('Before_Bank_Account_Delete_Form', $account);
$Hooks->do_action('After_Bank_Account_Delete_Form', $account);
$Hooks->do_action('Before_Showing_Bank_Account_List');
$Hooks->do_action('After_Showing_Bank_Account_List');
$Hooks->do_action('Before_Showing_Bank_AccountSheet');
$Hooks->do_action('After_Showing_Bank_AccountSheet');
$Hooks->do_action('Before_Showing_Bank_Transfer_list');
$Hooks->do_action('After_Showing_Bank_Transfer_list');
$Hooks->do_action('Before_Create_Box');
$Hooks->do_action('After_Create_Box', $box);
$Hooks->do_action('Before_Update_Box', $request);
$Hooks->do_action('After_Update_Box', $box);
$Hooks->do_action('Before_Delete_Box', $request);
$Hooks->do_action('After_Delete_Box', $box);
$Hooks->do_action('Before_Box_Delete_Form', $box);
$Hooks->do_action('After_Box_Delete_Form', $box);
$Hooks->do_action('Before_Showing_Box_List');
$Hooks->do_action('After_Showing_Box_List');
$Hooks->do_action('Before_Create_Brand', $request);
$Hooks->do_action('After_Create_Brand', $brand);
$Hooks->do_action('Before_Update_Brand', $request);
$Hooks->do_action('After_Update_Brand', $brand);
$Hooks->do_action('Before_Delete_Brand', $request);
$Hooks->do_action('After_Delete_Brand', $brand);
$Hooks->do_action('Before_Brand_Create_Form');
$Hooks->do_action('After_Brand_Create_Form');
$Hooks->do_action('Before_Brand_Edit_Form', $brand);
$Hooks->do_action('After_Brand_Edit_Form', $brand);
$Hooks->do_action('Before_Brand_Delete_Form');
$Hooks->do_action('Before_Brand_Delete_Form');
$Hooks->do_action('Before_Showing_Brand_List');
$Hooks->do_action('After_Showing_Brand_List');
$Hooks->do_action('Before_Create_Category', $request);
$Hooks->do_action('After_Create_Category', $category);
$Hooks->do_action('Before_Update_Category', $request);
$Hooks->do_action('After_Update_Category', $category);
$Hooks->do_action('Before_Delete_Category', $request);
$Hooks->do_action('After_Delete_Category', $category);
$Hooks->do_action('Before_Category_Delete_Form', $category);
$Hooks->do_action('After_Category_Delete_Form', $category);
$Hooks->do_action('Before_Showing_Category_List');
$Hooks->do_action('After_Showing_Category_List');
$Hooks->do_action('Before_Create_Currency', $request);
$Hooks->do_action('After_Create_Currency', $currency);
$Hooks->do_action('Before_Update_Currency', $request);
$Hooks->do_action('After_Update_Currency', $currency);
$Hooks->do_action('Before_Delete_Currency', $request);
$Hooks->do_action('After_Delete_Currency', $currency);
$Hooks->do_action('Before_Showing_Currency_List');
$Hooks->do_action('After_Showing_Currency_List');
$Hooks->do_action('Before_Create_Customer', $request);
$Hooks->do_action('After_Create_Customer', $customer);
$Hooks->do_action('Before_Update_Customer', $request);
$Hooks->do_action('After_Update_Customer', $customer_id);
$Hooks->do_action('Before_Delete_Customer', $request);
$Hooks->do_action('After_Delete_Customer', $customer);
$Hooks->do_action('Before_Customer_Delete_Form', $customer);
$Hooks->do_action('After_Customer_Delete_Form', $customer);
$Hooks->do_action('Before_Showing_Customer_List');
$Hooks->do_action('After_Showing_Customer_List');
$Hooks->do_action('Before_Showing_Customer_Profile');
$Hooks->do_action('After_Showing_Customer_Profile');
$Hooks->do_action('Before_Add_Expense');
$Hooks->do_action('After_Add_Expense', $id);
$Hooks->do_action('Before_Update_Expense', $request);
$Hooks->do_action('After_Update_Expense', $id);
$Hooks->do_action('Before_Delete_Expense', $request);
$Hooks->do_action('After_Delete_Expense', $id);
$Hooks->do_action('Before_Showing_Expense_List');
$Hooks->do_action('After_Showing_Expense_List');
$Hooks->do_action('Before_Create_Expense_Category');
$Hooks->do_action('After_Create_Expense_Category', $expense_category);
$Hooks->do_action('Before_Update_Expense_Category', $request);
$Hooks->do_action('After_Update_Expense_Category', $expense_category);
$Hooks->do_action('Before_Delete_Expense_Category', $request);
$Hooks->do_action('After_Delete_Expense_Category', $expense_category);
$Hooks->do_action('Before_ExpenseCategory_Delete_Form', $expense_category);
$Hooks->do_action('After_ExpenseCategory_Delete_Form', $expense_category);
$Hooks->do_action('Before_Showing_Expense_Category_List');
$Hooks->do_action('After_Showing_Expense_Category_List');
$Hooks->do_action('Before_Create_Giftcard', $request);
$Hooks->do_action('After_Create_Giftcard', $giftcard);
$Hooks->do_action('Before_Update_Giftcard', $request);
$Hooks->do_action('After_Update_Giftcard', $giftcard);
$Hooks->do_action('Before_Delete_Giftcard', $request);
$Hooks->do_action('After_Delete_Giftcard', $giftcard);
$Hooks->do_action('Before_Giftcard_Topup', $request);
$Hooks->do_action('After_Delete_Giftcard', $id);
$Hooks->do_action('Before_Giftcard_Delete_Form', $giftcard);
$Hooks->do_action('After_Giftcard_Delete_Form', $giftcard);
$Hooks->do_action('Before_Showing_Giftcard_List');
$Hooks->do_action('After_Showing_Giftcard_List');
$Hooks->do_action('Before_Delete_Giftcard_Topup', $giftcard);
$Hooks->do_action('After_Delete_Giftcard_Topup');
$Hooks->do_action('Before_Showing_Giftcard_Topup_List');
$Hooks->do_action('After_Showing_Giftcard_Topup_List');
$Hooks->do_action('Before_Delete_Holding_Order', $request);
$Hooks->do_action('After_Delete_Holding_Order', $ref_no);
$Hooks->do_action('Before_Add_Order_On_Hold', $request);
$Hooks->do_action('After_Add_Order_On_Hold', $id);
$Hooks->do_action('Before_Edit_Hold_order', $request);
$Hooks->do_action('After_Edit_Hold_order', $order);
$Hooks->do_action('Before_Create_Income_Source');
$Hooks->do_action('After_Create_Income_Source', $income_source);
$Hooks->do_action('Before_Update_Income_Source', $request);
$Hooks->do_action('After_Update_Income_Source', $income_source);
$Hooks->do_action('Before_Delete_Income_Source', $request);
$Hooks->do_action('After_Delete_Income_Source', $income_source);
$Hooks->do_action('Before_Showing_Income_Source_List');
$Hooks->do_action('After_Showing_Income_Source_List');
$Hooks->do_action('Before_Installment_payment', $request);
$Hooks->do_action('After_Installment_payment', $id);
$Hooks->do_action('Before_Delete_Installment', $request);
$Hooks->do_action('After_Delete_Installment', $request);
$Hooks->do_action('Before_Showing_Installment_List');
$Hooks->do_action('After_Showing_Installment_List');
$Hooks->do_action('Before_Delete_Invoice', $request);
$Hooks->do_action('Before_Delete_Invoice', $request);
$Hooks->do_action('Before_Update_Invoice_Info', $invoice_id);
$Hooks->do_action('After_Update_Invoice_Info', $invoice_id);
$Hooks->do_action('Before_Showing_Invoice_List');
$Hooks->do_action('After_Showing_Invoice_List');
$Hooks->do_action('Before_Take_Loan', $request);
$Hooks->do_action('After_Take_Loan', $loan);
$Hooks->do_action('Before_Update_Loan', $request);
$Hooks->do_action('After_Update_Loan', $loan);
$Hooks->do_action('Before_Delete_Loan', $request);
$Hooks->do_action('After_Delete_Loan', $loan);
$Hooks->do_action('Before_Loan_Pay');
$Hooks->do_action('After_Loan_Paid', $loan);
$Hooks->do_action('Before_Loan_Delete_Form', $loan);
$Hooks->do_action('After_Loan_Delete_Form', $loan);
$Hooks->do_action('Before_Showing_Loan_List');
$Hooks->do_action('After_Showing_Loan_List');
$Hooks->do_action('Before_Showing_Loan_Payment_List');
$Hooks->do_action('After_Showing_Loan_Payment_List');
$Hooks->do_action('Before_Payment', $request);
$Hooks->do_action('After_Payment', $request);
$Hooks->do_action('Before_Place_POS_Order', $request);
$Hooks->do_action('Before_Place_POS_Order', $invoice_info);
$Hooks->do_action('Before_Create_PMethod', $request);
$Hooks->do_action('After_Create_PMethod', $pmethod);
$Hooks->do_action('Before_Update_PMethod', $request);
$Hooks->do_action('After_Update_PMethod', $pmethod);
$Hooks->do_action('Before_Delete_PMethod', $request);
$Hooks->do_action('After_Delete_PMethod', $pmethod);
$Hooks->do_action('Before_PMethod_Delete_Form', $pmethod);
$Hooks->do_action('After_PMethod_Delete_Form', $pmethod);
$Hooks->do_action('Before_Showing_PMethod_List');
$Hooks->do_action('After_Showing_PMethod_List');
$Hooks->do_action('Before_Add_Printer');
$Hooks->do_action('After_Add_Printer', $printer);
$Hooks->do_action('Before_Update_Printer', $request);
$Hooks->do_action('After_Update_Printer', $printer);
$Hooks->do_action('Before_Delete_printer', $request);
$Hooks->do_action('After_Delete_printer', $printer);
$Hooks->do_action('Before_Showing_printer_List');
$Hooks->do_action('After_Showing_printer_List');
$Hooks->do_action('Before_Create_Product', $request);
$Hooks->do_action('After_Create_Product', $product);
$Hooks->do_action('Before_Update_Product', $p_id);
$Hooks->do_action('After_Update_Product', $p_id);
$Hooks->do_action('Before_Delete_Product', $request);
$Hooks->do_action('After_Delete_Product', $product);
$Hooks->do_action('Before_Showing_Product_List');
$Hooks->do_action('After_Showing_Product_List');
$Hooks->do_action('After_Product_Bulk_Action', $action);
$Hooks->do_action('After_Product_Bulk_Action', $action);
$Hooks->do_action('Before_Create_Purchase_Invoice', $request);
$Hooks->do_action('After_Create_Purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_Delete_Purchase_Invoice', $request);
$Hooks->do_action('After_Delete_Purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_Update_Purchase_Invoice', $invoice_id);
$Hooks->do_action('After_Update_Purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_Showing_Purchase_Invoice_List');
$Hooks->do_action('After_Showing_Purchase_Invoice_List');
$Hooks->do_action('Before_Purchase_Payment', $request);
$Hooks->do_action('After_Purchase_Payment', $request);
$Hooks->do_action('Before_Purchase_Return', $request);
$Hooks->do_action('After_Purchase_Return', $id);
$Hooks->do_action('Before_Showing_Purchase_Return_List');
$Hooks->do_action('Before_Showing_Purchase_Return_List');
$Hooks->do_action('Before_Showing_Purchase_Transactions_List');
$Hooks->do_action('After_Showing_Purchase_Transaction_List');
$Hooks->do_action('Before_Create_Quotation', $request);
$Hooks->do_action('After_Create_Quotation', $quotation_info);
$Hooks->do_action('Before_Update_Quotation', $request);
$Hooks->do_action('After_Update_Quotation', $quotation_info);
$Hooks->do_action('Before_Delete_Quotation', $request);
$Hooks->do_action('After_Delete_Quotation', $request);
$Hooks->do_action('Before_Showing_Quotation_List');
$Hooks->do_action('After_Showing_Quotation_List');
$Hooks->do_action('Before_Showing_Collection_Report');
$Hooks->do_action('Before_Showing_Loss_List');
$Hooks->do_action('After_Showing_Loss_List');
$Hooks->do_action('Before_Showing_purchase_Tax_Report');
$Hooks->do_action('After_Showing_purchase_Tax_Report');
$Hooks->do_action('Before_Database_Restore');
$Hooks->do_action('After_Database_Restore');
$Hooks->do_action('Before_Sell_Return', $request);
$Hooks->do_action('After_Sell_Return', $request);
$Hooks->do_action('Before_Showing_Sell_Return_List');
$Hooks->do_action('After_Showing_Sell_Return_List');
$Hooks->do_action('Before_Showing_Transactions_List');
$Hooks->do_action('After_Showing_Transactions_List');
$Hooks->do_action('Before_Send_Email', $request);
$Hooks->do_action('After_Send_Email', $request);
$Hooks->do_action('Before_Showinig_SMS_Report');
$Hooks->do_action('After_Showinig_SMS_Report');
$Hooks->do_action('Before_Update_SMS_Setting', $request);
$Hooks->do_action('After_Update_SMS_Setting', $request);
$Hooks->do_action('Before_Create_Store', $request);
$Hooks->do_action('After_Create_Store', $store_id);
$Hooks->do_action('Before_Update_Store', $request);
$Hooks->do_action('After_Update_Store', $the_store);
$Hooks->do_action('Before_Delete_Store', $request);
$Hooks->do_action('After_Delete_Store', $the_store);
$Hooks->do_action('Before_Store_Delete_Form', $store_info);
$Hooks->do_action('After_Store_Delete_Form', $store_info);
$Hooks->do_action('Before_Showing_Store_List');
$Hooks->do_action('After_Showing_Store_List');
$Hooks->do_action('Before_Create_Supplier', $request);
$Hooks->do_action('After_Create_Supplier', $supplier);
$Hooks->do_action('Before_Update_Supplier', $request);
$Hooks->do_action('After_Update_Supplier', $supplier);
$Hooks->do_action('Before_Delete_Supplier', $request);
$Hooks->do_action('After_Delete_Supplier', $supplier);
$Hooks->do_action('Before_Showing_Supplier_List');
$Hooks->do_action('After_Showing_Supplier_List');
$Hooks->do_action('Before_Showing_Supplier_Profile');
$Hooks->do_action('After_Showing_Supplier_Profile');
$Hooks->do_action('Before_Create_Taxrate', $request);
$Hooks->do_action('After_Create_Taxrate', $taxrate);
$Hooks->do_action('Before_Update_Taxrate', $request);
$Hooks->do_action('After_Update_Taxrate', $taxrate);
$Hooks->do_action('Before_Delete_Taxrate', $request);
$Hooks->do_action('After_Delete_Taxrate', $taxrate);
$Hooks->do_action('Before_Showing_Taxrate_List');
$Hooks->do_action('After_Showing_Taxrate_List');
$Hooks->do_action('Before_Stock_Transfer', $request);
$Hooks->do_action('After_Stock_Transfer', $invoice_id);
$Hooks->do_action('Before_Update_Stock_Transfer', $id);
$Hooks->do_action('After_Update_Stock_Transfer', $id);
$Hooks->do_action('Before_Showing_Transfer_List');
$Hooks->do_action('Aftere_Showing_Transfer_List');
$Hooks->do_action('Before_Create_Unit', $request);
$Hooks->do_action('After_Create_Unit', $unit);
$Hooks->do_action('Before_Update_Unit', $request);
$Hooks->do_action('After_Update_Unit', $unit);
$Hooks->do_action('Before_Delete_Unit', $request);
$Hooks->do_action('After_Delete_Unit', $unit);
$Hooks->do_action('Before_Showing_Unit_List');
$Hooks->do_action('After_Showing_Unit_List');
$Hooks->do_action('Before_Upload_Favicon', $request);
$Hooks->do_action('After_Upload_Favicon', $request);
$Hooks->do_action('Before_Upload_Logo', $request);
$Hooks->do_action('After_Upload_Logo', $request);
$Hooks->do_action('Before_Create_User', $request);
$Hooks->do_action('After_Create_User', $the_user);
$Hooks->do_action('Before_Update_User', $request);
$Hooks->do_action('After_Update_User', $the_user);
$Hooks->do_action('Before_Delete_User', $request);
$Hooks->do_action('After_Delete_User', $the_user);
$Hooks->do_action('Before_Showing_User_List');
$Hooks->do_action('After_Showing_User_List');
$Hooks->do_action('Before_Create_Usergroup', $request);
$Hooks->do_action('After_Create_Usergroup', $usergroup);
$Hooks->do_action('Before_Update_Usergroup', $request);
$Hooks->do_action('After_Update_Usergroup', $usergroup);
$Hooks->do_action('Before_Delete_Usergroup', $request);
$Hooks->do_action('After_Delete_Usergroup', $usergroup);
$Hooks->do_action('Before_Showing_Usergroup_List');
$Hooks->do_action('After_Showing_Usergroup_List');
$Hooks->do_action('After_Hooks_Setup', $Hooks);
$Hooks->do_action('Before_Showing_Barcode_List');
$Hooks->do_action('After_Showing_Barcode_List');
$Hooks->do_action('Before_Create_purchase_Invoice', $request);
$Hooks->do_action('After_Create_purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_Delete_purchase_Invoice', $request);
$Hooks->do_action('After_Delete_purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_purchase_Invoice_Create_Form', $sup_id);
$Hooks->do_action('After_purchase_Invoice_Create_Form', $sup_id);
$Hooks->do_action('Before_View_purchase_Invoice', $invoice_id);
$Hooks->do_action('After_View_purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_View_Quotation', $reference_no);
$Hooks->do_action('After_View_Quotation', $reference_no);
$Hooks->do_action('Before_Showing_Quotation_List');
$Hooks->do_action('After_Showing_Quotation_List');
$Hooks->do_action('Before_Create_purchase_Invoice', $request);
$Hooks->do_action('After_Create_purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_Delete_purchase_Invoice', $request);
$Hooks->do_action('After_Delete_purchase_Invoice', $invoice_id);
$Hooks->do_action('Before_purchase_Invoice_Create_Form', $sup_id);
$Hooks->do_action('After_purchase_Invoice_Create_Form', $sup_id);
$Hooks->do_action('Before_View_purchase_Invoice', $invoice_id);
$Hooks->do_action('After_View_purchase_Invoice', $invoice_id);
*/

// ============================================
// ANALYTICS FUNCTIONS
// ============================================

/**
 * Get login logs
 * @param int $limit
 * @return array
 */
function get_login_logs($limit = 50) {
    $statement = db()->prepare("SELECT `username`, `ip`, `status`, `created_at` FROM `login_logs` ORDER BY `id` DESC LIMIT 0, :limit");
    $statement->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get sales by hour for analytics
 * @param string $from
 * @param string $to
 * @return array
 */
function get_sales_by_hour($from = null, $to = null) {
    $from = $from ?? date('Y-m-d');
    $to = $to ?? date('Y-m-d');
    
    $statement = db()->prepare("
        SELECT 
            HOUR(si.created_at) as hour,
            COUNT(*) as total_sales,
            SUM(sp.payable_amount) as total_amount
        FROM `selling_info` si
        LEFT JOIN `selling_price` sp ON si.invoice_id = sp.invoice_id
        WHERE DATE(si.created_at) BETWEEN :from AND :to
        AND si.store_id = :store_id
        GROUP BY HOUR(si.created_at)
        ORDER BY hour
    ");
    $statement->execute([
        ':from' => $from,
        ':to' => $to,
        ':store_id' => store_id()
    ]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get payment methods distribution
 * @param string $from
 * @param string $to
 * @return array
 */
function get_payment_methods_distribution($from = null, $to = null) {
    $from = $from ?? date('Y-m-01');
    $to = $to ?? date('Y-m-d');
    
    $statement = db()->prepare("
        SELECT 
            pm.name as method_name,
            COUNT(p.id) as total_transactions,
            SUM(p.amount) as total_amount
        FROM `payments` p
        LEFT JOIN `pmethods` pm ON p.pmethod_id = pm.pmethod_id
        WHERE DATE(p.created_at) BETWEEN :from AND :to
        AND p.store_id = :store_id
        AND p.type = 'sell'
        GROUP BY p.pmethod_id
        ORDER BY total_amount DESC
    ");
    $statement->execute([
        ':from' => $from,
        ':to' => $to,
        ':store_id' => store_id()
    ]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get monthly comparison (current vs previous)
 * @return array
 */
function get_monthly_comparison() {
    $current_month_start = date('Y-m-01');
    $current_month_end = date('Y-m-d');
    $prev_month_start = date('Y-m-01', strtotime('-1 month'));
    $prev_month_end = date('Y-m-t', strtotime('-1 month'));
    
    return [
        'current' => [
            'sales' => selling_price($current_month_start, $current_month_end),
            'expenses' => get_total_expense($current_month_start, $current_month_end),
            'invoices' => get_total_invoices($current_month_start, $current_month_end),
            'label' => date('F Y')
        ],
        'previous' => [
            'sales' => selling_price($prev_month_start, $prev_month_end),
            'expenses' => get_total_expense($prev_month_start, $prev_month_end),
            'invoices' => get_total_invoices($prev_month_start, $prev_month_end),
            'label' => date('F Y', strtotime('-1 month'))
        ]
    ];
}

/**
 * Get total invoices count
 * @param string $from
 * @param string $to
 * @return int
 */
function get_total_invoices($from, $to) {
    $statement = db()->prepare("
        SELECT COUNT(*) as total
        FROM `selling_info` 
        WHERE DATE(created_at) BETWEEN :from AND :to
        AND store_id = :store_id
    ");
    $statement->execute([
        ':from' => $from,
        ':to' => $to,
        ':store_id' => store_id()
    ]);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Get average ticket value
 * @param string $from
 * @param string $to
 * @return float
 */
function get_average_ticket($from = null, $to = null) {
    $from = $from ?? date('Y-m-d');
    $to = $to ?? date('Y-m-d');
    
    $statement = db()->prepare("
        SELECT AVG(sp.payable_amount) as avg_ticket
        FROM `selling_info` si
        LEFT JOIN `selling_price` sp ON si.invoice_id = sp.invoice_id
        WHERE DATE(si.created_at) BETWEEN :from AND :to
        AND si.store_id = :store_id
    ");
    $statement->execute([
        ':from' => $from,
        ':to' => $to,
        ':store_id' => store_id()
    ]);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result['avg_ticket'] ?? 0;
}

/**
 * Get new customers count
 * @param string $from
 * @param string $to
 * @return int
 */
function get_new_customers_count($from = null, $to = null) {
    $from = $from ?? date('Y-m-01');
    $to = $to ?? date('Y-m-d');
    
    $statement = db()->prepare("
        SELECT COUNT(*) as total
        FROM `customers` 
        WHERE DATE(created_at) BETWEEN :from AND :to
    ");
    $statement->execute([
        ':from' => $from,
        ':to' => $to
    ]);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

/**
 * Get low rotation products
 * @param int $days
 * @param int $limit
 * @return array
 */
function get_low_rotation_products($days = 30, $limit = 10) {
    $date_limit = date('Y-m-d', strtotime("-{$days} days"));
    
    $statement = db()->prepare("
        SELECT 
            p.p_id,
            p.p_name,
            p.p_code,
            COALESCE(SUM(si.item_quantity), 0) as qty_sold,
            pq.quantity_in_stock as stock
        FROM `products` p
        LEFT JOIN `product_to_store` pq ON p.p_id = pq.product_id AND pq.store_id = :store_id
        LEFT JOIN `selling_item` si ON p.p_id = si.item_id 
            AND si.created_at >= :date_limit
            AND si.store_id = :store_id2
        WHERE p.p_type != 'service'
        AND pq.quantity_in_stock > 0
        GROUP BY p.p_id
        HAVING qty_sold < 3
        ORDER BY qty_sold ASC, stock DESC
        LIMIT :limit
    ");
    $statement->bindValue(':store_id', store_id(), PDO::PARAM_INT);
    $statement->bindValue(':store_id2', store_id(), PDO::PARAM_INT);
    $statement->bindValue(':date_limit', $date_limit);
    $statement->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate profit for period
 * @param string $from
 * @param string $to
 * @return float
 */
function get_period_profit($from = null, $to = null) {
    $from = $from ?? date('Y-m-d');
    $to = $to ?? date('Y-m-d');
    
    $income = get_total_substract_income($from, $to);
    $expense = get_total_expense($from, $to);
    
    return $income - $expense;
}
