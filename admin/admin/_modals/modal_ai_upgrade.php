<!-- Modal Upgrade IA (FASE T5) -->
<div id="aiUpgradeModal" class="mia-overlay hide">
  <div class="mia-modal modal-lg">
    <div class="mh">
      <div class="mh-info">
        <div class="mt">🔒 Limite de Chamadas IA Atingido</div>
        <div class="ms" id="aiUpgradeModalSubtitle">
          Você usou todas as chamadas deste mês
        </div>
      </div>
      <button class="mh-close" onclick="aiCloseUpgradeModal()">✕</button>
    </div>

    <div class="mb">
      <div class="upgrade-reason-box" style="background:#fef3c7;border:1px solid #f59e0b;padding:12px;border-radius:4px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <i class="fa fa-exclamation-triangle" style="color:#d97706;font-size:20px;"></i>
        <div style="color:#92400e;font-size:14px;">
          Sua cota mensal de chamadas IA esgotou. Adquira tokens extras para continuar usando o concierge sem interrupções.
        </div>
      </div>

      <div id="aiUpgradePackagesList" class="row">
        <!-- Populado via JS -->
        <div class="col-md-12 text-center py-4">
          <i class="fa fa-spinner fa-spin"></i> Carregando pacotes...
        </div>
      </div>

      <div style="text-align:center;margin:20px 0;color:#9ca3af;font-size:13px;position:relative;">
        <span style="background:#fff;padding:0 15px;position:relative;z-index:1;">ou</span>
        <div style="position:absolute;top:50%;left:0;right:0;height:1px;background:#e5e7eb;"></div>
      </div>

      <div style="text-align:center;">
        <a href="<?= ROOT_URL ?>conta/planos" class="btn" style="background:linear-gradient(135deg,#4c1d95,#7c3aed);color:#fff;padding:10px 25px;font-weight:600;">
          <i class="fa fa-arrow-up"></i> Ver Planos Completos
        </a>
      </div>
    </div>

    <div class="mf">
      <button class="btn btn-secondary" onclick="aiCloseUpgradeModal()">Cancelar</button>
    </div>
  </div>
</div>

<style>
.package-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.2s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.package-card:hover {
    border-color: #7c3aed;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
    transform: translateY(-2px);
}
.package-card .p-name { font-weight: 700; color: #1f2937; margin-bottom: 10px; }
.package-card .p-tokens { font-size: 24px; font-weight: 800; color: #7c3aed; margin-bottom: 5px; }
.package-card .p-price { font-size: 18px; font-weight: 600; color: #059669; margin-bottom: 15px; }
.package-card .btn-buy { margin-top: auto; width: 100%; font-weight: 600; }
</style>

<script>
window.aiShowUpgradeModal = function(reason = 'calls_exceeded') {
    const list = document.getElementById('aiUpgradePackagesList');
    list.innerHTML = '<div class="col-md-12 text-center py-4"><i class="fa fa-spinner fa-spin"></i> Carregando pacotes...</div>';
    
    fetch('<?= ROOT_URL ?>_inc/ai_token_packages_list.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.packages.length) {
                list.innerHTML = '<div class="col-md-12 text-center py-4">Nenhum pacote disponível no momento.</div>';
                return;
            }
            
            let html = '';
            data.packages.forEach(p => {
                html += `
                    <div class="col-md-4 mb-3">
                        <div class="package-card">
                            <div class="p-name">${p.name}</div>
                            <div class="p-tokens">${p.tokens_qty} <small style="font-size:12px;color:#6b7280">tokens</small></div>
                            <div class="p-price">R$ ${parseFloat(p.price).toFixed(2).replace('.',',')}</div>
                            <button class="btn btn-primary btn-buy" onclick="aiBuyTokenPackage(${p.package_id})">
                                <i class="fa fa-shopping-cart"></i> Comprar
                            </button>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        })
        .catch(err => {
            list.innerHTML = '<div class="col-md-12 text-center py-4 text-danger">Erro ao carregar pacotes.</div>';
        });

    document.getElementById('aiUpgradeModal').classList.remove('hide');
};

window.aiCloseUpgradeModal = function() {
    document.getElementById('aiUpgradeModal').classList.add('hide');
};

window.aiBuyTokenPackage = function(packageId) {
    if (!confirm('Deseja iniciar a compra deste pacote de tokens?')) return;
    
    // Bloquear botão e mostrar loading
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';

    const formData = new FormData();
    formData.append('package_id', packageId);
    formData.append('payment_method', 'pix'); // Default para Pix na modal rápida

    fetch('<?= ROOT_URL ?>_inc/ai_token_purchase.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Redirecionar para o checkout ou mostrar dados do Pix
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                alert('Pedido criado com sucesso! ID: ' + data.order_id);
                // Futuro: abrir modal de pagamento Pix aqui
            }
        } else {
            alert('Erro: ' + (data.message || 'Falha ao processar pedido.'));
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(err => {
        alert('Erro de conexão ao processar pedido.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
};
</script>
