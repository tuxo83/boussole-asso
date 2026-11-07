<h1>Espaces</h1>
<?php if (is_admin($membre)): ?><p><?= c_bouton('Nouvel espace', '/espaces/nouveau') ?></p><?php endif ?>
<?php if (!$espaces): ?><p>Aucun espace pour l'instant.</p><?php endif ?>
<div class="grille">
  <?php foreach ($espaces as $espace):
      $alertes = espace_alertes($espace);
      $n1 = db_one('SELECT m.* FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ? AND em.niveau = "n1"', [$espace['id']]);
      $prochaine = db_one('SELECT * FROM jalon WHERE espace_id = ? AND fait = 0 AND date_jalon >= CURDATE() ORDER BY date_jalon LIMIT 1', [$espace['id']]); ?>
  <?= c_carte($espace['titre'],
      '<p class="discret">' . e(ESPACE_TYPES[$espace['type']]) . '</p>'
      . '<p>' . c_meteo($espace['meteo']) . '</p>'
      . ($n1 ? '<p class="petit">N°1 : ' . e(membre_nom($n1)) . '</p>' : '')
      . ($prochaine ? '<p class="petit">Prochaine date : ' . e(date('d/m/Y', strtotime($prochaine['date_jalon']))) . ' · ' . e($prochaine['libelle']) . '</p>' : '')
      . ($alertes ? '<p class="alertes-mini">' . c_icone('attention') . e(count($alertes) === 1 ? $alertes[0] : count($alertes) . ' points à traiter') . '</p>' : ''),
      '/espace/' . $espace['slug']) ?>
  <?php endforeach ?>
</div>
