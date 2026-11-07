<section class="bandeau">
  <h1><?= e(setting('nom_association', '')) ?></h1>
  <?php if (setting('slogan')): ?><p class="slogan"><?= e(setting('slogan')) ?></p><?php endif ?>
  <p><?= c_bouton(current_member() !== null ? 'Mon espace' : 'Espace membres', current_member() !== null ? '/compte' : '/connexion') ?></p>
</section>
<?php if ($page): ?>
<div class="contenu"><?= $page['blocs'] !== null ? blocs_rendre($page['blocs']) : c_texte($page['contenu']) ?></div>
<?php else: ?>
<?= c_texte($presentation) ?>
<?php endif ?>
