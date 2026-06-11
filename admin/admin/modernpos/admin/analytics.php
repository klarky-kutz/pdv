<?php 
ob_start();
session_start();
include ("../_init.php");

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

if (user_group_id() != 1 && !has_permission('access', 'read_analytics')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

$from = from() ? from() : date('Y-m-d');
$to = to() ? to() : date('Y-m-d');

// Obter dados
$monthly_comparison = get_monthly_comparison();
$total_sales = selling_price($from, $to);
$total_profit = get_period_profit($from, $to);
$avg_ticket = get_average_ticket($from, $to);
$total_invoices = total_invoice($from, $to);
$payment_methods = get_payment_methods_distribution($from, $to);
$low_rotation = get_low_rotation_products(30, 5);

$document->setTitle(trans('title_analytics'));
$document->setBodyClass('sidebar-collapse');

include ("header.php"); 
include ("left_sidebar.php");
?>

<style>
.info-table td { padding: 8px 12px !important; }
.info-table .label-col { background: #f9f9f9; font-weight: 600; width: 50%; }
.info-table .value-col { text-align: right; }
.quick-stat { text-align: center; padding: 15px 10px; border-right: 1px solid #eee; }
.quick-stat:last-child { border-right: none; }
.quick-stat h4 { margin: 0 0 5px 0; font-size: 20px; font-weight: bold; }
.quick-stat p { margin: 0; font-size: 11px; color: #888; text-transform: uppercase; }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <?php include ("../_inc/template/partials/apply_filter.php"); ?>
    <h1><?php echo trans('text_analytics_title'); ?> <small><?php echo store('name'); ?></small></h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> <?php echo trans('text_dashboard'); ?></a></li>
      <li class="active"><?php echo trans('text_analytics_title'); ?></li>
    </ol>
  </section>
  
  <section class="content">

    <!-- Resumo Rápido -->
    <div class="box box-primary">
      <div class="box-body">
        <div class="row">
          <div class="col-xs-3 quick-stat">
            <h4 class="text-blue"><?php echo get_currency_symbol() . currency_format($total_sales); ?></h4>
            <p>Total Vendas</p>
          </div>
          <div class="col-xs-3 quick-stat">
            <h4 class="text-green"><?php echo get_currency_symbol() . currency_format($total_profit); ?></h4>
            <p>Lucro</p>
          </div>
          <div class="col-xs-3 quick-stat">
            <h4 class="text-purple"><?php echo get_currency_symbol() . currency_format($avg_ticket); ?></h4>
            <p>Ticket Médio</p>
          </div>
          <div class="col-xs-3 quick-stat">
            <h4 class="text-aqua"><?php echo number_format($total_invoices); ?></h4>
            <p>Qtd. Vendas</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Coluna Esquerda -->
      <div class="col-md-6">

        <!-- Comparativo Mensal -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-balance-scale"></i> Comparativo Mensal</h3>
          </div>
          <div class="box-body p-0">
            <table class="table info-table mb-0">
              <tr>
                <td class="label-col"><?php echo $monthly_comparison['current']['label']; ?></td>
                <td class="value-col">
                  <strong class="text-blue"><?php echo get_currency_symbol() . currency_format($monthly_comparison['current']['sales']); ?></strong>
                  <small class="text-muted">(<?php echo $monthly_comparison['current']['invoices']; ?> vendas)</small>
                </td>
              </tr>
              <tr>
                <td class="label-col"><?php echo $monthly_comparison['previous']['label']; ?></td>
                <td class="value-col">
                  <strong><?php echo get_currency_symbol() . currency_format($monthly_comparison['previous']['sales']); ?></strong>
                  <small class="text-muted">(<?php echo $monthly_comparison['previous']['invoices']; ?> vendas)</small>
                </td>
              </tr>
              <tr class="<?php echo ($monthly_comparison['current']['sales'] >= $monthly_comparison['previous']['sales']) ? 'success' : 'danger'; ?>">
                <td class="label-col">Variação</td>
                <td class="value-col">
                  <?php 
                  $diff = $monthly_comparison['current']['sales'] - $monthly_comparison['previous']['sales'];
                  $diff_pct = $monthly_comparison['previous']['sales'] > 0 ? ($diff / $monthly_comparison['previous']['sales']) * 100 : 0;
                  ?>
                  <strong><?php echo ($diff >= 0 ? '+' : '') . get_currency_symbol() . currency_format($diff); ?></strong>
                  <span class="label label-<?php echo $diff >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo ($diff_pct >= 0 ? '+' : '') . round($diff_pct, 1); ?>%
                  </span>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Formas de Pagamento -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-credit-card"></i> Formas de Pagamento</h3>
          </div>
          <div class="box-body p-0">
            <table class="table info-table mb-0">
              <?php 
              $total_payments = array_sum(array_column($payment_methods, 'total_amount'));
              foreach ($payment_methods as $pm): 
                $pct = $total_payments > 0 ? ($pm['total_amount'] / $total_payments) * 100 : 0;
              ?>
              <tr>
                <td class="label-col"><?php echo $pm['method_name'] ?? 'N/A'; ?></td>
                <td class="value-col">
                  <strong><?php echo get_currency_symbol() . currency_format($pm['total_amount']); ?></strong>
                  <small class="text-muted">(<?php echo round($pct, 1); ?>%)</small>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($payment_methods)): ?>
              <tr><td colspan="2" class="text-center text-muted">Sem dados no período</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <!-- Resumo do Caixa -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-book"></i> Resumo do Caixa</h3>
            <a href="report_cashbook.php" class="btn btn-xs btn-default pull-right">Ver Detalhes</a>
          </div>
          <div class="box-body">
            <?php include ROOT.'/_inc/template/partials/report_cashbook_summary.php';?>
          </div>
        </div>

      </div>

      <!-- Coluna Direita -->
      <div class="col-md-6">

        <!-- Top Produtos -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-star"></i> Produtos Mais Vendidos</h3>
          </div>
          <div class="box-body p-0">
            <table class="table info-table mb-0">
              <?php if ($top = top_products($from, $to, 5)): foreach ($top as $i => $p): ?>
              <tr>
                <td style="width:30px; text-align:center;"><span class="badge bg-blue"><?php echo $i+1; ?></span></td>
                <td><?php echo limit_char($p['item_name'], 30); ?></td>
                <td class="text-right"><strong><?php echo (int)$p['quantity']; ?></strong> un.</td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="3" class="text-center text-muted">Sem dados no período</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <!-- Top Clientes -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-users"></i> Clientes que Mais Compraram</h3>
          </div>
          <div class="box-body p-0">
            <table class="table info-table mb-0">
              <?php if ($top_c = top_customers($from, $to, 5)): foreach ($top_c as $i => $c): 
                $customer_name = get_the_customer($c['customer_id'], 'customer_name');
              ?>
              <tr>
                <td style="width:30px; text-align:center;"><span class="badge bg-green"><?php echo $i+1; ?></span></td>
                <td><?php echo limit_char($customer_name ? $customer_name : 'Cliente #'.$c['customer_id'], 25); ?></td>
                <td class="text-right"><strong><?php echo get_currency_symbol() . currency_format($c['total']); ?></strong></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="3" class="text-center text-muted">Sem dados no período</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <!-- Produtos Baixa Rotação -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-exclamation-triangle text-yellow"></i> Produtos Parados (30 dias)</h3>
          </div>
          <div class="box-body p-0">
            <table class="table info-table mb-0">
              <?php if (!empty($low_rotation)): foreach ($low_rotation as $p): ?>
              <tr>
                <td>
                  <?php echo limit_char($p['p_name'], 30); ?>
                  <small class="text-muted">(<?php echo $p['p_code']; ?>)</small>
                </td>
                <td class="text-right">
                  <span class="label label-warning"><?php echo $p['stock']; ?> em estoque</span>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center text-muted"><i class="fa fa-check text-green"></i> Nenhum produto parado</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <!-- Aniversariantes -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-birthday-cake text-red"></i> Aniversariantes de Hoje</h3>
          </div>
          <div class="box-body p-0">
            <table class="table info-table mb-0">
              <?php if ($birthday_customers = get_today_birthday_customers()): foreach ($birthday_customers as $bc): ?>
              <tr>
                <td><?php echo $bc['customer_name']; ?></td>
                <td class="text-right">
                  <a href="customer_profile.php?customer_id=<?php echo $bc['customer_id'];?>" class="btn btn-xs btn-info">Ver</a>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center text-muted">Nenhum aniversariante hoje</td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>

      </div>
    </div>

    <!-- Melhor Cliente -->
    <?php if (best_customer('customer_name')): ?>
    <div class="callout callout-info">
      <h4><i class="fa fa-trophy"></i> Melhor Cliente</h4>
      <p>
        <strong><?php echo best_customer('customer_name'); ?></strong> &mdash; 
        Total comprado: <strong><?php echo get_currency_symbol() . currency_format(best_customer('total')); ?></strong>
        <?php if (best_customer('customer_mobile')): ?> | Tel: <?php echo best_customer('customer_mobile'); ?><?php endif; ?>
        <a href="customer_profile.php?customer_id=<?php echo best_customer('customer_id'); ?>" class="btn btn-xs btn-info pull-right">Ver Perfil</a>
      </p>
    </div>
    <?php endif; ?>
    
  </section>

</div>

<?php include ("footer.php"); ?>
