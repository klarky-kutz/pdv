<div class="fm-modern">
  <!-- Alert Banner (Storage Warning) -->
  <div class="fm-alert-banner" id="fm-alert-banner">
    <i class="fa fa-exclamation-triangle alert-icon"></i>
    <span class="alert-text" id="fm-alert-text">Seu armazenamento está quase cheio.</span>
    <a href="<?php echo root_url(); ?>conta/planos" class="btn btn-warning btn-sm alert-action">Ver Planos</a>
  </div>

  <!-- Storage Usage Bar -->
  <div class="fm-storage-container" id="fm-storage-bar">
    <div class="fm-storage-header">
      <span class="fm-storage-title">
        <i class="fa fa-hdd-o"></i> Armazenamento
      </span>
      <a href="<?php echo root_url(); ?>conta/planos" class="fm-storage-link">Gerenciar plano &rarr;</a>
    </div>
    <div class="fm-storage-bar-wrapper">
      <div class="fm-storage-bar-fill success" style="width: 0%;"></div>
    </div>
    <div class="fm-storage-text">
      <span>Carregando...</span>
      <span></span>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="fm-toolbar">
    <div class="fm-toolbar-row">
      <!-- Search -->
      <div class="fm-search-wrap">
        <div class="fm-search">
          <i class="fa fa-search search-icon"></i>
          <input type="text" id="fm-search" placeholder="Pesquisar arquivos...">
        </div>
      </div>

      <!-- Stats -->
      <div class="fm-stats">
        <span class="fm-stat fm-stat--blue">
          <i class="fa fa-file-o"></i>
          <span id="fm-stat-files">0 Arquivos</span>
        </span>
        <span class="fm-stat fm-stat--green">
          <i class="fa fa-hdd-o"></i>
          <span id="fm-stat-size">0 B</span>
        </span>
        <span class="fm-stat fm-stat--purple">
          <i class="fa fa-image"></i>
          <span id="fm-stat-images">0 Imagens</span>
        </span>
      </div>

      <!-- Actions -->
      <div class="fm-actions">
        <div class="fm-view-toggle">
          <button id="fm-view-grid" class="active" title="Visualização em Grade">
            <i class="fa fa-th"></i>
          </button>
          <button id="fm-view-list" title="Visualização em Lista">
            <i class="fa fa-list"></i>
          </button>
        </div>
        <button class="fm-btn fm-btn-secondary" id="fm-btn-folder">
          <i class="fa fa-folder-o"></i> Nova Pasta
        </button>
        <button class="fm-btn fm-btn-primary" id="fm-btn-upload">
          <i class="fa fa-upload"></i> Carregar Arquivos
        </button>
      </div>
    </div>
  </div>

  <!-- Breadcrumb -->
  <div class="fm-breadcrumb" id="fm-breadcrumb">
    <a href="#" data-path="/"><i class="fa fa-home"></i></a>
  </div>

  <!-- Content -->
  <div class="fm-content" id="fm-content">
    <!-- Loading -->
    <div class="fm-loading" id="fm-loading">
      <div class="fm-spinner"></div>
      <span class="fm-loading-text">Carregando arquivos...</span>
    </div>

    <!-- Empty State -->
    <div class="fm-empty" id="fm-empty">
      <div class="fm-empty-icon">
        <i class="fa fa-folder-open-o"></i>
      </div>
      <h3>Nenhum arquivo encontrado</h3>
      <p>Envie imagens, vídeos ou documentos para começar sua biblioteca.</p>
    </div>

    <!-- Grid View -->
    <div class="fm-grid" id="fm-grid"></div>

    <!-- List View -->
    <div class="fm-list" id="fm-list"></div>
  </div>
</div>

<!-- Context Menu -->
<div class="fm-context-menu" id="fm-context-menu"></div>

<!-- Modal: Upload -->
<div class="fm-modal-backdrop" id="fm-upload-modal">
  <div class="fm-modal">
    <div class="fm-modal-header">
      <h3><i class="fa fa-upload"></i> Carregar Arquivos</h3>
      <button class="fm-modal-close"><i class="fa fa-times"></i></button>
    </div>
    <div class="fm-modal-body">
      <!-- Seletor de Pasta de Destino -->
      <div class="fm-upload-destination">
        <label for="fm-upload-folder"><i class="fa fa-folder-open"></i> Pasta de destino:</label>
        <select id="fm-upload-folder" class="fm-select">
          <option value="/">/ (Raíz - pasta atual)</option>
        </select>
        <small class="fm-help-text">Selecione onde os arquivos serão salvos</small>
      </div>

      <!-- Dropzone -->
      <div class="fm-dropzone" id="fm-dropzone">
        <input type="file" id="fm-file-input" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
        <div class="fm-dropzone-icon">
          <i class="fa fa-cloud-upload"></i>
        </div>
        <h4>Arraste arquivos aqui</h4>
        <p>ou clique para selecionar</p>
      </div>

      <!-- Preview -->
      <div class="fm-upload-preview" id="fm-upload-preview">
        <div id="fm-upload-list"></div>
      </div>
    </div>
    <div class="fm-modal-footer">
      <button class="fm-btn fm-btn-secondary fm-modal-close">Cancelar</button>
      <button class="fm-btn fm-btn-primary" id="fm-upload-confirm" disabled>Enviar</button>
    </div>
  </div>
</div>

<!-- Modal: Detail -->
<div class="fm-modal-backdrop" id="fm-detail-modal">
  <div class="fm-modal">
    <div class="fm-modal-header">
      <h3><i class="fa fa-info-circle"></i> Informações do Arquivo</h3>
      <button class="fm-modal-close"><i class="fa fa-times"></i></button>
    </div>
    <div class="fm-modal-body">
      <div class="fm-detail-preview"></div>
      
      <div class="fm-detail-grid">
        <div class="fm-detail-item">
          <div class="fm-detail-label">Nome</div>
          <div class="fm-detail-value" data-field="name">-</div>
        </div>
        <div class="fm-detail-item">
          <div class="fm-detail-label">Tipo</div>
          <div class="fm-detail-value" data-field="type">-</div>
        </div>
        <div class="fm-detail-item">
          <div class="fm-detail-label">Tamanho</div>
          <div class="fm-detail-value" data-field="size">-</div>
        </div>
        <div class="fm-detail-item">
          <div class="fm-detail-label">Data</div>
          <div class="fm-detail-value" data-field="date">-</div>
        </div>
        <div class="fm-detail-item fm-detail-url">
          <div class="fm-detail-label">URL</div>
          <input type="text" data-field="url" readonly onclick="this.select()">
        </div>
      </div>

      <div class="fm-detail-actions">
        <button class="fm-btn fm-btn-secondary fm-btn-copy">
          <i class="fa fa-copy"></i> Copiar Link
        </button>
        <a class="fm-btn fm-btn-secondary fm-btn-download" href="#" download>
          <i class="fa fa-download"></i> Download
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nova Pasta -->
<div class="fm-modal-backdrop" id="fm-folder-modal">
  <div class="fm-modal fm-modal-folder">
    <div class="fm-modal-header fm-modal-header-folder">
      <button class="fm-modal-close"><i class="fa fa-times"></i></button>
      <div class="fm-folder-modal-icon">
        <i class="fa fa-folder-open"></i>
      </div>
      <h3>Criar Nova Pasta</h3>
    </div>
    <div class="fm-modal-body">
      <div class="fm-folder-form">
        <div class="fm-form-group">
          <label for="fm-folder-name">
            <i class="fa fa-pencil"></i> Nome da pasta
          </label>
          <div class="fm-input-wrapper">
            <span class="fm-input-icon"><i class="fa fa-folder"></i></span>
            <input type="text" id="fm-folder-name" class="fm-input fm-input-lg" placeholder="Ex: Produtos, Imagens, Documentos..." autocomplete="off">
          </div>
          <div class="fm-folder-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <span>Evite caracteres especiais: / \ : * ? " &lt; &gt; |</span>
          </div>
        </div>
        
        <div class="fm-folder-location">
          <span class="fm-location-label">
            <i class="fa fa-map-marker"></i> Localização:
          </span>
          <span class="fm-location-path" id="fm-folder-location-path">/ (Raiz)</span>
        </div>
      </div>
    </div>
    <div class="fm-modal-footer">
      <button class="fm-btn fm-btn-secondary fm-modal-close">
        <i class="fa fa-times"></i> Cancelar
      </button>
      <button class="fm-btn fm-btn-success" id="fm-folder-confirm" disabled>
        <i class="fa fa-folder-open"></i> Criar Pasta
      </button>
    </div>
  </div>
</div>

<!-- Modal: Mover Arquivo -->
<div class="fm-modal-backdrop" id="fm-move-modal">
  <div class="fm-modal">
    <div class="fm-modal-header">
      <h3><i class="fa fa-arrows"></i> Mover Arquivo</h3>
      <button class="fm-modal-close"><i class="fa fa-times"></i></button>
    </div>
    <div class="fm-modal-body">
      <div class="fm-move-current-path">
        Mover: <strong id="fm-move-filename">-</strong>
      </div>
      <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 12px;">Selecione a pasta de destino:</p>
      <div class="fm-move-folder-list" id="fm-move-folder-list">
        <!-- Lista de pastas será renderizada aqui -->
      </div>
    </div>
    <div class="fm-modal-footer">
      <button class="fm-btn fm-btn-secondary fm-modal-close">Cancelar</button>
      <button class="fm-btn fm-btn-primary" id="fm-move-confirm" disabled>
        <i class="fa fa-check"></i> Mover
      </button>
    </div>
  </div>
</div>

<script>
  // Configura URL do filemanager
  window.FILEMANAGERURL = '<?php echo FILEMANAGERURL ?: root_url() . 'storage/products'; ?>';
</script>
