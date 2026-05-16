/**
 * Credit Card 3D Interactive Module
 * ModernPOS - Checkout Transparente
 * Integração com Stripe.js
 */

(function() {
  'use strict';

  // ==========================================
  // SVG Icons das Bandeiras
  // ==========================================
  const CARD_ICONS = {
    visa: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><g id="visa"><path fill="#0E4595" d="M278.198,334.228l33.36-195.763h53.358l-33.384,195.763H278.198z"/><path fill="#0E4595" d="M524.307,142.687c-10.57-3.966-27.135-8.222-47.822-8.222c-52.725,0-89.863,26.551-90.18,64.604c-0.297,28.129,26.514,43.821,46.754,53.185c20.77,9.597,27.752,15.716,27.652,24.283c-0.133,13.123-16.586,19.116-31.924,19.116c-21.355,0-32.701-2.967-50.225-10.274l-6.877-3.112l-7.488,43.823c12.463,5.466,35.508,10.199,59.438,10.445c56.09,0,92.502-26.248,92.916-66.884c0.199-22.27-14.016-39.216-44.801-53.188c-18.65-9.056-30.072-15.099-29.951-24.269c0-8.137,9.668-16.838,30.559-16.838c17.447-0.271,30.088,3.534,39.936,7.5l4.781,2.259L524.307,142.687"/><path fill="#0E4595" d="M661.615,138.464h-41.23c-12.773,0-22.332,3.486-27.941,16.234l-79.244,179.402h56.031c0,0,9.16-24.121,11.232-29.418c6.123,0,60.555,0.084,68.336,0.084c1.596,6.854,6.492,29.334,6.492,29.334h49.512L661.615,138.464z M596.198,264.872c4.414-11.279,21.26-54.724,21.26-54.724c-0.314,0.521,4.381-11.334,7.074-18.684l3.607,16.878c0,0,10.217,46.729,12.352,56.527h-44.293V264.872z"/><path fill="#0E4595" d="M232.903,138.464L180.664,271.96l-5.565-27.129c-9.726-31.274-40.025-65.157-73.898-82.12l47.767,171.204l56.455-0.064l84.004-195.386L232.903,138.464"/><path fill="#F2AE14" d="M131.92,138.464H45.879l-0.682,4.073c66.939,16.204,111.232,55.363,129.618,102.415l-18.709-89.96C152.877,142.596,143.509,138.896,131.92,138.464"/></g></svg>`,
    
    mastercard: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><g><rect fill="#000000" x="0" y="0" width="750" height="471" rx="40"/><g transform="translate(133,48)"><rect fill="#FF5F00" x="169.81" y="31.89" width="143.72" height="234.42"/><path fill="#EB001B" d="M317.05,197.6c0-47.5-22.3-89.8-56.74-117.21c-34.45,27.41-56.74,69.71-56.74,117.21s22.29,89.8,56.74,117.21C294.76,287.4,317.05,245.1,317.05,197.6z"/><path fill="#F79E1B" d="M615.26,197.6c0,82.4-66.79,149.2-149.2,149.2c-33.47,0-64.32-11.05-89.16-29.7c34.45-27.41,56.74-69.71,56.74-117.21s-22.29-89.8-56.74-117.21c24.84-18.65,55.69-29.7,89.16-29.7C548.47,52.99,615.26,119.78,615.26,197.6z"/></g></g></svg>`,
    
    amex: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><rect fill="#2557D6" x="0" y="0" width="750" height="471" rx="40"/><path fill="#FFFFFF" d="M0,221.2L36,221.2l8.1-19.5l18.2,0l8.1,19.5l70.9,0v-14.9l6.3,15h36.8l6.3-15.2v15.2h176.3l-0.1-32h3.4c2.4,0.1,3.1,0.3,3.1,4.2v27.8h91.2v-7.5c7.4,3.9,18.8,7.5,33.8,7.5h38.4l8.2-19.5h18.2l8,19.5h74V202.6l11.2,18.5h59.2V98.6h-58.6v14.5l-8.2-14.5h-60.1v14.5l-7.5-14.5h-81.2c-13.6,0-25.6,1.9-35.2,7.2v-7.2h-56.1v7.2c-6.1-5.4-14.5-7.2-23.8-7.2H180.8l-13.7,31.7L152.9,98.6H88.4v14.5L81.3,98.6H26.3L0.8,156.9V221.2z"/></svg>`,
    
    discover: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><rect fill="#4D4D4D" x="0" y="0" width="750" height="471" rx="40"/><path fill="#FFFFFF" d="M314.6,152.2c8.5,0,15.7,1.6,24.8,5.9v22.5c-8.4-7.6-15.5-10.9-25.3-10.9c-18.8,0-33.6,14.8-33.6,33.6c0,19.7,14.4,33.8,34.5,33.8c9,0,16.3-3,24.4-10.4v22.5c-8.9,4-16.4,5.6-24.8,5.6c-30.7,0-54.5-21.8-54.5-50.9C260.2,175.1,283.5,152.2,314.6,152.2z"/><ellipse fill="#F47216" cx="409.4" cy="201.1" rx="53.7" ry="52.7"/></svg>`,
    
    diners: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><rect fill="#0079BE" x="0" y="0" width="750" height="471" rx="40"/><path fill="#FFFFFF" d="M584.9,237.9c0-98.9-82.5-167.6-173.4-167.6h-78.2c-91.5,0-167.2,68.7-167.2,167.6c0,90.5,75.7,165.2,167.2,165.2h78.2C502,402.6,584.9,328.3,584.9,237.9z"/><path fill="#0079BE" d="M333.3,82.9c-83.6,0-151.7,68.3-151.7,152.1c0,83.8,68.1,152.1,151.7,152.1c83.6,0,151.7-68.3,151.7-152.1C485,151.2,416.9,82.9,333.3,82.9z"/><path fill="#FFFFFF" d="M237.1,236.1c0.1-41,25.6-76,61.9-89.8v179.6C262.7,312.1,237.2,277.1,237.1,236.1z M368.1,325.9V145.8c36.4,13.8,62.1,48.8,62.1,89.9C430.1,276.8,404.4,312,368.1,325.9z"/></svg>`,
    
    jcb: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><rect fill="#0E4C96" x="0" y="0" width="750" height="471" rx="40"/><path fill="#FFFFFF" d="M617.2,346.8c0,41.6-33.7,75.4-75.4,75.4H132.8V124.2c0-41.6,33.7-75.4,75.4-75.4h409.1V346.8z"/><path fill="url(#jcb-green)" d="M483.9,242c11.7,0.3,23.4-0.5,35.1,0.4c11.8,2.2,14.6,20,4.2,25.9c-7.1,3.8-15.6,1.4-23.4,2.1h-15.9V242z"/><defs><linearGradient id="jcb-green" x1="0%" y1="50%" x2="100%" y2="50%"><stop offset="0%" stop-color="#007B40"/><stop offset="100%" stop-color="#55B330"/></linearGradient></defs></svg>`,
    
    unknown: `<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="750" height="471" viewBox="0 0 750 471"><rect fill="#bdbdbd" x="0" y="0" width="750" height="471" rx="40"/><text x="375" y="260" font-family="Arial" font-size="120" fill="#fff" text-anchor="middle">?</text></svg>`
  };

  // ==========================================
  // Estado do Módulo
  // ==========================================
  let stripe = null;
  let cardElement = null;
  let paymentData = {};
  let fieldsInitialized = false;
  
  // ==========================================
  // Máscaras de Input Simples
  // ==========================================
  
  function formatCardNumber(value) {
    const v = value.replace(/\D/g, '').substring(0, 16);
    const parts = [];
    for (let i = 0; i < v.length; i += 4) {
      parts.push(v.substring(i, i + 4));
    }
    return parts.join(' ');
  }
  
  function formatExpiry(value) {
    const v = value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 2) {
      return v.substring(0, 2) + '/' + v.substring(2);
    }
    return v;
  }
  
  function formatCVV(value) {
    return value.replace(/\D/g, '').substring(0, 4);
  }

  // ==========================================
  // Detecção de Bandeira
  // ==========================================
  
  function detectCardType(number) {
    const patterns = {
      visa: /^4/,
      mastercard: /^(5[1-5]|22[2-9]|2[3-7])/,
      amex: /^3[47]/,
      discover: /^(6011|65|64[4-9])/,
      diners: /^3(0[0-5]|[689])/,
      jcb: /^(35|2131|1800)/
    };
    
    const cleanNumber = number.replace(/\D/g, '');
    
    for (const [type, pattern] of Object.entries(patterns)) {
      if (pattern.test(cleanNumber)) {
        return type;
      }
    }
    
    return 'unknown';
  }
  
  function getCardColor(type) {
    const colors = {
      visa: 'lime',
      mastercard: 'lightblue',
      amex: 'green',
      discover: 'purple',
      diners: 'orange',
      jcb: 'red',
      unknown: 'grey'
    };
    return colors[type] || 'grey';
  }

  // ==========================================
  // Atualização Visual do Cartão
  // ==========================================
  
  function updateCardDisplay(field, value) {
    switch (field) {
      case 'number':
        const svgNumber = document.getElementById('svgnumber');
        if (svgNumber) {
          svgNumber.textContent = value || '0123 4567 8910 1112';
        }
        break;
        
      case 'name':
        const svgName = document.getElementById('svgname');
        const svgNameBack = document.getElementById('svgnameback');
        const displayName = value || 'NOME NO CARTÃO';
        if (svgName) svgName.textContent = displayName;
        if (svgNameBack) svgNameBack.textContent = displayName;
        break;
        
      case 'expiry':
        const svgExpire = document.getElementById('svgexpire');
        if (svgExpire) {
          svgExpire.textContent = value || 'MM/AA';
        }
        break;
        
      case 'cvv':
        const svgSecurity = document.getElementById('svgsecurity');
        if (svgSecurity) {
          svgSecurity.textContent = value || '***';
        }
        break;
    }
  }
  
  function updateCardColor(type) {
    const color = getCardColor(type);
    
    // Usa setAttribute para elementos SVG (não suportam className diretamente)
    document.querySelectorAll('.creditcard .lightcolor').forEach(el => {
      el.setAttribute('class', 'lightcolor ' + color);
    });
    
    document.querySelectorAll('.creditcard .darkcolor').forEach(el => {
      el.setAttribute('class', 'darkcolor ' + color + 'dark');
    });
  }
  
  function updateCardIcon(type) {
    const ccicon = document.getElementById('ccicon');
    const ccsingle = document.getElementById('ccsingle');
    
    const icon = CARD_ICONS[type] || CARD_ICONS.unknown;
    
    if (ccicon) ccicon.innerHTML = icon;
    if (ccsingle) ccsingle.innerHTML = icon;
  }

  // ==========================================
  // Flip do Cartão
  // ==========================================
  
  function flipCard(toBack = true) {
    const card = document.querySelector('.creditcard');
    if (!card) return;
    
    if (toBack) {
      card.classList.add('flipped');
    } else {
      card.classList.remove('flipped');
    }
  }

  // ==========================================
  // Inicialização dos Campos
  // ==========================================
  
  function initCardFields() {
    // Evita registrar múltiplos listeners se o modal for aberto/fechado várias vezes
    if (fieldsInitialized) return;

    const numberInput = document.getElementById('card-number');
    const nameInput = document.getElementById('card-name');
    const expiryInput = document.getElementById('card-expiry');
    const cvvInput = document.getElementById('card-cvv');

    if (!numberInput && !nameInput && !expiryInput && !cvvInput) {
      return;
    }

    fieldsInitialized = true;
    
    if (numberInput) {
      numberInput.addEventListener('input', function(e) {
        const formatted = formatCardNumber(this.value);
        this.value = formatted;
        updateCardDisplay('number', formatted);
        
        const type = detectCardType(this.value);
        updateCardColor(type);
        updateCardIcon(type);
      });
      
      numberInput.addEventListener('focus', () => flipCard(false));
    }
    
    if (nameInput) {
      nameInput.addEventListener('input', function() {
        updateCardDisplay('name', this.value.toUpperCase());
      });
      nameInput.addEventListener('focus', () => flipCard(false));
    }
    
    if (expiryInput) {
      expiryInput.addEventListener('input', function() {
        const formatted = formatExpiry(this.value);
        this.value = formatted;
        updateCardDisplay('expiry', formatted);
      });
      expiryInput.addEventListener('focus', () => flipCard(false));
    }
    
    if (cvvInput) {
      cvvInput.addEventListener('input', function() {
        const formatted = formatCVV(this.value);
        this.value = formatted;
        updateCardDisplay('cvv', formatted);
      });
      cvvInput.addEventListener('focus', () => flipCard(true));
      cvvInput.addEventListener('blur', () => flipCard(false));
    }
    
    // Clique no cartão para virar
    const card = document.querySelector('.creditcard');
    if (card) {
      card.addEventListener('click', function() {
        this.classList.toggle('flipped');
      });
    }
    
    // Remove preload após carregar
    setTimeout(() => {
      const container = document.querySelector('.creditcard-container');
      if (container) container.classList.remove('preload');
    }, 100);
  }

  // ==========================================
  // Integração com Stripe.js
  // ==========================================
  
  async function initStripe(publishableKey) {
    if (typeof Stripe === 'undefined') {
      console.error('Stripe.js não carregado');
      return false;
    }
    
    stripe = Stripe(publishableKey);
    return true;
  }
  
  async function createPaymentMethod() {
    const numberInput = document.getElementById('card-number');
    const nameInput = document.getElementById('card-name');
    const expiryInput = document.getElementById('card-expiry');
    const cvvInput = document.getElementById('card-cvv');
    
    // Validação básica
    const errors = [];
    
    const number = (numberInput?.value || '').replace(/\D/g, '');
    if (number.length < 13) {
      errors.push({ field: 'card-number', message: 'Número do cartão inválido' });
    }
    
    const name = (nameInput?.value || '').trim();
    if (name.length < 3) {
      errors.push({ field: 'card-name', message: 'Nome obrigatório' });
    }
    
    const expiry = (expiryInput?.value || '').split('/');
    if (expiry.length !== 2 || expiry[0].length !== 2) {
      errors.push({ field: 'card-expiry', message: 'Data inválida' });
    }
    
    const cvv = (cvvInput?.value || '').replace(/\D/g, '');
    if (cvv.length < 3) {
      errors.push({ field: 'card-cvv', message: 'CVV inválido' });
    }
    
    // Mostra erros
    document.querySelectorAll('.card-form-container input').forEach(el => {
      el.classList.remove('is-invalid');
    });
    
    if (errors.length > 0) {
      errors.forEach(err => {
        const field = document.getElementById(err.field);
        if (field) {
          field.classList.add('is-invalid');
        }
      });
      return { error: { message: errors[0].message } };
    }
    
    // Retorna os dados do cartão para processamento
    return {
      card: {
        number: number,
        exp_month: parseInt(expiry[0], 10),
        exp_year: parseInt('20' + expiry[1], 10),
        cvc: cvv,
        name: name
      }
    };
  }
  
  async function confirmPayment(clientSecret, billingDetails) {
    if (!stripe) {
      return { error: { message: 'Stripe não inicializado' } };
    }
    
    const cardData = await createPaymentMethod();
    if (cardData.error) {
      return cardData;
    }
    
    // Usa Stripe.js para confirmar o pagamento
    const result = await stripe.confirmCardPayment(clientSecret, {
      payment_method: {
        card: {
          // Para Stripe.js, precisamos usar cardElement ou token
          // Como estamos usando campos customizados, vamos criar um token primeiro
        },
        billing_details: billingDetails || {}
      }
    });
    
    return result;
  }

  // ==========================================
  // Processamento do Pagamento (Custom)
  // ==========================================
  
  async function processPayment(options = {}) {
    const {
      apiEndpoint,
      orderId,
      clientSecret,
      publishableKey,
      billingDetails,
      onSuccess,
      onError
    } = options;
    
    showProcessing(true);
    
    try {
      // Coleta dados do cartão
      const cardData = await createPaymentMethod();
      if (cardData.error) {
        throw new Error(cardData.error.message);
      }
      
      // Se temos client_secret, usa Stripe.js
      if (clientSecret && publishableKey) {
        await initStripe(publishableKey);
        
        // Cria um PaymentMethod com Stripe Elements seria ideal,
        // mas como estamos usando campos customizados, vamos enviar para o backend
      }
      
      // Envia para o backend processar
      const payload = {
        order_id: orderId,
        card: cardData.card,
        billing_details: billingDetails
      };
      
      const response = await fetch(apiEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      
      showProcessing(false);
      
      if (data.success) {
        if (onSuccess) onSuccess(data);
        showResult('success', 'Pagamento aprovado!', 'Sua assinatura foi ativada com sucesso.');
      } else {
        throw new Error(data.message || data.error || 'Erro ao processar pagamento');
      }
      
    } catch (error) {
      showProcessing(false);
      if (onError) onError(error);
      showResult('error', 'Falha no pagamento', error.message);
    }
  }

  // ==========================================
  // UI Helpers
  // ==========================================
  
  function showProcessing(show = true) {
    const overlay = document.getElementById('processing-overlay');
    if (overlay) {
      overlay.classList.toggle('active', show);
    }
  }
  
  function showResult(type, title, message) {
    const section = document.querySelector('.transparent-checkout-section');
    if (!section) return;
    
    const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
    const iconColorClass = type === 'success' ? 'success' : 'error';
    
    section.innerHTML = `
      <div class="payment-result">
        <div class="result-icon ${iconColorClass}">
          <i class="bi ${iconClass}"></i>
        </div>
        <h4>${title}</h4>
        <p>${message}</p>
        <a href="${window.PLANS_CONFIG?.rootUrl || '/'}conta/planos" class="btn btn-primary">
          <i class="bi bi-arrow-left me-1"></i> Voltar para Planos
        </a>
      </div>
    `;
  }
  
  function showCardSection(show = true) {
    const checkoutForm = document.getElementById('checkout-form-section');
    const cardSection = document.querySelector('.transparent-checkout-section');
    
    if (checkoutForm) {
      checkoutForm.style.display = show ? 'none' : '';
    }
    
    if (cardSection) {
      cardSection.classList.toggle('active', show);
      if (show) {
        initCardFields();
        // Scroll suave para a seção
        cardSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }

  // ==========================================
  // Exposição Global
  // ==========================================
  
  window.Card3D = {
    init: initCardFields,
    initStripe: initStripe,
    showSection: showCardSection,
    processPayment: processPayment,
    showProcessing: showProcessing,
    showResult: showResult,
    setBrand: (brand) => {
      const b = (brand && typeof brand === 'string') ? brand : 'unknown';
      updateCardColor(b);
      updateCardIcon(b);
    },
    setPaymentData: (data) => { paymentData = data; },
    getPaymentData: () => paymentData
  };

})();
