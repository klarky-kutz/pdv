<?php
/**
 * API: Storage Usage
 * Retorna informações de uso de armazenamento do tenant
 */

session_start();
require_once dirname(__DIR__, 2) . '/_init.php';

header('Content-Type: application/json; charset=utf-8');

// Verifica autenticação
if (!function_exists('is_loggedin') || !is_loggedin()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $pdo = db();
    
    // Obtém tenant_id
    $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    if ($tenantId <= 0) {
        // Tenta obter do usuário
        $userId = user_id();
        if ($userId) {
            $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $tenantId = $row ? (int)$row['tenant_id'] : 0;
        }
    }
    
    // Calcula uso de armazenamento
    $usedBytes = 0;
    $limitMb = 0;
    
    // Calcula do sistema de arquivos - ISOLADO POR TENANT
    $basePath = defined('FILEMANAGERPATH') ? FILEMANAGERPATH : (ROOT . '/storage/products/');
    
    // Se tiver tenant_id, usa pasta isolada do tenant
    if ($tenantId > 0) {
        $storagePath = rtrim($basePath, '/\\') . '/' . $tenantId . '/';
    } else {
        // Fallback para path base (admin ou single-tenant)
        $storagePath = $basePath;
    }
    
    if (is_dir($storagePath)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storagePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($it as $file) {
            if ($file->isFile()) {
                $usedBytes += $file->getSize();
            }
        }
    }
    
    // Obtém limite do plano
    if ($tenantId > 0) {
        try {
            // Verifica se coluna storage_mb existe
            $checkCol = $pdo->query("SHOW COLUMNS FROM plans LIKE 'storage_mb'");
            $hasStorageMb = $checkCol && $checkCol->rowCount() > 0;
            
            if ($hasStorageMb) {
                $stmt = $pdo->prepare("
                    SELECT p.storage_mb 
                    FROM tenants t
                    LEFT JOIN plans p ON t.plan_id = p.plan_id
                    WHERE t.tenant_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$tenantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $limitMb = $row ? (int)$row['storage_mb'] : 0;
            }
        } catch (Exception $e) {
            // Ignora erro
        }
    }
    
    // Converte para MB
    $usedMb = round($usedBytes / (1024 * 1024), 2);
    $unlimited = ($limitMb <= 0);
    $percent = $unlimited ? 0 : round(($usedMb / $limitMb) * 100, 1);
    $remainingMb = $unlimited ? -1 : max(0, $limitMb - $usedMb);
    
    // Determina cor
    $colorClass = 'success';
    if (!$unlimited) {
        if ($percent >= 90) {
            $colorClass = 'danger';
        } elseif ($percent >= 70) {
            $colorClass = 'warning';
        }
    }
    
    echo json_encode([
        'success' => true,
        'usage' => [
            'used_mb' => $usedMb,
            'limit_mb' => $limitMb,
            'remaining_mb' => $remainingMb,
            'percent' => min(100, $percent),
            'unlimited' => $unlimited,
            'color_class' => $colorClass,
            'is_critical' => $percent >= 90,
            'is_warning' => $percent >= 70 && $percent < 90,
            'is_full' => $percent >= 100
        ],
        'display' => [
            'used' => number_format($usedMb, 2, ',', '.') . ' MB',
            'limit' => $unlimited ? 'Ilimitado' : number_format($limitMb, 0, ',', '.') . ' MB',
            'percent' => $percent . '%'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
