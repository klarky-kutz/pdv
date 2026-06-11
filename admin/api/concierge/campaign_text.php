<?php
ob_start();
session_start();
include('../../_init.php');

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

header('Content-Type: application/json; charset=UTF-8');

function concierge_text_extract_token(): string
{
    $token = trim((string)($_SERVER['HTTP_X_CONCIERGE_TOKEN'] ?? ''));
    if ($token === '') {
        $token = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    }
    if (stripos($token, 'Bearer ') === 0) {
        $token = trim(substr($token, 7));
    }
    if ($token === '') {
        $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    }
    return $token;
}

function concierge_text_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function concierge_text_forbid_if_no_permission(bool $isTokenAuth): void
{
    if ($isTokenAuth || user_group_id() == 1) {
        return;
    }
    if (!has_permission('access', 'concierge_groups_access')) {
        http_response_code(403);
        echo json_encode(ai_groups_response(true, 'Permissão insuficiente.', null));
        exit;
    }
}

function concierge_text_price_label(float $price): string
{
    if ($price <= 0) {
        return '';
    }
    return 'R$ ' . number_format($price, 2, ',', '.');
}

function concierge_text_build(array $input): array
{
    $tone = strtolower(trim((string)($input['tone'] ?? 'casual')));
    $cta = trim((string)($input['cta'] ?? 'Me chama no privado!'));
    $objective = trim((string)($input['objective'] ?? 'divulgação'));
    $storeName = trim((string)($input['store_name'] ?? 'sua loja'));
    $productName = trim((string)($input['product_name'] ?? 'produto especial'));
    $productPrice = (float)($input['product_price'] ?? 0);
    $productDescription = trim((string)($input['product_description'] ?? ''));
    $productSku = trim((string)($input['product_variant_sku'] ?? $input['product_sku'] ?? ''));
    $highlightsRaw = $input['highlights'] ?? $input['features'] ?? [];
    $highlights = [];
    if (is_array($highlightsRaw)) {
        foreach ($highlightsRaw as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $highlights[] = $value;
            }
        }
    } elseif (is_string($highlightsRaw)) {
        foreach (explode(',', $highlightsRaw) as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $highlights[] = $value;
            }
        }
    }
    $highlights = array_slice(array_values(array_unique($highlights)), 0, 3);
    $priceLabel = concierge_text_price_label($productPrice);
    $highlightsText = '';
    if (!empty($highlights)) {
        $highlightsText = '• ' . implode("\n• ", $highlights);
    }
    if ($productDescription !== '') {
        if ($highlightsText !== '') {
            $highlightsText = $productDescription . "\n\n" . $highlightsText;
        } else {
            $highlightsText = $productDescription;
        }
    }
    if ($productSku !== '' && stripos($highlightsText, $productSku) === false) {
        $skuLine = 'SKU: ' . $productSku;
        if ($highlightsText !== '') {
            $highlightsText = $skuLine . "\n" . $highlightsText;
        } else {
            $highlightsText = $skuLine;
        }
    }

    $toneTemplates = [
        'desejo' => [
            [
                'headline' => '✨ Novidade exclusiva para quem ama estilo',
                'prefix' => 'Uma escolha elegante para elevar seu visual.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '✨ Novidade exclusiva para quem ama estilo';
                    $b[] = '';
                    $b[] = '👗 ' . $productName;
                    $b[] = 'Uma escolha elegante para elevar seu visual.';

                    if ($highlightsText !== '') {
                        $b[] = '';
                        $b[] = $highlightsText;
                    }
                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '— Equipe ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '💎 A peça que faltava no seu guarda-roupa',
                'prefix' => 'Elegância e qualidade em cada detalhe.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '💎 A peça que faltava no seu guarda-roupa';
                    $b[] = '';
                    $b[] = '✨ ' . $productName;
                    $b[] = 'Elegância e qualidade em cada detalhe.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '— ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '🌟 Seu novo item favorito chegou',
                'prefix' => 'Design exclusivo para você brilhar.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🌟 Seu novo item favorito chegou';
                    $b[] = '';
                    $b[] = '🎀 ' . $productName;
                    $b[] = 'Design exclusivo para você brilhar.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = 'Equipe ' . $storeName . ' ❤️';
                    return $b;
                }
            ]
        ],
        'urgencia' => [
            [
                'headline' => '🔥 Oportunidade por tempo limitado',
                'prefix' => 'Corre porque as unidades são limitadas.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🔥 Oportunidade por tempo limitado';
                    $b[] = '';
                    $b[] = '👗 ' . $productName;
                    $b[] = 'Corre porque as unidades são limitadas.';

                    if ($highlightsText !== '') {
                        $b[] = '';
                        $b[] = $highlightsText;
                    }
                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '— Equipe ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '⚡ Últimas unidades disponíveis!',
                'prefix' => 'Não deixe essa chance passar.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '⚡ Últimas unidades disponíveis!';
                    $b[] = '';
                    $b[] = '🔥 ' . $productName;
                    $b[] = 'Não deixe essa chance passar.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta . ' AGORA!';
                    $b[] = '';
                    $b[] = '— ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '🚨 Corre que está acabando!',
                'prefix' => 'Estoque reduzido, não perca!',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🚨 Corre que está acabando!';
                    $b[] = '';
                    $b[] = '⚠️ ' . $productName;
                    $b[] = 'Estoque reduzido, não perca!';

                    $b[] = '';
                    $b[] = '📲 ' . $cta . ' antes que acabe!';
                    $b[] = '';
                    $b[] = 'Equipe ' . $storeName;
                    return $b;
                }
            ]
        ],
        'casual' => [
            [
                'headline' => '💚 Chegou novidade que combina com você',
                'prefix' => 'Conforto e estilo no mesmo produto.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '💚 Chegou novidade que combina com você';
                    $b[] = '';
                    $b[] = '👗 ' . $productName;
                    $b[] = 'Conforto e estilo no mesmo produto.';

                    if ($highlightsText !== '') {
                        $b[] = '';
                        $b[] = $highlightsText;
                    }
                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '— Equipe ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '😊 A peça perfeita para o dia a dia',
                'prefix' => 'Estilo fácil e confortável.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '😊 A peça perfeita para o dia a dia';
                    $b[] = '';
                    $b[] = '✨ ' . $productName;
                    $b[] = 'Estilo fácil e confortável.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = 'Abraço, ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '🌿 Novidade que você vai amar',
                'prefix' => 'Simples, charmosa e versátil.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🌿 Novidade que você vai amar';
                    $b[] = '';
                    $b[] = '👋 ' . $productName;
                    $b[] = 'Simples, charmosa e versátil.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = 'Beijos da ' . $storeName . ' 😘';
                    return $b;
                }
            ]
        ],
        'festivo' => [
            [
                'headline' => '🎉 Chegou a peça para destacar seu look',
                'prefix' => 'Perfeito para compor produções marcantes.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🎉 Chegou a peça para destacar seu look';
                    $b[] = '';
                    $b[] = '👗 ' . $productName;
                    $b[] = 'Perfeito para compor produções marcantes.';

                    if ($highlightsText !== '') {
                        $b[] = '';
                        $b[] = $highlightsText;
                    }
                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '— Equipe ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '✨ O item que vai fazer você brilhar',
                'prefix' => 'Perfeito para ocasiões especiais.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '✨ O item que vai fazer você brilhar';
                    $b[] = '';
                    $b[] = '🎊 ' . $productName;
                    $b[] = 'Perfeito para ocasiões especiais.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '🌟 ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '🎈 Novidade para seus melhores looks',
                'prefix' => 'Destaque-se com estilo e glamour.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🎈 Novidade para seus melhores looks';
                    $b[] = '';
                    $b[] = '💫 ' . $productName;
                    $b[] = 'Destaque-se com estilo e glamour.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = 'Equipe ' . $storeName . ' 🎉';
                    return $b;
                }
            ]
        ],
        'oferta' => [
            [
                'headline' => '💰 Oferta especial pra hoje',
                'prefix' => 'Condição especial para fechar agora.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '💰 Oferta especial pra hoje';
                    $b[] = '';
                    $b[] = '👗 ' . $productName;
                    $b[] = 'Condição especial para fechar agora.';

                    if ($highlightsText !== '') {
                        $b[] = '';
                        $b[] = $highlightsText;
                    }
                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = '— Equipe ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '🎁 Oferta que você não pode perder',
                'prefix' => 'Preço incrível por tempo limitado.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '🎁 Oferta que você não pode perder';
                    $b[] = '';
                    $b[] = '🔥 ' . $productName;
                    $b[] = 'Preço incrível por tempo limitado.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta . ' antes que acabe!';
                    $b[] = '';
                    $b[] = '— ' . $storeName;
                    return $b;
                }
            ],
            [
                'headline' => '💸 Desconto especial para você',
                'prefix' => 'Aproveite essa condição única.',
                'body' => function() use ($productName, $highlightsText, $cta, $storeName) {
                    $b = [];
                    $b[] = '💸 Desconto especial para você';
                    $b[] = '';
                    $b[] = '🎉 ' . $productName;
                    $b[] = 'Aproveite essa condição única.';

                    $b[] = '';
                    $b[] = '📲 ' . $cta;
                    $b[] = '';
                    $b[] = 'Equipe ' . $storeName . ' 💚';
                    return $b;
                }
            ]
        ]
    ];

    $templates = $toneTemplates[$tone] ?? $toneTemplates['casual'];
    $examples = [];

    foreach ($templates as $template) {
        $bodyArray = $template['body']();
        $hashtags = [];
        foreach (preg_split('/\s+/', mb_strtolower($productName, 'UTF-8')) as $token) {
            $token = preg_replace('/[^a-z0-9à-ÿ]/iu', '', $token);
            if (mb_strlen($token, 'UTF-8') >= 4) {
                $hashtags[] = '#' . $token;
            }
        }
        $hashtags = array_slice(array_values(array_unique($hashtags)), 0, 3);

        $examples[] = [
            'title' => $template['headline'],
            'text' => implode("\n", $bodyArray),
            'cta' => $cta,
            'hashtags' => $hashtags,
            'tone' => $tone,
            'objective' => $objective,
        ];
    }

    return [
        'examples' => $examples,
        'tone' => $tone,
    ];
}

try {
    $json = concierge_text_json_body();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $tenantId = (int)($_GET['loja_id'] ?? $_POST['loja_id'] ?? $json['loja_id'] ?? $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? $json['tenant_id'] ?? 0);
    $token = concierge_text_extract_token();
    $isTokenAuth = false;

    if ($tenantId > 0 && $token !== '') {
        $stmt = db()->prepare('SELECT ai_webhook_token FROM stores WHERE store_id = :tid LIMIT 1');
        $stmt->execute([':tid' => $tenantId]);
        $storedToken = (string)$stmt->fetchColumn();
        if ($storedToken !== '' && hash_equals($storedToken, $token)) {
            $isTokenAuth = true;
        } else {
            http_response_code(401);
            echo json_encode(ai_groups_response(true, 'Token inválido.', null));
            exit;
        }
    }

    if (!$isTokenAuth) {
        if (!is_loggedin()) {
            http_response_code(401);
            echo json_encode(ai_groups_response(true, 'Sessão inválida.', null));
            exit;
        }
        $tenantId = ai_tenant_id();
        if ($tenantId <= 0) {
            throw new Exception('Tenant inválido.');
        }
        concierge_text_forbid_if_no_permission(false);
    }

    if (!ai_groups_plan_is_enabled($tenantId)) {
        http_response_code(402);
        echo json_encode(ai_groups_response(true, 'Módulo de grupos indisponível no plano.', ['blocked' => true]));
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(ai_groups_response(true, 'Método não suportado.', null));
        exit;
    }

    $productId = (int)($json['product_id'] ?? 0);
    if ($productId > 0 && function_exists('ai_get_catalogo_model')) {
        $product = ai_get_catalogo_model($productId);
        if (is_array($product)) {
            if (empty($json['product_name'])) {
                $json['product_name'] = $product['name'] ?? ($json['product_name'] ?? '');
            }
            if (!isset($json['product_price'])) {
                $json['product_price'] = (float)($product['min_price'] ?? $product['price'] ?? 0);
            }
            if (!isset($json['product_description'])) {
                $json['product_description'] = $product['description'] ?? '';
            }
        }
    }
    if (empty($json['store_name'])) {
        $json['store_name'] = ai_get_store_name($tenantId);
    }

    $generated = concierge_text_build($json);
    $responseData = ['generated' => $generated];
    
    // Para compatibilidade com versões anteriores, retorna o primeiro exemplo também como texto único
    if (!empty($generated['examples'])) {
        $responseData['generated']['text'] = $generated['examples'][0]['text'];
        $responseData['generated']['title'] = $generated['examples'][0]['title'];
        $responseData['generated']['cta'] = $generated['examples'][0]['cta'];
    }
    
    echo json_encode(ai_groups_response(false, 'Texto gerado com sucesso.', $responseData), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (http_response_code() === 200) {
        http_response_code(422);
    }
    echo json_encode(ai_groups_response(true, $e->getMessage(), null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
