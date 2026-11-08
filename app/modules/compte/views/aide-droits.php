<p class="ariane"><a href="<?= e(url('/compte/parametres#administration')) ?>">Paramètres</a> › Administration</p>
<h1>Qui peut faire quoi</h1>
<p class="chapeau discret">Les droits sur le site, en un seul endroit. Trois choses indépendantes : les <strong>droits de gestion</strong> du site, la <strong>place dans l'équipe d'un espace</strong>, et ce que permet une <strong>clé d'API</strong>.</p>

<nav class="ancres" aria-label="Sur cette page"><a href="#site">Gestion du site</a><a href="#espaces">Espaces</a><a href="#evenements">Événements</a><a href="#pages">Pages</a><a href="#cles">Clés d'API</a><a href="#securite">Sécurité</a></nav>

<section id="site" class="aide-section">
  <h2>Gestion du site</h2>
  <p>Donnés par un administrateur, dans <em>Membres</em>. Tout droit de gestion impose la double authentification.</p>
  <div class="tableau"><table>
    <thead><tr><th>Droit</th><th>Ce qu'il permet</th></tr></thead>
    <tbody>
      <tr><td><strong>Administrateur</strong></td><td>Tout : réglages du site (nom, apparence, e-mails, météo automatique, arrivée après connexion), messages reçus, toutes les clés d'API, tous les espaces, et les trois droits ci-dessous.</td></tr>
      <tr><td><strong>Membres</strong></td><td>Ajouter et modifier les membres, envoyer l'invitation de bienvenue.</td></tr>
      <tr><td><strong>Cotisations</strong></td><td>Voir qui est à jour, noter un chèque, importer HelloAsso, relancer, effacer les données en fin de conservation.</td></tr>
      <tr><td><strong>Publications</strong></td><td>Créer et modifier les pages du site, les images, l'accueil.</td></tr>
      <tr><td>Membre sans droit</td><td>Son compte, ses inscriptions, les espaces et événements qu'il peut voir.</td></tr>
    </tbody>
  </table></div>
  <p class="petit">Seul l'administrateur donne des droits ; il reste toujours au moins un administrateur. Les fonctions (président, trésorier…) ne donnent aucun droit par elles-mêmes.</p>
</section>

<section id="espaces" class="aide-section">
  <h2>Espaces (comité, commissions, projets)</h2>
  <h3>Qui voit un espace</h3>
  <div class="tableau"><table>
    <thead><tr><th>Réglage « Qui peut voir ? »</th><th>Qui le voit</th></tr></thead>
    <tbody>
      <tr><td>Tout le monde (public)</td><td><?= e(ESPACE_VISIBILITES_AIDE['public']) ?></td></tr>
      <tr><td>Tous les membres connectés</td><td><?= e(ESPACE_VISIBILITES_AIDE['adherents']) ?></td></tr>
      <tr><td>L'équipe de l'espace</td><td><?= e(ESPACE_VISIBILITES_AIDE['equipe']) ?></td></tr>
    </tbody>
  </table></div>
  <p class="petit">Qui voit l'espace voit tout son contenu : description, météo, dates clés, fichiers, équipe, événements. Les adresses e-mail ne sont jamais montrées.</p>
  <h3>Ce que chacun fait, selon son niveau dans l'équipe</h3>
  <div class="tableau"><table>
    <thead><tr><th>Niveau</th><th>Voir</th><th>Ajouter fichiers, dates, météo</th><th>Gérer l'équipe, les réglages, organiser un événement</th><th>Journal de l'espace</th></tr></thead>
    <tbody>
      <tr><td>N°1 et N°2</td><td>oui</td><td>oui</td><td>oui</td><td>oui</td></tr>
      <tr><td>Membre</td><td>oui</td><td>oui</td><td>non</td><td>oui</td></tr>
      <tr><td>Associé</td><td>oui</td><td>non</td><td>non</td><td>oui</td></tr>
      <tr><td>Hors de l'équipe</td><td>selon le réglage</td><td>non</td><td>non</td><td>non</td></tr>
      <tr><td>Administrateur du site</td><td>oui</td><td>oui</td><td>oui</td><td>oui</td></tr>
    </tbody>
  </table></div>
  <p class="petit">Créer un espace : l'administrateur, ou le n°1 / n°2 d'un espace pour y créer un sous-espace. Archiver : qui gère l'espace ; supprimer : l'administrateur seul. Le libellé de chacun (« Bureau : Président ») est repris tel quel dans les pages qui affichent l'équipe.</p>
</section>

<section id="evenements" class="aide-section">
  <h2>Événements</h2>
  <div class="tableau"><table>
    <thead><tr><th>Qui</th><th>Ce qu'il voit et fait</th></tr></thead>
    <tbody>
      <tr><td>Visiteur</td><td>Les événements publics. D'un événement réservé, il ne voit que son existence et sa date, jamais son titre ni son lieu.</td></tr>
      <tr><td>Membre connecté</td><td>Les événements publics et réservés aux membres ; s'y inscrire (à jour de cotisation si le réglage l'exige). Les réunions d'équipe de ses espaces, dans son agenda seulement.</td></tr>
      <tr><td>Qui gère l'événement (n°1, n°2, administrateur)</td><td>Les réglages, la liste des inscrits et l'attente, écrire à tous, la feuille d'émargement, le pointage, annuler une inscription, noter un paiement.</td></tr>
    </tbody>
  </table></div>
  <p class="petit">Les inscrits ne sont visibles que de ceux qui gèrent l'événement. Un absent le jour J n'est pas remplacé : il est seulement noté absent.</p>
</section>

<section id="pages" class="aide-section">
  <h2>Pages du site</h2>
  <p>Une page est publique, ou <strong>réservée aux membres</strong> (case dans l'éditeur) : un visiteur ne la voit alors pas dans les menus. Les modifier demande le droit <em>Publications</em>. Un bloc « Équipe d'un espace » montre les noms et rôles de l'équipe à qui peut voir la page.</p>
</section>

<section id="cles" class="aide-section">
  <h2>Clés d'API (outils, IA, MCP)</h2>
  <div class="tableau"><table>
    <thead><tr><th>Portée</th><th>Ce qu'elle permet</th></tr></thead>
    <tbody>
      <tr><td>Lecture seule</td><td>Lire ce que son propriétaire peut voir.</td></tr>
      <tr><td>Lecture et écriture</td><td>Faire ce que son propriétaire peut faire dans les espaces, les événements, les pages.</td></tr>
      <tr><td>Administration</td><td>En plus, réglages du site et membres (pour un administrateur).</td></tr>
    </tbody>
  </table></div>
  <p class="petit">Une clé n'a jamais plus de droits que son propriétaire. Durée limitée (ou sans limite), révocable à tout moment. Par l'API, on ne touche <strong>jamais</strong> à la sécurité d'un compte : mot de passe, double authentification, clés.</p>
</section>

<section id="securite" class="aide-section">
  <h2>Sécurité des comptes</h2>
  <ul>
    <li>Chacun gère son mot de passe, sa double authentification et ses clés dans <em>Paramètres</em>.</li>
    <li><strong>Rester connecté sur cet appareil</strong> (case à la connexion, cochée d'office dans l'application) : seule la connexion simple reste ouverte ; la gestion redemande la double authentification, valable <?= (int) mfa_validite_heures() ?> heures. Changer de mot de passe déconnecte les autres appareils ; la liste est dans <em>Paramètres › Appareils connectés</em>.</li>
    <li>Un administrateur peut donner un <strong>mot de passe provisoire</strong> (à changer à la connexion suivante) ou effacer un membre.</li>
    <li>Un <strong>compte de démonstration</strong> a un mot de passe fixé par l'administrateur ; ses utilisateurs ne peuvent changer ni ce mot de passe ni la sécurité.</li>
    <li>Toute action de gestion laisse une trace : le journal, et l'historique du compte concerné.</li>
  </ul>
</section>
