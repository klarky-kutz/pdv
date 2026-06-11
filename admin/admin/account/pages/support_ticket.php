<?php
/**
 * ModernPOS - Módulo de Suporte (Cliente)
 * Visualização de ticket individual com conversa
 */

// Obtém tenant_id da sessão
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
$userId = function_exists('user_id') ? (int)user_id() : 0;
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$ticket = null;
$conversation = [];

// Funções auxiliares
function support_status_tone_view($status) {
    $s = strtolower(trim(str_replace(' ', '_', $status)));
    if ($s === 'open') return 'open';
    if ($s === 'on-hold' || $s === 'on_hold') return 'hold';
    if ($s === 'waiting_client') return 'waiting';
    if ($s === 'resolved') return 'resolved';
    return 'closed';
}

function support_status_label_view($status) {
    $s = strtolower(trim(str_replace(' ', '_', $status)));
    switch ($s) {
        case 'open': return 'Aberto';
        case 'on_hold':
        case 'on-hold': return 'Em Espera';
        case 'waiting_client': return 'Aguardando';
        case 'resolved': return 'Resolvido';
        case 'closed': return 'Fechado';
        default: return ucfirst($status);
    }
}

function support_priority_class_view($priority) {
    $p = strtolower(trim((string)$priority));
    if ($p === 'critical') return 'support-priority--critical';
    if ($p === 'high') return 'support-priority--high';
    if ($p === 'medium') return 'support-priority--medium';
    if ($p === 'low') return 'support-priority--low';
    return 'support-priority--medium';
}

function support_priority_label_view($priority) {
    $p = strtolower(trim((string)$priority));
    switch ($p) {
        case 'low': return 'Baixa';
        case 'medium': return 'Média';
        case 'high': return 'Alta';
        case 'critical': return 'Crítica';
        default: return '—';
    }
}

function support_fmt_date_view($dateTime) {
    $ts = strtotime($dateTime);
    if (!$ts) return '';
    return date('d/m/Y H:i', $ts);
}

// Carrega dados do ticket
if ($ticketId > 0 && $tenantId > 0) {
    try {
        $pdo = db();
        
        // Carrega ticket
        $stmt = $pdo->prepare("
            SELECT t.*,
                   COALESCE(c.name, 'Geral') AS category_name,
                   COALESCE(c.color, '#64748b') AS category_color
              FROM support_tickets t
         LEFT JOIN support_categories c ON c.id = t.category_id AND (c.tenant_id = t.tenant_id OR c.tenant_id = 0)
             WHERE t.id = :id
               AND t.tenant_id = :tenant_id
               AND t.deleted_at IS NULL
             LIMIT 1
        ");
        $stmt->bindValue(':id', $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ticket) {
            // Carrega mensagens
            $stmtMsg = $pdo->prepare("
                SELECT m.id,
                       m.body,
                       m.is_from_client,
                       m.created_at,
                       m.author_user_id
                  FROM support_ticket_messages m
                 WHERE m.ticket_id = :ticket_id
                   AND m.tenant_id = :tenant_id
                   AND (m.internal_visibility = 'public' OR m.internal_visibility IS NULL)
              ORDER BY m.created_at ASC
            ");
            $stmtMsg->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmtMsg->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmtMsg->execute();
            $messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);
            
            // Carrega anexos
            $stmtAtt = $pdo->prepare("
                SELECT a.id,
                       a.message_id,
                       a.file_name,
                       a.file_path,
                       a.mime_type
                  FROM support_ticket_attachments a
                 WHERE a.ticket_id = :ticket_id
                   AND a.tenant_id = :tenant_id
              ORDER BY a.created_at ASC
            ");
            $stmtAtt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
            $stmtAtt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmtAtt->execute();
            $attachments = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agrupa anexos por mensagem
            $attachmentsByMessage = [];
            foreach ($attachments as $att) {
                $msgId = (int)($att['message_id'] ?? 0);
                if ($msgId > 0) {
                    if (!isset($attachmentsByMessage[$msgId])) {
                        $attachmentsByMessage[$msgId] = [];
                    }
                    $attachmentsByMessage[$msgId][] = $att;
                }
            }
            
            // Monta conversa
            foreach ($messages as $m) {
                $isClient = !empty($m['is_from_client']);
                $msgId = (int)$m['id'];
                
                $conversation[] = [
                    'id' => $msgId,
                    'body' => $m['body'],
                    'is_client' => $isClient,
                    'created_at' => $m['created_at'],
                    'author_name' => $isClient ? ($ticket['requester_name'] ?? 'Você') : 'Suporte',
                    'attachments' => $attachmentsByMessage[$msgId] ?? []
                ];
            }
            
            // Se não há mensagens mas tem initial_message, usa como primeira mensagem
            if (empty($conversation) && !empty($ticket['initial_message'])) {
                $conversation[] = [
                    'id' => 0,
                    'body' => $ticket['initial_message'],
                    'is_client' => true,
                    'created_at' => $ticket['created_at'],
                    'author_name' => $ticket['requester_name'] ?? 'Você',
                    'attachments' => []
                ];
            }
        }
    } catch (Exception $e) {
        $ticket = null;
    }
}

// Verifica se ticket pode receber respostas
$ticketStatus = $ticket ? strtolower(str_replace(' ', '_', $ticket['status'])) : '';
$isClosed = in_array($ticketStatus, ['closed', 'resolved']);
$isOnHold = in_array($ticketStatus, ['on_hold', 'on-hold']);
$isWaitingClient = $ticketStatus === 'waiting_client';
$canReply = $ticket && !$isClosed && ($isWaitingClient || $ticketStatus === 'open');

// Verifica permissão de upload do cliente
$allowClientUploads = true;
if ($ticket && $tenantId > 0) {
    try {
        $stmtCfg = $pdo->prepare("SELECT allow_client_file_uploads FROM support_settings WHERE tenant_id = :tenant_id LIMIT 1");
        $stmtCfg->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmtCfg->execute();
        $rowCfg = $stmtCfg->fetch(PDO::FETCH_ASSOC);
        if ($rowCfg) {
            $allowClientUploads = !empty($rowCfg['allow_client_file_uploads']);
        }
    } catch (Exception $e) {
        // Mantém default
    }
}

// Separa a primeira mensagem (abertura) do restante
$opening = null;
$conversationRest = [];
if (!empty($conversation)) {
    $opening = $conversation[0];
    $conversationRest = array_slice($conversation, 1);
}
?>

<div class="app-content">
  <div class="container-fluid">
    
    <?php if (!$ticket): ?>
      <!-- Ticket não encontrado -->
      <div class="row mt-4">
        <div class="col-12">
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Ticket não encontrado ou você não tem permissão para visualizá-lo.
            <a href="<?php echo root_url(); ?>conta/suporte" class="alert-link ms-2">Voltar aos tickets</a>
          </div>
        </div>
      </div>
    <?php else: 
      $tone = support_status_tone_view($ticket['status']);
      $statusLabel = support_status_label_view($ticket['status']);
      $prioClass = support_priority_class_view($ticket['priority'] ?? '');
      $prioLabel = support_priority_label_view($ticket['priority'] ?? '');
      $categoryName = $ticket['category_name'] ?? '—';
      $categoryColor = $ticket['category_color'] ?? '#64748b';
    ?>
      
      <!-- Voltar -->
      <div class="row mt-4 mb-3">
        <div class="col-12">
          <a href="<?php echo root_url(); ?>conta/suporte" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>
            Voltar aos tickets
          </a>
        </div>
      </div>
      
      <div class="support-ticket-view">
        <!-- Coluna Principal -->
        <div class="support-ticket-main">
          
          <!-- Header do Ticket -->
          <div class="support-ticket-header">
            <div class="support-breadcrumb">
              <a href="<?php echo root_url(); ?>conta/suporte">Suporte</a>
              <span class="sep">/</span>
              <span>#<?php echo htmlspecialchars($ticket['code'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            
            <h4 class="support-ticket-title">
              <?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?>
            </h4>
            
            <div class="support-ticket-badges">
              <span class="support-status support-status--<?php echo $tone; ?>">
                <span class="status-dot"></span>
                <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
              </span>
              <span class="support-priority <?php echo $prioClass; ?>">
                <?php echo htmlspecialchars($prioLabel, ENT_QUOTES, 'UTF-8'); ?>
              </span>
              <?php if (!empty($categoryName) && $categoryName !== '—'): ?>
                <span class="support-category-badge" style="background-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff;">
                  <i class="bi bi-bookmark-fill me-1"></i><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              <?php endif; ?>
              <span class="text-muted small">
                <i class="bi bi-calendar3 me-1"></i>
                <?php echo support_fmt_date_view($ticket['created_at']); ?>
              </span>
            </div>
          </div>
          
          <!-- 1) ABERTURA DO PEDIDO -->
          <?php if ($opening): ?>
          <div class="support-opening-card">
            <div class="support-opening-card__header">
              <i class="bi bi-ticket-detailed me-2"></i>
              Abertura do pedido
            </div>
            <div class="support-opening-card__body">
              <div class="support-message support-message--client">
                <div class="support-avatar">
                  <?php echo strtoupper(substr($opening['author_name'], 0, 1)); ?>
                </div>
                <div class="support-message__body">
                  <div class="support-message__header">
                    <span class="support-message__name"><?php echo htmlspecialchars($opening['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="support-message__role support-message__role--client">Você</span>
                    <span class="support-message__time">
                      <i class="bi bi-clock me-1"></i>
                      <?php echo support_fmt_date_view($opening['created_at']); ?>
                    </span>
                  </div>
                  <div class="support-message__content">
                    <?php echo nl2br(htmlspecialchars($opening['body'], ENT_QUOTES, 'UTF-8')); ?>
                  </div>
                  
                  <?php if (!empty($opening['attachments'])): ?>
                    <div class="support-message__attachments">
                      <?php foreach ($opening['attachments'] as $att): 
                        $isImage = strpos(strtolower($att['mime_type'] ?? ''), 'image/') === 0;
                      ?>
                        <?php if ($isImage): ?>
                          <a href="<?php echo root_url() . htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                             target="_blank" 
                             class="support-attachment">
                            <img src="<?php echo root_url() . htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 alt="<?php echo htmlspecialchars($att['file_name'], ENT_QUOTES, 'UTF-8'); ?>">
                          </a>
                        <?php else: ?>
                          <a href="<?php echo root_url() . htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                             class="btn btn-sm btn-outline-secondary" 
                             download>
                            <i class="bi bi-paperclip me-1"></i>
                            <?php echo htmlspecialchars($att['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                          </a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
          
          <!-- 2) RESPONDER TICKET -->
          <?php if ($isOnHold): ?>
            <div class="support-waiting-alert support-waiting-alert--hold">
              <div class="support-waiting-alert__icon">
                <i class="bi bi-hourglass-split"></i>
              </div>
              <div class="support-waiting-alert__content">
                <div class="support-waiting-alert__title">Ticket em análise</div>
                <div class="support-waiting-alert__text">
                  Seu ticket está aguardando análise da nossa equipe de suporte. 
                  Você receberá uma notificação quando houver uma resposta.
                </div>
              </div>
            </div>
          <?php elseif ($isClosed): ?>
            <div class="support-waiting-alert support-waiting-alert--closed">
              <div class="support-waiting-alert__icon">
                <i class="bi bi-check-circle"></i>
              </div>
              <div class="support-waiting-alert__content">
                <div class="support-waiting-alert__title">Ticket <?php echo $ticketStatus === 'resolved' ? 'resolvido' : 'fechado'; ?></div>
                <div class="support-waiting-alert__text">
                  Este ticket foi finalizado e não aceita novas respostas.
                </div>
              </div>
            </div>
          <?php elseif ($canReply): ?>
            <?php if ($isWaitingClient): ?>
            <div class="support-waiting-alert support-waiting-alert--waiting">
              <div class="support-waiting-alert__icon">
                <i class="bi bi-chat-dots"></i>
              </div>
              <div class="support-waiting-alert__content">
                <div class="support-waiting-alert__title">Aguardando sua resposta</div>
                <div class="support-waiting-alert__text">
                  Nossa equipe de suporte aguarda sua resposta para dar continuidade ao atendimento.
                </div>
              </div>
            </div>
            <?php endif; ?>
            
            <div class="support-reply-card" id="replyCard">
              <div class="support-reply-card__header support-reply-card__header--toggle" onclick="toggleReplyCard()" style="cursor: pointer;">
                <span>
                  <i class="bi bi-reply-fill me-2"></i>
                  Responder ticket
                </span>
                <button type="button" class="btn btn-sm btn-light" id="replyToggleBtn">
                  <i class="bi bi-chevron-down" id="replyToggleIcon"></i>
                </button>
              </div>
              <div class="support-reply-card__body" id="replyCardBody" style="display: none;">
                <form id="replyTicketForm">
                  <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                  
                  <!-- Editor Quill -->
                  <div class="support-quill-wrapper">
                    <div id="replyQuillEditor"></div>
                    <input type="hidden" id="replyMessage" name="message">
                  </div>
                  
                  <?php if ($allowClientUploads): ?>
                  <!-- Área de Upload -->
                  <div class="support-reply-upload">
                    <div class="support-reply-upload__toggle">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="replyFileToggle">
                        <label class="form-check-label fw-bold" for="replyFileToggle">Anexar arquivo</label>
                      </div>
                    </div>
                    
                    <div class="support-reply-upload__area" id="replyFileArea" style="display:none;">
                      <label for="replyAttachment" class="support-file-dropzone" id="replyFileDropzone">
                        <div class="support-file-dropzone__icon">
                          <i class="bi bi-cloud-arrow-up"></i>
                        </div>
                        <div class="support-file-dropzone__text">
                          <span class="support-file-dropzone__title">Arraste e solte ou clique para enviar</span>
                          <span class="support-file-dropzone__hint">PNG, JPG até 10MB</span>
                        </div>
                        <input type="file" id="replyAttachment" name="attachment" class="d-none" accept="image/*">
                      </label>
                      
                      <div class="support-file-preview" id="replyFilePreview" style="display:none;">
                        <img id="replyFilePreviewImg" src="" alt="Preview">
                        <div class="support-file-preview__info">
                          <span class="support-file-preview__name" id="replyAttachmentName"></span>
                          <button type="button" class="support-file-preview__remove" id="replyFileRemoveBtn">
                            <i class="bi bi-x-lg"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                  
                  <!-- Botões -->
                  <div class="support-reply-actions">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitReply">
                      <i class="bi bi-send-fill me-2"></i>
                      Enviar resposta
                    </button>
                  </div>
                </form>
              </div>
            </div>
          <?php endif; ?>
          
          <!-- 3) CONVERSAS -->
          <?php if (!empty($conversationRest)): ?>
          <div class="support-conversation">
            <div class="support-conversation__title">
              <i class="bi bi-chat-left-text me-2"></i>
              Conversas
            </div>
            
            <?php foreach ($conversationRest as $msg): 
              $isClient = $msg['is_client'];
              $msgClass = $isClient ? 'support-message--client' : 'support-message--agent';
              $roleClass = $isClient ? 'support-message__role--client' : 'support-message__role--agent';
              $roleLabel = $isClient ? 'Você' : 'Suporte';
            ?>
              <div class="support-message <?php echo $msgClass; ?>">
                <div class="support-avatar">
                  <?php echo strtoupper(substr($msg['author_name'], 0, 1)); ?>
                </div>
                <div class="support-message__body">
                  <div class="support-message__header">
                    <span class="support-message__name"><?php echo htmlspecialchars($msg['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="support-message__role <?php echo $roleClass; ?>"><?php echo $roleLabel; ?></span>
                    <span class="support-message__time">
                      <i class="bi bi-clock me-1"></i>
                      <?php echo support_fmt_date_view($msg['created_at']); ?>
                    </span>
                  </div>
                  <div class="support-message__content">
                    <?php echo $msg['body']; ?>
                  </div>
                  
                  <?php if (!empty($msg['attachments'])): ?>
                    <div class="support-message__attachments">
                      <?php foreach ($msg['attachments'] as $att): 
                        $isImage = strpos(strtolower($att['mime_type'] ?? ''), 'image/') === 0;
                      ?>
                        <?php if ($isImage): ?>
                          <a href="<?php echo root_url() . htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                             target="_blank" 
                             class="support-attachment">
                            <img src="<?php echo root_url() . htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 alt="<?php echo htmlspecialchars($att['file_name'], ENT_QUOTES, 'UTF-8'); ?>">
                          </a>
                        <?php else: ?>
                          <a href="<?php echo root_url() . htmlspecialchars($att['file_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                             class="btn btn-sm btn-outline-secondary" 
                             download>
                            <i class="bi bi-paperclip me-1"></i>
                            <?php echo htmlspecialchars($att['file_name'], ENT_QUOTES, 'UTF-8'); ?>
                          </a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          
        </div>
        
        <!-- Sidebar -->
        <div class="support-ticket-side">
          
          <!-- Informações do Ticket -->
          <div class="support-info-card">
            <div class="support-info-card__title">
              <i class="bi bi-info-circle me-2"></i>
              Informações do Ticket
            </div>
            
            <div class="support-info-row">
              <span class="support-info-label">ID</span>
              <span class="support-info-value">#<?php echo (int)$ticket['id']; ?></span>
            </div>
            
            <div class="support-info-row">
              <span class="support-info-label">Código</span>
              <span class="support-info-value"><?php echo htmlspecialchars($ticket['code'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            
            <div class="support-info-row">
              <span class="support-info-label">Status</span>
              <span class="support-info-value">
                <span class="support-status support-status--<?php echo $tone; ?>">
                  <span class="status-dot"></span>
                  <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </span>
            </div>
            
            <div class="support-info-row">
              <span class="support-info-label">Prioridade</span>
              <span class="support-info-value">
                <span class="support-priority <?php echo $prioClass; ?>">
                  <?php echo htmlspecialchars($prioLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </span>
            </div>
            
            <div class="support-info-row">
              <span class="support-info-label">Categoria</span>
              <span class="support-info-value">
                <?php if (!empty($categoryName) && $categoryName !== '—'): ?>
                  <span class="support-category-badge" style="background-color: <?php echo htmlspecialchars($categoryColor, ENT_QUOTES, 'UTF-8'); ?>; color: #fff;">
                    <i class="bi bi-bookmark-fill me-1"></i><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </span>
            </div>
            
            <div class="support-info-row">
              <span class="support-info-label">Criado em</span>
              <span class="support-info-value"><?php echo support_fmt_date_view($ticket['created_at']); ?></span>
            </div>
            
            <?php if (!empty($ticket['last_message_at'])): ?>
            <div class="support-info-row">
              <span class="support-info-label">Última atualização</span>
              <span class="support-info-value"><?php echo support_fmt_date_view($ticket['last_message_at']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="support-info-row">
              <span class="support-info-label">Mensagens</span>
              <span class="support-info-value"><?php echo (int)$ticket['messages_count']; ?></span>
            </div>
          </div>
          
          <!-- Dicas -->
          <div class="support-info-card">
            <div class="support-info-card__title">
              <i class="bi bi-lightbulb me-2"></i>
              Dicas
            </div>
            <div class="small text-muted">
              <p class="mb-2">• Forneça o máximo de detalhes possível para agilizar o atendimento.</p>
              <p class="mb-2">• Anexe capturas de tela se necessário.</p>
              <p class="mb-0">• Nosso tempo médio de resposta é de 24 horas úteis.</p>
            </div>
          </div>
          
        </div>
      </div>
      
    <?php endif; ?>
    
  </div>
</div>


<!-- Quill Editor -->
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<script src="<?php echo root_url(); ?>account/js/support.js"></script>

<script>
(function() {
  'use strict';
  
  // Inicializa Quill se o editor existir
  var quillContainer = document.getElementById('replyQuillEditor');
  var quillInstance = null;
  
  if (quillContainer) {
    quillInstance = new Quill('#replyQuillEditor', {
      theme: 'snow',
      placeholder: 'Digite sua resposta aqui...',
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          ['link'],
          ['clean']
        ]
      }
    });
    
    // Atualiza o hidden input quando o conteudo mudar
    var hiddenInput = document.getElementById('replyMessage');
    if (hiddenInput) {
      quillInstance.on('text-change', function() {
        hiddenInput.value = quillInstance.root.innerHTML;
      });
    }
  }
  
  // Toggle de upload
  var fileToggle = document.getElementById('replyFileToggle');
  var fileArea = document.getElementById('replyFileArea');
  
  if (fileToggle && fileArea) {
    fileToggle.addEventListener('change', function() {
      fileArea.style.display = this.checked ? 'block' : 'none';
    });
  }
  
  // File preview para resposta
  var replyAttachment = document.getElementById('replyAttachment');
  var replyFileDropzone = document.getElementById('replyFileDropzone');
  var replyFilePreview = document.getElementById('replyFilePreview');
  var replyFilePreviewImg = document.getElementById('replyFilePreviewImg');
  var replyAttachmentName = document.getElementById('replyAttachmentName');
  var replyFileRemoveBtn = document.getElementById('replyFileRemoveBtn');
  
  if (replyAttachment) {
    replyAttachment.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        var file = this.files[0];
        
        if (!file.type.startsWith('image/')) {
          alert('Por favor, selecione apenas arquivos de imagem.');
          this.value = '';
          return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
          alert('O arquivo deve ter no maximo 10MB.');
          this.value = '';
          return;
        }
        
        var reader = new FileReader();
        reader.onload = function(e) {
          if (replyFilePreviewImg) replyFilePreviewImg.src = e.target.result;
          if (replyFileDropzone) replyFileDropzone.style.display = 'none';
          if (replyFilePreview) replyFilePreview.style.display = 'flex';
          if (replyAttachmentName) replyAttachmentName.textContent = file.name;
        };
        reader.readAsDataURL(file);
      }
    });
  }
  
  if (replyFileRemoveBtn) {
    replyFileRemoveBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      if (replyAttachment) replyAttachment.value = '';
      if (replyFileDropzone) replyFileDropzone.style.display = '';
      if (replyFilePreview) replyFilePreview.style.display = 'none';
      if (replyFilePreviewImg) replyFilePreviewImg.src = '';
      if (replyAttachmentName) replyAttachmentName.textContent = '';
    });
  }
  
  // Drag and drop para resposta
  if (replyFileDropzone) {
    replyFileDropzone.addEventListener('dragover', function(e) {
      e.preventDefault();
      this.classList.add('drag-over');
    });
    
    replyFileDropzone.addEventListener('dragleave', function(e) {
      e.preventDefault();
      this.classList.remove('drag-over');
    });
    
    replyFileDropzone.addEventListener('drop', function(e) {
      e.preventDefault();
      this.classList.remove('drag-over');
      
      if (e.dataTransfer.files && e.dataTransfer.files[0] && replyAttachment) {
        replyAttachment.files = e.dataTransfer.files;
        replyAttachment.dispatchEvent(new Event('change'));
      }
    });
  }
  
  // Form submit com Quill
  var replyForm = document.getElementById('replyTicketForm');
  var submitReplyBtn = document.getElementById('submitReply');
  
  if (replyForm && submitReplyBtn) {
    replyForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Pega conteudo do Quill
      var message = '';
      if (quillInstance) {
        message = quillInstance.root.innerHTML;
        // Remove conteudo vazio
        if (message === '<p><br></p>' || message.trim() === '') {
          alert('Digite sua resposta.');
          quillInstance.focus();
          return;
        }
      } else {
        var msgEl = document.getElementById('replyMessage');
        message = msgEl ? msgEl.value.trim() : '';
        if (!message) {
          alert('Digite sua resposta.');
          return;
        }
      }
      
      var ticketIdEl = replyForm.querySelector('input[name="ticket_id"]');
      var ticketId = ticketIdEl ? ticketIdEl.value : '';
      
      var fd = new FormData();
      fd.append('ticket_id', ticketId);
      fd.append('message', message);
      
      if (replyAttachment && replyAttachment.files && replyAttachment.files[0]) {
        fd.append('attachment', replyAttachment.files[0]);
      }
      
      submitReplyBtn.disabled = true;
      submitReplyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';
      
      var ROOT_URL = window.MODERNPOS_ROOT_URL || '/modernpos/';
      
      fetch(ROOT_URL + 'account/ajax/support_ticket_reply.php', {
        method: 'POST',
        body: fd
      })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        submitReplyBtn.disabled = false;
        submitReplyBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Enviar resposta';
        
        if (!data || data.success !== true) {
          alert(data.msg || 'Erro ao enviar a resposta.');
          return;
        }
        
        if (typeof window.showSupportToast === 'function') {
          window.showSupportToast('success', 'Sucesso!', 'Resposta enviada com sucesso!');
        }
        
        setTimeout(function() {
          window.location.reload();
        }, 1500);
      })
      .catch(function(err) {
        submitReplyBtn.disabled = false;
        submitReplyBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Enviar resposta';
        alert('Falha na comunicacao com o servidor.');
        console.error('Erro:', err);
      });
    });
  }
})();

// Função para toggle do card de resposta
function toggleReplyCard() {
  var body = document.getElementById('replyCardBody');
  var icon = document.getElementById('replyToggleIcon');
  
  if (!body) return;
  
  if (body.style.display === 'none' || body.style.display === '') {
    body.style.display = 'block';
    if (icon) {
      icon.classList.remove('bi-chevron-down');
      icon.classList.add('bi-chevron-up');
    }
    // Foca no editor após abrir
    setTimeout(function() {
      var editor = document.querySelector('#replyQuillEditor .ql-editor');
      if (editor) editor.focus();
    }, 100);
  } else {
    body.style.display = 'none';
    if (icon) {
      icon.classList.remove('bi-chevron-up');
      icon.classList.add('bi-chevron-down');
    }
  }
}
</script>
