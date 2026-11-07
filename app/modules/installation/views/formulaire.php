<h1>Installation de Boussole</h1>
<?php if (!empty($erreurs['db'])): ?><?= c_alerte('erreur', $erreurs['db']) ?><?php endif ?>
<form method="post" action="<?= e(url('/installation')) ?>" class="formulaire">
  <?= csrf_field() ?>
  <?php if (config() === null): ?>
  <fieldset>
    <legend>Base de données MySQL / MariaDB</legend>
    <?= c_champ('db_host', 'Serveur', ['valeur' => $valeurs['db_host'] ?? 'localhost', 'requis' => true]) ?>
    <?= c_champ('db_port', 'Port', ['type' => 'number', 'valeur' => (string) ($valeurs['db_port'] ?? 3306)]) ?>
    <?= c_champ('db_name', 'Nom de la base', ['valeur' => $valeurs['db_name'] ?? '', 'requis' => true]) ?>
    <?= c_champ('db_user', 'Utilisateur', ['valeur' => $valeurs['db_user'] ?? '', 'requis' => true]) ?>
    <?= c_champ('db_password', 'Mot de passe', ['type' => 'password']) ?>
  </fieldset>
  <?php elseif (!empty(config()['code_installation'])): ?>
  <?= c_champ('code_installation', "Code d'installation", ['requis' => true, 'aide' => 'Affiché dans le journal du déploiement.', 'erreur' => $erreurs['code_installation'] ?? null]) ?>
  <?php endif ?>
  <fieldset>
    <legend>Association</legend>
    <?= c_champ('nom_association', "Nom de l'association", ['valeur' => $valeurs['nom_association'] ?? '', 'requis' => true, 'max' => 150, 'erreur' => $erreurs['nom_association'] ?? null]) ?>
    <?= c_champ('email_admin', 'E-mail du premier administrateur', ['type' => 'email', 'valeur' => $valeurs['email_admin'] ?? '', 'requis' => true, 'erreur' => $erreurs['email_admin'] ?? null]) ?>
    <?= c_champ('mot_de_passe', 'Mot de passe du premier administrateur', ['type' => 'password', 'requis' => true, 'aide' => 'Au moins 12 caractères.', 'erreur' => $erreurs['mot_de_passe'] ?? null]) ?>
    <?= c_champ('confirmation', 'Confirmation du mot de passe', ['type' => 'password', 'requis' => true]) ?>
  </fieldset>
  <button type="submit" class="bouton bouton-principal">Installer</button>
</form>
