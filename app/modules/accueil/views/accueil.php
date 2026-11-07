<section class="bandeau">
  <h1><?= e(setting('nom_association', '')) ?></h1>
  <?php if (setting('slogan')): ?><p class="slogan"><?= e(setting('slogan')) ?></p><?php endif ?>
</section>
<?= c_texte($presentation) ?>
