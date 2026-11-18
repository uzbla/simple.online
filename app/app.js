'use strict';
/* INTIZOM OS — Telegram Mini App (vanilla JS, CSP-safe: inline handler/style yo‘q) */
(() => {
const TG = window.Telegram && window.Telegram.WebApp;
const API = window.INTIZOM_API || '../api/index.php';
const $app = document.getElementById('app');
const S = { token: null, me: null, view: 'home', tab: {}, cal: { mode: 'month', day: null, month: null }, fin: { month: null } };
const CATS = { work: 'Ish', study: 'O‘qish', sport: 'Sport', personal: 'Shaxsiy', family: 'Oila', finance: 'Moliya', other: 'Boshqa' };
const TERMS = { daily: 'Kunlik', weekly: 'Haftalik', monthly: 'Oylik', yearly: 'Yillik', long: 'Uzoq muddatli' };
const RTYPES = { morning_plan: 'Ertalabki reja', task: 'Vazifa vaqti', sport: 'Sport', reading: 'Kitob o‘qish', water: 'Suv ichish', wake: 'Uyg‘onish', sleep: 'Uyqu', day_end: 'Kun yakuni', weekly_report: 'Haftalik hisobot', goal_deadline: 'Maqsad muddati', custom: 'Boshqa' };
const WEEK = ['Yakshanba', 'Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
const DAYS = ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'];
const MONTHS = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];

// ---------- yordamchilar ----------
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const pad = n => String(n).padStart(2, '0');
const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const money = (n, c = 'UZS') => { const v = Number(n) || 0, x = Math.abs(v).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1'); const [i, f] = x.split('.'); return (v < 0 ? '−' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + (f ? '.' + f : '') + ' ' + c; };
const toLocal = s => s ? new Date(s.replace(' ', 'T') + 'Z') : null;
const fmtD = d => `${d.getDate()} ${MONTHS[d.getMonth()].toLowerCase()}`;
const fmtDT = d => `${fmtD(d)}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
const hhmm = d => d ? `${pad(d.getHours())}:${pad(d.getMinutes())}` : '';
const dayOf = s => { const d = toLocal(s); return d ? ymd(d) : null; };
const toast = (m) => { const t = document.getElementById('toast'); t.textContent = m; t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(() => t.hidden = true, 2600); };
const haptic = (k = 'light') => { try { TG && TG.HapticFeedback.impactOccurred(k); } catch (e) { } };
function xpToast(r) { const x = (Array.isArray(r) ? r : [r]).filter(Boolean); if (x.length) { toast('+' + x.reduce((a, b) => a + b.xp, 0) + ' XP 🏔️'); haptic('medium'); } }

// ---------- API ----------
async function auth() {
  const initData = TG ? TG.initData : '';
  const dev = new URLSearchParams(location.search).get('dev');
  const body = initData ? { initData } : (dev ? { dev_telegram_id: Number(dev) } : {});
  const r = await fetch(`${API}?r=auth`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const j = await r.json(); if (!j.ok) throw new Error(j.error); S.token = j.data.token;
}
async function api(res, method = 'GET', body, q, retried) {
  const qs = q ? '&' + new URLSearchParams(q).toString() : '';
  const r = await fetch(`${API}?r=${res}${qs}`, { method, headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + S.token }, body: body ? JSON.stringify(body) : undefined });
  const j = await r.json().catch(() => ({ ok: false, error: 'Tarmoq xatosi' }));
  if (r.status === 401 && !retried) { await auth(); return api(res, method, body, q, true); }
  if (!j.ok) { const e = new Error(j.error || 'Xato'); e.status = r.status; throw e; }
  return j.data;
}
const guard = fn => async (...a) => { try { return await fn(...a); } catch (e) { toast(e.message); if (e.status === 402) setTimeout(() => go('premium'), 700); } };

// ---------- shakl (bottom sheet) ----------
function openForm(title, fields, init, onSubmit, extraBtns = '') {
  const bg = document.createElement('div'); bg.className = 'sheet-bg';
  const inp = f => {
    const v = init?.[f.k] ?? f.def ?? '', n = `name="${f.k}"`, req = f.req ? ' required' : '';
    switch (f.t) {
      case 'textarea': return `<textarea ${n}${req}>${esc(v)}</textarea>`;
      case 'select': return `<select ${n}>${Object.entries(f.opts).map(([k, l]) => `<option value="${esc(k)}"${String(v) === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
      case 'days': return `<div class="days">${DAYS.map((d, i) => `<label><input type="checkbox" name="${f.k}" value="${i}"${(Number(v === '' ? 127 : v) >> i) & 1 ? ' checked' : ''}><span>${d}</span></label>`).join('')}</div>`;
      case 'check': return `<label><input type="checkbox" ${n}${v ? ' checked' : ''}> ${esc(f.label)}</label>`;
      default: return `<input type="${f.t || 'text'}" ${n}${req} value="${esc(v)}"${f.step ? ` step="${f.step}"` : ''}${f.min != null ? ` min="${f.min}"` : ''}${f.max ? ` maxlength="${f.max}"` : ''}>`;
    }
  };
  bg.innerHTML = `<form class="sheet glass"><h2>${esc(title)}</h2>${fields.map(f => f.t === 'check' ? inp(f) : `<label>${esc(f.label)}</label>${inp(f)}`).join('')}<div class="row mt16"><button type="button" class="btn ghost grow" data-x>Bekor</button><button class="btn grow">Saqlash</button></div>${extraBtns}</form>`;
  document.body.appendChild(bg);
  const close = () => bg.remove();
  bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-x]')) close(); });
  bg.querySelector('form').addEventListener('submit', guard(async e => {
    e.preventDefault(); const fd = new FormData(e.target), o = {};
    for (const f of fields) {
      if (f.t === 'days') o[f.k] = fd.getAll(f.k).reduce((a, i) => a | (1 << Number(i)), 0);
      else if (f.t === 'check') o[f.k] = fd.get(f.k) === 'on';
      else { const v = (fd.get(f.k) ?? '').toString().trim(); o[f.k] = v; }
    }
    await onSubmit(o, close);
  }));
  return bg;
}
function confirmBox(msg) { return Promise.resolve(TG && TG.showConfirm ? new Promise(r => TG.showConfirm(msg, r)) : confirm(msg)); }

// ---------- navigatsiya ----------
const NAV = [['home', '🏠', 'Bosh'], ['tasks', '✅', 'Vazifalar'], ['habits', '🔥', 'Odatlar'], ['finance', '💰', 'Moliya'], ['more', '⋯', 'Yana']];
function go(v) { S.view = v; render(); window.scrollTo(0, 0); }
async function render() {
  const nav = `<nav class="nav glass">${NAV.map(([k, i, l]) => `<button data-go="${k}" class="${(S.view === k || (k === 'more' && !NAV.some(n => n[0] === S.view))) ? 'on' : ''}"><i>${i}</i>${l}</button>`).join('')}</nav>`;
  let html = '';
  try { html = await VIEWS[S.view](); } catch (e) { html = `<div class="card glass empty">${esc(e.message)}<p><button class="btn" data-go="home">Qayta urinish</button></p></div>`; }
  $app.innerHTML = html + nav;
}
document.addEventListener('click', guard(async e => {
  const g = e.target.closest('[data-go]'); if (g) { go(g.dataset.go); return; }
  const a = e.target.closest('[data-act]'); if (a) { await ACT[a.dataset.act](a.dataset, a); }
}));

// ---------- umumiy komponentlar ----------
const empty = t => `<div class="empty">${t}</div>`;
const taskRow = t => { const d = toLocal(t.due_at), od = t.status === 'overdue';
  return `<div class="item ${t.status === 'done' ? 'done' : ''}"><button class="chk ${t.status === 'done' ? 'on' : ''}" data-act="toggleTask" data-id="${t.id}" data-done="${t.status === 'done' ? 0 : 1}">${t.status === 'done' ? '✓' : ''}</button>
  <div class="grow" data-act="editTask" data-id="${t.id}"><div class="t">${esc(t.title)}</div><div class="m">${esc(CATS[t.category] || '')}${'⚑'.repeat(Number(t.priority) === 3 ? 2 : Number(t.priority) === 2 ? 1 : 0) ? ' · ' + (Number(t.priority) === 3 ? 'Yuqori' : 'O‘rta') : ''}${d ? ' · ' + hhmm(d) : ''}${od ? ' · <span class="gold">kechikkan</span>' : ''}${t.repeat_rule ? ' · 🔁' : ''}</div></div>
  <button class="ico" data-act="delTask" data-id="${t.id}">🗑</button></div>`; };
function bars(values, w = 300, h = 90, color = '#55AFFF') {
  const v = Object.values(values), keys = Object.keys(values), max = Math.max(1, ...v), bw = w / Math.max(1, v.length);
  return `<svg class="chart" viewBox="0 0 ${w} ${h + 16}" preserveAspectRatio="none" role="img">${v.map((x, i) => `<rect x="${(i * bw + 1).toFixed(1)}" y="${(h - x / max * h + 2).toFixed(1)}" width="${Math.max(1, bw - 2).toFixed(1)}" height="${(x / max * h).toFixed(1)}" rx="2" fill="${color}"><title>${keys[i]}: ${x}</title></rect>`).join('')}<text class="ax" x="0" y="${h + 13}">${keys[0] || ''}</text><text class="ax" x="${w}" y="${h + 13}" text-anchor="end">${keys[keys.length - 1] || ''}</text></svg>`;
}
function mountain(lv) {
  const n = lv.total_levels, pts = Array.from({ length: n }, (_, i) => [30 + i * (240 / (n - 1)), 120 - i * (90 / (n - 1)) - (i % 2 ? 0 : 6)]);
  const cur = lv.index - 1;
  return `<svg class="mtn" viewBox="0 0 300 150" role="img" aria-label="Everest: ${esc(lv.name)}"><defs><linearGradient id="mg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff" stop-opacity=".9"/><stop offset="1" stop-color="#55AFFF" stop-opacity=".25"/></linearGradient></defs>
  <polygon points="0,140 70,70 105,95 190,18 300,140" fill="url(#mg)" opacity=".5"/><polygon points="190,18 160,52 190,45 215,60" fill="#fff" opacity=".8"/>
  <polyline points="${pts.map(p => p.join(',')).join(' ')}" fill="none" stroke="#9bb0cc" stroke-dasharray="3 4" stroke-width="1.5"/>
  ${pts.map((p, i) => `<circle cx="${p[0]}" cy="${p[1]}" r="${i === cur ? 7 : 4}" fill="${i <= cur ? '#D9B45B' : '#33496b'}" stroke="#fff" stroke-width="${i === cur ? 2 : 0}"/>`).join('')}
  <text class="camp" x="${pts[cur][0]}" y="${pts[cur][1] - 12}" text-anchor="middle" fill="#D9B45B">${esc(lv.name)}</text></svg>`;
}

// ---------- ko‘rinishlar ----------
const VIEWS = {
  async home() {
    const d = await api('dashboard'); S.me = d.me; const me = d.me, lv = me.level, pct = d.total ? Math.round(d.done / d.total * 100) : 0;
    const name = esc((me.first_name || '') + ' ' + (me.last_name || '')).trim();
    return `<div class="hero"><div class="ava">${me.photo_url ? `<img src="${esc(me.photo_url)}" alt="">` : esc((me.first_name || '?')[0].toUpperCase())}</div><div class="grow"><h1>${name || 'Salom'}</h1><div class="sub m0">${(n => `${WEEK[n.getDay()]}, ${fmtD(n)}`)(new Date())}</div></div>${me.premium_until ? '<span class="pill gold">💎 Premium</span>' : ''}</div>
    ${d.banners.map(b => `<div class="banner">${b.image_url ? `<img src="${esc(b.image_url)}" alt="${esc(b.title)}">` : ''}</div>`).join('')}
    ${d.announcements.map(a => `<div class="ann"><b>${esc(a.title)}</b><div class="m">${esc(a.body)}</div></div>`).join('')}
    <div class="stats"><div class="stat glass"><b>${d.done}/${d.total}</b><span>Bugungi vazifalar</span></div><div class="stat glass"><b>🔥 ${me.streak}</b><span>Streak</span></div><div class="stat glass"><b>${lv.xp}</b><span>XP</span></div></div>
    <div class="card glass"><div class="row between"><h2>Bugungi progress</h2><span class="muted">${pct}%</span></div><progress max="100" value="${pct}"></progress></div>
    <div class="card glass"><div class="row between"><h2>${lv.icon} ${esc(lv.name)}</h2><span class="muted">${lv.next_xp ? `${lv.next_xp - lv.xp} XP → ${esc(lv.next_name)}` : 'Cho‘qqi!'}</span></div>${mountain(lv)}${lv.next_xp ? `<progress class="gold" max="${lv.next_xp - lv.from_xp}" value="${lv.xp - lv.from_xp}"></progress>` : ''}</div>
    ${d.quote ? `<div class="card glass"><i>“${esc(d.quote.text)}”</i>${d.quote.author ? `<div class="m">— ${esc(d.quote.author)}</div>` : ''}</div>` : ''}
    <div class="row wrap mb14">${[['addTask', '＋ Vazifa'], ['addGoal', '＋ Maqsad'], ['addHabit', '＋ Odat'], ['addExpense', '＋ Xarajat'], ['addReminder', '＋ Eslatma']].map(([a, l]) => `<button class="btn ghost sm" data-act="${a}">${l}</button>`).join('')}</div>
    <div class="card glass"><h2>Bugungi reja</h2>${d.tasks.length ? d.tasks.map(taskRow).join('') : empty('Bugun vazifa yo‘q')}${d.habits_today.length ? `<h2 class="mt14">Odatlar</h2>` + d.habits_today.map(h => `<div class="item ${h.done ? 'done' : ''}"><button class="chk ${h.done ? 'on' : ''}" data-act="logHabit" data-id="${h.id}" data-done="${h.done ? 0 : 1}">${h.done ? '✓' : ''}</button><div class="grow t">${esc(h.title)}</div><span class="m">🔥 ${h.streak}</span></div>`).join('') : ''}</div>
    ${d.reminders.length ? `<div class="card glass"><h2>Yaqin eslatmalar</h2>${d.reminders.map(r => `<div class="item"><div class="grow"><div class="t">${esc(r.message)}</div><div class="m">${fmtDT(toLocal(r.next_run_at))}</div></div></div>`).join('')}</div>` : ''}
    ${d.goals.length ? `<div class="card glass"><h2>Maqsadlar</h2>${d.goals.map(g => `<div class="item"><div class="grow"><div class="t">${esc(g.title)}</div><progress max="100" value="${g.progress}"></progress></div><span class="m">${g.progress}%</span></div>`).join('')}</div>` : ''}
    <div class="card glass"><h2>Balans</h2>${d.balance.length ? d.balance.map(b => `<div class="t">${money(b.b, b.currency)}</div>`).join('') : '<span class="muted">Hali operatsiya yo‘q</span>'}</div>`;
  },

  async tasks() {
    const f = S.tab.tasks || 'todo'; const all = await api('tasks'); const list = all.filter(t => f === 'todo' ? t.status === 'todo' : f === 'done' ? t.status === 'done' : t.status === 'overdue');
    const tabs = [['todo', 'Faol'], ['overdue', 'Kechikkan'], ['done', 'Bajarilgan']];
    return `<h1>Vazifalar</h1><div class="tabs">${tabs.map(([k, l]) => `<button class="${f === k ? 'on' : ''}" data-act="tab" data-v="tasks" data-k="${k}">${l} (${all.filter(t => t.status === k).length})</button>`).join('')}</div>
    <div class="card glass">${list.length ? list.map(taskRow).join('') : empty('Bu yerda hozircha hech narsa yo‘q')}</div><button class="btn fab" data-act="addTask">＋</button>`;
  },

  async calendar() {
    const c = S.cal; const now = new Date(); c.day ||= ymd(now); const sel = new Date(c.day + 'T00:00'); c.month ||= ymd(new Date(sel.getFullYear(), sel.getMonth(), 1));
    const m0 = new Date(c.month + 'T00:00'); const first = new Date(m0.getFullYear(), m0.getMonth(), 1), last = new Date(m0.getFullYear(), m0.getMonth() + 1, 0);
    const tasks = await api('tasks', 'GET', null, { from: ymd(first), to: ymd(last) });
    const by = {}; tasks.forEach(t => { const k = dayOf(t.due_at); (by[k] ||= []).push(t); });
    const dayTasks = by[c.day] || [];
    let body;
    if (c.mode === 'month') {
      const lead = (first.getDay() + 6) % 7, cells = [];
      for (let i = 0; i < lead; i++) cells.push('<button class="out" disabled></button>');
      for (let d = 1; d <= last.getDate(); d++) { const k = ymd(new Date(m0.getFullYear(), m0.getMonth(), d)); cells.push(`<button class="${k === ymd(now) ? 'today ' : ''}${k === c.day ? 'sel ' : ''}${by[k] ? 'dot' : ''}" data-act="pickDay" data-d="${k}">${d}</button>`); }
      body = `<div class="card glass"><div class="row between"><button class="ico" data-act="calMonth" data-n="-1">‹</button><h2 class="m0">${MONTHS[m0.getMonth()]} ${m0.getFullYear()}</h2><button class="ico" data-act="calMonth" data-n="1">›</button></div><div class="cal mt10">${DAYS.map(d => `<div class="dh">${d}</div>`).join('')}${cells.join('')}</div></div>
      <div class="card glass"><h2>${fmtD(sel)}</h2>${dayTasks.length ? dayTasks.map(taskRow).join('') : empty('Reja yo‘q')}</div>`;
    } else if (c.mode === 'week') {
      const mon = new Date(sel); mon.setDate(sel.getDate() - ((sel.getDay() + 6) % 7));
      const wk = await api('tasks', 'GET', null, { from: ymd(mon), to: ymd(new Date(mon.getFullYear(), mon.getMonth(), mon.getDate() + 6)) });
      body = Array.from({ length: 7 }, (_, i) => { const d = new Date(mon); d.setDate(mon.getDate() + i); const k = ymd(d), ts = wk.filter(t => dayOf(t.due_at) === k);
        return `<div class="card glass"><h2>${DAYS[i]} · ${d.getDate()} ${MONTHS[d.getMonth()]}</h2>${ts.length ? ts.map(taskRow).join('') : '<span class="muted">—</span>'}</div>`; }).join('');
    } else {
      const slots = Array.from({ length: 19 }, (_, i) => i + 5), un = dayTasks.filter(t => !t.due_at);
      body = `<div class="card glass"><div class="row between"><button class="ico" data-act="calDay" data-n="-1">‹</button><h2 class="m0">${`${WEEK[sel.getDay()]}, ${fmtD(sel)}`}</h2><button class="ico" data-act="calDay" data-n="1">›</button></div>
      ${slots.map(h => `<div class="slot"><div class="h">${pad(h)}:00</div><div class="grow">${dayTasks.filter(t => toLocal(t.due_at) && toLocal(t.due_at).getHours() === h).map(taskRow).join('')}</div></div>`).join('')}</div>`;
    }
    return `<h1>Kalendar</h1><div class="tabs">${[['month', 'Oy'], ['week', 'Hafta'], ['day', 'Kun']].map(([k, l]) => `<button class="${c.mode === k ? 'on' : ''}" data-act="calMode" data-k="${k}">${l}</button>`).join('')}</div>${body}<button class="btn fab" data-act="addTask" data-d="${c.day}">＋</button>`;
  },

  async goals() {
    const gs = await api('goals'); const f = S.tab.goals || 'active'; const list = gs.filter(g => g.status === f);
    return `<h1>Maqsadlar</h1><div class="tabs">${[['active', 'Faol'], ['done', 'Erishilgan']].map(([k, l]) => `<button class="${f === k ? 'on' : ''}" data-act="tab" data-v="goals" data-k="${k}">${l}</button>`).join('')}</div>
    ${list.length ? list.map(g => `<div class="card glass"><div class="row between"><h2 data-act="editGoal" data-id="${g.id}">${esc(g.title)}</h2><span class="pill">${TERMS[g.term]}</span></div>${g.description ? `<div class="m">${esc(g.description)}</div>` : ''}
      <div class="m">${g.start_date || ''}${g.end_date ? ' → ' + g.end_date : ''}${g.expected_result ? ' · 🎯 ' + esc(g.expected_result) : ''}</div><div class="row my8"><progress max="100" value="${g.progress}"></progress><b>${g.progress}%</b></div>
      ${g.milestones.map(m => `<div class="item ${m.done_at ? 'done' : ''}"><button class="chk ${m.done_at ? 'on' : ''}" data-act="toggleMs" data-id="${m.id}" data-done="${m.done_at ? 0 : 1}">${m.done_at ? '✓' : ''}</button><div class="grow t">${esc(m.title)}</div><button class="ico" data-act="delMs" data-id="${m.id}">✕</button></div>`).join('')}
      <div class="row mt8"><button class="btn ghost sm" data-act="addMs" data-id="${g.id}">＋ Bosqich</button><button class="btn ghost sm" data-act="delGoal" data-id="${g.id}">🗑</button></div></div>`).join('') : `<div class="card glass">${empty('Maqsad yo‘q')}</div>`}<button class="btn fab" data-act="addGoal">＋</button>`;
  },

  async habits() {
    const hs = await api('habits'); const today = S.me?.today || ymd(new Date()); const last7 = Array.from({ length: 7 }, (_, i) => { const d = new Date(today + 'T00:00'); d.setDate(d.getDate() - 6 + i); return ymd(d); });
    return `<h1>Odatlar</h1>${hs.length ? hs.map(h => { const done = h.logs.includes(today), paused = h.status !== 'active';
      return `<div class="card glass ${paused ? 'done' : ''}"><div class="row"><button class="chk ${done ? 'on' : ''}" ${paused ? 'disabled' : ''} data-act="logHabit" data-id="${h.id}" data-done="${done ? 0 : 1}">${done ? '✓' : ''}</button><div class="grow" data-act="editHabit" data-id="${h.id}"><div class="t">${esc(h.title)}</div><div class="m">${DAYS.filter((_, i) => (h.days_mask >> i) & 1).join(' ')}${paused ? ' · to‘xtatilgan' : ''}</div></div><b>🔥 ${h.streak}</b></div>
      <div class="row mt10 g6">${last7.map(d => `<div class="grow tc"><div class="m">${DAYS[(new Date(d + 'T00:00').getDay() + 6) % 7]}</div><div>${h.logs.includes(d) ? '🟢' : '⚪'}</div></div>`).join('')}</div>
      <div class="row between m mt6"><span>Jami: ${h.total} marta</span><span><button class="ico" data-act="pauseHabit" data-id="${h.id}" data-s="${paused ? 'active' : 'paused'}">${paused ? '▶️' : '⏸'}</button><button class="ico" data-act="delHabit" data-id="${h.id}">🗑</button></span></div></div>`; }).join('') : `<div class="card glass">${empty('Odat yo‘q')}</div>`}<button class="btn fab" data-act="addHabit">＋</button>`;
  },

  async finance() {
    const mon = S.fin.month || (S.me?.today || ymd(new Date())).slice(0, 7); const f = await api('finance', 'GET', null, { month: mon }); const [y, m] = mon.split('-').map(Number);
    return `<h1>Moliya</h1><div class="card glass"><div class="muted">Umumiy balans</div>${f.balance.length ? f.balance.map(b => `<div class="big">${money(b.balance, b.currency)}</div>`).join('') : '<b>0</b>'}<div class="m">Balans = jami daromad − jami xarajat</div></div>
    <div class="card glass"><div class="row between"><button class="ico" data-act="finMonth" data-n="-1">‹</button><h2 class="m0">${MONTHS[m - 1]} ${y}</h2><button class="ico" data-act="finMonth" data-n="1">›</button></div>
      ${f.month_summary.map(s => `<div class="row between mt8"><span class="c-ok">＋ ${money(s.income, s.currency)}</span><span class="c-bad">− ${money(s.expense, s.currency)}</span></div>`).join('') || '<div class="empty">Bu oyda operatsiya yo‘q</div>'}</div>
    ${f.by_category ? `<div class="card glass"><h2>Kategoriyalar bo‘yicha</h2>${f.by_category.map(c => `<div class="row between item"><span>${esc(c.category || 'Kategoriyasiz')} <span class="m">${c.type === 'income' ? 'daromad' : 'xarajat'}</span></span><b>${money(c.total, c.currency)}</b></div>`).join('')}</div>` : `<div class="card glass"><span class="m">📊 Kategoriyalar bo‘yicha hisobot — Premium</span> <button class="btn sm ghost" data-go="premium">Ko‘rish</button></div>`}
    <div class="card glass"><div class="row between"><h2>Budjetlar</h2><button class="btn ghost sm" data-act="addBudget" data-m="${mon}">＋</button></div>${f.budgets.map(b => `<div class="item"><div class="grow"><div class="row between"><span class="t">${esc(b.category)}</span><span class="m">${money(b.spent)} / ${money(b.amount)}</span></div><progress class="${Number(b.spent) > Number(b.amount) ? '' : 'gold'}" max="${b.amount}" value="${Math.min(b.spent, b.amount)}"></progress></div><button class="ico" data-act="delBudget" data-id="${b.id}">✕</button></div>`).join('') || '<span class="muted">Budjet belgilanmagan</span>'}</div>
    <div class="card glass"><div class="row between"><h2>Jamg‘armalar</h2><button class="btn ghost sm" data-act="addSaving">＋</button></div>${f.savings.map(s => `<div class="item"><div class="grow"><div class="row between"><span class="t">${esc(s.title)}</span><span class="m">${money(s.saved)} / ${money(s.target)}</span></div><progress class="gold" max="${s.target}" value="${Math.min(s.saved, s.target)}"></progress></div><button class="ico" data-act="addToSaving" data-id="${s.id}">＋</button><button class="ico" data-act="delSaving" data-id="${s.id}">✕</button></div>`).join('') || '<span class="muted">Jamg‘arma yo‘q</span>'}</div>
    <div class="card glass"><h2>Operatsiyalar</h2>${f.records.length ? f.records.map(r => `<div class="item"><div class="grow"><div class="t">${esc(r.category || (r.type === 'income' ? 'Daromad' : 'Xarajat'))}</div><div class="m">${r.record_date}${r.note ? ' · ' + esc(r.note) : ''}</div></div><b class="${r.type === 'income' ? 'c-ok' : 'c-bad'}">${r.type === 'income' ? '+' : '−'}${money(r.amount, r.currency)}</b><button class="ico" data-act="delRec" data-id="${r.id}">🗑</button></div>`).join('') : empty('Operatsiya yo‘q')}</div>
    <button class="btn fab" data-act="addRecord">＋</button>`;
  },

  async stats() {
    const p = S.tab.stats || 'week'; const premium = !!S.me?.limits.full_stats; let s;
    try { s = await api('stats', 'GET', null, { period: premium || p === 'day' || p === 'week' ? p : 'week' }); } catch (e) { s = await api('stats', 'GET', null, { period: 'week' }); }
    const sum = o => Object.values(o).reduce((a, b) => a + b, 0);
    return `<h1>Statistika</h1><div class="tabs">${[['day', 'Kun'], ['week', 'Hafta'], ['month', 'Oy 💎'], ['year', 'Yil 💎']].map(([k, l]) => `<button class="${p === k ? 'on' : ''}" data-act="tab" data-v="stats" data-k="${k}">${l}</button>`).join('')}</div>
    <div class="stats"><div class="stat glass"><b>${sum(s.tasks_done)}</b><span>Bajarilgan</span></div><div class="stat glass"><b>${s.tasks_created}</b><span>Yaratilgan</span></div><div class="stat glass"><b>🔥 ${s.streak}</b><span>Streak</span></div></div>
    <div class="card glass"><h2>Vazifalar</h2>${bars(s.tasks_done)}</div><div class="card glass"><h2>Odatlar</h2>${bars(s.habit_logs, 300, 90, '#4ade80')}</div><div class="card glass"><h2>XP tarixi · jami ${s.xp_total}</h2>${bars(s.xp, 300, 90, '#D9B45B')}</div>
    <div class="card glass"><h2>Maqsadlar</h2>Faol: <b>${s.goals.active}</b> · Erishilgan: <b>${s.goals.done}</b><div class="row mt8"><progress max="100" value="${s.goals.avg_progress}"></progress><b>${s.goals.avg_progress}%</b></div></div>
    ${s.finance ? `<div class="card glass"><h2>Moliya</h2>${s.finance.map(f => `<div class="row between"><span class="c-ok">＋ ${money(f.income, f.currency)}</span><span class="c-bad">− ${money(f.expense, f.currency)}</span></div>`).join('') || '<span class="muted">Ma’lumot yo‘q</span>'}</div>` : ''}`;
  },

  async reminders() {
    const [rs, hist] = await Promise.all([api('reminders'), api('reminders/history')]);
    return `<h1>Eslatmalar</h1><p class="sub">Telegram bot orqali avtomatik yuboriladi</p>${rs.length ? rs.map(r => `<div class="card glass ${r.enabled == 1 ? '' : 'done'}"><div class="row"><div class="grow" data-act="editReminder" data-id="${r.id}"><div class="t">${esc(r.message)}</div><div class="m">${esc(RTYPES[r.type])} · ${r.time_of_day.slice(0, 5)} · ${Number(r.days_mask) === 0 ? r.once_date : Number(r.days_mask) === 127 ? 'har kuni' : DAYS.filter((_, i) => (r.days_mask >> i) & 1).join(' ')}${r.lead_minutes > 0 ? ` · ${r.lead_minutes} daq oldin` : ''}</div></div>
      <button class="chk ${r.enabled == 1 ? 'on' : ''}" data-act="toggleRem" data-id="${r.id}" data-on="${r.enabled == 1 ? 0 : 1}">${r.enabled == 1 ? '✓' : ''}</button><button class="ico" data-act="delRem" data-id="${r.id}">🗑</button></div></div>`).join('') : `<div class="card glass">${empty('Eslatma yo‘q')}</div>`}
    <div class="card glass"><h2>Tarix</h2>${hist.length ? hist.slice(0, 15).map(h => `<div class="item"><div class="grow"><div class="t">${esc(h.message)}</div><div class="m">${fmtDT(toLocal(h.scheduled_for))}</div></div><span class="pill">${h.status === 'sent' ? '✓ yuborildi' : h.status === 'failed' ? '✕ xato' : '…'}</span></div>`).join('') : empty('Hali yuborilmagan')}</div><button class="btn fab" data-act="addReminder">＋</button>`;
  },

  async profile() {
    const me = await api('me'); S.me = me; const lv = me.level;
    return `<h1>Profil</h1><div class="card glass"><div class="hero"><div class="ava">${me.photo_url ? `<img src="${esc(me.photo_url)}" alt="">` : esc((me.first_name || '?')[0].toUpperCase())}</div><div class="grow"><b>${esc(me.first_name)} ${esc(me.last_name)}</b><div class="m">${me.username ? '@' + esc(me.username) + ' · ' : ''}ID ${me.telegram_id}</div></div></div>
      <div class="item"><span class="grow">Ro‘yxatdan o‘tgan</span><span class="muted">${me.created_at.slice(0, 10)}</span></div><div class="item"><span class="grow">Premium</span><span>${me.premium_until ? '💎 ' + me.premium_until.slice(0, 10) + ' gacha' : 'Free'}</span></div><div class="item"><span class="grow">Everest darajasi</span><span>${lv.icon} ${esc(lv.name)}</span></div></div>
    <div class="card glass"><h2>Sozlamalar</h2><label>Ism</label><input id="pfn" value="${esc(me.first_name)}" maxlength="100"><label>Til</label><select id="plang">${[['uz', 'O‘zbek'], ['ru', 'Русский'], ['en', 'English']].map(([k, l]) => `<option value="${k}"${me.language === k ? ' selected' : ''}>${l}</option>`).join('')}</select>
      <label>Vaqt zonasi</label><input id="ptz" value="${esc(me.timezone)}"><label><input type="checkbox" id="pn1"${me.settings.notifications_enabled ? ' checked' : ''}> Bildirishnomalar</label><label><input type="checkbox" id="pn2"${me.settings.daily_report ? ' checked' : ''}> Kunlik hisobot</label><label><input type="checkbox" id="pn3"${me.settings.weekly_report ? ' checked' : ''}> Haftalik hisobot</label>
      <p><button class="btn block" data-act="saveProfile">Saqlash</button></p></div>
    <div class="card glass"><h2>Maxfiylik va ma’lumotlar</h2><div class="row wrap"><button class="btn ghost sm" data-act="exportData">⬇️ Eksport (Premium)</button><button class="btn ghost sm" data-act="page" data-p="privacy">Maxfiylik siyosati</button><button class="btn ghost sm" data-act="page" data-p="terms">Qoidalar</button><button class="btn danger sm" data-act="deleteMe">Hisobni o‘chirish</button></div></div>`;
  },

  async premium() {
    const [plans, me] = await Promise.all([api('plans'), api('me')]);
    const L = { max_goals: 'Maqsadlar', max_habits: 'Odatlar', max_reminders: 'Eslatmalar', full_stats: 'To‘liq statistika', finance_reports: 'Moliyaviy hisobotlar', export_data: 'Eksport' };
    return `<h1>💎 Premium</h1><p class="sub">${me.premium_until ? 'Faol: ' + me.premium_until.slice(0, 10) + ' gacha' : 'Hozir Free tarifdasiz'}</p>${plans.map(p => { const l = JSON.parse(p.limits_json || '{}');
      return `<div class="card glass"><div class="row between"><h2>${esc(p.name)}</h2><b class="gold">${Number(p.price) ? money(p.price, p.currency) : 'Bepul'}</b></div>${Number(p.duration_days) ? `<div class="m">${p.duration_days} kun${Number(p.trial_days) ? ` · ${p.trial_days} kun sinov` : ''}</div>` : ''}
      ${Object.entries(L).map(([k, n]) => `<div class="m">• ${n}: ${typeof l[k] === 'number' && k.startsWith('max') ? (l[k] < 0 ? 'cheksiz' : l[k]) : (l[k] ? '✓' : '—')}</div>`).join('')}</div>`; }).join('')}
    <div class="card glass"><p>Obuna uchun botda <b>/premium</b> buyrug‘ini yuboring yoki support bilan bog‘laning.</p><button class="btn block" data-go="support">Support</button></div>`;
  },

  async support() {
    const ts = await api('support'); const st = { new: 'Yangi', in_progress: 'Jarayonda', waiting: 'Javob berildi', resolved: 'Hal qilindi', closed: 'Yopilgan' };
    return `<h1>Support</h1>${ts.length ? ts.map(t => `<div class="card glass" data-act="openTicket" data-id="${t.id}"><div class="row between"><b>${esc(t.subject)}</b><span class="pill">${st[t.status]}</span></div><div class="m">${t.created_at.slice(0, 10)}</div></div>`).join('') : `<div class="card glass">${empty('Murojaat yo‘q')}</div>`}<button class="btn fab" data-act="newTicket">＋</button>`;
  },

  async more() {
    const items = [['calendar', '📅', 'Kalendar'], ['goals', '🎯', 'Maqsadlar'], ['stats', '📊', 'Statistika'], ['reminders', '⏰', 'Eslatmalar'], ['premium', '💎', 'Premium'], ['support', '💬', 'Support'], ['profile', '👤', 'Profil']];
    const c = await api('content').catch(() => null);
    return `<h1>Yana</h1><div class="card glass">${items.map(([k, i, l]) => `<button class="list-link" data-go="${k}"><span class="f22">${i}</span><span class="grow">${l}</span>›</button>`).join('')}</div>
      ${c ? `<div class="card glass"><button class="list-link" data-act="page" data-p="help">❓ Yordam</button></div>` : ''}`;
  },
};

// ---------- amallar ----------
const taskFields = [{ k: 'title', label: 'Nomi', req: 1, max: 255 }, { k: 'note', label: 'Izoh', t: 'textarea' }, { k: 'category', label: 'Kategoriya', t: 'select', opts: CATS, def: 'other' },
  { k: 'priority', label: 'Muhimlik', t: 'select', opts: { 1: 'Past', 2: 'O‘rta', 3: 'Yuqori' }, def: '2' }, { k: 'due', label: 'Sana va vaqt', t: 'datetime-local' }, { k: 'repeat_rule', label: 'Takrorlanish', t: 'select', opts: { none: 'Takrorlanmaydi', daily: 'Har kuni', weekly: 'Har hafta', monthly: 'Har oy' }, def: 'none' }];
const taskBody = o => ({ title: o.title, note: o.note || null, category: o.category, priority: Number(o.priority), due_at: o.due ? new Date(o.due).toISOString() : null, repeat_rule: o.repeat_rule });
const reload = () => render();
const ACT = {
  tab: d => { S.tab[d.v] = d.k; render(); },
  async toggleTask(d) { const r = await api('tasks', 'PATCH', { status: Number(d.done) ? 'done' : 'todo' }, { id: d.id }); xpToast(r.xp); haptic(); reload(); },
  async delTask(d) { if (await confirmBox('Vazifa o‘chirilsinmi?')) { await api('tasks', 'DELETE', null, { id: d.id }); reload(); } },
  async addTask(d) { const base = d.d ? { due: `${d.d}T09:00` } : {}; openForm('Yangi vazifa', taskFields, base, async (o, close) => { await api('tasks', 'POST', taskBody(o)); close(); toast('Qo‘shildi'); reload(); }); },
  async editTask(d) { const all = await api('tasks'); const t = all.find(x => x.id == d.id); if (!t) return; const dd = toLocal(t.due_at);
    openForm('Vazifani tahrirlash', taskFields, { ...t, due: dd ? `${ymd(dd)}T${hhmm(dd)}` : '', repeat_rule: t.repeat_rule || 'none', priority: String(t.priority) }, async (o, close) => { await api('tasks', 'PATCH', taskBody(o), { id: t.id }); close(); reload(); }); },
  pickDay: d => { S.cal.day = d.d; render(); }, calMode: d => { S.cal.mode = d.k; render(); },
  calMonth: d => { const m = new Date(S.cal.month + 'T00:00'); S.cal.month = ymd(new Date(m.getFullYear(), m.getMonth() + Number(d.n), 1)); S.cal.day = S.cal.month; render(); },
  calDay: d => { const x = new Date(S.cal.day + 'T00:00'); x.setDate(x.getDate() + Number(d.n)); S.cal.day = ymd(x); S.cal.month = ymd(new Date(x.getFullYear(), x.getMonth(), 1)); render(); },

  async addGoal() { openForm('Yangi maqsad', [{ k: 'title', label: 'Nomi', req: 1, max: 255 }, { k: 'description', label: 'Tavsif', t: 'textarea' }, { k: 'term', label: 'Muddat turi', t: 'select', opts: TERMS, def: 'monthly' }, { k: 'start_date', label: 'Boshlanish', t: 'date', def: S.me?.today }, { k: 'end_date', label: 'Tugash', t: 'date' }, { k: 'expected_result', label: 'Kutilayotgan natija', max: 500 }, { k: 'ms', label: 'Bosqichlar (har qatorda bittadan)', t: 'textarea' }],
    {}, async (o, close) => { await api('goals', 'POST', { title: o.title, description: o.description || null, term: o.term, start_date: o.start_date || null, end_date: o.end_date || null, expected_result: o.expected_result || null, milestones: o.ms.split('\n').map(s => s.trim()).filter(Boolean) }); close(); S.view = 'goals'; reload(); }); },
  async editGoal(d) { const gs = await api('goals'); const g = gs.find(x => x.id == d.id); openForm('Maqsadni tahrirlash', [{ k: 'title', label: 'Nomi', req: 1 }, { k: 'description', label: 'Tavsif', t: 'textarea' }, { k: 'term', label: 'Muddat turi', t: 'select', opts: TERMS }, { k: 'start_date', label: 'Boshlanish', t: 'date' }, { k: 'end_date', label: 'Tugash', t: 'date' }, { k: 'expected_result', label: 'Natija' }, ...(g.milestones.length ? [] : [{ k: 'progress', label: 'Progress %', t: 'number', min: 0 }])], g,
    async (o, close) => { const b = { title: o.title, description: o.description || null, term: o.term, start_date: o.start_date || null, end_date: o.end_date || null, expected_result: o.expected_result || null }; await api('goals', 'PATCH', b, { id: g.id }); if (o.progress !== undefined && o.progress !== '') await api('goals', 'PATCH', { progress: Number(o.progress) }, { id: g.id }); close(); reload(); }); },
  async delGoal(d) { if (await confirmBox('Maqsad o‘chirilsinmi?')) { await api('goals', 'DELETE', null, { id: d.id }); reload(); } },
  async addMs(d) { openForm('Yangi bosqich', [{ k: 'title', label: 'Nomi', req: 1 }], {}, async (o, close) => { await api('goals/milestone', 'POST', { goal_id: Number(d.id), title: o.title }); close(); reload(); }); },
  async toggleMs(d) { const r = await api('goals/milestone', 'PATCH', { done: !!Number(d.done) }, { id: d.id }); xpToast(r.result.xp); reload(); },
  async delMs(d) { await api('goals/milestone', 'DELETE', null, { id: d.id }); reload(); },

  async addHabit() { openForm('Yangi odat', [{ k: 'title', label: 'Nomi', req: 1, max: 255 }, { k: 'days_mask', label: 'Hafta kunlari', t: 'days' }], {}, async (o, close) => { if (!o.days_mask) return toast('Kamida bitta kun tanlang'); await api('habits', 'POST', { title: o.title, days_mask: o.days_mask }); close(); S.view = 'habits'; reload(); }); },
  async editHabit(d) { const hs = await api('habits'); const h = hs.find(x => x.id == d.id); openForm('Odatni tahrirlash', [{ k: 'title', label: 'Nomi', req: 1 }, { k: 'days_mask', label: 'Hafta kunlari', t: 'days' }], h, async (o, close) => { if (!o.days_mask) return toast('Kamida bitta kun tanlang'); await api('habits', 'PATCH', { title: o.title, days_mask: o.days_mask }, { id: h.id }); close(); reload(); }); },
  async logHabit(d) { const r = await api('habits/log', 'POST', { habit_id: Number(d.id), done: !!Number(d.done) }); xpToast(r.xp); haptic(); reload(); },
  async pauseHabit(d) { await api('habits', 'PATCH', { status: d.s }, { id: d.id }); reload(); },
  async delHabit(d) { if (await confirmBox('Odat o‘chirilsinmi?')) { await api('habits', 'DELETE', null, { id: d.id }); reload(); } },

  async addRecord() { openForm('Yangi operatsiya', [{ k: 'type', label: 'Turi', t: 'select', opts: { expense: 'Xarajat', income: 'Daromad' } }, { k: 'amount', label: 'Summa', t: 'number', step: '0.01', req: 1, min: 0 }, { k: 'currency', label: 'Valyuta', def: 'UZS', max: 3 }, { k: 'category', label: 'Kategoriya', max: 64 }, { k: 'note', label: 'Izoh', max: 255 }, { k: 'record_date', label: 'Sana', t: 'date', def: S.me?.today }],
    {}, async (o, close) => { await api('finance', 'POST', { type: o.type, amount: Number(o.amount), currency: o.currency || 'UZS', category: o.category, note: o.note || null, record_date: o.record_date }); close(); S.view = 'finance'; reload(); }); },
  addExpense() { ACT.addRecord(); },
  async delRec(d) { if (await confirmBox('O‘chirilsinmi?')) { await api('finance', 'DELETE', null, { id: d.id }); reload(); } },
  finMonth(d) { const [y, m] = (S.fin.month || (S.me?.today || ymd(new Date())).slice(0, 7)).split('-').map(Number); const x = new Date(y, m - 1 + Number(d.n), 1); S.fin.month = `${x.getFullYear()}-${pad(x.getMonth() + 1)}`; render(); },
  async addBudget(d) { openForm('Budjet', [{ k: 'category', label: 'Kategoriya', req: 1, max: 64 }, { k: 'amount', label: 'Oylik limit', t: 'number', req: 1, min: 0 }], {}, async (o, close) => { await api('finance/budget', 'POST', { category: o.category, amount: Number(o.amount), month: d.m }); close(); reload(); }); },
  async delBudget(d) { await api('finance/budget', 'DELETE', null, { id: d.id }); reload(); },
  async addSaving() { openForm('Jamg‘arma maqsadi', [{ k: 'title', label: 'Nomi', req: 1 }, { k: 'target', label: 'Maqsad summasi', t: 'number', req: 1, min: 0 }], {}, async (o, close) => { await api('finance/savings', 'POST', { title: o.title, target: Number(o.target) }); close(); reload(); }); },
  async addToSaving(d) { openForm('Jamg‘armaga qo‘shish', [{ k: 'add', label: 'Summa (manfiy = ayirish)', t: 'number', req: 1, step: '0.01' }], {}, async (o, close) => { await api('finance/savings', 'PATCH', { add: Number(o.add) }, { id: d.id }); close(); reload(); }); },
  async delSaving(d) { if (await confirmBox('O‘chirilsinmi?')) { await api('finance/savings', 'DELETE', null, { id: d.id }); reload(); } },

  async addReminder() { const f = [{ k: 'type', label: 'Turi', t: 'select', opts: RTYPES, def: 'custom' }, { k: 'message', label: 'Xabar matni', req: 1, max: 500 }, { k: 'time_of_day', label: 'Vaqt', t: 'time', req: 1, def: '09:00' }, { k: 'days_mask', label: 'Hafta kunlari (hech biri = bir martalik)', t: 'days' }, { k: 'once_date', label: 'Bir martalik sana', t: 'date' }, { k: 'lead_minutes', label: 'Necha daqiqa oldin ogohlantirish', t: 'number', min: 0, def: '0' }];
    openForm('Yangi eslatma', f, {}, async (o, close) => { await api('reminders', 'POST', { type: o.type, message: o.message, time_of_day: o.time_of_day, days_mask: o.days_mask, once_date: o.once_date || null, lead_minutes: Number(o.lead_minutes || 0) }); close(); S.view = 'reminders'; reload(); }); },
  async editReminder(d) { const rs = await api('reminders'); const r = rs.find(x => x.id == d.id); openForm('Eslatmani tahrirlash', [{ k: 'type', label: 'Turi', t: 'select', opts: RTYPES }, { k: 'message', label: 'Xabar', req: 1 }, { k: 'time_of_day', label: 'Vaqt', t: 'time' }, { k: 'days_mask', label: 'Kunlar', t: 'days' }, { k: 'once_date', label: 'Bir martalik sana', t: 'date' }, { k: 'lead_minutes', label: 'Oldindan (daqiqa)', t: 'number', min: 0 }], { ...r, time_of_day: r.time_of_day.slice(0, 5) },
    async (o, close) => { await api('reminders', 'PATCH', { type: o.type, message: o.message, time_of_day: o.time_of_day, days_mask: o.days_mask, once_date: o.once_date || null, lead_minutes: Number(o.lead_minutes || 0) }, { id: r.id }); close(); reload(); }); },
  async toggleRem(d) { await api('reminders', 'PATCH', { enabled: !!Number(d.on) }, { id: d.id }); reload(); },
  async delRem(d) { if (await confirmBox('Eslatma o‘chirilsinmi?')) { await api('reminders', 'DELETE', null, { id: d.id }); reload(); } },

  async saveProfile() { await api('me', 'PATCH', { first_name: document.getElementById('pfn').value, language: document.getElementById('plang').value, timezone: document.getElementById('ptz').value, notifications_enabled: document.getElementById('pn1').checked, daily_report: document.getElementById('pn2').checked, weekly_report: document.getElementById('pn3').checked }); toast('Saqlandi'); reload(); },
  async exportData() { const j = await api('export'); const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(j, null, 2)], { type: 'application/json' })); a.download = 'intizom-export.json'; a.click(); },
  async deleteMe() { if (await confirmBox('Hisobingiz va barcha ma’lumotlaringiz o‘chiriladi. Davom etasizmi?')) { await api('me', 'DELETE', null, { confirm: 'DELETE' }); $app.innerHTML = '<div class="splash"><div><div class="logo">👋</div><p>Hisob o‘chirildi.</p></div></div>'; } },
  async page(d) { const p = await api('content', 'GET', null, { page: d.p }); const bg = document.createElement('div'); bg.className = 'sheet-bg'; bg.innerHTML = `<div class="sheet glass"><h2>${esc(p.title)}</h2><div class="pre">${esc(p.body) || '<span class="muted">Matn hali kiritilmagan</span>'}</div><p><button class="btn block" data-x>Yopish</button></p></div>`; bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-x]')) bg.remove(); }); document.body.appendChild(bg); },
  async newTicket() { openForm('Yangi murojaat', [{ k: 'subject', label: 'Mavzu', req: 1, max: 200 }, { k: 'body', label: 'Xabar', t: 'textarea', req: 1 }], {}, async (o, close) => { await api('support', 'POST', { subject: o.subject, body: o.body }); close(); toast('Yuborildi'); S.view = 'support'; reload(); }); },
  async openTicket(d) { const t = await api('support', 'GET', null, { id: d.id }); const bg = document.createElement('div'); bg.className = 'sheet-bg';
    bg.innerHTML = `<form class="sheet glass"><h2>${esc(t.subject)}</h2>${t.messages.map(m => `<div class="ann" ${m.sender === 'admin' ? '' : ''}><b>${m.sender === 'admin' ? 'Support' : 'Siz'}</b><div>${esc(m.body)}</div></div>`).join('')}<label>Javob</label><textarea name="b" required></textarea><div class="row"><button type="button" class="btn ghost grow" data-x>Yopish</button><button class="btn grow">Yuborish</button></div></form>`;
    bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-x]')) bg.remove(); });
    bg.querySelector('form').addEventListener('submit', guard(async e => { e.preventDefault(); await api('support', 'POST', { ticket_id: Number(d.id), body: e.target.b.value.trim() }); bg.remove(); toast('Yuborildi'); reload(); })); document.body.appendChild(bg); },
};

// ---------- ishga tushirish ----------
(async () => {
  try { if (TG) { TG.ready(); TG.expand(); try { TG.setHeaderColor('#07172E'); TG.setBackgroundColor('#07172E'); } catch (e) { } } await auth(); S.me = await api('me'); await render(); }
  catch (e) { $app.innerHTML = `<div class="splash"><div><div class="logo">⛰️</div><h2>INTIZOM OS</h2><p class="muted">${esc(e.message || 'Kirish xatosi')}</p><p class="muted">Ilovani Telegram bot orqali oching.</p></div></div>`; }
})();
})();
