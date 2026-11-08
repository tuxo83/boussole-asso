// Boussole : JavaScript natif, uniquement en amélioration (les pages fonctionnent sans)
'use strict';

// Captcha invisible : calcule la preuve de travail pendant que la personne remplit le formulaire
(function () {
  async function sha256(bytes) {
    return new Uint8Array(await crypto.subtle.digest('SHA-256', bytes));
  }
  function leadingZeroBits(hash) {
    let bits = 0;
    for (const byte of hash) {
      if (byte === 0) { bits += 8; continue; }
      return bits + Math.clz32(byte) - 24;
    }
    return bits;
  }
  async function solve(salt, difficulty) {
    const encoder = new TextEncoder();
    for (let nonce = 0; nonce < 1e9; nonce++) {
      if (leadingZeroBits(await sha256(encoder.encode(salt + nonce))) >= difficulty) {
        return String(nonce);
      }
    }
    return '';
  }
  document.querySelectorAll('input[data-defi]').forEach(function (input) {
    const form = input.form;
    const proof = form.querySelector('input[name="_h_preuve"]');
    const [salt, difficulty] = input.value.split('.');
    if (!window.crypto || !crypto.subtle) { return; }
    const ready = solve(salt, parseInt(difficulty, 10)).then(function (value) { proof.value = value; });
    form.addEventListener('submit', function (event) {
      if (proof.value !== '') { return; }
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      if (button) { button.disabled = true; }
      ready.then(function () { form.submit(); });
    });
  });
})();

// Sélecteur de couleur : affiche la valeur choisie à côté de la pastille
document.querySelectorAll('.couleur input[type="color"]').forEach(function (input) {
  input.addEventListener('input', function () { input.nextElementSibling.textContent = input.value; });
});

// Actions irréversibles : confirmation (data-confirmer) ; sans JavaScript, l'action passe directement
document.querySelectorAll('[data-confirmer]').forEach(function (button) {
  button.addEventListener('click', function (event) {
    if (!window.confirm(button.getAttribute('data-confirmer'))) { event.preventDefault(); }
  });
});

// Validation d'une liste d'événement : compteur de retenus, dépassement signalé (pas bloqué)
(function () {
  const form = document.getElementById('validation');
  if (!form) { return; }
  const places = parseInt(form.querySelector('[data-places]').dataset.places, 10);
  const update = function () {
    const count = form.querySelectorAll('input[name="retenus[]"]:checked').length;
    document.getElementById('compte-retenus').textContent = count;
    document.getElementById('depassement').hidden = count <= places;
  };
  form.addEventListener('change', update);
})();

// Filtre instantané d'une liste (nom, e-mail)
document.querySelectorAll('[data-filtre]').forEach(function (input) {
  const items = document.querySelectorAll(input.dataset.filtre + ' > li');
  input.addEventListener('input', function () {
    const q = input.value.trim().toLowerCase();
    items.forEach(function (li) { li.hidden = q !== '' && !li.dataset.texte.includes(q); });
  });
});

// Bouton « Copier » : copie le texte d'un bloc (configuration, clé)
document.querySelectorAll('[data-copier]').forEach(function (button) {
  button.addEventListener('click', function () {
    const text = document.getElementById(button.dataset.copier).textContent;
    const done = function () { const old = button.textContent; button.textContent = 'Copié ✓'; setTimeout(function () { button.textContent = old; }, 2000); };
    if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); } else { window.prompt('Copiez :', text); }
  });
});

// Envoi dès le choix (météo, fichier à déposer) ; le bouton de secours n'est utile que sans JavaScript
document.querySelectorAll('[data-envoi-auto]').forEach(function (champ) {
  champ.addEventListener('change', function () { if (champ.value) { champ.form.requestSubmit(); } });
});
document.querySelectorAll('[data-sans-js]').forEach(function (bouton) { bouton.hidden = true; });
// Zone de dépôt : on la met en valeur quand un fichier la survole
document.querySelectorAll('.zone-depot').forEach(function (zone) {
  ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.add('survol'); }); });
  ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function () { zone.classList.remove('survol'); }); });
});

// « Cliquer pour modifier » : un clic n'importe où sur un bloc ouvre l'éditeur à cet endroit
document.querySelectorAll('.en-edition .bloc-editable').forEach(function (bloc) {
  bloc.addEventListener('click', function (event) {
    if (event.target.closest('.bloc-crayon')) { return; }
    event.preventDefault();
    window.location.href = bloc.querySelector('.bloc-crayon').href;
  });
});

// Modifier sur place : un clic (ou Entrée) sur le texte ouvre son champ ; Échap ou « Annuler » referme.
// Sans JavaScript, les formulaires restent simplement visibles.
document.querySelectorAll('[data-en-place]').forEach(function (zone) {
  const vue = zone.querySelector('.en-place-vue');
  const form = zone.querySelector('.en-place-form');
  form.hidden = true;
  const ouvrir = function (event) {
    if (event.target.closest('a')) { return; }
    vue.hidden = true; form.hidden = false;
    const champ = form.querySelector('input:not([type=hidden]), textarea, select');
    champ.focus();
    if (champ.tagName === 'INPUT') { champ.select(); } else if (champ.tagName === 'TEXTAREA') { champ.setSelectionRange(champ.value.length, champ.value.length); }
  };
  const fermer = function () { form.hidden = true; vue.hidden = false; vue.focus(); };
  vue.addEventListener('click', ouvrir);
  vue.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); ouvrir(event); } });
  form.querySelector('[data-en-place-annuler]').addEventListener('click', fermer);
  form.addEventListener('keydown', function (event) { if (event.key === 'Escape') { fermer(); } });
});

// Agenda : deux vues (frise par défaut, liste) ; le choix est retenu par ce navigateur
document.querySelectorAll('[data-vues]').forEach(function (bloc) {
  var choix = bloc.querySelector('.agenda-choix');
  if (!choix) return;
  choix.hidden = false;
  function montrer(vue) {
    bloc.querySelectorAll('[data-vue-contenu]').forEach(function (c) { c.hidden = c.dataset.vueContenu !== vue; });
    choix.querySelectorAll('[data-vue]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.vue === vue ? 'true' : 'false'); });
  }
  var garde = null;
  try { garde = localStorage.getItem('agenda-vue'); } catch (e) {}
  if (garde === 'liste' || garde === 'frise') montrer(garde);
  choix.addEventListener('click', function (e) {
    var b = e.target.closest('[data-vue]');
    if (!b) return;
    montrer(b.dataset.vue);
    try { localStorage.setItem('agenda-vue', b.dataset.vue); } catch (e2) {}
  });
});

// Gestion d'un événement : onglets Inscrits / Attente / Pointage (sans JavaScript, tout reste visible l'un sous l'autre)
document.querySelectorAll('[data-onglets]').forEach(function (bloc) {
  var barre = bloc.querySelector('[role="tablist"]');
  if (!barre) return;
  barre.hidden = false;
  bloc.classList.add('avec-onglets');
  var boutons = Array.prototype.slice.call(barre.querySelectorAll('[role="tab"]'));
  function montrer(id, focus) {
    boutons.forEach(function (b) {
      var actif = b.dataset.onglet === id;
      b.setAttribute('aria-selected', actif ? 'true' : 'false');
      b.tabIndex = actif ? 0 : -1;
      if (actif && focus) b.focus();
      var p = document.getElementById(b.dataset.onglet);
      if (p) p.hidden = !actif;
    });
  }
  var depart = boutons.filter(function (b) { return b.getAttribute('aria-selected') === 'true'; })[0] || boutons[0];
  var ancre = location.hash.slice(1);
  montrer(boutons.some(function (b) { return b.dataset.onglet === ancre; }) ? ancre : depart.dataset.onglet);
  barre.addEventListener('click', function (e) { var b = e.target.closest('[role="tab"]'); if (b) montrer(b.dataset.onglet); });
  barre.addEventListener('keydown', function (e) {
    var i = boutons.indexOf(document.activeElement);
    if (i < 0 || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
    e.preventDefault();
    montrer(boutons[(i + (e.key === 'ArrowRight' ? 1 : boutons.length - 1)) % boutons.length].dataset.onglet, true);
  });
  bloc.querySelectorAll('[data-vers]').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); montrer(a.dataset.vers); barre.scrollIntoView({ block: 'start', behavior: 'smooth' }); });
  });
});
// « Réglages » : ouvre le volet des réglages de l'événement
document.querySelectorAll('[data-ouvrir]').forEach(function (a) {
  a.addEventListener('click', function () { var d = document.getElementById(a.dataset.ouvrir); if (d) d.open = true; });
});

// Pointage : un toucher, sans recharger la page ; liste « À pointer » qui se vide, recherche par nom
document.querySelectorAll('.pointage-liste').forEach(function (liste) {
  var panneau = liste.closest('.panneau-onglet') || document;
  var filtres = panneau.querySelector('.filtres-pointage');
  var filtre = 'a-pointer';
  var cherche = '';
  if (filtres) filtres.hidden = false;
  function appliquer() {
    liste.querySelectorAll('.personne').forEach(function (li) {
      var ok = li.dataset.present === '1';
      var visible = (filtre === 'tous' || (filtre === 'presents' ? ok : !ok)) && (cherche === '' || li.dataset.nom.indexOf(cherche) !== -1);
      li.hidden = !visible;
    });
  }
  if (filtres) {
    filtres.addEventListener('click', function (e) {
      var b = e.target.closest('[data-filtre]');
      if (!b) return;
      filtre = b.dataset.filtre;
      filtres.querySelectorAll('[data-filtre]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      appliquer();
    });
    var champ = filtres.querySelector('[data-chercher]');
    if (champ) champ.addEventListener('input', function () { cherche = champ.value.trim().toLowerCase(); appliquer(); });
  }
  liste.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-pointer]');
    if (!form || !window.fetch) return;
    e.preventDefault();
    var li = form.closest('.personne');
    var bouton = form.querySelector('button');
    bouton.disabled = true;
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
      .then(function (d) {
        var ok = d.present === 1;
        li.dataset.present = ok ? '1' : '0';
        form.querySelector('[name="present"]').value = ok ? '' : '1';
        bouton.setAttribute('aria-pressed', ok ? 'true' : 'false');
        bouton.textContent = ok ? '✓ Présent' : 'Présent';
        document.querySelectorAll('[data-presents]').forEach(function (n) { n.textContent = d.presents; });
        var total = parseInt(liste.dataset.total, 10) || 1;
        var barre = panneau.querySelector('[data-barre]');
        if (barre) barre.style.width = Math.round(100 * d.presents / total) + '%';
        setTimeout(appliquer, ok ? 400 : 0);
      })
      .catch(function () { form.submit(); })
      .finally(function () { bouton.disabled = false; });
  });
  appliquer();
});

// Menu de compte (initiales) : sur ordinateur, il s'ouvre au survol de la souris (le clic marche toujours, et au doigt)
if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
  document.querySelectorAll('.compte-menu').forEach(function (menu) {
    var minuterie = null;
    menu.addEventListener('mouseenter', function () { clearTimeout(minuterie); menu.open = true; });
    menu.addEventListener('mouseleave', function () { minuterie = setTimeout(function () { menu.open = false; }, 250); });
  });
}

// Petites fenêtres (aide « ? », météo, menu de compte, ajouter à l'agenda, menu burger) : un clic ailleurs ou Échap les referme
(function () {
  var selecteur = 'details.aide[open], details.meteo-menu[open], details.compte-menu[open], details.ajout-agenda[open], details.menu-burger[open]';
  document.addEventListener('click', function (e) {
    document.querySelectorAll(selecteur).forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll(selecteur).forEach(function (d) { d.open = false; var s = d.querySelector('summary'); if (s && d.contains(document.activeElement)) s.focus(); });
  });
})();

// Équipe d'un espace : ajouter ou modifier une personne dans une petite fenêtre (crayon sur chaque personne)
(function () {
  var dialogue = document.getElementById('dialogue-equipe');
  if (!dialogue || typeof dialogue.showModal !== 'function') return;
  var titre = dialogue.querySelector('h3');
  var email = dialogue.querySelector('[name="email"]');
  var champEmail = dialogue.querySelector('[data-champ-email]');
  var niveau = dialogue.querySelector('[name="niveau"]');
  var groupe = dialogue.querySelector('[name="groupe"]');
  var role = dialogue.querySelector('[name="role"]');
  var retirer = dialogue.querySelector('[data-retirer]');
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-dialogue-equipe]');
    if (!b) return;
    e.preventDefault();
    var modifier = !!b.dataset.email;
    titre.textContent = modifier ? 'Modifier : ' + b.dataset.nom : titre.dataset.titreAjout;
    email.value = b.dataset.email || '';
    email.readOnly = modifier;
    champEmail.hidden = modifier;
    niveau.value = b.dataset.niveau || 'membre';
    groupe.value = b.dataset.groupe || '';
    role.value = b.dataset.role || '';
    if (retirer) {
      retirer.hidden = !modifier;
      if (modifier) retirer.querySelector('select').value = b.dataset.id;
    }
    dialogue.showModal();
    (modifier ? groupe : email).focus();
  });
  dialogue.querySelector('[data-fermer-dialogue]').addEventListener('click', function () { dialogue.close(); });
  dialogue.addEventListener('click', function (e) { if (e.target === dialogue) dialogue.close(); });
})();

// Fenêtres « ? » : toujours dans l'écran (recalées à gauche si elles débordent à droite)
document.addEventListener('toggle', function (e) {
  var d = e.target;
  if (!d.matches || !d.matches('details.aide') || !d.open) return;
  var c = d.querySelector('.aide-contenu');
  if (!c) return;
  c.style.left = ''; c.style.right = '';
  var r = c.getBoundingClientRect();
  var marge = 8;
  if (r.right > window.innerWidth - marge) c.style.left = (parseFloat(getComputedStyle(c).left) - (r.right - window.innerWidth + marge)) + 'px';
  r = c.getBoundingClientRect();
  if (r.left < marge) c.style.left = (parseFloat(getComputedStyle(c).left) + (marge - r.left)) + 'px';
}, true);

// Création : les champs de date et de lieu ne s'affichent que pour un événement
document.querySelectorAll('[data-creation]').forEach(function (form) {
  var bloc = form.querySelector('[data-si-evenement]');
  var types = form.querySelectorAll('input[name="type"][type="radio"]');
  if (!bloc || !types.length) return;
  function maj() { var t = form.querySelector('input[name="type"]:checked'); bloc.hidden = !(t && t.value === 'evenement'); }
  types.forEach(function (r) { r.addEventListener('change', maj); });
  maj();
});

// Réglages d'un événement : les places et le paiement n'apparaissent que si on les a choisis
document.querySelectorAll('[data-reglages-evenement]').forEach(function (form) {
  function maj() {
    var mode = form.querySelector('input[name="mode"]:checked');
    var reglement = form.querySelector('input[name="reglement"]:checked');
    var limite = mode && mode.value !== 'ouvert';
    form.querySelectorAll('[data-si-limite]').forEach(function (b) { b.hidden = !limite; });
    form.querySelectorAll('[data-si-paiement]').forEach(function (b) { b.hidden = !(limite && reglement && reglement.value === 'paiement'); });
  }
  form.addEventListener('change', maj);
  maj();
});
