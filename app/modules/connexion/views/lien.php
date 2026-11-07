<h1>Connexion</h1>
<form method="post" action="<?= e(url('/connexion/lien/' . $jeton)) ?>" class="formulaire">
  <?= csrf_field() ?>
  <button type="submit" class="bouton bouton-principal">Me connecter</button>
</form>
