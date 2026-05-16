<?php
// Modais globais do painel /conta (Criar Loja + Importação + Avisos)
// Exibidos em /conta e /conta/lojas
?>

<!-- Modal Premium: Criar nova loja -->
<div id="accountCreateStoreModal" class="modal-premium-overlay" data-modal="create-store">
  <div class="modal-premium-card" style="max-width: 760px;">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-shop"></i>
        Criar nova loja
      </h4>
      <button class="btn-close-premium" type="button" onclick="fecharModalPremium('create-store')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="modal-premium-body">
      <form id="accountCreateStoreForm" novalidate>
        <input type="hidden" name="country" value="BR" />
        <input type="hidden" name="import_source_store_id" value="" />
        <input type="hidden" name="import_product_ids" value="" />

        <div class="form-group-premium">
          <label class="label-premium">
            <i class="bi bi-tag ic-modal"></i>
            Nome da Loja <span class="text-danger">*</span>
          </label>
          <input type="text" class="input-premium" name="name" placeholder="Ex.: Loja Centro" required maxlength="120" />
        </div>

        <div class="form-row-premium">
          <div class="form-group-premium">
            <label class="label-premium">
              <i class="bi bi-telephone ic-modal"></i>
              Telefone <span class="text-danger">*</span>
            </label>
            <input type="text" class="input-premium" name="mobile" placeholder="(00) 00000-0000" required maxlength="40" />
          </div>

          <div class="form-group-premium">
            <label class="label-premium">
              <i class="bi bi-envelope ic-modal"></i>
              E-mail <span class="text-danger">*</span>
            </label>
            <input type="email" class="input-premium" name="email" placeholder="contato@minhaloja.com" required maxlength="120" />
          </div>
        </div>

        <div class="form-row-premium">
          <div class="form-group-premium">
            <label class="label-premium">
              <i class="bi bi-geo-alt ic-modal"></i>
              CEP <span class="text-danger">*</span>
            </label>
            <input type="text" class="input-premium" name="zip_code" placeholder="00000-000" required maxlength="15" />
          </div>

          <div class="form-group-premium">
            <label class="label-premium">
              <i class="bi bi-map ic-modal"></i>
              Endereço <span class="text-danger">*</span>
            </label>
            <input type="text" class="input-premium" name="address" placeholder="Rua, número, bairro" required maxlength="190" />
          </div>
        </div>

        <span class="form-section-title">Produtos (opcional)</span>
        <div class="form-group-premium">
          <div class="p-3 mp-import-trigger" data-account-create-store-import-open="1" title="Clique para importar produtos">
            <div class="d-flex align-items-center gap-3">
              <div class="mp-modal-icon bg-white text-primary" style="width: 2.5rem; height: 2.5rem; box-shadow:none;">
                <i class="bi bi-box-seam" style="font-size: 1.1rem"></i>
              </div>
              <div class="flex-grow-1">
                <div class="fw-semibold">Importar produtos de outra loja</div>
                <div class="small text-secondary">
                  <span data-account-create-store-import-summary="1">Nenhum produto selecionado.</span>
                </div>
              </div>
              <div class="text-primary fw-semibold">Escolher</div>
            </div>
          </div>
          <div class="small text-secondary mt-2">Você pode copiar os produtos de uma loja já existente.</div>
        </div>

        <span class="form-section-title">Logo (opcional)</span>
        <div class="form-group-premium">
          <div class="p-3 mp-dropzone" data-account-create-store-logo-dropzone="1" title="Clique para selecionar ou arraste a imagem">
            <div class="d-flex gap-3 align-items-center">
              <div class="mp-logo-preview border">
                <div data-account-create-store-logo-empty="1" style="display:flex; align-items:center; justify-content:center; width:100%; height:100%;">
                  <i class="bi bi-image text-secondary" style="font-size:1.5rem"></i>
                </div>
                <img data-account-create-store-logo-preview="1" alt="Logo" style="display:none;" />
              </div>

              <div class="flex-grow-1">
                <div class="fw-semibold">Envie a logo da loja</div>
                <div class="small text-secondary">PNG/JPG até 2MB</div>
                <div class="small mt-1 text-secondary" data-account-create-store-logo-filename="1"></div>

                <div class="mt-2 d-flex gap-2 flex-wrap">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-account-create-store-logo-pick="1">
                    <i class="bi bi-upload"></i> Escolher imagem
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger" style="display:none;" data-account-create-store-logo-remove="1">
                    <i class="bi bi-x-circle"></i> Remover
                  </button>
                </div>

                <input type="file" class="d-none" accept="image/*" name="logo_file" data-account-create-store-logo-input="1" />
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="modal-premium-footer">
      <button type="button" class="btn-cancel-premium" onclick="fecharModalPremium('create-store')">Cancelar</button>
      <button type="button" class="btn-save-premium" id="accountCreateStoreSubmit">
        <i class="bi bi-plus-circle"></i>
        Criar Loja
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Importar Produtos -->
<div id="accountImportProductsModal" class="modal-premium-overlay" data-modal="import-products">
  <div class="modal-premium-card" style="max-width: 860px;">
    <div class="modal-premium-header">
      <h4 class="modal-premium-title">
        <i class="bi bi-box-seam"></i>
        Importar produtos
      </h4>
      <button class="btn-close-premium" type="button" data-account-import-close="1">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="modal-premium-body" style="max-height: 70vh; overflow-y: auto;">
      <div class="form-group-premium">
        <label class="label-premium">
          <i class="bi bi-shop-window ic-modal"></i>
          Escolher Loja
        </label>
        <select class="input-premium" style="padding-right: 40px;" data-account-import-source-store="1">
          <option value="">Carregando...</option>
        </select>
      </div>

      <div class="form-row-premium" style="grid-template-columns: 1fr auto; align-items: end; gap: 12px;">
        <div class="form-group-premium" style="margin-bottom: 0;">
          <label class="label-premium">
            <i class="bi bi-search ic-modal"></i>
            Buscar produto
          </label>
          <input type="text" class="input-premium" placeholder="Buscar produto..." data-account-import-products-search="1" />
        </div>
        <div class="form-group-premium" style="margin-bottom: 0;">
          <label class="label-premium" style="opacity: 0;">&nbsp;</label>
          <div class="form-check" style="margin: 0;">
            <input class="form-check-input" type="checkbox" id="accountImportSelectAll" data-account-import-products-select-all="1" />
            <label class="form-check-label" for="accountImportSelectAll">Selecionar todos</label>
          </div>
        </div>
      </div>

      <div class="list-group mp-product-list" style="max-height: 360px; overflow:auto; border-radius: 14px;" data-account-import-products-list="1">
        <div class="list-group-item small text-secondary">Selecione uma loja para carregar os produtos.</div>
      </div>
    </div>

    <div class="modal-premium-footer">
      <button type="button" class="btn-cancel-premium" data-account-import-back="1">
        Voltar
      </button>
      <button type="button" class="btn-save-premium" data-account-import-products-apply="1">
        <i class="bi bi-check2"></i>
        Aplicar seleção
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Bloqueio / Upgrade (limite de lojas do plano) -->
<div id="modalUpgradeStoresPremium" class="modal-premium-overlay" data-modal="upgrade-stores">
  <div class="modal-premium-card" style="max-width: 520px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-stars"></i>
        <span id="upgradeStoresModalTitle">Limite Atingido</span>
      </h4>
      <button class="btn-close-premium" type="button" onclick="fecharModalPremium('upgrade-stores')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div style="display:flex; gap:14px; align-items:flex-start;">
        <div style="width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; background: rgba(249,115,22,0.12); color:#c2410c; flex: 0 0 auto;">
          <i class="bi bi-shield-lock-fill" style="font-size:22px;"></i>
        </div>
        <div>
          <div id="upgradeStoresModalMessage" style="font-size: 14px; color:#334155; font-weight:600; line-height:1.35;">
            Você atingiu o limite de lojas do seu plano.
          </div>
          <div style="margin-top: 10px; font-size: 13px; color:#64748b;">
            Uso atual: <b id="upgradeStoresModalUsage">0/0</b>
          </div>
          <div style="margin-top: 14px; background: #fff7ed; border: 1px solid rgba(249,115,22,0.25); border-radius: 12px; padding: 12px 14px;">
            <div style="display:flex; gap:10px; align-items:flex-start; font-size: 13px; color:#9a3412;">
              <i class="bi bi-info-circle" style="margin-top: 2px;"></i>
              <div>
                Para liberar mais lojas, faça upgrade do seu plano. A atualização é imediata e mantém seus dados.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: space-between;">
      <button type="button" class="btn-cancel-premium" onclick="fecharModalPremium('upgrade-stores')">Fechar</button>
      <button type="button" class="btn-save-premium" onclick="goToUpgradePlans()">
        <i class="bi bi-arrow-up-right-circle"></i> Fazer Upgrade
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Ação restrita (somente Administrador/Owner) -->
<div id="modalStoresAdminOnlyPremium" class="modal-premium-overlay" data-modal="stores-admin-only">
  <div class="modal-premium-card" style="max-width: 520px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #0f172a 0%, #334155 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-shield-lock"></i>
        Ação restrita
      </h4>
      <button class="btn-close-premium" type="button" onclick="fecharModalPremium('stores-admin-only')">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div style="display:flex; gap:12px; align-items:flex-start;">
        <div style="width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; background: rgba(15,23,42,0.10); color:#0f172a; flex: 0 0 auto;">
          <i class="bi bi-person-badge" style="font-size:22px;"></i>
        </div>
        <div>
          <div id="storesAdminOnlyMessage" style="font-size: 14px; color:#0f172a; font-weight:700; line-height:1.35;">
            Somente Administrador ou Owner pode criar lojas.
          </div>
          <div style="margin-top: 8px; font-size: 13px; color:#64748b;">
            Solicite ao Administrador/Owner da conta se você precisar desta ação.
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;">
      <button type="button" class="btn-save-premium" onclick="fecharModalPremium('stores-admin-only')">
        Entendi
      </button>
    </div>
  </div>
</div>

<!-- Modal Premium: Acesso negado (ex.: configurações da loja) -->
<div id="modalAccountAccessDeniedPremium" class="modal-premium-overlay" data-modal="account-access-denied">
  <div class="modal-premium-card" style="max-width: 520px;">
    <div class="modal-premium-header" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
      <h4 class="modal-premium-title">
        <i class="bi bi-shield-exclamation"></i>
        Acesso negado
      </h4>
      <button class="btn-close-premium" type="button" onclick="closeAccountAccessDeniedModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-premium-body">
      <div style="display:flex; gap:12px; align-items:flex-start;">
        <div style="width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; background: rgba(239,68,68,0.12); color:#b91c1c; flex: 0 0 auto;">
          <i class="bi bi-lock-fill" style="font-size:22px;"></i>
        </div>
        <div>
          <div id="accountAccessDeniedMessage" style="font-size: 14px; color:#0f172a; font-weight:700; line-height:1.35;">
            Você não tem permissão para acessar esta página.
          </div>
          <div style="margin-top: 8px; font-size: 13px; color:#64748b;">
            Se você acredita que isso é um erro, entre em contato com o Administrador/Owner da conta.
          </div>
        </div>
      </div>
    </div>
    <div class="modal-premium-footer" style="justify-content: center;">
      <button type="button" class="btn-save-premium" onclick="closeAccountAccessDeniedModal()">
        Entendi
      </button>
    </div>
  </div>
</div>
