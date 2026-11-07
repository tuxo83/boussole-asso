<?php
// Tâches planifiées : lancées par le cron de l'hébergeur (php bin/cli.php cron, toutes les 10 minutes),
// ou, chez un hébergeur sans cron, par les visites du site (au plus toutes les 10 minutes, après l'envoi de la page).
declare(strict_types=1);

function taches_planifiees(): array
{
    // Un seul passage à la fois, même si le cron et une visite arrivent ensemble
    if ((int) db_value("SELECT GET_LOCK('boussole_taches', 0)") !== 1) {
        return ['déjà en cours'];
    }
    try {
        setting_set('taches_derniere', (string) time());
        return [
            evenements_cloturer_echus() . ' événement(s) clôturé(s)',
            cotisations_relances_auto() . ' relance(s) de cotisation',
            rappels_envoyer() . ' rappel(s) envoyé(s)',
            helloasso_synchroniser() . ' mise(s) à jour HelloAsso',
            evenements_regler_tous() . ' événement(s) à règlement vérifié(s)',
            evenements_rappels_echeance() . " rappel(s) d'échéance",
            journal_purger() . ' ligne(s) de journal effacée(s)',
            messages_contact_purger() . ' message(s) de contact effacé(s)',
        ];
    } finally {
        db_value("SELECT RELEASE_LOCK('boussole_taches')");
    }
}

// Visites : si aucun passage depuis 10 minutes (pas de cron), on lance les tâches après avoir envoyé la page
function taches_par_les_visites(): void
{
    if (PHP_SAPI === 'cli' || !installation_done() || (config()['taches_par_les_visites'] ?? true) === false
        || (int) setting('taches_derniere', '0') > time() - 600) {
        return;
    }
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        ignore_user_abort(true);
        try {
            taches_planifiees();
        } catch (Throwable $erreur) {
            error_log('Boussole, tâches planifiées : ' . $erreur->getMessage());
        }
    });
}
