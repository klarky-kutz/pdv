<?php
header('Content-Type: text/html; charset=utf-8');
ob_start();
session_start();
include realpath(__DIR__.'/../../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

if (user_group_id() != 1 && !has_permission('access', 'concierge_groups_access')) {
  redirect(root_url().ADMINDIRNAME.'/dashboard.php');
}

// â”€â”€ Carregar helpers do Moda IA â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
require_once DIR_HELPER . 'ai_concierge.php';
require_once DIR_HELPER . 'ai_plan_gate.php';
require_once DIR_HELPER . 'ai_groups_helper.php';

// â”€â”€ Dados (Neste momento, dados estáticos para o visual funcional) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$tenantId = ai_tenant_id();
$groupsStats = ai_get_groups_stats($tenantId);
$groupsList = ai_get_concierge_groups($tenantId);
$totalMembers = $groupsStats['total_members'] ?? 0;
$totalGroups = $groupsStats['total_groups'] ?? 0;
$totalBroadcasts = $groupsStats['total_broadcasts'] ?? 0;

// Get connected WhatsApp number
$aiWhatsappNumber = ai_get_setting('ai_whatsapp_number', '', $tenantId);
if (empty($aiWhatsappNumber)) {
    $aiWhatsappNumber = '5511999999999';
}
// Clean number: remove non-digit characters
$aiWhatsappNumber = preg_replace('/[^0-9]/', '', $aiWhatsappNumber);

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$document->setTitle('Grupos IA · Moda IA');
$document->setBodyClass('concierge_grupos');

include ("../header.php");
include ("../left_sidebar.php");
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-bullhorn" style="color:#6d28d9;margin-right:8px"></i>Grupos IA</h1>
    <ol class="breadcrumb">
      <li><a href="../dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Grupos IA</li>
    </ol>
  </section>

  <section class="content">
    <?php if (!ai_groups_plan_is_enabled()): ?>
      <div class="box box-solid">
        <div class="box-body">
          <?php ai_render_groups_plan_locked_overlay(); ?>
        </div>
      </div>
    <?php else: ?>
        <link rel="stylesheet" href="CSS/concierge_grupos.css">

    <div class="mia-grupos-root">
      <!-- ACTIONS -->
      <div style="display:flex;justify-content:flex-end;gap:7px;margin-bottom:14px">
        <button class="btn btn-secondary" onclick="abrirMiaModal('agenda')"><i class="fa fa-calendar"></i> Agenda</button>
        <button class="btn btn-secondary" onclick="abrirMiaModal('status-automacao')"><i class="fa fa-circle-o-notch"></i> Status Manual</button>
        <button class="btn btn-ai" onclick="abrirMiaModal('ia-grupos-automacao')"><i class="fa fa-magic"></i> Automação IA</button>
        <button class="btn btn-wpp" onclick="abrirMiaModal('novo-disparo')"><i class="fa fa-whatsapp"></i> Novo Disparo</button>
      </div>

      <!-- AI BAR -->
      <div class="ai-bar" id="mia-ai-bar" style="display:none">
        <div class="ai-bar-icon"><i class="fa fa-magic"></i></div>
        <div class="ai-bar-info">
          <div class="ai-bar-title" id="mia-ai-bar-title">IA analisando catálogo...</div>
          <div class="ai-bar-sub" id="mia-ai-bar-sub">-- produtos identificados · -- campanhas prontas</div>
          <div class="ai-bar-prog"><div class="ai-bar-fill" style="width:0%"></div></div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="abrirMiaModal('ia-campanhas')"><i class="fa fa-eye"></i> Ver Sugestões</button>
      </div>

      <!-- STATS -->
      <div class="stats-row">
        <div class="stat-card c-purple">
          <div class="stat-top">
            <div><div class="stat-val"><?php echo $totalBroadcasts; ?></div><div class="stat-lbl">Disparos Totais</div></div>
            <div class="stat-icon"><i class="fa fa-bullhorn"></i></div>
          </div>
          <div class="stat-delta up"><i class="fa fa-arrow-up"></i> +<?php echo ai_count_monthly_group_broadcasts($tenantId); ?> este mês</div>
        </div>
        <div class="stat-card c-green">
          <div class="stat-top">
            <div><div class="stat-val"><?php echo $totalGroups; ?></div><div class="stat-lbl">Grupos Ativos</div></div>
            <div class="stat-icon"><i class="fa fa-users"></i></div>
          </div>
          <div class="stat-delta neutral">Sincronizado via API</div>
        </div>
        <div class="stat-card c-amber">
          <div class="stat-top">
            <div><div class="stat-val" id="mia-stat-scheduled">--</div><div class="stat-lbl">Agendados</div></div>
            <div class="stat-icon"><i class="fa fa-clock-o"></i></div>
          </div>
          <div class="stat-delta neutral" id="mia-stat-next-sched">Sem agendamentos</div>
        </div>
        <div class="stat-card c-blue">
          <div class="stat-top">
            <div><div class="stat-val"><?php echo number_format($totalMembers); ?></div><div class="stat-lbl">Alcance Total</div></div>
            <div class="stat-icon"><i class="fa fa-users"></i></div>
          </div>
          <div class="stat-delta up">Membros em grupos</div>
        </div>

      </div>

      <!-- FILTERS -->
      <div class="filter-zone">
        <span class="filter-zone-label"><i class="fa fa-filter"></i> Filtrar</span>
        <div class="fc on" data-filter="todos" onclick="filtrarMia(this,'todos')">Todos <span id="mia-count-todos" style="background:#e2e8f0;border-radius:8px;padding:1px 6px;font-size:10px">0</span></div>
        <div class="fc" data-filter="aprovacao" onclick="filtrarMia(this,'aprovacao')"><i class="fa fa-magic" style="color:#7c3aed"></i> Aprovação <span id="mia-count-aprovacao" style="background:#ede9fe;border-radius:8px;padding:1px 6px;font-size:10px;color:#5b21b6">0</span></div>
        <div class="fc" data-filter="agendado" onclick="filtrarMia(this,'agendado')"><i class="fa fa-clock-o"></i> Agendados <span id="mia-count-agendado" style="background:#dbeafe;border-radius:8px;padding:1px 6px;font-size:10px;color:#1d4ed8">0</span></div>
        <div class="fc" data-filter="enviado" onclick="filtrarMia(this,'enviado')"><i class="fa fa-check-circle" style="color:#15803d"></i> Enviados <span id="mia-count-enviado" style="background:#dcfce7;border-radius:8px;padding:1px 6px;font-size:10px;color:#15803d">0</span></div>
        <div class="fc" data-filter="enviando" onclick="filtrarMia(this,'enviando')"><i class="fa fa-spinner"></i> Enviando <span id="mia-count-enviando" style="background:#ede9fe;border-radius:8px;padding:1px 6px;font-size:10px;color:#5b21b6">0</span></div>
        <div class="filter-sep"></div>
        <div class="fz-search">
          <i class="fa fa-search"></i>
          <input type="text" id="mia-search-campanha" placeholder="Buscar campanha ou produto..." onkeyup="debounceBuscaMia()">
        </div>
      </div>

      <!-- LAYOUT -->
      <div class="layout-grid">
        <!-- LEFT: TABLE -->
        <div class="bcast-panel">
          <div class="panel-hdr">
            <div class="panel-hdr-title"><i class="fa fa-paper-plane"></i> Histórico & Gerenciamento</div>
            <div style="display:flex;align-items:center;gap:12px">
              <div class="mia-view-tabs" style="display:flex;background:#f1f5f9;padding:2px;border-radius:8px;gap:2px">
                <button class="fc on" style="border:none;border-radius:6px;font-size:10.5px;padding:4px 10px" onclick="mudarVisaoMia(this, 'campanhas')"><i class="fa fa-bullhorn"></i> Campanhas</button>
                <button class="fc" style="border:none;border-radius:6px;font-size:10.5px;padding:4px 10px" onclick="mudarVisaoMia(this, 'status')"><i class="fa fa-circle-o-notch"></i> Status</button>
                <button class="fc" style="border:none;border-radius:6px;font-size:10.5px;padding:4px 10px" onclick="mudarVisaoMia(this, 'grupos')"><i class="fa fa-users"></i> Grupos</button>
              </div>
              <span style="font-size:10px;color:#94a3b8;font-weight:600" id="mia-reg-count">0 registros</span>
              <span class="panel-filter-loading" id="mia-filter-loading"><i class="fa fa-spinner fa-spin"></i> Atualizando...</span>
            </div>
          </div>

          <!-- IA PENDING -->
          <div class="ai-pending-section" id="mia-pending-section" style="display:none">
            <div id="pending-campanhas"></div>
            <div id="pending-status" style="display:none"></div>
            <div id="pending-grupos" style="display:none"></div>
          </div>

          <!-- TABLE HEADERS -->
          <div id="tbl-hdr-campanhas" class="tbl-hdr">
            <div></div>
            <div>Produto / Campanha</div>
            <div><i class="fa fa-whatsapp"></i> Grupos</div>
            <div>Agendamento</div>
            <div style="text-align:center">Alcance</div>
            <div style="text-align:center">Vezes</div>
            <div style="text-align:center">Status</div>
            <div></div>
          </div>
          <div id="tbl-hdr-status" class="tbl-hdr" style="display:none;grid-template-columns:46px 1fr 120px 120px 140px 60px 80px;">
            <div></div>
            <div>Produto (Status)</div>
            <div>Status</div>
            <div>Horário Postagem</div>
            <div style="text-align:center">Postagem Semanal</div>
            <div style="text-align:center">Vezes</div>
            <div style="text-align:center">Ações</div>
          </div>
          <div id="tbl-hdr-grupos" class="tbl-hdr" style="display:none;grid-template-columns:46px 1fr 140px 170px 130px 110px;">
            <div></div>
            <div>Nome do Grupo</div>
            <div>Configuração</div>
            <div>Realizados / Progresso</div>
            <div>Próximo Disparo</div>
            <div style="text-align:center">Ações</div>
          </div>

          <div class="panel-body" id="mia-broadcast-list">
            <div class="mia-view-section" id="view-campanhas">
              <div style="padding:16px;color:#94a3b8;font-size:12px" id="mia-campaigns-loading"><i class="fa fa-spinner fa-spin"></i> Carregando campanhas...</div>
            </div>

            <!-- LISTA STATUS -->
            <div class="mia-view-section" id="view-status" style="display:none">
              <div style="padding:16px;color:#94a3b8;font-size:12px">Nenhuma postagem automática de status detectada.</div>
            </div>

            <!-- LISTA GRUPOS -->
            <div class="mia-view-section" id="view-grupos" style="display:none"></div>
            <div class="panel-empty" id="mia-empty-state"><i class="fa fa-search" style="margin-right:6px"></i>Nenhum registro encontrado para este filtro.</div>
          </div>
        </div>

        <!-- RIGHT: PANELS -->
        <div class="right-panel">
          <div class="rcard">
            <div class="next-hero" id="mia-next-hero">
              <div class="next-label">Próximo Agendado</div>
              <div style="font-size:12.5px;font-weight:700;margin-bottom:3px" id="mia-next-title">Nenhum disparo agendado</div>
              <div class="next-time" id="mia-next-time">--:--</div>
              <div style="font-size:10.5px;color:#a5b4fc;margin:8px 0" id="mia-next-date">Sem previsão</div>
              <div style="display:flex;gap:5px;flex-wrap:wrap" id="mia-next-groups"></div>
            </div>
          </div>

          <div class="rcard">
            <div class="rcard-hdr">
              <div class="rcard-hdr-title" style="color:#15803d"><i class="fa fa-whatsapp" style="color:#22c55e"></i> Grupos WhatsApp + Controle Diário</div>
              <button class="btn btn-secondary btn-sm" onclick="abrirMiaModal('grupos')"><i class="fa fa-cog"></i> Configurar</button>
            </div>
            <div class="rcard-body">
              <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px">Recursos de Grupos WhatsApp</div>
              <div id="mia-side-groups" style="font-size:12px;color:#94a3b8">Carregando grupos...</div>
              <div class="sep" style="margin:9px 0"></div>
              <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px">Disparos de Grupos</div>
              <div id="mia-quota-list">
                 <div style="font-size:11px;color:#94a3b8;padding:10px 0">Aguardando sincronização de grupos...</div>
              </div>
            </div>
          </div>

          <div class="rcard">
            <div class="rcard-hdr">
              <div class="rcard-hdr-title"><i class="fa fa-whatsapp" style="color:#7c3aed"></i> Automação Status WhatsApp</div>
              <button class="btn btn-ai btn-sm" onclick="abrirMiaModal('status-automacao')"><i class="fa fa-magic"></i> IA</button>
            </div>
            <div class="rcard-body">
              <div class="status-auto-box">
                <div class="status-auto-title"><i class="fa fa-circle-o-notch"></i> Postagem inteligente</div>
                <div class="status-auto-sub">IA sugerindo produtos estrategicamente</div>
                <div class="status-kpi">
                  <div class="status-kpi-item">
                    <div class="status-kpi-val" id="mia-status-kpi-today">--</div>
                    <div class="status-kpi-lbl">Posts hoje</div>
                  </div>
                  <div class="status-kpi-item">
                    <div class="status-kpi-val" id="mia-status-kpi-products">--</div>
                    <div class="status-kpi-lbl">Faltam</div>
                  </div>
                  <div class="status-kpi-item">
                    <div class="status-kpi-val" id="mia-status-kpi-repeat">--</div>
                    <div class="status-kpi-lbl">Repetição</div>
                  </div>
                </div>
                <div class="status-last-post" id="mia-status-last-post">
                  <div style="font-size:11px;color:#64748b"><i class="fa fa-history"></i> Último status:</div>
                  <div style="font-size:11.5px;margin-top:2px;color:#94a3b8">Nenhuma postagem recente.</div>
                </div>
              </div>
              <button class="btn btn-secondary btn-sm" style="width:100%;margin-top:8px;justify-content:center" onclick="abrirMiaModal('status-automacao')"><i class="fa fa-sliders"></i> Configurar automações de status</button>
            </div>
          </div>

          <!-- ATIVIDADE RECENTE -->
          <div class="rcard">
            <div class="rcard-hdr">
              <div class="rcard-hdr-title"><i class="fa fa-history" style="color:#7c3aed"></i> Atividade Recente</div>
            </div>
            <div class="rcard-body" style="padding:4px 12px 10px" id="mia-activity-feed">
               <div style="padding:16px;color:#94a3b8;font-size:11.5px;text-align:center">Nenhuma atividade registrada hoje.</div>
            </div>
          </div>
        </div>
      </div>

  <!-- MODAL: CONFIRMAR LIMPAR FOTOS -->
  <div class="mia-overlay hide" id="mia-ov-clear-photos" style="z-index: 1000001;">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-exclamation-triangle" style="color:#f59e0b"></i> Limpar todas as fotos?</div>
          <div class="mh-sub">Esta ação removerá todas as fotos selecionadas. Tem certeza?</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('clear-photos')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
          <button class="btn btn-secondary" onclick="fecharMiaModal('clear-photos')">Cancelar</button>
          <button class="btn btn-danger" onclick="clearAllPhotos(); fecharMiaModal('clear-photos')"><i class="fa fa-trash"></i> Limpar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: CONFIRMAR LIMPAR MàDIAS STATUS -->
  <div class="mia-overlay hide" id="mia-ov-clear-status-media" style="z-index: 1000001;">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-exclamation-triangle" style="color:#f59e0b"></i> Limpar todas as mídias do Status?</div>
          <div class="mh-sub">Esta ação removerá todas as mídias selecionadas. Tem certeza?</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('clear-status-media')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
          <button class="btn btn-secondary" onclick="fecharMiaModal('clear-status-media')">Cancelar</button>
          <button class="btn btn-danger" onclick="clearAllStatusMedia(); fecharMiaModal('clear-status-media')"><i class="fa fa-trash"></i> Limpar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: CONFIRMAR DELETAR TODO HISTÓRICO -->
  <div class="mia-overlay hide" id="mia-ov-clear-status-history" style="z-index: 1000001;">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-exclamation-triangle" style="color:#f59e0b"></i> Deletar todo o Histórico?</div>
          <div class="mh-sub">Esta ação removerá todas as postagens do histórico. Tem certeza?</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('clear-status-history')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
          <button class="btn btn-secondary" onclick="fecharMiaModal('clear-status-history')">Cancelar</button>
          <button class="btn btn-danger" onclick="clearAllStatusHistory(); fecharMiaModal('clear-status-history')"><i class="fa fa-trash"></i> Deletar Tudo</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: GERENCIAR GRUPOS -->
  <div class="mia-overlay hide" id="mia-ov-grupos">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-users"></i> Gerenciar Grupos WhatsApp</div>
          <div class="mh-sub">Vincule os grupos da Evolution API ao módulo Grupos IA</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('grupos')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:10px;margin-bottom:14px">
          <div style="width:30px;height:30px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0"><i class="fa fa-whatsapp"></i></div>
          <div style="flex:1">
            <div style="font-size:12px;font-weight:700;color:#14532d">Evolution API Conectada</div>
            <div style="font-size:11px;color:#15803d" id="mia-evolution-sync-info">Sincronização ativa · Buscando grupos...</div>
          </div>
          <button class="btn btn-secondary btn-sm" id="mia-sync-groups-btn" onclick="miaSyncGroups()"><i class="fa fa-refresh" id="mia-sync-groups-btn-icon"></i> <span id="mia-sync-groups-btn-label">Sync</span></button>
        </div>
        <div class="mia-sync-loading-box" id="mia-sync-loading-box">
          <div class="mia-sync-logo" aria-hidden="true">
            <span class="mia-sync-ring ring-a"></span>
            <span class="mia-sync-ring ring-b"></span>
            <div class="mia-sync-core"><i class="fa fa-whatsapp"></i></div>
          </div>
          <div class="mia-sync-loading-content">
            <div class="mia-sync-loading-title" id="mia-sync-loading-title">Sincronizando grupos com a Evolution...</div>
            <div class="mia-sync-loading-sub" id="mia-sync-loading-sub">A busca pode levar até 1 minuto. Aguarde enquanto atualizamos a lista.</div>
            <div class="mia-sync-loading-line"><span></span></div>
          </div>
        </div>

        <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Grupos detectados na instância</div>
        <div id="mia-groups-detected-grid" class="group-check-list">
           <!-- Populado via JS: miaLoadGroups() -->
           <div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8;font-size:12px">Carregando grupos...</div>
        </div>

        <div style="margin-top:14px;">
          <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Categorias Permitidas para IA</label>
          <div id="mia-group-cats-grid" style="display:grid;grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));gap:8px;background:#f8fafc;padding:12px;border:1px solid #e2e8f0;border-radius:8px;max-height:120px;overflow-y:auto">
             <div style="font-size:11px;color:#94a3b8">Carregando categorias...</div>
          </div>
        </div>
        
        <div class="sep"></div>
        <div class="fg-row">
          <div class="fg" style="margin:0">
            <label>Intervalo entre mensagens (ms)</label>
            <input class="finput" type="number" id="mia-group-delay" value="3000">
            <div style="font-size:10.5px;color:#94a3b8;margin-top:3px"><i class="fa fa-shield"></i> Recomendado: 2000â€“5000ms</div>
          </div>
          <div class="fg" style="margin:0">
            <label>Limite diário de disparos</label>
            <select class="fselect" id="mia-group-global-limit">
              <option value="5">5/dia</option>
              <option value="10" selected>10/dia</option>
              <option value="20">20/dia</option>
              <option value="0">Sem limite</option>
            </select>
          </div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('grupos')">Cancelar</button>
        <button class="btn btn-primary" onclick="miaSaveGroupsModal()"><i class="fa fa-check"></i> Salvar Configuração</button>
      </div>
    </div>
  </div>

  <!-- MODAL: STATUS DO DISPARO -->
  <div class="mia-overlay hide" id="mia-ov-status-campanha">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-bar-chart"></i> Status do Disparo</div>
          <div class="mh-sub" id="status-campanha-nome">Carregando detalhes...</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('status-campanha')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div class="status-summary">
          <div class="ss-card success">
            <div class="ss-val" id="mia-ov-sc-sent">--</div>
            <div class="ss-lbl">Enviados</div>
          </div>
          <div class="ss-card pending">
            <div class="ss-val" id="mia-ov-sc-targets">--</div>
            <div class="ss-lbl">Alvos</div>
          </div>
          <div class="ss-card">
            <div class="ss-val" id="mia-ov-sc-errors">--</div>
            <div class="ss-lbl">Erros</div>
          </div>
        </div>

        <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Destinos do Disparo</div>
        <div class="delivery-list" id="mia-ov-sc-list">
           <div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px">Buscando informações de entrega...</div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('status-campanha')">Fechar</button>
      </div>
    </div>
  </div>

  <!-- MODAL: SUGESTàƒO IA DE STATUS -->
  <div class="mia-overlay hide" id="mia-ov-sugestao-status">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-circle-o-notch"></i> Sugestão IA · Status</div>
          <div class="mh-sub" id="mia-ov-ss-subtitle">Analisando tendências...</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('sugestao-status')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal" id="mia-ov-ss-body">
         <div style="padding:40px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Gerando sugestão inteligente...</div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('sugestao-status')">Fechar</button>
        <button class="btn btn-wpp" id="mia-ov-ss-approve" style="display:none" onclick="fecharMiaModal('sugestao-status')"><i class="fa fa-check"></i> Aprovar Sugestão</button>
      </div>
    </div>
  </div>

  <!-- MODAL: SUGESTàƒO IA DE GRUPO -->
  <div class="mia-overlay hide" id="mia-ov-sugestao-grupo">
    <div class="mia-modal">
      <div class="mh-modal" style="background:linear-gradient(135deg,#065f46,#047857)">
        <div>
          <div class="mh-title"><i class="fa fa-users"></i> Sugestão IA · Grupo</div>
          <div class="mh-sub" id="mia-ov-sg-subtitle" style="color:#bbf7d0">Identificando melhor oportunidade...</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('sugestao-grupo')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal" id="mia-ov-sg-body">
         <div style="padding:40px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Processando dados do grupo...</div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('sugestao-grupo')">Fechar</button>
        <button class="btn btn-primary" id="mia-ov-sg-approve" style="display:none" onclick="fecharMiaModal('sugestao-grupo')"><i class="fa fa-check"></i> Aplicar Sugestão</button>
      </div>
    </div>
  </div>

  <!-- MODAL: AGENDA -->
  <div class="mia-overlay hide" id="mia-ov-agenda">
    <div class="mia-modal xl">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-calendar"></i> Agenda de Disparos</div>
          <div class="mh-sub">Visualize e gerencie todos os disparos agendados no calendário</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('agenda')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div class="cal-header">
          <div class="cal-month-title" id="mia-cal-title">Carregando calendário...</div>
          <div class="cal-nav">
            <button class="cal-nav-btn" onclick="miaPrevMonth()"><i class="fa fa-chevron-left"></i></button>
            <button class="cal-nav-btn" style="width:auto;padding:0 12px;font-size:11.5px;font-weight:600;color:#374151" onclick="miaGoToday()">Hoje</button>
            <button class="cal-nav-btn" onclick="miaNextMonth()"><i class="fa fa-chevron-right"></i></button>
          </div>
        </div>
        
        <div class="cal-grid" id="mia-cal-grid">
           <!-- Gerado dinamicamente via JS -->
        </div>

        <div class="cal-legend">
          <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#ede9fe;border:1px solid #c4b5fd"></div> Enviando</div>
          <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#dbeafe;border:1px solid #93c5fd"></div> Agendado</div>
          <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#dcfce7;border:1px solid #86efac"></div> Enviado</div>
          <div class="cal-legend-item" style="margin-left:auto"><i class="fa fa-info-circle" style="color:#7c3aed;font-size:11px"></i> <span style="color:#7c3aed;font-weight:600">Clique num dia para agendar</span></div>
        </div>

        <div class="agenda-next-list">
          <div class="agenda-next-title">Próximos disparos</div>
          <div id="mia-agenda-list">
             <div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px">Buscando agendamentos futuros...</div>
          </div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('agenda')">Fechar</button>
        <button class="btn btn-wpp" onclick="fecharMiaModal('agenda');abrirMiaModal('novo-disparo')"><i class="fa fa-whatsapp"></i> Novo Disparo</button>
      </div>
    </div>
  </div>

  <!-- MODAL: AUTOMAÇàƒO IA DE GRUPOS -->
  <div class="mia-overlay hide" id="mia-ov-ia-grupos-automacao">
    <div class="mia-modal lg">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-magic"></i> Automação de Postagens (IA)</div>
          <div class="mh-sub">Gerencie o limite diário e as categorias de produtos para disparos automáticos nos grupos.</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('ia-grupos-automacao')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px">
            <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Fluxo da automação</div>
            <div class="timeline">
              <div class="tl-item">
                <div class="tl-dot info"></div>
                <div class="tl-content">
                  <div class="tl-time">1. Produto / Catálogo</div>
                  <div class="tl-title">Análise inteligente de estoque</div>
                </div>
              </div>
              <div class="tl-item">
                <div class="tl-dot info"></div>
                <div class="tl-content">
                  <div class="tl-time">2. Regras</div>
                  <div class="tl-title">Categorias e limites diários</div>
                </div>
              </div>
              <div class="tl-item">
                <div class="tl-dot success"></div>
                <div class="tl-content">
                  <div class="tl-time">3. Publicação</div>
                  <div class="tl-title">Disparo automático via API</div>
                </div>
              </div>
            </div>
          </div>
          <div style="background:linear-gradient(135deg,#faf5ff,#f5f3ff);border:1px solid #ddd6fe;border-radius:10px;padding:12px">
            <div style="font-size:10px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Status Atual</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px" id="mia-ov-ia-stats">
              <div class="status-kpi-item"><div class="status-kpi-val">--</div><div class="status-kpi-lbl">Grupos</div></div>
              <div class="status-kpi-item"><div class="status-kpi-val">--</div><div class="status-kpi-lbl">Capacidade</div></div>
              <div class="status-kpi-item"><div class="status-kpi-val">--</div><div class="status-kpi-lbl">Hoje</div></div>
              <div class="status-kpi-item"><div class="status-kpi-val">--</div><div class="status-kpi-lbl">Restante</div></div>
            </div>
            <div style="font-size:11px;color:#7c3aed;margin-top:9px"><i class="fa fa-info-circle"></i> Os limites são aplicados individualmente por grupo para segurança anti-ban.</div>
          </div>
        </div>

        <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Configurações da Automação</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="fg" style="margin:0">
            <label>Nome da Automação</label>
            <input class="finput" type="text" id="mia-ia-auto-name" value="Automação Inteligente Geral">
          </div>
          <div class="fg" style="margin:0">
            <label>Status</label>
            <select class="fselect" id="mia-ia-auto-status">
              <option value="1" selected>Ativo</option>
              <option value="0">Pausado</option>
            </select>
          </div>
          <div class="fg" style="margin:0">
            <label>Horário Inicial Diário</label>
            <input class="finput" type="time" id="mia-ia-auto-time" value="09:00">
          </div>
          <div class="fg" style="margin:0">
            <label>Limite Global de Produtos/Dia</label>
            <input class="finput" type="number" id="mia-ia-auto-limit" value="10" min="1">
          </div>
        </div>

        <div style="margin-top: 14px;">
          <label style="font-size:11px;font-weight:700;color:#475569;margin-bottom:8px;display:block">Categorias Permitidas para IA</label>
          <div id="mia-ia-cats-grid" style="display:grid;grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));gap:8px;background:#f8fafc;padding:12px;border:1px solid #e2e8f0;border-radius:8px;max-height:120px;overflow-y:auto">
             <div style="font-size:11px;color:#94a3b8">Carregando categorias...</div>
          </div>
        </div>

        <div class="sep"></div>
        <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Limites individuais por grupo</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="mia-ia-quota-grid">
           <div style="font-size:11px;color:#94a3b8">Nenhum grupo ativo.</div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('ia-grupos-automacao')">Cancelar</button>
        <button class="btn btn-primary" onclick="miaSaveIAConfig()"><i class="fa fa-check"></i> Salvar Configurações</button>
      </div>
    </div>
  </div>

  <!-- MODAL: CAMPANHAS IA -->
  <div class="mia-overlay hide" id="mia-ov-ia-campanhas">
    <div class="mia-modal">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-magic"></i> Campanhas Geradas pela IA</div>
          <div class="mh-sub">Sugestões baseadas no seu estoque atual</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('ia-campanhas')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal" id="mia-ia-sug-list">
         <div style="padding:40px;text-align:center;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Buscando oportunidades no catálogo...</div>
      </div>
    </div>
  </div>

  <!-- MODAL: NOVO DISPARO (WIZARD) -->
  <div class="mia-overlay hide" id="mia-ov-novo-disparo">
    <div class="mia-modal lg">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-whatsapp"></i> Novo Disparo</div>
          <div class="mh-sub">Produto â†’ Conteúdo IA â†’ Grupos & Agenda</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('novo-disparo')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div class="wizard">
          <div class="ws-item">
            <div class="ws-circle act" id="ws1">1</div>
            <div class="ws-lbl act" id="wl1">Produto</div>
          </div>
          <div class="ws-line" id="wline1"></div>
          <div class="ws-item">
            <div class="ws-circle pend" id="ws2">2</div>
            <div class="ws-lbl" id="wl2">Conteúdo</div>
          </div>
          <div class="ws-line" id="wline2"></div>
          <div class="ws-item">
            <div class="ws-circle pend" id="ws3">3</div>
            <div class="ws-lbl" id="wl3">Agenda</div>
          </div>
        </div>

        <!-- STEP 1 -->
        <div id="ms1">
          <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:10px">Selecione o produto a divulgar:</div>
          <div class="fz-search" style="max-width:100%;margin-bottom:12px">
            <i class="fa fa-search"></i>
            <input type="text" id="mia-search-prod" placeholder="Buscar produto no catálogo..." class="finput" style="padding-left:30px" onkeyup="miaSearchProducts()">
          </div>
          <div class="prod-grid" id="mia-prod-grid">
             <div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8">Carregando produtos...</div>
          </div>
          <div class="fg-row" style="margin-top:13px">
            <div class="fg"><label>Tom de voz <span class="lbl-badge ai">IA</span></label>
              <select class="fselect" id="mia-input-tone" onchange="updateMiaWppPreviewCta()">
                <option value="desejo">✨ Desejo — exclusividade, luxo</option>
                <option value="urgencia">🔥 Urgência — últimas peças</option>
                <option value="casual" selected>💚 Casual — leve e amigável</option>
                <option value="festivo">🎉 Festivo — novidade e lançamento</option>
                <option value="oferta">💰 Oferta — desconto e vantagem</option>
                <option value="promocao" disabled>🎊 Promoção — em Breve</option>
              </select>
            </div>
            <div class="fg"><label>CTA principal <span class="lbl-badge ai">IA</span></label>
              <select class="fselect" id="mia-input-cta" onchange="updateMiaWppPreviewCta()">
                <option value="chama">📲 "Chama no privado!"</option>
                <option value="manda">💬 "Me manda mensagem!"</option>
                <option value="reserva">🛍️ "Quer reservar?"</option>
                <option value="quero">🙋‍♂️ "Eu Quero!"</option>
                <option value="corre">⚡ "Corre! Últimas unidades!"</option>
              </select>
            </div>
          </div>
        </div>

        <!-- STEP 2 -->
        <div id="ms2" style="display:none">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div>
              <!-- Toggle: Mensagem única ou individual -->
              <div class="fg" style="margin-bottom:12px">
                <label>Modo de mensagem</label>
                <div style="display:flex;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:4px">
                  <button class="mia-msg-mode-btn sel" onclick="setMiaMsgMode('single')" id="mia-msg-mode-single">
                    <i class="fa fa-comment"></i> Uma mensagem para todos
                  </button>
                  <button class="mia-msg-mode-btn" onclick="setMiaMsgMode('individual')" id="mia-msg-mode-individual">
                    <i class="fa fa-comments"></i> Mensagem individual por card
                  </button>
                </div>
              </div>

              <!-- Campo de Link do Botão CTA (only visible in individual mode) -->
              <div class="fg" style="margin-bottom:12px;display:none" id="mia-main-cta-link-container">
                <label>Link do Botão CTA</label>
                <input type="url" class="finput" id="mia-cta-link" placeholder="https://wa.me/5511999999999?text=Olá, quero saber mais" oninput="updateWppCtaPrev()">
                <div style="font-size:10.5px;color:#94a3b8;margin-top:4px">Link para onde o cliente será direcionado (ex: link do WhatsApp privado)</div>
              </div>

              <!-- Mensagem única (padrão) -->
              <div id="mia-single-msg-container">
              <div class="fg">
                <label style="display:flex;align-items:center;justify-content:space-between">
                  <span>Mensagem <span class="lbl-badge ai">IA</span></span>
                  <span style="font-size:11px;color:#94a3b8;font-weight:400" id="mia-char-count">0 chars</span>
                </label>
                
                <div class="msg-toolbar">
                  <button class="tbtn" onclick="formatMiaMsg('*')"><i class="fa fa-bold"></i></button>
                  <button class="tbtn" onclick="formatMiaMsg('_')"><i class="fa fa-italic"></i></button>
                  <button class="tbtn" onclick="formatMiaMsg('~')"><i class="fa fa-strikethrough"></i></button>
                  <button class="tbtn" onclick="formatMiaMsg('```')"><i class="fa fa-code"></i></button>
                  <button class="tbtn emoji-btn" onclick="toggleMiaEmoji()"><i class="fa fa-smile-o"></i> Emojis</button>
                </div>

                <div id="mia-emoji-picker" style="display:none;position:absolute;z-index:1000;background:#fff;border:1px solid #ddd;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.15);margin-top:5px;width:340px;overflow:hidden">
                  <emoji-picker class="light"></emoji-picker>
                </div>

                <textarea class="ftextarea" id="ia-msg" style="min-height:180px;border-top:none;border-radius:0 0 8px 8px" oninput="updateMiaCharCount(); miaRenderPreviewCarousel()" placeholder="Descreva o produto ou deixe a IA gerar o texto..."></textarea>
              </div>
              <div style="display:flex;gap:6px;margin-bottom:12px">
                <button class="btn btn-ai btn-sm" onclick="regerarIA()"><i class="fa fa-magic"></i> Gerar com IA</button>
                <button class="btn btn-secondary btn-sm" onclick="miaCopyMessage()"><i class="fa fa-copy"></i> Copiar</button>
              </div>
              </div>

              <!-- Mensagens individuais (oculto por padrão) -->
              <div id="mia-individual-msg-container" style="display:none">
                <div class="fg">
                  <label>Mensagens individuais</label>
                  <div id="mia-individual-msgs" style="display:flex;flex-direction:column;gap:8px"></div>
                </div>
              </div>
              
              <div id="mia-examples-container" style="display:none;margin-bottom:15px">
                <label style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;display:block">Sugestões do Tom de Voz:</label>
                <div id="mia-examples-list" style="display:flex;flex-direction:column;gap:8px"></div>
              </div>
              
              <div class="fg">
                <label style="display:flex;align-items:center;justify-content:space-between">
                  <span>Fotos anexadas</span>
                  <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-size:10px;color:#94a3b8;font-weight:600">(máx. 4)</span>
                    <button class="btn btn-sm btn-danger" id="btn-clear-photos" style="padding:2px 8px;font-size:10px;display:none" onclick="confirmClearPhotos()">
                      <i class="fa fa-trash"></i> Limpar
                    </button>
                  </div>
                </label>
                <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:4px" id="mia-msg-photos">
                </div>
                <div style="font-size:10.5px;color:#94a3b8;margin-top:4px" id="mia-msg-photos-help">0 de 4 selecionadas.</div>
              </div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:7px;display:flex;align-items:center;gap:5px"><i class="fa fa-whatsapp" style="color:#22c55e;font-size:13px"></i> Preview WhatsApp</div>
              <div class="wpp-wrap wpp-wrap-drawer wpp-wrap-etapa2">
                <div class="wpp-header-bar">
                  <div class="wpp-h-av" id="mia-wpp-prev-av">L</div>
                  <div>
                    <div class="wpp-h-name" id="mia-wpp-prev-name">Loja</div>
                    <div class="wpp-h-status wpp-h-status-live" id="mia-wpp-prev-status"><span class="wpp-status-dot"></span> Online</div>
                  </div>
                  <div class="wpp-h-actions">
                    <i class="fa fa-video-camera"></i>
                    <i class="fa fa-phone"></i>
                    <i class="fa fa-ellipsis-v"></i>
                  </div>
                </div>
                <div class="wpp-body wpp-body-drawer">
                  <div class="wpp-bubble wpp-bubble-drawer">
                    <div class="wpp-carousel wpp-carousel-cards wpp-carousel-drawer" id="mia-wpp-carousel"></div>
                    <div class="wpp-msg" id="wpp-text-prev">Sua mensagem aparecerá aqui...</div>
                    <div class="wpp-cta" id="mia-wpp-cta-prev"><i class="fa fa-comment-o"></i> Quero ver mais</div>
                  </div>
                  <div class="wpp-meta-row">
                    <div class="wpp-indicators" id="mia-wpp-indicators"></div>
                    <div class="wpp-time-check">--:-- <i class="fa fa-check"></i></div>
                  </div>
                </div>
              </div>
              <div class="wpp-metrics">
                <div class="wpp-metric"><div class="wpp-metric-val" id="mia-wpp-metric-reach">0</div><div class="wpp-metric-lbl">Alcance</div></div>
                <div class="wpp-metric"><div class="wpp-metric-val" id="mia-wpp-metric-groups">0</div><div class="wpp-metric-lbl">Grupos</div></div>
                <div class="wpp-metric"><div class="wpp-metric-val" id="mia-wpp-metric-rate">0%</div><div class="wpp-metric-lbl">Taxa méd.</div></div>
              </div>
            </div>
          </div>
        </div>

        <!-- STEP 3 -->
        <div id="ms3" style="display:none">
          <div class="ms3-grid">
            <div class="ms3-card">
              <div class="ms3-title"><i class="fa fa-users"></i> Grupos de destino <span class="lbl-badge req">Obrigatório</span></div>
              <div class="fg" style="margin-bottom:0">
                <div class="group-check-list" id="mia-groups-step3">
                   <div style="font-size:11px;color:#94a3b8">Carregando grupos...</div>
                </div>
              </div>
            </div>
            <div class="ms3-card">
              <div class="ms3-title"><i class="fa fa-calendar"></i> Agenda do disparo</div>
              <div class="fg" style="margin-bottom:0">
                <div class="sched-opts">
                  <div class="sched-opt" onclick="selMiaSched(this,'now')">
                    <i class="fa fa-bolt"></i>
                    <div class="sched-opt-lbl">Agora</div>
                    <div class="sched-opt-sub">Imediato</div>
                  </div>
                  <div class="sched-opt sel" onclick="selMiaSched(this,'schedule')">
                    <i class="fa fa-calendar"></i>
                    <div class="sched-opt-lbl">Agendar</div>
                    <div class="sched-opt-sub">Manual</div>
                  </div>
                </div>
                <div id="mia-sched-fields">
                  <div id="mia-sched-now" style="display:none">
                    <div style="background:#f0fdf4;padding:10px;border-radius:8px;border:1px solid #bbf7d0;margin-bottom:12px;font-size:12px;color:#15803d">
                      <i class="fa fa-info-circle"></i> Será enviado imediatamente ao salvar
                    </div>
                  </div>
                  <div id="mia-sched-manual">
                    <div class="fg-row" style="gap:10px">
                      <div class="fg" style="margin:0"><label>Data</label><input class="finput" type="date" id="mia-input-date" value="<?php echo date('Y-m-d'); ?>"></div>
                      <div class="fg" style="margin:0"><label>Horário</label><input class="finput" type="time" id="mia-input-time" value="<?php echo date('H:i', strtotime('+30 minutes')); ?>"></div>
                    </div>
                  </div>
                  <div id="mia-sched-common" style="margin-top:12px">
                    <div class="fg">
                      <label>Intervalo entre grupos</label>
                      <select class="fselect" id="mia-input-interval">
                        <option value="0">Sem intervalo</option>
                        <option value="5">5 min entre grupos</option>
                        <option value="10" selected>10 min entre grupos</option>
                        <option value="30">30 min entre grupos</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary btn-sm" onclick="fecharMiaModal('novo-disparo')" style="margin-right:auto">Cancelar</button>
        <button class="btn btn-secondary btn-sm" id="mbtn-back" style="display:none" onclick="mStepBack()"><i class="fa fa-chevron-left"></i> Voltar</button>
        <button class="btn btn-primary btn-sm" id="mbtn-next" onclick="mStepNext()">Próximo <i class="fa fa-chevron-right"></i></button>
      </div>
    </div>
  </div>

  <!-- MODAL: AUTOMAÇàƒO STATUS WHATSAPP -->
  <div class="mia-overlay hide" id="mia-ov-status-automacao">
    <div class="mia-modal lg">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-whatsapp"></i> Automação & Status Manual</div>
          <div class="mh-sub">Gerencie postagens inteligentes e agendamentos manuais</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('status-automacao')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        
        <div style="display:grid;grid-template-columns: 1fr 1fr; gap: 20px;">
          <!-- COLUNA ESQUERDA: CONFIGS E FORM -->
          <div style="display:flex; flex-direction:column; gap:20px">
            
            <!-- CARD: AUTOMAÇàƒO IA -->
            <div style="background:linear-gradient(135deg,#f8fafc,#f1f5f9);padding:15px;border-radius:12px;border:1px solid #e2e8f0">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                 <div>
                    <div style="font-size:13px;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:6px"><i class="fa fa-magic" style="color:#7c3aed"></i> Automação (IA)</div>
                    <div style="font-size:10.5px;color:#64748b">Postagens automáticas inteligentes</div>
                 </div>
                 <label class="toggle"><input type="checkbox" id="mia-status-auto-enable"><span class="toggle-sl"></span></label>
              </div>

              <div class="fg-row" style="gap:10px">
                <div class="fg" style="margin:0"><label>Posts/dia</label><input class="finput" type="number" id="mia-status-auto-count" value="4" min="1" max="20"></div>
                <div class="fg" style="margin:0"><label>Repetição</label>
                  <select class="fselect" id="mia-status-auto-rep">
                    <option value="1">1 vez</option>
                    <option value="3" selected>Até 3x</option>
                    <option value="5">Até 5x</option>
                  </select>
                </div>
              </div>
              
              <div class="fg-row" style="gap:10px;margin-top:10px">
                <div class="fg" style="margin:0"><label>Dias para reutilizar produto</label><input class="finput" type="number" id="mia-status-auto-days" value="3" min="1" max="30" placeholder="3"></div>
                <div class="fg" style="margin:0"><label>Intervalo entre status (horas)</label><input class="finput" type="number" id="mia-status-auto-interval" value="1" min="1" max="24" placeholder="1"></div>
              </div>
              
              <div style="display:flex;gap:8px;margin-top:12px">
                <button class="btn btn-primary btn-sm" style="flex:1;justify-content:center" onclick="miaSaveStatusAuto()"><i class="fa fa-check"></i> Salvar Configurações</button>
                <button class="btn btn-ai btn-sm" style="flex:1;justify-content:center" onclick="miaGenerateIntelligentStatusesNow()"><i class="fa fa-bolt"></i> Gerar Agora</button>
              </div>
            </div>

            <!-- CARD: NOVO STATUS MANUAL -->
            <div style="background:#fff;padding:15px;border-radius:12px;border:1px solid #e2e8f0;flex:1">
              <div style="font-size:11px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:15px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-plus-circle"></i> Novo Status Manual
              </div>
              
              <div class="fg"><label>Legenda</label><textarea class="ftextarea" id="mia-manual-status-caption" placeholder="O que você quer dizer?..." style="min-height:70px;font-size:12.5px"></textarea></div>

              <div class="fg">
                <label style="display:flex;justify-content:space-between;align-items:center">
                  <span>Mídias (Fotos/URLs)</span>
                  <button type="button" class="btn btn-secondary btn-sm" style="padding:3px 8px;font-size:10px" onclick="miaOpenMediaPicker('status')"><i class="fa fa-photo"></i> Biblioteca</button>
                </label>
                <div style="position:relative;margin-top:5px">
                  <i class="fa fa-link" style="position:absolute;left:10px;top:10px;color:#94a3b8;font-size:12px"></i>
                  <input class="finput" type="text" id="mia-manual-status-media" placeholder="URLs separadas por vírgula..." style="padding-left:30px;font-size:11.5px" oninput="miaRenderManualStatusPreview()">
                </div>
              </div>

              <div class="fg-row" style="gap:10px;margin-top:12px">
                <div class="fg" style="margin:0"><label>Data</label><input class="finput" type="date" id="mia-manual-status-date" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="fg" style="margin:0"><label>Hora</label><input class="finput" type="time" id="mia-manual-status-time" value="<?php echo date('H:i', strtotime('+20 minutes')); ?>"></div>
              </div>

              <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px; margin-top:12px">
                <div style="font-size:10.5px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:12px; display:flex; align-items:center; gap:6px">
                  <i class="fa fa-refresh" style="color:#7c3aed"></i> Ciclo de Repostagem
                </div>
                
                <div class="fg" style="margin-bottom:15px">
                  <label style="font-size:10px; color:#475569; text-transform:uppercase; margin-bottom:8px">Dias da Semana</label>
                  <div class="day-selector" id="mia-status-days" style="display:flex; justify-content:space-between; gap:2px">
                    <button type="button" class="day-btn" data-day="0" onclick="toggleMiaDay(this)" title="Domingo">D</button>
                    <button type="button" class="day-btn" data-day="1" onclick="toggleMiaDay(this)" title="Segunda">S</button>
                    <button type="button" class="day-btn" data-day="2" onclick="toggleMiaDay(this)" title="Terça">T</button>
                    <button type="button" class="day-btn" data-day="3" onclick="toggleMiaDay(this)" title="Quarta">Q</button>
                    <button type="button" class="day-btn" data-day="4" onclick="toggleMiaDay(this)" title="Quinta">Q</button>
                    <button type="button" class="day-btn" data-day="5" onclick="toggleMiaDay(this)" title="Sexta">S</button>
                    <button type="button" class="day-btn" data-day="6" onclick="toggleMiaDay(this)" title="Sábado">S</button>
                  </div>
                </div>

                <div class="fg" style="margin:0">
                  <label style="font-size:10px; color:#475569; text-transform:uppercase">Total de Repetições</label>
                  <div style="position:relative; margin-top:5px">
                    <input class="finput" type="number" id="mia-manual-status-rep" value="1" min="1" max="90" style="padding-right:35px; font-weight:700">
                    <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:11px; font-weight:700; color:#94a3b8">x</span>
                  </div>
                </div>

                <div style="background:#fff; border:1px solid #ede9fe; border-radius:8px; padding:10px; margin-top:12px; border-left:3px solid #7c3aed">
                  <div style="font-size:10.5px; color:#4c1d95; line-height:1.4; font-weight:500">
                    <i class="fa fa-magic"></i> <strong>Como funciona:</strong> Selecione os dias que deseja postar. O sistema repetirá a postagem nestes dias até completar o total de vezes escolhido.
                  </div>
                </div>
              </div>

              <div class="fg" style="margin-top:15px">
                <div style="font-size:11px;font-weight:700;color:#475569;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between">
                  <span><i class="fa fa-eye"></i> Preview</span>
                  <div style="display:flex;align-items:center;gap:8px">
                    <span id="mia-manual-status-preview-count" style="font-weight:400;color:#94a3b8;font-size:10px">0 mídias</span>
                    <button class="btn btn-sm btn-danger" id="btn-clear-status-media" style="padding:2px 8px;font-size:10px;display:none" onclick="confirmClearStatusMedia()">
                      <i class="fa fa-trash"></i> Limpar tudo
                    </button>
                  </div>
                </div>
                <div id="mia-manual-status-preview" class="media-preview-grid" style="background:#f8fafc;padding:8px;border-radius:10px;border:1px dashed #cbd5e1;min-height:70px;display:grid;grid-template-columns:repeat(4,1fr);gap:6px">
                  <div style="grid-column:1/-1;text-align:center;padding:10px;color:#94a3b8;font-size:11px">Nenhuma mídia selecionada.</div>
                </div>
              </div>
              
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px">
                <button class="btn btn-secondary" style="justify-content:center;padding:10px;border-radius:10px" onclick="miaCreateManualStatus(false)">
                  <i class="fa fa-calendar-plus-o"></i> Agendar
                </button>
                <button class="btn btn-wpp" style="justify-content:center;padding:10px;border-radius:10px" onclick="miaCreateManualStatus(true)">
                  <i class="fa fa-paper-plane"></i> Enviar Agora
                </button>
              </div>
            </div>
          </div>

          <!-- COLUNA DIREITA: RESUMO E HISTÓRICO -->
          <div style="display:flex; flex-direction:column; gap:20px">
            <!-- RESUMO -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:15px">
              <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px">Resumo Geral</div>
              <div class="status-summary" style="margin:0; gap:8px">
                <div class="ss-card pending" style="padding:10px"><div class="ss-val" id="mia-manual-sched-count" style="font-size:16px">0</div><div class="ss-lbl" style="font-size:8.5px">Agendados</div></div>
                <div class="ss-card success" style="padding:10px"><div class="ss-val" id="mia-manual-done-count" style="font-size:16px">0</div><div class="ss-lbl" style="font-size:8.5px">Realizados</div></div>
                <div class="ss-card" style="padding:10px"><div class="ss-val" id="mia-manual-progress" style="font-size:16px">0%</div><div class="ss-lbl" style="font-size:8.5px">Eficiência</div></div>
              </div>
            </div>

            <!-- HISTÓRICO -->
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:15px;flex:1;display:flex;flex-direction:column;min-height:0">
              <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
                <span>Histórico Recente</span>
                <div style="display:flex;gap:6px">
                  <button type="button" class="icon-btn" onclick="miaLoadStatusHistory()" title="Atualizar Histórico"><i class="fa fa-refresh"></i></button>
                  <button type="button" class="icon-btn" id="btn-clear-status-history" onclick="confirmClearStatusHistory()" title="Deletar Todo Histórico" style="display:none"><i class="fa fa-trash" style="color:#ef4444"></i></button>
                </div>
              </div>
              <div id="mia-status-history-list" style="flex:1; overflow-y:auto; max-height:380px" class="status-history-list">
                 <div style="padding:20px;text-align:center;color:#94a3b8;font-size:11.5px"><i class="fa fa-spinner fa-spin"></i> Carregando...</div>
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('status-automacao')">Fechar Janela</button>
      </div>
    </div>
  </div>

  <!-- MODAL: ADICIONAR URL MANUAL -->
  <div class="mia-overlay hide" id="mia-ov-add-url" style="z-index: 1000000;">
    <div class="mia-modal sm">
      <div class="mh-modal">
        <div class="mh-title"><i class="fa fa-link"></i> Adicionar URL de Mídia</div>
        <button class="mh-close" onclick="fecharMiaModal('add-url')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div class="fg">
          <label>URL da Imagem ou Vídeo</label>
          <input class="finput" type="text" id="mia-input-manual-url" placeholder="https://exemplo.com/imagem.jpg">
          <div style="font-size:10.5px;color:#94a3b8;margin-top:6px">Certifique-se de que a URL seja pública e termine em .jpg, .png, .webp ou .mp4.</div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary btn-sm" onclick="fecharMiaModal('add-url')">Cancelar</button>
        <button class="btn btn-primary btn-sm" onclick="miaConfirmAddManualUrl()">Adicionar à  Lista</button>
      </div>
    </div>
  </div>

  <!-- MODAL: SELECIONAR MàDIAS -->
  <div class="mia-overlay hide" id="mia-ov-media-picker">
    <div class="mia-modal lg">
      <div class="mh-modal">
        <div>
          <div class="mh-title"><i class="fa fa-photo"></i> Selecionar fotos anexadas</div>
          <div class="mh-sub" id="mia-media-picker-subtitle">Selecione até 4 mídias e adicione URLs extras quando necessário.</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('media-picker')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div class="media-picker-toolbar">
          <div class="media-picker-count" id="mia-media-picker-count">0 de 4 selecionadas</div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="miaMediaPickerAddUrls()"><i class="fa fa-plus"></i> Adicionar URL Manual</button>
        </div>

        <div class="media-picker-sec">
          <div class="media-picker-sec-title">Fotos do produto selecionado</div>
          <div id="mia-media-picker-product" class="media-picker-grid">
            <div style="grid-column:1/-1;font-size:11px;color:#94a3b8">Selecione um produto para exibir as fotos.</div>
          </div>
        </div>

        <div class="media-picker-sec">
          <div class="media-picker-sec-title">Banco de dados (catálogo)</div>
          <div id="mia-media-picker-catalog" class="media-picker-grid">
            <div style="grid-column:1/-1;font-size:11px;color:#94a3b8">Carregando mídias do catálogo...</div>
          </div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('media-picker')">Cancelar</button>
        <button class="btn btn-primary" onclick="miaApplyMediaPickerSelection()"><i class="fa fa-check"></i> Usar Seleção</button>
      </div>
    </div>
  </div>
  <!-- MODAL: CONFIRMAR EXCLUSàƒO -->
  <div class="mia-overlay hide" id="mia-ov-confirm-del" style="z-index: 4000;">
    <div class="mia-modal" style="max-width:400px;margin-top:10vh">
      <div class="mh-modal" style="background:linear-gradient(135deg,#6d28d9,#4c1d95);padding:12px 15px">
        <div class="mh-title" id="mia-confirm-del-title">Confirmar Ação?</div>
        <button class="mh-close" onclick="fecharMiaModal('confirm-del')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal" style="padding:20px;text-align:center">
        <div id="mia-confirm-del-text" style="font-size:13px;color:#475569;line-height:1.5">Deseja realmente prosseguir com esta ação?</div>
      </div>
      <div class="mf-modal" style="padding:10px 15px;background:#f8fafc;display:flex;justify-content:center;gap:10px">
        <button class="btn btn-secondary" onclick="fecharMiaModal('confirm-del')">Cancelar</button>
        <button class="btn btn-danger" id="mia-confirm-del-btn">Confirmar</button>
      </div>
    </div>
  </div>

  <!-- MODAL: EDITAR CONTEÚDO DA CAMPANHA -->
  <div class="mia-overlay hide" id="mia-ov-edit-campaign" style="z-index: 5000;">
    <div class="mia-modal" style="max-width:950px">
      <div class="mh-modal" style="background:linear-gradient(135deg,#7c3aed,#5b21b6)">
        <div>
          <div class="mh-title"><i class="fa fa-pencil"></i> Editar Conteúdo</div>
          <div class="mh-sub">Personalize a mensagem da sua campanha</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('edit-campaign')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div>
            <div class="fg">
              <label style="display:flex;justify-content:space-between;align-items:center">
                <span>Título da Campanha</span>
                <span style="font-size:10px;color:#94a3b8">Apenas para controle interno</span>
              </label>
              <input type="text" class="finput" id="mia-edit-campaign-title" placeholder="Ex: Promoção de Verão">
            </div>
            
            <!-- Modo de mensagem (edit) -->
            <div class="fg" style="margin-bottom:12px">
              <label>Modo de mensagem</label>
              <div style="display:flex;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:4px">
                <button class="mia-msg-mode-btn sel" onclick="setMiaEditMsgMode('single')" id="mia-edit-msg-mode-single">
                  <i class="fa fa-comment"></i> Uma mensagem para todos
                </button>
                <button class="mia-msg-mode-btn" onclick="setMiaEditMsgMode('individual')" id="mia-edit-msg-mode-individual">
                  <i class="fa fa-comments"></i> Mensagem individual por card
                </button>
              </div>
            </div>

            <!-- Mensagem única (padrão para edit) -->
            <div id="mia-edit-single-msg-container">
              <div class="fg">
                <label style="display:flex;justify-content:space-between;align-items:center">
                  <span>Mensagem</span>
                  <span style="font-size:11px;color:#94a3b8;font-weight:400" id="mia-edit-char-count">0 chars</span>
                </label>
                
                <div class="msg-toolbar">
                  <button class="tbtn" onclick="formatMiaEditMsg('*')"><i class="fa fa-bold"></i></button>
                  <button class="tbtn" onclick="formatMiaEditMsg('_')"><i class="fa fa-italic"></i></button>
                  <button class="tbtn" onclick="formatMiaEditMsg('~')"><i class="fa fa-strikethrough"></i></button>
                  <button class="tbtn" onclick="formatMiaEditMsg('```')"><i class="fa fa-code"></i></button>
                  <button class="tbtn emoji-btn" onclick="toggleMiaEditEmoji()"><i class="fa fa-smile-o"></i> Emojis</button>
                </div>

                <div id="mia-edit-emoji-container" style="display:none;position:absolute;z-index:1000;background:#fff;border:1px solid #ddd;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.15);margin-top:5px;width:340px;overflow:hidden">
                  <emoji-picker class="light"></emoji-picker>
                </div>

                <textarea class="ftextarea" id="mia-edit-campaign-content" style="min-height:180px;border-top:none;border-radius:0 0 8px 8px" oninput="updateMiaEditCharCount()" placeholder="Digite sua mensagem aqui..."></textarea>
                
                <div style="font-size:10.5px;color:#64748b;margin-top:8px">
                  <i class="fa fa-info-circle"></i> Use <strong>*texto*</strong> para negrito e <strong>_texto_</strong> para itálico.
                </div>
              </div>
            </div>

            <!-- Mensagens individuais (oculto por padrão para edit) -->
            <div id="mia-edit-individual-msg-container" style="display:none">
              <div class="fg">
                <label>Mensagens individuais</label>
                <div id="mia-edit-individual-msgs" style="display:flex;flex-direction:column;gap:8px"></div>
              </div>
            </div>
          </div>

          <div>
            <!-- Preview WhatsApp (edit) -->
            <div class="fg">
              <label>Preview</label>
              <div class="wpp-wrap">
                <div class="wpp-header-bar">
                  <div class="wpp-h-av">M</div>
                  <div>
                    <div class="wpp-h-name">Meu Grupo</div>
                    <div class="wpp-h-status">Prévia da mensagem</div>
                  </div>
                </div>
                <div class="wpp-body">
                  <div class="wpp-bubble">
                    <div class="wpp-carousel" id="mia-edit-wpp-carousel"></div>
                  </div>
                  <div class="wpp-meta-row">
                    <div class="wpp-indicators" id="mia-edit-wpp-indicators"></div>
                    <div class="wpp-time-check">12:34 <i class="fa fa-check"></i></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('edit-campaign')">Cancelar</button>
        <button class="btn btn-primary" id="mia-edit-campaign-save-btn" onclick="miaSaveCampaignEdit()"><i class="fa fa-save"></i> Salvar Alterações</button>
      </div>
    </div>
  </div>

  <!-- MODAL: EDITAR GRUPOS ALVO DA CAMPANHA -->
  <div class="mia-overlay hide" id="mia-ov-edit-groups" style="z-index: 5000;">
    <div class="mia-modal" style="max-width:700px">
      <div class="mh-modal" style="background:linear-gradient(135deg,#22c55e,#16a34a)">
        <div>
          <div class="mh-title"><i class="fa fa-users"></i> Editar Grupos Alvo</div>
          <div class="mh-sub">Selecione os grupos para esta campanha</div>
        </div>
        <button class="mh-close" onclick="fecharMiaModal('edit-groups')"><i class="fa fa-times"></i></button>
      </div>
      <div class="mb-modal">
        <div class="fg">
          <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:block">Grupos disponíveis</label>
          <div id="mia-edit-groups-grid" class="group-check-list" style="max-height:350px">
            <div style="grid-column:1/-1;padding:20px;text-align:center;color:#94a3b8;font-size:12px">Carregando grupos...</div>
          </div>
        </div>
      </div>
      <div class="mf-modal">
        <button class="btn btn-secondary" onclick="fecharMiaModal('edit-groups')">Cancelar</button>
        <button class="btn btn-primary" id="mia-edit-groups-save-btn" onclick="miaSaveCampaignGroups()"><i class="fa fa-save"></i> Salvar Grupos</button>
      </div>
    </div>
  </div>

  <!-- DETALHE DA CAMPANHA (DRAWER) -->
  <div class="mia-drawer-ov" id="mia-drawer-ov" onclick="fecharMiaDrawer()"></div>
  <div class="mia-drawer" id="mia-drawer">
    <div id="mia-drawer-content">
       <!-- O conteúdo será injetado via JS -->
    </div>
  </div>

  <!-- TOAST -->
  <div class="mia-toast info" id="mia-toast"><i class="fa fa-info-circle" id="mia-toast-icon"></i> <span id="mia-toast-msg">Operação concluída</span></div>

</div><!-- /mia-grupos-root -->
</section><!-- /content -->
</div><!-- /content-wrapper -->

<script>
window.MIA_API = {
  groups: '<?php echo root_url(); ?>api/concierge/groups.php',
  campaigns: '<?php echo root_url(); ?>api/concierge/campaigns.php',
  products: '<?php echo root_url(); ?>api/concierge/products.php',
  status: '<?php echo root_url(); ?>api/concierge/status.php',
  campaignText: '<?php echo root_url(); ?>api/concierge/campaign_text.php'
};
window.MIA_CAN_MANAGE = <?php echo (user_group_id() == 1 || has_permission('access', 'concierge_groups_manage')) ? 'true' : 'false'; ?>;
window.MIA_CAN_AI_CREATE = <?php echo (user_group_id() == 1 || has_permission('access', 'concierge_groups_ai_create')) ? 'true' : 'false'; ?>;
window.MIA_TENANT_ID = <?php echo (int)$tenantId; ?>;
window.MIA_ROOT = '<?php echo root_url(); ?>';
window.MIA_WHATSAPP_NUMBER = <?php echo json_encode($aiWhatsappNumber); ?>;
window.MIA_STORE_NAME = <?php echo json_encode(ai_get_store_name($tenantId)); ?>;
window.MIA_TOKEN = <?php
  $st = db()->prepare("SELECT ai_webhook_token FROM stores WHERE store_id = ? LIMIT 1");
  $st->execute([$tenantId]);
  echo json_encode((string)$st->fetchColumn());
?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!/\/admin\/grupo\/concierge_grupos\.php$/i.test(window.location.pathname)) return;
  var containers = document.querySelectorAll('.main-sidebar, .main-header');
  if (!containers || !containers.length) return;

  Array.prototype.forEach.call(containers, function (container) {
    var links = container.querySelectorAll('a[href]');
    Array.prototype.forEach.call(links, function (link) {
      if (link.getAttribute('data-mia-normalized') === '1') return;
      var href = (link.getAttribute('href') || '').trim();
      if (!href) return;

      if (
        href.indexOf('http://') === 0 ||
        href.indexOf('https://') === 0 ||
        href.indexOf('//') === 0 ||
        href.indexOf('/') === 0 ||
        href.indexOf('../') === 0 ||
        href.indexOf('#') === 0 ||
        href.indexOf('?') === 0 ||
        href.indexOf('javascript:') === 0 ||
        href.indexOf('mailto:') === 0 ||
        href.indexOf('tel:') === 0
      ) {
        return;
      }

      link.setAttribute('href', '../' + href.replace(/^\.\/+/, ''));
      link.setAttribute('data-mia-normalized', '1');
    });
  });
});
</script>
<script src="JS/concierge_grupos.js"></script>
<script src="JS/concierge_grupos_novas_funcionalidades.js"></script>
<script src="JS/concierge_grupos_edit_campaign.js"></script>
<?php endif; // End of plan check ?>
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1/index.js"></script>

<?php include ("../footer.php"); ?>




