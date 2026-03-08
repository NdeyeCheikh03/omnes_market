/**
 * OMNES MARKETPLACE — home.js
 */
'use strict';

async function loadStats() {
  try {
    const r = await apiGet(`${API.articles}?stats=1`);
    if (!r.success) return;
    const d = r.data;
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = Format.number(v); };
    set('statArticles', d.total_articles   || 0);
    set('statVendeurs', d.total_vendeurs   || 0);
    set('statEncheres', d.encheres_actives || 0);
    set('statVentes',   d.total_ventes     || 0);
    set('statMembres',  d.total_membres    || 0);
  } catch {}
}

async function loadRecentArticles() {
  const el = document.getElementById('recentArticles');
  if (!el) return;
  el.innerHTML = Array(4).fill(`<div class="card" style="pointer-events:none;opacity:.4"><div class="card__image" style="background:var(--clr-border)"></div><div class="card__body"><div style="height:12px;background:var(--clr-border);border-radius:4px;width:40%;margin-bottom:.5rem"></div><div style="height:14px;background:var(--clr-border);border-radius:4px"></div></div></div>`).join('');
  try {
    const r = await apiGet(`${API.articles}?sort=recent&page=1`);
    if (!r.success || !r.data.articles?.length) { el.innerHTML = '<p class="text-mid">Aucun article disponible.</p>'; return; }
    el.innerHTML = r.data.articles.slice(0, 8).map(buildCard).join('');
    el.querySelectorAll('[data-countdown]').forEach(startInlineCountdown);
  } catch (err) { el.innerHTML = `<p class="text-mid">${err.message}</p>`; }
}

async function loadLiveEncheres() {
  const el = document.getElementById('liveEncheres');
  if (!el) return;
  try {
    const r = await apiGet(`${API.articles}?type=enchere&sort=enchere&page=1`);
    if (!r.success || !r.data.articles?.length) { el.innerHTML = '<p class="text-mid">Aucune enchère en cours.</p>'; return; }
    el.innerHTML = r.data.articles.slice(0, 4).map(buildCard).join('');
    el.querySelectorAll('[data-countdown]').forEach(startInlineCountdown);
  } catch {}
}

function initHeaderSearch() {
  const inp = document.getElementById('headerSearch');
  if (!inp) return;
  const go = () => { if (inp.value.trim()) window.location.href = `browse.html?q=${encodeURIComponent(inp.value.trim())}`; };
  inp.addEventListener('keydown', e => { if (e.key === 'Enter') go(); });
  inp.closest?.('.search-bar')?.querySelector('button')?.addEventListener('click', go);
}

document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadRecentArticles();
  loadLiveEncheres();
  initHeaderSearch();
});
