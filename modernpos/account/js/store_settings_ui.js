/*
 * store_settings_ui.js
 * Página /conta/lojas/configuracoes (AdminLTE 4)
 * - Carrega e salva configurações reais via _inc/account_store.php
 * - Upload de logo separado via _inc/upload_logo.php
 */
(function () {
  'use strict';

  const API_URL = (window.MODERNPOS_ACCOUNT_STORE_API || (window.MODERNPOS_ROOT_URL ? (window.MODERNPOS_ROOT_URL + '_inc/account_store.php') : null));
  const UPLOAD_LOGO_URL = (window.MODERNPOS_ROOT_URL ? (window.MODERNPOS_ROOT_URL + '_inc/upload_logo.php') : null);

  function ensureToastContainer() {
    let container = document.getElementById('mpStoreSettingsToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'mpStoreSettingsToastContainer';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1080';
      document.body.appendChild(container);
    }
    return container;
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function showToast(opts) {
    const title = opts?.title || 'ModernPOS';
    const message = opts?.message || '';
    const variant = opts?.variant || 'primary';

    const container = ensureToastContainer();
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + variant + ' border-0';
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.setAttribute('aria-atomic', 'true');

    el.innerHTML =
      '<div class="d-flex">' +
      '  <div class="toast-body">' +
      '    <div class="fw-semibold">' + escapeHtml(title) + '</div>' +
      '    <div class="small opacity-75">' + escapeHtml(message) + '</div>' +
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

  function slugify(str) {
    return String(str || '')
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 60);
  }

  function getStoreId() {
    const hidden = document.querySelector('[data-store-settings-store-id="1"]');
    const v = hidden ? parseInt(hidden.value || '0', 10) : 0;
    return Number.isFinite(v) ? v : 0;
  }

  function qsParam(name) {
    try {
      const u = new URL(window.location.href);
      return u.searchParams.get(name);
    } catch (_) {
      return null;
    }
  }

  function setSelectValue(el, value) {
    if (!el) return;
    const v = value === null || value === undefined ? '' : String(value);
    el.value = v;
  }

  function setInputValue(el, value) {
    if (!el) return;
    el.value = value === null || value === undefined ? '' : String(value);
  }

  function logoSetPreviewUrl(url) {
    const img = document.querySelector('[data-store-settings-logo-preview="1"]');
    const empty = document.querySelector('[data-store-settings-logo-empty="1"]');
    const removeBtn = document.querySelector('[data-store-settings-logo-remove="1"]');

    if (!img) return;

    if (url) {
      img.src = String(url);
      img.style.display = 'block';
      if (empty) empty.style.display = 'none';
      if (removeBtn) removeBtn.style.display = 'inline-block';
    } else {
      img.removeAttribute('src');
      img.style.display = 'none';
      if (empty) empty.style.display = 'flex';
      if (removeBtn) removeBtn.style.display = 'none';
    }
  }

  async function apiPostForm(formData) {
    if (!API_URL) {
      throw new Error('API não configurada.');
    }

    // Debug: log the store_id being sent
    const storeIdValue = formData.get('store_id');
    console.log('[store_settings_ui] Enviando requisição com store_id:', storeIdValue);

    const resp = await fetch(API_URL, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });

    const json = await resp.json().catch(() => null);
    if (!resp.ok) {
      const msg = json?.errorMsg || 'Erro na requisição.';
      console.error('[store_settings_ui] Erro HTTP ' + resp.status + ':', msg);
      console.error('[store_settings_ui] Response JSON:', json);
      throw new Error(msg);
    }

    if (json && json.errorMsg) {
      throw new Error(json.errorMsg);
    }

    return json;
  }

  async function loadStoreSettings() {
    const storeId = getStoreId() || parseInt(qsParam('store_id') || '0', 10) || 0;

    if (!storeId) {
      showToast({ title: 'Selecione uma loja', message: 'Volte em “Gerenciar lojas” e clique em Editar.', variant: 'warning' });
      return;
    }

    const fd = new FormData();
    fd.append('action', 'store_settings_get');
    fd.append('store_id', String(storeId));

    const data = await apiPostForm(fd);
    const s = data?.store;
    if (!s) throw new Error('Resposta inválida do servidor.');

    // Navbar
    const nameEl = document.querySelector('[data-store-settings-store-name="1"]');
    if (nameEl) nameEl.textContent = s.name || '';

    // Store fields
    setInputValue(document.querySelector('[data-store-settings-name="1"]'), s.name);
    setInputValue(document.querySelector('[data-store-settings-code-name="1"]'), s.code_name);
    setInputValue(document.querySelector('[data-store-settings-mobile="1"]'), s.mobile);
    setInputValue(document.querySelector('[data-store-settings-email="1"]'), s.email);
    setInputValue(document.querySelector('[data-store-settings-zip-code="1"]'), s.zip_code);
    setInputValue(document.querySelector('[data-store-settings-address="1"]'), s.address);
    setInputValue(document.querySelector('[data-store-settings-vat-reg-no="1"]'), s.vat_reg_no);

    const countrySel = document.getElementById('store-country');
    setSelectValue(countrySel, s.country);

    // POS store columns
    setSelectValue(document.querySelector('[data-store-settings-remote-printing="1"]'), String(s.remote_printing ?? 0));
    setSelectValue(document.querySelector('[data-store-settings-receipt-printer="1"]'), String(s.receipt_printer ?? ''));
    setSelectValue(document.querySelector('[data-store-settings-auto-print="1"]'), String(s.auto_print ?? 0));
    setSelectValue(document.querySelector('[data-store-settings-sound-effect="1"]'), String(s.sound_effect ?? 0));

    // Preference
    const p = s.preference || {};
    setSelectValue(document.querySelector('[data-store-settings-timezone="1"]'), p.timezone);
    setInputValue(document.querySelector('[data-store-settings-tax="1"]'), p.tax);

    setInputValue(document.querySelector('[data-store-settings-invoice-edit-lifespan="1"]'), p.invoice_edit_lifespan);
    setSelectValue(document.querySelector('[data-store-settings-invoice-edit-lifespan-unit="1"]'), p.invoice_edit_lifespan_unit);

    setInputValue(document.querySelector('[data-store-settings-invoice-delete-lifespan="1"]'), p.invoice_delete_lifespan);
    setSelectValue(document.querySelector('[data-store-settings-invoice-delete-lifespan-unit="1"]'), p.invoice_delete_lifespan_unit);

    setInputValue(document.querySelector('[data-store-settings-stock-alert-quantity="1"]'), p.stock_alert_quantity);
    setInputValue(document.querySelector('[data-store-settings-datatable-item-limit="1"]'), p.datatable_item_limit);

    setSelectValue(document.querySelector('[data-store-settings-reference-format="1"]'), p.reference_format);
    setInputValue(document.querySelector('[data-store-settings-sales-reference-prefix="1"]'), p.sales_reference_prefix);
    setSelectValue(document.querySelector('[data-store-settings-receipt-template="1"]'), p.receipt_template);

    setInputValue(document.querySelector('[data-store-settings-pos-product-display-limit="1"]'), p.pos_product_display_limit);
    setSelectValue(document.querySelector('[data-store-settings-after-sell-page="1"]'), p.after_sell_page);
    setSelectValue(document.querySelector('[data-store-settings-change-item-price="1"]'), String(p.change_item_price_while_billing ?? '0'));
    setInputValue(document.querySelector('[data-store-settings-invoice-footer-text="1"]'), p.invoice_footer_text);

    // Logo
    if (s.logo_url) {
      logoSetPreviewUrl(s.logo_url);
    }

    // Re-apply printer requirement UI
    applyRemotePrintingUI();
  }

  function collectPayload() {
    const storeId = getStoreId();
    const name = document.querySelector('[data-store-settings-name="1"]')?.value || '';

    const payload = {
      store_id: storeId,
      name,
      code_name: document.querySelector('[data-store-settings-code-name="1"]')?.value || slugify(name),
      country: document.getElementById('store-country')?.value || '',
      mobile: document.querySelector('[data-store-settings-mobile="1"]')?.value || '',
      email: document.querySelector('[data-store-settings-email="1"]')?.value || '',
      zip_code: document.querySelector('[data-store-settings-zip-code="1"]')?.value || '',
      address: document.querySelector('[data-store-settings-address="1"]')?.value || '',
      vat_reg_no: document.querySelector('[data-store-settings-vat-reg-no="1"]')?.value || '',
      remote_printing: document.querySelector('[data-store-settings-remote-printing="1"]')?.value || '0',
      receipt_printer: document.querySelector('[data-store-settings-receipt-printer="1"]')?.value || '',
      auto_print: document.querySelector('[data-store-settings-auto-print="1"]')?.value || '0',
      sound_effect: document.querySelector('[data-store-settings-sound-effect="1"]')?.value || '0',
      preference: {
        timezone: document.querySelector('[data-store-settings-timezone="1"]')?.value || '',
        tax: document.querySelector('[data-store-settings-tax="1"]')?.value || '0',
        invoice_edit_lifespan: document.querySelector('[data-store-settings-invoice-edit-lifespan="1"]')?.value || '0',
        invoice_edit_lifespan_unit: document.querySelector('[data-store-settings-invoice-edit-lifespan-unit="1"]')?.value || 'minute',
        invoice_delete_lifespan: document.querySelector('[data-store-settings-invoice-delete-lifespan="1"]')?.value || '0',
        invoice_delete_lifespan_unit: document.querySelector('[data-store-settings-invoice-delete-lifespan-unit="1"]')?.value || 'minute',
        stock_alert_quantity: document.querySelector('[data-store-settings-stock-alert-quantity="1"]')?.value || '0',
        datatable_item_limit: document.querySelector('[data-store-settings-datatable-item-limit="1"]')?.value || '25',
        reference_format: document.querySelector('[data-store-settings-reference-format="1"]')?.value || 'year_month_sequence',
        sales_reference_prefix: document.querySelector('[data-store-settings-sales-reference-prefix="1"]')?.value || '',
        receipt_template: document.querySelector('[data-store-settings-receipt-template="1"]')?.value || '',
        pos_product_display_limit: document.querySelector('[data-store-settings-pos-product-display-limit="1"]')?.value || '0',
        after_sell_page: document.querySelector('[data-store-settings-after-sell-page="1"]')?.value || 'pos',
        change_item_price_while_billing: document.querySelector('[data-store-settings-change-item-price="1"]')?.value || '0',
        invoice_footer_text: document.querySelector('[data-store-settings-invoice-footer-text="1"]')?.value || '',
      },
    };

    return payload;
  }

  async function saveStoreSettings() {
    const storeId = getStoreId();
    if (!storeId) {
      showToast({ title: 'Loja não selecionada', message: 'Volte em “Gerenciar lojas” e clique em Editar.', variant: 'warning' });
      return;
    }

    const payload = collectPayload();

    const fd = new FormData();
    fd.append('action', 'store_settings_save');
    fd.append('store_id', String(payload.store_id));
    fd.append('name', payload.name);
    fd.append('code_name', payload.code_name);
    fd.append('country', payload.country);
    fd.append('mobile', payload.mobile);
    fd.append('email', payload.email);
    fd.append('zip_code', payload.zip_code);
    fd.append('address', payload.address);
    fd.append('vat_reg_no', payload.vat_reg_no);
    fd.append('remote_printing', String(payload.remote_printing));
    fd.append('receipt_printer', String(payload.receipt_printer));
    fd.append('auto_print', String(payload.auto_print));
    fd.append('sound_effect', String(payload.sound_effect));

    Object.keys(payload.preference || {}).forEach((k) => {
      fd.append('preference[' + k + ']', String(payload.preference[k] ?? ''));
    });

    await apiPostForm(fd);
    showToast({ title: 'Configurações salvas', message: 'Alterações registradas no banco com sucesso.', variant: 'success' });
  }

  function formatBytes(bytes) {
    const b = Number(bytes || 0);
    if (!b) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(b) / Math.log(1024)), units.length - 1);
    const val = b / Math.pow(1024, i);
    return (i === 0 ? val.toFixed(0) : val.toFixed(1)) + ' ' + units[i];
  }

  function wireAutoCodeName() {
    const nameInput = document.querySelector('[data-store-settings-name="1"]');
    const codeInput = document.querySelector('[data-store-settings-code-name="1"]');
    if (!nameInput || !codeInput) return;

    nameInput.addEventListener('input', function () {
      if (codeInput.dataset.manuallyEdited === '1') return;
      codeInput.value = slugify(nameInput.value);
    });

    codeInput.addEventListener('input', function () {
      codeInput.dataset.manuallyEdited = '1';
    });
  }

  function wireLogoPreview() {
    const dropzone = document.querySelector('[data-store-settings-logo-dropzone="1"]');
    const input = document.querySelector('[data-store-settings-logo-input="1"]');
    const img = document.querySelector('[data-store-settings-logo-preview="1"]');
    const empty = document.querySelector('[data-store-settings-logo-empty="1"]');
    const pickBtn = document.querySelector('[data-store-settings-logo-pick="1"]');
    const removeBtn = document.querySelector('[data-store-settings-logo-remove="1"]');
    const filenameEl = document.querySelector('[data-store-settings-logo-filename="1"]');

    if (!dropzone || !input || !img) return;

    const MAX_LOGO_BYTES = 2 * 1024 * 1024;

    function setMeta(file) {
      if (!filenameEl) return;
      filenameEl.textContent = file ? (String(file.name) + ' • ' + formatBytes(file.size)) : '';
    }

    function handleFile(file) {
      if (!file) {
        setMeta(null);
        return;
      }

      if (!file.type || !file.type.startsWith('image/')) {
        showToast({ title: 'Logo inválida', message: 'Escolha um arquivo de imagem (PNG/JPG).', variant: 'warning' });
        input.value = '';
        setMeta(null);
        return;
      }

      if (file.size && file.size > MAX_LOGO_BYTES) {
        showToast({ title: 'Arquivo muito grande', message: 'Use uma imagem de até 2MB.', variant: 'warning' });
        input.value = '';
        setMeta(null);
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        logoSetPreviewUrl(String(e?.target?.result || ''));
        setMeta(file);
      };
      reader.readAsDataURL(file);
    }

    if (pickBtn) {
      pickBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        input.click();
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        input.value = '';
        setMeta(null);
        logoSetPreviewUrl('');
        showToast({ title: 'Logo removida (tela)', message: 'A logo foi removida apenas da visualização. Use “Enviar logo” para atualizar no servidor.', variant: 'info' });
      });
    }

    dropzone.addEventListener('click', function (e) {
      const target = e.target;
      if (target && (target.closest('[data-store-settings-logo-pick="1"]') || target.closest('[data-store-settings-logo-remove="1"]') || target.closest('[data-store-settings-logo-upload="1"]'))) {
        return;
      }
      input.click();
    });

    input.addEventListener('change', function () {
      const file = input.files && input.files[0];
      handleFile(file);
    });

    // drag & drop
    const addHover = () => {
      dropzone.classList.add('border-primary');
      dropzone.classList.add('bg-white');
    };
    const removeHover = () => {
      dropzone.classList.remove('border-primary');
      dropzone.classList.remove('bg-white');
    };

    ['dragenter', 'dragover'].forEach(evt => {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        addHover();
      });
    });

    ['dragleave', 'dragend', 'drop'].forEach(evt => {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        removeHover();
      });
    });

    dropzone.addEventListener('drop', function (e) {
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!file) return;

      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
      } catch (_) {
        // ignore
      }

      handleFile(file);
    });
  }

  async function uploadLogo() {
    const storeId = getStoreId();
    if (!storeId) {
      showToast({ title: 'Loja não selecionada', message: 'Volte em “Gerenciar lojas” e clique em Editar.', variant: 'warning' });
      return;
    }

    if (!UPLOAD_LOGO_URL) {
      showToast({ title: 'Upload indisponível', message: 'URL de upload não configurada.', variant: 'warning' });
      return;
    }

    const input = document.querySelector('[data-store-settings-logo-input="1"]');
    const file = input && input.files && input.files[0];
    if (!file) {
      showToast({ title: 'Selecione uma imagem', message: 'Escolha uma logo antes de enviar.', variant: 'warning' });
      return;
    }

    const fd = new FormData();
    fd.append('file', file);
    fd.append('store_id', String(storeId));

    const btn = document.querySelector('[data-store-settings-logo-upload="1"]');
    const oldHtml = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando…';
    }

    try {
      const resp = await fetch(UPLOAD_LOGO_URL, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      const text = await resp.text();

      if (!resp.ok) {
        throw new Error('Falha ao enviar a logo.');
      }

      const ok = /success/i.test(text) || /uploaded/i.test(text);
      if (!ok) {
        throw new Error('Não foi possível enviar a logo.');
      }

      showToast({ title: 'Logo enviada', message: 'A logo foi atualizada no servidor.', variant: 'success' });

      // Recarrega do BD para pegar a URL final (cache bust)
      await loadStoreSettings();

    } catch (e) {
      showToast({ title: 'Erro ao enviar logo', message: String(e?.message || e), variant: 'danger' });
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
      }
    }
  }

  function applyRemotePrintingUI() {
    const remoteSelect = document.querySelector('[data-store-settings-remote-printing="1"]');
    const printerWrap = document.querySelector('[data-store-settings-printer-wrap="1"]');
    if (!remoteSelect || !printerWrap) return;

    const isPhpServer = String(remoteSelect.value || '0') === '1';
    printerWrap.classList.toggle('mp-printer-required', isPhpServer);
  }

  function wireRemotePrinting() {
    const remoteSelect = document.querySelector('[data-store-settings-remote-printing="1"]');
    if (!remoteSelect) return;
    remoteSelect.addEventListener('change', applyRemotePrintingUI);
    applyRemotePrintingUI();
  }

  function wireActions() {
    // Save
    document.querySelectorAll('[data-store-settings-save="1"]').forEach(btn => {
      btn.addEventListener('click', async function () {
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Salvando…';
        try {
          await saveStoreSettings();
        } catch (e) {
          showToast({ title: 'Erro ao salvar', message: String(e?.message || e), variant: 'danger' });
        } finally {
          btn.disabled = false;
          btn.innerHTML = oldHtml;
        }
      });
    });

    // Upload logo
    const uploadBtn = document.querySelector('[data-store-settings-logo-upload="1"]');
    if (uploadBtn) {
      uploadBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        uploadLogo();
      });
    }
  }

  async function init() {
    // Se não há store_id, a própria página mostra o aviso + redireciona.
    if (!getStoreId()) {
      return;
    }

    if (!API_URL) {
      showToast({ title: 'Erro', message: 'API do ModernPOS não configurada nesta página.', variant: 'danger' });
      return;
    }

    wireAutoCodeName();
    wireLogoPreview();
    wireRemotePrinting();
    wireActions();

    try {
      await loadStoreSettings();
    } catch (e) {
      showToast({ title: 'Erro ao carregar', message: String(e?.message || e), variant: 'danger' });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
