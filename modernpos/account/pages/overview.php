<?php
// Seção: Visão Geral das lojas (dashboard principal da conta)

// Integração com dados reais de lojas
global $user;

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
$stores = $user->getBelongsStore(user_id());
$totalStores = is_array($stores) ? count($stores) : 0;

$activeStores  = 0;
$storesStats   = [];

$totalSalesTodayAllStores        = 0;
$totalSalesYesterdayAllStores    = 0;
$totalSalesMonthAllStores        = 0;
$totalSalesPrevMonthAllStores    = 0;
$totalInvoicesTodayAllStores     = 0;
$totalInvoicesYesterdayAllStores = 0;

if (!empty($stores)) {
  $reportModel = registry()->get('loader')->model('report');

  $today      = date('Y-m-d');
  $yesterday  = date('Y-m-d', strtotime('-1 day'));
  $monthStart = date('Y-m-01');
  $monthEnd   = date('Y-m-t');
  $from7      = date('Y-m-d', strtotime('-6 day'));
  $prevMonthStart = date('Y-m-01', strtotime('first day of previous month'));
  $prevMonthEnd   = date('Y-m-t', strtotime('first day of previous month'));

  foreach ($stores as $s) {
    $storeId  = $s['store_id'];
    $isActive = !empty($s['status']);

    if ($isActive) {
      $activeStores++;
    }

    // Vendas reais por período para cada loja
    $salesToday      = $reportModel->getSellingPrice($today, $today, $storeId);
    $salesYesterday  = $reportModel->getSellingPrice($yesterday, $yesterday, $storeId);
    $salesMonth      = $reportModel->getSellingPrice($monthStart, $monthEnd, $storeId);
    $sales7days      = $reportModel->getSellingPrice($from7, $today, $storeId);
    $salesPrevMonth  = $reportModel->getSellingPrice($prevMonthStart, $prevMonthEnd, $storeId);

    // Quantidade de vendas (notas) hoje e ontem por loja
    $invoicesToday     = 0;
    $invoicesYesterday = 0;

    if (function_exists('get_total_invoice_today_by_store')) {
      $invoicesToday = get_total_invoice_today_by_store($storeId);
    }

    if (function_exists('get_total_invoice_yesterday_by_store')) {
      $invoicesYesterday = get_total_invoice_yesterday_by_store($storeId);
    }

    $totalSalesTodayAllStores        += $salesToday;
    $totalSalesYesterdayAllStores    += $salesYesterday;
    $totalSalesMonthAllStores        += $salesMonth;
    $totalSalesPrevMonthAllStores    += $salesPrevMonth;
    $totalInvoicesTodayAllStores     += $invoicesToday;
    $totalInvoicesYesterdayAllStores += $invoicesYesterday;

    $storesStats[] = [
      'id'               => $storeId,
      'name'             => $s['name'],
      'status'           => $isActive ? 'Ativa' : 'Inativa',
      'statusClass'      => $isActive ? 'bg-success' : 'bg-danger',
      'salesToday'       => $salesToday,
      'salesMonth'       => $salesMonth,
      'salesYesterday'   => $salesYesterday,
      'sales7days'       => $sales7days,
      'salesPrevMonth'   => $salesPrevMonth,
      'invoicesToday'    => $invoicesToday,
      'invoicesYesterday'=> $invoicesYesterday,
    ];
  }
}

// Ticket médio hoje (todas as lojas do usuário)
$ticketMedioHoje = 0;
if ($totalInvoicesTodayAllStores > 0) {
  $ticketMedioHoje = $totalSalesTodayAllStores / $totalInvoicesTodayAllStores;
}

// Ticket médio ontem (todas as lojas do usuário)
$ticketMedioOntem = 0;
if ($totalInvoicesYesterdayAllStores > 0) {
  $ticketMedioOntem = $totalSalesYesterdayAllStores / $totalInvoicesYesterdayAllStores;
}

// Variação de vendas de hoje vs ontem (todas as lojas)
$percentVsOntem = null;
if ($totalSalesYesterdayAllStores > 0) {
  $percentVsOntem = (($totalSalesTodayAllStores - $totalSalesYesterdayAllStores) / $totalSalesYesterdayAllStores) * 100;
}

// Variação de ticket médio hoje vs ontem (todas as lojas)
$percentTicketVsOntem = null;
if ($ticketMedioOntem > 0) {
  $percentTicketVsOntem = (($ticketMedioHoje - $ticketMedioOntem) / $ticketMedioOntem) * 100;
}

// Variação de vendas no mês vs mês anterior (todas as lojas)
$percentMonthVsPrevMonth = null;
if ($totalSalesPrevMonthAllStores > 0) {
  $percentMonthVsPrevMonth = (($totalSalesMonthAllStores - $totalSalesPrevMonthAllStores) / $totalSalesPrevMonthAllStores) * 100;
}

// Dados para gráficos
$dailyLabels = [];
$dailyValues = [];
$storeLabels = [];
$storeValues = [];
$storeValuesToday     = [];
$storeValues7days     = [];
$storeValuesPrevMonth = [];
$storeStatuses        = [];

if (!empty($storesStats)) {
  // Por padrão, calculamos os últimos 90 dias (incluindo hoje).
  // O JavaScript aplica o recorte visual (7, 30 ou 90 dias).
  $days = 90;
  for ($i = $days - 1; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime('-'.$i.' day'));
    $label = date('d/m', strtotime($date));
    $sum   = 0;

    if (!empty($stores)) {
      foreach ($stores as $s) {
        $sum += $reportModel->getSellingPrice($date, $date, $s['store_id']);
      }
    }

    $dailyLabels[] = $label;
    $dailyValues[] = (float)$sum;
  }

  // Vendas por loja em diferentes períodos (já temos em $storesStats)
  foreach ($storesStats as $st) {
    $storeLabels[]         = $st['name'];
    $storeValues[]         = (float)$st['salesMonth'];
    $storeValuesToday[]    = (float)$st['salesToday'];
    $storeValues7days[]    = (float)$st['sales7days'];
    $storeValuesPrevMonth[] = (float)$st['salesPrevMonth'];
    $storeStatuses[]       = $st['status'];
  }
}

$dailyLabelsJson       = json_encode($dailyLabels);
$dailyValuesJson       = json_encode($dailyValues);
$storeLabelsJson       = json_encode($storeLabels);
$storeValuesJson       = json_encode($storeValues);
$storeValuesTodayJson  = json_encode($storeValuesToday);
$storeValues7daysJson  = json_encode($storeValues7days);
$storeValuesPrevJson   = json_encode($storeValuesPrevMonth);
$storeStatusesJson     = json_encode($storeStatuses);
?>

<script>
  // Expor dados para overview.js
  window.accountOverview = {
    dailyLabels: <?php echo $dailyLabelsJson; ?>,
    dailyValues: <?php echo $dailyValuesJson; ?>,
    storesLabels: <?php echo $storeLabelsJson; ?>,
    storesValues: <?php echo $storeValuesJson; ?>,
    storesValuesToday: <?php echo $storeValuesTodayJson; ?>,
    storesValues7days: <?php echo $storeValues7daysJson; ?>,
    storesValuesPrevMonth: <?php echo $storeValuesPrevJson; ?>,
    storesStatus: <?php echo $storeStatusesJson; ?>,
  };
</script>

<?php if ($trialAlertShow): ?>
<div class="trial-alert-banner" style="
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 0;
    position: sticky;
    top: 0;
    z-index: 1040;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
">
    <i class="bi bi-clock-fill" style="font-size: 1.2rem;"></i>
    <span style="font-size: 0.95rem;">
        <strong>Você está no período de teste.</strong>
        <?php if ($trialDaysRemaining > 0): ?>
            Restam <strong><?php echo $trialDaysRemaining; ?> dia<?php echo $trialDaysRemaining > 1 ? 's' : ''; ?></strong> para escolher um plano.
        <?php else: ?>
            O período de teste expirou. Escolha um plano para continuar.
        <?php endif; ?>
    </span>
    <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-light btn-sm" style="font-weight: 600;">
        <i class="bi bi-arrow-right-circle me-1"></i> Ver Planos
    </a>
</div>
<?php endif; ?>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6">
        <h3 class="mb-0">Visão Geral das Lojas</h3>
        <p class="text-secondary mb-0">
          Acompanhe o desempenho de todas as suas lojas em um só lugar.
        </p>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var buttons      = document.querySelectorAll('.btn-activate-store');
    // Usa o mesmo endpoint do fluxo de login: qualquer página que inclui _init.php
    // com ?active_store_id define a loja ativa e retorna JSON.
    var activateBaseUrl = "<?php echo root_url() . ADMINDIRNAME; ?>/dashboard.php?active_store_id=";
    var dashboardUrl    = "<?php echo root_url() . ADMINDIRNAME; ?>/dashboard.php";
    var statusFilter    = document.getElementById('storesStatusFilter');
    var periodFilter    = document.getElementById('periodFilter');
    var resetBtn        = document.getElementById('btnResetFilters');
    // Quantidade total de lojas vinculadas a este usuário (usada para bloquear exclusão da última loja)
    var totalStoresForUser = <?php echo (int)$totalStores; ?>;

    if (buttons.length) {
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var storeId = this.getAttribute('data-store-id');
          if (!storeId) return;

          var status = this.getAttribute('data-store-status');
          if (status === 'Inativa') {
            alert('Esta loja está desativada. Ative a loja antes de entrar.');
            return;
          }

          this.disabled = true;

          fetch(activateBaseUrl + encodeURIComponent(storeId), {
            method: 'GET',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          })
          .then(function () {
            window.location.href = dashboardUrl;
          })
          .catch(function () {
            window.location.href = dashboardUrl;
          });
        });
      });
    }

    function applyStatusFilter(filter) {
      // Cards
      var cardCols = document.querySelectorAll('#storesCardsView .col-md-6.col-xl-3');
      cardCols.forEach(function (col) {
        var status = col.getAttribute('data-store-status');
        if (!filter || filter === 'all') {
          col.classList.remove('d-none');
        } else if (filter === 'active') {
          col.classList.toggle('d-none', status !== 'Ativa');
        } else if (filter === 'inactive') {
          col.classList.toggle('d-none', status !== 'Inativa');
        }
      });

      // Tabela
      var rows = document.querySelectorAll('#storesTableView tbody tr[data-store-status]');
      rows.forEach(function (row) {
        var status = row.getAttribute('data-store-status');
        if (!filter || filter === 'all') {
          row.classList.remove('d-none');
        } else if (filter === 'active') {
          row.classList.toggle('d-none', status !== 'Ativa');
        } else if (filter === 'inactive') {
          row.classList.toggle('d-none', status !== 'Inativa');
        }
      });
    }

    // Filtro de status (Todas / Ativas / Inativas)
    if (statusFilter) {
      statusFilter.addEventListener('change', function () {
        applyStatusFilter(this.value);
      });
    }

    // Textos dinâmicos por período
    function applyPeriodTexts(value) {
      var kpiSalesLabel    = document.getElementById('kpiSalesPeriodLabel');
      var kpiTicketLabel   = document.getElementById('kpiTicketLabel');
      var thSalesPeriod    = document.getElementById('thSalesPeriodLabel');
      var thSalesMonth     = document.getElementById('thSalesMonthLabel');
      var storesChartTitle = document.getElementById('storesChartTitle');

      if (!kpiSalesLabel || !kpiTicketLabel || !thSalesPeriod || !thSalesMonth) return;

      switch (value) {
        case 'today':
          kpiSalesLabel.textContent  = 'Vendas hoje (todas as lojas)';
          kpiTicketLabel.textContent = 'Ticket médio hoje';
          thSalesPeriod.textContent  = 'Vendas hoje';
          thSalesMonth.textContent   = 'Vendas no mês';
          if (storesChartTitle) storesChartTitle.textContent = 'Desempenho por loja (hoje)';
          break;
        case '7days':
          kpiSalesLabel.textContent  = 'Vendas nos últimos 7 dias';
          kpiTicketLabel.textContent = 'Ticket médio (7 dias)';
          thSalesPeriod.textContent  = 'Vendas (7 dias)';
          thSalesMonth.textContent   = 'Vendas no mês atual';
          if (storesChartTitle) storesChartTitle.textContent = 'Desempenho por loja (últimos 7 dias)';
          break;
        case 'month':
          kpiSalesLabel.textContent  = 'Vendas neste mês';
          kpiTicketLabel.textContent = 'Ticket médio (mês atual)';
          thSalesPeriod.textContent  = 'Vendas neste mês';
          thSalesMonth.textContent   = 'Vendas no mês atual';
          if (storesChartTitle) storesChartTitle.textContent = 'Desempenho por loja (mês atual)';
          break;
        case 'prev_month':
          kpiSalesLabel.textContent  = 'Vendas no mês anterior';
          kpiTicketLabel.textContent = 'Ticket médio (mês anterior)';
          thSalesPeriod.textContent  = 'Vendas mês anterior';
          thSalesMonth.textContent   = 'Vendas no mês atual';
          if (storesChartTitle) storesChartTitle.textContent = 'Desempenho por loja (mês anterior)';
          break;
        default:
          kpiSalesLabel.textContent  = 'Vendas hoje (todas as lojas)';
          kpiTicketLabel.textContent = 'Ticket médio hoje';
          thSalesPeriod.textContent  = 'Vendas hoje';
          thSalesMonth.textContent   = 'Vendas no mês';
          if (storesChartTitle) storesChartTitle.textContent = 'Desempenho por loja (mês atual)';
      }
    }

    if (periodFilter) {
      applyPeriodTexts(periodFilter.value || 'month');
      periodFilter.addEventListener('change', function () {
        applyPeriodTexts(this.value);
      });
    }

    // Botão de reset para voltar ao padrão (mês atual + todas as lojas)
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (periodFilter) {
          periodFilter.value = 'month';
          applyPeriodTexts('month');
        }

        if (statusFilter) {
          statusFilter.value = 'all';
          applyStatusFilter('all');
        }

        // Resetar range do gráfico para 7 dias, se existir
        var btn7 = document.querySelector('#salesRangeButtons button[data-range="7"]');
        if (btn7) {
          btn7.click();
        }
      });
    }

    // ===== Menu "3 pontos" e modal de exclusão de loja =====
    var deleteModalEl        = document.getElementById('deleteStoreModal');
    var deleteStoreNameEl    = document.getElementById('deleteStoreName');
    var deleteStoreMessageEl = document.getElementById('deleteStoreMessage');
    var deleteConfirmBtn     = document.getElementById('confirmDeleteStore');
    var deleteStoreId        = null;
    var deleteStoreUrl       = "<?php echo root_url(); ?>_inc/store.php";

    // Modal de aviso para bloqueio da última loja
    var lastStoreModalEl = document.getElementById('lastStoreWarningModal');

    if (typeof bootstrap !== 'undefined') {
      var deleteModal   = deleteModalEl   ? new bootstrap.Modal(deleteModalEl)   : null;
      var lastStoreModal = lastStoreModalEl ? new bootstrap.Modal(lastStoreModalEl) : null;

      // ===== Ativar / Desativar loja pelo menu de 3 pontos =====
      var statusToggleUrl = "<?php echo root_url(); ?>_inc/store_status.php";

      function changeStoreStatus(storeId, status) {
        var formData = new URLSearchParams();
        formData.append('store_id', storeId);
        formData.append('status', status);

        return fetch(statusToggleUrl, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData
        })
        .then(function(res) {
          if (!res.ok) {
            return res.json().then(function (data) { throw data; }).catch(function () { throw { errorMsg: 'Erro ao alterar status da loja.' }; });
          }
          return res.json();
        });
      }

      var activateMenuButtons = document.querySelectorAll('.store-menu-activate');
      activateMenuButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var storeId = this.getAttribute('data-store-id');
          if (!storeId) return;
          changeStoreStatus(storeId, 1)
            .then(function () {
              window.location.reload();
            })
            .catch(function (err) {
              var msg = (err && (err.errorMsg || err.msg)) ? (err.errorMsg || err.msg) : 'Não foi possível ativar a loja.';
              alert(msg);
            });
        });
      });

      var deactivateMenuButtons = document.querySelectorAll('.store-menu-deactivate');
      deactivateMenuButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var storeId = this.getAttribute('data-store-id');
          if (!storeId) return;
          changeStoreStatus(storeId, 0)
            .then(function () {
              window.location.reload();
            })
            .catch(function (err) {
              var msg = (err && (err.errorMsg || err.msg)) ? (err.errorMsg || err.msg) : 'Não foi possível desativar a loja.';
              alert(msg);
            });
        });
      });

      // Abrir modal ao clicar em "Excluir" no menu
      var deleteButtons = document.querySelectorAll('.store-menu-delete');
      deleteButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          // Bloqueia exclusão da última loja do usuário para não travar login posterior
          if (typeof totalStoresForUser !== 'undefined' && totalStoresForUser <= 1) {
            if (lastStoreModal) {
              lastStoreModal.show();
            } else {
              alert('Você precisa manter pelo menos uma loja na sua conta. Crie uma nova loja antes de excluir esta.');
            }
            return;
          }

          deleteStoreId = this.getAttribute('data-store-id');
          var storeName = this.getAttribute('data-store-name') || '';

          if (deleteStoreNameEl) {
            deleteStoreNameEl.textContent = storeName;
          }
          if (deleteStoreMessageEl) {
            deleteStoreMessageEl.textContent = 'Tem certeza que deseja excluir a loja "' + storeName + '" de forma permanente? Esta ação não poderá ser desfeita.';
          }

          deleteConfirmBtn.disabled = false;
          deleteConfirmBtn.textContent = 'Sim, excluir permanentemente';
          deleteModal.show();
        });
      });

      // Confirmar exclusão
      if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', function () {
          if (!deleteStoreId) return;

          var btn = this;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Excluindo...';

          var formData = new URLSearchParams();
          formData.append('action_type', 'DELETE');
          formData.append('store_id', deleteStoreId);
          formData.append('delete_action', 'delete');
          formData.append('new_store_id', '0');

          fetch(deleteStoreUrl, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            body: formData
          })
          .then(function (res) {
            if (!res.ok) {
              return res.json().then(function (data) { throw data; }).catch(function () { throw { errorMsg: 'Erro ao excluir a loja.' }; });
            }
            return res.json();
          })
          .then(function (data) {
            deleteModal.hide();
            // Recarrega a página para atualizar lista, KPIs e gráficos
            window.location.reload();
          })
          .catch(function (err) {
            btn.disabled = false;
            btn.textContent = 'Sim, excluir permanentemente';
            var msg = (err && (err.errorMsg || err.msg)) ? (err.errorMsg || err.msg) : 'Não foi possível excluir a loja.';
            alert(msg);
          });
        });
      }
    }
  });
</script>

<div class="app-content">
  <div class="container-fluid">
    <!-- Filtros principais -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-center">
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="periodFilter">
              <option value="today">Hoje</option>
              <option value="7days">Últimos 7 dias</option>
              <option value="month" selected>Este mês</option>
              <option value="prev_month">Mês anterior</option>
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" id="storesStatusFilter">
              <option value="all" selected>Todas as lojas</option>
              <option value="active">Ativas</option>
              <option value="inactive">Inativas</option>
            </select>
          </div>
          <div class="col-md-4 text-md-end ms-auto d-flex justify-content-end gap-2">
            <button class="btn btn-secondary btn-sm" type="button" id="btnResetFilters">
              <i class="bi bi-arrow-counterclockwise"></i> Resetar filtros
            </button>
            <button class="btn btn-primary btn-sm" type="button" data-open-create-store-modal="1">
              <i class="bi bi-plus"></i> Criar nova loja
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- KPIs globais -->
    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
          <div class="inner">
            <h3>
              <?php echo get_currency_symbol() . ' ' . currency_format($totalSalesTodayAllStores); ?>
            </h3>
            <p id="kpiSalesPeriodLabel">Vendas hoje (todas as lojas)</p>
          </div>
          <svg
            class="small-box-icon"
            fill="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"
            ></path>
          </svg>
          <span
            class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
          >
            <?php
              if ($percentVsOntem === null) {
                echo '&mdash;';
              } else {
                $formatted = number_format($percentVsOntem, 1, ',', '.');
                echo ($percentVsOntem >= 0 ? '+' : '') . $formatted . '% vs ontem';
              }
            ?>
          </span>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-info">
          <div class="inner">
            <h3>
              <?php echo get_currency_symbol() . ' ' . currency_format($totalSalesMonthAllStores); ?>
            </h3>
            <p>Vendas no mês</p>
          </div>
          <svg
            class="small-box-icon"
            fill="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              d="M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.036-.84-1.875-1.875-1.875h-.75zM9.75 8.625c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-.75a1.875 1.875 0 01-1.875-1.875V8.625zM3 13.125c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v6.75c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 013 19.875v-6.75z"
            ></path>
          </svg>
          <span
            class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
          >
            <?php
              if ($percentMonthVsPrevMonth === null) {
                echo '&mdash;';
              } else {
                $formatted = number_format($percentMonthVsPrevMonth, 1, ',', '.');
                echo ($percentMonthVsPrevMonth >= 0 ? '+' : '') . $formatted . '% vs mês anterior';
              }
            ?>
          </span>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
          <div class="inner">
            <h3><?php echo $activeStores . ' de ' . $totalStores; ?></h3>
            <p>Lojas ativas / total</p>
          </div>
          <svg
            class="small-box-icon"
            fill="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              d="M6.25 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM3.25 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM19.75 7.5a.75.75 0 00-1.5 0v2.25H16a.75.75 0 000 1.5h2.25v2.25a.75.75 0 001.5 0v-2.25H22a.75.75 0 000-1.5h-2.25V7.5z"
            ></path>
          </svg>
          <span
            class="small-box-footer link-dark link-underline-opacity-0 link-underline-opacity-50-hover"
          >
            <?php echo max($totalStores - $activeStores, 0); ?> loja(s) inativa(s)
          </span>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
          <div class="inner">
            <h3>
              <?php echo get_currency_symbol() . ' ' . currency_format($ticketMedioHoje); ?>
            </h3>
            <p id="kpiTicketLabel">Ticket médio hoje</p>
          </div>
          <svg
            class="small-box-icon"
            fill="currentColor"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              clip-rule="evenodd"
              fill-rule="evenodd"
              d="M2.25 13.5a8.25 8.25 0 018.25-8.25.75.75 0 01.75.75v6.75H18a.75.75 0 01.75.75 8.25 8.25 0 01-16.5 0z"
            ></path>
            <path
              clip-rule="evenodd"
              fill-rule="evenodd"
              d="M12.75 3a.75.75 0 01.75-.75 8.25 8.25 0 018.25 8.25.75.75 0 01-.75.75h-7.5a.75.75 0 01-.75-.75V3z"
            ></path>
          </svg>
          <span
            class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover"
          >
            <?php
              if ($percentTicketVsOntem === null) {
                echo '&mdash;';
              } else {
                $formatted = number_format($percentTicketVsOntem, 1, ',', '.');
                echo ($percentTicketVsOntem >= 0 ? '+' : '') . $formatted . '% vs ontem';
              }
            ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Gráficos (exemplo estático) -->
    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Vendas por dia (todas as lojas)</h3>
            <div class="btn-group btn-group-sm" role="group" id="salesRangeButtons">
              <button type="button" class="btn btn-primary" data-range="7">7 dias</button>
              <button type="button" class="btn btn-outline-secondary" data-range="30">
                30 dias
              </button>
              <button type="button" class="btn btn-outline-secondary" data-range="90">
                90 dias
              </button>
            </div>
          </div>
          <div class="card-body">
            <div style="height:260px">
              <canvas id="salesChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header">
            <h3 class="card-title mb-0" id="storesChartTitle">Desempenho por loja (mês atual)</h3>
          </div>
          <div class="card-body">
            <div style="height:260px">
              <canvas id="storesChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lojas cadastradas -->
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Lojas cadastradas (<?php echo (int)$totalStores; ?>)</h3>
        <div class="btn-group btn-group-sm ms-auto" role="group">
          <button
            type="button"
            class="btn btn-primary"
            id="viewCardsBtn"
          >
            <i class="bi bi-grid-3x3-gap"></i> Cards
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary"
            id="viewTableBtn"
          >
            <i class="bi bi-list"></i> Lista
          </button>
        </div>
      </div>
      <div class="card-body">
        <!-- Visão em cards -->
        <div id="storesCardsView">
          <?php if (!empty($storesStats)): ?>
            <div class="row g-3">
              <?php foreach ($storesStats as $store): ?>
                <div class="col-md-6 col-xl-3" data-store-status="<?php echo $store['status']; ?>">
                  <div class="card h-100 border <?php echo $store['status'] === 'Inativa' ? 'opacity-75' : ''; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($store['name']); ?></div>
                        <small class="text-secondary">ID #<?php echo (int)$store['id']; ?></small>
                      </div>
                      <span class="badge <?php echo $store['statusClass']; ?>"><?php echo $store['status']; ?></span>
                    </div>
                    <div class="card-body small">
                      <div class="d-flex justify-content-between mb-1">
                        <span class="text-secondary">Vendas hoje:</span>
                        <span class="fw-semibold">
                          <?php echo get_currency_symbol() . ' ' . currency_format($store['salesToday']); ?>
                        </span>
                      </div>
                      <div class="d-flex justify-content-between mb-1">
                        <span class="text-secondary">Vendas no mês:</span>
                        <span class="fw-semibold">
                          <?php echo get_currency_symbol() . ' ' . currency_format($store['salesMonth']); ?>
                        </span>
                      </div>
                      <div class="d-flex justify-content-between mb-1">
                        <span class="text-secondary">Ticket médio:</span>
                        <span class="fw-semibold">
                          <?php
                            if (!empty($store['invoicesToday'])) {
                              $ticketLoja = $store['salesToday'] / $store['invoicesToday'];
                              echo get_currency_symbol() . ' ' . currency_format($ticketLoja);
                            } else {
                              echo '-';
                            }
                          ?>
                        </span>
                      </div>
                      <div class="d-flex justify-content-between">
                        <span class="text-secondary">Última venda:</span>
                        <span class="fw-semibold">
                          <?php
                            if (function_exists('get_last_sale_time_today_by_store')) {
                              $lastSale = get_last_sale_time_today_by_store($store['id']);
                              if ($lastSale !== 'N/A') {
                                echo 'Hoje às ' . $lastSale;
                              } else {
                                echo 'Nenhuma venda hoje';
                              }
                            } else {
                              echo '-';
                            }
                          ?>
                        </span>
                      </div>
                    </div>
                    <div class="card-footer d-flex gap-2 align-items-center">
                      <button
                        type="button"
                        class="btn btn-success btn-sm w-100 btn-activate-store"
                        data-store-id="<?php echo (int)$store['id']; ?>"
                        data-store-status="<?php echo $store['status']; ?>"
                        <?php echo $store['status'] === 'Inativa' ? 'disabled title="Loja desativada"' : ''; ?>
                      >
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </button>
                      <div class="dropdown ms-auto">
                        <button
                          class="btn btn-outline-secondary btn-sm dropdown-toggle"
                          type="button"
                          data-bs-toggle="dropdown"
                          aria-expanded="false"
                        >
                          <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                          <li>
                            <button
                              type="button"
                              class="dropdown-item store-menu-activate"
                              data-store-id="<?php echo (int)$store['id']; ?>"
                              data-store-name="<?php echo htmlspecialchars($store['name']); ?>"
                            >
                              <i class="bi bi-toggle-on me-2 text-success"></i> Ativar
                            </button>
                          </li>
                          <li>
                            <button
                              type="button"
                              class="dropdown-item store-menu-deactivate"
                              data-store-id="<?php echo (int)$store['id']; ?>"
                              data-store-name="<?php echo htmlspecialchars($store['name']); ?>"
                            >
                              <i class="bi bi-toggle-off me-2 text-warning"></i> Desativar
                            </button>
                          </li>
                          <li><hr class="dropdown-divider"></li>
                          <li>
                            <button
                              type="button"
                              class="dropdown-item text-danger store-menu-delete"
                              data-store-id="<?php echo (int)$store['id']; ?>"
                              data-store-name="<?php echo htmlspecialchars($store['name']); ?>"
                            >
                              <i class="bi bi-trash3 me-2"></i> Excluir
                            </button>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-secondary mb-0">Nenhuma loja cadastrada ainda.</p>
          <?php endif; ?>
        </div>

        <!-- Visão em tabela -->
        <div id="storesTableView" class="table-responsive d-none">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Loja</th>
                <th>Status</th>
                <th><span id="thSalesPeriodLabel">Vendas hoje</span></th>
                <th><span id="thSalesMonthLabel">Vendas no mês</span></th>
                <th>Ticket médio</th>
                <th>Última venda</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($storesStats)): ?>
                <?php foreach ($storesStats as $store): ?>
                  <tr class="<?php echo $store['status'] === 'Inativa' ? 'opacity-75' : ''; ?>" data-store-status="<?php echo $store['status']; ?>">
                    <td><?php echo htmlspecialchars($store['name']); ?></td>
                    <td>
                      <span class="badge <?php echo $store['statusClass']; ?>"><?php echo $store['status']; ?></span>
                    </td>
                    <td><?php echo get_currency_symbol() . ' ' . currency_format($store['salesToday']); ?></td>
                    <td><?php echo get_currency_symbol() . ' ' . currency_format($store['salesMonth']); ?></td>
                    <td>
                      <?php
                        if (!empty($store['invoicesToday'])) {
                          $ticketLoja = $store['salesToday'] / $store['invoicesToday'];
                          echo get_currency_symbol() . ' ' . currency_format($ticketLoja);
                        } else {
                          echo '-';
                        }
                      ?>
                    </td>
                    <td>
                      <?php
                        if (function_exists('get_last_sale_time_today_by_store')) {
                          $lastSale = get_last_sale_time_today_by_store($store['id']);
                          if ($lastSale !== 'N/A') {
                            echo 'Hoje às ' . $lastSale;
                          } else {
                            echo 'Nenhuma venda hoje';
                          }
                        } else {
                          echo '-';
                        }
                      ?>
                    </td>
                    <td class="text-end">
                      <button
                        type="button"
                        class="btn btn-success btn-sm btn-activate-store me-2"
                        data-store-id="<?php echo (int)$store['id']; ?>"
                        data-store-status="<?php echo $store['status']; ?>"
                        <?php echo $store['status'] === 'Inativa' ? 'disabled title="Loja desativada"' : ''; ?>
                      >
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                      </button>
                      <div class="dropdown d-inline-block">
                        <button
                          class="btn btn-outline-secondary btn-sm dropdown-toggle"
                          type="button"
                          data-bs-toggle="dropdown"
                          aria-expanded="false"
                        >
                          <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                          <li>
                            <button
                              type="button"
                              class="dropdown-item store-menu-activate"
                              data-store-id="<?php echo (int)$store['id']; ?>"
                              data-store-name="<?php echo htmlspecialchars($store['name']); ?>"
                            >
                              <i class="bi bi-toggle-on me-2 text-success"></i> Ativar
                            </button>
                          </li>
                          <li>
                            <button
                              type="button"
                              class="dropdown-item store-menu-deactivate"
                              data-store-id="<?php echo (int)$store['id']; ?>"
                              data-store-name="<?php echo htmlspecialchars($store['name']); ?>"
                            >
                              <i class="bi bi-toggle-off me-2 text-warning"></i> Desativar
                            </button>
                          </li>
                          <li><hr class="dropdown-divider"></li>
                          <li>
                            <button
                              type="button"
                              class="dropdown-item text-danger store-menu-delete"
                              data-store-id="<?php echo (int)$store['id']; ?>"
                              data-store-name="<?php echo htmlspecialchars($store['name']); ?>"
                            >
                              <i class="bi bi-trash3 me-2"></i> Excluir
                            </button>
                          </li>
                        </ul>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center text-secondary">Nenhuma loja cadastrada ainda.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer">
        <a href="<?php echo root_url(); ?>/conta/relatorios" class="text-decoration-none">
          <i class="bi bi-arrow-right-circle"></i>
          Ver relatório consolidado completo
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal de aviso: não é possível excluir a última loja -->
<div class="modal fade premium-modal-info" id="lastStoreWarningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 bg-gradient-info text-white">
        <div class="d-flex align-items-center">
          <div class="premium-modal-icon bg-white text-primary me-3">
            <i class="bi bi-info-lg"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0">Atenção</h5>
            <small class="opacity-75">Você precisa manter pelo menos uma loja ativa na sua conta.</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Para continuar usando o sistema, sua conta precisa ter pelo menos uma loja cadastrada.</p>
        <p class="mb-0">Crie uma nova loja antes de excluir a atual. Assim você não perderá o acesso ao painel.</p>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-end">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
          Entendi, vou criar uma nova loja
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de confirmação de exclusão de loja (estilo premium) -->
<div class="modal fade premium-modal-danger" id="deleteStoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 bg-gradient-danger text-white">
        <div class="d-flex align-items-center">
          <div class="premium-modal-icon bg-white text-danger me-3">
            <i class="bi bi-trash3-fill"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0">Excluir loja</h5>
            <small class="opacity-75">Esta ação é permanente e não poderá ser desfeita.</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="premium-modal-highlight text-center mb-3">
          <p class="text-secondary mb-1">Você está prestes a excluir a loja:</p>
          <p class="h5 fw-bold mb-0" id="deleteStoreName">Nome da loja</p>
        </div>
        <div class="alert alert-warning premium-alert-soft mb-3">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <span id="deleteStoreMessage">
            Todos os dados de vendas, estoque e configurações vinculados a esta loja serão removidos de forma definitiva.
          </span>
        </div>
        <ul class="small text-secondary mb-0">
          <li>Esta operação <strong>não pode ser desfeita</strong>.</li>
          <li>Certifique-se de que não há operações pendentes antes de continuar.</li>
        </ul>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancelar
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteStore">
          Sim, excluir permanentemente
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .premium-modal-danger .modal-content {
    border-radius: 1rem;
    overflow: hidden;
  }

  .premium-modal-danger .bg-gradient-danger {
    background: linear-gradient(135deg, #dc3545, #a61e2d);
  }

  .premium-modal-info .bg-gradient-info {
    background: linear-gradient(135deg, #0d6efd, #084298);
  }

  .premium-modal-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 0.5rem 1rem rgba(220, 53, 69, 0.25);
  }

  .premium-alert-soft {
    border-radius: 0.75rem;
    background: #fff9e6;
    border-color: #ffe8a3;
    color: #8a6d3b;
  }

  .premium-modal-highlight {
    background: #f3f4f6;
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    text-align: center;
  }
</style>
