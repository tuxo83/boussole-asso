<?php
// Frise des phases d'un événement à places limitées. Les phases ont des noms, les groupes des numéros : pas de confusion.
$cloture = $ev['cloture'];
$dernieres = evenement_debut_dernieres($ev);
$etapes = [['voeux', 'Vœux', $cloture !== null ? "jusqu'au " . evenement_date($cloture) : '', $cloture]];
if ($exige) {
    $etapes[] = ['reglement', $ev['reglement'] === 'paiement' ? 'Paiement' : 'Confirmation', (int) $ev['delai_jours'] . ' jour(s) par personne', $dernieres];
    $etapes[] = ['dernieres', 'Dernières places', $dernieres !== null ? 'dès le ' . evenement_date($dernieres) . ' : premier arrivé, premier servi' : '', $ev['debut']];
} else {
    $etapes[] = ['attribuee', 'Places attribuées', 'liste d\'attente si une place se libère', $ev['debut']];
}
$etapes[] = ['termine', 'Événement', evenement_date($ev['debut']), null];
$ordre = array_column($etapes, 0);
$courante = $phase === 'validation' ? 'voeux' : $phase;
$index = array_search($courante, $ordre, true);
$aide = c_aide('les phases', '<p>Les inscriptions se déroulent en phases :</p><ol>'
    . '<li><strong>Vœux</strong> : chacun dit qu\'il souhaite venir ; l\'heure ne compte pas.</li>'
    . ($exige ? '<li><strong>' . ($ev['reglement'] === 'paiement' ? 'Paiement' : 'Confirmation') . '</strong> : les personnes retenues ont ' . (int) $ev['delai_jours'] . ' jour(s) pour ' . ($ev['reglement'] === 'paiement' ? 'payer' : 'confirmer') . '. Sinon la place passe à la suivante de la liste d\'attente, qui a le même délai.</li>'
    . '<li><strong>Dernières places</strong> : ' . (int) $ev['dernieres_jours'] . ' jour(s) avant l\'événement, les places encore libres vont aux premières personnes de la liste d\'attente qui ' . ($ev['reglement'] === 'paiement' ? 'paient' : 'se manifestent') . '.</li>' : '')
    . '<li><strong>Événement</strong>.</li></ol><p>Rappels automatiques par e-mail : la veille de chaque échéance.</p>');
?>
<div class="frise-bloc">
  <p class="frise-titre">Déroulement <?= $aide ?></p>
  <ol class="frise">
    <?php foreach ($etapes as $i => [$cle, $nom, $detail, $fin]): $etat = $index === false ? '' : ($i < $index ? 'passee' : ($i === $index ? 'courante' : '')); ?>
    <li class="<?= $etat ?>"<?= $etat === 'courante' ? ' aria-current="step"' : '' ?>>
      <strong><?= e($nom) ?></strong>
      <?php if ($detail !== ''): ?><span><?= e($detail) ?></span><?php endif ?>
      <?php if ($etat === 'courante' && $fin !== null && $fin > date('Y-m-d H:i:s')): ?><span class="badge"><?= e(duree_restante($fin)) ?></span><?php endif ?>
    </li>
    <?php endforeach ?>
  </ol>
</div>
