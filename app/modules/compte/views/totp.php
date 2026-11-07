<h1>Sécurité du compte</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<p>Pour la gestion de l'association, chaque connexion demande deux preuves : par exemple votre mot de passe puis votre clé, ou un code reçu par e-mail.</p>

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
  <li>Dans votre application d'authentification (FreeOTP, Aegis, Google Authenticator…), ajoutez un compte avec cette clé :
    <p><code class="cle"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
    <p class="discret">ou ce lien, si l'application le permet : <code><?= e(totp_uri($secret, $membre['email'])) ?></code></p>
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
<script src="<?= e(url('/assets/js/cles.js')) ?>?v=<?= e(BOUSSOLE_VERSION) ?>" defer></script>
