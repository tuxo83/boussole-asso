<h1>Connexion</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<form method="post" action="<?= e(url('/connexion')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('email', 'Votre adresse e-mail', ['type' => 'email', 'valeur' => $email, 'requis' => true]) ?>
  <?= c_champ('mot_de_passe', 'Mot de passe (facultatif)', ['type' => 'password', 'aide' => 'Sans mot de passe, vous recevez un lien de connexion par e-mail.']) ?>
  <button type="submit" class="bouton bouton-principal">Se connecter</button>
</form>
