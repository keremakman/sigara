const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const myUserKey = document.querySelector('meta[name="user-key"]')?.content || '';
let cooldownTimer = null;
const labels = window.labels || { user1: 'Kişi 1', user2: 'Kişi 2' };
function formatAvgMessage(avg) {
  if (avg == null) return 'Veri yok';
  const minutes = Math.max(1, Math.round(avg));
  return `Ortalama ${minutes} dakikada bir sigara içiyorsun. Şu sigarayı azalt!`;
}

async function fetchStats() {
  try {
    const res = await fetch(`api/stats.php?t=${Date.now()}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (!json.success) return;
    updateStatsFromData(json.data);
    if (json.meta && typeof json.meta.cooldown_remaining === 'number') {
      updateButtonCooldown(json.meta.cooldown_remaining);
    }
  } catch (e) {
    console.error(e);
  }
}

function updateStatsFromData(data) {
  ['user1','user2'].forEach(uk => {
    setValue(`${uk}-today`, data[uk].today);
    setValue(`${uk}-yesterday`, data[uk].yesterday);
    setValue(`${uk}-last_week`, data[uk].last_week);
    if (data[uk].this_week !== undefined) setValue(`${uk}-this_week`, data[uk].this_week);
    setValue(`${uk}-this_month`, data[uk].this_month);
    if (data[uk].total !== undefined) {
      setValue(`${uk}-total`, data[uk].total);
    }
  });
  // comparison text
  const t1 = data.user1.today ?? 0; const t2 = data.user2.today ?? 0;
  const cmp = document.getElementById('compare-today');
  if (cmp) {
    if (t1 === t2) cmp.textContent = `Bugün eşit: ${t1} - ${t2}`;
    else if (t1 < t2) cmp.textContent = `Bugün lider: ${labels.user1 || 'Kişi 1'} (${t1} - ${t2}, daha az içti)`;
    else cmp.textContent = `Bugün lider: ${labels.user2 || 'Kişi 2'} (${t2} - ${t1}, daha az içti)`;
  }
  // leaderboard simple (en az içen liderdir)
  const lb = document.getElementById('leaderboard-list');
  if (lb) {
    lb.innerHTML = '';
    const periods = [
      { key: 'today', title: 'Günlük' },
      { key: 'this_week', title: 'Haftalık' },
      { key: 'this_month', title: 'Aylık' }
    ];
    periods.forEach(p => {
      const v1 = data.user1[p.key] ?? 0; const v2 = data.user2[p.key] ?? 0;
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between';
      const winner = v1 === v2 ? 'Berabere' : (v1 < v2 ? (labels.user1 || 'Kişi 1') : (labels.user2 || 'Kişi 2'));
      li.textContent = `${p.title}: ${v1} - ${v2} (${winner} lider)`;
      lb.appendChild(li);
    });
  }
}

function setValue(id, val) {
  const el = document.getElementById(id);
  if (el) { el.textContent = String(val); }
}

function setMsg(userKey, msg) {
  const el = document.getElementById(`msg-${userKey}`);
  if (el) el.textContent = msg || '';
}

function updateButtonCooldown(seconds) {
  const btn = document.getElementById(`btn-${myUserKey}`);
  if (!btn) return;
  if (cooldownTimer) {
    clearInterval(cooldownTimer);
    cooldownTimer = null;
  }
  let remain = Math.max(0, Math.floor(seconds));
  if (remain <= 0) {
    btn.disabled = false;
    setMsg(myUserKey, '');
    return;
  }
  btn.disabled = true;
  setMsg(myUserKey, `Tekrar tıklamak için ${remain} sn bekleyin.`);
  cooldownTimer = setInterval(() => {
    remain -= 1;
    if (remain <= 0) {
      clearInterval(cooldownTimer);
      cooldownTimer = null;
      btn.disabled = false;
      setMsg(myUserKey, '');
    } else {
      setMsg(myUserKey, `Tekrar tıklamak için ${remain} sn bekleyin.`);
    }
  }, 1000);
}

async function incrementMine() {
  const btn = document.getElementById(`btn-${myUserKey}`);
  if (!btn) return;
  try {
    btn.disabled = true;
    const res = await fetch('api/increment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({}),
      cache: 'no-store'
    });
    const json = await res.json().catch(() => null);
    if (res.ok && json && json.success) {
      if (json.data) updateStatsFromData(json.data);
      const cd = json.meta?.cooldown_remaining ?? json.meta?.cooldown_seconds ?? 60;
      updateButtonCooldown(cd);
      fetchLogs();
    } else if (res.status === 429 && json) {
      const cd = json.meta?.cooldown_remaining ?? 60;
      updateButtonCooldown(cd);
    } else {
      setMsg(myUserKey, 'Hata oluştu, tekrar deneyin.');
      btn.disabled = false;
    }
  } catch (e) {
    console.error(e);
    setMsg(myUserKey, 'Bağlantı hatası, tekrar deneyin.');
    btn.disabled = false;
  }
}

async function fetchLogs() {
  try {
    const res = await fetch(`api/logs.php?limit=100&t=${Date.now()}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (!json.success) return;
    ['user1','user2'].forEach(uk => {
      const avgEl = document.getElementById(`${uk}-avg-msg`);
      if (avgEl) avgEl.textContent = formatAvgMessage(json.data[uk].avg_minutes_per_cig);
    });
  } catch (e) { console.error(e); }
}

function attachCardToggleHandlers() {
  ['user1','user2'].forEach(uk => {
    const header = document.getElementById(`card-header-${uk}`);
    const section = document.getElementById(`card-section-${uk}`);
    if (header && section) {
      header.addEventListener('click', (e) => {
        if (e.target.closest('button')) return;
        const opening = section.style.display === 'none';
        section.style.display = opening ? '' : 'none';
      });
    }
  });
}

function attachLogsPanelHandlers() {
  ['user1','user2'].forEach(uk => {
    const toggleBtn = document.getElementById(`toggle-logs-${uk}`);
    const panel = document.getElementById(`logs-panel-${uk}`);
    const fetchBtn = document.getElementById(`${uk}-logs-fetch`);
    const dateEl = document.getElementById(`${uk}-date`);
    const list = document.getElementById(`${uk}-logs-list`);

    async function fetchForDay() {
      try {
        const params = new URLSearchParams({ limit: '500' });
        if (dateEl && dateEl.value) params.set('date', dateEl.value);
        const res = await fetch(`api/logs.php?${params.toString()}&t=${Date.now()}`, { headers: { 'Accept':'application/json' }, cache:'no-store' });
        if (!res.ok) return;
        const json = await res.json();
        if (!json.success) return;
        if (!list) return;
        list.innerHTML = '';
        (json.data[uk].logs || []).forEach(item => {
          const div = document.createElement('div');
          div.className = 'list-group-item d-flex justify-content-between align-items-center';
          div.textContent = item.display;
          list.appendChild(div);
        });
      } catch (e) { console.error(e); }
    }

    function todayStr() {
      const d = new Date();
      const y = d.getFullYear();
      const m = String(d.getMonth()+1).padStart(2,'0');
      const day = String(d.getDate()).padStart(2,'0');
      return `${y}-${m}-${day}`;
    }

    if (toggleBtn && panel) {
      toggleBtn.addEventListener('click', async () => {
        const opening = panel.style.display === 'none';
        panel.style.display = opening ? '' : 'none';
        if (opening) {
          if (dateEl && !dateEl.value) dateEl.value = todayStr();
          await fetchForDay();
        }
      });
    }
    if (fetchBtn) {
      fetchBtn.addEventListener('click', fetchForDay);
    }
  });
}

async function fetchSettingsAndRender() {
  try {
    const res = await fetch('api/settings.php?all=1', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (!json.success) return;
    ['user1','user2'].forEach(uk => {
      const s = json.data[uk] || {};
      const limitEl = document.getElementById(`${uk}-daily-limit`);
      const emailEl = document.getElementById(`${uk}-email`);
      const penEl = document.getElementById(`${uk}-penalty`);
      if (limitEl) limitEl.value = s.daily_limit ?? '';
      if (emailEl) emailEl.value = s.notify_email ?? '';
      if (penEl) penEl.value = s.penalty_text ?? '';
    });
  } catch (e) { console.error(e); }
}

function attachSettingsSaveHandlers() {
  ['user1','user2'].forEach(uk => {
    const btn = document.getElementById(`${uk}-save-settings`);
    if (btn) {
      btn.addEventListener('click', async () => {
        try {
          const limit = parseInt(document.getElementById(`${uk}-daily-limit`).value || '');
          const email = document.getElementById(`${uk}-email`).value || null;
          const penalty = document.getElementById(`${uk}-penalty`).value || null;
          await fetch('api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ daily_limit: Number.isFinite(limit) ? limit : null, notify_email: email, penalty_text: penalty }),
          });
          setMsg(uk, 'Ayarlar kaydedildi.');
          fetchStats();
        } catch (e) { console.error(e); }
      });
    }
  });
}

async function fetchHistory(days = 7) {
  try {
    const res = await fetch(`api/history.php?days=${days}&t=${Date.now()}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    if (!json.success) return;
    const tbody = document.querySelector('#history-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    json.data.slice().reverse().forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.date}</td><td>${r.user1}</td><td>${r.user2}</td>`;
      tbody.appendChild(tr);
    });
  } catch (e) { console.error(e); }
}

async function challengeAction(action, period, payload={}) {
  const res = await fetch('api/challenges.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
    body: JSON.stringify({ action, period, ...payload })
  });
  return res.json().catch(() => ({ success:false }));
}

async function fetchChallenge(period) {
  const res = await fetch(`api/challenges.php?current=1&period=${period}&t=${Date.now()}`, { headers: { 'Accept':'application/json' }, cache: 'no-store' });
  if (!res.ok) return null;
  return res.json().catch(()=>null);
}

function renderChallengeUI(period, info) {
  const wrap = document.getElementById(`challenge-${period}-controls`);
  const statusEl = document.getElementById(`challenge-${period}-status`);
  if (!wrap || !statusEl) return;
  const c = info.data.challenge;
  const can = info.data.can;
  const counts = info.data.counts;
  const me = myUserKey;
  const other = me === 'user1' ? 'user2' : 'user1';
  const names = labels || { user1:'Kişi 1', user2:'Kişi 2' };

  // Attach propose buttons (always visible)
  const btnId = `challenge-${period}-propose`;
  const inputId = `challenge-${period}-penalty`;
  const proposeBtn = document.getElementById(btnId);
  const penaltyInput = document.getElementById(inputId);
  if (proposeBtn) {
    proposeBtn.onclick = async () => {
      try {
        proposeBtn.disabled = true;
        const r = await challengeAction('propose', period, { penalty_text: (penaltyInput?.value || null) });
        if (r && r.success) {
          statusEl.textContent = r.message ? `Durum: ${r.message}` : 'Durum: Teklif gönderildi.';
        } else {
          statusEl.textContent = `Durum: Hata${r && r.error ? ' - ' + r.error : ''}`;
        }
        await refreshChallenges();
      } catch (e) {
        statusEl.textContent = 'Durum: Hata - ağ yanıtı yok';
      } finally {
        proposeBtn.disabled = false;
      }
    };
  }
  // Clear dynamic buttons next to propose area
  const dynamicBtns = wrap.querySelectorAll('.dyn-btn');
  dynamicBtns.forEach(el => el.remove());

  if (!c) {
    statusEl.textContent = 'Durum: Henüz yarışma yok.';
    return;
  }

  if (c) {
    const start = new Date(info.data.start_at); const end = new Date(info.data.end_at);
    statusEl.textContent = `Durum: ${c.status}. Dönem: ${start.toLocaleDateString()} - ${end.toLocaleDateString()} | Skor: ${names.user1} ${counts.user1} - ${counts.user2} ${names.user2}`;
    if (c.status === 'pending') {
      if (c.created_by !== me) {
        const acceptBtn = document.createElement('button'); acceptBtn.className='btn btn-success btn-sm dyn-btn'; acceptBtn.textContent='Kabul et';
        const declineBtn = document.createElement('button'); declineBtn.className='btn btn-outline-danger btn-sm dyn-btn'; declineBtn.textContent='Reddet';
        wrap.appendChild(acceptBtn); wrap.appendChild(declineBtn);
        acceptBtn.onclick = async ()=>{ await challengeAction('accept', period, { id: c.id }); await refreshChallenges(); };
        declineBtn.onclick = async ()=>{ await challengeAction('decline', period, { id: c.id }); await refreshChallenges(); };
      } else {
        const cancelBtn = document.createElement('button'); cancelBtn.className='btn btn-outline-secondary btn-sm dyn-btn'; cancelBtn.textContent='İptal et';
        wrap.appendChild(cancelBtn);
        cancelBtn.onclick = async ()=>{ await challengeAction('cancel', period, { id: c.id }); await refreshChallenges(); };
      }
    } else if (c.status === 'active') {
      // If ended, allow complete for both users; backend checks end time
      const completeBtn = document.createElement('button'); completeBtn.className='btn btn-warning btn-sm dyn-btn'; completeBtn.textContent='Yarışmayı bitir';
      wrap.appendChild(completeBtn);
      completeBtn.onclick = async ()=>{ const r = await challengeAction('complete', period, { id: c.id }); await refreshChallenges(); if (r && r.success) alert(`Bitti! Kazanan: ${r.data.winner ?? 'Berabere'}`); };
    } else if (c.status === 'completed') {
      const winner = c.winner_user_key ? names[c.winner_user_key] : 'Berabere';
      const badge = document.createElement('span'); badge.className='badge bg-success'; badge.textContent = `Kazan: ${winner}`;
      wrap.appendChild(badge);
    } else if (c.status === 'declined') {
      const badge = document.createElement('span'); badge.className='badge bg-danger'; badge.textContent = 'Reddedildi';
      wrap.appendChild(badge);
    } else if (c.status === 'canceled') {
      const badge = document.createElement('span'); badge.className='badge bg-secondary'; badge.textContent = 'İptal edildi';
      wrap.appendChild(badge);
    }
  }
}

async function refreshChallenges() {
  const wk = await fetchChallenge('weekly'); if (wk) renderChallengeUI('weekly', wk);
  const mo = await fetchChallenge('monthly'); if (mo) renderChallengeUI('monthly', mo);
}

document.addEventListener('DOMContentLoaded', () => {
  if (myUserKey) {
    const btn = document.getElementById(`btn-${myUserKey}`);
    if (btn) {
      btn.addEventListener('click', incrementMine);
    }
  }
  refreshChallenges();
  fetchStats();
  fetchLogs();
  attachLogsPanelHandlers();
  attachCardToggleHandlers();
  const hr = document.getElementById('history-range');
  let currentDays = 7;
  if (hr) {
    currentDays = parseInt(hr.value);
    hr.addEventListener('change', () => {
      currentDays = parseInt(hr.value);
      fetchHistory(currentDays);
    });
  }
  fetchHistory(currentDays);
  setInterval(refreshChallenges, 5000);
  setInterval(fetchStats, 30000);
  setInterval(fetchLogs, 60000);
  setInterval(() => fetchHistory(currentDays), 120000);
});

