<?php
// Cotisations (droit « cotisations ») : cocher, importer HelloAsso, relancer, exporter, effacer les données anciennes
declare(strict_types=1);

return [
    'ordre' => 40,
    'routes' => [
        ['GET', '/cotisations', function () {
            require_right('cotisations');
            $periode = cotisation_periode_demandee($_GET['periode'] ?? null);
            $effacer = cotisation_a_effacer();
            render('cotisations/liste', [
                'titre' => 'Cotisations',
                'periode' => $periode,
                'membres' => db_all('SELECT m.*, c.paye_le, c.moyen, s.prenom AS saisi_prenom, s.nom AS saisi_nom, s.email AS saisi_email, r.envoyee_le
                    FROM membre m LEFT JOIN cotisation c ON c.membre_id = m.id AND c.periode = ? LEFT JOIN membre s ON s.id = c.saisi_par
                    LEFT JOIN cotisation_relance r ON r.membre_id = m.id AND r.periode = ? WHERE m.actif = 1 ORDER BY c.paye_le IS NOT NULL, m.nom, m.prenom, m.email', [$periode, $periode]),
                'effacer' => $effacer,
                'doublons' => db_all('SELECT d.*, m.prenom, m.nom, m.email, c.paye_le AS premier_le, c.moyen AS premier_moyen FROM cotisation_doublon d JOIN membre m ON m.id = d.membre_id
                    LEFT JOIN cotisation c ON c.membre_id = d.membre_id AND c.periode = d.periode WHERE d.traite = 0 ORDER BY d.signale_le'),
                'nb_attente' => (int) db_value('SELECT COUNT(*) FROM membre WHERE en_attente = 1'),
                'import' => $_SESSION['import_bilan'] ?? null,
            ]);
            unset($_SESSION['import_bilan']);
        }],
        ['POST', '/cotisations/marquer', function () {
            $par = require_right('cotisations');
            $periode = cotisation_periode_demandee($_POST['periode'] ?? null);
            $member = db_one('SELECT * FROM membre WHERE id = ?', [(int) ($_POST['membre_id'] ?? 0)]);
            if ($member === null || !cotisation_marquer($member, $periode, (string) ($_POST['paye_le'] ?? ''), (string) ($_POST['moyen'] ?? ''), $par)) {
                flash('erreur', 'Date (pas dans le futur) et moyen de paiement obligatoires.');
            }
            redirect('/cotisations?periode=' . $periode . '#m' . (int) ($_POST['membre_id'] ?? 0));
        }],
        ['POST', '/cotisations/annuler', function () {
            $par = require_right('cotisations');
            $periode = cotisation_periode_demandee($_POST['periode'] ?? null);
            $member = db_one('SELECT * FROM membre WHERE id = ?', [(int) ($_POST['membre_id'] ?? 0)]);
            if ($member !== null && db_query('DELETE FROM cotisation WHERE membre_id = ? AND periode = ?', [$member['id'], $periode])->rowCount() === 1) {
                journal(null, $par, 'Cotisation ' . cotisation_periode_libelle($periode) . ' annulée : ' . membre_nom($member));
            }
            redirect('/cotisations?periode=' . $periode);
        }],
        ['POST', '/cotisations/relancer', function () {
            $par = require_right('cotisations');
            $periode = cotisation_periode_demandee($_POST['periode'] ?? null);
            $liste = cotisation_retardataires($periode);
            foreach ($liste as $member) {
                cotisation_relancer($member, $periode);
            }
            journal(null, $par, 'Relance des cotisations ' . cotisation_periode_libelle($periode) . ' : ' . count($liste) . ' e-mail(s)');
            flash('succes', count($liste) . ' relance(s) envoyée(s).');
            redirect('/cotisations?periode=' . $periode);
        }],
        ['POST', '/cotisations/import', function () {
            $par = require_right('cotisations');
            $periode = cotisation_periode_demandee($_POST['periode'] ?? null);
            $file = $_FILES['fichier'] ?? null;
            if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
                flash('erreur', 'Choisissez le fichier CSV exporté depuis HelloAsso (5 Mo maximum).');
                redirect('/cotisations?periode=' . $periode);
            }
            $bilan = cotisation_import_helloasso($file['tmp_name'], $periode, $par);
            isset($bilan['erreur']) ? flash('erreur', $bilan['erreur']) : $_SESSION['import_bilan'] = $bilan;
            redirect('/cotisations?periode=' . $periode);
        }],
        ['GET', '/cotisations/export', function () {
            $par = require_right('cotisations');
            $periode = cotisation_periode_demandee($_GET['periode'] ?? null);
            journal(null, $par, 'Export des cotisations ' . cotisation_periode_libelle($periode));
            cotisation_csv('cotisations-' . cotisation_periode_libelle($periode), ['Nom', 'Prénom', 'E-mail', 'Période', 'Payé le', 'Moyen'],
                array_map(fn($r) => [$r['nom'], $r['prenom'], $r['email'], cotisation_periode_libelle($periode), $r['paye_le'], COTISATION_MOYENS[$r['moyen']]],
                    db_all('SELECT m.nom, m.prenom, m.email, c.paye_le, c.moyen FROM cotisation c JOIN membre m ON m.id = c.membre_id WHERE c.periode = ? ORDER BY m.nom', [$periode])));
        }],
        // Avant d'effacer : on peut garder une trace (qui a cotisé, quand), comme le prévoit la décision
        ['GET', '/cotisations/export-effacement', function () {
            $par = require_right('cotisations');
            journal(null, $par, 'Export des données à effacer');
            $lignes = [];
            foreach (cotisation_a_effacer() as $m) {
                $periodes = array_column(db_all('SELECT periode FROM cotisation WHERE membre_id = ? ORDER BY periode', [$m['id']]), 'periode');
                $lignes[] = [$m['nom'], $m['prenom'], $m['email'], implode(' ', array_map(fn($p) => cotisation_periode_libelle((int) $p), $periodes))];
            }
            cotisation_csv('donnees-a-effacer-' . date('Y-m-d'), ['Nom', 'Prénom', 'E-mail', 'Cotisations'], $lignes);
        }],
        ['POST', '/cotisations/effacer', function () {
            $par = require_right('cotisations');
            $choisis = array_map('intval', (array) ($_POST['membres'] ?? []));
            $n = 0;
            foreach (cotisation_a_effacer() as $member) {
                if (in_array((int) $member['id'], $choisis, true)) {
                    membre_effacer($member, $par);
                    $n++;
                }
            }
            flash('succes', "$n membre(s) effacé(s).");
            redirect('/cotisations');
        }],
        ['POST', '/cotisations/helloasso', function () {
            $par = require_right('cotisations');
            $slug = fn(string $k) => strtolower(trim(preg_replace('#^.*/#', '', trim((string) ($_POST[$k] ?? '')))));
            foreach (['helloasso_organisation' => $slug('helloasso_organisation'), 'helloasso_adhesion' => $slug('helloasso_adhesion'),
                'helloasso_client_id' => mb_substr(trim((string) ($_POST['helloasso_client_id'] ?? '')), 0, 100),
                'helloasso_intervalle' => (string) max(5, min(1440, (int) ($_POST['helloasso_intervalle'] ?? 10)))] as $cle => $valeur) {
                setting_set($cle, $valeur);
            }
            if (trim((string) ($_POST['helloasso_secret'] ?? '')) !== '') {
                setting_set('helloasso_secret', secret_encrypt(trim((string) $_POST['helloasso_secret'])));
            }
            if (($_POST['desactiver'] ?? '') === '1') {
                setting_set('helloasso_secret', '');
            }
            // Nouveaux identifiants : nouveau jeton, et première lecture depuis le début de la période
            setting_set('helloasso_jeton', '');
            setting_set('helloasso_premiere', '');
            if (setting('helloasso_notification_cle', '') === '') {
                setting_set('helloasso_notification_cle', bin2hex(random_bytes(16)));
            }
            journal(null, $par, 'Connexion HelloAsso modifiée');
            flash('succes', 'Connexion HelloAsso enregistrée.');
            redirect('/cotisations#helloasso');
        }],
        ['POST', '/cotisations/helloasso/synchroniser', function () {
            require_right('cotisations');
            $n = helloasso_synchroniser(true);
            $erreur = (string) setting('helloasso_erreur', '');
            $erreur !== '' ? flash('erreur', $erreur) : flash('succes', "HelloAsso lu : $n mise(s) à jour.");
            redirect('/cotisations#helloasso');
        }],
        // Adresse à donner à HelloAsso (« Mon compte > Intégrations et API ») : un paiement déclenche une lecture immédiate
        ['POST', '/helloasso/notification/{cle}', function (array $p) {
            $cle = (string) setting('helloasso_notification_cle', '');
            if ($cle !== '' && hash_equals($cle, $p['cle']) && !too_many_attempts('helloasso|notification', 20, 10)) {
                helloasso_synchroniser(true);
            }
            // Toujours 200 : HelloAsso ne réessaie pas inutilement, et l'appelant n'apprend rien
            http_response_code(200);
        }],
        ['POST', '/cotisations/doublon', function () {
            $par = require_right('cotisations');
            db_query('UPDATE cotisation_doublon SET traite = 1 WHERE membre_id = ? AND periode = ? AND paye_le = ?', [(int) ($_POST['membre_id'] ?? 0), (int) ($_POST['periode'] ?? 0), (string) ($_POST['paye_le'] ?? '')]);
            journal(null, $par, 'Paiement en double marqué traité');
            redirect('/cotisations');
        }],
        ['POST', '/cotisations/reglages', function () {
            $par = require_right('cotisations');
            $mois = (int) ($_POST['cotisation_mois_debut'] ?? 1);
            $jours = (int) ($_POST['cotisation_relance_jours'] ?? 30);
            $ans = (int) ($_POST['cotisation_conservation_ans'] ?? 2);
            if ($mois < 1 || $mois > 12 || $jours < 0 || $jours > 365 || $ans < 1 || $ans > 10) {
                flash('erreur', 'Mois 1 à 12, relance 0 à 365 jours, conservation 1 à 10 ans.');
            } else {
                setting_set('cotisation_mois_debut', (string) $mois);
                setting_set('cotisation_relance_jours', (string) $jours);
                setting_set('cotisation_conservation_ans', (string) $ans);
                setting_set('inscription_cotisants', ($_POST['inscription_cotisants'] ?? '') === '1' ? '1' : '0');
                $lien = trim((string) ($_POST['lien_adhesion'] ?? ''));
                setting_set('lien_adhesion', preg_match('#^https://[^\s"<>]+$#', $lien) ? mb_substr($lien, 0, 300) : '');
                journal(null, $par, 'Réglages des cotisations modifiés');
                flash('succes', 'Réglages enregistrés.');
            }
            redirect('/cotisations');
        }],
    ],
];

function cotisation_periode_demandee(mixed $value): int
{
    $periode = (int) $value;
    $actuelle = cotisation_periode();
    return $periode >= $actuelle - 10 && $periode <= $actuelle + 1 ? $periode : $actuelle;
}

// Export CSV lisible par un tableur français (point-virgule, BOM UTF-8) ; cellules neutralisées contre les formules
function cotisation_csv(string $nom, array $entetes, array $lignes): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9.-]/i', '-', $nom) . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach (array_merge([$entetes], $lignes) as $ligne) {
        fputcsv($out, array_map(fn($c) => preg_match('/^[=+\-@\t\r]/', (string) $c) ? "'" . $c : (string) $c, $ligne), ';', '"', '');
    }
    exit;
}
