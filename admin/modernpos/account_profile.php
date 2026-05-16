<?php
ob_start();
session_start();
include "_init.php";

// Garante que só usuários logados acessem
if (!$user->isLogged()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

// Título do documento
$document->setTitle('Perfil da conta');

// Buscar dados reais do usuário logado
$pdo = db();
$userId = user_id();
$userData = [];
$tenantData = [];
$storesCount = 0;

try {
    // Dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Tenant ID do usuário
    $tenantId = isset($userData['tenant_id']) ? (int)$userData['tenant_id'] : 0;
    if ($tenantId <= 0 && isset($_SESSION['tenant_id'])) {
        $tenantId = (int)$_SESSION['tenant_id'];
    }
    
    // Dados do tenant
    if ($tenantId > 0) {
        $stmt = $pdo->prepare("SELECT t.*, p.name AS plan_name, p.max_stores, p.max_users, p.price_monthly
                               FROM tenants t 
                               LEFT JOIN plans p ON t.plan_id = p.plan_id 
                               WHERE t.tenant_id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenantData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // Contar lojas do tenant
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $storesCount = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Silently fail
}

// Valores para exibição
$userName = $userData['username'] ?? $user->getUserName() ?? '';
$userEmail = $userData['email'] ?? '';
$userMobile = $userData['mobile'] ?? '';
$userCpf = $userData['cpf'] ?? '';

$companyName = $tenantData['company_name'] ?? '';
$companyCnpj = $tenantData['cpf_cnpj'] ?? '';
$companySegmento = $tenantData['segmento'] ?? '';
$companyEndereco = $tenantData['endereco'] ?? '';
$companyCep = $tenantData['cep'] ?? '';

$planName = $tenantData['plan_name'] ?? 'Sem Plano';
$maxStores = $tenantData['max_stores'] ?? 1;
$subscriptionStatus = $tenantData['subscription_status'] ?? 'inactive';

// Logo do tenant (busca do tenant ou da primeira loja)
$tenantLogo = '';
$tenantLogoUrl = '';
if ($tenantId > 0) {
    // Tenta buscar logo do tenant primeiro
    if (!empty($tenantData['logo'])) {
        $tenantLogo = $tenantData['logo'];
    } else {
        // Fallback: busca da primeira loja
        try {
            $stmt = $pdo->prepare("SELECT logo FROM stores WHERE tenant_id = ? ORDER BY store_id ASC LIMIT 1");
            $stmt->execute([$tenantId]);
            $tenantLogo = $stmt->fetchColumn() ?: '';
        } catch (Exception $e) {}
    }
}

if (!empty($tenantLogo) && $tenantLogo !== 'sem-foto.jpg') {
    $tenantLogoUrl = root_url() . 'assets/itsolution24/img/logo-favicons/' . $tenantLogo;
} else {
    $tenantLogoUrl = '';
}

// Data de renovação
$renewalDate = '';
if (!empty($tenantData['subscription_expires_at'])) {
    $renewalDate = date('d/m/Y', strtotime($tenantData['subscription_expires_at']));
} elseif (!empty($tenantData['trial_ends_at'])) {
    $renewalDate = date('d/m/Y', strtotime($tenantData['trial_ends_at']));
}

// Status formatado
$statusText = 'Inativa';
$statusClass = 'bg-secondary';
switch ($subscriptionStatus) {
    case 'active':
        $statusText = 'Ativa';
        $statusClass = 'bg-success';
        break;
    case 'trial':
        $statusText = 'Trial';
        $statusClass = 'bg-warning text-dark';
        break;
    case 'past_due':
        $statusText = 'Pendente';
        $statusClass = 'bg-danger';
        break;
}

?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>Perfil da conta - ModernPOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <base href="<?php echo rtrim(root_url(), '/'); ?>/AdminLTE-4.0.0-rc4/dist/" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="./css/adminlte.css" />
    <style>
      .app-sidebar[data-bs-theme="dark"] { background-color: #222d32 !important; }
      .app-sidebar .brand-link { background-color: #1a2226; border-bottom: 1px solid #4b545c; }
      .app-sidebar .nav-link { color: #b8c7ce; font-size: 0.88rem; }
      .app-sidebar .nav-icon { color: #9effd3; }
      .app-sidebar .nav-link.active, .app-sidebar .nav-link:hover { background-color: #1e282c; color: #fff; }
      .profile-label { font-size: 0.85rem; color: #6c757d; margin-bottom: 0.15rem; }
      .profile-value { font-size: 0.95rem; font-weight: 500; }
      .profile-section-title { font-size: 0.9rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
      .card-security { background-color: #f8fafc; border-color: #b6d4fe; }
      .card-security .card-header { background-color: #e9f2ff; border-bottom-color: #b6d4fe; }
      .logo-upload-wrapper {
        width: 120px; height: 120px; border: 2px dashed #dee2e6; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
        transition: all 0.3s ease; position: relative; overflow: hidden; background-color: #f8f9fa;
      }
      .logo-upload-wrapper:hover { border-color: #0d6efd; background-color: #e9ecef; }
      .logo-upload-wrapper img { width: 100%; height: 100%; object-fit: cover; display: none; }
      .logo-upload-wrapper.has-logo img { display: block; }
      .logo-upload-wrapper.has-logo .logo-placeholder { display: none; }
      .logo-placeholder { text-align: center; color: #6c757d; }
      .logo-remove-btn {
        position: absolute; top: 5px; right: 5px; background: #dc3545; color: white;
        border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 14px;
        cursor: pointer; display: none; z-index: 10; align-items: center; justify-content: center;
      }
      .logo-upload-wrapper.has-logo .logo-remove-btn { display: flex; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li>
            <li class="nav-item d-none d-md-block"><span class="nav-link">Perfil da conta</span></li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <?php if (!empty($tenantLogoUrl)): ?>
                  <img src="<?php echo htmlspecialchars($tenantLogoUrl); ?>" class="user-image rounded-circle shadow" alt="Logo" style="width: 32px; height: 32px; object-fit: cover;" />
                <?php else: ?>
                  <div class="user-image rounded-circle shadow bg-secondary d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bi bi-person-fill text-white"></i>
                  </div>
                <?php endif; ?>
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($userName); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <?php if (!empty($tenantLogoUrl)): ?>
                    <img src="<?php echo htmlspecialchars($tenantLogoUrl); ?>" class="rounded-circle shadow" alt="Logo" style="width: 90px; height: 90px; object-fit: cover;" />
                  <?php else: ?>
                    <div class="rounded-circle shadow bg-secondary d-inline-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                      <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                    </div>
                  <?php endif; ?>
                  <p><?php echo htmlspecialchars($userName); ?><small><?php echo htmlspecialchars($user->getRole()); ?></small></p>
                </li>
                <li class="user-footer">
                  <a href="<?php echo root_url(); ?>store_select.php" class="btn btn-default btn-flat">Painel de lojas</a>
                  <a href="<?php echo root_url(); ?>admin/logout.php" class="btn btn-default btn-flat float-end">Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
          <a href="<?php echo root_url(); ?>store_select.php" class="brand-link">
            <?php if (!empty($tenantLogoUrl)): ?>
              <img src="<?php echo htmlspecialchars($tenantLogoUrl); ?>" alt="Logo" class="brand-image opacity-75 shadow" style="object-fit: cover;" />
            <?php else: ?>
              <img src="./assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow" />
            <?php endif; ?>
            <span class="brand-text fw-light">ModernPOS Conta</span>
          </a>
        </div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation">
              <li class="nav-item"><a href="<?php echo root_url(); ?>store_select.php" class="nav-link"><i class="nav-icon bi bi-speedometer2"></i><p>Visão Geral</p></a></li>
              <li class="nav-item"><a href="<?php echo root_url(); ?>store_select.php?view=overview" class="nav-link"><i class="nav-icon bi bi-shop"></i><p>Lojas</p></a></li>
              <li class="nav-item"><a href="<?php echo root_url(); ?>account_plans.php" class="nav-link"><i class="nav-icon bi bi-credit-card-2-front"></i><p>Assinatura &amp; Planos</p></a></li>
              <li class="nav-item"><a href="<?php echo root_url(); ?>account_users.php" class="nav-link"><i class="nav-icon bi bi-people"></i><p>Usuários da conta</p></a></li>
              <li class="nav-item"><a href="<?php echo root_url(); ?>account_reports.php" class="nav-link"><i class="nav-icon bi bi-bar-chart"></i><p>Relatórios</p></a></li>
              <li class="nav-item"><a href="<?php echo root_url(); ?>account_profile.php" class="nav-link active"><i class="nav-icon bi bi-person-circle"></i><p>Perfil da conta</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row align-items-center">
              <div class="col-sm-6">
                <h3 class="mb-0">Perfil da conta</h3>
                <p class="text-secondary mb-0">Gerencie os dados da sua conta e empresa.</p>
              </div>
              <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                <button type="button" class="btn btn-primary btn-sm" id="btnEditProfile"><i class="bi bi-pencil-square"></i> Editar dados</button>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            <div class="row g-3">
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-body text-center">
                    <div class="mb-3 d-flex flex-column align-items-center">
                      <label for="inputLogoProfile" class="logo-upload-wrapper mx-auto <?php echo !empty($tenantLogoUrl) ? 'has-logo' : ''; ?>" id="logoWrapperProfile" title="Clique para alterar a logo">
                        <button type="button" class="logo-remove-btn" id="btnRemoveLogoProfile">&times;</button>
                        <img src="<?php echo !empty($tenantLogoUrl) ? htmlspecialchars($tenantLogoUrl) : ''; ?>" class="logo-preview" id="logoPreviewProfile" alt="Logo">
                        <div class="logo-placeholder">
                          <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 2rem;"></i>
                          <span class="small text-muted d-block mt-1">Enviar logo</span>
                        </div>
                        <input type="file" id="inputLogoProfile" class="d-none" accept="image/*">
                      </label>
                      <div class="small text-muted mt-2 text-center" id="logoFileNameProfile"></div>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2 d-none" id="btnSaveLogoProfile"><i class="bi bi-cloud-upload"></i> Salvar logo</button>
                    </div>
                    <h4 class="mb-0"><?php echo htmlspecialchars($userName); ?></h4>
                    <p class="text-secondary mb-2"><?php echo htmlspecialchars($companyName ?: 'Empresa'); ?></p>
                    <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    <hr class="my-4" />
                    <div class="text-start small">
                      <div class="mb-3"><div class="profile-label">WhatsApp</div><div class="profile-value"><?php echo htmlspecialchars($userMobile ?: '-'); ?></div></div>
                      <div class="mb-3"><div class="profile-label">Nome da Empresa</div><div class="profile-value"><?php echo htmlspecialchars($companyName ?: '-'); ?></div></div>
                      <div class="mb-3"><div class="profile-label">CPF</div><div class="profile-value"><?php echo htmlspecialchars($userCpf ?: '-'); ?></div></div>
                      <div class="mb-3"><div class="profile-label">CNPJ</div><div class="profile-value"><?php echo htmlspecialchars($companyCnpj ?: '-'); ?></div></div>
                      <hr class="my-3" />
                      <div class="mb-2">
                        <div class="profile-label">Plano atual</div>
                        <div class="profile-value"><?php echo htmlspecialchars($planName); ?> &mdash; até <?php echo (int)$maxStores; ?> loja<?php echo $maxStores > 1 ? 's' : ''; ?></div>
                        <?php if (!empty($renewalDate)): ?><small class="text-success">Renovação em <?php echo $renewalDate; ?></small><?php endif; ?>
                      </div>
                      <div class="mb-0"><div class="profile-label">Uso da conta</div><div class="profile-value"><?php echo (int)$storesCount; ?> de <?php echo (int)$maxStores; ?> lojas ativas</div></div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-8">
                <form id="account-profile-form">
                  <input type="hidden" name="tenant_id" value="<?php echo (int)$tenantId; ?>">
                  <input type="hidden" name="user_id" value="<?php echo (int)$userId; ?>">

                  <div class="card mb-3">
                    <div class="card-header"><span class="profile-section-title">Dados do responsável</span></div>
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nome completo</label><input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($userName); ?>"></div>
                        <div class="col-md-6"><label class="form-label">CPF</label><input type="text" class="form-control" name="cpf" value="<?php echo htmlspecialchars($userCpf); ?>"></div>
                        <div class="col-md-6"><label class="form-label">WhatsApp</label><input type="text" class="form-control" name="whatsapp" value="<?php echo htmlspecialchars($userMobile); ?>"></div>
                        <div class="col-md-6"><label class="form-label">E-mail</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($userEmail); ?>"></div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-3">
                    <div class="card-header"><span class="profile-section-title">Dados da empresa / negócio</span></div>
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nome da empresa</label><input type="text" class="form-control" name="nome_empresa" value="<?php echo htmlspecialchars($companyName); ?>"></div>
                        <div class="col-md-6"><label class="form-label">CNPJ</label><input type="text" class="form-control" name="cnpj" value="<?php echo htmlspecialchars($companyCnpj); ?>"></div>
                        <div class="col-md-6">
                          <label class="form-label">Segmento</label>
                          <select class="form-select" name="segmento">
                            <option value="">Selecione...</option>
                            <option value="Varejo" <?php echo $companySegmento === 'Varejo' ? 'selected' : ''; ?>>Varejo</option>
                            <option value="Serviços" <?php echo $companySegmento === 'Serviços' ? 'selected' : ''; ?>>Serviços</option>
                            <option value="Alimentação" <?php echo $companySegmento === 'Alimentação' ? 'selected' : ''; ?>>Alimentação</option>
                            <option value="Outros" <?php echo $companySegmento === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                          </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">CEP</label><input type="text" class="form-control" name="cep" value="<?php echo htmlspecialchars($companyCep); ?>"></div>
                        <div class="col-12"><label class="form-label">Endereço</label><input type="text" class="form-control" name="endereco" value="<?php echo htmlspecialchars($companyEndereco); ?>"></div>
                      </div>
                    </div>
                  </div>

                  <div class="card mb-3 card-security">
                    <div class="card-header"><span class="profile-section-title">Segurança / senha de acesso</span></div>
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Senha atual</label><input type="password" class="form-control" name="senha_atual"></div>
                        <div class="col-md-4"><label class="form-label">Nova senha</label><input type="password" class="form-control" name="nova_senha" minlength="6"></div>
                        <div class="col-md-4"><label class="form-label">Confirmar nova senha</label><input type="password" class="form-control" name="confirmar_senha"></div>
                      </div>
                      <small class="text-muted d-block mt-2">Para alterar a senha, informe a senha atual e a nova senha (mínimo 6 caracteres).</small>
                    </div>
                  </div>

                  <div class="d-flex justify-content-end gap-2 mb-3">
                    <button type="button" class="btn btn-secondary btn-sm d-none" id="btnCancelProfile">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm d-none" id="btnSaveProfile">Salvar alterações</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <footer class="app-footer">
          <strong>ModernPOS</strong> &mdash; painel de conta.
        </footer>
      </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="./js/adminlte.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('account-profile-form');
        const btnEdit = document.getElementById('btnEditProfile');
        const btnSave = document.getElementById('btnSaveProfile');
        const btnCancel = document.getElementById('btnCancelProfile');

        if (form && btnEdit && btnSave && btnCancel) {
          const fields = form.querySelectorAll('input, select, textarea');
          function setEditable(edit) {
            fields.forEach(el => { if (el.type !== 'hidden') el.disabled = !edit; });
            btnSave.classList.toggle('d-none', !edit);
            btnCancel.classList.toggle('d-none', !edit);
            btnEdit.classList.toggle('d-none', edit);
          }
          setEditable(false);
          btnEdit.addEventListener('click', () => setEditable(true));
          btnCancel.addEventListener('click', () => { form.reset(); setEditable(false); });

          form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            fetch('<?php echo root_url(); ?>_inc/account_profile_save.php', { method: 'POST', body: formData })
              .then(res => res.json())
              .then(data => {
                if (data.success) { alert('Dados salvos com sucesso!'); window.location.reload(); }
                else { alert(data.error || 'Erro ao salvar dados.'); }
              })
              .catch(() => alert('Erro ao salvar dados.'));
          });
        }

        // Upload de Logo
        const inputLogo = document.getElementById('inputLogoProfile');
        const logoWrapper = document.getElementById('logoWrapperProfile');
        const logoPreview = document.getElementById('logoPreviewProfile');
        const logoFileName = document.getElementById('logoFileNameProfile');
        const btnRemoveLogo = document.getElementById('btnRemoveLogoProfile');
        const btnSaveLogo = document.getElementById('btnSaveLogoProfile');
        let selectedFile = null, removeLogoFlag = false;

        if (inputLogo && logoWrapper) {
          inputLogo.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
              selectedFile = file;
              removeLogoFlag = false;
              const reader = new FileReader();
              reader.onload = e => {
                logoPreview.src = e.target.result;
                logoWrapper.classList.add('has-logo');
                btnSaveLogo.classList.remove('d-none');
              };
              reader.readAsDataURL(file);
              logoFileName.textContent = file.name;
            }
          });

          if (btnRemoveLogo) {
            btnRemoveLogo.addEventListener('click', function(e) {
              e.preventDefault(); e.stopPropagation();
              logoPreview.src = '';
              logoWrapper.classList.remove('has-logo');
              inputLogo.value = '';
              logoFileName.textContent = '';
              selectedFile = null;
              removeLogoFlag = true;
              btnSaveLogo.classList.remove('d-none');
            });
          }

          if (btnSaveLogo) {
            btnSaveLogo.addEventListener('click', function() {
              const formData = new FormData();
              formData.append('tenant_id', '<?php echo (int)$tenantId; ?>');
              if (removeLogoFlag) formData.append('remove_logo', '1');
              else if (selectedFile) formData.append('logo', selectedFile);
              else return;

              btnSaveLogo.disabled = true;
              btnSaveLogo.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

              fetch('<?php echo root_url(); ?>_inc/account_logo_save.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                  if (data.success) { alert('Logo atualizada com sucesso!'); window.location.reload(); }
                  else { alert(data.error || 'Erro ao salvar logo.'); btnSaveLogo.disabled = false; btnSaveLogo.innerHTML = '<i class="bi bi-cloud-upload"></i> Salvar logo'; }
                })
                .catch(() => { alert('Erro ao salvar logo.'); btnSaveLogo.disabled = false; btnSaveLogo.innerHTML = '<i class="bi bi-cloud-upload"></i> Salvar logo'; });
            });
          }
        }
      });
    </script>
  </body>
</html>
