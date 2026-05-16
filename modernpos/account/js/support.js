/**
 * ModernPOS - Módulo de Suporte (Cliente)
 * JavaScript para interações de UI
 */

(function() {
  'use strict';

  // URL base para AJAX
  var ROOT_URL = window.MODERNPOS_ROOT_URL || '/modernpos/';

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function ensureToastContainer() {
    // Mesma convenção da página /conta
    var container = document.getElementById('accountToastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'accountToastContainer';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1080';
      document.body.appendChild(container);
    }
    return container;
  }

  /**
   * Exibe Toast de notificação (mesmo estilo da página /conta)
   */
  function showToast(type, title, message) {
    var variant = 'primary';
    if (type === 'success') variant = 'success';
    else if (type === 'error') variant = 'danger';
    else if (type === 'warning') variant = 'warning';

    var container = ensureToastContainer();

    var el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + variant + ' border-0';
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.setAttribute('aria-atomic', 'true');

    el.innerHTML =
      '<div class="d-flex">' +
      '  <div class="toast-body">' +
      '    <div class="fw-semibold">' + escapeHtml(title || 'ModernPOS') + '</div>' +
      '    <div class="small opacity-75">' + escapeHtml(message || '') + '</div>' +
      '  </div>' +
      '  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>' +
      '</div>';

    container.appendChild(el);

    if (window.bootstrap && window.bootstrap.Toast) {
      var toast = new window.bootstrap.Toast(el, { delay: 3500 });
      toast.show();
      el.addEventListener('hidden.bs.toast', function () {
        el.remove();
      });
    }
  }

  /**
   * Exibe toast/alerta (compatibilidade)
   */
  function showAlert(type, message) {
    if (type === 'success') {
      showToast('success', 'Sucesso', message);
      return;
    }

    if (type === 'error') {
      showToast('error', 'Erro', message);
      return;
    }

    if (type === 'warning') {
      showToast('warning', 'Atenção', message);
      return;
    }

    // default/info
    showToast('info', 'Aviso', message);
  }
  
  // Exporta funções globais
  window.showSupportToast = showToast;

  /**
   * Inicialização quando DOM estiver pronto
   */
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  onReady(function() {
    
    // ==========================================
    // Página de Listagem de Tickets (Busca + Filtros)
    // ==========================================

    // Desktop elements
    var toolbarCard = document.getElementById('supportToolbarCard');
    var searchInput = document.getElementById('supportSearch');
    var doSearchBtn = document.getElementById('supportDoSearch');
    var filterToggleBtn = document.getElementById('supportFilterToggle');
    var filterCountEl = document.getElementById('supportFilterCount');
    var filterPanel = document.getElementById('support-filter-panel');
    var statusFilter = document.getElementById('supportFilterStatus');
    var priorityFilter = document.getElementById('supportFilterPriority');
    var categoryFilter = document.getElementById('supportFilterCategory');
    var applyFiltersBtn = document.getElementById('supportApplyFilters');
    var resetFiltersBtn = document.getElementById('supportResetFilters');

    // Mobile elements
    var searchInputMobile = document.getElementById('supportSearchMobile');
    var doSearchBtnMobile = document.getElementById('supportDoSearchMobile');
    var statusFilterMobile = document.getElementById('supportFilterStatusMobile');
    var priorityFilterMobile = document.getElementById('supportFilterPriorityMobile');
    var categoryFilterMobile = document.getElementById('supportFilterCategoryMobile');

    var tableBody = document.getElementById('ticketsTableBody');

    function isMobile() {
      return window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
    }

    function getActiveFilters() {
      var mobile = isMobile();
      return {
        searchTerm: (mobile ? (searchInputMobile && searchInputMobile.value) : (searchInput && searchInput.value)) || '',
        status: (mobile ? (statusFilterMobile && statusFilterMobile.value) : (statusFilter && statusFilter.value)) || '',
        priority: (mobile ? (priorityFilterMobile && priorityFilterMobile.value) : (priorityFilter && priorityFilter.value)) || '',
        categoryId: (mobile ? (categoryFilterMobile && categoryFilterMobile.value) : (categoryFilter && categoryFilter.value)) || ''
      };
    }

    function updateFilterCountUI() {
      if (!filterCountEl || !filterToggleBtn || !toolbarCard || !statusFilter || !priorityFilter || !categoryFilter) return;

      var count = 0;
      if (String(statusFilter.value || '').trim() !== '') count++;
      if (String(priorityFilter.value || '').trim() !== '') count++;
      if (String(categoryFilter.value || '').trim() !== '') count++;

      if (count > 0) {
        filterCountEl.style.display = '';
        filterCountEl.textContent = String(count);
        filterToggleBtn.classList.add('active');
      } else {
        filterCountEl.style.display = 'none';
        filterCountEl.textContent = '0';
        filterToggleBtn.classList.remove('active');
      }
    }

    function filterTickets() {
      if (!tableBody) return;

      var f = getActiveFilters();
      var searchTerm = String(f.searchTerm || '').toLowerCase().trim();
      var statusValue = String(f.status || '').toLowerCase().trim();
      var priorityValue = String(f.priority || '').toLowerCase().trim();
      var categoryIdValue = String(f.categoryId || '').trim();

      var rows = tableBody.querySelectorAll('tr');

      rows.forEach(function(row) {
        var subject = String(row.getAttribute('data-subject') || '').toLowerCase();
        var status = String(row.getAttribute('data-status') || '').toLowerCase();
        var priority = String(row.getAttribute('data-priority') || '').toLowerCase();
        var categoryId = String(row.getAttribute('data-category-id') || '').trim();

        var matchSearch = !searchTerm || subject.indexOf(searchTerm) !== -1;
        var matchStatus = !statusValue || status === statusValue;
        var matchPriority = !priorityValue || priority === priorityValue;
        var matchCategory = !categoryIdValue || categoryId === categoryIdValue;

        row.style.display = (matchSearch && matchStatus && matchPriority && matchCategory) ? '' : 'none';
      });

      updateFilterCountUI();
    }

    // Desktop: busca
    if (searchInput) {
      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          filterTickets();
        }
      });
      searchInput.addEventListener('input', function() {
        // busca “live” apenas se o painel de filtros estiver fechado
        filterTickets();
      });
    }

    if (doSearchBtn) {
      doSearchBtn.addEventListener('click', function(e) {
        e.preventDefault();
        filterTickets();
      });
    }

    // Desktop: toggle filtros
    if (filterToggleBtn && filterPanel && toolbarCard) {
      filterToggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var isOpen = filterPanel.classList.contains('is-open');
        if (isOpen) {
          filterPanel.classList.remove('is-open');
          toolbarCard.classList.remove('is-filters-open');
          filterPanel.setAttribute('aria-hidden', 'true');
        } else {
          filterPanel.classList.add('is-open');
          toolbarCard.classList.add('is-filters-open');
          filterPanel.setAttribute('aria-hidden', 'false');
        }
      });
    }

    // Desktop: mudanças de filtros
    [statusFilter, priorityFilter, categoryFilter].forEach(function(el) {
      if (!el) return;
      el.addEventListener('change', filterTickets);
    });

    if (applyFiltersBtn) {
      applyFiltersBtn.addEventListener('click', function(e) {
        e.preventDefault();
        filterTickets();
      });
    }

    if (resetFiltersBtn) {
      resetFiltersBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (statusFilter) statusFilter.value = '';
        if (priorityFilter) priorityFilter.value = '';
        if (categoryFilter) categoryFilter.value = '';
        filterTickets();
      });
    }

    // Mobile
    if (doSearchBtnMobile) {
      doSearchBtnMobile.addEventListener('click', function(e) {
        e.preventDefault();
        filterTickets();
      });
    }

    if (searchInputMobile) {
      searchInputMobile.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          filterTickets();
        }
      });
    }

    [statusFilterMobile, priorityFilterMobile, categoryFilterMobile].forEach(function(el) {
      if (!el) return;
      el.addEventListener('change', filterTickets);
    });

    // Botão de refresh
    var refreshBtn = document.getElementById('supportRefresh');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.reload();
      });
    }

    // Inicia UI do contador de filtros
    updateFilterCountUI();
    
    // ==========================================
    // Modal de Criação de Ticket
    // ==========================================
    
    var createForm = document.getElementById('createTicketForm');
    var submitBtn = document.getElementById('submitTicket');
    var attachmentInput = document.getElementById('ticketAttachment');
    var attachmentName = document.getElementById('ticketAttachmentName');
    
    // Exibe nome do arquivo selecionado
    if (attachmentInput && attachmentName) {
      attachmentInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
          attachmentName.textContent = this.files[0].name;
        } else {
          attachmentName.textContent = 'Nenhum arquivo selecionado';
        }
      });
    }
    
    // Submit do formulário de criação
    if (submitBtn && createForm) {
      submitBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        var subjectEl = document.getElementById('ticketSubject');
        var messageEl = document.getElementById('ticketMessage');
        var priorityEl = document.getElementById('ticketPriority');
        var categoryEl = document.getElementById('ticketCategory');
        
        var subject = subjectEl ? subjectEl.value.trim() : '';
        var message = messageEl ? messageEl.value.trim() : '';
        var priority = (priorityEl && priorityEl.value) ? priorityEl.value : 'low';
        var categoryId = categoryEl ? categoryEl.value : '';
        
        // Validações
        if (!subject) {
          showAlert('warning', 'Informe o assunto do ticket.');
          if (subjectEl) subjectEl.focus();
          return;
        }
        
        if (!message) {
          showAlert('warning', 'Descreva o problema.');
          if (messageEl) messageEl.focus();
          return;
        }
        
        // Monta FormData
        var fd = new FormData();
        fd.append('subject', subject);
        fd.append('message', message);
        fd.append('priority', priority);
        fd.append('category_id', categoryId);
        
        // Anexo (se houver)
        if (attachmentInput && attachmentInput.files && attachmentInput.files[0]) {
          fd.append('attachment', attachmentInput.files[0]);
        }
        
        // Desabilita botão
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
        
        // Envia requisição
        fetch(ROOT_URL + 'account/ajax/support_ticket_create.php', {
          method: 'POST',
          body: fd
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar Ticket';
          
          if (!data || data.success !== true) {
            showAlert('error', data.msg || 'Erro ao criar o ticket.');
            return;
          }
          
          // Fecha modal premium
          if (typeof window.fecharModalTicket === 'function') {
            window.fecharModalTicket();
          }
          
          // Exibe Toast de sucesso
          showToast('success', 'Sucesso!', 'Ticket criado com sucesso!');
          
          // Recarrega página após breve delay
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        })
        .catch(function(err) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar Ticket';
          showAlert('error', 'Falha na comunicação com o servidor.');
          console.error('Erro:', err);
        });
      });
    }
    
    // ==========================================
    // Página de Visualização do Ticket
    // ==========================================
    // IMPORTANTE: a página `support_ticket.php` possui um script próprio (com Quill)
    // que já faz o submit da resposta. Para evitar mensagens duplicadas,
    // não registramos o handler aqui quando Quill estiver presente.
    
    var replyForm = document.getElementById('replyTicketForm');
    var replyBtn = document.getElementById('submitReply');
    var replyAttachment = document.getElementById('replyAttachment');
    var replyAttachmentName = document.getElementById('replyAttachmentName');
    var hasQuillEditor = !!document.getElementById('replyQuillEditor');
    
    if (!hasQuillEditor) {
      // Exibe nome do arquivo anexado na resposta
      if (replyAttachment && replyAttachmentName) {
        replyAttachment.addEventListener('change', function() {
          if (this.files && this.files[0]) {
            replyAttachmentName.textContent = this.files[0].name;
          } else {
            replyAttachmentName.textContent = '';
          }
        });
      }
      
      // Submit do formulário de resposta
      if (replyForm) {
        replyForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          var ticketIdEl = replyForm.querySelector('input[name="ticket_id"]');
          var messageEl = document.getElementById('replyMessage');
          
          var ticketId = ticketIdEl ? ticketIdEl.value : '';
          var message = messageEl ? messageEl.value.trim() : '';
          
          if (!message) {
            showAlert('warning', 'Digite sua resposta.');
            if (messageEl) messageEl.focus();
            return;
          }
          
          // Monta FormData
          var fd = new FormData();
          fd.append('ticket_id', ticketId);
          fd.append('message', message);
          
          // Anexo (se houver)
          if (replyAttachment && replyAttachment.files && replyAttachment.files[0]) {
            fd.append('attachment', replyAttachment.files[0]);
          }
          
          // Desabilita botão
          if (replyBtn) {
            replyBtn.disabled = true;
            replyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
          }
          
          // Envia requisição
          fetch(ROOT_URL + 'account/ajax/support_ticket_reply.php', {
            method: 'POST',
            body: fd
          })
          .then(function(res) { return res.json(); })
          .then(function(data) {
            if (replyBtn) {
              replyBtn.disabled = false;
              replyBtn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
            }
            
            if (!data || data.success !== true) {
              showAlert('error', data.msg || 'Erro ao enviar a resposta.');
              return;
            }
            
            showAlert('success', data.msg || 'Resposta enviada com sucesso!');
            
            // Recarrega página para mostrar nova mensagem
            setTimeout(function() {
              window.location.reload();
            }, 1500);
          })
          .catch(function(err) {
            if (replyBtn) {
              replyBtn.disabled = false;
              replyBtn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
            }
            showAlert('error', 'Falha na comunicação com o servidor.');
            console.error('Erro:', err);
          });
        });
      }
    }
    
    // ==========================================
    // Modal Premium - Criar Ticket
    // ==========================================
    
    var modalPremiumOverlay = document.getElementById('modalCreateTicketPremium');
    var ticketFileUploadArea = document.getElementById('ticketFileUploadArea');
    var ticketFilePreviewArea = document.getElementById('ticketFilePreviewArea');
    var ticketFilePreviewImg = document.getElementById('ticketFilePreviewImg');
    var ticketFileRemoveBtn = document.getElementById('ticketFileRemoveBtn');
    
    // Função para abrir modal premium (com verificação de tickets abertos)
    window.abrirModalTicket = function(forceOpen) {
      // Se for forçado (usuário confirmou), abre direto
      if (forceOpen === true) {
        abrirModalTicketDireto();
        return;
      }
      
      // Verifica tickets abertos antes de abrir
      fetch(ROOT_URL + 'account/ajax/support_check_open_tickets.php')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (!data || data.success !== true) {
            // Erro na verificação, permite abrir
            abrirModalTicketDireto();
            return;
          }
          
          // Bloqueado: já tem 2+ tickets abertos
          if (data.is_blocked) {
            mostrarModalBloqueio(data.tickets);
            return;
          }
          
          // Aviso: tem 1 ticket aberto
          if (data.has_warning) {
            mostrarModalAviso(data.tickets);
            return;
          }
          
          // Pode criar normalmente
          abrirModalTicketDireto();
        })
        .catch(function(err) {
          console.error('Erro ao verificar tickets:', err);
          // Em caso de erro, permite abrir
          abrirModalTicketDireto();
        });
    };
    
    // Função interna para abrir modal diretamente
    function abrirModalTicketDireto() {
      if (modalPremiumOverlay) {
        // Limpa formulário
        var subjectEl = document.getElementById('ticketSubject');
        var messageEl = document.getElementById('ticketMessage');
        var categoryEl = document.getElementById('ticketCategory');
        
        if (subjectEl) subjectEl.value = '';
        if (messageEl) messageEl.value = '';
        if (categoryEl) categoryEl.value = '';
        if (attachmentInput) attachmentInput.value = '';
        
        // Reseta preview
        if (ticketFileUploadArea) ticketFileUploadArea.style.display = '';
        if (ticketFilePreviewArea) ticketFilePreviewArea.style.display = 'none';
        if (ticketFilePreviewImg) ticketFilePreviewImg.src = '';
        
        // Mostra modal
        modalPremiumOverlay.classList.add('active');
        
        // Foca no assunto após animação
        setTimeout(function() {
          if (subjectEl) subjectEl.focus();
        }, 300);
      }
    }
    
    // Modal de aviso (1 ticket aberto)
    function mostrarModalAviso(tickets) {
      var modal = document.getElementById('modalTicketWarning');
      var listEl = document.getElementById('warningTicketsList');
      
      if (modal && listEl) {
        // Popula lista de tickets
        var html = '';
        tickets.forEach(function(t) {
          html += '<a href="' + ROOT_URL + 'conta/suporte/ticket?id=' + t.id + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
          html += '<div>';
          html += '<strong class="text-primary">' + escapeHtml(t.subject) + '</strong>';
          html += '<br><small class="text-muted">#' + escapeHtml(t.code) + ' - ' + t.created_at + '</small>';
          html += '</div>';
          html += '<span class="badge bg-warning text-dark">' + escapeHtml(t.status_label) + '</span>';
          html += '</a>';
        });
        listEl.innerHTML = html;
        
        modal.classList.add('active');
      }
    }
    
    // Modal de bloqueio (2+ tickets abertos)
    function mostrarModalBloqueio(tickets) {
      var modal = document.getElementById('modalTicketBlocked');
      var listEl = document.getElementById('blockedTicketsList');
      
      if (modal && listEl) {
        // Popula lista de tickets
        var html = '';
        tickets.forEach(function(t) {
          html += '<a href="' + ROOT_URL + 'conta/suporte/ticket?id=' + t.id + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
          html += '<div>';
          html += '<strong class="text-primary">' + escapeHtml(t.subject) + '</strong>';
          html += '<br><small class="text-muted">#' + escapeHtml(t.code) + ' - ' + t.created_at + '</small>';
          html += '</div>';
          html += '<span class="badge bg-warning text-dark">' + escapeHtml(t.status_label) + '</span>';
          html += '</a>';
        });
        listEl.innerHTML = html;
        
        modal.classList.add('active');
      }
    }
    
    // Helper para escapar HTML
    function escapeHtml(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
    
    // Fechar modais de aviso/bloqueio
    window.fecharModalWarning = function() {
      var modal = document.getElementById('modalTicketWarning');
      if (modal) modal.classList.remove('active');
    };
    
    window.fecharModalBlocked = function() {
      var modal = document.getElementById('modalTicketBlocked');
      if (modal) modal.classList.remove('active');
    };
    
    // Confirmar abertura de novo ticket (após aviso)
    window.confirmarNovoTicket = function() {
      fecharModalWarning();
      abrirModalTicketDireto();
    };
    
    // Função para fechar modal premium
    window.fecharModalTicket = function() {
      if (modalPremiumOverlay) {
        modalPremiumOverlay.classList.remove('active');
      }
    };
    
    // Fechar modal ao clicar fora
    if (modalPremiumOverlay) {
      modalPremiumOverlay.addEventListener('click', function(e) {
        if (e.target === modalPremiumOverlay) {
          fecharModalTicket();
        }
      });
    }
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modalPremiumOverlay && modalPremiumOverlay.classList.contains('active')) {
        fecharModalTicket();
      }
    });
    
    // File Upload com Preview
    if (attachmentInput) {
      attachmentInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
          var file = this.files[0];
          
          // Verifica se é imagem
          if (!file.type.startsWith('image/')) {
            showAlert('warning', 'Por favor, selecione apenas arquivos de imagem.');
            this.value = '';
            return;
          }
          
          // Verifica tamanho (10MB)
          if (file.size > 10 * 1024 * 1024) {
            showAlert('warning', 'O arquivo deve ter no máximo 10MB.');
            this.value = '';
            return;
          }
          
          // Exibe preview
          var reader = new FileReader();
          reader.onload = function(e) {
            if (ticketFilePreviewImg) {
              ticketFilePreviewImg.src = e.target.result;
            }
            if (ticketFileUploadArea) ticketFileUploadArea.style.display = 'none';
            if (ticketFilePreviewArea) ticketFilePreviewArea.style.display = 'block';
            if (attachmentName) attachmentName.textContent = file.name;
          };
          reader.readAsDataURL(file);
        }
      });
    }
    
    // Remover arquivo
    if (ticketFileRemoveBtn) {
      ticketFileRemoveBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (attachmentInput) attachmentInput.value = '';
        if (ticketFileUploadArea) ticketFileUploadArea.style.display = '';
        if (ticketFilePreviewArea) ticketFilePreviewArea.style.display = 'none';
        if (ticketFilePreviewImg) ticketFilePreviewImg.src = '';
        if (attachmentName) attachmentName.textContent = '';
      });
    }
    
    // Drag and Drop
    if (ticketFileUploadArea) {
      ticketFileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
      });
      
      ticketFileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
      });
      
      ticketFileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          attachmentInput.files = e.dataTransfer.files;
          attachmentInput.dispatchEvent(new Event('change'));
        }
      });
    }
    
  });
  
})();
