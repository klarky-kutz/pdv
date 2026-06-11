<?php
ob_start();
session_start();
include("../_init.php");

// JSON helpers
function account_json($data, $status = 200)
{
  // Garante resposta limpa (evita que warnings/echo em includes quebrem o JSON)
  if (function_exists('ob_get_level') && ob_get_level() > 0) {
    @ob_clean();
  }

  http_response_code($status);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($data);
  exit();
}

function account_error($msg, $status = 422, $code = null, $extra = null)
{
  $out = ['errorMsg' => $msg];
  if ($code) {
    $out['code'] = (string)$code;
  }
  if (is_array($extra)) {
    // merge raso (permite used/max/limit_type etc.)
    foreach ($extra as $k => $v) {
      $out[$k] = $v;
    }
  }
  account_json($out, $status);
}

if (!is_loggedin()) {
  account_error(trans('error_login'), 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  account_error('Método não permitido.', 405);
}

// Painel /conta: verificar permissão usando o sistema de duas camadas (RBAC + Feature Gating)
if (!function_exists('has_permission') || !has_permission('access', 'account.view_stores')) {
  account_error('Permissão negada. Você precisa ter permissão account.view_stores.', 403);
}

// Resolve tenant_id (prefer session; fallback users table)
function account_current_tenant_id()
{
  if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id']) {
    return (int)$_SESSION['tenant_id'];
  }

  $uid = function_exists('user_id') ? (int)user_id() : 0;
  if ($uid <= 0) {
    return 0;
  }

  try {
    $st = db()->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
    $st->execute([$uid]);
    $tmp = $st->fetchColumn();
    if ($tmp !== false && $tmp !== null && $tmp !== '') {
      return (int)$tmp;
    }
  } catch (Throwable $e) {
    // ignore
  }

  return 0;
}

function account_slugify($str)
{
  $s = (string)($str ?? '');
  $s = trim($s);

  // Transliterate accents when possible
  if (function_exists('iconv')) {
    $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if (is_string($tmp) && $tmp !== '') {
      $s = $tmp;
    }
  }

  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/', '_', $s);
  $s = preg_replace('/_+/', '_', $s);
  $s = trim($s, '_');
  if (strlen($s) > 60) {
    $s = substr($s, 0, 60);
  }
  return $s;
}

function account_unserialize_array($raw)
{
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $tmp = @unserialize($raw);
  return is_array($tmp) ? $tmp : [];
}

function account_load_modernpos_defaults()
{
  $defaults_path = ROOT . '/../saas/includes/ModernposDefaults.php';
  if (file_exists($defaults_path)) {
    require_once $defaults_path;
  }
}

function account_get_defaults_pref(PDO $pdo)
{
  account_load_modernpos_defaults();
  if (class_exists('ModernposDefaults')) {
    $d = ModernposDefaults::getDefaults($pdo);
    $pref = isset($d['preference']) && is_array($d['preference']) ? $d['preference'] : [];
    return $pref;
  }

  return [];
}

function account_user_can_access_store(PDO $pdo, int $storeId)
{
  $uid = (int)user_id();
  if ($uid <= 0 || $storeId <= 0) {
    return false;
  }

  $tenantId = account_current_tenant_id();
  $groupId = function_exists('user_group_id') ? (int)user_group_id() : 0;

  // Admin (dono): permitir acesso mesmo sem vínculo em user_to_store.
  // Em modo SaaS (quando existir tenant_id), limita para lojas do mesmo tenant.
  if ($groupId === 1) {
    if ($tenantId > 0) {
      try {
        $st = $pdo->prepare('SELECT tenant_id FROM stores WHERE store_id = ? LIMIT 1');
        $st->execute([$storeId]);
        $storeTenant = $st->fetchColumn();
        
        // Se a loja não tem tenant_id definido (null), permite acesso
        if ($storeTenant === false || $storeTenant === null || $storeTenant === '') {
          error_log('[account_store] Admin acessando loja ' . $storeId . ' sem tenant_id definido. Permitindo acesso.');
          return true;
        }
        
        // Verifica se o tenant da loja corresponde ao tenant do usuário
        $storeTenantInt = (int)$storeTenant;
        $accessGranted = $storeTenantInt === $tenantId;
        
        if (!$accessGranted) {
          error_log('[account_store] Admin user_id=' . $uid . ' (tenant ' . $tenantId . ') tentou acessar loja ' . $storeId . ' (tenant ' . $storeTenantInt . '). Acesso negado.');
        } else {
          error_log('[account_store] Admin user_id=' . $uid . ' (tenant ' . $tenantId . ') acessando loja ' . $storeId . '. Acesso concedido.');
        }
        
        return $accessGranted;
      } catch (Throwable $e) {
        // Se a coluna tenant_id não existir, libera como no modo single-tenant.
        error_log('[account_store] Erro ao verificar tenant_id para loja ' . $storeId . ': ' . $e->getMessage() . '. Permitindo acesso de admin.');
        return true;
      }
    }

    // Modo single-tenant: admin tem acesso total
    error_log('[account_store] Admin user_id=' . $uid . ' acessando loja ' . $storeId . ' em modo single-tenant. Acesso concedido.');
    return true;
  }

  // Não-admin: verifica vínculo em user_to_store
  // Prefer tenant-filtered query (SaaS)
  try {
    if ($tenantId > 0) {
      $st = $pdo->prepare(
        "SELECT s.store_id FROM stores s " .
        "INNER JOIN user_to_store u2s ON (u2s.store_id = s.store_id) " .
        "WHERE u2s.user_id = ? AND s.store_id = ? AND s.tenant_id = ? LIMIT 1"
      );
      $st->execute([$uid, $storeId, $tenantId]);
      return (bool)$st->fetchColumn();
    }
  } catch (Throwable $e) {
    // fallback
  }

  try {
    $st = $pdo->prepare(
      "SELECT s.store_id FROM stores s " .
      "INNER JOIN user_to_store u2s ON (u2s.store_id = s.store_id) " .
      "WHERE u2s.user_id = ? AND s.store_id = ? LIMIT 1"
    );
    $st->execute([$uid, $storeId]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    error_log('[account_store] Erro ao verificar acesso de não-admin user_id=' . $uid . ' à loja ' . $storeId . ': ' . $e->getMessage());
    return false;
  }
}

function account_ensure_user_to_store(PDO $pdo, int $storeId, int $userId)
{
  if ($storeId <= 0 || $userId <= 0) {
    return;
  }

  try {
    $chk = $pdo->prepare("SELECT 1 FROM user_to_store WHERE user_id = ? AND store_id = ? LIMIT 1");
    $chk->execute([$userId, $storeId]);
    if ($chk->fetchColumn()) {
      return;
    }

    // Em muitas instalações, user_to_store possui colunas NOT NULL como status/sort_order.
    // Tentamos inserir com esses campos primeiro e fazemos fallback para schemas mais simples.
    try {
      $ins = $pdo->prepare("INSERT INTO user_to_store (user_id, store_id, status, sort_order) VALUES (?, ?, 1, 0)");
      $ins->execute([$userId, $storeId]);
      return;
    } catch (Throwable $e1) {
      // fallback
    }

    try {
      $ins = $pdo->prepare("INSERT INTO user_to_store (user_id, store_id, status) VALUES (?, ?, 1)");
      $ins->execute([$userId, $storeId]);
      return;
    } catch (Throwable $e2) {
      // fallback
    }

    $ins = $pdo->prepare("INSERT INTO user_to_store (user_id, store_id) VALUES (?, ?)");
    $ins->execute([$userId, $storeId]);
  } catch (Throwable $e) {
    // ignore
  }
}

function account_unique_code_name(PDO $pdo, string $base, int $excludeStoreId = 0)
{
  $code = $base !== '' ? $base : 'store';
  $tenantId = account_current_tenant_id();

  for ($i = 0; $i < 50; $i++) {
    $try = $i === 0 ? $code : ($code . '_' . ($i + 1));

    try {
      if ($tenantId > 0) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE tenant_id = ? AND code_name = ? AND store_id != ?");
        $st->execute([$tenantId, $try, (int)$excludeStoreId]);
      } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE code_name = ? AND store_id != ?");
        $st->execute([$try, (int)$excludeStoreId]);
      }

      $count = (int)$st->fetchColumn();
      if ($count === 0) {
        return $try;
      }
    } catch (Throwable $e) {
      // If schema differs, just return the first value
      return $try;
    }
  }

  return $code . '_' . time();
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$pdo = db();

try {
  // =====================
  // List stores (for import modal)
  // =====================
  if ($action === 'list_stores') {
    global $user;

    $stores = [];
    try {
      $stores = $user->getBelongsStore(user_id());
    } catch (Throwable $e) {
      $stores = [];
    }

    $out = [];
    if (is_array($stores)) {
      foreach ($stores as $s) {
        $out[] = [
          'id' => (int)($s['store_id'] ?? 0),
          'name' => (string)($s['name'] ?? ''),
          'status' => (int)($s['status'] ?? 0),
        ];
      }
    }

    account_json([
      'ok' => true,
      'stores' => $out,
    ]);
  }

  // =====================
  // Create store (modal)
  // =====================
  if ($action === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
      throw new Exception('Informe o nome da loja.');
    }

    // Validar nome duplicado para o mesmo tenant
    $tenantId = account_current_tenant_id();

    // Regra rígida: somente Admin/Owner pode criar lojas
    $isOwnerOrAdmin = (function_exists('user_group_id') && (int)user_group_id() === 1)
      || (function_exists('is_tenant_owner') && is_tenant_owner());

    if (!$isOwnerOrAdmin) {
      account_error('Somente Administrador pode criar lojas.', 403, 'ADMIN_ONLY');
    }

    // Enforce quota (max_stores) quando tenant_id existir
    if ($tenantId > 0) {
      if (!class_exists('SaasLimitsBridge')) {
        $saasLimitsPath = ROOT . '/../saas/includes/SaasLimitsBridge.php';
        if (file_exists($saasLimitsPath)) {
          require_once $saasLimitsPath;
        }
      }

      if (class_exists('SaasLimitsBridge') && method_exists('SaasLimitsBridge', 'canCreateStore')) {
        $check = SaasLimitsBridge::canCreateStore($pdo, (int)$tenantId);
        if (is_array($check) && isset($check[0]) && !$check[0]) {
          $limits = SaasLimitsBridge::getPlanLimits($pdo, (int)$tenantId);
          $max = (int)($limits['max_stores'] ?? 0);
          $used = method_exists('SaasLimitsBridge', 'countTenantStoresTotal')
            ? SaasLimitsBridge::countTenantStoresTotal($pdo, (int)$tenantId)
            : 0;

          account_error(
            (string)($check[1] ?? 'Limite de lojas do seu plano atingido. Faça upgrade.'),
            422,
            'LIMIT_REACHED',
            [
              'limit_type' => 'stores',
              'used' => (int)$used,
              'max' => (int)$max,
            ]
          );
        }
      }
    }

    try {
      if ($tenantId > 0) {
        $checkName = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ? AND name = ?');
        $checkName->execute([$tenantId, $name]);
      } else {
        $checkName = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE name = ?');
        $checkName->execute([$name]);
      }
      
      $existingCount = (int)$checkName->fetchColumn();
      if ($existingCount > 0) {
        throw new Exception('Já existe uma loja com o nome "' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '". Por favor, escolha outro nome.');
      }
    } catch (Exception $e) {
      // Se for a exceção que acabamos de lançar, propaga
      if (strpos($e->getMessage(), 'Já existe uma loja') !== false) {
        throw $e;
      }
      // Para outros erros (ex: coluna tenant_id não existe), continua
      error_log('[account_store] Aviso ao verificar nome duplicado: ' . $e->getMessage());
    }

    $country = trim((string)($_POST['country'] ?? 'BR'));
    if ($country === '') $country = 'BR';

    $mobile = trim((string)($_POST['mobile'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $zip = trim((string)($_POST['zip_code'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($mobile === '') {
      throw new Exception('Informe o telefone da loja.');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception('Informe um e-mail válido.');
    }
    if ($zip === '') {
      throw new Exception('Informe o CEP.');
    }
    if ($address === '') {
      throw new Exception('Informe o endereço.');
    }

    $codeBase = account_slugify((string)($_POST['code_name'] ?? $name));
    $codeName = account_unique_code_name($pdo, $codeBase, 0);

    // Defaults (tenant/global) => apply to columns where necessary
    $prefDefaults = account_get_defaults_pref($pdo);
    $remote_printing = isset($prefDefaults['remote_printing']) ? (int)$prefDefaults['remote_printing'] : 0;
    $sound_effect = isset($prefDefaults['sound_effect']) ? (int)$prefDefaults['sound_effect'] : 1;

    // Minimal required fields for ModelStore::addStore
    $storeData = [
      'name' => $name,
      'code_name' => $codeName,
      'mobile' => $mobile,
      'email' => $email,
      'country' => $country,
      'currency' => 'BRL',  // Define BRL como moeda padrão
      'vat_reg_no' => '',
      'zip_code' => $zip,
      // Use the current user as cashier by default (non-blocking)
      'cashier_id' => (int)user_id(),
      'address' => $address,
      'logo' => null,
      'favicon' => null,
      'sound_effect' => $sound_effect,
      'status' => 1,
      'sort_order' => 0,
      'receipt_printer' => '',
      'remote_printing' => $remote_printing,
      'auto_print' => 0,
      'tenant_id' => $tenantId, // IMPORTANTE: associar tenant_id para contagem correta de lojas
    ];

    $store_model = registry()->get('loader')->model('store');
    $storeId = (int)$store_model->addStore($storeData);

    if ($storeId <= 0) {
      throw new Exception('Não foi possível criar a loja.');
    }

    // Ensure owner is linked
    account_ensure_user_to_store($pdo, $storeId, (int)user_id());

    // Apply defaults to the new store (preference + pmethods/currency/unit/box + walkin customer)
    error_log("[account_store] Iniciando aplicação de defaults para loja {$storeId}");
    
    account_load_modernpos_defaults();
    
    if (!class_exists('ModernposDefaults')) {
      error_log("[account_store] ERRO: Classe ModernposDefaults não encontrada!");
    } else {
      error_log("[account_store] Classe ModernposDefaults carregada com sucesso");
      
      try {
        error_log("[account_store] Chamando ModernposDefaults::applyDefaultsToStore para loja {$storeId}");
        ModernposDefaults::applyDefaultsToStore($pdo, $storeId);
        error_log("[account_store] applyDefaultsToStore executado");
        
        // NOVO: Aplicar dados de exemplo globais (categoria, fornecedor, marca, conta, templates, produtos demo)
        // para manter consistência com lojas criadas via Checkout SaaS
        if (method_exists('ModernposDefaults', 'applyGlobalSampleDataToStore')) {
          error_log("[account_store] Chamando ModernposDefaults::applyGlobalSampleDataToStore para loja {$storeId}");
          ModernposDefaults::applyGlobalSampleDataToStore($pdo, $storeId);
          error_log("[account_store] applyGlobalSampleDataToStore executado");
        } else {
          error_log("[account_store] Método applyGlobalSampleDataToStore não encontrado");
        }
        
        // Verify that defaults were applied successfully
        $verify = $pdo->prepare('SELECT COUNT(*) FROM pmethod_to_store WHERE store_id = :sid');
        $verify->execute([':sid' => $storeId]);
        $pmethodCount = (int)$verify->fetchColumn();
        
        $verify = $pdo->prepare('SELECT COUNT(*) FROM currency_to_store WHERE store_id = :sid');
        $verify->execute([':sid' => $storeId]);
        $currencyCount = (int)$verify->fetchColumn();
        
        error_log("[account_store] Verificação de defaults - Loja {$storeId}: {$pmethodCount} métodos de pagamento, {$currencyCount} moedas");
        
        if ($pmethodCount === 0 || $currencyCount === 0) {
          error_log("[account_store] AVISO: Loja {$storeId} criada mas defaults incompletos. Métodos: {$pmethodCount}, Moedas: {$currencyCount}");
        } else {
          error_log("[account_store] SUCESSO: Defaults aplicados corretamente na loja {$storeId}");
        }
        
        // Verificar cliente balcão
        $verifyCustomer = $pdo->prepare('SELECT preference FROM stores WHERE store_id = :sid LIMIT 1');
        $verifyCustomer->execute([':sid' => $storeId]);
        $pref = $verifyCustomer->fetchColumn();
        if ($pref) {
          $prefArray = @unserialize($pref);
          if (is_array($prefArray) && isset($prefArray['walkin_customer_id'])) {
            error_log("[account_store] Cliente balcão criado: ID {$prefArray['walkin_customer_id']}");
          } else {
            error_log("[account_store] AVISO: Cliente balcão NÃO foi criado!");
          }
        }
        
      } catch (Throwable $eDefaults) {
        error_log('[account_store] ERRO ao aplicar ModernposDefaults para loja ' . $storeId . ': ' . $eDefaults->getMessage());
        error_log('[account_store] Stack trace: ' . $eDefaults->getTraceAsString());
        // Não lança exceção para não impedir a criação da loja
      }
    }

    // Importar produtos selecionados (opcional)
    $productIds = [];

    // 1) Se veio como array (product_ids[])
    if (isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
      foreach ($_POST['product_ids'] as $pid) {
        $pid = (int)$pid;
        if ($pid > 0) {
          $productIds[] = $pid;
        }
      }
    }

    // 2) Fallback: JSON em import_product_ids
    if (empty($productIds)) {
      $raw = trim((string)($_POST['import_product_ids'] ?? ''));
      if ($raw !== '') {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) {
          foreach ($tmp as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) {
              $productIds[] = $pid;
            }
          }
        }
      }
    }

    $productIds = array_values(array_unique($productIds));

    if (!empty($productIds)) {
      foreach ($productIds as $product_id) {
        try {
          $product_info = get_the_product($product_id);
          if (!$product_info || !is_array($product_info)) {
            continue;
          }

          $category_id = (int)($product_info['category_id'] ?? 0);
          $box_id = (int)($product_info['box_id'] ?? 0);
          $sup_id = (int)($product_info['sup_id'] ?? 0);
          $e_date = $product_info['e_date'] ?? null;

          // Category to store
          if ($category_id > 0) {
            $st = $pdo->prepare('SELECT 1 FROM category_to_store WHERE store_id = ? AND ccategory_id = ? LIMIT 1');
            $st->execute([(int)$storeId, $category_id]);
            if (!$st->fetchColumn()) {
              $ins = $pdo->prepare('INSERT INTO category_to_store (ccategory_id, store_id) VALUES (?, ?)');
              $ins->execute([$category_id, (int)$storeId]);
            }
          }

          // Box to store
          if ($box_id > 0) {
            $st = $pdo->prepare('SELECT 1 FROM box_to_store WHERE store_id = ? AND box_id = ? LIMIT 1');
            $st->execute([(int)$storeId, $box_id]);
            if (!$st->fetchColumn()) {
              $ins = $pdo->prepare('INSERT INTO box_to_store (box_id, store_id) VALUES (?, ?)');
              $ins->execute([$box_id, (int)$storeId]);
            }
          }

          // Supplier to store
          if ($sup_id > 0) {
            $st = $pdo->prepare('SELECT 1 FROM supplier_to_store WHERE store_id = ? AND sup_id = ? LIMIT 1');
            $st->execute([(int)$storeId, $sup_id]);
            if (!$st->fetchColumn()) {
              $ins = $pdo->prepare('INSERT INTO supplier_to_store (sup_id, store_id) VALUES (?, ?)');
              $ins->execute([$sup_id, (int)$storeId]);
            }
          }

          // Product link to store
          $st = $pdo->prepare('SELECT 1 FROM product_to_store WHERE product_id = ? AND store_id = ? LIMIT 1');
          $st->execute([(int)$product_id, (int)$storeId]);
          if (!$st->fetchColumn()) {
            $ins = $pdo->prepare('INSERT INTO product_to_store (product_id, store_id, sup_id, box_id, e_date, p_date) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->execute([(int)$product_id, (int)$storeId, $sup_id, $box_id, $e_date, date('Y-m-d')]);
          }

        } catch (Throwable $eImport) {
          // não interrompe a criação da loja
          try {
            error_log('[account_store] Falha ao importar produto ' . $product_id . ' na loja ' . $storeId . ': ' . $eImport->getMessage());
          } catch (Throwable $_) {
            // ignore
          }
        }
      }
    }

    account_json([
      'ok' => true,
      'id' => $storeId,
      'msg' => 'Loja criada com sucesso.',
    ]);
  }

  // =====================
  // Update store (editar)
  // =====================
  if ($action === 'update') {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId <= 0) {
      throw new Exception('store_id inválido.');
    }

    if (!account_user_can_access_store($pdo, $storeId)) {
      throw new Exception('Acesso negado para esta loja.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
      throw new Exception('Informe o nome da loja.');
    }

    $country = trim((string)($_POST['country'] ?? 'BR'));
    if ($country === '') $country = 'BR';

    $mobile = trim((string)($_POST['mobile'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $zip = trim((string)($_POST['zip_code'] ?? ''));
    $vat = trim((string)($_POST['vat_reg_no'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    $cashierIdRaw = trim((string)($_POST['cashier_id'] ?? ''));
    $cashierId = $cashierIdRaw === '' ? null : (int)$cashierIdRaw;

    $codeBase = account_slugify((string)($_POST['code_name'] ?? $name));
    $codeName = account_unique_code_name($pdo, $codeBase, $storeId);

    // Update store base fields
    if ($cashierId !== null && $cashierId > 0) {
      // Ensure cashier exists
      $chkUser = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
      $chkUser->execute([$cashierId]);
      if (!$chkUser->fetchColumn()) {
        $cashierId = null;
      }
    }

    if ($cashierId !== null && $cashierId > 0) {
      $st = $pdo->prepare('UPDATE stores SET name = ?, code_name = ?, country = ?, mobile = ?, email = ?, zip_code = ?, vat_reg_no = ?, address = ?, cashier_id = ?, status = ? WHERE store_id = ?');
      $st->execute([$name, $codeName, $country, $mobile, $email, $zip, $vat, $address, $cashierId, (int)($_POST['status'] ?? 1), $storeId]);

      // Ensure cashier is linked too
      account_ensure_user_to_store($pdo, $storeId, $cashierId);
    } else {
      $st = $pdo->prepare('UPDATE stores SET name = ?, code_name = ?, country = ?, mobile = ?, email = ?, zip_code = ?, vat_reg_no = ?, address = ?, status = ? WHERE store_id = ?');
      $st->execute([$name, $codeName, $country, $mobile, $email, $zip, $vat, $address, (int)($_POST['status'] ?? 1), $storeId]);
    }

    // Update preference subset (gst_reg_no)
    $stmtGet = $pdo->prepare('SELECT preference FROM stores WHERE store_id = ? LIMIT 1');
    $stmtGet->execute([$storeId]);
    $pref = account_unserialize_array($stmtGet->fetchColumn());

    $gst = '';
    if (isset($_POST['preference']) && is_array($_POST['preference']) && isset($_POST['preference']['gst_reg_no'])) {
      $gst = trim((string)$_POST['preference']['gst_reg_no']);
    }
    $pref['gst_reg_no'] = $gst;

    $stmtUpd = $pdo->prepare('UPDATE stores SET preference = ? WHERE store_id = ?');
    $stmtUpd->execute([serialize($pref), $storeId]);

    account_json([
      'ok' => true,
      'id' => $storeId,
      'msg' => 'Loja atualizada com sucesso.',
    ]);
  }

  // =====================
  // Get store details (for edit modal)
  // =====================
  if ($action === 'get') {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId <= 0) {
      throw new Exception('store_id inválido.');
    }

    if (!account_user_can_access_store($pdo, $storeId)) {
      throw new Exception('Acesso negado para esta loja.');
    }

    $stmt = $pdo->prepare('SELECT * FROM stores WHERE store_id = ? LIMIT 1');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
      throw new Exception('Loja não encontrada.');
    }

    account_json([
      'ok' => true,
      'store' => $store,
    ]);
  }

  // =====================
  // Delete store
  // =====================
  if ($action === 'delete') {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId <= 0) {
      throw new Exception('store_id inválido.');
    }

    if (!account_user_can_access_store($pdo, $storeId)) {
      throw new Exception('Acesso negado para esta loja.');
    }

    $tenantId = account_current_tenant_id();

    // Verificar se não é a última loja do tenant
    try {
      if ($tenantId > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
      } else {
        $stmt = $pdo->query('SELECT COUNT(*) FROM stores');
      }
      $count = (int)$stmt->fetchColumn();

      if ($count <= 1) {
        throw new Exception('Não é possível excluir a última loja.');
      }
    } catch (Throwable $e) {
      if (strpos($e->getMessage(), 'última loja') !== false) {
        throw $e;
      }
      // Ignorar outros erros e permitir exclusão
    }

    // Delete related records first
    try {
      $pdo->prepare('DELETE FROM currency_to_store WHERE store_id = ?')->execute([$storeId]);
      $pdo->prepare('DELETE FROM pmethod_to_store WHERE store_id = ?')->execute([$storeId]);
      $pdo->prepare('DELETE FROM user_to_store WHERE store_id = ?')->execute([$storeId]);
      $pdo->prepare('DELETE FROM box_to_store WHERE store_id = ?')->execute([$storeId]);
    } catch (Throwable $e) {
      // Ignorar erros de relacionamento
    }

    // Delete store
    $stmt = $pdo->prepare('DELETE FROM stores WHERE store_id = ?');
    $stmt->execute([$storeId]);

    account_json([
      'ok' => true,
      'msg' => 'Loja excluída com sucesso.',
    ]);
  }

  // ===============================
  // Store Settings (Account) - Get
  // ===============================
  if ($action === 'store_settings_get') {
    error_log('[account_store] store_settings_get iniciado. POST: ' . json_encode($_POST));
    error_log('[account_store] user_id: ' . (function_exists('user_id') ? user_id() : 'N/A'));
    error_log('[account_store] user_group_id: ' . (function_exists('user_group_id') ? user_group_id() : 'N/A'));
    error_log('[account_store] tenant_id: ' . account_current_tenant_id());
    
    $storeId = (int)($_POST['store_id'] ?? 0);
    error_log('[account_store] storeId recebido: ' . $storeId);
    
    if ($storeId <= 0) {
      error_log('[account_store] ERRO: store_id inválido ou zero');
      throw new Exception('store_id inválido.');
    }

    $canAccess = account_user_can_access_store($pdo, $storeId);
    error_log('[account_store] account_user_can_access_store retornou: ' . ($canAccess ? 'true' : 'false'));
    
    if (!$canAccess) {
      error_log('[account_store] ERRO: Acesso negado para loja ' . $storeId);
      throw new Exception('Acesso negado para esta loja.');
    }

    $stmt = $pdo->prepare('SELECT * FROM stores WHERE store_id = ? LIMIT 1');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
      throw new Exception('Loja não encontrada.');
    }

    $pref = account_unserialize_array($store['preference'] ?? '');

    $logoFile = isset($store['logo']) && $store['logo'] ? (string)$store['logo'] : 'nologo.png';
    $logoUrl = root_url() . 'assets/itsolution24/img/logo-favicons/' . $logoFile . '?v=' . time();

    account_json([
      'ok' => true,
      'store' => [
        'store_id' => (int)($store['store_id'] ?? 0),
        'name' => (string)($store['name'] ?? ''),
        'code_name' => (string)($store['code_name'] ?? ''),
        'country' => (string)($store['country'] ?? ''),
        'mobile' => (string)($store['mobile'] ?? ''),
        'email' => (string)($store['email'] ?? ''),
        'zip_code' => (string)($store['zip_code'] ?? ''),
        'vat_reg_no' => (string)($store['vat_reg_no'] ?? ''),
        'address' => (string)($store['address'] ?? ''),
        'remote_printing' => (int)($store['remote_printing'] ?? 0),
        'receipt_printer' => (string)($store['receipt_printer'] ?? ''),
        'auto_print' => (int)($store['auto_print'] ?? 0),
        'sound_effect' => (int)($store['sound_effect'] ?? 0),
        'status' => (int)($store['status'] ?? 0),
        'logo' => (string)($store['logo'] ?? ''),
        'logo_url' => $logoUrl,
        'preference' => $pref,
      ],
    ]);
  }

  // ===============================
  // Store Settings (Account) - Save
  // ===============================
  if ($action === 'store_settings_save') {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId <= 0) {
      throw new Exception('store_id inválido.');
    }

    if (!account_user_can_access_store($pdo, $storeId)) {
      throw new Exception('Acesso negado para esta loja.');
    }

    $stmt = $pdo->prepare('SELECT * FROM stores WHERE store_id = ? LIMIT 1');
    $stmt->execute([$storeId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
      throw new Exception('Loja não encontrada.');
    }

    $tenantId = account_current_tenant_id();

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
      throw new Exception('Informe o nome da loja.');
    }

    $codeNameRaw = trim((string)($_POST['code_name'] ?? ''));
    $codeName = account_slugify($codeNameRaw !== '' ? $codeNameRaw : $name);
    if ($codeName === '') {
      throw new Exception('Informe o código da loja.');
    }

    $country = trim((string)($_POST['country'] ?? ''));
    if ($country === '') {
      throw new Exception('Informe o país.');
    }

    // Campos opcionais para configurações rápidas (obrigatórios apenas no admin completo)
    $mobile = trim((string)($_POST['mobile'] ?? $current['mobile'] ?? ''));
    $email = trim((string)($_POST['email'] ?? $current['email'] ?? ''));
    $zip = trim((string)($_POST['zip_code'] ?? $current['zip_code'] ?? ''));
    $address = trim((string)($_POST['address'] ?? $current['address'] ?? ''));

    // Validação de email apenas se preenchido
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception('E-mail inválido.');
    }

    $vat = trim((string)($_POST['vat_reg_no'] ?? ''));

    $remotePrinting = (int)($_POST['remote_printing'] ?? 0);
    $remotePrinting = $remotePrinting ? 1 : 0;

    $receiptPrinter = trim((string)($_POST['receipt_printer'] ?? ''));
    if ($remotePrinting === 1 && $receiptPrinter === '') {
      throw new Exception('Selecione a impressora de recibo para o modo PHP Server.');
    }

    $autoPrint = (int)($_POST['auto_print'] ?? 0);
    $autoPrint = $autoPrint ? 1 : 0;

    $soundEffect = (int)($_POST['sound_effect'] ?? 0);
    $soundEffect = $soundEffect ? 1 : 0;

    // Unicidade: nome
    try {
      if ($tenantId > 0) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ? AND name = ? AND store_id != ?');
        $q->execute([$tenantId, $name, $storeId]);
      } else {
        $q = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE name = ? AND store_id != ?');
        $q->execute([$name, $storeId]);
      }
      if ((int)$q->fetchColumn() > 0) {
        throw new Exception('Já existe uma loja com este nome.');
      }
    } catch (Throwable $e) {
      // se a coluna tenant_id não existir, cai no modo global
      if (strpos($e->getMessage(), 'Já existe') !== false) {
        throw $e;
      }
      $q = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE name = ? AND store_id != ?');
      $q->execute([$name, $storeId]);
      if ((int)$q->fetchColumn() > 0) {
        throw new Exception('Já existe uma loja com este nome.');
      }
    }

    // Unicidade: code_name
    try {
      if ($tenantId > 0) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ? AND code_name = ? AND store_id != ?');
        $q->execute([$tenantId, $codeName, $storeId]);
      } else {
        $q = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE code_name = ? AND store_id != ?');
        $q->execute([$codeName, $storeId]);
      }
      if ((int)$q->fetchColumn() > 0) {
        throw new Exception('Já existe uma loja com este código.');
      }
    } catch (Throwable $e) {
      if (strpos($e->getMessage(), 'Já existe') !== false) {
        throw $e;
      }
      $q = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE code_name = ? AND store_id != ?');
      $q->execute([$codeName, $storeId]);
      if ((int)$q->fetchColumn() > 0) {
        throw new Exception('Já existe uma loja com este código.');
      }
    }

    // Preferences subset
    $incoming = isset($_POST['preference']) && is_array($_POST['preference']) ? $_POST['preference'] : [];

    $pref = account_unserialize_array($current['preference'] ?? '');

    $setPrefStr = function ($key, $default = '') use (&$incoming) {
      return isset($incoming[$key]) ? trim((string)$incoming[$key]) : $default;
    };

    $setPrefInt = function ($key, $default = 0) use (&$incoming) {
      $v = isset($incoming[$key]) ? $incoming[$key] : $default;
      return (int)$v;
    };

    $timezone = $setPrefStr('timezone', (string)($pref['timezone'] ?? ''));
    if ($timezone === '') {
      throw new Exception('Selecione o timezone.');
    }

    $invoiceEdit = $setPrefInt('invoice_edit_lifespan', (int)($pref['invoice_edit_lifespan'] ?? 0));
    if ($invoiceEdit < 0) $invoiceEdit = 0;

    $invoiceEditUnit = $setPrefStr('invoice_edit_lifespan_unit', (string)($pref['invoice_edit_lifespan_unit'] ?? 'minute'));
    if (!in_array($invoiceEditUnit, ['minute', 'second'], true)) {
      $invoiceEditUnit = 'minute';
    }

    $invoiceDelete = $setPrefInt('invoice_delete_lifespan', (int)($pref['invoice_delete_lifespan'] ?? 0));
    if ($invoiceDelete < 0) $invoiceDelete = 0;

    $invoiceDeleteUnit = $setPrefStr('invoice_delete_lifespan_unit', (string)($pref['invoice_delete_lifespan_unit'] ?? 'minute'));
    if (!in_array($invoiceDeleteUnit, ['minute', 'second'], true)) {
      $invoiceDeleteUnit = 'minute';
    }

    $tax = (float)$setPrefStr('tax', (string)($pref['tax'] ?? 0));
    if ($tax < 0) $tax = 0;
    if ($tax > 99) $tax = 99;

    $stockAlertQty = $setPrefInt('stock_alert_quantity', (int)($pref['stock_alert_quantity'] ?? 0));
    if ($stockAlertQty < 0) $stockAlertQty = 0;

    $datatableLimit = $setPrefInt('datatable_item_limit', (int)($pref['datatable_item_limit'] ?? 25));
    if ($datatableLimit < 1) $datatableLimit = 25;

    $referenceFormat = $setPrefStr('reference_format', (string)($pref['reference_format'] ?? 'year_month_sequence'));
    $allowedRef = ['year_sequence', 'year_month_sequence', 'sequence', 'random'];
    if (!in_array($referenceFormat, $allowedRef, true)) {
      $referenceFormat = 'year_month_sequence';
    }

    $salesPrefix = $setPrefStr('sales_reference_prefix', (string)($pref['sales_reference_prefix'] ?? ''));

    $receiptTemplate = $setPrefStr('receipt_template', (string)($pref['receipt_template'] ?? ''));

    $posLimit = $setPrefInt('pos_product_display_limit', (int)($pref['pos_product_display_limit'] ?? 0));
    if ($posLimit < 0) $posLimit = 0;

    $afterSell = $setPrefStr('after_sell_page', (string)($pref['after_sell_page'] ?? 'pos'));
    $allowedAfterSell = ['pos', 'receipt_in_new_window', 'receipt_in_popup', 'toastr_msg', 'sweet_alert_msg', 'invoice'];
    if (!in_array($afterSell, $allowedAfterSell, true)) {
      $afterSell = 'pos';
    }

    $changePrice = $setPrefStr('change_item_price_while_billing', (string)($pref['change_item_price_while_billing'] ?? '0'));
    $changePrice = in_array($changePrice, ['1', 'true', 'yes'], true) ? '1' : '0';

    $invoiceFooter = $setPrefStr('invoice_footer_text', (string)($pref['invoice_footer_text'] ?? ''));

    $pref['timezone'] = $timezone;
    $pref['invoice_edit_lifespan'] = $invoiceEdit;
    $pref['invoice_edit_lifespan_unit'] = $invoiceEditUnit;
    $pref['invoice_delete_lifespan'] = $invoiceDelete;
    $pref['invoice_delete_lifespan_unit'] = $invoiceDeleteUnit;
    $pref['tax'] = $tax;
    $pref['stock_alert_quantity'] = $stockAlertQty;
    $pref['datatable_item_limit'] = $datatableLimit;
    $pref['reference_format'] = $referenceFormat;
    $pref['sales_reference_prefix'] = $salesPrefix;
    $pref['receipt_template'] = $receiptTemplate;
    $pref['pos_product_display_limit'] = $posLimit;
    $pref['after_sell_page'] = $afterSell;
    $pref['change_item_price_while_billing'] = $changePrice;
    $pref['invoice_footer_text'] = $invoiceFooter;

    $prefSer = serialize($pref);

    $stmtUpd = $pdo->prepare('UPDATE stores SET name = ?, code_name = ?, country = ?, mobile = ?, email = ?, zip_code = ?, vat_reg_no = ?, address = ?, remote_printing = ?, receipt_printer = ?, auto_print = ?, sound_effect = ?, preference = ? WHERE store_id = ?');
    $stmtUpd->execute([
      $name,
      $codeName,
      $country,
      $mobile,
      $email,
      $zip,
      $vat,
      $address,
      $remotePrinting,
      $receiptPrinter,
      $autoPrint,
      $soundEffect,
      $prefSer,
      $storeId,
    ]);

    account_json([
      'ok' => true,
      'id' => $storeId,
      'msg' => 'Configurações salvas com sucesso.',
    ]);
  }

  // ===============================
  // Update store extras (config)
  // ===============================
  if ($action === 'update_extras') {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId <= 0) {
      throw new Exception('store_id inválido.');
    }

    if (!account_user_can_access_store($pdo, $storeId)) {
      throw new Exception('Acesso negado para esta loja.');
    }

    $allowed = [
      'email_driver',
      'email_from',
      'email_address',
      'smtp_host',
      'smtp_port',
      'ssl_tls',
      'ftp_hostname',
      'ftp_username',
      'ftp_password',
    ];

    $incomingPref = [];
    if (isset($_POST['preference']) && is_array($_POST['preference'])) {
      foreach ($allowed as $k) {
        if (array_key_exists($k, $_POST['preference'])) {
          $incomingPref[$k] = trim((string)$_POST['preference'][$k]);
        }
      }
    }

    $stmtGet = $pdo->prepare('SELECT preference FROM stores WHERE store_id = ? LIMIT 1');
    $stmtGet->execute([$storeId]);
    $pref = account_unserialize_array($stmtGet->fetchColumn());

    foreach ($incomingPref as $k => $v) {
      $pref[$k] = $v;
    }

    $stmtUpd = $pdo->prepare('UPDATE stores SET preference = ? WHERE store_id = ?');
    $stmtUpd->execute([serialize($pref), $storeId]);

    account_json([
      'ok' => true,
      'id' => $storeId,
      'msg' => 'Configurações salvas com sucesso.',
    ]);
  }

  // =====================
  // List products (for import modal)
  // =====================
  if ($action === 'list_products') {
    $storeId = (int)($_POST['store_id'] ?? 0);
    if ($storeId <= 0) {
      throw new Exception('store_id inválido.');
    }

    if (!account_user_can_access_store($pdo, $storeId)) {
      throw new Exception('Acesso negado para esta loja.');
    }

    // Lista básica (para o modal): nome + SKU + categoria
    $out = [];

    $queries = [
      // schema padrão ModernPOS
      "SELECT p.p_id AS id, p.p_name AS name, p.p_code AS sku, c.category_name AS category " .
      "FROM product_to_store pts " .
      "INNER JOIN products p ON (p.p_id = pts.product_id) " .
      "LEFT JOIN categorys c ON (c.category_id = p.category_id) " .
      "WHERE pts.store_id = ? ORDER BY p.p_name ASC LIMIT 500",

      // fallback: products.product_id
      "SELECT p.product_id AS id, p.p_name AS name, p.p_code AS sku, c.category_name AS category " .
      "FROM product_to_store pts " .
      "INNER JOIN products p ON (p.product_id = pts.product_id) " .
      "LEFT JOIN categorys c ON (c.category_id = p.category_id) " .
      "WHERE pts.store_id = ? ORDER BY p.p_name ASC LIMIT 500",

      // sem categoria
      "SELECT p.p_id AS id, p.p_name AS name, p.p_code AS sku, '' AS category " .
      "FROM product_to_store pts " .
      "INNER JOIN products p ON (p.p_id = pts.product_id) " .
      "WHERE pts.store_id = ? ORDER BY p.p_name ASC LIMIT 500",

      // sem categoria + products.product_id
      "SELECT p.product_id AS id, p.p_name AS name, p.p_code AS sku, '' AS category " .
      "FROM product_to_store pts " .
      "INNER JOIN products p ON (p.product_id = pts.product_id) " .
      "WHERE pts.store_id = ? ORDER BY p.p_name ASC LIMIT 500",
    ];

    foreach ($queries as $sql) {
      try {
        $st = $pdo->prepare($sql);
        $st->execute([$storeId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
          $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'name' => (string)($r['name'] ?? ''),
            'sku' => (string)($r['sku'] ?? ''),
            'category' => (string)($r['category'] ?? ''),
          ];
        }
        break;
      } catch (Throwable $e) {
        // tenta próxima query
        $out = [];
      }
    }

    account_json([
      'ok' => true,
      'store_id' => $storeId,
      'products' => $out,
    ]);
  }

  // =========================
  // Defaults (tenant/global)
  // =========================
  if ($action === 'get_defaults') {
    account_load_modernpos_defaults();
    if (!class_exists('ModernposDefaults')) {
      account_json(['ok' => true, 'preference' => []]);
    }

    $d = ModernposDefaults::getDefaults($pdo);
    $pref = isset($d['preference']) && is_array($d['preference']) ? $d['preference'] : [];

    account_json([
      'ok' => true,
      'preference' => $pref,
    ]);
  }

  if ($action === 'save_defaults') {
    $payloadRaw = isset($_POST['defaults_json']) ? (string)$_POST['defaults_json'] : '';
    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
      throw new Exception('Payload inválido.');
    }

    $pref = isset($payload['preference']) && is_array($payload['preference']) ? $payload['preference'] : [];

    // Se a tabela não existir, falha para que o front faça fallback em localStorage.
    try {
      $stmt = $pdo->query("SHOW TABLES LIKE 'modernpos_store_defaults'");
      $has = $stmt && $stmt->fetchColumn();
      if (!$has) {
        throw new Exception('A tabela modernpos_store_defaults não existe (defaults não podem ser salvos no banco).');
      }
    } catch (Throwable $e) {
      throw new Exception('A tabela modernpos_store_defaults não existe (defaults não podem ser salvos no banco).');
    }

    account_load_modernpos_defaults();
    if (class_exists('ModernposDefaults')) {
      // Salva apenas preference. (pmethods/currencies/units/boxes permanecem no default canônico)
      ModernposDefaults::saveDefaults($pdo, $pref, []);
    }

    account_json([
      'ok' => true,
      'msg' => 'Padrões salvos com sucesso.',
    ]);
  }

  throw new Exception('Ação inválida.');

} catch (Exception $e) {
  account_error($e->getMessage(), 422);
}
