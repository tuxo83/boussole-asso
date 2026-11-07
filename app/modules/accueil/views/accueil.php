<?php $image = (string) setting('bandeau_image', ''); $adhesion = (string) setting('lien_adhesion', ''); ?>
<section class="bandeau<?= $image !== '' ? ' bandeau-photo' : '' ?>"<?= $image !== '' ? ' style="--bandeau: url(\'' . e(url('/' . $image)) . '\')"' : '' ?>>
  <h1><?= e(setting('nom_association', '')) ?></h1>
  <?php if (setting('slogan')): ?><p class="slogan"><?= e(setting('slogan')) ?></p><?php endif ?>
  <p class="en-ligne">
    <?php if ($adhesion !== '' && current_member() === null): ?><?= c_bouton('Adhérer', $adhesion) ?><?php endif ?>
    <?= c_bouton(current_member() !== null ? 'Mon espace' : 'Espace membres', current_member() !== null ? '/compte' : '/connexion', $adhesion !== '' && current_member() === null ? 'secondaire' : 'principal') ?>
  </p>
</section>
<?php if ($page): ?>
<div class="contenu contenu-accueil"><?= $page['blocs'] !== null ? blocs_rendre($page['blocs']) : c_texte($page['contenu']) ?></div>
<?php else: ?>
<?= c_texte($presentation) ?>
<?php endif ?>
