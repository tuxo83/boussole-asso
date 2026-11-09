<?php
// Formulaire de contact : motifs, PDF joint, transmission par e-mail (aussi utilisés par les tâches planifiées)
declare(strict_types=1);

// Motifs proposés en cartes en haut du formulaire (réglables : « Libellé : explication », un par ligne)
const CONTACT_MOTIFS_DEFAUT = "Devenir membre associé : Les questions de défense vous intéressent et vous souhaitez nous rejoindre.\n"
    . "Devenir membre auditeur : Vous avez suivi une session de l'Institut et souhaitez rejoindre l'association.\n"
    . "Proposer une intervention ou un partenariat : Conférence, visite, projet commun.\n"
    . "Autre question : Tout le reste.";

// Motifs du formulaire : [libellé => explication] ; aucun motif réglé : le champ « Objet » à la place
function contact_motifs(): array
{
    $motifs = [];
    foreach (preg_split('/\R/', (string) setting('contact_motifs', CONTACT_MOTIFS_DEFAUT)) as $ligne) {
        [$libelle, $aide] = array_map('trim', explode(':', $ligne, 2)) + [1 => ''];
        if ($libelle !== '') {
            $motifs[mb_substr($libelle, 0, 120)] = mb_substr($aide, 0, 200);
        }
    }
    return $motifs;
}

function contact_piece_active(): bool
{
    return setting('contact_piece_jointe', '1') === '1';
}

function contact_piece_max_mo(): int
{
    return max(1, min(10, (int) setting('contact_piece_max_mo', '5')));
}

// Un vrai PDF : reçu sans erreur, pas trop lourd, qui commence par « %PDF- » et reconnu comme tel
function contact_piece_valide(array $piece): bool
{
    if (!contact_piece_active() || ($piece['error'] ?? 1) !== UPLOAD_ERR_OK || (int) $piece['size'] > contact_piece_max_mo() * 1024 * 1024
        || !is_uploaded_file($piece['tmp_name'])) {
        return false;
    }
    return file_get_contents($piece['tmp_name'], false, null, 0, 5) === '%PDF-'
        && (new finfo(FILEINFO_MIME_TYPE))->file($piece['tmp_name']) === 'application/pdf';
}

// Rangé hors du dossier public sous un nom aléatoire ; le nom d'origine n'est qu'une étiquette, toujours en .pdf
function contact_piece_ranger(int $id, array $piece): ?array
{
    $stockage = 'contact-' . bin2hex(random_bytes(16));
    $chemin = BOUSSOLE_ROOT . '/stockage/' . $stockage;
    if (!move_uploaded_file($piece['tmp_name'], $chemin)) {
        return null;
    }
    $nom = mb_substr(preg_replace('/[\x00-\x1f\/\\\\"]/', '_', pathinfo((string) $piece['name'], PATHINFO_FILENAME)), 0, 120) ?: 'piece-jointe';
    $nom .= '.pdf';
    db_query('UPDATE message_contact SET pj_nom = ?, pj_stockage = ?, pj_taille = ? WHERE id = ?', [$nom, $stockage, (int) $piece['size'], $id]);
    return ['nom' => $nom, 'chemin' => $chemin];
}

// Efface les PDF des messages choisis (avant d'effacer les messages)
function contact_pieces_effacer(string $where, array $params = []): void
{
    foreach (db_all("SELECT pj_stockage FROM message_contact WHERE pj_stockage IS NOT NULL AND $where", $params) as $m) {
        if (preg_match('/^contact-[a-f0-9]{32}$/', $m['pj_stockage'])) {
            @unlink(BOUSSOLE_ROOT . '/stockage/' . $m['pj_stockage']);
        }
    }
}

// Destinataires : l'adresse réglée, sinon les administrateurs ; « répondre » va à l'expéditeur
function contact_transmettre(array $v, ?array $jointe = null): bool
{
    $pieces = $jointe !== null ? [['nom' => $jointe['nom'], 'type' => 'application/pdf', 'contenu' => (string) file_get_contents($jointe['chemin'])]] : [];
    $dest = trim((string) setting('contact_destinataire', ''));
    $adresses = $dest !== '' ? [$dest] : array_column(db_all("SELECT m.email FROM membre m JOIN droit_plateforme d ON d.membre_id = m.id WHERE d.droit = 'admin' AND m.actif = 1"), 'email');
    $ok = false;
    foreach ($adresses as $adresse) {
        $ok = mail_send($adresse, 'Contact : ' . $v['objet'],
            "Message reçu par le formulaire de contact du site.\n\nDe : {$v['nom']} <{$v['email']}>\nObjet : {$v['objet']}\n\n{$v['message']}\n\n"
            . ($jointe !== null ? "Fichier joint : {$jointe['nom']}\n\n" : '')
            . "Pour répondre, répondez simplement à cet e-mail.\nTous les messages : " . absolute_url('/admin/messages') . "\n",
            null, $v['email'], $pieces) || $ok;
    }
    return $ok;
}
