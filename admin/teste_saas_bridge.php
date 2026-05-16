<?php
/**
 * Teste direto do SaasLimitsBridge
 */

ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

require_once __DIR__ . '/../../saas/includes/SaasLimitsBridge.php';

$tenantId = 279;
$pdo = db();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste SaasLimitsBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4" style="max-width: 900px;">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4>🔍 Teste SaasLimitsBridge - Tenant 279</h4>
        </div>
        <div class="card-body">
            
            <h5>1️⃣ Consulta Direta no Banco</h5>
            <?php
            $stmt = $pdo->prepare("
                SELECT t.tenant_id, t.plan_id, p.name AS plan_name, p.features_json
                FROM tenants t
                JOIN plans p ON p.plan_id = t.plan_id
                WHERE t.tenant_id = :tid
            ");
            $stmt->execute(['tid' => $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-bordered">';
            echo '<tr><th>Campo</th><th>Valor</th></tr>';
            echo '<tr><td>tenant_id</td><td>' . ($row['tenant_id'] ?? 'NULL') . '</td></tr>';
            echo '<tr><td>plan_id</td><td>' . ($row['plan_id'] ?? 'NULL') . '</td></tr>';
            echo '<tr><td>plan_name</td><td>' . htmlspecialchars($row['plan_name'] ?? 'NULL') . '</td></tr>';
            echo '<tr><td>features_json (raw)</td><td><code>' . htmlspecialchars($row['features_json'] ?? 'NULL') . '</code></td></tr>';
            
            $decoded = json_decode($row['features_json'] ?? '', true);
            echo '<tr><td>features_json (decoded)</td><td><pre>' . print_r($decoded, true) . '</pre></td></tr>';
            echo '</table>';
            echo '</div>';
            ?>
            
            <hr>
            
            <h5>2️⃣ Método SaasLimitsBridge::getTenantPlan()</h5>
            <?php
            $tenantPlan = SaasLimitsBridge::getTenantPlan($pdo, $tenantId);
            
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-bordered">';
            echo '<tr><th>Campo</th><th>Valor</th></tr>';
            
            if ($tenantPlan) {
                foreach ($tenantPlan as $key => $value) {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($key) . '</code></td>';
                    echo '<td>';
                    if ($key === 'features_json') {
                        echo '<strong>RAW:</strong> <code>' . htmlspecialchars($value ?? 'NULL') . '</code><br>';
                        $dec = json_decode($value ?? '', true);
                        echo '<strong>DECODED:</strong> <pre>' . print_r($dec, true) . '</pre>';
                    } else {
                        echo htmlspecialchars($value ?? 'NULL');
                    }
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="2" class="text-danger">NULL ou erro</td></tr>';
            }
            
            echo '</table>';
            echo '</div>';
            
            echo '<div class="alert alert-info">';
            echo '<strong>array_key_exists("features_json", $tenantPlan)?</strong> ';
            echo $tenantPlan && array_key_exists('features_json', $tenantPlan) ? '✅ SIM' : '❌ NÃO';
            echo '</div>';
            ?>
            
            <hr>
            
            <h5>3️⃣ Método SaasLimitsBridge::getPlanFeatures()</h5>
            <?php
            $features = SaasLimitsBridge::getPlanFeatures($pdo, $tenantId);
            
            echo '<div class="alert alert-warning">';
            echo '<strong>Resultado:</strong><br>';
            echo '<pre>' . print_r($features, true) . '</pre>';
            
            if (in_array('*', $features)) {
                echo '<span class="badge bg-success">✅ PERMISSIVO (contém "*")</span>';
            } elseif (empty($features)) {
                echo '<span class="badge bg-danger">❌ VAZIO (bloqueia tudo)</span>';
            } else {
                echo '<span class="badge bg-warning">⚠️ RESTRITIVO (' . count($features) . ' features)</span>';
            }
            echo '</div>';
            ?>
            
            <hr>
            
            <h5>4️⃣ Limpar Cache Static</h5>
            <form method="POST">
                <button type="submit" name="clear_cache" class="btn btn-warning">🗑️ Limpar Cache e Recarregar</button>
            </form>
            
            <?php
            if (isset($_POST['clear_cache'])) {
                // Forçar limpeza do cache static usando Reflection
                try {
                    $reflection = new ReflectionClass('SaasLimitsBridge');
                    $cacheProperty = $reflection->getProperty('tenantPlanCache');
                    $cacheProperty->setAccessible(true);
                    $cacheProperty->setValue(null, []);
                    
                    echo '<div class="alert alert-success mt-3">✅ Cache limpo! Recarregando...</div>';
                    echo '<script>setTimeout(function(){ location.reload(); }, 1000);</script>';
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger mt-3">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            ?>
            
        </div>
    </div>
    
    <div class="text-center mt-3">
        <a href="dashboard.php" class="btn btn-primary">← Voltar ao Dashboard</a>
    </div>
</div>
</body>
</html>
