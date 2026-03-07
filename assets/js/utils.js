/**
 * OMNES MARKETPLACE — utils.js
 * Toast, Format, Countdown — AUCUNE déclaration de openModal, API, apiPost
 */
'use strict';

/* ── TOAST ── */
const Toast = (() => {
  const show = (msg, type = 'info', dur = 4000) => {
    let c = document.getElementById('toastContainer');
    if (!c) { c = document.createElement('div'); c.id = 'toastContainer'; c.className = 'toast-container'; document.body.appendChild(c); }
    const t = document.createElement('div');
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
    t.className = `toast ${type}`;
    t.innerHTML = `<i class="fa-solid ${icons[type]||icons.info}"></i><span style="flex:1">${msg}</span><button style="background:none;border:none;color:inherit;cursor:pointer;padding:0 0 0 .5rem" onclick="this.closest('.toast').remove()"><i class="fa-solid fa-xmark"></i></button>`;
    c.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    if (dur > 0) setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, dur);
    return t;
  };
  return { show, success:(m,d)=>show(m,'success',d), error:(m,d)=>show(m,'error',d), warning:(m,d)=>show(m,'warning',d), info:(m,d)=>show(m,'info',d) };
})();
window.Toast = Toast;

/* ── FORMAT ── */
const Format = {
  price: (v, locale='fr-FR') => new Intl.NumberFormat(locale, {style:'currency',currency:'EUR'}).format(+v||0),
  number: (v, locale='fr-FR') => new Intl.NumberFormat(locale).format(+v||0),
  date: (d, locale='fr-FR') => new Intl.DateTimeFormat(locale,{day:'numeric',month:'long',year:'numeric'}).format(new Date(d)),
  relativeTime: d => {
    const diff = Date.now() - new Date(d).getTime();
    const m = Math.floor(diff/60000);
    if (m < 1)  return 'À l\'instant';
    if (m < 60) return `Il y a ${m} min`;
    const h = Math.floor(m/60);
    if (h < 24) return `Il y a ${h}h`;
    const j = Math.floor(h/24);
    if (j < 7)  return `Il y a ${j} jour${j>1?'s':''}`;
    return Format.date(d);
  },
  initials: name => (name||'?').split(' ').map(w=>w[0]||'').slice(0,2).join('').toUpperCase() || '?',
};
window.Format = Format;

/* ── COUNTDOWN ── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-reltime]').forEach(el => { el.textContent = Format.relativeTime(el.dataset.reltime); });
  document.querySelectorAll('[data-price]').forEach(el => { el.textContent = Format.price(el.dataset.price); });
});
