<?php 
ob_start();
session_start();
include("../_init.php");

$document->setTitle('Debug has_permission()');
include("header.php"); 
include("left_sidebar.php");
?>

<div class="content-wrapper">
  <section class="content-header">
    <h1>🔍 Debug Profundo - has_permission()</h1>
  </section>

  <section class="content">
    <div class="box box-danger">
      <div class="box-header with-border">
        <h3 class="box-title">🧪 Teste com Debug Ativado</h3>
      </div>
      <div class="box-body">
        <?php
        global $user;
        
        echo '<h4>📊 Variáveis Globais:</h4>';
        echo '<table class="table table-bordered table-sm">';
        echo '<tr><td><strong>$user existe?</strong></td><td>' . (isset($user) ? '✅ SIM' : '❌ NÃO') . '</td></tr>';
        
        if (isset($user)) {
            echo '<tr><td><strong>Tipo de $user:</strong></td><td>' . get_class($user) . '</td></tr>';
        }
        
        echo '</table>';
        
        // Testar uma permissão específica com debug manual
        echo '<h4 class="mt-4">🔍 Teste Manual: read_dashboard</h4>';
        
        $testPerm = 'read_dashboard';
        $type = 'access';
        
        echo '<div class="well" style="background: #f5f5f5; padding: 15px; font-family: monospace; font-size: 12px;">';
        
        // Passo 1: Verificar se é admin
        echo '<strong>PASSO 1: Verificar se é Admin (group_id == 1)</strong><br>';
        $groupId = user_group_id();
        echo 'user_group_id() = ' . $groupId . '<br>';
        
        if ($groupId == 1) {
            echo '<span class="label label-success">✅ É ADMIN - Tem privilégios especiais</span><br>';
        } else {
            echo '<span class="label label-warning">⚠️ NÃO é admin (group_id != 1) - Precisa verificar RBAC</span><br>';
        }
        
        echo '<br>';
        
        // Passo 2: Verificar RBAC
        echo '<strong>PASSO 2: Verificar RBAC ($user->hasPermission)</strong><br>';
        
        if (isset($user)) {
            $allowedByRbac = $user->hasPermission($type, $testPerm);
            echo '$user->hasPermission("' . $type . '", "' . $testPerm . '") = ' . ($allowedByRbac ? '<span class="label label-success">TRUE ✅</span>' : '<span class="label label-danger">FALSE ❌</span>') . '<br>';
            
            if (!$allowedByRbac) {
                echo '<span class="label label-danger">❌ BLOQUEADO NO RBAC - Função retorna FALSE aqui</span><br>';
            } else {
                echo '<span class="label label-success">✅ PASSOU NO RBAC - Continua para Feature Gating</span><br>';
            }
        } else {
            echo '<span class="label label-danger">❌ $user não está definido!</span><br>';
        }
        
        echo '<br>';
        
        // Passo 3: Verificar Feature Gating
        echo '<strong>PASSO 3: Feature Gating (SaaS)</strong><br>';
        
        try {
            if (!class_exists('SaasLimitsBridge')) {
                $saasLimitsPath = defined('ROOT') ? (ROOT . '/../saas/includes/SaasLimitsBridge.php') : null;
                if ($saasLimitsPath && file_exists($saasLimitsPath)) {
                    require_once $saasLimitsPath;
                    echo 'SaasLimitsBridge carregado ✅<br>';
                } else {
                    echo 'SaasLimitsBridge NÃO encontrado ⚠️<br>';
                }
            } else {
                echo 'SaasLimitsBridge já estava carregado ✅<br>';
            }
            
            if (class_exists('SaasLimitsBridge') && function_exists('db')) {
                echo 'SaasLimitsBridge e db() disponíveis ✅<br>';
                
                // Resolver tenant_id
                $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
                $uid = function_exists('user_id') ? (int)user_id() : 0;
                
                echo 'Session tenant_id: ' . $sessionTid . '<br>';
                echo 'user_id(): ' . $uid . '<br>';
                
                $tenantId = SaasLimitsBridge::resolveTenantId(db(), $uid, $sessionTid > 0 ? $sessionTid : null);
                echo 'Tenant ID resolvido: <strong>' . $tenantId . '</strong><br>';
                
                if ((int)$tenantId <= 0) {
                    echo '<span class="label label-warning">⚠️ Sem tenant - Modo legacy (permissivo)</span><br>';
                } else {
                    // Buscar features do plano
                    $features = SaasLimitsBridge::getPlanFeatures(db(), (int)$tenantId);
                    echo 'Features do plano: <pre>' . print_r($features, true) . '</pre>';
                    
                    $isPermissive = in_array('*', $features, true);
                    
                    if ($isPermissive) {
                        echo '<span class="label label-success">✅ PLANO PERMISSIVO (wildcard *) - Libera tudo</span><br>';
                    } else {
                        echo '<span class="label label-warning">⚠️ Plano RESTRITIVO - Verifica feature por feature</span><br>';
                        
                        $allowedByPlan = in_array($testPerm, $features, true);
                        echo 'Permissão "' . $testPerm . '" está nas features? ' . ($allowedByPlan ? '<span class="label label-success">SIM ✅</span>' : '<span class="label label-danger">NÃO ❌</span>') . '<br>';
                        
                        if (!$allowedByPlan) {
                            echo '<span class="label label-danger">❌ BLOQUEADO NO FEATURE GATING</span><br>';
                        }
                    }
                }
            } else {
                echo '<span class="label label-warning">⚠️ SaasLimitsBridge não disponível - Sistema permissivo</span><br>';
            }
            
        } catch (Throwable $e) {
            echo '<span class="label label-danger">❌ ERRO: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        
        echo '</div>';
        
        // Resultado final
        echo '<h4 class="mt-4">📋 Resultado Final:</h4>';
        $finalResult = has_permission($type, $testPerm);
        
        if ($finalResult) {
            echo '<div class="alert alert-success">';
            echo '<h5><i class="fa fa-check-circle"></i> ✅ PERMITIDO</h5>';
            echo '<p>A função <code>has_permission("' . $type . '", "' . $testPerm . '")</code> retornou <strong>TRUE</strong></p>';
            echo '</div>';
        } else {
            echo '<div class="alert alert-danger">';
            echo '<h5><i class="fa fa-times-circle"></i> ❌ BLOQUEADO</h5>';
            echo '<p>A função <code>has_permission("' . $type . '", "' . $testPerm . '")</code> retornou <strong>FALSE</strong></p>';
            echo '<p><strong>Causa:</strong> Verifique qual passo retornou FALSE acima</p>';
            echo '</div>';
        }
        
        // Mostrar permissões raw do grupo
        echo '<h4 class="mt-4">🔑 Permissões RAW do Grupo:</h4>';
        
        $stmt = db()->prepare("SELECT permission FROM user_group WHERE group_id = ?");
        $stmt->execute([user_group_id()]);
        $groupData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($groupData) {
            $permRaw = $groupData['permission'];
            $unserialized = @unserialize($permRaw);
            
            if (is_array($unserialized) && isset($unserialized['access'])) {
                echo '<div class="alert alert-info">';
                echo '<p><strong>Total de permissões no grupo:</strong> ' . count($unserialized['access']) . '</p>';
                
                // Verificar se read_dashboard existe
                if (isset($unserialized['access'][$testPerm])) {
                    echo '<p><strong>Permissão "' . $testPerm . '" no grupo:</strong> <span class="label label-success">✅ EXISTE</span></p>';
                    echo '<p><strong>Valor:</strong> ' . htmlspecialchars($unserialized['access'][$testPerm]) . '</p>';
                } else {
                    echo '<p><strong>Permissão "' . $testPerm . '" no grupo:</strong> <span class="label label-danger">❌ NÃO EXISTE</span></p>';
                }
                
                echo '<details>';
                echo '<summary>Ver todas as permissões do grupo (primeiras 50)</summary>';
                echo '<pre style="max-height: 400px; overflow: auto;">';
                $count = 0;
                foreach ($unserialized['access'] as $key => $val) {
                    if ($count++ >= 50) {
                        echo '... (restante omitido)';
                        break;
                    }
                    echo htmlspecialchars($key) . ' = ' . htmlspecialchars($val) . "\n";
                }
                echo '</pre>';
                echo '</details>';
                echo '</div>';
            }
        }
        ?>
      </div>
    </div>
  </section>
</div>

<?php include("footer.php"); ?>
