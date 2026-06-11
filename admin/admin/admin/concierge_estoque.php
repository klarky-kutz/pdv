<?php
ob_start();
session_start();
include realpath(__DIR__.'/../').'/_init.php';

if (!is_loggedin()) {
  redirect(root_url().'index.php?redirect_to=' . url());
}

$document->setTitle('Estoque IA · Moda IA');
$document->setBodyClass('concierge_estoque');

include ("header.php");
include ("left_sidebar.php");
?>
<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-cubes" style="color:#6d28d9;margin-right:8px"></i>Gestão de Estoque IA</h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
      <li>Moda IA</li>
      <li class="active">Estoque IA</li>
    </ol>
  </section>
  <section class="content">
<style>
@keyframes mia-fadeIn{from{opacity:0}to{opacity:1}}
#mia-root{display:flex;flex-direction:column;gap:14px}
.mia-actions{display:flex;justify-content:flex-end;gap:8px}
#mia-root .btn{display:inline-flex!important;align-items:center;gap:6px;padding:8px 14px;border-radius:2px!important;font-size:13px;font-weight:600;transition:all .18s;cursor:pointer}
#mia-root .btn-primary{background:linear-gradient(135deg,#6d28d9,#7c3aed)!important;color:#fff!important;border:none!important}
#mia-root .btn-secondary{background:#fff!important;border:1px solid #d1d5db!important;color:#374151!important}
#mia-root .btn-secondary:hover{border-color:#a78bfa!important;color:#6d28d9!important}
#mia-root .btn-sm{padding:5px 10px!important;font-size:12px!important}
.ib-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.ib{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:14px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);position:relative;overflow:hidden}
.ib-icon{width:62px;height:62px;border-radius:2px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0}
.ib-icon.violet{background:linear-gradient(135deg,#6d28d9,#7c3aed)}
.ib-icon.amber{background:linear-gradient(135deg,#d97706,#f59e0b)}
.ib-icon.green{background:linear-gradient(135deg,#059669,#10b981)}
.ib-icon.red{background:linear-gradient(135deg,#dc2626,#ef4444)}
.ib-content{flex:1;min-width:0}
.ib-num{font-size:26px;font-weight:900;color:#111827;line-height:1.1}
.ib-label{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.ib-sub{font-size:11px;color:#9ca3af;margin-top:3px}
.ib-badge{position:absolute;top:10px;right:10px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px}
.ib-badge.up{background:#d1fae5;color:#065f46}
.ib-badge.warn{background:#fef3c7;color:#92400e}
.ib-badge.crit{background:#fee2e2;color:#991b1b}
.filter-bar{background:#fff;border:1px solid #e5e7eb;border-radius:2px;padding:10px 14px;display:flex;align-items:center;gap:10px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.fb-label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;display:flex;align-items:center;gap:5px}
.fb-chips{display:flex;gap:6px;flex-wrap:wrap}
.fb-chip{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #d1d5db;color:#6b7280;cursor:pointer;transition:all .15s;background:#fff;display:flex;align-items:center;gap:4px}
.fb-chip:hover{border-color:#a78bfa;color:#6d28d9;background:#f5f3ff}
.fb-chip.on{background:#ede9fe;border-color:#c4b5fd;color:#4c1d95}
.fb-spacer{flex:1}
.fb-search{display:flex;align-items:center;gap:7px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:2px;padding:5px 10px}
.fb-search i{color:#9ca3af;font-size:13px}
.fb-search input{border:none;background:transparent;outline:none;font-size:13px;color:#374151;width:180px}
.fb-search input::placeholder{color:#9ca3af}
.mia-box{background:#fff;border:1px solid #e5e7eb;border-radius:2px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden}
.bh{border-top:3px solid #6d28d9;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f3f4f6}
.bt{font-size:14px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px}
.bt i{color:#6d28d9}
.bt .count{background:#ede9fe;color:#4c1d95;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
.bt .count-crit{background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
.bh-actions{display:flex;gap:8px;align-items:center}
#mia-root table{width:100%;border-collapse:collapse}
#mia-root thead th{background:#f9fafb;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:1px solid #e5e7eb;white-space:nowrap}
#mia-root tbody tr{border-bottom:1px solid #f3f4f6;transition:background .12s}
#mia-root tbody tr:hover{background:#faf5ff}
#mia-root tbody tr.row-crit{background:#fff8f8}
#mia-root tbody tr.row-crit:hover{background:#fff0f0}
#mia-root tbody tr.row-zero{background:#fafafa;opacity:.7}
#mia-root tbody tr:last-child{border-bottom:none}
#mia-root td{padding:10px 14px;vertical-align:middle}
.bf{background:#f9fafb;border-top:1px solid #f3f4f6;padding:10px 16px;display:flex;align-items:center;justify-content:space-between}
.bf-info{font-size:12px;color:#9ca3af;font-weight:600}
#mia-root .badge{font-size:10px!important;font-weight:700!important;padding:3px 8px!important;border-radius:20px!important;display:inline-flex!important;align-items:center;gap:4px;white-space:nowrap;line-height:normal!important;background-color:transparent!important}
#mia-root .badge-ok{background:#d1fae5!important;color:#065f46!important}
#mia-root .badge-crit{background:#fee2e2!important;color:#991b1b!important}
#mia-root .badge-zero{background:#f3f4f6!important;color:#6b7280!important}
#mia-root .badge-low{background:#fef3c7!important;color:#92400e!important}
#mia-root .pagination{display:flex!important;gap:4px!important;padding:0;margin:0;list-style:none}
#mia-root .pg-btn{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:2px;border:1px solid #d1d5db;background:#fff;color:#6b7280;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
#mia-root .pg-btn:hover{border-color:#a78bfa;color:#6d28d9}
#mia-root .pg-btn.act{background:linear-gradient(135deg,#6d28d9,#7c3aed);border-color:#6d28d9;color:#fff}
/* estoque-specific */
.prod-name{font-weight:700;color:#111827;font-size:13px}
.prod-id{font-size:11px;color:#9ca3af;margin-top:2px}
.prod-cat{display:inline-block;background:#f3f4f6;color:#6b7280;font-size:10px;padding:2px 6px;border-radius:10px;margin-top:3px;font-weight:600}
.color-cell{display:flex;align-items:center;gap:8px}
.swatch-lg{width:22px;height:22px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1.5px rgba(0,0,0,.15);flex-shrink:0}
.color-name{font-size:13px;font-weight:600;color:#374151}
.size-pill{display:inline-flex;align-items:center;justify-content:center;background:#ede9fe;color:#4c1d95;border:1px solid #c4b5fd;border-radius:2px;font-size:11px;font-weight:700;width:34px;height:24px}
.qty-ctrl{display:flex;align-items:center;gap:4px}
.qty-val{width:32px;height:24px;border:1px solid #d1d5db;border-radius:2px;text-align:center;font-size:12px;font-weight:700;color:#374151;background:#fff;outline:none}
.qty-val:focus{border-color:#7c3aed}
.qty-val.crit{border-color:#fca5a5;color:#dc2626;background:#fff8f8}
.qty-val.zero{border-color:#e5e7eb;color:#9ca3af;background:#f9fafb}
.btn-icon{width:22px;height:22px;display:flex;align-items:center;justify-content:center;border-radius:2px;border:1px solid #d1d5db;background:#fff;color:#6b7280;font-size:11px;cursor:pointer;transition:all .15s;flex-shrink:0}
.btn-icon:hover{border-color:#a78bfa;color:#6d28d9}
.demand-num{font-size:18px;font-weight:900;color:#4c1d95;line-height:1}
.demand-sub{font-size:10px;color:#9ca3af;font-weight:600;margin-top:2px}
.demand-bar{height:5px;background:#ede9fe;border-radius:3px;margin-top:5px;overflow:hidden}
.demand-fill{height:100%;background:linear-gradient(90deg,#7c3aed,#a78bfa);border-radius:3px}
/* toast */
.mia-toast{position:fixed;bottom:20px;right:20px;background:#222d32;color:#fff;border-left:3px solid #6d28d9;padding:10px 16px;border-radius:2px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;z-index:9999;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1);min-width:260px;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.mia-toast.show{transform:translateX(0)}
.mia-toast i{color:#a78bfa}
</style>

<div id="mia-root">
  <div class="mia-actions">
    <button class="btn btn-secondary btn-sm"><i class="fa fa-download"></i> Exportar</button>
    <button class="btn btn-primary" onclick="showToast('Estoque sincronizado!')"><i class="fa fa-refresh"></i> Sincronizar</button>
  </div>

  <div class="ib-grid">
    <div class="ib"><div class="ib-icon violet"><i class="fa fa-cubes"></i></div><div class="ib-content"><div class="ib-num" id="ib-total-skus">0</div><div class="ib-label">Total de SKUs</div><div class="ib-sub">variantes ativas</div></div></div>
    <div class="ib"><div class="ib-icon red"><i class="fa fa-times-circle"></i></div><div class="ib-content"><div class="ib-num" id="ib-zerados">0</div><div class="ib-label">SKUs Zerados</div><div class="ib-sub">sem estoque — IA bloqueia</div></div></div>
    <div class="ib"><div class="ib-icon amber"><i class="fa fa-exclamation-triangle"></i></div><div class="ib-content"><div class="ib-num" id="ib-criticos">0</div><div class="ib-label">SKUs Críticos</div><div class="ib-sub">até 3 unidades</div></div></div>
    <div class="ib"><div class="ib-icon green"><i class="fa fa-money"></i></div><div class="ib-content"><div class="ib-num" id="ib-valor">R$ 0,00</div><div class="ib-label">Valor Total Estoque</div><div class="ib-sub">preço × quantidade</div></div></div>
  </div>

  <div class="filter-bar">
    <span class="fb-label"><i class="fa fa-filter"></i> Filtrar</span>
    <div class="fb-chips">
      <div class="fb-chip on" onclick="filtrar(this,'')">Todos</div>
      <div class="fb-chip" onclick="filtrar(this,'critico')"><i class="fa fa-exclamation-triangle" style="color:#dc2626;font-size:9px"></i> Críticos</div>
      <div class="fb-chip" onclick="filtrar(this,'zerado')"><i class="fa fa-times-circle" style="color:#6b7280;font-size:10px"></i> Zerados</div>
      <div class="fb-chip" onclick="filtrar(this,'ok')"><i class="fa fa-check-circle" style="color:#059669;font-size:10px"></i> OK</div>
    </div>
    <div class="fb-spacer"></div>
    <div style="display:flex;align-items:center;gap:6px">
      <span class="fb-label">Mostrar:</span>
      <select id="stock-limit" class="form-control" style="height:28px;padding:2px 8px;font-size:12px;width:70px;border-radius:2px" onchange="carregarEstoque()">
        <option value="20" selected>20</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>
    <div style="width:1px;height:20px;background:#e5e7eb;margin:0 4px"></div>
    <div class="fb-search"><i class="fa fa-search"></i><input type="text" id="stock-search" placeholder="Buscar produto, cor ou tamanho..." oninput="debounceBuscar()"></div>
  </div>

  <div class="mia-box">
    <div class="bh">
      <span class="bt"><i class="fa fa-cubes"></i> Variantes de Estoque <span class="count" id="stock-count">0</span> <span class="count-crit" id="stock-count-crit">0 zerados · 0 críticos</span></span>
      <div class="bh-actions">
        <button class="btn btn-secondary btn-sm"><i class="fa fa-download"></i> Exportar CSV</button>
        <button class="btn btn-secondary btn-sm" onclick="carregarEstoque()"><i class="fa fa-refresh"></i> Atualizar</button>
      </div>
    </div>
    <table>
      <thead><tr><th>Produto</th><th>Cor</th><th>Tamanho</th><th style="color:#6d28d9">Demanda IA</th><th>Preço</th><th>Estoque Atual</th><th>Status</th></tr></thead>
      <tbody id="stock-body"></tbody>
    </table>
    <div class="bf">
      <span class="bf-info" id="stock-footer">—</span>
      <div class="pagination" id="stock-pagination"></div>
    </div>
  </div>
</div>

  </section>
</div>

<div class="mia-toast" id="toast"><i class="fa fa-magic"></i> <span id="toast-msg">Operação concluída</span></div>
<script>
let _tt;
function showToast(msg){const t=document.getElementById('toast');document.getElementById('toast-msg').textContent=msg;t.classList.add('show');clearTimeout(_tt);_tt=setTimeout(()=>t.classList.remove('show'),2800)}
let _stockStatus='';
let _stockTimer=null;
let _stockPage=1;

function filtrar(el,status){_stockStatus=status||'';_stockPage=1;document.querySelectorAll('.fb-chip').forEach(c=>c.classList.remove('on'));el.classList.add('on');carregarEstoque();}
function debounceBuscar(){_stockPage=1;clearTimeout(_stockTimer);_stockTimer=setTimeout(carregarEstoque,200);}
function mudarPagina(p){_stockPage=p;carregarEstoque();}

function carregarEstoque(){
  const q=(document.getElementById('stock-search')||{}).value||'';
  const limit=(document.getElementById('stock-limit')||{}).value||20;
  
  fetch('../_inc/ai_estoque_actions.php?action=list&status='+encodeURIComponent(_stockStatus)+'&search='+encodeURIComponent(q)+'&page='+_stockPage+'&limit='+limit)
    .then(r=>r.json()).then(d=>{
      if(d.error){showToast('Erro: '+(d.message||''));return;}
      document.getElementById('stock-body').innerHTML=d.rows_html||'';
      const s=d.summary||{};
      document.getElementById('ib-total-skus').textContent=s.total_skus||0;
      document.getElementById('ib-zerados').textContent=s.zerados||0;
      document.getElementById('ib-criticos').textContent=s.criticos||0;
      document.getElementById('ib-valor').textContent=money(s.valor_total||0);
      document.getElementById('stock-count').textContent=d.total_filtered||0;
      document.getElementById('stock-count-crit').textContent=(s.zerados||0)+' zerados · '+(s.criticos||0)+' críticos';
      
      const start = d.offset + 1;
      const end = Math.min(d.offset + d.count, d.total_filtered);
      document.getElementById('stock-footer').textContent='Exibindo '+start+'-'+end+' de '+(d.total_filtered||0)+' SKU(s) filtrados';
      
      renderPagination(d.total_filtered, limit, d.page);
    }).catch(()=>showToast('Erro ao carregar estoque.'));
}

function renderPagination(total, limit, current) {
  const container = document.getElementById('stock-pagination');
  if (!container) return;
  container.innerHTML = '';
  
  const pages = Math.max(1, Math.ceil(total / limit));
  const maxVisible = 5;
  let start = Math.max(1, current - 2);
  let end = Math.min(pages, start + maxVisible - 1);
  if (end - start < maxVisible - 1) start = Math.max(1, end - maxVisible + 1);

  if (current > 1) {
    container.innerHTML += `<div class="pg-btn" onclick="mudarPagina(${current-1})"><i class="fa fa-chevron-left"></i></div>`;
  }
  
  for (let i = start; i <= end; i++) {
    container.innerHTML += `<div class="pg-btn ${i===current?'act':''}" onclick="mudarPagina(${i})">${i}</div>`;
  }

  if (current < pages) {
    container.innerHTML += `<div class="pg-btn" onclick="mudarPagina(${current+1})"><i class="fa fa-chevron-right"></i></div>`;
  }
}
function ajustarQty(btn,delta){const input=btn.parentNode.querySelector('.qty-val');const v=Math.max(0,parseInt(input.value||0)+delta);input.value=v;salvarQty(input,true);}
function salvarQty(input,silent){
  const v=Math.max(0,parseInt(input.value)||0);input.value=v;atualizarEstadoQty(input);
  const vid=input.dataset.variantId||0;
  const fd=new FormData();fd.append('action','update_qty');fd.append('variant_id',vid);fd.append('qty',v);
  fetch('../_inc/ai_estoque_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{if(d.error){showToast('Erro: '+(d.message||''));} else if(!silent){showToast('Quantidade salva: '+v);} })
    .catch(()=>showToast('Erro ao salvar estoque.'));
}
function atualizarEstadoQty(input){
  const v=parseInt(input.value);
  input.className='qty-val'+(v===0?' zero':v<3?' crit':'');
  const badge=input.closest('tr').querySelector('.badge');
  if(v===0){badge.className='badge badge-zero';badge.innerHTML='<i class="fa fa-times-circle"></i> Zerado';}
  else if(v<3){badge.className='badge badge-crit';badge.innerHTML='<i class="fa fa-exclamation-triangle"></i> Crítico';}
  else if(v<5){badge.className='badge badge-low';badge.innerHTML='<i class="fa fa-exclamation-circle"></i> Baixo';}
  else{badge.className='badge badge-ok';badge.innerHTML='<i class="fa fa-check-circle"></i> OK';}
}
function money(v){v=parseFloat(v||0);return 'R$ '+v.toFixed(2).replace('.',',');}
document.addEventListener('DOMContentLoaded',carregarEstoque);
</script>

<?php include ("footer.php"); ?>
