<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_tokens.php';

$tid = ai_tenant_id();
$tokenBalance = ai_get_token_balance($tid);
$packages = ai_get_token_packages();
$history = ai_get_purchase_history($tid);
$ranking = ai_get_demand_ranking($tid, 'month', 10);

$document->setTitle('Créditos IA · Moda IA');
$document->setBodyClass('concierge_tokens');

include ("header.php");
include ("left_sidebar.php");
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-database" style="color:#6d28d9;margin-right:8px"></i>Créditos e Demanda IA</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Créditos</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- Coluna Esquerda: Saldo e Pacotes -->
      <div class="col-md-8">
        <!-- Card Saldo -->
        <div class="box box-solid" style="background:linear-gradient(135deg,#4c1d95,#7c3aed);color:#fff;border-radius:4px;margin-bottom:20px;">
          <div class="box-body" style="padding:25px;display:flex;align-items:center;justify-content:space-between;">
            <div>
              <div style="font-size:14px;opacity:0.8;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Saldo de Tokens Extras</div>
              <div style="font-size:42px;font-weight:800;margin-top:5px;"><?= $tokenBalance ?> <small style="font-size:16px;color:#ddd;font-weight:400;">créditos</small></div>
              <div style="font-size:12px;margin-top:10px;background:rgba(255,255,255,0.15);display:inline-block;padding:4px 12px;border-radius:20px;">
                Estes créditos não expiram mensalmente
              </div>
            </div>
            <i class="fa fa-database" style="font-size:80px;opacity:0.15;"></i>
          </div>
        </div>

        <!-- Pacotes Disponíveis -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-shopping-cart text-purple"></i> Adquirir Mais Créditos</h3>
          </div>
          <div class="box-body">
            <div class="row">
              <?php foreach ($packages as $p): ?>
                <div class="col-md-4 mb-4">
                  <div class="package-card-v2" style="border:1px solid #eee;border-radius:8px;padding:20px;text-align:center;transition:all 0.3s;height:100%;display:flex;flex-direction:column;">
                    <div style="font-weight:700;color:#4b5563;margin-bottom:15px;"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:28px;font-weight:800;color:#7c3aed;"><?= $p['tokens_qty'] ?></div>
                    <div style="font-size:12px;color:#9ca3af;margin-bottom:15px;">Tokens</div>
                    <div style="font-size:20px;font-weight:700;color:#059669;margin-bottom:20px;">R$ <?= number_format($p['price'], 2, ',', '.') ?></div>
                    <button class="btn btn-primary" style="margin-top:auto;width:100%;font-weight:700;border-radius:4px;" onclick="aiBuyTokenPackage(<?= $p['package_id'] ?>)">
                      <i class="fa fa-bolt"></i> Comprar Agora
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Histórico de Compras -->
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-history text-purple"></i> Histórico de Recargas</h3>
          </div>
          <div class="box-body no-padding">
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Pacote</th>
                    <th>Qtd</th>
                    <th>Valor</th>
                    <th>Método</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($history)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma recarga encontrada.</td></tr>
                  <?php else: ?>
                    <?php foreach ($history as $h): ?>
                      <tr>
                        <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        <td><?= htmlspecialchars($h['package_name'] ?: 'Personalizado') ?></td>
                        <td><?= $h['tokens_qty'] ?></td>
                        <td>R$ <?= number_format($h['amount_paid'], 2, ',', '.') ?></td>
                        <td style="text-transform:uppercase"><?= $h['payment_method'] ?></td>
                        <td>
                          <?php if ($h['status'] === 'paid'): ?>
                            <span class="label label-success">Pago</span>
                          <?php elseif ($h['status'] === 'pending'): ?>
                            <span class="label label-warning">Pendente</span>
                          <?php else: ?>
                            <span class="label label-danger"><?= $h['status'] ?></span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Coluna Direita: Ranking de Demanda -->
      <div class="col-md-4">
        <div class="box box-default">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-line-chart text-purple"></i> Top 10 Produtos (30 dias)</h3>
          </div>
          <div class="box-body no-padding">
            <ul class="nav nav-pills nav-stacked">
              <?php if (empty($ranking)): ?>
                <li class="p-4 text-center text-muted">Ainda não há dados de demanda.</li>
              <?php else: ?>
                <?php foreach ($ranking as $idx => $item): ?>
                  <li style="border-bottom:1px solid #f4f4f4;">
                    <a href="concierge_catalogo.php?search=<?= urlencode($item['model_name']) ?>" style="display:flex;justify-content:space-between;align-items:center;padding:12px 15px;">
                      <span style="font-size:13px;font-weight:600;color:#374151;">
                        <span style="color:#9ca3af;margin-right:8px;font-weight:400;"><?= $idx + 1 ?>.</span>
                        <?= htmlspecialchars($item['model_name']) ?>
                      </span>
                      <span class="badge bg-purple" style="font-size:11px;font-weight:700;"><?= $item['count'] ?> buscas</span>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>
          <div class="box-footer text-center">
            <a href="concierge_catalogo.php" class="uppercase" style="font-size:12px;font-weight:700;">Ver Catálogo Completo</a>
          </div>
        </div>

        <!-- Dica de Tokens -->
        <div class="box box-solid bg-gray" style="border-radius:4px;border-left:4px solid #7c3aed;">
          <div class="box-body" style="padding:15px;">
            <div style="font-weight:700;color:#1f2937;margin-bottom:8px;"><i class="fa fa-lightbulb-o"></i> Como funcionam os créditos?</div>
            <p style="font-size:12px;color:#4b5563;line-height:1.5;margin:0;">
              Cada plano mensal possui uma cota-base de chamadas IA. Quando essa cota acaba, o sistema utiliza automaticamente seus <strong>Tokens Extras</strong>. Diferente da cota do plano, os tokens comprados nesta página não expiram e ficam disponíveis até serem consumidos.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<style>
.package-card-v2:hover {
  border-color: #7c3aed !important;
  box-shadow: 0 8px 24px rgba(124, 58, 237, 0.12);
  transform: translateY(-3px);
}
.text-purple { color: #7c3aed !important; }
.bg-purple { background-color: #7c3aed !important; color: #fff !important; }
</style>

<?php include ("footer.php"); ?>
