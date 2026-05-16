<?php
// Página: Relatórios Consolidados (Painel da Conta / AdminLTE 4)
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

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'vendas';
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>Relatórios Consolidados - ModernPOS (Conta)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta
      name="description"
      content="Relatórios consolidados de vendas e comparativos entre lojas da conta ModernPOS."
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

    <!-- Chart.js apenas para gráficos (grandes) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

      .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
      }

      .filter-row .form-select,
      .filter-row .form-control {
        min-width: 150px;
      }

      @media (max-width: 768px) {
        .filter-row {
          flex-direction: column;
          align-items: stretch;
        }
      }

      /* Summary cards (balanço de caixa) */
      .summary-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 1.25rem 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      }

      .summary-card.green {
        background: linear-gradient(135deg, #00a65a 0%, #008d4c 100%);
      }

      .summary-card.blue {
        background: linear-gradient(135deg, #3c8dbc 0%, #2c6c94 100%);
      }

      .summary-card-title {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.9;
        margin-bottom: 0.5rem;
      }

      .summary-card-value {
        font-size: 1.9rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
      }

      .summary-card-subtitle {
        font-size: 0.8rem;
        opacity: 0.85;
      }

      /* Grid de meios de pagamento */
      .payment-methods-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
      }

      .payment-method-card {
        border: 1px solid #d2d6de;
        border-radius: 0.5rem;
        padding: 0.9rem 1rem;
        background-color: #fff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
      }

      .payment-method-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-bottom: 0.75rem;
        margin-bottom: 0.75rem;
        border-bottom: 2px solid #f4f4f4;
      }

      .payment-method-icon {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.1rem;
      }

      .payment-method-icon.money {
        background: #00a65a;
      }

      .payment-method-icon.credit {
        background: #3c8dbc;
      }

      .payment-method-icon.debit {
        background: #00c0ef;
      }

      .payment-method-icon.pix {
        background: #605ca8;
      }

      .payment-method-title {
        font-weight: 600;
        font-size: 0.95rem;
      }

      .payment-method-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem 1rem;
      }

      .payment-stat {
        display: flex;
        flex-direction: column;
      }

      .payment-stat-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #777;
      }

      .payment-stat-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #333;
      }

      .payment-stat-value.large {
        font-size: 1.2rem;
        color: #00a65a;
      }

      /* Charts */
      .chart-container {
        position: relative;
        height: 280px;
      }

      /* Mini indicadores na tabela de detalhamento */
      .sparkline-cell {
        text-align: center;
        font-size: 0.7rem;
        white-space: nowrap;
      }

      .sparkline-change-up {
        color: #00a65a;
      }

      .sparkline-change-down {
        color: #dd4b39;
      }

      .sparkline-bar {
        display: inline-block;
        width: 42px;
        height: 3px;
        border-radius: 999px;
        background: #f0f0f0;
        overflow: hidden;
        margin-top: 0;
        margin-left: 0;
      }

      .sparkline-bar span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #00a65a, #00c0ef);
        width: var(--spark-value, 50%);
      }

      .value-with-trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.84rem;
      }

      .sparkline-cell.sparkline-down .sparkline-bar span {
        background: linear-gradient(90deg, #dd4b39, #f39c12);
      }

      .table-summary-row {
        background-color: #2c3e50;
        color: #fff;
      }

      .table-summary-row td {
        border-top: 2px solid #1b2834;
      }

      /* Info boxes */
      .info-box {
        display: flex;
        background: #fff;
        border-radius: 0.25rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        min-height: 80px;
      }

      .info-box-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 70px;
        font-size: 2rem;
        color: #fff;
        border-top-left-radius: 0.25rem;
        border-bottom-left-radius: 0.25rem;
      }

      .info-box-icon.bg-aqua {
        background: #00c0ef;
      }

      .info-box-icon.bg-green {
        background: #00a65a;
      }

      .info-box-icon.bg-yellow {
        background: #f39c12;
      }

      .info-box-icon.bg-red {
        background: #dd4b39;
      }

      .info-box-content {
        padding: 0.6rem 0.75rem;
        flex: 1;
      }

      .info-box-text {
        font-size: 0.75rem;
        color: #555;
        text-transform: uppercase;
      }

      .info-box-number {
        font-size: 1.35rem;
        font-weight: 700;
      }

      .progress-description {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #999;
      }

      /* Knobs na tabela de detalhamento */
      .knob-spark {
        font-size: 0.75rem !important;
      }

      @media (max-width: 992px) {
        .payment-methods-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (max-width: 576px) {
        .payment-methods-grid {
          grid-template-columns: minmax(0, 1fr);
        }
      }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
      <!-- Navbar topo -->
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
                  <p>Lojas</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo root_url(); ?>/account_plans.php?tab=plano_atual" class="nav-link">
                  <i class="nav-icon bi bi-credit-card-2-front"></i>
                  <p>Assinatura &amp; Planos</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="<?php echo root_url(); ?>/account_users.php?tab=usuarios" class="nav-link">
                  <i class="nav-icon bi bi-people"></i>
                  <p>Usuários da conta</p>
                </a>
              </li>

              <!-- Relatórios Consolidados (item único) -->
              <li class="nav-item menu-open">
                <a href="<?php echo root_url(); ?>/account_reports.php?tab=vendas" class="nav-link active">
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
                <h3 class="mb-0">Relatórios Consolidados</h3>
                <p class="text-secondary mb-0">
                  Acompanhe vendas agregadas e compare o desempenho entre lojas.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            <!-- Conteúdo: Vendas consolidadas / Balanço de Caixa -->
            <div class="tab-content">
              <div class="tab-pane fade show active" id="tab-vendas" role="tabpanel">
                <!-- Filtros principais -->
                <div class="card mb-3">
                  <div class="card-body">
                    <div class="filter-row">
                      <div class="d-flex flex-column" style="min-width: 220px;">
                        <label class="text-uppercase text-muted small mb-1">Período</label>
                        <select class="form-select form-select-sm">
                          <option>Hoje</option>
                          <option>Ontem</option>
                          <option>Últimos 7 dias</option>
                          <option selected>Este mês</option>
                          <option>Mês anterior</option>
                          <option>Este ano</option>
                          <option>Período personalizado</option>
                        </select>
                      </div>
                      <div class="d-flex flex-column" style="min-width: 220px;">
                        <label class="text-uppercase text-muted small mb-1">Loja</label>
                        <select class="form-select form-select-sm">
                          <option selected>Todas as lojas (consolidado)</option>
                          <option>Loja Centro</option>
                          <option>Loja Shopping</option>
                          <option>Loja Outlet</option>
                          <option>Loja Online</option>
                        </select>
                      </div>
                      <div class="ms-auto d-flex flex-wrap gap-2 align-items-end">
                        <button type="button" class="btn btn-primary btn-sm">
                          <i class="bi bi-arrow-clockwise"></i> Atualizar
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="window.print()">
                          <i class="bi bi-printer"></i> Imprimir
                        </button>
                        <button type="button" class="btn btn-success btn-sm">
                          <i class="bi bi-file-earmark-excel"></i> Exportar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Cards de resumo -->
                <div class="row g-3 mb-3">
                  <div class="col-md-4">
                    <div class="summary-card green h-100">
                      <div class="summary-card-title">FATURAMENTO TOTAL</div>
                      <div class="summary-card-value">R$ 87.450,00</div>
                      <div class="summary-card-subtitle">+8% comparado ao mês anterior</div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="summary-card blue h-100">
                      <div class="summary-card-title">TOTAL DE VENDAS</div>
                      <div class="summary-card-value">1.247</div>
                      <div class="summary-card-subtitle">Ticket médio: R$ 70,14</div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="summary-card h-100">
                      <div class="summary-card-title">LOJAS ATIVAS</div>
                      <div class="summary-card-value">3 / 4</div>
                      <div class="summary-card-subtitle">1 loja inativa no período</div>
                    </div>
                  </div>
                </div>

                <!-- Resumo por Meio de Pagamento -->
                <div class="card mb-3">
                  <div class="card-header">
                    <h5 class="card-title mb-0">
                      <i class="bi bi-credit-card-2-front me-2"></i>
                      Resumo por meio de pagamento (todas as lojas)
                    </h5>
                  </div>
                  <div class="card-body">
                    <div class="payment-methods-grid">
                      <!-- Dinheiro -->
                      <div class="payment-method-card">
                        <div class="payment-method-header">
                          <div class="payment-method-icon money">
                            <i class="fa fa-money"></i>
                          </div>
                          <div class="payment-method-title">Dinheiro</div>
                        </div>
                        <div class="payment-method-stats">
                          <div class="payment-stat">
                            <span class="payment-stat-label">Total</span>
                            <span class="payment-stat-value large">R$ 28.450,00</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">% do Total</span>
                            <span class="payment-stat-value">32,5%</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Transações</span>
                            <span class="payment-stat-value">423</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Ticket Médio</span>
                            <span class="payment-stat-value">R$ 67,26</span>
                          </div>
                        </div>
                      </div>

                      <!-- Crédito -->
                      <div class="payment-method-card">
                        <div class="payment-method-header">
                          <div class="payment-method-icon credit">
                            <i class="fa fa-credit-card"></i>
                          </div>
                          <div class="payment-method-title">Cartão de Crédito</div>
                        </div>
                        <div class="payment-method-stats">
                          <div class="payment-stat">
                            <span class="payment-stat-label">Total</span>
                            <span class="payment-stat-value large">R$ 35.200,00</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">% do Total</span>
                            <span class="payment-stat-value">40,3%</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Transações</span>
                            <span class="payment-stat-value">498</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Ticket Médio</span>
                            <span class="payment-stat-value">R$ 70,68</span>
                          </div>
                        </div>
                      </div>

                      <!-- Débito -->
                      <div class="payment-method-card">
                        <div class="payment-method-header">
                          <div class="payment-method-icon debit">
                            <i class="fa fa-credit-card-alt"></i>
                          </div>
                          <div class="payment-method-title">Cartão de Débito</div>
                        </div>
                        <div class="payment-method-stats">
                          <div class="payment-stat">
                            <span class="payment-stat-label">Total</span>
                            <span class="payment-stat-value large">R$ 18.600,00</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">% do Total</span>
                            <span class="payment-stat-value">21,3%</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Transações</span>
                            <span class="payment-stat-value">267</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Ticket Médio</span>
                            <span class="payment-stat-value">R$ 69,66</span>
                          </div>
                        </div>
                      </div>

                      <!-- PIX -->
                      <div class="payment-method-card">
                        <div class="payment-method-header">
                          <div class="payment-method-icon pix">
                            <i class="fa fa-bolt"></i>
                          </div>
                          <div class="payment-method-title">PIX</div>
                        </div>
                        <div class="payment-method-stats">
                          <div class="payment-stat">
                            <span class="payment-stat-label">Total</span>
                            <span class="payment-stat-value large">R$ 5.200,00</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">% do Total</span>
                            <span class="payment-stat-value">5,9%</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Transações</span>
                            <span class="payment-stat-value">59</span>
                          </div>
                          <div class="payment-stat">
                            <span class="payment-stat-label">Ticket Médio</span>
                            <span class="payment-stat-value">R$ 88,14</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Gráficos -->
                <div class="row g-3 mb-3">
                  <div class="col-lg-6">
                    <div class="card h-100">
                      <div class="card-header">
                        <h5 class="card-title mb-0">Distribuição por meio de pagamento</h5>
                      </div>
                      <div class="card-body">
                        <div class="chart-container">
                          <canvas id="paymentMethodsChart"></canvas>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-6">
                    <div class="card h-100">
                      <div class="card-header">
                        <h5 class="card-title mb-0">Evolução de vendas por meio de pagamento</h5>
                      </div>
                      <div class="card-body">
                        <div class="chart-container">
                          <canvas id="paymentTrendChart"></canvas>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Detalhamento por loja -->
                <div class="card mb-3">
                  <div class="card-header">
                    <h5 class="card-title mb-0">
                      <i class="bi bi-table me-2"></i>
                      Detalhamento por loja e meio de pagamento
                    </h5>
                  </div>
                  <div class="card-body table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Loja</th>
                          <th>Dinheiro</th>
                          <th>Crédito</th>
                          <th>Débito</th>
                          <th>PIX</th>
                          <th>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td><strong><i class="bi bi-shop me-1"></i> Loja Centro</strong></td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 12%</span>
                              R$ 14.200,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 12%</span>
                              R$ 17.850,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 12%</span>
                              R$ 9.100,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 12%</span>
                              R$ 2.050,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 12%</span>
                              <strong>R$ 43.200,00</strong>
                            </span>
                          </td>
                        </tr>
                        <tr>
                          <td><strong><i class="bi bi-shop me-1"></i> Loja Shopping</strong></td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 8%</span>
                              R$ 11.650,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 8%</span>
                              R$ 15.560,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 8%</span>
                              R$ 8.790,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 8%</span>
                              R$ 2.900,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-up">↑ 8%</span>
                              <strong>R$ 38.900,00</strong>
                            </span>
                          </td>
                        </tr>
                        <tr>
                          <td><strong><i class="bi bi-shop me-1"></i> Loja Outlet</strong></td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-down">↓ 3%</span>
                              R$ 2.600,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-down">↓ 3%</span>
                              R$ 1.790,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-down">↓ 3%</span>
                              R$ 710,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-down">↓ 3%</span>
                              R$ 250,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="sparkline-change-down">↓ 3%</span>
                              <strong>R$ 5.350,00</strong>
                            </span>
                          </td>
                        </tr>
                        <tr class="text-muted">
                          <td><strong><i class="bi bi-shop me-1"></i> Loja Online</strong></td>
                          <td>
                            <span class="value-with-trend">
                              <span class="text-muted">–</span>
                              R$ 0,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="text-muted">–</span>
                              R$ 0,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="text-muted">–</span>
                              R$ 0,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="text-muted">–</span>
                              R$ 0,00
                            </span>
                          </td>
                          <td>
                            <span class="value-with-trend">
                              <span class="text-muted">–</span>
                              <strong>R$ 0,00</strong>
                            </span>
                          </td>
                        </tr>
                      </tbody>
                      <tfoot>
                        <tr class="table-summary-row">
                          <td><strong>TOTAL CONSOLIDADO</strong></td>
                          <td><strong>R$ 28.450,00</strong></td>
                          <td><strong>R$ 35.200,00</strong></td>
                          <td><strong>R$ 18.600,00</strong></td>
                          <td><strong>R$ 5.200,00</strong></td>
                          <td><strong>R$ 87.450,00</strong></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>

                <!-- KPIs adicionais -->
                <div class="row g-3 mb-4">
                  <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                      <span class="info-box-icon bg-aqua">
                        <i class="fa fa-shopping-cart"></i>
                      </span>
                      <div class="info-box-content">
                        <span class="info-box-text">Total de vendas</span>
                        <span class="info-box-number">1.247</span>
                        <span class="progress-description">+12% vs período anterior</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                      <span class="info-box-icon bg-green">
                        <i class="fa fa-line-chart"></i>
                      </span>
                      <div class="info-box-content">
                        <span class="info-box-text">Ticket médio</span>
                        <span class="info-box-number">R$ 70,14</span>
                        <span class="progress-description">-3% vs período anterior</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                      <span class="info-box-icon bg-yellow">
                        <i class="fa fa-credit-card"></i>
                      </span>
                      <div class="info-box-content">
                        <span class="info-box-text">Forma de pagamento mais usada</span>
                        <span class="info-box-number">Cartão de crédito</span>
                        <span class="progress-description">40,3% do faturamento total</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                      <span class="info-box-icon bg-red">
                        <i class="fa fa-star"></i>
                      </span>
                      <div class="info-box-content">
                        <span class="info-box-text">Loja que mais vendeu</span>
                        <span class="info-box-number">Loja Centro</span>
                        <span class="progress-description">R$ 43.200,00 no período</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
          </div>
        </div>
      </main>

      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">Relatórios consolidados ModernPOS</div>
        <strong>ModernPOS</strong> &mdash; visão geral multi-loja.
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

        // Gráfico de faturamento por dia (exemplo)
        const revCanvas = document.getElementById('chartRevenue');
        if (revCanvas) {
          const ctx = revCanvas.getContext('2d');
          new Chart(ctx, {
            type: 'line',
            data: {
              labels: ['10/01', '11/01', '12/01', '13/01', '14/01'],
              datasets: [
                {
                  label: 'Faturamento',
                  data: [12300, 11750, 13820, 12050, 18530],
                  borderColor: '#0d6efd',
                  backgroundColor: 'rgba(13,110,253,0.1)',
                  tension: 0.3,
                  fill: true,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    callback: (value) => 'R$ ' + (value / 1000).toFixed(0) + 'k',
                  },
                },
              },
            },
          });
        }

        // Gráfico de distribuição por meio de pagamento (exemplo)
        const pmCanvas = document.getElementById('paymentMethodsChart');
        if (pmCanvas) {
          const ctxPm = pmCanvas.getContext('2d');
          new Chart(ctxPm, {
            type: 'doughnut',
            data: {
              labels: ['Dinheiro', 'Crédito', 'Débito', 'PIX'],
              datasets: [
                {
                  data: [28450, 35200, 18600, 5200],
                  backgroundColor: ['#00a65a', '#3c8dbc', '#00c0ef', '#605ca8'],
                  borderColor: '#ffffff',
                  borderWidth: 2,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                },
              },
            },
          });
        }

        // Gráfico de evolução por meio de pagamento (exemplo)
        const trendCanvas = document.getElementById('paymentTrendChart');
        if (trendCanvas) {
          const ctxTrend = trendCanvas.getContext('2d');
          new Chart(ctxTrend, {
            type: 'line',
            data: {
              labels: ['10/01', '11/01', '12/01', '13/01', '14/01'],
              datasets: [
                {
                  label: 'Dinheiro',
                  data: [5200, 4800, 5600, 5300, 5550],
                  borderColor: '#00a65a',
                  backgroundColor: 'rgba(0,166,90,0.08)',
                  tension: 0.3,
                  fill: true,
                },
                {
                  label: 'Crédito',
                  data: [6100, 6300, 6400, 6200, 6500],
                  borderColor: '#3c8dbc',
                  backgroundColor: 'rgba(60,141,188,0.08)',
                  tension: 0.3,
                  fill: true,
                },
                {
                  label: 'Débito',
                  data: [3200, 3100, 3300, 3250, 3350],
                  borderColor: '#00c0ef',
                  backgroundColor: 'rgba(0,192,239,0.08)',
                  tension: 0.3,
                  fill: true,
                },
                {
                  label: 'PIX',
                  data: [900, 850, 1000, 950, 1150],
                  borderColor: '#605ca8',
                  backgroundColor: 'rgba(96,92,168,0.08)',
                  tension: 0.3,
                  fill: true,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              interaction: {
                mode: 'index',
                intersect: false,
              },
              stacked: false,
              plugins: {
                legend: {
                  position: 'bottom',
                },
              },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    callback: (value) => 'R$ ' + (value / 1000).toFixed(0) + 'k',
                  },
                },
              },
            },
          });
        }

        // Gráfico de faturamento por loja (exemplo)
        const storesCanvas = document.getElementById('chartStores');
        if (storesCanvas) {
          const ctx2 = storesCanvas.getContext('2d');
          new Chart(ctx2, {
            type: 'bar',
            data: {
              labels: ['Loja Centro', 'Loja Shopping', 'Loja Outlet', 'Loja Online'],
              datasets: [
                {
                  label: 'Faturamento do período',
                  data: [43200, 38900, 21350, 0],
                  backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#dc3545'],
                },
              ],
            },
            options: {
              indexAxis: 'y',
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { display: false },
              },
              scales: {
                x: {
                  beginAtZero: true,
                  ticks: {
                    callback: (value) => 'R$ ' + (value / 1000).toFixed(0) + 'k',
                  },
                },
              },
            },
          });
        }
      });
    </script>
  </body>
</html>
