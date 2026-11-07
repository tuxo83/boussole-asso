<section class="acces">
  <h1>Site en construction</h1>
  <p>Ce site est en cours de préparation. Saisissez le code d'accès qui vous a été transmis.</p>
  <?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
  <form method="post" action="<?= e(url('/acces')) ?>" class="formulaire">
    <?= csrf_field() ?>
    <input type="hidden" name="suite" value="<?= e($suite) ?>">
    <div class="champ">
      <label for="champ-code">Code d'accès (6 chiffres)</label>
      <input type="text" id="champ-code" name="code" class="code-acces" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus>
    </div>
    <button type="submit" class="bouton bouton-principal">Entrer</button>
  </form>
  <p class="petit">Membre de l'association ? <a href="<?= e(url('/connexion')) ?>">Se connecter</a></p>
</section>
