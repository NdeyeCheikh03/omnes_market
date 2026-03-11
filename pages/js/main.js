
'use strict';

/* ── BASE URL ── */
/**
 * Calcule la base URL du projet de façon robuste.
 * Fonctionne quel que soit le nom du dossier WAMP/XAMPP
 * et que les pages soient dans /pages/ ou à la racine.
 *
 * Exemples :
 *   http://localhost/omnes_fixed/pages/login.html  → /omnes_fixed
 *   http://localhost/monprojet/pages/login.html     → /monprojet
 *   http://localhost/pages/login.html               → (vide)
 */
const _base = (() => {
  const path = window.location.pathname; // ex: /omnes_fixed/pages/login.html
  // Cherche /pages/ dans le chemin
  const idx = path.indexOf('/pages/');
  if (idx !== -1) {
    return path.slice(0, idx); // ex: /omnes_fixed
  }
  // Si pas de /pages/, on remonte d'un niveau depuis le fichier courant
  const parts = path.split('/');
  parts.pop(); // retire le fichier
  parts.pop(); // remonte d'un dossier
  return parts.join('/');
})();

/* ── API ENDPOINTS ── */
const API = {
  auth:          _base + '/php/api/auth.php',
  articles:      _base + '/php/api/articles.php',
  encheres:      _base + '/php/api/encheres.php',
  panier:        _base + '/php/api/panier.php',
  negociation:   _base + '/php/api/negociation.php',
  commande:      _base + '/php/api/commande.php',
  notifications: _base + '/php/api/notifications.php',
};
window.API = API;

/* ── HTTP HELPERS ── */
async function apiPost(url, data) {
  const isForm = data instanceof FormData;
  // Debug : afficher l'URL et les données dans la console navigateur
  if (typeof console !== 'undefined') {
    console.debug('[apiPost]', url, isForm ? '(FormData)' : data);
  }
  try {
    const r = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: isForm ? { 'X-Requested-With': 'XMLHttpRequest' }
                      : { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: isForm ? data : JSON.stringify(data),
    });
    const json = await r.json();
    if (typeof console !== 'undefined' && !json.success) {
      console.warn('[apiPost] réponse KO:', json);
    }
    return json;
  } catch (e) {
    console.error('[apiPost] erreur réseau:', url, e);
    return { success: false, message: 'Erreur réseau — vérifiez que le serveur PHP est actif.' };
  }
}
async function apiGet(url) {
  try {
    const r = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return await r.json();
  } catch (e) { return { success: false, message: 'Erreur réseau.' }; }
}
window.apiPost = apiPost;
window.apiGet  = apiGet;

/* ── AUTH ── */
let _user = null;
window.currentUser = () => _user;

let _authResolve;
window.authReady = new Promise(res => { _authResolve = res; });

async function loadAuthState() {
  try {
    const r = await apiPost(API.auth, { action: 'me' });
    if (r.success && r.data) { _user = r.data; _applyUI(true, r.data); }
    else _applyUI(false);
  } catch { _applyUI(false); }
  if (_authResolve) { _authResolve(_user); _authResolve = null; }
}

function _applyUI(ok, u) {
  const $ = id => document.getElementById(id);
  if (ok && u) {
    if ($('userMenu'))     $('userMenu').style.display = 'flex';
    if ($('loginBtn'))     $('loginBtn').style.display = 'none';
    if ($('userAvatarBtn')) $('userAvatarBtn').textContent = Format.initials(u.name || 'U');
  } else {
    if ($('userMenu'))  $('userMenu').style.display  = 'none';
    if ($('loginBtn'))  $('loginBtn').style.display  = 'inline-flex';
  }
  // Badge notifications
  loadNotifBadge();
  loadCartBadge();
}

async function loadNotifBadge() {
  if (!_user) return;
  try {
    const r = await apiGet(`${API.notifications}?action=liste`);
    if (r.success) {
      const n = r.data.unread || 0;
      const el = document.getElementById('notifBadge');
      if (el) { el.textContent = n; el.style.display = n > 0 ? 'flex' : 'none'; }
    }
  } catch {}
}

async function loadCartBadge() {
  if (!_user || _user.role !== 'acheteur') return;
  try {
    const r = await apiGet(`${API.panier}?action=get`);
    if (r.success) {
      const n = r.data.count || 0;
      const el = document.getElementById('cartBadge');
      if (el) { el.textContent = n; el.style.display = n > 0 ? 'flex' : 'none'; }
    }
  } catch {}
}

/* ── LOGOUT ── */
async function logout() {
  try { await apiPost(API.auth, { action: 'logout' }); } catch {}
  _user = null;
  window.location.href = 'login.html';
}
window.logout = logout;

/* ── HEADER ── */
function _initHeader() {
  const h = document.getElementById('header');
  if (h) window.addEventListener('scroll', () => h.classList.toggle('scrolled', scrollY > 8), { passive: true });

  // Hamburger menu mobile
  const menuBtn = document.getElementById('menuBtn');
  const mobileNav = document.getElementById('mobileNav');
  if (menuBtn && mobileNav) {
    menuBtn.addEventListener('click', () => {
      const open = mobileNav.classList.toggle('open');
      const i = menuBtn.querySelector('i');
      if (i) i.className = open ? 'fas fa-times' : 'fas fa-bars';
    });
  }

  // Dropdown user
  const avatarBtn = document.getElementById('userAvatarBtn');
  const dropdown  = document.getElementById('userDropdown');
  if (avatarBtn && dropdown) {
    avatarBtn.addEventListener('click', e => { e.stopPropagation(); dropdown.classList.toggle('open'); });
    document.addEventListener('click', () => dropdown.classList.remove('open'));
  }
}

/* ── MODALS ── */
function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => el.querySelector('input,textarea,select')?.focus(), 100);
}
function closeModal(id) {
  if (id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  } else {
    document.querySelectorAll('.modal-overlay.open').forEach(el => { el.classList.remove('open'); });
    document.body.style.overflow = '';
  }
}
window.openModal  = openModal;
window.closeModal = closeModal;

function _initModals() {
  document.querySelectorAll('[data-modal-open]').forEach(b =>
    b.addEventListener('click', () => openModal(b.dataset.modalOpen)));
  document.querySelectorAll('[data-modal-close], .modal-close').forEach(b =>
    b.addEventListener('click', () => closeModal(b.dataset.modalClose || null)));
  document.querySelectorAll('.modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(null); });
}

/* ── COUNTDOWN ── */
function startInlineCountdown(el) {
  const end = new Date(el.dataset.countdown || el.dataset.end || '').getTime();
  if (!end || isNaN(end)) return;
  const pad = n => String(n).padStart(2, '0');
  (function tick() {
    const d = Math.max(0, end - Date.now());
    if (!d) { el.innerHTML = '<span style="color:var(--clr-accent)">Terminé</span>'; return; }
    const jj = Math.floor(d / 86400000);
    const hh = Math.floor((d % 86400000) / 3600000);
    const mm = Math.floor((d % 3600000) / 60000);
    const ss = Math.floor((d % 60000) / 1000);
    const urgent = d < 3600000;
    const col = urgent ? 'var(--clr-accent)' : 'var(--clr-gold)';
    el.innerHTML = jj > 0
      ? `<span style="color:${col}">${jj}j ${pad(hh)}h ${pad(mm)}m</span>`
      : `<span style="color:${col}">${pad(hh)}:${pad(mm)}:${pad(ss)}</span>`;
    setTimeout(tick, 1000);
  })();
}
window.startInlineCountdown = startInlineCountdown;

/* ── BUILD CARD ── */
function buildCard(a) {
  const prix = +(a.prix_actuel ?? a.prix_immediat ?? a.prix_depart_enchere ?? a.prix_depart_nego ?? 0);
  const esc = s => String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  let badges = '';
  if (a.type_enchere)  badges += `<span class="badge badge-enchere"><i class="fas fa-gavel"></i> Enchère</span>`;
  if (a.type_nego)     badges += `<span class="badge badge-nego"><i class="fas fa-comments-dollar"></i> Négo</span>`;
  if (a.type_immediat) badges += `<span class="badge badge-immediat"><i class="fas fa-bolt"></i> Direct</span>`;
  if (a.categorie === 'rare')          badges += `<span class="badge badge-rare"><i class="fas fa-gem"></i> Rare</span>`;
  if (a.categorie === 'haut_de_gamme') badges += `<span class="badge badge-luxe"><i class="fas fa-crown"></i> Luxe</span>`;

  const timer = (a.type_enchere && a.date_fin)
    ? `<span class="card__timer"><i class="fas fa-clock"></i> <span data-countdown="${a.date_fin}">…</span></span>`
    : '';

  const imgHtml = a.image_url
    ? `<img src="${esc(a.image_url)}" alt="${esc(a.titre)}" loading="lazy">`
    : `<div class="card__image--empty"><i class="fas fa-image"></i></div>`;

  return `
<article class="card" onclick="location.href='article.html?id=${a.article_id}'" style="cursor:pointer">
  <div class="card__image">${imgHtml}<div class="card__badges">${badges}</div></div>
  <div class="card__body">
    <div class="card__category">${esc(a.sous_categorie || a.categorie || 'Article')}</div>
    <h3 class="card__title">${esc(a.titre)}</h3>
    <div class="card__vendor"><i class="fas fa-user-circle"></i> ${esc((a.v_prenom||'')+' '+(a.v_nom||''))}</div>
  </div>
  <div class="card__footer">
    <strong class="card__price">${Format.price(prix)}</strong>
    ${timer}
    <a class="btn btn-primary btn-sm" href="article.html?id=${a.article_id}" onclick="event.stopPropagation()">Voir</a>
  </div>
</article>`;
}
window.buildCard = buildCard;

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', () => {
  loadAuthState();
  _initHeader();
  _initModals();
});
