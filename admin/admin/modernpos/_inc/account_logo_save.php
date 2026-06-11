<?php
/**
 * API: Salvar logo do tenant
 * A logo é salva no tenant e também aplicada à primeira loja como padrão
 * POST /modernpos/_inc/account_logo_save.php
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../_init.php';

if (!function_exists('user_id') || !user_id()) {
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

try {
    $pdo = db();
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $removeLogo = isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1';
    
    if ($tenantId <= 0) {
        // Tenta pegar da sessão
        if (isset($_SESSION['tenant_id'])) {
            $tenantId = (int)$_SESSION['tenant_id'];
        } else {
            // Tenta pegar do usuário
            $userId = user_id();
            $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $tenantId = (int)$stmt->fetchColumn();
        }
    }
    
    if ($tenantId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Tenant não identificado.']);
        exit;
    }
    
    // Diretório de upload
    $uploadDir = ROOT . '/assets/itsolution24/img/logo-favicons/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    
    // Busca a logo atual do tenant
    $oldLogo = '';
    try {
        // Primeiro tenta buscar do tenant
        $stmt = $pdo->prepare("SELECT logo FROM tenants WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $oldLogo = $stmt->fetchColumn() ?: '';
        
        // Se não encontrou no tenant, busca da primeira loja
        if (empty($oldLogo)) {
            $stmt = $pdo->prepare("SELECT logo FROM stores WHERE tenant_id = ? ORDER BY store_id ASC LIMIT 1");
            $stmt->execute([$tenantId]);
            $oldLogo = $stmt->fetchColumn() ?: '';
        }
    } catch (Exception $e) {
        $oldLogo = '';
    }
    
    $newLogo = 'sem-foto.jpg';
    
    if ($removeLogo) {
        // Remover logo
        $newLogo = 'sem-foto.jpg';
        
        // Remove arquivo anterior
        if (!empty($oldLogo) && $oldLogo !== 'sem-foto.jpg') {
            $oldPath = $uploadDir . $oldLogo;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    } else if (isset($_FILES['logo']) && empty($_FILES['logo']['error'])) {
        // Upload de nova logo
        $file = $_FILES['logo'];
        $tmpName = $file['tmp_name'];
        $originalName = $file['name'];
        
        if (!is_uploaded_file($tmpName)) {
            echo json_encode(['success' => false, 'error' => 'Arquivo inválido.']);
            exit;
        }
        
        // Validar extensão
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            echo json_encode(['success' => false, 'error' => 'Formato de imagem não permitido. Use JPG, PNG, GIF ou WEBP.']);
            exit;
        }
        
        // Validar tamanho (máximo 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Imagem muito grande. Máximo 2MB.']);
            exit;
        }
        
        // Gerar nome único
        $newLogo = 'tenant_' . $tenantId . '_' . uniqid() . '.' . $ext;
        $newPath = $uploadDir . $newLogo;
        
        if (!move_uploaded_file($tmpName, $newPath)) {
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar imagem no servidor.']);
            exit;
        }
        
        // Remove arquivo anterior
        if (!empty($oldLogo) && $oldLogo !== 'sem-foto.jpg' && $oldLogo !== $newLogo) {
            $oldPath = $uploadDir . $oldLogo;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Nenhuma imagem enviada.']);
        exit;
    }
    
    // Atualizar logo no tenant (se a coluna existir)
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'logo'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("UPDATE tenants SET logo = ? WHERE tenant_id = ?");
            $stmt->execute([$newLogo, $tenantId]);
        }
    } catch (Exception $e) {
        // Coluna pode não existir, ignorar
    }
    
    // Atualizar logo na primeira loja do tenant (para manter compatibilidade)
    try {
        $stmt = $pdo->prepare("SELECT store_id FROM stores WHERE tenant_id = ? ORDER BY store_id ASC LIMIT 1");
        $stmt->execute([$tenantId]);
        $storeId = $stmt->fetchColumn();
        
        if ($storeId) {
            $stmt = $pdo->prepare("UPDATE stores SET logo = ? WHERE store_id = ?");
            $stmt->execute([$newLogo, $storeId]);
        }
    } catch (Exception $e) {
        // Ignorar erros
    }
    
    $logoUrl = root_url() . 'assets/itsolution24/img/logo-favicons/' . $newLogo;
    
    echo json_encode([
        'success' => true,
        'message' => 'Logo atualizada com sucesso.',
        'data' => [
            'tenant_id' => $tenantId,
            'logo' => $newLogo,
            'logo_url' => $logoUrl
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar logo: ' . $e->getMessage()]);
}
