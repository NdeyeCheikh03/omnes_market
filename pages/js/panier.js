/**
 * OMNES MARKETPLACE — panier.js
 */
'use strict';

let _items = [];
let _promoApplied = 0;

const PROMOS = { 'OMNES2026':15, 'ECE10':10, 'INSEEC':12 };

/* ── Charger ── */
async function loadPanier() {
  try {
    const r = await apiGet(`${API.panier}?action=get`);
    _items = r.success ? (r.data?.items || []) : [];
  } catch { _items = []; }
  renderItems();
  calcSummary();
}

/* ── Afficher ── */
function renderItems() {
  const table = document.getElementById('panierTable');
  const tbody = table?.querySelector('tbody');
  const empty = document.getElementById('emptyCart');
  const content = document.getElementById('cartContent');
  const countEl = document.getElementById('cartItemCount');

  if (countEl) countEl.textContent = _items.length;
  updateCartBadge(_items.length);

  if (!_items.length) {
    if (content) content.style.display = 'none';
    if (empty)   empty.style.display   = '';
    return;
  }
  if (empty)   empty.style.display   = 'none';
  if (content) content.style.display = '';

  if (!tbody) return;
  tbody.innerHTML = _items.map(item => `
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:.75rem">
          <div style="width:64px;height:64px;border-radius:var(--radius-sm);overflow:hidden;background:var(--clr-light);flex-shrink:0;display:flex;align-items:center;justify-content:center">
            ${item.image_url ? `<img src="${item.image_url}" alt="${item.titre}" style="width:100%;height:100%;object-fit:cover">` : '<i class="fas fa-image" style="opacity:.3"></i>'}
          </div>
          <div>
            <a href="article.html?id=${item.article_id}" class="fw-600" style="display:block">${item.titre}</a>
            <span style="font-size:var(--text-xs);color:var(--clr-mid)">${item.etat||''}</span>
          </div>
        </div>
      </td>
      <td><strong style="color:var(--clr-primary)">${Format.price(item.prix_final||item.prix_immediat||0)}</strong></td>
      <td>
        <button class="btn btn-ghost btn-sm" onclick="removeItem(${item.article_id})" title="Retirer">
          <i class="fas fa-trash" style="color:var(--clr-danger)"></i>
        </button>
      </td>
    </tr>`).join('');
}

window.removeItem = async function(articleId) {
  try { await apiPost(API.panier, { action:'remove', article_id: articleId }); } catch {}
  _items = _items.filter(i => i.article_id !== articleId);
  renderItems(); calcSummary();
  Toast.success('Article retiré du panier.');
};

window.viderPanier = async function() {
  if (!_items.length || !confirm('Vider tout le panier ?')) return;
  try { await apiPost(API.panier, { action:'clear' }); } catch {}
  _items = []; renderItems(); calcSummary();
  Toast.info('Panier vidé.');
};

/* ── Calcul ── */
function calcSummary() {
  const sousTotal = _items.reduce((s,i) => s + +(i.prix_final||i.prix_immediat||0), 0);
  const promo     = _promoApplied > 0 ? sousTotal * _promoApplied / 100 : 0;
  const remise10  = sousTotal >= 100   ? sousTotal * 0.10 : 0;
  const remise    = Math.max(promo, remise10);
  const total     = sousTotal - remise;

  const set = (id,v) => { const el=document.getElementById(id); if(el) el.textContent=v; };
  set('sousTotal',    Format.price(sousTotal));
  set('totalPanier',  Format.price(total));
  set('montantRemise','−'+Format.price(remise));

  const remEl = document.getElementById('remiseLine');
  if (remEl) remEl.style.display = remise > 0 ? '' : 'none';

  const cmdBtn = document.getElementById('btnCommander');
  if (cmdBtn) cmdBtn.disabled = _items.length === 0;
}

/* ── Code promo ── */
function initCodePromo() {
  const btn   = document.getElementById('appliquerCode');
  const input = document.getElementById('codePromo');
  if (!btn||!input) return;
  btn.addEventListener('click', () => {
    const code = input.value.trim().toUpperCase();
    if (!code) return;
    const pct = PROMOS[code];
    if (!pct)          { Toast.error('Code promo invalide.'); return; }
    if (_promoApplied) { Toast.warning('Un code est déjà appliqué.'); return; }
    _promoApplied = pct;
    input.disabled = btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-check"></i> Appliqué';
    Toast.success(`Code ${code} — ${pct}% de réduction !`);
    calcSummary();
  });
}

/* ── Checkout ── */
let _step = 1;
function goStep(n) {
  _step = n;
  [1,2,3].forEach(i => {
    const s = document.getElementById(`checkoutStep${i}`);
    if (s) s.style.display = i===n ? '' : 'none';
    const dot = document.getElementById(`step${i}Indicator`);
    if (dot) dot.classList.toggle('active', i<=n);
  });
  const next = document.getElementById('checkoutNext');
  if (next) next.innerHTML = n===3 ? '<i class="fas fa-check"></i> Confirmer la commande' : 'Suivant <i class="fas fa-chevron-right"></i>';
}

function initCheckout() {
  const openBtn = document.getElementById('btnCommander');
  if (openBtn) openBtn.addEventListener('click', () => {
    if (!_items.length) { Toast.warning('Votre panier est vide.'); return; }
    if (!currentUser()) { location.href = 'login.html'; return; }
    openModal('modalCheckout'); goStep(1);
  });

  document.getElementById('viderPanier')?.addEventListener('click', window.viderPanier);

  const next = document.getElementById('checkoutNext');
  const prev = document.getElementById('checkoutPrev');
  if (next) next.addEventListener('click', () => _step < 3 ? goStep(_step+1) : placeOrder());
  if (prev) prev.addEventListener('click', () => _step > 1 ? goStep(_step-1) : null);

  initCardFormatting();
}

async function placeOrder() {
  const prenom   = (document.getElementById('livPrenom') || document.getElementById('coPrenom'))?.value.trim();
  const nom      = (document.getElementById('livNom')    || document.getElementById('coNom'))?.value.trim();
  const adresse  = (document.getElementById('adresseLiv')|| document.getElementById('coAdresse'))?.value.trim();
  const livraison = document.querySelector('input[name="modeLivraison"]:checked')?.value || 'standard';

  if (!prenom||!nom||!adresse) { Toast.warning('Complétez tous les champs de livraison.'); goStep(1); return; }

  const btn = document.getElementById('checkoutNext');
  if (btn) { btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Traitement…'; }

  try {
    const r = await apiPost(API.commande, { action:'passer', adresse, prenom, nom, livraison });
    if (!r.success) throw new Error(r.message);
    closeModal('modalCheckout');
    _items=[]; renderItems(); calcSummary();
    Toast.success('🎉 Commande confirmée !');
    const refEl = document.getElementById('orderRef');
    if (refEl&&r.data?.reference) refEl.textContent = r.data.reference;
    setTimeout(() => location.href='compte.html#commandes', 2000);
  } catch (err) {
    Toast.error(err.message||'Erreur lors de la commande.');
    if (btn) { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Confirmer la commande'; }
  }
}

function initCardFormatting() {
  const cn = document.getElementById('cardNumber');
  if (cn) cn.addEventListener('input', () => {
    cn.value = cn.value.replace(/\D/g,'').slice(0,16).replace(/(.{4})/g,'$1 ').trim();
    const p = document.getElementById('previewNumber'); if(p) p.textContent=cn.value||'•••• •••• •••• ••••';
  });
  const cc = document.getElementById('cardCvv');
  if (cc) cc.addEventListener('input', () => { cc.value=cc.value.replace(/\D/g,'').slice(0,4); });
  const cx = document.getElementById('cardExpiry');
  if (cx) cx.addEventListener('input', () => {
    let v=cx.value.replace(/\D/g,''); if(v.length>2) v=v.slice(0,2)+'/'+v.slice(2,4);
    cx.value=v;
    const p=document.getElementById('previewExpiry'); if(p) p.textContent=cx.value||'MM/YY';
  });
  const cn2 = document.getElementById('cardName');
  if (cn2) cn2.addEventListener('input', () => {
    const p=document.getElementById('previewName'); if(p) p.textContent=cn2.value.toUpperCase()||'VOTRE NOM';
  });
}

function updateCartBadge(n) {
  const badge = document.getElementById('cartBadge');
  if (badge) { badge.textContent=n; badge.style.display=n>0?'flex':'none'; }
}

document.addEventListener('DOMContentLoaded', () => {
  loadPanier();
  initCodePromo();
  initCheckout();
});
