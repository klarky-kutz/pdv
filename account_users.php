<?php
// Página: Usuários da Conta (Painel da Conta / AdminLTE 4)
// Acessível apenas para administradores de conta.

ob_start();
session_start();
require_once "_init.php";

if (!$user->isLogged()) {
  redirect(root_url() . 'index.php?redirect_to=' . url());
}

// Apenas administradores de conta
if (user_group_id() != 1) {
  redirect(root_url() . 'store_select.php');
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'usuarios';
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>Usuários da Conta - ModernPOS (Conta)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta
      name="description"
      content="Gerencie usuários com acesso às lojas da conta ModernPOS e suas permissões gerais."
    />

    <?php // Caminho base para AdminLTE 4 dist ?>
    <base href="<?php echo rtrim(root_url(), '/'); ?>/AdminLTE-4.0.0-rc4/dist/" />

    <!-- Fonte padrão do AdminLTE 4 -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
      crossorigin="anonymous"
      media="print"
      onload="this.media='all'"
    />

    <!-- OverlayScrollbars -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
      crossorigin="anonymous"
    />

    <!-- Bootstrap Icons -->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
      crossorigin="anonymous"
    />

    <!-- CSS principal do AdminLTE 4 -->
    <link rel="preload" href="./css/adminlte.css" as="style" />
    <link rel="stylesheet" href="./css/adminlte.css" />

    <!-- Font Awesome para ícones extras -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    />

    <style>
      :root {
        --lte-sidebar-width: 230px;
        --lte-sidebar-mini-width: 60px;
      }

      /* Sidebar estilo ModernPOS */
      .app-sidebar[data-bs-theme="dark"] {
        background-color: #222d32 !important;
      }

      .app-sidebar .brand-link {
        background-color: #1a2226;
        border-bottom: 1px solid #4b545c;
      }

      .app-sidebar .sidebar-menu .nav-link,
      .app-sidebar .nav-link {
        color: #b8c7ce;
        font-size: 0.88rem;
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
      }

      .app-sidebar .nav-icon {
        color: #9effd3;
        font-size: 1rem;
      }

      .app-sidebar .nav-link.active,
      .app-sidebar .nav-link:hover {
        background-color: #1e282c;
        color: #ffffff;
      }

      .app-sidebar .nav-link .nav-icon,
      .app-sidebar .nav-link.active .nav-icon,
      .app-sidebar .nav-link:hover .nav-icon {
        color: #9effd3;
      }

      .app-sidebar .nav-treeview {
        border-left: 1px solid #3c4b52;
        margin-left: 0.75rem;
        padding-left: 0.25rem;
      }

      .app-sidebar .nav-treeview > .nav-item > .nav-link {
        position: relative;
        padding-left: 1.5rem;
        font-size: 0.82rem;
      }

      .app-sidebar .nav-treeview > .nav-item > .nav-link::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        width: 10px;
        height: 1px;
        background-color: #3c4b52;
        transform: translateY(-50%);
      }

      .app-sidebar .nav-treeview .nav-link {
        background-color: transparent;
      }

      .app-sidebar .nav-treeview .nav-link.active,
      .app-sidebar .nav-treeview .nav-link:hover {
        background-color: #1e282c;
        color: #ffffff;
      }

      .app-sidebar .nav-treeview .nav-icon.bi-circle {
        display: none;
      }

      /* Tabs locais */
      .account-tabs .nav-link {
        border-radius: 0;
      }

      .account-tabs .nav-link.active {
        background-color: #3c8dbc;
        color: #fff;
      }

      .badge-role {
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 11px;
      }

      .badge-role-admin {
        background-color: #00a65a;
        color: #fff;
      }

      .badge-role-gerente {
        background-color: #3c8dbc;
        color: #fff;
      }

      .badge-role-colaborador {
        background-color: #f39c12;
        color: #fff;
      }

      .user-avatar-initials {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #3c8dbc;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
      }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
      <!-- Navbar topo (reutiliza padrão do painel de conta) -->
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list"></i>
              </a>
            </li>
            <li class="nav-item d-none d-md-block">
              <span class="nav-link">Painel da Conta</span>
            </li>
          </ul>

          <ul class="navbar-nav ms-auto">
            <!-- Ícones de mensagem / notificações poderiam ser reaproveitados aqui se desejar -->
            <!-- Mensagens -->
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#">
                <i class="bi bi-chat-text"></i>
                <span class="navbar-badge badge text-bg-danger">3</span>
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <a href="#" class="dropdown-item">
                  <div class="d-flex">
                    <div class="flex-shrink-0">
                      <img
                        src="./assets/img/user1-128x128.jpg"
                        alt="User Avatar"
                        class="img-size-50 rounded-circle me-3"
                      />
                    </div>
                    <div class="flex-grow-1">
                      <h3 class="dropdown-item-title">
                        Suporte ModernPOS
                        <span class="float-end fs-7 text-danger">
                          <i class="bi bi-star-fill"></i>
                        </span>
                      </h3>
                      <p class="fs-7">Bem-vindo ao painel da conta!</p>
                      <p class="fs-7 text-secondary">
                        <i class="bi bi-clock-fill me-1"></i> há 4 horas
                      </p>
                    </div>
                  </div>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <div class="d-flex">
                    <div class="flex-shrink-0">
                      <img
                        src="./assets/img/user8-128x128.jpg"
                        alt="User Avatar"
                        class="img-size-50 rounded-circle me-3"
                      />
                    </div>
                    <div class="flex-grow-1">
                      <h3 class="dropdown-item-title">
                        Equipe Financeira
                        <span class="float-end fs-7 text-secondary">
                          <i class="bi bi-star-fill"></i>
                        </span>
                      </h3>
                      <p class="fs-7">Sua fatura deste mês já está disponível.</p>
                      <p class="fs-7 text-secondary">
                        <i class="bi bi-clock-fill me-1"></i> há 1 dia
                      </p>
                    </div>
                  </div>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">Ver todas as mensagens</a>
              </div>
            </li>
            <!-- Notificações -->
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#">
                <i class="bi bi-bell-fill"></i>
                <span class="navbar-badge badge text-bg-warning">15</span>
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <span class="dropdown-item dropdown-header">15 notificações</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <i class="bi bi-bar-chart me-2"></i> Novo relatório consolidado disponível
                  <span class="float-end text-secondary fs-7">agora</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <i class="bi bi-shop me-2"></i> Loja Shopping atingiu 100 vendas hoje
                  <span class="float-end text-secondary fs-7">há 2 h</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                  <i class="bi bi-credit-card-2-front me-2"></i> Pagamento da assinatura confirmado
                  <span class="float-end text-secondary fs-7">há 1 dia</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">Ver todas as notificações</a>
              </div>
            </li>
            <!-- Usuário -->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img
                  src="./assets/img/user2-160x160.jpg"
                  class="user-image rounded-circle shadow"
                  alt="User Image"
                />
                <span class="d-none d-md-inline">
                  <?php echo isset($user) ? htmlspecialchars($user->getUserName()) : 'Usuário'; ?>
                </span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img
                    src="./assets/img/user2-160x160.jpg"
                    class="rounded-circle shadow"
                    alt="User Image"
                  />
                  <p>
                    <?php echo isset($user) ? htmlspecialchars($user->getUserName()) : 'Usuário ModernPOS'; ?>
                    <small>
                      <?php echo isset($user) ? htmlspecialchars($user->getRole()) : 'Administrador'; ?>
                    </small>
                  </p>
                </li>
                <li class="user-footer">
                  <a
                    href="<?php echo root_url(); ?>/account_profile.php"
                    class="btn btn-default btn-flat"
                  >Perfil</a>
                  <a
                    href="<?php echo root_url(); ?>/logout.php"
                    class="btn btn-default btn-flat float-end"
                  >Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Sidebar -->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
          <a href="<?php echo root_url(); ?>/store_select.php" class="brand-link">
            <img
              src="./assets/img/AdminLTELogo.png"
              alt="Logo"
              class="brand-image opacity-75 shadow"
            />
            <span class="brand-text fw-light">ModernPOS Conta</span>
          </a>
        </div>

        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              role="navigation"
              aria-label="Main navigation"
              data-accordion="false"
            >
              <li class="nav-item">
                <a href="<?php echo root_url(); ?>/store_select.php" class="nav-link">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Visão Geral</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo root_url(); ?>/store_select.php" class="nav-link">
                  <i class="nav-icon bi bi-shop"></i>
                  <p>
                    Lojas
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo root_url(); ?>/account_plans.php?tab=plano_atual" class="nav-link">
                  <i class="nav-icon bi bi-credit-card-2-front"></i>
                  <p>Assinatura &amp; Planos</p>
                </a>
              </li>

              <!-- Usuários da conta (ativo) -->
              <li class="nav-item menu-open">
                <a href="#" class="nav-link active">
                  <i class="nav-icon bi bi-people"></i>
                  <p>
                    Usuários da conta
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>/account_users.php?tab=usuarios" class="nav-link <?php echo $tab === 'usuarios' ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Usuários com acesso às lojas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>/account_users.php?tab=permissoes" class="nav-link <?php echo $tab === 'permissoes' ? 'active' : ''; ?>">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Permissões gerais</p>
                    </a>
                  </li>
                </ul>
              </li>

              <li class="nav-item">
                <a href="#" class="nav-link">
                  <i class="nav-icon bi bi-bar-chart"></i>
                  <p>Relatórios Consolidados</p>
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </aside>

      <!-- Conteúdo principal -->
      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-8">
                <h3 class="mb-0">Usuários da Conta</h3>
                <p class="text-secondary mb-0">
                  Gerencie quem pode acessar suas lojas e as permissões gerais da conta.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            <!-- Tabs locais: Usuários | Permissões -->
            <ul class="nav nav-pills mb-3 account-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <a
                  href="#tab-usuarios"
                  class="nav-link <?php echo $tab === 'usuarios' ? 'active' : ''; ?>"
                  data-bs-toggle="tab"
                  role="tab"
                >
                  Usuários com acesso às lojas
                </a>
              </li>
              <li class="nav-item" role="presentation">
                <a
                  href="#tab-permissoes"
                  class="nav-link <?php echo $tab === 'permissoes' ? 'active' : ''; ?>"
                  data-bs-toggle="tab"
                  role="tab"
                >
                  Permissões gerais
                </a>
              </li>
            </ul>

            <div class="tab-content">
              <!-- Aba: Usuários -->
              <div
                class="tab-pane fade <?php echo $tab === 'usuarios' ? 'show active' : ''; ?>"
                id="tab-usuarios"
                role="tabpanel"
              >
                <div class="card mb-3">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Usuários com acesso às lojas</h5>
                    <button class="btn btn-primary btn-sm">
                      <i class="bi bi-person-plus"></i> Adicionar usuário
                    </button>
                  </div>
                  <div class="card-body table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Usuário</th>
                          <th>E-mail</th>
                          <th>Função na conta</th>
                          <th>Lojas com acesso</th>
                          <th>Status</th>
                          <th class="text-end">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="user-avatar-initials">JS</div>
                              <div>
                                <div>João Silva</div>
                                <small class="text-secondary">Responsável financeiro</small>
                              </div>
                            </div>
                          </td>
                          <td>joao.silva@empresa.com</td>
                          <td><span class="badge-role badge-role-admin">Administrador</span></td>
                          <td>Todas as lojas</td>
                          <td><span class="badge bg-success">Ativo</span></td>
                          <td class="text-end">
                            <button class="btn btn-outline-secondary btn-sm">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                              <i class="bi bi-three-dots-vertical"></i>
                            </button>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="user-avatar-initials">MC</div>
                              <div>
                                <div>Maria Costa</div>
                                <small class="text-secondary">Gerente de lojas</small>
                              </div>
                            </div>
                          </td>
                          <td>maria.costa@empresa.com</td>
                          <td><span class="badge-role badge-role-gerente">Gerente</span></td>
                          <td>Loja Centro, Loja Shopping</td>
                          <td><span class="badge bg-success">Ativo</span></td>
                          <td class="text-end">
                            <button class="btn btn-outline-secondary btn-sm">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                              <i class="bi bi-three-dots-vertical"></i>
                            </button>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="user-avatar-initials">PF</div>
                              <div>
                                <div>Pedro Ferreira</div>
                                <small class="text-secondary">Vendedor</small>
                              </div>
                            </div>
                          </td>
                          <td>pedro.ferreira@empresa.com</td>
                          <td><span class="badge-role badge-role-colaborador">Colaborador</span></td>
                          <td>Loja Outlet</td>
                          <td><span class="badge bg-warning text-dark">Convite pendente</span></td>
                          <td class="text-end">
                            <button class="btn btn-outline-secondary btn-sm">
                              <i class="bi bi-envelope"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm">
                              <i class="bi bi-three-dots-vertical"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Aba: Permissões gerais -->
              <div
                class="tab-pane fade <?php echo $tab === 'permissoes' ? 'show active' : ''; ?>"
                id="tab-permissoes"
                role="tabpanel"
              >
                <div class="card mb-3">
                  <div class="card-header">
                    <h5 class="card-title mb-0">Permissões gerais da conta</h5>
                  </div>
                  <div class="card-body">
                    <p class="text-secondary mb-3">
                      Defina regras de acesso padrão para novos usuários da conta. Essas configurações podem ser
                      ajustadas individualmente para cada usuário depois.
                    </p>
                    <div class="row g-3">
                      <div class="col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="permReports" checked />
                          <label class="form-check-label" for="permReports">
                            Permitir acesso a relatórios consolidados
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="permExport" checked />
                          <label class="form-check-label" for="permExport">
                            Permitir exportação de dados (CSV/Excel)
                          </label>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="permBilling" />
                          <label class="form-check-label" for="permBilling">
                            Permitir visualizar informações de cobrança
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="permUsers" />
                          <label class="form-check-label" for="permUsers">
                            Permitir gerenciar outros usuários da conta
                          </label>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="permStores" checked />
                          <label class="form-check-label" for="permStores">
                            Permitir criar novas lojas (dentro do limite do plano)
                          </label>
                        </div>
                      </div>
                    </div>
                    <button class="btn btn-primary btn-sm mt-3">
                      Salvar configurações padrão
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">Usuários da conta ModernPOS</div>
        <strong>ModernPOS</strong> &mdash; gerenciamento de acesso.
      </footer>
    </div>

    <!-- Scripts principais AdminLTE 4 -->
    <script
      src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
      crossorigin="anonymous"
    ></script>
    <script
      src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
      crossorigin="anonymous"
    ></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
      crossorigin="anonymous"
    ></script>
    <script src="./js/adminlte.js"></script>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector('.sidebar-wrapper');
        if (
          sidebarWrapper &&
          window.OverlayScrollbarsGlobal &&
          window.OverlayScrollbarsGlobal.OverlayScrollbars
        ) {
          window.OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
              theme: 'os-theme-light',
              autoHide: 'leave',
              clickScroll: true,
            },
          });
        }
      });
    </script>
  </body>
</html>
