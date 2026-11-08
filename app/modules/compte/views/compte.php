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
</section>
<?php
// Mes espaces : ceux dont je fais partie de l'équipe (actifs), et les archivés pour les Archives
$mes_espaces = db_all('SELECT s.*, em.niveau, em.libelle AS role FROM espace s JOIN espace_membre em ON em.espace_id = s.id AND em.membre_id = ? WHERE s.type <> "evenement" ORDER BY s.archive, FIELD(s.type, "comite", "commission", "projet"), s.titre', [$membre['id']]);
$actifs = array_filter($mes_espaces, fn($e) => !$e['archive']);
$archives = array_filter($mes_espaces, fn($e) => (bool) $e['archive']);
?>
<?php if ($actifs): ?>
<section class="compte-section">
  <div class="titre-avec-lien"><h2>Mes espaces</h2><a href="<?= e(url('/espaces')) ?>">Tous les espaces →</a></div>
  <ul class="tuiles">
    <?php foreach ($actifs as $es): $alertes = espace_peut_gerer($membre, $es) || espace_peut_contribuer($membre, $es) ? espace_alertes($es) : []; ?>
    <li><a href="<?= e(url('/espace/' . $es['slug'])) ?>"><span class="tuile-texte"><strong><?= e($es['titre']) ?></strong><span class="discret"><?= e(ESPACE_TYPES[$es['type']]) ?><?= $es['role'] ? ' · ' . e($es['role']) : (in_array($es['niveau'], ['n1', 'n2'], true) ? ' · ' . e(ESPACE_NIVEAUX[$es['niveau']]) : '') ?></span>
      <span class="tuile-etat"><?= c_meteo($es['meteo']) ?><?php if ($alertes): ?><span class="alertes-mini"><?= c_icone('attention') ?><?= e(count($alertes) === 1 ? $alertes[0] : count($alertes) . ' points à traiter') ?></span><?php endif ?></span></span></a></li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>
<?php if ($passes || $archives): ?>
<details class="compte-archives"><summary>Archives</summary>
  <?php if ($passes): ?><h3>Mes rendez-vous passés</h3><?= $mini_frise($passes, true) ?><?php endif ?>
  <?php if ($archives): ?><h3>Mes espaces archivés</h3>
  <ul class="liste"><?php foreach ($archives as $es): ?><li><a href="<?= e(url('/espace/' . $es['slug'])) ?>"><?= e($es['titre']) ?></a> <span class="discret">· <?= e(ESPACE_TYPES[$es['type']]) ?></span></li><?php endforeach ?></ul>
  <?php endif ?>
</details>
<?php endif ?>
