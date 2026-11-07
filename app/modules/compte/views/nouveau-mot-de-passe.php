<h1>Choisissez votre mot de passe</h1>
<p>Vous vous êtes connecté avec un mot de passe provisoire. Choisissez-en un à vous pour continuer.</p>
<?php if ($erreur): ?><div class="alerte alerte-erreur" role="alert"><?= e($erreur) ?></div><?php endif ?>
<form method="post" action="<?= e(url('/compte/nouveau-mot-de-passe')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('mot_de_passe', 'Nouveau mot de passe', ['type' => 'password', 'requis' => true, 'aide' => 'Au moins ' . MOT_DE_PASSE_MIN . ' caractères. Une phrase courte est facile à retenir et solide.']) ?>
  <?= c_champ('confirmation', 'Confirmation', ['type' => 'password', 'requis' => true]) ?>
  <button type="submit" class="bouton bouton-principal">Enregistrer et continuer</button>
</form>
<form method="post" action="<?= e(url('/deconnexion')) ?>" class="separe"><?= csrf_field() ?><button type="submit" class="lien-bouton">Se déconnecter</button></form>
