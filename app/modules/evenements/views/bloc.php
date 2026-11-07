<?php
$lien = fn(string $chemin) => e(url('/espace/' . $espace['slug'] . $chemin));
$bouton = fn(string $action, string $texte, string $style = 'principal', string $confirmer = '') => '<form method="post" action="' . $lien('/inscription') . '" class="en-ligne">' . csrf_field()
    . '<input type="hidden" name="action" value="' . $action . '"><button type="submit" class="bouton bouton-' . $style . '"' . ($confirmer !== '' ? ' data-confirmer="' . e($confirmer) . '"' : '') . '>' . e($texte) . '</button></form>';
$limite = evenement_limite($ev);
$statut = $mien['statut'] ?? null;
?>
<section class="evenement" aria-labelledby="titre-inscription">
  <ul class="infos">
    <li><strong>Quand :</strong> <?= e(evenement_date($ev['debut'])) ?></li>
    <?php if ($ev['lieu'] !== ''): ?><li><strong>Où :</strong> <?= e($ev['lieu']) ?></li><?php endif ?>
    <li><strong>Places :</strong> <?= $limite ? (int) $ev['places'] . ($ev['etat'] === 'attribuee' ? ' (' . $inscrits . ' inscrits)' : '') : 'sans limite' ?></li>
    <?php if ($limite && $ev['cloture'] !== null): ?><li><strong>Vœux jusqu'au :</strong> <?= e(evenement_date($ev['cloture'])) ?></li><?php endif ?>
  </ul>
  <h2 id="titre-inscription">Inscription</h2>
  <?php if ($limite && $ev['etat'] === 'ouvert'): ?>
  <p class="petit">Pas de course au clic : l'heure de dépôt ne compte pas. À la clôture, les places vont d'abord à celles et ceux qui sont venus le moins souvent ces 12 derniers mois ; un tirage départage les ex æquo.</p>
  <?php endif ?>
  <?php if ($membre === null): ?>
  <p><?= c_bouton('Se connecter pour s\'inscrire', '/connexion') ?></p>
  <?php elseif ($statut === 'invite'): ?>
  <p>L'organisateur vous invite en priorité.</p>
  <p><?= $bouton('confirmer', 'J\'accepte l\'invitation') ?> <?= $bouton('decliner', 'Je décline', 'secondaire') ?></p>
  <?php elseif ($statut === 'voeu'): ?>
  <p><strong>Votre vœu est enregistré<?= $mien['invite'] ? ' (invité prioritaire)' : '' ?>.</strong> Réponse à la clôture, par e-mail.</p>
  <p><?= $bouton('retirer', 'Retirer mon vœu', 'secondaire') ?></p>
  <?php elseif ($statut === 'retenu'): ?>
  <p><strong>Vous êtes inscrit.</strong></p>
  <p><?= $bouton('desister', 'Finalement, je ne peux pas', 'secondaire', 'Libérer votre place ? Elle ira à la personne suivante sur la liste d\'attente.') ?></p>
  <?php elseif ($statut === 'attente'): ?>
  <p><strong>Vous êtes n°<?= (int) $mien['rang'] ?> sur la liste d'attente.</strong> Vous serez prévenu si une place se libère.</p>
  <p><?= $bouton('desister', 'Je ne suis plus intéressé', 'secondaire') ?></p>
  <?php elseif ($ev['debut'] !== null && $ev['debut'] <= date('Y-m-d H:i:s')): ?>
  <p>Cet événement a eu lieu.</p>
  <?php elseif ($ev['etat'] === 'ouvert' && !evenement_accepte_voeux($ev) && $ev['mode'] !== 'ouvert'): ?>
  <p>Les inscriptions sont closes.</p>
  <?php else: ?>
  <p><?= $bouton('voeu', $ev['mode'] === 'ouvert' ? 'Je m\'inscris' : ($ev['etat'] === 'ouvert' ? 'Je souhaite venir' : 'M\'inscrire sur la liste d\'attente')) ?></p>
  <?php endif ?>

  <?php if ($gere): ?>
  <details class="separe"<?= $ev['debut'] === null ? ' open' : '' ?>><summary>Réglages de l'événement</summary>
  <form method="post" action="<?= $lien('/evenement') ?>" class="formulaire">
    <?= csrf_field() ?>
    <?= c_champ('debut', 'Date et heure', ['type' => 'datetime-local', 'valeur' => $ev['debut'] ? date('Y-m-d\TH:i', strtotime($ev['debut'])) : '']) ?>
    <?= c_champ('lieu', 'Lieu', ['valeur' => $ev['lieu'], 'max' => 200]) ?>
    <?= c_champ('mode', 'Inscriptions', ['type' => 'select', 'valeur' => $ev['mode'], 'choix' => EVENEMENT_MODES]) ?>
    <?= c_champ('places', 'Nombre de places (places limitées)', ['type' => 'number', 'valeur' => (string) ($ev['places'] ?? '')]) ?>
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

  <?php if ($ev['etat'] === 'proposee'): $proposes = count(array_filter($liste, fn($l) => $l['statut'] === 'voeu' && $l['propose'])); ?>
  <h3>Liste proposée</h3>
  <p class="petit">Cochez ou décochez, puis validez : chacun est prévenu par e-mail, les autres vont en liste d'attente dans l'ordre.</p>
  <form method="post" action="<?= $lien('/valider') ?>" class="formulaire" id="validation">
    <?= csrf_field() ?>
    <p role="status" data-places="<?= (int) $ev['places'] ?>"><span id="compte-retenus"><?= $proposes ?></span> retenus pour <?= (int) $ev['places'] ?> places<span id="depassement" class="alerte-texte"<?= $proposes > (int) $ev['places'] ? '' : ' hidden' ?>> : capacité dépassée</span></p>
    <?php foreach ($liste as $l): if ($l['statut'] !== 'voeu') { continue; } ?>
    <label class="case"><input type="checkbox" name="retenus[]" value="<?= (int) $l['membre_id'] ?>"<?= $l['propose'] ? ' checked' : '' ?>>
      <?= e(membre_nom($l)) ?> <span class="discret">· <?= $l['groupe'] !== null ? 'groupe ' . (int) $l['groupe'] . ' · ' . (int) $l['participations'] . ' participation(s) sur 12 mois' : 'vœu arrivé après la clôture' ?></span></label>
    <?php endforeach ?>
    <button type="submit" class="bouton bouton-principal">Valider la liste</button>
  </form>
  <?php elseif ($liste): ?>
  <h3>Inscrits et candidats</h3>
  <?= c_tableau(['Nom', 'Situation', 'Priorité'], array_map(fn($l) => [
      membre_nom($l),
      INSCRIPTION_STATUTS[$l['statut']] . ($l['statut'] === 'attente' ? ' n°' . (int) $l['rang'] : ''),
      $l['groupe'] !== null ? (int) $l['groupe'] . ' · ' . EVENEMENT_GROUPES[(int) $l['groupe']] : ($l['invite'] ? 'invité' : '—'),
  ], $liste)) ?>
  <?php endif ?>
  <?php endif ?>
</section>
