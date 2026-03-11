
'use strict';

let filters = { q:'', type:[], categorie:'', etat:'', vendeur_id:'', prix_min:'', prix_max:'', sort:'recent', page:1 };

function readParams() {
  const p = new URLSearchParams(location.search);
  if (p.get('q'))         filters.q         = p.get('q');
  if (p.get('type'))      filters.type       = [p.get('type')];
  if (p.get('categorie')) filters.categorie  = p.get('categorie');
  if (p.get('sort'))      filters.sort       = p.get('sort');
  if (p.get('page'))      filters.page       = Math.max(1, parseInt(p.get('page')) || 1);
  const si = document.getElementById('searchInput'); if (si && filters.q) si.value = filters.q;
  const ss = document.getElementById('sortSelect');  if (ss) ss.value = filters.sort;
}

function buildUrl() {
  const p = new URLSearchParams();
  if (filters.q)          p.set('q',         filters.q);
  if (filters.type.length === 1) p.set('type', filters.type[0]);
  filters.type.forEach(t => p.append('type[]', t));
  if (filters.categorie)  p.set('categorie', filters.categorie);
  if (filters.etat)       p.set('etat',      filters.etat);
  if (filters.vendeur_id) p.set('vendeur_id',filters.vendeur_id);
  if (filters.prix_min)   p.set('prix_min',  filters.prix_min);
  if (filters.prix_max)   p.set('prix_max',  filters.prix_max);
  p.set('sort', filters.sort);
  p.set('page', filters.page);
  return `${API.articles}?${p}`;
}

async function loadArticles() {
  const grid  = document.getElementById('articlesGrid') || document.getElementById('productsGrid');
  const count = document.getElementById('resultCount') || document.getElementById('articleCount');
  const empty = document.getElementById('emptyState') || null;
  const pag   = document.getElementById('pagination');
  if (!grid) return;

  grid.innerHTML = Array(6).fill(`<div class="card" style="pointer-events:none;opacity:.35"><div class="card__image" style="background:var(--clr-border)"></div><div class="card__body"><div style="height:12px;background:var(--clr-border);border-radius:4px;width:40%;margin-bottom:.5rem"></div><div style="height:14px;background:var(--clr-border);border-radius:4px;margin-bottom:.4rem"></div><div style="height:14px;background:var(--clr-border);border-radius:4px;width:65%"></div></div></div>`).join('');

  try {
    const r = await apiGet(buildUrl());
    if (!r.success) throw new Error(r.message);
    const { articles, pagination: pg } = r.data;
    if (count) count.textContent = pg.total;
    if (!articles.length) {
      grid.innerHTML = '';
      if (empty) empty.style.display = '';
      if (pag)   pag.innerHTML = '';
      return;
    }
    if (empty) empty.style.display = 'none';
    grid.innerHTML = articles.map(buildCard).join('');
    grid.querySelectorAll('[data-countdown]').forEach(startInlineCountdown);
    if (pag) renderPagination(pag, pg);
  } catch (e) {
    console.error('[browse] loadArticles error:', e, '— URL:', buildUrl());
    grid.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--grey-600)">
        <i class="fas fa-exclamation-circle" style="font-size:2.5rem;color:var(--danger);display:block;margin:0 auto var(--sp-4);opacity:.7"></i>
        <strong style="color:var(--navy);display:block;margin-bottom:.5rem">Impossible de charger les articles</strong>
        <p style="font-size:var(--fs-sm)">${e.message}</p>
        <p style="font-size:var(--fs-xs);color:var(--grey-400);margin-top:.5rem">Vérifiez que le serveur PHP est actif et la base de données importée.</p>
        <button class="btn btn-outline btn-sm" style="margin-top:1rem" onclick="loadArticles()">
          <i class="fas fa-redo"></i> Réessayer
        </button>
      </div>`;
  }
}

function renderPagination(el, pg) {
  if (pg.total_pages <= 1) { el.innerHTML = ''; return; }
  const { current: cur, total_pages: tot, has_prev, has_next } = pg;
  let h = `<button class="btn btn-outline btn-sm" onclick="goPage(${cur-1})" ${!has_prev?'disabled':''}><i class="fas fa-chevron-left"></i></button>`;
  const s = Math.max(1, cur-2), e = Math.min(tot, cur+2);
  if (s > 1) h += `<button class="btn btn-ghost btn-sm" onclick="goPage(1)">1</button>`;
  if (s > 2) h += `<span style="padding:.5rem">…</span>`;
  for (let i=s; i<=e; i++) h += `<button class="btn btn-sm ${i===cur?'btn-primary':'btn-ghost'}" onclick="goPage(${i})">${i}</button>`;
  if (e < tot-1) h += `<span style="padding:.5rem">…</span>`;
  if (e < tot)   h += `<button class="btn btn-ghost btn-sm" onclick="goPage(${tot})">${tot}</button>`;
  h += `<button class="btn btn-outline btn-sm" onclick="goPage(${cur+1})" ${!has_next?'disabled':''}><i class="fas fa-chevron-right"></i></button>`;
  el.innerHTML = `<div style="display:flex;gap:.35rem;align-items:center;justify-content:center;flex-wrap:wrap">${h}</div>`;
}

window.goPage = p => { filters.page = p; loadArticles(); window.scrollTo({top:0,behavior:'smooth'}); };

function initFilters() {
  // Recherche debounce
  let timer;
  const si = document.getElementById('searchInput');
  if (si) si.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { filters.q = si.value.trim(); filters.page = 1; loadArticles(); }, 500); });

  // Tri
  const ss = document.getElementById('sortSelect');
  if (ss) ss.addEventListener('change', () => { filters.sort = ss.value; filters.page = 1; loadArticles(); });

  // Pills catégorie
  document.querySelectorAll('#pillsBar .pill, [data-cat]').forEach(pill => {
    pill.addEventListener('click', () => {
      document.querySelectorAll('#pillsBar .pill, [data-cat]').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      filters.categorie = pill.dataset.cat || '';
      filters.page = 1;
      loadArticles();
    });
  });

  // Bouton appliquer
  document.getElementById('applyFilters')?.addEventListener('click', () => {
    filters.type       = [...document.querySelectorAll('input[name="type"]:checked')].map(c => c.value);
    const catR         = document.querySelector('input[name="categorie"]:checked');
    if (catR)          filters.categorie = catR.value;
    const etatR        = document.querySelector('input[name="etat"]:checked');
    if (etatR)         filters.etat = etatR.value;
    filters.prix_min   = document.getElementById('fPrixMin')?.value  || '';
    filters.prix_max   = document.getElementById('fPrixMax')?.value  || '';
    filters.vendeur_id = document.getElementById('fVendeur')?.value  || '';
    filters.page = 1;
    loadArticles();
  });

  // Reset
  document.querySelectorAll('#resetFilters,#resetFilters2').forEach(btn => {
    btn.addEventListener('click', () => {
      filters = { q:'', type:[], categorie:'', etat:'', vendeur_id:'', prix_min:'', prix_max:'', sort:'recent', page:1 };
      document.querySelectorAll('input[name="type"],input[name="etat"]').forEach(c => c.checked = false);
      const allCat = document.querySelector('input[name="categorie"][value=""]'); if (allCat) allCat.checked = true;
      const fPrix = ['fPrixMin','fPrixMax']; fPrix.forEach(id => { const e = document.getElementById(id); if (e) e.value=''; });
      const fV = document.getElementById('fVendeur'); if (fV) fV.value = '';
      if (ss) ss.value = 'recent';
      if (si) si.value = '';
      document.querySelectorAll('#pillsBar .pill,[data-cat]').forEach((p,i) => p.classList.toggle('active', i===0));
      if (empty) empty.style.display = 'none';
      loadArticles();
    });
  });

  // Charger vendeurs
  apiGet(`${API.articles}?vendeurs=1`).then(r => {
    const sel = document.getElementById('fVendeur');
    if (!sel || !r.success) return;
    r.data.vendeurs?.forEach(v => { const o = document.createElement('option'); o.value = v.vendeur_id; o.textContent = `${v.prenom} ${v.nom}`; sel.appendChild(o); });
  }).catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
  readParams();
  initFilters();
  loadArticles();
});

window.loadArticles = loadArticles;
window.applyFilters = function() { loadArticles(); };
