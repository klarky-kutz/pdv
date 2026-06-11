<?php
/**
 * Seção: Assinatura & Planos (Sistema de Pagamentos Interno)
 * 
 * NOVA ESTRUTURA:
 * - Tab "Planos": Plano atual + Uso + Grid de planos disponíveis
 * - Tab "Histórico": Histórico de pagamentos + Ações + Método de pagamento + Cancelamento
 */

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'plano_atual';
// Redireciona tabs antigas para as novas
if ($tab === 'upgrade') {
    $tab = 'plano_atual'; // upgrade agora é parte de plano_atual
}

// Permite "checkout" e "payment" como tabs internas
$validTabs = ['plano_atual', 'historico', 'checkout', 'payment'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'plano_atual';
}

// Para UI: checkout/pagamento devem manter "Planos" como ativo no menu/pills
$tabUi = in_array($tab, ['checkout', 'payment'], true) ? 'plano_atual' : $tab;
?>

<?php
  // Cache bust para CSS/JS (evita “não mudou nada” por cache do browser)
  $plansCssPath = __DIR__ . '/../../conta/assets/css/plans.css';
  $plansJsPath  = __DIR__ . '/../../conta/assets/js/plans.js';
  $plansCssV = file_exists($plansCssPath) ? (int)filemtime($plansCssPath) : time();
  $plansJsV  = file_exists($plansJsPath) ? (int)filemtime($plansJsPath) : time();
?>

<!-- CSS específico da página de planos -->
<link rel="stylesheet" href="<?php echo root_url(); ?>conta/assets/css/plans.css?v=<?php echo $plansCssV; ?>" />
<!-- CSS do cartão 3D interativo -->
<link rel="stylesheet" href="<?php echo root_url(); ?>conta/assets/css/card-3d.css?v=<?php echo $plansCssV; ?>" />
<!-- Fonte Source Code Pro para números do cartão -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@300;400;600&display=swap" />
<!-- Stripe.js para processamento seguro de cartões (PCI compliant) -->
<script src="https://js.stripe.com/v3/"></script>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-8">
        <h3 class="mb-0">Planos e Assinatura</h3>
        <p class="text-secondary mb-0">
          Gerencie seu plano, assinatura e histórico de pagamentos.
        </p>
      </div>
      <div class="col-sm-4 text-end">
        <span id="subscription-status-badge" class="badge bg-secondary">Carregando...</span>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="account-plans-page">
      
      <!-- Alert de status da assinatura -->
      <div id="subscription-alert" class="alert mb-4" style="display: none;"></div>

      <!-- Tabs - NOVA ESTRUTURA -->
      <ul class="nav nav-pills mb-4 account-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a href="<?php echo root_url(); ?>conta/planos" 
           class="nav-link <?php echo $tabUi === 'plano_atual' ? 'active' : ''; ?>">
          <i class="bi bi-grid-3x3-gap me-1"></i> Planos
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a href="<?php echo root_url(); ?>conta/planos/historico" 
           class="nav-link <?php echo $tabUi === 'historico' ? 'active' : ''; ?>">
          <i class="bi bi-clock-history me-1"></i> Histórico
        </a>
      </li>
    </ul>

    <div class="tab-content">
      
      <!-- TAB: PLANOS (Grid de Planos) -->
      <div class="tab-pane fade <?php echo $tabUi === 'plano_atual' ? 'show active' : ''; ?>" 
           id="tab-planos" role="tabpanel">

        <?php if ($tab === 'checkout'): ?>
          <?php
            $checkoutPlanId = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
            $checkoutBilling = isset($_GET['billing']) ? strtolower(trim((string)$_GET['billing'])) : 'monthly';
            if (!in_array($checkoutBilling, ['monthly', 'yearly'], true)) {
              $checkoutBilling = 'monthly';
            }

            $prefill = [
              'name' => '',
              'email' => '',
              'phone' => '',
              'document' => '',
              'person_type' => 'cpf',
              'company' => '',
            ];

            $checkoutPlan = null;
            $checkoutError = null;

            try {
              if (!function_exists('db')) {
                throw new Exception('Função db() não disponível.');
              }

              $pdoCheckout = db();

              // Prefill de usuário/tenant
              $uid = function_exists('user_id') ? (int)user_id() : 0;
              $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;

              if ($uid > 0) {
                $stU = $pdoCheckout->prepare('SELECT tenant_id, username, email, mobile, cpf FROM users WHERE id = ? LIMIT 1');
                $stU->execute([$uid]);
                $urow = $stU->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($urow) {
                  if (!empty($urow['username'])) $prefill['name'] = (string)$urow['username'];
                  if (!empty($urow['email'])) $prefill['email'] = (string)$urow['email'];
                  if (!empty($urow['mobile'])) $prefill['phone'] = (string)$urow['mobile'];
                  if (!empty($urow['cpf'])) $prefill['document'] = (string)$urow['cpf'];
                  if ($sessionTid <= 0 && !empty($urow['tenant_id'])) $sessionTid = (int)$urow['tenant_id'];
                }
              }

              if ($sessionTid > 0) {
                $stT = $pdoCheckout->prepare('SELECT company_name FROM tenants WHERE tenant_id = ? LIMIT 1');
                $stT->execute([$sessionTid]);
                $trow = $stT->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($trow && !empty($trow['company_name'])) {
                  $prefill['company'] = (string)$trow['company_name'];
                }
              }

              if ($checkoutPlanId > 0) {
                // Compat: algumas instalações não possuem price_yearly ou is_active
                $hasPriceYearly = false;
                $hasIsActive = true;

                try {
                  $stCol = $pdoCheckout->query("SHOW COLUMNS FROM plans LIKE 'price_yearly'");
                  $hasPriceYearly = $stCol && $stCol->rowCount() > 0;
                } catch (Throwable $eCol) {
                  $hasPriceYearly = false;
                }

                try {
                  $stCol = $pdoCheckout->query("SHOW COLUMNS FROM plans LIKE 'is_active'");
                  $hasIsActive = $stCol && $stCol->rowCount() > 0;
                } catch (Throwable $eCol) {
                  $hasIsActive = false;
                }

                $sqlPlan = 'SELECT plan_id, name, price_monthly'
                  . ($hasPriceYearly ? ', price_yearly' : ', NULL AS price_yearly')
                  . ($hasIsActive ? ', is_active' : ', 1 AS is_active')
                  . ' FROM plans WHERE plan_id = ? LIMIT 1';

                $stP = $pdoCheckout->prepare($sqlPlan);
                $stP->execute([$checkoutPlanId]);
                $prow = $stP->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($prow && (int)($prow['is_active'] ?? 1) === 1) {
                  $checkoutPlan = [
                    'plan_id' => (int)$prow['plan_id'],
                    'name' => (string)($prow['name'] ?? 'Plano'),
                    'price_monthly' => (float)($prow['price_monthly'] ?? 0),
                    'price_yearly' => ($prow['price_yearly'] !== null && $prow['price_yearly'] !== '') ? (float)$prow['price_yearly'] : null,
                  ];
                } else {
                  $checkoutError = 'Plano não encontrado ou inativo.';
                }
              } else {
                $checkoutError = 'Selecione um plano para continuar.';
              }
            } catch (Throwable $e) {
              if (function_exists('error_log')) {
                error_log('[checkout] Falha ao carregar dados do checkout: ' . $e->getMessage());
              }
              $checkoutError = 'Não foi possível carregar os dados do checkout: ' . $e->getMessage();
            }

            $displayAmount = 0.0;
            $periodLabel = ($checkoutBilling === 'yearly') ? 'ano' : 'mês';
            if ($checkoutPlan) {
              $displayAmount = ($checkoutBilling === 'yearly')
                ? (float)(($checkoutPlan['price_yearly'] !== null) ? $checkoutPlan['price_yearly'] : ($checkoutPlan['price_monthly'] * 10))
                : (float)$checkoutPlan['price_monthly'];
            }

            // Descobre quais meios de pagamento estão habilitados (para evitar escolher um método sem gateway)
            $pmEnabled = ['card' => true, 'pix' => true, 'boleto' => true];
            $pmDefault = 'card';
            try {
              $gatewayTenantId = 1;
              try {
                $stmtG = $pdoCheckout->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
                if ($stmtG) {
                  $tmp = (int)$stmtG->fetchColumn();
                  if ($tmp > 0) $gatewayTenantId = $tmp;
                }
              } catch (Throwable $eG) {
                $gatewayTenantId = 1;
              }

              $stmt = $pdoCheckout->prepare("SELECT gateway FROM saas_payment_gateways WHERE tenant_id = :tid AND is_enabled = 1");
              $stmt->execute([':tid' => $gatewayTenantId]);
              $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
              $rows = array_map(fn($v) => strtolower(trim((string)$v)), $rows);

              $hasStripe = in_array('stripe', $rows, true);
              $hasAsaas = in_array('asaas', $rows, true);
              $hasMp = in_array('mercado_pago', $rows, true);
              $hasPixManual = in_array('pix_manual', $rows, true);

              // Cartão pode ser Stripe / Asaas / Mercado Pago
              $pmEnabled['card'] = ($hasStripe || $hasAsaas || $hasMp);
              $pmEnabled['pix'] = ($hasAsaas || $hasMp || $hasPixManual);
              $pmEnabled['boleto'] = ($hasAsaas || $hasMp);

              if ($pmEnabled['card']) $pmDefault = 'card';
              elseif ($pmEnabled['pix']) $pmDefault = 'pix';
              elseif ($pmEnabled['boleto']) $pmDefault = 'boleto';
            } catch (Throwable $ePm) {
              // fallback permissivo
            }
          ?>

          <div id="checkout-form-section">
          <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
              <h5 class="card-title mb-0"><i class="bi bi-lock me-2"></i>Finalizar Assinatura</h5>
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo root_url(); ?>conta/planos">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Planos
              </a>
            </div>
            <div class="card-body">
              <?php if ($checkoutError): ?>
                <div class="alert alert-danger mb-0">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  <?php echo htmlspecialchars((string)$checkoutError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              <?php else: ?>
                <div class="row g-4">
                  <div class="col-lg-5">
                    <div class="p-3 border rounded bg-light">
                      <div class="text-uppercase text-muted small">Resumo</div>
                      <div class="h4 mb-1"><?php echo htmlspecialchars($checkoutPlan['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="h2 mb-1 text-primary">R$ <?php echo number_format($displayAmount, 2, ',', '.'); ?><small class="text-muted">/<?php echo $periodLabel; ?></small></div>
                      <div class="small text-muted">
                        Cobrança <?php echo $checkoutBilling === 'yearly' ? 'anual' : 'mensal'; ?>.
                      </div>
                    </div>

                    <div class="mt-3">
                      <div class="text-uppercase text-muted small mb-2">Método de pagamento</div>

                      <?php if (!$pmEnabled['card'] && !$pmEnabled['pix'] && !$pmEnabled['boleto']): ?>
                        <div class="alert alert-warning small">
                          <i class="bi bi-exclamation-triangle me-1"></i>
                          Nenhum gateway de pagamento está habilitado. Ative pelo menos um em <code>saas_payment_gateways</code>.
                        </div>
                      <?php endif; ?>

                      <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="pm-card" value="card"
                          <?php echo ($pmDefault === 'card') ? 'checked' : ''; ?>
                          <?php echo !$pmEnabled['card'] ? 'disabled' : ''; ?>
                        >
                        <label class="form-check-label" for="pm-card">
                          <i class="bi bi-credit-card me-1"></i> Cartão
                          <?php if (!$pmEnabled['card']): ?><span class="text-muted">(indisponível)</span><?php endif; ?>
                        </label>
                      </div>
                      <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="pm-pix" value="pix"
                          <?php echo ($pmDefault === 'pix') ? 'checked' : ''; ?>
                          <?php echo !$pmEnabled['pix'] ? 'disabled' : ''; ?>
                        >
                        <label class="form-check-label" for="pm-pix">
                          <i class="bi bi-qr-code me-1"></i> Pix
                          <?php if (!$pmEnabled['pix']): ?><span class="text-muted">(indisponível)</span><?php endif; ?>
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pm-boleto" value="boleto"
                          <?php echo ($pmDefault === 'boleto') ? 'checked' : ''; ?>
                          <?php echo !$pmEnabled['boleto'] ? 'disabled' : ''; ?>
                        >
                        <label class="form-check-label" for="pm-boleto">
                          <i class="bi bi-upc me-1"></i> Boleto
                          <?php if (!$pmEnabled['boleto']): ?><span class="text-muted">(indisponível)</span><?php endif; ?>
                        </label>
                      </div>

                      <div class="alert alert-info mt-3 mb-0 small" id="checkout-redirect-info">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="checkout-info-text">Ao continuar, você será redirecionado para o pagamento seguro.</span>
                      </div>
                    </div>
                  </div>

                  <div class="col-lg-7">
                    <div id="checkout-inline-error" class="alert alert-danger d-none mb-0"></div>

                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" id="checkout-name" value="<?php echo htmlspecialchars($prefill['name'], ENT_QUOTES, 'UTF-8'); ?>" />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="checkout-email" value="<?php echo htmlspecialchars($prefill['email'], ENT_QUOTES, 'UTF-8'); ?>" />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">CPF/CNPJ</label>
                        <input type="text" class="form-control" id="checkout-document" inputmode="numeric" autocomplete="off" placeholder="CPF: 000.000.000-00" value="<?php echo htmlspecialchars($prefill['document'], ENT_QUOTES, 'UTF-8'); ?>" />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="checkout-phone" inputmode="tel" autocomplete="tel" maxlength="15" placeholder="(00) 00000-0000" value="<?php echo htmlspecialchars($prefill['phone'], ENT_QUOTES, 'UTF-8'); ?>" />
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Tipo de pessoa</label>
                        <select class="form-select" id="checkout-person-type">
                          <option value="cpf" <?php echo ($prefill['person_type'] === 'cpf') ? 'selected' : ''; ?>>Pessoa Física (CPF)</option>
                          <option value="cnpj" <?php echo ($prefill['person_type'] === 'cnpj') ? 'selected' : ''; ?>>Pessoa Jurídica (CNPJ)</option>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Empresa (opcional)</label>
                        <input type="text" class="form-control" id="checkout-company" value="<?php echo htmlspecialchars($prefill['company'], ENT_QUOTES, 'UTF-8'); ?>" />
                      </div>

                      <div class="col-12">
                        <button type="button" class="btn btn-primary btn-lg w-100" id="btn-checkout-submit"
                          data-plan-id="<?php echo (int)$checkoutPlan['plan_id']; ?>"
                          data-billing="<?php echo htmlspecialchars($checkoutBilling, ENT_QUOTES, 'UTF-8'); ?>"
                          <?php echo (!$pmEnabled['card'] && !$pmEnabled['pix'] && !$pmEnabled['boleto']) ? 'disabled' : ''; ?>
                        >
                          <i class="bi bi-lock me-1"></i> Prosseguir para pagamento
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <script>
                  (function(){
                    const btn = document.getElementById('btn-checkout-submit');
                    if (!btn) return;

                    // Estado do checkout (para cartão via modal: Asaas / Mercado Pago)
                    let modalPaymentData = null;

                    function getSelectedPaymentMethod(){
                      const el = document.querySelector('input[name="payment_method"]:checked');
                      return el ? el.value : 'card';
                    }

                    function showError(msg){
                      const box = document.getElementById('checkout-inline-error');
                      if (box) {
                        box.textContent = msg || 'Não foi possível iniciar o pagamento.';
                        box.classList.remove('d-none');
                      }

                      if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Não foi possível iniciar o pagamento', text: msg || 'Tente novamente.' });
                      } else {
                        alert(msg || 'Erro ao iniciar o pagamento');
                      }
                    }

                    // Modal do cartão 3D (checkout transparente para Asaas / Mercado Pago)
                    let cardModalEl = null;
                    let cardModal = null;

                    // Mercado Pago: tokenização via SDK JS v2 (CardForm)
                    let mp = null;
                    let mpCardForm = null;
                    let mpMountedForOrderId = null;

                    function formatNumberBRL(value) {
                      const v = Number(value || 0);
                      try {
                        return new Intl.NumberFormat('pt-BR', {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        }).format(v);
                      } catch (e) {
                        return String(v.toFixed(2)).replace('.', ',');
                      }
                    }

                    // ==========================
                    // Máscaras: CPF/CNPJ e Telefone (BR)
                    // ==========================
                    function onlyDigits(v){ return String(v || '').replace(/\D+/g, ''); }
                    function formatCPF(v){
                      let d = onlyDigits(v).slice(0, 11);
                      if (d.length > 9) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
                      if (d.length > 6) return d.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
                      if (d.length > 3) return d.replace(/(\d{3})(\d{0,3})/, '$1.$2');
                      return d;
                    }
                    function formatCNPJ(v){
                      let d = onlyDigits(v).slice(0, 14);
                      if (d.length > 12) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
                      if (d.length > 8) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
                      if (d.length > 5) return d.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
                      if (d.length > 2) return d.replace(/(\d{2})(\d{0,3})/, '$1.$2');
                      return d;
                    }
                    function formatPhoneBR(v){
                      let d = onlyDigits(v).slice(0, 11);
                      if (d.length > 10) return d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                      if (d.length > 6) return d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                      if (d.length > 2) return d.replace(/(\d{2})(\d{0,5})/, '($1) $2');
                      return d ? '(' + d : '';
                    }
                    function applyDocumentMask(){
                      const sel = document.getElementById('checkout-person-type');
                      const input = document.getElementById('checkout-document');
                      if (!input || !sel) return;
                      const type = (sel.value || 'cpf').toLowerCase();
                      const digits = onlyDigits(input.value);
                      input.maxLength = type === 'cnpj' ? 18 : 14;
                      input.placeholder = type === 'cnpj' ? 'CNPJ: 00.000.000/0000-00' : 'CPF: 000.000.000-00';
                      input.value = (type === 'cnpj') ? formatCNPJ(digits) : formatCPF(digits);
                    }
                    function initMasks(){
                      const phoneInput = document.getElementById('checkout-phone');
                      if (phoneInput) {
                        phoneInput.maxLength = 15;
                        phoneInput.addEventListener('input', () => { phoneInput.value = formatPhoneBR(phoneInput.value); });
                        phoneInput.value = formatPhoneBR(phoneInput.value);
                      }
                      const docInput = document.getElementById('checkout-document');
                      const personSel = document.getElementById('checkout-person-type');
                      if (docInput) {
                        docInput.addEventListener('input', applyDocumentMask);
                        applyDocumentMask();
                      }
                      if (personSel) {
                        personSel.addEventListener('change', applyDocumentMask);
                      }
                    }

                    function loadScriptOnce(src) {
                      if (!src) return Promise.reject(new Error('src inválido'));

                      // cache por src
                      window.__finxLoadedScripts = window.__finxLoadedScripts || {};
                      if (window.__finxLoadedScripts[src]) {
                        return window.__finxLoadedScripts[src];
                      }

                      window.__finxLoadedScripts[src] = new Promise((resolve, reject) => {
                        const el = document.createElement('script');
                        el.src = src;
                        el.async = true;
                        el.onload = () => resolve(true);
                        el.onerror = () => reject(new Error('Falha ao carregar script: ' + src));
                        document.head.appendChild(el);
                      });

                      return window.__finxLoadedScripts[src];
                    }

                    function resetMercadoPago() {
                      mp = null;
                      mpCardForm = null;
                      mpMountedForOrderId = null;
                    }

                    async function ensureMercadoPagoCardForm() {
                      if (!modalPaymentData || modalPaymentData.gateway_code !== 'mercado_pago') {
                        return true;
                      }

                      const publicKey = String(modalPaymentData.mp_public_key || '');
                      if (!publicKey) {
                        throw new Error('Chave pública do Mercado Pago não disponível.');
                      }

                      if (typeof window.MercadoPago === 'undefined') {
                        await loadScriptOnce('https://sdk.mercadopago.com/js/v2');
                      }

                      if (!mp) {
                        mp = new window.MercadoPago(publicKey, { locale: 'pt-BR' });
                      }

                      if (mpMountedForOrderId === modalPaymentData.order_id && mpCardForm) {
                        return true;
                      }

                      mpMountedForOrderId = modalPaymentData.order_id;

                      const amount = Number(modalPaymentData.amount || 0);
                      const amountStr = isFinite(amount) ? amount.toFixed(2) : '0.00';

                      try {
                        mpCardForm = mp.cardForm({
                          amount: amountStr,
                          autoMount: true,
                          form: {
                            id: 'card3d-payment-form',
                            cardNumber: { id: 'card-number', placeholder: '0000 0000 0000 0000' },
                            expirationDate: { id: 'card-expiry', placeholder: 'MM/AA' },
                            securityCode: { id: 'card-cvv', placeholder: '***' },
                            cardholderName: { id: 'card-name', placeholder: 'Como aparece no cartão' },
                            installments: { id: 'installments' },
                            issuer: { id: 'issuer' },
                            cardholderEmail: { id: 'checkout-email' },
                          },
                          callbacks: {
                            onFormMounted: function (error) {
                              if (error) {
                                console.error('[checkout] Mercado Pago onFormMounted error', error);
                              }
                            },
                            onSubmit: function () {
                              // submit é controlado manualmente
                            },
                            onError: function (error) {
                              // evita poluir console com erros esperados enquanto digita
                              try {
                                if (Array.isArray(error)) {
                                  const relevantes = error.filter((e) => e && e.message && !/can not be null\/empty/i.test(e.message));
                                  if (!relevantes.length) return;
                                  console.error('[checkout] Mercado Pago cardForm error', relevantes);
                                  return;
                                }
                              } catch (e) {
                                // ignore
                              }
                              console.error('[checkout] Mercado Pago cardForm error', error);
                            }
                          }
                        });
                      } catch (e) {
                        console.error('[checkout] Falha ao inicializar Mercado Pago JS v2', e);
                        resetMercadoPago();
                        throw new Error('Não foi possível carregar o formulário do Mercado Pago.');
                      }

                      return true;
                    }

                    function updateCardModalSummary() {
                      const amount = (modalPaymentData && modalPaymentData.amount) ? Number(modalPaymentData.amount) : 0;
                      const planName = (modalPaymentData && modalPaymentData.plan_name) ? String(modalPaymentData.plan_name) : '';

                      const planNameEl = document.getElementById('card-plan-name');
                      const totalEl = document.getElementById('card-total');
                      const btnAmt = document.getElementById('btn-pay-amount');

                      if (planNameEl && planName) {
                        planNameEl.textContent = planName;
                      }

                      const amountLabel = formatNumberBRL(amount);
                      if (totalEl) totalEl.textContent = amountLabel;
                      if (btnAmt) btnAmt.textContent = amountLabel;
                    }

                    function openCardModal() {
                      cardModalEl = cardModalEl || document.getElementById('card3dModal');
                      if (!cardModalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                        showError('Checkout transparente está ativo, mas não foi possível abrir a janela do cartão. Atualize a página e tente novamente.');
                        return false;
                      }

                      if (!cardModal) {
                        cardModal = new bootstrap.Modal(cardModalEl, { backdrop: 'static', keyboard: false });

                        cardModalEl.addEventListener('shown.bs.modal', () => {
                          // Garante que o listener do botão Pagar está registrado
                          setupPayButtonListener();
                          
                          // Inicializa o cartão 3D (apenas UI)
                          if (window.Card3D && window.Card3D.init) {
                            window.Card3D.init();
                          }

                          // Mercado Pago: inicializa CardForm/tokenização quando necessário
                          if (modalPaymentData && modalPaymentData.gateway_code === 'mercado_pago') {
                            ensureMercadoPagoCardForm().catch((e) => {
                              const errorBox = document.getElementById('card-error-message');
                              if (errorBox) {
                                errorBox.textContent = (e && e.message) ? e.message : 'Não foi possível carregar o Mercado Pago.';
                                errorBox.classList.remove('d-none');
                              }
                            });
                          } else {
                            resetMercadoPago();
                          }

                          // Atualiza preview (nome/número/validade/cvv)
                          ['card-name', 'card-number', 'card-expiry', 'card-cvv'].forEach((id) => {
                            const el = document.getElementById(id);
                            if (el) {
                              el.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                          });
                        });

                        cardModalEl.addEventListener('hidden.bs.modal', () => {
                          // Limpa estado do MP entre tentativas
                          resetMercadoPago();
                        });
                      }

                      // Atualiza valores do resumo
                      updateCardModalSummary();

                      // Prefill do titular
                      const cardNameInput = document.getElementById('card-name');
                      if (cardNameInput && modalPaymentData && modalPaymentData.customer && modalPaymentData.customer.name) {
                        cardNameInput.value = String(modalPaymentData.customer.name).toUpperCase();
                        cardNameInput.dispatchEvent(new Event('input', { bubbles: true }));
                      }

                      // Limpa erro
                      const errorBox = document.getElementById('card-error-message');
                      if (errorBox) {
                        errorBox.classList.add('d-none');
                        errorBox.textContent = '';
                      }

                      cardModal.show();
                      return true;
                    }

                    function redirectToPlansConfirmed(orderId) {
                      const base = (window.PLANS_CONFIG && window.PLANS_CONFIG.rootUrl) ? window.PLANS_CONFIG.rootUrl : '/';
                      const params = new URLSearchParams();
                      params.set('payment_confirmed', '1');
                      if (orderId) params.set('order_id', String(orderId));
                      window.location.href = base + 'conta/planos?' + params.toString();
                    }

                    // Texto informativo (redirect vs modal)
                    function updateCheckoutInfoText() {
                      const paymentMethod = getSelectedPaymentMethod();
                      const infoText = document.getElementById('checkout-info-text');
                      if (!infoText) return;

                      if (paymentMethod === 'card') {
                        infoText.textContent = 'Ao continuar, abriremos uma janela segura para você informar os dados do cartão.';
                      } else {
                        infoText.textContent = 'Ao continuar, você será redirecionado para o pagamento seguro.';
                      }
                    }
                    document.querySelectorAll('input[name="payment_method"]').forEach((el) => {
                      el.addEventListener('change', updateCheckoutInfoText);
                    });
                    updateCheckoutInfoText();

                    // Inicializa máscaras de telefone e CPF/CNPJ
                    initMasks();

                    // Botão Pagar (no modal do cartão) - Processa via AJAX (Asaas / Mercado Pago)
                    // NOTA: O listener é registrado no evento shown.bs.modal para garantir que o botão existe
                    function setupPayButtonListener() {
                      const btnPay = document.getElementById('btn-process-payment');
                      if (!btnPay || btnPay.dataset.listenerAttached) return;
                      btnPay.dataset.listenerAttached = 'true';
                      
                      btnPay.addEventListener('click', async function() {
                        if (!modalPaymentData || !modalPaymentData.order_id || !modalPaymentData.gateway_code) {
                          const errorBox = document.getElementById('card-error-message');
                          if (errorBox) {
                            errorBox.textContent = 'Dados do pagamento não disponíveis. Feche a janela e tente novamente.';
                            errorBox.classList.remove('d-none');
                          }
                          return;
                        }

                        const errorBox = document.getElementById('card-error-message');
                        if (errorBox) {
                          errorBox.classList.add('d-none');
                          errorBox.textContent = '';
                        }

                        btnPay.disabled = true;
                        const oldHtml = btnPay.innerHTML;
                        btnPay.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processando...';

                        if (window.Card3D && window.Card3D.showProcessing) {
                          window.Card3D.showProcessing(true);
                        }

                        try {
                          const apiBase = (window.PLANS_CONFIG && window.PLANS_CONFIG.apiBase) ? window.PLANS_CONFIG.apiBase : '<?php echo root_url(); ?>conta/_ajax/';

                          const requestBody = {
                            order_id: modalPaymentData.order_id,
                            gateway_code: modalPaymentData.gateway_code,
                            customer: modalPaymentData.customer || {}
                          };

                          if (modalPaymentData.gateway_code === 'mercado_pago') {
                            await ensureMercadoPagoCardForm();

                            const cardData = mpCardForm ? mpCardForm.getCardFormData() : null;
                            if (!cardData || !cardData.token) {
                              throw new Error('Não foi possível gerar o token do cartão no Mercado Pago.');
                            }

                            requestBody.mp = {
                              token: cardData.token,
                              installments: cardData.installments || '1',
                              payment_method_id: cardData.paymentMethodId || '',
                              issuer_id: cardData.issuerId || ''
                            };
                          } else {
                            // Asaas
                            const cardName = (document.getElementById('card-name')?.value || '').trim();
                            const cardNumber = (document.getElementById('card-number')?.value || '').trim();
                            const cardExpiry = (document.getElementById('card-expiry')?.value || '').trim();
                            const cardCvc = (document.getElementById('card-cvv')?.value || '').trim();

                            if (cardName.length < 3) throw new Error('Nome no cartão obrigatório');
                            if (cardNumber.replace(/\D/g, '').length < 13) throw new Error('Número do cartão inválido');
                            if (!cardExpiry) throw new Error('Validade do cartão obrigatória');
                            if (cardCvc.replace(/\D/g, '').length < 3) throw new Error('CVC inválido');

                            requestBody.card = {
                              name: cardName,
                              number: cardNumber,
                              expiry: cardExpiry,
                              cvc: cardCvc,
                            };
                          }

                          const res = await fetch(apiBase + 'process_card_modal_payment.php', {
                            method: 'POST',
                            headers: {
                              'Content-Type': 'application/json',
                              'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify(requestBody)
                          });

                          const data = await res.json().catch(() => null);
                          if (!data || !data.success) {
                            throw new Error((data && data.message) ? data.message : 'Pagamento não foi confirmado.');
                          }

                          redirectToPlansConfirmed(modalPaymentData.order_id);

                        } catch (e) {
                          if (errorBox) {
                            errorBox.textContent = (e && e.message) ? e.message : 'Erro ao processar pagamento';
                            errorBox.classList.remove('d-none');
                          }
                        } finally {
                          if (window.Card3D && window.Card3D.showProcessing) {
                            window.Card3D.showProcessing(false);
                          }
                          btnPay.disabled = false;
                          btnPay.innerHTML = oldHtml;
                        }
                      });
                    }
                    
                    // Registra o listener quando o DOM estiver pronto ou quando o modal abrir
                    if (document.readyState === 'loading') {
                      document.addEventListener('DOMContentLoaded', setupPayButtonListener);
                    } else {
                      // Tenta registrar imediatamente, e também no show do modal como fallback
                      setTimeout(setupPayButtonListener, 0);
                    }

                    // Botão principal do checkout
                    btn.addEventListener('click', async function(){
                      const planId = parseInt(btn.dataset.planId || '0', 10);
                      const billing = btn.dataset.billing || 'monthly';
                      const paymentMethod = getSelectedPaymentMethod();

                      const box = document.getElementById('checkout-inline-error');
                      if (box) {
                        box.classList.add('d-none');
                        box.textContent = '';
                      }

                      btn.disabled = true;
                      const oldHtml = btn.innerHTML;
                      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Iniciando...';

                      try {
                        const payload = {
                          plan_id: planId,
                          billing: billing,
                          payment_method: paymentMethod,
                          customer: {
                            name: (document.getElementById('checkout-name')?.value || '').trim(),
                            email: (document.getElementById('checkout-email')?.value || '').trim(),
                            document: (document.getElementById('checkout-document')?.value || '').trim(),
                            person_type: (document.getElementById('checkout-person-type')?.value || 'cpf'),
                            phone: (document.getElementById('checkout-phone')?.value || '').trim(),
                            company: (document.getElementById('checkout-company')?.value || '').trim(),
                          }
                        };

                        const apiBase = (window.PLANS_CONFIG && window.PLANS_CONFIG.apiBase) ? window.PLANS_CONFIG.apiBase : '<?php echo root_url(); ?>conta/_ajax/';
                        const res = await fetch(apiBase + 'process_upgrade.php', {
                          method: 'POST',
                          headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                          },
                          body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        
                        // Cartão via modal (Asaas / Mercado Pago)
                        if (data && data.success && data.flow_mode === 'modal' && data.order_id) {
                          modalPaymentData = {
                            order_id: data.order_id,
                            gateway_code: data.gateway_code || '',
                            mp_public_key: data.mp_public_key || '',
                            amount: data.amount,
                            plan_name: data.plan_name || '',
                            customer: payload.customer || {}
                          };

                          const opened = openCardModal();
                          if (!opened) {
                            showError('Não foi possível abrir a janela segura de pagamento com cartão. Atualize a página e tente novamente.');
                          }
                          return;
                        }

                        // Modo redirect (Stripe transparente / checkout / Pix / Boleto etc)
                        if (data && data.success && data.redirect_url) {
                          window.location.href = data.redirect_url;
                          return;
                        }

                        showError((data && (data.message || data.error)) ? (data.message || data.error) : 'Resposta inválida do servidor.');
                      } catch (e) {
                        console.error('[checkout] erro:', e);
                        showError(e && e.message ? e.message : 'Erro desconhecido.');
                      } finally {
                        btn.disabled = false;
                        btn.innerHTML = oldHtml;
                      }
                    });
                  })();
                </script>
              <?php endif; ?>
            </div>
          </div>
          </div><!-- /checkout-form-section -->

          <!-- Modal: Checkout Transparente - Cartão (reutiliza Cartão 3D) -->
          <div class="modal fade card3d-modal" id="card3dModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header card3d-modal-header">
                  <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-shield-lock me-2"></i>Pagamento seguro com cartão</h5>
                    <div class="small opacity-75">Preencha os dados do cartão para concluir o pagamento.</div>
                  </div>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-3 p-lg-4">
                  <div class="transparent-checkout-section active" id="card-checkout-section">
                    <div class="row g-3">
                      <!-- Cartão 3D Visual -->
                      <div class="col-12">
                        <div class="creditcard-container preload">
                      <div class="creditcard">
                        <div class="front">
                          <div id="ccsingle"></div>
                          <svg version="1.1" id="cardfront" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" viewBox="0 0 750 471" style="enable-background:new 0 0 750 471;" xml:space="preserve">
                            <g id="Front">
                              <g id="CardBackground">
                                <g id="Page-1_1_">
                                  <g id="amex_1_">
                                    <path id="Rectangle-1_1_" class="lightcolor grey" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40C0,17.9,17.9,0,40,0z" />
                                  </g>
                                </g>
                                <path class="darkcolor greydark" d="M750,431V193.2c-217.6-57.5-556.4-13.5-750,24.9V431c0,22.1,17.9,40,40,40h670C732.1,471,750,453.1,750,431z" />
                              </g>
                              <text transform="matrix(1 0 0 1 60.106 295.0121)" id="svgnumber" class="st2 st3 st4">0123 4567 8910 1112</text>
                              <text transform="matrix(1 0 0 1 54.1064 428.1723)" id="svgname" class="st2 st5 st6">NOME NO CARTÃO</text>
                              <text transform="matrix(1 0 0 1 54.1074 389.8793)" class="st7 st5 st8">titular do cartão</text>
                              <text transform="matrix(1 0 0 1 479.7754 388.8793)" class="st7 st5 st8">validade</text>
                              <text transform="matrix(1 0 0 1 65.1054 241.5)" class="st7 st5 st8">número do cartão</text>
                              <g>
                                <text transform="matrix(1 0 0 1 574.4219 433.8095)" id="svgexpire" class="st2 st5 st9">MM/AA</text>
                                <text transform="matrix(1 0 0 1 479.3848 417.0097)" class="st2 st10 st11">VALID</text>
                                <text transform="matrix(1 0 0 1 479.3848 435.6762)" class="st2 st10 st11">THRU</text>
                                <polygon class="st2" points="554.5,421 540.4,414.2 540.4,427.9" />
                              </g>
                              <g id="cchip">
                                <g>
                                  <path class="st2" d="M168.1,143.6H82.9c-10.2,0-18.5-8.3-18.5-18.5V74.9c0-10.2,8.3-18.5,18.5-18.5h85.3c10.2,0,18.5,8.3,18.5,18.5v50.2C186.6,135.3,178.3,143.6,168.1,143.6z" />
                                </g>
                                <g>
                                  <g><rect x="82" y="70" class="st12" width="1.5" height="60" /></g>
                                  <g><rect x="167.4" y="70" class="st12" width="1.5" height="60" /></g>
                                  <g>
                                    <path class="st12" d="M125.5,130.8c-10.2,0-18.5-8.3-18.5-18.5c0-4.6,1.7-8.9,4.7-12.3c-3-3.4-4.7-7.7-4.7-12.3c0-10.2,8.3-18.5,18.5-18.5s18.5,8.3,18.5,18.5c0,4.6-1.7,8.9-4.7,12.3c3,3.4,4.7,7.7,4.7,12.3C143.9,122.5,135.7,130.8,125.5,130.8z M125.5,70.8c-9.3,0-16.9,7.6-16.9,16.9c0,4.4,1.7,8.6,4.8,11.8l0.5,0.5l-0.5,0.5c-3.1,3.2-4.8,7.4-4.8,11.8c0,9.3,7.6,16.9,16.9,16.9s16.9-7.6,16.9-16.9c0-4.4-1.7-8.6-4.8-11.8l-0.5-0.5l0.5-0.5c3.1-3.2,4.8-7.4,4.8-11.8C142.4,78.4,134.8,70.8,125.5,70.8z" />
                                  </g>
                                  <g><rect x="82.8" y="82.1" class="st12" width="25.8" height="1.5" /></g>
                                  <g><rect x="82.8" y="117.9" class="st12" width="26.1" height="1.5" /></g>
                                  <g><rect x="142.4" y="82.1" class="st12" width="25.8" height="1.5" /></g>
                                  <g><rect x="142" y="117.9" class="st12" width="26.2" height="1.5" /></g>
                                </g>
                              </g>
                            </g>
                          </svg>
                        </div><!-- /front -->
                        <div class="back">
                          <svg version="1.1" id="cardback" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" viewBox="0 0 750 471" style="enable-background:new 0 0 750 471;" xml:space="preserve">
                            <g id="Front"><line class="st0" x1="35.3" y1="10.4" x2="36.7" y2="11" /></g>
                            <g id="Back">
                              <g id="Page-1_2_">
                                <g id="amex_2_">
                                  <path id="Rectangle-1_2_" class="darkcolor greydark" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40C0,17.9,17.9,0,40,0z" />
                                </g>
                              </g>
                              <rect y="61.6" class="st2" width="750" height="78" />
                              <g>
                                <path class="st3" d="M701.1,249.1H48.9c-3.3,0-6-2.7-6-6v-52.5c0-3.3,2.7-6,6-6h652.1c3.3,0,6,2.7,6,6v52.5C707.1,246.4,704.4,249.1,701.1,249.1z" />
                                <rect x="42.9" y="198.6" class="st4" width="664.1" height="10.5" />
                                <rect x="42.9" y="224.5" class="st4" width="664.1" height="10.5" />
                                <path class="st5" d="M701.1,184.6H618h-8h-10v64.5h10h8h83.1c3.3,0,6-2.7,6-6v-52.5C707.1,187.3,704.4,184.6,701.1,184.6z" />
                              </g>
                              <text transform="matrix(1 0 0 1 621.999 227.2734)" id="svgsecurity" class="st6 st7">***</text>
                              <g class="st8"><text transform="matrix(1 0 0 1 518.083 280.0879)" class="st9 st6 st10">código de segurança</text></g>
                              <rect x="58.1" y="378.6" class="st11" width="375.5" height="13.5" />
                              <rect x="58.1" y="405.6" class="st11" width="421.7" height="13.5" />
                              <text transform="matrix(1 0 0 1 59.5073 228.6099)" id="svgnameback" class="st12 st13">NOME NO CARTÃO</text>
                            </g>
                          </svg>
                        </div>
                      </div><!-- /creditcard -->
                    </div><!-- /creditcard-container -->
                      </div>

                      <!-- Formulário de Cartão -->
                      <div class="col-12">
                    <div class="payment-title text-center">
                      <h4>Dados do Cartão</h4>
                      <p>Preencha com segurança. O processamento será feito pelo gateway selecionado.</p>
                    </div>

                    <form id="card3d-payment-form" onsubmit="return false;">
                      <div class="card-form-container">
                        <div class="field-container">
                          <label for="card-name">Nome no Cartão</label>
                          <input id="card-name" type="text" maxlength="25" placeholder="Como está no cartão" autocomplete="cc-name">
                        </div>

                        <div class="field-container has-icon">
                          <label for="card-number">Número do Cartão</label>
                          <input id="card-number" type="text" inputmode="numeric" placeholder="0000 0000 0000 0000" autocomplete="cc-number">
                          <div class="ccicon-container" id="ccicon"></div>
                        </div>

                        <div class="card-form-row">
                          <div class="field-container">
                            <label for="card-expiry">Validade</label>
                            <input id="card-expiry" type="text" inputmode="numeric" placeholder="MM/AA" autocomplete="cc-exp">
                          </div>
                          <div class="field-container">
                            <label for="card-cvv">CVC</label>
                            <input id="card-cvv" type="text" inputmode="numeric" placeholder="***" autocomplete="cc-csc">
                          </div>
                        </div>

                        <!-- Recibo (Plano / Total / Segurança) -->
                        <div class="receipt-box" aria-label="Resumo do pagamento">
                          <div class="receipt-row">
                            <div class="receipt-label">Plano</div>
                            <div class="receipt-value" id="card-plan-name"><?php echo htmlspecialchars($checkoutPlan['name'] ?? 'Plano', ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                          <div class="receipt-divider"></div>
                          <div class="receipt-row receipt-total">
                            <div class="receipt-label">Total</div>
                            <div class="receipt-value">R$ <span id="card-total"><?php echo number_format($displayAmount, 2, ',', '.'); ?></span></div>
                          </div>
                          <div class="security-info">
                            <i class="bi bi-shield-lock"></i>
                            <span>Pagamento 100% seguro</span>
                          </div>
                        </div>

                        <!-- Mercado Pago CardForm v2 exige issuer/installments (podem ficar ocultos) -->
                        <select id="issuer" name="issuer" style="display:none;"></select>
                        <select id="installments" name="installments" style="display:none;"></select>

                        <div id="card-error-message" class="alert alert-danger d-none mt-3"></div>

                        <div class="card-actions">
                          <button type="button" class="btn btn-pay" id="btn-process-payment">
                            <i class="bi bi-lock me-1"></i> Pagar R$ <span id="btn-pay-amount"><?php echo number_format($displayAmount, 2, ',', '.'); ?></span>
                          </button>
                        </div>
                      </div><!-- /card-form-container -->
                    </form>
                  </div><!-- /col -->
                </div><!-- /row -->
                  </div><!-- /transparent-checkout-section -->
                </div><!-- /modal-body -->
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                  <div class="text-muted small d-flex align-items-center gap-2">
                    <i class="bi bi-lock-fill"></i>
                    <span>Seus dados são criptografados e processados com segurança.</span>
                  </div>
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancelar
                  </button>
                </div>
              </div><!-- /modal-content -->
            </div><!-- /modal-dialog -->
          </div><!-- /modal -->
          
          <!-- Overlay de processamento -->
          <div class="processing-overlay" id="processing-overlay">
            <div class="spinner"></div>
            <div class="message">Processando pagamento...</div>
            <div class="submessage">Por favor, aguarde</div>
          </div>

        <?php elseif ($tab === 'payment'): ?>
          <?php
            $paymentOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
            $gatewayParam = isset($_GET['gateway']) ? strtolower(trim((string)$_GET['gateway'])) : '';

            $paymentError = null;
            $order = null;
            $pixQrBase64 = null;
            $pixQrText = null;
            $boletoUrl = null;
            $boletoBarcode = null;

            // Helpers locais
            $resolveTenantIdForPage = function(PDO $pdo): int {
              $tid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
              if ($tid > 0) return $tid;
              $uid = function_exists('user_id') ? (int)user_id() : 0;
              if ($uid > 0) {
                $st = $pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
                $st->execute([$uid]);
                $tid = (int)$st->fetchColumn();
              }
              return $tid;
            };

            $findGatewayTenantId = function(PDO $pdo): int {
              $tid = 1;
              try {
                $stmt = $pdo->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
                if ($stmt) {
                  $tmp = (int)$stmt->fetchColumn();
                  if ($tmp > 0) $tid = $tmp;
                }
              } catch (Throwable $e) {
                // ignore
              }
              return $tid;
            };

            // QR code lib (mesma do /saas/landing/pix.php)
            $qrLibAvailable = false;
            $qrLibAutoload = __DIR__ . '/../../../saas/PIX/vendor/autoload.php';
            if (file_exists($qrLibAutoload)) {
              require_once $qrLibAutoload;
              $qrLibAvailable = true;
            }

            $buildPixQrBase64 = function(string $data, int $size = 300) use (&$qrLibAvailable): ?string {
              if (!$qrLibAvailable || $data === '') return null;
              try {
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $qrCode = new \Endroid\QrCode\QrCode(
                  data: $data,
                  encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                  errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Low,
                  size: $size,
                  margin: 10,
                  roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin
                );
                $result = $writer->write($qrCode);
                return base64_encode($result->getString());
              } catch (Throwable $e) {
                return null;
              }
            };

            $pixEmvField = function(string $id, string $value): string {
              $len = strlen($value);
              return $id . sprintf('%02d', $len) . $value;
            };

            $pixCrc16 = function(string $payload): string {
              $poly = 0x1021;
              $crc  = 0xFFFF;
              $len = strlen($payload);
              for ($i = 0; $i < $len; $i++) {
                $crc ^= (ord($payload[$i]) << 8);
                for ($b = 0; $b < 8; $b++) {
                  if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ $poly) & 0xFFFF;
                  } else {
                    $crc = ($crc << 1) & 0xFFFF;
                  }
                }
              }
              return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
            };

            $buildStaticPixEmvPayload = function(string $pixKey, float $amount = 0.0, string $merchantName = 'PIX MANUAL', string $merchantCity = 'BRASILIA', string $tid = '') use ($pixEmvField, $pixCrc16): string {
              $pixKey = trim($pixKey);
              if ($pixKey === '') return '';

              $gui = $pixEmvField('00', 'br.gov.bcb.pix');
              $key = $pixEmvField('01', $pixKey);
              $mai = $pixEmvField('26', $gui . $key);

              $amountField = '';
              if ($amount > 0) {
                $amountField = $pixEmvField('54', number_format($amount, 2, '.', ''));
              }

              $merchantName = strtoupper(substr($merchantName !== '' ? $merchantName : 'PIX MANUAL', 0, 25));
              $merchantCity = strtoupper(substr($merchantCity !== '' ? $merchantCity : 'BRASILIA', 0, 15));

              $addData = '';
              if ($tid !== '') {
                $ref = $pixEmvField('05', substr($tid, 0, 25));
                $addData = $pixEmvField('62', $ref);
              }

              $payload  = $pixEmvField('00', '01');
              $payload .= $pixEmvField('01', '11');
              $payload .= $mai;
              $payload .= $pixEmvField('52', '0000');
              $payload .= $pixEmvField('53', '986');
              $payload .= $amountField;
              $payload .= $pixEmvField('58', 'BR');
              $payload .= $pixEmvField('59', $merchantName);
              $payload .= $pixEmvField('60', $merchantCity);
              $payload .= $addData;

              $payloadNoCrc = $payload . '6304';
              $crc = $pixCrc16($payloadNoCrc);
              return $payloadNoCrc . $crc;
            };

            try {
              $pdoPay = db();
              $tenantIdPay = $resolveTenantIdForPage($pdoPay);
              if ($tenantIdPay <= 0) {
                throw new Exception('Tenant não identificado.');
              }

              if ($paymentOrderId <= 0) {
                throw new Exception('order_id inválido.');
              }

              $st = $pdoPay->prepare("SELECT order_id, tenant_id, plan_id, reference_no, amount, payment_method, status, transaction_id, proof_file, created_at, due_date, paid_at FROM saas_orders WHERE order_id = :id LIMIT 1");
              $st->bindValue(':id', $paymentOrderId, PDO::PARAM_INT);
              $st->execute();
              $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;

              if (!$row) {
                throw new Exception('Pedido não encontrado.');
              }
              if ((int)$row['tenant_id'] !== $tenantIdPay) {
                throw new Exception('Acesso negado para este pedido.');
              }

              $order = [
                'order_id' => (int)$row['order_id'],
                'tenant_id' => (int)$row['tenant_id'],
                'plan_id' => (int)$row['plan_id'],
                'reference_no' => (string)($row['reference_no'] ?? ''),
                'amount' => (float)($row['amount'] ?? 0),
                'payment_method' => strtolower((string)($row['payment_method'] ?? '')),
                'status' => strtolower((string)($row['status'] ?? 'pending')),
                'transaction_id' => (string)($row['transaction_id'] ?? ''),
                'proof_file' => (string)($row['proof_file'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'due_date' => (string)($row['due_date'] ?? ''),
                'paid_at' => (string)($row['paid_at'] ?? ''),
              ];

              // Detecta gateway pelo reference_no se não veio por GET
              $ref = $order['reference_no'];
              if ($gatewayParam === '') {
                if (stripos($ref, 'Asaas') === 0) $gatewayParam = 'asaas';
                elseif (stripos($ref, 'MP') === 0) $gatewayParam = 'mercadopago';
                elseif (stripos($ref, 'Pix') === 0) $gatewayParam = 'pix_manual';
                elseif (stripos($ref, 'Stripe') === 0) $gatewayParam = 'stripe';
              }

              $gatewayTenantId = $findGatewayTenantId($pdoPay);

              // Integrações (carrega PaymentGatewayConfig)
              $pgcPath = realpath(__DIR__ . '/../../conta/_inc/PaymentGatewayConfig.php');
              if ($pgcPath && file_exists($pgcPath)) {
                require_once $pgcPath;
              }

              // PIX
              if ($order['payment_method'] === 'pix') {
                if ($gatewayParam === 'pix_manual') {
                  // Busca config do pix manual direto na tabela (preferimos sandbox no ambiente local)
                  $pixRow = null;
                  try {
                    $stmtPix = $pdoPay->prepare("SELECT * FROM saas_payment_gateways WHERE tenant_id = :tenant AND gateway = 'pix_manual' AND environment = 'sandbox' LIMIT 1");
                    $stmtPix->execute([':tenant' => $gatewayTenantId]);
                    $pixRow = $stmtPix->fetch(PDO::FETCH_ASSOC) ?: null;

                    if (!$pixRow) {
                      $stmtPix = $pdoPay->prepare("SELECT * FROM saas_payment_gateways WHERE tenant_id = :tenant AND gateway = 'pix_manual' LIMIT 1");
                      $stmtPix->execute([':tenant' => $gatewayTenantId]);
                      $pixRow = $stmtPix->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                  } catch (Throwable $ePix) {
                    $pixRow = null;
                  }

                  $pixKey = $pixRow['pix_chave'] ?? '';
                  if ($pixRow && !empty($pixRow['is_enabled']) && $pixKey !== '') {
                    $payload = $buildStaticPixEmvPayload($pixKey, (float)$order['amount'], (string)($pixRow['pix_titular'] ?? 'PIX MANUAL'), 'BRASILIA', (string)$order['order_id']);
                    $pixQrText = $payload !== '' ? $payload : $pixKey;
                    $pixQrBase64 = $buildPixQrBase64($pixQrText, 260);
                  } else {
                    $paymentError = 'Pix manual não configurado.';
                  }
                } elseif ($gatewayParam === 'asaas') {
                  $gw = class_exists('PaymentGatewayConfig') ? PaymentGatewayConfig::get($pdoPay, $gatewayTenantId, 'asaas') : null;
                  if ($gw && !empty($gw['enabled']) && !empty($gw['asaas_api_key']) && $order['transaction_id'] !== '') {
                    $apiKey = (string)$gw['asaas_api_key'];
                    $environment = !empty($gw['environment']) ? (string)$gw['environment'] : 'sandbox';
                    $baseUrl = $environment === 'production' ? 'https://api.asaas.com/v3' : 'https://sandbox.asaas.com/api/v3';

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                      CURLOPT_URL => $baseUrl . '/payments/' . urlencode((string)$order['transaction_id']) . '/pixQrCode',
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'access_token: ' . $apiKey,
                        'User-Agent: ModernPOS/1.0',
                      ],
                    ]);

                    $resp = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($resp !== false && $status >= 200 && $status < 300) {
                      $p = json_decode($resp, true);
                      if (is_array($p)) {
                        $pixQrBase64 = $p['encodedImage'] ?? null;
                        $pixQrText = $p['payload'] ?? null;
                        if (!$pixQrBase64 && !empty($pixQrText)) {
                          $pixQrBase64 = $buildPixQrBase64($pixQrText, 260);
                        }
                      }
                    } else {
                      $paymentError = 'Não foi possível carregar o QR Code via Asaas (HTTP ' . $status . ').';
                    }
                  } else {
                    $paymentError = 'Asaas não configurado ou transação inválida.';
                  }
                } else {
                  // Mercado Pago
                  $gw = class_exists('PaymentGatewayConfig') ? PaymentGatewayConfig::get($pdoPay, $gatewayTenantId, 'mercado_pago') : null;
                  if ($gw && !empty($gw['enabled']) && !empty($gw['mp_access_token']) && $order['transaction_id'] !== '') {
                    $accessToken = (string)$gw['mp_access_token'];

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                      CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . urlencode((string)$order['transaction_id']),
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $accessToken,
                      ],
                    ]);

                    $resp = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($resp !== false && $status >= 200 && $status < 300) {
                      $p = json_decode($resp, true);
                      if (is_array($p)) {
                        $txData = $p['point_of_interaction']['transaction_data'] ?? [];
                        $pixQrBase64 = $txData['qr_code_base64'] ?? null;
                        $pixQrText = $txData['qr_code'] ?? null;
                        if (!$pixQrBase64 && !empty($pixQrText)) {
                          $pixQrBase64 = $buildPixQrBase64($pixQrText, 260);
                        }
                      }
                    } else {
                      $paymentError = 'Não foi possível carregar o QR Code via Mercado Pago (HTTP ' . $status . ').';
                    }
                  } else {
                    $paymentError = 'Mercado Pago não configurado ou transação inválida.';
                  }
                }
              }

              // BOLETO
              if ($order['payment_method'] === 'boleto' && $order['transaction_id'] !== '') {
                if ($gatewayParam === 'asaas') {
                  $gw = class_exists('PaymentGatewayConfig') ? PaymentGatewayConfig::get($pdoPay, $gatewayTenantId, 'asaas') : null;
                  if ($gw && !empty($gw['enabled']) && !empty($gw['asaas_api_key'])) {
                    $apiKey = (string)$gw['asaas_api_key'];
                    $environment = !empty($gw['environment']) ? (string)$gw['environment'] : 'sandbox';
                    $baseUrl = $environment === 'production' ? 'https://api.asaas.com/v3' : 'https://sandbox.asaas.com/api/v3';

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                      CURLOPT_URL => $baseUrl . '/payments/' . urlencode((string)$order['transaction_id']),
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'access_token: ' . $apiKey,
                        'User-Agent: ModernPOS/1.0',
                      ],
                    ]);

                    $resp = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($resp !== false && $status >= 200 && $status < 300) {
                      $p = json_decode($resp, true);
                      if (is_array($p)) {
                        $boletoUrl = $p['invoiceUrl'] ?? ($p['bankSlipUrl'] ?? null);
                        $boletoBarcode = $p['identificationField'] ?? null;
                      }
                    } else {
                      $paymentError = 'Não foi possível carregar boleto via Asaas (HTTP ' . $status . ').';
                    }
                  }
                } else {
                  $gw = class_exists('PaymentGatewayConfig') ? PaymentGatewayConfig::get($pdoPay, $gatewayTenantId, 'mercado_pago') : null;
                  if ($gw && !empty($gw['enabled']) && !empty($gw['mp_access_token'])) {
                    $accessToken = (string)$gw['mp_access_token'];

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                      CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . urlencode((string)$order['transaction_id']),
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $accessToken,
                      ],
                    ]);

                    $resp = curl_exec($ch);
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($resp !== false && $status >= 200 && $status < 300) {
                      $p = json_decode($resp, true);
                      if (is_array($p)) {
                        $boletoUrl = $p['transaction_details']['external_resource_url'] ?? ($p['transaction_details']['payment_url'] ?? null);
                        $boletoBarcode = $p['barcode']['content']
                          ?? ($p['transaction_details']['barcode']['content'] ?? ($p['transaction_details']['payment_method_reference_id'] ?? null));
                      }
                    } else {
                      $paymentError = 'Não foi possível carregar boleto via Mercado Pago (HTTP ' . $status . ').';
                    }
                  }
                }
              }

            } catch (Throwable $e) {
              $paymentError = $e->getMessage();
            }

            $statusMap = [
              'paid' => ['label' => 'Pago', 'class' => 'bg-success'],
              'pending' => ['label' => 'Pendente', 'class' => 'bg-warning text-dark'],
              'failed' => ['label' => 'Falhou', 'class' => 'bg-danger'],
              'cancelled' => ['label' => 'Cancelado', 'class' => 'bg-secondary'],
              'refunded' => ['label' => 'Estornado', 'class' => 'bg-info'],
            ];

            $statusKey = $order ? ($order['status'] ?: 'pending') : 'pending';
            $st = $statusMap[$statusKey] ?? ['label' => ucfirst($statusKey), 'class' => 'bg-secondary'];
          ?>

          <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
              <h5 class="card-title mb-0"><i class="bi bi-receipt me-2"></i>Pagamento</h5>
              <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo root_url(); ?>conta/planos">
                  <i class="bi bi-grid-3x3-gap me-1"></i> Planos
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo root_url(); ?>conta/planos/historico">
                  <i class="bi bi-clock-history me-1"></i> Histórico
                </a>
              </div>
            </div>
            <div class="card-body">
              <?php if ($paymentError): ?>
                <div class="alert alert-danger">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  <?php echo htmlspecialchars((string)$paymentError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              <?php endif; ?>

              <?php if ($order): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                  <span class="badge <?php echo $st['class']; ?>" id="payment-status-badge"><?php echo htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="text-muted">Pedido #<?php echo (int)$order['order_id']; ?></span>
                  <span class="text-muted">&middot;</span>
                  <strong>R$ <?php echo number_format((float)$order['amount'], 2, ',', '.'); ?></strong>
                  <span class="text-muted">&middot;</span>
                  <span class="text-muted">Método: <?php echo htmlspecialchars(strtoupper($order['payment_method']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <?php if ($order['payment_method'] === 'pix'): ?>
                  <?php
                    // Busca configuração do gateway PIX Manual (WhatsApp)
                    $pixWhatsappNumber = '';
                    $pixWhatsappMessage = '';
                    $isPixManual = ($gatewayParam === 'pix_manual');
                    
                    if ($isPixManual) {
                      try {
                        $gatewayTenantId = 1;
                        try {
                          $stmtGT = $pdoPay->query("SELECT tenant_id FROM landing_pages ORDER BY is_default DESC, id ASC LIMIT 1");
                          if ($stmtGT) {
                            $tmp = (int)$stmtGT->fetchColumn();
                            if ($tmp > 0) $gatewayTenantId = $tmp;
                          }
                        } catch (Throwable $eGT) {
                          $gatewayTenantId = 1;
                        }
                        
                        $stmtWA = $pdoPay->prepare("SELECT whatsapp_support, whatsapp_message FROM saas_payment_gateways WHERE tenant_id = :tid AND gateway = 'pix_manual' LIMIT 1");
                        $stmtWA->execute([':tid' => $gatewayTenantId]);
                        $waRow = $stmtWA->fetch(PDO::FETCH_ASSOC);
                        if ($waRow) {
                          $pixWhatsappNumber = trim((string)($waRow['whatsapp_support'] ?? ''));
                          $pixWhatsappMessage = trim((string)($waRow['whatsapp_message'] ?? ''));
                        }
                      } catch (Throwable $eWA) {
                        // ignora
                      }
                      
                      if ($pixWhatsappMessage === '') {
                        $pixWhatsappMessage = 'Olá! Realizei um pagamento PIX e gostaria de confirmar a verificação. Pedido #' . $order['order_id'];
                      }
                    }
                  ?>
                  <div class="row g-4">
                    <div class="col-lg-6">
                      <div class="card bg-light">
                        <div class="card-body text-center">
                          <div class="text-uppercase text-muted small mb-2">QR Code Pix</div>
                          <?php if ($pixQrBase64): ?>
                            <img src="data:image/png;base64,<?php echo htmlspecialchars((string)$pixQrBase64, ENT_QUOTES, 'UTF-8'); ?>" alt="QR Pix" style="max-width:260px;width:100%;height:auto;" />
                          <?php else: ?>
                            <div class="text-muted">QR Code indisponível.</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="card">
                        <div class="card-body">
                          <div class="text-uppercase text-muted small mb-2">Pix copia e cola</div>
                          <textarea class="form-control" rows="5" readonly id="pix-copy-text"><?php echo htmlspecialchars((string)($pixQrText ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                          <div class="d-grid gap-2 mt-2">
                            <button type="button" class="btn btn-outline-primary" id="btn-copy-pix">
                              <i class="bi bi-clipboard me-1"></i> Copiar código Pix
                            </button>
                          </div>
                          <?php if (!$isPixManual): ?>
                          <p class="text-muted small mt-2 mb-0">
                            Após pagar, esta tela atualizará automaticamente o status.
                          </p>
                          <?php endif; ?>
                        </div>
                      </div>
                      
                      <?php if ($isPixManual): ?>
                      <!-- Área especial PIX Manual: Suporte -->
                      <div class="card mt-3 border-primary">
                        <div class="card-header bg-primary text-white">
                          <h6 class="mb-0"><i class="bi bi-headset me-2"></i>Precisa de ajuda?</h6>
                        </div>
                        <div class="card-body">
                          <p class="small text-muted mb-3">
                            O PIX Manual requer verificação da nossa equipe. Após realizar o pagamento:
                          </p>
                          
                          <div class="d-grid gap-2">
                            <?php if ($pixWhatsappNumber !== ''): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $pixWhatsappNumber); ?>?text=<?php echo urlencode($pixWhatsappMessage); ?>" 
                               target="_blank" 
                               class="btn btn-success btn-sm">
                              <i class="bi bi-whatsapp me-1"></i> Falar no WhatsApp
                            </a>
                            <?php endif; ?>
                            
                            <button type="button" 
                                    class="btn btn-outline-primary btn-sm" 
                                    id="btn-open-pix-verification-ticket"
                                    data-order-id="<?php echo (int)$order['order_id']; ?>">
                              <i class="bi bi-ticket-detailed me-1"></i> Enviar Comprovante
                            </button>
                          </div>
                          
                          <p class="text-muted small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Após a verificação, seu plano será ativado automaticamente.
                          </p>
                        </div>
                      </div>
                      <?php else: ?>
                      <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Após pagar, esta tela atualizará automaticamente o status.
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php elseif ($order['payment_method'] === 'boleto'): ?>
                  <div class="row g-4">
                    <div class="col-lg-7">
                      <div class="card">
                        <div class="card-body">
                          <div class="text-uppercase text-muted small mb-2">Boleto</div>
                          <?php if ($boletoUrl): ?>
                            <a class="btn btn-primary" href="<?php echo htmlspecialchars((string)$boletoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                              <i class="bi bi-box-arrow-up-right me-1"></i> Abrir boleto
                            </a>
                          <?php else: ?>
                            <div class="text-muted">Link do boleto indisponível.</div>
                          <?php endif; ?>

                          <div class="mt-3">
                            <div class="text-uppercase text-muted small mb-2">Linha digitável</div>
                            <textarea class="form-control" rows="3" readonly id="boleto-copy-text"><?php echo htmlspecialchars((string)($boletoBarcode ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <div class="d-grid gap-2 mt-2">
                              <button type="button" class="btn btn-outline-primary" id="btn-copy-boleto">
                                <i class="bi bi-clipboard me-1"></i> Copiar linha digitável
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-lg-5">
                      <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Assim que o pagamento for confirmado, o plano será atualizado automaticamente.
                      </div>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Acompanhe o status do pagamento abaixo.
                    <?php if (!empty($order['proof_file'])): ?>
                      <div class="mt-2">
                        <a href="<?php echo htmlspecialchars((string)$order['proof_file'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Ver comprovante</a>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="mt-4 d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-outline-secondary" id="btn-refresh-order" data-order-id="<?php echo (int)$order['order_id']; ?>">
                    <i class="bi bi-arrow-repeat me-1"></i> Atualizar status
                  </button>
                </div>

                <script>
                  (function(){
                    const orderId = <?php echo (int)$order['order_id']; ?>;
                    const apiBase = (window.PLANS_CONFIG && window.PLANS_CONFIG.apiBase) ? window.PLANS_CONFIG.apiBase : '<?php echo root_url(); ?>conta/_ajax/';

                    async function refresh(){
                      try {
                        const res = await fetch(apiBase + 'order_details.php?order_id=' + encodeURIComponent(orderId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await res.json();
                        if (!data || !data.success || !data.order) return;
                        const st = (data.order.status || 'pending').toLowerCase();
                        const badge = document.getElementById('payment-status-badge');
                        if (badge) {
                          const map = { paid: ['Pago','bg-success'], pending: ['Pendente','bg-warning text-dark'], failed: ['Falhou','bg-danger'], cancelled: ['Cancelado','bg-secondary'], refunded: ['Estornado','bg-info'] };
                          const v = map[st] || [st, 'bg-secondary'];
                          badge.textContent = v[0];
                          badge.className = 'badge ' + v[1];
                        }
                      } catch (e) {
                        // ignore
                      }
                    }

                    const btn = document.getElementById('btn-refresh-order');
                    if (btn) btn.addEventListener('click', refresh);

                    const btnCopyPix = document.getElementById('btn-copy-pix');
                    if (btnCopyPix) {
                      btnCopyPix.addEventListener('click', async function(){
                        const txt = document.getElementById('pix-copy-text')?.value || '';
                        try { await navigator.clipboard.writeText(txt); } catch(e) {
                          const el = document.getElementById('pix-copy-text');
                          if (el) { el.focus(); el.select(); document.execCommand('copy'); }
                        }
                      });
                    }

                    const btnCopyBol = document.getElementById('btn-copy-boleto');
                    if (btnCopyBol) {
                      btnCopyBol.addEventListener('click', async function(){
                        const txt = document.getElementById('boleto-copy-text')?.value || '';
                        try { await navigator.clipboard.writeText(txt); } catch(e) {
                          const el = document.getElementById('boleto-copy-text');
                          if (el) { el.focus(); el.select(); document.execCommand('copy'); }
                        }
                      });
                    }

                    // auto-refresh leve
                    setInterval(refresh, 10000);
                    refresh();
                  })();
                </script>
              <?php else: ?>
                <div class="alert alert-danger mb-0">Pedido inválido.</div>
              <?php endif; ?>
            </div>
          </div>

        <?php else: ?>

        <!-- Alerta (modelo AdminLTE2) com dados do plano atual -->
        <div id="current-plan-alert" class="alert alert-info mb-4" style="display:none;">
          <i class="fa fa-info-circle"></i>
          <strong>Plano Atual:</strong> <span id="current-plan-alert-name">Carregando...</span>
          <span class="d-none d-md-inline">&mdash;</span>
          <span id="current-plan-alert-text" class="d-none d-md-inline">Carregando...</span>
        </div>

        <!-- Toggle Mensal / Anual (centralizado, como referência) -->
        <div class="billing-toggle mb-4" aria-label="Alternar cobrança mensal/anual">
          <span class="billing-label active">Mensal</span>
          <label class="toggle-switch">
            <input type="checkbox" id="billingToggle" />
            <span class="toggle-slider"></span>
          </label>
          <span class="billing-label">Anual</span>
          <span class="discount-badge" id="annualDiscountBadge">ECONOMIZE</span>
        </div>

        <!-- Grid de Planos -->
        <div class="plans-grid" id="plans-grid">
          <div class="text-center py-5" style="grid-column: 1 / -1;">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Carregando planos disponíveis...</p>
          </div>
        </div>

        <!-- FAQ -->
        <div class="card mt-4">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-question-circle me-2"></i>Perguntas Frequentes</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 mb-3 mb-md-0">
                <div class="faq-item">
                  <strong><i class="bi bi-arrow-repeat text-primary me-2"></i>Posso mudar de plano?</strong>
                  <p class="mb-0 small text-secondary mt-1">
                    Sim! Upgrade é imediato. Downgrade entra no próximo ciclo.
                  </p>
                </div>
              </div>
              <div class="col-md-4 mb-3 mb-md-0">
                <div class="faq-item">
                  <strong><i class="bi bi-shop text-primary me-2"></i>Limite de lojas?</strong>
                  <p class="mb-0 small text-secondary mt-1">
                    Ao atingir o limite, faça upgrade ou desative uma loja.
                  </p>
                </div>
              </div>
              <div class="col-md-4">
                <div class="faq-item">
                  <strong><i class="bi bi-x-circle text-primary me-2"></i>Cancelamento?</strong>
                  <p class="mb-0 small text-secondary mt-1">
                    Acesso continua até o fim do período pago.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php endif; ?>
      </div>

      <!-- TAB: HISTÓRICO (Pagamentos + Ações + Método Pagamento + Cancelamento) -->
      <div class="tab-pane fade <?php echo $tabUi === 'historico' ? 'show active' : ''; ?>" 
           id="tab-historico" role="tabpanel">
        
        <!-- Alerta de Assinatura Cancelada -->
        <div id="cancelled-subscription-alert" class="alert alert-danger d-flex align-items-start mb-4" style="display: none !important;">
          <div class="flex-shrink-0 me-3">
            <i class="bi bi-x-octagon-fill fs-1 text-danger"></i>
          </div>
          <div class="flex-grow-1">
            <h5 class="alert-heading mb-1">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Assinatura Cancelada
            </h5>
            <p class="mb-2" id="cancelled-alert-message">
              Sua assinatura foi cancelada. O acesso às funcionalidades continuará até o fim do período já pago.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-danger btn-sm">
                <i class="bi bi-arrow-repeat me-1"></i> Reativar Assinatura
              </a>
              <a href="<?php echo root_url(); ?>conta/suporte" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-headset me-1"></i> Falar com Suporte
              </a>
            </div>
          </div>
        </div>
        
        <div class="row">
          <!-- Coluna Principal: Histórico -->
          <div class="col-lg-8">
            <!-- Sumário -->
            <div class="row mb-4">
              <div class="col-4">
                <div class="card bg-success text-white h-100">
                  <div class="card-body text-center py-3">
                    <h5 id="summary-last-paid" class="mb-0">R$ 0,00</h5>
                    <small>Último Valor Pago</small>
                  </div>
                </div>
              </div>
              <div class="col-4">
                <div class="card bg-warning text-dark h-100">
                  <div class="card-body text-center py-3">
                    <h5 id="summary-total-pending" class="mb-0">R$ 0,00</h5>
                    <small>Pendente</small>
                  </div>
                </div>
              </div>
              <div class="col-4">
                <div class="card bg-info text-white h-100">
                  <div class="card-body text-center py-3">
                    <h5 id="summary-count-paid" class="mb-0">0</h5>
                    <small>Faturas</small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tabela de Histórico -->
            <div class="card">
              <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h5 class="card-title mb-0"><i class="bi bi-receipt me-2"></i>Histórico de Pagamentos</h5>
                <div class="btn-group btn-group-sm ms-auto filter-btn-group" role="group" aria-label="Filtro de pagamentos">
                  <button type="button" class="btn btn-outline-secondary active" data-history-filter="">Todos</button>
                  <button type="button" class="btn btn-outline-secondary" data-history-filter="paid">Pagos</button>
                  <button type="button" class="btn btn-outline-secondary" data-history-filter="pending">Pendentes</button>
                </div>
              </div>
              <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Data</th>
                      <th>Descrição</th>
                      <th>Valor</th>
                      <th>Status</th>
                      <th class="text-end">Ações</th>
                    </tr>
                  </thead>
                  <tbody id="billing-history-body">
                    <tr>
                      <td colspan="5" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="card-footer" id="billing-pagination"></div>
            </div>
          </div>
          
          <!-- Coluna Lateral: Uso do Plano + Próxima Cobrança + Método de Pagamento + Cancelamento -->
          <div class="col-lg-4">
            <!-- Card: Uso do Plano (movido para o Histórico) -->
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Uso do Plano</h5>
              </div>
              <div class="card-body" id="usage-details">
                <div class="text-center py-3">
                  <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                </div>
              </div>
            </div>

            <!-- Card: Próxima Cobrança (calculada pelo sistema) -->
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar-event me-2"></i>Próxima Cobrança</h5>
              </div>
              <div class="card-body" id="next-billing-details">
                <div class="text-center py-3">
                  <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                </div>
              </div>
            </div>
            
            <!-- Card: Método de Pagamento -->
            <div class="card mb-4">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-credit-card me-2"></i>Método de Pagamento</h5>
              </div>
              <div class="card-body" id="payment-method-details">
                <div class="text-center py-3">
                  <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                </div>
              </div>
            </div>
            
            <!-- Card: Gerenciar Assinatura -->
            <div class="card border-secondary">
              <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-gear me-2"></i>Gerenciar Assinatura</h5>
              </div>
              <div class="card-body">
                <div class="d-grid gap-2">
                  <button class="btn btn-outline-danger" id="btn-cancel-subscription">
                    <i class="bi bi-x-circle me-1"></i> Cancelar Assinatura
                  </button>
                </div>
                <p class="text-muted small mt-2 mb-0">
                  <i class="bi bi-info-circle me-1"></i>
                  Ao cancelar, você mantém acesso até o fim do período pago.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div> <!-- tab-content -->
    </div> <!-- account-plans-page -->
  </div> <!-- container-fluid -->
</div> <!-- app-content -->

<!-- Modal: Checkout (ex.: Assinar Básico) -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i><span id="checkout-modal-title">Finalizar Pagamento</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="checkout-modal-body"></div>
    </div>
  </div>
</div>

<!-- Modal: Pagamento Confirmado -->
<div class="modal fade" id="paymentConfirmedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white border-0">
        <h5 class="modal-title"><i class="bi bi-check-circle-fill me-2"></i>Pagamento confirmado</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Seu pagamento foi aprovado e sua assinatura já está ativa.</p>
        <div class="alert alert-light border d-flex align-items-center gap-2 mb-0">
          <i class="bi bi-shield-check text-success fs-5"></i>
          <span class="small text-muted">Você pode ver os detalhes na aba <strong>Histórico</strong>.</span>
        </div>
      </div>
      <div class="modal-footer border-0">
        <a href="<?php echo root_url(); ?>conta/planos/historico" class="btn btn-outline-secondary">
          <i class="bi bi-clock-history me-1"></i> Ver histórico
        </a>
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">Ok</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Cancelar Assinatura -->
<div class="modal fade" id="cancelModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Cancelar Assinatura</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="lead">Tem certeza que deseja cancelar sua assinatura?</p>
        <div class="alert alert-warning">
          <strong><i class="bi bi-exclamation-triangle me-1"></i> Atenção:</strong>
          <ul class="mb-0 mt-2">
            <li>Você perderá acesso às funcionalidades premium</li>
            <li>Seus dados serão mantidos por 30 dias após o cancelamento</li>
            <li>O acesso continua até o fim do período já pago</li>
          </ul>
        </div>
        <div class="mb-3">
          <label class="form-label">Por que está cancelando?</label>
          <select class="form-select" id="cancel-reason">
            <option value="">Selecione um motivo...</option>
            <option value="too_expensive">Muito caro para meu orçamento</option>
            <option value="not_using">Não estou usando o sistema</option>
            <option value="missing_features">Faltam funcionalidades que preciso</option>
            <option value="switching">Vou usar outro sistema</option>
            <option value="temporary">Pausa temporária no negócio</option>
            <option value="other">Outro motivo</option>
          </select>
        </div>
        <div class="mb-3" id="cancel-reason-other-container" style="display: none;">
          <label class="form-label">Conte-nos mais:</label>
          <textarea class="form-control" id="cancel-reason-other" rows="2" placeholder="Descreva o motivo..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-arrow-left me-1"></i> Manter Assinatura
        </button>
        <button type="button" class="btn btn-danger" id="btn-confirm-cancel">
          <i class="bi bi-x-circle me-1"></i> Confirmar Cancelamento
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Verificação PIX Manual (Upload Comprovante) -->
<div class="modal fade" id="pixVerificationModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-qr-code me-2"></i>Verificação de Pagamento PIX</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-1"></i>
          Envie o comprovante do seu pagamento PIX para agilizar a verificação. 
          Um ticket será criado automaticamente para nossa equipe.
        </div>
        
        <form id="pix-verification-form" enctype="multipart/form-data">
          <input type="hidden" id="pix-order-id" name="order_id" />
          
          <div class="mb-3">
            <label class="form-label">Comprovante de Pagamento</label>
            <input type="file" 
                   class="form-control" 
                   id="pix-proof-file" 
                   name="proof" 
                   accept="image/jpeg,image/jpg,image/png,application/pdf" />
            <div class="form-text">
              Formatos aceitos: JPG, PNG ou PDF. Tamanho máximo: 5MB
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Informações Adicionais (Opcional)</label>
            <textarea class="form-control" 
                      id="pix-description" 
                      name="description" 
                      rows="3" 
                      placeholder="Adicione qualquer informação que possa ajudar na verificação..."></textarea>
          </div>
          
          <div class="card bg-light" id="pix-order-info" style="display: none;">
            <div class="card-body py-2">
              <small class="text-muted">Pedido selecionado:</small>
              <div id="pix-order-details"></div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="btn-submit-pix-verification">
          <i class="bi bi-send me-1"></i> Enviar para Verificação
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Contestação (Abre Ticket) -->
<div class="modal fade" id="contestModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>Abrir Contestação</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-1"></i>
          Um ticket será aberto automaticamente com nossa equipe de suporte.
        </div>
        
        <input type="hidden" id="contest-order-id" />
        
        <div class="mb-3">
          <label class="form-label">Tipo de Contestação <span class="text-danger">*</span></label>
          <select class="form-select" id="contest-type" required>
            <option value="">Selecione...</option>
            <option value="refund">Solicitação de Estorno</option>
            <option value="payment_issue">Problema com Pagamento</option>
            <option value="wrong_charge">Cobrança Indevida</option>
            <option value="duplicate">Cobrança Duplicada</option>
            <option value="other">Outro Problema</option>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Título do Ticket <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="contest-title" 
                 placeholder="Ex: Solicitação de estorno - Fatura #123" required />
        </div>
        
        <div class="mb-3">
          <label class="form-label">Descrição Detalhada <span class="text-danger">*</span></label>
          <textarea class="form-control" id="contest-description" rows="4" 
                    placeholder="Descreva o problema em detalhes..." required></textarea>
        </div>
        
        <div class="card bg-light" id="contest-order-info" style="display: none;">
          <div class="card-body py-2">
            <small class="text-muted">Fatura selecionada:</small>
            <div id="contest-order-details"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-submit-contest">
          <i class="bi bi-send me-1"></i> Abrir Ticket
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  window.PLANS_CONFIG = {
    apiBase: '<?php echo root_url(); ?>conta/_ajax/',
    rootUrl: '<?php echo root_url(); ?>',
    currentTab: '<?php echo $tab; ?>'
  };
</script>
<script src="<?php echo root_url(); ?>conta/assets/js/plans.js?v=<?php echo $plansJsV; ?>"></script>
<!-- Cartão 3D Interativo (checkout transparente) -->
<script src="<?php echo root_url(); ?>conta/assets/js/card-3d.js?v=<?php echo $plansJsV; ?>"></script>
