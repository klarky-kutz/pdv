<?php
/**
 * Sample Data Helper
 * Creates example data when a new store is created
 */

if (!function_exists('link_global_templates_to_store')) {
    /**
     * Link all global receipt templates to a new store
     * 
     * @param int $store_id The ID of the store
     * @return bool Success status
     */
    function link_global_templates_to_store($store_id) {
        try {
            // Buscar todos os templates globais (sem marcador de personalização)
            $stmt = db()->prepare("
                SELECT template_id, template_name 
                FROM pos_templates 
                WHERE template_name NOT LIKE '%(Personalizado)%'
                ORDER BY template_id ASC
            ");
            $stmt->execute();
            $global_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($global_templates)) {
                return true; // Nenhum template global para vincular
            }
            
            $sort_order = 1;
            $first_template_id = null;
            
            foreach ($global_templates as $template) {
                $template_id = (int)$template['template_id'];
                
                if ($first_template_id === null) {
                    $first_template_id = $template_id;
                }
                
                // Verificar se já existe vínculo
                $check = db()->prepare("
                    SELECT pt2s 
                    FROM pos_template_to_store 
                    WHERE store_id = ? AND ttemplate_id = ? 
                    LIMIT 1
                ");
                $check->execute([$store_id, $template_id]);
                $exists = $check->fetch(PDO::FETCH_ASSOC);
                
                if (!$exists) {
                    // Criar vínculo
                    $insert = db()->prepare("
                        INSERT INTO pos_template_to_store 
                        (store_id, ttemplate_id, is_active, status, sort_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    // Primeiro template será ativo, outros inativos
                    $is_active = ($template_id === $first_template_id) ? 1 : 0;
                    
                    $insert->execute([
                        $store_id,
                        $template_id,
                        $is_active,
                        1, // status ativo
                        $sort_order
                    ]);
                    
                    $sort_order++;
                }
            }
            
            // Definir o primeiro template como padrão na preferência da loja
            if ($first_template_id) {
                $store_model = registry()->get('loader')->model('store');
                $store_data = $store_model->getStore($store_id);
                
                if ($store_data) {
                    $pref = valid_unserialize($store_data['preference']);
                    if (!is_array($pref)) {
                        $pref = [];
                    }
                    
                    // Só define se ainda não existe
                    if (!isset($pref['receipt_template'])) {
                        $pref['receipt_template'] = $first_template_id;
                        
                        $update = db()->prepare("
                            UPDATE stores 
                            SET preference = ? 
                            WHERE store_id = ?
                        ");
                        $update->execute([serialize($pref), $store_id]);
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Error linking global templates: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('create_sample_data_for_store')) {
    /**
     * Create sample data for a new store by linking to global data
     * 
     * @param int $store_id Store ID
     * @return bool Success status
     */
    function create_sample_data_for_store($store_id) {
        try {
            // 0. Link global receipt templates to this store
            link_global_templates_to_store($store_id);
            
            // 1-4. Link global sample data (category, supplier, brand, bank account)
            link_global_sample_data_to_store($store_id);

            // 5. Demo products por loja (SERVICE + sem taxa)
            create_demo_products_for_store($store_id);
            
            return true;
        } catch (Exception $e) {
            error_log('Error creating sample data: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('link_global_sample_data_to_store')) {
    /**
     * Link global sample data to a new store
     * Uses pre-created global records instead of creating new ones
     * 
     * @param int $store_id Store ID
     * @return bool Success status
     */
    function link_global_sample_data_to_store($store_id) {
        try {
            // Buscar dados globais pelo nome com prefixo [Global]
            
            // 1. Categoria Global
            // OBS: na tabela `categorys` a PK é `category_id` (não `ccategory_id`).
            // Já na tabela de vínculo `category_to_store` a coluna se chama `ccategory_id`.
            $stmt = db()->prepare("SELECT category_id FROM categorys WHERE category_name = ?");
            $stmt->execute(['[Global] Eletrônicos']);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($category) {
                $category_id = (int)$category['category_id'];

                // Verificar se já não está vinculado
                $check = db()->prepare("SELECT 1 FROM category_to_store WHERE ccategory_id = ? AND store_id = ?");
                $check->execute([$category_id, $store_id]);
                
                if (!$check->fetch()) {
                    $link = db()->prepare("INSERT INTO category_to_store (ccategory_id, store_id) VALUES (?, ?)");
                    $link->execute([$category_id, $store_id]);
                }
            }
            
            // 2. Fornecedor Global
            $stmt = db()->prepare("SELECT sup_id FROM suppliers WHERE sup_name = ?");
            $stmt->execute(['[Global] Fornecedor Exemplo LTDA']);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supplier) {
                $check = db()->prepare("SELECT 1 FROM supplier_to_store WHERE sup_id = ? AND store_id = ?");
                $check->execute([$supplier['sup_id'], $store_id]);
                
                if (!$check->fetch()) {
                    $link = db()->prepare("INSERT INTO supplier_to_store (sup_id, store_id, status, sort_order) VALUES (?, ?, ?, ?)");
                    $link->execute([$supplier['sup_id'], $store_id, 1, 0]);
                }
            }
            
            // 3. Marca Global
            $stmt = db()->prepare("SELECT brand_id FROM brands WHERE brand_name = ?");
            $stmt->execute(['[Global] Marca Exemplo']);
            $brand = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($brand) {
                $check = db()->prepare("SELECT 1 FROM brand_to_store WHERE brand_id = ? AND store_id = ?");
                $check->execute([$brand['brand_id'], $store_id]);
                
                if (!$check->fetch()) {
                    $link = db()->prepare("INSERT INTO brand_to_store (brand_id, store_id, status, sort_order) VALUES (?, ?, ?, ?)");
                    $link->execute([$brand['brand_id'], $store_id, 1, 0]);
                }
            }
            
            // 4. Conta Bancária Global
            $stmt = db()->prepare("SELECT id FROM bank_accounts WHERE account_name = ?");
            $stmt->execute(['[Global] Conta Corrente Exemplo']);
            $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bank_account) {
                $check = db()->prepare("SELECT 1 FROM bank_account_to_store WHERE account_id = ? AND store_id = ?");
                $check->execute([$bank_account['id'], $store_id]);
                
                if (!$check->fetch()) {
                    $link = db()->prepare("INSERT INTO bank_account_to_store (account_id, store_id, status, sort_order) VALUES (?, ?, ?, ?)");
                    $link->execute([$bank_account['id'], $store_id, 1, 0]);
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Error linking global sample data: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('create_demo_products_for_store')) {
    /**
     * Cria 2 produtos de demonstração exclusivos por loja.
     *
     * Importante: não pode ser "global reutilizado" porque, no ModernPOS,
     * a exclusão de produto remove o registro de `products` (global) e afetaria outras lojas.
     */
    function create_demo_products_for_store($store_id) {
        try {
            // Dependências
            $category_id = 1;
            $sup_id = 1;
            $brand_id = 1;
            $unit_id = 1;
            $box_id = 1;
            // 0 = sem taxa (fallback)
            $taxrate_id = 0;

            // Categoria global (se existir)
            $stmt = db()->prepare("SELECT category_id FROM categorys WHERE category_name = ? LIMIT 1");
            $stmt->execute(['[Global] Eletrônicos']);
            $tmp = $stmt->fetchColumn();
            if ($tmp) $category_id = (int)$tmp;

            // Fornecedor global (se existir)
            $stmt = db()->prepare("SELECT sup_id FROM suppliers WHERE sup_name = ? LIMIT 1");
            $stmt->execute(['[Global] Fornecedor Exemplo LTDA']);
            $tmp = $stmt->fetchColumn();
            if ($tmp) $sup_id = (int)$tmp;

            // Marca global (se existir)
            $stmt = db()->prepare("SELECT brand_id FROM brands WHERE brand_name = ? LIMIT 1");
            $stmt->execute(['[Global] Marca Exemplo']);
            $tmp = $stmt->fetchColumn();
            if ($tmp) $brand_id = (int)$tmp;

            // Unit padrão (primeira)
            $stmt = db()->prepare("SELECT unit_id FROM units ORDER BY unit_id ASC LIMIT 1");
            $stmt->execute();
            $tmp = $stmt->fetchColumn();
            if ($tmp) $unit_id = (int)$tmp;

            // Box padrão (primeiro)
            $stmt = db()->prepare("SELECT box_id FROM boxes ORDER BY box_id ASC LIMIT 1");
            $stmt->execute();
            $tmp = $stmt->fetchColumn();
            if ($tmp) $box_id = (int)$tmp;

            // Taxrate padrão: tenta pegar "no_tax"; se não existir, pega o primeiro taxrate com 0%
            $stmt = db()->prepare("SELECT taxrate_id FROM taxrates WHERE code_name = 'no_tax' ORDER BY taxrate_id ASC LIMIT 1");
            $stmt->execute();
            $tmp = $stmt->fetchColumn();
            if ($tmp) {
                $taxrate_id = (int)$tmp;
            } else {
                $stmt = db()->prepare("SELECT taxrate_id FROM taxrates WHERE taxrate = 0 ORDER BY taxrate_id ASC LIMIT 1");
                $stmt->execute();
                $tmp = $stmt->fetchColumn();
                if ($tmp) $taxrate_id = (int)$tmp;
            }

            // Garante vínculos básicos (para evitar telas vazias)
            $stmt = db()->prepare("SELECT COUNT(*) FROM unit_to_store WHERE store_id = ? AND uunit_id = ?");
            $stmt->execute([$store_id, $unit_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                db()->prepare("INSERT INTO unit_to_store (uunit_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)")
                    ->execute([$unit_id, $store_id]);
            }

            $stmt = db()->prepare("SELECT COUNT(*) FROM box_to_store WHERE store_id = ? AND box_id = ?");
            $stmt->execute([$store_id, $box_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                db()->prepare("INSERT INTO box_to_store (box_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)")
                    ->execute([$box_id, $store_id]);
            }

            $stmt = db()->prepare("SELECT COUNT(*) FROM supplier_to_store WHERE store_id = ? AND sup_id = ?");
            $stmt->execute([$store_id, $sup_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                db()->prepare("INSERT INTO supplier_to_store (sup_id, store_id, balance, status, sort_order) VALUES (?, ?, 0.0000, 1, 0)")
                    ->execute([$sup_id, $store_id]);
            }

            $stmt = db()->prepare("SELECT COUNT(*) FROM brand_to_store WHERE store_id = ? AND brand_id = ?");
            $stmt->execute([$store_id, $brand_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                db()->prepare("INSERT INTO brand_to_store (brand_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)")
                    ->execute([$brand_id, $store_id]);
            }

            $stmt = db()->prepare("SELECT COUNT(*) FROM category_to_store WHERE store_id = ? AND ccategory_id = ?");
            $stmt->execute([$store_id, $category_id]);
            if ((int)$stmt->fetchColumn() === 0) {
                db()->prepare("INSERT INTO category_to_store (ccategory_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)")
                    ->execute([$category_id, $store_id]);
            }

            $today = date('Y-m-d');
            $e_date = date('Y-m-d', strtotime('+2 years'));
            $pref = serialize([]);

            // Produtos DEMO por loja: SERVICE (não exige compra/estoque)
            $demo_products = [
                [
                    'p_name' => '[Demo] Produto Teste 01',
                    'p_code' => 'DEMO-' . (int)$store_id . '-01',
                    'sell_price' => 199.90,
                    'purchase_price' => 120.00,
                    // Estoque grande só para exibição (service não desconta)
                    'qty' => 99999,
                ],
                [
                    'p_name' => '[Demo] Produto Teste 02',
                    'p_code' => 'DEMO-' . (int)$store_id . '-02',
                    'sell_price' => 59.90,
                    'purchase_price' => 30.00,
                    // Estoque grande só para exibição (service não desconta)
                    'qty' => 99999,
                ],
            ];

            // Se já existem demos, atualiza para SERVICE + Sem Taxa (corrige lojas já criadas)
            $demo_found = false;
            foreach ($demo_products as $p) {
                $stmt = db()->prepare("SELECT p_id FROM products WHERE p_code = ? LIMIT 1");
                $stmt->execute([$p['p_code']]);
                $product_id = (int)$stmt->fetchColumn();
                if ($product_id > 0) {
                    $demo_found = true;

                    // Garante que é SERVICE (não exige purchase_item)
                    db()->prepare("UPDATE products SET p_type = 'service', category_id = ?, unit_id = ? WHERE p_id = ?")
                        ->execute([(int)$category_id, (int)$unit_id, (int)$product_id]);

                    // Garante vínculo na loja com Sem Taxa
                    $stmt2 = db()->prepare("SELECT COUNT(*) FROM product_to_store WHERE store_id = ? AND product_id = ?");
                    $stmt2->execute([(int)$store_id, (int)$product_id]);
                    $exists = (int)$stmt2->fetchColumn() > 0;
                    if ($exists) {
                        db()->prepare("UPDATE product_to_store SET purchase_price = ?, sell_price = ?, quantity_in_stock = ?, sup_id = ?, brand_id = ?, box_id = ?, taxrate_id = ?, tax_method = 'exclusive' WHERE store_id = ? AND product_id = ?")
                            ->execute([
                                (float)$p['purchase_price'],
                                (float)$p['sell_price'],
                                (float)$p['qty'],
                                (int)$sup_id,
                                (int)$brand_id,
                                (int)$box_id,
                                (int)$taxrate_id,
                                (int)$store_id,
                                (int)$product_id,
                            ]);
                    } else {
                        db()->prepare("INSERT INTO product_to_store (product_id, store_id, purchase_price, sell_price, quantity_in_stock, alert_quantity, sup_id, brand_id, box_id, taxrate_id, tax_method, preference, e_date, p_date, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)")
                            ->execute([
                                (int)$product_id,
                                (int)$store_id,
                                (float)$p['purchase_price'],
                                (float)$p['sell_price'],
                                (float)$p['qty'],
                                5,
                                (int)$sup_id,
                                (int)$brand_id,
                                (int)$box_id,
                                (int)$taxrate_id,
                                'exclusive',
                                $pref,
                                $e_date,
                                $today,
                            ]);
                    }
                }
            }

            if ($demo_found) {
                return true;
            }

            // Se a loja já possui produtos vinculados, não cria demos.
            $stmt = db()->prepare("SELECT COUNT(*) FROM product_to_store WHERE store_id = ?");
            $stmt->execute([(int)$store_id]);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }

            // Loja vazia -> cria demos
            foreach ($demo_products as $p) {
                $ins = db()->prepare("INSERT INTO products (p_type, p_name, p_code, hsn_code, barcode_symbology, category_id, unit_id, p_image, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    'service',
                    $p['p_name'],
                    $p['p_code'],
                    null,
                    'code128',
                    $category_id,
                    $unit_id,
                    null,
                    'Produto de demonstração criado automaticamente para a loja ' . (int)$store_id,
                ]);
                $product_id = (int)db()->lastInsertId();

                db()->prepare("INSERT INTO product_to_store (product_id, store_id, purchase_price, sell_price, quantity_in_stock, alert_quantity, sup_id, brand_id, box_id, taxrate_id, tax_method, preference, e_date, p_date, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)")
                    ->execute([
                        (int)$product_id,
                        (int)$store_id,
                        (float)$p['purchase_price'],
                        (float)$p['sell_price'],
                        (float)$p['qty'],
                        5,
                        (int)$sup_id,
                        (int)$brand_id,
                        (int)$box_id,
                        (int)$taxrate_id,
                        'exclusive',
                        $pref,
                        $e_date,
                        $today,
                    ]);
            }

            return true;
        } catch (Exception $e) {
            error_log('Error creating demo products: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('create_sample_category')) {
    function create_sample_category($store_id) {
        // Inserir categoria
        $statement = db()->prepare("
            INSERT INTO `categorys` (category_name, category_slug, parent_id, category_details, category_image, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $statement->execute([
            'Exemplo - Eletrônicos',
            'exemplo-eletronicos',
            0, // parent_id
            'Categoria de exemplo - Produtos eletrônicos diversos',
            '', // category_image
            date('Y-m-d H:i:s')
        ]);
        
        $category_id = db()->lastInsertId();
        
        // Vincular categoria à loja
        $link = db()->prepare("
            INSERT INTO `category_to_store` (ccategory_id, store_id) 
            VALUES (?, ?)
        ");
        $link->execute([$category_id, $store_id]);
        
        return $category_id;
    }
}

if (!function_exists('create_sample_supplier')) {
    function create_sample_supplier($store_id) {
        // Inserir fornecedor
        $statement = db()->prepare("
            INSERT INTO `suppliers` (sup_name, code_name, sup_mobile, sup_email, gtin, sup_address, sup_city, sup_state, sup_country, sup_details, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $statement->execute([
            'Fornecedor Exemplo LTDA',
            'fornecedor-exemplo',
            '(11) 98765-4321',
            'contato@fornecedorexemplo.com.br',
            '',
            'Rua Exemplo, 123',
            'São Paulo',
            'SP',
            'Brasil',
            'Fornecedor de exemplo para demonstração',
            date('Y-m-d H:i:s')
        ]);
        
        $supplier_id = db()->lastInsertId();
        
        // Vincular fornecedor à loja
        $link = db()->prepare("
            INSERT INTO `supplier_to_store` (sup_id, store_id, status, sort_order) 
            VALUES (?, ?, ?, ?)
        ");
        $link->execute([$supplier_id, $store_id, 1, 0]);
        
        return $supplier_id;
    }
}

if (!function_exists('create_sample_brand')) {
    function create_sample_brand($store_id) {
        // Inserir marca
        $statement = db()->prepare("
            INSERT INTO `brands` (brand_name, code_name, brand_details, brand_image, created_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $statement->execute([
            'Marca Exemplo',
            'marca-exemplo',
            'Marca de exemplo para demonstração',
            '',
            date('Y-m-d H:i:s')
        ]);
        
        $brand_id = db()->lastInsertId();
        
        // Vincular marca à loja
        $link = db()->prepare("
            INSERT INTO `brand_to_store` (brand_id, store_id, status, sort_order) 
            VALUES (?, ?, ?, ?)
        ");
        $link->execute([$brand_id, $store_id, 1, 0]);
        
        return $brand_id;
    }
}

if (!function_exists('create_sample_bank_account')) {
    function create_sample_bank_account($store_id) {
        // Inserir conta bancária
        $statement = db()->prepare("
            INSERT INTO `bank_accounts` (
                account_name, 
                account_details, 
                account_no, 
                contact_person, 
                phone_number, 
                url, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $statement->execute([
            'Conta Corrente Exemplo',
            'Conta bancária de exemplo para demonstração',
            '12345-6',
            'Responsável Financeiro',
            '(11) 98765-4321',
            '',
            date('Y-m-d H:i:s')
        ]);
        
        $account_id = db()->lastInsertId();
        
        // Vincular conta bancária à loja
        $link = db()->prepare("
            INSERT INTO `bank_account_to_store` (account_id, store_id, status, sort_order) 
            VALUES (?, ?, ?, ?)
        ");
        $link->execute([$account_id, $store_id, 1, 0]);
        
        return $account_id;
    }
}

if (!function_exists('create_sample_products')) {
    function create_sample_products($store_id, $category_id, $supplier_id, $brand_id) {
        $products = [
            [
                'name' => 'Mouse Sem Fio Exemplo',
                'code' => '7898000000018', // EAN-13 válido
                'unit' => 'Peça',
                'cost' => 35.00,
                'price' => 59.90,
                'stock' => 50,
                'alert_quantity' => 10,
                'description' => 'Mouse sem fio ergonômico - Produto de exemplo'
            ],
            [
                'name' => 'Teclado USB Exemplo',
                'code' => '7898000000025',
                'unit' => 'Peça',
                'cost' => 45.00,
                'price' => 79.90,
                'stock' => 30,
                'alert_quantity' => 5,
                'description' => 'Teclado USB padrão ABNT2 - Produto de exemplo'
            ],
            [
                'name' => 'Webcam HD Exemplo',
                'code' => '7898000000032',
                'unit' => 'Peça',
                'cost' => 85.00,
                'price' => 149.90,
                'stock' => 20,
                'alert_quantity' => 5,
                'description' => 'Webcam HD 720p com microfone - Produto de exemplo'
            ],
            [
                'name' => 'Headset com Microfone Exemplo',
                'code' => '7898000000049',
                'unit' => 'Peça',
                'cost' => 55.00,
                'price' => 99.90,
                'stock' => 40,
                'alert_quantity' => 8,
                'description' => 'Headset estéreo com microfone ajustável - Produto de exemplo'
            ]
        ];
        
        foreach ($products as $product) {
            // Insert product
            $stmt = db()->prepare("
                INSERT INTO `products` (
                    p_name,
                    p_code,
                    p_category,
                    p_brand,
                    p_unit,
                    p_cost,
                    p_price,
                    p_stock,
                    p_stock_alert_quantity,
                    p_description,
                    store_id,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $product['name'],
                $product['code'],
                $category_id,
                $brand_id,
                $product['unit'],
                $product['cost'],
                $product['price'],
                $product['stock'],
                $product['alert_quantity'],
                $product['description'],
                $store_id,
                1,
                date('Y-m-d H:i:s')
            ]);
            
            $product_id = db()->lastInsertId();
            
            // Link product to supplier
            $stmt = db()->prepare("
                INSERT INTO `product_to_supplier` (p_id, supplier_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$product_id, $supplier_id]);
        }
        
        return true;
    }
}
