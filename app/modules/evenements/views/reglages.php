<?php
// Réglages d'un événement (page « Gérer ») : quand et où, inscriptions, après l'attribution, après l'événement ; inviter, clôturer
$lien = fn(string $chemin) => e(url('/espace/' . $espace['slug'] . $chemin));
?>
  <?php $dt = fn(?string $d) => $d ? date('Y-m-d\TH:i', strtotime($d)) : '';
  $modes = [
      'ouvert' => ['Ouvert', 'Tout le monde est inscrit, sans limite de places.'],
      'auto' => ['Places limitées', 'Chacun dépose un vœu ; les places sont attribuées automatiquement à la clôture.'],
      'valide' => ['Places limitées, je valide', 'Chacun dépose un vœu ; je valide la liste proposée à la clôture.'],
  ];
  $reglements = [
      'aucun' => ['Rien à faire', 'La place est acquise dès l\'attribution.'],
      'confirmation' => ['Confirmer sa venue', 'Chacun confirme dans un délai, sinon la place passe au suivant.'],
      'paiement' => ['Payer', 'Chacun paie dans un délai (lien HelloAsso), sinon la place passe au suivant.'],
  ]; ?>
  <form method="post" action="<?= $lien('/evenement') ?>" class="formulaire reglages-evenement" data-reglages-evenement>
    <?= csrf_field() ?>
    <fieldset class="bloc-reglage"><legend>Quand et où</legend>
      <div class="champs-ligne">
        <?= c_champ('debut', 'Début', ['type' => 'datetime-local', 'valeur' => $dt($ev['debut'])]) ?>
        <?= c_champ('fin', 'Fin (facultatif)', ['type' => 'datetime-local', 'valeur' => $dt($ev['fin'] ?? null)]) ?>
      </div>
      <?= c_champ('lieu', 'Lieu', ['valeur' => $ev['lieu'], 'max' => 200, 'aide' => 'Une salle, une adresse, ou « en visio ». Un lien « Itinéraire » est ajouté tout seul.']) ?>
    </fieldset>

    <fieldset class="bloc-reglage"><legend>Inscriptions</legend>
      <div class="choix-cartes">
        <?php foreach ($modes as $cle => [$titre_mode, $aide_mode]): ?>
        <label class="carte-choix"><input type="radio" name="mode" value="<?= $cle ?>"<?= $ev['mode'] === $cle ? ' checked' : '' ?>><span class="carte-choix-texte"><strong><?= e($titre_mode) ?></strong><small><?= e($aide_mode) ?></small></span></label>
        <?php endforeach ?>
      </div>
      <div data-si-limite>
        <div class="champs-ligne">
          <?= c_champ('places', 'Nombre de places', ['type' => 'number', 'valeur' => (string) ($ev['places'] ?? '')]) ?>
          <?= c_champ('cloture', 'Clôture des vœux', ['type' => 'datetime-local', 'valeur' => $dt($ev['cloture']), 'aide' => 'Avant l\'événement.']) ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="bloc-reglage" data-si-limite><legend>Après l'attribution des places</legend>
      <div class="choix-cartes">
        <?php foreach ($reglements as $cle => [$titre_r, $aide_r]): ?>
        <label class="carte-choix"><input type="radio" name="reglement" value="<?= $cle ?>"<?= $ev['reglement'] === $cle ? ' checked' : '' ?>><span class="carte-choix-texte"><strong><?= e($titre_r) ?></strong><small><?= e($aide_r) ?></small></span></label>
        <?php endforeach ?>
      </div>
      <div data-si-paiement>
        <?= c_champ('lien_paiement', 'Lien de paiement HelloAsso', ['type' => 'url', 'valeur' => (string) ($ev['lien_paiement'] ?? ''), 'aide' => helloasso_actif() ? 'Les paiements sont lus tout seuls.' : 'Sans connexion HelloAsso, notez les paiements reçus dans la liste (« noter payé »).']) ?>
      </div>
      <details class="avance"><summary>Délais</summary>
        <div class="champs-ligne">
          <?= c_champ('delai_jours', 'Délai pour payer ou confirmer (jours)', ['type' => 'number', 'valeur' => (string) $ev['delai_jours']]) ?>
          <?= c_champ('dernieres_jours', '« Dernières places » (jours avant)', ['type' => 'number', 'valeur' => (string) $ev['dernieres_jours'], 'aide' => 'Ensuite, les places libres vont au premier qui paie ou confirme.']) ?>
        </div>
      </details>
    </fieldset>

    <fieldset class="bloc-reglage"><legend>Après l'événement</legend>
      <input type="hidden" name="public_apres" value="0"><label class="case case-bloc"><input type="checkbox" name="public_apres" value="1"<?= !empty($ev['public_apres']) ? ' checked' : '' ?>><span><strong>Le rendre public une fois passé</strong><small class="discret">Réservé avant, visible de tous ensuite : compte rendu, photos.</small></span></label>
    </fieldset>

    <button type="submit" class="bouton bouton-principal">Enregistrer</button>
  </form>
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
