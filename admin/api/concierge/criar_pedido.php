<?php
/**
 * API: api/concierge/criar_pedido.php
 * Cria um pedido originado via WhatsApp (Moda IA).
 */

if (!isset($tid)) {
    exit; // Apenas via webhook.php
}

require_once DIR_HELPER . 'ai_evolution.php';

try {
    $sqlStep = 'init';
    $rawInput = file_get_contents('php://input');
    $jsonBody = [];
    if ($rawInput !== '' && $rawInput !== false) {
        $tmpBody = json_decode($rawInput, true);
        if (is_array($tmpBody)) {
            $jsonBody = $tmpBody;
        }
    }

    $getField = static function(array $keys, $default = null) use ($request, $jsonBody) {
        foreach ($keys as $key) {
            if (isset($request->post[$key])) {
                $value = $request->post[$key];
                if (!(is_string($value) && trim($value) === '')) return $value;
            }
            if (isset($request->get[$key])) {
                $value = $request->get[$key];
                if (!(is_string($value) && trim($value) === '')) return $value;
            }
            if (isset($jsonBody[$key])) {
                $value = $jsonBody[$key];
                if (!(is_string($value) && trim($value) === '')) return $value;
            }
        }
        return $default;
    };

    // Captura flexível de parâmetros para tolerar variações do n8n/IA.
    $phoneRaw       = $getField(['phone', 'whatsapp', 'whatsapp_phone', 'from'], '');
    $itemsRaw       = $getField(['items', 'itens', 'order_items'], '');
    $paymentMethod  = trim((string)$getField(['payment_method', 'payment', 'metodo_pagamento'], 'pix'));
    $customerName   = trim((string)$getField(['customer_name', 'name', 'cliente_nome'], ''));
    $customerName   = $customerName !== '' ? $customerName : 'Cliente';
    $phone          = preg_replace('/\D+/', '', (string)$phoneRaw);

    if ($phone === '' || empty($itemsRaw)) {
        throw new Exception('Número de WhatsApp e itens do pedido não informados.');
    }

    // --- PARSING ROBUSTO DOS ITENS ---
    $itemsData = [];
    if (is_array($itemsRaw)) {
        $itemsData = $itemsRaw;
    } else {
        // Se for string, tenta decodificar de várias formas
        $itemsData = json_decode((string)$itemsRaw, true);
        
        if (!is_array($itemsData)) {
            // Tenta decodificar entidades HTML (ex: &quot;) e remover escapes
            $cleaned = html_entity_decode((string)$itemsRaw, ENT_QUOTES, 'UTF-8');
            $cleaned = stripslashes($cleaned);
            $itemsData = json_decode($cleaned, true);
        }
    }

    if (!is_array($itemsData) || empty($itemsData)) {
        throw new Exception('Itens do pedido inválidos ou formato JSON incorreto.');
    }

    db()->beginTransaction();

    $totalAmount = 0.0;
    $orderItems  = [];

    foreach ($itemsData as $item) {
        // Ignorar itens vazios ou malformados
        if (!is_array($item)) continue;

        $vid = (int)($item['variant_id'] ?? 0);
        $sku = trim((string)($item['sku'] ?? ''));
        $qty = (int)($item['qty'] ?? $item['quantidade'] ?? 1);

        // Bloquear placeholders não preenchidos (evita erros de estoque/banco)
        if (strpos($sku, '{{') !== false || strpos($sku, '}}') !== false) {
            throw new Exception("Erro: SKU contém placeholders '{{ }}'. Verifique a integração.");
        }

        if (!$vid && !$sku) {
            throw new Exception("Item inválido: cada item deve ter um SKU ou variant_id.");
        }
        if ($qty <= 0) $qty = 1;

        // Buscar variante e modelo
        $sqlVariant = "
            SELECT v.*, m.name AS model_name
            FROM   ai_catalogo_variants v
            INNER JOIN ai_catalogo_models m ON m.id = v.model_id
            WHERE  v.tenant_id = :tid AND v.is_active = 1
        ";
        
        $paramsVariant = [':tid' => $tid];
        if ($vid > 0) {
            $sqlVariant .= " AND v.id = :vid";
            $paramsVariant[':vid'] = $vid;
        } else {
            $sqlVariant .= " AND v.sku = :sku";
            $paramsVariant[':sku'] = $sku;
        }
        $sqlVariant .= " LIMIT 1";

        $sqlStep = 'buscar_variante';
        $stmt = db()->prepare($sqlVariant);
        $stmt->execute($paramsVariant);
        $variant = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se não encontrou a variante, verifica se o SKU enviado é na verdade um SKU PAI (modelo)
        if (!$variant && $sku !== '') {
            $sqlStep = 'validar_sku_pai';
            $stmtModelSku = db()->prepare("
                SELECT id, name
                FROM ai_catalogo_models
                WHERE tenant_id = :tid AND is_active = 1 AND sku = :sku
                LIMIT 1
            ");
            $stmtModelSku->execute([':tid' => $tid, ':sku' => $sku]);
            $modelFound = $stmtModelSku->fetch(PDO::FETCH_ASSOC);

            if ($modelFound) {
                throw new Exception("O SKU '{$sku}' pertence ao modelo '{$modelFound['name']}', mas não possui estoque próprio para venda direta. Por favor, selecione uma variante (cor/tamanho) específica ou cadastre o estoque para este SKU.");
            }
        }

        if (!$variant) {
            $idStr = $vid ?: $sku;
            throw new Exception("Produto não encontrado ou indisponível (ID/SKU: {$idStr}).");
        }
        
        $vid = (int)$variant['id'];

        if ((int)$variant['stock_qty'] < $qty) {
            throw new Exception("Estoque insuficiente para '{$variant['model_name']}' (Disponível: {$variant['stock_qty']}).");
        }

        $price    = (float)$variant['price'];
        $subtotal = $price * $qty;
        $totalAmount += $subtotal;

        $orderItems[] = [
            'variant_id' => $vid,
            'model_name' => $variant['model_name'],
            'color'      => $variant['color'],
            'size'       => $variant['size'],
            'qty'        => $qty,
            'unit_price' => $price,
            'subtotal'   => $subtotal,
        ];
    }

    if (empty($orderItems)) {
        throw new Exception('Nenhum item válido foi processado.');
    }

    // Criar o pedido
    $sqlStep = 'insert_ai_orders';
    $stmt = db()->prepare("
        INSERT INTO ai_orders (tenant_id, whatsapp_phone, customer_name, status, total_amount, payment_method, moved_by_ia, created_at, updated_at)
        VALUES (:tid, :phone, :name, 'pendente', :total, :pm, 1, NOW(), NOW())
    ");
    $stmt->execute([
        ':tid'   => $tid,
        ':phone' => $phone,
        ':name'  => $customerName,
        ':total' => $totalAmount,
        ':pm'    => $paymentMethod,
    ]);
    $orderId = (int)db()->lastInsertId();

    $paymentRef = strtoupper($paymentMethod) . '-' . $orderId . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

    // Link público de pagamento — checkout_pix.php para Pix, fallback admin para outros métodos
    if (strtolower($paymentMethod) === 'pix') {
        $paymentLink = rtrim(ROOT_URL, '/') . '/checkout_pix.php?order_id=' . $orderId;
    } else {
        $paymentLink = rtrim(ROOT_URL, '/') . '/admin/concierge_pix.php?order_id=' . $orderId;
    }

    $sqlStep = 'update_payment_ref_link';
    db()->prepare("UPDATE ai_orders SET payment_ref = :pref, payment_link = :plink WHERE id = :oid")
        ->execute([':pref' => $paymentRef, ':plink' => $paymentLink, ':oid' => $orderId]);

    // Criar itens e baixar estoque
    // NOTA: db() retorna o singleton Database; prepare() sobrescreve $this->db na mesma instância.
    // Por isso o prepare do INSERT é feito DENTRO do loop — assim cada par prepare→execute
    // é atômico e não há sobreposição entre as duas queries.
    foreach ($orderItems as $item) {
        $sqlStep = 'insert_ai_order_item';
        db()->prepare("
            INSERT INTO ai_order_items (order_id, variant_id, model_name, color, size, qty, unit_price, subtotal)
            VALUES (:oid, :vid, :mname, :color, :size, :qty, :price, :subtotal)
        ")->execute([
            ':oid'      => $orderId,
            ':vid'      => $item['variant_id'],
            ':mname'    => $item['model_name'],
            ':color'    => $item['color'],
            ':size'     => $item['size'],
            ':qty'      => $item['qty'],
            ':price'    => $item['unit_price'],
            ':subtotal' => $item['subtotal'],
        ]);

        $sqlStep = 'update_stock_variant';
        db()->prepare("UPDATE ai_catalogo_variants SET stock_qty = stock_qty - :qty WHERE id = :id")
            ->execute([':qty' => $item['qty'], ':id' => $item['variant_id']]);
    }

    db()->commit();

    ai_notify_store_new_order($tid, $orderId);

    echo json_encode([
        'success'      => true,
        'message'      => 'Pedido criado com sucesso!',
        'order_id'     => $orderId,
        'total'        => $totalAmount,
        'payment_link' => $paymentLink,   // URL pública — envie este link ao cliente via WhatsApp
        'checkout_url' => $paymentLink,   // alias explícito para facilitar mapeamento no n8n
        'status'       => 'pendente',
        'payment_method' => $paymentMethod,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    http_response_code(422);
    
    // Log detalhado para o n8n conseguir depurar o que a IA enviou
    $debug = [
        'sql_step' => $sqlStep ?? 'unknown',
        'exception_file' => $e->getFile(),
        'exception_line' => $e->getLine(),
        'received_phone_raw' => $phoneRaw ?? 'Não capturado',
        'received_phone_normalized' => $phone ?? 'Não capturado',
        'received_items' => $itemsRaw ?? 'Não capturado',
        'items_type'    => gettype($itemsRaw ?? null),
        'method'        => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'content_type'  => $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'
    ];

    echo json_encode([
        'success' => false, 
        'error' => true, 
        'message' => $e->getMessage(),
        'debug' => $debug
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
