<section class="acces">
  <h1>Confirmez votre identité</h1>
  <?php if ($deja_connecte): ?>
  <p><strong>Vous êtes bien connecté.</strong> Cette action (gestion, clé d'API en écriture, sécurité du compte) demande une seconde preuve, différente de la première : choisissez-en une ci-dessous. Elle reste valable jusqu'à la déconnexion.</p>
  <?php else: ?>
  <p>Une seconde vérification protège les comptes qui gèrent l'association.</p>
  <?php endif ?>
  <?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>

  <?php if ($cle): ?>
  <form method="post" action="<?= e(url('/connexion/double-authentification/cle')) ?>" id="connexion-cle" data-options="<?= e(url('/connexion/double-authentification/cle')) ?>">
    <?= csrf_field() ?><input type="hidden" name="reponse">
    <p><button type="submit" class="bouton bouton-principal">Utiliser ma clé de sécurité</button></p>
    <p class="petit">Clé USB, empreinte, visage ou téléphone, selon ce que vous avez enregistré.</p>
  </form>
  <?php endif ?>

  <?php if ($totp): ?>
  <details<?= !$cle ? ' open' : '' ?>><summary>Code de l'application</summary>
  <form method="post" action="<?= e(url('/connexion/double-authentification')) ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('code', "Code affiché par l'application", ['requis' => true, 'max' => 6]) ?>
    <button type="submit" class="bouton bouton-secondaire">Valider</button>
  </form>
  </details>
  <?php endif ?>

  <?php if ($email): ?>
  <details<?= !$cle && !$totp || $code_envoye ? ' open' : '' ?>><summary><?= $cle ? "Je n'ai pas ma clé : code par e-mail" : 'Code par e-mail' ?></summary>
  <?php if ($code_envoye): ?>
  <form method="post" action="<?= e(url('/connexion/double-authentification/code')) ?>" class="formulaire">
    <?= csrf_field() ?>
    <div class="champ"><label for="champ-code-email">Code reçu par e-mail (6 chiffres, valable 10 minutes)</label>
      <input type="text" id="champ-code-email" name="code" class="code-acces" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required></div>
    <button type="submit" class="bouton bouton-principal">Valider</button>
  </form>
  <?php else: ?>
  <form method="post" action="<?= e(url('/connexion/double-authentification/envoyer-code')) ?>">
    <?= csrf_field() ?><p><button type="submit" class="bouton bouton-secondaire">M'envoyer un code</button></p>
  </form>
  <?php endif ?>
  </details>
  <?php endif ?>

  <?php if ($mdp): ?>
  <details<?= !$cle && !$totp && !$email ? ' open' : '' ?>><summary>Mon mot de passe</summary>
  <form method="post" action="<?= e(url('/connexion/double-authentification/mot-de-passe')) ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('mot_de_passe', 'Mot de passe', ['type' => 'password', 'requis' => true]) ?>
    <button type="submit" class="bouton bouton-secondaire">Valider</button>
  </form>
  </details>
  <?php endif ?>

  <?php if (!$cle && !$totp && !$email && !$mdp): ?>
  <p>Aucun second moyen disponible : définissez un mot de passe ou ajoutez une clé de sécurité depuis votre compte.</p>
  <?php endif ?>
</section>
<script src="<?= e(url('/assets/js/cles.js')) ?>?v=<?= e(BOUSSOLE_VERSION) ?>" defer></script>
