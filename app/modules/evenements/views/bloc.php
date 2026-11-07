<?php
$lien = fn(string $chemin) => e(url('/espace/' . $espace['slug'] . $chemin));
$bouton = fn(string $action, string $texte, string $style = 'principal', string $confirmer = '') => '<form method="post" action="' . $lien('/inscription') . '">' . csrf_field()
    . '<input type="hidden" name="action" value="' . $action . '"><button type="submit" class="bouton bouton-' . $style . '"' . ($confirmer !== '' ? ' data-confirmer="' . e($confirmer) . '"' : '') . '>' . e($texte) . '</button></form>';
$limite = evenement_limite($ev);
$statut = $mien['statut'] ?? null;
$commence = $ev['debut'] !== null && $ev['debut'] <= date('Y-m-d H:i:s');
$jour_j = $ev['debut'] !== null && substr($ev['debut'], 0, 10) <= date('Y-m-d');
$groupe = fn(?int $g) => $g === null ? '' : '<span class="groupe groupe-' . $g . '" title="' . e(EVENEMENT_GROUPES[$g]) . '">' . $g . '</span>';
$aide = c_aide('comment les places sont attribuées', '<p><strong>Pas de course au clic.</strong> Chacun dépose un vœu avant la clôture ; l\'heure de dépôt ne compte pas.</p>'
    . '<p>S\'il y a plus de vœux que de places, on sert dans cet ordre :</p>'
    . '<div class="tableau"><table><tbody>'
    . '<tr><td>' . $groupe(0) . '</td><td>Les invités de l\'organisateur (intervenant, partenaire…)</td></tr>'
    . '<tr><td>' . $groupe(1) . '</td><td>Ceux qui viennent moins que la moyenne, ou jamais</td></tr>'
    . '<tr><td>' . $groupe(2) . '</td><td>Ceux qui sont dans la moyenne</td></tr>'
    . '<tr><td>' . $groupe(3) . '</td><td>Les très assidus : plus du double de la moyenne</td></tr>'
    . '</tbody></table></div>'
    . '<p>On compte les événements à places limitées des 12 derniers mois où la personne avait une place (les événements ouverts à tous, comme l\'assemblée générale, ne comptent pas). Dans un même groupe, la personne venue le moins souvent passe d\'abord ; à égalité, un tirage au sort décide (toujours le même si on le refait).</p>'
    . '<p>Les autres vont en liste d\'attente, dans le même ordre. Si quelqu\'un se désiste, le suivant est prévenu par e-mail.</p>');
$exige = evenement_exige($ev);
$phase = evenement_phase($ev);
$paiement = $ev['reglement'] === 'paiement';
$verbe = $paiement ? 'payer' : 'confirmer';
$libres = $limite && $ev['etat'] === 'attribuee' ? evenement_places_libres($ev) : 0;
$bouton_payer = $paiement && $ev['lien_paiement'] ? '<a class="bouton bouton-principal" href="' . e($ev['lien_paiement']) . '" rel="noopener noreferrer" target="_blank">Payer sur HelloAsso</a>' : '';
?>
<section class="evenement" aria-labelledby="titre-inscription">
  <ul class="infos">
    <li><?= c_icone('calendrier') ?><?= e(evenement_date($ev['debut'])) ?></li>
    <?php if ($ev['lieu'] !== ''): ?><li><?= c_icone('lieu') ?><?= e($ev['lieu']) ?></li><?php endif ?>
    <li><?= c_icone('personnes') ?><?= $limite ? (int) $ev['places'] . ' places' . ($ev['etat'] === 'attribuee' ? ' · ' . $inscrits . ' inscrits' : '') : 'Places sans limite' ?></li>
    <?php if ($limite && $ev['cloture'] !== null && $ev['etat'] === 'ouvert'): ?><li><?= c_icone('horloge') ?>Vœux jusqu'au <?= e(evenement_date($ev['cloture'])) ?></li><?php endif ?>
  </ul>

  <?php if ($limite): ?><?= partial('evenements/frise', ['ev' => $ev, 'phase' => $phase, 'exige' => $exige]) ?><?php endif ?>
  <h2 id="titre-inscription">Inscription <?php if ($limite): ?><?= $aide ?><?php endif ?></h2>
  <?php if ($membre === null): ?>
  <p><?= c_bouton('Se connecter pour s\'inscrire', '/connexion') ?></p>
  <?php elseif ($statut === 'invite'): ?>
  <div class="statut-inscription"><p><strong>L'organisateur vous invite en priorité.</strong> Répondez avant la clôture.</p>
    <div class="en-ligne"><?= $bouton('confirmer', 'J\'accepte l\'invitation') ?><?= $bouton('decliner', 'Je décline', 'secondaire') ?></div></div>
  <?php elseif ($statut === 'voeu'): ?>
  <div class="statut-inscription"><p><strong>Votre vœu est enregistré<?= $mien['invite'] ? ', en invité prioritaire' : '' ?>.</strong></p>
    <p class="petit">Les places sont attribuées à la clôture ; vous recevrez la réponse par e-mail.</p>
    <div class="en-ligne"><?= $bouton('retirer', 'Retirer mon vœu', 'secondaire') ?></div></div>
  <?php elseif ($statut === 'retenu' && $exige && !inscription_reglee($mien, $ev) && $mien['echeance'] !== null): ?>
  <div class="statut-inscription statut-a-regler"><p><strong>Une place vous est réservée.</strong> Pour la garder, <?= $verbe ?> avant le <?= e(evenement_date($mien['echeance'])) ?> <span class="badge"><?= e(duree_restante($mien['echeance'])) ?></span></p>
    <p class="petit">Sans <?= $paiement ? 'paiement' : 'confirmation' ?> à cette date, la place passe à la personne suivante. Rappel automatique par e-mail la veille.<?= $paiement ? ' Utilisez la même adresse e-mail que sur Boussole.' : '' ?></p>
    <div class="en-ligne"><?= $paiement ? $bouton_payer : $bouton('venue', 'Je confirme ma venue') ?><?= $bouton('desister', 'Je ne viens pas', 'secondaire', 'Libérer votre place ? Elle ira à la personne suivante.') ?></div></div>
  <?php elseif ($statut === 'retenu'): ?>
  <div class="statut-inscription statut-retenu"><p><strong>Vous êtes inscrit.</strong><?= $exige ? ($paiement ? ' Paiement reçu.' : ' Venue confirmée.') : '' ?> À bientôt !</p>
    <?php if (!$commence): ?><div class="en-ligne"><?= $bouton('desister', 'Finalement, je ne peux pas', 'secondaire', 'Libérer votre place ? Elle ira à la personne suivante sur la liste d\'attente.') ?></div><?php endif ?></div>
  <?php elseif ($statut === 'attente' && $exige && $paiement && $mien['paye_le'] !== null && $libres === 0): ?>
  <div class="statut-inscription"><p><strong>Paiement reçu, mais les places sont toutes prises.</strong> Vous restez sur la liste d'attente ; si aucune place ne se libère, l'organisateur vous remboursera.</p></div>
  <?php elseif ($statut === 'attente' && $phase === 'dernieres' && $libres > 0): ?>
  <div class="statut-inscription statut-a-regler"><p><strong>Dernières places : il en reste <?= $libres ?>.</strong> Elles vont aux premières personnes qui <?= $paiement ? 'paient' : 'se manifestent' ?>.</p>
    <div class="en-ligne"><?= $paiement ? $bouton_payer : $bouton('prendre', 'Je prends la place') ?><?= $bouton('desister', 'Je ne suis plus intéressé', 'secondaire') ?></div></div>
  <?php elseif ($statut === 'expire'): ?>
  <div class="statut-inscription"><p><strong>Le délai pour <?= $verbe ?> est passé</strong> : votre place est allée à la personne suivante.</p>
    <?php if (!$commence): ?><div class="en-ligne"><?= $bouton('voeu', 'Me remettre sur la liste d\'attente', 'secondaire') ?></div><?php endif ?></div>
  <?php elseif ($statut === 'attente'): ?>
  <div class="statut-inscription"><p><strong>Vous êtes n°<?= (int) $mien['rang'] ?> sur la liste d'attente.</strong></p>
    <p class="petit">Si une place se libère, vous êtes prévenu par e-mail<?= $exige ? ' et vous aurez ' . (int) $ev['delai_jours'] . ' jour(s) pour ' . $verbe : ', et inscrit automatiquement' ?>.</p>
    <div class="en-ligne"><?= $bouton('desister', 'Je ne suis plus intéressé', 'secondaire') ?></div></div>
  <?php elseif ($commence): ?>
  <p>Cet événement a eu lieu.</p>
  <?php elseif ($ev['etat'] === 'ouvert' && !evenement_accepte_voeux($ev) && $ev['mode'] !== 'ouvert'): ?>
  <p>Les inscriptions sont closes.</p>
  <?php else: ?>
  <?php if ($limite && $ev['etat'] === 'ouvert'): ?><p class="petit">Pas de course au clic : l'heure de dépôt ne compte pas. Priorité à celles et ceux qui sont venus le moins souvent.</p><?php endif ?>
  <p><?= $bouton('voeu', $ev['mode'] === 'ouvert' ? 'Je m\'inscris' : ($ev['etat'] === 'ouvert' ? 'Je souhaite venir' : 'M\'inscrire sur la liste d\'attente')) ?></p>
  <?php endif ?>

  <?php if ($gere): ?>
  <?php if ($ev['etat'] === 'proposee'): $proposes = count(array_filter($liste, fn($l) => $l['statut'] === 'voeu' && $l['propose'])); ?>
  <h3>Liste proposée <?= $aide ?></h3>
  <p class="petit">Cochez ou décochez, puis validez : chacun est prévenu par e-mail, les autres vont en liste d'attente dans l'ordre.</p>
  <form method="post" action="<?= $lien('/valider') ?>" id="validation">
    <?= csrf_field() ?>
    <p role="status" class="compteur" data-places="<?= (int) $ev['places'] ?>"><span id="compte-retenus"><?= $proposes ?></span> retenus pour <?= (int) $ev['places'] ?> places<span id="depassement" class="alerte-texte"<?= $proposes > (int) $ev['places'] ? '' : ' hidden' ?>> : capacité dépassée</span></p>
    <?php foreach ($liste as $l): if ($l['statut'] !== 'voeu') { continue; } ?>
    <label class="case candidat"><span class="qui"><input type="checkbox" name="retenus[]" value="<?= (int) $l['membre_id'] ?>"<?= $l['propose'] ? ' checked' : '' ?>> <?= e(membre_nom($l)) ?></span>
      <span class="discret"><?= $l['groupe'] !== null ? $groupe((int) $l['groupe']) . ' ' . (int) $l['participations'] . ' venue(s) sur 12 mois' : 'vœu arrivé après la clôture' ?></span></label>
    <?php endforeach ?>
    <p><button type="submit" class="bouton bouton-principal">Valider la liste</button></p>
  </form>
  <?php elseif ($jour_j && $inscrits > 0): ?>
  <h3 id="pointage">Pointage <span class="discret">(facultatif, sans effet sur les priorités)</span></h3>
  <ul class="liste">
    <?php foreach ($liste as $l): if ($l['statut'] !== 'retenu') { continue; } ?>
    <li class="en-ligne candidat case"><span class="qui"><?= e(membre_nom($l)) ?></span>
      <span class="en-ligne">
      <?php foreach (['1' => 'Présent', '0' => 'Absent'] as $valeur => $texte): $actif = $l['present'] !== null && (string) $l['present'] === $valeur; ?>
      <form method="post" action="<?= $lien('/presence') ?>"><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $l['membre_id'] ?>"><input type="hidden" name="present" value="<?= $actif ? '' : $valeur ?>">
        <button type="submit" class="bouton bouton-<?= $actif ? 'principal' : 'secondaire' ?>" aria-pressed="<?= $actif ? 'true' : 'false' ?>"><?= $texte ?></button></form>
      <?php endforeach ?>
      </span></li>
    <?php endforeach ?>
  </ul>
  <?php elseif ($liste): ?>
  <h3>Inscrits et candidats <?php if ($limite): ?><?= $aide ?><?php endif ?></h3>
  <ul class="liste">
    <?php foreach ($liste as $l): ?>
    <li class="en-ligne candidat case"><span class="qui"><?= $groupe($l['groupe'] !== null ? (int) $l['groupe'] : null) ?> <?= e(membre_nom($l)) ?></span>
      <span class="en-ligne"><span class="badge"><?= e(INSCRIPTION_STATUTS[$l['statut']] . ($l['statut'] === 'attente' ? ' n°' . (int) $l['rang'] : '')) ?></span>
      <?php if ($l['paye_le'] !== null): ?><span class="badge <?= $l['statut'] === 'attente' && $ev['etat'] === 'attribuee' ? 'badge-alerte' : 'badge-ok' ?>">payé le <?= e(date('d/m', strtotime($l['paye_le']))) ?><?= $l['statut'] === 'attente' && $ev['etat'] === 'attribuee' ? ' · sans place' : '' ?></span><?php endif ?>
      <?php if ($l['statut'] === 'retenu' && $l['echeance'] !== null): ?><span class="badge">à <?= $verbe ?> avant le <?= e(date('d/m H\hi', strtotime($l['echeance']))) ?></span><?php endif ?>
      <?php if ($l['confirme_le'] !== null && $l['statut'] === 'retenu'): ?><span class="badge badge-ok">venue confirmée</span><?php endif ?>
      <?php if ($paiement && $l['paye_le'] === null && in_array($l['statut'], ['retenu', 'attente'], true)): ?>
      <form method="post" action="<?= $lien('/paye') ?>"><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $l['membre_id'] ?>"><button type="submit" class="lien-bouton" data-confirmer="Noter que <?= e(membre_nom($l)) ?> a payé ?">noter payé</button></form>
      <?php endif ?>
      <?php if ($l['statut'] === 'retenu' && !$commence): ?>
      <form method="post" action="<?= $lien('/annuler') ?>"><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $l['membre_id'] ?>"><button type="submit" class="lien-bouton" data-confirmer="Annuler l'inscription de <?= e(membre_nom($l)) ?> ? Sa place ira au suivant de la liste d'attente.">annuler</button></form>
      <?php endif ?></span></li>
    <?php endforeach ?>
  </ul>
  <?php endif ?>

  <details class="separe"<?= $ev['debut'] === null ? ' open' : '' ?>><summary>Réglages de l'événement</summary>
  <form method="post" action="<?= $lien('/evenement') ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('debut', 'Date et heure', ['type' => 'datetime-local', 'valeur' => $ev['debut'] ? date('Y-m-d\TH:i', strtotime($ev['debut'])) : '']) ?>
    <?= c_champ('lieu', 'Lieu', ['valeur' => $ev['lieu'], 'max' => 200]) ?>
    <?= c_champ('mode', 'Inscriptions', ['type' => 'select', 'valeur' => $ev['mode'], 'choix' => EVENEMENT_MODES]) ?>
    <?= c_champ('places', 'Nombre de places (places limitées)', ['type' => 'number', 'valeur' => (string) ($ev['places'] ?? '')]) ?>
    <?= c_champ('reglement', 'Après l\'attribution des places (places limitées)', ['type' => 'select', 'valeur' => $ev['reglement'], 'choix' => EVENEMENT_REGLEMENTS]) ?>
    <?= c_champ('lien_paiement', 'Lien de paiement HelloAsso (événement payant)', ['type' => 'url', 'valeur' => (string) ($ev['lien_paiement'] ?? ''), 'aide' => helloasso_actif() ? 'Les paiements sont lus tout seuls.' : 'Sans connexion HelloAsso, notez les paiements reçus dans la liste (« noter payé »).']) ?>
    <?= c_champ('delai_jours', 'Délai pour payer ou confirmer, en jours', ['type' => 'number', 'valeur' => (string) $ev['delai_jours']]) ?>
    <?= c_champ('dernieres_jours', '« Dernières places » : combien de jours avant l\'événement', ['type' => 'number', 'valeur' => (string) $ev['dernieres_jours'], 'aide' => 'À partir de ce moment, les places libres vont au premier qui paie ou confirme.']) ?>
    <?= c_champ('cloture', 'Clôture des vœux (places limitées)', ['type' => 'datetime-local', 'valeur' => $ev['cloture'] ? date('Y-m-d\TH:i', strtotime($ev['cloture'])) : '']) ?>
    <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
  </form>
  </details>
  <?php if ($ev['mode'] !== 'ouvert' && evenement_accepte_voeux($ev)): ?>
  <details><summary>Inviter quelqu'un en priorité</summary>
  <form method="post" action="<?= $lien('/inviter') ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('email', 'E-mail du membre', ['type' => 'email', 'requis' => true, 'aide' => 'Il est servi avant tout le monde s\'il confirme avant la clôture.']) ?>
    <button type="submit" class="bouton bouton-secondaire">Inviter</button>
  </form>
  </details>
  <form method="post" action="<?= $lien('/cloturer') ?>"><?= csrf_field() ?><button type="submit" class="lien-bouton" data-confirmer="Clôturer les vœux maintenant et attribuer les places ?">Clôturer les vœux maintenant</button></form>
  <?php endif ?>
  <?php endif ?>
</section>
