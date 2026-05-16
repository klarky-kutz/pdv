let miaIndividualMessages = {};
let miaIndividualLinks = {};
let miaCtaLink = '';
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
  let sku = '';
  if (product && product.sku) {
    sku = String(product.sku).trim();
  }
  
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
  const sku = (product && product.sku) ? String(product.sku).trim() : '';
  
  carousel.innerHTML = media.map(function(url, index){
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
      + (sku ? '<div style="font-size:9px;color:#64748b;font-weight:600">SKU: '+miaEsc(sku)+'</div>' : '')
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

const originalAbrirMiaModal = abrirMiaModal;

abrirMiaModal = function(id){
  originalAbrirMiaModal(id);
  if (id === 'novo-disparo') {
    miaRenderIndividualMessages();
  }
};

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
  if (miaMsgMode === 'individual') {
    miaRenderIndividualMessages();
  }
};
