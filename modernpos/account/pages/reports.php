<?php
// Seção: Relatórios Consolidados (conteúdo migrado de account_reports.php)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'vendas';

global $user;

// Lojas vinculadas à conta (normalmente via user_to_store)
$stores = method_exists($user, 'getBelongsStore') ? $user->getBelongsStore(user_id()) : array();

// Fallback (SaaS): se não houver vínculo explícito no user_to_store,
// usa as lojas do tenant (store model já está filtrado por tenant_id).
if (empty($stores)) {
  $storeModel = registry()->get('loader')->model('store');
  $stores = $storeModel->getStores();
}

$storeIds = array();
foreach ($stores as $store) {
  if (isset($store['store_id'])) {
    $storeIds[] = (int)$store['store_id'];
  }
}

// Filtros
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$selected_store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0; // 0 = todas
$custom_from = isset($_GET['from']) ? $_GET['from'] : null;
$custom_to   = isset($_GET['to']) ? $_GET['to'] : null;

// Valida store_id selecionado (só permite lojas do cliente)
if ($selected_store_id && !in_array($selected_store_id, $storeIds, true)) {
  $selected_store_id = 0;
}

// Define intervalo (from/to) baseado no período
switch ($period) {
  case 'today':
    $from = date('Y-m-d');
    $to   = date('Y-m-d');
    break;
  case 'yesterday':
    $from = date('Y-m-d', strtotime('-1 day'));
    $to   = $from;
    break;
  case '7days':
    $from = date('Y-m-d', strtotime('-6 day'));
    $to   = date('Y-m-d');
    break;
  case 'prev_month':
    $from = date('Y-m-01', strtotime('first day of last month'));
    $to   = date('Y-m-t', strtotime('last day of last month'));
    break;
  case 'year':
    $from = date('Y-01-01');
    $to   = date('Y-m-d');
    break;
  case 'custom':
    $from = $custom_from ? date('Y-m-d', strtotime($custom_from)) : date('Y-m-01');
    $to   = $custom_to ? date('Y-m-d', strtotime($custom_to)) : date('Y-m-t');
    break;
  case 'month':
  default:
    $from = date('Y-m-01');
    $to   = date('Y-m-t');
    break;
}

// Lojas da consulta (todas ou uma específica)
$storesForView = $stores;
$storeIdsForQuery = $storeIds;
if ($selected_store_id) {
  $storeIdsForQuery = array($selected_store_id);
  $storesForView = array_values(array_filter($stores, function ($s) use ($selected_store_id) {
    return isset($s['store_id']) && (int)$s['store_id'] === (int)$selected_store_id;
  }));
}

// Resumo de pagamentos (consolidado)
$paymentSummaryRaw = account_payment_summary($from, $to, $storeIdsForQuery);
$byMethodRaw = isset($paymentSummaryRaw['by_method']) ? $paymentSummaryRaw['by_method'] : array();
$byStoreAndMethodRaw = isset($paymentSummaryRaw['by_store_and_method']) ? $paymentSummaryRaw['by_store_and_method'] : array();
$totals = isset($paymentSummaryRaw['totals']) ? $paymentSummaryRaw['totals'] : array('grand_total' => 0, 'transactions' => 0, 'ticket_medio' => 0);

// =======================================================
// Métodos globais (Dinheiro / Crédito / Débito / PIX)
// =======================================================
// IDs canônicos (documentados em _inc/helper/pmethod.php)
$methodGroups = array(
  7 => array(
    'label' => 'Dinheiro',
    'ids'   => array(7, 1),
    'codes' => array('cash', 'dinheiro', 'cod'),
    'class' => 'money',
    'icon'  => 'fa fa-money',
    'color' => '#00a65a',
  ),
  5 => array(
    'label' => 'Crédito',
    'ids'   => array(5, 4),
    'codes' => array('card_credit', 'credit', 'credito'),
    'class' => 'credit',
    'icon'  => 'fa fa-credit-card',
    'color' => '#3c8dbc',
  ),
  9 => array(
    'label' => 'Débito',
    'ids'   => array(9),
    'codes' => array('card_debit', 'debit', 'debito'),
    'class' => 'debit',
    'icon'  => 'fa fa-credit-card-alt',
    'color' => '#00c0ef',
  ),
  6 => array(
    'label' => 'PIX',
    'ids'   => array(6),
    'codes' => array('pix'),
    'class' => 'pix',
    'icon'  => 'fa fa-bolt',
    'color' => '#605ca8',
  ),
);

$canonicalIds = array_keys($methodGroups);
$allMethodIds = array();
foreach ($methodGroups as $cid => $g) {
  foreach ($g['ids'] as $pid) {
    $allMethodIds[] = (int)$pid;
  }
}
$allMethodIds = array_values(array_unique($allMethodIds));

// Normaliza by_method para apenas os 4 globais (em ordem fixa)
$byMethod = array();
foreach ($methodGroups as $cid => $g) {
  $byMethod[$cid] = array(
    'pmethod_id'   => (int)$cid,
    'name'         => $g['label'],
    'code_name'    => $g['codes'][0],
    'total'        => 0.0,
    'transactions' => 0,
    'ticket_medio' => 0.0,
    'percent'      => 0.0,
  );
}

foreach ($byMethodRaw as $m) {
  $pid  = isset($m['pmethod_id']) ? (int)$m['pmethod_id'] : 0;
  $code = isset($m['code_name']) ? strtolower((string)$m['code_name']) : '';
  foreach ($methodGroups as $cid => $g) {
    $idsLower = array_map('intval', $g['ids']);
    $codesLower = array_map('strtolower', $g['codes']);
    if (in_array($pid, $idsLower, true) || ($code && in_array($code, $codesLower, true))) {
      $byMethod[$cid]['total'] += isset($m['total']) ? (float)$m['total'] : 0.0;
      $byMethod[$cid]['transactions'] += isset($m['transactions']) ? (int)$m['transactions'] : 0;
      break;
    }
  }
}

foreach ($byMethod as $cid => &$m) {
  $m['ticket_medio'] = $m['transactions'] > 0 ? ($m['total'] / $m['transactions']) : 0.0;
  $m['percent'] = (isset($totals['grand_total']) && (float)$totals['grand_total'] > 0)
    ? ($m['total'] / (float)$totals['grand_total'])
    : 0.0;
}
unset($m);

// Normaliza by_store_and_method para os 4 globais
$byStoreAndMethod = array();
foreach ($storesForView as $store) {
  if (!isset($store['store_id'])) continue;
  $sid = (int)$store['store_id'];
  $rowRaw = isset($byStoreAndMethodRaw[$sid]) ? $byStoreAndMethodRaw[$sid] : array();
  $byStoreAndMethod[$sid] = array();
  foreach ($methodGroups as $cid => $g) {
    $sum = 0.0;
    $tx = 0;
    foreach ($g['ids'] as $pid) {
      $pid = (int)$pid;
      if (isset($rowRaw[$pid])) {
        $sum += isset($rowRaw[$pid]['total']) ? (float)$rowRaw[$pid]['total'] : 0.0;
        $tx  += isset($rowRaw[$pid]['transactions']) ? (int)$rowRaw[$pid]['transactions'] : 0;
      }
    }
    $byStoreAndMethod[$sid][$cid] = array('total' => $sum, 'transactions' => $tx);
  }
}

// KPIs auxiliares
$topMethodName = '-';
$topMethodPercent = 0.0;
$topMethodTotal = 0.0;
foreach ($byMethod as $m) {
  if ((float)$m['total'] > $topMethodTotal) {
    $topMethodTotal = (float)$m['total'];
    $topMethodName = $m['name'];
    $topMethodPercent = (float)$m['percent'] * 100;
  }
}

$topStoreName = '-';
$topStoreTotal = 0.0;
foreach ($storesForView as $store) {
  if (!isset($store['store_id'])) continue;
  $sid = (int)$store['store_id'];
  $row = isset($byStoreAndMethod[$sid]) ? $byStoreAndMethod[$sid] : array();
  $t = 0.0;
  foreach ($row as $cell) {
    $t += isset($cell['total']) ? (float)$cell['total'] : 0.0;
  }
  if ($t > $topStoreTotal) {
    $topStoreTotal = $t;
    $topStoreName = isset($store['name']) ? $store['name'] : '-';
  }
}

// Série diária para o gráfico de tendência
$report_model = registry()->get('loader')->model('report');
$trendRows = $report_model->getPaymentTrendByStores($from, $to, $storeIdsForQuery, $allMethodIds);
$trendMap = array();
foreach ($trendRows as $r) {
  $day = isset($r['day']) ? $r['day'] : null;
  $pid = isset($r['pmethod_id']) ? (int)$r['pmethod_id'] : 0;
  $total = isset($r['total']) ? (float)$r['total'] : 0.0;
  if (!$day || !$pid) continue;

  foreach ($methodGroups as $cid => $g) {
    if (in_array($pid, array_map('intval', $g['ids']), true)) {
      if (!isset($trendMap[$day])) $trendMap[$day] = array();
      if (!isset($trendMap[$day][$cid])) $trendMap[$day][$cid] = 0.0;
      $trendMap[$day][$cid] += $total;
      break;
    }
  }
}

$trendLabels = array();
$trendSeries = array();
foreach ($methodGroups as $cid => $g) {
  $trendSeries[$cid] = array();
}

$dt = new DateTime($from);
$dtEnd = new DateTime($to);
$dtEnd->setTime(0, 0, 0);
while ($dt <= $dtEnd) {
  $dayKey = $dt->format('Y-m-d');
  $trendLabels[] = $dt->format('d/m');
  foreach ($methodGroups as $cid => $g) {
    $trendSeries[$cid][] = isset($trendMap[$dayKey][$cid]) ? (float)$trendMap[$dayKey][$cid] : 0.0;
  }
  $dt->modify('+1 day');
}
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-8">
        <h3 class="mb-0">Relatórios Consolidados</h3>
        <p class="text-secondary mb-0">
          Acompanhe vendas agregadas e compare o desempenho entre lojas.
        </p>
      </div>
    </div>
  </div>
</div>

<style>
  /* Estilos específicos da página de relatórios consolidados (adaptados do original) */
  .account-tabs .nav-link {
    border-radius: 0;
  }

  .account-tabs .nav-link.active {
    background-color: #3c8dbc;
    color: #fff;
  }

  .filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .filter-row .form-select,
  .filter-row .form-control {
    min-width: 150px;
  }

  .summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 1.25rem 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
  }

  .summary-card.green {
    background: linear-gradient(135deg, #00a65a 0%, #008d4c 100%);
  }

  .summary-card.blue {
    background: linear-gradient(135deg, #3c8dbc 0%, #2c6c94 100%);
  }

  .summary-card-title {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    margin-bottom: 0.5rem;
  }

  .summary-card-value {
    font-size: 1.9rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  .summary-card-subtitle {
    font-size: 0.8rem;
    opacity: 0.85;
  }

  .payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }

  .payment-method-card {
    border: 1px solid #d2d6de;
    border-radius: 0.5rem;
    padding: 0.9rem 1rem;
    background-color: #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
  }

  .payment-method-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
    border-bottom: 2px solid #f4f4f4;
  }

  .payment-method-icon {
    width: 42px;
    height: 42px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
  }

  .payment-method-icon.money {
    background: #00a65a;
  }

  .payment-method-icon.credit {
    background: #3c8dbc;
  }

  .payment-method-icon.debit {
    background: #00c0ef;
  }

  .payment-method-icon.pix {
    background: #605ca8;
  }

  .payment-method-title {
    font-weight: 600;
    font-size: 0.95rem;
  }

  .payment-method-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem 1rem;
  }

  .payment-stat {
    display: flex;
    flex-direction: column;
  }

  .payment-stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: #777;
  }

  .payment-stat-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #333;
  }

  .payment-stat-value.large {
    font-size: 1.2rem;
    color: #00a65a;
  }

  .chart-container {
    position: relative;
    height: 280px;
  }

  .value-with-trend {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.84rem;
  }

  .sparkline-change-up {
    color: #00a65a;
  }

  .sparkline-change-down {
    color: #dd4b39;
  }

  .table-summary-row {
    background-color: #2c3e50;
    color: #fff;
  }

  .table-summary-row td {
    border-top: 2px solid #1b2834;
  }

  .info-box {
    display: flex;
    background: #fff;
    border-radius: 0.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    min-height: 80px;
  }

  .info-box-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    font-size: 2rem;
    color: #fff;
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
  }

  .info-box-icon.bg-aqua {
    background: #00c0ef;
  }

  .info-box-icon.bg-green {
    background: #00a65a;
  }

  .info-box-icon.bg-yellow {
    background: #f39c12;
  }

  .info-box-icon.bg-red {
    background: #dd4b39;
  }

  .info-box-content {
    padding: 0.6rem 0.75rem;
    flex: 1;
  }

  .info-box-text {
    font-size: 0.75rem;
    color: #555;
    text-transform: uppercase;
  }

  .info-box-number {
    font-size: 1.35rem;
    font-weight: 700;
  }

  .progress-description {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #999;
  }

  @media (max-width: 992px) {
    .payment-methods-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 576px) {
    .payment-methods-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
</style>

<div class="app-content">
  <div class="container-fluid">
    <!-- Conteúdo: Vendas consolidadas / Balanço de Caixa -->
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tab-vendas" role="tabpanel">
        <!-- Filtros principais -->
        <div class="card mb-3">
          <div class="card-body">
            <form class="filter-row" method="get" action="">
                <div class="d-flex flex-column" style="min-width: 220px;">
                  <label class="text-uppercase text-muted small mb-1">Período</label>
                  <select class="form-select form-select-sm" name="period" id="periodSelect">
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hoje</option>
                    <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Ontem</option>
                    <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Este mês</option>
                    <option value="prev_month" <?php echo $period === 'prev_month' ? 'selected' : ''; ?>>Mês anterior</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Este ano</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Período personalizado</option>
                  </select>
                </div>

                <div class="d-flex flex-column" style="min-width: 220px;">
                  <label class="text-uppercase text-muted small mb-1">Loja</label>
                  <select class="form-select form-select-sm" name="store_id">
                    <option value="0" <?php echo $selected_store_id === 0 ? 'selected' : ''; ?>>Todas as lojas (consolidado)</option>
                    <?php foreach ($stores as $s): ?>
                      <?php if (!isset($s['store_id'])) continue; ?>
                      <option value="<?php echo (int)$s['store_id']; ?>" <?php echo (int)$selected_store_id === (int)$s['store_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="d-flex flex-column" style="min-width: 180px;" id="customFromWrap">
                  <label class="text-uppercase text-muted small mb-1">De</label>
                  <input type="date" class="form-control form-control-sm" name="from" value="<?php echo htmlspecialchars($from); ?>">
                </div>
                <div class="d-flex flex-column" style="min-width: 180px;" id="customToWrap">
                  <label class="text-uppercase text-muted small mb-1">Até</label>
                  <input type="date" class="form-control form-control-sm" name="to" value="<?php echo htmlspecialchars($to); ?>">
                </div>

                <div class="ms-auto d-flex flex-wrap gap-2 align-items-end">
                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                  </button>
                  <button type="button" class="btn btn-success btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                  </button>
                </div>
              </form>
          </div>
        </div>

        <!-- Cards de resumo -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="summary-card green h-100">
              <div class="summary-card-title">FATURAMENTO TOTAL</div>
              <div class="summary-card-value">
                R$ <?php echo number_format(isset($totals['grand_total']) ? (float)$totals['grand_total'] : 0, 2, ',', '.'); ?>
              </div>
              <div class="summary-card-subtitle">Consolidado no período selecionado</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="summary-card blue h-100">
              <div class="summary-card-title">TOTAL DE VENDAS</div>
              <div class="summary-card-value">
                <?php echo (int)(isset($totals['transactions']) ? $totals['transactions'] : 0); ?>
              </div>
              <div class="summary-card-subtitle">
                Ticket médio: R$
                <?php echo number_format(isset($totals['ticket_medio']) ? (float)$totals['ticket_medio'] : 0, 2, ',', '.'); ?>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="summary-card h-100">
              <div class="summary-card-title">LOJAS ATIVAS</div>
              <div class="summary-card-value"><?php echo count($storesForView); ?></div>
              <div class="summary-card-subtitle">Lojas no relatório</div>
            </div>
          </div>
        </div>

        <!-- Resumo por Meio de Pagamento -->
        <div class="card mb-3">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-credit-card-2-front me-2"></i>
              Resumo por meio de pagamento (todas as lojas)
            </h5>
          </div>
          <div class="card-body">
            <div class="payment-methods-grid">
              <?php foreach ($byMethod as $cid => $m): ?>
                <?php
                  $meta = isset($methodGroups[$cid]) ? $methodGroups[$cid] : array('class' => 'money', 'icon' => 'fa fa-money');
                  $total = isset($m['total']) ? (float)$m['total'] : 0;
                  $tx    = isset($m['transactions']) ? (int)$m['transactions'] : 0;
                  $percent = isset($m['percent']) ? ((float)$m['percent'] * 100) : 0;
                  $ticket = isset($m['ticket_medio']) ? (float)$m['ticket_medio'] : 0;
                ?>
                <div class="payment-method-card">
                  <div class="payment-method-header">
                    <div class="payment-method-icon <?php echo $meta['class']; ?>">
                      <i class="<?php echo $meta['icon']; ?>"></i>
                    </div>
                    <div class="payment-method-title">
                      <?php echo htmlspecialchars($m['name']); ?>
                    </div>
                  </div>
                  <div class="payment-method-stats">
                    <div class="payment-stat">
                      <span class="payment-stat-label">Total</span>
                      <span class="payment-stat-value large">
                        R$ <?php echo number_format($total, 2, ',', '.'); ?>
                      </span>
                    </div>
                    <div class="payment-stat">
                      <span class="payment-stat-label">% do Total</span>
                      <span class="payment-stat-value">
                        <?php echo number_format($percent, 1, ',', '.'); ?>%
                      </span>
                    </div>
                    <div class="payment-stat">
                      <span class="payment-stat-label">Transações</span>
                      <span class="payment-stat-value"><?php echo $tx; ?></span>
                    </div>
                    <div class="payment-stat">
                      <span class="payment-stat-label">Ticket Médio</span>
                      <span class="payment-stat-value">
                        R$ <?php echo number_format($ticket, 2, ',', '.'); ?>
                      </span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-3 mb-3">
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header">
                <h5 class="card-title mb-0">Distribuição por meio de pagamento</h5>
              </div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="paymentMethodsChart"></canvas>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header">
                <h5 class="card-title mb-0">Evolução de vendas por meio de pagamento</h5>
              </div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="paymentTrendChart"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Detalhamento por loja -->
        <div class="card mb-3">
          <div class="card-header">
            <h5 class="card-title mb-0">
              <i class="bi bi-table me-2"></i>
              Detalhamento por loja e meio de pagamento
            </h5>
          </div>
          <div class="card-body table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Loja</th>
                  <?php foreach ($byMethod as $m): ?>
                    <th><?php echo htmlspecialchars($m['name']); ?></th>
                  <?php endforeach; ?>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($storesForView as $store): ?>
                  <?php
                    if (!isset($store['store_id'])) {
                      continue;
                    }
                    $sid = (int)$store['store_id'];
                    $row = isset($byStoreAndMethod[$sid]) ? $byStoreAndMethod[$sid] : array();
                    $totalStore = 0.0;
                  ?>
                  <tr>
                    <td><strong><i class="bi bi-shop me-1"></i> <?php echo htmlspecialchars($store['name']); ?></strong></td>
                    <?php foreach ($byMethod as $pm_id => $m): ?>
                      <?php
                        $cell = isset($row[$pm_id]) ? $row[$pm_id] : array('total' => 0, 'transactions' => 0);
                        $cellTotal = (float)$cell['total'];
                        $totalStore += $cellTotal;
                      ?>
                      <td>
                        R$ <?php echo number_format($cellTotal, 2, ',', '.'); ?>
                      </td>
                    <?php endforeach; ?>
                    <td>
                      <strong>R$ <?php echo number_format($totalStore, 2, ',', '.'); ?></strong>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="table-summary-row">
                  <td><strong>TOTAL CONSOLIDADO</strong></td>
                  <?php foreach ($byMethod as $m): ?>
                    <td><strong>R$ <?php echo number_format((float)$m['total'], 2, ',', '.'); ?></strong></td>
                  <?php endforeach; ?>
                  <td><strong>R$ <?php echo number_format(isset($totals['grand_total']) ? (float)$totals['grand_total'] : 0, 2, ',', '.'); ?></strong></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- KPIs adicionais -->
        <div class="row g-3 mb-4">
          <div class="col-md-3 col-sm-6">
            <div class="info-box">
              <span class="info-box-icon bg-aqua">
                <i class="fa fa-shopping-cart"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Total de vendas</span>
                <span class="info-box-number"><?php echo (int)(isset($totals['transactions']) ? $totals['transactions'] : 0); ?></span>
                <span class="progress-description">Consolidado no período</span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="info-box">
              <span class="info-box-icon bg-green">
                <i class="fa fa-line-chart"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Ticket médio</span>
                <span class="info-box-number">R$ <?php echo number_format(isset($totals['ticket_medio']) ? (float)$totals['ticket_medio'] : 0, 2, ',', '.'); ?></span>
                <span class="progress-description">Consolidado no período</span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="info-box">
              <span class="info-box-icon bg-yellow">
                <i class="fa fa-credit-card"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Forma de pagamento mais usada</span>
                <span class="info-box-number"><?php echo htmlspecialchars($topMethodName); ?></span>
                <span class="progress-description"><?php echo number_format($topMethodPercent, 1, ',', '.'); ?>% do faturamento total</span>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="info-box">
              <span class="info-box-icon bg-red">
                <i class="fa fa-star"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Loja que mais vendeu</span>
                <span class="info-box-number"><?php echo htmlspecialchars($topStoreName); ?></span>
                <span class="progress-description">R$ <?php echo number_format($topStoreTotal, 2, ',', '.'); ?> no período</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Inicialização dos gráficos desta seção (migrado de account_reports.php)
  document.addEventListener('DOMContentLoaded', function () {
    // Mostra/oculta campos de período personalizado
    const periodSelect = document.getElementById('periodSelect');
    const customFromWrap = document.getElementById('customFromWrap');
    const customToWrap = document.getElementById('customToWrap');

    function toggleCustomRange() {
      const isCustom = periodSelect && periodSelect.value === 'custom';
      if (customFromWrap) customFromWrap.style.display = isCustom ? 'flex' : 'none';
      if (customToWrap) customToWrap.style.display = isCustom ? 'flex' : 'none';
    }

    if (periodSelect) {
      periodSelect.addEventListener('change', toggleCustomRange);
    }
    toggleCustomRange();

    // Gráfico de distribuição por meio de pagamento
    const pmCanvas = document.getElementById('paymentMethodsChart');
    if (pmCanvas && typeof Chart !== 'undefined') {
      const ctxPm = pmCanvas.getContext('2d');
      const pmLabels = <?php echo json_encode(array_values(array_map(function ($m) {
        return $m['name'];
      }, $byMethod))); ?>;
      const pmData = <?php echo json_encode(array_values(array_map(function ($m) {
        return (float)$m['total'];
      }, $byMethod))); ?>;

      new Chart(ctxPm, {
        type: 'doughnut',
        data: {
          labels: pmLabels,
          datasets: [
            {
              data: pmData,
              backgroundColor: ['#00a65a', '#3c8dbc', '#00c0ef', '#605ca8'],
              borderColor: '#ffffff',
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' },
          },
        },
      });
    }

    // Gráfico de evolução por meio de pagamento
    const trendCanvas = document.getElementById('paymentTrendChart');
    if (trendCanvas && typeof Chart !== 'undefined') {
      const ctxTrend = trendCanvas.getContext('2d');
      const trendLabels = <?php echo json_encode($trendLabels); ?>;
      const trendSeries = <?php echo json_encode($trendSeries); ?>;

      new Chart(ctxTrend, {
        type: 'line',
        data: {
          labels: trendLabels,
          datasets: [
            {
              label: 'Dinheiro',
              data: trendSeries['7'] || [],
              borderColor: '#00a65a',
              backgroundColor: 'rgba(0,166,90,0.08)',
              tension: 0.3,
              fill: true,
            },
            {
              label: 'Crédito',
              data: trendSeries['5'] || [],
              borderColor: '#3c8dbc',
              backgroundColor: 'rgba(60,141,188,0.08)',
              tension: 0.3,
              fill: true,
            },
            {
              label: 'Débito',
              data: trendSeries['9'] || [],
              borderColor: '#00c0ef',
              backgroundColor: 'rgba(0,192,239,0.08)',
              tension: 0.3,
              fill: true,
            },
            {
              label: 'PIX',
              data: trendSeries['6'] || [],
              borderColor: '#605ca8',
              backgroundColor: 'rgba(96,92,168,0.08)',
              tension: 0.3,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          stacked: false,
          plugins: {
            legend: { position: 'bottom' },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => 'R$ ' + (value / 1000).toFixed(0) + 'k',
              },
            },
          },
        },
      });
    }
  });
</script>
