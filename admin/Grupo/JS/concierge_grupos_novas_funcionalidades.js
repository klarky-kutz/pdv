let miaExpandedCards = new Set();

function setMiaMsgMode(mode){
  miaMsgMode = mode;
  const btnSingle = document.getElementById('mia-msg-mode-single');
  const btnIndividual = document.getElementById('mia-msg-mode-individual');
  const singleContainer = document.getElementById('mia-single-msg-container');
  const individualContainer = document.getElementById('mia-individual-msg-container');
  
  if (!btnSingle || !btnIndividual) return;
  
  btnSingle.classList.remove('sel');
  btnIndividual.classList.remove('sel');
  
  if (mode === 'single') {
    btnSingle.classList.add('sel');
    if (singleContainer) singleContainer.style.display = 'block';
    if (individualContainer) individualContainer.style.display = 'none';
  } else {
    btnIndividual.classList.add('sel');
    if (singleContainer) singleContainer.style.display = 'none';
    if (individualContainer) individualContainer.style.display = 'block';
    miaRenderIndividualMessages();
  }
  
  miaRenderPreviewCarousel();
}

function miaGenerateDefaultLinkForCard(index){
  const product = miaGetSelectedProduct();
  const ctaText = miaGetSelectedCtaText();
  const mediaUrl = Array.isArray(miaSelectedMediaUrls) ? String(miaSelectedMediaUrls[index] || '').trim() : '';
  const variant = miaFindVariantByMediaUrl(product, mediaUrl);
  const variantSku = (variant && variant.sku) ? String(variant.sku).trim() : '';
  const parentSku = (product && product.sku) ? String(product.sku).trim() : '';
  const sku = variantSku || parentSku;
  
  const waNumber = window.MIA_WHATSAPP_NUMBER || '5511999999999';
  
  const messageText = ctaText + (sku ? ' - SKU: ' + sku : '');
  const encodedText = encodeURIComponent(messageText);
  
  return 'https://wa.me/' + waNumber + '?text=' + encodedText;
}

function miaRenderIndividualMessages(){
  const container = document.getElementById('mia-individual-msgs');
  if (!container) return;
  
  if (!miaSelectedMediaUrls.length) {
    container.innerHTML = '<div style="font-size:11px;color:#94a3b8">Adicione fotos para ver os campos de mensagem individual</div>';
    return;
  }
  
  container.innerHTML = miaSelectedMediaUrls.map(function(url, index){
    if (!miaIndividualMessages[index]) {
      miaIndividualMessages[index] = '';
    }
    if (!miaIndividualLinks[index]) {
      miaIndividualLinks[index] = miaGenerateDefaultLinkForCard(index);
    }
    
    const msg = miaIndividualMessages[index] || '';
    const link = miaIndividualLinks[index] || '';
    const isExpanded = miaExpandedCards.has(index);
    
    return '<div class="mia-individual-msg-card' + (isExpanded ? ' expanded' : '') + '" data-index="' + index + '">'
      + '<div class="mia-individual-msg-header" onclick="toggleMiaIndividualCard(' + index + ')">'
      + '<span><i class="fa fa-image" style="margin-right:5px;color:#7c3aed"></i> Card ' + (index + 1) + '</span>'
      + '<i class="fa fa-chevron-down" style="color:#94a3b8;font-size:11px"></i>'
      + '</div>'
      + '<div class="mia-individual-msg-content">'
      + '<div class="fg" style="margin-bottom:8px">'
      + '<label style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px;display:block">Mensagem</label>'
      + '<div class="mia-individual-msg-toolbar">'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaIndividualMsg(' + index + ', \'*\')"><i class="fa fa-bold"></i></button>'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaIndividualMsg(' + index + ', \'_\')"><i class="fa fa-italic"></i></button>'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaIndividualMsg(' + index + ', \'~\')"><i class="fa fa-strikethrough"></i></button>'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaIndividualMsg(' + index + ', \'```\')"><i class="fa fa-code"></i></button>'
      + '<button class="mia-individual-msg-tbtn emoji-btn" onclick="openMiaIndividualEmojiPicker(' + index + ')"><i class="fa fa-smile-o"></i> Emojis</button>'
      + '</div>'
      + '<textarea class="mia-individual-msg-textarea" id="mia-individual-msg-' + index + '" style="border-top:none;border-radius:0 0 6px 6px" data-index="' + index + '" placeholder="Mensagem para este card..." oninput="updateMiaIndividualMsg(' + index + ', this)">' + miaEscHtml(msg) + '</textarea>'
      + '<div id="mia-individual-emoji-picker-' + index + '" style="display:none;position:absolute;z-index:1000;background:#fff;border:1px solid #ddd;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.15);margin-top:5px;width:340px;overflow:hidden">'
      + '<emoji-picker class="light"></emoji-picker>'
      + '</div>'
      + '</div>'
      + '<div class="fg">'
      + '<label style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px;display:block">Link do Botão CTA</label>'
      + '<input type="url" class="finput" id="mia-individual-link-' + index + '" data-index="' + index + '" placeholder="https://wa.me/5511999999999?text=Olá" value="' + miaEscHtml(link) + '" oninput="updateMiaIndividualLink(' + index + ', this)">'
      + '</div>'
      + '</div>'
      + '</div>';
  }).join('');
  
  // Attach emoji picker listeners
  container.querySelectorAll('emoji-picker').forEach(function(picker, idx){
    picker.addEventListener('emoji-click', function(event){
      const textarea = document.getElementById('mia-individual-msg-' + idx);
      if (textarea) {
        const emoji = event.detail.unicode;
        textarea.value += emoji;
        updateMiaIndividualMsg(idx, textarea);
      }
    });
  });
}

function miaAcceptSuggestion(suggestionId){
  miaAcceptSuggestionGroup([suggestionId]);
}

function miaRejectSuggestion(suggestionId){
  miaRejectSuggestionGroup([suggestionId]);
}

function toggleMiaIndividualCard(index){
  if (miaExpandedCards.has(index)) {
    miaExpandedCards.delete(index);
  } else {
    miaExpandedCards.add(index);
  }
  miaRenderIndividualMessages();
}

function formatMiaIndividualMsg(index, char){
  const textarea = document.getElementById('mia-individual-msg-' + index);
  if (!textarea) return;
  
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const selectedText = textarea.value.substring(start, end);
  
  let formattedText = '';
  if (selectedText) {
    formattedText = char + selectedText + char;
  } else {
    formattedText = char + char;
  }
  
  textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
  textarea.focus();
  if (selectedText) {
    textarea.setSelectionRange(start, start + formattedText.length);
  } else {
    textarea.setSelectionRange(start + 1, start + 1);
  }
  updateMiaIndividualMsg(index, textarea);
}

function openMiaIndividualEmojiPicker(index){
  const picker = document.getElementById('mia-individual-emoji-picker-' + index);
  if (!picker) return;
  
  // Close other open pickers
  document.querySelectorAll('[id^="mia-individual-emoji-picker-"]').forEach(function(p){
    if (p.id !== picker.id) {
      p.style.display = 'none';
    }
  });
  
  picker.style.display = picker.style.display === 'block' ? 'none' : 'block';
}

function updateMiaIndividualMsg(index, el){
  miaIndividualMessages[index] = String(el.value || '');
  miaRenderPreviewCarousel();
}

function updateMiaIndividualLink(index, el){
  miaIndividualLinks[index] = String(el.value || '');
}

function toggleMiaWelcomeCard(){
  miaWelcomeCardExpanded = !miaWelcomeCardExpanded;
  const card = document.getElementById('mia-welcome-card');
  if (!card) return;
  if (miaWelcomeCardExpanded) {
    card.classList.add('expanded');
  } else {
    card.classList.remove('expanded');
  }
}

function updateMiaWelcomeMessage(el){
  miaWelcomeMessage = String((el && el.value) ? el.value : '');
}

function updateWppCtaPrev(){
  const linkInput = document.getElementById('mia-cta-link');
  if (linkInput) {
    miaCtaLink = String(linkInput.value || '');
  }
}

function miaGetMsgForCard(index){
  if (miaMsgMode === 'individual') {
    return miaIndividualMessages[index] || '';
  }
  const msgInput = document.getElementById('ia-msg');
  return msgInput ? String(msgInput.value || '') : '';
}

function miaGetLinkForCard(index){
  if (miaIndividualLinks[index]) {
    return miaIndividualLinks[index];
  }
  return miaCtaLink || '';
}

function miaEscHtml(str){
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

const originalMiaRenderPreviewCarousel = miaRenderPreviewCarousel;

miaRenderPreviewCarousel = function(){
  const carousel = document.getElementById('mia-wpp-carousel');
  const wppTextPrev = document.getElementById('wpp-text-prev');
  const wppCtaPrev = document.getElementById('mia-wpp-cta-prev');
  if(!carousel) return;
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const product = miaGetSelectedProduct();
  const ctaText = miaGetSelectedCtaText();
  
  const media = miaSelectedMediaUrls.slice(0, 4);
  
  if (!media.length) {
    carousel.innerHTML = '<div class="wpp-carousel-thumb sel"><i class="fa fa-image" style="font-size:18px;color:#94a3b8"></i></div>';
    carousel.onscroll = null;
    miaRenderWppIndicators(0, 'mia-wpp-indicators', 0);
    if (wppTextPrev) wppTextPrev.style.display = 'block';
    if (wppCtaPrev) wppCtaPrev.style.display = 'flex';
    return;
  }
  
  if (wppTextPrev) wppTextPrev.style.display = 'none';
  if (wppCtaPrev) wppCtaPrev.style.display = 'none';
  
  const productDescription = String((product && product.description) ? product.description : '').replace(/\s+/g, ' ').trim();
  const parentSku = (product && product.sku) ? String(product.sku).trim() : '';
  
  carousel.innerHTML = media.map(function(url, index){
    const productName = product ? product.name : 'Produto';
    const variant = miaFindVariantByMediaUrl(product, url);
    const variantSku = (variant && variant.sku) ? String(variant.sku).trim() : '';
    const displaySku = variantSku || parentSku;
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
    
    const messageDescription = miaGetMsgForCard(index);
    const cardDescription = messageDescription || productDescription;
    
    return '<div class="wpp-carousel-card">'
      + '<div class="wpp-card-media">'
      + '<img src="'+miaEsc(url)+'" loading="lazy" onerror="this.src=\''+fallback+'\'">'
      + '</div>'
      + '<div class="wpp-card-body">'
      + '<div class="wpp-card-title">'+miaEsc(productName)+'</div>'
      + (cardDescription ? '<div class="wpp-card-desc">'+miaEsc(cardDescription)+'</div>' : '')
      + '<div class="wpp-card-meta">'
      + (displaySku ? '<div style="font-size:9px;color:#64748b;font-weight:600">SKU: '+miaEsc(displaySku)+'</div>' : '')
      + (colorLabel ? '<div style="font-size:9px;color:#1f2937;font-weight:600">Cor: '+colorEmoji+' '+miaEsc(colorLabel)+'</div>' : '')
      + (priceLabel ? '<div class="wpp-price-chip">'+priceLabel+'</div>' : '')
      + '</div>'
      + '<div class="wpp-card-cta">'+miaEsc(ctaText)+'</div>'
      + '</div>'
      + '</div>';
  }).join('');

  const totalCards = media.length;
  const updateIndicatorsFromCarousel = function(){
    const activeIndex = miaGetCarouselActiveIndex(carousel, totalCards);
    miaSelectedMediaIndex = activeIndex;
    miaRenderWppIndicators(totalCards, 'mia-wpp-indicators', activeIndex);
  };
  carousel.onscroll = updateIndicatorsFromCarousel;
  updateIndicatorsFromCarousel();
  
  miaEnableCarouselDrag(carousel);
};

function miaUpdateIndividualLinksToUseCorrectNumber(){
  const waNumber = window.MIA_WHATSAPP_NUMBER || '5511999999999';
  for (let index in miaIndividualLinks) {
    const oldLink = miaIndividualLinks[index];
    if (oldLink) {
      // Replace the old wa.me number with the correct one
      const newLink = oldLink.replace(/wa\.me\/[0-9]+/, 'wa.me/' + waNumber);
      miaIndividualLinks[index] = newLink;
    } else {
      // If no link exists, generate a new default one
      miaIndividualLinks[index] = miaGenerateDefaultLinkForCard(index);
    }
  }
  // Also update the main CTA link
  if (miaCtaLink) {
    miaCtaLink = miaCtaLink.replace(/wa\.me\/[0-9]+/, 'wa.me/' + waNumber);
  }
}

const originalMiaRenderMediaAttachments = miaRenderMediaAttachments;

miaRenderMediaAttachments = function(){
  originalMiaRenderMediaAttachments();
  if (miaMsgMode === 'individual') {
    miaRenderIndividualMessages();
  }
};

const originalMiaApplySelectedProduct = miaApplySelectedProduct;

miaApplySelectedProduct = function(id){
  originalMiaApplySelectedProduct(id);
  // Reset individual links to default when product changes
  miaIndividualLinks = {};
  // Update links to use correct number
  miaUpdateIndividualLinksToUseCorrectNumber();
  if (miaMsgMode === 'individual') {
    miaRenderIndividualMessages();
  }
};

let miaSuggestions = [];
let miaSuggestionUiState = {};
let miaSuggestionLookupByGroup = {};
let miaSuggestionEditContext = null;

function miaSuggestionSystemCtaText(){
  return '📲 Chama no privado!';
}
function miaNormalizeSkuBase(sku){
  const raw = String(sku || '').trim().toUpperCase();
  if (!raw) return '';
  return raw.replace(/-\d+$/, '');
}
function miaSuggestionGroupKey(suggestion){
  const payload = suggestion && suggestion.payload ? suggestion.payload : {};
  const product = payload.product || {};
  const skuBase = miaNormalizeSkuBase(product.sku || '');
  if (skuBase) return 'sku:' + skuBase;
  const productId = Number(suggestion && suggestion.product_id ? suggestion.product_id : 0);
  return productId > 0 ? ('product:' + productId) : ('suggestion:' + String((suggestion && suggestion.id) || '0'));
}
function miaGroupSuggestionsBySkuBase(list){
  const groups = {};
  (Array.isArray(list) ? list : []).forEach(function(sug){
    const key = miaSuggestionGroupKey(sug);
    if (!groups[key]) groups[key] = [];
    groups[key].push(sug);
  });
  return Object.keys(groups).map(function(key){
    return { key: key, suggestions: groups[key] };
  });
}
function miaSuggestionMediaList(value){
  let urls = [];
  if (Array.isArray(value)) {
    value.forEach(function(item){
      urls = urls.concat(miaSuggestionMediaList(item));
    });
  } else if (value && typeof value === 'object') {
    ['media_urls','media_url','image','image_url','photo_url','thumb_url','thumbnail_url','cover_webp','cover_image_webp','main_image','url','src'].forEach(function(key){
      if (Object.prototype.hasOwnProperty.call(value, key)) {
        urls = urls.concat(miaSuggestionMediaList(value[key]));
      }
    });
  } else if (typeof value === 'string') {
    const raw = value.trim();
    if (!raw) return [];
    if (raw.charAt(0) === '[' || raw.charAt(0) === '{') {
      try {
        return miaSuggestionMediaList(JSON.parse(raw));
      } catch (e) {}
    }
    if (raw.indexOf(',') !== -1) {
      raw.split(',').forEach(function(item){
        const chunk = String(item || '').trim();
        if (chunk) urls.push(chunk);
      });
    } else {
      urls.push(raw);
    }
  }
  return urls.filter(function(url){ return !!String(url || '').trim(); });
}
function miaGetSuggestionMediaUrl(product){
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const source = (product && typeof product === 'object') ? product : {};
  let media = [];
  ['media_urls','media_url','image','image_url','photo_url','thumb_url','thumbnail_url','cover_webp','cover_image_webp','main_image','photos_json','payload_json'].forEach(function(key){
    media = media.concat(miaSuggestionMediaList(source[key]));
  });
  const unique = [];
  media.forEach(function(url){
    const clean = String(url || '').trim();
    if (clean && unique.indexOf(clean) === -1) unique.push(clean);
  });
  return unique[0] || fallback;
}
function miaSuggestionDecodeGroupKey(groupKeyToken){
  const raw = String(groupKeyToken || '');
  if (!raw) return '';
  try {
    return decodeURIComponent(raw);
  } catch (e) {
    return raw;
  }
}
function miaGetSuggestionDefaults(suggestion){
  const payload = suggestion && suggestion.payload ? suggestion.payload : {};
  const product = payload.product || {};
  return {
    product_name: String(product.nome || product.name || 'Produto'),
    texto_boas_vindas: String(payload.texto_boas_vindas || payload.welcome_text || ''),
    texto_card: String(payload.texto_card || payload.card_text || ''),
    cta: String(payload.cta || '').trim() || miaSuggestionSystemCtaText()
  };
}
function miaEnsureSuggestionGroupState(groupKey, suggestions){
  if (!miaSuggestionUiState[groupKey]) {
    miaSuggestionUiState[groupKey] = { selectedId: 0, removedIds: {}, edits: {} };
  }
  const state = miaSuggestionUiState[groupKey];
  const validIds = {};
  (suggestions || []).forEach(function(sug){
    const sid = Number(sug && sug.id ? sug.id : 0);
    if (sid > 0) validIds[sid] = true;
  });
  Object.keys(state.removedIds || {}).forEach(function(id){
    if (!validIds[Number(id)]) delete state.removedIds[id];
  });
  Object.keys(state.edits || {}).forEach(function(id){
    if (!validIds[Number(id)]) delete state.edits[id];
  });
  let active = (suggestions || []).filter(function(sug){
    const sid = Number(sug && sug.id ? sug.id : 0);
    return sid > 0 && !state.removedIds[sid];
  });
  if (!active.length && suggestions.length) {
    const firstId = Number(suggestions[0] && suggestions[0].id ? suggestions[0].id : 0);
    if (firstId > 0) delete state.removedIds[firstId];
    active = (suggestions || []).filter(function(sug){
      const sid = Number(sug && sug.id ? sug.id : 0);
      return sid > 0 && !state.removedIds[sid];
    });
  }
  if (!active.some(function(sug){ return Number(sug.id || 0) === Number(state.selectedId || 0); })) {
    state.selectedId = active.length ? Number(active[0].id || 0) : 0;
  }
  return state;
}
function miaGetSuggestionActiveList(groupKey, suggestions){
  const state = miaEnsureSuggestionGroupState(groupKey, suggestions || []);
  return (suggestions || []).filter(function(sug){
    const sid = Number(sug && sug.id ? sug.id : 0);
    return sid > 0 && !state.removedIds[sid];
  });
}
function miaGetSuggestionById(groupKey, suggestionId){
  const list = miaSuggestionLookupByGroup[groupKey] || [];
  const sid = Number(suggestionId || 0);
  return list.find(function(item){ return Number(item && item.id ? item.id : 0) === sid; }) || null;
}
function miaGetSuggestionValues(groupKey, suggestion){
  const sid = Number(suggestion && suggestion.id ? suggestion.id : 0);
  const state = miaEnsureSuggestionGroupState(groupKey, miaSuggestionLookupByGroup[groupKey] || []);
  const defaults = miaGetSuggestionDefaults(suggestion);
  const edits = (state.edits && state.edits[sid]) ? state.edits[sid] : {};
  return {
    product_name: Object.prototype.hasOwnProperty.call(edits, 'product_name') ? String(edits.product_name || '') : defaults.product_name,
    texto_boas_vindas: Object.prototype.hasOwnProperty.call(edits, 'texto_boas_vindas') ? String(edits.texto_boas_vindas || '') : defaults.texto_boas_vindas,
    texto_card: Object.prototype.hasOwnProperty.call(edits, 'texto_card') ? String(edits.texto_card || '') : defaults.texto_card,
    cta: Object.prototype.hasOwnProperty.call(edits, 'cta') ? String(edits.cta || '') : defaults.cta
  };
}
function miaOpenSuggestionTextEdit(groupKeyToken, suggestionId, field){
  const groupKey = miaSuggestionDecodeGroupKey(groupKeyToken);
  const suggestion = miaGetSuggestionById(groupKey, suggestionId);
  if (!groupKey || !suggestion) return;
  const values = miaGetSuggestionValues(groupKey, suggestion);
  const fieldKey = String(field || '');
  const fieldMap = {
    product_name: { title: 'Editar nome do produto', label: 'Nome do Produto', placeholder: 'Digite o nome do produto...' },
    texto_boas_vindas: { title: 'Editar boas-vindas', label: 'Texto de Boas-Vindas', placeholder: 'Digite a mensagem de boas-vindas...' },
    texto_card: { title: 'Editar texto do card', label: 'Texto do Card', placeholder: 'Digite o texto principal do card...' },
    cta: { title: 'Editar CTA', label: 'Texto do CTA', placeholder: 'Digite o texto do botão CTA...' }
  };
  if (!fieldMap[fieldKey]) return;
  const titleEl = document.getElementById('mia-suggestion-edit-title');
  const labelEl = document.getElementById('mia-suggestion-edit-label');
  const inputEl = document.getElementById('mia-suggestion-edit-input');
  if (!inputEl) return;
  if (titleEl) titleEl.textContent = fieldMap[fieldKey].title;
  if (labelEl) labelEl.textContent = fieldMap[fieldKey].label;
  inputEl.placeholder = fieldMap[fieldKey].placeholder;
  inputEl.value = String(values[fieldKey] || '');
  miaSuggestionEditContext = {
    groupKey: groupKey,
    suggestionId: Number(suggestionId || 0),
    field: fieldKey
  };
  abrirMiaModal('suggestion-text-edit');
  setTimeout(function(){ inputEl.focus(); }, 40);
}
function miaSaveSuggestionTextEdit(){
  if (!miaSuggestionEditContext) return;
  const inputEl = document.getElementById('mia-suggestion-edit-input');
  if (!inputEl) return;
  const groupKey = String(miaSuggestionEditContext.groupKey || '');
  const suggestionId = Number(miaSuggestionEditContext.suggestionId || 0);
  const field = String(miaSuggestionEditContext.field || '');
  const suggestion = miaGetSuggestionById(groupKey, suggestionId);
  if (!groupKey || suggestionId <= 0 || !field || !suggestion) return;
  const state = miaEnsureSuggestionGroupState(groupKey, miaSuggestionLookupByGroup[groupKey] || []);
  if (!state.edits[suggestionId]) state.edits[suggestionId] = {};
  const value = String(inputEl.value || '');
  const defaults = miaGetSuggestionDefaults(suggestion);
  if (value === String(defaults[field] || '')) {
    delete state.edits[suggestionId][field];
  } else {
    state.edits[suggestionId][field] = value;
  }
  if (!Object.keys(state.edits[suggestionId]).length) delete state.edits[suggestionId];
  fecharMiaModal('suggestion-text-edit');
  miaRenderSuggestions();
}
function miaSelectSuggestionCard(groupKeyToken, suggestionId){
  const groupKey = miaSuggestionDecodeGroupKey(groupKeyToken);
  const suggestions = miaSuggestionLookupByGroup[groupKey] || [];
  if (!groupKey || !suggestions.length) return;
  const state = miaEnsureSuggestionGroupState(groupKey, suggestions);
  const sid = Number(suggestionId || 0);
  if (sid <= 0 || state.removedIds[sid]) return;
  state.selectedId = sid;
  miaRenderSuggestions();
}
function miaRemoveSuggestionCard(groupKeyToken, suggestionId){
  const groupKey = miaSuggestionDecodeGroupKey(groupKeyToken);
  const suggestions = miaSuggestionLookupByGroup[groupKey] || [];
  if (!groupKey || !suggestions.length) return;
  const state = miaEnsureSuggestionGroupState(groupKey, suggestions);
  const sid = Number(suggestionId || 0);
  if (sid <= 0) return;
  const active = suggestions.filter(function(sug){
    const id = Number(sug && sug.id ? sug.id : 0);
    return id > 0 && !state.removedIds[id];
  });
  if (active.length <= 1) {
    showMiaToast('É necessário manter pelo menos 1 card no carrossel.', 'warning');
    return;
  }
  state.removedIds[sid] = true;
  if (Number(state.selectedId || 0) === sid) {
    const nextActive = suggestions.filter(function(sug){
      const id = Number(sug && sug.id ? sug.id : 0);
      return id > 0 && !state.removedIds[id];
    });
    state.selectedId = nextActive.length ? Number(nextActive[0].id || 0) : 0;
  }
  miaRenderSuggestions();
}
function miaBuildSuggestionApprovePayload(groupKey){
  const suggestions = miaSuggestionLookupByGroup[groupKey] || [];
  const state = miaEnsureSuggestionGroupState(groupKey, suggestions);
  const active = suggestions.filter(function(sug){
    const sid = Number(sug && sug.id ? sug.id : 0);
    return sid > 0 && !state.removedIds[sid];
  });
  const removed = suggestions.filter(function(sug){
    const sid = Number(sug && sug.id ? sug.id : 0);
    return sid > 0 && !!state.removedIds[sid];
  });
  const overrides = {};
  active.forEach(function(sug){
    const sid = Number(sug && sug.id ? sug.id : 0);
    if (sid <= 0) return;
    const edits = state.edits && state.edits[sid] ? state.edits[sid] : null;
    if (!edits) return;
    const item = {};
    ['product_name', 'texto_boas_vindas', 'texto_card', 'cta'].forEach(function(key){
      if (Object.prototype.hasOwnProperty.call(edits, key)) {
        item[key] = String(edits[key] || '');
      }
    });
    if (Object.keys(item).length) overrides[sid] = item;
  });
  return {
    approve_ids: active.map(function(sug){ return Number(sug && sug.id ? sug.id : 0); }).filter(function(id){ return id > 0; }),
    reject_ids: removed.map(function(sug){ return Number(sug && sug.id ? sug.id : 0); }).filter(function(id){ return id > 0; }),
    overrides_json: Object.keys(overrides).length ? JSON.stringify(overrides) : ''
  };
}
function miaApproveSuggestionGroup(groupKeyToken){
  const groupKey = miaSuggestionDecodeGroupKey(groupKeyToken);
  if (!groupKey) return;
  const payload = miaBuildSuggestionApprovePayload(groupKey);
  if (!payload.approve_ids.length) {
    showMiaToast('Nenhum card válido para aprovar.', 'warning');
    return;
  }
  miaAcceptSuggestionGroup(payload.approve_ids, {
    reject_ids: payload.reject_ids,
    overrides_json: payload.overrides_json
  });
}
let miaIsAnalyzing = false;
let miaProgressInterval = null;
let miaProgressStartTime = null;
let miaLatestTokenStats = null;
let miaSuggestionsRequestSeq = 0;
let miaMainBarProgressInterval = null;

function miaUpdateTokenStats(tokenStats){
  if (tokenStats && typeof tokenStats === 'object') {
    miaLatestTokenStats = tokenStats;
  }
  const stats = miaLatestTokenStats;
  if (!stats) return;
  const usageNumEl = document.getElementById('mia-ia-usage-num');
  const usageFillEl = document.getElementById('mia-ia-usage-fill');
  const usadosEl = document.getElementById('mia-ia-tokens-usados');
  const reanalisarBtn = document.getElementById('mia-ia-reanalisar-btn');
  
  if (usageNumEl) {
    if (stats.is_unlimited) {
      usageNumEl.textContent = `${stats.used_month} / ∞`;
    } else {
      usageNumEl.textContent = `${stats.used_month} / ${stats.monthly_limit}`;
    }
  }
  
  if (usageFillEl) {
    let pct = 0;
    let fillClass = '';
    if (!stats.is_unlimited && stats.monthly_limit > 0) {
      pct = Math.min(100, Math.round((stats.used_month / stats.monthly_limit) * 100));
      if (pct >= 95) {
        fillClass = 'crit';
      } else if (pct >= 80) {
        fillClass = 'warn';
      }
    }
    usageFillEl.style.width = pct + '%';
    usageFillEl.className = 'usage-fill' + (fillClass ? ' ' + fillClass : '');
  }
  
  if (usadosEl) {
    usadosEl.textContent = Number(stats.used_this_generation || 0);
  }
  
  if (reanalisarBtn) {
    reanalisarBtn.disabled = !!miaIsAnalyzing;
  }
}

let miaPollingInterval = null;

function miaParseProcessingTimestamp(value){
  if (value === null || value === undefined || value === '') return null;
  const num = Number(value);
  if (!Number.isNaN(num) && num > 0) {
    return num < 1000000000000 ? num * 1000 : num;
  }
  const str = String(value).trim();
  if (!str) return null;
  const normalized = str.indexOf('T') !== -1 ? str : str.replace(' ', 'T');
  const parsed = new Date(normalized);
  const ts = parsed.getTime();
  return Number.isNaN(ts) ? null : ts;
}

function miaGetProcessingTiming(processing){
  const state = processing && typeof processing === 'object' ? processing : {};
  const startMsRaw = miaParseProcessingTimestamp(state.started_at_ts || state.started_at);
  const endMsRaw = miaParseProcessingTimestamp(state.expires_at_ts || state.expires_at);
  const nowMs = miaParseProcessingTimestamp(state.now_ts) || Date.now();
  let startMs = startMsRaw;
  let endMs = endMsRaw;
  if (!startMs && endMs) startMs = Math.max(0, endMs - 180000);
  if (!endMs && startMs) endMs = startMs + 180000;
  if (!startMs && !endMs) {
    startMs = nowMs;
    endMs = nowMs + 180000;
  }
  if (endMs <= startMs) endMs = startMs + 180000;
  return { startMs: startMs, endMs: endMs };
}

function miaComputeProcessingPct(processing){
  const timing = miaGetProcessingTiming(processing);
  const total = Math.max(1, timing.endMs - timing.startMs);
  const elapsed = Math.max(0, Date.now() - timing.startMs);
  return Math.max(0, Math.min(100, (elapsed / total) * 100));
}

function miaStopMainBarProgress(){
  if (miaMainBarProgressInterval) {
    clearInterval(miaMainBarProgressInterval);
    miaMainBarProgressInterval = null;
  }
}

function miaLoadSuggestions(forceGenerate){
  const shouldGenerate = !!forceGenerate;
  const action = shouldGenerate ? 'generate' : 'list';
  const requestId = ++miaSuggestionsRequestSeq;
  const loadingEl = document.getElementById('mia-ia-sug-loading');
  const containerEl = document.getElementById('mia-ia-sug-container');
  const syncLoadingEl = document.getElementById('mia-ia-sync-loading');
  const progressBarEl = document.getElementById('mia-ia-progress-bar');
  const reanalisarBtn = document.getElementById('mia-ia-reanalisar-btn');
  
  function stopProgress(){
    if (miaProgressInterval) {
      clearInterval(miaProgressInterval);
      miaProgressInterval = null;
    }
  }
  
  function stopPolling(){
    if (miaPollingInterval) {
      clearInterval(miaPollingInterval);
      miaPollingInterval = null;
    }
  }

  function setLoadingFallback(){
    if (syncLoadingEl) syncLoadingEl.style.display = 'none';
    if (containerEl) containerEl.style.display = 'none';
    if (loadingEl) loadingEl.style.display = 'block';
    if (reanalisarBtn) reanalisarBtn.disabled = !!miaIsAnalyzing;
  }

  function setProcessingUI(processing){
    miaIsAnalyzing = !!(processing && processing.is_processing);
    if (!miaIsAnalyzing) {
      stopProgress();
      if (syncLoadingEl) syncLoadingEl.style.display = 'none';
      if (reanalisarBtn) reanalisarBtn.disabled = false;
      return;
    }
    if (syncLoadingEl) syncLoadingEl.style.display = 'flex';
    if (loadingEl) loadingEl.style.display = 'none';
    if (containerEl) containerEl.style.display = 'none';
    if (reanalisarBtn) reanalisarBtn.disabled = true;
    const updateBar = function(){
      const pct = miaComputeProcessingPct(processing);
      if (progressBarEl) progressBarEl.style.width = pct + '%';
    };
    stopProgress();
    updateBar();
    miaProgressInterval = setInterval(updateBar, 200);
  }

  function applyDataState(data){
    const processing = data && data.processing ? data.processing : null;
    setProcessingUI(processing);
    miaUpdateTokenStats(data ? data.token_stats : null);
    if (typeof miaApplyMainAIBarState === 'function') {
      miaApplyMainAIBarState(data || {});
    }
    if (processing && processing.is_processing) {
      return;
    }
    if (data && Array.isArray(data.suggestions) && data.suggestions.length > 0) {
      miaSuggestions = data.suggestions;
      miaRenderSuggestions();
      miaRenderPendingSuggestionsOnMainPage();
      return;
    }
    if (data && data.errorMsg) {
      showMiaToast(data.errorMsg, 'error');
      if (loadingEl) {
        loadingEl.innerHTML = '<div style="padding:40px;text-align:center;color:#ef4444"><i class="fa fa-exclamation-triangle"></i> ' + data.errorMsg + '</div>';
        loadingEl.style.display = 'block';
      }
      return;
    }
    miaSuggestions = [];
    miaRenderSuggestions();
    miaRenderPendingSuggestionsOnMainPage();
  }

  function fetchSuggestions(fetchAction){
    return fetch(MIA_ROOT + 'api/concierge/suggestions.php?action=' + encodeURIComponent(fetchAction), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=' + encodeURIComponent(fetchAction)
    }).then(function(res){ return res.json(); });
  }

  function ensurePolling(){
    if (miaPollingInterval) return;
    miaPollingInterval = setInterval(function(){
      fetchSuggestions('list').then(function(pollData){
        if (requestId !== miaSuggestionsRequestSeq) return;
        applyDataState(pollData || {});
        if (!pollData || !pollData.processing || !pollData.processing.is_processing) {
          stopPolling();
        }
      }).catch(function(err){
        console.error(err);
      });
    }, 5000);
  }

  stopPolling();
  if (shouldGenerate) {
    setProcessingUI({
      is_processing: true,
      started_at_ts: Math.floor(Date.now() / 1000),
      expires_at_ts: Math.floor(Date.now() / 1000) + 180
    });
  } else {
    stopProgress();
    setLoadingFallback();
  }

  fetchSuggestions(action)
    .then(function(data){
      if (requestId !== miaSuggestionsRequestSeq) return;
      const safeData = data || {};
      applyDataState(safeData);
      if (safeData.processing && safeData.processing.is_processing) {
        ensurePolling();
      } else {
        stopPolling();
      }
    })
    .catch(function(err){
      if (requestId !== miaSuggestionsRequestSeq) return;
      console.error(err);
      if (miaIsAnalyzing) return;
      stopPolling();
      stopProgress();
      miaIsAnalyzing = false;
      if (syncLoadingEl) syncLoadingEl.style.display = 'none';
      showMiaToast('Erro ao carregar sugestões', 'error');
      if (loadingEl) {
        loadingEl.innerHTML = '<div style="padding:40px;text-align:center;color:#ef4444"><i class="fa fa-exclamation-triangle"></i> Erro ao carregar sugestões</div>';
        loadingEl.style.display = 'block';
      }
    });
}

function miaRenderPendingSuggestionsOnMainPage(){
  const pendingSection = document.getElementById('pending-campanhas');
  const pendingSectionWrapper = document.getElementById('mia-pending-section');
  
  if (!pendingSection) return;
  
  if (!miaSuggestions || miaSuggestions.length === 0) {
    if (pendingSectionWrapper) pendingSectionWrapper.style.display = 'none';
    return;
  }
  
  if (pendingSectionWrapper) pendingSectionWrapper.style.display = 'block';
  
  const groupedSuggestions = miaGroupSuggestionsBySkuBase(miaSuggestions);
  
  const activeGroups = window.miaActiveGroups || [];
  const groupNames = activeGroups.map(g => g.name || 'Grupo').join(' + ');
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  
  pendingSection.innerHTML = '<div class="ai-pending-label"><i class="fa fa-magic"></i> Sugerido pela IA · Aguardando aprovação</div>'
    + groupedSuggestions.map(function(group){
      const suggestions = group.suggestions || [];
      if (!suggestions.length) return '';
      const firstSug = suggestions[0];
      const payload = firstSug.payload || {};
      const product = payload.product || {};
      const productName = product.nome || product.name || 'Produto';
      const mediaUrl = miaGetSuggestionMediaUrl(product);
      const suggestionIds = suggestions.map(function(s){ return Number(s.id || 0); }).filter(function(id){ return id > 0; });
      
      // Thumbnails para a lista principal (menores)
      const thumbnailsHtml = suggestions.map(function(sug, idx){
        const p = sug.payload && sug.payload.product ? sug.payload.product : {};
        const url = miaGetSuggestionMediaUrl(p);
        return '<img src="' + miaEscHtml(url) + '" style="width:28px;height:28px;border-radius:6px;object-fit:cover;border:1px solid #e2e8f0;margin-left:-8px" onerror="this.onerror=null;this.src=\'' + miaEscHtml(fallback) + '\'">';
      }).join('');
      
      return '<div class="ai-pending-row" onclick="abrirMiaModal(\'ia-campanhas\')">'
        + '<div class="ai-pending-icon" style="display:flex;align-items:center;justify-content:flex-start;overflow:visible">' 
        + '<img src="' + miaEscHtml(mediaUrl) + '" style="width:100%;height:100%;object-fit:cover;display:block" onerror="this.onerror=null;this.src=\'' + miaEscHtml(fallback) + '\'">'
        + '</div>'
        + '<div style="flex:1">'
        + '<div class="ai-pending-title">' + miaEscHtml(productName) + ' — "' + suggestions.length + ' card(s) no carrossel"</div>'
        + '<div class="ai-pending-sub" style="display:flex;align-items:center;gap:8px">'
        + '<div style="display:flex;align-items:center;gap:4px"><i class="fa fa-magic"></i> Conteúdo gerado</div>'
        + '<div style="display:flex;align-items:center;gap:4px;margin-left:8px">' + thumbnailsHtml + '</div>'
        + '<div style="margin-left:8px">· Grupos: ' + miaEscHtml(groupNames || 'VIPs + Promos') + ' · Horário sugerido: hoje 19h</div>'
        + '</div>'
        + '</div>'
        + '<div style="display:flex;gap:5px">'
        + '<button class="btn btn-secondary btn-sm" onclick="event.stopPropagation();miaRejectSuggestionGroup([\'' + suggestionIds.join('\',\'') + '\'])"><i class="fa fa-times"></i></button>'
        + '<button class="btn btn-wpp btn-sm" onclick="event.stopPropagation();miaAcceptSuggestionGroup([\'' + suggestionIds.join('\',\'') + '\'])"><i class="fa fa-check"></i> Aprovar</button>'
        + '</div>'
        + '</div>';
    }).join('');
}

function miaGetCategoryEmoji(productName) {
  const name = (productName || '').toLowerCase();
  if (name.includes('vestido') || name.includes('vest')) return '👗';
  if (name.includes('blusa') || name.includes('camisa') || name.includes('camiseta')) return '👚';
  if (name.includes('scarpin') || name.includes('sapato') || name.includes('tenis') || name.includes('tênis')) return '👠';
  if (name.includes('bolsa') || name.includes('clutch')) return '👜';
  if (name.includes('chapéu') || name.includes('chapeu')) return '👒';
  if (name.includes('jaqueta') || name.includes('casaco')) return '🧥';
  if (name.includes('lenço') || name.includes('cachecol')) return '🧣';
  return '👕';
}

function miaRenderSuggestions(){
  const loadingEl = document.getElementById('mia-ia-sug-loading');
  const containerEl = document.getElementById('mia-ia-sug-container');
  const syncLoadingEl = document.getElementById('mia-ia-sync-loading');
  if (miaIsAnalyzing) {
    if (loadingEl) loadingEl.style.display = 'none';
    if (containerEl) containerEl.style.display = 'none';
    if (syncLoadingEl) syncLoadingEl.style.display = 'flex';
    return;
  }
  if (syncLoadingEl) syncLoadingEl.style.display = 'none';
  if (loadingEl) loadingEl.style.display = 'none';
  if (containerEl) containerEl.style.display = 'block';
  miaUpdateTokenStats();
  
  if (!containerEl) return;
  
  console.log('miaSuggestions:', miaSuggestions);
  
  if (miaSuggestions.length === 0) {
    containerEl.innerHTML = '<div style="padding:40px;text-align:center;color:#94a3b8"><i class="fa fa-inbox"></i> Nenhuma sugestão disponível no momento</div>';
    return;
  }
  
  const groupedSuggestions = miaGroupSuggestionsBySkuBase(miaSuggestions);
  const validGroupKeys = {};
  groupedSuggestions.forEach(function(group){
    validGroupKeys[group.key] = true;
    miaSuggestionLookupByGroup[group.key] = group.suggestions || [];
    miaEnsureSuggestionGroupState(group.key, group.suggestions || []);
  });
  Object.keys(miaSuggestionUiState).forEach(function(key){
    if (!validGroupKeys[key]) delete miaSuggestionUiState[key];
  });
  Object.keys(miaSuggestionLookupByGroup).forEach(function(key){
    if (!validGroupKeys[key]) delete miaSuggestionLookupByGroup[key];
  });
  const activeGroups = window.miaActiveGroups || [];
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  
  containerEl.innerHTML = groupedSuggestions.map(function(group){
    const suggestions = group.suggestions || [];
    if (!suggestions.length) return '';
    const state = miaEnsureSuggestionGroupState(group.key, suggestions);
    const activeSuggestions = miaGetSuggestionActiveList(group.key, suggestions);
    if (!activeSuggestions.length) return '';
    const allSuggestionIds = suggestions.map(function(s){ return Number(s.id || 0); }).filter(function(id){ return id > 0; });
    const selectedSuggestion = activeSuggestions.find(function(item){
      return Number(item && item.id ? item.id : 0) === Number(state.selectedId || 0);
    }) || activeSuggestions[0];
    const selectedId = Number(selectedSuggestion && selectedSuggestion.id ? selectedSuggestion.id : 0);
    state.selectedId = selectedId;
    const selectedPayload = selectedSuggestion.payload || {};
    const selectedProduct = selectedPayload.product || {};
    const selectedValues = miaGetSuggestionValues(group.key, selectedSuggestion);
    const productName = selectedValues.product_name || selectedProduct.nome || selectedProduct.name || 'Produto';
    const selectedMediaUrl = miaGetSuggestionMediaUrl(selectedProduct);
    const selectedSku = String(selectedProduct.sku || '').trim();
    const selectedIdx = Math.max(0, activeSuggestions.findIndex(function(item){
      return Number(item && item.id ? item.id : 0) === selectedId;
    }));
    const groupKeyToken = encodeURIComponent(group.key);
    const canRemove = activeSuggestions.length > 1;
    
    const groupsChips = activeGroups.slice(0, 2).map(function(g){
      const memberCount = Number(g.member_count || 0);
      return '<span class="gchip" style="font-size:10.5px"><i class="fa fa-users"></i> ' + miaEscHtml(g.name || 'Grupo') + ' (' + memberCount + ')</span>';
    }).join('');
    
    const thumbnailsHtml = activeSuggestions.slice(0, 8).map(function(sug, idx){
      const payload = sug && sug.payload ? sug.payload : {};
      const product = payload.product || {};
      const mediaUrl = miaGetSuggestionMediaUrl(product);
      const sid = Number(sug && sug.id ? sug.id : 0);
      const values = miaGetSuggestionValues(group.key, sug);
      const thumbTitle = values.product_name || product.nome || product.name || ('Card ' + (idx + 1));
      const isActive = sid === selectedId;
      return '<button type="button" class="mia-sug-thumb' + (isActive ? ' active' : '') + '" '
        + 'title="' + miaEscHtml(thumbTitle) + '" '
        + 'onclick="miaSelectSuggestionCard(\'' + groupKeyToken + '\',' + sid + ')">'
        + '<img src="' + miaEscHtml(mediaUrl) + '" style="width:100%;height:100%;object-fit:cover" onerror="this.onerror=null;this.src=\'' + miaEscHtml(fallback) + '\'">'
        + '</button>';
    }).join('');
    
    const previewCardHtml = '<div class="wpp-carousel-card mia-sug-preview-card">'
        + '<div class="wpp-card-media"><img src="' + miaEscHtml(selectedMediaUrl) + '" style="width:100%;height:100%;object-fit:cover" onerror="this.onerror=null;this.src=\'' + miaEscHtml(fallback) + '\'"></div>'
        + '<div class="wpp-card-body">'
        + '<div class="wpp-card-title">' + miaEscHtml(productName) + '</div>'
        + (selectedValues.texto_card ? '<div class="wpp-card-desc">' + miaEscHtml(selectedValues.texto_card) + '</div>' : '')
        + (selectedSku ? '<div style="font-size:9px;color:#64748b;font-weight:600;margin-top:4px">SKU: ' + miaEscHtml(selectedSku) + '</div>' : '')
        + '<div class="wpp-card-cta">' + miaEscHtml(selectedValues.cta) + '</div>'
        + '</div>'
        + '</div>';

    return '<div class="ai-sug-card" data-suggestion-group="' + miaEscHtml(group.key) + '">'
      + '<div class="ai-sug-header">'
      + '<div style="flex:1">'
      + '<div class="ai-sug-title">' + miaEscHtml(productName) + '</div>'
      + '<div class="ai-sug-reason"><i class="fa fa-picture-o"></i> ' + activeSuggestions.length + ' card(s) ativo(s)' + (suggestions.length !== activeSuggestions.length ? (' de ' + suggestions.length + ' sugerido(s)') : '') + '</div>'
      + '</div>'
      + '<div class="ai-sug-badge"><i class="fa fa-magic"></i> IA · Desejo</div>'
      + '</div>'
      + '<div class="mia-sug-main">'
      + '<div class="mia-sug-preview-col">'
      + '<div class="mia-sug-thumbs-wrap">' + thumbnailsHtml + '</div>'
      + '<div class="mia-sug-preview-wrap">'
      + '<div class="wpp-carousel wpp-carousel-cards wpp-carousel-drawer mia-sug-single-carousel">' + previewCardHtml + '</div>'
      + '</div>'
      + '<div class="mia-sug-preview-meta"><span><i class="fa fa-clone"></i> Card ' + (selectedIdx + 1) + ' de ' + activeSuggestions.length + '</span></div>'
      + '<div class="mia-sug-preview-actions">'
      + '<button class="btn btn-danger btn-sm mia-sug-remove-btn" onclick="miaRemoveSuggestionCard(\'' + groupKeyToken + '\',' + selectedId + ')" ' + (canRemove ? '' : 'disabled') + '><i class="fa fa-trash"></i> Remover card</button>'
      + '</div>'
      + '</div>'
      + '<div class="mia-sug-text-col">'
      + '<div class="mia-sug-text-block product">'
      + '<div class="mia-sug-text-head"><span><i class="fa fa-tag"></i> Nome do Produto</span><button type="button" class="mia-sug-edit-btn" title="Editar nome do produto" onclick="miaOpenSuggestionTextEdit(\'' + groupKeyToken + '\',' + selectedId + ',\'product_name\')"><i class="fa fa-pencil"></i></button></div>'
      + '<div class="mia-sug-text-body"><strong>' + miaEscHtml(productName) + '</strong></div>'
      + '</div>'
      + '<div class="mia-sug-text-block welcome">'
      + '<div class="mia-sug-text-head"><span><i class="fa fa-whatsapp"></i> Texto de Boas-Vindas</span><button type="button" class="mia-sug-edit-btn" title="Editar boas-vindas" onclick="miaOpenSuggestionTextEdit(\'' + groupKeyToken + '\',' + selectedId + ',\'texto_boas_vindas\')"><i class="fa fa-pencil"></i></button></div>'
      + '<div class="mia-sug-whatsapp-bubble">' + (selectedValues.texto_boas_vindas ? miaEscHtml(selectedValues.texto_boas_vindas) : '<span class="mia-sug-empty">Sem texto de boas-vindas.</span>') + '</div>'
      + '</div>'
      + '<div class="mia-sug-text-block cardtext">'
      + '<div class="mia-sug-text-head"><span><i class="fa fa-file-text-o"></i> Texto do Card</span><button type="button" class="mia-sug-edit-btn" title="Editar texto do card" onclick="miaOpenSuggestionTextEdit(\'' + groupKeyToken + '\',' + selectedId + ',\'texto_card\')"><i class="fa fa-pencil"></i></button></div>'
      + '<div class="mia-sug-card-simple">' + (selectedValues.texto_card ? miaEscHtml(selectedValues.texto_card) : '<span class="mia-sug-empty">Sem texto do card.</span>') + '</div>'
      + '</div>'
      + '<div class="mia-sug-text-block cta">'
      + '<div class="mia-sug-text-head"><span><i class="fa fa-mouse-pointer"></i> CTA</span><button type="button" class="mia-sug-edit-btn" title="Editar CTA" onclick="miaOpenSuggestionTextEdit(\'' + groupKeyToken + '\',' + selectedId + ',\'cta\')"><i class="fa fa-pencil"></i></button></div>'
      + '<div class="mia-sug-cta-card">' + miaEscHtml(selectedValues.cta || miaSuggestionSystemCtaText()) + '</div>'
      + '</div>'
      + '</div>'
      + '</div>'
      + '<div class="ai-sug-footer">'
      + '<div style="display:flex;gap:5px;align-items:center">'
      + groupsChips
      + '<span style="font-size:10.5px;color:#7c3aed;font-weight:600;margin-left:4px"><i class="fa fa-clock"></i> 19:00</span>'
      + '</div>'
      + '<div style="display:flex;gap:6px">'
      + '<button class="btn btn-secondary btn-sm" onclick="miaRejectSuggestionGroup([' + allSuggestionIds.join(',') + '])"><i class="fa fa-times"></i> Rejeitar</button>'
      + '<button class="btn btn-wpp btn-sm" onclick="miaApproveSuggestionGroup(\'' + groupKeyToken + '\')"><i class="fa fa-check"></i> Aprovar Carrossel</button>'
      + '</div>'
      + '</div>'
      + '</div>';
  }).join('');
}

// Função para selecionar a variante do produto
function miaSelectProductVariant(productId, idx){
  // Atualiza thumbnails
  const cardEl = document.querySelector('.ai-sug-card[data-product-id="' + productId + '"]');
  if (!cardEl) return;
  
  const thumbs = cardEl.querySelectorAll('.mia-variant-thumb');
  thumbs.forEach(function(thumb){
    thumb.style.borderColor = (thumb.getAttribute('data-idx') == idx) ? '#7c3aed' : '#e9d5ff';
  });
  
  // Atualiza conteúdo
  const contents = cardEl.querySelectorAll('.mia-variant-content');
  contents.forEach(function(content){
    content.style.display = (content.getAttribute('data-idx') == idx) ? 'block' : 'none';
  });
}

function miaAcceptSuggestionGroup(suggestionIds, extraPayload){
  const ids = (Array.isArray(suggestionIds) ? suggestionIds : [suggestionIds]).map(function(id){ return Number(id || 0); }).filter(function(id){ return id > 0; });
  if (!ids.length) return;
  const extras = extraPayload && typeof extraPayload === 'object' ? extraPayload : {};
  let body = 'action=accept_group&suggestion_ids=' + encodeURIComponent(ids.join(','));
  if (extras.overrides_json) {
    body += '&overrides_json=' + encodeURIComponent(String(extras.overrides_json));
  }
  if (Array.isArray(extras.reject_ids) && extras.reject_ids.length) {
    const rejectIds = extras.reject_ids.map(function(id){ return Number(id || 0); }).filter(function(id){ return id > 0; });
    if (rejectIds.length) {
      body += '&reject_ids=' + encodeURIComponent(rejectIds.join(','));
    }
  }
  console.log('miaAcceptSuggestionGroup called with IDs:', ids);
  fetch(MIA_ROOT + 'api/concierge/suggestions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body
  })
  .then(res => {
    console.log('Accept response status:', res.status);
    return res.text().then(text => {
      console.log('Accept response text:', text);
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error('Invalid JSON: ' + text);
      }
    });
  })
  .then(data => {
    console.log('Accept response data:', data);
    if (data.errorMsg) {
      showMiaToast(data.errorMsg, 'error');
      return;
    }
    showMiaToast('Carrossel aprovado! Campanha criada.', 'success');
    miaLoadSuggestions(false);
  })
  .catch(err => {
    console.error('Accept error:', err);
    showMiaToast('Erro ao aprovar carrossel: ' + err.message, 'error');
  });
}

function miaRejectSuggestionGroup(suggestionIds){
  const ids = (Array.isArray(suggestionIds) ? suggestionIds : [suggestionIds]).map(function(id){ return Number(id || 0); }).filter(function(id){ return id > 0; });
  if (!ids.length) return;
  console.log('miaRejectSuggestionGroup called with IDs:', ids);
  fetch(MIA_ROOT + 'api/concierge/suggestions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=reject_group&suggestion_ids=' + encodeURIComponent(ids.join(','))
  })
  .then(res => {
    console.log('Reject response status:', res.status);
    return res.text().then(text => {
      console.log('Reject response text:', text);
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error('Invalid JSON: ' + text);
      }
    });
  })
  .then(data => {
    console.log('Reject response data:', data);
    if (data.errorMsg) {
      showMiaToast(data.errorMsg, 'error');
      return;
    }
    showMiaToast('Grupo de sugestões rejeitado!', 'info');
    miaLoadSuggestions(false);
  })
  .catch(err => {
    console.error('Reject error:', err);
    showMiaToast('Erro ao rejeitar grupo: ' + err.message, 'error');
  });
}

// Unificado wrapper para abrirMiaModal
const originalAbrirMiaModal = abrirMiaModal;

abrirMiaModal = function(id){
  originalAbrirMiaModal(id);
  if (id === 'novo-disparo') {
    miaCampaignSendLocks = {};
    miaStatusSendLocks = {};
    miaUpdateIndividualLinksToUseCorrectNumber();
    miaRenderIndividualMessages();
    const welcomeCard = document.getElementById('mia-welcome-card');
    if (welcomeCard) {
      welcomeCard.classList.toggle('expanded', !!miaWelcomeCardExpanded);
    }
    const welcomeInput = document.getElementById('mia-welcome-message');
    if (welcomeInput) {
      welcomeInput.value = String(miaWelcomeMessage || '');
    }
  }
  if (id === 'ia-campanhas') {
    miaLoadSuggestions(false);
  }
};
const originalFecharMiaModalForSuggestions = fecharMiaModal;
fecharMiaModal = function(id){
  originalFecharMiaModalForSuggestions(id);
  if (id === 'suggestion-text-edit') {
    miaSuggestionEditContext = null;
    const inputEl = document.getElementById('mia-suggestion-edit-input');
    if (inputEl) inputEl.value = '';
  }
};

function miaReanalizarCatalogo(){
  miaLoadSuggestions(true);
}
function miaApplyMainAIBarState(data){
  const aiBar = document.getElementById('mia-ai-bar');
  const aiBarTitle = document.getElementById('mia-ai-bar-title');
  const aiBarSub = document.getElementById('mia-ai-bar-sub');
  const aiBarFill = document.querySelector('.ai-bar-fill');
  
  if (!aiBar) return;
  const safeData = data && typeof data === 'object' ? data : {};
  const processing = safeData.processing || {};
  const isProcessing = !!processing.is_processing;
  const pendingCampaigns = Array.isArray(safeData.suggestions) ? safeData.suggestions.length : 0;
  const catalogCount = Number(window.miaAIBarCatalogCount || 0);

  aiBar.style.display = 'flex';
  if (isProcessing) {
    if (aiBarTitle) aiBarTitle.innerText = 'IA está analisando seu catálogo...';
    if (aiBarSub) aiBarSub.innerText = catalogCount + ' produtos ativos · buscando campanhas para aprovação';
    if (aiBarFill) {
      const wasProcessing = aiBarFill.classList.contains('is-processing');
      aiBarFill.classList.add('is-processing');
      aiBarFill.classList.remove('is-complete');
      if (!wasProcessing) {
        aiBarFill.style.width = '0%';
      }
      const updateBar = function(){
        const pct = miaComputeProcessingPct(processing);
        aiBarFill.style.width = Math.max(0, Math.min(100, pct)) + '%';
      };
      miaStopMainBarProgress();
      updateBar();
      miaMainBarProgressInterval = setInterval(updateBar, 200);
    }
    return;
  }

  miaStopMainBarProgress();
  if (aiBarTitle) aiBarTitle.innerText = 'IA analisou seu catálogo e identificou oportunidades!';
  if (aiBarSub) aiBarSub.innerText = catalogCount + ' produtos ativos · ' + pendingCampaigns + ' campanhas prontas para aprovação';
  if (aiBarFill) {
    aiBarFill.classList.remove('is-processing');
    aiBarFill.classList.add('is-complete');
    aiBarFill.style.width = '100%';
  }
}

function miaUpdateMainAIBar(){
  fetch(MIA_ROOT + 'api/concierge/suggestions.php?action=list', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=list'
  })
  .then(res => res.json())
  .then(data => {
    miaApplyMainAIBarState(data || {});
    if (data && data.token_stats) {
      miaUpdateTokenStats(data.token_stats);
    }
  })
  .catch(err => {
    console.error('Error updating main AI bar:', err);
  });
}

// Set up interval to check processing state every 10 seconds
let miaMainAIBarInterval = null;
document.addEventListener('DOMContentLoaded', function(){
  miaUpdateMainAIBar();
  if (miaMainAIBarInterval) clearInterval(miaMainAIBarInterval);
  miaMainAIBarInterval = setInterval(miaUpdateMainAIBar, 5000);
});

function miaAprovarTodasSugestoes(){
  const grouped = miaGroupSuggestionsBySkuBase(miaSuggestions || []);
  if (!grouped.length) {
    showMiaToast('Não há sugestões para aprovar.', 'info');
    return;
  }
  grouped.forEach(function(group){
    miaApproveSuggestionGroup(encodeURIComponent(group.key));
  });
}


function miaConfirmStatusSent(statusId){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permiss�o.', 'warning'); return; }
  if (!statusId) return;
  
  miaApi('PATCH', MIA_API.status, {
    id: statusId,
    status: 'sent',
    sent_at: new Date().toISOString().replace('T', ' ').slice(0, 19)
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message);
    showMiaToast('Status marcado como enviado!', 'success');
    miaLoadStatusHistory();
    if (miaCurrentView === 'status') miaLoadStatuses();
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao confirmar status.', 'error');
  });
}

function miaMarkStatusAsError(statusId){
  if (!MIA_CAN_MANAGE) { showMiaToast('Sem permiss�o.', 'warning'); return; }
  if (!statusId) return;
  
  miaApi('PATCH', MIA_API.status, {
    id: statusId,
    status: 'error',
    error_message: 'Marca��o manual como erro.'
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message);
    showMiaToast('Status marcado como erro!', 'success');
    miaLoadStatusHistory();
    if (miaCurrentView === 'status') miaLoadStatuses();
  }).catch(function(err){
    showMiaToast(err.message || 'Erro ao marcar status como erro.', 'error');
  });
}

