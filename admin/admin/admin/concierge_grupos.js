function mudarVisaoMia(el, visao){
  // Update buttons
  document.querySelectorAll('.mia-view-tabs button').forEach(function(btn){ btn.classList.remove('on'); });
  el.classList.add('on');
  
  // Hide all sections and headers
  document.querySelectorAll('.mia-view-section').forEach(function(sec){ sec.style.display = 'none'; });
  document.querySelectorAll('.tbl-hdr').forEach(function(hdr){ hdr.style.display = 'none'; });
  document.getElementById('pending-campanhas').style.display = 'none';
  document.getElementById('pending-status').style.display = 'none';
  document.getElementById('pending-grupos').style.display = 'none';
  
  // Show active section and header
  document.getElementById('view-' + visao).style.display = 'block';
  document.getElementById('tbl-hdr-' + visao).style.display = 'grid';
  document.getElementById('pending-' + visao).style.display = 'block';
  miaCurrentView = visao;
  miaRefreshCurrentView();
}

function abrirMiaModal(id){
  const modal = document.getElementById('mia-ov-' + id);
  if (!modal) return;
  modal.classList.remove('hide');
  if (id === 'status-automacao') {
    miaLoadStatusHistory();
    miaPrefillManualStatusDate();
    miaRenderManualStatusPreview();
    // Reset day selector
    document.querySelectorAll('#mia-status-days .day-btn').forEach(function(b){ b.classList.remove('active'); });
    // Carrega as configuraÃ§Ãµes de automaÃ§Ã£o de status
    miaLoadStatusAutoSettings();
  }
  if (id === 'novo-disparo') {
    miaLoadProducts();
    miaRenderMediaAttachments();
    miaRenderPreviewCarousel();
    updateMiaCharCount();
  }
}

function toggleMiaDay(el){
  el.classList.toggle('active');
}
function toggleMiaEditDay(el){
  el.classList.toggle('active');
}
function miaUpdateStatusRepostSettings(event, id){
  const btn = event ? event.currentTarget : null;
  const oldHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
  }

  const daysContainer = document.getElementById('mia-edit-status-days');
  const selectedDays = [];
  if (daysContainer) {
    daysContainer.querySelectorAll('.day-btn.active').forEach(function(btn){
      selectedDays.push(parseInt(btn.dataset.day));
    });
  }

  const repeatCountInput = document.getElementById('mia-edit-status-repeat-count');
  const repeatIntervalInput = document.getElementById('mia-edit-status-repeat-interval');

  const repeatCount = parseInt(repeatCountInput ? repeatCountInput.value : 1);
  const repeatInterval = parseInt(repeatIntervalInput ? repeatIntervalInput.value : 1);
  const repeatDays = selectedDays.sort((a, b) => a - b).join(',');

  miaApi('PATCH', MIA_API.status, {
    id: id,
    loja_id: MIA_TENANT_ID,
    repeat_count: repeatCount,
    repeat_interval: repeatInterval,
    repeat_days: repeatDays
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message);
    showMiaToast('ConfiguraÃ§Ãµes de repostagem atualizadas com sucesso!', 'success');
    
    setTimeout(function(){
      miaOpenStatusDetails(id);
      miaLoadStatusHistory();
      if (miaCurrentView === 'status') miaLoadStatuses();
    }, 300);
    
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao atualizar configuraÃ§Ãµes.', 'error');
  }).finally(function(){
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = oldHtml;
    }
  });
}
function fecharMiaModal(id){
  const modal = document.getElementById('mia-ov-' + id);
  if (!modal) return;
  modal.classList.add('hide');
  if (id === 'novo-disparo') resetMiaSteps();
}

document.querySelectorAll('.mia-overlay').forEach(function(overlay){
  overlay.addEventListener('click', function(e){
    if (e.target !== this) return;
    const id = this.id.replace('mia-ov-', '');
    if (id === 'novo-disparo') return; // NÃ£o fecha ao clicar fora
    fecharMiaModal(id);
  });
});
document.addEventListener('keydown', function(e){
  if (e.key !== 'Escape') return;
  const modaisAbertos = Array.prototype.slice.call(document.querySelectorAll('.mia-overlay')).filter(function(ov){ return !ov.classList.contains('hide'); });
  if (!modaisAbertos.length) return;
  const ultimo = modaisAbertos[modaisAbertos.length - 1];
  const id = ultimo.id.replace('mia-ov-', '');
  if (id === 'novo-disparo') return; // NÃ£o fecha no Escape
  fecharMiaModal(id);
});

let _miaTt;
function showMiaToast(msg, tipo){
  const t = document.getElementById('mia-toast');
  const icon = document.getElementById('mia-toast-icon');
  const texto = document.getElementById('mia-toast-msg');
  if (!t || !icon || !texto) return;

  const type = tipo || 'info';
  const iconByType = {
    info: 'fa-info-circle',
    success: 'fa-check-circle',
    warning: 'fa-exclamation-triangle',
    error: 'fa-times-circle'
  };
  t.classList.remove('info', 'success', 'warning', 'error');
  t.classList.add(iconByType[type] ? type : 'info');
  icon.className = 'fa ' + (iconByType[type] || iconByType.info);
  texto.textContent = msg;
  t.classList.add('show');
  clearTimeout(_miaTt);
  _miaTt = setTimeout(function(){ t.classList.remove('show'); }, 2800);
}

let miaFiltroAtual = 'todos';
let miaBuscaTimer;
let miaAjaxTimer;
let miaCurrentView = 'campanhas';
function filtrarMia(el, filtro){
  document.querySelectorAll('.filter-zone .fc').forEach(function(chip){ chip.classList.remove('on'); });
  if (el) el.classList.add('on');
  miaFiltroAtual = filtro;
  miaRefreshCurrentView();
}
function debounceBuscaMia(){
  clearTimeout(miaBuscaTimer);
  miaBuscaTimer = setTimeout(miaRefreshCurrentView, 180);
}
function aplicarFiltroMiaAjax(){
  const loading = document.getElementById('mia-filter-loading');
  const chips = document.querySelectorAll('.filter-zone .fc');
  if (loading) loading.classList.add('show');
  chips.forEach(function(chip){ chip.classList.add('loading'); });

  clearTimeout(miaAjaxTimer);
  miaAjaxTimer = setTimeout(function(){
    const busca = (document.getElementById('mia-search-campanha') ? document.getElementById('mia-search-campanha').value : '').toLowerCase().trim();
    const rows = Array.prototype.slice.call(document.querySelectorAll('#mia-broadcast-list .brow'));
    let campanhasVisiveis = 0;

    rows.forEach(function(row){
      const status = (row.getAttribute('data-status') || '').toLowerCase();
      const texto = ((row.getAttribute('data-text') || '') + ' ' + row.textContent).toLowerCase();
      const matchFiltro = (miaFiltroAtual === 'todos') || (status === miaFiltroAtual);
      const matchBusca = !busca || texto.indexOf(busca) !== -1;
      const mostrar = matchFiltro && matchBusca;
      row.style.display = mostrar ? '' : 'none';
      if (mostrar) campanhasVisiveis++;
    });

    const pendingSection = document.getElementById('mia-pending-section');
    const pendingRows = pendingSection ? Array.prototype.slice.call(pendingSection.querySelectorAll('.ai-pending-row')) : [];
    let pendentesVisiveis = 0;
    const pendingPermitido = (miaFiltroAtual === 'todos' || miaFiltroAtual === 'aprovacao');
    pendingRows.forEach(function(row){
      const texto = ((row.getAttribute('data-text') || '') + ' ' + row.textContent).toLowerCase();
      const matchBusca = !busca || texto.indexOf(busca) !== -1;
      const mostrar = pendingPermitido && matchBusca;
      row.style.display = mostrar ? '' : 'none';
      if (mostrar) pendentesVisiveis++;
    });
    if (pendingSection) pendingSection.style.display = pendentesVisiveis > 0 ? '' : 'none';

    const totalVisivel = campanhasVisiveis + pendentesVisiveis;
    const regCount = document.getElementById('mia-reg-count');
    if (regCount) regCount.textContent = totalVisivel + (totalVisivel === 1 ? ' registro' : ' registros');

    const vazio = document.getElementById('mia-empty-state');
    if (vazio) vazio.classList.toggle('show', totalVisivel === 0);

    if (loading) loading.classList.remove('show');
    chips.forEach(function(chip){ chip.classList.remove('loading'); });
  }, 220);
}

let miaProdTimer;
let miaSelectedProductId = 0;
let miaProductsMap = {};
let miaSelectedMediaUrls = [];
let miaSelectedMediaIndex = 0;
let miaIsStatusSending = false;
let miaAvailableMediaUrls = [];
let miaMediaPickerContext = 'campaign';
let miaMediaPickerSelection = [];

function miaSearchProducts(){
  clearTimeout(miaProdTimer);
  miaProdTimer = setTimeout(function(){
    const q = document.getElementById('mia-search-prod').value;
    miaLoadProducts(q);
  }, 300);
}

function miaLoadProducts(q){
  console.log('Mia: Carregando produtos...', q || 'todos');
  const grid = document.getElementById('mia-prod-grid');
  if(!grid) return;
  grid.innerHTML = '<div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Buscando produtos...</div>';
  
  const url = MIA_API.products + (q ? '?q=' + encodeURIComponent(q) : '');
  miaApi('GET', url).then(function(resp){
    console.log('Mia: Resposta produtos:', resp);
    if(resp.error) throw new Error(resp.message);
    miaRenderProducts(resp.data.items || []);
  }).catch(function(err){
    console.error('Mia: Erro ao carregar produtos:', err);
    grid.innerHTML = '<div style="grid-column:1/-1;padding:20px;text-align:center;color:#ef4444">' + miaEsc(err.message) + '</div>';
  });
}

function miaRenderProducts(items){
  const grid = document.getElementById('mia-prod-grid');
  if(!grid) return;
  miaProductsMap = {};
  if(!items.length){
    grid.innerHTML = '<div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8">Nenhum produto encontrado.</div>';
    return;
  }
  grid.innerHTML = items.map(function(p){
    miaProductsMap[p.id] = p;
    const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
    const mediaUrls = Array.isArray(p.media_urls) ? p.media_urls : [];
    const img = p.image || mediaUrls[0] || fallback;
    const stock = Number(p.stock || 0);
    const badge = stock <= 0
      ? '<span class="pp-badge low">Sem estoque</span>'
      : (stock <= 3 ? '<span class="pp-badge low">Ãšltimas '+stock+'</span>' : '<span class="pp-badge stock">Estoque</span>');
    return '<div class="prod-pick '+(miaSelectedProductId === p.id ? 'sel' : '')+'" onclick="selMiaProd(this, '+p.id+')">'
      + '<img src="'+miaEsc(img)+'" loading="lazy" onerror="this.src=\''+fallback+'\'">'
      + '<div class="pp-name">'+miaEsc(p.name)+'</div>'
      + '<div class="pp-price">R$ '+Number(p.price||0).toFixed(2).replace('.',',')+'</div>'
      + badge
    + '</div>';
  }).join('');
}

function miaGetSelectedProduct(){
  return miaProductsMap[miaSelectedProductId] || null;
}

function miaUniqueMediaUrls(list){
  return Array.from(new Set((Array.isArray(list) ? list : []).map(function(v){
    return String(v || '').trim();
  }).filter(function(v){ return v !== ''; })));
}

function miaCollectCatalogMediaUrls(){
  const urls = [];
  Object.keys(miaProductsMap || {}).forEach(function(key){
    const p = miaProductsMap[key] || {};
    const media = Array.isArray(p.media_urls) ? p.media_urls : [];
    media.forEach(function(url){ urls.push(String(url || '').trim()); });
    if (p.image) urls.push(String(p.image).trim());
  });
  return miaUniqueMediaUrls(urls);
}

function miaMediaPickerCard(url){
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const selected = miaMediaPickerSelection.indexOf(url) !== -1;
  return '<div class="media-picker-item '+(selected ? 'sel' : '')+'" onclick="miaToggleMediaPickerUrl(\''+miaEsc(url)+'\')">'
    + '<img src="'+miaEsc(url)+'" loading="lazy" onerror="this.src=\''+fallback+'\'">'
    + '<span class="tick"><i class="fa fa-check"></i></span>'
    + '</div>';
}

function miaRenderMediaPicker(){
  const count = document.getElementById('mia-media-picker-count');
  const limit = miaMediaPickerContext === 'status' ? 1 : 4;
  const productGrid = document.getElementById('mia-media-picker-product');
  const catalogGrid = document.getElementById('mia-media-picker-catalog');
  if (count) count.textContent = miaMediaPickerSelection.length + ' de ' + limit + ' selecionada' + (limit > 1 ? 's' : '');
  const fromProduct = miaUniqueMediaUrls(miaAvailableMediaUrls);
  const fromCatalog = miaCollectCatalogMediaUrls();
  if (productGrid) {
    productGrid.innerHTML = fromProduct.length
      ? fromProduct.map(miaMediaPickerCard).join('')
      : '<div style="grid-column:1/-1;font-size:11px;color:#94a3b8">Nenhuma foto no produto atual.</div>';
  }
  if (catalogGrid) {
    catalogGrid.innerHTML = fromCatalog.length
      ? fromCatalog.map(miaMediaPickerCard).join('')
      : '<div style="grid-column:1/-1;font-size:11px;color:#94a3b8">Nenhuma mÃ­dia encontrada no catÃ¡logo.</div>';
  }
}

function miaOpenMediaPicker(context){
  miaMediaPickerContext = context === 'status' ? 'status' : 'campaign';
  const limit = miaMediaPickerContext === 'status' ? 1 : 4;
  const subtitle = document.getElementById('mia-media-picker-subtitle');
  if (subtitle) {
    subtitle.innerText = limit === 1 
      ? 'Selecione apenas 1 mÃ­dia para o Status Manual.' 
      : 'Selecione atÃ© 4 mÃ­dias e adicione URLs extras quando necessÃ¡rio.';
  }
  
  if (miaMediaPickerContext === 'status') {
    miaMediaPickerSelection = miaParseManualStatusMediaInput().slice(0, limit);
  } else {
    miaMediaPickerSelection = miaSelectedMediaUrls.slice(0, limit);
  }
  const field = document.getElementById('mia-media-picker-input');
  if (field) field.value = '';
  abrirMiaModal('media-picker');
  miaRenderMediaPicker();
}

function miaToggleMediaPickerUrl(url){
  const val = String(url || '').trim();
  if (!val) return;
  const idx = miaMediaPickerSelection.indexOf(val);
  if (idx !== -1) {
    miaMediaPickerSelection.splice(idx, 1);
  } else {
    const limit = miaMediaPickerContext === 'status' ? 1 : 4;
    if (miaMediaPickerSelection.length >= limit) {
      if (limit === 1) {
        showMiaToast('Para o Status, vocÃª pode selecionar apenas 1 mÃ­dia.', 'warning');
        // Opcional: substitui a seleÃ§Ã£o atual pela nova
        miaMediaPickerSelection = [val];
      } else {
        showMiaToast('VocÃª pode selecionar no mÃ¡ximo 4 mÃ­dias.', 'warning');
        return;
      }
    } else {
      miaMediaPickerSelection.push(val);
    }
  }
  miaRenderMediaPicker();
}

function miaMediaPickerAddUrls(){
  abrirMiaModal('add-url');
}

function miaConfirmAddManualUrl(){
  const field = document.getElementById('mia-input-manual-url');
  const url = field ? field.value.trim() : '';
  if (!url) {
    showMiaToast('Informe uma URL vÃ¡lida.', 'warning');
    return;
  }
  
  const limit = miaMediaPickerContext === 'status' ? 1 : 4;
  if (miaMediaPickerSelection.length >= limit) {
    if (limit === 1) {
      showMiaToast('Para o Status, vocÃª pode selecionar apenas 1 mÃ­dia.', 'warning');
      miaMediaPickerSelection = [url];
      miaRenderMediaPicker();
    } else {
      showMiaToast('VocÃª jÃ¡ selecionou o limite de 4 mÃ­dias.', 'warning');
      return;
    }
  } else if (miaMediaPickerSelection.indexOf(url) === -1) {
    miaMediaPickerSelection.push(url);
    miaRenderMediaPicker();
    showMiaToast('URL adicionada!', 'success');
  } else {
    showMiaToast('Esta URL jÃ¡ estÃ¡ na lista.', 'info');
  }

  if (field) field.value = '';
  fecharMiaModal('add-url');
}

function miaApplyMediaPickerSelection(){
  if (miaMediaPickerContext === 'status') {
    const mediaEl = document.getElementById('mia-manual-status-media');
    if (mediaEl) mediaEl.value = miaMediaPickerSelection.join(', ');
    miaRenderManualStatusPreview();
  } else {
    miaSelectedMediaUrls = miaMediaPickerSelection.slice(0, 4);
    if (miaSelectedMediaIndex >= miaSelectedMediaUrls.length) {
      miaSelectedMediaIndex = Math.max(0, miaSelectedMediaUrls.length - 1);
    }
    miaRenderMediaAttachments();
    miaRenderPreviewCarousel();
  }
  fecharMiaModal('media-picker');
}

function miaParseManualStatusMediaInput(){
  const mediaEl = document.getElementById('mia-manual-status-media');
  return miaUniqueMediaUrls((mediaEl ? mediaEl.value : '').split(',')).slice(0, 4);
}

function miaRenderManualStatusPreview(){
  const wrap = document.getElementById('mia-manual-status-preview');
  const countEl = document.getElementById('mia-manual-status-preview-count');
  const clearBtn = document.getElementById('btn-clear-status-media');
  if (!wrap) return;
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const mediaUrls = miaParseManualStatusMediaInput();
  if (countEl) {
    countEl.textContent = '(' + mediaUrls.length + ' selecionada' + (mediaUrls.length !== 1 ? 's' : '') + ')';
  }
  
  // Mostrar/ocultar botÃ£o de limpar tudo
  if (clearBtn) {
    clearBtn.style.display = mediaUrls.length > 0 ? 'flex' : 'none';
  }
  
  if (!mediaUrls.length) {
    wrap.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:10px;color:#94a3b8;font-size:11px">Nenhuma mÃ­dia selecionada.</div>';
    return;
  }
  wrap.innerHTML = mediaUrls.map(function(url, idx){
    return '<div class="media-preview-card">'
      + '<img src="'+miaEsc(url)+'" loading="lazy" onerror="this.src=\''+fallback+'\'">'
      + '<button type="button" class="remove" onclick="miaRemoveManualStatusMedia('+idx+')"><i class="fa fa-times"></i></button>'
      + '</div>';
  }).join('');
}

function miaRemoveManualStatusMedia(index){
  const mediaEl = document.getElementById('mia-manual-status-media');
  const current = miaParseManualStatusMediaInput();
  if (index < 0 || index >= current.length) return;
  current.splice(index, 1);
  if (mediaEl) mediaEl.value = current.join(', ');
  miaRenderManualStatusPreview();
}

function confirmClearStatusMedia(){
  abrirMiaModal('clear-status-media');
}

function clearAllStatusMedia(){
  const mediaEl = document.getElementById('mia-manual-status-media');
  if (mediaEl) mediaEl.value = '';
  miaRenderManualStatusPreview();
}

function miaRenderMediaAttachments(){
  const photos = document.getElementById('mia-msg-photos');
  const help = document.getElementById('mia-msg-photos-help');
  const clearBtn = document.getElementById('btn-clear-photos');
  if(!photos) return;
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const thumbs = miaSelectedMediaUrls.slice(0, 4).map(function(url, idx){
    return '<div style="width:56px;height:56px;border-radius:7px;border:2px solid '+(idx === miaSelectedMediaIndex ? '#7c3aed' : '#e2e8f0')+';position:relative;overflow:hidden;background:#f8fafc;cursor:pointer" onclick="miaSelectMedia('+idx+')">'
      + '<img src="'+miaEsc(url)+'" loading="lazy" style="width:100%;height:100%;object-fit:cover" onerror="this.src=\''+fallback+'\'">'
      + '<button type="button" onclick="event.stopPropagation();miaRemoveMedia('+idx+')" style="position:absolute;top:2px;right:2px;width:18px;height:18px;border-radius:50%;border:1px solid #fff;background:#ef4444;color:#fff;font-size:10px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:999"><i class="fa fa-times"></i></button>'
      + '</div>';
  });
  if (miaSelectedMediaUrls.length < 4) {
    thumbs.push('<button type="button" onclick="miaOpenMediaPicker(\'campaign\')" style="width:56px;height:56px;border:1.5px dashed #d1d5db;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#94a3b8;background:#fff;cursor:pointer"><i class="fa fa-plus"></i></button>');
  }
  photos.innerHTML = thumbs.join('');
  if (help) help.textContent = Math.min(4, miaSelectedMediaUrls.length) + ' de 4 selecionadas.';
  
  // Mostrar/ocultar botÃ£o de limpar
  if (clearBtn) {
    clearBtn.style.display = miaSelectedMediaUrls.length > 0 ? 'flex' : 'none';
  }
}

function confirmClearPhotos(){
  abrirMiaModal('clear-photos');
}
function miaGetSelectedCtaText(){
  const ctaSelect = document.getElementById('mia-input-cta');
  if (!ctaSelect || !ctaSelect.options || ctaSelect.selectedIndex < 0) {
    return 'ðŸ“² Chama no privado!';
  }
  let selectedCtaText = String(ctaSelect.options[ctaSelect.selectedIndex].text || '').trim();
  selectedCtaText = selectedCtaText.replace(/\\"/g, '').replace(/"/g, '').trim();
  return selectedCtaText || 'ðŸ“² Chama no privado!';
}

function miaNormalizeMediaKey(url){
  let key = String(url || '').trim();
  if (key === '') return '';
  key = key.split('?')[0].split('#')[0];
  try { key = decodeURIComponent(key); } catch (e) {}
  return key.replace(/\/+$/, '').toLowerCase();
}

function miaFindVariantByMediaUrl(product, mediaUrl){
  if (!product || !Array.isArray(product.variants) || !product.variants.length) return null;
  const mediaKey = miaNormalizeMediaKey(mediaUrl);
  if (!mediaKey) return null;
  return product.variants.find(function(variant){
    const variantKey = miaNormalizeMediaKey(variant && variant.media_url ? variant.media_url : '');
    return variantKey !== '' && variantKey === mediaKey;
  }) || null;
}

function miaGetPreviewMessageForCard(){
  const msgEl = document.getElementById('ia-msg');
  return String((msgEl && msgEl.value) ? msgEl.value : '').replace(/\s+/g, ' ').trim();
}

function miaRenderWppIndicators(total){
  const indicators = document.getElementById('mia-wpp-indicators');
  if (!indicators) return;
  const count = Math.max(0, Math.min(4, Number(total || 0)));
  if (count <= 1) {
    indicators.innerHTML = '';
    return;
  }
  const activeIndex = Math.max(0, Math.min(Number(miaSelectedMediaIndex || 0), count - 1));
  indicators.innerHTML = Array.from({ length: count }).map(function(_, idx){
    return '<span class="dot '+(idx === activeIndex ? 'act' : '')+'"></span>';
  }).join('');
}

function clearAllPhotos(){
  miaSelectedMediaUrls = [];
  miaSelectedMediaIndex = 0;
  miaRenderMediaAttachments();
  miaRenderPreviewCarousel();
  
  // Mostrar texto separado novamente (sem cards selecionados)
  const wppTextPrev = document.getElementById('wpp-text-prev');
  const wppCtaPrev = document.getElementById('mia-wpp-cta-prev');
  if (wppTextPrev) {
    wppTextPrev.style.display = 'block';
  }
  if (wppCtaPrev) {
    wppCtaPrev.style.display = 'flex';
  }
}

function miaGetColorEmoji(colorName){
  if(!colorName) return '';
  const colorMap = {
    'azul': 'ðŸ”µ', 'vermelho': 'ðŸ”´', 'verde': 'ðŸŸ¢', 'amarelo': 'ðŸŸ¡',
    'preto': 'âš«', 'branco': 'âšª', 'rosa': 'ðŸ©·', 'roxo': 'ðŸŸ£',
    'laranja': 'ðŸŸ ', 'marrom': 'ðŸŸ¤', 'cinza': 'âšª', 'bege': 'ðŸŸ¤'
  };
  const key = colorName.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  for (const [k, emoji] of Object.entries(colorMap)) {
    if (key.includes(k)) {
      return emoji;
    }
  }
  return 'ðŸŽ¨';
}

function miaRenderPreviewCarousel(){
  const carousel = document.getElementById('mia-wpp-carousel');
  const wppTextPrev = document.getElementById('wpp-text-prev');
  const wppCtaPrev = document.getElementById('mia-wpp-cta-prev');
  if(!carousel) return;
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const product = miaGetSelectedProduct();
  const ctaText = miaGetSelectedCtaText();
  
  // Usar apenas as mÃ­dias selecionadas pelo usuÃ¡rio (max 4)
  const media = miaSelectedMediaUrls.slice(0, 4);
  
  if (!media.length) {
    carousel.innerHTML = '<div class="wpp-carousel-thumb sel"><i class="fa fa-image" style="font-size:18px;color:#94a3b8"></i></div>';
    if (wppTextPrev) wppTextPrev.style.display = 'block';
    if (wppCtaPrev) wppCtaPrev.style.display = 'flex';
    return;
  }
  
  // Esconder texto e CTA externos: conteÃºdo principal fica dentro dos cards
  if (wppTextPrev) wppTextPrev.style.display = 'none';
  if (wppCtaPrev) wppCtaPrev.style.display = 'none';
  
  const messageDescription = miaGetPreviewMessageForCard();
  const productDescription = String((product && product.description) ? product.description : '').replace(/\s+/g, ' ').trim();
  const cardDescription = messageDescription || productDescription;
  const sku = (product && product.sku) ? String(product.sku).trim() : '';
  
  carousel.innerHTML = media.map(function(url){
    const productName = product ? product.name : 'Produto';
    const variant = miaFindVariantByMediaUrl(product, url);
    let colorLabel = '';
    let colorEmoji = '';
    let priceLabel = '';
    if (variant) {
      colorLabel = String(variant.color || '').trim();
      colorEmoji = miaGetColorEmoji(colorLabel);
      if (variant.price !== undefined && variant.price !== null && String(variant.price) !== '') {
        priceLabel = Number(variant.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
      }
    } else if (product && product.price !== undefined && product.price !== null && String(product.price) !== '') {
      priceLabel = Number(product.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }
    
    return '<div class="wpp-carousel-card">'
      + '<div class="wpp-card-media">'
      + '<img src="'+miaEsc(url)+'" loading="lazy" onerror="this.src=\''+fallback+'\'">'
      + '</div>'
      + '<div class="wpp-card-body">'
      + '<div class="wpp-card-title">'+miaEsc(productName)+'</div>'
      + (cardDescription ? '<div class="wpp-card-desc">'+miaEsc(cardDescription)+'</div>' : '')
      + '<div class="wpp-card-meta">'
      + (sku ? '<div style="font-size:9px;color:#64748b;font-weight:600">SKU: '+miaEsc(sku)+'</div>' : '')
      + (colorLabel ? '<div style="font-size:9px;color:#1f2937;font-weight:600">Cor: '+colorEmoji+' '+miaEsc(colorLabel)+'</div>' : '')
      + (priceLabel ? '<div class="wpp-price-chip">'+priceLabel+'</div>' : '')
      + '</div>'
      + '<div class="wpp-card-cta">'+miaEsc(ctaText)+'</div>'
      + '</div>'
      + '</div>';
  }).join('');
  
  // Adicionar suporte para arrastar o carrossel
  miaEnableCarouselDrag(carousel);
}

function miaEnableCarouselDrag(carousel){
  if(!carousel) return;
  
  let isDown = false;
  let startX;
  let scrollLeft;
  
  carousel.addEventListener('mousedown', function(e){
    isDown = true;
    startX = e.pageX - carousel.offsetLeft;
    scrollLeft = carousel.scrollLeft;
  });
  
  carousel.addEventListener('mouseleave', function(){
    isDown = false;
  });
  
  carousel.addEventListener('mouseup', function(){
    isDown = false;
  });
  
  carousel.addEventListener('mousemove', function(e){
    if(!isDown) return;
    e.preventDefault();
    const x = e.pageX - carousel.offsetLeft;
    const walk = (x - startX) * 2; // velocidade do scroll
    carousel.scrollLeft = scrollLeft - walk;
  });
}

function miaSelectMedia(index){
  miaSelectedMediaIndex = Math.max(0, Math.min(index, Math.max(0, miaSelectedMediaUrls.length - 1)));
  miaRenderMediaAttachments();
  miaRenderPreviewCarousel();
}

function miaRemoveMedia(index){
  if (index < 0 || index >= miaSelectedMediaUrls.length) return;
  miaSelectedMediaUrls.splice(index, 1);
  if (miaSelectedMediaIndex >= miaSelectedMediaUrls.length) {
    miaSelectedMediaIndex = Math.max(0, miaSelectedMediaUrls.length - 1);
  }
  miaRenderMediaAttachments();
  miaRenderPreviewCarousel();
}

function miaAddMediaPrompt(){
  miaOpenMediaPicker('campaign');
}

function miaApplySelectedProduct(){
  const product = miaGetSelectedProduct();
  if (!product) return;
  const name = String(product.name || 'Produto');
  const mediaUrls = Array.isArray(product.media_urls) ? product.media_urls : [];
  const fallbackMedia = product.image ? [product.image] : [];
  miaAvailableMediaUrls = miaUniqueMediaUrls(mediaUrls.length ? mediaUrls : fallbackMedia);
  miaSelectedMediaUrls = miaAvailableMediaUrls.slice(0, 4);
  miaSelectedMediaIndex = 0;
  const prevName = document.getElementById('mia-wpp-prev-name');
  const prevAv = document.getElementById('mia-wpp-prev-av');
  const prevStatus = document.getElementById('mia-wpp-prev-status');
  if (prevName) prevName.textContent = String(MIA_STORE_NAME || 'Loja');
  if (prevStatus) prevStatus.textContent = name + ' Â· ' + Number(product.stock || 0) + ' unidades';
  if (prevAv) prevAv.textContent = (String(MIA_STORE_NAME || name).charAt(0) || 'L').toUpperCase();

  // Garante que o CTA e o Tom iniciais apareÃ§am no preview
  updateMiaWppPreviewCta();

  const msg = document.getElementById('ia-msg');
  if (msg && !msg.value.trim()) {
    msg.value = 'âœ¨ ' + name + '\n\n' + 'DisponÃ­vel agora por R$ ' + Number(product.price || 0).toFixed(2).replace('.', ',') + '.\nðŸ“² Fale com a gente para garantir o seu!';
  }
  updateMiaCharCount();
  miaRenderMediaAttachments();
  miaRenderPreviewCarousel();
}

function selMiaProd(el, id){
  document.querySelectorAll('.prod-pick').forEach(function(p){ p.classList.remove('sel'); });
  el.classList.add('sel');
  miaSelectedProductId = id;
  
  // Buscar detalhes completos do produto (incluindo variantes)
  miaApi('GET', MIA_API.products + '?id=' + id).then(function(resp){
    if(!resp.error && resp.data && resp.data.items && resp.data.items.length > 0){
      const fullProduct = resp.data.items[0];
      miaProductsMap[id] = fullProduct;
    }
    miaApplySelectedProduct();
  }).catch(function(err){
    console.error('Erro ao carregar detalhes do produto:', err);
    miaApplySelectedProduct();
  });
}

let miaSchedType = 'schedule';
function selMiaSched(el, type){
  document.querySelectorAll('.sched-opts .sched-opt').forEach(function(o){ o.classList.remove('sel'); });
  el.classList.add('sel');
  miaSchedType = type;
  
  const now = document.getElementById('mia-sched-now');
  const manual = document.getElementById('mia-sched-manual');
  
  if(now) now.style.display = (type === 'now') ? 'block' : 'none';
  if(manual) manual.style.display = (type === 'schedule') ? 'block' : 'none';
}

let mstep=1;
function mStepNext(){
  if(mstep===1){
    if (!miaSelectedProductId) {
      showMiaToast('Selecione um produto antes de continuar.', 'warning');
      return;
    }
    miaApplySelectedProduct();
    mstep=2;
    document.getElementById('ms1').style.display='none';
    document.getElementById('ms2').style.display='block';
    setMiaWS(1,'done');setMiaWS(2,'act');
    document.getElementById('wline1').classList.add('done');
    document.getElementById('mbtn-back').style.display='inline-flex';
  } else if(mstep===2){
    mstep=3;
    document.getElementById('ms2').style.display='none';
    document.getElementById('ms3').style.display='block';
    setMiaWS(2,'done');setMiaWS(3,'act');
    document.getElementById('wline2').classList.add('done');
    document.getElementById('mbtn-next').innerHTML='<i class="fa fa-check"></i> Agendar';
  } else {
    miaSaveCampaign();
  }
}

function miaSaveCampaign(){
  const selectedProduct = miaGetSelectedProduct();
  const title = (selectedProduct && selectedProduct.name) ? selectedProduct.name : (document.getElementById('mia-search-prod').value || 'Nova Campanha');
  const content = document.getElementById('ia-msg').value;
  const productId = miaSelectedProductId;
  const mediaUrls = miaSelectedMediaUrls.slice(0, 4);
  const tone = (document.getElementById('mia-input-tone') || {}).value || '';
  const cta = (document.getElementById('mia-input-cta') || {}).value || '';
  
  const groupIds = Array.from(document.querySelectorAll('input[name="mia-target-groups"]:checked')).map(function(el){ return parseInt(el.value); });
  
  if (!content) { showMiaToast('Por favor, escreva uma mensagem.', 'warning'); return; }
  if (groupIds.length === 0) { showMiaToast('Selecione pelo menos um grupo.', 'warning'); return; }
  
  const date = document.getElementById('mia-input-date').value;
  const time = document.getElementById('mia-input-time').value;
  if (miaSchedType !== 'now' && (!date || !time)) { showMiaToast('Informe data e hora para agendar.', 'warning'); return; }
  const scheduledAt = miaSchedType === 'now' ? '' : (date + ' ' + time);
  const lockKey = 'save_campaign_' + (miaSchedType === 'now' ? 'now' : 'schedule');
  if (!miaAcquireDispatchLock('campaign', lockKey)) return;
  
  const payload = {
    title: title,
    content: content,
    product_id: productId,
    media_url: mediaUrls[0] || '',
    media_urls: mediaUrls,
    group_ids: groupIds,
    scheduled_at: scheduledAt,
    send_now: miaSchedType === 'now' ? 1 : 0,
    status: scheduledAt ? 'scheduled' : 'sent',
    payload_json: {
      media_urls: mediaUrls,
      tone: tone,
      cta: cta,
      objective: 'divulgaÃ§Ã£o'
    }
  };
  
  const nextBtn = document.getElementById('mbtn-next');
  const oldText = nextBtn.innerHTML;
  nextBtn.disabled = true;
  nextBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';

  miaApi('POST', MIA_API.campaigns, payload).then(function(resp){
    if (resp.error) throw new Error(resp.message);
    showMiaToast('Campanha salva com sucesso! âœ…', 'success');
    fecharMiaModal('novo-disparo');
    miaLoadCampaigns();
    miaScheduleDispatchSync(1100);
  }).catch(function(err){
    showMiaToast('Erro ao salvar: ' + err.message, 'error');
  }).finally(function(){
    miaReleaseDispatchLock('campaign', lockKey);
    nextBtn.disabled = false;
    nextBtn.innerHTML = oldText;
  });
}
function mStepBack(){
  if(mstep===2){
    mstep=1;
    document.getElementById('ms2').style.display='none';
    document.getElementById('ms1').style.display='block';
    setMiaWS(1,'act');setMiaWS(2,'pend');
    document.getElementById('wline1').classList.remove('done');
    document.getElementById('mbtn-back').style.display='none';
  } else if(mstep===3){
    mstep=2;
    document.getElementById('ms3').style.display='none';
    document.getElementById('ms2').style.display='block';
    setMiaWS(2,'act');setMiaWS(3,'pend');
    document.getElementById('wline2').classList.remove('done');
    document.getElementById('mbtn-next').innerHTML='PrÃ³ximo <i class="fa fa-chevron-right"></i>';
  }
}
function setMiaWS(n,s){
  const c=document.getElementById('ws'+n),l=document.getElementById('wl'+n);
  if(!c)return;
  if(s==='done'){c.className='ws-circle done';c.innerHTML='<i class="fa fa-check"></i>';l.className='ws-lbl done'}
  else if(s==='act'){c.className='ws-circle act';c.textContent=n;l.className='ws-lbl act'}
  else{c.className='ws-circle pend';c.textContent=n;l.className='ws-lbl'}
}
function resetMiaSteps(){
  mstep=1;
  document.getElementById('ms2').style.display='none';
  document.getElementById('ms3').style.display='none';
  document.getElementById('ms1').style.display='block';
  setMiaWS(1,'act');setMiaWS(2,'pend');setMiaWS(3,'pend');
  document.getElementById('wline1').classList.remove('done');
  document.getElementById('wline2').classList.remove('done');
  document.getElementById('mbtn-back').style.display='none';
  document.getElementById('mbtn-next').innerHTML='PrÃ³ximo <i class="fa fa-chevron-right"></i>';
  miaSelectedMediaUrls = [];
  miaSelectedMediaIndex = 0;
  miaRenderMediaAttachments();
  miaRenderPreviewCarousel();
}

function abrirMiaDrawer(type){
  const ov = document.getElementById('mia-drawer-ov');
  const dw = document.getElementById('mia-drawer');
  const content = document.getElementById('mia-drawer-content');
  if(!ov || !dw || !content) return;

  let html = '<div style="padding:40px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Carregando detalhes...</div>';
  content.innerHTML = html;
  ov.classList.add('open');
  dw.classList.add('open');

  // Se for campanha, carrega dados reais
  if (type && !isNaN(type)) {
    miaOpenCampaign(type);
  }
}
function fecharMiaDrawer(){
  const ov = document.getElementById('mia-drawer-ov');
  const dw = document.getElementById('mia-drawer');
  if(!ov || !dw) return;
  ov.classList.remove('open');
  dw.classList.remove('open');
}
function miaCopyMessage(){
  const msg = document.getElementById('ia-msg');
  if (!msg || !msg.value.trim()) {
    showMiaToast('NÃ£o hÃ¡ conteÃºdo para copiar.', 'warning');
    return;
  }
  const copyPromise = navigator.clipboard && navigator.clipboard.writeText
    ? navigator.clipboard.writeText(msg.value)
    : Promise.reject(new Error('Clipboard indisponÃ­vel.'));
  copyPromise.then(function(){
    showMiaToast('Mensagem copiada.', 'success');
  }).catch(function(){
    msg.select();
    document.execCommand('copy');
    showMiaToast('Mensagem copiada.', 'success');
  });
}
function regerarIA(){
  const msg = document.getElementById('ia-msg');
  const product = miaGetSelectedProduct();
  if(!msg) return;
  if(!product){
    showMiaToast('Selecione um produto para gerar o texto.', 'warning');
    return;
  }
  const previous = msg.value;
  msg.value = 'Gerando novo conteÃºdo com IA...';
  updateMiaCharCount();
  
  const examplesContainer = document.getElementById('mia-examples-container');
  const examplesList = document.getElementById('mia-examples-list');
  examplesContainer.style.display = 'none';
  examplesList.innerHTML = '';

  miaApi('POST', MIA_API.campaignText, {
    product_id: Number(product.id || 0),
    product_name: product.name || '',
    product_price: Number(product.price || 0),
    product_description: product.description || '',
    tone: (document.getElementById('mia-input-tone') || {}).value || 'casual',
    cta: (document.getElementById('mia-input-cta') || {}).value || 'Me chama no privado!',
    objective: 'divulgaÃ§Ã£o',
    highlights: []
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao gerar texto.');
    const data = (resp.data || {});
    const examples = (data.examples || []);
    
    if (examples.length > 0) {
      examplesContainer.style.display = 'block';
      examplesList.innerHTML = '';
      
      examples.forEach(function(example, index) {
        const exampleDiv = document.createElement('div');
        exampleDiv.className = 'mia-example-card';
        exampleDiv.style.cssText = 'border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#f8fafc;cursor:pointer;transition:all 0.2s';
        exampleDiv.onmouseover = function() {
          this.style.borderColor = '#6d28d9';
          this.style.boxShadow = '0 2px 8px rgba(109,40,217,0.15)';
        };
        exampleDiv.onmouseout = function() {
          this.style.borderColor = '#e2e8f0';
          this.style.boxShadow = 'none';
        };
        exampleDiv.onclick = function() {
          msg.value = example.text;
          updateMiaCharCount();
          showMiaToast('Exemplo selecionado!', 'success');
        };
        
        const previewText = example.text.length > 120 ? example.text.substring(0, 120) + '...' : example.text;
        exampleDiv.innerHTML = `
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
            <span style="font-size:11px;font-weight:700;color:#6d28d9">Exemplo ${index + 1}</span>
            <i class="fa fa-check-circle-o" style="color:#94a3b8;font-size:13px"></i>
          </div>
          <div style="font-size:12px;color:#475569;line-height:1.5">${previewText.replace(/\n/g, '<br>')}</div>
        `;
        examplesList.appendChild(exampleDiv);
      });
      
      msg.value = examples[0].text;
    } else {
      msg.value = previous;
    }
    
    updateMiaCharCount();
    showMiaToast('ConteÃºdo gerado com sucesso.', 'success');
  }).catch(function(err){
    msg.value = previous;
    updateMiaCharCount();
    examplesContainer.style.display = 'none';
    showMiaToast(err.message || 'Erro ao gerar conteÃºdo.', 'error');
  });
}

function formatMiaMsg(symbol){
  const msg = document.getElementById('ia-msg');
  const start = msg.selectionStart;
  const end = msg.selectionEnd;
  const text = msg.value;
  const selectedText = text.substring(start, end);
  const before = text.substring(0, start);
  const after = text.substring(end);
  
  if(selectedText){
    msg.value = before + symbol + selectedText + symbol + after;
    msg.selectionStart = start;
    msg.selectionEnd = end + (symbol.length * 2);
  } else {
    msg.value = before + symbol + symbol + after;
    msg.selectionStart = start + symbol.length;
    msg.selectionEnd = start + symbol.length;
  }
  msg.focus();
  updateMiaCharCount();
}

function toggleMiaEmoji(){
  const p = document.getElementById('mia-emoji-picker');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
  
  if(p.style.display === 'block'){
    const picker = p.querySelector('emoji-picker');
    if(picker && !picker.hasListener){
      picker.addEventListener('emoji-click', event => {
        addMiaEmoji(event.detail.unicode);
      });
      picker.hasListener = true;
    }
  }
}

function addMiaEmoji(emoji){
  const msg = document.getElementById('ia-msg');
  const start = msg.selectionStart;
  const text = msg.value;
  msg.value = text.substring(0, start) + emoji + text.substring(start);
  msg.selectionStart = msg.selectionEnd = start + emoji.length;
  msg.focus();
  updateMiaCharCount();
  document.getElementById('mia-emoji-picker').style.display = 'none';
}

function updateMiaWppPreviewCta(){
  const ctaSelect = document.getElementById('mia-input-cta');
  const toneSelect = document.getElementById('mia-input-tone');
  const ctaPreview = document.getElementById('mia-wpp-cta-prev');
  const statusPreview = document.getElementById('mia-wpp-prev-status');
  
  if (ctaSelect && ctaPreview) {
    ctaPreview.textContent = miaGetSelectedCtaText();
  }
  
  if (toneSelect && statusPreview) {
    const selectedToneText = toneSelect.options[toneSelect.selectedIndex].text;
    const toneEmoji = selectedToneText.split(' â€” ')[0];
    
    // Tenta manter as informaÃ§Ãµes do produto se jÃ¡ existirem
    const product = miaGetSelectedProduct();
    if (product) {
        statusPreview.textContent = toneEmoji + ' Â· ' + String(product.name || 'Produto') + ' Â· ' + Number(product.stock || 0) + ' un.';
    } else {
        statusPreview.textContent = toneEmoji;
    }
  }
  
  // Atualizar o carrossel com o novo CTA
  miaRenderPreviewCarousel();
}

function updateMiaCharCount(){
  const msg = document.getElementById('ia-msg');
  const count = document.getElementById('mia-char-count');
  const prev = document.getElementById('wpp-text-prev');
  const metricGroups = document.getElementById('mia-wpp-metric-groups');
  const metricReach = document.getElementById('mia-wpp-metric-reach');
  const metricRate = document.getElementById('mia-wpp-metric-rate');
  if(msg && count){
    count.textContent = msg.value.length + ' chars';
  }
  // NÃ£o atualizar o texto separado se temos mÃ­dias selecionadas (carrossel)
  if(msg && prev && miaSelectedMediaUrls.length === 0){
    prev.textContent = msg.value;
  }
  
  // Atualiza Preview do CTA e Tom
  updateMiaWppPreviewCta();

  const selectedGroupIds = Array.from(document.querySelectorAll('input[name="mia-target-groups"]:checked')).map(function(el){
    return Number(el.value || 0);
  });
  const reach = selectedGroupIds.reduce(function(acc, gid){
    const group = miaGroupsMap[gid] || {};
    return acc + Number(group.member_count || 0);
  }, 0);
  if (metricGroups) metricGroups.textContent = String(selectedGroupIds.length);
  if (metricReach) metricReach.textContent = String(reach);
  if (metricRate) metricRate.textContent = selectedGroupIds.length > 0 ? '38%' : '0%';
}
const MIA_API = {
  groups: '<?php echo root_url(); ?>api/concierge/groups.php',
  campaigns: '<?php echo root_url(); ?>api/concierge/campaigns.php',
  products: '<?php echo root_url(); ?>api/concierge/products.php',
  status: '<?php echo root_url(); ?>api/concierge/status.php',
  campaignText: '<?php echo root_url(); ?>api/concierge/campaign_text.php'
};
const MIA_CAN_MANAGE = <?php echo (user_group_id() == 1 || has_permission('access', 'concierge_groups_manage')) ? 'true' : 'false'; ?>;
const MIA_CAN_AI_CREATE = <?php echo (user_group_id() == 1 || has_permission('access', 'concierge_groups_ai_create')) ? 'true' : 'false'; ?>;
const MIA_TENANT_ID = <?php echo (int)$tenantId; ?>;
const MIA_ROOT = '<?php echo root_url(); ?>';
const MIA_STORE_NAME = <?php echo json_encode(ai_get_store_name($tenantId)); ?>;
const MIA_TOKEN = '<?php 
  $st = db()->prepare("SELECT ai_webhook_token FROM stores WHERE store_id = ? LIMIT 1");
  $st->execute([$tenantId]);
  echo (string)$st->fetchColumn(); 
?>';
let miaCampaignsMap = {};
let miaGroupsMap = {};
let miaStatusAutoTarget = 0;
let miaCurrentCampaignId = 0;
let miaCurrentGroupId = 0;
let miaGroupsSyncLoading = false;
let miaCampaignSendLocks = {};
let miaStatusSendLocks = {};
let miaLazyCronInterval = null;
function miaHasAnyDispatchLock(){
  const campaignBusy = Object.keys(miaCampaignSendLocks).some(function(key){ return !!miaCampaignSendLocks[key]; });
  const statusBusy = Object.keys(miaStatusSendLocks).some(function(key){ return !!miaStatusSendLocks[key]; });
  return campaignBusy || statusBusy;
}
function miaAcquireDispatchLock(kind, lockKey){
  if (miaHasAnyDispatchLock()) {
    showMiaToast('JÃ¡ existe um disparo em andamento. Aguarde terminar antes de enviar outro.', 'warning');
    return false;
  }
  const key = String(lockKey || 'default');
  if (kind === 'status') {
    miaStatusSendLocks[key] = true;
  } else {
    miaCampaignSendLocks[key] = true;
  }
  return true;
}
function miaReleaseDispatchLock(kind, lockKey){
  const key = String(lockKey || 'default');
  if (kind === 'status') {
    if (Object.prototype.hasOwnProperty.call(miaStatusSendLocks, key)) {
      delete miaStatusSendLocks[key];
    }
    return;
  }
  if (Object.prototype.hasOwnProperty.call(miaCampaignSendLocks, key)) {
    delete miaCampaignSendLocks[key];
  }
}

function miaSafeParseApiData(rawText){
  const text = String(rawText == null ? '' : rawText).trim();
  if (text === '') {
    return { error: true, message: 'Resposta vazia do servidor.' };
  }
  try {
    const parsed = JSON.parse(text);
    if (parsed && typeof parsed === 'object') return parsed;
  } catch (e) {}

  const firstBrace = text.indexOf('{');
  const lastBrace = text.lastIndexOf('}');
  if (firstBrace !== -1 && lastBrace > firstBrace) {
    try {
      const parsed = JSON.parse(text.substring(firstBrace, lastBrace + 1));
      if (parsed && typeof parsed === 'object') return parsed;
    } catch (e) {}
  }
  return { error: true, message: 'Resposta invÃ¡lida do servidor.' };
}

function miaScheduleDispatchSync(delayMs){
  const delay = Math.max(600, Number(delayMs || 2500));
  setTimeout(function(){
    miaTriggerLazyCron();
    miaRefreshCurrentView();
    miaLoadStatusHistory();
  }, delay);
}

function miaStatusToFiltro(status){
  const map = { scheduled: 'agendado', pending: 'agendado', sending: 'enviando', sent: 'enviado', draft: 'rascunho', needs_approval: 'aprovacao' };
  return map[status] || (status || 'todos');
}
function miaFiltroToStatus(filtro){
  const map = { agendado: 'pending', enviando: 'sending', enviado: 'sent', rascunho: 'draft', aprovacao: 'needs_approval' };
  return map[filtro] || '';
}
function miaEsc(v){
  return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; });
}
function miaFmtDateTime(dt){
  if(!dt) return {day:'NÃ£o agendado',time:'--:--'};
  const d = new Date(dt.replace(' ', 'T'));
  if(Number.isNaN(d.getTime())) return {day: dt, time: '--:--'};
  return {
    day: d.toLocaleDateString('pt-BR'),
    time: d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})
  };
}
function miaParseGroupSettings(raw){
  // Sempre retorna um OBJETO (nunca Array!), para garantir serializaÃ§Ã£o do JSON.stringify
  if (raw && typeof raw === 'object' && raw !== null && !Array.isArray(raw)) return raw;
  if (!raw) return {};
  try {
    const parsed = JSON.parse(String(raw));
    if (parsed && typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
      return parsed;
    }
    // Se for um Array, converte para Objeto vazio
    return {};
  } catch (e) {
    return {};
  }
}
function miaGetGroupDispatchInterval(group){
  const g = group || {};
  const settings = miaParseGroupSettings(g.settings_json);
  return Math.max(0, Number(
    g.dispatch_interval_minutes
    || settings.dispatch_interval_minutes
    || settings.interval_between_dispatches
    || 0
  ) || 0);
}
function miaSetGroupsSyncLoading(isLoading, opts){
  miaGroupsSyncLoading = !!isLoading;
  const cfg = opts || {};
  const loader = document.getElementById('mia-sync-loading-box');
  const loaderTitle = document.getElementById('mia-sync-loading-title');
  const loaderSub = document.getElementById('mia-sync-loading-sub');
  const syncInfo = document.getElementById('mia-evolution-sync-info');
  const btn = document.getElementById('mia-sync-groups-btn');
  const btnIcon = document.getElementById('mia-sync-groups-btn-icon');
  const btnLabel = document.getElementById('mia-sync-groups-btn-label');
  const groupsGrid = document.getElementById('mia-groups-detected-grid');
  if (loaderTitle && cfg.title) loaderTitle.textContent = cfg.title;
  if (loaderSub && cfg.sub) loaderSub.textContent = cfg.sub;
  if (loader) loader.classList.toggle('show', miaGroupsSyncLoading);
  if (groupsGrid) groupsGrid.classList.toggle('sync-loading', miaGroupsSyncLoading);
  if (btn) {
    btn.disabled = miaGroupsSyncLoading;
    btn.classList.toggle('is-loading', miaGroupsSyncLoading);
  }
  if (btnIcon) btnIcon.className = miaGroupsSyncLoading ? 'fa fa-spinner fa-spin' : 'fa fa-refresh';
  if (btnLabel) btnLabel.textContent = miaGroupsSyncLoading ? 'Sincronizando...' : 'Sync';
  if (syncInfo && miaGroupsSyncLoading) {
    syncInfo.textContent = 'SincronizaÃ§Ã£o em andamento Â· Buscando grupos na Evolution...';
  }
}

function miaApi(method, url, payload){
  const upperMethod = String(method || 'GET').toUpperCase();
  const opts = { 
    method: upperMethod,
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      'X-Concierge-Token': MIA_TOKEN
    } 
  };
  if (payload !== undefined && payload !== null) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(payload);
  }
  let timeoutId = null;
  if (typeof AbortController !== 'undefined') {
    const timeoutMs = upperMethod === 'GET' ? 45000 : 240000;
    const controller = new AbortController();
    opts.signal = controller.signal;
    timeoutId = setTimeout(function(){ controller.abort(); }, timeoutMs);
  }

  return fetch(url, opts).then(function(r){
    return r.text().then(function(rawText){
      const data = miaSafeParseApiData(rawText);
      data.__http = r.status;
      if (!r.ok && !data.message) {
        data.message = 'Erro HTTP ' + r.status + '.';
      }
      return data;
    });
  }).catch(function(err){
    if (err && err.name === 'AbortError') {
      return { error: true, message: 'Tempo limite da API excedido. O envio pode ter sido processado; atualize para confirmar.' };
    }
    return { error: true, message: 'Falha de conexÃ£o com a API. Verifique e tente novamente.' };
  }).finally(function(){
    if (timeoutId) clearTimeout(timeoutId);
  });
}
function miaSyncCategoryToggle(sourceInput){
  if (!sourceInput) return;
  const sourceValue = String(sourceInput.value || '');
  const sourceChecked = !!sourceInput.checked;
  document.querySelectorAll('input[name="mia-cat"]').forEach(function(chk){
    if (chk !== sourceInput && String(chk.value || '') === sourceValue) {
      chk.checked = sourceChecked;
    }
  });
}
function miaUpdateFilterCounts(items){
  const counts = { todos: 0, aprovacao: 0, agendado: 0, enviado: 0, enviando: 0 };
  (items || []).forEach(function(c){
    counts.todos++;
    const key = miaStatusToFiltro(c.status || '');
    if (Object.prototype.hasOwnProperty.call(counts, key)) counts[key]++;
  });
  const ids = {
    'mia-count-todos': counts.todos,
    'mia-count-aprovacao': counts.aprovacao,
    'mia-count-agendado': counts.agendado,
    'mia-count-enviado': counts.enviado,
    'mia-count-enviando': counts.enviando
  };
  Object.keys(ids).forEach(function(id){
    const el = document.getElementById(id);
    if (el) el.textContent = String(ids[id]);
  });
}
function miaUpdateNextScheduled(items){
  const nextTitle = document.getElementById('mia-next-title');
  const nextTime = document.getElementById('mia-next-time');
  const nextDate = document.getElementById('mia-next-date');
  const nextGroups = document.getElementById('mia-next-groups');
  if (!nextTitle || !nextTime || !nextDate || !nextGroups) return;

  const scheduled = (items || [])
    .filter(function(c){ return (c.status || '') === 'scheduled' && !!c.scheduled_at; })
    .sort(function(a,b){ return new Date(a.scheduled_at).getTime() - new Date(b.scheduled_at).getTime(); });

  if (!scheduled.length) {
    nextTitle.textContent = 'Sem campanha agendada';
    nextTime.textContent = '--:--';
    nextDate.textContent = 'Sem previsÃ£o';
    nextGroups.innerHTML = '';
    return;
  }
  const c = scheduled[0];
  const dt = miaFmtDateTime(c.scheduled_at);
  nextTitle.textContent = c.title || ('Campanha #' + c.id);
  nextTime.textContent = dt.time;
  nextDate.textContent = dt.day;
  nextGroups.innerHTML = '<span class="next-chip">'+Number(c.total_targets || 0)+' grupos alvo</span>';
}
function miaRenderSideGroups(groups){
  const side = document.getElementById('mia-side-groups');
  if (!side) return;
  if (!groups || !groups.length) {
    side.textContent = 'Nenhum grupo sincronizado.';
    return;
  }
  side.innerHTML = groups.slice(0, 5).map(function(g){
    const on = Number(g.is_active || 0) === 1;
    return '<div class="group-item '+(on ? 'active' : '')+'">'
      + '<div class="group-av" style="background:'+(on ? '#7c3aed' : '#94a3b8')+'"><i class="fa fa-users"></i><div class="g-status '+(on ? 'g-on' : 'g-off')+'"></div></div>'
      + '<div style="flex:1"><div class="group-name">'+miaEsc(g.name || 'Grupo')+'</div><div class="group-members"><i class="fa fa-users"></i> '+Number(g.member_count || 0)+' membros</div></div>'
      + '</div>';
  }).join('');
}
function miaRenderGroupRules(groups){
  const rules = document.getElementById('mia-group-rules-container');
  if (!rules) return;
  if (!groups || !groups.length) {
    rules.textContent = 'Nenhum grupo sincronizado.';
    return;
  }
  rules.innerHTML = groups.map(function(g){
    const on = Number(g.is_active || 0) === 1;
    return '<div class="group-config-card" style="margin-bottom:10px">'
      + '<div class="gc-header"><div class="gc-title"><i class="fa fa-users"></i> '+miaEsc(g.name || 'Grupo')+'</div><div class="sbadge '+(on ? 'sent' : 'draft')+'">'+(on ? 'Ativo' : 'Inativo')+'</div></div>'
      + '<div class="gc-body"><div style="font-size:11px;color:#64748b">Membros: '+Number(g.member_count || 0)+' Â· Limite diÃ¡rio: '+Number(g.daily_limit || 0)+'/dia</div></div>'
      + '</div>';
  }).join('');
}
function miaRenderQuotaList(groups, plan){
  const quota = document.getElementById('mia-quota-list');
  if (!quota) return;
  const list = Array.isArray(groups) ? groups : [];
  const planMeta = plan || {};
  if (!list.length) {
    quota.innerHTML = '<div style="font-size:11px;color:#94a3b8;padding:10px 0">Aguardando sincronizaÃ§Ã£o de grupos...</div>';
    return;
  }
  quota.innerHTML = '<div class="quota-group-list">' + list.map(function(g){
    const dailyLimit = Number(g.daily_limit || 0);
    const completed = Number(g.completed_campaigns || 0);
    const scheduled = Number(g.scheduled_campaigns || 0);
    const total = completed + scheduled;
    const progress = Number(g.progress_pct || 0);
    const unlimited = dailyLimit <= 0;
    const statusClass = unlimited ? 'unlimited' : (progress >= 80 ? 'limited' : 'status-wpp');
    const fillClass = progress >= 80 ? 'warn' : 'ok';
    const fillWidth = Math.max(0, Math.min(100, progress));
    const planBadge = Number(planMeta.groups_limit || 0) > 0
      ? ('Plano: ' + Number(planMeta.groups_limit) + ' ativos')
      : 'Plano ilimitado';
    return '<div class="quota-group-item '+statusClass+'">'
      + '<div class="quota-top"><div class="quota-name">'+miaEsc(g.name || 'Grupo')+'</div><span class="quota-pill '+(unlimited ? 'unlimited' : 'limited')+'">'+(unlimited ? 'Sem limite/dia' : (dailyLimit + '/dia'))+'</span></div>'
      + '<div class="quota-sub"><span><i class="fa fa-users"></i> '+Number(g.member_count || 0)+' membros</span><span><i class="fa fa-line-chart"></i> '+total+' campanhas</span><span><i class="fa fa-shield"></i> '+miaEsc(planBadge)+'</span></div>'
      + '<div class="quota-track"><div class="quota-fill '+fillClass+'" style="width:'+fillWidth+'%"></div></div>'
      + '<div class="quota-cats"><span class="qcat">Agendados: '+scheduled+'</span><span class="qcat">Realizados: '+completed+'</span><span class="qcat">Progresso: '+fillWidth+'%</span></div>'
      + '</div>';
  }).join('') + '</div>';
}
function miaRenderWeeklyStatus(statusItem, isDrawer){
  const history = miaParseGroupSettings(statusItem.success_history_json || '{}');
  const repeatDays = String(statusItem.repeat_days || '').split(',').filter(d => d !== '').map(Number);
  const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'SÃ¡b'];
  const today = new Date();
  
  // Normaliza o histÃ³rico para garantir que chaves com espaÃ§os ou formatos diferentes batam
  const normHistory = {};
  Object.keys(history).forEach(function(k){
    normHistory[k.trim()] = history[k];
  });

  let html = '<div style="display:flex; gap:6px; justify-content:space-between; align-items:center; width:100%">';
  
  for (let i = 0; i < 7; i++) {
    const d = new Date(today);
    const diff = i - d.getDay();
    d.setDate(d.getDate() + diff);
    
    // Formata YYYY-MM-DD usando data local para bater com o PHP
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const dateStr = year + '-' + month + '-' + day;
    
    const isTargetDay = repeatDays.indexOf(i) !== -1;
    let wasSuccessful = normHistory[dateStr] === 'sent';
    const isToday = i === today.getDay();

    // Fallback: se o status for 'sent' e o sent_at/updated_at for hoje, marca como sucesso
    if (!wasSuccessful && isToday && (statusItem.status === 'sent')) {
      const lastDate = (statusItem.sent_at || statusItem.updated_at || '').slice(0, 10);
      if (lastDate === dateStr) {
        wasSuccessful = true;
      }
    }
    
    let color = '#f1f5f9'; // Inativo
    let textColor = '#94a3b8';
    let title = days[i] + ': NÃ£o agendado';
    
    if (isTargetDay) {
      color = '#607af92b'; // Agendado (Azul claro solicitado)
      textColor = '#000'; // Letra preta solicitada
      title = days[i] + ': Agendado';
      if (wasSuccessful) {
        color = '#22c55e'; // Sucesso (verde)
        textColor = isDrawer ? '#fff' : '#000'; // Branco no Drawer, Preto na Lista
        title = days[i] + ': Postado com sucesso';
      } else if (d < today && !isToday) {
        color = '#ef4444'; // Falha (vermelho)
        textColor = '#fff';
        title = days[i] + ': Falha no envio';
      }
    } else if (wasSuccessful) {
        color = '#22c55e';
        textColor = isDrawer ? '#fff' : '#000'; // Branco no Drawer, Preto na Lista
        title = days[i] + ': Postado manualmente';
    }

    const borderStyle = isToday ? 'border: 2px solid #7c3aed;' : '';
    const showIcon = wasSuccessful && isDrawer; // Somente no Drawer

    html += '<div title="'+title+'" style="flex:1; aspect-ratio:1; max-width:34px; border-radius:8px; background:'+color+'; display:flex; flex-direction:column; align-items:center; justify-content:center; '+borderStyle+' transition: transform 0.2s">'
      + '<span style="font-size:8px; font-weight:800; color:'+textColor+'; text-transform:uppercase">'+days[i].charAt(0)+'</span>'
      + (showIcon ? '<i class="fa fa-check" style="font-size:8px; color:#fff; margin-top:1px"></i>' : '')
      + '</div>';
  }
  
  html += '</div>';
  return html;
}

function miaLoadStatuses(){
  const status = miaFiltroToStatus(miaFiltroAtual);
  const search = (document.getElementById('mia-search-campanha') ? document.getElementById('mia-search-campanha').value : '').trim().toLowerCase();
  const qs = new URLSearchParams({ page: '1', limit: '50' });
  if (status) qs.set('status', status);
  const loading = document.getElementById('mia-filter-loading');
  if (loading) loading.classList.add('show');
  miaApi('GET', MIA_API.status + '?' + qs.toString()).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao carregar status');
    const items = (resp.data || {}).items || [];
    miaUpdateStatusSidebarKpis(items);
    const view = document.getElementById('view-status');
    if (!view) return;
    const filtered = items.filter(function(s){
      const txt = ((s.content || '') + ' ' + (s.category || '') + ' ' + (s.status || '')).toLowerCase();
      return !search || txt.indexOf(search) !== -1;
    });
    if (!filtered.length) {
      view.innerHTML = '<div style="padding:16px;color:#94a3b8;font-size:12px">Nenhuma postagem de status encontrada.</div>';
    } else {
      view.innerHTML = filtered.map(function(s){
        const dt = miaFmtDateTime(s.scheduled_at || s.created_at);
        const mediaUrls = Array.isArray(s.media_urls) ? s.media_urls : [];
        const thumb = mediaUrls[0] || '';
        const productLabel = (s.product_name || ((s.payload_json || {}).product_name) || (Number(s.product_id || 0) > 0 ? ('Produto #' + Number(s.product_id)) : ('Status #' + Number(s.id))));
        const statusText = String(s.content || '').trim();
        
        let isSending = s.status === 'sending';
        let hasTimeout = false;
        if (isSending && s.updated_at) {
          const lastUpdate = new Date(s.updated_at.replace(' ', 'T')).getTime();
          const now = new Date().getTime();
          // Timeout se estiver enviando hÃ¡ mais de 120 segundos (120000ms)
          if (now - lastUpdate > 120000) { hasTimeout = true; isSending = false; }
        }

        let badgeClass = s.status === 'sent' ? 'sent' : (s.status === 'error' ? 'draft' : (s.status === 'canceled' ? 'draft' : (isSending ? 'sending' : (s.status === 'pending' ? 'scheduled' : 'scheduled'))));
        let badgeText = s.status === 'sent' ? 'Sucesso' : (s.status === 'error' ? 'Erro' : (s.status === 'canceled' ? 'Cancelado' : (isSending ? 'Enviando' : (s.status === 'pending' ? 'Agendado' : 'Agendado'))));
        
        if (hasTimeout) { 
          badgeText = 'Sem resposta'; 
          badgeClass = 'draft'; 
        }

        const canCancel = MIA_CAN_MANAGE && s.status === 'pending';
        const canSendNow = MIA_CAN_MANAGE && (s.status === 'pending' || isSending);
        const canDelete = MIA_CAN_MANAGE && (s.status === 'sent' || s.status === 'error' || s.status === 'canceled' || badgeText === 'Sem resposta');
        return '<div class="brow" id="status-row-'+Number(s.id)+'" style="grid-template-columns:46px 1fr 120px 120px 140px 60px 80px; cursor:pointer" onclick="miaOpenStatusDetails('+Number(s.id)+')">'
          + '<div class="brow-thumb" onclick="event.stopPropagation()">'+(thumb ? ('<img src="'+miaEsc(thumb)+'" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:8px">') : 'ðŸŸ£')+'</div>'
          + '<div>'
            + '<div class="brow-name">'+miaEsc(productLabel)+'</div>'
            + '<div style="font-size:10px;color:#7c3aed;font-weight:700;margin-top:1px">ID: #'+Number(s.id)+'</div>'
            + '<div class="brow-meta">'+miaEsc(statusText.substring(0, 80) || (mediaUrls.length ? mediaUrls.length + ' mÃ­dias' : 'Sem mÃ­dia'))+'</div>'
          + '</div>'
          + '<div><span class="sbadge '+badgeClass+'">'+(isSending ? '<i class="fa fa-spinner fa-spin"></i> ' : '')+miaEsc(badgeText)+'</span></div>'
          + '<div><div class="brow-sched">'+miaEsc(dt.day)+'</div><div class="brow-sched-sub"><i class="fa fa-clock-o"></i> '+miaEsc(dt.time)+'</div></div>'
          + '<div class="brow-count">'+miaRenderWeeklyStatus(s)+'</div>'
          + '<div class="brow-count"><div class="brow-count-val" style="color:#22c55e">'+Number(s.post_count || 0)+'</div><div class="brow-count-lbl">sucessos</div></div>'
          + '<div class="brow-actions" onclick="event.stopPropagation()">'
            + (canSendNow ? '<button class="icon-btn fire" '+(isSending ? 'disabled' : '')+' title="'+(isSending ? 'Enviando...' : 'Disparar agora')+'" onclick="miaSendStatusNow('+Number(s.id)+')">'+(isSending ? '<i class="fa fa-spinner fa-spin"></i>' : '<i class="fa fa-bolt"></i>')+'</button>' : '')
            + (canCancel ? '<button class="icon-btn danger" title="Cancelar status" onclick="miaCancelStatus('+Number(s.id)+')"><i class="fa fa-times"></i></button>' : '')
            + (canDelete ? '<button class="icon-btn danger" title="Excluir postagem" onclick="miaCancelStatus('+Number(s.id)+')"><i class="fa fa-trash"></i></button>' : '')
            + (!canCancel && !canSendNow && !canDelete ? '<span class="sbadge '+badgeClass+'">'+miaEsc(badgeText)+'</span>' : '')
          + '</div>'
          + '</div>';
      }).join('');
    }
    const regCount = document.getElementById('mia-reg-count');
    if (regCount) regCount.textContent = (filtered.length || 0) + ' registros';
    const empty = document.getElementById('mia-empty-state');
    if (empty) empty.classList.toggle('show', filtered.length === 0);
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao carregar status', 'error');
  }).finally(function(){
    if (loading) loading.classList.remove('show');
  });
}
function miaCancelStatus(statusId){
  if (!MIA_CAN_MANAGE) return;
  
  const statusItem = (miaCurrentView === 'status') ? null : null; // We don't have a map for status yet in this scope easily
  
  const modal = document.getElementById('mia-ov-confirm-del');
  const btn = document.getElementById('mia-confirm-del-btn');
  const titleEl = document.getElementById('mia-confirm-del-title');
  const textEl = document.getElementById('mia-confirm-del-text');
  
  if (titleEl) titleEl.textContent = 'Remover Postagem?';
  if (textEl) textEl.textContent = 'Deseja realmente remover esta postagem de status? Esta aÃ§Ã£o nÃ£o pode ser desfeita.';
  
  if (btn) {
    btn.onclick = function(){
      btn.disabled = true;
      miaApi('DELETE', MIA_API.status + '?id=' + statusId).then(function(resp){
        if (resp.error) throw new Error(resp.message || 'Falha ao remover status');
        showMiaToast(resp.message || 'Postagem removida.', 'success');
        fecharMiaModal('confirm-del');
        miaLoadStatuses();
        miaLoadStatusHistory();
      }).catch(function(err){
        showMiaToast(err.message || 'Erro ao remover status', 'error');
      }).finally(function(){
        btn.disabled = false;
      });
    };
  }
  abrirMiaModal('confirm-del');
}
function miaSendStatusNow(statusId){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o.', 'warning'); return; }
  
  // Bloqueio de ConcorrÃªncia
  const lockKey = 'send_status_now_' + Number(statusId || 0);
  if (!miaAcquireDispatchLock('status', lockKey)) return;
  
  const row = document.getElementById('status-row-' + statusId);
  const btn = row ? row.querySelector('.icon-btn.fire') : null;
  let oldHtml = '';
  if (btn) {
    oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
  }

  showMiaToast('Iniciando disparo de status...', 'info');
  miaApi('POST', MIA_API.status + '?action=send_now', { id: statusId }).then(function(resp){
    if (resp.error) {
        throw new Error(resp.message || 'Falha no disparo');
    }
    showMiaToast(resp.message || 'Status processado com sucesso.', 'success');
    miaLoadStatuses();
    miaLoadStatusHistory();
    miaScheduleDispatchSync(1200);
  }).catch(function(err){ 
    showMiaToast(err.message || 'Erro ao disparar status', 'error'); 
  }).finally(function(){
    miaReleaseDispatchLock('status', lockKey);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = oldHtml;
    }
  });
}
function miaRefreshCurrentView(){
  if (miaCurrentView === 'status') {
    miaLoadStatuses();
    return;
  }
  if (miaCurrentView === 'grupos') {
    miaLoadGroups();
    return;
  }
  miaLoadCampaigns();
}

// â”€â”€ LAZY CRON & AUTO REFRESH â”€â”€
let miaStatusPollingInterval = null;
let miaIsDispatching = false;
let miaNotifiedSentIds = new Set(); // Controle de IDs jÃ¡ notificados com Toast

function miaStartStatusPolling(){
  if (miaStatusPollingInterval) return;
  miaStatusPollingInterval = setInterval(function(){
    const sendingItems = document.querySelectorAll('.sbadge.sending');
    if (sendingItems.length > 0 || miaCurrentView === 'status') {
      const qs = new URLSearchParams({ page: '1', limit: '20' });
      miaApi('GET', MIA_API.status + '?' + qs.toString()).then(function(resp){
        if (resp.error) return;
        const items = (resp.data || {}).items || [];
        
        items.forEach(function(s){
          if (s.status === 'sent' && !miaNotifiedSentIds.has(s.id)) {
            miaNotifiedSentIds.add(s.id);
            const dt = miaFmtDateTime(s.sent_at || new Date().toISOString());
            let msg = 'Postagem do Item #' + s.id + ' feita com sucesso em ' + dt.day + ' Ã s ' + dt.time + '.';
            
            // Verifica se hÃ¡ prÃ³xima postagem agendada (pelo repeat_days)
            if (s.repeat_days && s.scheduled_at) {
              const dtNext = miaFmtDateTime(s.scheduled_at);
              msg += ' PrÃ³xima postagem: ' + dtNext.day + ' ' + dtNext.time;
            }
            
            showMiaToast(msg, 'success');
          }
        });

        if (miaCurrentView === 'status') miaLoadStatuses();
        miaLoadStatusHistory();
      });
    }
  }, 10000); // Polling cada 10s
}

function miaTriggerLazyCron(){
  if (miaIsDispatching) return;
  miaIsDispatching = true;
  
  // Pequeno delay para nÃ£o competir com o carregamento inicial da pÃ¡gina
  setTimeout(function(){
    fetch(MIA_ROOT + 'api/concierge/dispatch.php', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'X-Concierge-Token': MIA_TOKEN
      }
    })
      .then(r => r.json())
      .then(resp => {
        if (resp && resp.data && (resp.data.statuses || resp.data.campaigns)) {
          // Se houve algum disparo, atualiza a interface
          if (miaCurrentView === 'status') miaLoadStatuses();
          miaLoadStatusHistory();
        }
      })
      .catch(() => {})
      .finally(() => {
        miaIsDispatching = false;
      });
  }, 2000); 
}

function miaLoadCampaigns(){
  const status = miaFiltroToStatus(miaFiltroAtual);
  const search = (document.getElementById('mia-search-campanha') ? document.getElementById('mia-search-campanha').value : '').trim();
  const qs = new URLSearchParams({ page: '1', limit: '50' });
  if (status) qs.set('status', status);
  if (search) qs.set('search', search);
  const loading = document.getElementById('mia-filter-loading');
  if (loading) loading.classList.add('show');

  miaApi('GET', MIA_API.campaigns + '?' + qs.toString()).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao carregar campanhas');
    const data = (resp.data || {});
    const items = data.items || [];
    const view = document.getElementById('view-campanhas');
    if (!view) return;
    miaCampaignMap = {};
    view.innerHTML = items.map(function(c){
      miaCampaignMap[c.id] = c;
      const gcount = Number(c.total_targets || 0);
      const dt = miaFmtDateTime(c.scheduled_at || c.created_at);
      const mediaUrls = Array.isArray(c.media_urls) ? c.media_urls : [];
      const thumb = c.media_url || mediaUrls[0] || '';
      const groupNames = Array.isArray(c.group_names) ? c.group_names : [];
      const groupsMeta = groupNames.length ? groupNames.slice(0, 3).join(' Â· ') : (gcount + ' grupos');
      const groupsCol = groupNames.length
        ? groupNames.slice(0, 2).map(function(name){ return '<span class="gchip">'+miaEsc(name)+'</span>'; }).join('')
        : '<span class="gchip">'+gcount+' grupos</span>';
      const filtro = miaStatusToFiltro(c.status);
      const badgeClass = c.status === 'sent' ? 'sent' : (c.status === 'scheduled' ? 'scheduled' : (c.status === 'sending' ? 'sending' : 'draft'));
      const badgeText = c.status === 'scheduled' ? 'Agendado' : (c.status === 'sending' ? 'Enviando' : (c.status === 'sent' ? 'Enviado' : (c.status === 'needs_approval' ? 'AprovaÃ§Ã£o' : 'Rascunho')));
      return '<div class="brow" data-status="'+miaEsc(filtro)+'" data-text="'+miaEsc((c.title || '') + ' ' + groupsMeta)+'" onclick="miaOpenCampaign('+Number(c.id)+')">'
        + '<div class="brow-thumb">'+(thumb ? ('<img src="'+miaEsc(thumb)+'" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:8px">') : 'ðŸ“£')+'</div>'
        + '<div><div class="brow-name">'+miaEsc(c.title || ('Campanha #' + c.id))+'</div><div class="brow-meta">'+miaEsc(groupsMeta)+'</div></div>'
        + '<div class="brow-groups">'+groupsCol+'</div>'
        + '<div><div class="brow-sched">'+miaEsc(dt.day)+'</div><div class="brow-sched-sub"><i class="fa fa-clock-o"></i> '+miaEsc(dt.time)+'</div></div>'
        + '<div class="brow-count"><div class="brow-count-val">'+Number(c.sent_targets || 0)+'</div><div class="brow-count-lbl">enviados</div></div>'
        + '<div class="brow-count"><div class="brow-count-val" style="color:#64748b">'+gcount+'</div><div class="brow-count-lbl">alvos</div></div>'
        + '<div><span class="sbadge '+badgeClass+'"><i class="fa fa-circle"></i> '+miaEsc(badgeText)+'</span></div>'
        + '<div class="brow-actions">'
            + (MIA_CAN_MANAGE ? '<button class="icon-btn fire" title="Disparar agora" onclick="event.stopPropagation();miaSendNow('+Number(c.id)+')"><i class="fa fa-bolt"></i></button>' : '')
            + (MIA_CAN_MANAGE ? '<button class="icon-btn danger" title="Excluir campanha" onclick="event.stopPropagation();miaDeleteCampaign('+Number(c.id)+')"><i class="fa fa-times"></i></button>' : '')
          + '</div>'
        + '</div>';
    }).join('');

    const regCount = document.getElementById('mia-reg-count');
    if (regCount) regCount.textContent = (items.length || 0) + ' registros';
    const empty = document.getElementById('mia-empty-state');
    if (empty) empty.classList.toggle('show', items.length === 0);
    miaUpdateFilterCounts(items);
    miaUpdateNextScheduled(items);
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao carregar campanhas', 'error');
  }).finally(function(){
    if (loading) loading.classList.remove('show');
  });
}

function miaLoadCategories(allowedCatsCsv){
  const grid = document.getElementById('mia-group-cats-grid');
  const gridIA = document.getElementById('mia-ia-cats-grid');
  if (!grid && !gridIA) return;
  
  const allowed = new Set((allowedCatsCsv || '').split(',').map(function(v){
    return String(v || '').trim();
  }).filter(function(v){ return v !== ''; }));

  miaApi('GET', MIA_API.products + '?action=categories').then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao carregar categorias');
    const rawItems = (resp.data || {}).items || [];
    
    // Garantir que os itens da lista sejam Ãºnicos por ID e por nome para evitar duplicaÃ§Ã£o visual.
    const items = [];
    const seenIds = new Set();
    const seenNames = new Set();
    rawItems.forEach(function(item){
      const idStr = String(item && item.id != null ? item.id : '').trim();
      const nameStr = String(item && item.name != null ? item.name : '').trim();
      const nameKey = nameStr.toLowerCase();
      if (nameStr === '') return;
      if (idStr !== '' && seenIds.has(idStr)) return;
      if (seenNames.has(nameKey)) return;

      if (idStr !== '') seenIds.add(idStr);
      seenNames.add(nameKey);
      items.push({
        id: idStr,
        name: nameStr
      });
    });
    
    const html = items.length === 0 
      ? '<div style="font-size:11px;color:#94a3b8">Nenhuma categoria encontrada.</div>'
      : items.map(function(c){
          const value = c.id !== '' ? c.id : c.name;
          const isChecked = allowed.has(String(value));
          return '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;background:#fff;padding:4px 8px;border-radius:6px;border:1px solid #e2e8f0;font-size:11px">'
            + '<input type="checkbox" name="mia-cat" value="'+miaEsc(value)+'" onchange="miaSyncCategoryToggle(this)" '+(isChecked ? 'checked' : '')+'>'
            + '<span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+miaEsc(c.name)+'</span>'
            + '</label>';
        }).join('');

    if (grid) grid.innerHTML = html;
    if (gridIA) gridIA.innerHTML = html;
  }).catch(function(err){
    const errHtml = '<div style="font-size:11px;color:#ef4444">Erro ao carregar categorias.</div>';
    if (grid) grid.innerHTML = errHtml;
    if (gridIA) gridIA.innerHTML = errHtml;
  });
}

function miaLoadGroups(){
  return miaApi('GET', MIA_API.groups).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao carregar grupos');
    const data = resp.data || {};
    const activeGroups = Array.isArray(data.groups) ? data.groups : [];
    const allGroups = Array.isArray(data.all_groups) ? data.all_groups : activeGroups;
    const displayGroups = Array.isArray(data.display_groups) ? data.display_groups : activeGroups.slice(0, 5);
    const settings = data.settings || {};
    const aiStats = data.ai_stats || {};
    const plan = data.plan || {};
    
    // Update AI Bar
    const aiBar = document.getElementById('mia-ai-bar');
    const aiBarTitle = document.getElementById('mia-ai-bar-title');
    const aiBarSub = document.getElementById('mia-ai-bar-sub');
    const aiBarFill = document.querySelector('.ai-bar-fill');
    if (aiBar && aiStats.catalog_count > 0) {
      aiBar.style.display = 'flex';
      aiBarTitle.innerText = 'IA analisou seu catÃ¡logo e identificou oportunidades!';
      if (aiBarSub) aiBarSub.innerText = aiStats.catalog_count + ' produtos ativos Â· ' + (aiStats.pending_campaigns || 0) + ' campanhas prontas para aprovaÃ§Ã£o';
      if (aiBarFill) { aiBarFill.style.width = '100%'; aiBarFill.style.animation = 'none'; aiBarFill.style.background = '#22c55e'; }
    }
    const normalizeGroup = function(raw){
      const g = Object.assign({}, raw || {});
      g.id = Number(g.id || 0);
      g.settings_json = miaParseGroupSettings(g.settings_json);
      if (!g.dispatch_interval_minutes) {
        g.dispatch_interval_minutes = miaGetGroupDispatchInterval(g);
      }
      return g;
    };
    const activeGroupsNorm = activeGroups.map(normalizeGroup);
    const allGroupsNorm = allGroups.map(normalizeGroup);
    const displayGroupsNorm = displayGroups.map(normalizeGroup);
    miaGroupsMap = {};
    allGroupsNorm.forEach(function(g){ miaGroupsMap[g.id] = g; });

    // Salva as configuraÃ§Ãµes globais em window.miaGlobalSettings para uso posterior
    window.miaGlobalSettings = settings || {};

    // Global settings
    const inDelay = document.getElementById('mia-group-delay');
    if (inDelay && settings.mia_group_delay) inDelay.value = settings.mia_group_delay;
    const inLimit = document.getElementById('mia-group-global-limit');
    if (inLimit && settings.mia_group_global_limit) inLimit.value = settings.mia_group_global_limit;
    
    // Status automation settings
    const autoEnable = document.getElementById('mia-status-auto-enable');
    if (autoEnable) autoEnable.checked = Number(settings.mia_status_auto_enable || 0) === 1;
    const autoCount = document.getElementById('mia-status-auto-count');
    if (autoCount) {
      autoCount.value = settings.mia_status_auto_count !== undefined && settings.mia_status_auto_count !== null ? settings.mia_status_auto_count : 4;
      miaStatusAutoTarget = Number(autoCount.value);
    }
    const autoRep = document.getElementById('mia-status-auto-rep');
    if (autoRep && settings.mia_status_auto_rep) autoRep.value = settings.mia_status_auto_rep;

    miaLoadCategories(settings.mia_allowed_categories || '');

    const view = document.getElementById('view-grupos');
    if (view) {
      if (!activeGroupsNorm.length) {
        view.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px">Nenhum grupo ativo sincronizado.</div>';
      } else {
        view.innerHTML = activeGroupsNorm.map(function(g){
          const progress = Math.max(0, Math.min(100, Number(g.progress_pct || 0)));
          const completed = Number(g.completed_campaigns || 0);
          const nextDispatch = g.next_dispatch_at ? miaFmtDateTime(g.next_dispatch_at) : { day: 'Sem previsÃ£o', time: '--:--' };
          const intervalMin = miaGetGroupDispatchInterval(g);
          const intervalLabel = intervalMin > 0 ? (intervalMin + ' min') : 'Sem intervalo';
          return '<div class="brow" style="grid-template-columns:46px 1fr 140px 170px 130px 110px;" onclick="miaOpenGroup('+Number(g.id)+')">'
            + '<div class="brow-thumb">ðŸ‘¥</div>'
            + '<div><div class="brow-name">'+miaEsc(g.name || 'Grupo')+'</div><div class="brow-meta">'+Number(g.member_count || 0)+' membros</div></div>'
            + '<div><div class="brow-count-val" style="font-size:13px;text-align:left">'+Number(g.daily_limit || 0)+'/dia</div><div class="brow-count-lbl" style="text-align:left">'+miaEsc(intervalLabel)+' entre disparos</div></div>'
            + '<div><div style="font-size:11px;font-weight:700;color:#334155">Realizados: '+completed+'</div><div style="font-size:10px;color:#64748b">Progresso: '+progress+'%</div><div class="group-mini-progress"><span style="width:'+progress+'%"></span></div></div>'
            + '<div><div class="group-next">'+miaEsc(nextDispatch.day)+'</div><div class="group-next-sub"><i class="fa fa-clock-o"></i> '+miaEsc(nextDispatch.time)+'</div></div>'
            + '<div class="brow-actions">'
            + (MIA_CAN_MANAGE ? '<button class="icon-btn" title="Editar grupo" onclick="event.stopPropagation();miaOpenGroup('+Number(g.id)+', true)"><i class="fa fa-pencil"></i></button>' : '')
            + (MIA_CAN_MANAGE ? '<button class="icon-btn" onclick="event.stopPropagation();miaToggleGroup('+Number(g.id)+')"><i class="fa fa-power-off"></i></button>' : '')
            + (MIA_CAN_MANAGE ? '<button class="icon-btn danger" onclick="event.stopPropagation();miaLeaveGroup('+Number(g.id)+')"><i class="fa fa-sign-out"></i></button>' : '')
            + '</div>'
            + '</div>';
        }).join('');
      }
    }
    if (miaCurrentView === 'grupos') {
      const regCount = document.getElementById('mia-reg-count');
      if (regCount) regCount.textContent = (activeGroupsNorm.length || 0) + ' registros';
      const empty = document.getElementById('mia-empty-state');
      if (empty) empty.classList.toggle('show', activeGroupsNorm.length === 0);
    }
    const groupsCfg = document.getElementById('mia-groups-detected-grid');
    if (groupsCfg) {
      if (!allGroupsNorm.length) {
        groupsCfg.innerHTML = '<div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8;font-size:12px">Nenhum grupo encontrado na sincronizaÃ§Ã£o.</div>';
      } else {
        groupsCfg.innerHTML = allGroupsNorm.map(function(g){
          const on = Number(g.is_active || 0) === 1;
          return '<label class="gc-item '+(on ? 'sel' : '')+'">'
            + '<input type="checkbox" data-group-id="'+Number(g.id)+'" '+(on ? 'checked' : '')+'>'
            + '<div class="gc-av" style="background:'+(on ? '#7c3aed' : '#94a3b8')+'"><i class="fa fa-users"></i></div>'
            + '<div style="flex:1"><div class="gc-name">'+miaEsc(g.name || 'Grupo')+'</div><div class="gc-meta">'+Number(g.member_count || 0)+' membros</div></div>'
            + '</label>';
        }).join('');
      }
    }
    const syncInfo = document.getElementById('mia-evolution-sync-info');
    if (syncInfo) {
      const limitLabel = Number(plan.groups_limit || 0) > 0 ? (' Â· plano: ' + Number(plan.groups_limit) + ' ativos') : ' Â· plano ilimitado';
      syncInfo.textContent = 'SincronizaÃ§Ã£o ativa Â· ' + allGroupsNorm.length + ' grupos detectados' + limitLabel;
    }

    const groupsStep3 = document.getElementById('mia-groups-step3');
    if (groupsStep3) {
      if (!activeGroupsNorm.length) {
        groupsStep3.innerHTML = '<div style="font-size:11px;color:#94a3b8">Nenhum grupo ativo disponÃ­vel.</div>';
      } else {
        groupsStep3.innerHTML = activeGroupsNorm.map(function(g){
          return '<label class="gc-item">'
            + '<input type="checkbox" name="mia-target-groups" value="'+Number(g.id)+'">'
            + '<div class="gc-av"><i class="fa fa-users"></i></div>'
            + '<div style="flex:1"><div class="gc-name">'+miaEsc(g.name || 'Grupo')+'</div><div class="gc-meta">'+Number(g.member_count || 0)+' membros</div></div>'
            + '</label>';
        }).join('');
        groupsStep3.querySelectorAll('input[name="mia-target-groups"]').forEach(function(input){
          input.addEventListener('change', function(){
            const label = input.closest('label');
            if (label) label.classList.toggle('sel', !!input.checked);
            updateMiaCharCount();
          });
        });
      }
    }

    miaRenderSideGroups(displayGroupsNorm);
    miaRenderQuotaList(displayGroupsNorm, plan);
    miaRenderGroupRules(allGroupsNorm);
    miaUpdateStatusSummaryFromGroups(allGroupsNorm);
    updateMiaCharCount();
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao carregar grupos', 'error');
  });
}

function miaSyncGroups(){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o para sincronizar grupos.', 'warning'); return; }
  if (miaGroupsSyncLoading) return;
  miaSetGroupsSyncLoading(true, {
    title: 'Sincronizando grupos com o WhatsApp...',
    sub: 'A busca pode levar atÃ© 1 minuto. Aguarde enquanto os grupos sÃ£o localizados.'
  });
  showMiaToast('Sincronizando grupos...', 'info');
  miaApi('POST', MIA_API.groups + '?action=sync', {}).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha no sync');
    showMiaToast(resp.message || 'SincronizaÃ§Ã£o concluÃ­da.', 'success');
  }).catch(function(err){
    showMiaToast(err.message || 'Erro no sync', 'error');
  }).then(function(){
    return miaLoadGroups();
  }).finally(function(){
    miaSetGroupsSyncLoading(false);
  });
}

function miaUpdateCampaignStatus(campaignId, status){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o.', 'warning'); return; }
  miaApi('PATCH', MIA_API.campaigns, { id: campaignId, status: status }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao atualizar campanha');
    showMiaToast(resp.message || 'Campanha atualizada', 'success');
    miaLoadCampaigns();
  }).catch(function(err){ showMiaToast(err.message || 'Erro', 'error'); });
}

function miaDeleteCampaign(campaignId){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o para excluir.', 'warning'); return; }
  const campaign = miaCampaignMap[campaignId];
  const title = campaign ? (campaign.title || 'esta campanha') : 'esta campanha';
  
  const modal = document.getElementById('mia-ov-confirm-del');
  const titleEl = document.getElementById('mia-confirm-del-title');
  const textEl = document.getElementById('mia-confirm-del-text');
  const btn = document.getElementById('mia-confirm-del-btn');
  
  if (titleEl) titleEl.textContent = 'Excluir Campanha?';
  if (textEl) textEl.textContent = 'Deseja realmente excluir "' + title + '"? Esta aÃ§Ã£o removerÃ¡ o registro e todo o histÃ³rico de disparos vinculado.';
  
  if (btn) {
    btn.onclick = function(){
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Excluindo...';
      
      miaApi('DELETE', MIA_API.campaigns + '?id=' + campaignId).then(function(resp){
        if (resp.error) throw new Error(resp.message || 'Falha ao excluir');
        showMiaToast(resp.message || 'Campanha excluÃ­da com sucesso.', 'success');
        fecharMiaModal('confirm-del');
        miaLoadCampaigns();
      }).catch(function(err){
        showMiaToast(err.message || 'Erro ao excluir', 'error');
      }).finally(function(){
        btn.disabled = false;
        btn.innerHTML = 'Excluir';
      });
    };
  }
  
  abrirMiaModal('confirm-del');
}

function miaSendNow(campaignId){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o.', 'warning'); return; }
  
  // Bloqueio de ConcorrÃªncia
  const lockKey = 'send_campaign_now_' + Number(campaignId || 0);
  if (!miaAcquireDispatchLock('campaign', lockKey)) return;
  
  const row = document.querySelector('.brow[onclick*="miaOpenCampaign('+campaignId+')"]');
  const btn = row ? row.querySelector('.icon-btn.fire') : null;
  let oldHtml = '';
  if (btn) {
    oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    btn.title = 'Enviando...';
  }

  miaApi('POST', MIA_API.campaigns + '?action=send_now', { campaign_id: campaignId }).then(function(resp){
    if (resp.error) {
        // Se o erro for de conflito (5 min), a mensagem virÃ¡ do backend
        throw new Error(resp.message || 'Falha no disparo');
    }
    showMiaToast(resp.message || 'Disparo solicitado.', 'success');
    miaLoadCampaigns();
    miaScheduleDispatchSync(900);
  }).catch(function(err){ 
    showMiaToast(err.message || 'Erro ao disparar', 'error'); 
  }).finally(function(){
    miaReleaseDispatchLock('campaign', lockKey);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = oldHtml;
      btn.title = 'Disparar agora';
    }
  });
}

function miaOpenCampaign(campaignId){
  miaCurrentCampaignId = campaignId;
  
  // Limpa o conteÃºdo anterior e mostra um loading se necessÃ¡rio
  const content = document.getElementById('mia-drawer-content');
  if (content) content.innerHTML = '<div style="padding:40px;text-align:center;color:#7c3aed"><i class="fa fa-spinner fa-spin fa-2x"></i><div style="margin-top:10px;font-weight:700">Carregando detalhes...</div></div>';

  miaApi('GET', MIA_API.campaigns + '?id=' + Number(campaignId)).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao carregar campanha');
    const c = (resp.data || {}).campaign || null;
    if (!c) return;
    miaCampaignMap[Number(c.id)] = c;

    // Se tiver produto vinculado, busca os detalhes do produto primeiro
    if (Number(c.product_id || 0) > 0) {
        return miaApi('GET', MIA_API.products + '?id=' + Number(c.product_id)).then(function(pResp){
            return { campaign: c, product: (pResp.data || {}).items ? pResp.data.items[0] : null };
        });
    }
    return { campaign: c, product: null };
  }).then(function(data){
    const c = data.campaign;
    const p = data.product;
    const ov = document.getElementById('mia-drawer-ov');
    const dw = document.getElementById('mia-drawer');
    const content = document.getElementById('mia-drawer-content');
    
    const targets = (c.targets || []).map(function(t){
      return '<span class="gchip">'+miaEsc(t.group_name || ('Grupo #' + t.group_id))+'</span>';
    }).join(' ');
    
    const dt = miaFmtDateTime(c.scheduled_at || c.created_at);
    const mediaUrls = Array.isArray(c.media_urls) ? c.media_urls.slice(0, 4) : [];
    const mediaPreview = mediaUrls.length
      ? '<div class="wpp-carousel">' + mediaUrls.map(function(url){
          return '<div class="wpp-carousel-thumb sel"><img src="'+miaEsc(url)+'" style="width:100%;height:100%;object-fit:cover;border-radius:5px"></div>';
        }).join('') + '</div>'
      : '<div class="wpp-carousel"><div class="wpp-carousel-thumb sel"><i class="fa fa-image" style="font-size:18px;color:#94a3b8"></i></div></div>';
    
    const badgeClass = c.status === 'sent' ? 'sent' : (c.status === 'scheduled' ? 'scheduled' : (c.status === 'sending' ? 'sending' : 'draft'));
    const badgeText = c.status === 'scheduled' ? 'Agendado' : (c.status === 'sending' ? 'Enviando' : (c.status === 'sent' ? 'Enviado' : 'Rascunho'));
    
    let productHtml = '';
    if (p) {
        const pPrice = Number(p.price || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        const pMedia = Array.isArray(p.media_urls) ? p.media_urls.slice(0, 8) : [];
        const pMeta = p.metadata || {};
        const pSizes = Array.isArray(pMeta.sizes) ? pMeta.sizes.join(', ') : '';
        const pColors = Array.isArray(pMeta.colors) ? pMeta.colors.join(', ') : '';
        const pPhotoCount = parseInt(pMeta.photo_count || 0);
        
        let metaParts = [];
        if (pPhotoCount > 0) metaParts.push(pPhotoCount + ' foto' + (pPhotoCount > 1 ? 's' : ''));
        if (pSizes !== '') metaParts.push(pSizes);
        if (pColors !== '') metaParts.push(pColors);
        const metaText = metaParts.join(' Â· ');

        productHtml = '<div class="mia-d-sec">'
            + '<div class="mia-d-sec-title"><i class="fa fa-shopping-bag"></i> InformaÃ§Ãµes do Produto</div>'
            + '<div class="mia-product-info">'
                + '<div class="mia-product-image"><img src="'+miaEsc(p.image || '')+'"></div>'
                + '<div class="mia-product-details">'
                    + '<div class="mia-product-name">'+miaEsc(p.name || 'Produto')+'</div>'
                    + (metaText !== '' ? '<div class="mia-product-meta">'+miaEsc(metaText)+'</div>' : '')
                    + '<div class="mia-product-price-box">'
                        + '<div class="mia-product-price-label">Valor de Venda</div>'
                        + '<div class="mia-product-price-value"><small>R$</small> '+pPrice+'</div>'
                    + '</div>'
                + '</div>'
            + '</div>'
            + '</div>';
    }

    content.innerHTML = '<div class="mia-dh"><div><div class="mia-dh-title"><i class="fa fa-bullhorn"></i> '+miaEsc(c.title || ('Campanha #' + c.id))+'</div><div class="mia-dh-sub"><span class="sbadge '+badgeClass+'">'+miaEsc(badgeText)+'</span><span><i class="fa fa-clock-o"></i> '+miaEsc(dt.day)+' '+miaEsc(dt.time)+'</span></div></div><button class="mia-dh-close" onclick="fecharMiaDrawer()"><i class="fa fa-times"></i></button></div>'
      + '<div class="mia-db">'
      + productHtml
      + '<div class="mia-d-sec"><div class="mia-d-sec-title"><i class="fa fa-whatsapp"></i> Preview da campanha</div>'
      + '<div class="wpp-wrap"><div class="wpp-header-bar"><div class="wpp-h-av">L</div><div><div class="wpp-h-name">'+miaEsc(c.title || 'Campanha')+'</div><div class="wpp-h-status">PrÃ©via da mensagem</div></div></div><div class="wpp-body"><div class="wpp-bubble">'+mediaPreview+'<div class="wpp-msg">'+miaEsc(c.content || '')+'</div><div class="wpp-footer">'+miaEsc(dt.time)+' <i class="fa fa-check"></i></div><div class="wpp-cta"><i class="fa fa-comment-o"></i> Quero ver mais</div></div><div class="wpp-reaction">â¤ï¸ 0 ðŸ”¥ 0</div></div></div></div>'
      + '<div class="mia-d-sec"><div class="mia-d-sec-title"><i class="fa fa-file-text-o"></i> ConteÃºdo</div><div class="mia-d-box">'+miaEsc(c.content || '')+'</div>'
      + '<div style="display:flex;justify-content:flex-end;gap:6px;margin-top:8px">'
        + '<button class="btn btn-secondary btn-sm" onclick="miaEditCampaignContent('+Number(c.id)+')"><i class="fa fa-pencil"></i> Editar</button>'
        + '<button class="btn btn-secondary btn-sm" onclick="miaCopyCampaignContent('+Number(c.id)+')"><i class="fa fa-copy"></i> Copiar</button>'
        + '<button class="btn btn-ai btn-sm" onclick="showMiaToast(\'Use o Novo Disparo para regenerar IA.\',\'info\')"><i class="fa fa-magic"></i> Regenerar IA</button>'
      + '</div></div>'
      + '<div class="mia-d-sec"><div class="mia-d-sec-title"><i class="fa fa-users"></i> Grupos alvo</div><div class="mia-d-box">'+(targets || '<span style="color:#94a3b8">Sem grupos vinculados.</span>')+'</div></div>'
      + '<div class="mia-d-sec"><div class="mia-d-sec-title"><i class="fa fa-line-chart"></i> Resumo</div><div class="mia-perf-grid"><div class="mia-perf-item"><div class="mia-perf-val">'+Number((c.summary||{}).total||0)+'</div><div class="mia-perf-lbl">Total</div></div><div class="mia-perf-item"><div class="mia-perf-val">'+Number((c.summary||{}).sent||0)+'</div><div class="mia-perf-lbl">Enviados</div></div><div class="mia-perf-item"><div class="mia-perf-val">'+Number((c.summary||{}).error||0)+'</div><div class="mia-perf-lbl">Erros</div></div></div></div>'
      + '</div>'
      + '<div class="mia-df">'
      + '<button class="btn btn-secondary btn-sm" onclick="fecharMiaDrawer()">Fechar</button>'
      + (MIA_CAN_MANAGE ? '<button class="btn btn-wpp btn-sm" style="margin-left:auto" onclick="miaSendNow('+Number(c.id)+');fecharMiaDrawer()"><i class="fa fa-bolt"></i> Disparar Agora</button>' : '')
      + '</div>';
    ov.classList.add('open');
    dw.classList.add('open');
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao abrir campanha', 'error');
  });
}

function miaEditCampaignContent(campaignId){
  const c = miaCampaignMap[Number(campaignId)];
  if (!c) { showMiaToast('Campanha nÃ£o carregada.', 'warning'); return; }
  
  document.getElementById('mia-edit-campaign-title').value = c.title || '';
  document.getElementById('mia-edit-campaign-content').value = c.content || '';
  
  // Atualiza contador de caracteres
  updateMiaEditCharCount();
  
  // Garante que o container de emoji comece fechado
  const p = document.getElementById('mia-edit-emoji-container');
  if (p) p.style.display = 'none';

  abrirMiaModal('edit-campaign');
}

function formatMiaEditMsg(symbol){
  const msg = document.getElementById('mia-edit-campaign-content');
  const start = msg.selectionStart;
  const end = msg.selectionEnd;
  const text = msg.value;
  const selectedText = text.substring(start, end);
  const before = text.substring(0, start);
  const after = text.substring(end);
  
  if(selectedText){
    msg.value = before + symbol + selectedText + symbol + after;
    msg.selectionStart = start;
    msg.selectionEnd = end + (symbol.length * 2);
  } else {
    msg.value = before + symbol + symbol + after;
    msg.selectionStart = start + symbol.length;
    msg.selectionEnd = start + symbol.length;
  }
  msg.focus();
  updateMiaEditCharCount();
}

function toggleMiaEditEmoji(){
  const p = document.getElementById('mia-edit-emoji-container');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
  
  if(p.style.display === 'block'){
    const picker = p.querySelector('emoji-picker');
    if(picker && !picker.hasListener){
      picker.addEventListener('emoji-click', event => {
        addMiaEditEmoji(event.detail.unicode);
      });
      picker.hasListener = true;
    }
  }
}

function addMiaEditEmoji(emoji){
  const msg = document.getElementById('mia-edit-campaign-content');
  const start = msg.selectionStart;
  const text = msg.value;
  msg.value = text.substring(0, start) + emoji + text.substring(start);
  msg.selectionStart = msg.selectionEnd = start + emoji.length;
  msg.focus();
  updateMiaEditCharCount();
  document.getElementById('mia-edit-emoji-container').style.display = 'none';
}

function updateMiaEditCharCount(){
  const msg = document.getElementById('mia-edit-campaign-content');
  const count = document.getElementById('mia-edit-char-count');
  if(msg && count){
    count.textContent = msg.value.length + ' chars';
  }
}

function miaSaveCampaignEdit(){
  const campaignId = miaCurrentCampaignId;
  if (!campaignId) return;
  
  const title = document.getElementById('mia-edit-campaign-title').value.trim();
  const content = document.getElementById('mia-edit-campaign-content').value.trim();
  
  if (!content) { showMiaToast('O conteÃºdo nÃ£o pode estar vazio.', 'warning'); return; }
  
  const btn = document.getElementById('mia-edit-campaign-save-btn');
  const oldHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';
  
  miaApi('PATCH', MIA_API.campaigns, {
    id: campaignId,
    title: title,
    content: content
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao salvar');
    showMiaToast('Campanha atualizada com sucesso!', 'success');
    fecharMiaModal('edit-campaign');
    
    // Recarrega os detalhes no drawer
    miaOpenCampaign(campaignId);
    
    // Atualiza a lista principal
    miaLoadCampaigns();
  }).catch(function(err){
    showMiaToast(err.message, 'error');
  }).finally(function(){
    btn.disabled = false;
    btn.innerHTML = oldHtml;
  });
}

function miaCopyCampaignContent(campaignId){
  const campaign = miaCampaignMap[Number(campaignId)] || null;
  const text = campaign && campaign.content ? String(campaign.content) : '';
  if (!text) {
    showMiaToast('Sem conteÃºdo para copiar.', 'warning');
    return;
  }
  const copyPromise = navigator.clipboard && navigator.clipboard.writeText
    ? navigator.clipboard.writeText(text)
    : Promise.reject(new Error('Clipboard indisponÃ­vel.'));
  copyPromise.then(function(){
    showMiaToast('ConteÃºdo copiado.', 'success');
  }).catch(function(){
    showMiaToast('NÃ£o foi possÃ­vel copiar automaticamente.', 'warning');
  });
}

function miaOpenGroup(groupId, forceEdit){
  const g = miaGroupsMap[groupId];
  if (!g) return;
  const ov = document.getElementById('mia-drawer-ov');
  const dw = document.getElementById('mia-drawer');
  const content = document.getElementById('mia-drawer-content');
  if (!ov || !dw || !content) return;
  miaCurrentGroupId = Number(groupId);
  const progress = Math.max(0, Math.min(100, Number(g.progress_pct || 0)));
  const completed = Number(g.completed_campaigns || 0);
  const scheduled = Number(g.scheduled_campaigns || 0);
  const nextDispatch = g.next_dispatch_at ? miaFmtDateTime(g.next_dispatch_at) : { day: 'Sem previsÃ£o', time: '--:--' };
  const intervalMinutes = miaGetGroupDispatchInterval(g);
  content.innerHTML = '<div class="mia-dh"><div><div class="mia-dh-title"><i class="fa fa-users"></i> '+miaEsc(g.name || 'Grupo')+'</div><div class="mia-dh-sub"><span><i class="fa fa-users"></i> '+Number(g.member_count || 0)+' membros</span></div></div><button class="mia-dh-close" onclick="fecharMiaDrawer()"><i class="fa fa-times"></i></button></div>'
    + '<div class="mia-db">'
    + '<div class="mia-d-sec"><div class="mia-d-sec-title"><i class="fa fa-line-chart"></i> Performance</div><div class="mia-d-box">'
      + '<div style="font-size:12px;font-weight:700;color:#334155">Realizados: '+completed+'</div>'
      + '<div style="font-size:11px;color:#64748b;margin-top:2px">Agendados: '+scheduled+' Â· Progresso: '+progress+'%</div>'
      + '<div class="group-mini-progress"><span style="width:'+progress+'%"></span></div>'
      + '<div style="margin-top:10px" class="group-next">'+miaEsc(nextDispatch.day)+'</div>'
      + '<div class="group-next-sub"><i class="fa fa-clock-o"></i> '+miaEsc(nextDispatch.time)+'</div>'
    + '</div></div>'
    + '<div class="mia-d-sec"><div class="mia-d-sec-title"><i class="fa fa-sliders"></i> ConfiguraÃ§Ã£o do Grupo</div><div class="mia-d-box">'
      + '<div class="fg"><label>Quantidade de produtos por dia</label><input class="finput" type="number" min="0" id="mia-group-edit-daily-limit" value="'+Number(g.daily_limit || 0)+'"></div>'
      + '<div class="fg" style="margin-bottom:0"><label>Intervalo entre disparos (min)</label><input class="finput" type="number" min="0" id="mia-group-edit-interval" value="'+intervalMinutes+'"><div style="font-size:10px;color:#94a3b8;margin-top:4px">0 = sem intervalo.</div></div>'
    + '</div></div>'
    + '</div>'
    + '<div class="mia-df">'
      + '<button class="btn btn-secondary btn-sm" onclick="fecharMiaDrawer()">Fechar</button>'
      + (MIA_CAN_MANAGE ? '<button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="miaSaveGroupDrawer()"><i class="fa fa-check"></i> Salvar ediÃ§Ã£o</button>' : '')
    + '</div>';
  ov.classList.add('open');
  dw.classList.add('open');
  if (forceEdit) {
    setTimeout(function(){
      const input = document.getElementById('mia-group-edit-daily-limit');
      if (input) input.focus();
    }, 90);
  }
}
function miaSaveGroupDrawer(){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o para editar grupo.', 'warning'); return; }
  const groupId = Number(miaCurrentGroupId || 0);
  if (groupId <= 0) { showMiaToast('Grupo invÃ¡lido.', 'error'); return; }
  const g = miaGroupsMap[groupId] || {};
  const dailyInput = document.getElementById('mia-group-edit-daily-limit');
  const intervalInput = document.getElementById('mia-group-edit-interval');
  const dailyLimit = Math.max(0, Number(dailyInput ? dailyInput.value : 0) || 0);
  const intervalMinutes = Math.max(0, Number(intervalInput ? intervalInput.value : 0) || 0);
  const existingSettings = miaParseGroupSettings(g.settings_json);
  const settings = {};
  if (typeof existingSettings === 'object' && existingSettings !== null) {
    for (const key in existingSettings) {
      if (existingSettings.hasOwnProperty(key)) {
        settings[key] = existingSettings[key];
      }
    }
  }
  settings.dispatch_interval_minutes = intervalMinutes;
  settings.interval_between_dispatches = intervalMinutes;

  miaApi('PATCH', MIA_API.groups, {
    id: groupId,
    daily_limit: dailyLimit,
    settings_json: settings
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao salvar grupo.');
    showMiaToast('Grupo atualizado com sucesso.', 'success');
    fecharMiaDrawer();
    miaLoadGroups();
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao salvar grupo.', 'error');
  });
}

function miaToggleGroup(groupId){
  const g = miaGroupsMap[groupId];
  if (!g || !MIA_CAN_MANAGE) return;
  miaApi('PATCH', MIA_API.groups, { id: Number(groupId), is_active: Number(g.is_active || 0) === 1 ? 0 : 1 }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao atualizar grupo');
    showMiaToast('Grupo atualizado.', 'success');
    if (Number(miaCurrentGroupId || 0) === Number(groupId)) {
      fecharMiaDrawer();
    }
    miaLoadGroups();
  }).catch(function(err){ showMiaToast(err.message || 'Erro ao atualizar grupo', 'error'); });
}

function miaLeaveGroup(groupId){
  const g = miaGroupsMap[groupId];
  if (!g || !MIA_CAN_MANAGE) return;
  const ok = window.confirm('Deseja sair do grupo "' + (g.name || 'Grupo') + '" na instÃ¢ncia?');
  if (!ok) return;
  miaApi('POST', MIA_API.groups + '?action=leave', { group_id: Number(groupId) }).then(function(resp){
    if (resp.error) {
      const msg = String(resp.message || '');
      const softError = /nÃ£o encontrado|not.*found|participant|nÃ£o.*participante/i.test(msg);
      if (!softError) throw new Error(msg || 'Falha ao sair do grupo.');
      showMiaToast('Grupo jÃ¡ nÃ£o estava disponÃ­vel na instÃ¢ncia. Atualizando lista...', 'warning');
    } else {
      showMiaToast(resp.message || 'Grupo removido da instÃ¢ncia.', 'success');
    }
    if (Number(miaCurrentGroupId || 0) === Number(groupId)) {
      fecharMiaDrawer();
    }
    miaLoadGroups();
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao sair do grupo', 'error');
  });
}

function miaSaveGroupsModal(){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o para salvar.', 'warning'); return; }
  
  const ovGroups = document.getElementById('mia-ov-grupos');
  const ovIA = document.getElementById('mia-ov-ia-grupos-automacao');
  const isGroupsOpen = !!(ovGroups && !ovGroups.classList.contains('hide'));
  const isIAOpen = !!(ovIA && !ovIA.classList.contains('hide'));
  // 1. Salva configuraÃ§Ãµes globais
  // Usamos apenas o modal ativo para refletir exatamente a seleÃ§Ã£o visÃ­vel ao usuÃ¡rio.
  const catSet = new Set();
  const catSelector = isGroupsOpen
    ? '#mia-group-cats-grid input[name="mia-cat"]:checked'
    : (isIAOpen ? '#mia-ia-cats-grid input[name="mia-cat"]:checked' : '#mia-group-cats-grid input[name="mia-cat"]:checked');
  document.querySelectorAll(catSelector).forEach(function(el){
    catSet.add(el.value);
  });
  const selectedCats = Array.from(catSet);
  
  const globalData = {
    mia_group_delay: document.getElementById('mia-group-delay').value,
    mia_group_global_limit: document.getElementById('mia-group-global-limit').value,
    mia_allowed_categories: selectedCats
  };
  
  const globalUpdate = miaApi('PATCH', MIA_API.groups, globalData);

  // 2. Salva status dos grupos
  const checks = isGroupsOpen
    ? Array.prototype.slice.call(document.querySelectorAll('#mia-ov-grupos input[type=checkbox][data-group-id]'))
    : [];
  const groupUpdates = checks.map(function(chk){
    return miaApi('PATCH', MIA_API.groups, { id: Number(chk.getAttribute('data-group-id')), is_active: chk.checked ? 1 : 0 });
  });

  Promise.all([globalUpdate].concat(groupUpdates)).then(function(){
    showMiaToast('ConfiguraÃ§Ãµes salvas com sucesso!', 'success');
    if (isGroupsOpen) fecharMiaModal('grupos');
    if (isIAOpen) fecharMiaModal('ia-grupos-automacao');
    miaLoadGroups();
  }).catch(function(){ showMiaToast('Erro ao salvar configuraÃ§Ãµes.', 'error'); });
}

function miaSaveIAConfig(){
  miaSaveGroupsModal();
}
function miaPrefillManualStatusDate(){
  const dateInput = document.getElementById('mia-manual-status-date');
  const timeInput = document.getElementById('mia-manual-status-time');
  const now = new Date();
  const plus = new Date(now.getTime() + (20 * 60 * 1000));
  if (dateInput && !dateInput.value) {
    dateInput.value = plus.toISOString().slice(0, 10);
  }
  if (timeInput && !timeInput.value) {
    timeInput.value = plus.toTimeString().slice(0, 5);
  }
}

function miaUpdateStatusSummaryCounts(scheduled, done){
  const schedEl = document.getElementById('mia-manual-sched-count');
  const doneEl = document.getElementById('mia-manual-done-count');
  const progressEl = document.getElementById('mia-manual-progress');
  const total = Math.max(0, Number(scheduled || 0) + Number(done || 0));
  const pct = total > 0 ? Math.round((Number(done || 0) / total) * 100) : 0;
  if (schedEl) schedEl.textContent = String(Number(scheduled || 0));
  if (doneEl) doneEl.textContent = String(Number(done || 0));
  if (progressEl) progressEl.textContent = String(pct) + '%';
}

function miaUpdateStatusSummaryFromGroups(groups){
  const list = Array.isArray(groups) ? groups : [];
  const scheduled = list.reduce(function(acc, g){ return acc + Number(g.scheduled_campaigns || 0); }, 0);
  const done = list.reduce(function(acc, g){ return acc + Number(g.completed_campaigns || 0); }, 0);
  const schedEl = document.getElementById('mia-manual-sched-count');
  const doneEl = document.getElementById('mia-manual-done-count');
  if (schedEl) schedEl.dataset.groupsScheduled = String(scheduled);
  if (doneEl) doneEl.dataset.groupsDone = String(done);
  miaUpdateStatusSummaryCounts(scheduled, done);
}

function miaUpdateStatusSidebarKpis(items){
  const list = Array.isArray(items) ? items : [];
  const d = new Date();
  const today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  
  // Posts hoje (verificando no histÃ³rico de sucesso para precisÃ£o em itens recorrentes)
  let postsHoje = 0;
  list.forEach(function(s){
    const history = miaParseGroupSettings(s.success_history_json || '{}');
    // Normaliza chaves do histÃ³rico
    const normHistory = {};
    Object.keys(history).forEach(k => normHistory[k.trim()] = history[k]);
    
    if (normHistory[today] === 'sent') {
      postsHoje++;
    } else if (s.status === 'sent' && s.sent_at && s.sent_at.slice(0, 10) === today) {
      // Fallback para disparos Ãºnicos que nÃ£o geram histÃ³rico JSON
      postsHoje++;
    }
  });
  
  // Postagens restantes para hoje
  const faltamHoje = Math.max(0, miaStatusAutoTarget - postsHoje);
  
  // RepetiÃ§Ã£o total (soma de post_count de todos os itens do histÃ³rico)
  const totalRep = list.reduce(function(acc, s){ return acc + Number(s.post_count || 0); }, 0);

  const kpiToday = document.getElementById('mia-status-kpi-today');
  const kpiProd = document.getElementById('mia-status-kpi-products');
  const kpiRep = document.getElementById('mia-status-kpi-repeat');
  const lastPostBox = document.getElementById('mia-status-last-post');

  // Pega o valor do seletor de repetiÃ§Ã£o para o KPI
  const repEl = document.getElementById('mia-status-auto-rep');
  const currentRep = repEl ? repEl.value : '--';

  if (kpiToday) kpiToday.textContent = String(postsHoje);
  if (kpiProd) kpiProd.textContent = String(faltamHoje);
  if (kpiRep) kpiRep.textContent = String(currentRep) + 'x';

  if (lastPostBox) {
    const lastSent = list.filter(function(s){ 
      const history = miaParseGroupSettings(s.success_history_json || '{}');
      const hasHistory = Object.values(history).indexOf('sent') !== -1;
      return s.status === 'sent' || hasHistory; 
    }).sort(function(a,b){
       return new Date(b.sent_at || b.updated_at || 0) - new Date(a.sent_at || a.updated_at || 0);
    })[0];

    if (lastSent) {
      const dt = miaFmtDateTime(lastSent.sent_at || lastSent.updated_at);
      const mediaUrls = Array.isArray(lastSent.media_urls) ? lastSent.media_urls : [];
      const thumb = lastSent.media_url || mediaUrls[0] || '';
      lastPostBox.innerHTML = '<div style="font-size:11px;color:#64748b"><i class="fa fa-history"></i> Ãšltimo status:</div>'
        + '<div style="display:flex;gap:8px;margin-top:5px;align-items:center">'
          + '<div style="width:32px;height:32px;border-radius:6px;overflow:hidden;background:#f1f5f9;border:1px solid #e9d5ff;flex-shrink:0">'
            + (thumb ? '<img src="'+miaEsc(thumb)+'" style="width:100%;height:100%;object-fit:cover">' : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:14px">ðŸŸ£</div>')
          + '</div>'
          + '<div style="flex:1;min-width:0">'
            + '<div style="font-size:11px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">#'+Number(lastSent.id)+'</div>'
            + '<div style="font-size:10px;color:#94a3b8">'+dt.day+' '+dt.time+'</div>'
          + '</div>'
        + '</div>';
    } else {
      lastPostBox.innerHTML = '<div style="font-size:11px;color:#64748b"><i class="fa fa-history"></i> Ãšltimo status:</div><div style="font-size:11.5px;margin-top:2px;color:#94a3b8">Nenhuma postagem recente.</div>';
    }
  }
}

function miaRenderStatusHistoryItem(s){
  const dt = miaFmtDateTime(s.scheduled_at || s.created_at);
  let isSending = String(s.status || '') === 'sending';
  const isPending = String(s.status || '') === 'pending';
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  
  let hasTimeout = false;
  if (isSending && s.updated_at) {
    const lastUpdate = new Date(s.updated_at.replace(' ', 'T')).getTime();
    const now = new Date().getTime();
    if (now - lastUpdate > 120000) { 
      hasTimeout = true;
      isSending = false;
    }
  }

  let badge = s.status === 'sent' ? 'sent' : (s.status === 'error' ? 'draft' : (isSending ? 'sending' : 'scheduled'));
  let label = s.status === 'sent' ? 'Sucesso' : (s.status === 'error' ? 'Erro' : (isSending ? 'Enviando...' : (s.status === 'canceled' ? 'Cancelado' : 'Pendente')));
  
  if (hasTimeout) {
    badge = 'draft';
    label = 'Sem resposta';
  }

  const mediaUrls = Array.isArray(s.media_urls) ? s.media_urls : [];
  const thumb = s.media_url || mediaUrls[0] || '';
  
  return '<div class="brow" id="status-item-'+(s.id || 'temp')+'" style="grid-template-columns: 36px 1fr 100px 80px 40px; padding: 10px; border-bottom: 1px solid #f1f5f9; cursor: default; align-items:center; opacity: '+(isSending ? '0.7' : '1')+'">'
    + '<div class="brow-thumb" style="width:30px;height:30px;min-width:30px;margin-right:0">'+(thumb ? ('<img src="'+miaEsc(thumb)+'" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:5px" onerror="this.src=\''+fallback+'\'">') : 'ðŸŸ£')+'</div>'
    + '<div style="font-size:11.5px; color:#334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-left:5px" title="'+miaEsc(s.content || '')+'">'
      + '<div style="font-weight:700">#'+(s.id ? Number(s.id) : '...')+'</div>'
      + miaEsc(s.content || 'Sem legenda')
    + '</div>'
    + '<div style="font-size:10px; color:#94a3b8">'+dt.day+' '+dt.time+'</div>'
    + '<div style="display:flex; flex-direction:column; align-items:center">'
      + '<span class="sbadge '+badge+'" style="font-size:9px; padding:2px 6px">'+label+'</span>'
      + (isSending ? '<div class="status-progress-container" style="height:3px; width:100%; margin-top:4px"><div class="status-progress-bar loading"></div></div>' : '')
    + '</div>'
    + '<div style="display:flex; gap:4px; justify-content: flex-end">' 
      + (isPending ? '<button class="icon-btn fire" title="Disparar agora" onclick="miaSendStatusNow('+s.id+')" style="width:22px;height:22px"><i class="fa fa-bolt" style="font-size:10px"></i></button>' : '') 
      + (s.id ? '<button class="icon-btn danger" title="Excluir" onclick="miaDeleteStatus('+s.id+')" style="width:22px;height:22px"><i class="fa fa-trash" style="font-size:10px"></i></button>' : '') 
    + '</div>'
  + '</div>';
}

function miaCreateManualStatus(sendNow){
  const captionEl = document.getElementById('mia-manual-status-caption');
  const mediaEl = document.getElementById('mia-manual-status-media');
  const dateEl = document.getElementById('mia-manual-status-date');
  const timeEl = document.getElementById('mia-manual-status-time');
  const repEl = document.getElementById('mia-manual-status-rep');
  
  const selectedDays = Array.prototype.slice.call(document.querySelectorAll('#mia-status-days .day-btn.active')).map(function(b){ return b.dataset.day; });

  const caption = captionEl ? captionEl.value.trim() : '';
  const mediaUrls = miaParseManualStatusMediaInput();
  
  if (mediaUrls.length > 1) {
     window.alert('AtenÃ§Ã£o: Para postagens de Status, vocÃª deve selecionar apenas 1 item (imagem ou vÃ­deo). Por favor, remova as mÃ­dias excedentes antes de prosseguir.');
     return;
   }

  const date = dateEl ? dateEl.value : '';
  const time = timeEl ? timeEl.value : '';
  const rep = repEl ? parseInt(repEl.value) || 1 : 1;
  
  if (miaIsStatusSending && sendNow) {
    const ok = window.confirm('Uma postagem estÃ¡ aguardando confirmaÃ§Ã£o. Deseja realmente carregar outra agora?');
    if (!ok) return;
  }

  if (!mediaUrls.length && !caption) {
    showMiaToast('Informe ao menos uma legenda ou uma mÃ­dia para o status.', 'warning');
    return;
  }
  if (!sendNow && (!date || !time)) {
    showMiaToast('Informe data e horÃ¡rio para agendar.', 'warning');
    return;
  }
  const lockKey = sendNow ? 'manual_status_now' : 'manual_status_schedule';
  if (!miaAcquireDispatchLock('status', lockKey)) return;
  
  const btn = event && event.currentTarget && event.currentTarget.tagName === 'BUTTON' ? event.currentTarget : null;
  let oldHtml = '';
  if (btn) {
    oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
  }

  const scheduledAt = sendNow ? '' : (date + ' ' + time);
  const payload = {
    content: caption,
    media_url: mediaUrls[0] || '',
    media_urls: mediaUrls,
    scheduled_at: scheduledAt,
    status: sendNow ? 'sending' : 'pending',
    repeat_count: rep,
    repeat_days: selectedDays.join(','),
    payload_json: {
      media_urls: mediaUrls,
      scheduled_at: scheduledAt,
      source: 'manual_status',
      send_now: sendNow ? 1 : 0,
      allContacts: true,
      statusJidList: [],
      repeat_count: rep,
      repeat_days: selectedDays.join(',')
    }
  };

  if (sendNow) {
    miaIsStatusSending = true;
    // AdiÃ§Ã£o otimista ao histÃ³rico
    const list = document.getElementById('mia-status-history-list');
    if (list) {
      const tempItem = {
        id: 0,
        content: caption,
        status: 'sending',
        created_at: new Date().toISOString(),
        media_urls: mediaUrls
      };
      const currentHtml = list.innerHTML;
      if (currentHtml.indexOf('Nenhuma postagem') !== -1) {
        list.innerHTML = miaRenderStatusHistoryItem(tempItem);
      } else {
        list.innerHTML = miaRenderStatusHistoryItem(tempItem) + currentHtml;
      }
    }
  }
  
  const url = MIA_API.status + (sendNow ? '?action=send_now' : '');
  
  miaApi('POST', url, payload).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao processar status manual.');
    showMiaToast(resp.message || (sendNow ? 'Status enviado!' : 'Status manual agendado.'), 'success');
    if (captionEl) captionEl.value = '';
    if (mediaEl) mediaEl.value = '';
    miaRenderManualStatusPreview();
    miaLoadStatusHistory();
    if (miaCurrentView === 'status') miaLoadStatuses();
    if (sendNow) fecharMiaModal('status-automacao');
    miaScheduleDispatchSync(sendNow ? 900 : 1200);
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao processar status manual.', 'error');
    miaLoadStatusHistory(); // Recarrega para remover o item otimista se falhou
  }).finally(function(){
    miaReleaseDispatchLock('status', lockKey);
    if (sendNow) miaIsStatusSending = false;
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = oldHtml;
    }
  });
}

function miaLoadStatusHistory(){
  const list = document.getElementById('mia-status-history-list');
  const btn = document.querySelector('button[onclick="miaLoadStatusHistory()"]');
  const clearBtn = document.getElementById('btn-clear-status-history');
  if(!list) return;
  
  if (btn) {
    btn.disabled = true;
    const icon = btn.querySelector('i');
    if (icon) icon.classList.add('fa-spin');
  }

  if (!list.innerHTML || list.innerHTML.indexOf('fa-spinner') !== -1) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Carregando...</div>';
  }

  miaApi('GET', MIA_API.status + '?page=1&limit=50').then(function(resp){
    if(resp.error) throw new Error(resp.message);
    const items = (resp.data || {}).items || [];
    const scheduledByStatus = items.filter(function(s){ return String(s.status || '') === 'pending' && String(s.scheduled_at || '') !== ''; }).length;
    const doneByStatus = items.filter(function(s){ return String(s.status || '') === 'sent'; }).length;
    const schedEl = document.getElementById('mia-manual-sched-count');
    const doneEl = document.getElementById('mia-manual-done-count');
    const groupSched = Number((schedEl && schedEl.dataset.groupsScheduled) || 0);
    const groupDone = Number((doneEl && doneEl.dataset.groupsDone) || 0);
    miaUpdateStatusSummaryCounts(groupSched + scheduledByStatus, groupDone + doneByStatus);
    miaUpdateStatusSidebarKpis(items);
    
    // Mostrar/ocultar botÃ£o de deletar todo histÃ³rico
    if (clearBtn) {
      clearBtn.style.display = items.length > 0 ? 'flex' : 'none';
    }
    
    if(!items.length){
      list.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:11px">Nenhuma postagem realizada pela automaÃ§Ã£o.</div>';
      return;
    }
    list.innerHTML = items.map(miaRenderStatusHistoryItem).join('');
  }).catch(function(err){
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#ef4444;font-size:11px">Erro ao carregar histÃ³rico.</div>';
  }).finally(function(){
    if (btn) {
      btn.disabled = false;
      const icon = btn.querySelector('i');
      if (icon) icon.classList.remove('fa-spin');
    }
  });
}

function confirmClearStatusHistory(){
  abrirMiaModal('clear-status-history');
}

function clearAllStatusHistory(){
  const list = document.getElementById('mia-status-history-list');
  const clearBtn = document.getElementById('btn-clear-status-history');
  if(!list) return;
  
  list.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Deletando...</div>';
  
  miaApi('DELETE', MIA_API.status + '?action=clear_history').then(function(resp){
    if(resp.error) throw new Error(resp.message);
    showMiaToast(resp.message || 'HistÃ³rico deletado com sucesso!', 'success');
    miaLoadStatusHistory();
    miaUpdateStatusSummaryCounts(0, 0);
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao deletar histÃ³rico.', 'error');
    miaLoadStatusHistory();
  });
}

function miaDeleteStatus(id){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permissÃ£o.', 'warning'); return; }
  
  const modal = document.getElementById('mia-ov-confirm-del');
  const btn = document.getElementById('mia-confirm-del-btn');
  const titleEl = document.getElementById('mia-confirm-del-title');
  const textEl = document.getElementById('mia-confirm-del-text');
  
  if (titleEl) titleEl.textContent = 'Remover Postagem?';
  if (textEl) textEl.textContent = 'Deseja realmente remover esta postagem do histÃ³rico?';
  
  if (btn) {
    btn.onclick = function(){
      btn.disabled = true;
      miaApi('DELETE', MIA_API.status + '?id=' + id).then(function(resp){
        if (resp.error) throw new Error(resp.message);
        showMiaToast('Postagem removida! ðŸ—‘ï¸', 'success');
        fecharMiaModal('confirm-del');
        miaLoadStatusHistory();
        if (miaCurrentView === 'status') miaLoadStatuses();
      }).catch(function(err){
        showMiaToast(err.message || 'Erro ao remover.', 'error');
      }).finally(function(){
        btn.disabled = false;
      });
    };
  }
  abrirMiaModal('confirm-del');
}

function miaSaveStatusAuto(){
  const enabled = document.getElementById('mia-status-auto-enable').checked;
  const count = document.getElementById('mia-status-auto-count').value;
  const rep = document.getElementById('mia-status-auto-rep').value;
  const days = document.getElementById('mia-status-auto-days').value;
  const interval = document.getElementById('mia-status-auto-interval').value;
  
  const payload = {
    mia_status_auto_enable: enabled ? 1 : 0,
    mia_status_auto_count: count,
    mia_status_auto_rep: rep,
    mia_status_auto_days: days,
    mia_status_auto_interval: interval
  };
  
  showMiaToast('Salvando configuraÃ§Ãµes...', 'info');
  miaApi('PATCH', MIA_API.groups, payload).then(function(resp){
    if(resp.error) throw new Error(resp.message);
    showMiaToast('ConfiguraÃ§Ãµes de status salvas! âœ…', 'success');
    // Recarrega todas as configuraÃ§Ãµes e grupos do servidor
    miaLoadGroups();
  }).catch(function(err){
    showMiaToast('Erro ao salvar: ' + err.message, 'error');
  });
}

function miaLoadStatusAutoSettings(){
  // Usa a variÃ¡vel global que jÃ¡ Ã© carregada no miaLoadGroups
  if (!window.miaGlobalSettings) window.miaGlobalSettings = {};
  const autoEnable = document.getElementById('mia-status-auto-enable');
  if (autoEnable) autoEnable.checked = Number(window.miaGlobalSettings.mia_status_auto_enable || 0) === 1;
  const autoCount = document.getElementById('mia-status-auto-count');
  if (autoCount) {
    autoCount.value = window.miaGlobalSettings.mia_status_auto_count !== undefined && window.miaGlobalSettings.mia_status_auto_count !== null ? window.miaGlobalSettings.mia_status_auto_count : 4;
    miaStatusAutoTarget = Number(autoCount.value);
  }
  const autoRep = document.getElementById('mia-status-auto-rep');
  if (autoRep) {
    autoRep.value = window.miaGlobalSettings.mia_status_auto_rep || 3;
  }
  const autoDays = document.getElementById('mia-status-auto-days');
  if (autoDays) {
    autoDays.value = window.miaGlobalSettings.mia_status_auto_days || 3;
  }
  const autoInterval = document.getElementById('mia-status-auto-interval');
  if (autoInterval) {
    autoInterval.value = window.miaGlobalSettings.mia_status_auto_interval || 1;
  }
}

function miaGenerateIntelligentStatusesNow(){
  showMiaToast('Gerando postagens inteligentes...', 'info');
  // Chama o dispatch.php para gerar os status automaticamente
  fetch(MIA_ROOT + 'api/concierge/dispatch.php?action=status&loja_id=' + encodeURIComponent(MIA_TENANT_ID), {
    method: 'GET',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      'X-Concierge-Token': MIA_TOKEN
    }
  })
    .then(r => r.json())
    .then(resp => {
      console.log('Resposta do gerador:', resp);
      if (resp.error) throw new Error(resp.message);
      const statusesData = (resp.data || {}).statuses || {};
      const generationDebug = statusesData.generation_debug || {};
      const countCreated = Number(statusesData.generated || 0);
      if(countCreated > 0){
        if (String(generationDebug.blocked_reason_code || '') === 'partial_generation') {
          showMiaToast(
            'Gerados ' + countCreated + ' posts. Restante bloqueado por elegibilidade do catÃ¡logo.',
            'warning'
          );
        } else {
          showMiaToast('Gerados ' + countCreated + ' postagens inteligentes! ðŸŽ‰', 'success');
        }
        miaLoadStatuses();
        miaLoadStatusHistory();
      } else {
        const reasonMessage = String(generationDebug.blocked_reason_message || '').trim();
        const reasonCode = String(generationDebug.blocked_reason_code || '').trim();
        const extra = [];
        if (Number(generationDebug.eligible_products_count || 0) >= 0) {
          extra.push('ElegÃ­veis: ' + Number(generationDebug.eligible_products_count || 0));
        }
        if (Number(generationDebug.excluded_missing_media_count || 0) > 0) {
          extra.push('Sem mÃ­dia: ' + Number(generationDebug.excluded_missing_media_count || 0));
        }
        if (Number(generationDebug.excluded_recent_count || 0) > 0) {
          extra.push('Reuso bloqueado: ' + Number(generationDebug.excluded_recent_count || 0));
        }

        let fallbackMessage = 'Nenhuma nova postagem foi gerada.';
        if (reasonCode === 'daily_target_reached') {
          fallbackMessage = 'Meta diÃ¡ria jÃ¡ atingida.';
        } else if (reasonCode === 'auto_disabled') {
          fallbackMessage = 'AutomaÃ§Ã£o de status estÃ¡ desativada.';
        } else if (reasonCode === 'no_eligible_products') {
          fallbackMessage = 'Nenhum produto elegÃ­vel para geraÃ§Ã£o automÃ¡tica.';
        } else if (reasonCode === 'internal_error') {
          fallbackMessage = 'Falha interna ao gerar postagens automÃ¡ticas.';
        }

        const fullMessage = (reasonMessage || fallbackMessage) + (extra.length ? ' (' + extra.join(' Â· ') + ')' : '');
        showMiaToast(fullMessage, reasonCode === 'internal_error' ? 'error' : 'warning');
      }
    })
    .catch(err => {
      console.error(err);
      showMiaToast(err.message || 'Erro ao gerar postagens.', 'error');
    });
}

function miaUpdateStatusTime(event, id){
  const date = document.getElementById('mia-edit-status-date').value;
  const time = document.getElementById('mia-edit-status-time').value;
  if (!date || !time) {
    showMiaToast('Selecione data e hora vÃ¡lidas.', 'warning');
    return;
  }
  
  const btn = event ? event.currentTarget : null;
  const oldHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
  }

  // Garante o formato YYYY-MM-DD HH:MM:SS
  const fullScheduledAt = date + ' ' + time + ':00';

  miaApi('PATCH', MIA_API.status, {
    id: id,
    loja_id: MIA_TENANT_ID,
    scheduled_at: fullScheduledAt
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message);
    showMiaToast('HorÃ¡rio atualizado com sucesso!', 'success');
    
    // Pequeno delay para garantir que o banco processou antes do refresh
    setTimeout(function(){
      miaOpenStatusDetails(id);
      miaLoadStatusHistory();
      if (miaCurrentView === 'status') miaLoadStatuses();
      miaScheduleDispatchSync(1200);
    }, 300);
    
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao atualizar horÃ¡rio.', 'error');
  }).finally(function(){
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = oldHtml;
    }
  });
}

function miaOpenStatusDetails(id){
  abrirMiaDrawer();
  const content = document.getElementById('mia-drawer-content');
  if (content) content.innerHTML = '<div style="padding:40px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Carregando detalhes...</div>';

  miaApi('GET', MIA_API.status + '?id=' + id + '&_t=' + new Date().getTime()).then(function(resp){
    if (resp.error) throw new Error(resp.message);
    const s = (resp.data || {}).status;
    if (!s) throw new Error('Status nÃ£o encontrado.');
    const dt = miaFmtDateTime(s.scheduled_at || s.created_at);
    const dtSent = s.sent_at ? miaFmtDateTime(s.sent_at) : dt;
    const mediaUrls = Array.isArray(s.media_urls) ? s.media_urls : [];
    const thumb = s.media_url || mediaUrls[0] || '';
    
    const repeatCount = parseInt(s.repeat_count || 1); // Este Ã© o nÃºmero de CICLOS
    const postCount = parseInt(s.post_count || 0);     // Este Ã© o nÃºmero de SUCESSOS
    const attemptCount = parseInt(s.attempt_count || 0); // Este Ã© o nÃºmero de TENTATIVAS (Sucesso + Falha)
    
    const repeatDays = String(s.repeat_days || '').split(',').filter(d => d !== '').map(Number);
    const daysInWeek = Math.max(1, repeatDays.length);
    const totalScheduled = daysInWeek * repeatCount;
    
    const remaining = Math.max(0, totalScheduled - attemptCount);
    const progress = Math.min(100, (postCount / totalScheduled) * 100);
    
    const isFailed = s.status === 'error';
    const isSent = s.status === 'sent';
    const isPending = s.status === 'pending';
    const isSending = s.status === 'sending';

    let html = '<div class="mia-dh">'
      + '<div><div class="mia-dh-title"><i class="fa fa-circle-o-notch"></i> Detalhes da Postagem</div>'
      + '<div class="mia-dh-sub"><span>ID: #'+Number(s.id)+'</span></div></div>'
      + '<button class="mia-dh-close" onclick="fecharMiaDrawer()"><i class="fa fa-times"></i></button>'
    + '</div>'
    + '<div class="mia-db">'
      + '<div style="display:flex;gap:15px;margin-bottom:20px;align-items:center">'
        + '<div style="width:80px;height:80px;border-radius:12px;overflow:hidden;background:#f1f5f9;border:1px solid #e2e8f0;flex-shrink:0">'
          + (thumb ? '<img src="'+miaEsc(thumb)+'" style="width:100%;height:100%;object-fit:cover">' : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:30px">ðŸŸ£</div>')
        + '</div>'
        + '<div style="flex:1">'
          + '<div style="font-size:16px;font-weight:800;color:#1e293b">#'+Number(s.id)+'</div>'
          + '<div style="font-size:12px;color:#64748b;margin-top:2px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+miaEsc(s.content || '')+'">'+miaEsc(s.content || 'Sem legenda')+'</div>'
          + '<div style="margin-top:6px"><span class="sbadge '+(isSent ? 'sent' : (isFailed ? 'draft' : (isSending ? 'sending' : 'scheduled')))+'">'+(isSent ? 'Sucesso' : (isFailed ? 'Falha no envio' : (isSending ? 'Enviando...' : 'Aguardando')))+'</span></div>'
        + '</div>'
      + '</div>';

      if (isFailed) {
        html += '<div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:12px; padding:12px; margin-bottom:15px; display:flex; align-items:center; gap:10px">'
          + '<div style="width:30px; height:30px; background:#e11d48; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff"><i class="fa fa-exclamation-circle"></i></div>'
          + '<div style="flex:1">'
            + '<div style="font-size:12px; font-weight:700; color:#9f1239">Ocorreu um erro no disparo</div>'
            + '<div style="font-size:11px; color:#be123c">'+miaEsc(s.error_message || 'Erro desconhecido na Evolution API')+'</div>'
          + '</div>'
          + '<button class="btn btn-wpp btn-sm" onclick="miaSendStatusNow('+Number(s.id)+')"><i class="fa fa-bolt"></i> Tentar Novamente</button>'
          + '</div>';
      } else if (isSent) {
        const isCycleFinished = postCount >= totalScheduled;
        const msgTitle = isCycleFinished ? 'Ciclo de Postagens Finalizado!' : 'Postagem realizada com sucesso';
        const msgSub = isCycleFinished ? 'Todas as postagens agendadas foram concluÃ­das.' : ('Confirmado em ' + dtSent.day + ' Ã s ' + dtSent.time);
        const icon = isCycleFinished ? 'fa-trophy' : 'fa-check-circle';
        const bg = isCycleFinished ? '#7c3aed' : '#22c55e';
        const border = isCycleFinished ? '#ddd6fe' : '#bbf7d0';
        const lightBg = isCycleFinished ? '#f5f3ff' : '#f0fdf4';
        const text = isCycleFinished ? '#5b21b6' : '#14532d';

        html += '<div style="background:'+lightBg+'; border:1px solid '+border+'; border-radius:12px; padding:12px; margin-bottom:15px; display:flex; align-items:center; gap:10px">'
          + '<div style="width:30px; height:30px; background:'+bg+'; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff"><i class="fa '+icon+'"></i></div>'
          + '<div style="flex:1">'
            + '<div style="font-size:12px; font-weight:700; color:'+text+'">'+msgTitle+'</div>'
            + '<div style="font-size:11px; color:'+text+'">'+msgSub+'</div>'
          + '</div>'
          + '</div>';
      }

      html += '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:15px;margin-bottom:15px">'
        + '<div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;display:flex;justify-content:space-between"><span>Ciclo de Repostagem</span> <span style="color:#7c3aed">'+repeatCount+' ciclos</span></div>'
        + '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:15px">'
          + '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center"><div style="font-size:18px;font-weight:800;color:#22c55e">'+postCount+'</div><div style="font-size:10px;color:#94a3b8">Sucessos</div></div>'
          + '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center"><div style="font-size:18px;font-weight:800;color:#ef4444">'+remaining+'</div><div style="font-size:10px;color:#94a3b8">Faltam</div></div>'
          + '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center"><div style="font-size:18px;font-weight:800;color:#64748b">'+totalScheduled+'</div><div style="font-size:10px;color:#94a3b8">Total Geral</div></div>'
        + '</div>'
        + '<div style="height:8px;background:#e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:8px">'
          + '<div style="height:100%;background:linear-gradient(90deg,#22c55e,#4ade80);width:'+progress+'%;border-radius:10px;transition:width 0.5s ease"></div>'
        + '</div>'
        + '<div style="font-size:11px;color:#64748b;display:flex;justify-content:space-between">'
          + '<span>Progresso de Sucessos</span>'
          + '<span>'+Math.round(progress)+'%</span>'
        + '</div>'
      + '</div>'

      + '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:15px;margin-bottom:15px">'
        + '<div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">Check-in de Postagem Semanal</div>'
        + '<div style="background:#f8fafc; border-radius:10px; padding:12px">'
          + miaRenderWeeklyStatus(s, true)
        + '</div>'
        + '<div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:8px">'
          + '<div style="font-size:10px; color:#64748b; display:flex; align-items:center; gap:4px"><div style="width:8px; height:8px; border-radius:50%; background:#22c55e"></div> Sucesso</div>'
          + '<div style="font-size:10px; color:#64748b; display:flex; align-items:center; gap:4px"><div style="width:8px; height:8px; border-radius:50%; background:#ef4444"></div> Falha</div>'
          + '<div style="font-size:10px; color:#64748b; display:flex; align-items:center; gap:4px"><div style="width:8px; height:8px; border-radius:50%; background:#607af92b; border:1px solid #607af9"></div> Agendado</div>'
          + '<div style="font-size:10px; color:#64748b; display:flex; align-items:center; gap:4px"><div style="width:8px; height:8px; border-radius:50%; background:#f1f5f9"></div> NÃ£o definido</div>'
        + '</div>'
      + '</div>'

      + '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:15px;margin-bottom:15px">'
        + '<div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">PrÃ³ximo Agendamento</div>'
        + '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px">'
          + '<div><label style="font-size:10px; color:#94a3b8; display:block; margin-bottom:4px">Data</label><input type="date" id="mia-edit-status-date" class="finput" value="'+(s.scheduled_at ? s.scheduled_at.slice(0, 10) : '')+'"></div>'
          + '<div><label style="font-size:10px; color:#94a3b8; display:block; margin-bottom:4px">Hora</label><input type="time" id="mia-edit-status-time" class="finput" value="'+(s.scheduled_at ? s.scheduled_at.slice(11, 16) : '')+'"></div>'
        + '</div>'
        + '<button class="btn btn-secondary btn-sm" style="width:100%; margin-top:12px; justify-content:center" onclick="miaUpdateStatusTime(event, '+Number(s.id)+')"><i class="fa fa-save"></i> Atualizar HorÃ¡rio</button>'
      + '</div>'
      
      + '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:15px;margin-bottom:15px">'
        + '<div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">Ciclo de Repostagem</div>'
        + '<div style="margin-bottom:12px">'
          + '<label style="font-size:10px; color:#475569; text-transform:uppercase; margin-bottom:8px; display:block">Dias da Semana</label>'
          + '<div class="day-selector" id="mia-edit-status-days" style="display:flex; justify-content:space-between; gap:2px">'
            + '<button type="button" class="day-btn" data-day="0" onclick="toggleMiaEditDay(this)" title="Domingo">D</button>'
            + '<button type="button" class="day-btn" data-day="1" onclick="toggleMiaEditDay(this)" title="Segunda">S</button>'
            + '<button type="button" class="day-btn" data-day="2" onclick="toggleMiaEditDay(this)" title="TerÃ§a">T</button>'
            + '<button type="button" class="day-btn" data-day="3" onclick="toggleMiaEditDay(this)" title="Quarta">Q</button>'
            + '<button type="button" class="day-btn" data-day="4" onclick="toggleMiaEditDay(this)" title="Quinta">Q</button>'
            + '<button type="button" class="day-btn" data-day="5" onclick="toggleMiaEditDay(this)" title="Sexta">S</button>'
            + '<button type="button" class="day-btn" data-day="6" onclick="toggleMiaEditDay(this)" title="SÃ¡bado">S</button>'
          + '</div>'
        + '</div>'
        + '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:12px">'
          + '<div><label style="font-size:10px; color:#94a3b8; display:block; margin-bottom:4px">Ciclos de RepetiÃ§Ã£o</label><input type="number" id="mia-edit-status-repeat-count" class="finput" min="1" value="'+repeatCount+'"></div>'
          + '<div><label style="font-size:10px; color:#94a3b8; display:block; margin-bottom:4px">Intervalo (dias)</label><input type="number" id="mia-edit-status-repeat-interval" class="finput" min="1" value="'+(parseInt(s.repeat_interval || 1))+'"></div>'
        + '</div>'
        + '<button class="btn btn-primary btn-sm" style="width:100%; justify-content:center" onclick="miaUpdateStatusRepostSettings(event, '+Number(s.id)+')"><i class="fa fa-check"></i> Salvar ConfiguraÃ§Ãµes</button>'
      + '</div>'

      + '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px">'
        + '<div style="font-size:10px;color:#94a3b8;margin-bottom:4px">Ãšltima Atividade</div>'
        + '<div style="font-size:13px;font-weight:600;color:#475569"><i class="fa fa-clock-o"></i> '+dt.day+' Ã s '+dt.time+'</div>'
      + '</div>'
    + '</div>'
    + '<div class="mia-df">'
      + '<button class="btn btn-secondary btn-sm" onclick="fecharMiaDrawer()">Fechar</button>'
      + (!isSent && !isSending ? '<button class="btn btn-wpp btn-sm" style="margin-left:auto" onclick="miaSendStatusNow('+Number(s.id)+')"><i class="fa fa-bolt"></i> Disparar Imediatamente</button>' : '')
    + '</div>';
    
    // Pre-seleciona os dias
    setTimeout(function(){
      const daysContainer = document.getElementById('mia-edit-status-days');
      if (daysContainer) {
        daysContainer.querySelectorAll('.day-btn').forEach(function(btn){
          const day = parseInt(btn.dataset.day);
          if (repeatDays.includes(day)) btn.classList.add('active');
        });
      }
    }, 50);
    
    if (content) content.innerHTML = html;
  }).catch(function(err){
    if (content) content.innerHTML = '<div style="padding:40px;text-align:center;color:#ef4444"><i class="fa fa-exclamation-triangle"></i> '+miaEsc(err.message)+'</div>';
  });
}

function fecharMiaDrawer(){
  const d = document.getElementById('mia-drawer');
  const o = document.getElementById('mia-drawer-ov');
  if (d) { d.classList.remove('open'); d.classList.remove('show'); }
  if (o) { o.classList.remove('open'); o.classList.remove('show'); }
}
function abrirMiaDrawer(){
  const d = document.getElementById('mia-drawer');
  const o = document.getElementById('mia-drawer-ov');
  if (d) { d.classList.add('open'); d.classList.add('show'); }
  if (o) { o.classList.add('open'); o.classList.add('show'); }
}

function miaBoot(){
  // Initialize Select2 for all fselect elements in modals
  if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
    $('.fselect').each(function() {
      var $select = $(this);
      var $modal = $select.closest('.mia-overlay');
      $select.select2({
        dropdownParent: $modal.length ? $modal : $('body'),
        width: '100%'
      });
    });
  }

  const syncBtn = document.querySelector('#mia-ov-grupos button.btn.btn-secondary.btn-sm');
  if (syncBtn) syncBtn.setAttribute('onclick', 'miaSyncGroups()');
  const saveBtn = document.querySelector('#mia-ov-grupos .mf-modal .btn.btn-primary');
  if (saveBtn) saveBtn.setAttribute('onclick', 'miaSaveGroupsModal()');

  document.querySelectorAll('[onclick*="Campanha rejeitada"]').forEach(function(btn){
    btn.setAttribute('onclick', "event.stopPropagation(); if(miaCurrentCampaignId){miaUpdateCampaignStatus(miaCurrentCampaignId,'canceled');} else {showMiaToast('Nenhuma campanha selecionada.','warning');}");
  });
  document.querySelectorAll('[onclick*="Campanha aprovada e agendada"]').forEach(function(btn){
    btn.setAttribute('onclick', "event.stopPropagation(); if(miaCurrentCampaignId){miaUpdateCampaignStatus(miaCurrentCampaignId,'scheduled');} else {showMiaToast('Nenhuma campanha selecionada.','warning');}");
  });

  aplicarFiltroMiaAjax = function(){ miaLoadCampaigns(); };
  filtrarMia = function(el, filtro){
    document.querySelectorAll('.filter-zone .fc').forEach(function(chip){ chip.classList.remove('on'); });
    if (el) el.classList.add('on');
    miaFiltroAtual = filtro;
    miaRefreshCurrentView();
  };
  debounceBuscaMia = function(){
    clearTimeout(miaBuscaTimer);
    miaBuscaTimer = setTimeout(miaRefreshCurrentView, 220);
  };

  miaLoadGroups();
  miaLoadCampaigns();
  miaLoadStatusHistory();
  miaLoadProducts();
  miaRenderMediaAttachments();
  miaRenderPreviewCarousel();
  miaRenderManualStatusPreview();
  
  // Inicializa IDs jÃ¡ enviados para nÃ£o disparar Toast de itens antigos
  miaApi('GET', MIA_API.status + '?page=1&limit=50').then(function(resp){
    const items = (resp.data || {}).items || [];
    items.forEach(function(s){ if(s.status === 'sent') miaNotifiedSentIds.add(s.id); });
  });
  
  // â”€â”€ INICIALIZA LAZY CRON & POLLING â”€â”€
  miaTriggerLazyCron();
  if (miaLazyCronInterval) clearInterval(miaLazyCronInterval);
  miaLazyCronInterval = setInterval(function(){
    miaTriggerLazyCron();
  }, 30000);
  miaStartStatusPolling();
}

miaBoot();
<?php endif; // End of plan check ?>
