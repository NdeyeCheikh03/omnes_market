
'use strict';

const artId = new URLSearchParams(location.search).get('id');

async function loadArticle() {
  if (!artId) { showError('Aucun article sélectionné.'); return; }
  try {
    const r = await apiGet(`${API.articles}?id=${artId}`);
    if (!r.success) throw new Error(r.message);
    renderArticle(r.data);
  } catch (e) { showError(e.message); }
}

function showError(msg) {
  const sk = document.getElementById('skeletonLoader');
  if (sk) sk.style.display = 'none';
  const el = document.querySelector('main .container');
  if (el) el.innerHTML = `<div style="text-align:center;padding:4rem 0"><i class="fas fa-exclamation-triangle" style="font-size:3rem;color:var(--clr-accent);display:block;margin-bottom:1rem"></i><h2>${msg}</h2><a href="browse.html" class="btn btn-primary" style="margin-top:1rem;display:inline-flex">Retour au catalogue</a></div>`;
}

function renderArticle(a) {
  document.title = `${a.titre} — Omnes MarketPlace`;

  // Basculer skeleton → contenu
  const sk = document.getElementById('skeletonLoader');
  if (sk) sk.style.display = 'none';
  const ac = document.getElementById('articleContent');
  if (ac) ac.style.display = '';

  const set     = (id, v)  => { const el = document.getElementById(id); if (el) el.textContent = v; };
  const setHtml = (id, v)  => { const el = document.getElementById(id); if (el) el.innerHTML = v; };
  const esc     = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  set('articleTitle',       a.titre);
  set('articleDescription', a.description || '');
  set('vendeurNom',         `${a.v_prenom||''} ${a.v_nom||''}`.trim());
  set('vendeurEmail',       a.v_email || '');
  set('breadcrumbTitle',    a.titre);

  // Initiale vendeur
  const vi = document.getElementById('vendeurInitiale');
  if (vi) vi.textContent = Format.initials(`${a.v_prenom||''} ${a.v_nom||''}`);

  // Image principale
  const img = document.getElementById('mainImage');
  const ph  = document.getElementById('imagePlaceholder');
  if (img && a.image_url) { img.src = a.image_url; img.alt = esc(a.titre); img.style.display = ''; if (ph) ph.style.display = 'none'; }

  // Prix
  const prix = +(a.prix_actuel ?? a.prix_immediat ?? a.prix_depart_enchere ?? a.prix_depart_nego ?? 0);
  set('articlePrice', Format.price(prix));
  set('prixAffiche',  Format.price(prix));
  set('prixActuel',   Format.price(a.prix_actuel || a.prix_depart || prix));

  // Badges
  let badges = '';
  if (a.type_enchere)  badges += `<span class="badge badge-enchere"><i class="fas fa-gavel"></i> Enchère</span>`;
  if (a.type_nego)     badges += `<span class="badge badge-nego"><i class="fas fa-comments-dollar"></i> Négociation</span>`;
  if (a.type_immediat) badges += `<span class="badge badge-immediat"><i class="fas fa-bolt"></i> Achat direct</span>`;
  if (a.categorie === 'rare')          badges += `<span class="badge badge-rare"><i class="fas fa-gem"></i> Rare</span>`;
  if (a.categorie === 'haut_de_gamme') badges += `<span class="badge badge-luxe"><i class="fas fa-crown"></i> Luxe</span>`;
  setHtml('articleBadges', badges);

  // Méta
  const etatLabels = { neuf:'Neuf', tres_bon:'Très bon état', bon:'Bon état', correct:'Correct' };
  const metaItems = [
    a.sous_categorie && `<div style="display:flex;gap:.5rem;align-items:center"><i class="fas fa-tag" style="width:16px;color:var(--clr-primary)"></i><span>${esc(a.sous_categorie)}</span></div>`,
    a.etat           && `<div style="display:flex;gap:.5rem;align-items:center"><i class="fas fa-star" style="width:16px;color:var(--clr-gold)"></i><span>${etatLabels[a.etat]||a.etat}</span></div>`,
    a.date_creation  && `<div style="display:flex;gap:.5rem;align-items:center"><i class="fas fa-calendar" style="width:16px;color:var(--clr-mid)"></i><span>Publié le ${Format.date(a.date_creation)}</span></div>`,
  ].filter(Boolean).join('');
  setHtml('articleMeta', metaItems);

  // Boxes visibilité
  const show = (id, v) => { const el = document.getElementById(id); if (el) el.style.display = v ? '' : 'none'; };
  show('boxImmédiat', a.type_immediat);
  show('boxEnchere',  a.type_enchere);
  show('boxNego',     a.type_nego);

  // Enchère — countdown unifié
  if (a.type_enchere && a.date_fin) {
    set('nbOffres', a.nb_offres || 0);
    set('enchereCourante', Format.price(a.prix_actuel || a.prix_depart || prix));
    const cdEl = document.getElementById('countdownDisplay');
    if (cdEl) { cdEl.dataset.countdown = a.date_fin; startInlineCountdown(cdEl); }
  }

  // Tour négociation
  if (a.nego_active) {
    const tourEl = document.getElementById('tourActuel');
    if (tourEl) tourEl.textContent = (a.nego_active.nb_tours || 1);
    const negoStatus = document.getElementById('negoStatus');
    const negoStatusMsg = document.getElementById('negoStatusMsg');
    if (negoStatus) negoStatus.style.display = '';
    if (negoStatusMsg) negoStatusMsg.textContent = `Négociation en cours — tour ${a.nego_active.nb_tours || 1}/5`;
  }

  // Init interactions
  window.authReady.then(() => {
    if (a.type_immediat) initAchatImmediat(a);
    if (a.type_enchere)  initEnchere(a);
    if (a.type_nego)     initNego(a);
  });
}

/* ── Achat immédiat ── */
function initAchatImmediat(a) {
  const btn = document.getElementById('btnAchatImmediat');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    if (!currentUser()) { location.href = 'login.html'; return; }
    if (currentUser().role !== 'acheteur') { Toast.warning('Connectez-vous en tant qu\'acheteur.'); return; }
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout…';
    try {
      const r = await apiPost(API.panier, { action:'add', article_id: +artId });
      if (!r.success) throw new Error(r.message);
      Toast.success('Article ajouté au panier !');
      btn.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
      const badge = document.getElementById('cartBadge');
      if (badge) { badge.textContent = (parseInt(badge.textContent)||0)+1; badge.style.display='flex'; }
      setTimeout(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier'; }, 2500);
    } catch (err) {
      Toast.error(err.message || 'Erreur');
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier';
    }
  });
}

/* ── Enchère ── */
function initEnchere(a) {
  const btn = document.getElementById('btnEncherir');
  if (!btn) return;
  btn.addEventListener('click', () => {
    if (!currentUser()) { location.href = 'login.html'; return; }
    if (currentUser().role !== 'acheteur') { Toast.warning('Connectez-vous en tant qu\'acheteur.'); return; }
    const inp = document.getElementById('montantEnchere');
    const minPrix = +(a.prix_actuel || a.prix_depart_enchere || 0) + 1;
    if (inp) { inp.min = minPrix; inp.placeholder = `Min. ${Format.price(minPrix)}`; if (!inp.value || +inp.value < minPrix) inp.value = Math.ceil(minPrix); }
    openModal('modalEnchere');
  });

  document.getElementById('submitEnchere')?.addEventListener('click', async () => {
    const val = +document.getElementById('montantEnchere')?.value;
    const btn2 = document.getElementById('submitEnchere');
    if (!val || val <= 0) { Toast.warning('Montant invalide.'); return; }
    if (!a.enchere_id) { Toast.error('ID enchère manquant.'); return; }
    btn2.disabled = true; btn2.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
      const r = await apiPost(API.encheres, { action:'encherir', enchere_id: +a.enchere_id, montant_max: val });
      if (!r.success) throw new Error(r.message);
      Toast.success('Enchère placée ! Vous êtes en tête.');
      closeModal('modalEnchere');
      loadArticle();
    } catch (err) {
      Toast.error(err.message || 'Erreur enchère');
      btn2.disabled = false; btn2.innerHTML = '<i class="fas fa-gavel"></i> Confirmer l\'offre';
    }
  });
}

/* ── Négociation ── */
function initNego(a) {
  const btn = document.getElementById('btnNego');
  if (!btn) return;
  btn.addEventListener('click', () => {
    if (!currentUser()) { location.href = 'login.html'; return; }
    if (currentUser().role !== 'acheteur') { Toast.warning('Connectez-vous en tant qu\'acheteur.'); return; }
    if (a.nego_active) { Toast.info('Vous avez déjà une négociation en cours pour cet article.'); return; }
    openModal('modalNego');
  });

  document.getElementById('submitNego')?.addEventListener('click', async () => {
    const prix    = +document.getElementById('prixPropose')?.value;
    const message = document.getElementById('negoMessage')?.value.trim();
    const btn2    = document.getElementById('submitNego');
    if (!prix || prix <= 0) { Toast.warning('Proposez un prix valide.'); return; }
    btn2.disabled = true; btn2.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
      const r = await apiPost(API.negociation, { action:'proposer', article_id: +artId, prix_propose: prix, message });
      if (!r.success) throw new Error(r.message);
      Toast.success('Proposition envoyée au vendeur !');
      closeModal('modalNego');
      loadArticle();
    } catch (err) {
      Toast.error(err.message || 'Erreur négociation');
      btn2.disabled = false; btn2.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer l\'offre';
    }
  });
}

document.addEventListener('DOMContentLoaded', loadArticle);
