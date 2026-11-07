<h1>Vérifiez vos e-mails</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<p>Si l'adresse est connue, un lien de connexion vient d'être envoyé. Vous pouvez aussi saisir le code à 6 chiffres reçu dans le même e-mail.</p>
<form method="post" action="<?= e(url('/connexion/code')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= humain_champs() ?>
  <?php if ($email === ''): ?><?= c_champ('email', 'Votre adresse e-mail', ['type' => 'email', 'requis' => true]) ?><?php endif ?>
  <?= c_champ('code', 'Code à 6 chiffres', ['requis' => true, 'max' => 6]) ?>
  <button type="submit" class="bouton bouton-principal">Valider</button>
</form>
