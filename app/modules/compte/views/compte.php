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

<?php
// Mes prochains rendez-vous : mes inscriptions, les réunions de mes équipes, et les échéances de mes espaces (60 jours)
$rdv = [];
$inscrit = [];
foreach ($inscriptions as $i) {
    $inscrit[$i['slug']] = true;
    $rdv[] = ['date' => $i['debut'], 'heure' => true, 'titre' => $i['titre'], 'lien' => '/espace/' . $i['slug'], 'ligne' => '', 'genre' => 'evenement',
        'badge' => [$i['statut'] === 'retenu' ? 'badge-ok' : 'badge-attention', ucfirst(INSCRIPTION_STATUTS[$i['statut']]) . ($i['statut'] === 'attente' ? ' n°' . (int) $i['rang'] : '')]];
}
foreach (db_all('SELECT s.*, e.debut, e.lieu, p.titre AS parent_titre FROM espace s JOIN evenement e ON e.espace_id = s.id LEFT JOIN espace p ON p.id = s.parent_id
    WHERE s.type = "evenement" AND s.visibilite = "equipe" AND s.archive = 0 AND e.debut >= CURDATE() AND e.debut < CURDATE() + INTERVAL 60 DAY ORDER BY e.debut') as $r) {
    if (!isset($inscrit[$r['slug']]) && (espace_niveau($membre, $r) !== null || espace_equipe_parente($membre, $r))) {
        $rdv[] = ['date' => $r['debut'], 'heure' => true, 'titre' => $r['titre'], 'lien' => '/espace/' . $r['slug'], 'ligne' => $r['lieu'], 'genre' => 'reunion',
            'badge' => ['badge-equipe', 'Réunion' . ($r['parent_titre'] ? ' · ' . $r['parent_titre'] : '')]];
    }
}
foreach (db_all('SELECT j.*, s.titre AS espace_titre, s.slug FROM jalon j JOIN espace s ON s.id = j.espace_id JOIN espace_membre em ON em.espace_id = s.id AND em.membre_id = ?
    WHERE j.fait = 0 AND s.archive = 0 AND j.date_jalon >= CURDATE() AND j.date_jalon < CURDATE() + INTERVAL 60 DAY ORDER BY j.date_jalon', [$membre['id']]) as $j) {
    $rdv[] = ['date' => $j['date_jalon'], 'heure' => false, 'titre' => $j['libelle'], 'lien' => '/espace/' . $j['slug'], 'ligne' => '', 'genre' => 'echeance',
        'badge' => ['badge-echeance', 'Échéance · ' . $j['espace_titre']]];
}
usort($rdv, fn($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
// Mes rendez-vous passés (12 derniers mois) : les événements où j'étais inscrit, repliés par défaut
$passes = array_map(fn($i) => ['date' => $i['debut'], 'heure' => true, 'titre' => $i['titre'], 'lien' => '/espace/' . $i['slug'], 'ligne' => $i['lieu'], 'genre' => $i['visibilite'] === 'equipe' ? 'reunion' : 'evenement', 'badge' => null],
    db_all('SELECT s.titre, s.slug, s.visibilite, e.debut, e.lieu FROM inscription i JOIN evenement e ON e.espace_id = i.evenement_id JOIN espace s ON s.id = e.espace_id
        WHERE i.membre_id = ? AND i.statut = "retenu" AND e.debut < CURDATE() AND e.debut >= CURDATE() - INTERVAL 12 MONTH ORDER BY e.debut DESC', [$membre['id']]));
// Une mini-frise : des lignes compactes posées sur la ligne du temps de l'agenda
$mini_frise = function (array $items, bool $passe = false) use ($mois): string {
    $html = '<ul class="rdv rdv-frise' . ($passe ? ' rdv-frise-passee' : '') . '">';
    foreach ($items as $r) {
        $html .= '<li class="rdv-' . e($r['genre']) . '"><a href="' . e(url($r['lien'])) . '"><span class="date-tuile">'
            . ($r['date'] ? '<span class="jour">' . (int) date('j', strtotime($r['date'])) . '</span><span class="mois">' . $mois[(int) date('n', strtotime($r['date']))] . '</span>' : '<span class="date-icone">' . c_icone('calendrier') . '</span>')
            . '</span><span class="rdv-texte"><strong>' . e($r['titre']) . '</strong><span class="rdv-ligne">';
        $quand = trim(($r['heure'] ? ($r['date'] ? ($passe ? date('d/m/Y', strtotime($r['date'])) : date('H\\hi', strtotime($r['date']))) : 'Date à venir') : '') . ($r['ligne'] ? ' · ' . $r['ligne'] : ''), ' ·');
        $html .= ($quand !== '' ? '<span class="discret">' . e($quand) . '</span>' : '') . ($r['badge'] ? '<span class="badge ' . e($r['badge'][0]) . '">' . e($r['badge'][1]) . '</span>' : '') . '</span></span></a></li>';
    }
    return $html . '</ul>';
};
?>
<section class="compte-section">
  <div class="titre-avec-lien"><h2>Mes prochains rendez-vous</h2><?php if ($agenda && $rdv): ?><a href="<?= e(url('/page/' . $agenda)) ?>">Tout l'agenda →</a><?php endif ?></div>
  <?php if ($rdv): ?>
  <?= $mini_frise($rdv) ?>
  <?php else: ?>
  <p class="vide"><?= c_icone('calendrier') ?><span>Rien de prévu pour l'instant.<?php if ($agenda): ?> <a href="<?= e(url('/page/' . $agenda)) ?>">Voir les prochains événements</a><?php endif ?></span></p>
  <?php endif ?>
  <?php if ($passes): ?>
  <details class="rdv-passes"><summary>Mes rendez-vous passés (<?= count($passes) ?>)</summary><?= $mini_frise($passes, true) ?></details>
  <?php endif ?>
</section>
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
  <li>
    <span class="tuile-icone"><?= c_icone('horloge') ?></span><div><strong>Activité du compte</strong><span class="discret"><?= $membre['derniere_connexion'] ? 'Dernière connexion : ' . e(date('d/m/Y à H\\hi', strtotime($membre['derniere_connexion']))) : 'Connexions et modifications du compte' ?></span></div>
    <details class="reglage-action"><summary class="bouton bouton-secondaire">Voir</summary>
      <div class="activite">
        <?= partial('historique', ['historique' => $historique ?? [], 'cible_id' => $membre['id']]) ?>
        <p class="petit">Une ligne que vous ne reconnaissez pas ? Changez votre mot de passe et prévenez l'association.</p>
      </div>
    </details>
  </li>
</ul>
</section>
