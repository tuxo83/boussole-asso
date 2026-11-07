<h1>Double authentification</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<form method="post" action="<?= e(url('/connexion/double-authentification')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('code', "Code affiché par votre application d'authentification", ['requis' => true, 'max' => 6]) ?>
  <button type="submit" class="bouton bouton-principal">Valider</button>
</form>
