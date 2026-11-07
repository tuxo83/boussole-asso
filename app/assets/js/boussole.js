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
