<?php
// Gabarit commun à toutes les pages : en-tête, menu, messages, contenu, pied de page
$nom = setting('nom_association', 'Boussole') ?? 'Boussole';
$logo = setting('logo');
$menu = installation_done() ? menu_items() : [];
$chemin = request_path();
$moi_menu = installation_done() ? current_member() : null;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.classList.add('js')</script>
<?php $slogan = installation_done() ? trim((string) setting('slogan', '')) : '';
// Accueil : nom et accroche, si l'ensemble reste lisible dans un résultat de recherche (65 caractères)
$titre_complet = isset($titre) ? $titre . ' · ' . $nom : ($slogan !== '' && mb_strlen($nom . ' – ' . $slogan) <= 65 ? $nom . ' – ' . $slogan : $nom); ?>
<title><?= e($titre_complet) ?></title>
<?= installation_done() ? seo_balises($titre_complet, $seo ?? []) : '' ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/boussole.css')) ?>">
<style>:root{--couleur-principale:<?= color_setting('couleur_principale', '#1f4e79') ?>;--couleur-accent:<?= color_setting('couleur_accent', '#c8102e') ?>;--acces-membres:<?= color_setting('couleur_membres', '#5a2d91') ?>}</style>
<?php if (installation_done() && trim((string) setting('css_personnalise', '')) !== ''): ?>
<link rel="stylesheet" href="<?= e(url('/theme.css')) ?>?v=<?= e(substr(md5((string) setting('css_personnalise')), 0, 8)) ?>">
<?php endif ?>
</head>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu</a>
<?php if (installation_done() && construction_active()): ?>
<div class="bandeau-construction" role="note"><?= c_icone('attention') ?><span><strong>Site en construction</strong> : version de travail, contenu en cours de rédaction.</span></div>
<?php endif ?>
<header class="entete">
  <div class="conteneur entete-interne">
    <a class="marque" href="<?= e(url('/')) ?>">
      <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt="" height="48"><?php endif ?>
      <span><?= e($nom) ?></span>
    </a>
    <?php if ($menu): ?>
    <nav aria-label="Menu principal" class="navigation">
      <ul class="menu">
        <?php foreach ($menu as $item): if (isset($item['zone'])) { continue; } ?>
        <li><a href="<?= e(url($item['lien'])) ?>"<?= $item['lien'] === $chemin ? ' aria-current="page"' : '' ?><?= !empty($item['verrou']) ? ' class="lien-verrou" title="Réservé aux membres"' : '' ?>><?= !empty($item['verrou']) ? c_icone('cadenas') : '' ?><?= e($item['libelle']) ?></a></li>
        <?php endforeach ?>
      </ul>
      <ul class="menu menu-membre">
        <?php foreach ($menu as $item): if (!isset($item['zone']) || ($item['zone'] === 'compte' && $moi_menu !== null)) { continue; } ?>
        <li><a href="<?= e(url($item['lien'])) ?>"<?= $item['zone'] === 'compte' ? ' class="lien-compte"' : '' ?><?= $item['lien'] === $chemin ? ' aria-current="page"' : '' ?>><?= e($item['libelle']) ?></a></li>
        <?php endforeach ?>
      </ul>
    </nav>
    <?php endif ?>
    <?php if ($menu): // Téléphone : un bouton menu qui déplie toutes les pages (le menu en ligne y est masqué) ?>
    <details class="menu-burger">
      <summary aria-label="Menu"><span class="burger" aria-hidden="true"><span></span><span></span><span></span></span></summary>
      <nav class="burger-panneau" aria-label="Menu principal (téléphone)">
        <?php foreach ($menu as $item): if (($item['zone'] ?? '') === 'compte' && $moi_menu !== null) { continue; } ?>
        <a href="<?= e(url($item['lien'])) ?>"<?= $item['lien'] === $chemin ? ' aria-current="page"' : '' ?><?= ($item['zone'] ?? '') === 'compte' ? ' class="burger-compte"' : '' ?>><?= !empty($item['verrou']) ? c_icone('cadenas') : '' ?><?= e($item['libelle']) ?></a>
        <?php endforeach ?>
      </nav>
    </details>
    <?php endif ?>
    <?php if ($moi_menu !== null): // Menu de compte : initiales ; Mon compte, Administration (si droits), Se déconnecter
        $initiales = mb_strtoupper(mb_substr($moi_menu['prenom'] ?: $moi_menu['email'], 0, 1) . mb_substr((string) $moi_menu['nom'], 0, 1));
        $attente = member_rights($moi_menu['id']) ? administration_en_attente($moi_menu) : 0; ?>
    <details class="compte-menu">
      <summary aria-label="Mon compte"<?= str_starts_with($chemin, '/compte') ? ' aria-current="page"' : '' ?>><span class="compte-initiales"><?= e($initiales) ?></span><?php if ($attente): ?><span class="compte-attente" aria-label="<?= $attente ?> en attente"><?= $attente ?></span><?php endif ?></summary>
      <div class="compte-panneau">
        <p class="compte-qui"><strong><?= e(membre_nom($moi_menu)) ?></strong><span><?= cotisation_a_jour($moi_menu) !== null ? 'Cotisation ' . e(cotisation_periode_libelle(cotisation_periode())) . ' à jour' : 'Cotisation ' . e(cotisation_periode_libelle(cotisation_periode())) . ' à régler' ?></span></p>
        <a href="<?= e(url('/compte')) ?>"><?= c_icone('personne') ?>Mon compte</a>
        <a href="<?= e(url('/compte/parametres')) ?>"><?= c_icone('reglages') ?>Paramètres<?php if ($attente): ?><span class="compte-compteur"><?= $attente ?></span><?php endif ?></a>
        <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= csrf_field() ?><button type="submit" class="compte-sortie-menu"><?= c_icone('sortie') ?>Se déconnecter</button></form>
      </div>
    </details>
    <?php endif ?>
  </div>
</header>
<?php $moi = installation_done() ? current_member() : null; ?>
<?php // Rappel de cotisation (avis d'Élise) : discret mais lisible, masquable pour la session, sauf sur « Mon compte »
$sur_compte = str_starts_with(request_path(), '/compte'); ?>
<?php if ($moi !== null && cotisation_a_jour($moi) === null && request_path() !== '/compte' && ($sur_compte || empty($_SESSION['rappel_cotisation_masque']))): ?>
<div class="bandeau-cotisation" role="note"><div class="conteneur"><span>Votre cotisation <?= e(cotisation_periode_libelle(cotisation_periode())) ?> n'est pas encore enregistrée.
  <?php $adhesion = (string) setting('lien_adhesion', ''); ?><a href="<?= e($adhesion !== '' ? $adhesion : url('/compte')) ?>"<?= $adhesion !== '' ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>Régler ma cotisation</a></span>
  <?php if (!$sur_compte): ?><form method="post" action="<?= e(url('/compte/rappel-cotisation/masquer')) ?>"><?= csrf_field() ?><input type="hidden" name="retour" value="<?= e(request_path()) ?>"><button type="submit" class="bandeau-fermer" aria-label="Masquer ce rappel jusqu'à la prochaine connexion" title="Masquer jusqu'à la prochaine connexion">×</button></form><?php endif ?></div></div>
<?php endif ?>
<main id="contenu" class="conteneur">
  <?php foreach (flash() as $message): ?><?= c_alerte($message['type'], (string) $message['message']) ?><?php endforeach ?>
  <?= $content ?>
</main>
<footer class="pied">
  <div class="conteneur">
    <?php $liens_pied = installation_done() ? db_all('SELECT slug, titre FROM page WHERE publiee = 1 AND reservee_membres = 0 AND dans_menu = 0 AND est_accueil = 0 AND parent_id IS NULL ORDER BY ordre, titre') : []; ?>
    <div class="pied-grille">
      <div>
        <p class="pied-nom"><?= e($nom) ?></p>
        <?php if (trim((string) setting('pied_de_page', '')) !== ''): ?><p><?= e((string) setting('pied_de_page')) ?></p><?php endif ?>
      </div>
      <?php if ($liens_pied): ?>
      <ul class="pied-liens"><?php foreach ($liens_pied as $lien): ?><li><a href="<?= e(url('/page/' . $lien['slug'])) ?>"><?= e($lien['titre']) ?></a></li><?php endforeach ?></ul>
      <?php endif ?>
    </div>
    <p class="discret"><a href="<?= e(BOUSSOLE_DEPOT) ?>" rel="noopener"><?= e(t('pied.propulse')) ?></a>, logiciel libre</p>
  </div>
</footer>
<script src="<?= e(asset('/assets/js/boussole.js')) ?>" defer></script>
</body>
</html>
