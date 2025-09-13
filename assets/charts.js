let charts = {
  weekly: { user1: null, user2: null },
  monthly: { user1: null, user2: null }
};

const chartColors = {
  user1: 'rgba(14,165,234,0.9)',
  user2: 'rgba(16,185,129,0.9)'
};

async function fetchTimeSeries() {
  try {
    const res = await fetch(`api/timeseries.php?t=${Date.now()}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (!json.success) return;
    renderWeekly(json.data.weekly);
    renderMonthly(json.data.monthly);
  } catch (e) { console.error(e); }
}

function renderWeekly(data) {
  renderChart('weekly', data);
  toggleChartVisibilityBySelect('weekly');
}
function renderMonthly(data) {
  renderChart('monthly', data);
  toggleChartVisibilityBySelect('monthly');
}

function renderChart(type, data) {
  ['user1','user2'].forEach(uk => {
    const canvasId = `chart-${type}-${uk}`;
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    const existing = charts[type][uk];
    const cfg = baseBarConfig(data.labels, data[uk], chartColors[uk]);
    if (existing) {
      existing.data.labels = cfg.data.labels;
      existing.data.datasets[0].data = cfg.data.datasets[0].data;
      existing.update();
    } else {
      charts[type][uk] = new Chart(ctx, cfg);
    }
  });
}

function baseBarConfig(labels, values, color) {
  return {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Adet',
        data: values,
        backgroundColor: color,
        borderRadius: 6,
        maxBarThickness: 24,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      },
      scales: {
        x: { ticks: { color: '#e5e7eb' }, grid: { color: 'rgba(148,163,184,0.2)' } },
        y: { beginAtZero: true, ticks: { color: '#e5e7eb' }, grid: { color: 'rgba(148,163,184,0.2)' } }
      }
    }
  };
}

function toggleChartVisibilityBySelect(context) {
  const pairs = [
    { sel: document.getElementById('chart-mode-user1'), w: 'chart-weekly-user1', m: 'chart-monthly-user1' },
    { sel: document.getElementById('chart-mode-user2'), w: 'chart-weekly-user2', m: 'chart-monthly-user2' },
    { sel: document.getElementById('chart-mode-compare'), w: 'chart-weekly-compare', m: 'chart-monthly-compare' },
  ];
  pairs.forEach(p => {
    if (!p.sel) return;
    const mode = p.sel.value;
    const w = document.getElementById(p.w);
    const m = document.getElementById(p.m);
    if (!w || !m) return;
    if (mode === 'weekly') { w.style.display = ''; m.style.display = 'none'; }
    else { w.style.display = 'none'; m.style.display = ''; }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  // attach chart mode change
  ['chart-mode-user1','chart-mode-user2','chart-mode-compare'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', () => toggleChartVisibilityBySelect());
  });
  fetchTimeSeries();
  setInterval(fetchTimeSeries, 60000);
});

