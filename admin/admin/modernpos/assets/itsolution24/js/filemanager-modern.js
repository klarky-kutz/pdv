/**
 * FileManager Modern - JavaScript
 * Gerenciador de arquivos moderno com controle de armazenamento
 */

(function() {
  'use strict';

  // Configuração
  const CONFIG = {
    apiUrl: '../_inc/bridges/php-local/index.php',
    storageApiUrl: '../_inc/api/storage_usage.php',
    baseUrl: window.FILEMANAGERURL || '../storage/products',
    allowedExtensions: ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
    maxFileSize: 10 * 1024 * 1024 // 10MB
  };

  // Estado da aplicação
  const state = {
    currentPath: '/',
    items: [],
    filteredItems: [],
    selectedItems: [],
    viewMode: 'grid', // 'grid' ou 'list'
    search: '',
    storage: {
      used_mb: 0,
      limit_mb: 0,
      percent: 0,
      unlimited: true
    },
    loading: false
  };

  // Elementos DOM
  let elements = {};

  // ==========================================
  // Inicialização
  // ==========================================
  function init() {
    cacheElements();
    bindEvents();
    loadStorage();
    loadFiles();
  }

  function cacheElements() {
    elements = {
      // Storage
      storageBar: document.getElementById('fm-storage-bar'),
      storageBarFill: document.querySelector('.fm-storage-bar-fill'),
      storageText: document.querySelector('.fm-storage-text'),
      alertBanner: document.getElementById('fm-alert-banner'),
      alertText: document.getElementById('fm-alert-text'),
      
      // Stats
      statFiles: document.getElementById('fm-stat-files'),
      statSize: document.getElementById('fm-stat-size'),
      statImages: document.getElementById('fm-stat-images'),
      
      // Search
      searchInput: document.getElementById('fm-search'),
      
      // View
      btnViewGrid: document.getElementById('fm-view-grid'),
      btnViewList: document.getElementById('fm-view-list'),
      
      // Actions
      btnUpload: document.getElementById('fm-btn-upload'),
      btnNewFolder: document.getElementById('fm-btn-folder'),
      
      // Breadcrumb
      breadcrumb: document.getElementById('fm-breadcrumb'),
      
      // Content
      content: document.getElementById('fm-content'),
      grid: document.getElementById('fm-grid'),
      list: document.getElementById('fm-list'),
      empty: document.getElementById('fm-empty'),
      loading: document.getElementById('fm-loading'),
      
      // Modals
      uploadModal: document.getElementById('fm-upload-modal'),
      detailModal: document.getElementById('fm-detail-modal'),
      dropzone: document.getElementById('fm-dropzone'),
      fileInput: document.getElementById('fm-file-input'),
      uploadPreview: document.getElementById('fm-upload-preview'),
      uploadList: document.getElementById('fm-upload-list'),
      btnUploadConfirm: document.getElementById('fm-upload-confirm'),
      
      // Context menu
      contextMenu: document.getElementById('fm-context-menu')
    };
  }

  // ==========================================
  // Event Bindings
  // ==========================================
  function bindEvents() {
    // Search
    if (elements.searchInput) {
      let debounce;
      elements.searchInput.addEventListener('input', (e) => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
          state.search = e.target.value.toLowerCase();
          filterItems();
        }, 200);
      });
    }

    // View toggle
    if (elements.btnViewGrid) {
      elements.btnViewGrid.addEventListener('click', () => setViewMode('grid'));
    }
    if (elements.btnViewList) {
      elements.btnViewList.addEventListener('click', () => setViewMode('list'));
    }

    // Upload button
    if (elements.btnUpload) {
      elements.btnUpload.addEventListener('click', openUploadModal);
    }

    // New folder
    if (elements.btnNewFolder) {
      elements.btnNewFolder.addEventListener('click', openFolderModal);
    }

    // Dropzone
    if (elements.dropzone) {
      elements.dropzone.addEventListener('click', () => elements.fileInput?.click());
      elements.dropzone.addEventListener('dragover', handleDragOver);
      elements.dropzone.addEventListener('dragleave', handleDragLeave);
      elements.dropzone.addEventListener('drop', handleDrop);
    }

    // File input
    if (elements.fileInput) {
      elements.fileInput.addEventListener('change', handleFileSelect);
    }

    // Upload confirm
    if (elements.btnUploadConfirm) {
      elements.btnUploadConfirm.addEventListener('click', uploadFiles);
    }

    // Modal close buttons
    document.querySelectorAll('.fm-modal-close').forEach(btn => {
      btn.addEventListener('click', closeModals);
    });

    // Modal backdrop click
    document.querySelectorAll('.fm-modal-backdrop').forEach(backdrop => {
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeModals();
      });
    });

    // Context menu
    document.addEventListener('click', () => hideContextMenu());
    document.addEventListener('contextmenu', (e) => {
      if (!e.target.closest('.fm-card')) {
        hideContextMenu();
      }
    });

    // Grid click events (delegação)
    if (elements.grid) {
      elements.grid.addEventListener('click', handleGridClick);
      elements.grid.addEventListener('dblclick', handleGridDblClick);
      elements.grid.addEventListener('contextmenu', handleContextMenu);
    }

    // List click events (delegação) - adiciona os mesmos handlers para o modo lista
    if (elements.list) {
      elements.list.addEventListener('click', handleListClick);
      elements.list.addEventListener('dblclick', handleListDblClick);
      elements.list.addEventListener('contextmenu', handleListContextMenu);
    }
  }

  // ==========================================
  // API Calls
  // ==========================================
  async function loadFiles(path = '/') {
    showLoading(true);
    state.currentPath = path;

    try {
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'list', path: path })
      });

      const data = await response.json();
      
      if (data.result && Array.isArray(data.result)) {
        state.items = data.result.map(item => ({
          ...item,
          fullPath: path === '/' ? '/' + item.name : path + '/' + item.name,
          isImage: isImageFile(item.name),
          extension: getExtension(item.name)
        }));
        filterItems();
        updateBreadcrumb();
        updateStats();
      } else {
        state.items = [];
        renderEmpty();
      }
    } catch (error) {
      console.error('[FileManager] Erro ao carregar arquivos:', error);
      showError('Erro ao carregar arquivos');
      state.items = [];
      renderEmpty();
    }

    showLoading(false);
  }

  async function loadStorage() {
    try {
      const response = await fetch(CONFIG.storageApiUrl);
      const data = await response.json();
      
      if (data.success && data.usage) {
        state.storage = data.usage;
        updateStorageBar();
      }
    } catch (error) {
      console.warn('[FileManager] Erro ao carregar uso de storage:', error);
    }
  }

  async function uploadFiles() {
    const files = elements.fileInput?.files;
    if (!files || files.length === 0) return;

    // Verifica limite de storage antes do upload
    if (!state.storage.unlimited) {
      const totalSize = Array.from(files).reduce((sum, f) => sum + f.size, 0);
      const totalMb = totalSize / (1024 * 1024);
      
      if (state.storage.used_mb + totalMb > state.storage.limit_mb) {
        showStorageLimitModal();
        return;
      }
    }

    showLoading(true);
    elements.btnUploadConfirm.disabled = true;
    elements.btnUploadConfirm.textContent = 'Enviando...';

    // Obtém pasta de destino do seletor
    const folderSelect = document.getElementById('fm-upload-folder');
    const uploadDestination = folderSelect ? folderSelect.value : state.currentPath;

    try {
      const formData = new FormData();
      formData.append('destination', uploadDestination);
      
      Array.from(files).forEach((file, index) => {
        formData.append(`file-${index}`, file);
      });

      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.result?.success) {
        showSuccess('Arquivos enviados com sucesso!');
        closeModals();
        loadFiles(state.currentPath);
        loadStorage();
      } else {
        throw new Error(data.result?.error || 'Erro no upload');
      }
    } catch (error) {
      console.error('[FileManager] Erro no upload:', error);
      showError(error.message || 'Erro ao enviar arquivos');
    }

    elements.btnUploadConfirm.disabled = false;
    elements.btnUploadConfirm.textContent = 'Enviar';
    showLoading(false);
  }

  async function deleteFile(item) {
    if (!confirm(`Deseja excluir "${item.name}"?`)) return;

    try {
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'remove',
          items: [item.fullPath]
        })
      });

      const data = await response.json();

      if (data.result?.success) {
        showSuccess('Arquivo excluído');
        loadFiles(state.currentPath);
        loadStorage();
      } else {
        throw new Error(data.result?.error || 'Erro ao excluir');
      }
    } catch (error) {
      showError(error.message);
    }
  }

  // Estado para mover arquivo
  let moveState = {
    item: null,
    targetFolder: null
  };

  async function openMoveModal(item) {
    moveState.item = item;
    moveState.targetFolder = null;

    const modal = document.getElementById('fm-move-modal');
    const filenameEl = document.getElementById('fm-move-filename');
    const folderList = document.getElementById('fm-move-folder-list');
    const confirmBtn = document.getElementById('fm-move-confirm');

    if (!modal || !folderList) return;

    // Atualiza nome do arquivo
    if (filenameEl) {
      filenameEl.textContent = item.name;
    }

    // Desabilita botão de confirmar
    if (confirmBtn) confirmBtn.disabled = true;

    // Carrega lista de pastas
    folderList.innerHTML = '<div class="fm-loading-folders"><i class="fa fa-spinner fa-spin"></i> Carregando pastas...</div>';
    modal.classList.add('show');

    try {
      const folders = await loadAllFolders();
      renderFolderList(folders, item.fullPath);
    } catch (error) {
      folderList.innerHTML = '<div class="fm-error">Erro ao carregar pastas</div>';
    }
  }

  async function loadAllFolders(path = '/') {
    const folders = [];
    
    try {
      console.log('[FileManager] Buscando pastas em:', path);
      
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'list', path: path })
      });

      const data = await response.json();
      console.log('[FileManager] Resposta da API:', data);
      
      // Verifica se tem resultado válido
      const items = data.result || data.items || data.files || [];
      
      if (Array.isArray(items)) {
        for (const item of items) {
          // Verifica se é uma pasta (dir ou folder)
          if (item.type === 'dir' || item.type === 'folder' || item.isDir) {
            const fullPath = path === '/' ? '/' + item.name : path + '/' + item.name;
            folders.push({
              name: item.name,
              path: fullPath,
              level: path.split('/').filter(Boolean).length
            });
            // Carrega subpastas recursivamente (apenas 2 níveis)
            if (path.split('/').filter(Boolean).length < 2) {
              const subfolders = await loadAllFolders(fullPath);
              folders.push(...subfolders);
            }
          }
        }
      }
      
      console.log('[FileManager] Pastas encontradas em', path + ':', folders.length);
      
    } catch (error) {
      console.error('[FileManager] Erro ao carregar pastas:', error);
    }

    return folders;
  }

  function renderFolderList(folders, currentFilePath) {
    const folderList = document.getElementById('fm-move-folder-list');
    const confirmBtn = document.getElementById('fm-move-confirm');
    if (!folderList) return;

    // Adiciona pasta raiz
    let html = `
      <div class="fm-folder-item" data-path="/">
        <i class="fa fa-home"></i> <span>Raíz</span>
      </div>
    `;

    // Adiciona pastas encontradas
    folders.forEach(folder => {
      const indent = folder.level * 20;
      html += `
        <div class="fm-folder-item" data-path="${escapeHtml(folder.path)}" style="padding-left: ${indent + 12}px;">
          <i class="fa fa-folder"></i> <span>${escapeHtml(folder.name)}</span>
        </div>
      `;
    });

    if (folders.length === 0) {
      html += '<div class="fm-no-folders">Nenhuma pasta disponível</div>';
    }

    folderList.innerHTML = html;

    // Bind click events
    folderList.querySelectorAll('.fm-folder-item').forEach(item => {
      item.addEventListener('click', () => {
        // Remove seleção anterior
        folderList.querySelectorAll('.fm-folder-item').forEach(i => i.classList.remove('selected'));
        // Adiciona seleção
        item.classList.add('selected');
        moveState.targetFolder = item.dataset.path;
        if (confirmBtn) confirmBtn.disabled = false;
      });
    });
  }

  async function moveFile() {
    if (!moveState.item || !moveState.targetFolder) return;

    const confirmBtn = document.getElementById('fm-move-confirm');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Movendo...';
    }

    try {
      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'move',
          items: [moveState.item.fullPath],
          newPath: moveState.targetFolder
        })
      });

      const data = await response.json();

      if (data.result?.success) {
        showSuccess(`Arquivo movido para "${moveState.targetFolder}"`);
        closeModals();
        loadFiles(state.currentPath);
      } else {
        throw new Error(data.result?.error || 'Erro ao mover arquivo');
      }
    } catch (error) {
      showError(error.message);
    }

    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fa fa-check"></i> Mover';
    }
  }

  // Bind do botão de confirmar mover
  document.getElementById('fm-move-confirm')?.addEventListener('click', moveFile);

  function openFolderModal() {
    const modal = document.getElementById('fm-folder-modal');
    const input = document.getElementById('fm-folder-name');
    const confirmBtn = document.getElementById('fm-folder-confirm');
    const locationPath = document.getElementById('fm-folder-location-path');
    
    if (!modal || !input) return;

    // Reset
    input.value = '';
    if (confirmBtn) confirmBtn.disabled = true;
    
    // Atualiza localização atual
    if (locationPath) {
      const currentPathDisplay = state.currentPath === '/' ? '/ (Raiz)' : state.currentPath;
      locationPath.textContent = currentPathDisplay;
    }

    // Bind input event
    input.oninput = function() {
      const value = this.value.trim();
      if (confirmBtn) {
        confirmBtn.disabled = value.length === 0;
      }
    };

    // Bind confirm button
    if (confirmBtn) {
      confirmBtn.onclick = function() {
        const name = input.value.trim();
        if (name) {
          createFolder(name);
        }
      };
    }

    // Bind enter key
    input.onkeydown = function(e) {
      if (e.key === 'Enter') {
        const name = this.value.trim();
        if (name) {
          createFolder(name);
        }
      }
    };

    modal.classList.add('show');
    
    // Focus input after modal opens
    setTimeout(() => input.focus(), 100);
  }

  async function createFolder(name) {
    if (!name) return;

    const modal = document.getElementById('fm-folder-modal');
    const confirmBtn = document.getElementById('fm-folder-confirm');

    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Criando pasta...';
    }

    try {
      const newPath = state.currentPath === '/' 
        ? '/' + name 
        : state.currentPath + '/' + name;

      const response = await fetch(CONFIG.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'createFolder',
          newPath: newPath
        })
      });

      const data = await response.json();

      if (data.result?.success) {
        showSuccess('Pasta criada com sucesso!');
        if (modal) modal.classList.remove('show');
        loadFiles(state.currentPath);
      } else {
        throw new Error(data.result?.error || 'Erro ao criar pasta');
      }
    } catch (error) {
      showError(error.message);
    }

    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fa fa-folder-open"></i> Criar Pasta';
    }
  }

  // ==========================================
  // Render Functions
  // ==========================================
  function renderGrid() {
    if (!elements.grid) return;

    // Sempre oculta o estado vazio primeiro
    if (elements.empty) {
      elements.empty.classList.remove('show');
      elements.empty.style.display = 'none';
    }

    if (state.filteredItems.length === 0) {
      renderEmpty();
      return;
    }
    
    elements.grid.innerHTML = state.filteredItems.map(item => {
      const isFolder = item.type === 'dir';
      const previewUrl = item.isImage 
        ? `${CONFIG.baseUrl}${item.fullPath}` 
        : null;

      return `
        <div class="fm-card" data-path="${escapeHtml(item.fullPath)}" data-name="${escapeHtml(item.name)}" data-type="${item.type}">
          <div class="fm-card-preview">
            ${previewUrl 
              ? `<img src="${previewUrl}" alt="${escapeHtml(item.name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                 <i class="fa fa-file-image-o fm-file-icon" style="display:none;"></i>`
              : isFolder 
                ? `<i class="fa fa-folder fm-folder-icon"></i>`
                : `<i class="fa fa-file-o fm-file-icon"></i>`
            }
            <span class="fm-card-badge">${isFolder ? 'Pasta' : (item.extension || 'FILE').toUpperCase()}</span>
            <div class="fm-card-checkbox"></div>
            <div class="fm-card-overlay">
              ${!isFolder ? `
                <button class="view" title="Ver"><i class="fa fa-eye"></i></button>
                <button class="copy" title="Copiar link"><i class="fa fa-link"></i></button>
              ` : ''}
              <button class="delete" title="Excluir"><i class="fa fa-trash"></i></button>
            </div>
          </div>
          <div class="fm-card-info">
            <div class="fm-card-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
            <div class="fm-card-meta">
              <span>${isFolder ? '-' : formatBytes(item.size)}</span>
              <span>${formatDate(item.date)}</span>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderList() {
    if (!elements.list) return;

    // Sempre oculta o estado vazio primeiro
    if (elements.empty) {
      elements.empty.classList.remove('show');
      elements.empty.style.display = 'none';
    }

    if (state.filteredItems.length === 0) {
      renderEmpty();
      return;
    }

    elements.list.innerHTML = `
      <table class="fm-list-table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Tamanho</th>
            <th>Data</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          ${state.filteredItems.map(item => {
            const isFolder = item.type === 'dir';
            const previewUrl = item.isImage ? `${CONFIG.baseUrl}${item.fullPath}` : null;
            
            return `
              <tr data-path="${escapeHtml(item.fullPath)}" data-name="${escapeHtml(item.name)}" data-type="${item.type}">
                <td>
                  <div class="file-name">
                    <div class="file-icon">
                      ${previewUrl 
                        ? `<img src="${previewUrl}" alt="">`
                        : isFolder 
                          ? `<i class="fa fa-folder" style="color:#fbbf24;"></i>`
                          : `<i class="fa fa-file-o"></i>`
                      }
                    </div>
                    <span>${escapeHtml(item.name)}</span>
                  </div>
                </td>
                <td>${isFolder ? '-' : formatBytes(item.size)}</td>
                <td>${formatDate(item.date)}</td>
                <td>
                  <div class="actions">
                    ${!isFolder ? `
                      <button class="view" title="Ver"><i class="fa fa-eye"></i></button>
                      <button class="copy" title="Copiar link"><i class="fa fa-link"></i></button>
                    ` : ''}
                    <button class="delete" title="Excluir"><i class="fa fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    `;
  }

  function renderEmpty() {
    if (elements.grid) elements.grid.innerHTML = '';
    if (elements.list) elements.list.innerHTML = '';
    if (elements.empty) {
      elements.empty.classList.add('show');
      elements.empty.style.display = 'flex';
    }
  }

  function updateStorageBar() {
    if (!elements.storageBarFill) return;

    const { used_mb, limit_mb, percent, unlimited } = state.storage;

    if (unlimited || limit_mb === 0) {
      elements.storageBarFill.style.width = '0%';
      elements.storageBarFill.className = 'fm-storage-bar-fill success';
      if (elements.storageText) {
        elements.storageText.innerHTML = `<span>${used_mb.toFixed(2)} MB usado</span><span>Sem limite</span>`;
      }
      elements.alertBanner?.classList.remove('show');
      return;
    }

    const safePercent = Math.min(100, percent);
    elements.storageBarFill.style.width = safePercent + '%';

    // Determina a cor
    elements.storageBarFill.classList.remove('success', 'warning', 'danger');
    if (percent >= 90) {
      elements.storageBarFill.classList.add('danger');
    } else if (percent >= 70) {
      elements.storageBarFill.classList.add('warning');
    } else {
      elements.storageBarFill.classList.add('success');
    }

    if (elements.storageText) {
      elements.storageText.innerHTML = `
        <span>${used_mb.toFixed(2)} MB de ${limit_mb} MB</span>
        <span>${safePercent.toFixed(1)}%</span>
      `;
    }

    // Alert banner
    if (elements.alertBanner && elements.alertText) {
      if (percent >= 100) {
        elements.alertBanner.classList.add('show', 'danger');
        elements.alertBanner.classList.remove('warning');
        elements.alertText.textContent = 'Armazenamento cheio! Exclua arquivos ou faça upgrade.';
        if (elements.btnUpload) elements.btnUpload.disabled = true;
      } else if (percent >= 80) {
        elements.alertBanner.classList.add('show', 'warning');
        elements.alertBanner.classList.remove('danger');
        elements.alertText.textContent = `Atenção: ${safePercent.toFixed(0)}% do armazenamento usado.`;
      } else {
        elements.alertBanner.classList.remove('show');
      }
    }
  }

  function updateBreadcrumb() {
    if (!elements.breadcrumb) return;

    const parts = state.currentPath.split('/').filter(Boolean);
    let html = `<a href="#" data-path="/"><i class="fa fa-home"></i></a>`;

    let currentPath = '';
    parts.forEach((part, index) => {
      currentPath += '/' + part;
      const isLast = index === parts.length - 1;
      
      if (isLast) {
        html += `<span class="separator">/</span><span class="current">${escapeHtml(part)}</span>`;
      } else {
        html += `<span class="separator">/</span><a href="#" data-path="${currentPath}">${escapeHtml(part)}</a>`;
      }
    });

    elements.breadcrumb.innerHTML = html;

    // Bind click events
    elements.breadcrumb.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        loadFiles(link.dataset.path);
      });
    });
  }

  function updateStats() {
    let totalSize = 0;
    let imageCount = 0;
    let fileCount = 0;

    state.items.forEach(item => {
      if (item.type !== 'dir') {
        fileCount++;
        totalSize += item.size || 0;
        if (item.isImage) imageCount++;
      }
    });

    if (elements.statFiles) {
      elements.statFiles.textContent = `${fileCount} Arquivos`;
    }
    if (elements.statSize) {
      elements.statSize.textContent = formatBytes(totalSize);
    }
    if (elements.statImages) {
      elements.statImages.textContent = `${imageCount} Imagens`;
    }
  }

  // ==========================================
  // Event Handlers
  // ==========================================
  function handleGridClick(e) {
    const card = e.target.closest('.fm-card');
    if (!card) return;

    const action = e.target.closest('button');
    if (action) {
      e.stopPropagation();
      const item = findItemByPath(card.dataset.path);
      
      if (action.classList.contains('view')) {
        openDetailModal(item);
      } else if (action.classList.contains('copy')) {
        copyLink(item);
      } else if (action.classList.contains('delete')) {
        deleteFile(item);
      }
      return;
    }

    // Toggle selection
    card.classList.toggle('selected');
    updateSelectedItems();
  }

  function handleGridDblClick(e) {
    const card = e.target.closest('.fm-card');
    if (!card) return;

    const item = findItemByPath(card.dataset.path);
    if (item.type === 'dir') {
      loadFiles(item.fullPath);
    } else if (item.isImage) {
      openDetailModal(item);
    } else if (typeof pickFileCallback === 'function') {
      // Callback para seleção de arquivo (quando usado em modal)
      pickFileCallback({
        name: item.name,
        fullPath: () => item.fullPath.replace(/^\//, '')
      });
    }
  }

  // ==========================================
  // List View Event Handlers
  // ==========================================
  function handleListClick(e) {
    const row = e.target.closest('tr[data-path]');
    if (!row) return;

    const action = e.target.closest('button');
    if (action) {
      e.stopPropagation();
      const item = findItemByPath(row.dataset.path);
      
      if (action.classList.contains('view')) {
        openDetailModal(item);
      } else if (action.classList.contains('copy')) {
        copyLink(item);
      } else if (action.classList.contains('delete')) {
        deleteFile(item);
      }
      return;
    }

    // Toggle selection
    row.classList.toggle('selected');
    updateSelectedItems();
  }

  function handleListDblClick(e) {
    const row = e.target.closest('tr[data-path]');
    if (!row) return;

    const item = findItemByPath(row.dataset.path);
    if (item.type === 'dir') {
      loadFiles(item.fullPath);
    } else if (item.isImage) {
      openDetailModal(item);
    } else if (typeof pickFileCallback === 'function') {
      pickFileCallback({
        name: item.name,
        fullPath: () => item.fullPath.replace(/^\//, '')
      });
    }
  }

  function handleListContextMenu(e) {
    const row = e.target.closest('tr[data-path]');
    if (!row) return;

    e.preventDefault();
    const item = findItemByPath(row.dataset.path);
    showContextMenu(e.clientX, e.clientY, item);
  }

  function handleContextMenu(e) {
    const card = e.target.closest('.fm-card');
    if (!card) return;

    e.preventDefault();
    const item = findItemByPath(card.dataset.path);
    showContextMenu(e.clientX, e.clientY, item);
  }

  function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    elements.dropzone?.classList.add('dragover');
  }

  function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    elements.dropzone?.classList.remove('dragover');
  }

  function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    elements.dropzone?.classList.remove('dragover');

    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
      // Simula seleção via input
      const dt = new DataTransfer();
      Array.from(files).forEach(f => dt.items.add(f));
      if (elements.fileInput) {
        elements.fileInput.files = dt.files;
        handleFileSelect();
      }
    }
  }

  function handleFileSelect() {
    const files = elements.fileInput?.files;
    if (!files || files.length === 0) {
      elements.uploadPreview?.classList.remove('show');
      elements.btnUploadConfirm.disabled = true;
      return;
    }

    // Renderiza preview dos arquivos
    let html = '';
    Array.from(files).forEach((file, index) => {
      const isImage = file.type.startsWith('image/');
      html += `
        <div class="fm-upload-item" data-index="${index}">
          <div class="fm-upload-item-preview">
            ${isImage 
              ? `<img src="${URL.createObjectURL(file)}" alt="">` 
              : `<i class="fa fa-file-o"></i>`
            }
          </div>
          <div class="fm-upload-item-info">
            <div class="fm-upload-item-name">${escapeHtml(file.name)}</div>
            <div class="fm-upload-item-size">${formatBytes(file.size)}</div>
          </div>
        </div>
      `;
    });

    if (elements.uploadList) {
      elements.uploadList.innerHTML = html;
    }
    elements.uploadPreview?.classList.add('show');
    elements.btnUploadConfirm.disabled = false;
  }

  // ==========================================
  // Modal Functions
  // ==========================================
  async function openUploadModal() {
    // Verifica se storage está cheio
    if (!state.storage.unlimited && state.storage.percent >= 100) {
      showStorageLimitModal();
      return;
    }

    // Reset
    if (elements.fileInput) elements.fileInput.value = '';
    if (elements.uploadList) elements.uploadList.innerHTML = '';
    elements.uploadPreview?.classList.remove('show');
    elements.btnUploadConfirm.disabled = true;
    
    elements.uploadModal?.classList.add('show');

    // Carrega pastas no seletor
    await loadFoldersForUpload();
  }

  async function loadFoldersForUpload() {
    const folderSelect = document.getElementById('fm-upload-folder');
    if (!folderSelect) return;

    // Mostra indicador de carregamento
    const currentPathLabel = state.currentPath === '/' ? '/ (Raíz - pasta atual)' : state.currentPath + ' (pasta atual)';
    folderSelect.innerHTML = `<option value="${state.currentPath}">⏳ Carregando pastas...</option>`;
    folderSelect.disabled = true;

    try {
      const folders = await loadAllFolders();
      
      // Limpa e adiciona pasta atual primeiro
      folderSelect.innerHTML = `<option value="${state.currentPath}">${currentPathLabel}</option>`;
      
      // Adiciona pasta raiz se não for a atual
      if (state.currentPath !== '/') {
        folderSelect.innerHTML += '<option value="/">🏠 / (Raíz)</option>';
      }

      // Adiciona outras pastas encontradas
      if (folders && folders.length > 0) {
        folders.forEach(folder => {
          if (folder.path !== state.currentPath) {
            const indent = '\u00A0\u00A0'.repeat(folder.level);
            folderSelect.innerHTML += `<option value="${escapeHtml(folder.path)}">${indent}📁 ${escapeHtml(folder.name)}</option>`;
          }
        });
      }
      
      // Log para debug
      console.log('[FileManager] Pastas carregadas:', folders.length, folders);
      
    } catch (error) {
      console.error('[FileManager] Erro ao carregar pastas:', error);
      // Em caso de erro, mantém apenas a pasta atual
      folderSelect.innerHTML = `<option value="${state.currentPath}">${currentPathLabel}</option>`;
    }
    
    folderSelect.disabled = false;
  }

  function openDetailModal(item) {
    if (!item || !elements.detailModal) return;

    const previewEl = elements.detailModal.querySelector('.fm-detail-preview');
    const nameEl = elements.detailModal.querySelector('[data-field="name"]');
    const typeEl = elements.detailModal.querySelector('[data-field="type"]');
    const sizeEl = elements.detailModal.querySelector('[data-field="size"]');
    const dateEl = elements.detailModal.querySelector('[data-field="date"]');
    const urlEl = elements.detailModal.querySelector('[data-field="url"]');

    const fullUrl = CONFIG.baseUrl + item.fullPath;

    if (previewEl) {
      if (item.isImage) {
        previewEl.innerHTML = `<img src="${fullUrl}" alt="">`;
      } else {
        previewEl.innerHTML = `<i class="fa fa-file-o"></i>`;
      }
    }

    if (nameEl) nameEl.textContent = item.name;
    if (typeEl) typeEl.textContent = item.extension?.toUpperCase() || item.type;
    if (sizeEl) sizeEl.textContent = formatBytes(item.size);
    if (dateEl) dateEl.textContent = formatDate(item.date);
    if (urlEl) urlEl.value = fullUrl;

    // Bind actions
    const copyBtn = elements.detailModal.querySelector('.fm-btn-copy');
    const downloadBtn = elements.detailModal.querySelector('.fm-btn-download');
    const deleteBtn = elements.detailModal.querySelector('.fm-btn-delete');

    copyBtn?.addEventListener('click', () => copyToClipboard(fullUrl));
    downloadBtn?.setAttribute('href', fullUrl);
    downloadBtn?.setAttribute('download', item.name);
    deleteBtn?.addEventListener('click', () => {
      closeModals();
      deleteFile(item);
    });

    elements.detailModal.classList.add('show');
  }

  function showStorageLimitModal() {
    swal({
      title: 'Armazenamento Cheio',
      text: `Você está usando ${state.storage.used_mb.toFixed(2)} MB de ${state.storage.limit_mb} MB disponíveis. Exclua arquivos ou faça upgrade do seu plano.`,
      type: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ver Planos',
      cancelButtonText: 'Gerenciar Arquivos'
    }, function(confirmed) {
      if (confirmed) {
        window.location.href = baseUrl + 'conta/planos';
      }
    });
  }

  function closeModals() {
    document.querySelectorAll('.fm-modal-backdrop').forEach(modal => {
      modal.classList.remove('show');
    });
  }

  function showContextMenu(x, y, item) {
    if (!elements.contextMenu) return;

    elements.contextMenu.innerHTML = `
      ${item.type !== 'dir' ? `
        <button data-action="view"><i class="fa fa-eye"></i> Visualizar</button>
        <button data-action="copy"><i class="fa fa-link"></i> Copiar link</button>
        <button data-action="download"><i class="fa fa-download"></i> Download</button>
        <hr>
        <button data-action="move"><i class="fa fa-arrows"></i> Mover para pasta</button>
        <hr>
      ` : `
        <button data-action="open"><i class="fa fa-folder-open"></i> Abrir</button>
        <button data-action="move"><i class="fa fa-arrows"></i> Mover</button>
        <hr>
      `}
      <button data-action="delete" class="danger"><i class="fa fa-trash"></i> Excluir</button>
    `;

    // Position
    elements.contextMenu.style.left = x + 'px';
    elements.contextMenu.style.top = y + 'px';
    elements.contextMenu.classList.add('show');

    // Bind events
    elements.contextMenu.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        hideContextMenu();

        switch (action) {
          case 'view': openDetailModal(item); break;
          case 'copy': copyLink(item); break;
          case 'download': downloadFile(item); break;
          case 'open': loadFiles(item.fullPath); break;
          case 'move': openMoveModal(item); break;
          case 'delete': deleteFile(item); break;
        }
      });
    });
  }

  function hideContextMenu() {
    elements.contextMenu?.classList.remove('show');
  }

  // ==========================================
  // Utility Functions
  // ==========================================
  function filterItems() {
    if (!state.search) {
      state.filteredItems = [...state.items];
    } else {
      state.filteredItems = state.items.filter(item => 
        item.name.toLowerCase().includes(state.search)
      );
    }

    if (state.viewMode === 'grid') {
      renderGrid();
    } else {
      renderList();
    }
  }

  function setViewMode(mode) {
    state.viewMode = mode;
    
    elements.btnViewGrid?.classList.toggle('active', mode === 'grid');
    elements.btnViewList?.classList.toggle('active', mode === 'list');
    elements.content?.classList.toggle('view-list', mode === 'list');
    
    filterItems();
  }

  function findItemByPath(path) {
    return state.items.find(item => item.fullPath === path);
  }

  function updateSelectedItems() {
    state.selectedItems = Array.from(document.querySelectorAll('.fm-card.selected'))
      .map(card => findItemByPath(card.dataset.path))
      .filter(Boolean);
  }

  function copyLink(item) {
    const url = CONFIG.baseUrl + item.fullPath;
    copyToClipboard(url);
    showSuccess('Link copiado!');
  }

  async function copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (e) {
      const input = document.createElement('input');
      input.value = text;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);
      return true;
    }
  }

  function downloadFile(item) {
    const url = CONFIG.baseUrl + item.fullPath;
    const a = document.createElement('a');
    a.href = url;
    a.download = item.name;
    a.click();
  }

  function showLoading(show) {
    state.loading = show;
    elements.loading?.classList.toggle('show', show);
    elements.grid?.classList.toggle('loading', show);
  }

  function showSuccess(message) {
    if (typeof toastr !== 'undefined') {
      toastr.success(message);
    } else {
      alert(message);
    }
  }

  function showError(message) {
    if (typeof toastr !== 'undefined') {
      toastr.error(message);
    } else {
      alert('Erro: ' + message);
    }
  }

  function isImageFile(filename) {
    const ext = getExtension(filename);
    return ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico'].includes(ext);
  }

  function getExtension(filename) {
    return (filename || '').split('.').pop()?.toLowerCase() || '';
  }

  function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString('pt-BR');
    } catch (e) {
      return dateStr;
    }
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ==========================================
  // Inicialização
  // ==========================================
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expõe API pública
  window.FileManagerModern = {
    refresh: () => loadFiles(state.currentPath),
    navigate: (path) => loadFiles(path),
    getSelected: () => state.selectedItems
  };

})();
