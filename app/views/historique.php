<?php // Lignes de l'historique d'un compte (Mon compte, fiche d'un membre) ?>
<?php if (!$historique): ?><p class="discret">Rien pour l'instant.</p><?php endif ?>
<ul class="liste journal historique">
  <?php foreach ($historique as $ligne): $par = $ligne['membre_id'] === null ? 'quelqu\'un' : ((int) $ligne['membre_id'] === (int) $cible_id ? null : membre_nom($ligne)); ?>
  <li class="<?= str_starts_with($ligne['action'], 'Échec') ? 'echec' : '' ?>"><time datetime="<?= e($ligne['le']) ?>"><?= e(date('d/m/Y H:i', strtotime($ligne['le']))) ?></time> · <?= e($ligne['action']) ?><?= $par !== null ? ' <span class="discret">· par ' . e($par) . '</span>' : '' ?><?= $ligne['via'] === 'ia' ? ' <span class="badge">via IA</span>' : '' ?></li>
  <?php endforeach ?>
</ul>
