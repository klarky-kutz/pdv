<?php
/**
 * API: Salvar dados do perfil da conta
 * POST /modernpos/_inc/account_profile_save.php
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
    $userId = (int)($_POST['user_id'] ?? user_id());
    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    
    // Validar que o usuário só pode editar seus próprios dados
    if ($userId !== user_id() && user_group_id() != 1) {
        echo json_encode(['success' => false, 'error' => 'Permissão negada.']);
        exit;
    }
    
    // Dados do usuário
    $nome = trim($_POST['nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Dados da empresa
    $nomeEmpresa = trim($_POST['nome_empresa'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $segmento = trim($_POST['segmento'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    
    // Senha
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    
    // Validações básicas
    if (empty($nome)) {
        echo json_encode(['success' => false, 'error' => 'Nome é obrigatório.']);
        exit;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'E-mail inválido.']);
        exit;
    }
    
    // Atualizar dados do usuário
    $updateUserFields = [];
    $updateUserParams = [];
    
    if (!empty($nome)) {
        $updateUserFields[] = 'username = ?';
        $updateUserParams[] = $nome;
    }
    if (!empty($cpf)) {
        $updateUserFields[] = 'cpf = ?';
        $updateUserParams[] = $cpf;
    }
    if (!empty($whatsapp)) {
        $updateUserFields[] = 'mobile = ?';
        $updateUserParams[] = $whatsapp;
    }
    if (!empty($email)) {
        $updateUserFields[] = 'email = ?';
        $updateUserParams[] = $email;
    }
    
    // Alteração de senha
    if (!empty($novaSenha)) {
        if ($novaSenha !== $confirmarSenha) {
            echo json_encode(['success' => false, 'error' => 'As senhas não conferem.']);
            exit;
        }
        if (strlen($novaSenha) < 6) {
            echo json_encode(['success' => false, 'error' => 'A nova senha deve ter pelo menos 6 caracteres.']);
            exit;
        }
        
        // Verificar senha atual
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentHash = $stmt->fetchColumn();
        
        if (!empty($senhaAtual)) {
            // Verifica se a senha atual está correta (tenta MD5, SHA1 e password_hash)
            $valid = false;
            if ($currentHash === md5($senhaAtual) || $currentHash === sha1($senhaAtual) || password_verify($senhaAtual, $currentHash)) {
                $valid = true;
            }
            
            if (!$valid) {
                echo json_encode(['success' => false, 'error' => 'Senha atual incorreta.']);
                exit;
            }
        }
        
        // Atualizar senha com SHA1 (padrão do sistema)
        $updateUserFields[] = 'password = ?';
        $updateUserParams[] = sha1($novaSenha);
    }
    
    if (!empty($updateUserFields)) {
        $updateUserParams[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updateUserFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateUserParams);
    }
    
    // Atualizar dados do tenant
    if ($tenantId > 0) {
        $updateTenantFields = [];
        $updateTenantParams = [];
        
        if (!empty($nomeEmpresa)) {
            $updateTenantFields[] = 'company_name = ?';
            $updateTenantParams[] = $nomeEmpresa;
        }
        if (!empty($cnpj)) {
            $updateTenantFields[] = 'cpf_cnpj = ?';
            $updateTenantParams[] = $cnpj;
        }
        if (!empty($segmento)) {
            $updateTenantFields[] = 'segmento = ?';
            $updateTenantParams[] = $segmento;
        }
        if (!empty($cep)) {
            $updateTenantFields[] = 'cep = ?';
            $updateTenantParams[] = $cep;
        }
        if (!empty($endereco)) {
            $updateTenantFields[] = 'endereco = ?';
            $updateTenantParams[] = $endereco;
        }
        
        if (!empty($updateTenantFields)) {
            $updateTenantParams[] = $tenantId;
            $sql = "UPDATE tenants SET " . implode(', ', $updateTenantFields) . " WHERE tenant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateTenantParams);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Dados salvos com sucesso.']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar dados: ' . $e->getMessage()]);
}
