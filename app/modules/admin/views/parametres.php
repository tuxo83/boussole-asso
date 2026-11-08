<h1>Réglages</h1>
<form method="post" action="<?= e(url('/admin/parametres')) ?>" class="formulaire" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <fieldset>
    <legend>Association</legend>
    <?= c_champ('nom_association', "Nom de l'association", ['valeur' => $v['nom_association'] ?? '', 'requis' => true, 'erreur' => $erreurs['nom_association'] ?? null]) ?>
    <?= c_champ('slogan', 'Slogan', ['valeur' => $v['slogan'] ?? '']) ?>
    <?= c_champ('presentation', "Présentation (page d'accueil)", ['type' => 'textarea', 'valeur' => $v['presentation'] ?? '', 'lignes' => 8, 'aide' => 'Texte simple : une ligne vide sépare les paragraphes.']) ?>
    <?= c_champ('pied_de_page', 'Pied de page', ['valeur' => $v['pied_de_page'] ?? '']) ?>
    <?= c_champ('adresse_site', 'Adresse publique du site', ['type' => 'url', 'valeur' => $v['adresse_site'] ?? '', 'aide' => 'Utilisée dans les liens des e-mails.', 'erreur' => $erreurs['adresse_site'] ?? null]) ?>
  </fieldset>
  <fieldset>
    <legend>Apparence</legend>
    <?= c_champ('bandeau_legende', "Légende de l'image d'accueil", ['valeur' => $v['bandeau_legende'] ?? '', 'max' => 300, 'aide' => "Facultatif : ce que montre la photo et pourquoi elle a été choisie. Affichée discrètement, au clic sur le « i » du bandeau."]) ?>
    <?= c_champ('logo', 'Logo', ['type' => 'file', 'aide' => 'PNG, JPEG ou WebP, 1 Mo maximum.', 'erreur' => $erreurs['logo'] ?? null]) ?>
    <?= c_champ('couleur_principale', 'Couleur principale', ['type' => 'color', 'valeur' => $v['couleur_principale'] ?? '#1f4e79', 'erreur' => $erreurs['couleur_principale'] ?? null]) ?>
    <?= c_champ('couleur_accent', "Couleur d'accent", ['type' => 'color', 'valeur' => $v['couleur_accent'] ?? '#c8102e', 'erreur' => $erreurs['couleur_accent'] ?? null]) ?>
    <?= c_champ('couleur_membres', 'Couleur « réservé aux membres » (agenda)', ['type' => 'color', 'valeur' => $v['couleur_membres'] ?? '#5a2d91', 'erreur' => $erreurs['couleur_membres'] ?? null, 'aide' => 'Choisir une couleur foncée : le texte blanc doit rester lisible dessus.']) ?>
    <?= c_champ('css_personnalise', 'CSS personnalisé', ['type' => 'textarea', 'valeur' => $v['css_personnalise'] ?? '', 'aide' => 'Facultatif, chargé après la feuille de style de base.', 'erreur' => $erreurs['css_personnalise'] ?? null]) ?>
  </fieldset>
  <fieldset>
    <legend>Site en construction</legend>
    <label class="case"><input type="checkbox" name="construction_active" value="1"<?= ($v['construction_active'] ?? '') === '1' ? ' checked' : '' ?>> Site en construction : bandeau sur chaque page, code d'accès pour les visiteurs</label>
    <?= c_champ('construction_code', "Code d'accès (6 chiffres)", ['valeur' => $v['construction_code'] ?? '', 'aide' => 'À transmettre aux personnes invitées à voir le site. Vide : un code est tiré au hasard. Les membres connectés n\'en ont pas besoin. Changer le code ferme l\'accès à ceux qui avaient l\'ancien.', 'erreur' => $erreurs['construction_code'] ?? null]) ?>
  </fieldset>
  <fieldset>
    <legend>Conservation</legend>
    <?= c_champ('contact_conservation_mois', 'Garder les messages du formulaire de contact pendant (mois)', ['type' => 'number', 'valeur' => $v['contact_conservation_mois'] ?? '12', 'aide' => 'De 1 à 60 mois, puis effacement automatique.']) ?>
    <?= c_champ('journal_conservation_ans', 'Garder le journal des actions pendant (années)', ['type' => 'number', 'valeur' => $v['journal_conservation_ans'] ?? '3', 'aide' => 'De 1 à 10 ans. Les lignes plus anciennes sont effacées automatiquement. À indiquer dans la page de confidentialité.']) ?>
  </fieldset>
  <fieldset>
    <legend>Après la connexion</legend>
    <p class="petit">Page où arrive un membre qui se connecte depuis « Connexion » (s'il voulait voir une page précise, il y retourne).</p>
    <?php $arrivees = connexion_arrivees_possibles(); ?>
    <?= c_champ('arrivee_cotisant', 'Membre à jour de cotisation', ['type' => 'select', 'valeur' => $v['arrivee_cotisant'] ?? '', 'choix' => $arrivees]) ?>
    <?= c_champ('arrivee_non_cotisant', 'Membre pas encore à jour', ['type' => 'select', 'valeur' => $v['arrivee_non_cotisant'] ?? '', 'choix' => $arrivees]) ?>
  </fieldset>
  <fieldset>
    <legend>Rappels par e-mail</legend>
    <p class="petit">Nombres de jours avant l'échéance, séparés par des virgules. Vide : aucun rappel.</p>
    <?= c_champ('rappel_jalons', 'Dates clés des comités, commissions et projets', ['valeur' => $v['rappel_jalons'] ?? RAPPEL_DELAIS_DEFAUT['rappel_jalons'], 'erreur' => $erreurs['rappel_jalons'] ?? null]) ?>
    <?= c_champ('rappel_jalons_evenements', "Dates clés de l'organisation des événements", ['valeur' => $v['rappel_jalons_evenements'] ?? RAPPEL_DELAIS_DEFAUT['rappel_jalons_evenements'], 'aide' => 'Ex. 60 : deux mois avant, pour réserver une salle.', 'erreur' => $erreurs['rappel_jalons_evenements'] ?? null]) ?>
    <?= c_champ('rappel_participants', 'Participants inscrits à un événement', ['valeur' => $v['rappel_participants'] ?? RAPPEL_DELAIS_DEFAUT['rappel_participants'], 'erreur' => $erreurs['rappel_participants'] ?? null]) ?>
  </fieldset>
  <fieldset>
    <legend>Envoi des e-mails</legend>
    <?= c_champ('contact_destinataire', 'Adresse qui reçoit les messages du formulaire de contact', ['type' => 'email', 'valeur' => $v['contact_destinataire'] ?? '', 'aide' => 'Vide : les administrateurs. Les messages restent aussi consultables dans Administration → Messages reçus.']) ?>
    <?= c_champ('mail_expediteur', 'Adresse d\'expédition', ['type' => 'email', 'valeur' => $v['mail_expediteur'] ?? '', 'erreur' => $erreurs['mail_expediteur'] ?? null]) ?>
    <?= c_champ('smtp_hote', 'Serveur SMTP', ['valeur' => $v['smtp_hote'] ?? '', 'aide' => "Vide : la fonction d'envoi de l'hébergeur est utilisée."]) ?>
    <?= c_champ('smtp_port', 'Port', ['valeur' => $v['smtp_port'] ?? '587', 'erreur' => $erreurs['smtp_port'] ?? null]) ?>
    <?= c_champ('smtp_securite', 'Sécurité de la connexion', ['type' => 'select', 'valeur' => $v['smtp_securite'] ?? 'starttls', 'choix' => ['starttls' => 'STARTTLS (port 587, le plus courant)', 'ssl' => 'SSL / TLS (port 465)', 'aucune' => 'Aucune (déconseillé)'], 'erreur' => $erreurs['smtp_securite'] ?? null]) ?>
    <?= c_champ('smtp_utilisateur', 'Utilisateur', ['valeur' => $v['smtp_utilisateur'] ?? '']) ?>
    <?= c_champ('smtp_mot_de_passe', 'Mot de passe', ['type' => 'password', 'aide' => 'Laisser vide pour garder le mot de passe enregistré.']) ?>
    <div class="test-envoi" id="test-envoi">
      <?php if (!empty($test_mail)): ?><div class="alerte alerte-<?= e($test_mail[0]) ?>" role="status"><?= e($test_mail[1]) ?></div><?php endif ?>
      <?= c_champ('test_destinataire', 'Envoyer un e-mail de test à', ['type' => 'email', 'valeur' => $test_destinataire ?? current_member()['email'], 'aide' => "Teste les réglages ci-dessus tels qu'ils sont saisis, sans les enregistrer."]) ?>
      <button type="submit" class="bouton bouton-secondaire" formaction="<?= e(url('/admin/mail-test')) ?>#test-envoi" formnovalidate>Tester l'envoi</button>
    </div>
  </fieldset>
  <button type="submit" class="bouton bouton-principal">Enregistrer</button>
</form>
