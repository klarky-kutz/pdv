<?php
// Configurações da Loja (Conta) - Conectado ao BD via _inc/account_store.php
// A página renderiza opções (country/timezone/templates/printers) e o JS carrega/salva valores.

global $user;
$storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

// Regra: apenas Admin (group_id=1) ou Owner do tenant pode editar configurações da loja
$currentUserIsOwnerOrAdmin = (function_exists('user_group_id') && (int)user_group_id() === 1)
  || (function_exists('is_tenant_owner') && is_tenant_owner());

// Segurança extra (multi-tenant): se houver tenant_id nas tabelas, limita ao mesmo tenant
$canAccessStore = false;
if ($storeId > 0 && $currentUserIsOwnerOrAdmin) {
  $canAccessStore = true;

  try {
    $uid = function_exists('user_id') ? (int)user_id() : 0;
    if ($uid > 0) {
      $hasTenantUsers = false;
      $hasTenantStores = false;

      $st = db()->prepare("SHOW COLUMNS FROM `users` LIKE 'tenant_id'");
      $st->execute();
      $hasTenantUsers = $st->rowCount() > 0;

      $st = db()->prepare("SHOW COLUMNS FROM `stores` LIKE 'tenant_id'");
      $st->execute();
      $hasTenantStores = $st->rowCount() > 0;

      if ($hasTenantUsers && $hasTenantStores) {
        $st = db()->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $tenantId = (int)$st->fetchColumn();

        if ($tenantId > 0) {
          $st = db()->prepare("SELECT tenant_id FROM stores WHERE store_id = ? LIMIT 1");
          $st->execute([(int)$storeId]);
          $storeTenantId = (int)$st->fetchColumn();

          if ($storeTenantId !== $tenantId) {
            $canAccessStore = false;
          }
        }
      }
    }
  } catch (Throwable $e) {
    $canAccessStore = false;
  }
}

// Para listas dependentes de store (ex.: templates), usa store_id válido.
$storeForLists = ($storeId > 0 && $canAccessStore) ? $storeId : store_id();

$noRedirect = isset($_GET['no_redirect']) && (string)$_GET['no_redirect'] === '1';

// Popup de acesso negado (somente Admin/Owner)
if ($storeId > 0 && !$currentUserIsOwnerOrAdmin) {
  $msg = 'Você não tem permissão para acessar as configurações da loja. Somente Administrador ou Owner pode acessar esta página.';
  $redirectUrl = root_url() . 'conta/lojas';

  echo '<script>';
  echo 'window.__ACCOUNT_ACCESS_DENIED_PAYLOAD = { message: ' . json_encode($msg, JSON_UNESCAPED_UNICODE) . ', redirectUrl: ' . json_encode($redirectUrl, JSON_UNESCAPED_UNICODE) . ' };';
  echo 'document.addEventListener("DOMContentLoaded", function () {';
  echo '  var p = window.__ACCOUNT_ACCESS_DENIED_PAYLOAD;';
  echo '  if (p && window.openAccountAccessDeniedModal) {';
  echo '    window.openAccountAccessDeniedModal(p.message, p.redirectUrl);';
  echo '  } else if (p && p.redirectUrl) {';
  echo '    alert(p.message || "Acesso negado");';
  echo '    window.location.href = p.redirectUrl;';
  echo '  }';
  echo '});';
  echo '</script>';

  return;
}
?>

<?php if ($storeId <= 0 || ($storeId > 0 && !$canAccessStore)) : ?>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-7">
          <h3 class="mb-0">Configurações da Loja</h3>
          <p class="text-secondary mb-0">Selecione uma loja para editar as configurações.</p>
        </div>
        <div class="col-sm-5 text-sm-end mt-2 mt-sm-0">
          <a href="<?php echo root_url(); ?>conta/lojas" class="btn btn-primary btn-sm">
            <i class="bi bi-card-list"></i> Ir para Gerenciar lojas
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mp-card mp-settings-shell">
        <div class="card-body p-4">
          <div class="d-flex flex-column flex-md-row gap-3 align-items-start">
            <div class="mp-settings-shopicon" style="width:48px;height:48px;">
              <i class="bi bi-exclamation-triangle" style="font-size:1.25rem;"></i>
            </div>
            <div class="flex-grow-1">
              <div class="h5 mb-1">Nenhuma loja selecionada</div>
              <div class="text-secondary">
                Para abrir esta página, volte em <strong>Gerenciar lojas</strong> e clique no ícone de <strong>Editar</strong>.
              </div>

              <?php if ($storeId > 0 && !$canAccessStore) : ?>
                <div class="alert alert-warning mt-3 mb-0">
                  Você não tem permissão para acessar a loja <strong>#<?php echo (int)$storeId; ?></strong>.
                </div>
              <?php endif; ?>

              <div class="mt-3 d-flex flex-wrap gap-2">
                <a href="<?php echo root_url(); ?>conta/lojas" class="btn btn-primary">
                  <i class="bi bi-card-list"></i> Gerenciar lojas
                </a>
                <a href="<?php echo root_url(); ?>conta" class="btn btn-outline-secondary">
                  <i class="bi bi-speedometer2"></i> Voltar ao painel
                </a>
                <?php if (!$noRedirect) : ?>
                  <button type="button" class="btn btn-outline-secondary" id="btnCancelRedirect">
                    Cancelar redirecionamento
                  </button>
                <?php endif; ?>
              </div>

              <?php if (!$noRedirect) : ?>
                <div class="small text-secondary mt-3">
                  Redirecionando para <strong>Gerenciar lojas</strong> em <span id="redirectCountdown">5</span>s…
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$noRedirect) : ?>
        <script>
          (function () {
            var seconds = 5;
            var el = document.getElementById('redirectCountdown');
            var btn = document.getElementById('btnCancelRedirect');
            var timer = null;

            function tick() {
              seconds -= 1;
              if (el) el.textContent = String(seconds);
              if (seconds <= 0) {
                window.location.href = "<?php echo root_url(); ?>conta/lojas";
              }
            }

            timer = window.setInterval(tick, 1000);

            if (btn) {
              btn.addEventListener('click', function () {
                window.clearInterval(timer);
                btn.disabled = true;
                btn.textContent = 'Redirecionamento cancelado';
                if (el) el.closest('.small') && (el.closest('.small').style.display = 'none');
              });
            }
          })();
        </script>
      <?php endif; ?>
    </div>
  </div>

  <?php return; ?>
<?php endif; ?>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-7">
        <h3 class="mb-0">Configurações da Loja</h3>
        <p class="text-secondary mb-0">
          <?php if ($storeId > 0): ?>
            Loja #<?php echo (int)$storeId; ?> •
          <?php endif; ?>
          Organize as informações gerais e as preferências do POS em um só lugar.
        </p>
      </div>
      <div class="col-sm-5 text-sm-end mt-2 mt-sm-0">
        <a href="<?php echo root_url(); ?>conta/lojas" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left"></i> Voltar para lojas
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-store-settings-save="1">
          <i class="bi bi-save"></i> Salvar
        </button>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="alert alert-info mp-settings-alert" role="alert">
      <div class="d-flex gap-2">
        <div class="mp-settings-alert-icon"><i class="bi bi-info-circle-fill"></i></div>
        <div>
          <div class="fw-semibold">Configurações reais (Banco de Dados)</div>
          <div class="small">As alterações em Geral/POS são salvas no banco. A Logo tem um botão separado para upload.</div>
        </div>
      </div>
    </div>

    <div class="card mp-card mp-settings-shell mb-4">
      <div class="mp-settings-navbar px-3 py-2">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <div class="mp-settings-shopicon"><i class="bi bi-shop"></i></div>
            <div>
              <div class="fw-semibold">Loja selecionada</div>
              <div class="small text-secondary">
                <?php if ($storeId > 0): ?>
                  Loja #<?php echo (int)$storeId; ?> • <span data-store-settings-store-name="1">Carregando…</span>
                <?php else: ?>
                  Selecione uma loja em <a href="<?php echo root_url(); ?>conta/lojas">Gerenciar lojas</a>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
            <span class="badge text-bg-success">Conectado</span>
            <a href="<?php echo root_url(); ?>conta/lojas" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-card-list"></i> Gerenciar lojas
            </a>
          </div>
        </div>
      </div>

      <div class="card-body p-0">
        <ul class="nav nav-tabs px-3" id="storeSettingsTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-geral-btn" data-bs-toggle="tab" data-bs-target="#tab-geral" type="button" role="tab" aria-controls="tab-geral" aria-selected="true">
              <i class="bi bi-sliders"></i> Geral
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-pos-btn" data-bs-toggle="tab" data-bs-target="#tab-pos" type="button" role="tab" aria-controls="tab-pos" aria-selected="false">
              <i class="bi bi-receipt"></i> POS
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-logo-btn" data-bs-toggle="tab" data-bs-target="#tab-logo" type="button" role="tab" aria-controls="tab-logo" aria-selected="false">
              <i class="bi bi-image"></i> Logo
            </button>
          </li>
        </ul>

        <form data-store-settings-form="1" novalidate>
          <input type="hidden" data-store-settings-store-id="1" value="<?php echo (int)$storeId; ?>" />

          <div class="tab-content p-3 p-lg-4" id="storeSettingsTabsContent">
            <!-- ABA: GERAL -->
            <div class="tab-pane fade show active" id="tab-geral" role="tabpanel" aria-labelledby="tab-geral-btn" tabindex="0">

              <div id="geral-identificacao" class="mp-form-section">
                <div class="mp-section-title">Identificação</div>
                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label">Nome da loja</label>
                    <input type="text" class="form-control" placeholder="Ex.: Loja Centro" data-store-settings-name="1" />
                  </div>
                  <div class="col-lg-3">
                    <label class="form-label">Código da loja</label>
                    <input type="text" class="form-control" placeholder="ex.: loja_centro" data-store-settings-code-name="1" />
                    <div class="form-text">Gerado automaticamente a partir do nome (você pode editar).</div>
                  </div>
                  <div class="col-lg-3">
                    <label class="form-label">País</label>
                    <?php echo countrySelector('', 'store-country', 'country', 'form-select'); ?>
                  </div>

                  <div class="col-lg-4">
                    <label class="form-label">CNPJ / VAT</label>
                    <input type="text" class="form-control" placeholder="00.000.000/0000-00" data-store-settings-vat-reg-no="1" />
                  </div>
                  <div class="col-lg-4">
                    <label class="form-label">Timezone</label>
                    <select class="form-select" id="store-timezone" data-store-settings-timezone="1">
                      <?php
                        $timezone = null;
                        include __DIR__ . '/../../_inc/helper/timezones.php';
                      ?>
                    </select>
                  </div>
                  <div class="col-lg-4">
                    <label class="form-label">Imposto (%)</label>
                    <input type="number" class="form-control" placeholder="0" min="0" max="99" data-store-settings-tax="1" />
                  </div>
                </div>
              </div>

            </div>

            <!-- ABA: POS -->
            <div class="tab-pane fade" id="tab-pos" role="tabpanel" aria-labelledby="tab-pos-btn" tabindex="0">
              <div class="mp-form-section">
                <div class="mp-section-title">Referência de vendas</div>
                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label">Formato</label>
                    <select class="form-select" data-store-settings-reference-format="1">
                      <option value="year_month_sequence">ANO/MÊS/Sequência (SL/2024/08/001)</option>
                      <option value="year_sequence">ANO/Sequência (SL/2024/001)</option>
                      <option value="sequence">Sequência</option>
                      <option value="random">Aleatório</option>
                    </select>
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label">Prefixo</label>
                    <input type="text" class="form-control" placeholder="Ex.: SL" data-store-settings-sales-reference-prefix="1" />
                  </div>
                </div>
              </div>

              <div class="mp-form-section">
                <div class="mp-section-title">Recibo e impressão</div>
                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label">Template do recibo</label>
                    <select class="form-select" data-store-settings-receipt-template="1">
                      <option value="">Selecione…</option>
                      <?php foreach (get_postemplates([], $storeForLists) as $t) : ?>
                        <option value="<?php echo (int)($t['template_id'] ?? 0); ?>">
                          <?php echo htmlspecialchars(postemplate_strip_custom_marker((string)($t['template_name'] ?? ''))); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-lg-3">
                    <label class="form-label">Impressão</label>
                    <select class="form-select" data-store-settings-remote-printing="1">
                      <option value="0">Web Browser</option>
                      <option value="1">PHP Server</option>
                    </select>
                  </div>
                  <div class="col-lg-3">
                    <label class="form-label">Auto imprimir</label>
                    <select class="form-select" data-store-settings-auto-print="1">
                      <option value="0">Não</option>
                      <option value="1">Sim</option>
                    </select>
                  </div>

                  <div class="col-12" data-store-settings-printer-wrap="1">
                    <label class="form-label">Impressora de recibo</label>
                    <select class="form-select" data-store-settings-receipt-printer="1">
                      <option value="">Selecione…</option>
                      <?php foreach (get_printers() as $p) : ?>
                        <option value="<?php echo (int)($p['printer_id'] ?? 0); ?>">
                          <?php echo htmlspecialchars((string)($p['title'] ?? '')); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text">Em modo “PHP Server”, uma impressora geralmente é obrigatória.</div>
                  </div>
                </div>
              </div>

              <div class="mp-form-section">
                <div class="mp-section-title">Comportamento do POS</div>
                <div class="row g-3">
                  <div class="col-lg-4">
                    <label class="form-label">Limite de produtos no POS</label>
                    <input type="number" class="form-control" min="0" data-store-settings-pos-product-display-limit="1" />
                  </div>
                  <div class="col-lg-4">
                    <label class="form-label">Após finalizar venda</label>
                    <select class="form-select" data-store-settings-after-sell-page="1">
                      <option value="pos">Voltar ao POS</option>
                      <option value="receipt_in_new_window">Abrir recibo em nova janela</option>
                      <option value="receipt_in_popup">Abrir recibo em popup</option>
                      <option value="toastr_msg">Mensagem (toastr)</option>
                      <option value="sweet_alert_msg">Mensagem (sweet alert)</option>
                      <option value="invoice">Abrir fatura</option>
                    </select>
                  </div>
                  <div class="col-lg-4">
                    <label class="form-label">Permitir alterar preço no caixa</label>
                    <select class="form-select" data-store-settings-change-item-price="1">
                      <option value="0">Não</option>
                      <option value="1">Sim</option>
                    </select>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Rodapé do recibo</label>
                    <textarea class="form-control" rows="2" placeholder="Ex.: Obrigado pela preferência!" data-store-settings-invoice-footer-text="1"></textarea>
                  </div>

                  <div class="col-lg-6">
                    <label class="form-label">Som de confirmação</label>
                    <select class="form-select" data-store-settings-sound-effect="1">
                      <option value="1">Ativo</option>
                      <option value="0">Inativo</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="small text-secondary">
                Dica: ajuste primeiro a parte “Geral” e depois o “POS” para o fluxo ideal de venda.
              </div>
            </div>

            <!-- ABA: LOGO -->
            <div class="tab-pane fade" id="tab-logo" role="tabpanel" aria-labelledby="tab-logo-btn" tabindex="0">
              <div class="mp-form-section">
                <div class="mp-section-title">Logo da Loja</div>
                <div class="text-secondary small mb-2">Uma logo ajuda a identificar a loja no sistema e em relatórios.</div>

                <div class="mp-dropzone" data-store-settings-logo-dropzone="1" title="Clique para selecionar ou arraste a imagem">
                  <div class="d-flex flex-column flex-md-row gap-3 align-items-center">
                    <div class="mp-logo-preview">
                      <div data-store-settings-logo-empty="1" class="mp-logo-empty">
                        <i class="bi bi-image"></i>
                      </div>
                      <img data-store-settings-logo-preview="1" alt="Logo" style="display:none;" />
                    </div>

                    <div class="flex-grow-1">
                      <div class="mp-dropzone-title">Envie a logo da sua loja</div>
                      <div class="mp-dropzone-subtitle">PNG/JPG até 2MB • Recomendado: fundo transparente</div>
                      <div class="small text-secondary mt-1" data-store-settings-logo-filename="1"></div>

                      <div class="mt-3 d-flex gap-2 flex-wrap justify-content-center justify-content-md-start">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-store-settings-logo-pick="1">
                          <i class="bi bi-upload"></i> Escolher imagem
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" style="display:none;" data-store-settings-logo-remove="1">
                          <i class="bi bi-x-circle"></i> Remover
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-store-settings-logo-upload="1">
                          <i class="bi bi-cloud-upload"></i> Enviar logo
                        </button>
                      </div>

                      <input type="file" class="d-none" accept="image/*" data-store-settings-logo-input="1" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="px-3 pb-3 px-lg-4 pb-lg-4 d-flex flex-column flex-sm-row gap-2 justify-content-between">
            <a href="<?php echo root_url(); ?>conta/lojas" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <button type="button" class="btn btn-primary btn-sm" data-store-settings-save="1">
              <i class="bi bi-save"></i> Salvar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
