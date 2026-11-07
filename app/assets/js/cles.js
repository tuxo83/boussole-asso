// Clés de sécurité (WebAuthn) : connexion et enregistrement. Les données binaires circulent en base64url.
'use strict';

(function () {
  const b64 = {
    decode: function (s) { return Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/')), function (c) { return c.charCodeAt(0); }).buffer; },
    encode: function (buf) { return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); },
  };
  const charger = function (url) { return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
  const pasPrisEnCharge = function () { window.alert("Ce navigateur ne prend pas en charge les clés de sécurité : utilisez un autre moyen."); };

  // Connexion
  const connexion = document.getElementById('connexion-cle');
  if (connexion) {
    connexion.addEventListener('submit', function (event) {
      if (connexion.reponse.value) { return; }
      event.preventDefault();
      if (!window.PublicKeyCredential) { pasPrisEnCharge(); return; }
      charger(connexion.dataset.options).then(function (o) {
        o.challenge = b64.decode(o.challenge);
        o.allowCredentials = o.allowCredentials.map(function (c) { return { type: c.type, id: b64.decode(c.id) }; });
        return navigator.credentials.get({ publicKey: o });
      }).then(function (cred) {
        connexion.reponse.value = JSON.stringify({
          id: cred.id,
          clientDataJSON: b64.encode(cred.response.clientDataJSON),
          authenticatorData: b64.encode(cred.response.authenticatorData),
          signature: b64.encode(cred.response.signature),
        });
        HTMLFormElement.prototype.submit.call(connexion);
      }).catch(function () { window.alert('La clé n\'a pas répondu. Réessayez, ou utilisez le code par e-mail.'); });
    });
  }

  // Enregistrement d'une nouvelle clé
  const ajout = document.getElementById('ajout-cle');
  if (ajout) {
    ajout.addEventListener('submit', function (event) {
      if (ajout.reponse.value) { return; }
      event.preventDefault();
      if (!window.PublicKeyCredential) { pasPrisEnCharge(); return; }
      charger(ajout.dataset.options).then(function (o) {
        o.challenge = b64.decode(o.challenge);
        o.user.id = b64.decode(o.user.id);
        o.excludeCredentials = o.excludeCredentials.map(function (c) { return { type: c.type, id: b64.decode(c.id) }; });
        return navigator.credentials.create({ publicKey: o });
      }).then(function (cred) {
        ajout.reponse.value = JSON.stringify({
          clientDataJSON: b64.encode(cred.response.clientDataJSON),
          attestationObject: b64.encode(cred.response.attestationObject),
        });
        HTMLFormElement.prototype.submit.call(ajout);
      }).catch(function () { window.alert("La clé n'a pas été enregistrée (annulée, ou déjà enregistrée)."); });
    });
  }
})();

// QR code de l'application d'authentification, calculé dans le navigateur (aucun service extérieur)
document.querySelectorAll('[data-qrcode]').forEach(function (box) {
  if (typeof qrcode === 'undefined') { return; }
  const qr = qrcode(0, 'M');
  qr.addData(box.dataset.qrcode);
  qr.make();
  box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
});
