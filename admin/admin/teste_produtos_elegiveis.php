<?php
ob_start();
session_start();
include('../_init.php');
require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

$tenantId = ai_tenant_id(); // Usa o tenant do usuário logado
$daysToReuse = 1;
echo "<h1>Teste de Produtos Elegíveis para Tenant ID: $tenantId</h1>";

// 1. Verificar quantos produtos existem ativos
$st1 = db()->prepare("SELECT COUNT(*) FROM ai_catalogo_models WHERE tenant_id = :tid AND is_active = 1");
$st1->execute([':tid' => $tenantId]);
$totalActive = (int)$st1->fetchColumn();
echo "<p><strong>Total de produtos ativos:</strong> $totalActive</p>";

// 1.5. Verificar TODOS os produtos (ativos e inativos)
echo "<hr><h3>TODOS os Produtos Cadastrados:</h3>";
try {
    $stAll = db()->prepare("SELECT id, name, is_active, cover_webp, main_price FROM ai_catalogo_models WHERE tenant_id = :tid ORDER BY id DESC LIMIT 50");
    $stAll->execute([':tid' => $tenantId]);
    $allProds = $stAll->fetchAll(PDO::FETCH_ASSOC);
    echo "<p><strong>Total de produtos (todos):</strong> " . count($allProds) . "</p>";
    if(count($allProds) > 0) {
        echo "<ul>";
        foreach($allProds as $p) {
            $status = $p['is_active'] ? '✅ Ativo' : '❌ Inativo';
            echo "<li>ID: {$p['id']} - Nome: {$p['name']} - Status: $status - Preço: {$p['main_price']} - Media: " . ($p['cover_webp'] ? 'Sim' : 'Não') . "</li>";
        }
        echo "</ul>";
    }
} catch (Throwable $e) {
    echo "<p style='color: red;'><strong>Erro na consulta todos:</strong> " . $e->getMessage() . "</p>";
}

// 2. Verificar quantos têm cover_webp
$st2 = db()->prepare("SELECT COUNT(*) FROM ai_catalogo_models WHERE tenant_id = :tid AND is_active = 1 AND (cover_webp IS NOT NULL AND cover_webp != '')");
$st2->execute([':tid' => $tenantId]);
$withMedia = (int)$st2->fetchColumn();
echo "<p><strong>Produtos ativos com imagem:</strong> $withMedia</p>";

// 3. Verificar quantos foram postados nos últimos X dias
$st3 = db()->prepare("SELECT COUNT(DISTINCT product_id) FROM concierge_status WHERE tenant_id = :tid AND product_id > 0 AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)");
$st3->execute([':tid' => $tenantId, ':days' => $daysToReuse]);
$recentlyPosted = (int)$st3->fetchColumn();
echo "<p><strong>Produtos postados nos últimos $daysToReuse dias:</strong> $recentlyPosted</p>";

// 4. Consultar os produtos elegíveis diretamente
echo "<hr><h3>Consulta de Produtos Elegíveis:</h3>";
try {
    $sql = "
        SELECT m.id, m.name, m.cover_webp, m.main_price
        FROM ai_catalogo_models m
        WHERE m.tenant_id = :tid1 
          AND m.is_active = 1
          AND (m.cover_webp IS NOT NULL AND m.cover_webp != '')
          AND m.id NOT IN (
              SELECT product_id FROM concierge_status 
              WHERE tenant_id = :tid2 AND product_id > 0 
                AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
          )
        ORDER BY RAND()
        LIMIT 20
    ";
    $stProds = db()->prepare($sql);
    $stProds->bindValue(':tid1', $tenantId, PDO::PARAM_INT);
    $stProds->bindValue(':tid2', $tenantId, PDO::PARAM_INT);
    $stProds->bindValue(':days', $daysToReuse, PDO::PARAM_INT);
    $stProds->execute();
    $prods = $stProds->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Produtos elegíveis encontrados:</strong> " . count($prods) . "</p>";
    
    if(count($prods) > 0) {
        echo "<ul>";
        foreach($prods as $p) {
            echo "<li>ID: {$p['id']} - Nome: {$p['name']} - Preço: {$p['main_price']} - Media: " . ($p['cover_webp'] ? 'Sim' : 'Não') . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Throwable $e) {
    echo "<p style='color: red;'><strong>Erro na consulta:</strong> " . $e->getMessage() . "</p>";
}
?>