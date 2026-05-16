<?php
// Painel da Conta (Visão Geral das Lojas) usando o template AdminLTE 4.
// Este arquivo é incluído por store_select.php APÓS o _init.php, então
// $user, root_url() etc. já estão disponíveis aqui.

// Carregar helper de controle de acesso
require_once __DIR__ . '/account/includes/account_access.php';

// Define qual "seção" estamos vendo neste painel (overview, stores, plans, users, reports)
$section = isset($_GET['section']) ? $_GET['section'] : 'overview';
$tab     = isset($_GET['tab']) ? $_GET['tab'] : null;

// Compatibilidade com o parâmetro antigo ?view=overview/manage
if (!isset($_GET['section']) && isset($_GET['view'])) {
    $section = $_GET['view'] === 'manage' ? 'stores' : 'overview';
}

// =======================================================
// Dados globais para UI (Owner/Admin + limites de lojas)
// =======================================================
$accountIsOwnerOrAdmin = (function_exists('user_group_id') && (int)user_group_id() === 1)
  || (function_exists('is_tenant_owner') && is_tenant_owner());

$accountStoresUsedTotal = 0;
$accountStoresMax = 0;

try {
  if (!class_exists('SaasLimitsBridge')) {
    $saasLimitsPath = __DIR__ . '/../saas/includes/SaasLimitsBridge.php';
    if (file_exists($saasLimitsPath)) {
      require_once $saasLimitsPath;
    }
  }

  if (class_exists('SaasLimitsBridge')) {
    $pdo = db();
    $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    $uid = function_exists('user_id') ? (int)user_id() : 0;

    $tenantIdForLimits = SaasLimitsBridge::resolveTenantId($pdo, $uid, $sessionTid > 0 ? $sessionTid : null);
    if ((int)$tenantIdForLimits > 0) {
      $limits = SaasLimitsBridge::getPlanLimits($pdo, (int)$tenantIdForLimits);
      $accountStoresMax = (int)($limits['max_stores'] ?? 0);

      if (method_exists('SaasLimitsBridge', 'countTenantStoresTotal')) {
        $accountStoresUsedTotal = (int)SaasLimitsBridge::countTenantStoresTotal($pdo, (int)$tenantIdForLimits);
      } else {
        // Fallback simples
        try {
          $st = $pdo->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ?');
          $st->execute([(int)$tenantIdForLimits]);
          $accountStoresUsedTotal = (int)$st->fetchColumn();
        } catch (Throwable $eCount) {
          $accountStoresUsedTotal = 0;
        }
      }
    }
  }
} catch (Throwable $e) {
  // ignore
}
// =======================================================
// VERIFICA STATUS DE TRIAL PARA EXIBIR ALERTA
// =======================================================
$trialAlertShow = false;
$trialDaysRemaining = 0;
$subscriptionStatus = '';

try {
    $pdo = db();
    $currentTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
    
    // Fallback: buscar tenant_id do usuário se não estiver na sessão
    if ($currentTenantId <= 0 && function_exists('user_id') && user_id() > 0) {
        $stmtUser = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([user_id()]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($userRow && !empty($userRow['tenant_id'])) {
            $currentTenantId = (int)$userRow['tenant_id'];
        }
    }
    
    if ($currentTenantId > 0) {
        $stmtTenant = $pdo->prepare("
            SELECT subscription_status, trial_ends_at, subscription_expires_at 
            FROM tenants 
            WHERE tenant_id = ? 
            LIMIT 1
        ");
        $stmtTenant->execute([$currentTenantId]);
        $tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
        
        if ($tenantData) {
            $subscriptionStatus = $tenantData['subscription_status'] ?? '';
            
            if ($subscriptionStatus === 'trial' && !empty($tenantData['trial_ends_at'])) {
                $trialEndsAt = new DateTime($tenantData['trial_ends_at']);
                $now = new DateTime();
                $diff = $now->diff($trialEndsAt);
                
                if ($now < $trialEndsAt) {
                    $trialDaysRemaining = (int)$diff->days;
                    $trialAlertShow = true;
                } else {
                    $trialDaysRemaining = 0;
                    $trialAlertShow = true;
                }
            }
        }
    }
} catch (Exception $e) {
    // Silencioso
}
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>Visão Geral das Lojas - ModernPOS (Conta)</title>
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <meta name="color-scheme" content="light dark" />
    <meta
      name="description"
      content="Painel de conta ModernPOS para acompanhar o desempenho de todas as lojas."
    />

    <?php // Garante que todos os caminhos ./css, ./js, ./assets do AdminLTE apontem para a pasta dist ?>
    <base href="<?php echo rtrim(root_url(), '/'); ?>/AdminLTE-4.0.0-rc4/dist/" />

    <!-- Fonte padrão do AdminLTE 4 -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
      crossorigin="anonymous"
      media="print"
      onload="this.media='all'"
    />

    <!-- OverlayScrollbars -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
      crossorigin="anonymous"
    />

    <!-- Bootstrap Icons -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
      crossorigin="anonymous"
    />

    <!-- CSS principal do AdminLTE 4 -->
    <link rel="preload" href="./css/adminlte.css" as="style" />
    <link rel="stylesheet" href="./css/adminlte.css" />

    <!-- Modal Premium (mesmo estilo de /conta/usuarios) -->
    <link rel="stylesheet" href="<?php echo root_url(); ?>account/css/modal_premium.css" />

    <!-- Modal: Criar loja (custom) -->
    <link rel="stylesheet" href="<?php echo root_url(); ?>account/css/store_create_modal.css" />

    <?php if ($section === 'store_settings') : ?>
      <link rel="stylesheet" href="<?php echo root_url(); ?>account/css/store_settings.css" />
    <?php endif; ?>

    <?php if ($section === 'users') : ?>
      <link rel="stylesheet" href="<?php echo root_url(); ?>account/css/users.css" />
    <?php endif; ?>

    <?php if ($section === 'support' || $section === 'support_ticket') : ?>
      <link rel="stylesheet" href="<?php echo root_url(); ?>account/css/support.css" />
    <?php endif; ?>

    <!-- Font Awesome (ícones usados nas páginas de conta) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    />

    <!-- Customização para aproximar a paleta do ModernPOS (AdminLTE 2) -->
      <style>
        :root {
          /* Largura semelhante ao sidebar do ModernPOS */
          --lte-sidebar-width: 230px;
          --lte-sidebar-mini-width: 60px;
        }

      /* Fundo e cores do menu lateral no mesmo estilo do ModernPOS */
      .app-sidebar[data-bs-theme="dark"] {
        background-color: #222d32 !important;
      }

      .app-sidebar .brand-link {
        background-color: #1a2226;
        border-bottom: 1px solid #4b545c;
      }

      .app-sidebar .sidebar-menu .nav-link,
      .app-sidebar .nav-link {
        color: #b8c7ce;
        font-size: 0.88rem;
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
      }

      .app-sidebar .nav-icon {
        /* Cor mint usada nos ícones SVG do ModernPOS (#9effd3) */
        color: #9effd3;
        font-size: 1rem;
      }

      .app-sidebar .nav-link.active,
      .app-sidebar .nav-link:hover {
        background-color: #1e282c;
        color: #ffffff;
      }

      /* Mantém os ícones sempre na cor mint, mesmo no hover/ativo */
      .app-sidebar .nav-link .nav-icon,
      .app-sidebar .nav-link.active .nav-icon,
      .app-sidebar .nav-link:hover .nav-icon {
        color: #9effd3;
      }

      /* Estilo dos submenus (treeview) com linha vertical, parecido com ModernPOS */
      .app-sidebar .nav-treeview {
        border-left: 1px solid #3c4b52;
        margin-left: 0.75rem;
        padding-left: 0.25rem;
      }

      .app-sidebar .nav-treeview > .nav-item > .nav-link {
        position: relative;
        padding-left: 1.5rem; /* espaço para a linha horizontal + ícone */
        font-size: 0.82rem;
      }

      /* Pequena linha horizontal ligando a linha vertical ao item */
      .app-sidebar .nav-treeview > .nav-item > .nav-link::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        width: 10px;
        height: 1px;
        background-color: #3c4b52;
        transform: translateY(-50%);
      }

      /* Hover/ativo específico dos submenus */
      .app-sidebar .nav-treeview .nav-link {
        background-color: transparent;
      }

      .app-sidebar .nav-treeview .nav-link.active,
      .app-sidebar .nav-treeview .nav-link:hover {
        background-color: #1e282c;
        color: #ffffff;
      }

      /* Remove a "bolinha" (ícone bi-circle) dos sub-menus, deixando só a linha */
      .app-sidebar .nav-treeview .nav-icon.bi-circle {
        display: none;
      }
    </style>

    <!-- (Opcional) Chart.js só para os gráficos de exemplo -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  </head>
  <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
      <?php 
      // Banner de impersonação (admin SaaS logado como cliente)
      $isImpersonateMode = !empty($_SESSION['impersonate_mode']);
      if ($isImpersonateMode): 
      ?>
      <div class="impersonate-banner" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 1050;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <i class="bi bi-shield-exclamation" style="font-size: 1.2rem;"></i>
          <span>
            <strong>Modo Administrador:</strong> 
            Você está logado como cliente 
            <?php echo isset($_SESSION['username']) ? '(' . htmlspecialchars($_SESSION['username']) . ')' : ''; ?>
          </span>
        </div>
        <a href="<?php echo rtrim(str_replace('modernpos', 'saas', root_url()), '/'); ?>/painel/index.php?pag=clientes" 
           class="btn btn-light btn-sm" 
           style="font-weight: 600; display: flex; align-items: center; gap: 6px;">
          <i class="bi bi-arrow-left"></i> Voltar ao SAAS
        </a>
      </div>
      <?php endif; ?>
      
      <?php // Banner de Trial (Período de Teste) ?>
      <?php if ($trialAlertShow): ?>
      <div class="trial-alert-banner" style="
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: #fff;
          padding: 14px 24px;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-wrap: wrap;
          gap: 15px;
          position: relative;
          z-index: 1049;
          box-shadow: 0 2px 10px rgba(0,0,0,0.15);
      ">
          <i class="bi bi-clock-fill" style="font-size: 1.3rem;"></i>
          <span style="font-size: 0.95rem; text-align: center;">
              <strong>🎉 Você está no período de teste!</strong>
              <?php if ($trialDaysRemaining > 0): ?>
                  Restam <strong style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px;"><?php echo $trialDaysRemaining; ?> dia<?php echo $trialDaysRemaining > 1 ? 's' : ''; ?></strong> para escolher um plano.
              <?php else: ?>
                  <span style="background: rgba(255,59,59,0.3); padding: 2px 8px; border-radius: 4px;">O período de teste expirou.</span> Escolha um plano para continuar.
              <?php endif; ?>
          </span>
          <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-light btn-sm" style="font-weight: 600; white-space: nowrap;">
              <i class="bi bi-arrow-right-circle me-1"></i> Ver Planos
          </a>
      </div>
      <?php endif; ?>
      
      <!-- Navbar topo -->
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a
                class="nav-link"
                data-lte-toggle="sidebar"
                href="#"
                role="button"
              >
                <i class="bi bi-list"></i>
              </a>
            </li>
            <li class="nav-item d-none d-md-block">
              <span class="nav-link">Painel da Conta</span>
            </li>
          </ul>

          <?php
          // Carrega tickets de suporte recentes e notificações para o dropdown
          $headerTickets = [];
          $headerTicketCount = 0;
          $headerNotifications = [];
          $headerNotificationCount = 0;
          $headerTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
          if ($headerTenantId > 0) {
            try {
              $pdo = db();

              // Conta tickets aguardando resposta do cliente
              $stmtWaiting = $pdo->prepare("
                SELECT COUNT(*)
                  FROM support_tickets
                 WHERE tenant_id = :tenant_id
                   AND status = 'waiting_client'
                   AND deleted_at IS NULL
              ");
              $stmtWaiting->bindValue(':tenant_id', $headerTenantId, PDO::PARAM_INT);
              $stmtWaiting->execute();
              $headerTicketCount = (int)$stmtWaiting->fetchColumn();

              // Busca últimos 3 tickets (para o dropdown)
              $stmtTickets = $pdo->prepare("
                SELECT id, code, subject, status,
                       COALESCE(last_message_at, created_at) as last_activity
                  FROM support_tickets
                 WHERE tenant_id = :tenant_id
                   AND deleted_at IS NULL
              ORDER BY (status = 'waiting_client') DESC,
                       COALESCE(last_message_at, created_at) DESC
                 LIMIT 3
              ");
              $stmtTickets->bindValue(':tenant_id', $headerTenantId, PDO::PARAM_INT);
              $stmtTickets->execute();
              $headerTickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);
              
              // Busca notificações não lidas (ex: cancelamento de assinatura)
              try {
                $stmtNotifCount = $pdo->prepare("
                  SELECT COUNT(*)
                    FROM saas_notifications
                   WHERE tenant_id = :tenant_id
                     AND is_read = 0
                ");
                $stmtNotifCount->bindValue(':tenant_id', $headerTenantId, PDO::PARAM_INT);
                $stmtNotifCount->execute();
                $headerNotificationCount = (int)$stmtNotifCount->fetchColumn();
                
                $stmtNotifs = $pdo->prepare("
                  SELECT notification_id, type, title, message, created_at
                    FROM saas_notifications
                   WHERE tenant_id = :tenant_id
                     AND is_read = 0
                  ORDER BY created_at DESC
                   LIMIT 3
                ");
                $stmtNotifs->bindValue(':tenant_id', $headerTenantId, PDO::PARAM_INT);
                $stmtNotifs->execute();
                $headerNotifications = $stmtNotifs->fetchAll(PDO::FETCH_ASSOC);
              } catch (Exception $eNotif) {
                // tabela pode não existir
                $headerNotificationCount = 0;
                $headerNotifications = [];
              }
            } catch (Exception $e) {
              $headerTickets = [];
              $headerTicketCount = 0;
              $headerNotifications = [];
              $headerNotificationCount = 0;
            }
          }
          
          // Total de notificações (tickets + outras notificações)
          $headerTotalNotifications = $headerTicketCount + $headerNotificationCount;
          
          function header_time_ago($datetime) {
            $ts = strtotime($datetime);
            if (!$ts) return '';
            $diff = time() - $ts;
            if ($diff < 60) return 'agora';
            if ($diff < 3600) return (int)($diff / 60) . ' min';
            if ($diff < 86400) return (int)($diff / 3600) . ' h';
            return (int)($diff / 86400) . ' dias';
          }
          
          function header_status_badge($status) {
            $s = strtolower(str_replace(' ', '_', $status));
            if ($s === 'waiting_client') return '<span class="badge bg-primary">Aguardando</span>';
            if ($s === 'open') return '<span class="badge bg-success">Aberto</span>';
            if ($s === 'on_hold') return '<span class="badge bg-warning text-dark">Em Espera</span>';
            return '<span class="badge bg-secondary">Fechado</span>';
          }
          ?>
          <ul class="navbar-nav ms-auto">
            <!-- Notificação (Sininho): Tickets + Notificações do sistema -->
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#" title="Notificações">
                <i class="bi bi-bell"></i>
                <?php if ($headerTotalNotifications > 0): ?>
                  <span class="navbar-badge badge text-bg-danger"><?php echo $headerTotalNotifications; ?></span>
                <?php endif; ?>
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" style="min-width: 350px;">
                <span class="dropdown-item dropdown-header">
                  <i class="bi bi-bell me-1"></i>
                  Notificações
                  <?php if ($headerTotalNotifications > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo $headerTotalNotifications; ?> nova<?php echo $headerTotalNotifications > 1 ? 's' : ''; ?></span>
                  <?php else: ?>
                    <span class="badge bg-secondary ms-1">0 novas</span>
                  <?php endif; ?>
                </span>
                
                <?php // Notificações do sistema (cancelamento, etc) ?>
                <?php if (!empty($headerNotifications)): ?>
                  <?php foreach ($headerNotifications as $hn): ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo root_url(); ?>conta/planos/historico" class="dropdown-item">
                      <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-2">
                          <?php 
                          $notifIcon = 'bi-info-circle';
                          $notifBg = 'bg-info';
                          if ($hn['type'] === 'subscription_cancelled') {
                            $notifIcon = 'bi-x-circle';
                            $notifBg = 'bg-danger';
                          } elseif ($hn['type'] === 'payment_pending') {
                            $notifIcon = 'bi-clock';
                            $notifBg = 'bg-warning';
                          }
                          ?>
                          <div class="rounded-circle <?php echo $notifBg; ?> text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi <?php echo $notifIcon; ?>"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                          <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-1 text-truncate" style="max-width: 200px;">
                              <?php echo htmlspecialchars($hn['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            <small class="text-muted ms-2"><?php echo header_time_ago($hn['created_at']); ?></small>
                          </div>
                          <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                            <?php echo htmlspecialchars($hn['message'], ENT_QUOTES, 'UTF-8'); ?>
                          </small>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
                
                <?php // Tickets de suporte ?>
                <?php if (!empty($headerTickets)): ?>
                  <?php if (!empty($headerNotifications)): ?>
                    <div class="dropdown-divider"></div>
                    <span class="dropdown-item dropdown-header small">
                      <i class="bi bi-ticket-perforated me-1"></i> Tickets de Suporte
                    </span>
                  <?php endif; ?>
                  <?php foreach ($headerTickets as $ht): ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo root_url(); ?>conta/suporte/ticket?id=<?php echo (int)$ht['id']; ?>" class="dropdown-item">
                      <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-2">
                          <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-ticket-detailed"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                          <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-1 text-truncate" style="max-width: 180px;">
                              <?php echo htmlspecialchars($ht['subject'], ENT_QUOTES, 'UTF-8'); ?>
                            </h6>
                            <small class="text-muted ms-2"><?php echo header_time_ago($ht['last_activity']); ?></small>
                          </div>
                          <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">#<?php echo htmlspecialchars($ht['code'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php echo header_status_badge($ht['status']); ?>
                          </div>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (empty($headerTickets) && empty($headerNotifications)): ?>
                  <div class="dropdown-item text-center text-muted py-3">
                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                    Nenhuma notificação
                  </div>
                <?php endif; ?>
                
                <div class="dropdown-divider"></div>
                <a href="<?php echo root_url(); ?>conta/suporte" class="dropdown-item dropdown-footer text-primary">
                  <i class="bi bi-arrow-right me-1"></i> Ver todos os tickets
                </a>
              </div>
            </li>
            <!-- Usuário -->
            <?php
            // Busca logo do tenant para a navbar (com fallback para stores)
            $navbarTenantLogo = '';
            $navbarTenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
            if ($navbarTenantId > 0) {
              try {
                // Primeiro tenta buscar logo do tenant
                $tenantLogoValue = '';
                try {
                  $stmtLogo = $pdo->prepare("SELECT logo FROM tenants WHERE tenant_id = ? LIMIT 1");
                  $stmtLogo->execute([$navbarTenantId]);
                  $tenantLogoRow = $stmtLogo->fetch(PDO::FETCH_ASSOC);
                  if ($tenantLogoRow && !empty($tenantLogoRow['logo']) && $tenantLogoRow['logo'] !== 'sem-foto.jpg') {
                    $tenantLogoValue = $tenantLogoRow['logo'];
                  }
                } catch (Exception $eTenant) {
                  // Coluna logo pode não existir em tenants
                }
                
                // Fallback: busca da primeira loja do tenant
                if (empty($tenantLogoValue)) {
                  try {
                    $stmtStoreLogo = $pdo->prepare("SELECT logo FROM stores WHERE tenant_id = ? ORDER BY store_id ASC LIMIT 1");
                    $stmtStoreLogo->execute([$navbarTenantId]);
                    $storeLogoValue = $stmtStoreLogo->fetchColumn();
                    if (!empty($storeLogoValue) && $storeLogoValue !== 'sem-foto.jpg') {
                      $tenantLogoValue = $storeLogoValue;
                    }
                  } catch (Exception $eStore) {
                    // Ignorar
                  }
                }
                
                if (!empty($tenantLogoValue)) {
                  $navbarTenantLogo = root_url() . 'assets/itsolution24/img/logo-favicons/' . $tenantLogoValue;
                }
              } catch (Exception $e) {
                $navbarTenantLogo = '';
              }
            }
            ?>
            <li class="nav-item dropdown user-menu">
              <a
                href="#"
                class="nav-link dropdown-toggle"
                data-bs-toggle="dropdown"
              >
                <?php if (!empty($navbarTenantLogo)): ?>
                <img
                  src="<?php echo htmlspecialchars($navbarTenantLogo); ?>"
                  class="user-image rounded-circle shadow"
                  alt="Logo"
                  style="object-fit: cover;"
                />
                <?php else: ?>
                <div class="user-image rounded-circle shadow bg-secondary d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                  <i class="bi bi-person-fill text-white"></i>
                </div>
                <?php endif; ?>
                <span class="d-none d-md-inline">
                  <?php echo isset($user) ? htmlspecialchars($user->getUserName()) : 'Usuário'; ?>
                </span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <?php if (!empty($navbarTenantLogo)): ?>
                  <img
                    src="<?php echo htmlspecialchars($navbarTenantLogo); ?>"
                    class="rounded-circle shadow"
                    alt="Logo"
                    style="width: 90px; height: 90px; object-fit: cover;"
                  />
                  <?php else: ?>
                  <div class="rounded-circle shadow bg-secondary d-inline-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                    <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                  </div>
                  <?php endif; ?>
                  <p>
                    <?php echo isset($user) ? htmlspecialchars($user->getUserName()) : 'Usuário ModernPOS'; ?>
                    <small>
                      <?php echo isset($user) ? htmlspecialchars($user->getRole()) : 'Administrador'; ?>
                    </small>
                  </p>
                </li>
                <li class="user-footer">
                  <a
                    href="<?php echo root_url(); ?>account_profile.php"
                    class="btn btn-default btn-flat"
                  >Perfil</a>
                  <a
                    href="<?php echo root_url(); ?>admin/logout.php"
                    class="btn btn-default btn-flat float-end"
                  >Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Sidebar (menu lateral) -->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
          <a href="<?php echo root_url(); ?>conta" class="brand-link">
            <?php if (!empty($navbarTenantLogo)): ?>
            <img
              src="<?php echo htmlspecialchars($navbarTenantLogo); ?>"
              alt="Logo"
              class="brand-image opacity-75 shadow"
              style="object-fit: cover;"
            />
            <?php else: ?>
            <img
              src="./assets/img/AdminLTELogo.png"
              alt="Logo"
              class="brand-image opacity-75 shadow"
            />
            <?php endif; ?>
            <span class="brand-text fw-light">ModernPOS Conta</span>
          </a>
        </div>

        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              role="navigation"
              aria-label="Main navigation"
              data-accordion="false"
              id="navigation"
            >
              <!-- Visão geral da conta -->
              <?php if (can_access_account_section('overview')): ?>
              <li class="nav-item">
                <a href="<?php echo root_url(); ?>conta" class="nav-link <?php echo $section === 'overview' ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Visão Geral</p>
                </a>
              </li>
              <?php endif; ?>

              <!-- Lojas -->
              <?php if (can_access_account_section('stores') || can_access_account_section('overview')): ?>
              <li class="nav-item <?php echo ($section === 'stores' || $section === 'store_settings') ? 'menu-open' : ''; ?>">
                <a href="#" class="nav-link">
                  <i class="nav-icon bi bi-shop"></i>
                  <p>
                    Lojas
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <?php if (can_access_account_section('overview')): ?>
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta" class="nav-link <?php echo $section === 'overview' ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Visão geral das lojas</p>
                    </a>
                  </li>
                  <?php endif; ?>
                  <?php if (can_access_account_section('stores')): ?>
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta/lojas" class="nav-link <?php echo $section === 'stores' ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Gerenciar lojas</p>
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </li>
              <?php endif; ?>

              <!-- Planos -->
              <?php if (can_access_account_section('plans')): ?>
              <li class="nav-item <?php echo $section === 'plans' ? 'menu-open' : ''; ?>">
                <a href="#" class="nav-link <?php echo $section === 'plans' ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-credit-card-2-front"></i>
                  <p>
                    Planos
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta/planos" class="nav-link <?php echo ($section === 'plans' && $tab !== 'historico') ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Planos</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta/planos/historico" class="nav-link <?php echo ($section === 'plans' && $tab === 'historico') ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Histórico</p>
                    </a>
                  </li>
                </ul>
              </li>
              <?php endif; ?>

              <!-- Usuários da conta -->
              <?php if (can_access_account_section('users')): ?>
              <li class="nav-item <?php echo $section === 'users' ? 'menu-open' : ''; ?>">
                <a href="<?php echo root_url(); ?>conta/usuarios" class="nav-link <?php echo $section === 'users' ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-people"></i>
                  <p>
                    Usuários da conta
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta/usuarios" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Usuários com acesso às lojas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta/usuarios/permissoes" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Permissões gerais</p>
                    </a>
                  </li>
                </ul>
              </li>
              <?php endif; ?>

              <!-- Suporte (Tickets) -->
              <?php if (can_access_account_section('support')): ?>
              <li class="nav-item <?php echo ($section === 'support' || $section === 'support_ticket') ? 'menu-open' : ''; ?>">
                <a href="<?php echo root_url(); ?>conta/suporte" class="nav-link <?php echo ($section === 'support' || $section === 'support_ticket') ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-life-preserver"></i>
                  <p>
                    Suporte
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>conta/suporte" class="nav-link <?php echo $section === 'support' ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Meus Tickets</p>
                    </a>
                  </li>
                </ul>
              </li>
              <?php endif; ?>

              <!-- Relatórios Consolidados (item único) -->
              <?php if (can_access_account_section('reports')): ?>
              <li class="nav-item">
                <a href="<?php echo root_url(); ?>conta/relatorios" class="nav-link <?php echo $section === 'reports' ? 'active' : ''; ?>">
                  <i class="nav-icon bi bi-bar-chart"></i>
                  <p>Relatórios Consolidados</p>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </nav>
        </div>
      </aside>

      <!-- Conteúdo principal -->
      <main class="app-main">
        <?php
          // =======================================================
          // VALIDAÇÃO DE ACESSO ANTES DE CARREGAR A SEÇÃO
          // =======================================================
          if (!can_access_account_section($section, $tab)) {
              // Usuário não tem permissão para acessar esta seção
              render_access_denied_message($section);
          } else {
              // Router simples para trocar apenas o miolo, semelhante ao SaaS
              $sectionFile = null;
              switch ($section) {
                  case 'stores':
                      $sectionFile = __DIR__ . '/account/pages/stores.php';
                      break;
                  case 'store_settings':
                      $sectionFile = __DIR__ . '/account/pages/store_settings.php';
                      break;
                  case 'plans':
                      $sectionFile = __DIR__ . '/account/pages/plans.php';
                      break;
                  case 'users':
                      $sectionFile = __DIR__ . '/account/pages/users.php';
                      break;
                  case 'reports':
                      $sectionFile = __DIR__ . '/account/pages/reports.php';
                      break;
                  case 'support':
                      $sectionFile = __DIR__ . '/account/pages/support.php';
                      break;
                  case 'support_ticket':
                      $sectionFile = __DIR__ . '/account/pages/support_ticket.php';
                      break;
                  case 'overview':
                  default:
                      $sectionFile = __DIR__ . '/account/pages/overview.php';
                      break;
              }

              if ($sectionFile && file_exists($sectionFile)) {
                  include $sectionFile;
              } else {
                  echo '<div class="app-content"><div class="container-fluid"><div class="alert alert-danger mt-4">Seção não encontrada.</div></div></div>';
              }
          }
        ?>
      </main>

      <?php // Modais globais (ex.: Criar Loja) ?>
      <?php include __DIR__ . '/account/partials/store_create_modal.php'; ?>

      <script>
        // Necessário por causa do <base> do AdminLTE (evita fetch relativo quebrado)
        window.MODERNPOS_ROOT_URL = "<?php echo root_url(); ?>";
        window.MODERNPOS_ACCOUNT_STORE_API = "<?php echo root_url(); ?>_inc/account_store.php";

        // Limites/roles (usado pela modal de criação de lojas em /conta e /conta/lojas)
        window.ACCOUNT_IS_OWNER_OR_ADMIN = <?php echo $accountIsOwnerOrAdmin ? 'true' : 'false'; ?>;
        window.ACCOUNT_STORES_USED = <?php echo (int)$accountStoresUsedTotal; ?>;
        window.ACCOUNT_STORES_MAX = <?php echo (int)$accountStoresMax; ?>;
        window.ACCOUNT_UPGRADE_URL = "<?php echo root_url(); ?>saas/landing/index.php#pricing";
      </script>

    <!-- Scripts principais AdminLTE 4 -->
    <script
      src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
      crossorigin="anonymous"
    ></script>
    <script
      src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
      crossorigin="anonymous"
    ></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
      crossorigin="anonymous"
    ></script>
    <script src="./js/adminlte.js"></script>

    <!-- Configuração do OverlayScrollbars para a sidebar -->
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector('.sidebar-wrapper');
        if (
          sidebarWrapper &&
          window.OverlayScrollbarsGlobal &&
          window.OverlayScrollbarsGlobal.OverlayScrollbars
        ) {
          window.OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
              theme: 'os-theme-light',
              autoHide: 'leave',
              clickScroll: true,
            },
          });
        }
      });
    </script>

    <!-- Gráficos de exemplo e ajustes de layout específicos do painel de conta -->
    <script src="<?php echo root_url(); ?>account/js/overview.js"></script>

    <!-- UI: Modal Criar Loja + Importar Produtos -->
    <script src="<?php echo root_url(); ?>account/js/account_store_ui.js"></script>

    <?php if ($section === 'store_settings') : ?>
      <script src="<?php echo root_url(); ?>account/js/store_settings_ui.js"></script>
    <?php endif; ?>
  </body>
</html>
