<?php
// Formulaire de contact : le message est gardé dans le site (Administration → Messages reçus)
// et transmis par e-mail quand l'envoi est réglé ; l'adresse de l'association n'est pas affichée en clair
declare(strict_types=1);

const CONTACT_MESSAGE_MAX = 5000;
return [
    'ordre' => 96,
    'routes' => [
        ['POST', '/contact', function () {
            $retour = (string) ($_POST['retour'] ?? '/');
            $retour = str_starts_with($retour, '/') && !str_starts_with($retour, '//') ? $retour : '/';
            if (!humain_verifier()) {
                humain_refus();
            }
            if (ip_blocked() || too_many_attempts('contact|' . client_ip(), 5, 60)) {
                flash('erreur', 'Trop de messages envoyés : réessayez dans une heure.');
                redirect($retour);
            }
            $motifs = contact_motifs();
            $motif = trim((string) ($_POST['motif'] ?? ''));
            if ($motifs && !isset($motifs[$motif])) {
                flash('erreur', 'Choisissez le motif de votre message.');
                redirect($retour);
            }
            // Fichier joint : un PDF seulement (moins de risques), d'une taille réglable
            $piece = $_FILES['piece'] ?? null;
            if ($piece !== null && ($piece['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $piece = null;
            }
            if ($piece !== null && !contact_piece_valide($piece)) {
                flash('erreur', 'Le fichier joint doit être un PDF de ' . contact_piece_max_mo() . ' Mo au plus.');
                redirect($retour);
            }
            $v = [
                'nom' => mb_substr(trim((string) ($_POST['nom'] ?? '')), 0, 100),
                'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
                'objet' => mb_substr(trim((string) ($_POST['objet'] ?? '')), 0, 200),
                'message' => mb_substr(trim((string) ($_POST['message'] ?? '')), 0, CONTACT_MESSAGE_MAX),
            ];
            if ($v['nom'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 190 || $v['message'] === '') {
                flash('erreur', 'Nom, adresse e-mail valide et message sont obligatoires.');
                redirect($retour);
            }
            if ($motif !== '') {
                $v['objet'] = mb_substr($motif . ($v['objet'] !== '' ? ' : ' . $v['objet'] : ''), 0, 200);
            }
            if ($v['objet'] === '') {
                $v['objet'] = 'Message du site';
            }
            db_query('INSERT INTO message_contact (nom, email, objet, message) VALUES (?, ?, ?, ?)', array_values($v));
            $id = (int) db()->lastInsertId();
            $jointe = $piece !== null ? contact_piece_ranger($id, $piece) : null;
            if (contact_transmettre($v, $jointe)) {
                db_query('UPDATE message_contact SET transmis = 1 WHERE id = ?', [$id]);
            }
            flash('succes', 'Merci, votre message a bien été envoyé. Nous vous répondrons à l\'adresse indiquée.');
            redirect($retour);
        }],
        ['GET', '/admin/messages', function () {
            require_right('admin');
            $messages = db_all('SELECT * FROM message_contact ORDER BY recu_le DESC');
            render('contact/messages', ['titre' => 'Messages reçus', 'messages' => $messages]);
            db_query('UPDATE message_contact SET lu = 1 WHERE lu = 0');
        }],
        // Le PDF joint, pour l'administrateur seulement, toujours en téléchargement
        ['GET', '/admin/messages/{id}/piece', function (array $p) {
            require_right('admin');
            $m = db_one('SELECT pj_nom, pj_stockage FROM message_contact WHERE id = ?', [(int) $p['id']]);
            $chemin = $m && $m['pj_stockage'] ? BOUSSOLE_ROOT . '/stockage/' . $m['pj_stockage'] : null;
            if ($chemin === null || !is_file($chemin)) {
                http_response_code(404);
                render('erreur', ['titre' => 'Fichier introuvable']);
                return;
            }
            header('Content-Type: application/pdf');
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: default-src 'none'; sandbox");
            header('Content-Disposition: attachment; filename="' . str_replace(['"', '\\'], '_', $m['pj_nom']) . '"; filename*=UTF-8\'\'' . rawurlencode($m['pj_nom']));
            header('Content-Length: ' . filesize($chemin));
            readfile($chemin);
        }],
        ['POST', '/admin/messages/{id}/supprimer', function (array $p) {
            $admin = require_right('admin');
            contact_pieces_effacer('id = ?', [(int) $p['id']]);
            db_query('DELETE FROM message_contact WHERE id = ?', [(int) $p['id']]);
            journal(null, $admin, 'Message de contact supprimé');
            flash('succes', 'Message supprimé.');
            redirect('/admin/messages');
        }],
    ],
];
