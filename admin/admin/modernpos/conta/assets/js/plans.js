/**
 * Plans & Subscription Page JavaScript
 * Sistema de Pagamentos Interno - ModernPOS
 */

(function() {
  'use strict';

  // Configuração
  const CONFIG = window.PLANS_CONFIG || {
    apiBase: '/conta/_ajax/',
    rootUrl: '/',
    currentTab: 'plano_atual'
  };

  // Estado da aplicação
  const state = {
    plans: [],
    currentSubscription: null,
    billingHistory: [],
    isYearly: false,
    currentPage: 1,
    totalPages: 1,
    historyFilter: ''
  };

  // ==========================================
  // Utilitários
  // ==========================================

  function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(value);
  }

  function formatCurrencyNoCents(value) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value);
  }

  function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  }

  function formatDateLong(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  async function apiCall(endpoint, options = {}) {
    try {
      const response = await fetch(CONFIG.apiBase + endpoint, {
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        ...options
      });
      const data = await response.json();
      if (!data.success && data.error) {
        console.error('API Error:', data.error);
      }
      return data;
    } catch (error) {
      console.error('API Call Failed:', error);
      return { success: false, error: error.message };
    }
  }

  function showToast(message, type = 'info') {
    // Usa o sistema de toast existente ou cria um simples
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
        title: message,
        showConfirmButton: false,
        timer: 3000
      });
    } else {
      alert(message);
    }
  }

  async function showPaymentConfirmedModalIfNeeded() {
    try {
      const params = new URLSearchParams(window.location.search);
      if (!params.has('payment_confirmed')) return;

      const orderId = params.get('order_id');
      const paymentIntentId = params.get('payment_intent_id') || params.get('payment_intent');

      // Se voltamos de um redirect 3DS, confirmamos no backend antes de exibir a mensagem
      if (orderId && paymentIntentId) {
        try {
          const result = await apiCall('confirm_card_payment.php', {
            method: 'POST',
            body: JSON.stringify({
              order_id: parseInt(orderId, 10),
              payment_intent_id: paymentIntentId
            })
          });

          if (!result || !result.success) {
            showToast((result && result.message) ? result.message : 'Não foi possível confirmar o pagamento.', 'error');
          }
        } catch (eConfirm) {
          showToast('Não foi possível confirmar o pagamento.', 'error');
        }
      }

      const modalEl = document.getElementById('paymentConfirmedModal');
      if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
      }

      // Remove os parâmetros para não abrir de novo no refresh
      ['payment_confirmed', 'order_id', 'payment_intent_id', 'payment_intent', 'payment_intent_client_secret', 'redirect_status']
        .forEach((k) => params.delete(k));

      const qs = params.toString();
      const newUrl = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
      window.history.replaceState({}, document.title, newUrl);
    } catch (e) {
      // ignore
    }
  }

  // ==========================================
  // Carregamento de Dados
  // ==========================================

  async function loadPlans() {
    const result = await apiCall('plans_list.php');
    if (result.success) {
      state.plans = result.plans || [];
      state.currentPlanId = result.current_plan_id;
      updateAnnualDiscountBadge();
      renderPlansGrid();
    }
  }

  function updateAnnualDiscountBadge() {
    const badge = document.getElementById('annualDiscountBadge');
    if (!badge) return;

    const discounts = (state.plans || [])
      .map((p) => parseInt(p.yearly_discount_percent || 0, 10))
      .filter((n) => !isNaN(n));

    const maxDiscount = discounts.length ? Math.max(...discounts) : 0;

    if (maxDiscount > 0) {
      badge.textContent = `ECONOMIZE ${maxDiscount}%`;
      badge.style.display = '';
    } else {
      badge.style.display = 'none';
    }
  }

  function renderCurrentPlanAlert() {
    const alert = document.getElementById('current-plan-alert');
    const nameEl = document.getElementById('current-plan-alert-name');
    const textEl = document.getElementById('current-plan-alert-text');

    if (!alert || !nameEl || !textEl) return;

    const sub = state.currentSubscription;
    if (!sub || !sub.plan) {
      alert.style.display = 'none';
      return;
    }

    const planName = sub.plan.name || 'Plano';
    const nextDate = sub.next_billing_formatted || (sub.next_billing_date ? formatDate(sub.next_billing_date) : null);

    const storesUsed = sub.usage?.stores ?? 0;
    const storesLimit = sub.usage?.stores_limit ?? sub.plan?.limits?.stores ?? null;

    const storesText = (storesLimit === null || typeof storesLimit === 'undefined')
      ? `${storesUsed} lojas ativas`
      : `${storesUsed} lojas ativas de um limite de ${storesLimit}`;

    nameEl.textContent = planName;
    textEl.textContent = `Sua assinatura está ativa${nextDate ? ' e será renovada em ' + nextDate : ''}. Você tem ${storesText}.`;

    alert.style.display = '';
  }

  async function loadCurrentSubscription() {
    const result = await apiCall('current_subscription.php');
    if (result.success) {
      // A API retorna os dados diretamente, não em result.data
      // Determina método de pagamento: usa o padrão configurado, ou fallback para última fatura
      const paymentMethodType = result.billing?.payment_method || result.billing?.last_payment?.method;
      const paymentMethodNames = {
        'card': 'Cartão',
        'credit_card': 'Cartão de Crédito',
        'pix': 'PIX',
        'boleto': 'Boleto'
      };

      state.currentSubscription = {
        status: result.subscription?.status,
        plan: result.current_plan,
        usage: result.usage ? {
          stores: result.usage.stores_used,
          stores_limit: result.usage.stores_limit,
          users: result.usage.users_used || 0,
          users_limit: result.usage.users_limit,
          products: result.usage.products_used || 0,
          products_limit: result.usage.products_limit,
          clients: result.usage.clients_used || 0,
          clients_limit: result.usage.clients_limit || 0,
          storage_used_mb: result.usage.storage_used_mb || 0,
          storage_limit_mb: result.usage.storage_limit_mb || 0
        } : null,
        next_billing_date: result.billing?.next_billing_date,
        next_billing_formatted: result.billing?.next_billing_formatted,
        next_billing_amount: result.billing?.next_billing_amount,
        payment_method: paymentMethodType ? {
          type: paymentMethodType,
          last_four: result.billing.card_last_four,
          brand: result.billing.card_brand,
          name: paymentMethodNames[paymentMethodType] || paymentMethodType
        } : null,
        trial_days_remaining: result.subscription?.trial_days_remaining,
        cancellation_scheduled_at: result.subscription?.cancellation_date,
        current_price: result.current_plan?.price_monthly,
        billing_cycle: 'monthly',
        created_at: result.tenant?.created_at
      };
      renderCurrentPlan();
      renderCurrentPlanAlert();
      renderNextBilling();
      renderPaymentMethod();
      renderUsage();
      updateSubscriptionBadge();
      updateSubscriptionAlert();
      updateCancelledSubscriptionAlert();
    }
  }

  async function loadBillingHistory(page = 1) {
    const params = new URLSearchParams({
      page: page,
      per_page: 10
    });
    if (state.historyFilter) {
      params.append('status', state.historyFilter);
    }

    const result = await apiCall('billing_history.php?' + params.toString());
    if (result.success) {
      // A API pode retornar diretamente ou em result.data
      const data = result.data || result;
      state.billingHistory = data.payments || [];
      state.currentPage = data.pagination?.current_page || 1;
      state.totalPages = data.pagination?.total_pages || 1;
      renderBillingHistory();
      renderBillingSummary(data.summary);
      renderPagination();
    }
  }

  // ==========================================
  // Renderização
  // ==========================================

  function renderPlansGrid() {
    const container = document.getElementById('plans-grid');
    if (!container) return;

    if (!state.plans.length) {
      container.innerHTML = `
        <div class="empty-state" style="grid-column: 1 / -1;">
          <i class="bi bi-inbox"></i>
          <h5>Nenhum plano disponível</h5>
          <p>Entre em contato com o suporte.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = state.plans.map(plan => renderPlanCard(plan)).join('');
  }

  function renderPlanCard(plan) {
    const isCurrentPlan = plan.plan_id === state.currentPlanId;
    const isFeatured = plan.is_featured == 1;

    const monthlyPrice = parseFloat(plan.price_monthly || 0);
    const yearlyTotal = parseFloat(plan.price_yearly || (monthlyPrice * 10));

    // No modo anual, mostramos o valor /mês equivalente (como no modelo de referência)
    const displayMonthly = state.isYearly ? (yearlyTotal / 12) : monthlyPrice;

    // Subtexto do período
    let periodHtml = '';
    if (state.isYearly && monthlyPrice > 0) {
      const monthlyTotal = monthlyPrice * 12;
      const savings = monthlyTotal - yearlyTotal;
      const yearlyText = `${formatCurrencyNoCents(yearlyTotal)}/ano`;
      periodHtml = savings > 0
        ? `<span class="yearly-period">${yearlyText} - Economize ${formatCurrencyNoCents(savings)}</span>`
        : `<span class="yearly-period">${yearlyText}</span>`;
    } else {
      periodHtml = `<span class="monthly-period">Cobrado mensalmente</span>`;
    }

    // Badge
    let badgeHtml = '';
    if (isCurrentPlan) {
      badgeHtml = '<span class="plan-badge">PLANO ATUAL</span>';
    } else if (plan.badge_text) {
      badgeHtml = `<span class="plan-badge featured">${escapeHtml(plan.badge_text)}</span>`;
    } else if (isFeatured) {
      badgeHtml = '<span class="plan-badge featured">MAIS POPULAR</span>';
    }

    // Features
    const features = parseFeatures(plan);
    const featuresHtml = features.map(f => `
      <li>
        <i class="fa ${f.disabled ? 'fa-times' : 'fa-check'}"></i>
        ${escapeHtml(f.text)}
      </li>
    `).join('');

    // Button
    let buttonHtml = '';
    if (isCurrentPlan) {
      buttonHtml = `<button class="btn btn-success btn-block btn-lg" disabled>Plano Ativo</button>`;
    } else if (plan.price_monthly == 0) {
      buttonHtml = `<button class="btn btn-default btn-block btn-lg" disabled>Gratuito</button>`;
    } else {
      const isUpgrade = state.currentSubscription &&
                        plan.price_monthly > (state.currentSubscription.plan?.price_monthly || 0);

      const btnClass = isUpgrade ? 'btn-primary' : 'btn-default';
      const label = isUpgrade ? 'Fazer Upgrade' : 'Selecionar Plano';

      buttonHtml = `
        <button class="btn ${btnClass} btn-block btn-lg btn-select-plan"
                data-plan-id="${plan.plan_id}"
                data-billing="${state.isYearly ? 'yearly' : 'monthly'}">
          ${label}
        </button>
      `;
    }

    return `
      <div class="plan-card${isFeatured ? ' featured' : ''}${isCurrentPlan ? ' current' : ''}">
        ${badgeHtml}
        <div class="plan-header">
          <div class="plan-name">${escapeHtml(plan.name)}</div>
          <div class="plan-price">
            R$ ${Math.round(displayMonthly)} <small>/mês</small>
          </div>
          <div class="plan-period">
            ${periodHtml}
          </div>
        </div>
        <div class="plan-body">
          <ul class="plan-features">
            ${featuresHtml}
          </ul>
        </div>
        <div class="plan-footer">
          ${buttonHtml}
        </div>
      </div>
    `;
  }

  function parseFeatures(plan) {
    // Montagem de features no padrão do modelo de referência
    // Objetivo: sempre mostrar itens "interessantes" no topo.

    const limits = plan.limits || {};

    const maxStores = (typeof limits.stores !== 'undefined') ? limits.stores : (plan.max_stores || 1);
    const maxProducts = (typeof limits.products !== 'undefined') ? limits.products : (plan.max_products || 100);
    const maxUsers = (typeof limits.users !== 'undefined') ? limits.users : (plan.max_users || 1);
    const whatsappAccounts = (typeof limits.whatsapp !== 'undefined') ? limits.whatsapp : (plan.whatsapp_accounts || 0);

    const storesText = (maxStores === -1)
      ? 'Lojas ilimitadas'
      : `Até ${maxStores} loja${maxStores > 1 ? 's' : ''}`;

    const productsText = (maxProducts === -1)
      ? 'Produtos ilimitados'
      : `Até ${maxProducts} produtos`;

    const supportText = 'Suporte prioritário';
    const supportDisabled = !(plan.priority_support == 1 || plan.priority_support === true);

    const whatsappText = 'WhatsApp integrado';
    const whatsappDisabled = !(whatsappAccounts > 0);

    const usersText = (maxUsers === -1)
      ? 'Usuários ilimitados'
      : `Até ${maxUsers} usuário${maxUsers > 1 ? 's' : ''}`;

    const features = [
      { text: storesText, disabled: false },
      { text: productsText, disabled: false },
      { text: supportText, disabled: supportDisabled },
      { text: whatsappText, disabled: whatsappDisabled },
      { text: usersText, disabled: false },
      { text: 'Vendas ilimitadas', disabled: false },
    ];

    return features;
  }

  function renderCurrentPlan() {
    // NOVA ESTRUTURA: Hero card
    const planName = document.getElementById('current-plan-name');
    const planPrice = document.getElementById('current-plan-price');
    const planFeatures = document.getElementById('current-plan-features');
    const planBadge = document.getElementById('plan-badge');
    const planSince = document.getElementById('plan-since');
    
    const sub = state.currentSubscription;
    
    if (!sub || !sub.plan) {
      if (planName) planName.textContent = 'Sem Plano';
      if (planPrice) planPrice.textContent = 'R$ 0,00';
      if (planFeatures) planFeatures.innerHTML = '<span class="feature-tag"><i class="bi bi-x-circle"></i> Nenhum plano ativo</span>';
      if (planBadge) {
        planBadge.textContent = 'Inativo';
        planBadge.className = 'badge bg-secondary';
      }
      return;
    }

    // Atualiza hero card
    if (planName) planName.textContent = sub.plan.name || 'Plano';
    if (planPrice) planPrice.textContent = formatCurrency(sub.current_price || sub.plan.price_monthly || 0);
    
    if (planBadge) {
      const statusLabels = {
        'active': { text: 'Ativo', class: 'bg-success' },
        'trial': { text: 'Trial', class: 'bg-warning text-dark' },
        'cancelled': { text: 'Cancelado', class: 'bg-secondary' },
        'past_due': { text: 'Pendente', class: 'bg-danger' }
      };
      const st = statusLabels[sub.status] || { text: 'Ativo', class: 'bg-success' };
      planBadge.textContent = st.text;
      planBadge.className = 'badge ' + st.class;
    }
    
    if (planSince && sub.created_at) {
      planSince.textContent = 'Desde: ' + formatDate(sub.created_at);
    }
    
    // Features inline
    if (planFeatures && sub.plan.limits) {
      const limits = sub.plan.limits;
      const features = [];
      
      if (limits.stores) {
        features.push(`<span class="feature-tag"><i class="bi bi-shop"></i> ${limits.stores} loja${limits.stores > 1 ? 's' : ''}</span>`);
      }
      if (limits.users) {
        features.push(`<span class="feature-tag"><i class="bi bi-people"></i> ${limits.users} usuário${limits.users > 1 ? 's' : ''}</span>`);
      }
      if (limits.products === -1) {
        features.push(`<span class="feature-tag"><i class="bi bi-box"></i> Produtos ilimitados</span>`);
      } else if (limits.products) {
        features.push(`<span class="feature-tag"><i class="bi bi-box"></i> ${limits.products} produtos</span>`);
      }
      if (sub.plan.priority_support) {
        features.push(`<span class="feature-tag"><i class="bi bi-headset"></i> Suporte prioritário</span>`);
      }
      
      planFeatures.innerHTML = features.join('');
    }
  }

  function renderNextBilling() {
    const container = document.getElementById('next-billing-details');
    if (!container) return;

    const sub = state.currentSubscription;
    if (!sub || !sub.plan) {
      container.innerHTML = `
        <p class="text-muted mb-0">Nenhuma cobrança agendada.</p>
      `;
      return;
    }

    // CALCULAR PRÓXIMA COBRANÇA LOCALMENTE (ciclo sempre mensal - 30 dias)
    let nextBillingDate;
    if (sub.next_billing_date) {
      nextBillingDate = new Date(sub.next_billing_date);
    } else {
      // Calcula baseado no último pagamento ou data de criação + 30 dias
      const baseDate = sub.last_payment_date ? new Date(sub.last_payment_date) : new Date(sub.created_at || Date.now());
      nextBillingDate = new Date(baseDate);
      nextBillingDate.setDate(nextBillingDate.getDate() + 30);
      
      // Se a data calculada já passou, calcular a partir de hoje
      if (nextBillingDate < new Date()) {
        nextBillingDate = new Date();
        nextBillingDate.setDate(nextBillingDate.getDate() + 30);
      }
    }

    const nextDate = formatDateLong(nextBillingDate.toISOString());
    const amount = sub.current_price || sub.plan?.price_monthly || 0;

    container.innerHTML = `
      <div class="next-billing-info">
        <div class="next-billing-date">
          <div class="icon-wrapper">
            <i class="bi bi-calendar-check"></i>
          </div>
          <div class="date-info">
            <div class="label">Próxima cobrança</div>
            <div class="value">${nextDate}</div>
          </div>
        </div>
        <div class="next-billing-amount">
          <div class="label">Valor</div>
          <div class="value">${formatCurrency(amount)}</div>
        </div>
      </div>
    `;
  }

  function renderPaymentMethod() {
    const container = document.getElementById('payment-method-details');
    const btnChange = document.getElementById('btn-change-payment');
    if (!container) return;

    const sub = state.currentSubscription;
    if (!sub || !sub.payment_method) {
      container.innerHTML = `
        <p class="text-muted mb-0">Nenhum método configurado.</p>
      `;
      return;
    }

    const pm = sub.payment_method;
    let iconHtml = '<i class="bi bi-credit-card"></i>';
    
    if (pm.type === 'card') {
      const brandIcons = {
        'visa': 'bi-credit-card-2-front',
        'mastercard': 'bi-credit-card-2-back',
        'amex': 'bi-credit-card',
      };
      iconHtml = `<i class="bi ${brandIcons[pm.brand?.toLowerCase()] || 'bi-credit-card'}"></i>`;
    } else if (pm.type === 'pix') {
      iconHtml = '<i class="bi bi-qr-code"></i>';
    } else if (pm.type === 'boleto') {
      iconHtml = '<i class="bi bi-upc"></i>';
    }

    container.innerHTML = `
      <div class="payment-method-display">
        <div class="payment-method-icon">${iconHtml}</div>
        <div class="payment-method-info">
          <div class="payment-method-name">${escapeHtml(pm.name || pm.type)}</div>
          ${pm.last_four ? `<div class="payment-method-details">**** ${pm.last_four}</div>` : ''}
        </div>
      </div>
    `;

    if (btnChange) {
      btnChange.disabled = false;
    }
  }

  function renderUsage() {
    const container = document.getElementById('usage-details');
    if (!container) return;

    const sub = state.currentSubscription;
    if (!sub || !sub.usage) {
      container.innerHTML = `
        <p class="text-muted mb-0 small">Sem dados de uso disponíveis.</p>
      `;
      return;
    }

    const usage = sub.usage;
    const limits = sub.plan?.limits || {};
    const items = [
      { label: 'Lojas', current: usage.stores || 0, max: usage.stores_limit || limits.stores || 1 },
      { label: 'Usuários', current: usage.users || 0, max: usage.users_limit || limits.users || 3 },
      { label: 'Produtos', current: usage.products || 0, max: usage.products_limit || limits.products || 500 },
      { label: 'Clientes', current: usage.clients || 0, max: usage.clients_limit || limits.clients || 0 },
      { key: 'storage', label: 'Armazenamento', current: usage.storage_used_mb || 0, max: usage.storage_limit_mb || limits.storage_mb || 0, unit: 'MB' }
    ];

    container.innerHTML = items.map(item => {
      // Se o limite é 0 ou não configurado, não mostra a barra para este item
      if (item.max === 0 && item.current === 0) return '';
      
      const isUnlimited = item.max === -1 || item.max === 0;
      const percent = isUnlimited ? 0 : Math.min(100, (item.current / item.max) * 100);
      const levelClass = percent >= 90 ? 'high' : (percent >= 70 ? 'medium' : 'low');
      const maxText = isUnlimited ? '∞' : item.max;
      const unit = item.unit || '';
      const currentDisplay = item.unit ? item.current.toFixed(2) : item.current;
      
      // Destaque especial para armazenamento crítico (>90%)
      const isStorageCritical = item.key === 'storage' && percent >= 90;
      const isStorageWarning = item.key === 'storage' && percent >= 70 && percent < 90;
      const isStorageFull = item.key === 'storage' && percent >= 100;
      
      let alertBadge = '';
      let actionLink = '';
      
      if (isStorageFull) {
        alertBadge = `<span class="badge bg-danger ms-2" title="Armazenamento cheio!"><i class="bi bi-exclamation-triangle-fill"></i> Cheio</span>`;
        actionLink = `<a href="/painel/paginas/media-library" class="small text-danger d-block mt-1"><i class="bi bi-folder2 me-1"></i>Gerenciar arquivos</a>`;
      } else if (isStorageCritical) {
        alertBadge = `<span class="badge bg-danger ms-2" title="Armazenamento quase cheio!"><i class="bi bi-exclamation-triangle"></i></span>`;
        actionLink = `<a href="/painel/paginas/media-library" class="small text-danger d-block mt-1"><i class="bi bi-folder2 me-1"></i>Gerenciar arquivos</a>`;
      } else if (isStorageWarning) {
        alertBadge = `<span class="badge bg-warning text-dark ms-2" title="Armazenamento em alerta"><i class="bi bi-exclamation-circle"></i></span>`;
      }

      return `
        <div class="usage-item ${isStorageCritical ? 'usage-item-critical' : ''} ${isStorageFull ? 'usage-item-full' : ''}">
          <div class="usage-header">
            <span class="usage-label">${item.label}${alertBadge}</span>
            <span class="usage-value ${isStorageCritical || isStorageFull ? 'text-danger fw-bold' : ''}">${currentDisplay}${unit ? ' ' + unit : ''} / ${maxText}${unit ? ' ' + unit : ''}</span>
          </div>
          <div class="usage-bar">
            <div class="usage-bar-fill ${levelClass}" style="width: ${isUnlimited ? 0 : percent}%"></div>
          </div>
          ${actionLink}
        </div>
      `;
    }).filter(Boolean).join('');
  }

  function updateSubscriptionBadge() {
    const badge = document.getElementById('subscription-status-badge');
    if (!badge) return;

    const sub = state.currentSubscription;
    if (!sub) {
      badge.textContent = 'Sem Assinatura';
      badge.className = 'badge bg-secondary';
      return;
    }

    const statusMap = {
      'active': { text: 'Ativo', class: 'bg-success' },
      'trial': { text: 'Período de Teste', class: 'bg-warning text-dark' },
      'past_due': { text: 'Pagamento Pendente', class: 'bg-danger' },
      'cancelled': { text: 'Cancelado', class: 'bg-secondary' },
      'suspended': { text: 'Suspenso', class: 'bg-danger' }
    };

    const status = statusMap[sub.status] || { text: sub.status, class: 'bg-secondary' };
    badge.textContent = status.text;
    badge.className = 'badge ' + status.class;
  }

  function updateSubscriptionAlert() {
    const alert = document.getElementById('subscription-alert');
    if (!alert) return;

    const sub = state.currentSubscription;
    if (!sub) {
      alert.style.display = 'none';
      return;
    }

    let message = '';
    let alertClass = '';

    if (sub.status === 'trial') {
      const daysLeft = sub.trial_days_remaining || 0;
      message = `<i class="bi bi-hourglass-split me-2"></i>Você está no período de teste. Restam <strong>${daysLeft} dia${daysLeft !== 1 ? 's' : ''}</strong> para escolher um plano.`;
      alertClass = 'alert-trial';
    } else if (sub.status === 'past_due') {
      message = `<i class="bi bi-exclamation-triangle me-2"></i>Seu pagamento está pendente. <a href="#tab-historico" data-bs-toggle="tab">Ver detalhes</a>`;
      alertClass = 'alert-danger';
    } else if (sub.cancellation_scheduled_at) {
      const cancelDate = formatDate(sub.cancellation_scheduled_at);
      message = `<i class="bi bi-info-circle me-2"></i>Sua assinatura será cancelada em <strong>${cancelDate}</strong>.`;
      alertClass = 'alert-cancelled';
    }

    if (message) {
      alert.innerHTML = message;
      alert.className = 'alert mb-4 ' + alertClass;
      alert.style.display = 'block';
    } else {
      alert.style.display = 'none';
    }
  }

  function updateCancelledSubscriptionAlert() {
    const alertEl = document.getElementById('cancelled-subscription-alert');
    if (!alertEl) return;

    const sub = state.currentSubscription;
    const isCancelled = sub && sub.status === 'cancelled';

    if (isCancelled) {
      // Atualiza mensagem se houver data de término
      const msgEl = document.getElementById('cancelled-alert-message');
      if (msgEl && sub.cancellation_scheduled_at) {
        const endDate = formatDate(sub.cancellation_scheduled_at);
        msgEl.innerHTML = `Sua assinatura foi cancelada. O acesso às funcionalidades continuará até <strong>${endDate}</strong>.`;
      } else if (msgEl) {
        msgEl.textContent = 'Sua assinatura foi cancelada. O acesso às funcionalidades continuará até o fim do período já pago.';
      }
      alertEl.style.cssText = 'display: flex !important;';
    } else {
      alertEl.style.cssText = 'display: none !important;';
    }
  }

  function renderBillingHistory() {
    const tbody = document.getElementById('billing-history-body');
    if (!tbody) return;

    if (!state.billingHistory.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center py-4">
            <div class="empty-state py-3">
              <i class="bi bi-receipt" style="font-size: 2rem;"></i>
              <p class="mb-0 mt-2">Nenhum pagamento encontrado.</p>
            </div>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = state.billingHistory.map(payment => {
      const statusMap = {
        'paid': { text: 'Pago', class: 'paid', icon: 'check-circle' },
        'pending': { text: 'Pendente', class: 'pending', icon: 'clock' },
        'failed': { text: 'Falhou', class: 'failed', icon: 'x-circle' },
        'refunded': { text: 'Estornado', class: 'refunded', icon: 'arrow-counterclockwise' }
      };
      const status = statusMap[payment.status] || { text: payment.status, class: '', icon: 'question' };
      
      // Descrição com nome do plano
      const description = payment.plan_name 
        ? `Mensalidade - ${escapeHtml(payment.plan_name)}` 
        : escapeHtml(payment.description || 'Assinatura');

      return `
        <tr>
          <td>
            <span class="fw-medium">${formatDate(payment.created_at)}</span>
          </td>
          <td>
            <div class="d-flex flex-column">
              <span>${description}</span>
              <small class="text-muted">#${payment.order_id || payment.reference || '-'}</small>
            </div>
          </td>
          <td><strong class="text-success">${formatCurrency(payment.amount)}</strong></td>
          <td>
            <span class="invoice-status ${status.class}">
              <i class="bi bi-${status.icon}"></i> ${status.text}
            </span>
          </td>
          <td class="text-end">
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle btn-actions" 
                      type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <a class="dropdown-item text-primary btn-open-contest" href="#" 
                     data-order-id="${payment.order_id}" 
                     data-amount="${payment.amount}"
                     data-date="${formatDate(payment.created_at)}"
                     data-plan="${escapeHtml(payment.plan_name || 'Assinatura')}">
                    <i class="bi bi-chat-left-text me-2"></i> Contestação
                  </a>
                </li>
                ${payment.invoice_url ? `
                  <li>
                    <a class="dropdown-item" href="${payment.invoice_url}" target="_blank">
                      <i class="bi bi-download me-2"></i> Baixar Comprovante
                    </a>
                  </li>
                ` : ''}
              </ul>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  function renderBillingSummary(summary) {
    if (!summary) return;

    const lastPaid = document.getElementById('summary-last-paid');
    const totalPending = document.getElementById('summary-total-pending');
    const countPaid = document.getElementById('summary-count-paid');

    if (lastPaid) lastPaid.textContent = formatCurrency(summary.last_paid || 0);
    if (totalPending) totalPending.textContent = formatCurrency(summary.total_pending || 0);
    if (countPaid) countPaid.textContent = summary.count_paid || 0;
  }

  function renderPagination() {
    const container = document.getElementById('billing-pagination');
    if (!container || state.totalPages <= 1) {
      if (container) container.innerHTML = '';
      return;
    }

    let html = '<nav><ul class="pagination pagination-sm mb-0 justify-content-center">';
    
    // Previous
    html += `
      <li class="page-item ${state.currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${state.currentPage - 1}">&laquo;</a>
      </li>
    `;

    // Pages
    for (let i = 1; i <= state.totalPages; i++) {
      if (i === 1 || i === state.totalPages || (i >= state.currentPage - 2 && i <= state.currentPage + 2)) {
        html += `
          <li class="page-item ${i === state.currentPage ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
          </li>
        `;
      } else if (i === state.currentPage - 3 || i === state.currentPage + 3) {
        html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
      }
    }

    // Next
    html += `
      <li class="page-item ${state.currentPage === state.totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${state.currentPage + 1}">&raquo;</a>
      </li>
    `;

    html += '</ul></nav>';
    container.innerHTML = html;
  }

  // ==========================================
  // Event Handlers
  // ==========================================

  function setupEventListeners() {
    // Toggle Mensal/Anual
    const billingToggle = document.getElementById('billingToggle');
    if (billingToggle) {
      billingToggle.addEventListener('change', function() {
        state.isYearly = this.checked;
        updateBillingLabels();
        renderPlansGrid();
      });
    }

    // Filtro de histórico (Todos / Pagos / Pendentes)
    const historyFilterButtons = document.querySelectorAll('[data-history-filter]');
    if (historyFilterButtons && historyFilterButtons.length) {
      const applyActive = (value) => {
        historyFilterButtons.forEach((b) => b.classList.remove('active'));
        const match = Array.from(historyFilterButtons).find((b) => (b.dataset.historyFilter ?? '') === (value ?? ''));
        if (match) match.classList.add('active');
      };

      historyFilterButtons.forEach((btn) => {
        btn.addEventListener('click', function () {
          const value = this.dataset.historyFilter ?? '';
          state.historyFilter = value;
          applyActive(value);
          loadBillingHistory(1);
        });
      });

      applyActive(state.historyFilter);
    }

    // Paginação
    document.addEventListener('click', function(e) {
      if (e.target.closest('#billing-pagination .page-link')) {
        e.preventDefault();
        const page = parseInt(e.target.closest('.page-link').dataset.page);
        if (page && page !== state.currentPage && page >= 1 && page <= state.totalPages) {
          loadBillingHistory(page);
        }
      }
    });

    // Selecionar plano
    document.addEventListener('click', function(e) {
      if (e.target.closest('.btn-select-plan')) {
        const btn = e.target.closest('.btn-select-plan');
        const planId = btn.dataset.planId;
        const billing = btn.dataset.billing;
        openCheckoutModal(planId, billing);
      }
    });

    // Cancelar assinatura
    const btnCancel = document.getElementById('btn-cancel-subscription');
    if (btnCancel) {
      btnCancel.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
        modal.show();
      });
    }

    // Confirmar cancelamento
    const btnConfirmCancel = document.getElementById('btn-confirm-cancel');
    if (btnConfirmCancel) {
      btnConfirmCancel.addEventListener('click', handleCancelSubscription);
    }

    // Solicitar estorno
    const btnRefund = document.getElementById('btn-request-refund');
    if (btnRefund) {
      btnRefund.addEventListener('click', function() {
        loadRefundableOrders();
        const modal = new bootstrap.Modal(document.getElementById('refundModal'));
        modal.show();
      });
    }

    // Enviar estorno
    const btnSubmitRefund = document.getElementById('btn-submit-refund');
    if (btnSubmitRefund) {
      btnSubmitRefund.addEventListener('click', handleRefundRequest);
    }

    // Tab change - carrega dados quando necessário
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
      tab.addEventListener('shown.bs.tab', function(e) {
        const target = e.target.getAttribute('href');
        if (target === '#tab-historico' && !state.billingHistory.length) {
          loadBillingHistory();
        }
      });
    });

    // Abrir modal de contestação
    document.addEventListener('click', function(e) {
      if (e.target.closest('.btn-open-contest')) {
        e.preventDefault();
        const btn = e.target.closest('.btn-open-contest');
        const orderId = btn.dataset.orderId;
        const amount = parseFloat(btn.dataset.amount) || 0;
        const date = btn.dataset.date || '';
        const plan = btn.dataset.plan || 'Assinatura';
        openContestModal(orderId, amount, date, plan);
      }
    });

    // Submeter contestação
    const btnSubmitContest = document.getElementById('btn-submit-contest');
    if (btnSubmitContest) {
      btnSubmitContest.addEventListener('click', handleSubmitContest);
    }
    
    // Abrir modal de verificação PIX
    document.addEventListener('click', function(e) {
      if (e.target.closest('#btn-open-pix-verification-ticket')) {
        e.preventDefault();
        const btn = e.target.closest('#btn-open-pix-verification-ticket');
        const orderId = btn.dataset.orderId;
        if (orderId) {
          openPixVerificationModal(orderId);
        }
      }
    });
    
    // Submeter verificação PIX
    const btnSubmitPixVerification = document.getElementById('btn-submit-pix-verification');
    if (btnSubmitPixVerification) {
      btnSubmitPixVerification.addEventListener('click', handleSubmitPixVerification);
    }

    // Mostrar/esconder campo "outro motivo"
    const contestType = document.getElementById('contest-type');
    if (contestType) {
      contestType.addEventListener('change', function() {
        const otherGroup = document.getElementById('contest-other-group');
        if (otherGroup) {
          otherGroup.style.display = this.value === 'outro' ? 'block' : 'none';
        }
      });
    }
  }

  function updateBillingLabels() {
    const labels = document.querySelectorAll('.billing-label');
    if (labels.length >= 2) {
      labels[0].classList.toggle('active', !state.isYearly);
      labels[1].classList.toggle('active', state.isYearly);
    }
  }

  function openCheckoutModal(planId, billing) {
    const plan = state.plans.find(p => p.plan_id == planId);
    if (!plan) return;

    const modal = document.getElementById('checkoutModal');
    const title = document.getElementById('checkout-modal-title');
    const body = document.getElementById('checkout-modal-body');

    title.textContent = `Assinar ${plan.name}`;
    
    const price = billing === 'yearly' ? (plan.price_yearly || plan.price_monthly * 10) : plan.price_monthly;
    const period = billing === 'yearly' ? 'ano' : 'mês';

    body.innerHTML = `
      <div class="text-center mb-4">
        <h4>${escapeHtml(plan.name)}</h4>
        <p class="h2 mb-0">${formatCurrency(price)}<small class="text-muted">/${period}</small></p>
      </div>
      <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        Em breve você será redirecionado para o checkout seguro.
      </div>
      <div class="d-grid gap-2">
        <button class="btn btn-primary btn-lg" id="btn-proceed-checkout" 
                data-plan-id="${planId}" data-billing="${billing}">
          <i class="bi bi-lock me-2"></i>Prosseguir para Pagamento
        </button>
      </div>
    `;

    // Handler do botão de checkout
    document.getElementById('btn-proceed-checkout').addEventListener('click', function() {
      const planId = this.dataset.planId;
      const billing = this.dataset.billing;
      // Redirecionar para o checkout interno ou processar
      window.location.href = CONFIG.rootUrl + `conta/checkout.php?plan=${planId}&billing=${billing}`;
    });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  }

  async function handleCancelSubscription() {
    const reason = document.getElementById('cancel-reason').value;
    const btn = document.getElementById('btn-confirm-cancel');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processando...';

    // TODO: Implementar endpoint de cancelamento
    const result = await apiCall('subscription_cancel.php', {
      method: 'POST',
      body: JSON.stringify({ reason: reason })
    });

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Confirmar';

    if (result.success) {
      showToast('Assinatura cancelada. Você terá acesso até o final do período pago.', 'success');
      bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
      loadCurrentSubscription();
    } else {
      showToast(result.error || 'Erro ao cancelar. Tente novamente.', 'error');
    }
  }

  async function loadRefundableOrders() {
    const container = document.getElementById('refund-orders-list');
    if (!container) return;

    container.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div></div>';

    // Buscar últimos pagamentos elegíveis para estorno
    const result = await apiCall('billing_history.php?status=paid&per_page=5');
    
    if (result.success && result.data.payments?.length) {
      container.innerHTML = `
        <label class="form-label">Selecione o pagamento</label>
        <select class="form-select" id="refund-payment-id">
          <option value="">Selecione...</option>
          ${result.data.payments.map(p => `
            <option value="${p.payment_id}">${formatDate(p.created_at)} - ${formatCurrency(p.amount)}</option>
          `).join('')}
        </select>
      `;
    } else {
      container.innerHTML = `
        <div class="alert alert-warning mb-0">
          <i class="bi bi-exclamation-triangle me-1"></i>
          Nenhum pagamento elegível para estorno.
        </div>
      `;
    }
  }

  async function handleRefundRequest() {
    const paymentId = document.getElementById('refund-payment-id')?.value;
    const reason = document.getElementById('refund-reason').value;
    const details = document.getElementById('refund-details').value;
    const btn = document.getElementById('btn-submit-refund');

    if (!reason) {
      showToast('Selecione um motivo.', 'error');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';

    // TODO: Implementar endpoint de estorno
    const result = await apiCall('refund_request.php', {
      method: 'POST',
      body: JSON.stringify({
        payment_id: paymentId,
        reason: reason,
        details: details
      })
    });

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';

    if (result.success) {
      showToast('Solicitação enviada! Um ticket foi criado automaticamente.', 'success');
      bootstrap.Modal.getInstance(document.getElementById('refundModal')).hide();
    } else {
      showToast(result.error || 'Erro ao enviar. Tente novamente.', 'error');
    }
  }

  // ==========================================
  // Verificação PIX Manual
  // ==========================================
  
  function openPixVerificationModal(orderId) {
    const modal = document.getElementById('pixVerificationModal');
    if (!modal) return;
    
    // Preenche order_id
    document.getElementById('pix-order-id').value = orderId;
    
    // Limpa campos
    document.getElementById('pix-proof-file').value = '';
    document.getElementById('pix-description').value = '';
    
    // Mostra info do pedido (opcional)
    const orderInfo = document.getElementById('pix-order-info');
    const orderDetails = document.getElementById('pix-order-details');
    if (orderInfo && orderDetails) {
      orderDetails.innerHTML = `
        <div>
          <strong>Pedido:</strong> #${orderId}
        </div>
      `;
      orderInfo.style.display = 'block';
    }
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  }
  
  async function handleSubmitPixVerification() {
    const orderId = document.getElementById('pix-order-id').value;
    const proofFile = document.getElementById('pix-proof-file').files[0];
    const description = document.getElementById('pix-description').value.trim();
    const btn = document.getElementById('btn-submit-pix-verification');
    
    // Validação (arquivo é opcional)
    if (!orderId) {
      showToast('ID do pedido inválido.', 'error');
      return;
    }
    
    // Valida tamanho do arquivo se foi selecionado
    if (proofFile) {
      const maxSize = 5 * 1024 * 1024; // 5MB
      if (proofFile.size > maxSize) {
        showToast('Arquivo muito grande. Tamanho máximo: 5MB.', 'error');
        return;
      }
      
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
      if (!allowedTypes.includes(proofFile.type)) {
        showToast('Formato inválido. Use JPG, PNG ou PDF.', 'error');
        return;
      }
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
    
    // Prepara FormData para upload
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('description', description);
    if (proofFile) {
      formData.append('proof', proofFile);
    }
    
    try {
      const response = await fetch(CONFIG.apiBase + 'create_pix_verification_ticket.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });
      
      const result = await response.json();
      
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar para Verificação';
      
      if (result.success) {
        showToast(result.msg || 'Ticket criado com sucesso!', 'success');
        bootstrap.Modal.getInstance(document.getElementById('pixVerificationModal')).hide();
        
        // Redireciona para o ticket se houver
        if (result.ticket && result.ticket.id) {
          setTimeout(() => {
            window.location.href = CONFIG.rootUrl + 'conta/suporte/ticket?id=' + result.ticket.id;
          }, 1500);
        }
      } else {
        showToast(result.msg || result.message || 'Erro ao criar ticket.', 'error');
      }
    } catch (error) {
      console.error('[PIX Verification] Erro:', error);
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar para Verificação';
      showToast('Erro ao enviar. Tente novamente.', 'error');
    }
  }
  
  // ==========================================
  // Contestação (Abre Ticket)
  // ==========================================
  
  function openContestModal(orderId, amount, date, plan) {
    const modal = document.getElementById('contestModal');
    if (!modal) return;
    
    // Preenche dados do pedido
    document.getElementById('contest-order-id').value = orderId;
    
    // Exibe info do pedido
    const orderInfo = document.getElementById('contest-order-info');
    const orderDetails = document.getElementById('contest-order-details');
    if (orderInfo && orderDetails) {
      orderDetails.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
          <span><strong>${plan}</strong> - ${date}</span>
          <span class="fw-bold text-primary">${formatCurrency(amount)}</span>
        </div>
      `;
      orderInfo.style.display = 'block';
    }
    
    // Limpa campos
    document.getElementById('contest-type').value = '';
    document.getElementById('contest-title').value = '';
    document.getElementById('contest-description').value = '';
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  }
  
  async function handleSubmitContest() {
    const orderId = document.getElementById('contest-order-id').value;
    const contestType = document.getElementById('contest-type').value;
    const title = document.getElementById('contest-title').value.trim();
    const description = document.getElementById('contest-description').value.trim();
    const btn = document.getElementById('btn-submit-contest');
    
    // Validação
    if (!contestType) {
      showToast('Selecione o tipo de contestação.', 'error');
      return;
    }
    if (!title) {
      showToast('Informe o título do ticket.', 'error');
      return;
    }
    if (!description) {
      showToast('Descreva o problema em detalhes.', 'error');
      return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Abrindo ticket...';
    
    // Envia para criar ticket
    const result = await apiCall('create_contest_ticket.php', {
      method: 'POST',
      body: JSON.stringify({
        order_id: orderId,
        contest_type: contestType,
        title: title,
        description: description
      })
    });
    
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send me-1"></i> Abrir Ticket';
    
    if (result.success) {
      showToast('Ticket aberto com sucesso! Nossa equipe entrará em contato.', 'success');
      bootstrap.Modal.getInstance(document.getElementById('contestModal')).hide();
      
      // Redireciona para o ticket se houver ID
      if (result.ticket_id) {
        setTimeout(() => {
          window.location.href = CONFIG.rootUrl + 'conta/suporte/ticket?id=' + result.ticket_id;
        }, 1500);
      }
    } else {
      showToast(result.message || 'Erro ao abrir ticket. Tente novamente.', 'error');
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ==========================================
  // Inicialização
  // ==========================================

  async function init() {
    await showPaymentConfirmedModalIfNeeded();

    setupEventListeners();
    updateBillingLabels();
    
    // Carregar dados iniciais
    await Promise.all([
      loadPlans(),
      loadCurrentSubscription()
    ]);

    // Carregar histórico se estiver na tab
    if (CONFIG.currentTab === 'historico') {
      loadBillingHistory();
    }
  }

  // Inicializar quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
