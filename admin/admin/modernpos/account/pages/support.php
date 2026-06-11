<?php
/**
 * ModernPOS - Módulo de Suporte (Cliente)
 * Listagem de tickets do cliente/tenant
 */

// Obtém tenant_id da sessão
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
$userId = function_exists('user_id') ? (int)user_id() : 0;

// Carrega tickets do tenant
$tickets = [];
$totalTickets = 0;
$openTickets = 0;
$pendingTickets = 0;
$closedTickets = 0;

try {
    $pdo = db();
    
    if ($tenantId > 0) {
        $sql = "SELECT t.id,
                       t.code,
                       t.subject,
                       t.status,
                       t.priority,
                       t.category_id,
                       COALESCE(c.name, 'Geral') AS category,
                       COALESCE(c.color, '#64748b') AS category_color,
                       t.messages_count AS messages,
                       t.created_at,
                       COALESCE(t.last_message_at, t.created_at) AS updated_at
                  FROM support_tickets t
             LEFT JOIN support_categories c ON c.id = t.category_id AND (c.tenant_id = t.tenant_id OR c.tenant_id = 0)
                 WHERE t.tenant_id = :tenant_id
                   AND t.deleted_at IS NULL
              ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Conta estatísticas
        foreach ($tickets as $t) {
            $totalTickets++;
            $status = strtolower(str_replace(' ', '_', $t['status']));
            if ($status === 'open' || $status === 'waiting_client') {
                $openTickets++;
            } elseif ($status === 'on_hold' || $status === 'on-hold') {
                $pendingTickets++;
            } elseif ($status === 'closed' || $status === 'resolved') {
                $closedTickets++;
            }
        }
    }
} catch (Exception $e) {
    $tickets = [];
}

// Carrega categorias do suporte (cadastradas no SaaS) para o tenant
$categories = [];
try {
    if ($tenantId > 0) {
        $pdoCats = isset($pdo) ? $pdo : db();
        $stmtCats = $pdoCats->prepare("
            SELECT id, name, color
              FROM support_categories
             WHERE (tenant_id = :tenant_id OR tenant_id = 0)
               AND is_active = 1
          ORDER BY name ASC
        ");
        $stmtCats->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmtCats->execute();
        $categories = $stmtCats->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    $categories = [];
}

// Funções auxiliares
function support_status_tone($status) {
    $s = strtolower(trim(str_replace(' ', '_', $status)));
    if ($s === 'open') return 'open';
    if ($s === 'on-hold' || $s === 'on_hold') return 'hold';
    if ($s === 'waiting_client') return 'waiting';
    if ($s === 'resolved') return 'resolved';
    if ($s === 'cancelled') return 'cancelled';
    return 'closed';
}

function support_status_label($status) {
    $s = strtolower(trim(str_replace(' ', '_', $status)));
    switch ($s) {
        case 'open': return 'Aberto';
        case 'on_hold':
        case 'on-hold': return 'Em Espera';
        case 'waiting_client': return 'Aguardando';
        case 'resolved': return 'Resolvido';
        case 'closed': return 'Fechado';
        case 'cancelled': return 'Cancelado';
        default: return ucfirst($status);
    }
}

function support_priority_class($priority) {
    $p = strtolower(trim((string)$priority));
    if ($p === 'critical') return 'support-priority--critical';
    if ($p === 'high') return 'support-priority--high';
    if ($p === 'medium') return 'support-priority--medium';
    if ($p === 'low') return 'support-priority--low';
    return 'support-priority--medium';
}

function support_fmt_date($dateTime) {
    $ts = strtotime($dateTime);
    if (!$ts) return '';
    return date('d/m/Y H:i', $ts);
}

function support_days_ago($dateTime) {
    $ts = strtotime($dateTime);
    if (!$ts) return 0;
    return (int)floor((time() - $ts) / 86400);
}
?>

<div class="app-content">
  <div class="container-fluid">
    
    <!-- Título da página -->
    <div class="row mt-4 mb-3">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <h3 class="mb-1">
              <i class="bi bi-life-preserver text-primary me-2"></i>
              Suporte - Meus Tickets
            </h3>
            <p class="text-muted mb-0">Acompanhe seus chamados de suporte e abra novos tickets</p>
          </div>
          <button type="button" class="btn btn-primary" onclick="abrirModalTicket()">
            <i class="bi bi-plus-lg me-1"></i>
            Novo Ticket
          </button>
        </div>
      </div>
    </div>
    
    <!-- Cards de estatísticas -->
    <div class="support-stats">
      <div class="support-stat-card">
        <div class="support-stat-card__icon tone-blue">
          <i class="bi bi-ticket-perforated"></i>
        </div>
        <div>
          <div class="support-stat-card__label">Total de Tickets</div>
          <div class="support-stat-card__value"><?php echo $totalTickets; ?></div>
        </div>
      </div>
      <div class="support-stat-card">
        <div class="support-stat-card__icon tone-green">
          <i class="bi bi-folder2-open"></i>
        </div>
        <div>
          <div class="support-stat-card__label">Abertos</div>
          <div class="support-stat-card__value"><?php echo $openTickets; ?></div>
        </div>
      </div>
      <div class="support-stat-card">
        <div class="support-stat-card__icon tone-amber">
          <i class="bi bi-pause-circle"></i>
        </div>
        <div>
          <div class="support-stat-card__label">Em Espera</div>
          <div class="support-stat-card__value"><?php echo $pendingTickets; ?></div>
        </div>
      </div>
      <div class="support-stat-card">
        <div class="support-stat-card__icon tone-slate">
          <i class="bi bi-check-circle"></i>
        </div>
        <div>
          <div class="support-stat-card__label">Finalizados</div>
          <div class="support-stat-card__value"><?php echo $closedTickets; ?></div>
        </div>
      </div>
    </div>
    
    <!-- Toolbar (estilo SaaS: Buscar + Filtros Dinâmicos) -->
    <div class="support-toolbar-card d-none d-md-block" id="supportToolbarCard">
      <div class="support-toolbar__top support-toolbar__top--no-title">
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" id="supportRefresh">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
      </div>
      <div class="support-toolbar__bottom">
        <div class="support-toolbar__left">
          <div class="input-group support-search-group">
            <span class="input-group-text support-search-icon"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="supportSearch" placeholder="Buscar assunto...">
          </div>
          <button class="btn btn-primary" id="supportDoSearch">Buscar</button>
          <button class="btn btn-light border support-btn-filter" id="supportFilterToggle" type="button">
            <i class="bi bi-funnel me-1"></i><span class="support-btn-filter-text">Filtros</span>
            <span class="support-badge-circle ms-1" id="supportFilterCount" style="display:none;">0</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Painel de filtros (desktop) -->
    <div id="support-filter-panel" class="support-filter-panel d-none d-md-block" aria-hidden="true">
      <div class="support-filter-panel__inner">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label small text-muted fw-bold text-uppercase">Status</label>
            <select class="form-select form-select-sm" id="supportFilterStatus">
              <option value="">Todos</option>
              <option value="open">Aberto</option>
              <option value="on_hold">Em Espera</option>
              <option value="waiting_client">Aguardando</option>
              <option value="resolved">Resolvido</option>
              <option value="closed">Fechado</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted fw-bold text-uppercase">Prioridade</label>
            <select class="form-select form-select-sm" id="supportFilterPriority">
              <option value="">Todas</option>
              <option value="critical">Crítica</option>
              <option value="high">Alta</option>
              <option value="medium">Média</option>
              <option value="low">Baixa</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted fw-bold text-uppercase">Categoria</label>
            <select class="form-select form-select-sm" id="supportFilterCategory">
              <option value="">Todas</option>
              <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-sm btn-primary w-100" id="supportApplyFilters">Aplicar</button>
          <button class="btn btn-sm btn-light border w-100" id="supportResetFilters">Resetar</button>
        </div>
      </div>
    </div>

    <!-- Toolbar mobile (simples) -->
    <div class="support-mobile-toolbar d-block d-md-none">
      <div class="input-group mb-2">
        <input type="text" id="supportSearchMobile" class="form-control" placeholder="Buscar assunto...">
        <button class="btn btn-primary" type="button" id="supportDoSearchMobile"><i class="bi bi-search"></i></button>
      </div>
      <div class="row g-2">
        <div class="col-12">
          <select class="form-select" id="supportFilterStatusMobile">
            <option value="">Todos os status</option>
            <option value="open">Aberto</option>
            <option value="on_hold">Em Espera</option>
            <option value="waiting_client">Aguardando</option>
            <option value="resolved">Resolvido</option>
            <option value="closed">Fechado</option>
          </select>
        </div>
        <div class="col-12">
          <select class="form-select" id="supportFilterPriorityMobile">
            <option value="">Todas as prioridades</option>
            <option value="critical">Crítica</option>
            <option value="high">Alta</option>
            <option value="medium">Média</option>
            <option value="low">Baixa</option>
          </select>
        </div>
        <div class="col-12">
          <select class="form-select" id="supportFilterCategoryMobile">
            <option value="">Todas as categorias</option>
            <?php if (!empty($categories)): ?>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
      </div>
    </div>
    
    <!-- Tabela de tickets -->
    <div class="support-table-wrap">
      <?php if (empty($tickets)): ?>
        <div class="support-empty">
          <div class="support-empty__icon">
            <i class="bi bi-ticket-perforated"></i>
          </div>
          <div class="support-empty__title">Nenhum ticket encontrado</div>
          <div class="support-empty__text">Você ainda não abriu nenhum chamado de suporte.</div>
          <button type="button" class="btn btn-primary" onclick="abrirModalTicket()">
            <i class="bi bi-plus-lg me-1"></i>
            Abrir Primeiro Ticket
          </button>
        </div>
      <?php else: ?>
        <table class="table support-table">
          <thead>
            <tr>
              <th>Assunto</th>
              <th style="width: 140px;">Categoria</th>
              <th style="width: 140px;">Status</th>
              <th style="width: 130px;">Atualizado</th>
              <th style="width: 80px;">Ações</th>
            </tr>
          </thead>
          <tbody id="ticketsTableBody">
            <?php foreach ($tickets as $t): 
              $tone = support_status_tone($t['status']);
              $statusLabel = support_status_label($t['status']);
              $daysAgo = support_days_ago($t['updated_at']);
              $category = $t['category'] ?? '—';
              $categoryColor = $t['category_color'] ?? '#64748b';
            ?>
              <tr data-ticket-id="<?php echo (int)$t['id']; ?>"
                  data-status="<?php echo htmlspecialchars(strtolower(str_replace(' ', '_', $t['status'])), ENT_QUOTES, 'UTF-8'); ?>"
                  data-priority="<?php echo htmlspecialchars(strtolower((string)($t['priority'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                  data-category-id="<?php echo (int)($t['category_id'] ?? 0); ?>"
                  data-category="<?php echo htmlspecialchars(strtolower((string)($t['category'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                  data-subject="<?php echo htmlspecialchars(strtolower($t['subject']), ENT_QUOTES, 'UTF-8'); ?>">
                <td>
                  <div class="support-ticket-subject">
                    <a href="<?php echo root_url(); ?>conta/suporte/ticket?id=<?php echo (int)$t['id']; ?>" class="support-ticket-link support-ticket-link--blue">
                      <?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </div>
                  <div class="support-ticket-meta">
                    <span class="support-ticket-code">#<?php echo htmlspecialchars($t['code'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo support_fmt_date($t['created_at']); ?></span>
                    <span><i class="bi bi-chat-left-dots"></i> <?php echo (int)$t['messages']; ?></span>
                  </div>
                </td>
                <td>
                  <?php if ($category !== '—'): ?>
                    <span class="support-category-badge" style="background-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff;">
                      <i class="bi bi-bookmark-fill me-1"></i><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="support-status support-status--<?php echo $tone; ?>">
                    <span class="status-dot"></span>
                    <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </td>
                <td>
                  <span class="text-muted small">
                    <?php if ($daysAgo === 0): ?>
                      Hoje
                    <?php elseif ($daysAgo === 1): ?>
                      Ontem
                    <?php else: ?>
                      <?php echo $daysAgo; ?> dias atrás
                    <?php endif; ?>
                  </span>
                </td>
                <td>
                  <div class="support-actions">
                    <a href="<?php echo root_url(); ?>conta/suporte/ticket?id=<?php echo (int)$t['id']; ?>" 
                       class="btn btn-light btn-sm support-action-btn" 
                       title="Ver ticket">
                      <i class="bi bi-eye"></i>
                    </a>
                    <?php 
                    $statusLower = strtolower(str_replace(' ', '_', $t['status']));
                    $canCancel = !in_array($statusLower, ['closed', 'resolved', 'cancelled']);
                    if ($canCancel): ?>
                    <button type="button" 
                            class="btn btn-outline-danger btn-sm support-action-btn btn-cancel-ticket" 
                            title="Cancelar ticket"
                            data-ticket-id="<?php echo (int)$t['id']; ?>"
                            data-ticket-code="<?php echo htmlspecialchars($t['code'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-ticket-subject="<?php echo htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8'); ?>">
                      <i class="bi bi-x-circle"></i>
                    </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    
  </div>
</div>

<!-- Modal Premium: Criar Ticket -->
<div id="modalCreateTicketPremium" class="modal-premium-overlay" data-modal="create-ticket">
  <div class="modal-premium-card" style="max-width: 600px;">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-ticket-perforated-fill"></i>
        Abrir Novo Ticket
      </h4>
      <button class="btn-close-premium" onclick="fecharModalTicket()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body" style="max-height: 70vh; overflow-y: auto;">
      <form id="createTicketForm">
        
        <!-- Assunto -->
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-chat-left-text ic-modal"></i> Assunto <span class="text-danger">*</span>
          </label>
          <input type="text" id="ticketSubject" name="subject" class="input-premium" placeholder="Descreva brevemente o problema" required>
        </div>
        
        <!-- Categoria -->
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-bookmark ic-modal"></i> Categoria
          </label>
          <select id="ticketCategory" name="category_id" class="input-premium" style="padding-right: 40px;">
            <option value="">Geral</option>
            <?php if (!empty($categories)): ?>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>">
                  <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- fallback (caso não existam categorias no SaaS para este tenant) -->
              <option value="">Suporte Geral</option>
            <?php endif; ?>
          </select>
        </div>
        
        <!-- Descrição -->
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-text-paragraph ic-modal"></i> Descrição do problema <span class="text-danger">*</span>
          </label>
          <textarea id="ticketMessage" name="message" class="input-premium textarea-premium" rows="5" 
                    placeholder="Descreva detalhadamente o problema que você está enfrentando..." required></textarea>
          <div class="form-hint">Quanto mais detalhes você fornecer, mais rápido poderemos ajudá-lo.</div>
        </div>
        
        <!-- Anexo -->
        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-paperclip ic-modal"></i> Anexar imagem (opcional)
          </label>
          <div class="file-upload-premium">
            <label for="ticketAttachment" class="file-upload-area" id="ticketFileUploadArea">
              <div class="file-upload-icon">
                <i class="bi bi-cloud-arrow-up"></i>
              </div>
              <div class="file-upload-text">
                <span class="file-upload-title">Arraste e solte ou clique para selecionar</span>
                <span class="file-upload-hint">PNG, JPG até 10MB</span>
              </div>
              <input type="file" class="d-none" id="ticketAttachment" name="attachment" accept="image/*">
            </label>
            <div class="file-preview-area" id="ticketFilePreviewArea" style="display: none;">
              <div class="file-preview-item">
                <img id="ticketFilePreviewImg" src="" alt="Preview">
                <div class="file-preview-info">
                  <span class="file-preview-name" id="ticketAttachmentName"></span>
                  <button type="button" class="file-preview-remove" id="ticketFileRemoveBtn">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Campo oculto de prioridade (vazio - definido pelo operador no SAAS) -->
        <input type="hidden" id="ticketPriority" name="priority" value="">
        
      </form>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalTicket()">Cancelar</button>
      <button class="btn-save-premium" id="submitTicket">
        <i class="bi bi-send-fill"></i> Enviar Ticket
      </button>
    </div>
  </div>
</div>


<!-- Modal: Aviso de Ticket Existente -->
<div id="modalTicketWarning" class="modal-premium-overlay" data-modal="ticket-warning">
  <div class="modal-premium-card" style="max-width: 500px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Você já tem um ticket aberto
      </h4>
      <button class="btn-close-premium" onclick="fecharModalWarning()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-ticket-perforated text-warning" style="font-size: 3rem;"></i>
        </div>
        <p class="mb-2">Você já possui um ticket em andamento. Considere adicionar informações ao ticket existente antes de abrir um novo.</p>
        <p class="text-muted small">Limite máximo: 2 tickets abertos simultaneamente.</p>
      </div>
      
      <div class="mb-3">
        <label class="form-label fw-bold"><i class="bi bi-list-ul me-1"></i> Seu ticket em andamento:</label>
        <div class="list-group" id="warningTicketsList">
          <!-- Preenchido via JS -->
        </div>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalWarning()">Cancelar</button>
      <button class="btn-save-premium" onclick="confirmarNovoTicket()" style="background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);">
        <i class="bi bi-plus-lg"></i> Abrir Novo Ticket Mesmo Assim
      </button>
    </div>
  </div>
</div>

<!-- Modal: Bloqueio de Tickets -->
<div id="modalTicketBlocked" class="modal-premium-overlay" data-modal="ticket-blocked">
  <div class="modal-premium-card" style="max-width: 500px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-x-octagon-fill"></i>
        Limite de Tickets Atingido
      </h4>
      <button class="btn-close-premium" onclick="fecharModalBlocked()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-exclamation-octagon text-danger" style="font-size: 3rem;"></i>
        </div>
        <p class="mb-2"><strong>Você já possui 2 tickets abertos.</strong></p>
        <p class="text-muted">Para abrir um novo ticket, é necessário aguardar a conclusão de pelo menos um dos tickets existentes.</p>
      </div>
      
      <div class="mb-3">
        <label class="form-label fw-bold"><i class="bi bi-list-ul me-1"></i> Seus tickets em andamento:</label>
        <div class="list-group" id="blockedTicketsList">
          <!-- Preenchido via JS -->
        </div>
      </div>
      
      <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle me-2"></i>
        <small>Clique em um ticket acima para acompanhar ou adicionar informações.</small>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;">
      <button class="btn-cancel-premium" onclick="fecharModalBlocked()" style="min-width: 150px;">
        <i class="bi bi-check-lg me-1"></i> Entendi
      </button>
    </div>
  </div>
</div>

<!-- Modal: Confirmar Cancelamento de Ticket -->
<div id="modalCancelTicket" class="modal-premium-overlay" data-modal="cancel-ticket">
  <div class="modal-premium-card" style="max-width: 500px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-x-circle-fill"></i>
        Cancelar Ticket
      </h4>
      <button class="btn-close-premium" onclick="fecharModalCancelTicket()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
        </div>
        <p class="mb-1">Você tem certeza que deseja cancelar o ticket:</p>
        <p class="fw-bold mb-2" id="cancelTicketSubject">-</p>
        <p class="text-muted small" id="cancelTicketCode">-</p>
      </div>
      
      <div class="form-group-premium mb-3">
        <label class="label-premium">
          <i class="bi bi-chat-left-text ic-modal"></i> Motivo do cancelamento (opcional)
        </label>
        <textarea id="cancelTicketReason" class="input-premium textarea-premium" rows="3" 
                  placeholder="Informe o motivo do cancelamento..."></textarea>
      </div>
      
      <div class="alert alert-warning mb-0">
        <i class="bi bi-info-circle me-2"></i>
        <small>Após cancelar, o ticket será fechado e você não poderá reabri-lo.</small>
      </div>
    </div>
    <div class="modal-premium-footer">
      <button class="btn-cancel-premium" onclick="fecharModalCancelTicket()">Voltar</button>
      <button class="btn-save-premium" id="confirmCancelTicket" style="background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);">
        <i class="bi bi-x-circle"></i> Confirmar Cancelamento
      </button>
    </div>
    <input type="hidden" id="cancelTicketId" value="">
  </div>
</div>

<script src="<?php echo root_url(); ?>account/js/support.js"></script>
<script>
// Funções para cancelar ticket
let cancelTicketData = null;

function abrirModalCancelTicket(ticketId, ticketCode, ticketSubject) {
  cancelTicketData = { id: ticketId, code: ticketCode, subject: ticketSubject };
  document.getElementById('cancelTicketId').value = ticketId;
  document.getElementById('cancelTicketSubject').textContent = ticketSubject;
  document.getElementById('cancelTicketCode').textContent = '#' + ticketCode;
  document.getElementById('cancelTicketReason').value = '';
  document.getElementById('modalCancelTicket').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function fecharModalCancelTicket() {
  document.getElementById('modalCancelTicket').classList.remove('active');
  document.body.style.overflow = '';
  cancelTicketData = null;
}

document.addEventListener('DOMContentLoaded', function() {
  // Associar evento aos botões de cancelar
  document.querySelectorAll('.btn-cancel-ticket').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const ticketId = this.dataset.ticketId;
      const ticketCode = this.dataset.ticketCode;
      const ticketSubject = this.dataset.ticketSubject;
      abrirModalCancelTicket(ticketId, ticketCode, ticketSubject);
    });
  });
  
  // Confirmar cancelamento
  document.getElementById('confirmCancelTicket').addEventListener('click', function() {
    const ticketId = document.getElementById('cancelTicketId').value;
    const reason = document.getElementById('cancelTicketReason').value.trim();
    
    if (!ticketId) return;
    
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cancelando...';
    
    fetch('<?php echo root_url(); ?>conta/_ajax/ticket_cancel.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ ticket_id: parseInt(ticketId, 10), reason: reason })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        fecharModalCancelTicket();
        // Atualiza o status na tabela para "Cancelado"
        const row = document.querySelector('tr[data-ticket-id="' + ticketId + '"]');
        if (row) {
          // Atualiza o badge de status
          const statusCell = row.querySelector('.support-status');
          if (statusCell) {
            statusCell.className = 'support-status support-status--cancelled';
            statusCell.innerHTML = '<span class="status-dot"></span> Cancelado';
          }
          // Atualiza o data-status da linha
          row.dataset.status = 'cancelled';
          // Desabilita o botão de cancelar (ao invés de remover)
          const cancelBtn = row.querySelector('.btn-cancel-ticket');
          if (cancelBtn) {
            cancelBtn.disabled = true;
            cancelBtn.classList.add('disabled');
            cancelBtn.classList.remove('btn-outline-danger');
            cancelBtn.classList.add('btn-secondary');
            cancelBtn.style.opacity = '0.5';
            cancelBtn.style.cursor = 'not-allowed';
            cancelBtn.title = 'Ticket já foi cancelado';
            // Remove o event listener do botão
            cancelBtn.onclick = function(e) { e.preventDefault(); return false; };
          }
        }
        
        // Atualiza os contadores de estatísticas na página (sem reload)
        try {
          // Decrementa contador de "Abertos" e incrementa "Finalizados"
          const statsCards = document.querySelectorAll('.support-stat-card');
          statsCards.forEach(function(card) {
            const label = card.querySelector('.support-stat-card__label');
            const valueEl = card.querySelector('.support-stat-card__value');
            if (label && valueEl) {
              const labelText = label.textContent.trim().toLowerCase();
              const currentValue = parseInt(valueEl.textContent, 10) || 0;
              if (labelText === 'abertos') {
                valueEl.textContent = Math.max(0, currentValue - 1);
              } else if (labelText === 'finalizados') {
                valueEl.textContent = currentValue + 1;
              }
            }
          });
        } catch (e) {
          console.warn('[Support] Erro ao atualizar estatísticas:', e);
        }
        
        // Mostra Toast de sucesso
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Ticket cancelado com sucesso!',
            text: data.message || '',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
          });
        }
        // NÃO recarrega a página - as atualizações de UI são suficientes
      } else {
        // Mostra Toast de erro
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Erro ao cancelar',
            text: data.error || 'Não foi possível cancelar o ticket.',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
          });
        } else {
          alert(data.error || 'Erro ao cancelar ticket.');
        }
      }
    })
    .catch(err => {
      console.error(err);
      alert('Erro ao cancelar ticket.');
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-x-circle"></i> Confirmar Cancelamento';
    });
  });
  
  // Fechar modal ao clicar fora
  document.getElementById('modalCancelTicket').addEventListener('click', function(e) {
    if (e.target === this) {
      fecharModalCancelTicket();
    }
  });
});
</script>
