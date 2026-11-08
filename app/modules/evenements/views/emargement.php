<div class="emargement">
  <p class="emargement-retour"><a href="<?= e(url('/espace/' . $espace['slug'])) ?>">← Retour à l'événement</a> · <button type="button" class="lien-bouton" onclick="window.print()">Imprimer</button></p>
  <h1><?= e($espace['titre']) ?></h1>
  <p><?= e(evenement_horaire($ev)) ?><?= trim((string) $ev['lieu']) !== '' ? ' · ' . e($ev['lieu']) : '' ?></p>
  <table class="emargement-table">
    <thead><tr><th>Nom</th><th>Présent</th><th>Signature</th></tr></thead>
    <tbody>
      <?php foreach ($inscrits as $i): ?><tr><td><?= e(membre_nom_affiche($i)) ?></td><td class="case-vide"></td><td></td></tr><?php endforeach ?>
      <?php for ($k = 0; $k < 5; $k++): ?><tr><td>&nbsp;</td><td class="case-vide"></td><td></td></tr><?php endfor ?>
    </tbody>
  </table>
  <p class="petit"><?= count($inscrits) ?> inscrit(s). Document à détruire après l'événement.</p>
</div>
