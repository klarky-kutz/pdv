<?php
/**
 * API: Storage Usage
 * Retorna informações de uso de armazenamento do tenant
 */

session_start();
require_once dirname(__DIR__, 2) . '/_init.php';
require_once DIR_HELPER . 'ai_concierge.php';

header('Content-Type: application/json; charset=utf-8');

// Verifica autenticação
if (!function_exists('is_loggedin') || !is_loggedin()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    // Mesma resolução de tenant e mesma regra de cálculo usada no catálogo IA.
    $tenantId  = ai_resolve_filemanager_tenant_id();
    $usedBytes = ai_get_storage_usage_bytes();
    $limitMb   = ai_get_storage_limit_mb($tenantId);
    
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
