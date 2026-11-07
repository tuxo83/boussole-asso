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
