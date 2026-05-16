<?php
/**
 * ARQUIVO: modernpos/support_impersonate.php
 * 
 * Permite que um admin do painel SaaS faça login como dono de um tenant.
 * Aceita duas formas de autenticação:
 * 1. Via token (gerado pela API do SAAS e salvo no BD) - mais seguro
 * 2. Via tenant_id direto (legado)
 */

ob_start();

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;
$storeId  = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

// =========================================================================
// MODO TOKEN: Validar token do BANCO DE DADOS
// =========================================================================
if (!empty($token)) {
    // 1. Inicia sessão do ModernPOS com ID NOVO
    //    IMPORTANTE: Precisamos garantir que o PHPSESSID seja diferente do SAAS_SESSID
    //    para evitar que as duas sessões compartilhem o mesmo arquivo no servidor
    session_name('PHPSESSID');
    
    // Se já existe um cookie PHPSESSID com o mesmo valor do SAAS_SESSID, precisamos regenerar
    $saasSessionId = isset($_COOKIE['SAAS_SESSID']) ? $_COOKIE['SAAS_SESSID'] : '';
    $phpSessionId = isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : '';
    
    session_start();
    
    // Se os IDs são iguais, regenera para evitar conflito
    if ($saasSessionId && ($phpSessionId === $saasSessionId || session_id() === $saasSessionId)) {
        session_regenerate_id(true); // true = deleta a sessão antiga
    }
    
    // 2. Conecta ao banco ANTES de incluir _init.php para validar o token
    //    Usamos o mesmo config.php do ModernPOS
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        die('Arquivo de configuração não encontrado.');
    }
    require_once $configPath;
    
    try {
        $tempDb = new PDO(
            'mysql:host=' . $sql_details['host'] . ';dbname=' . $sql_details['db'] . ';port=' . $sql_details['port'] . ';charset=utf8mb4',
            $sql_details['user'],
            $sql_details['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Busca o token usando timestamp Unix (independente de timezone)
        $currentUnixTime = time();
        
        // Verifica se a coluna expiry_unix existe, senão adiciona
        try {
            $checkCol = $tempDb->query("SHOW COLUMNS FROM impersonate_tokens LIKE 'expiry_unix'");
            if ($checkCol->rowCount() == 0) {
                $tempDb->exec("ALTER TABLE impersonate_tokens ADD COLUMN expiry_unix BIGINT NOT NULL DEFAULT 0");
            }
        } catch (Exception $e) {
            // Ignora erro
        }
        
        // Busca o token
        $stmtToken = $tempDb->prepare("
            SELECT * FROM impersonate_tokens 
            WHERE token = :token 
              AND used = 0
            LIMIT 1
        ");
        $stmtToken->execute([':token' => $token]);
        $tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);
        
        // Verifica expiração usando PHP (evita problemas de timezone do MySQL)
        if ($tokenData) {
            $isExpired = false;
            
            if (!empty($tokenData['expiry_unix']) && $tokenData['expiry_unix'] > 0) {
                // Modo novo: usa expiry_unix
                $isExpired = ($tokenData['expiry_unix'] <= $currentUnixTime);
            } else {
                // Modo legado: converte expiry datetime para timestamp
                $expiryTimestamp = strtotime($tokenData['expiry']);
                $isExpired = ($expiryTimestamp <= $currentUnixTime);
            }
            
            if ($isExpired) {
                $tokenData = false; // Marca como inválido
            }
        }
        
        if (!$tokenData) {
            // Busca token para verificar motivo da falha
            $checkStmt = $tempDb->prepare("SELECT used FROM impersonate_tokens WHERE token = :token LIMIT 1");
            $checkStmt->execute([':token' => $token]);
            $checkData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$checkData) {
                die('Token não encontrado. Por favor, tente novamente.');
            } elseif ($checkData['used'] == 1) {
                die('Token já foi utilizado. Por favor, tente novamente.');
            } else {
                die('Token expirado. Por favor, tente novamente.');
            }
        }
        
        // Marca o token como usado (uso único)
        $stmtUpdate = $tempDb->prepare("UPDATE impersonate_tokens SET used = 1 WHERE id = :id");
        $stmtUpdate->execute([':id' => $tokenData['id']]);
        
    } catch (PDOException $e) {
        die('Erro ao validar token: ' . $e->getMessage());
    }
    
    // 3. Token válido! Define a sessão ANTES de incluir _init.php
    //    IMPORTANTE: A classe User do ModernPOS verifica $_SESSION['id'] (via $session->data['id'])
    $userId = (int)$tokenData['user_id'];
    
    // Define o ID na sessão ANTES de carregar _init.php (essencial!)
    $_SESSION['id'] = $userId;
    $_SESSION['tenant_id'] = (int)$tokenData['tenant_id'];
    $_SESSION['impersonate_mode'] = true;
    
    // URL de retorno para o SAAS (detecta ambiente)
    // Se estiver em produção (pdv.easysaascloud.com), usa o domínio do SAAS
    $isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'easysaascloud.com') !== false);
    if ($isProduction) {
        $_SESSION['saas_return_url'] = 'https://saas.easysaascloud.com/painel/index.php?pag=clientes';
    } else {
        $_SESSION['saas_return_url'] = '/saas/painel/index.php?pag=clientes';
    }
    
    // 4. Agora inclui o _init.php - ele vai reconhecer o usuário logado
    include(__DIR__ . '/_init.php');
    
    // 5. Define a loja ativa
    $storeId = (int)$tokenData['store_id'];
    $session->data['store_id'] = $storeId;
    
    // Redireciona para o dashboard
    header('Location: ' . ROOT_URL . ADMINDIRNAME . '/dashboard.php');
    exit();
}

// =========================================================================
// MODO LEGADO: Via tenant_id direto
// =========================================================================

// Inicia sessão do ModernPOS (para modo legado)
session_name('PHPSESSID');
session_start();

// Inclui o _init.php do ModernPOS (carrega config.php com $sql_details)
include(__DIR__ . '/_init.php');

if (!$tenantId) {
    die('Tenant não informado.');
}

// Conecta ao banco usando $sql_details do config.php
try {
    $saasDb = new PDO(
        'mysql:host=' . $sql_details['host'] . ';dbname=' . $sql_details['db'] . ';port=' . $sql_details['port'] . ';charset=utf8mb4',
        $sql_details['user'],
        $sql_details['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Erro de conexão com o banco: ' . $e->getMessage());
}

// Busca o owner_user_id do tenant
$stmtTenant = $saasDb->prepare("SELECT owner_user_id, company_name FROM tenants WHERE tenant_id = :tenant_id LIMIT 1");
$stmtTenant->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
$stmtTenant->execute();
$tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);

if (!$tenant || empty($tenant['owner_user_id'])) {
    die('Tenant não encontrado ou sem dono configurado.');
}

$ownerUserId = (int)$tenant['owner_user_id'];
$companyName = $tenant['company_name'];

// Busca as lojas do tenant
$stmtStores = $saasDb->prepare("SELECT store_id, name, status FROM stores WHERE tenant_id = :tenant_id ORDER BY store_id ASC");
$stmtStores->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
$stmtStores->execute();
$stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);

$totalStores = count($stores);

// Se foi passado um store_id específico ou só tem 1 loja, faz login direto
if ($storeId > 0 || $totalStores === 1) {
    
    // Se não informou store_id mas só tem 1, usa essa
    if ($storeId === 0 && $totalStores === 1) {
        $storeId = (int)$stores[0]['store_id'];
    }
    
    // Busca dados do usuário dono
    $stmtUser = $saasDb->prepare("SELECT id, username, email, group_id, status FROM users WHERE id = :id LIMIT 1");
    $stmtUser->bindValue(':id', $ownerUserId, PDO::PARAM_INT);
    $stmtUser->execute();
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        die('Usuário dono não encontrado.');
    }
    
    // Força o login do usuário (impersonação)
    $_SESSION['user_id']   = $userData['id'];
    $_SESSION['username']  = $userData['username'];
    $_SESSION['email']     = $userData['email'];
    $_SESSION['group_id']  = $userData['group_id'];
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['impersonate_mode'] = true; // Flag para indicar que é impersonação
    
    // Define a loja ativa na sessão do ModernPOS
    $session->data['store_id'] = $storeId;
    $session->data['user_id']  = $userData['id'];
    
    // Redireciona para o dashboard
    header('Location: ' . ROOT_URL . ADMINDIRNAME . '/dashboard.php');
    exit();
}

// Se tem múltiplas lojas, exibe página de seleção
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Loja - <?php echo htmlspecialchars($companyName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .store-select-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .store-select-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d4a6f 100%);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        .store-select-header h4 {
            margin: 0 0 5px 0;
            font-weight: 700;
        }
        .store-select-header p {
            margin: 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        .store-list {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        .store-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .store-item:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .store-item:last-child {
            margin-bottom: 0;
        }
        .store-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        .store-info h5 {
            margin: 0 0 3px 0;
            font-weight: 600;
            color: #1e3a5f;
        }
        .store-info span {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .store-info .badge {
            font-size: 0.7rem;
        }
        .store-arrow {
            margin-left: auto;
            color: #adb5bd;
            font-size: 1.2rem;
        }
        .store-item:hover .store-arrow {
            color: #667eea;
        }
        .store-select-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .store-select-footer a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .store-select-footer a:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="store-select-card">
        <div class="store-select-header">
            <h4><i class="bi bi-shop me-2"></i> Selecionar Loja</h4>
            <p><?php echo htmlspecialchars($companyName); ?></p>
        </div>
        
        <div class="store-list">
            <?php if ($totalStores === 0): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-shop-window" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-2 mb-0">Nenhuma loja cadastrada para este tenant.</p>
                </div>
            <?php else: ?>
                <?php foreach ($stores as $store): ?>
                    <a href="?tenant_id=<?php echo $tenantId; ?>&store_id=<?php echo $store['store_id']; ?>" class="store-item text-decoration-none">
                        <div class="store-icon">
                            <i class="bi bi-shop"></i>
                        </div>
                        <div class="store-info">
                            <h5><?php echo htmlspecialchars($store['name']); ?></h5>
                            <span>
                                ID: <?php echo $store['store_id']; ?>
                                <?php if ($store['status'] != 1): ?>
                                    <span class="badge bg-secondary ms-1">Inativa</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-1">Ativa</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="store-arrow">
                            <i class="bi bi-chevron-right"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="store-select-footer">
            <a href="javascript:window.close();"><i class="bi bi-arrow-left me-1"></i> Voltar ao Painel SaaS</a>
        </div>
    </div>
</body>
</html>
