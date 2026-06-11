<?php
/**
 * Script de Migração - Tabelas de Vendas SaaS
 * 
 * Este script:
 * 1. Cria backup automático do banco de dados
 * 2. Executa a migração das tabelas de vendas
 * 3. Verifica a integridade dos dados
 */

// Configurações do banco de dados
// IMPORTANTE: Ajuste estas configurações conforme seu ambiente
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'modernpos');
define('BACKUP_DIR', __DIR__ . '/../backups/');

// Cores para output no terminal
class Colors {
    public static $GREEN = "\033[32m";
    public static $RED = "\033[31m";
    public static $YELLOW = "\033[33m";
    public static $BLUE = "\033[34m";
    public static $RESET = "\033[0m";
}

// Função para exibir mensagens coloridas
function log_message($message, $color = null) {
    if ($color) {
        echo $color . $message . Colors::$RESET . PHP_EOL;
    } else {
        echo $message . PHP_EOL;
    }
}

// Função para criar backup do banco de dados
function create_backup($conn) {
    log_message("\n=== CRIANDO BACKUP DO BANCO DE DADOS ===", Colors::$BLUE);
    
    // Criar diretório de backup se não existir
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0755, true);
        log_message("✓ Diretório de backup criado", Colors::$GREEN);
    }
    
    $backup_file = BACKUP_DIR . 'backup_modernpos_' . date('Ymd_His') . '.sql';
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        DB_HOST,
        DB_USER,
        DB_PASS ? DB_PASS : '',
        DB_NAME,
        $backup_file
    );
    
    // No Windows, use aspas duplas
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = str_replace('\'', '"', $command);
    }
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($backup_file)) {
        $size = filesize($backup_file);
        log_message("✓ Backup criado com sucesso: " . basename($backup_file), Colors::$GREEN);
        log_message("  Tamanho: " . number_format($size / 1024, 2) . " KB", Colors::$GREEN);
        return $backup_file;
    } else {
        log_message("✗ Erro ao criar backup!", Colors::$RED);
        return false;
    }
}

// Função para verificar se as tabelas existem
function verify_tables($conn) {
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $missing = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missing[] = $table;
        }
    }
    
    return $missing;
}

// Função para contar registros antes da migração
function count_records($conn) {
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    $counts = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as total FROM `$table`");
        $row = $result->fetch_assoc();
        $counts[$table] = $row['total'];
    }
    
    return $counts;
}

// Função principal de migração
function run_migration($conn) {
    log_message("\n=== EXECUTANDO MIGRAÇÃO ===", Colors::$BLUE);
    
    $migration_file = __DIR__ . '/migrate_sales_tables_add_tenant.sql';
    
    if (!file_exists($migration_file)) {
        log_message("✗ Arquivo de migração não encontrado: $migration_file", Colors::$RED);
        return false;
    }
    
    $sql = file_get_contents($migration_file);
    
    // Dividir em comandos individuais
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        // Pular comentários e linhas vazias
        if (empty(trim($statement)) || preg_match('/^--/', trim($statement))) {
            continue;
        }
        
        try {
            if ($conn->multi_query($statement . ';')) {
                do {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
                $success_count++;
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            // Ignorar erros de "IF NOT EXISTS" quando a coluna já existe
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                log_message("⚠ Aviso: " . $e->getMessage(), Colors::$YELLOW);
                $error_count++;
            }
        }
    }
    
    log_message("\n✓ Comandos executados com sucesso: $success_count", Colors::$GREEN);
    if ($error_count > 0) {
        log_message("⚠ Avisos/Erros: $error_count", Colors::$YELLOW);
    }
    
    return true;
}

// Função para verificar integridade após migração
function verify_integrity($conn, $before_counts) {
    log_message("\n=== VERIFICANDO INTEGRIDADE DOS DADOS ===", Colors::$BLUE);
    
    $after_counts = count_records($conn);
    $all_ok = true;
    
    foreach ($before_counts as $table => $count) {
        if ($after_counts[$table] == $count) {
            log_message("✓ $table: $count registros (OK)", Colors::$GREEN);
        } else {
            log_message("✗ $table: Esperado $count, encontrado {$after_counts[$table]}", Colors::$RED);
            $all_ok = false;
        }
    }
    
    // Verificar se tenant_id foi adicionado
    log_message("\n=== VERIFICANDO COLUNA tenant_id ===", Colors::$BLUE);
    $tables = ['selling_info', 'selling_item', 'customers', 'products'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
        if ($result->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as total FROM `$table` WHERE tenant_id = 1");
            $row = $result->fetch_assoc();
            log_message("✓ $table: coluna tenant_id existe ({$row['total']} registros com tenant_id=1)", Colors::$GREEN);
        } else {
            log_message("✗ $table: coluna tenant_id NÃO encontrada", Colors::$RED);
            $all_ok = false;
        }
    }
    
    return $all_ok;
}

// ==================================
// SCRIPT PRINCIPAL
// ==================================

try {
    log_message("\n╔════════════════════════════════════════════════════════╗", Colors::$BLUE);
    log_message("║  MIGRAÇÃO DE TABELAS DE VENDAS - ModernPOS SaaS       ║", Colors::$BLUE);
    log_message("╚════════════════════════════════════════════════════════╝", Colors::$BLUE);
    
    // Conectar ao banco de dados
    log_message("\n=== CONECTANDO AO BANCO DE DADOS ===", Colors::$BLUE);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }
    
    log_message("✓ Conectado ao banco de dados: " . DB_NAME, Colors::$GREEN);
    
    // Verificar se as tabelas existem
    $missing_tables = verify_tables($conn);
    if (!empty($missing_tables)) {
        throw new Exception("Tabelas não encontradas: " . implode(', ', $missing_tables));
    }
    
    log_message("✓ Todas as tabelas necessárias existem", Colors::$GREEN);
    
    // Contar registros antes da migração
    $before_counts = count_records($conn);
    log_message("\n=== CONTAGEM DE REGISTROS ATUAL ===", Colors::$BLUE);
    foreach ($before_counts as $table => $count) {
        log_message("  $table: $count registros");
    }
    
    // Perguntar confirmação
    log_message("\n⚠ ATENÇÃO: Esta operação irá modificar a estrutura do banco de dados!", Colors::$YELLOW);
    log_message("Um backup será criado automaticamente antes da migração.", Colors::$YELLOW);
    echo "\nDeseja continuar? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 's') {
        log_message("\n✗ Migração cancelada pelo usuário", Colors::$YELLOW);
        exit(0);
    }
    
    // Criar backup
    $backup_file = create_backup($conn);
    if (!$backup_file) {
        throw new Exception("Falha ao criar backup. Migração cancelada por segurança.");
    }
    
    // Executar migração
    if (!run_migration($conn)) {
        throw new Exception("Falha na execução da migração");
    }
    
    // Verificar integridade
    if (!verify_integrity($conn, $before_counts)) {
        log_message("\n⚠ AVISO: Problemas detectados na verificação de integridade", Colors::$YELLOW);
        log_message("Verifique os dados manualmente ou restaure o backup", Colors::$YELLOW);
    }
    
    log_message("\n╔════════════════════════════════════════════════════════╗", Colors::$GREEN);
    log_message("║           MIGRAÇÃO CONCLUÍDA COM SUCESSO!              ║", Colors::$GREEN);
    log_message("╚════════════════════════════════════════════════════════╝", Colors::$GREEN);
    
    log_message("\nBackup salvo em: $backup_file", Colors::$BLUE);
    
    $conn->close();
    
} catch (Exception $e) {
    log_message("\n✗ ERRO: " . $e->getMessage(), Colors::$RED);
    log_message("\nA migração foi interrompida.", Colors::$RED);
    if (isset($conn)) {
        $conn->close();
    }
    exit(1);
}
