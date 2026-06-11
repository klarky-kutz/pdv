<?php 
ob_start();
session_start();
include ("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
    redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_product')) {
    redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// Get export format
$format = isset($request->get['format']) ? $request->get['format'] : 'xls';
$sup_id = isset($request->get['sup_id']) ? (int)$request->get['sup_id'] : null;

// Fetch all products with full data
$sql = "SELECT 
    p.*,
    p2s.purchase_price,
    p2s.sell_price,
    p2s.alert_quantity,
    p2s.sup_id,
    p2s.brand_id,
    p2s.box_id,
    p2s.taxrate_id,
    p2s.tax_method,
    p2s.status as product_status,
    c.category_slug,
    c.category_name,
    u.code_name as unit_code,
    u.unit_name,
    s.code_name as sup_code,
    s.sup_name,
    b.code_name as brand_code,
    b.brand_name,
    bx.code_name as box_code,
    bx.box_name,
    t.code_name as taxrate_code,
    t.taxrate_name,
    st.code_name as store_code,
    st.name as store_name
FROM products p
INNER JOIN product_to_store p2s ON p.p_id = p2s.product_id AND p2s.store_id = " . store_id() . "
LEFT JOIN categorys c ON p.category_id = c.category_id
LEFT JOIN units u ON p.unit_id = u.unit_id
LEFT JOIN suppliers s ON p2s.sup_id = s.sup_id
LEFT JOIN brands b ON p2s.brand_id = b.brand_id
LEFT JOIN boxes bx ON p2s.box_id = bx.box_id
LEFT JOIN taxrates t ON p2s.taxrate_id = t.taxrate_id
LEFT JOIN stores st ON p2s.store_id = st.store_id
WHERE p2s.store_id = " . store_id();

if ($sup_id) {
    $sql .= " AND p2s.sup_id = " . $sup_id;
}

$sql .= " ORDER BY p.p_name ASC";

$statement = $db->prepare($sql);
$statement->execute();
$products = $statement->fetchAll(PDO::FETCH_ASSOC);

// Get product images
function getProductImages($product_id) {
    global $db;
    $statement = $db->prepare("SELECT url FROM product_images WHERE product_id = ?");
    $statement->execute(array($product_id));
    $images = $statement->fetchAll(PDO::FETCH_COLUMN);
    return implode('|', $images);
}

// Export based on format
switch ($format) {
    case 'xls':
        exportToExcel($products);
        break;
    case 'csv':
        exportToCSV($products);
        break;
    case 'pdf':
        exportToPDF($products);
        break;
    default:
        exportToExcel($products);
}

function exportToExcel($products) {
    $filename = 'produtos_export_' . date('Y-m-d_His') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]>';
    echo '<xml>';
    echo '<x:ExcelWorkbook>';
    echo '<x:ExcelWorksheets>';
    echo '<x:ExcelWorksheet>';
    echo '<x:Name>Product</x:Name>';
    echo '<x:WorksheetOptions>';
    echo '<x:Selected/>';
    echo '<x:FreezePanes/>';
    echo '<x:FrozenNoSplit/>';
    echo '<x:SplitHorizontal>1</x:SplitHorizontal>';
    echo '<x:TopRowBottomPane>1</x:TopRowBottomPane>';
    echo '</x:WorksheetOptions>';
    echo '</x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets>';
    echo '</x:ExcelWorkbook>';
    echo '</xml>';
    echo '<![endif]-->';
    echo '<style>';
    echo 'table { border-collapse: collapse; }';
    echo 'th { background-color: #4CAF50; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; text-align: center; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo '.number { mso-number-format:"\@"; text-align: right; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<table>';
    
    // Header row - matching import format
    echo '<tr>';
    echo '<th>Nº</th>';
    echo '<th>ProductName</th>';
    echo '<th>ProductType</th>';
    echo '<th>Code</th>';
    echo '<th>HSNCode</th>';
    echo '<th>BarcodeSymbology</th>';
    echo '<th>StoreCode</th>';
    echo '<th>CategorySlug</th>';
    echo '<th>UnitCode</th>';
    echo '<th>TaxrateCode</th>';
    echo '<th>TaxMethod</th>';
    echo '<th>SupplierCode</th>';
    echo '<th>BrandCode</th>';
    echo '<th>BoxCode</th>';
    echo '<th>AlertQuantity</th>';
    echo '<th>CostPrice</th>';
    echo '<th>SellPrice</th>';
    echo '<th>Description</th>';
    echo '<th>Status</th>';
    echo '<th>Thumbnail</th>';
    echo '<th>Images</th>';
    echo '</tr>';
    
    // Data rows
    $row_num = 1;
    foreach ($products as $product) {
        $images = getProductImages($product['p_id']);
        
        echo '<tr>';
        echo '<td>' . $row_num . '</td>';
        echo '<td>' . htmlspecialchars($product['p_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['p_type'] ?? 'standard') . '</td>';
        echo '<td class="number">' . htmlspecialchars($product['p_code'] ?? '') . '</td>';
        echo '<td class="number">' . htmlspecialchars($product['hsn_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['barcode_symbology'] ?? 'code128') . '</td>';
        echo '<td>' . htmlspecialchars($product['store_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['category_slug'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['unit_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['taxrate_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['tax_method'] ?? 'inclusive') . '</td>';
        echo '<td>' . htmlspecialchars($product['sup_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['brand_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['box_code'] ?? '') . '</td>';
        echo '<td class="number">' . ($product['alert_quantity'] ?? 10) . '</td>';
        echo '<td class="number">' . number_format(($product['purchase_price'] ?? 0), 2, '.', '') . '</td>';
        echo '<td class="number">' . number_format(($product['sell_price'] ?? 0), 2, '.', '') . '</td>';
        echo '<td>' . htmlspecialchars(strip_tags($product['description'] ?? '')) . '</td>';
        echo '<td>' . ($product['product_status'] ?? 1) . '</td>';
        echo '<td>' . htmlspecialchars($product['p_image'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($images) . '</td>';
        echo '</tr>';
        
        $row_num++;
    }
    
    echo '</table>';
    
    // Add information footer
    echo '<br><br>';
    echo '<table>';
    echo '<tr><td colspan="5" style="background:#f0f0f0;padding:15px;font-weight:bold;">Informações da Exportação</td></tr>';
    echo '<tr><td colspan="5">Data de Exportação: ' . date('d/m/Y H:i:s') . '</td></tr>';
    echo '<tr><td colspan="5">Total de Produtos: ' . count($products) . '</td></tr>';
    echo '<tr><td colspan="5">Loja: ' . store('name') . '</td></tr>';
    echo '<tr><td colspan="5" style="color:#666;font-size:11px;padding-top:10px;">Este arquivo é compatível com o sistema de importação. Para reimportar, remova a linha de cabeçalho "Nº" antes de fazer upload.</td></tr>';
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

function exportToCSV($products) {
    $filename = 'produtos_export_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // BOM for UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, array(
        'Nº',
        'ProductName',
        'ProductType',
        'Code',
        'HSNCode',
        'BarcodeSymbology',
        'StoreCode',
        'CategorySlug',
        'UnitCode',
        'TaxrateCode',
        'TaxMethod',
        'SupplierCode',
        'BrandCode',
        'BoxCode',
        'AlertQuantity',
        'CostPrice',
        'SellPrice',
        'Description',
        'Status',
        'Thumbnail',
        'Images'
    ), ';');
    
    // Data rows
    $row_num = 1;
    foreach ($products as $product) {
        $images = getProductImages($product['p_id']);
        
        fputcsv($output, array(
            $row_num,
            $product['p_name'] ?? '',
            $product['p_type'] ?? 'standard',
            $product['p_code'] ?? '',
            $product['hsn_code'] ?? '',
            $product['barcode_symbology'] ?? 'code128',
            $product['store_code'] ?? '',
            $product['category_slug'] ?? '',
            $product['unit_code'] ?? '',
            $product['taxrate_code'] ?? '',
            $product['tax_method'] ?? 'inclusive',
            $product['sup_code'] ?? '',
            $product['brand_code'] ?? '',
            $product['box_code'] ?? '',
            $product['alert_quantity'] ?? 10,
            number_format(($product['purchase_price'] ?? 0), 2, '.', ''),
            number_format(($product['sell_price'] ?? 0), 2, '.', ''),
            strip_tags($product['description'] ?? ''),
            $product['product_status'] ?? 1,
            $product['p_image'] ?? '',
            $images
        ), ';');
        
        $row_num++;
    }
    
    fclose($output);
    exit;
}

function exportToPDF($products) {
    // For PDF, we'll create a simple HTML that can be printed
    $filename = 'produtos_export_' . date('Y-m-d_His') . '.pdf';
    
    // Include TCPDF or use a simpler approach
    // For simplicity, redirect to a print-friendly HTML page
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Exportação de Produtos - ' . store('name') . '</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }';
    echo 'h1 { color: #333; font-size: 18px; margin-bottom: 5px; }';
    echo 'h2 { color: #666; font-size: 12px; margin-bottom: 20px; font-weight: normal; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 10px; }';
    echo 'th { background: #4CAF50; color: white; padding: 8px 5px; text-align: left; font-size: 9px; border: 1px solid #ddd; }';
    echo 'td { padding: 6px 5px; border: 1px solid #ddd; font-size: 9px; }';
    echo 'tr:nth-child(even) { background: #f9f9f9; }';
    echo '.text-right { text-align: right; }';
    echo '.text-center { text-align: center; }';
    echo '.footer { margin-top: 20px; font-size: 10px; color: #666; }';
    echo '@media print { .no-print { display: none; } }';
    echo '.btn-print { background: #4CAF50; color: white; border: none; padding: 10px 20px; font-size: 14px; cursor: pointer; margin-bottom: 20px; border-radius: 5px; }';
    echo '.btn-print:hover { background: #45a049; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="no-print">';
    echo '<button class="btn-print" onclick="window.print()"><i class="fa fa-print"></i> Imprimir / Salvar como PDF</button>';
    echo '</div>';
    
    echo '<h1>Relatório de Produtos</h1>';
    echo '<h2>' . store('name') . ' - Gerado em ' . date('d/m/Y H:i:s') . '</h2>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Nº</th>';
    echo '<th>Código</th>';
    echo '<th>Nome do Produto</th>';
    echo '<th>Tipo</th>';
    echo '<th>Categoria</th>';
    echo '<th>Fornecedor</th>';
    echo '<th>Marca</th>';
    echo '<th class="text-right">Custo</th>';
    echo '<th class="text-right">Venda</th>';
    echo '<th class="text-center">Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $row_num = 1;
    $total_cost = 0;
    $total_sell = 0;
    
    foreach ($products as $product) {
        $total_cost += ($product['purchase_price'] ?? 0);
        $total_sell += ($product['sell_price'] ?? 0);
        
        echo '<tr>';
        echo '<td class="text-center">' . $row_num . '</td>';
        echo '<td>' . htmlspecialchars($product['p_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['p_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['p_type'] ?? 'standard') . '</td>';
        echo '<td>' . htmlspecialchars($product['category_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['sup_name'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($product['brand_name'] ?? '') . '</td>';
        echo '<td class="text-right">' . number_format(($product['purchase_price'] ?? 0), 2, ',', '.') . '</td>';
        echo '<td class="text-right">' . number_format(($product['sell_price'] ?? 0), 2, ',', '.') . '</td>';
        echo '<td class="text-center">' . (($product['product_status'] ?? 1) == 1 ? 'Ativo' : 'Inativo') . '</td>';
        echo '</tr>';
        
        $row_num++;
    }
    
    echo '</tbody>';
    echo '<tfoot>';
    echo '<tr style="background:#eee;font-weight:bold;">';
    echo '<td colspan="7" class="text-right">TOTAIS:</td>';
    echo '<td class="text-right">' . number_format($total_cost, 2, ',', '.') . '</td>';
    echo '<td class="text-right">' . number_format($total_sell, 2, ',', '.') . '</td>';
    echo '<td></td>';
    echo '</tr>';
    echo '</tfoot>';
    echo '</table>';
    
    echo '<div class="footer">';
    echo '<p>Total de Produtos: ' . count($products) . '</p>';
    echo '<p>Exportado por: ' . get_the_user(user_id(), 'username') . '</p>';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    exit;
}
?>
