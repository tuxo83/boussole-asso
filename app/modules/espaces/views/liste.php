<h1>Espaces</h1>
<?php if (is_admin($membre)): ?><p><?= c_bouton('Nouvel espace', '/espaces/nouveau') ?></p><?php endif ?>
<?php if (!$espaces): ?><p>Aucun espace pour l'instant.</p><?php endif ?>
<?php foreach (ESPACE_TYPES as $type => $libelle_type):
    $du_type = array_filter($espaces, fn($e) => $e['type'] === $type);
    if (!$du_type) { continue; } ?>
<h2><?= e($libelle_type) ?></h2>
<div class="grille">
  <?php foreach ($du_type as $espace): $alertes = espace_alertes($espace); ?>
  <?= c_carte($espace['titre'],
      '<p>' . c_meteo($espace['meteo']) . '</p>'
      . ($alertes ? '<p class="alertes-mini">⚠ ' . e(count($alertes) === 1 ? $alertes[0] : count($alertes) . ' points à traiter') . '</p>' : ''),
      '/espace/' . $espace['slug']) ?>
  <?php endforeach ?>
</div>
<?php endforeach ?>
