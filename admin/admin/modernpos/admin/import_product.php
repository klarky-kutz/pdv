<?php 
ob_start();
session_start();
include ("../_init.php");

// Incluir funções SaaS
if (file_exists(__DIR__ . '/../_inc/saas_limits_check.php')) {
    include_once(__DIR__ . '/../_inc/saas_limits_check.php');
}

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'import_product')) {
	redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

$message = '';
$error_message = null;
$success_message = null;
$import_warnings = array();
$import_errors_detail = array();
$document->setTitle(trans('title_import_product'));

require DIR_SRC.'spreadsheet-reader/Helper.php';
require DIR_SRC.'spreadsheet-reader/SpreadsheetReader.php';

include("header.php");
include ("left_sidebar.php");

/**
 * Obtém ID da categoria padrão [Global] Eletrônicos
 * Se não existir, cria automaticamente
 * @param int $store_id ID da loja para associar
 * @return int|null
 */
function get_default_category_id($store_id = null) 
{
	// Buscar categoria [Global] Eletrônicos
	$statement = db()->prepare("SELECT `category_id` FROM `categorys` WHERE `category_slug` = 'global-eletronicos' OR `category_name` LIKE '%[Global]%Eletr%nicos%' LIMIT 1");
	$statement->execute();
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($result) {
		$category_id = $result['category_id'];
		
		// Verificar se está associada à loja
		if ($store_id) {
			$statement = db()->prepare("SELECT * FROM `category_to_store` WHERE `ccategory_id` = ? AND `store_id` = ?");
			$statement->execute(array($category_id, $store_id));
			if (!$statement->fetch()) {
				// Associar à loja
				try {
					$statement = db()->prepare("INSERT INTO `category_to_store` (`ccategory_id`, `store_id`, `status`, `sort_order`) VALUES (?, ?, 1, 0)");
					$statement->execute(array($category_id, $store_id));
				} catch (Exception $e) {}
			}
		}
		return $category_id;
	}
	
	// Se não existe, criar a categoria [Global] Eletrônicos
	try {
		$statement = db()->prepare("INSERT INTO `categorys` (category_name, category_slug, parent_id, category_details, category_image, created_at) VALUES (?, ?, ?, ?, ?, ?)");
		$statement->execute(array(
			'[Global] Eletrônicos',
			'global-eletronicos',
			0,
			'Categoria padrão para produtos sem categoria definida',
			'',
			date('Y-m-d H:i:s')
		));
		
		$category_id = db()->lastInsertId();
		
		if ($category_id && $store_id) {
			$statement = db()->prepare("INSERT INTO `category_to_store` (`ccategory_id`, `store_id`, `status`, `sort_order`) VALUES (?, ?, 1, 0)");
			$statement->execute(array($category_id, $store_id));
		}
		
		return $category_id;
	} catch (Exception $e) {
		return null;
	}
}

/**
 * Verifica se categoria existe e está associada à loja, se não, associa ou cria
 * @param string $category_slug O slug/nome da categoria
 * @param int $store_id ID da loja para associar a categoria  
 * @param bool $create_if_not_exists Se deve criar a categoria caso não exista
 * @return array ['category_id' => int|null, 'action' => 'found'|'associated'|'created'|null]
 */
function get_or_create_category_for_store($category_slug, $store_id, $create_if_not_exists = false) 
{
	if (empty($category_slug)) {
		return array('category_id' => null, 'action' => null);
	}
	
	// 1. Verificar se categoria existe E está associada à loja
	$statement = db()->prepare("
		SELECT c.category_id FROM `categorys` c
		INNER JOIN `category_to_store` c2s ON c.category_id = c2s.ccategory_id
		WHERE c.`category_slug` = ? AND c2s.`store_id` = ? AND c2s.`status` = 1
		LIMIT 1
	");
	$statement->execute(array($category_slug, $store_id));
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($result) {
		return array('category_id' => $result['category_id'], 'action' => 'found');
	}
	
	// 2. Categoria existe mas NÃO está associada à loja - associar
	$statement = db()->prepare("SELECT category_id, category_name FROM `categorys` WHERE `category_slug` = ? LIMIT 1");
	$statement->execute(array($category_slug));
	$existing = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($existing) {
		// Associar à loja
		try {
			$statement = db()->prepare("INSERT INTO `category_to_store` (`ccategory_id`, `store_id`, `status`, `sort_order`) VALUES (?, ?, 1, 0)");
			$statement->execute(array($existing['category_id'], $store_id));
		} catch (Exception $e) {
			// Já existe, ignorar
		}
		return array('category_id' => $existing['category_id'], 'action' => 'associated', 'name' => $existing['category_name']);
	}
	
	// 3. Categoria NÃO existe - criar se permitido
	if ($create_if_not_exists) {
		$category_name = ucwords(str_replace(array('-', '_'), ' ', $category_slug));
		
		try {
			$statement = db()->prepare("INSERT INTO `categorys` (category_name, category_slug, parent_id, category_details, category_image, created_at) VALUES (?, ?, ?, ?, ?, ?)");
			$statement->execute(array(
				$category_name,
				$category_slug,
				0,
				'Categoria criada automaticamente via importação',
				'',
				date('Y-m-d H:i:s')
			));
			
			$category_id = db()->lastInsertId();
			
			if ($category_id) {
				$statement = db()->prepare("INSERT INTO `category_to_store` (`ccategory_id`, `store_id`, `status`, `sort_order`) VALUES (?, ?, 1, 0)");
				$statement->execute(array($category_id, $store_id));
				return array('category_id' => $category_id, 'action' => 'created', 'name' => $category_name);
			}
		} catch (Exception $e) {
			// Erro ao criar
		}
	}
	
	return array('category_id' => null, 'action' => null);
}

/**
 * Obtém ID padrão para marca (primeira marca disponível)
 */
function get_default_brand_id() 
{
	$statement = db()->prepare("SELECT `brand_id` FROM `brands` ORDER BY `brand_id` ASC LIMIT 1");
	$statement->execute();
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	return $result ? $result['brand_id'] : null;
}

/**
 * Obtém ID padrão para unidade (primeira unidade disponível)
 */
function get_default_unit_id_fallback() 
{
	$statement = db()->prepare("SELECT `unit_id` FROM `units` ORDER BY `unit_id` ASC LIMIT 1");
	$statement->execute();
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	return $result ? $result['unit_id'] : null;
}

function syncImage($product_id, $img_array)
{
	$statement = db()->prepare("DELETE FROM `product_images` WHERE `product_id` = ?");
	$statement->execute(array($product_id));
	foreach ($img_array as $img) {
		if ($img) {
			$statement = db()->prepare("INSERT INTO `product_images` SET `product_id` = ?, `url` = ?");
			$statement->execute(array($product_id, $img));
		}
	}
}

/**
 * Parser para arquivos XLS em formato HTML (exportados pelo sistema)
 * Retorna array de linhas, onde cada linha é array de colunas
 */
function parseHtmlXls($file_path) 
{
	$content = file_get_contents($file_path);
	if (!$content) {
		return array('sheets' => array(), 'error' => 'Não foi possível ler o arquivo.');
	}
	
	// Verifica se é um arquivo HTML-based XLS
	if (strpos($content, '<html') === false && strpos($content, '<table') === false) {
		return array('sheets' => array(), 'error' => 'Arquivo não é um XLS válido.');
	}
	
	$sheets = array();
	$rows = array();
	
	// Extrai o nome da planilha do XML do Excel
	$sheet_name = 'Product';
	if (preg_match('/<x:Name>([^<]+)<\/x:Name>/', $content, $matches)) {
		$sheet_name = trim($matches[1]);
	}
	
	// Remove comentários XML e tags de estilo
	$content = preg_replace('/<!--.*?-->/s', '', $content);
	
	// Encontra a primeira tabela (tabela de dados)
	if (preg_match('/<table[^>]*>(.*?)<\/table>/is', $content, $table_match)) {
		$table_content = $table_match[1];
		
		// Encontra todas as linhas
		preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $table_content, $tr_matches);
		
		if (!empty($tr_matches[1])) {
			foreach ($tr_matches[1] as $tr) {
				$row = array();
				
				// Encontra todas as células (th ou td)
				preg_match_all('/<(th|td)[^>]*>(.*?)<\/\1>/is', $tr, $cell_matches);
				
				if (!empty($cell_matches[2])) {
					foreach ($cell_matches[2] as $cell) {
						// Remove tags HTML e decodifica entidades
						$cell_value = strip_tags($cell);
						$cell_value = html_entity_decode($cell_value, ENT_QUOTES, 'UTF-8');
						$cell_value = trim($cell_value);
						$row[] = $cell_value;
					}
				}
				
				if (!empty($row)) {
					$rows[] = $row;
				}
			}
		}
	}
	
	$sheets[$sheet_name] = $rows;
	
	return array('sheets' => $sheets, 'error' => null);
}

if (isset($request->post['submit'])) 
{
	try {

		if (user_group_id() != 1 && !has_permission('access', 'import_product') || DEMO) {
	      throw new Exception(trans('error_permission'));
	    }
		if (!$_FILES['filename']['name']) 
		{
			throw new Exception('Nenhum arquivo foi selecionado.');
		}
		
		// Validação de MIME type mais flexível (arquivos XLS podem ter diferentes MIME types)
		$allowed_mimes = array(
			'application/vnd.ms-excel',
			'application/excel', 
			'application/x-excel',
			'application/x-msexcel',
			'application/octet-stream', // Alguns navegadores enviam assim
			'text/html' // Arquivos XLS exportados em formato HTML
		);
		
		if (!in_array($_FILES["filename"]["type"], $allowed_mimes)) 
		{
			throw new Exception('Tipo de arquivo inválido: ' . $_FILES["filename"]["type"] . '. Envie um arquivo .xls válido.');
		}
		if(isset($_FILES["filename"]["type"]))
		{
			$validextensions = array("xls");
			$temporary = explode(".", $_FILES["filename"]["name"]);
			$file_extension = end($temporary);
			
			if (in_array($file_extension, $validextensions)) {
				if ($_FILES["filename"]["error"] > 0) {
					throw new Exception("Return Code: " . $_FILES['filename']['error']);
				} else {
					$temp = explode(".", $_FILES["filename"]["name"]);
					$newfilename = 'products.' . end($temp);
					$sourcePath = $_FILES["filename"]["tmp_name"];
					$targetPath = "../storage/".$newfilename;
					if(!move_uploaded_file($sourcePath, $targetPath)) {
						throw new Exception(trans('error_upload'));
					}
				}
			} else {
				throw new Exception(trans('error_invalid_file'));
			}
		}

		$file_path = realpath(__DIR__.'/../').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'products.xls';
		if (!file_exists($file_path)) {
			throw new Exception(trans('error_invalid_file'));
		}

		$p_date = date('Y-m-d');
		$expired_date = date('Y-m-d H:i:s', strtotime("+1 year", time()));
		$insert_status = array();
		$update_status = array();
		$categories_created = array();
		
		// Opção de criar categorias automaticamente
		$auto_create_categories = isset($request->post['auto_create_categories']) && $request->post['auto_create_categories'] == '1';
		$total_item_no = 0;
		
		// IMPORTANTE: Validação inteligente de loja para SaaS
		$active_store_id = store_id();
		if (!$active_store_id) {
			throw new Exception('Nenhuma loja ativa na sessão. Por favor, selecione uma loja antes de importar.');
		}
		
		// Verificar se o usuário tem permissão para importar na loja ativa
		$user_stores = get_stores();
		$user_store_ids = array_column($user_stores, 'store_id');
		
		if (!in_array($active_store_id, $user_store_ids) && user_group_id() != 1) {
			throw new Exception('Você não tem permissão para importar produtos nesta loja.');
		}
		
		// Guardar nome da loja para feedback
		$active_store_name = store('name');
		$import_warnings[] = "Produtos serão importados para a loja: {$active_store_name} (ID: {$active_store_id}).";

		$Hooks->do_action('Before_Import_Product', $request);

		// Tenta primeiro com SpreadsheetReader (para XLS binário)
		$use_html_parser = false;
		$product_rows = array();
		// Verifica se o arquivo é HTML (exportado pelo sistema) ou XLS binário
		$file_content_start = file_get_contents($file_path, false, null, 0, 500);
		$is_html_file = (strpos($file_content_start, '<html') !== false || strpos($file_content_start, '<table') !== false);
		
		if ($is_html_file) {
			// Arquivo é HTML, usar parser HTML diretamente
			$use_html_parser = true;
		} else {
			// Tentar SpreadsheetReader para XLS binário
			try {
				$Reader = new SpreadsheetReader($file_path);
				$Sheets = $Reader->Sheets();
				
				if (empty($Sheets) || !in_array('Product', $Sheets)) {
					$use_html_parser = true;
				} else {
					$product_index = array_search('Product', $Sheets);
					$Reader->ChangeSheet($product_index);
					
					foreach ($Reader as $Row) {
						$product_rows[] = $Row;
					}
					
					if (empty($product_rows)) {
						$use_html_parser = true;
					}
				}
			} catch (Exception $e) {
				$use_html_parser = true;
			}
		}
		
		// Se SpreadsheetReader falhou, tenta o parser HTML
		if ($use_html_parser) {
			$html_result = parseHtmlXls($file_path);
			
			if ($html_result['error']) {
				throw new Exception('Erro ao ler arquivo: ' . $html_result['error']);
			}
			
			if (empty($html_result['sheets'])) {
				throw new Exception('Nenhuma planilha encontrada no arquivo.');
			}
			
			// Pega a primeira planilha (geralmente 'Product')
			$sheet_name = array_key_first($html_result['sheets']);
			$product_rows = $html_result['sheets'][$sheet_name];
			
			if (empty($product_rows)) {
				throw new Exception('A planilha está vazia ou não contém dados válidos.');
			}
		}
		
		// Processa as linhas
		$row_count = 0;
		foreach ($product_rows as $Row)
		{
			$row_count++;
			if ($Row[1] == 'ProductName' || !$Row[1]) continue;

						$pro_data['product_name'] = $Row[1];
						$pro_data['product_type'] = $Row[2];
						$pro_data['code'] = isset($Row[3]) ? $Row[3] : '';
						$pro_data['hsn_code'] = isset($Row[4]) ? $Row[4] : '';
						$pro_data['barcode_symbology'] = $Row[5];
						
						// Verificar StoreCode do arquivo para aviso (mas usar sempre a loja ativa)
						$file_store_code = isset($Row[6]) ? trim($Row[6]) : '';
						if ($file_store_code && $row_count == 2) { // Verificar apenas na primeira linha de dados
							$file_store_id = get_store_id_by_code($file_store_code);
							if ($file_store_id && $file_store_id != $active_store_id) {
								$import_warnings[] = "ATENÇÃO: O arquivo foi exportado da loja '{$file_store_code}', mas os produtos serão importados para a loja ativa '{$active_store_name}'.";
							}
						}
						// Categoria - verifica se existe e está associada à loja
						$category_slug = isset($Row[7]) ? trim($Row[7]) : '';
						$pro_data['category_id'] = null;
						
						if ($category_slug) {
							// Verificar/associar/criar categoria
							$cat_result = get_or_create_category_for_store($category_slug, $active_store_id, $auto_create_categories);
							$pro_data['category_id'] = $cat_result['category_id'];
							
							// Registrar ação tomada
							if ($cat_result['action'] == 'associated' && !in_array($category_slug, $categories_created)) {
								$categories_created[] = $category_slug;
								$import_warnings[] = "Categoria '{$cat_result['name']}' associada à sua loja.";
							} elseif ($cat_result['action'] == 'created' && !in_array($category_slug, $categories_created)) {
								$categories_created[] = $category_slug;
								$import_warnings[] = "Categoria '{$cat_result['name']}' criada automaticamente.";
							}
						}
						
						if (!$pro_data['category_id']) {
							// Usar [Global] Eletrônicos como categoria padrão
							$pro_data['category_id'] = get_default_category_id($active_store_id);
							if ($pro_data['category_id'] && $category_slug) {
								$import_warnings[] = "Produto '{$Row[1]}': Categoria '{$category_slug}' não encontrada, usando '[Global] Eletrônicos'.";
							} elseif ($pro_data['category_id'] && !$category_slug) {
								// Categoria não especificada no arquivo
							}
						}
						
						// Unidade - usa padrão se vazio
						$unit_code = isset($Row[8]) ? trim($Row[8]) : '';
						$pro_data['unit_id'] = $unit_code ? get_unit_id_by_code($unit_code) : null;
						if (!$pro_data['unit_id']) {
							$pro_data['unit_id'] = get_default_unit_id_fallback();
							if ($pro_data['unit_id'] && $unit_code) {
								$import_warnings[] = "Produto '{$Row[1]}': Unidade '{$unit_code}' não encontrada, usando unidade padrão.";
							}
						}
						
						$pro_data['taxrate_id'] = get_taxrate_id_by_code($Row[9]);
						$pro_data['tax_method'] = isset($Row[10]) ? $Row[10] : 'inclusive';
						$pro_data['sup_id'] = get_supplier_id_by_code($Row[11]);
						
						// Marca - usa padrão se vazio
						$brand_code = isset($Row[12]) ? trim($Row[12]) : '';
						$pro_data['brand_id'] = $brand_code ? get_brand_id_by_code($brand_code) : null;
						if (!$pro_data['brand_id']) {
							$pro_data['brand_id'] = get_default_brand_id();
							if ($pro_data['brand_id'] && $brand_code) {
								$import_warnings[] = "Produto '{$Row[1]}': Marca '{$brand_code}' não encontrada, usando marca padrão.";
							}
						}
						
						$pro_data['box_id'] = get_box_id_by_code($Row[13]);
						$pro_data['alert_quantity'] = isset($Row[14]) ? (float)$Row[14] : 10;
						$pro_data['cost_price'] = isset($Row[15]) ? (float)$Row[15] : 0;
						$pro_data['sell_price'] = isset($Row[16]) ? (float)$Row[16] : 0;
						$pro_data['description'] = isset($Row[17]) ? $Row[17] : '';
						$pro_data['status'] = isset($Row[18]) ? (int)$Row[18] : 1;
						$pro_data['thumbnail'] = isset($Row[19]) ? $Row[19] : '';
						$img_array = isset($Row[20]) ? $Row[20] : array();

						if (!$pro_data['product_name']) {
							throw new Exception(trans('error_product_name'));
						}
						if (!$pro_data['code']) {
							throw new Exception(trans('error_product_code'));
						}
						// Validação de StoreCode removida - usar sempre loja ativa da sessão

						if (!in_array($pro_data['product_type'], array('standard', 'service'))) {
							throw new Exception(trans('error_invalid_product_type').' ('.$pro_data['product_name'].'-'.$pro_data['code'].')');
						}
						if (!in_array($pro_data['barcode_symbology'], array('code25','code39','code128','ean5','ean13','upca','upce'))) {
							throw new Exception(trans('error_invalid_barcode_symbology').' ('.$pro_data['product_name'].'-'.$pro_data['code'].')');
						}
						// Validações com mensagens detalhadas
						$product_identifier = "'{$pro_data['product_name']}' (Código: {$pro_data['code']})";
						
						if (!$pro_data['category_id']) {
							throw new Exception("Categoria inválida para o produto {$product_identifier}. Verifique se a coluna 'CategorySlug' está preenchida com um slug válido cadastrado no sistema.");
						}
						if (!$pro_data['unit_id']) {
							throw new Exception("Unidade inválida para o produto {$product_identifier}. Verifique se a coluna 'UnitCode' está preenchida com um código de unidade válido cadastrado no sistema.");
						}
						if (!$pro_data['taxrate_id']) {
							throw new Exception("Taxa de imposto inválida para o produto {$product_identifier}. Verifique se a coluna 'TaxrateCode' (valor: '{$Row[9]}') está preenchida com um código de taxa válido.");
						}
						if (!in_array($pro_data['tax_method'], array('inclusive','exclusive'))) {
							throw new Exception("Método de imposto inválido para o produto {$product_identifier}. A coluna 'TaxMethod' deve ser 'inclusive' ou 'exclusive'.");
						}
						if (!$pro_data['sup_id']) {
							throw new Exception("Fornecedor inválido para o produto {$product_identifier}. Verifique se a coluna 'SupplierCode' (valor: '{$Row[11]}') está preenchida com um código de fornecedor válido.");
						}
						if (!$pro_data['brand_id']) {
							throw new Exception("Marca inválida para o produto {$product_identifier}. Verifique se a coluna 'BrandCode' está preenchida com um código de marca válido cadastrado no sistema.");
						}
						if (!$pro_data['box_id']) {
							throw new Exception("Caixa/Embalagem inválida para o produto {$product_identifier}. Verifique se a coluna 'BoxCode' (valor: '{$Row[13]}') está preenchida com um código de caixa válido.");
						}
						if ($pro_data['alert_quantity'] < 0) {
							throw new Exception("Quantidade de alerta inválida para o produto {$product_identifier}. O valor não pode ser negativo.");
						}
						if ($pro_data['sell_price'] <= 0) {
							throw new Exception("Preço de venda inválido para o produto {$product_identifier}. O valor deve ser maior que zero.");
						}
						if (!empty($img_array)) {
							$img_array = explode('|', $img_array);
						}
						
						// ========== LÓGICA DE IMPORTAÇÃO SaaS ==========
						// 1. Verificar se produto já existe na LOJA ATIVA (para update)
						// 2. Se não existe na loja ativa, verificar se existe GLOBALMENTE (pelo código)
						// 3. Se existe globalmente: apenas criar associação product_to_store
						// 4. Se não existe globalmente: criar novo produto
						
						$product_in_store = null;
						$product_global = null;
						$p_code = $pro_data['code'];
						
						// Verificar se produto já está associado à loja ativa
						$sql = "SELECT DISTINCT p.* FROM `products` p 
								INNER JOIN `product_to_store` p2s ON p.p_id = p2s.product_id 
								WHERE (p.`p_name` = ? OR p.`p_code` = ?) AND p2s.`store_id` = ?
								LIMIT 1";
						$statement = db()->prepare($sql);
						$statement->execute(array($pro_data['product_name'], $pro_data['code'], $active_store_id));
						$product_in_store = $statement->fetch(PDO::FETCH_ASSOC);
						
						// Verificar se produto existe globalmente (pelo código)
						$statement = db()->prepare("SELECT * FROM `products` WHERE `p_code` = ? LIMIT 1");
						$statement->execute(array($pro_data['code']));
						$product_global = $statement->fetch(PDO::FETCH_ASSOC);
						
						if ($product_in_store) {
							// CASO 1: Produto já existe na loja ativa - ATUALIZAR
							$product_id = $product_in_store['p_id'];
							
							$statement = db()->prepare("UPDATE `products` SET `p_type` = ?, `hsn_code` = ?, `barcode_symbology` = ?, `p_name` = ?, `category_id` = ?, `unit_id` = ?, `p_image` = ?, `description` = ? WHERE `p_id` = ?");
							$statement->execute(array($pro_data['product_type'], $pro_data['hsn_code'], $pro_data['barcode_symbology'], $pro_data['product_name'], $pro_data['category_id'], $pro_data['unit_id'], $pro_data['thumbnail'], $pro_data['description'], $product_id));

							// Atualizar associação com a loja
							$statement = db()->prepare("DELETE FROM `product_to_store` WHERE `product_id` = ? AND `store_id` = ?");
							$statement->execute(array($product_id, $active_store_id));
							
							$statement = db()->prepare("INSERT INTO `product_to_store` (product_id, store_id, purchase_price, sell_price, alert_quantity, sup_id, brand_id, box_id, taxrate_id, tax_method, e_date, p_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
							$statement->execute(array($product_id, $active_store_id, $pro_data['cost_price'], $pro_data['sell_price'], $pro_data['alert_quantity'], (int)$pro_data['sup_id'], (int)$pro_data['brand_id'], (int)$pro_data['box_id'], $pro_data['taxrate_id'], $pro_data['tax_method'], $expired_date, $p_date, $pro_data['status']));
							
							$update_status[] = 'ok';
							$total_item_no++;
							
						} elseif ($product_global) {
							// CASO 2: Produto existe globalmente mas NÃO na loja ativa - ASSOCIAR
							$product_id = $product_global['p_id'];
							
							// Apenas criar associação com a loja ativa (não modificar o produto global)
							$statement = db()->prepare("DELETE FROM `product_to_store` WHERE `product_id` = ? AND `store_id` = ?");
							$statement->execute(array($product_id, $active_store_id));
							
							$statement = db()->prepare("INSERT INTO `product_to_store` (product_id, store_id, purchase_price, sell_price, alert_quantity, sup_id, brand_id, box_id, taxrate_id, tax_method, e_date, p_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
							$statement->execute(array($product_id, $active_store_id, $pro_data['cost_price'], $pro_data['sell_price'], $pro_data['alert_quantity'], (int)$pro_data['sup_id'], (int)$pro_data['brand_id'], (int)$pro_data['box_id'], $pro_data['taxrate_id'], $pro_data['tax_method'], $expired_date, $p_date, $pro_data['status']));
							
							$import_warnings[] = "Produto '{$pro_data['product_name']}' (código: {$pro_data['code']}) já existe no sistema - associado à sua loja.";
							$insert_status[] = 'ok';
							$total_item_no++;
							
						} else {
							// CASO 3: Produto NÃO existe - CRIAR NOVO
							if (!$p_code) {
								$p_code = randomNumber(8);
								$p = 1;
								while ($p) {
									$p_code = randomNumber(8);
									$statement = db()->prepare("SELECT * FROM `products` WHERE `p_code` = ?");
									$statement->execute(array($p_code));
									$p = $statement->fetch(PDO::FETCH_ASSOC);
								}
							}

							$statement = db()->prepare("INSERT INTO `products` (`p_type`, `p_code`, `hsn_code`, `barcode_symbology`, `p_name`, `category_id`, `unit_id`, `p_image`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
							$statement->execute(array($pro_data['product_type'], $p_code, $pro_data['hsn_code'], $pro_data['barcode_symbology'], $pro_data['product_name'], (int)$pro_data['category_id'], (int)$pro_data['unit_id'], $pro_data['thumbnail'], $pro_data['description']));
							$product_id = db()->lastInsertId();
							
							if ($product_id) {
								$statement = db()->prepare("INSERT INTO `product_to_store` (product_id, store_id, purchase_price, sell_price, alert_quantity, sup_id, brand_id, box_id, taxrate_id, tax_method, e_date, p_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
								$statement->execute(array($product_id, $active_store_id, $pro_data['cost_price'], $pro_data['sell_price'], $pro_data['alert_quantity'], (int)$pro_data['sup_id'], (int)$pro_data['brand_id'], (int)$pro_data['box_id'], $pro_data['taxrate_id'], $pro_data['tax_method'], $expired_date, $p_date, $pro_data['status']));
								$insert_status[] = 'ok';
							} else {
								$insert_status[] = 'error';
							}
							$total_item_no++;
						}
			if ($product_id) {
				if ($img_array) {
					syncImage($product_id, $img_array);
				}
			}
		}

		$success = 0;
		$error = 0;
		$message = '';
		$message .= '<div><span class="fa fa-fw fa-info-circle"></span> Total Item: ' . $total_item_no . '</div>';
		if ( count($insert_status) > 0 ) {
			for ($i=0; $i < count($insert_status); $i++) { 
				if ( $insert_status[$i] == 'ok' ) {
					$success++;
				}
				if ( $insert_status[$i] == 'error' ) {
					$error++;
				}
			} 
			$message .= '<p><strong>Insert Status</strong></p>';
			$message .= '<ul>';
			$message .= '<li>Total Inserted: ' . $success . '</li>';
			$message .= '<li>Error in: ' . $error . '</li>';
			$message .= '</ul>';
		}

		if (count($update_status) > 0) {
			for ($i=0; $i < count($update_status); $i++) 
			{ 
				if ($update_status[$i]=='ok') {
					$success++;
				}
				if ($update_status[$i]=='error') {
					$error++;
				}
			}
			$message .= '<p><strong>Update Status</strong></p>';
			$message .= '<ul>';
			$message .= '<li>Total Updated: ' . $success . '</li>';
			$message .= '<li>Unchanged in: ' . $error . '</li>';
			$message .= '</ul>';
		}

		$Hooks->do_action('After_Import_Product', $request);
	}
	catch(Exception $e) { 
	    $error_message = $e->getMessage();
	}
} ?>

<!-- Custom Styles for Import Page -->
<style>
.import-container {
    max-width: 100%;
    margin: 0;
}
.import-card {
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 25px;
}
.import-card-header {
    background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%);
    color: #fff;
    padding: 25px 30px;
    text-align: center;
}
.import-card-header h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 600;
}
.import-card-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}
.import-card-header .icon-large {
    font-size: 50px;
    margin-bottom: 15px;
    opacity: 0.9;
}
.import-card-body {
    padding: 30px 40px;
}
.step-indicator {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 2px solid #f0f0f0;
}
.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.step-number {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    margin-bottom: 8px;
    box-shadow: 0 3px 10px rgba(0,166,90,0.3);
}
.step-label {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}
.info-box {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    border-left: 4px solid #2196f3;
}
.info-box h4 {
    color: #1565c0;
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
}
.info-box p {
    color: #1976d2;
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
}
.download-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px 25px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 2px dashed #dee2e6;
}
.download-section .download-info {
    display: flex;
    align-items: center;
    gap: 15px;
}
.download-section .download-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
}
.download-section .download-text h5 {
    margin: 0 0 3px 0;
    color: #333;
    font-size: 15px;
    font-weight: 600;
}
.download-section .download-text span {
    color: #888;
    font-size: 13px;
}
.download-section .btn-download {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: #fff;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(40,167,69,0.3);
}
.download-section .btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40,167,69,0.4);
    color: #fff;
    text-decoration: none;
}
.upload-area {
    border: 3px dashed #dee2e6;
    border-radius: 15px;
    padding: 40px;
    text-align: center;
    background: #fafafa;
    transition: all 0.3s ease;
    margin-bottom: 25px;
}
.upload-area:hover {
    border-color: #00a65a;
    background: #f0fff4;
}
.upload-area .upload-icon {
    font-size: 60px;
    color: #ccc;
    margin-bottom: 15px;
}
.upload-area h4 {
    color: #333;
    margin: 0 0 8px 0;
    font-size: 18px;
}
.upload-area p {
    color: #888;
    margin: 0 0 20px 0;
    font-size: 14px;
}
.upload-area input[type="file"] {
    display: none;
}
.upload-area .btn-choose {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: #fff;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
.upload-area .btn-choose:hover {
    background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%);
}
.file-name-display {
    margin-top: 15px;
    padding: 10px 15px;
    background: #e8f5e9;
    border-radius: 8px;
    color: #2e7d32;
    font-weight: 500;
    display: none;
}
.file-name-display.show {
    display: block;
}
.btn-import {
    background: linear-gradient(135deg, #00a65a 0%, #026c3c 100%);
    color: #fff;
    border: none;
    padding: 15px 40px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    box-shadow: 0 4px 15px rgba(0,166,90,0.3);
}
.btn-import:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,166,90,0.4);
}
.result-box {
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.result-box.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-left: 4px solid #28a745;
}
.result-box.error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border-left: 4px solid #dc3545;
}
.result-box.info {
    background: linear-gradient(135deg, #cce5ff 0%, #b8daff 100%);
    border-left: 4px solid #007bff;
}
.result-box.warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    border-left: 4px solid #ffc107;
}
.result-box h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
}
.result-box.success h4 { color: #155724; }
.result-box.error h4 { color: #721c24; }
.result-box.info h4 { color: #004085; }
.result-box.warning h4 { color: #856404; }
.result-box ul {
    margin: 0;
    padding-left: 20px;
}
.result-box li {
    margin-bottom: 5px;
}
.result-box .warning-list {
    max-height: 200px;
    overflow-y: auto;
    font-size: 13px;
}
.result-box .warning-list li {
    margin-bottom: 3px;
    color: #856404;
}
</style>

<!-- Content Wrapper Start -->
<div class="content-wrapper">

	<!-- Content Header Start -->
	<section class="content-header">
		<h1>
		  <?php echo sprintf(trans('text_import_title'), trans('text_product')); ?>
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
		        <a href="product.php"><?php echo trans('text_products'); ?></a>  
		    </li>
			<li class="active">
			  	<?php echo sprintf(trans('text_import_title'), trans('text_product')); ?>
			</li>
		</ol>
	</section>
	<!-- Content Header End -->

	<!-- Content Start -->
	<section class="content">

		<?php if(DEMO) : ?>
	    <div class="result-box error">
			<h4><i class="fa fa-exclamation-triangle"></i> Modo Demonstração</h4>
			<p><?php echo $demo_text; ?></p>
			<p><?php echo trans('text_disabled_in_demo'); ?></p>
	    </div>
	    <?php endif; ?>
    
		<div class="import-container">
			
		<!-- Messages -->
			<?php if ($error_message): ?>
			<div class="result-box error">
				<h4><i class="fa fa-times-circle"></i> Erro na Importação</h4>
				<p><?php echo htmlspecialchars($error_message); ?></p>
				<p style="margin-top: 10px; font-size: 13px;"><strong>Dica:</strong> Verifique se todos os campos obrigatórios estão preenchidos corretamente no arquivo XLS.</p>
			</div>
			<?php endif; ?>

			<?php if ($message): ?>
			<div class="result-box success">
				<h4><i class="fa fa-check-circle"></i> Importação Concluída</h4>
				<?php echo $message; ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($import_warnings)): ?>
			<div class="result-box warning">
				<h4><i class="fa fa-exclamation-triangle"></i> Avisos (<?php echo count($import_warnings); ?>)</h4>
				<p>Alguns campos estavam vazios ou inválidos e foram substituídos por valores padrão:</p>
				<ul class="warning-list">
					<?php foreach(array_slice($import_warnings, 0, 20) as $warning): ?>
					<li><?php echo htmlspecialchars($warning); ?></li>
					<?php endforeach; ?>
					<?php if (count($import_warnings) > 20): ?>
					<li><em>... e mais <?php echo count($import_warnings) - 20; ?> avisos</em></li>
					<?php endif; ?>
				</ul>
			</div>
			<?php endif; ?>

			<!-- Main Import Card -->
			<div class="import-card">
				<div class="import-card-header">
					<div class="icon-large">
						<i class="fa fa-cloud-upload"></i>
					</div>
					<h2><?php echo sprintf(trans('text_import_title'), trans('text_product')); ?></h2>
					<p>Importe seus produtos de forma rápida usando um arquivo XLS</p>
				</div>
				
				<div class="import-card-body">
					<!-- Step Indicator -->
					<div class="step-indicator">
						<div class="step-item">
							<div class="step-number">1</div>
							<div class="step-label">Baixar Modelo</div>
						</div>
						<div class="step-item">
							<div class="step-number">2</div>
							<div class="step-label">Preencher Dados</div>
						</div>
						<div class="step-item">
							<div class="step-number">3</div>
							<div class="step-label">Enviar Arquivo</div>
						</div>
					</div>

					<!-- Instructions -->
					<div class="info-box">
						<h4><i class="fa fa-lightbulb-o"></i> Instruções Importantes</h4>
						<p><?php echo trans('text_product_import_instruction'); ?></p>
					</div>

					<!-- Download Section -->
					<div class="download-section">
						<div class="download-info">
							<div class="download-icon">
								<i class="fa fa-file-excel-o"></i>
							</div>
							<div class="download-text">
								<h5>Arquivo Modelo (Template)</h5>
								<span>pos-products.xls - Formato Excel 97-2003</span>
							</div>
						</div>
						<a href="../storage/pos-products.xls" class="btn-download" id="download_demo">
							<i class="fa fa-download"></i>
							<?php echo trans('button_download'); ?>
						</a>
					</div>

					<!-- Upload Form -->
					<form action="" method="post" enctype="multipart/form-data" id="import-form">
						<div class="upload-area" id="upload-area">
							<div class="upload-icon">
								<i class="fa fa-file-excel-o"></i>
							</div>
							<h4><?php echo trans('text_select_xls_file'); ?></h4>
							<p>Arraste e solte seu arquivo aqui ou clique para selecionar</p>
							<input type="file" name="filename" id="filename" accept=".xls" required>
							<label for="filename" class="btn-choose">
								<i class="fa fa-folder-open"></i> Escolher Arquivo
							</label>
							<div class="file-name-display" id="file-name-display">
								<i class="fa fa-check-circle"></i> <span id="selected-file-name"></span>
							</div>
						</div>

						<!-- Opções de Importação -->
						<div class="import-options" style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 25px; border: 1px solid #e9ecef;">
							<h5 style="margin: 0 0 15px 0; color: #333; font-size: 15px; font-weight: 600;">
								<i class="fa fa-cogs"></i> Opções de Importação
							</h5>
							<div class="checkbox-option" style="display: flex; align-items: center; gap: 10px;">
								<input type="checkbox" name="auto_create_categories" id="auto_create_categories" value="1" 
									style="width: 20px; height: 20px; cursor: pointer;">
								<label for="auto_create_categories" style="cursor: pointer; margin: 0; font-size: 14px; color: #555;">
									<strong>Criar categorias automaticamente</strong><br>
									<small style="color: #888;">Se uma categoria não existir, ela será criada automaticamente com base no slug do arquivo.</small>
								</label>
							</div>
						</div>

						<button type="submit" class="btn-import" name="submit">
							<i class="fa fa-upload"></i>
							<?php echo trans('button_import'); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	</section>
	<!-- Content End -->

</div>
<!-- Content Wrapper End -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('filename');
    var fileNameDisplay = document.getElementById('file-name-display');
    var selectedFileName = document.getElementById('selected-file-name');
    var uploadArea = document.getElementById('upload-area');
    
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            selectedFileName.textContent = this.files[0].name;
            fileNameDisplay.classList.add('show');
            uploadArea.style.borderColor = '#00a65a';
            uploadArea.style.background = '#f0fff4';
        } else {
            fileNameDisplay.classList.remove('show');
            uploadArea.style.borderColor = '#dee2e6';
            uploadArea.style.background = '#fafafa';
        }
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#00a65a';
        this.style.background = '#f0fff4';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        if (!fileInput.files || fileInput.files.length === 0) {
            this.style.borderColor = '#dee2e6';
            this.style.background = '#fafafa';
        }
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            var event = new Event('change');
            fileInput.dispatchEvent(event);
        }
    });
});
</script>

<?php include ("footer.php"); ?>