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
    <li><?= c_icone('calendrier') ?><?= e(evenement_horaire($ev)) ?></li>
    <?php if ($ev['lieu'] !== ''): ?><li><?= c_icone('lieu') ?><span><?= e($ev['lieu']) ?> <a class="lien-discret" href="<?= e(evenement_lien_itineraire($ev['lieu'])) ?>" target="_blank" rel="noopener noreferrer">Itinéraire</a></span></li><?php endif ?>
    <li><?= c_icone('personnes') ?><?= $limite ? (int) $ev['places'] . ' places' . ($ev['etat'] === 'attribuee' ? ' · ' . $inscrits . ' inscrits' : '') : 'Places sans limite' ?></li>
    <?php if ($limite && $ev['cloture'] !== null && $ev['etat'] === 'ouvert'): ?><li><?= c_icone('horloge') ?>Vœux jusqu'au <?= e(evenement_date($ev['cloture'])) ?></li><?php endif ?>
  </ul>

  <?php if ($ev['debut'] !== null && !$commence): ?>
  <details class="ajout-agenda"><summary class="bouton bouton-secondaire"><?= c_icone('calendrier') ?>Ajouter à mon agenda</summary>
    <div class="ajout-agenda-choix"><a href="<?= $lien('/agenda.ics') ?>">Agenda du téléphone ou de l'ordinateur (fichier .ics)</a><a href="<?= e(evenement_lien_google($espace, $ev)) ?>" target="_blank" rel="noopener noreferrer">Google Agenda</a></div>
  </details>
  <?php endif ?>
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
  <?php elseif (setting('inscription_cotisants', '1') === '1' && !espace_inscription_ouverte($membre, $espace)): ?>
  <?php $adhesion = (string) setting('lien_adhesion', ''); ?>
  <div class="statut-inscription statut-a-regler"><p><strong>Réglez votre cotisation pour vous inscrire.</strong> Cet événement est réservé aux membres à jour de cotisation.</p>
    <p><?= c_bouton('Régler ma cotisation', $adhesion !== '' ? $adhesion : '/compte') ?></p></div>
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
  <?php elseif ($liste): // Gestion : quatre chiffres, trois outils, puis Inscrits / Attente / Pointage
    $retenus = array_values(array_filter($liste, fn($l) => $l['statut'] === 'retenu'));
    $attente = array_values(array_filter($liste, fn($l) => in_array($l['statut'], ['attente', 'voeu', 'invite'], true)));
    $a_regler = $exige ? count(array_filter($retenus, fn($l) => !inscription_reglee($l, $ev))) : 0;
    $presents = count(array_filter($retenus, fn($l) => (string) $l['present'] === '1'));
    $emails = implode(',', array_map(fn($l) => $l['email'], $retenus));
    $onglet = $jour_j ? 'pointage' : 'inscrits'; ?>
  <div class="gestion-evenement" data-onglets>
  <h3 class="visuellement-cache">Gestion de l'événement</h3>
  <ul class="chiffres-gestion">
    <li><a href="#inscrits" data-vers="inscrits"><strong><?= count($retenus) ?><?= $limite ? '/' . (int) $ev['places'] : '' ?></strong><span>inscrits</span></a></li>
    <?php if ($exige): ?><li><a href="#inscrits" data-vers="inscrits"><strong><?= $a_regler ?></strong><span>à <?= $verbe ?></span></a></li><?php endif ?>
    <li><a href="#attente" data-vers="attente"><strong><?= count($attente) ?></strong><span>en attente</span></a></li>
    <li><a href="#pointage" data-vers="pointage"><strong data-presents><?= $presents ?></strong><span>présents</span></a></li>
  </ul>
  <p class="outils-gestion">
    <?php if ($emails !== ''): ?><a class="bouton bouton-secondaire" href="mailto:?bcc=<?= e($emails) ?>&amp;subject=<?= e(rawurlencode($espace['titre'])) ?>"><?= c_icone('enveloppe') ?>Écrire à tous</a><?php endif ?>
    <a class="bouton bouton-secondaire" href="<?= $lien('/emargement') ?>"><?= c_icone('liste') ?>Émargement</a>
    <a class="bouton bouton-secondaire" href="<?= $lien('/gerer') ?>"><?= c_icone('reglages') ?>Réglages</a>
  </p>
  <div class="onglets" role="tablist" hidden>
    <?php foreach (['inscrits' => 'Inscrits · ' . count($retenus), 'attente' => 'Attente · ' . count($attente), 'pointage' => 'Pointage'] as $id => $texte): ?>
    <button type="button" role="tab" id="onglet-<?= $id ?>" aria-controls="<?= $id ?>" aria-selected="<?= $id === $onglet ? 'true' : 'false' ?>" data-onglet="<?= $id ?>"><?= e($texte) ?></button>
    <?php endforeach ?>
  </div>
  <?php $ligne = function (array $l, string $actions) use ($groupe): string {
      return '<li class="personne"><span class="avatar" aria-hidden="true">' . e(mb_strtoupper(mb_substr(membre_nom_affiche($l), 0, 1))) . '</span><span class="qui"><strong>' . e(membre_nom($l)) . '</strong>' . $actions . '</span></li>';
  }; ?>
  <section id="inscrits" role="tabpanel" aria-labelledby="onglet-inscrits" class="panneau-onglet">
    <h4 class="titre-onglet">Inscrits</h4>
    <?php if (!$retenus): ?><p class="discret">Personne pour l'instant.</p><?php else: ?>
    <ul class="personnes">
      <?php foreach ($retenus as $l):
          $etat = '';
          if ($l['paye_le'] !== null) { $etat .= '<span class="badge badge-ok">payé le ' . e(date('d/m', strtotime($l['paye_le']))) . '</span>'; }
          if ($l['echeance'] !== null && !inscription_reglee($l, $ev)) { $etat .= '<span class="badge badge-attention">à ' . $verbe . ' avant le ' . e(date('d/m H\hi', strtotime($l['echeance']))) . '</span>'; }
          if ($l['confirme_le'] !== null) { $etat .= '<span class="badge badge-ok">venue confirmée</span>'; }
          $act = '';
          if ($paiement && $l['paye_le'] === null) { $act .= '<form method="post" action="' . $lien('/paye') . '">' . csrf_field() . '<input type="hidden" name="membre_id" value="' . (int) $l['membre_id'] . '"><button type="submit" class="lien-bouton" data-confirmer="Noter que ' . e(membre_nom($l)) . ' a payé ?">noter payé</button></form>'; }
          if (!$commence) { $act .= '<form method="post" action="' . $lien('/annuler') . '">' . csrf_field() . '<input type="hidden" name="membre_id" value="' . (int) $l['membre_id'] . '"><button type="submit" class="lien-bouton" data-confirmer="Annuler l\'inscription de ' . e(membre_nom($l)) . ' ? Sa place ira au suivant de la liste d\'attente.">annuler</button></form>'; }
          $act = '<a class="lien-bouton" href="mailto:' . e($l['email']) . '">écrire</a>' . $act; ?>
      <?= $ligne($l, ($etat !== '' ? '<span class="etat">' . $etat . '</span>' : '') . '<span class="actions">' . $act . '</span>') ?>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
  </section>
  <section id="attente" role="tabpanel" aria-labelledby="onglet-attente" class="panneau-onglet">
    <h4 class="titre-onglet">Liste d'attente et vœux <?php if ($limite): ?><?= $aide ?><?php endif ?></h4>
    <?php if (!$attente): ?><p class="discret">Personne en attente.</p><?php else: ?>
    <ul class="personnes">
      <?php foreach ($attente as $l): ?>
      <?= $ligne($l, '<span class="etat">' . ($l['groupe'] !== null ? $groupe((int) $l['groupe']) . ' ' : '') . '<span class="badge">' . e(INSCRIPTION_STATUTS[$l['statut']] . ($l['statut'] === 'attente' ? ' n°' . (int) $l['rang'] : '')) . '</span>'
          . ($l['paye_le'] !== null ? '<span class="badge badge-alerte">payé le ' . e(date('d/m', strtotime($l['paye_le']))) . ' · sans place</span>' : '') . '</span><span class="actions"><a class="lien-bouton" href="mailto:' . e($l['email']) . '">écrire</a></span>') ?>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
  </section>
  <section id="pointage" role="tabpanel" aria-labelledby="onglet-pointage" class="panneau-onglet">
    <h4 class="titre-onglet">Pointage</h4>
    <?php if (!$jour_j): ?><p class="discret">Le pointage s'ouvre le jour de l'événement. En cas de réseau faible sur place, imprimez la feuille d'émargement.</p>
    <?php elseif (!$retenus): ?><p class="discret">Personne à pointer.</p>
    <?php else: ?>
    <div class="compteur-pointage" role="status"><strong><span data-presents><?= $presents ?></span> présent(s) sur <?= count($retenus) ?></strong><span class="barre"><span style="width: <?= count($retenus) ? round(100 * $presents / count($retenus)) : 0 ?>%" data-barre></span></span></div>
    <p class="filtres-pointage" hidden><input type="search" placeholder="Chercher un nom" aria-label="Chercher un nom" data-chercher>
      <span class="segments"><button type="button" data-filtre="a-pointer" aria-pressed="true">À pointer</button><button type="button" data-filtre="presents" aria-pressed="false">Présents</button><button type="button" data-filtre="tous" aria-pressed="false">Tous</button></span></p>
    <ul class="personnes pointage-liste" data-total="<?= count($retenus) ?>">
      <?php foreach ($retenus as $l): $ok = (string) $l['present'] === '1'; ?>
      <li class="personne" data-present="<?= $ok ? '1' : '0' ?>" data-nom="<?= e(mb_strtolower(membre_nom($l))) ?>"><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(membre_nom_affiche($l), 0, 1))) ?></span><span class="qui"><strong><?= e(membre_nom($l)) ?></strong></span>
        <form method="post" action="<?= $lien('/presence') ?>" data-pointer><?= csrf_field() ?><input type="hidden" name="membre_id" value="<?= (int) $l['membre_id'] ?>"><input type="hidden" name="present" value="<?= $ok ? '' : '1' ?>">
          <button type="submit" class="bouton-present" aria-pressed="<?= $ok ? 'true' : 'false' ?>"><?= $ok ? '✓ Présent' : 'Présent' ?></button></form></li>
      <?php endforeach ?>
    </ul>
    <p class="petit">Pointage facultatif, sans effet sur les priorités. Une personne non pointée compte simplement comme « non renseigné ».</p>
    <?php endif ?>
  </section>
  </div>
  <?php endif ?>
  <?php endif ?>
</section>
