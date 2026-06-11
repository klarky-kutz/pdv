// ========================================
// STORES.JS - ModernPOS
// (modo DEMO sem banco de dados: localStorage)
// ========================================

// Estado global
let currentEditStoreId = null;
let currentConfigStoreId = null;

const STORES_MODE = (window.MODERNPOS_STORES_MODE || 'demo').toLowerCase();
const STORE_LS_KEY = 'modernpos.demo.stores.v1';

// ========================================
// UTILITY FUNCTIONS
// ========================================

function ensureToastContainer() {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  return container;
}

function showToast(type, message) {
  const container = ensureToastContainer();
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;

  const iconMap = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>'
  };

  toast.innerHTML = `
    <div class="toast-icon">${iconMap[type] || ''}</div>
    <div class="toast-message">${message}</div>
    <button class="toast-close" type="button" onclick="this.parentElement.remove()">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 5000);
}

function setButtonLoading(button, loading) {
  if (!button) return;
  if (loading) {
    button.disabled = true;
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = '<div class="spinner"></div> Salvando...';
  } else {
    button.disabled = false;
    button.innerHTML = button.dataset.originalText || button.innerHTML;
  }
}

function slugify(str) {
  return (str || '')
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s]/g, '')
    .replace(/\s+/g, '_')
    .replace(/^_+|_+$/g, '')
    .substring(0, 60);
}

function safeJsonParse(str, fallback) {
  try {
    return JSON.parse(str);
  } catch (_) {
    return fallback;
  }
}

function getNowIso() {
  return new Date().toISOString();
}

function getNextId(items) {
  const maxId = (items || []).reduce((max, s) => Math.max(max, Number(s.id || 0)), 0);
  return maxId + 1;
}

function uniqueCodeName(baseCodeName, stores, excludeId) {
  const base = slugify(baseCodeName) || 'loja';
  const exists = (code) => stores.some(s => String(s.code_name).toLowerCase() === String(code).toLowerCase() && Number(s.id) !== Number(excludeId));
  if (!exists(base)) return base;
  let i = 2;
  while (exists(`${base}_${i}`)) i++;
  return `${base}_${i}`;
}

function formatStoreAddress(store) {
  const parts = [];
  const addr = store.address || '';
  const num = store.number ? String(store.number).trim() : '';
  const comp = store.complement ? String(store.complement).trim() : '';

  let line1 = addr;
  if (num) line1 = `${line1}, ${num}`;
  if (comp) line1 = `${line1} (${comp})`;

  if (line1.trim()) parts.push(line1.trim());

  const bairro = store.bairro ? String(store.bairro).trim() : '';
  const city = store.city ? String(store.city).trim() : '';
  const state = store.state ? String(store.state).trim() : '';

  const line2 = [bairro, city && state ? `${city}/${state}` : (city || state)].filter(Boolean).join(' - ');
  if (line2.trim()) parts.push(line2.trim());

  return parts.join(' • ');
}

// ========================================
// DEMO STORE REPOSITORY (localStorage)
// ========================================

const StoreRepo = {
  list() {
    const raw = localStorage.getItem(STORE_LS_KEY);
    const stores = safeJsonParse(raw, []);
    return Array.isArray(stores) ? stores : [];
  },

  save(stores) {
    localStorage.setItem(STORE_LS_KEY, JSON.stringify(stores || []));
  },

  ensureSeed() {
    const stores = this.list();
    if (stores.length > 0) return;

    const seed = [
      {
        id: 1,
        name: 'Loja Centro',
        code_name: 'loja_centro',
        status: 1,
        country: 'BR',
        mobile: '(11) 98765-4321',
        email: 'centro@exemplo.com',
        zip_code: '01310-000',
        address: 'Av. Paulista',
        number: '1000',
        complement: '',
        bairro: 'Bela Vista',
        city: 'São Paulo',
        state: 'SP',
        vat_reg_no: '',
        remote_printing: 0,
        auto_print: 0,
        sound_effect: 1,
        timezone: 'America/Sao_Paulo',
        sort_order: 0,
        preference: {
          after_sale_page: 'pos',
          show_stock: 1,
          show_item_code: 1,
          show_customer: 1,
          show_invoice_note: 0,
          show_currency: 1,
          pos_background: '',
          gst_reg_no: '',
          tax_method: 'exclusive',
          tax: 0,
          receipt_template: 'default',
          webhook_url: '',
          ecommerce_sync: 0,
          delivery_integration: 0,
          whatsapp_notifications: 0,
        },
        created_at: getNowIso(),
        updated_at: getNowIso(),
      },
      {
        id: 2,
        name: 'Loja Shopping',
        code_name: 'loja_shopping',
        status: 1,
        country: 'BR',
        mobile: '(11) 91234-5678',
        email: 'shopping@exemplo.com',
        zip_code: '04538-132',
        address: 'Av. Brg. Faria Lima',
        number: '2232',
        complement: 'Loja 12',
        bairro: 'Itaim Bibi',
        city: 'São Paulo',
        state: 'SP',
        vat_reg_no: '',
        remote_printing: 1,
        auto_print: 0,
        sound_effect: 1,
        timezone: 'America/Sao_Paulo',
        sort_order: 1,
        preference: {
          after_sale_page: 'sales_list',
          show_stock: 1,
          show_item_code: 1,
          show_customer: 1,
          show_invoice_note: 1,
          show_currency: 1,
          pos_background: 'dark.png',
          gst_reg_no: '',
          tax_method: 'exclusive',
          tax: 0,
          receipt_template: 'modern',
          webhook_url: '',
          ecommerce_sync: 1,
          delivery_integration: 0,
          whatsapp_notifications: 1,
        },
        created_at: getNowIso(),
        updated_at: getNowIso(),
      },
      {
        id: 3,
        name: 'Loja Depósito',
        code_name: 'loja_deposito',
        status: 0,
        country: 'BR',
        mobile: '',
        email: '',
        zip_code: '',
        address: 'Rua das Flores',
        number: '50',
        complement: '',
        bairro: 'Centro',
        city: 'Campinas',
        state: 'SP',
        vat_reg_no: '',
        remote_printing: 0,
        auto_print: 0,
        sound_effect: 1,
        timezone: 'America/Sao_Paulo',
        sort_order: 2,
        preference: {
          after_sale_page: 'pos',
          show_stock: 1,
          show_item_code: 1,
          show_customer: 1,
          show_invoice_note: 0,
          show_currency: 1,
          pos_background: '',
          gst_reg_no: '',
          tax_method: 'exclusive',
          tax: 0,
          receipt_template: 'compact',
          webhook_url: '',
          ecommerce_sync: 0,
          delivery_integration: 0,
          whatsapp_notifications: 0,
        },
        created_at: getNowIso(),
        updated_at: getNowIso(),
      },
    ];

    this.save(seed);
  },

  getById(id) {
    const stores = this.list();
    return stores.find(s => Number(s.id) === Number(id)) || null;
  },

  create(store) {
    const stores = this.list();
    const id = getNextId(stores);
    const now = getNowIso();
    const newStore = {
      id,
      status: 1,
      country: 'BR',
      mobile: '',
      email: '',
      zip_code: '',
      address: '',
      number: '',
      complement: '',
      bairro: '',
      city: '',
      state: '',
      vat_reg_no: '',
      remote_printing: 0,
      auto_print: 0,
      sound_effect: 1,
      timezone: 'America/Sao_Paulo',
      sort_order: id,
      preference: {},
      created_at: now,
      updated_at: now,
      ...store,
    };
    stores.push(newStore);
    this.save(stores);
    return newStore;
  },

  update(id, patch) {
    const stores = this.list();
    const idx = stores.findIndex(s => Number(s.id) === Number(id));
    if (idx < 0) return null;
    stores[idx] = { ...stores[idx], ...patch, updated_at: getNowIso() };
    this.save(stores);
    return stores[idx];
  },

  delete(id) {
    const stores = this.list();
    const next = stores.filter(s => Number(s.id) !== Number(id));
    this.save(next);
    return true;
  }
};

// ========================================
// RENDER
// ========================================

function renderStores() {
  const container = document.getElementById('stores-container');
  if (!container) return;

  const stores = StoreRepo.list().slice().sort((a, b) => {
    const sa = Number(a.sort_order ?? 0);
    const sb = Number(b.sort_order ?? 0);
    if (sa !== sb) return sa - sb;
    return String(a.name || '').localeCompare(String(b.name || ''));
  });

  if (stores.length === 0) {
    container.innerHTML = `
      <div class="empty-state" style="grid-column: 1/-1;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
        <h3>Nenhuma loja cadastrada</h3>
        <p>Crie sua primeira loja para começar a usar o sistema</p>
        <button class="btn btn-primary" type="button" onclick="openCreateModal()">Criar Primeira Loja</button>
      </div>
    `;
    return;
  }

  container.innerHTML = '';
  stores.forEach(store => {
    container.appendChild(renderStoreCard(store));
  });
}

function renderStoreCard(store) {
  const card = document.createElement('div');
  card.className = 'store-card';
  card.dataset.storeId = store.id;

  const status = Number(store.status) === 1;
  const badgeClass = status ? 'active' : 'inactive';
  const badgeText = status ? 'Ativa' : 'Inativa';

  const addr = formatStoreAddress(store);

  card.innerHTML = `
    <div class="store-card-header">
      <h3 class="store-card-title">${escapeHtml(store.name || '')}</h3>
      <span class="store-badge ${badgeClass}">${badgeText}</span>
      <button class="store-menu-btn" type="button" aria-label="Menu" title="Mais opções">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6h.01M12 12h.01M12 18h.01" />
        </svg>
      </button>
      <div class="store-dropdown">
        <div class="store-dropdown-item" data-action="edit">✏️ Editar Informações</div>
        <div class="store-dropdown-item" data-action="config">⚙️ Configurações Avançadas</div>
        <div class="store-dropdown-item" data-action="users">👥 Gerenciar Usuários</div>
        <div class="store-dropdown-item danger" data-action="delete">🗑️ Excluir Loja</div>
      </div>
    </div>

    <div class="store-card-body">
      <div class="store-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      </div>

      <span class="store-code">${escapeHtml(store.code_name || '')}</span>

      <div class="store-info">
        ${addr ? `
          <div class="store-info-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>${escapeHtml(addr)}</span>
          </div>
        ` : ''}

        ${store.mobile ? `
          <div class="store-info-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            <span>${escapeHtml(store.mobile)}</span>
          </div>
        ` : ''}

        ${store.email ? `
          <div class="store-info-item">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span>${escapeHtml(store.email)}</span>
          </div>
        ` : ''}
      </div>
    </div>

    <div class="store-card-footer">
      <button class="btn btn-primary" type="button" style="width: 100%;" data-action="access">Acessar</button>
      <div class="store-actions">
        <button class="btn btn-outline" type="button" title="Editar" data-action="edit">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button class="btn btn-outline" type="button" title="Configurar" data-action="config">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
        <button class="btn btn-danger" type="button" title="Excluir" data-action="delete">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>
  `;

  // menu dropdown behavior
  const menuBtn = card.querySelector('.store-menu-btn');
  const dropdown = card.querySelector('.store-dropdown');
  if (menuBtn && dropdown) {
    menuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeAllDropdowns();
      dropdown.classList.toggle('active');
    });

    dropdown.querySelectorAll('.store-dropdown-item').forEach(item => {
      item.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.remove('active');
        handleCardAction(item.dataset.action, store);
      });
    });
  }

  // buttons
  card.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const action = btn.getAttribute('data-action');
      handleCardAction(action, store);
    });
  });

  return card;
}

function closeAllDropdowns() {
  document.querySelectorAll('.store-dropdown.active').forEach(d => d.classList.remove('active'));
}

function handleCardAction(action, store) {
  switch (action) {
    case 'access':
      accessStore(store.id);
      return;
    case 'edit':
      openEditModal(store.id);
      return;
    case 'config':
      openConfigModal(store.id);
      return;
    case 'delete':
      deleteStore(store.id, store.name);
      return;
    case 'users':
      showToast('info', 'Gerenciar usuários: em breve.');
      return;
    default:
      return;
  }
}

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// ========================================
// TOGGLE SWITCH
// ========================================

// ========================================
// TOGGLE SWITCH
// ========================================

function toggleSwitch(element) {
  element.classList.toggle('active');
  const isActive = element.classList.contains('active');

  // Update hidden input
  const hiddenInput = element.nextElementSibling;
  if (hiddenInput && hiddenInput.tagName === 'INPUT') {
    hiddenInput.value = isActive ? '1' : '0';
  }
}

// ========================================
// TAB MANAGEMENT
// ========================================

function switchTab(event, tabId) {
  event.preventDefault();
  
  // Remove active from all tabs
  const modal = event.target.closest('.modal-overlay');
  modal.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  modal.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
  
  // Add active to selected tab
  event.target.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}

// ========================================
// AUTO-GENERATION CODE_NAME
// ========================================

document.addEventListener('DOMContentLoaded', function() {
  if (STORES_MODE === 'demo') {
    StoreRepo.ensureSeed();
  }

  const createNameInput = document.getElementById('create-name');
  const createCodeInput = document.getElementById('create-code-name');

  if (createNameInput && createCodeInput) {
    createNameInput.addEventListener('input', function(e) {
      if (!createCodeInput.dataset.manuallyEdited) {
        createCodeInput.value = slugify(e.target.value);
      }
    });

    createCodeInput.addEventListener('input', function() {
      createCodeInput.dataset.manuallyEdited = 'true';
    });
  }

  // Initialize input masks
  initInputMasks();

  // Render stores
  renderStores();
});

function initInputMasks() {
  // Phone mask
  const phoneMasks = document.querySelectorAll('#create-mobile, #edit-mobile');
  phoneMasks.forEach(input => {
    if (typeof Inputmask !== 'undefined') {
      Inputmask('(99) 99999-9999').mask(input);
    }
  });
  
  // CEP mask
  const cepMasks = document.querySelectorAll('#create-zip, #edit-zip');
  cepMasks.forEach(input => {
    if (typeof Inputmask !== 'undefined') {
      Inputmask('99999-999').mask(input);
    }
  });
  
  // CNPJ mask
  const cnpjMasks = document.querySelectorAll('#edit-vat, #config-vat');
  cnpjMasks.forEach(input => {
    if (typeof Inputmask !== 'undefined') {
      Inputmask('99.999.999/9999-99').mask(input);
    }
  });
}

// ========================================
// BUSCAR CEP (ViaCEP API)
// ========================================

async function buscarCEP(prefix) {
  const cepInput = document.getElementById(`${prefix}-zip`);
  const cep = cepInput.value.replace(/\D/g, '');
  
  if (cep.length !== 8) {
    showToast('error', 'CEP inválido');
    return;
  }
  
  try {
    const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const data = await response.json();
    
    if (data.erro) {
      showToast('error', 'CEP não encontrado');
      return;
    }
    
    document.getElementById(`${prefix}-address`).value = data.logradouro || '';
    document.getElementById(`${prefix}-bairro`).value = data.bairro || '';
    document.getElementById(`${prefix}-city`).value = data.localidade || '';
    document.getElementById(`${prefix}-state`).value = data.uf || '';
    
    showToast('success', 'CEP encontrado!');
  } catch (error) {
    showToast('error', 'Erro ao buscar CEP');
  }
}

// ========================================
// MODAL: CREATE STORE
// ========================================

function resetCreateModalDefaults() {
  const remoteToggle = document.getElementById('toggle-remote-printing');
  const remoteInput = document.getElementById('remote-printing');
  if (remoteToggle) remoteToggle.classList.remove('active');
  if (remoteInput) remoteInput.value = '0';

  const autoToggle = document.getElementById('toggle-auto-print');
  const autoInput = document.getElementById('auto-print');
  if (autoToggle) autoToggle.classList.remove('active');
  if (autoInput) autoInput.value = '0';

  const soundToggle = document.getElementById('toggle-sound-effect');
  const soundInput = document.getElementById('sound-effect');
  if (soundToggle) soundToggle.classList.add('active');
  if (soundInput) soundInput.value = '1';
}

function openCreateModal() {
  const modal = document.getElementById('modal-create-store');
  if (!modal) return;
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';

  // Reset form
  const form = document.getElementById('form-create-store');
  if (form) form.reset();

  resetCreateModalDefaults();

  // Reset code_name manual edit flag
  const codeInput = document.getElementById('create-code-name');
  if (codeInput) {
    delete codeInput.dataset.manuallyEdited;
  }
}

function closeCreateModal() {
  const modal = document.getElementById('modal-create-store');
  modal.classList.remove('active');
  document.body.style.overflow = '';
}

async function handleCreateStore(event) {
  event.preventDefault();

  const form = event.target;
  const button = document.getElementById('btn-create-store');
  setButtonLoading(button, true);

  try {
    const name = (document.getElementById('create-name')?.value || '').trim();
    const codeNameRaw = (document.getElementById('create-code-name')?.value || '').trim();
    const address = (document.getElementById('create-address')?.value || '').trim();

    if (!name) {
      showToast('error', 'Informe o nome da loja.');
      return;
    }
    if (!address) {
      showToast('error', 'Informe o endereço.');
      return;
    }

    const stores = StoreRepo.list();
    const code_name = uniqueCodeName(codeNameRaw || name, stores);

    const store = {
      name,
      code_name,
      country: (form.querySelector('select[name="country"]')?.value || 'BR'),
      mobile: (document.getElementById('create-mobile')?.value || '').trim(),
      email: (form.querySelector('input[name="email"]')?.value || '').trim(),
      zip_code: (document.getElementById('create-zip')?.value || '').trim(),
      address,
      number: (document.getElementById('create-number')?.value || '').trim(),
      complement: (document.getElementById('create-complement')?.value || '').trim(),
      bairro: (document.getElementById('create-bairro')?.value || '').trim(),
      city: (document.getElementById('create-city')?.value || '').trim(),
      state: (document.getElementById('create-state')?.value || '').trim(),
      remote_printing: Number(document.getElementById('remote-printing')?.value || 0),
      auto_print: Number(document.getElementById('auto-print')?.value || 0),
      sound_effect: Number(document.getElementById('sound-effect')?.value || 1),
      timezone: (form.querySelector('select[name="timezone"]')?.value || 'America/Sao_Paulo'),
      status: 1,
    };

    // payment methods
    const pmethods = Array.from(form.querySelectorAll('input[name="pmethods[]"]:checked')).map(i => i.value);
    store.pmethods = pmethods;

    StoreRepo.create(store);
    showToast('success', 'Loja criada com sucesso!');
    closeCreateModal();
    renderStores();
  } catch (error) {
    console.error('Error:', error);
    showToast('error', 'Erro ao criar loja.');
  } finally {
    setButtonLoading(button, false);
  }
}

// ========================================
// MODAL: EDIT STORE
// ========================================

async function openEditModal(storeId) {
  currentEditStoreId = storeId;

  const modal = document.getElementById('modal-edit-store');
  if (!modal) return;
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';

  const store = StoreRepo.getById(storeId);
  if (!store) {
    showToast('error', 'Loja não encontrada.');
    closeEditModal();
    return;
  }

  document.getElementById('edit-store-id').value = store.id;
  document.getElementById('edit-name').value = store.name || '';
  document.getElementById('edit-code-name').value = store.code_name || '';
  document.getElementById('edit-country').value = store.country || 'BR';
  document.getElementById('edit-mobile').value = store.mobile || '';
  document.getElementById('edit-email').value = store.email || '';
  document.getElementById('edit-vat').value = store.vat_reg_no || '';
  document.getElementById('edit-zip').value = store.zip_code || '';
  document.getElementById('edit-address').value = store.address || '';
  document.getElementById('edit-number').value = store.number || '';
  document.getElementById('edit-complement').value = store.complement || '';
  document.getElementById('edit-bairro').value = store.bairro || '';
  document.getElementById('edit-city').value = store.city || '';
  document.getElementById('edit-state').value = store.state || '';

  // Update status toggle
  const statusToggle = document.getElementById('toggle-edit-status');
  const statusInput = document.getElementById('edit-status');
  if (Number(store.status) === 1) {
    statusToggle.classList.add('active');
    statusInput.value = '1';
  } else {
    statusToggle.classList.remove('active');
    statusInput.value = '0';
  }

  // Update modal title
  document.getElementById('edit-modal-title').textContent = `Editar Loja: ${store.name}`;
}

function closeEditModal() {
  const modal = document.getElementById('modal-edit-store');
  modal.classList.remove('active');
  document.body.style.overflow = '';
  currentEditStoreId = null;
}

async function handleEditStore(event) {
  event.preventDefault();

  const button = document.getElementById('btn-edit-store');
  setButtonLoading(button, true);

  try {
    const id = Number(document.getElementById('edit-store-id')?.value || currentEditStoreId);
    const existing = StoreRepo.getById(id);
    if (!existing) {
      showToast('error', 'Loja não encontrada.');
      return;
    }

    const name = (document.getElementById('edit-name')?.value || '').trim();
    const address = (document.getElementById('edit-address')?.value || '').trim();
    if (!name) {
      showToast('error', 'Informe o nome da loja.');
      return;
    }
    if (!address) {
      showToast('error', 'Informe o endereço.');
      return;
    }

    const patch = {
      name,
      country: (document.getElementById('edit-country')?.value || 'BR'),
      mobile: (document.getElementById('edit-mobile')?.value || '').trim(),
      email: (document.getElementById('edit-email')?.value || '').trim(),
      vat_reg_no: (document.getElementById('edit-vat')?.value || '').trim(),
      zip_code: (document.getElementById('edit-zip')?.value || '').trim(),
      address,
      number: (document.getElementById('edit-number')?.value || '').trim(),
      complement: (document.getElementById('edit-complement')?.value || '').trim(),
      bairro: (document.getElementById('edit-bairro')?.value || '').trim(),
      city: (document.getElementById('edit-city')?.value || '').trim(),
      state: (document.getElementById('edit-state')?.value || '').trim(),
      status: Number(document.getElementById('edit-status')?.value || 0),
    };

    StoreRepo.update(id, patch);
    showToast('success', 'Loja atualizada com sucesso!');
    closeEditModal();
    renderStores();
  } catch (error) {
    console.error('Error:', error);
    showToast('error', 'Erro ao atualizar loja.');
  } finally {
    setButtonLoading(button, false);
  }
}

// ========================================
// MODAL: CONFIG STORE
// ========================================

function setToggleByHiddenInput(toggleId, inputId) {
  const toggle = document.getElementById(toggleId);
  const input = document.getElementById(inputId);
  if (!toggle || !input) return;
  const val = String(input.value) === '1';
  toggle.classList.toggle('active', val);
}

function setHiddenAndToggle(toggleId, inputId, val) {
  const toggle = document.getElementById(toggleId);
  const input = document.getElementById(inputId);
  if (input) input.value = val ? '1' : '0';
  if (toggle) toggle.classList.toggle('active', !!val);
}

function resetConfigTabs() {
  const overlay = document.getElementById('modal-config-store');
  if (!overlay) return;
  overlay.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  overlay.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  const firstBtn = overlay.querySelector('.tab-btn[data-tab="config-tab-geral"]');
  const firstTab = document.getElementById('config-tab-geral');
  if (firstBtn) firstBtn.classList.add('active');
  if (firstTab) firstTab.classList.add('active');
}

async function openConfigModal(storeId) {
  currentConfigStoreId = storeId;

  const modal = document.getElementById('modal-config-store');
  if (!modal) return;
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';

  resetConfigTabs();

  const store = StoreRepo.getById(storeId);
  if (!store) {
    showToast('error', 'Loja não encontrada.');
    closeConfigModal();
    return;
  }

  document.getElementById('config-store-id').value = storeId;
  document.getElementById('config-modal-title').textContent = `Configurações Avançadas: ${store.name}`;

  // Geral
  document.getElementById('config-vat').value = store.vat_reg_no || '';
  document.getElementById('config-sort-order').value = store.sort_order ?? 0;
  document.getElementById('config-status').value = Number(store.status) === 1 ? '1' : '0';
  setToggleByHiddenInput('toggle-config-status', 'config-status');

  // PDV
  const pref = store.preference || {};
  document.getElementById('config-after-sale').value = pref.after_sale_page || 'pos';
  document.getElementById('config-show-stock').value = String(pref.show_stock ?? 1);
  setToggleByHiddenInput('toggle-show-stock', 'config-show-stock');
  document.getElementById('config-show-code').value = String(pref.show_item_code ?? 1);
  setToggleByHiddenInput('toggle-show-code', 'config-show-code');
  document.getElementById('config-show-customer').value = String(pref.show_customer ?? 1);
  setToggleByHiddenInput('toggle-show-customer', 'config-show-customer');
  document.getElementById('config-show-note').value = String(pref.show_invoice_note ?? 0);
  setToggleByHiddenInput('toggle-show-note', 'config-show-note');
  document.getElementById('config-show-currency').value = String(pref.show_currency ?? 1);
  setToggleByHiddenInput('toggle-show-currency', 'config-show-currency');
  document.getElementById('config-pos-bg').value = pref.pos_background || '';

  // Fiscal
  document.getElementById('config-gst').value = pref.gst_reg_no || '';
  document.getElementById('config-tax-method').value = pref.tax_method || 'exclusive';
  document.getElementById('config-tax').value = pref.tax ?? 0;

  // Impressão
  document.getElementById('config-printer-type').value = store.receipt_printer || '';
  document.getElementById('config-remote-print').value = String(store.remote_printing ?? 0);
  setToggleByHiddenInput('toggle-remote-print', 'config-remote-print');
  document.getElementById('config-auto-print').value = String(store.auto_print ?? 0);
  setToggleByHiddenInput('toggle-auto-print-config', 'config-auto-print');
  document.getElementById('config-receipt-template').value = pref.receipt_template || 'default';

  // Integrações
  document.getElementById('config-webhook').value = pref.webhook_url || '';
  document.getElementById('config-ecommerce').value = String(pref.ecommerce_sync ?? 0);
  setToggleByHiddenInput('toggle-ecommerce', 'config-ecommerce');
  document.getElementById('config-delivery').value = String(pref.delivery_integration ?? 0);
  setToggleByHiddenInput('toggle-delivery', 'config-delivery');
  document.getElementById('config-whatsapp').value = String(pref.whatsapp_notifications ?? 0);
  setToggleByHiddenInput('toggle-whatsapp', 'config-whatsapp');
}

function closeConfigModal() {
  const modal = document.getElementById('modal-config-store');
  modal.classList.remove('active');
  document.body.style.overflow = '';
  currentConfigStoreId = null;
}

async function handleConfigStore(event) {
  event.preventDefault();

  const button = document.getElementById('btn-config-store');
  setButtonLoading(button, true);

  try {
    const storeId = Number(document.getElementById('config-store-id')?.value || currentConfigStoreId);
    const store = StoreRepo.getById(storeId);
    if (!store) {
      showToast('error', 'Loja não encontrada.');
      return;
    }

    const preference = {
      after_sale_page: document.getElementById('config-after-sale')?.value || 'pos',
      show_stock: Number(document.getElementById('config-show-stock')?.value || 0),
      show_item_code: Number(document.getElementById('config-show-code')?.value || 0),
      show_customer: Number(document.getElementById('config-show-customer')?.value || 0),
      show_invoice_note: Number(document.getElementById('config-show-note')?.value || 0),
      show_currency: Number(document.getElementById('config-show-currency')?.value || 0),
      pos_background: document.getElementById('config-pos-bg')?.value || '',
      gst_reg_no: document.getElementById('config-gst')?.value || '',
      tax_method: document.getElementById('config-tax-method')?.value || 'exclusive',
      tax: Number(document.getElementById('config-tax')?.value || 0),
      receipt_template: document.getElementById('config-receipt-template')?.value || 'default',
      webhook_url: document.getElementById('config-webhook')?.value || '',
      ecommerce_sync: Number(document.getElementById('config-ecommerce')?.value || 0),
      delivery_integration: Number(document.getElementById('config-delivery')?.value || 0),
      whatsapp_notifications: Number(document.getElementById('config-whatsapp')?.value || 0),
    };

    const patch = {
      vat_reg_no: (document.getElementById('config-vat')?.value || '').trim(),
      sort_order: Number(document.getElementById('config-sort-order')?.value || 0),
      status: Number(document.getElementById('config-status')?.value || 0),
      receipt_printer: document.getElementById('config-printer-type')?.value || '',
      remote_printing: Number(document.getElementById('config-remote-print')?.value || 0),
      auto_print: Number(document.getElementById('config-auto-print')?.value || 0),
      preference: { ...(store.preference || {}), ...preference },
    };

    StoreRepo.update(storeId, patch);
    showToast('success', 'Configurações salvas com sucesso!');
    closeConfigModal();
    renderStores();
  } catch (error) {
    console.error('Error:', error);
    showToast('error', 'Erro ao salvar configurações.');
  } finally {
    setButtonLoading(button, false);
  }
}

// ========================================
// DELETE STORE
// ========================================

function deleteStore(storeId, storeName) {
  if (typeof Swal === 'undefined') {
    const ok = confirm(`Excluir a loja "${storeName}"?`);
    if (!ok) return;
    StoreRepo.delete(storeId);
    renderStores();
    showToast('success', 'Loja excluída!');
    return;
  }

  Swal.fire({
    title: 'Excluir Loja?',
    html: `Tem certeza que deseja excluir <strong>"${storeName}"</strong>?<br><br>Esta ação não pode ser desfeita.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#64748B',
    confirmButtonText: 'Sim, excluir',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (!result.isConfirmed) return;

    StoreRepo.delete(storeId);
    renderStores();
    showToast('success', 'Loja excluída com sucesso!');
  });
}

// ========================================
// ACCESS STORE
// ========================================

function accessStore(storeId) {
  // Sem backend por enquanto: salva a loja "ativa" no localStorage e avisa o usuário.
  localStorage.setItem('modernpos.demo.active_store_id', String(storeId));
  showToast('info', 'Loja selecionada. (Modo demo: integração com PDV será feita depois)');
}

// ========================================
// API KEYS (Placeholder functions)
// ========================================

function regenerateApiKey() {
  if (typeof Swal === 'undefined') {
    showToast('info', 'Funcionalidade em desenvolvimento');
    return;
  }

  Swal.fire({
    title: 'Regenerar API Key?',
    text: 'A chave antiga será invalidada. Atualize suas integrações após regenerar.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sim, regenerar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      showToast('info', 'Funcionalidade em desenvolvimento');
    }
  });
}

function toggleApiSecret(buttonEl) {
  const input = document.getElementById('config-api-secret');
  if (!input) return;

  if (input.type === 'password') {
    input.type = 'text';
    if (buttonEl) buttonEl.textContent = 'Ocultar';
  } else {
    input.type = 'password';
    if (buttonEl) buttonEl.textContent = 'Mostrar';
  }
}

// ========================================
// CLOSE MODALS ON OVERLAY CLICK
// ========================================

document.addEventListener('click', function(event) {
  // close dropdowns
  closeAllDropdowns();

  // close modals on overlay click
  if (event.target.classList.contains('modal-overlay')) {
    if (event.target.id === 'modal-create-store') {
      closeCreateModal();
    } else if (event.target.id === 'modal-edit-store') {
      closeEditModal();
    } else if (event.target.id === 'modal-config-store') {
      closeConfigModal();
    }
  }
});

// ========================================
// KEYBOARD SHORTCUTS
// ========================================

document.addEventListener('keydown', function(event) {
  // ESC to close modals
  if (event.key === 'Escape') {
    const activeModal = document.querySelector('.modal-overlay.active');
    if (activeModal) {
      if (activeModal.id === 'modal-create-store') closeCreateModal();
      if (activeModal.id === 'modal-edit-store') closeEditModal();
      if (activeModal.id === 'modal-config-store') closeConfigModal();
    }
  }
});
