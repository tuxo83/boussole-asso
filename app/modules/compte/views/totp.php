<h1>Sécurité du compte</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<p>Deux preuves au lieu d'une : par exemple votre mot de passe puis votre clé, ou un code reçu par e-mail. Toujours exigées pour la gestion de l'association.</p>

<section class="mfa-moment">
  <h2>Quand la demander ?</h2>
  <form method="post" action="<?= e(url('/compte/double-authentification/moment')) ?>">
    <?= csrf_field() ?>
    <fieldset class="choix-visibilite"><legend class="visuellement-cache">Quand demander la double authentification</legend>
      <label class="choix"><input type="radio" name="mfa_connexion" value="0"<?= empty($membre['mfa_connexion']) ? ' checked' : '' ?>><span><strong>Seulement pour la gestion</strong><small>On se connecte normalement pour consulter ; la deuxième preuve est demandée en entrant dans l'administration, une fois par session.</small></span></label>
      <label class="choix"><input type="radio" name="mfa_connexion" value="1"<?= !empty($membre['mfa_connexion']) ? ' checked' : '' ?>><span><strong>À chaque connexion</strong><small>Le plus sûr : la deuxième preuve est demandée dès la connexion (si vous avez une clé ou une application).</small></span></label>
    </fieldset>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
  <p class="petit">Dans les deux cas, changer de mot de passe ou créer une clé d'API redemande une preuve.</p>
</section>

<section>
  <h2>Clés de sécurité (recommandé)</h2>
  <p class="petit">Clé USB (YubiKey…), empreinte ou visage (Windows Hello, Touch ID), ou votre téléphone. Aucune application à installer.</p>
  <?php if ($cles): ?>
  <ul class="liste">
    <?php foreach ($cles as $c): ?>
    <li class="candidat"><span class="qui"><strong><?= e($c['libelle']) ?></strong></span>
      <span class="en-ligne"><span class="discret">ajoutée le <?= e(date('d/m/Y', strtotime($c['cree_le']))) ?><?= $c['derniere_utilisation'] ? ' · utilisée le ' . e(date('d/m/Y', strtotime($c['derniere_utilisation']))) : '' ?></span>
      <form method="post" action="<?= e(url('/compte/cles-securite/' . (int) $c['id'] . '/supprimer')) ?>"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Retirer la clé « <?= e($c['libelle']) ?> » ?">retirer</button></form></span>
    </li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>
  <form method="post" action="<?= e(url('/compte/cles-securite')) ?>" id="ajout-cle" data-options="<?= e(url('/compte/cles-securite/options')) ?>" class="formulaire">
    <?= csrf_field() ?><input type="hidden" name="reponse">
    <?= c_champ('libelle', 'Nom de la clé', ['valeur' => '', 'max' => 100, 'aide' => 'Ex. « YubiKey bleue », « ordinateur du bureau ».']) ?>
    <button type="submit" class="bouton bouton-principal">Ajouter une clé</button>
  </form>
</section>

<section class="separe">
  <h2>Code par e-mail</h2>
  <p class="petit">Toujours possible en secours, quand vous vous connectez avec votre mot de passe : un code à 6 chiffres vous est envoyé.</p>
</section>

<details class="separe"><summary>Application d'authentification (facultatif)</summary>
<?php if ((int) $membre['totp_actif']): ?>
  <p>Active.</p>
<?php else: ?>
<ol>
  <li>Dans votre application d'authentification (FreeOTP, Aegis, Google Authenticator…), scannez ce QR code :
    <div class="qrcode" data-qrcode="<?= e(totp_uri($secret, $membre['email'])) ?>" role="img" aria-label="QR code à scanner avec l'application"></div>
    <p class="discret">ou saisissez cette clé : <code class="cle"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
  </li>
  <li>Saisissez le code à 6 chiffres qu'elle affiche.</li>
</ol>
<form method="post" action="<?= e(url('/compte/double-authentification')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('code', 'Code à 6 chiffres', ['requis' => true, 'max' => 6]) ?>
  <button type="submit" class="bouton bouton-secondaire">Activer</button>
</form>
<?php endif ?>
</details>
<script src="<?= e(asset('/assets/vendor/qrcode/qrcode.js')) ?>" defer></script>
<script src="<?= e(asset('/assets/js/cles.js')) ?>" defer></script>
