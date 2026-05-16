<?php
// =======================================================
// ARQUIVO: process_register.php (MODO DEBUG & CORREÇÃO)
// =======================================================

// 1. CONFIGURAÇÕES DE DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=UTF-8');

// Carrega as configurações de banco (mas não o sistema todo, para evitar conflitos)
if (!file_exists("config.php")) {
    die(json_encode(['status' => 'error', 'error' => 'Arquivo config.php não encontrado.']));
}
require_once("config.php");

try {
    // ==================================================
    // 2. CONEXÃO MANUAL (Para garantir que funciona)
    // ==================================================
    $dsn = "mysql:host={$sql_details['host']};dbname={$sql_details['db']};port={$sql_details['port']};charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $db = new PDO($dsn, $sql_details['user'], $sql_details['pass'], $options);

    // ==================================================
    // 3. VERIFICAÇÃO DA TABELA DE USUÁRIOS (CRUCIAL!)
    // ==================================================
    // Verifica se a tabela 'users' tem a coluna 'username' (padrão ModernPOS)
    // ou 'nome' (padrão antigo/importado)
    $check_table = $db->query("SHOW COLUMNS FROM users LIKE 'username'");
    $is_modernpos = $check_table->fetch();

    if (!$is_modernpos) {
        throw new Exception("ERRO CRÍTICO DE TABELA: A tabela `users` no seu banco de dados está incorreta! O sistema espera a coluna `username`, mas ela não existe. Provavelmente você importou o arquivo 'usuarios.sql' antigo. Restaure a tabela `users` original do ModernPOS.");
    }

    // ==================================================
    // 4. LÓGICA DE CADASTRO
    // ==================================================

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Este arquivo espera uma requisição POST.");
    }

    // Dados do Formulário
    $nome_completo = trim($_POST['nome'] ?? '');
    $email         = $_SESSION['verification_email'] ?? trim($_POST['email'] ?? '');
    $whatsapp      = trim($_POST['whatsapp'] ?? '');
    $senha_plana   = $_POST['password'] ?? '123456';
    $nome_loja     = trim($_POST['nome_loja'] ?? 'Minha Loja');

    // Tipo de pessoa e documento vindos do cadastro.php
    $tipo_pessoa   = $_POST['tipo_pessoa'] ?? 'PF';              // PF ou PJ
    $documento     = preg_replace('/[^0-9]/', '', $_POST['documento'] ?? '');

    // Mapeia para as colunas do banco:
    // - cnpj_cpf => deve receber o CPF
    // - cpf_cnpj => deve receber o CNPJ
    if ($tipo_pessoa === 'PF') {
        $cnpj_cpf = $documento;   // aqui vai o CPF
        $cpf_cnpj = null;         // sem CNPJ
    } else {
        $cpf_cnpj = $documento;   // aqui vai o CNPJ
        $cnpj_cpf = null;         // sem CPF
    }

    $segmento      = trim($_POST['segmento'] ?? 'Geral');

    // Buscar plano de teste da configuração do SAAS
    $plan_id = 1; // valor padrão
    try {
        $checkColumnPlano = $db->query("SHOW COLUMNS FROM config LIKE 'plano_teste'");
        if ($checkColumnPlano && $checkColumnPlano->rowCount() > 0) {
            $stmPlano = $db->query("SELECT plano_teste FROM config WHERE id = 1 LIMIT 1");
            if ($stmPlano && $rowPlano = $stmPlano->fetch(PDO::FETCH_ASSOC)) {
                $plan_id = (int)($rowPlano['plano_teste'] ?? 1);
            }
        }
    } catch (Exception $e) {
        // Mantém valor padrão em caso de erro
    }
    
    // Buscar dias de teste da configuração do SAAS
    $dias_teste = 7; // valor padrão
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM config LIKE 'dias_teste'");
        if ($checkColumn && $checkColumn->rowCount() > 0) {
            $stmDias = $db->query("SELECT dias_teste FROM config WHERE id = 1 LIMIT 1");
            if ($stmDias && $rowDias = $stmDias->fetch(PDO::FETCH_ASSOC)) {
                $dias_teste = (int)($rowDias['dias_teste'] ?? 7);
            }
        }
    } catch (Exception $e) {
        // Mantém valor padrão em caso de erro
    }
    $trial_ends_at = date('Y-m-d H:i:s', strtotime('+' . $dias_teste . ' days'));

    $db->beginTransaction();

    // A. CRIAR USUÁRIO
    // Verifica se a coluna plain_password existe na tabela users
    $hasPlainPasswordColumn = false;
    try {
        $checkCol = $db->query("SHOW COLUMNS FROM users LIKE 'plain_password'");
        $hasPlainPasswordColumn = $checkCol && $checkCol->fetch();
    } catch (Exception $e) {
        $hasPlainPasswordColumn = false;
    }

    if ($hasPlainPasswordColumn) {
        $stmt = $db->prepare("INSERT INTO users (username, email, mobile, password, plain_password, group_id, status, created_at)
                              VALUES (?, ?, ?, ?, ?, 1, 1, NOW())");
        $stmt->execute([
            $nome_completo,
            $email,
            $whatsapp,
            password_hash($senha_plana, PASSWORD_DEFAULT),
            $senha_plana  // Salva a senha em texto plano para exibição na modal "Alterar Senha"
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO users (username, email, mobile, password, group_id, status, created_at)
                              VALUES (?, ?, ?, ?, 1, 1, NOW())");
        $stmt->execute([
            $nome_completo,
            $email,
            $whatsapp,
            password_hash($senha_plana, PASSWORD_DEFAULT)
        ]);
    }
    $user_id = $db->lastInsertId();

    // B. CRIAR TENANT
    $stmt = $db->prepare("INSERT INTO tenants (
            company_name,
            owner_user_id,
            plan_id,
            cnpj_cpf,   -- CPF
            cpf_cnpj,   -- CNPJ
            segmento,
            whatsapp,
            subscription_status,
            trial_ends_at,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'trial', ?, NOW())");

    $stmt->execute([
        $nome_loja,
        $user_id,
        $plan_id,
        $cnpj_cpf,
        $cpf_cnpj,
        $segmento,
        $whatsapp,
        $trial_ends_at,
    ]);
    $tenant_id = $db->lastInsertId();

    // C. VINCULAR
    $db->prepare("UPDATE users SET tenant_id = ? WHERE id = ?")->execute([$tenant_id, $user_id]);
    // D. CRIAR LOJA
    $code_name = substr(preg_replace('/[^a-z0-9]/', '', strtolower($nome_loja)), 0, 20) ?: 'loja_' . time();
    $stmt = $db->prepare("INSERT INTO stores (name, code_name, mobile, email, country, zip_code, currency, cashier_id, address, status, created_at, tenant_id, sound_effect, sort_order, receipt_printer, remote_printing, auto_print) VALUES (?, ?, ?, ?, 'BR', '00000-000', 'BRL', ?, 'Endereço Principal', 1, NOW(), ?, 1, 0, 1, 0, 0)");
    $stmt->execute([
        $nome_loja,
        $code_name,
        $whatsapp,
        $email,
        $user_id,
        $tenant_id
    ]);
    $store_id = (int)$db->lastInsertId();
    // D.1 Aplicar defaults centralizados (preferences + pmethod_to_store)
    try {
        require_once __DIR__ . '/../saas/includes/ModernposDefaults.php';
        if (class_exists('ModernposDefaults')) {
            ModernposDefaults::applyDefaultsToStore($db, (int)$store_id);
            
            // NOVO: Aplicar dados de exemplo globais (categoria, fornecedor, marca, conta, templates, produtos demo)
            // para manter consistência com lojas criadas via Checkout SaaS
            if (method_exists('ModernposDefaults', 'applyGlobalSampleDataToStore')) {
                ModernposDefaults::applyGlobalSampleDataToStore($db, (int)$store_id);
            }
        }
    } catch (Throwable $eDefaults) {
        // Falha ao aplicar defaults não impede o cadastro; apenas registra log se possível
        if (function_exists('error_log')) {
            error_log('[process_register] Falha ao aplicar ModernposDefaults para loja ' . $store_id . ': ' . $eDefaults->getMessage());
        }
    }

    // E. FINALIZAR - Vincula usuário à loja criada
    // Tenta inserir com sort_order; se a coluna não existir, usa fallback
    try {
        $db->prepare("INSERT INTO user_to_store (user_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)")->execute([$user_id, $store_id]);
    } catch (PDOException $e) {
        // Fallback: tabela pode não ter coluna sort_order
        $db->prepare("INSERT INTO user_to_store (user_id, store_id, status) VALUES (?, ?, 1)")->execute([$user_id, $store_id]);
    }
    
    // Insere registro de uso do tenant
    try {
        $db->prepare("INSERT INTO tenant_usage (tenant_id, storage_used_mb) VALUES (?, 0.00)")->execute([$tenant_id]);
    } catch (PDOException $e) {
        // Ignora se a tabela não existir ou registro já existir
        error_log('[process_register] Aviso ao inserir tenant_usage: ' . $e->getMessage());
    }

    $db->commit();

    // Login na sessão
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $nome_completo;
    $_SESSION['email'] = $email;
    $_SESSION['group_id'] = 1;
    $_SESSION['tenant_id'] = $tenant_id;
    $_SESSION['store_id'] = $store_id;

    echo json_encode(['status' => 'success', 'msg' => 'Cadastro realizado com sucesso!', 'redirect' => 'store_select.php']);
} catch (PDOException $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['status' => 'error', 'error' => 'ERRO BANCO DE DADOS: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
