// JS específico do painel de conta (visão geral)
// Inclui: gráficos (Chart.js) e alternância Cards/Lista das lojas.

(function () {
  const el = document.getElementById('salesChart');
  if (!el || typeof Chart === 'undefined') return;
  const ctx = el.getContext('2d');

  const cfg = window.accountOverview || {};
  const allLabels = Array.isArray(cfg.dailyLabels) && cfg.dailyLabels.length
    ? cfg.dailyLabels
    : ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
  const allValues = Array.isArray(cfg.dailyValues) && cfg.dailyValues.length
    ? cfg.dailyValues
    : [3200, 4100, 3800, 5200, 4800, 6100, 5230];

  // Por padrão, mostrar os últimos 7 pontos (se existirem)
  const defaultRange = 7;
  const len = allLabels.length;
  const start = Math.max(len - defaultRange, 0);
  let labels = allLabels.slice(start);
  let values = allValues.slice(start);

  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Vendas diárias',
          data: values,
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

  // Botões de range (7, 30, 90 dias) apenas ajustando visualmente o recorte
  const rangeGroup = document.getElementById('salesRangeButtons');
  if (rangeGroup && Array.isArray(allLabels) && allLabels.length) {
    const buttons = rangeGroup.querySelectorAll('button[data-range]');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const range = parseInt(btn.getAttribute('data-range'), 10) || 7;

        // Atualiza estilo de seleção
        buttons.forEach(b => {
          b.classList.remove('btn-primary');
          b.classList.add('btn-outline-secondary');
        });
        btn.classList.add('btn-primary');
        btn.classList.remove('btn-outline-secondary');

        // Pega últimos N pontos dos dados originais
        const len = allLabels.length;
        const start = Math.max(len - range, 0);

        chart.data.labels = allLabels.slice(start);
        chart.data.datasets[0].data = allValues.slice(start);
        chart.update();
      });
    });
  }
})();

(function () {
  const el = document.getElementById('storesChart');
  if (!el || typeof Chart === 'undefined') return;
  const ctx = el.getContext('2d');

  const cfg = window.accountOverview || {};
  const allLabels = Array.isArray(cfg.storesLabels) && cfg.storesLabels.length
    ? cfg.storesLabels
    : ['Loja Centro', 'Loja Shopping', 'Loja Outlet', 'Loja Online'];
  const allStatus = Array.isArray(cfg.storesStatus) && cfg.storesStatus.length
    ? cfg.storesStatus
    : allLabels.map(() => 'Ativa');
  const valuesMonth = Array.isArray(cfg.storesValues) && cfg.storesValues.length
    ? cfg.storesValues
    : [43200, 38900, 21350, 0];
  const valuesToday = Array.isArray(cfg.storesValuesToday) && cfg.storesValuesToday.length
    ? cfg.storesValuesToday
    : valuesMonth;
  const values7days = Array.isArray(cfg.storesValues7days) && cfg.storesValues7days.length
    ? cfg.storesValues7days
    : valuesMonth;
  const valuesPrevMonth = Array.isArray(cfg.storesValuesPrevMonth) && cfg.storesValuesPrevMonth.length
    ? cfg.storesValuesPrevMonth
    : valuesMonth;

  const periodFilter = document.getElementById('periodFilter');
  const statusFilter = document.getElementById('storesStatusFilter');

  function getBaseValues(period) {
    switch (period) {
      case 'today':      return valuesToday;
      case '7days':      return values7days;
      case 'prev_month': return valuesPrevMonth;
      case 'month':
      default:           return valuesMonth;
    }
  }

  function computeSeries() {
    const period = periodFilter ? periodFilter.value : 'month';
    const status = statusFilter ? statusFilter.value : 'all';
    const base = getBaseValues(period);

    const outLabels = [];
    const outValues = [];

    allLabels.forEach((name, idx) => {
      const st = allStatus[idx] || 'Ativa';
      if (status === 'active' && st !== 'Ativa') return;
      if (status === 'inactive' && st !== 'Inativa') return;
      outLabels.push(name);
      outValues.push(base[idx] || 0);
    });

    return { labels: outLabels, values: outValues };
  }

  const initial = computeSeries();

  const chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: initial.labels,
      datasets: [
        {
          label: 'Vendas por loja',
          data: initial.values,
          backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#dc3545'],
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
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

  function updateChart() {
    const series = computeSeries();
    chart.data.labels = series.labels;
    chart.data.datasets[0].data = series.values;
    chart.update();
  }

  if (periodFilter) {
    periodFilter.addEventListener('change', updateChart);
  }
  if (statusFilter) {
    statusFilter.addEventListener('change', updateChart);
  }
})();

// Alterna entre visão em cards e tabela
(function () {
  const btnCards = document.getElementById('viewCardsBtn');
  const btnTable = document.getElementById('viewTableBtn');
  const cardsView = document.getElementById('storesCardsView');
  const tableView = document.getElementById('storesTableView');
  if (!btnCards || !btnTable || !cardsView || !tableView) return;

  btnCards.addEventListener('click', function () {
    btnCards.classList.add('btn-primary');
    btnCards.classList.remove('btn-outline-secondary');
    btnTable.classList.remove('btn-primary');
    btnTable.classList.add('btn-outline-secondary');
    cardsView.classList.remove('d-none');
    tableView.classList.add('d-none');
  });

  btnTable.addEventListener('click', function () {
    btnTable.classList.add('btn-primary');
    btnTable.classList.remove('btn-outline-secondary');
    btnCards.classList.remove('btn-primary');
    btnCards.classList.add('btn-outline-secondary');
    tableView.classList.remove('d-none');
    cardsView.classList.add('d-none');
  });
})();
