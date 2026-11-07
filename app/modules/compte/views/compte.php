<h1>Mon compte</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<p><?= e($membre['email']) ?></p>
<div class="grille">
  <?php $nb_cles = (int) db_value('SELECT COUNT(*) FROM cle_securite WHERE membre_id = ?', [$membre['id']]); ?>
  <?= c_carte('Sécurité du compte', '<p>' . ($nb_cles ? $nb_cles . ' clé(s) de sécurité' : 'Aucune clé de sécurité') . ((int) $membre['totp_actif'] === 1 ? ' · application active' : '') . '</p>'
      . (member_rights($membre['id']) && !$nb_cles && !(int) $membre['totp_actif'] ? '<p class="petit">Conseillé pour la gestion : ajoutez une clé (sinon un code par e-mail vous sera demandé).</p>' : '')
      . c_bouton('Gérer', '/compte/double-authentification', 'secondaire')) ?>
  <?php $ma = cotisation_a_jour($membre); $depuis = cotisation_depuis($membre); ?>
  <?= c_carte('Ma cotisation ' . cotisation_periode_libelle(cotisation_periode()), $ma
      ? '<p><span class="badge badge-ok">À jour</span></p><p class="petit">Payée le ' . e(date('d/m/Y', strtotime($ma['paye_le']))) . ($depuis !== null && $depuis < cotisation_periode() ? ' · fidèle depuis ' . e(cotisation_periode_libelle($depuis)) : '') . '</p>'
      : '<p><span class="badge">Pas encore à jour</span></p>' . (setting('lien_adhesion', '') !== '' ? c_bouton('Payer ma cotisation', (string) setting('lien_adhesion'), 'principal') . '<p class="petit">Utilisez la même adresse e-mail que votre compte.</p>' : '<p class="petit">Si vous avez déjà payé, l\'enregistrement peut prendre quelques jours.</p>')) ?>
  <?php $inscriptions = evenement_mes_inscriptions($membre); ?>
  <?= c_carte('Mes inscriptions', $inscriptions ? '<ul class="liste">' . implode('', array_map(fn($i) => '<li><a href="' . e(url('/espace/' . $i['slug'])) . '">' . e($i['titre']) . '</a> <span class="discret">· ' . e(evenement_date($i['debut'])) . ' · ' . e(INSCRIPTION_STATUTS[$i['statut']]) . ($i['statut'] === 'attente' ? ' n°' . (int) $i['rang'] : '') . '</span></li>', $inscriptions)) . '</ul>' : '<p>Aucune inscription à venir.</p>') ?>
  <?= c_carte("Clés d'API (IA, MCP)", '<p>Donner à un outil ou à une IA un accès avec vos droits.</p>' . c_bouton('Mes clés', '/compte/cles', 'secondaire')) ?>
  <?php if (is_admin($membre)): ?>
  <?= c_carte("Toutes les clés", '<p>Voir et révoquer les clés de tous les membres.</p>' . c_bouton('Toutes les clés', '/admin/cles', 'secondaire')) ?>
  <?php endif ?>
  <?php if (has_right($membre, 'publications')): ?>
  <?= c_carte('Pages du site', '<p>Créer et modifier les pages publiques.</p>' . c_bouton('Gérer les pages', '/admin/pages', 'secondaire')) ?>
  <?php endif ?>
  <?php if (has_right($membre, 'cotisations')): $effacer = cotisation_a_effacer(); ?>
  <?= c_carte('Cotisations', '<p>Qui est à jour, chèques, import HelloAsso, relances.</p>' . ($effacer ? '<p>' . c_meteo(cotisation_meteo_effacement($effacer)) . ' ' . count($effacer) . ' donnée(s) à effacer</p>' : '') . c_bouton('Gérer les cotisations', '/cotisations', 'secondaire')) ?>
  <?php endif ?>
  <?php if (has_right($membre, 'membres')): ?>
  <?= c_carte('Membres', '<p>Ajouter, modifier, donner des droits.</p>' . c_bouton('Gérer les membres', '/admin/membres', 'secondaire')) ?>
  <?php endif ?>
  <?php if (is_admin($membre)): ?>
  <?= c_carte('Administration', '<p>Nom, apparence, envoi des e-mails.</p>' . c_bouton('Réglages', '/admin/parametres', 'secondaire')) ?>
  <?php endif ?>
</div>
<h2>Mot de passe</h2>
<form method="post" action="<?= e(url('/compte/mot-de-passe')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?= c_champ('mot_de_passe', 'Nouveau mot de passe', ['type' => 'password', 'aide' => 'Facultatif. Laisser vide pour se connecter uniquement par lien envoyé par e-mail.']) ?>
  <?= c_champ('confirmation', 'Confirmation', ['type' => 'password']) ?>
  <button type="submit" class="bouton bouton-secondaire">Enregistrer</button>
</form>
<form method="post" action="<?= e(url('/deconnexion')) ?>" class="separe">
  <?= csrf_field() ?>
  <button type="submit" class="bouton bouton-secondaire">Se déconnecter</button>
</form>
