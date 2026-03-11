
'use strict';

const DEMO = {
  acheteur: { email: 'sophie.lemaire@gmail.com', password: 'acheteur123' },
  vendeur:  { email: 'jean.dupont@email.fr',      password: 'vendeur123'  },
  admin:    { email: 'admin@omnes.fr',             password: 'admin123'   },
};

/* ── Role tabs (login) ── */
function initRoleTabs() {
  const tabs = document.querySelectorAll('.role-tab');
  const roleInput = document.getElementById('roleInput');
  if (!tabs.length || !document.getElementById('loginForm')) return; // Login only
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      if (roleInput) roleInput.value = tab.dataset.role;
      updateDemoBox(tab.dataset.role);
    });
  });
}

function updateDemoBox(role) {
  const d = DEMO[role];
  if (!d) return;
  const de = document.getElementById('demoEmail');
  const dp = document.getElementById('demoPass');
  if (de) de.textContent = d.email;
  if (dp) dp.textContent = d.password;
}

window.fillDemo = function() {
  const role = document.getElementById('roleInput')?.value || 'acheteur';
  const d = DEMO[role];
  if (!d) return;
  const e = document.getElementById('email');
  const p = document.getElementById('password');
  if (e) e.value = d.email;
  if (p) p.value = d.password;
  Toast.info('Champs remplis — cliquez sur Se connecter.');
};

/* ── Toggle password ── */
function initPasswordToggle() {
  document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = document.getElementById(btn.dataset.togglePass);
      if (!inp) return;
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      const i = btn.querySelector('i');
      if (i) i.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
  });
  const toggleBtn = document.getElementById('togglePass');
  const passInp   = document.getElementById('password') || document.getElementById('passwordReg');
  const eyeIcon   = document.getElementById('eyeIcon');
  if (toggleBtn && passInp) {
    toggleBtn.addEventListener('click', () => {
      const show = passInp.type === 'password';
      passInp.type = show ? 'text' : 'password';
      if (eyeIcon) eyeIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
  }
}

/* ── Force mot de passe ── */
function initPasswordStrength() {
  const inp = document.getElementById('passwordReg');
  const bar = document.getElementById('passStrengthBar');
  const lbl = document.getElementById('passStrengthLabel');
  if (!inp) return;
  inp.addEventListener('input', () => {
    const v = inp.value;
    const score = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/, /.{8,}/].filter(r => r.test(v)).length;
    const colors = ['', '#ef4444', '#f9c74f', '#f9c74f', '#059669', '#059669'];
    const labels = ['', 'Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
    if (bar) { bar.style.width = `${score * 20}%`; bar.style.background = colors[score]; }
    if (lbl) lbl.textContent = labels[score] || '';
  });
}

/* ── Redirects par rôle ── */
const ROLE_REDIRECTS = {
  acheteur: 'index.html',
  vendeur:  'vendor-dashboard.html',
  admin:    'admin-dashboard.html',
};

/* ── LOGIN ── */
function initLoginForm() {
  const form = document.getElementById('loginForm');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const email    = document.getElementById('email')?.value.trim();
    const password = document.getElementById('password')?.value;
    const role     = document.getElementById('roleInput')?.value || 'acheteur';
    const alertEl  = document.getElementById('loginAlert');
    const alertMsg = document.getElementById('loginAlertMsg');
    const btn      = document.getElementById('submitBtn');

    if (alertEl) alertEl.style.display = 'none';

    const emailErr = document.getElementById('emailErr');
    const passErr  = document.getElementById('passErr');
    if (emailErr) emailErr.style.display = (!email || !email.includes('@')) ? 'flex' : 'none';
    if (passErr)  passErr.style.display  = !password ? 'flex' : 'none';
    if (!email || !email.includes('@') || !password) return;

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion…'; }

    try {
      const r = await apiPost(API.auth, { action: 'login', email, password, role });
      if (!r.success) throw new Error(r.message || 'Identifiants incorrects.');
      Toast.success(`Bienvenue ${r.data.name} !`);
      const dest = r.data.redirect || ROLE_REDIRECTS[role] || 'index.html';
      setTimeout(() => { window.location.href = dest; }, 600);
    } catch (err) {
      if (alertEl) { alertEl.style.display = 'flex'; if (alertMsg) alertMsg.textContent = err.message; }
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Se connecter'; }
    }
  });
}

/* ── REGISTER ── */
function initRegisterForm() {
  const form = document.getElementById('registerForm');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();

    const prenom   = document.getElementById('prenom')?.value.trim();
    const nom      = document.getElementById('nom')?.value.trim();
    const email    = document.getElementById('emailReg')?.value.trim();
    const password = document.getElementById('passwordReg')?.value;
    const confirm  = document.getElementById('confirm')?.value;
    const tel      = document.getElementById('tel')?.value.trim();
    const adresse  = document.getElementById('adresse')?.value.trim();
    const pseudo   = document.getElementById('pseudo')?.value.trim();
    const cgu      = document.getElementById('cgu')?.checked;
    const role     = document.getElementById('roleInput')?.value || 'acheteur';
    const alertEl  = document.getElementById('registerAlert');
    const alertMsg = document.getElementById('registerAlertMsg');
    const btn      = document.getElementById('submitBtnReg');

    const showAlert = msg => {
      if (alertEl) { alertEl.style.display = 'flex'; alertEl.className = 'alert alert-error'; }
      if (alertMsg) alertMsg.textContent = msg;
    };

    if (alertEl) alertEl.style.display = 'none';

    if (!prenom || !nom)              { showAlert('Prénom et nom requis.'); return; }
    if (!email || !email.includes('@')){ showAlert('Adresse email invalide.'); return; }
    if (!password || password.length < 8) { showAlert('Mot de passe : minimum 8 caractères.'); return; }
    if (confirm !== password)         { showAlert('Les mots de passe ne correspondent pas.'); return; }
    if (!cgu)                         { showAlert('Vous devez accepter les conditions d\'utilisation.'); return; }
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création…'; }

    try {
      const r = await apiPost(API.auth, {
        action: 'register',
        prenom, nom, email, password,
        telephone: tel, adresse,
        pseudo: pseudo || '',
        role,
      });
      if (!r.success) throw new Error(r.message);

      if (role === 'vendeur') {
        // Vendeur : compte créé, maintenant se connecter automatiquement
        Toast.success('Compte vendeur créé ! Connexion en cours…');
        try {
          const loginR = await apiPost(API.auth, { action: 'login', email, password, role: 'vendeur' });
          if (loginR.success) {
            setTimeout(() => { window.location.href = 'vendor-dashboard.html'; }, 1000);
          } else {
            setTimeout(() => { window.location.href = 'login.html?role=vendeur'; }, 1500);
          }
        } catch {
          setTimeout(() => { window.location.href = 'login.html?role=vendeur'; }, 1500);
        }
      } else {
        Toast.success('Compte créé ! Redirection vers la connexion…');
        setTimeout(() => { window.location.href = 'login.html'; }, 1500);
      }
    } catch (err) {
      showAlert(err.message);
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-user-plus"></i> <span id="submitBtnText">Créer mon compte</span>'; }
    }
  });
}

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', () => {
  initRoleTabs();
  initPasswordToggle();
  initPasswordStrength();
  initLoginForm();
  initRegisterForm();

  // Pré-remplir rôle login depuis URL param
  const params = new URLSearchParams(window.location.search);
  const roleParam = params.get('role');
  if (roleParam) {
    const tab = document.querySelector(`.role-tab[data-role="${roleParam}"]`);
    if (tab) {
      tab.click();
    }
  }
});
