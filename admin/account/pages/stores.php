<?php
// Seção: Gerenciar lojas da conta - Nova implementação moderna
// Integrada ao AdminLTE 4 mantendo a estrutura do sistema

global $user;
$pdo = db();

// =======================================================
// SaaS Limits Bridge (plano/limites)
// =======================================================
if (!class_exists('SaasLimitsBridge')) {
  $saasLimitsPath = ROOT . '/../saas/includes/SaasLimitsBridge.php';
  if (file_exists($saasLimitsPath)) {
    require_once $saasLimitsPath;
  }
}

$tenantIdForLimits = 0;
$tenantLimits = [];
$storesUsageTotal = 0; // total de lojas do tenant (independente de status)
$maxStores = 0;

$currentUserIsOwnerOrAdmin = (function_exists('user_group_id') && (int)user_group_id() === 1)
  || (function_exists('is_tenant_owner') && is_tenant_owner());

try {
  $sessionTid = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
  $uid = function_exists('user_id') ? (int)user_id() : 0;

  if (class_exists('SaasLimitsBridge')) {
    $tenantIdForLimits = SaasLimitsBridge::resolveTenantId($pdo, $uid, $sessionTid > 0 ? $sessionTid : null);
    if ($tenantIdForLimits > 0) {
      $tenantLimits = SaasLimitsBridge::getPlanLimits($pdo, (int)$tenantIdForLimits);
      $maxStores = (int)($tenantLimits['max_stores'] ?? 0);

      if (method_exists('SaasLimitsBridge', 'countTenantStoresTotal')) {
        $storesUsageTotal = SaasLimitsBridge::countTenantStoresTotal($pdo, (int)$tenantIdForLimits);
      }
    }
  }
} catch (Throwable $e) {
  // ignore
}

$canCreateStores = $currentUserIsOwnerOrAdmin && ($maxStores <= 0 || $storesUsageTotal < $maxStores);

// =======================================================
// Objetivo desta página
// - Exibir as lojas que o usuário atual TEM (user_to_store)
// - Se for admin e não houver vínculos em user_to_store, usar fallback (tenant_id ou todas)
// =======================================================

function column_exists($table, $column) {
  try {
    $st = db()->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $st->execute([$column]);
    return $st->rowCount() > 0;
  } catch (Throwable $e) {
    return false;
  }
}

function get_user_tenant_id_safe($user_id) {
  if (!column_exists('users', 'tenant_id')) {
    return 0;
  }

  try {
    $st = db()->prepare("SELECT `tenant_id` FROM `users` WHERE `id` = ? LIMIT 1");
    $st->execute([(int)$user_id]);
    $tmp = $st->fetchColumn();
    if ($tmp !== false && $tmp !== null && $tmp !== '') {
      return (int)$tmp;
    }
  } catch (Throwable $e) {
    return 0;
  }

  return 0;
}

function fetch_stores_by_tenant($tenant_id) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT s.* FROM `stores` s WHERE s.tenant_id = ? ORDER BY s.sort_order ASC, s.name ASC");
  $stmt->execute([(int)$tenant_id]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_all_stores() {
  $pdo = db();
  $stmt = $pdo->query("SELECT s.* FROM `stores` s ORDER BY s.sort_order ASC, s.name ASC");
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 1) Primário: lojas vinculadas ao usuário (user_to_store)
$stores = [];
try {
  $stores = $user->getBelongsStore(user_id());
} catch (Throwable $e) {
  $stores = [];
}

// 2) Fallback: admin sem vínculos -> tenta tenant_id, senão lista todas
if (empty($stores) && function_exists('user_group_id') && (int)user_group_id() === 1) {
  $tid = 0;

  // Sessão primeiro (se existir)
  if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id']) {
    $tid = (int)$_SESSION['tenant_id'];
  }

  // Depois tenta ler de users.tenant_id (se existir)
  if ($tid <= 0) {
    $tid = get_user_tenant_id_safe(user_id());
  }

  try {
    if ($tid > 0 && column_exists('stores', 'tenant_id')) {
      $stores = fetch_stores_by_tenant($tid);
    } else {
      $stores = fetch_all_stores();
    }
  } catch (Throwable $e) {
    $stores = [];
  }
}

$totalStores = is_array($stores) ? count($stores) : 0;

// Normaliza status / badge
$storesRows = [];
if (!empty($stores)) {
  foreach ($stores as $s) {
    $isActive   = !empty($s['status']);
    $statusText = $isActive ? 'Ativa' : 'Inativa';
    $statusClass= $isActive ? 'bg-success' : 'bg-danger';

    $storesRows[] = [
      'id'         => (int)$s['store_id'],
      'name'       => $s['name'] ?? '',
      'type'       => !empty($s['vat_reg_no']) ? 'Matriz' : 'Filial', // heurística simples
      'statusText' => $statusText,
      'statusClass'=> $statusClass,
      'created_at' => $s['created_at'] ?? null,
    ];
  }
}
?>
<style>
/* Reuso do mesmo visual do card de usuários (versão reduzida) */
.usage-card-premium {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
  position: relative;
  overflow: hidden;
}
.usage-card-premium::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%);
}
.usage-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.usage-card-title {
  display: flex;
  align-items: center;
  gap: 10px;
}
.usage-card-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}
.usage-card-icon.stores {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
  color: #10b981;
}
.usage-card-label {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}
.usage-card-sublabel {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}
.usage-card-badge {
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.usage-card-badge.success { background: rgba(16, 185, 129, 0.12); color: #059669; }
.usage-card-badge.warning { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.usage-card-badge.danger  { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
.usage-card-body { margin-bottom: 12px; }
.usage-card-numbers { display: flex; align-items: baseline; gap: 6px; }
.usage-card-current { font-size: 32px; font-weight: 800; color: #0f172a; line-height: 1; }
.usage-card-separator { font-size: 20px; color: #94a3b8; font-weight: 500; }
.usage-card-max { font-size: 20px; font-weight: 600; color: #64748b; }
.usage-card-unlimited {
  font-size: 14px;
  color: #10b981;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.usage-progress-container {
  height: 8px;
  background: #e2e8f0;
  border-radius: 999px;
  overflow: hidden;
}
.usage-progress-bar {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}
.usage-progress-bar.success { background: linear-gradient(90deg, #10b981 0%, #059669 100%); }
.usage-progress-bar.warning { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
.usage-progress-bar.danger  { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); }
.usage-card-footer { margin-top: 12px; display: flex; align-items: center; justify-content: space-between; }
.usage-card-percent { font-size: 13px; font-weight: 600; color: #64748b; }
</style>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6">
        <h3 class="mb-0">Gerenciar lojas</h3>
        <p class="text-secondary mb-0">
          Gerencie todas as lojas vinculadas à sua conta.
        </p>
      </div>
      <div class="col-sm-6 mt-2 mt-sm-0">
        <?php if ($tenantIdForLimits > 0 && isset($tenantLimits['max_stores'])): ?>
          <?php
            $rawPercent = $maxStores > 0 ? (($storesUsageTotal / $maxStores) * 100) : 0;
            $usagePercent = $maxStores > 0 ? min(100, $rawPercent) : 0;
            $isLimitReached = ($maxStores > 0 && $storesUsageTotal >= $maxStores);
            $usagePercentLabel = $isLimitReached ? 100 : (int)floor(min(99.0, $rawPercent));

            if ($isLimitReached) {
              $progressStatus = 'danger';
              $badgeLabel = 'Limite Atingido';
            } elseif ($rawPercent >= 90) {
              $progressStatus = 'danger';
              $badgeLabel = 'Limite Próximo';
            } elseif ($rawPercent >= 75) {
              $progressStatus = 'warning';
              $badgeLabel = 'Atenção';
            } else {
              $progressStatus = 'success';
              $badgeLabel = 'Disponível';
            }
          ?>

          <div class="usage-card-premium">
            <div class="usage-card-header">
              <div class="usage-card-title">
                <div class="usage-card-icon stores">
                  <i class="bi bi-shop"></i>
                </div>
                <div>
                  <div class="usage-card-label">Lojas cadastradas</div>
                  <div class="usage-card-sublabel">Limite do seu plano</div>
                </div>
              </div>
              <?php if ($maxStores > 0): ?>
                <span class="usage-card-badge <?php echo $progressStatus; ?>"><?php echo $badgeLabel; ?></span>
              <?php endif; ?>
            </div>

            <div class="usage-card-body">
              <div class="usage-card-numbers">
                <span class="usage-card-current"><?php echo (int)$storesUsageTotal; ?></span>
                <?php if ($maxStores > 0): ?>
                  <span class="usage-card-separator">/</span>
                  <span class="usage-card-max"><?php echo (int)$maxStores; ?></span>
                <?php else: ?>
                  <span class="usage-card-unlimited"><i class="bi bi-infinity"></i> Ilimitado</span>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($maxStores > 0): ?>
              <div class="usage-progress-container">
                <div class="usage-progress-bar <?php echo $progressStatus; ?>" style="width: <?php echo $usagePercent; ?>%"></div>
              </div>
              <div class="usage-card-footer">
                <span class="usage-card-percent"><?php echo (int)$usagePercentLabel; ?>% utilizado</span>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  // Limites para o JS bloquear abertura da modal (mesma regra do backend)
  window.ACCOUNT_IS_OWNER_OR_ADMIN = <?php echo $currentUserIsOwnerOrAdmin ? 'true' : 'false'; ?>;
  window.ACCOUNT_STORES_USED = <?php echo (int)$storesUsageTotal; ?>;
  window.ACCOUNT_STORES_MAX = <?php echo (int)$maxStores; ?>;
  window.ACCOUNT_UPGRADE_URL = '<?php echo root_url(); ?>saas/landing/index.php#pricing';
</script>

<div class="app-content">
  <div class="container-fluid">
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Lojas da conta (<?php echo (int)$totalStores; ?>)</h3>

        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
          <button
            type="button"
            class="btn btn-primary btn-sm"
            data-open-create-store-modal="1"
            <?php echo !$currentUserIsOwnerOrAdmin ? 'title="Somente Administrador ou Owner pode criar lojas"' : (!$canCreateStores ? 'title="Limite de lojas do plano atingido"' : ''); ?>
          >
            <i class="bi bi-plus"></i> Criar nova loja
          </button>

          <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.location.reload(); return false;">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
          </button>
        </div>
      </div>
      <div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome da loja</th>
              <th>Tipo</th>
              <th>Status</th>
              <th>Data de criação</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($storesRows)): ?>
            <?php foreach ($storesRows as $store): ?>
              <tr class="<?php echo $store['statusText'] === 'Inativa' ? 'opacity-75' : ''; ?>">
                <td>#<?php echo (int)$store['id']; ?></td>
                <td><?php echo htmlspecialchars($store['name']); ?></td>
                <td><?php echo htmlspecialchars($store['type']); ?></td>
                <td>
                  <span class="badge <?php echo $store['statusClass']; ?>"><?php echo $store['statusText']; ?></span>
                </td>
                <td>
                  <?php
                    if (!empty($store['created_at']) && $store['created_at'] !== '0000-00-00 00:00:00') {
                      echo date('d/m/Y H:i', strtotime($store['created_at']));
                    } else {
                      echo '-';
                    }
                  ?>
                </td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-success btn-sm btn-activate-store me-2"
                    data-store-id="<?php echo (int)$store['id']; ?>"
                    data-store-status="<?php echo $store['statusText']; ?>"
                    <?php echo $store['statusText'] === 'Inativa' ? 'disabled title="Loja desativada"' : ''; ?>
                  >
                    <i class="bi bi-box-arrow-in-right"></i> Entrar
                  </button>
                  <a
                    href="<?php echo root_url(); ?>conta/lojas/configuracoes?store_id=<?php echo (int)$store['id']; ?>"
                    class="btn btn-outline-secondary btn-sm"
                    title="Configurações da loja"
                  >
                    <i class="bi bi-pencil-square"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-secondary">Nenhuma loja cadastrada ainda.</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
</div>
</div>

<!-- Modal de aviso: não é possível excluir a última loja -->
<div class="modal fade premium-modal-info" id="lastStoreWarningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 bg-gradient-info text-white">
        <div class="d-flex align-items-center">
          <div class="premium-modal-icon bg-white text-primary me-3">
            <i class="bi bi-info-lg"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0">Atenção</h5>
            <small class="opacity-75">Você precisa manter pelo menos uma loja ativa na sua conta.</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Para continuar usando o sistema, sua conta precisa ter pelo menos uma loja cadastrada.</p>
        <p class="mb-0">Crie uma nova loja antes de excluir a atual. Assim você não perderá o acesso ao painel.</p>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-end">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
          Entendi, vou criar uma nova loja
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de confirmação de exclusão de loja (estilo premium) -->
<div class="modal fade premium-modal-danger" id="deleteStoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 bg-gradient-danger text-white">
        <div class="d-flex align-items-center">
          <div class="premium-modal-icon bg-white text-danger me-3">
            <i class="bi bi-trash3-fill"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0">Excluir loja</h5>
            <small class="opacity-75">Esta ação é permanente e não poderá ser desfeita.</small>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="premium-modal-highlight text-center mb-3">
          <p class="text-secondary mb-1">Você está prestes a excluir a loja:</p>
          <p class="h5 fw-bold mb-0" id="deleteStoreName">Nome da loja</p>
        </div>
        <div class="alert alert-warning premium-alert-soft mb-3">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <span id="deleteStoreMessage">
            Todos os dados de vendas, estoque e configurações vinculados a esta loja serão removidos de forma definitiva.
          </span>
        </div>
        <ul class="small text-secondary mb-0">
          <li>Esta operação <strong>não pode ser desfeita</strong>.</li>
          <li>Certifique-se de que não há operações pendentes antes de continuar.</li>
        </ul>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          Cancelar
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteStore">
          Sim, excluir permanentemente
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var buttons      = document.querySelectorAll('.btn-activate-store');
    var activateBaseUrl = "<?php echo root_url() . ADMINDIRNAME; ?>/dashboard.php?active_store_id=";
    var dashboardUrl    = "<?php echo root_url() . ADMINDIRNAME; ?>/dashboard.php";
    var totalStoresForUser = <?php echo (int)$totalStores; ?>;

    if (buttons.length) {
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var storeId = this.getAttribute('data-store-id');
          if (!storeId) return;

          var status = this.getAttribute('data-store-status');
          if (status === 'Inativa') {
            alert('Esta loja está desativada. Ative a loja antes de entrar.');
            return;
          }

          this.disabled = true;

          fetch(activateBaseUrl + encodeURIComponent(storeId), {
            method: 'GET',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          })
          .then(function () {
            window.location.href = dashboardUrl;
          })
          .catch(function () {
            window.location.href = dashboardUrl;
          });
        });
      });
    }

    var deleteModalEl        = document.getElementById('deleteStoreModal');
    var deleteStoreNameEl    = document.getElementById('deleteStoreName');
    var deleteStoreMessageEl = document.getElementById('deleteStoreMessage');
    var deleteConfirmBtn     = document.getElementById('confirmDeleteStore');
    var deleteStoreId        = null;
    var deleteStoreUrl       = "<?php echo root_url(); ?>_inc/store.php";
    var lastStoreModalEl     = document.getElementById('lastStoreWarningModal');

    if (typeof bootstrap !== 'undefined') {
      var deleteModal    = deleteModalEl    ? new bootstrap.Modal(deleteModalEl)    : null;
      var lastStoreModal = lastStoreModalEl ? new bootstrap.Modal(lastStoreModalEl) : null;

      var statusToggleUrl = "<?php echo root_url(); ?>_inc/store_status.php";

      function changeStoreStatus(storeId, status) {
        var formData = new URLSearchParams();
        formData.append('store_id', storeId);
        formData.append('status', status);

        return fetch(statusToggleUrl, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData
        })
        .then(function(res) {
          if (!res.ok) {
            return res.json().then(function (data) { throw data; }).catch(function () { throw { errorMsg: 'Erro ao alterar status da loja.' }; });
          }
          return res.json();
        });
      }

      var activateMenuButtons = document.querySelectorAll('.store-menu-activate');
      activateMenuButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var storeId = this.getAttribute('data-store-id');
          if (!storeId) return;
          changeStoreStatus(storeId, 1)
            .then(function () { window.location.reload(); })
            .catch(function (err) {
              var msg = (err && (err.errorMsg || err.msg)) ? (err.errorMsg || err.msg) : 'Não foi possível ativar a loja.';
              alert(msg);
            });
        });
      });

      var deactivateMenuButtons = document.querySelectorAll('.store-menu-deactivate');
      deactivateMenuButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var storeId = this.getAttribute('data-store-id');
          if (!storeId) return;
          changeStoreStatus(storeId, 0)
            .then(function () { window.location.reload(); })
            .catch(function (err) {
              var msg = (err && (err.errorMsg || err.msg)) ? (err.errorMsg || err.msg) : 'Não foi possível desativar a loja.';
              alert(msg);
            });
        });
      });

      var deleteButtons = document.querySelectorAll('.store-menu-delete');
      deleteButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (typeof totalStoresForUser !== 'undefined' && totalStoresForUser <= 1) {
            if (lastStoreModal) {
              lastStoreModal.show();
            } else {
              alert('Você precisa manter pelo menos uma loja na sua conta. Crie uma nova loja antes de excluir esta.');
            }
            return;
          }

          deleteStoreId = this.getAttribute('data-store-id');
          var storeName = this.getAttribute('data-store-name') || '';

          if (deleteStoreNameEl) {
            deleteStoreNameEl.textContent = storeName;
          }
          if (deleteStoreMessageEl) {
            deleteStoreMessageEl.textContent = 'Tem certeza que deseja excluir a loja "' + storeName + '" de forma permanente? Esta ação não poderá ser desfeita.';
          }

          deleteConfirmBtn.disabled = false;
          deleteConfirmBtn.textContent = 'Sim, excluir permanentemente';
          deleteModal.show();
        });
      });

      if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener('click', function () {
          if (!deleteStoreId) return;

          var btn = this;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Excluindo...';

          var formData = new URLSearchParams();
          formData.append('action_type', 'DELETE');
          formData.append('store_id', deleteStoreId);
          formData.append('delete_action', 'delete');
          formData.append('new_store_id', '0');

          fetch(deleteStoreUrl, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            body: formData
          })
          .then(function (res) {
            if (!res.ok) {
              return res.json().then(function (data) { throw data; }).catch(function () { throw { errorMsg: 'Erro ao excluir a loja.' }; });
            }
            return res.json();
          })
          .then(function () {
            deleteModal.hide();
            window.location.reload();
          })
          .catch(function (err) {
            btn.disabled = false;
            btn.textContent = 'Sim, excluir permanentemente';
            var msg = (err && (err.errorMsg || err.msg)) ? (err.errorMsg || err.msg) : 'Não foi possível excluir a loja.';
            alert(msg);
          });
        });
      }
    }
  });
</script>

<style>
  .premium-modal-danger .modal-content,
  .premium-modal-info .modal-content {
    border-radius: 1rem;
    overflow: hidden;
  }

  .premium-modal-danger .bg-gradient-danger {
    background: linear-gradient(135deg, #dc3545, #a61e2d);
  }

  .premium-modal-info .bg-gradient-info {
    background: linear-gradient(135deg, #0d6efd, #084298);
  }

  .premium-modal-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
  }

  .premium-modal-highlight {
    background: #f3f4f6;
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    text-align: center;
  }

  .premium-alert-soft {
    border-radius: 0.75rem;
    background: #fff9e6;
    border-color: #ffe8a3;
    color: #8a6d3b;
  }
</style>
</div>
