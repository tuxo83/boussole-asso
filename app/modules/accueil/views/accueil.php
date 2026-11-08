<?php $image = (string) setting('bandeau_image', ''); $adhesion = (string) setting('lien_adhesion', ''); ?>
<section class="bandeau<?= $image !== '' ? ' bandeau-photo' : '' ?>"<?= $image !== '' ? ' style="--bandeau: url(\'' . e(url('/' . $image)) . '\')"' : '' ?>>
  <h1><?= e(setting('nom_association', '')) ?></h1>
  <?php if (setting('slogan')): ?><p class="slogan"><?= e(setting('slogan')) ?></p><?php endif ?>
  <p class="en-ligne">
    <?php if ($adhesion !== '' && current_member() === null): ?><?= c_bouton('Adhérer', $adhesion) ?><?php endif ?>
    <?= c_bouton(current_member() !== null ? 'Mon espace' : 'Espace membres', current_member() !== null ? '/compte' : '/connexion', $adhesion !== '' && current_member() === null ? 'secondaire' : 'principal') ?>
  </p>
  <?php $legende = trim((string) setting('bandeau_legende', '')); if ($image !== '' && $legende !== ''): // Ce que montre la photo, au clic : discret, sans gêner le titre ?>
  <details class="bandeau-legende"><summary aria-label="À propos de cette photo"><span aria-hidden="true">i</span></summary><p><?= e($legende) ?></p></details>
  <?php endif ?>
</section>
<?php if ($page): $peut_publier = current_member() !== null && has_right(current_member(), 'publications'); $edition = page_mode_edition(); ?>
<?php if ($peut_publier): ?><?= page_barre_edition($page, $edition) ?><?php endif ?>
<div class="contenu contenu-accueil<?= $edition ? ' en-edition' : '' ?>"><?= $page['blocs'] !== null ? blocs_rendre($page['blocs'], $edition ? url('/admin/pages/' . (int) $page['id']) : null) : c_texte($page['contenu']) ?></div>
<?php else: ?>
<?= c_texte($presentation) ?>
<?php endif ?>
