<?php
// Página: Planos e Assinatura (Painel da Conta / AdminLTE 4)
// Acessível apenas para administradores de conta.

ob_start();
session_start();
require_once "_init.php";

if (!$user->isLogged()) {
  redirect(root_url() . 'index.php?redirect_to=' . url());
}

// Apenas administradores de conta (mesma regra do painel de conta)
if (user_group_id() != 1) {
  redirect(root_url() . 'store_select.php');
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'plano_atual';
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>Planos e Assinatura - ModernPOS (Conta)</title>
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <meta name="color-scheme" content="light dark" />
    <meta
      name="description"
      content="Gerencie seu plano ModernPOS, assinaturas e histórico de pagamentos."
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

    <!-- Font Awesome (para ícones dos cards de plano, FAQ etc.) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    />

    <!-- Customização para aproximar a paleta do ModernPOS (AdminLTE 2) -->
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
        /* Cor mint dos ícones SVG do ModernPOS */
        color: #9effd3;
        font-size: 1rem;
      }

      .app-sidebar .nav-link.active,
      .app-sidebar .nav-link:hover {
        background-color: #1e282c;
        color: #ffffff;
      }

      /* Mantém ícones sempre mint, mesmo hover/ativo */
      .app-sidebar .nav-link .nav-icon,
      .app-sidebar .nav-link.active .nav-icon,
      .app-sidebar .nav-link:hover .nav-icon {
        color: #9effd3;
      }

      /* Submenus com linha vertical (estilo ModernPOS) */
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

      /* Remove a bolinha (bi-circle) dos submenus */
      .app-sidebar .nav-treeview .nav-icon.bi-circle {
        display: none;
      }

      /* Cards azuis no estilo ModernPOS (usado nos planos) */
      .card-plan {
        border-radius: 4px;
        background: #f4f8ff;
        border: 1px solid #d0e2ff;
      }

      .card-plan-header {
        background: linear-gradient(90deg, #3c8dbc, #00c0ef);
        color: #fff;
      }

      .card-plan.price {
        font-size: 1.6rem;
        font-weight: 600;
      }

      .ribbon-top-right {
        position: absolute;
        top: 0.5rem;
        right: -0.6rem;
        overflow: hidden;
        z-index: 10;
      }

      .ribbon-top-right span {
        display: block;
        background: #ffc107;
        color: #444;
        padding: 0.15rem 0.75rem;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        transform: rotate(45deg);
        transform-origin: left top;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
      }

      /* Tabs de sub-menus locais */
      .account-tabs .nav-link {
        border-radius: 0;
      }

      .account-tabs .nav-link.active {
        background-color: #3c8dbc;
        color: #fff;
      }

      /* === Estilos do layout conceitual (conteúdo interno) === */
      .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
      }

      .alert-info {
        background-color: #d9edf7;
        border-color: #bce8f1;
        color: #31708f;
      }

      .alert i {
        margin-right: 8px;
      }

      .box {
        position: relative;
        border-radius: 3px;
        background: #fff;
        border-top: 3px solid #d2d6de;
        margin-bottom: 20px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
      }

      .box.box-primary {
        border-top-color: #3c8dbc;
      }

      .box.box-success {
        border-top-color: #00a65a;
      }

      .box.box-default {
        border-top-color: #d2d6de;
      }

      .box-header {
        color: #444;
        padding: 15px;
        border-bottom: 1px solid #f4f4f4;
      }

      .box-title {
        font-size: 18px;
        font-weight: 400;
        margin: 0;
      }

      .box-body {
        padding: 15px;
      }

      .plans-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
      }

      .plan-card {
        background: #fff;
        border: 2px solid #d2d6de;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s;
        position: relative;
      }

      .plan-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
      }

      .plan-card.featured {
        border-color: #3c8dbc;
        box-shadow: 0 3px 15px rgba(60, 141, 188, 0.2);
      }

      .plan-card.current {
        border-color: #00a65a;
        border-width: 3px;
      }

      .plan-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: #00a65a;
        color: #fff;
        padding: 4px 12px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
      }

      .plan-badge.featured {
        background: #f39c12;
      }

      .plan-header {
        padding: 25px 20px;
        text-align: center;
        background: #f9f9f9;
        border-bottom: 1px solid #e9e9e9;
      }

      .plan-card.featured .plan-header {
        background: linear-gradient(135deg, #3c8dbc 0%, #2c6c94 100%);
        color: #fff;
      }

      .plan-name {
        font-size: 22px;
        font-weight: 600;
        margin-bottom: 10px;
      }

      .plan-price {
        font-size: 36px;
        font-weight: bold;
        margin: 15px 0 5px;
      }

      .plan-price small {
        font-size: 16px;
        font-weight: normal;
        opacity: 0.8;
      }

      .plan-period {
        font-size: 13px;
        opacity: 0.8;
      }

      .plan-body {
        padding: 25px 20px;
      }

      .plan-features {
        list-style: none;
        padding: 0;
        margin: 0;
      }

      .plan-features li {
        padding: 10px 0;
        border-bottom: 1px solid #f4f4f4;
        display: flex;
        align-items: flex-start;
      }

      .plan-features li:last-child {
        border-bottom: none;
      }

      .plan-features i {
        color: #00a65a;
        margin-right: 10px;
        margin-top: 3px;
      }

      .plan-features .fa-times {
        color: #dd4b39;
      }

      .plan-footer {
        padding: 20px;
        text-align: center;
        border-top: 1px solid #f4f4f4;
      }

      .billing-toggle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
        padding: 16px;
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e6eef7;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        flex-wrap: wrap;
      }

      .billing-toggle .toggle-label {
        font-weight: 700;
        color: #556;
      }

      .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
      }

      .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
      }

      .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.4s;
        border-radius: 24px;
      }

      .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: #fff;
        transition: 0.4s;
        border-radius: 50%;
      }

      input:checked + .toggle-slider {
        background-color: #00a65a;
      }

      input:checked + .toggle-slider:before {
        transform: translateX(26px);
      }

      .discount-badge {
        background: #00a65a;
        color: #fff;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.2px;
        display: none; /* aparece apenas no anual */
      }

      .current-plan-hero {
        border: 1px solid #d0e2ff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
      }

      .current-plan-hero .hero-header {
        background: linear-gradient(90deg, #3c8dbc, #00c0ef);
        color: #fff;
      }

      .usage-card {
        border-left: 4px solid #3c8dbc;
      }

      .usage-metric {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 10px;
        padding: 14px;
      }

      .usage-metric small {
        display: block;
        margin-top: 6px;
      }

      .filter-btn-group .btn.active {
        background: #3c8dbc;
        border-color: #3c8dbc;
        color: #fff;
      }

      .table {
        width: 100%;
        border-collapse: collapse;
      }

      .table thead {
        background: #f4f4f4;
      }

      .table th {
        padding: 12px 8px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #ddd;
      }

      .table td {
        padding: 12px 8px;
        border-bottom: 1px solid #f4f4f4;
      }

      .table tbody tr:hover {
        background: #f9f9f9;
      }

      .badge {
        display: inline-block;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: bold;
        border-radius: 3px;
      }

      .badge-success {
        background: #00a65a;
        color: #fff;
      }

      .badge-warning {
        background: #f39c12;
        color: #fff;
      }

      .badge-danger {
        background: #dd4b39;
        color: #fff;
      }

      .faq-item {
        background: #fff;
        border: 1px solid #d2d6de;
        border-radius: 4px;
        margin-bottom: 10px;
        overflow: hidden;
      }

      .faq-question {
        padding: 15px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.3s;
      }

      .faq-question:hover {
        background: #f9f9f9;
      }

      .faq-answer {
        padding: 0 15px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s, padding 0.3s;
      }

      .faq-item.active .faq-answer {
        padding: 15px;
        max-height: 500px;
        border-top: 1px solid #f4f4f4;
      }

      .faq-item.active .faq-question i {
        transform: rotate(180deg);
      }

      /* Pequeno ajuste visual no card de status da assinatura */
      .subscription-status-card {
        border-left: 4px solid #00a65a;
      }

      .subscription-status-card .badge {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 999px;
      }

      @media (max-width: 992px) {
        .plans-grid {
          grid-template-columns: 1fr;
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
              <a
                class="nav-link"
                data-lte-toggle="sidebar"
                href="#"
                role="button"
              >
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
              <a
                href="#"
                class="nav-link dropdown-toggle"
                data-bs-toggle="dropdown"
              >
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

      <!-- Sidebar (menu lateral) -->
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
              id="navigation"
            >
              <!-- Visão geral da conta -->
              <li class="nav-item">
                <a href="<?php echo root_url(); ?>/store_select.php" class="nav-link">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Visão Geral</p>
                </a>
              </li>

              <!-- Lojas -->
              <li class="nav-item">
                <a href="#" class="nav-link">
                  <i class="nav-icon bi bi-shop"></i>
                  <p>
                    Lojas
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="<?php echo root_url(); ?>/store_select.php" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Visão geral das lojas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="#" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Gerenciar lojas</p>
                    </a>
                  </li>
                </ul>
              </li>

              <!-- Planos -->
              <li class="nav-item">
                <a
                  href="<?php echo root_url(); ?>/conta/planos"
                  class="nav-link <?php echo $tab === 'plano_atual' ? 'active' : ''; ?>"
                >
                  <i class="nav-icon bi bi-credit-card-2-front"></i>
                  <p>Planos</p>
                </a>
              </li>

              <!-- Histórico -->
              <li class="nav-item">
                <a
                  href="<?php echo root_url(); ?>/conta/planos/historico"
                  class="nav-link <?php echo $tab === 'historico' ? 'active' : ''; ?>"
                >
                  <i class="nav-icon bi bi-receipt"></i>
                  <p>Histórico</p>
                </a>
              </li>

              <!-- Usuários da conta -->
              <li class="nav-item">
                <a href="#" class="nav-link">
                  <i class="nav-icon bi bi-people"></i>
                  <p>
                    Usuários da conta
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="#" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Usuários com acesso às lojas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="#" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Permissões gerais</p>
                    </a>
                  </li>
                </ul>
              </li>

              <!-- Relatórios Consolidados -->
              <li class="nav-item">
                <a href="#" class="nav-link">
                  <i class="nav-icon bi bi-bar-chart"></i>
                  <p>
                    Relatórios Consolidados
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="#" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Vendas consolidadas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="#" class="nav-link">
                      <i class="nav-icon bi bi-circle"></i>
                      <p>Comparativo de lojas</p>
                    </a>
                  </li>
                </ul>
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
                <h3 class="mb-0">Planos e Assinatura</h3>
                <p class="text-secondary mb-0">
                  Gerencie seu plano e histórico de pagamentos.
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            <?php if ($tab === 'historico') : ?>
              <div class="row g-3">
                <div class="col-12">
                  <div class="card subscription-status-card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">Status da assinatura</h5>
                    </div>
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                      <div>
                        <p class="mb-1">
                          Sua assinatura do <strong>Plano Profissional</strong> está
                          <span class="badge bg-success">Ativa</span>.
                        </p>
                        <p class="mb-0 text-muted">
                          Próxima renovação em <strong>15/02/2026</strong> no valor de
                          <strong>R$ 149,00</strong> via cartão de crédito final 4532.
                        </p>
                      </div>
                      <div class="mt-3 mt-md-0 d-flex gap-2">
                        <a href="<?php echo root_url(); ?>/conta/planos" class="btn btn-outline-primary btn-sm">
                          <i class="fa fa-arrow-left"></i> Ver planos
                        </a>
                        <button class="btn btn-outline-danger btn-sm btn-subscription-cancel">
                          <i class="fa fa-times"></i> Cancelar assinatura
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Uso do Plano (movido para o Histórico) -->
                <div class="col-12">
                  <div class="card usage-card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">Uso do Plano</h5>
                    </div>
                    <div class="card-body">
                      <div class="row g-3">
                        <div class="col-lg-4">
                          <div class="usage-metric">
                            <div class="d-flex justify-content-between">
                              <span class="text-muted">Lojas ativas</span>
                              <span class="fw-semibold">4 / 5</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                              <div class="progress-bar bg-success" style="width: 80%;"></div>
                            </div>
                            <small class="text-muted">Você está usando 80% do limite do plano.</small>
                          </div>
                        </div>
                        <div class="col-lg-4">
                          <div class="usage-metric">
                            <div class="d-flex justify-content-between">
                              <span class="text-muted">Usuários</span>
                              <span class="fw-semibold">6 / 10</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                              <div class="progress-bar bg-info" style="width: 60%;"></div>
                            </div>
                            <small class="text-muted">Inclui usuários com acesso às lojas.</small>
                          </div>
                        </div>
                        <div class="col-lg-4">
                          <div class="usage-metric">
                            <div class="d-flex justify-content-between">
                              <span class="text-muted">Integrações</span>
                              <span class="fw-semibold">2 / 5</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                              <div class="progress-bar bg-warning" style="width: 40%;"></div>
                            </div>
                            <small class="text-muted">Integrações ativas no momento.</small>
                          </div>
                        </div>
                      </div>
                      <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="<?php echo root_url(); ?>/conta/planos" class="btn btn-primary btn-sm">
                          Fazer upgrade / trocar plano
                        </a>
                        <button class="btn btn-outline-secondary btn-sm" type="button">
                          Ver detalhes de limites
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Histórico de Pagamentos -->
                <div class="col-12">
                  <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                      <h5 class="card-title mb-0">Histórico de Pagamentos</h5>
                      <div class="btn-group btn-group-sm ms-auto filter-btn-group" role="group" aria-label="Filtro de pagamentos">
                        <button type="button" class="btn btn-outline-secondary active" data-payment-filter="all">Todos</button>
                        <button type="button" class="btn btn-outline-secondary" data-payment-filter="pago">Pagos</button>
                        <button type="button" class="btn btn-outline-secondary" data-payment-filter="pendente">Pendentes</button>
                      </div>
                    </div>
                    <div class="card-body table-responsive">
                      <table class="table table-striped table-hover align-middle mb-0" id="paymentHistoryTable">
                        <thead>
                          <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Nota Fiscal</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr data-status="pago">
                            <td>15/01/2026</td>
                            <td>Mensalidade Plano Profissional</td>
                            <td>R$ 149,00</td>
                            <td><span class="badge bg-success">Pago</span></td>
                            <td><a href="#">NF 2026-00015</a></td>
                          </tr>
                          <tr data-status="pago">
                            <td>15/12/2025</td>
                            <td>Mensalidade Plano Profissional</td>
                            <td>R$ 149,00</td>
                            <td><span class="badge bg-success">Pago</span></td>
                            <td><a href="#">NF 2025-00112</a></td>
                          </tr>
                          <tr data-status="pendente">
                            <td>15/02/2026</td>
                            <td>Mensalidade Plano Profissional</td>
                            <td>R$ 149,00</td>
                            <td><span class="badge bg-warning text-dark">Pendente</span></td>
                            <td><span class="text-muted">—</span></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <!-- Método de Pagamento -->
                <div class="col-12">
                  <div class="card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">Método de Pagamento</h5>
                    </div>
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                      <div class="d-flex align-items-center gap-3">
                        <i class="fa fa-credit-card" style="font-size: 32px; color: #3c8dbc;"></i>
                        <div>
                          <div class="fw-semibold">Cartão de Crédito</div>
                          <div class="text-secondary small">•••• •••• •••• 4532</div>
                          <div class="text-secondary small">Válido até 08/2027</div>
                        </div>
                      </div>
                      <button class="btn btn-primary btn-sm">
                        <i class="fa fa-edit"></i> Alterar Método
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            <?php else : ?>
              <div class="row g-3">
                <!-- Card Seu Plano Atual (maior / full width) -->
                <div class="col-12">
                  <div class="card current-plan-hero">
                    <div class="card-header hero-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div>
                        <h4 class="mb-0">Seu Plano Atual</h4>
                        <div class="small opacity-75">Renovação em <strong>15/02/2026</strong></div>
                      </div>
                      <span class="badge bg-success">Ativo</span>
                    </div>
                    <div class="card-body">
                      <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                          <h3 class="mb-1">Plano Profissional</h3>
                          <p class="mb-0 text-secondary">
                            Ideal para empresas com múltiplas lojas e necessidade de relatórios avançados.
                          </p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                          <div class="fw-bold" style="font-size: 2rem; line-height: 1.1;">
                            R$ 149 <span class="fs-6 fw-normal opacity-75">/mês</span>
                          </div>
                          <div class="d-flex flex-wrap gap-2 justify-content-lg-end mt-2">
                            <a href="<?php echo root_url(); ?>/conta/planos/historico" class="btn btn-outline-primary btn-sm">
                              Ver histórico
                            </a>
                            <button class="btn btn-warning btn-sm" type="button">
                              Trocar plano
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Toggle Mensal / Anual (com desconto) -->
                <div class="col-12">
                  <div class="billing-toggle">
                    <span class="toggle-label">Mensal</span>
                    <label class="toggle-switch" aria-label="Alternar cobrança mensal/anual">
                      <input type="checkbox" id="billingToggle" />
                      <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Anual</span>
                    <span class="discount-badge" id="annualDiscountBadge">20% OFF</span>
                  </div>
                </div>

                <!-- Planos disponíveis -->
                <div class="col-12">
                  <div class="plans-grid">
                    <!-- Plano Básico -->
                    <div class="plan-card">
                      <div class="plan-header">
                        <div class="plan-name">Básico</div>
                        <div class="plan-price">
                          R$ <span class="monthly-price">79</span><span class="yearly-price" style="display: none;">63</span>
                          <small>/mês</small>
                        </div>
                        <div class="plan-period">
                          <span class="monthly-period">Cobrado mensalmente</span>
                          <span class="yearly-period" style="display: none;">R$ 756/ano - Economize R$ 192</span>
                        </div>
                      </div>
                      <div class="plan-body">
                        <ul class="plan-features">
                          <li><i class="fa fa-check"></i> Até 2 lojas</li>
                          <li><i class="fa fa-check"></i> Vendas ilimitadas</li>
                          <li><i class="fa fa-check"></i> Relatórios básicos</li>
                          <li><i class="fa fa-check"></i> Suporte por e-mail</li>
                          <li><i class="fa fa-check"></i> App móvel</li>
                          <li><i class="fa fa-times"></i> Múltiplos usuários</li>
                          <li><i class="fa fa-times"></i> Integrações avançadas</li>
                          <li><i class="fa fa-times"></i> API de acesso</li>
                        </ul>
                      </div>
                      <div class="plan-footer">
                        <button class="btn btn-outline-primary w-100 btn-lg" data-bs-toggle="modal" data-bs-target="#modalAssinarBasico">
                          Assinar Básico
                        </button>
                      </div>
                    </div>

                    <!-- Plano Profissional (Atual) -->
                    <div class="plan-card featured current">
                      <span class="plan-badge">PLANO ATUAL</span>
                      <div class="plan-header">
                        <div class="plan-name">Profissional</div>
                        <div class="plan-price">
                          R$ <span class="monthly-price">149</span><span class="yearly-price" style="display: none;">119</span>
                          <small>/mês</small>
                        </div>
                        <div class="plan-period">
                          <span class="monthly-period">Cobrado mensalmente</span>
                          <span class="yearly-period" style="display: none;">R$ 1.428/ano - Economize R$ 360</span>
                        </div>
                      </div>
                      <div class="plan-body">
                        <ul class="plan-features">
                          <li><i class="fa fa-check"></i> Até 5 lojas</li>
                          <li><i class="fa fa-check"></i> Vendas ilimitadas</li>
                          <li><i class="fa fa-check"></i> Relatórios avançados</li>
                          <li><i class="fa fa-check"></i> Suporte prioritário</li>
                          <li><i class="fa fa-check"></i> App móvel</li>
                          <li><i class="fa fa-check"></i> Até 10 usuários</li>
                          <li><i class="fa fa-check"></i> Integrações avançadas</li>
                          <li><i class="fa fa-times"></i> API de acesso</li>
                        </ul>
                      </div>
                      <div class="plan-footer">
                        <button class="btn btn-success w-100 btn-lg" disabled>Plano Ativo</button>
                      </div>
                    </div>

                    <!-- Plano Enterprise -->
                    <div class="plan-card">
                      <span class="plan-badge featured">MAIS POPULAR</span>
                      <div class="plan-header">
                        <div class="plan-name">Enterprise</div>
                        <div class="plan-price">
                          R$ <span class="monthly-price">299</span><span class="yearly-price" style="display: none;">239</span>
                          <small>/mês</small>
                        </div>
                        <div class="plan-period">
                          <span class="monthly-period">Cobrado mensalmente</span>
                          <span class="yearly-period" style="display: none;">R$ 2.868/ano - Economize R$ 720</span>
                        </div>
                      </div>
                      <div class="plan-body">
                        <ul class="plan-features">
                          <li><i class="fa fa-check"></i> Lojas ilimitadas</li>
                          <li><i class="fa fa-check"></i> Vendas ilimitadas</li>
                          <li><i class="fa fa-check"></i> Relatórios personalizados</li>
                          <li><i class="fa fa-check"></i> Suporte 24/7</li>
                          <li><i class="fa fa-check"></i> App móvel</li>
                          <li><i class="fa fa-check"></i> Usuários ilimitados</li>
                          <li><i class="fa fa-check"></i> Todas as integrações</li>
                          <li><i class="fa fa-check"></i> API completa</li>
                        </ul>
                      </div>
                      <div class="plan-footer">
                        <button class="btn btn-primary w-100 btn-lg">Fazer Upgrade</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Perguntas Frequentes (melhorado) -->
                <div class="col-12">
                  <div class="card mt-1 mb-5">
                    <div class="card-header">
                      <h5 class="card-title mb-0">Perguntas Frequentes</h5>
                    </div>
                    <div class="card-body">
                      <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                          <span>Como funciona o plano anual com desconto?</span>
                          <i class="fa fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                          <p class="mb-0 small text-secondary">
                            No plano anual, você paga 12 meses de uma vez com desconto (ex.: 20% OFF) e mantém o
                            valor travado durante o período.
                          </p>
                        </div>
                      </div>
                      <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                          <span>Posso mudar de plano a qualquer momento?</span>
                          <i class="fa fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                          <p class="mb-0 small text-secondary">
                            Sim. Você pode solicitar upgrade ou downgrade a qualquer momento. Mudanças de plano podem
                            ser cobradas proporcionalmente no próximo ciclo.
                          </p>
                        </div>
                      </div>
                      <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                          <span>O que acontece se eu ultrapassar o limite de lojas do meu plano?</span>
                          <i class="fa fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                          <p class="mb-0 small text-secondary">
                            Ao atingir o limite, você será avisado e poderá desativar uma loja ou fazer upgrade para
                            um plano superior.
                          </p>
                        </div>
                      </div>
                      <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                          <span>Consigo acessar notas fiscais antigas?</span>
                          <i class="fa fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                          <p class="mb-0 small text-secondary">
                            Sim. As notas fiscais ficam disponíveis no histórico de pagamentos.
                          </p>
                        </div>
                      </div>
                      <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                          <span>Como eu cancelo a assinatura?</span>
                          <i class="fa fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                          <p class="mb-0 small text-secondary">
                            Você pode cancelar a qualquer momento em <strong>Histórico</strong>. O acesso permanece
                            ativo até o fim do período já pago.
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>

      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">
          Assinaturas ModernPOS
        </div>
        <strong>ModernPOS</strong> &mdash; planos e assinatura.
      </footer>

      <!-- Modal: Assinar Básico (centralizado) -->
      <div class="modal fade" id="modalAssinarBasico" tabindex="-1" aria-labelledby="modalAssinarBasicoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalAssinarBasicoLabel">Assinar Plano Básico</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <p class="mb-2">
                Você está prestes a assinar o <strong>Plano Básico</strong>.
              </p>
              <ul class="small text-secondary mb-0">
                <li>Até 2 lojas</li>
                <li>Vendas ilimitadas</li>
                <li>Relatórios básicos</li>
              </ul>
              <div class="alert alert-info mt-3 mb-0" role="alert">
                Dica: ative o <strong>plano anual</strong> para aplicar desconto.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-primary">Confirmar Assinatura</button>
            </div>
          </div>
        </div>
      </div>
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

    <!-- OverlayScrollbars para sidebar -->
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

        // Billing Toggle (Mensal x Anual)
        const billingToggle = document.getElementById('billingToggle');
        if (billingToggle) {
          const monthlyPrices = document.querySelectorAll('.monthly-price');
          const yearlyPrices = document.querySelectorAll('.yearly-price');
          const monthlyPeriods = document.querySelectorAll('.monthly-period');
          const yearlyPeriods = document.querySelectorAll('.yearly-period');
          const annualDiscountBadge = document.getElementById('annualDiscountBadge');

          const applyBillingView = (yearly) => {
            monthlyPrices.forEach((el) => (el.style.display = yearly ? 'none' : 'inline'));
            yearlyPrices.forEach((el) => (el.style.display = yearly ? 'inline' : 'none'));
            monthlyPeriods.forEach((el) => (el.style.display = yearly ? 'none' : 'inline'));
            yearlyPeriods.forEach((el) => (el.style.display = yearly ? 'inline' : 'none'));
            if (annualDiscountBadge) annualDiscountBadge.style.display = yearly ? 'inline-flex' : 'none';
          };

          // estado inicial
          applyBillingView(billingToggle.checked);

          billingToggle.addEventListener('change', function () {
            applyBillingView(this.checked);
          });
        }

        // Filtro: Histórico de Pagamentos (Todos / Pagos / Pendentes)
        const paymentTable = document.getElementById('paymentHistoryTable');
        const filterButtons = document.querySelectorAll('[data-payment-filter]');
        if (paymentTable && filterButtons.length) {
          const rows = paymentTable.querySelectorAll('tbody tr');

          const applyPaymentFilter = (filter) => {
            rows.forEach((row) => {
              const status = (row.getAttribute('data-status') || '').toLowerCase();
              const visible = filter === 'all' ? true : status === filter;
              row.style.display = visible ? '' : 'none';
            });
          };

          filterButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
              filterButtons.forEach((b) => b.classList.remove('active'));
              btn.classList.add('active');
              applyPaymentFilter(btn.getAttribute('data-payment-filter'));
            });
          });

          applyPaymentFilter('all');
        }

        // FAQ Toggle
        window.toggleFaq = function (element) {
          const faqItem = element.parentElement;
          const isActive = faqItem.classList.contains('active');

          document.querySelectorAll('.faq-item').forEach((item) => {
            item.classList.remove('active');
          });

          if (!isActive) {
            faqItem.classList.add('active');
          }
        };
      });
    </script>
  </body>
</html>
