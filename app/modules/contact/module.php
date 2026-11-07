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
            if ($v['objet'] === '') {
                $v['objet'] = 'Message du site';
            }
            db_query('INSERT INTO message_contact (nom, email, objet, message) VALUES (?, ?, ?, ?)', array_values($v));
            $id = (int) db()->lastInsertId();
            if (contact_transmettre($v)) {
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
        ['POST', '/admin/messages/{id}/supprimer', function (array $p) {
            $admin = require_right('admin');
            db_query('DELETE FROM message_contact WHERE id = ?', [(int) $p['id']]);
            journal(null, $admin, 'Message de contact supprimé');
            flash('succes', 'Message supprimé.');
            redirect('/admin/messages');
        }],
    ],
];

// Destinataires : l'adresse réglée, sinon les administrateurs ; « répondre » va à l'expéditeur
function contact_transmettre(array $v): bool
{
    $dest = trim((string) setting('contact_destinataire', ''));
    $adresses = $dest !== '' ? [$dest] : array_column(db_all("SELECT m.email FROM membre m JOIN droit_plateforme d ON d.membre_id = m.id WHERE d.droit = 'admin' AND m.actif = 1"), 'email');
    $ok = false;
    foreach ($adresses as $adresse) {
        $ok = mail_send($adresse, 'Contact : ' . $v['objet'],
            "Message reçu par le formulaire de contact du site.\n\nDe : {$v['nom']} <{$v['email']}>\nObjet : {$v['objet']}\n\n{$v['message']}\n\n"
            . "Pour répondre, répondez simplement à cet e-mail.\nTous les messages : " . absolute_url('/admin/messages') . "\n",
            null, $v['email']) || $ok;
    }
    return $ok;
}
