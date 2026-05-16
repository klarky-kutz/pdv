<?php
/**
 * Interface Web para Migração de Tabelas de Vendas
 * Acesse via: http://localhost/modernpos/migrations/web_migration.php
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'modernpos');
define('BACKUP_DIR', __DIR__ . '/../backups/');

// Processar ação
$action = $_POST['action'] ?? 'show_info';
$result = ['success' => false, 'message' => '', 'data' => []];

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }
    
    switch ($action) {
        case 'check_status':
            $result = checkMigrationStatus($conn);
            break;
            
        case 'create_backup':
            $result = createBackup($conn);
            break;
            
        case 'run_migration':
            $result = runMigration($conn);
            break;
            
        case 'verify_migration':
            $result = verifyMigration($conn);
            break;
            
        default:
            $result = getSystemInfo($conn);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $result = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Se for requisição AJAX, retornar JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Funções auxiliares
function getSystemInfo($conn) {
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $info = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as total FROM `$table`");
        $row = $result->fetch_assoc();
        
        // Verificar se tenant_id já existe
        $colResult = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
        $hasTenantId = $colResult->num_rows > 0;
        
        $info[$table] = [
            'total' => $row['total'],
            'has_tenant_id' => $hasTenantId
        ];
    }
    
    return [
        'success' => true,
        'data' => $info
    ];
}

function checkMigrationStatus($conn) {
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $status = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
        $status[$table] = $result->num_rows > 0;
    }
    
    $allMigrated = !in_array(false, $status);
    
    return [
        'success' => true,
        'data' => [
            'migrated' => $allMigrated,
            'tables' => $status
        ]
    ];
}

function createBackup($conn) {
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
    }
    
    $backup_file = BACKUP_DIR . 'backup_modernpos_' . date('Ymd_His') . '.sql';
    
    // Tentar localizar mysqldump no XAMPP
    $mysqldump_paths = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\xampp\\mysql\\bin\\mysqldump',
        'mysqldump' // Fallback para PATH do sistema
    ];
    
    $mysqldump = null;
    foreach ($mysqldump_paths as $path) {
        if (file_exists($path) || $path === 'mysqldump') {
            $mysqldump = $path;
            break;
        }
    }
    
    if (!$mysqldump) {
        throw new Exception("mysqldump não encontrado. Verifique a instalação do MySQL/XAMPP.");
    }
    
    // Construir comando com aspas para Windows
    $password_param = DB_PASS ? '--password="' . DB_PASS . '"' : '';
    $command = sprintf(
        '"%s" --host=%s --user=%s %s %s > "%s" 2>&1',
        $mysqldump,
        DB_HOST,
        DB_USER,
        $password_param,
        DB_NAME,
        $backup_file
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($backup_file) && filesize($backup_file) > 100) {
        $size = filesize($backup_file);
        return [
            'success' => true,
            'message' => 'Backup criado com sucesso!',
            'data' => [
                'file' => basename($backup_file),
                'size' => number_format($size / 1024, 2) . ' KB'
            ]
        ];
    } else {
        // Fallback: tentar backup via PHP
        return createBackupViaPHP($conn, $backup_file);
    }
}

function createBackupViaPHP($conn, $backup_file) {
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $sql_dump = "-- Backup ModernPOS - " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Get CREATE TABLE
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $row[1] . ";\n\n";
        
        // Get data
        $result = $conn->query("SELECT * FROM `$table`");
        if ($result->num_rows > 0) {
            $sql_dump .= "INSERT INTO `$table` VALUES\n";
            $values = [];
            while ($row = $result->fetch_row()) {
                $escaped = array_map(function($val) use ($conn) {
                    if ($val === null) return 'NULL';
                    return "'" . $conn->real_escape_string($val) . "'";
                }, $row);
                $values[] = '(' . implode(', ', $escaped) . ')';
            }
            $sql_dump .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    if (file_put_contents($backup_file, $sql_dump)) {
        $size = filesize($backup_file);
        return [
            'success' => true,
            'message' => 'Backup criado com sucesso via PHP!',
            'data' => [
                'file' => basename($backup_file),
                'size' => number_format($size / 1024, 2) . ' KB'
            ]
        ];
    } else {
        throw new Exception("Erro ao salvar arquivo de backup.");
    }
}

function runMigration($conn) {
    $migration_file = __DIR__ . '/migrate_sales_tables_add_tenant.sql';
    
    if (!file_exists($migration_file)) {
        throw new Exception("Arquivo de migração não encontrado");
    }
    
    $sql = file_get_contents($migration_file);
    
    // Executar comandos SQL
    $conn->multi_query($sql);
    
    // Processar todos os resultados
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->error) {
        // Ignorar erro de "Duplicate column" se a coluna já existe
        if (strpos($conn->error, 'Duplicate column name') === false) {
            throw new Exception("Erro na migração: " . $conn->error);
        }
    }
    
    return [
        'success' => true,
        'message' => 'Migração executada com sucesso!'
    ];
}

function verifyMigration($conn) {
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $verification = [];
    
    foreach ($tables as $table) {
        // Verificar se coluna existe
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
        $hasColumn = $result->num_rows > 0;
        
        // Contar registros com tenant_id
        if ($hasColumn) {
            $result = $conn->query("SELECT COUNT(*) as total, SUM(tenant_id = 1) as with_tenant FROM `$table`");
            $row = $result->fetch_assoc();
            
            $verification[$table] = [
                'has_column' => true,
                'total_records' => $row['total'],
                'with_tenant_id' => $row['with_tenant'],
                'ok' => $row['total'] == $row['with_tenant']
            ];
        } else {
            $verification[$table] = [
                'has_column' => false,
                'ok' => false
            ];
        }
    }
    
    $allOk = array_reduce($verification, function($carry, $item) {
        return $carry && $item['ok'];
    }, true);
    
    return [
        'success' => true,
        'data' => [
            'all_ok' => $allOk,
            'tables' => $verification
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração de Tabelas - ModernPOS SaaS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .card h2 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .status-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        
        .status-item h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .status-item .value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-item .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover:not(:disabled) {
            background: #e0a800;
            transform: translateY(-2px);
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .loading.show {
            display: block;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        
        .icon {
            display: inline-block;
            width: 20px;
            height: 20px;
        }
        
        .verification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .verification-table th,
        .verification-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .verification-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .check-icon {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }
        
        .cross-icon {
            color: #dc3545;
            font-weight: bold;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Migração de Tabelas de Vendas</h1>
            <p>ModernPOS → SaaS Multi-Tenant</p>
        </div>
        
        <div class="content">
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar"></div>
            </div>
            
            <div id="alertContainer"></div>
            
            <!-- Step 1: Informações do Sistema -->
            <div class="step active" id="step1">
                <div class="card">
                    <h2>
                        <span class="icon">📊</span>
                        Estado Atual do Banco de Dados
                    </h2>
                    <div class="status-grid" id="systemInfo">
                        <div class="status-item">
                            <h3>Carregando...</h3>
                        </div>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="checkStatus()">
                        🔍 Verificar Status da Migração
                    </button>
                    <button class="btn btn-success" onclick="nextStep(2)" id="btnNextStep1">
                        ➡️ Próximo Passo
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Criar Backup -->
            <div class="step" id="step2">
                <div class="card">
                    <h2>
                        <span class="icon">💾</span>
                        Criar Backup de Segurança
                    </h2>
                    <p style="margin-top: 10px; color: #666;">
                        <strong>Importante:</strong> Antes de executar a migração, é essencial criar um backup completo do banco de dados. 
                        Isso garante que você possa restaurar os dados caso algo dê errado.
                    </p>
                    
                    <div id="backupInfo" style="margin-top: 15px;"></div>
                </div>
                
                <div class="loading" id="loadingBackup">
                    <div class="spinner"></div>
                    <p>Criando backup do banco de dados...</p>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-warning" onclick="prevStep(1)">
                        ⬅️ Voltar
                    </button>
                    <button class="btn btn-primary" onclick="createBackup()">
                        💾 Criar Backup Agora
                    </button>
                    <button class="btn btn-success" onclick="nextStep(3)" id="btnNextStep2" disabled>
                        ➡️ Próximo Passo
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Executar Migração -->
            <div class="step" id="step3">
                <div class="card">
                    <h2>
                        <span class="icon">⚙️</span>
                        Executar Migração
                    </h2>
                    <p style="margin-top: 10px; color: #666;">
                        A migração irá:
                    </p>
                    <ul style="margin: 15px 0 15px 20px; color: #666;">
                        <li>Adicionar coluna <code>tenant_id</code> nas tabelas</li>
                        <li>Criar índices para melhorar performance</li>
                        <li>Atribuir <code>tenant_id = 1</code> para todos os registros existentes</li>
                        <li><strong>Nenhum dado será perdido!</strong></li>
                    </ul>
                </div>
                
                <div class="loading" id="loadingMigration">
                    <div class="spinner"></div>
                    <p>Executando migração... Aguarde...</p>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-warning" onclick="prevStep(2)">
                        ⬅️ Voltar
                    </button>
                    <button class="btn btn-success" onclick="runMigration()" id="btnRunMigration">
                        🚀 Executar Migração
                    </button>
                    <button class="btn btn-primary" onclick="nextStep(4)" id="btnNextStep3" disabled>
                        ➡️ Ver Resultados
                    </button>
                </div>
            </div>
            
            <!-- Step 4: Verificação -->
            <div class="step" id="step4">
                <div class="card">
                    <h2>
                        <span class="icon">✅</span>
                        Verificação de Integridade
                    </h2>
                    <div id="verificationResults">
                        <p style="color: #666;">Clique no botão abaixo para verificar se a migração foi bem-sucedida.</p>
                    </div>
                </div>
                
                <div class="loading" id="loadingVerification">
                    <div class="spinner"></div>
                    <p>Verificando integridade dos dados...</p>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="verifyMigration()">
                        🔍 Verificar Integridade
                    </button>
                    <button class="btn btn-success" onclick="location.reload()">
                        🔄 Reiniciar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentStep = 1;
        let backupCreated = false;
        let migrationCompleted = false;
        
        // Carregar informações ao iniciar
        window.onload = function() {
            loadSystemInfo();
        };
        
        // Função global para corrigir tenant_id
        window.fixTenantId = function() {
            if (!confirm('Deseja corrigir os registros sem tenant_id?')) {
                return;
            }
            
            showAlert('Corrigindo registros...', 'info');
            
            fetch('fix_tenant_id.php', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✅ Correção aplicada com sucesso!', 'success');
                    // Verificar novamente
                    setTimeout(() => verifyMigration(), 1000);
                } else {
                    showAlert('Erro na correção: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Erro na correção: ' + error.message, 'error');
            });
        };
        
        function showAlert(message, type = 'info') {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.textContent = message;
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        function updateProgress(percent) {
            document.getElementById('progressBar').style.width = percent + '%';
        }
        
        function nextStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${step}`).classList.add('active');
            currentStep = step;
            updateProgress(step * 25);
            window.scrollTo(0, 0);
        }
        
        function prevStep(step) {
            nextStep(step);
        }
        
        function loadSystemInfo() {
            fetch('web_migration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=show_info'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('systemInfo');
                    container.innerHTML = '';
                    
                    Object.keys(data.data).forEach(table => {
                        const info = data.data[table];
                        const badge = info.has_tenant_id 
                            ? '<span class="badge success">✓ Migrada</span>'
                            : '<span class="badge warning">Pendente</span>';
                        
                        container.innerHTML += `
                            <div class="status-item">
                                <h3>${table}</h3>
                                <div class="value">${info.total}</div>
                                ${badge}
                            </div>
                        `;
                    });
                }
            })
            .catch(error => {
                showAlert('Erro ao carregar informações: ' + error.message, 'error');
            });
        }
        
        function checkStatus() {
            fetch('web_migration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=check_status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.data.migrated) {
                        showAlert('✅ Todas as tabelas já foram migradas!', 'success');
                    } else {
                        showAlert('⚠️ Migração pendente. Continue com os próximos passos.', 'info');
                    }
                }
            })
            .catch(error => {
                showAlert('Erro ao verificar status: ' + error.message, 'error');
            });
        }
        
        function createBackup() {
            document.getElementById('loadingBackup').classList.add('show');
            
            fetch('web_migration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=create_backup'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingBackup').classList.remove('show');
                
                if (data.success) {
                    backupCreated = true;
                    document.getElementById('btnNextStep2').disabled = false;
                    
                    document.getElementById('backupInfo').innerHTML = `
                        <div class="alert alert-success show">
                            <strong>✓ Backup criado com sucesso!</strong><br>
                            Arquivo: ${data.data.file}<br>
                            Tamanho: ${data.data.size}
                        </div>
                    `;
                    
                    showAlert('Backup criado com sucesso!', 'success');
                } else {
                    showAlert('Erro ao criar backup: ' + data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loadingBackup').classList.remove('show');
                showAlert('Erro ao criar backup: ' + error.message, 'error');
            });
        }
        
        function runMigration() {
            if (!confirm('Tem certeza que deseja executar a migração? Esta ação modificará a estrutura do banco de dados.')) {
                return;
            }
            
            document.getElementById('loadingMigration').classList.add('show');
            document.getElementById('btnRunMigration').disabled = true;
            
            fetch('web_migration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=run_migration'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingMigration').classList.remove('show');
                
                if (data.success) {
                    migrationCompleted = true;
                    document.getElementById('btnNextStep3').disabled = false;
                    showAlert('✅ Migração executada com sucesso!', 'success');
                } else {
                    showAlert('Erro na migração: ' + data.message, 'error');
                    document.getElementById('btnRunMigration').disabled = false;
                }
            })
            .catch(error => {
                document.getElementById('loadingMigration').classList.remove('show');
                document.getElementById('btnRunMigration').disabled = false;
                showAlert('Erro na migração: ' + error.message, 'error');
            });
        }
        
        function verifyMigration() {
            document.getElementById('loadingVerification').classList.add('show');
            
            fetch('web_migration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=verify_migration'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingVerification').classList.remove('show');
                
                if (data.success) {
                    let html = '<table class="verification-table">';
                    html += '<thead><tr><th>Tabela</th><th>Coluna tenant_id</th><th>Total Registros</th><th>Com tenant_id</th><th>Status</th></tr></thead>';
                    html += '<tbody>';
                    
                    Object.keys(data.data.tables).forEach(table => {
                        const info = data.data.tables[table];
                        const status = info.ok 
                            ? '<span class="check-icon">✓</span>' 
                            : '<span class="cross-icon">✗</span>';
                        
                        html += `<tr>
                            <td><strong>${table}</strong></td>
                            <td>${info.has_column ? '✓' : '✗'}</td>
                            <td>${info.total_records || 0}</td>
                            <td>${info.with_tenant_id || 0}</td>
                            <td>${status}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                    
                    if (data.data.all_ok) {
                        html = '<div class="alert alert-success show"><strong>✓ Migração concluída com sucesso!</strong><br>Todos os dados foram migrados corretamente.</div>' + html;
                    } else {
                        html = '<div class="alert alert-error show"><strong>⚠️ Atenção!</strong><br>Alguns problemas foram detectados. <button class="btn btn-warning" onclick="fixTenantId()" style="margin-left: 10px; padding: 6px 15px; font-size: 14px;">🔧 Corrigir Agora</button></div>' + html;
                    }
                    
                    document.getElementById('verificationResults').innerHTML = html;
                } else {
                    showAlert('Erro na verificação: ' + data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loadingVerification').classList.remove('show');
                showAlert('Erro na verificação: ' + error.message, 'error');
            });
        }
    </script>
</body>
</html>
