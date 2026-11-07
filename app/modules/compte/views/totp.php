<h1>Activer la double authentification</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<ol>
  <li>Dans votre application d'authentification (FreeOTP, Aegis, Google Authenticator…), ajoutez un compte avec cette clé :
    <p><code class="cle"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
    <p class="discret">ou ce lien, si l'application le permet : <code><?= e(totp_uri($secret, $membre['email'])) ?></code></p>
  </li>
  <li>Saisissez le code à 6 chiffres qu'elle affiche.</li>
</ol>
<form method="post" action="<?= e(url('/compte/double-authentification')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('code', 'Code à 6 chiffres', ['requis' => true, 'max' => 6]) ?>
  <button type="submit" class="bouton bouton-principal">Activer</button>
</form>
