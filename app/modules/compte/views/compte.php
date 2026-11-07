<h1>Mon compte</h1>
<?php if ($erreur): ?><?= c_alerte('erreur', $erreur) ?><?php endif ?>
<p><?= e($membre['email']) ?></p>
<div class="grille">
  <?= c_carte('Double authentification', (int) $membre['totp_actif'] === 1
      ? '<p>Active.</p>'
      : '<p>Inactive' . (member_rights($membre['id']) ? ' : obligatoire pour la gestion.' : '.') . '</p>' . c_bouton('Activer', '/compte/double-authentification')) ?>
  <?php if (has_right($membre, 'publications')): ?>
  <?= c_carte('Pages du site', '<p>Créer et modifier les pages publiques.</p>' . c_bouton('Gérer les pages', '/admin/pages', 'secondaire')) ?>
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
