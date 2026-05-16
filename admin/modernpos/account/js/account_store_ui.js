/*
 * account_store_ui.js
 * Modal de criação rápida de loja + (opcional) simulação via localStorage.
 * Compatível com AdminLTE 4 (Bootstrap 5).
 */
(function () {
  'use strict';

  const LS_KEY = 'modernpos.account.stores.simulated.v1';
  const LS_KEY_DEFAULTS = 'modernpos.account.store_defaults.v1';

  const BASE_DEFAULTS = {
    timezone: 'America/Sao_Paulo',
    invoice_edit_lifespan: '60',
    invoice_edit_lifespan_unit: 'minute',
    invoice_delete_lifespan: '60',
    invoice_delete_lifespan_unit: 'minute',
    after_sell_page: 'pos',
    remote_printing: '0',
    tax: '0',
    stock_alert_quantity: '5',
    datatable_item_limit: '25',
    invoice_footer_text: '',
    sound_effect: '1',
    reference_format: 'year_month_sequence',
    sales_reference_prefix: '',
    receipt_template: '1',
    invoice_view: 'standard',
    change_item_price_while_billing: '0',
    pos_product_display_limit: '30',
    sms_gateway: 'Twilio',
    expiry_yes: false,
    expiring_soon_alert_days: '7',
  };

  function safeJsonParse(str, fallback) {
    try {
      const v = JSON.parse(str);
      return v ?? fallback;
    } catch (e) {
      return fallback;
    }
  }

  function getSimulatedStores() {
    const arr = safeJsonParse(window.localStorage.getItem(LS_KEY), []);
    return Array.isArray(arr) ? arr : [];
  }

  function setSimulatedStores(stores) {
    window.localStorage.setItem(LS_KEY, JSON.stringify(stores || []));
  }

  function getAccountDefaults() {
    // Prefer defaults vindo do backend (salvos no banco)
    const server = window.MODERNPOS_ACCOUNT_DEFAULTS;
    const baseObj = (server && typeof server === 'object')
      ? server
      : safeJsonParse(window.localStorage.getItem(LS_KEY_DEFAULTS), {});

    const merged = {
      ...BASE_DEFAULTS,
      ...(baseObj && typeof baseObj === 'object' ? baseObj : {}),
    };

    // Migração leve (para quem abriu versões anteriores da UI)
    let changed = false;
    if (merged.sales_reference_prefix === 'SL') {
      merged.sales_reference_prefix = '';
      changed = true;
    }
    if (merged.sms_gateway === 'WhatsApp') {
      merged.sms_gateway = 'Twilio';
      changed = true;
    }

    if (changed) {
      setAccountDefaults(merged);
    }

    return merged;
  }

  function setAccountDefaults(nextDefaults) {
    const next = nextDefaults || {};
    // Mantém localStorage como fallback/offline
    window.localStorage.setItem(LS_KEY_DEFAULTS, JSON.stringify(next));
    // E também mantém em memória (server mode)
    window.MODERNPOS_ACCOUNT_DEFAULTS = next;
  }

  function slugify(str) {
    return String(str || '')
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 60);
  }

  function joinUrl(base, path) {
    const b = String(base || '');
    const p = String(path || '');
    if (!b) return p;
    return b.replace(/\/+$/, '/') + p.replace(/^\/+/, '');
  }

  function getAccountStoreApiUrl() {
    if (window.MODERNPOS_ACCOUNT_STORE_API) {
      return String(window.MODERNPOS_ACCOUNT_STORE_API);
    }
    return joinUrl(window.MODERNPOS_ROOT_URL || '', '_inc/account_store.php');
  }

  // =========================================================
  // Premium modal helpers (mesmo estilo de /conta/usuarios)
  // =========================================================
  function premiumOverlayByKey(key) {
    const k = String(key || '');
    if (!k) return null;
    return document.querySelector('.modal-premium-overlay[data-modal="' + k + '"]');
  }

  function abrirModalPremium(key) {
    const el = premiumOverlayByKey(key);
    if (!el) return;
    el.classList.add('active');
  }

  function fecharModalPremium(key) {
    const el = premiumOverlayByKey(key);
    if (!el) return;
    el.classList.remove('active');
  }

  // Expor no window para suportar onclick="fecharModalPremium('...')" nos partials
  if (typeof window.abrirModalPremium !== 'function') {
    window.abrirModalPremium = abrirModalPremium;
  }
  if (typeof window.fecharModalPremium !== 'function') {
    window.fecharModalPremium = fecharModalPremium;
  }

  function goToUpgradePlans() {
    const url = String(window.ACCOUNT_UPGRADE_URL || '');
    if (url) {
      window.location.href = url;
    }
  }
  if (typeof window.goToUpgradePlans !== 'function') {
    window.goToUpgradePlans = goToUpgradePlans;
  }

  // Modal: acesso negado (com redirect opcional)
  let accountAccessDeniedRedirectUrl = '';

  function openAccountAccessDeniedModal(message, redirectUrl) {
    const msgEl = document.getElementById('accountAccessDeniedMessage');
    if (msgEl && message) {
      msgEl.textContent = String(message);
    }

    accountAccessDeniedRedirectUrl = String(redirectUrl || '');
    abrirModalPremium('account-access-denied');
  }

  function closeAccountAccessDeniedModal() {
    fecharModalPremium('account-access-denied');
    const url = String(accountAccessDeniedRedirectUrl || '');
    accountAccessDeniedRedirectUrl = '';
    if (url) {
      window.location.href = url;
    }
  }

  window.openAccountAccessDeniedModal = openAccountAccessDeniedModal;
  window.closeAccountAccessDeniedModal = closeAccountAccessDeniedModal;

  function postAccountApiForm(action, formData) {
    const apiUrl = getAccountStoreApiUrl();
    const fd = formData instanceof window.FormData ? formData : new window.FormData();
    fd.set('action', String(action || ''));

    return fetch(apiUrl, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
      body: fd,
    }).then(function (res) {
      return res.json().catch(function () {
        return null;
      }).then(function (data) {
        // Se não conseguiu parsear JSON, trata como erro (mesmo com HTTP 200),
        // pois este endpoint SEMPRE deveria retornar JSON.
        if (data === null) {
          throw new Error('Resposta inválida do servidor (JSON). Verifique o log do PHP.');
        }

        if (!res.ok) {
          const msg = (data && (data.errorMsg || data.msg))
            ? (data.errorMsg || data.msg)
            : ('Erro ao processar a solicitação (HTTP ' + res.status + ').');
          const err = new Error(msg);
          // preserva código/payload do backend (ex.: LIMIT_REACHED)
          err.code = data?.code;
          err.data = data;
          err.httpStatus = res.status;
          throw err;
        }
        return data || {};
      });
    });
  }

  function labelForDefaultPref(key, value) {
    const v = String(value ?? '');

    if (key === 'remote_printing') {
      return v === '1' ? 'PHP Server' : 'Web Browser';
    }

    if (key === 'after_sell_page') {
      const map = {
        pos: 'POS',
        invoice: 'Fatura',
        receipt_in_new_window: 'Recibo em nova aba',
        receipt_in_popup: 'Recibo em popup',
        toastr_msg: 'Mensagem (toastr)',
        sweet_alert_msg: 'Mensagem (sweet alert)',
      };
      return map[v] || v;
    }

    if (key === 'invoice_edit_lifespan_unit' || key === 'invoice_delete_lifespan_unit') {
      if (v === 'day') return 'Dias';
      return v === 'second' ? 'Segundos' : 'Minutos';
    }

    if (key === 'sound_effect') {
      return v === '0' ? 'Inativo' : 'Ativo';
    }

    if (key === 'change_item_price_while_billing') {
      return v === '1' ? 'Sim' : 'Não';
    }

    if (key === 'expiry_yes') {
      return (value === true || v === '1') ? 'Ativo' : 'Inativo';
    }

    return v;
  }

  function applyDefaultPrefs(container) {
    if (!container) return;
    const defaults = getAccountDefaults();

    container.querySelectorAll('[data-account-default-pref]').forEach(el => {
      const key = el.getAttribute('data-account-default-pref');
      if (!key) return;

      const raw = defaults[key];
      if (typeof raw === 'undefined') return;

      // Checkbox
      if (el instanceof HTMLInputElement && el.type === 'checkbox') {
        el.checked = raw === true || String(raw) === '1';
        return;
      }

      // Select
      if (el instanceof HTMLSelectElement) {
        el.value = String(raw);
        if (el.value !== String(raw) && el.options && el.options.length) {
          // fallback
          el.selectedIndex = 0;
        }
        return;
      }

      // Input/text
      const display = labelForDefaultPref(key, raw);
      el.value = String(display);
    });
  }

  function readDefaultPrefsFrom(container) {
    const current = getAccountDefaults();
    const next = { ...current };

    container.querySelectorAll('[data-account-default-pref]').forEach(el => {
      const key = el.getAttribute('data-account-default-pref');
      if (!key) return;

      if (el instanceof HTMLInputElement && el.type === 'checkbox') {
        next[key] = !!el.checked;
        return;
      }

      if (el instanceof HTMLSelectElement) {
        next[key] = String(el.value || '');
        return;
      }

      next[key] = String(el.value || '');
    });

    return next;
  }

  function showToast(opts) {
    const title = opts?.title || 'ModernPOS';
    const message = opts?.message || '';
    const variant = opts?.variant || 'primary'; // primary, success, warning, danger, info

    let container = document.getElementById('accountToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'accountToastContainer';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1080';
      document.body.appendChild(container);
    }

    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + variant + ' border-0';
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.setAttribute('aria-atomic', 'true');

    el.innerHTML =
      '<div class="d-flex">' +
      '  <div class="toast-body">' +
      '    <div class="fw-semibold">' + title + '</div>' +
      '    <div class="small opacity-75">' + message + '</div>' +
      '  </div>' +
      '  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>' +
      '</div>';

    container.appendChild(el);

    if (window.bootstrap && window.bootstrap.Toast) {
      const t = new window.bootstrap.Toast(el, { delay: 3500 });
      t.show();
      el.addEventListener('hidden.bs.toast', function () {
        el.remove();
      });
    }
  }

  function loadAccountDefaultsFromBackend() {
    // Carrega os defaults do banco para preencher previews e o formulário de padrões
    return postAccountApiForm('get_defaults', new window.FormData())
      .then(function (data) {
        if (data && data.preference && typeof data.preference === 'object') {
          // Mantém em memória + fallback local
          setAccountDefaults(data.preference);
        }
      })
      .catch(function () {
        // silencioso: fallback localStorage
      });
  }

  function renderSimulatedRowsInStoresTable() {
    const tbody = document.querySelector('table[data-account-stores-table] tbody');
    if (!tbody) return;

    // remove rows anteriores simuladas
    tbody.querySelectorAll('tr[data-simulated="1"]').forEach(tr => tr.remove());

    const simulated = getSimulatedStores();
    simulated.forEach(st => {
      const tr = document.createElement('tr');
      tr.setAttribute('data-simulated', '1');
      tr.className = 'table-light';

      const createdAt = st.created_at ? new Date(st.created_at) : new Date();
      const createdLabel = createdAt.toLocaleString('pt-BR', { hour12: false });

      const editUrl = joinUrl(
        window.MODERNPOS_ROOT_URL || '',
        'conta/lojas/editar?sim=1&store_id=' + encodeURIComponent(st.id)
      );

      tr.innerHTML =
        '<td>#' + st.id + '</td>' +
        '<td>' +
        '  <div class="d-flex flex-column">' +
        '    <span class="fw-semibold">' + escapeHtml(st.name) + '</span>' +
        '    <small class="text-secondary">Simulação</small>' +
        '  </div>' +
        '</td>' +
        '<td>' + (st.type || 'Loja') + '</td>' +
        '<td><span class="badge bg-success">Ativa</span></td>' +
        '<td>' + createdLabel + '</td>' +
        '<td class="text-end">' +
        '  <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Simulação">' +
        '    <i class="bi bi-box-arrow-in-right"></i> Entrar' +
        '  </button>' +
        '  <a class="btn btn-outline-primary btn-sm ms-2" href="' + editUrl + '" data-simulated-edit="1" data-store-id="' + st.id + '">' +
        '    <i class="bi bi-pencil"></i> Editar' +
        '  </a>' +
        '</td>';

      tbody.appendChild(tr);
    });

    // contador no título, se existir
    const totalEl = document.querySelector('[data-account-stores-count]');
    if (totalEl) {
      const base = parseInt(totalEl.getAttribute('data-base-count') || '0', 10) || 0;
      totalEl.textContent = String(base + simulated.length);
    }
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function wireStoreEditForm() {
    const saveBtn = document.querySelector('[data-account-store-edit-save="1"]');
    const formEl = document.querySelector('form[data-account-store-edit-form="1"]');
    const titleEl = document.querySelector('[data-account-store-edit-title="1"]');

    if (!saveBtn || !formEl) return;

    const url = new URL(window.location.href);
    const storeId = parseInt(url.searchParams.get('store_id') || '0', 10) || 0;
    const isSim = url.searchParams.get('sim') === '1';

    function fillFormFromStore(st) {
      if (!st) return;
      const set = (name, value) => {
        const input = formEl.querySelector('[name="' + name + '"]');
        if (input) input.value = value || '';
      };
      set('name', st.name);
      set('code_name', st.code_name);
      set('country', st.country || 'BR');
      set('mobile', st.mobile || st.phone);
      set('email', st.email);
      set('zip_code', st.zip_code || st.zip);
      set('address', st.address);
      set('vat_reg_no', st.vat_reg_no);
      set('preference[gst_reg_no]', st.gst_reg_no);
      set('cashier_id', st.cashier_id);
    }

    if (isSim && storeId) {
      const simulated = getSimulatedStores();
      const st = simulated.find(s => String(s.id) === String(storeId));
      fillFormFromStore(st);
      if (titleEl && st?.name) titleEl.textContent = st.name;
    }

    saveBtn.addEventListener('click', function () {
      const nameInput = formEl.querySelector('input[name="name"]');
      const name = String(nameInput?.value || '').trim();

      if (!name) {
        if (nameInput) {
          nameInput.classList.add('is-invalid');
          nameInput.focus();
        }
        return;
      }
      if (nameInput) nameInput.classList.remove('is-invalid');

      if (isSim && storeId) {
        const stores = getSimulatedStores();
        const idx = stores.findIndex(s => String(s.id) === String(storeId));
        if (idx >= 0) {
          const fd = new window.FormData(formEl);
          stores[idx] = {
            ...stores[idx],
            name: String(fd.get('name') || '').trim(),
            code_name: String(fd.get('code_name') || stores[idx].code_name || '').trim(),
            country: String(fd.get('country') || 'BR').trim(),
            mobile: String(fd.get('mobile') || '').trim(),
            email: String(fd.get('email') || '').trim(),
            zip_code: String(fd.get('zip_code') || '').trim(),
            address: String(fd.get('address') || '').trim(),
            vat_reg_no: String(fd.get('vat_reg_no') || '').trim(),
            gst_reg_no: String(fd.get('preference[gst_reg_no]') || '').trim(),
            cashier_id: String(fd.get('cashier_id') || '').trim(),
          };
          setSimulatedStores(stores);
          if (titleEl) titleEl.textContent = stores[idx].name;
        }

        showToast({
          title: 'Loja atualizada (simulação)',
          message: 'Alterações salvas apenas no navegador (localStorage).',
          variant: 'success',
        });
        return;
      }

      const fd = new window.FormData(formEl);
      postAccountApiForm('update', fd)
        .then(function () {
          if (titleEl) titleEl.textContent = name;
          showToast({
            title: 'Loja atualizada',
            message: 'Alterações salvas com sucesso.',
            variant: 'success',
          });
        })
        .catch(function (err) {
          showToast({
            title: 'Erro ao salvar',
            message: String(err?.message || 'Não foi possível salvar as alterações.'),
            variant: 'danger',
          });
        });
    });
  }

  function wireStoreDefaultsForm() {
    const saveBtn = document.querySelector('[data-account-store-defaults-save="1"]');
    const formEl = document.querySelector('form[data-account-store-defaults-form="1"]');

    if (!saveBtn || !formEl) return;

    // prefill (será chamado novamente após carregar defaults do backend)
    applyDefaultPrefs(formEl);

    saveBtn.addEventListener('click', function () {
      const next = readDefaultPrefsFrom(formEl);

      // reforça IMPOSTO = 0 se vier vazio
      if (!next.tax && next.tax !== '0') {
        next.tax = '0';
      }

      const fd = new window.FormData();
      fd.set('defaults_json', JSON.stringify({ preference: next }));

      postAccountApiForm('save_defaults', fd)
        .then(function () {
          // mantém em memória + fallback
          setAccountDefaults(next);

          // atualiza os previews na página (se existirem)
          applyDefaultPrefs(document);

          showToast({
            title: 'Padrões salvos',
            message: 'Configurações padrão foram salvas no banco e serão usadas em novas lojas.',
            variant: 'success',
          });
        })
        .catch(function (err) {
          // fallback: ainda salva no navegador para não perder o trabalho
          setAccountDefaults(next);
          applyDefaultPrefs(document);

          showToast({
            title: 'Não foi possível salvar no banco',
            message: String(err?.message || 'Salvo apenas no navegador (fallback).'),
            variant: 'warning',
          });
        });
    });
  }

  function wireStoreExtrasForm() {
    const saveBtns = Array.from(document.querySelectorAll('[data-account-store-extras-save="1"]'));
    const formEl = document.querySelector('form[data-account-store-extras-form="1"]');

    if (!saveBtns.length || !formEl) return;

    const url = new URL(window.location.href);
    const storeId = url.searchParams.get('store_id');
    const isSim = url.searchParams.get('sim') === '1';

    function toArray(v) {
      if (!v) return [];
      return Array.isArray(v) ? v : [v];
    }

    function readFormSnapshot() {
      const fd = new window.FormData(formEl);
      const keys = Array.from(new Set(Array.from(fd.keys())));
      const snap = {};
      keys.forEach(k => {
        if (k.endsWith('[]')) {
          snap[k] = fd.getAll(k).map(x => String(x));
        } else {
          snap[k] = String(fd.get(k) ?? '');
        }
      });
      return snap;
    }

    function applySnapshot(snap) {
      if (!snap || typeof snap !== 'object') return;

      Object.keys(snap).forEach(name => {
        const val = snap[name];
        const el = formEl.querySelector('[name="' + cssEscape(name) + '"]');
        if (!el) return;

        if (el instanceof HTMLSelectElement && el.multiple) {
          const values = toArray(val).map(String);
          Array.from(el.options).forEach(opt => {
            opt.selected = values.includes(String(opt.value));
          });
          return;
        }

        if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement) {
          el.value = Array.isArray(val) ? (val[0] || '') : String(val);
        }
      });
    }

    // prefill (sim)
    if (isSim && storeId) {
      const stores = getSimulatedStores();
      const st = stores.find(s => String(s.id) === String(storeId));

      // título (se existir)
      const titleEl = document.querySelector('[data-account-store-extras-title="1"]');
      if (titleEl && st?.name) {
        titleEl.textContent = st.name;
      }

      if (st && st.extras_form) {
        applySnapshot(st.extras_form);
      }
    }

    saveBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        if (isSim && storeId) {
          const stores = getSimulatedStores();
          const idx = stores.findIndex(s => String(s.id) === String(storeId));
          if (idx >= 0) {
            stores[idx] = { ...stores[idx], extras_form: readFormSnapshot() };
            setSimulatedStores(stores);
          }

          showToast({
            title: 'Configurações salvas (simulação)',
            message: 'Salvo apenas no navegador (localStorage).',
            variant: 'success',
          });
          return;
        }

        const fd = new window.FormData(formEl);
        postAccountApiForm('update_extras', fd)
          .then(function () {
            showToast({
              title: 'Configurações salvas',
              message: 'E-mail/FTP foram atualizados com sucesso.',
              variant: 'success',
            });
          })
          .catch(function (err) {
            showToast({
              title: 'Erro ao salvar',
              message: String(err?.message || 'Não foi possível salvar as configurações.'),
              variant: 'danger',
            });
          });
      });
    });
  }

  function cssEscape(str) {
    // escape mínimo para seletor [name="..."]
    return String(str || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function wireStoreLogoUpload() {
    const input = document.querySelector('[data-account-store-logo-input="1"]');
    const img = document.querySelector('[data-account-store-logo-preview="1"]');
    const empty = document.querySelector('[data-account-store-logo-empty="1"]');
    const removeBtn = document.querySelector('[data-account-store-logo-remove="1"]');

    if (!input || !img) return;

    const url = new URL(window.location.href);
    const storeId = url.searchParams.get('store_id');
    const isSim = url.searchParams.get('sim') === '1';

    function setPreview(dataUrl) {
      if (dataUrl) {
        img.src = dataUrl;
        img.style.display = 'block';
        if (empty) empty.style.display = 'none';
      } else {
        img.removeAttribute('src');
        img.style.display = 'none';
        if (empty) empty.style.display = 'flex';
      }
    }

    // prefill (sim)
    if (isSim && storeId) {
      const stores = getSimulatedStores();
      const st = stores.find(s => String(s.id) === String(storeId));
      if (st && st.logo_data_url) {
        setPreview(st.logo_data_url);
      }
    }

    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      if (!file) return;

      if (!file.type || !file.type.startsWith('image/')) {
        showToast({ title: 'Logo inválida', message: 'Escolha um arquivo de imagem (PNG/JPG).', variant: 'warning' });
        input.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        const dataUrl = String(e?.target?.result || '');
        setPreview(dataUrl);

        if (isSim && storeId) {
          const stores = getSimulatedStores();
          const idx = stores.findIndex(s => String(s.id) === String(storeId));
          if (idx >= 0) {
            stores[idx] = { ...stores[idx], logo_data_url: dataUrl };
            setSimulatedStores(stores);
          }
          showToast({ title: 'Logo atualizada (simulação)', message: 'A logo foi salva apenas no navegador.', variant: 'success' });
          return;
        }

        showToast({ title: 'Logo selecionada', message: 'Quando conectarmos ao backend, essa logo será enviada e salva.', variant: 'info' });
      };
      reader.readAsDataURL(file);
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        input.value = '';
        setPreview('');

        if (isSim && storeId) {
          const stores = getSimulatedStores();
          const idx = stores.findIndex(s => String(s.id) === String(storeId));
          if (idx >= 0) {
            stores[idx] = { ...stores[idx] };
            delete stores[idx].logo_data_url;
            setSimulatedStores(stores);
          }
          showToast({ title: 'Logo removida (simulação)', message: 'Removida apenas no navegador.', variant: 'success' });
          return;
        }

        showToast({ title: 'Remover logo', message: 'Quando conectarmos ao backend, isso removerá a logo da loja.', variant: 'info' });
      });
    }
  }

  function applyPhoneMask(input) {
    if (!input) return;
    input.addEventListener('input', function (e) {
      let val = String(e.target.value || '').replace(/\D/g, '');
      if (val.length > 11) val = val.substr(0, 11);
      
      if (val.length <= 10) {
        // (00) 0000-0000
        val = val.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
      } else {
        // (00) 00000-0000
        val = val.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
      }
      e.target.value = val;
    });
  }

  function applyCepMask(input) {
    if (!input) return;
    input.addEventListener('input', function (e) {
      let val = String(e.target.value || '').replace(/\D/g, '');
      if (val.length > 8) val = val.substr(0, 8);
      val = val.replace(/(\d{5})(\d{0,3})/, '$1-$2');
      e.target.value = val;
    });
  }

  function wireCreateStoreModal() {
    const modalEl = document.getElementById('accountCreateStoreModal');
    const formEl = document.getElementById('accountCreateStoreForm');
    const submitBtn = document.getElementById('accountCreateStoreSubmit');

    if (!modalEl || !formEl || !submitBtn) return;

    function showCreateModal() {
      abrirModalPremium('create-store');

      // foco no nome
      window.setTimeout(function () {
        const nameInput = formEl.querySelector('input[name="name"]');
        if (nameInput) nameInput.focus();
      }, 50);
    }

    function hideCreateModal() {
      fecharModalPremium('create-store');
    }

    // Aplica máscaras nos campos de telefone e CEP
    const mobileInput = formEl.querySelector('input[name="mobile"]');
    const zipInput = formEl.querySelector('input[name="zip_code"]');
    applyPhoneMask(mobileInput);
    applyCepMask(zipInput);

    function getStoreLimitState() {
      const used = Number(window.ACCOUNT_STORES_USED ?? 0);
      const max = Number(window.ACCOUNT_STORES_MAX ?? 0);
      const isOwnerOrAdmin = Boolean(window.ACCOUNT_IS_OWNER_OR_ADMIN);
      const upgradeUrl = String(window.ACCOUNT_UPGRADE_URL || '');
      return { used, max, isOwnerOrAdmin, upgradeUrl };
    }

    function openStoresAdminOnlyModal(message) {
      const el = document.getElementById('storesAdminOnlyMessage');
      if (el && message) {
        el.textContent = String(message);
      }
      abrirModalPremium('stores-admin-only');
    }

    function openUpgradeStoresModal(payload) {
      const info = payload && typeof payload === 'object'
        ? { used: Number(payload.used ?? 0), max: Number(payload.max ?? 0) }
        : { used: Number(window.ACCOUNT_STORES_USED ?? 0), max: Number(window.ACCOUNT_STORES_MAX ?? 0) };

      const titleEl = document.getElementById('upgradeStoresModalTitle');
      const msgEl = document.getElementById('upgradeStoresModalMessage');
      const usageEl = document.getElementById('upgradeStoresModalUsage');

      if (titleEl) titleEl.textContent = 'Limite de lojas atingido';
      if (msgEl) msgEl.textContent = 'Você atingiu o limite de lojas do seu plano e não pode criar novas lojas no momento.';
      if (usageEl) usageEl.textContent = info.max > 0 ? (String(info.used) + '/' + String(info.max)) : String(info.used);

      abrirModalPremium('upgrade-stores');
    }

    function guardCanCreateStore() {
      const st = getStoreLimitState();

      if (!st.isOwnerOrAdmin) {
        openStoresAdminOnlyModal('Somente Administrador ou Owner pode criar lojas.');
        return false;
      }

      if (st.max > 0 && st.used >= st.max) {
        openUpgradeStoresModal({ used: st.used, max: st.max });
        return false;
      }

      return true;
    }

    // Botões que abrem a modal (em /conta e /conta/lojas)
    document.querySelectorAll('[data-open-create-store-modal="1"]').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();

        if (!guardCanCreateStore()) {
          return;
        }

        // Reset state
        try { formEl.reset(); } catch (_) {}
        formEl.classList.remove('was-validated');

        importState = { enabled: false, source_store_id: '', product_ids: [] };
        updateImportUI();

        if (logoInput) logoInput.value = '';
        setCreateLogoPreview('');
        setCreateLogoMeta(null);

        showCreateModal();
      });
    });

    function setLoading(isLoading) {
      submitBtn.disabled = isLoading;
      submitBtn.innerHTML = isLoading
        ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Criando...'
        : '<span class="me-1"><i class="bi bi-plus-circle"></i></span>Criar loja';
    }

    let selectedLogoDataUrl = '';
    let selectedLogoFileName = '';

    // Preview de logo na modal
    const logoDropzone = modalEl.querySelector('[data-account-create-store-logo-dropzone="1"]');
    const logoPickBtn = modalEl.querySelector('[data-account-create-store-logo-pick="1"]');
    const logoRemoveBtn = modalEl.querySelector('[data-account-create-store-logo-remove="1"]');
    const logoFileNameEl = modalEl.querySelector('[data-account-create-store-logo-filename="1"]');

    const logoInput = modalEl.querySelector('[data-account-create-store-logo-input="1"]');
    const logoPreview = modalEl.querySelector('[data-account-create-store-logo-preview="1"]');
    const logoEmpty = modalEl.querySelector('[data-account-create-store-logo-empty="1"]');

    const MAX_LOGO_BYTES = 2 * 1024 * 1024; // 2MB

    function formatBytes(bytes) {
      const b = Number(bytes || 0);
      if (!b) return '0 B';
      const units = ['B', 'KB', 'MB', 'GB'];
      const i = Math.min(Math.floor(Math.log(b) / Math.log(1024)), units.length - 1);
      const val = b / Math.pow(1024, i);
      return (i === 0 ? val.toFixed(0) : val.toFixed(1)) + ' ' + units[i];
    }

    function setCreateLogoMeta(file) {
      selectedLogoFileName = file?.name ? String(file.name) : '';

      if (logoFileNameEl) {
        logoFileNameEl.textContent = file ? (String(file.name) + ' • ' + formatBytes(file.size)) : '';
      }

      if (logoRemoveBtn) {
        logoRemoveBtn.style.display = file ? 'inline-block' : 'none';
      }
    }

    function handleCreateLogoFile(file) {
      if (!file) {
        setCreateLogoPreview('');
        setCreateLogoMeta(null);
        return;
      }

      if (!file.type || !file.type.startsWith('image/')) {
        showToast({ title: 'Logo inválida', message: 'Escolha um arquivo de imagem (PNG/JPG).', variant: 'warning' });
        if (logoInput) logoInput.value = '';
        setCreateLogoPreview('');
        setCreateLogoMeta(null);
        return;
      }

      if (file.size && file.size > MAX_LOGO_BYTES) {
        showToast({ title: 'Arquivo muito grande', message: 'Use uma imagem de até 2MB.', variant: 'warning' });
        if (logoInput) logoInput.value = '';
        setCreateLogoPreview('');
        setCreateLogoMeta(null);
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        setCreateLogoPreview(String(e?.target?.result || ''));
        setCreateLogoMeta(file);
      };
      reader.readAsDataURL(file);
    }

    // Importação de produtos
    // (abre a segunda modal e, ao aplicar, grava a seleção em hidden inputs)
    const importOpenTrigger = modalEl.querySelector('[data-account-create-store-import-open="1"]');
    const importSummaryEl = modalEl.querySelector('[data-account-create-store-import-summary="1"]');
    const importSourceHidden = formEl.querySelector('input[name="import_source_store_id"]');
    const importProductsHidden = formEl.querySelector('input[name="import_product_ids"]');

    const importModalEl = document.getElementById('accountImportProductsModal');
    const importStoreSelect = importModalEl ? importModalEl.querySelector('[data-account-import-source-store="1"]') : null;
    const importApplyBtn = importModalEl ? importModalEl.querySelector('[data-account-import-products-apply="1"]') : null;
    const importBackBtn = importModalEl ? importModalEl.querySelector('[data-account-import-back="1"]') : null;
    const importCloseBtn = importModalEl ? importModalEl.querySelector('[data-account-import-close="1"]') : null;
    const importSearchInput = importModalEl ? importModalEl.querySelector('[data-account-import-products-search="1"]') : null;
    const importSelectAll = importModalEl ? importModalEl.querySelector('[data-account-import-products-select-all="1"]') : null;
    const importProductsListEl = importModalEl ? importModalEl.querySelector('[data-account-import-products-list="1"]') : null;

    let importState = {
      enabled: false,
      source_store_id: '',
      product_ids: [],
    };

    function getImportProductCheckboxes() {
      if (!importModalEl) return [];
      return Array.from(importModalEl.querySelectorAll('[data-account-import-product="1"]'));
    }

    function setImportProductsMessage(text) {
      if (!importProductsListEl) return;
      importProductsListEl.innerHTML = '<div class="list-group-item small text-secondary">' + escapeHtml(text) + '</div>';
    }

    function renderImportProducts(products) {
      if (!importProductsListEl) return;

      const arr = Array.isArray(products) ? products : [];
      if (!arr.length) {
        setImportProductsMessage('Nenhum produto encontrado para esta loja.');
        return;
      }

      const selected = new Set((importState.product_ids || []).map(String));
      importProductsListEl.innerHTML = '';

      arr.forEach(function (p) {
        const id = String(p?.id ?? '');
        const name = String(p?.name ?? '');
        const sku = String(p?.sku ?? '');
        const category = String(p?.category ?? '');

        const label = document.createElement('label');
        label.className = 'list-group-item d-flex gap-2';

        const chk = document.createElement('input');
        chk.className = 'form-check-input flex-shrink-0';
        chk.type = 'checkbox';
        chk.value = id;
        chk.setAttribute('data-account-import-product', '1');
        chk.checked = selected.has(id);
        chk.addEventListener('change', function () {
          updateSelectAllState();
        });

        const wrapper = document.createElement('span');
        const title = document.createElement('span');
        title.className = 'fw-semibold';
        title.textContent = name || ('Produto #' + id);

        const meta = document.createElement('span');
        meta.className = 'd-block small text-secondary';
        const metaParts = [];
        if (sku) metaParts.push('SKU: ' + sku);
        if (category) metaParts.push('Categoria: ' + category);
        if (!metaParts.length) metaParts.push('ID: ' + id);
        meta.textContent = metaParts.join(' • ');

        wrapper.appendChild(title);
        wrapper.appendChild(meta);

        label.appendChild(chk);
        label.appendChild(wrapper);

        importProductsListEl.appendChild(label);
      });
    }

    function loadImportProductsForStore(storeId) {
      if (!importModalEl || !importProductsListEl) {
        return;
      }

      const sid = String(storeId || '').trim();
      if (!sid) {
        setImportProductsMessage('Selecione uma loja de origem para carregar os produtos.');
        if (importSearchInput) importSearchInput.value = '';
        updateSelectAllState();
        return;
      }


      setImportProductsMessage('Carregando produtos...');

      const fd = new window.FormData();
      fd.set('store_id', sid);

      postAccountApiForm('list_products', fd)
        .then(function (data) {
          renderImportProducts(data?.products || []);

          // reset filtro
          if (importSearchInput) {
            importSearchInput.value = '';
            const q = '';
            importModalEl.querySelectorAll('.list-group-item').forEach(item => {
              const text = String(item.textContent || '').toLowerCase();
              item.style.display = text.includes(q) ? '' : 'none';
            });
          }

          updateSelectAllState();
        })
        .catch(function (err) {
          setImportProductsMessage('Erro ao carregar produtos: ' + String(err?.message || '')); 
          updateSelectAllState();
        });
    }

    let importStoresCache = [];

    function fetchImportStores() {
      const fd = new window.FormData();
      return postAccountApiForm('list_stores', fd)
        .then(function (data) {
          importStoresCache = Array.isArray(data?.stores) ? data.stores : [];
          return importStoresCache;
        });
    }

    function renderImportStoreOptions() {
      if (!importStoreSelect) return;

      const stores = Array.isArray(importStoresCache) ? importStoresCache : [];
      importStoreSelect.innerHTML = '';

      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = 'Selecione a loja de origem';
      importStoreSelect.appendChild(opt0);

      stores.forEach(function (s) {
        const opt = document.createElement('option');
        opt.value = String(s?.id ?? '');
        opt.textContent = String(s?.name ?? ('Loja #' + String(s?.id ?? '')));
        importStoreSelect.appendChild(opt);
      });

      if (importState.source_store_id) {
        importStoreSelect.value = String(importState.source_store_id);
      }
    }

    function storeNameById(id) {
      const stores = Array.isArray(importStoresCache) ? importStoresCache : [];
      const found = stores.find(s => String(s?.id) === String(id));
      return found ? String(found.name || '') : ('#' + String(id));
    }

    function setImportHiddenFields() {
      if (importSourceHidden) importSourceHidden.value = importState.enabled ? String(importState.source_store_id || '') : '';
      if (importProductsHidden) importProductsHidden.value = importState.enabled ? JSON.stringify(importState.product_ids || []) : '';
    }

    function updateImportSummary() {
      if (!importSummaryEl) return;

      if (!importState.enabled) {
        importSummaryEl.textContent = 'Nenhum produto selecionado.';
        return;
      }

      if (!importState.source_store_id) {
        importSummaryEl.textContent = 'Selecione a loja de origem.';
        return;
      }

      const count = (importState.product_ids || []).length;
      if (!count) {
        importSummaryEl.textContent =
          'Selecione os produtos para importar da loja ' + storeNameById(importState.source_store_id) + '.';
        return;
      }

      importSummaryEl.textContent =
        'Importar ' + count + ' produto(s) da loja ' + storeNameById(importState.source_store_id) + '.';
    }

    function updateImportUI() {
      setImportHiddenFields();
      updateImportSummary();
    }

    function getVisibleProductCheckboxes() {
      if (!importModalEl) return [];
      return Array.from(importModalEl.querySelectorAll('.list-group-item'))
        .filter(item => item.style.display !== 'none')
        .map(item => item.querySelector('[data-account-import-product="1"]'))
        .filter(Boolean);
    }

    function updateSelectAllState() {
      if (!importSelectAll) return;

      const visible = getVisibleProductCheckboxes();
      if (!visible.length) {
        importSelectAll.checked = false;
        importSelectAll.indeterminate = false;
        return;
      }

      const checkedCount = visible.filter(chk => chk.checked).length;
      importSelectAll.checked = checkedCount === visible.length;
      importSelectAll.indeterminate = checkedCount > 0 && checkedCount < visible.length;
    }

    function closeImportModalToCreate() {
      fecharModalPremium('import-products');
      updateImportUI();
      showCreateModal();
    }

    function openImportModal() {
      if (!importModalEl) return;

      // Evita duas overlays ao mesmo tempo
      hideCreateModal();

      setImportProductsMessage('Carregando lojas...');

      fetchImportStores()
        .then(function () {
          renderImportStoreOptions();

          // Pré-seleciona a loja (se ainda não tiver)
          if (!importState.source_store_id && Array.isArray(importStoresCache) && importStoresCache.length) {
            importState.source_store_id = String(importStoresCache[0].id);
          }
          if (importStoreSelect && importState.source_store_id) {
            importStoreSelect.value = String(importState.source_store_id);
          }

          // Carrega produtos da loja selecionada
          loadImportProductsForStore(String(importStoreSelect?.value || importState.source_store_id || ''));

          updateSelectAllState();
          abrirModalPremium('import-products');
        })
        .catch(function (err) {
          showToast({
            title: 'Importar produtos',
            message: String(err?.message || 'Não foi possível carregar suas lojas.'),
            variant: 'danger',
          });

          // Se falhou, volta para a modal principal
          showCreateModal();
        });
    }

    if (importBackBtn) {
      importBackBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeImportModalToCreate();
      });
    }

    if (importCloseBtn) {
      importCloseBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeImportModalToCreate();
      });
    }

    if (importModalEl) {
      importModalEl.addEventListener('click', function (e) {
        if (e.target === importModalEl) {
          closeImportModalToCreate();
        }
      });
    }

    function setCreateLogoPreview(dataUrl) {
      selectedLogoDataUrl = dataUrl || '';
      if (logoPreview && logoEmpty) {
        if (selectedLogoDataUrl) {
          logoPreview.src = selectedLogoDataUrl;
          logoPreview.style.display = 'block';
          logoEmpty.style.display = 'none';
        } else {
          logoPreview.removeAttribute('src');
          logoPreview.style.display = 'none';
          logoEmpty.style.display = 'flex';
        }
      }
    }

    if (logoPickBtn && logoInput) {
      logoPickBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        logoInput.click();
      });
    }

    if (logoRemoveBtn && logoInput) {
      logoRemoveBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        logoInput.value = '';
        handleCreateLogoFile(null);
      });
    }

    if (logoDropzone && logoInput) {
      logoDropzone.addEventListener('click', function (e) {
        // evita abrir file picker quando clicar nos botões
        const target = e.target;
        if (target && (target.closest('[data-account-create-store-logo-pick="1"]') || target.closest('[data-account-create-store-logo-remove="1"]'))) {
          return;
        }
        logoInput.click();
      });

      const addHover = () => {
        logoDropzone.classList.add('border-primary');
        logoDropzone.classList.add('bg-white');
      };
      const removeHover = () => {
        logoDropzone.classList.remove('border-primary');
        logoDropzone.classList.remove('bg-white');
      };

      ['dragenter', 'dragover'].forEach(evt => {
        logoDropzone.addEventListener(evt, function (e) {
          e.preventDefault();
          e.stopPropagation();
          addHover();
        });
      });

      ['dragleave', 'dragend', 'drop'].forEach(evt => {
        logoDropzone.addEventListener(evt, function (e) {
          e.preventDefault();
          e.stopPropagation();
          removeHover();
        });
      });

      logoDropzone.addEventListener('drop', function (e) {
        const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (!file) return;

        // tenta preencher o input (para futura integração com backend)
        try {
          const dt = new DataTransfer();
          dt.items.add(file);
          logoInput.files = dt.files;
        } catch (err) {
          // ignore
        }

        handleCreateLogoFile(file);
      });
    }

    if (logoInput) {
      logoInput.addEventListener('change', function () {
        const file = logoInput.files && logoInput.files[0];
        handleCreateLogoFile(file);
      });
    }

    // Wire: importar produtos (clique no campo "Importa Produtos")
    if (importOpenTrigger) {
      importOpenTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        openImportModal();
      });
    }

    if (importStoreSelect) {
      importStoreSelect.addEventListener('change', function () {
        importState.source_store_id = String(importStoreSelect.value || '');
        importState.product_ids = [];
        loadImportProductsForStore(importStoreSelect.value);
        updateImportUI();
      });
    }

    if (importSearchInput && importModalEl) {
      importSearchInput.addEventListener('input', function () {
        const q = String(importSearchInput.value || '').trim().toLowerCase();
        importModalEl.querySelectorAll('.list-group-item').forEach(item => {
          const text = String(item.textContent || '').toLowerCase();
          item.style.display = text.includes(q) ? '' : 'none';
        });
        updateSelectAllState();
      });
    }

    // Select all (aplica apenas aos itens visíveis no filtro)
    if (importSelectAll) {
      importSelectAll.addEventListener('change', function () {
        const visible = getVisibleProductCheckboxes();
        visible.forEach(chk => {
          chk.checked = !!importSelectAll.checked;
        });
        updateSelectAllState();
      });
    }

    // Atualiza o estado do select-all ao clicar em um produto
    if (importModalEl) {
      getImportProductCheckboxes().forEach(chk => {
        chk.addEventListener('change', function () {
          updateSelectAllState();
        });
      });
    }

    if (importApplyBtn) {
      importApplyBtn.addEventListener('click', function () {
        const sourceId = String(importStoreSelect?.value || '').trim();
        const products = getImportProductCheckboxes()
          .filter(chk => chk.checked)
          .map(chk => String(chk.value))
          .filter(Boolean);

        if (!sourceId) {
          showToast({ title: 'Importar produtos', message: 'Selecione a loja de origem.', variant: 'warning' });
          return;
        }
        if (!products.length) {
          showToast({ title: 'Importar produtos', message: 'Selecione ao menos 1 produto.', variant: 'warning' });
          return;
        }

        importState = { enabled: true, source_store_id: sourceId, product_ids: products };
        updateImportUI();

        fecharModalPremium('import-products');
        showCreateModal();
      });
    }


    function readFormData() {
      const fd = new window.FormData(formEl);
      return {
        name: String(fd.get('name') || '').trim(),
        country: String(fd.get('country') || 'BR').trim(),
        zip_code: String(fd.get('zip_code') || '').trim(),
        mobile: String(fd.get('mobile') || '').trim(),
        email: String(fd.get('email') || '').trim(),
        address: String(fd.get('address') || '').trim(),
      };
    }

    function uploadStoreLogo(storeId, file) {
      const url = joinUrl(window.MODERNPOS_ROOT_URL || '', '_inc/upload_logo.php');
      const fd = new window.FormData();
      fd.append('store_id', String(storeId));
      fd.append('file', file);

      return fetch(url, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
      }).then(function (res) {
        return res.text().then(function (text) {
          const lower = String(text || '').toLowerCase();
          if (!res.ok) {
            throw new Error('Erro ao enviar a logo.');
          }
          if (lower.includes('invalid')) {
            throw new Error('Logo inválida (tipo/tamanho).');
          }
          return text;
        });
      });
    }

    submitBtn.addEventListener('click', function () {
      // Regra: não deixa nem enviar se estiver bloqueado por role/limite
      if (!guardCanCreateStore()) {
        return;
      }

      // Validação nativa (Bootstrap)
      formEl.classList.add('was-validated');
      updateImportUI();

      if (!formEl.checkValidity()) {
        showToast({
          title: 'Campos obrigatórios',
          message: 'Preencha Nome, Telefone, E-mail, CEP e Endereço.',
          variant: 'warning',
        });
        return;
      }

      const data = readFormData();
      setLoading(true);

      const fd = new window.FormData(formEl);

      // Se houver importação selecionada, envia também como array (para o backend)
      if (importState && importState.source_store_id && Array.isArray(importState.product_ids) && importState.product_ids.length) {
        fd.set('import_source_store_id', String(importState.source_store_id));
        fd.set('import_product_ids', JSON.stringify(importState.product_ids));
        importState.product_ids.forEach(function (pid) {
          fd.append('product_ids[]', String(pid));
        });
      } else {
        fd.set('import_source_store_id', '');
        fd.set('import_product_ids', '');
      }

      postAccountApiForm('create', fd)
        .then(function (resp) {
          const createdId = Number(resp?.id || 0);
          const logoFile = logoInput && logoInput.files && logoInput.files[0];

          if (createdId > 0 && logoFile) {
            return uploadStoreLogo(createdId, logoFile)
              .then(function () {
                showToast({ title: 'Logo enviada', message: 'Logo salva com sucesso.', variant: 'success' });
                return resp;
              })
              .catch(function (err) {
                showToast({ title: 'Loja criada', message: 'A loja foi criada, mas a logo falhou: ' + String(err?.message || ''), variant: 'warning' });
                return resp;
              });
          }

          return resp;
        })
        .then(function () {
          setLoading(false);
          hideCreateModal();

          showToast({
            title: 'Loja criada',
            message: '"' + data.name + '" foi criada com sucesso.',
            variant: 'success',
          });

          // Recarrega para atualizar a lista de lojas
          window.location.reload();
        })
        .catch(function (err) {
          setLoading(false);

          // Trata códigos do backend
          const code = err?.code || err?.data?.code;
          if (code === 'ADMIN_ONLY') {
            hideCreateModal();
            openStoresAdminOnlyModal(String(err?.message || 'Somente Administrador ou Owner pode criar lojas.'));
            return;
          }

          if (code === 'LIMIT_REACHED') {
            const used = Number(err?.data?.used ?? window.ACCOUNT_STORES_USED ?? 0);
            const max = Number(err?.data?.max ?? window.ACCOUNT_STORES_MAX ?? 0);

            hideCreateModal();
            openUpgradeStoresModal({ used, max });
            return;
          }

          showToast({
            title: 'Erro ao criar loja',
            message: String(err?.message || 'Não foi possível criar a loja.'),
            variant: 'danger',
          });
        });
    });
  }

  function init() {
    if (!window.bootstrap) return;

    // Carrega defaults do backend (se disponível), depois preenche a UI
    loadAccountDefaultsFromBackend().then(function () {
      // Preenche qualquer área da página que exiba padrões (inputs/selects com data-account-default-pref)
      applyDefaultPrefs(document);

      wireCreateStoreModal();
      wireStoreEditForm();
      wireStoreLogoUpload();
      wireStoreDefaultsForm();
      wireStoreExtrasForm();
      renderSimulatedRowsInStoresTable();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
