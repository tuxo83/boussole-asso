<?php
$nom = trim($membre['prenom'] . ' ' . $membre['nom']);
$initiales = mb_strtoupper(mb_substr($membre['prenom'] ?: $membre['email'], 0, 1) . mb_substr((string) $membre['nom'], 0, 1));
$ma = cotisation_a_jour($membre); $depuis = cotisation_depuis($membre); $periode = cotisation_periode_libelle(cotisation_periode());
$adhesion = (string) setting('lien_adhesion', '');
$inscriptions = evenement_mes_inscriptions($membre);
$agenda = page_agenda_slug();
$mois = ['', 'janv.', 'févr.', 'mars', 'avril', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
?>
<h1 class="visuellement-cache">Mon compte</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<section class="profil">
  <span class="profil-avatar" aria-hidden="true"><?= e($initiales) ?></span>
  <div class="profil-texte">
    <p class="profil-nom"><?= e($nom !== '' ? $nom : 'Mon compte') ?></p>
    <p class="profil-email"><?= e($membre['email']) ?></p>
  </div>
  <div class="profil-cotisation">
    <?php if ($ma): ?>
      <span class="badge badge-ok">Cotisation <?= e($periode) ?> à jour</span>
      <span class="petit">Payée le <?= e(date('d/m/Y', strtotime($ma['paye_le']))) ?><?= $depuis !== null && $depuis < cotisation_periode() ? ' · fidèle depuis ' . e(cotisation_periode_libelle($depuis)) : '' ?></span>
    <?php else: ?>
      <span class="badge badge-attention">Cotisation <?= e($periode) ?> à régler</span>
      <?php if ($adhesion !== ''): ?><?= c_bouton('Payer ma cotisation', $adhesion, 'principal') ?><?php else: ?><span class="petit">Déjà payée ? L'enregistrement peut prendre quelques jours.</span><?php endif ?>
    <?php endif ?>
  </div>
</section>

<section class="compte-section">
  <div class="titre-avec-lien"><h2>Mes prochains rendez-vous</h2><?php if ($agenda && $inscriptions): ?><a href="<?= e(url('/page/' . $agenda)) ?>">Tout l'agenda →</a><?php endif ?></div>
  <?php if ($inscriptions): ?>
  <ul class="rdv">
    <?php foreach ($inscriptions as $i): ?>
    <li><a href="<?= e(url('/espace/' . $i['slug'])) ?>">
      <span class="date-tuile"><?php if ($i['debut']): ?><span class="jour"><?= (int) date('j', strtotime($i['debut'])) ?></span><span class="mois"><?= $mois[(int) date('n', strtotime($i['debut']))] ?></span><?php else: ?><span class="date-icone"><?= c_icone('calendrier') ?></span><?php endif ?></span>
      <span class="rdv-texte"><strong><?= e($i['titre']) ?></strong><span class="rdv-ligne"><span class="discret"><?= $i['debut'] ? e(date('H\hi', strtotime($i['debut']))) : 'Date à venir' ?></span>
      <span class="badge<?= $i['statut'] === 'retenu' ? ' badge-ok' : ' badge-attention' ?>"><?= e(ucfirst(INSCRIPTION_STATUTS[$i['statut']])) ?><?= $i['statut'] === 'attente' ? ' n°' . (int) $i['rang'] : '' ?></span></span></span>
    </a></li>
    <?php endforeach ?>
  </ul>
  <?php else: ?>
  <p class="vide"><?= c_icone('calendrier') ?><span>Aucune inscription à venir.<?php if ($agenda): ?> <a href="<?= e(url('/page/' . $agenda)) ?>">Voir les prochains événements</a><?php endif ?></span></p>
  <?php endif ?>
</section>
<?php if (member_rights($membre['id'])):
    $tuiles = [];
    if (has_right($membre, 'publications')) { $tuiles[] = ['crayon', 'Pages du site', 'Créer et modifier les pages', '/admin/pages', '']; }
    if (has_right($membre, 'cotisations')) { $effacer = cotisation_a_effacer(); $tuiles[] = ['medaille', 'Cotisations', 'À jour, chèques, relances', '/cotisations', $effacer ? count($effacer) . ' à effacer' : '']; }
    if (has_right($membre, 'membres')) { $tuiles[] = ['personnes', 'Membres', 'Ajouter, modifier, droits', '/admin/membres', '']; }
    if (is_admin($membre)) {
        $nouveaux = (int) db_value('SELECT COUNT(*) FROM message_contact WHERE lu = 0');
        $tuiles[] = ['enveloppe', 'Messages reçus', 'Formulaire de contact', '/admin/messages', $nouveaux ? $nouveaux . ($nouveaux > 1 ? ' nouveaux' : ' nouveau') : ''];
        $tuiles[] = ['reglages', 'Réglages du site', 'Nom, apparence, e-mails', '/admin/parametres', ''];
        $tuiles[] = ['cle', 'Toutes les clés', "Clés d'API des membres", '/admin/cles', ''];
    } ?>
<section class="compte-section">
  <h2>Gestion de l'association</h2>
  <ul class="tuiles">
    <?php foreach ($tuiles as [$icone, $titre, $aide, $lien, $pastille]): ?>
    <li><a href="<?= e(url($lien)) ?>"><span class="tuile-icone"><?= c_icone($icone) ?></span><span class="tuile-texte"><strong><?= e($titre) ?></strong><span class="discret"><?= e($aide) ?></span></span><?php if ($pastille !== ''): ?><span class="pastille"><?= e($pastille) ?></span><?php endif ?></a></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>
<section class="compte-section">
<h2>Connexion et sécurité</h2>
<?php $nb_cles = (int) db_value('SELECT COUNT(*) FROM cle_securite WHERE membre_id = ?', [$membre['id']]); $demo = !empty($membre['compte_demo']); ?>
<ul class="reglages">
  <li>
    <span class="tuile-icone"><?= c_icone('cadenas') ?></span><div><strong>Mot de passe</strong><span class="discret"><?= $demo ? 'Compte de démonstration : fixé par un administrateur' : ($membre['mot_de_passe'] !== null ? 'Défini' : 'Aucun : connexion par lien envoyé par e-mail') ?></span></div>
    <?php if (!$demo): ?>
    <details class="reglage-action"<?= $erreur ? ' open' : '' ?>><summary class="bouton bouton-secondaire"><?= $membre['mot_de_passe'] !== null ? 'Changer' : 'Choisir' ?></summary>
      <form method="post" action="<?= e(url('/compte/mot-de-passe')) ?>" class="formulaire">
        <?= csrf_field() ?>
        <?= c_champ('mot_de_passe', 'Nouveau mot de passe', ['type' => 'password', 'aide' => 'Au moins ' . MOT_DE_PASSE_MIN . ' caractères. Laisser vide pour se connecter seulement par lien envoyé par e-mail.']) ?>
        <?= c_champ('confirmation', 'Confirmation', ['type' => 'password']) ?>
        <button type="submit" class="bouton bouton-principal">Enregistrer</button>
      </form>
    </details>
    <?php endif ?>
  </li>
  <li>
    <span class="tuile-icone"><?= c_icone('bouclier') ?></span><div><strong>Double authentification</strong><span class="discret"><?= ($nb_cles ? $nb_cles . ' clé(s) de sécurité' : 'Aucune clé de sécurité') . ((int) $membre['totp_actif'] === 1 ? ' · application active' : '') ?></span>
      <?php if (member_rights($membre['id']) && !$nb_cles && !(int) $membre['totp_actif']): ?><span class="petit">Conseillé pour la gestion : sinon un code par e-mail vous sera demandé.</span><?php endif ?></div>
    <?php if (!$demo): ?><?= c_bouton('Gérer', '/compte/double-authentification', 'secondaire') ?><?php endif ?>
  </li>
  <?php if (!$demo): ?>
  <li>
    <span class="tuile-icone"><?= c_icone('cle') ?></span><div><strong>Clés d'API</strong><span class="discret">Donner à un outil ou à une IA un accès avec vos droits</span></div>
    <?= c_bouton('Gérer', '/compte/cles', 'secondaire') ?>
  </li>
  <?php endif ?>
</ul>
</section>
<details class="separe"><summary>Mon historique</summary>
  <p class="petit">Les dernières connexions et modifications de votre compte. Une ligne que vous ne reconnaissez pas ? Changez votre mot de passe et prévenez l'association.</p>
  <?= partial('historique', ['historique' => $historique ?? [], 'cible_id' => $membre['id']]) ?>
</details>
<form method="post" action="<?= e(url('/deconnexion')) ?>" class="compte-sortie">
  <?= csrf_field() ?>
  <button type="submit" class="lien-bouton"><?= c_icone('sortie') ?>Se déconnecter</button>
</form>
