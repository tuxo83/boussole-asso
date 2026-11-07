<h1>Toutes les clés d'API</h1>
<?php if (!$cles): ?><p>Aucune clé.</p><?php endif ?>
<ul class="liste">
  <?php foreach ($cles as $cle): $active = !$cle['revoquee'] && $cle['expire_le'] >= date('Y-m-d'); ?>
  <li><strong><?= e(membre_nom($cle)) ?></strong> · <?= e($cle['libelle']) ?> <span class="discret">· <?= $cle['portee'] === 'ecriture' ? 'écriture' : 'lecture' ?> · <?= $cle['revoquee'] ? 'révoquée' : ($active ? "jusqu'au " . e(date('d/m/Y', strtotime($cle['expire_le']))) : 'expirée') ?></span>
    <?php if ($active): ?>
    <form method="post" action="<?= e(url('/compte/cles/' . (int) $cle['id'] . '/revoquer')) ?>" class="en-ligne"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Révoquer cette clé ?">révoquer</button></form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
