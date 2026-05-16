let miaEditMsgMode = 'single';
let miaEditIndividualMessages = {};
let miaEditIndividualLinks = {};
let miaEditCtaLink = '';
let miaEditSelectedMediaUrls = [];
let miaEditCampaign = null;
let miaEditProduct = null;

function setMiaEditMsgMode(mode){
  miaEditMsgMode = mode;
  const btnSingle = document.getElementById('mia-edit-msg-mode-single');
  const btnIndividual = document.getElementById('mia-edit-msg-mode-individual');
  const singleContainer = document.getElementById('mia-edit-single-msg-container');
  const individualContainer = document.getElementById('mia-edit-individual-msg-container');
  
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
    miaRenderEditIndividualMessages();
  }
  
  miaRenderEditPreviewCarousel();
}

function miaGenerateEditDefaultLinkForCard(index){
  const product = miaGetSelectedProduct() || miaEditProduct;
  const ctaText = (miaEditCampaign && miaEditCampaign.payload_json && miaEditCampaign.payload_json.cta) || '';
  let sku = '';
  if (product && product.sku) {
    sku = String(product.sku).trim();
  }
  
  const waNumber = window.MIA_WHATSAPP_NUMBER || '5511999999999';
  
  const messageText = ctaText + (sku ? ' - SKU: ' + sku : '');
  const encodedText = encodeURIComponent(messageText);
  
  return 'https://wa.me/' + waNumber + '?text=' + encodedText;
}

function miaRenderEditIndividualMessages(){
  const container = document.getElementById('mia-edit-individual-msgs');
  if (!container) return;
  
  const mediaUrls = miaEditSelectedMediaUrls;
  
  if (!mediaUrls.length) {
    container.innerHTML = '<div style="font-size:11px;color:#94a3b8">Sem fotos para exibir.</div>';
    return;
  }
  
  container.innerHTML = mediaUrls.map(function(url, index){
    if (!miaEditIndividualMessages[index]) {
      miaEditIndividualMessages[index] = '';
    }
    if (!miaEditIndividualLinks[index]) {
      miaEditIndividualLinks[index] = miaGenerateEditDefaultLinkForCard(index);
    }
    
    const msg = miaEditIndividualMessages[index] || '';
    const link = miaEditIndividualLinks[index] || '';
    const isExpanded = true; // Always expanded for edit
    
    return '<div class="mia-individual-msg-card expanded" data-index="' + index + '">'
      + '<div class="mia-individual-msg-header">'
      + '<span><i class="fa fa-image" style="margin-right:5px;color:#7c3aed"></i> Card ' + (index + 1) + '</span>'
      + '</div>'
      + '<div class="mia-individual-msg-content">'
      + '<div class="fg" style="margin-bottom:8px">'
      + '<label style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px;display:block">Mensagem</label>'
      + '<div class="mia-individual-msg-toolbar">'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaEditIndividualMsg(' + index + ', \'*\')"><i class="fa fa-bold"></i></button>'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaEditIndividualMsg(' + index + ', \'_\')"><i class="fa fa-italic"></i></button>'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaEditIndividualMsg(' + index + ', \'~\')"><i class="fa fa-strikethrough"></i></button>'
      + '<button class="mia-individual-msg-tbtn" onclick="formatMiaEditIndividualMsg(' + index + ', \'```\')"><i class="fa fa-code"></i></button>'
      + '</div>'
      + '<textarea class="mia-individual-msg-textarea" id="mia-edit-individual-msg-' + index + '" style="border-top:none;border-radius:0 0 6px 6px" data-index="' + index + '" placeholder="Mensagem para este card..." oninput="updateMiaEditIndividualMsg(' + index + ', this)">' + miaEscHtml(msg) + '</textarea>'
      + '</div>'
      + '<div class="fg">'
      + '<label style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px;display:block">Link do Botão CTA</label>'
      + '<input type="url" class="finput" id="mia-edit-individual-link-' + index + '" data-index="' + index + '" placeholder="https://wa.me/5511999999999?text=Olá" value="' + miaEscHtml(link) + '" oninput="updateMiaEditIndividualLink(' + index + ', this)">'
      + '</div>'
      + '</div>'
      + '</div>';
  }).join('');
}

function formatMiaEditIndividualMsg(index, char){
  const textarea = document.getElementById('mia-edit-individual-msg-' + index);
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
  updateMiaEditIndividualMsg(index, textarea);
}

function updateMiaEditIndividualMsg(index, el){
  miaEditIndividualMessages[index] = String(el.value || '');
  miaRenderEditPreviewCarousel();
}

function updateMiaEditIndividualLink(index, el){
  miaEditIndividualLinks[index] = String(el.value || '');
}

function miaGetEditMsgForCard(index){
  if (miaEditMsgMode === 'individual') {
    return miaEditIndividualMessages[index] || '';
  }
  const msgInput = document.getElementById('mia-edit-campaign-content');
  return msgInput ? String(msgInput.value || '') : '';
}

function miaRenderEditPreviewCarousel(){
  const carousel = document.getElementById('mia-edit-wpp-carousel');
  if (!carousel) return;
  
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const product = miaGetSelectedProduct() || miaEditProduct;
  const ctaText = (miaEditCampaign && miaEditCampaign.payload_json && miaEditCampaign.payload_json.cta) || '';
  
  const media = miaEditSelectedMediaUrls;
  
  if (!media.length) {
    carousel.innerHTML = '';
    return;
  }
  
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
    
    const messageDescription = miaGetEditMsgForCard(index);
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
  const updateIndicators = function(){
    const activeIndex = miaGetCarouselActiveIndex(carousel, totalCards);
    miaRenderWppIndicators(totalCards, 'mia-edit-wpp-indicators', activeIndex);
  };
  if (carousel) {
    carousel.onscroll = updateIndicators;
    updateIndicators();
    miaEnableCarouselDrag(carousel);
  }
}

// Override original miaEditCampaignContent
const originalMiaEditCampaignContent = miaEditCampaignContent;

miaEditCampaignContent = function(campaignId){
  miaEditCampaign = miaCampaignMap[Number(campaignId)];
  if (!miaEditCampaign) { showMiaToast('Campanha não carregada.', 'warning'); return; }
  
  // Set selected product ID if it exists
  if (miaEditCampaign.product_id) {
    miaSelectedProductId = miaEditCampaign.product_id;
  }
  
  // Reset edit variables
  miaEditMsgMode = 'single';
  miaEditIndividualMessages = {};
  miaEditIndividualLinks = {};
  miaEditCtaLink = '';
  miaEditSelectedMediaUrls = Array.isArray(miaEditCampaign.media_urls) ? miaEditCampaign.media_urls : [];
  miaEditProduct = null;
  
  // Load from payload
  if (miaEditCampaign.payload_json) {
    if (miaEditCampaign.payload_json.msg_mode) {
      miaEditMsgMode = miaEditCampaign.payload_json.msg_mode;
    }
    if (miaEditCampaign.payload_json.individual_messages && typeof miaEditCampaign.payload_json.individual_messages === 'object') {
      miaEditIndividualMessages = Object.assign({}, miaEditCampaign.payload_json.individual_messages);
    }
    if (miaEditCampaign.payload_json.individual_links && typeof miaEditCampaign.payload_json.individual_links === 'object') {
      miaEditIndividualLinks = Object.assign({}, miaEditCampaign.payload_json.individual_links);
    }
    if (miaEditCampaign.payload_json.main_cta_link) {
      miaEditCtaLink = miaEditCampaign.payload_json.main_cta_link;
    }
  }
  
  // Populate fields
  document.getElementById('mia-edit-campaign-title').value = miaEditCampaign.title || '';
  document.getElementById('mia-edit-campaign-content').value = miaEditCampaign.content || '';
  
  const p = document.getElementById('mia-edit-emoji-container');
  if (p) p.style.display = 'none';
  
  // Open modal
  abrirMiaModal('edit-campaign');
  
  // If campaign has a product_id, fetch the product first!
  if (Number(miaEditCampaign.product_id || 0) > 0) {
    miaApi('GET', MIA_API.products + '?id=' + Number(miaEditCampaign.product_id)).then(function(pResp){
      const items = (pResp.data || {}).items;
      miaEditProduct = (Array.isArray(items) && items.length) ? items[0] : null;
      
      // Now set mode and render preview
      setTimeout(function(){
        setMiaEditMsgMode(miaEditMsgMode);
        updateMiaEditCharCount();
        miaRenderEditPreviewCarousel();
      }, 50);
    }).catch(function(){
      // If product fetch fails, still show modal
      setTimeout(function(){
        setMiaEditMsgMode(miaEditMsgMode);
        updateMiaEditCharCount();
        miaRenderEditPreviewCarousel();
      }, 50);
    });
  } else {
    // No product, set mode and render preview
    setTimeout(function(){
      setMiaEditMsgMode(miaEditMsgMode);
      updateMiaEditCharCount();
      miaRenderEditPreviewCarousel();
    }, 50);
  }
};

// Override original miaSaveCampaignEdit
const originalMiaSaveCampaignEdit = miaSaveCampaignEdit;

miaSaveCampaignEdit = function(){
  const campaignId = miaCurrentCampaignId;
  if (!campaignId) return;
  
  const title = document.getElementById('mia-edit-campaign-title').value.trim();
  const content = document.getElementById('mia-edit-campaign-content').value.trim();
  
  if (!content) { showMiaToast('O conteúdo não pode estar vazio.', 'warning'); return; }
  
  const btn = document.getElementById('mia-edit-campaign-save-btn');
  const oldHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';
  
  miaApi('PATCH', MIA_API.campaigns, {
    id: campaignId,
    title: title,
    content: content,
    payload_json: {
      ...(miaEditCampaign && miaEditCampaign.payload_json ? miaEditCampaign.payload_json : {}),
      msg_mode: miaEditMsgMode,
      individual_messages: miaEditIndividualMessages,
      individual_links: miaEditIndividualLinks,
      main_cta_link: miaEditCtaLink
    }
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao salvar');
    showMiaToast('Campanha atualizada com sucesso!', 'success');
    fecharMiaModal('edit-campaign');
    
    miaOpenCampaign(campaignId);
    miaLoadCampaigns();
  }).catch(function(err){
    showMiaToast(err.message, 'error');
  }).finally(function(){
    btn.disabled = false;
    btn.innerHTML = oldHtml;
  });
};

function updateMiaEditCharCount(){
  const msg = document.getElementById('mia-edit-campaign-content');
  const count = document.getElementById('mia-edit-char-count');
  if(msg && count){
    count.textContent = msg.value.length + ' chars';
  }
  miaRenderEditPreviewCarousel();
}

function miaEscHtml(str){
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// Override original formatMiaEditMsg
const originalFormatMiaEditMsg = formatMiaEditMsg;

formatMiaEditMsg = function(symbol){
  originalFormatMiaEditMsg(symbol);
  miaRenderEditPreviewCarousel();
};

// Override original addMiaEditEmoji
const originalAddMiaEditEmoji = addMiaEditEmoji;

addMiaEditEmoji = function(emoji){
  originalAddMiaEditEmoji(emoji);
  miaRenderEditPreviewCarousel();
};

// Override original miaBuildCampaignDrawerCarousel to handle individual messages
const originalMiaBuildCampaignDrawerCarousel = miaBuildCampaignDrawerCarousel;

miaBuildCampaignDrawerCarousel = function(campaign, product, mediaUrls){
  const fallback = MIA_ROOT + 'assets/itsolution24/img/noimage.jpg';
  const urls = miaUniqueMediaUrls(Array.isArray(mediaUrls) ? mediaUrls : []).slice(0, 4);
  const productName = String((product && product.name) || (campaign && campaign.title) || 'Produto').trim();
  const sku = (product && product.sku) ? String(product.sku).trim() : '';
  const ctaText = miaGetCampaignCtaText(campaign);
  const campaignDescription = String((campaign && campaign.content) ? campaign.content : '').replace(/\s+/g, ' ').trim();
  const productDescription = String((product && product.description) ? product.description : '').replace(/\s+/g, ' ').trim();
  
  // Check campaign payload for mode and individual messages
  const payloadJson = campaign && campaign.payload_json ? campaign.payload_json : {};
  const msgMode = payloadJson.msg_mode || 'single';
  const individualMessages = payloadJson.individual_messages || {};

  if (!urls.length) {
    return '<div class="wpp-carousel-thumb sel"><i class="fa fa-image" style="font-size:18px;color:#94a3b8"></i></div>';
  }

  return urls.map(function(url, index){
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

    // Get the card description based on mode
    let cardDescription = '';
    if (msgMode === 'individual' && individualMessages[index]) {
      cardDescription = individualMessages[index];
    } else {
      cardDescription = campaignDescription || productDescription;
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
      + '<div class="wpp-card-cta"><i class="fa fa-comment-o"></i> '+miaEsc(ctaText)+'</div>'
      + '</div>'
      + '</div>';
  }).join('');
};

// FUNCTIONS FOR EDITING CAMPAIGN TARGET GROUPS
let miaEditingCampaignId = null;

function miaEditCampaignGroups(campaignId){
  miaEditingCampaignId = campaignId;
  const c = miaCampaignMap[Number(campaignId)];
  if (!c) {
    showMiaToast('Campanha não carregada.', 'warning');
    return;
  }
  
  abrirMiaModal('edit-groups');
  
  miaRenderEditGroupsGrid(c);
}

function miaRenderEditGroupsGrid(campaign){
  const grid = document.getElementById('mia-edit-groups-grid');
  if (!grid) return;
  
  const groups = window.miaActiveGroups || [];
  const selectedGroupIds = Array.isArray(campaign.group_ids) ? campaign.group_ids : [];
  
  if (!groups.length) {
    grid.innerHTML = '<div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8;font-size:12px">Nenhum grupo sincronizado.</div>';
    return;
  }
  
  grid.innerHTML = groups.map(function(g){
    const isSelected = selectedGroupIds.indexOf(Number(g.id)) !== -1;
    const isActive = Number(g.is_active || 0) === 1;
    const initials = (g.name || 'G').substring(0, 2).toUpperCase();
    
    return '<div class="gc-item ' + (isSelected ? 'sel' : '') + '" onclick="toggleMiaEditGroup(' + Number(g.id) + ', this)">'
      + '<input type="checkbox" name="mia-edit-target-groups" value="' + Number(g.id) + '" ' + (isSelected ? 'checked' : '') + '>'
      + '<div class="gc-av">' + initials + '</div>'
      + '<div style="flex:1;min-width:0">'
      + '<div class="gc-name">' + miaEsc(g.name || 'Grupo') + '</div>'
      + '<div class="gc-meta"><i class="fa fa-users"></i> ' + Number(g.member_count || 0) + ' membros</div>'
      + '</div>'
      + '</div>';
  }).join('');
}

function toggleMiaEditGroup(groupId, el){
  const checkbox = el.querySelector('input[type="checkbox"]');
  if (!checkbox) return;
  
  checkbox.checked = !checkbox.checked;
  el.classList.toggle('sel', checkbox.checked);
}

function miaSaveCampaignGroups(){
  const campaignId = miaEditingCampaignId;
  if (!campaignId) return;
  
  const groupIds = Array.from(document.querySelectorAll('input[name="mia-edit-target-groups"]:checked')).map(function(el){ return parseInt(el.value); });
  
  const btn = document.getElementById('mia-edit-groups-save-btn');
  const oldHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';
  
  miaApi('PATCH', MIA_API.campaigns, {
    id: campaignId,
    group_ids: groupIds
  }).then(function(resp){
    if (resp.error) throw new Error(resp.message || 'Falha ao salvar');
    
    showMiaToast('Grupos atualizados com sucesso!', 'success');
    fecharMiaModal('edit-groups');
    
    miaLoadCampaigns();
    miaOpenCampaign(campaignId);
  }).catch(function(err){
    showMiaToast(err.message, 'error');
  }).finally(function(){
    btn.disabled = false;
    btn.innerHTML = oldHtml;
  });
}
