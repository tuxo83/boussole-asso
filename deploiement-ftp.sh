#!/bin/bash
#
# Déploiement par FTP chez un hébergeur ordinaire (OVH, o2switch, Infomaniak, IONOS…).
# Envoie le dossier app/ ; ne touche jamais à la configuration ni aux fichiers déposés sur le site.
# Les mises à jour de la base s'appliquent toutes seules à la première visite qui suit.
#
# Usage : ./deploiement-ftp.sh [--essai] [fichier de configuration]
#   --essai : affiche ce qui serait envoyé ou supprimé, sans rien changer
#

######################### Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ESSAI=0
if [[ "${1:-}" == "--essai" ]]; then
    ESSAI=1
    shift
fi
CONF="${1:-$SCRIPT_DIR/deploiement-ftp.conf}"

######################### Vérifications
if [[ ! -f "$CONF" ]]; then
    echo "ERREUR : $CONF introuvable. Copier deploiement-ftp.conf.exemple en deploiement-ftp.conf et le compléter."
    exit 1
fi
# shellcheck source=/dev/null
source "$CONF"
for var in FTP_HOTE FTP_UTILISATEUR FTP_DOSSIER; do
    if [[ -z "${!var:-}" ]]; then
        echo "ERREUR : $var est vide dans $CONF"
        exit 1
    fi
done
if [[ "${FTP_SECURITE:-ftps}" != "ftps" && "${FTP_SECURITE:-}" != "ftp" ]]; then
    echo "ERREUR : FTP_SECURITE doit valoir ftps ou ftp"
    exit 1
fi
if ! command -v lftp >/dev/null; then
    echo "ERREUR : lftp est nécessaire (Debian, Ubuntu : sudo apt install lftp ; macOS : brew install lftp)"
    exit 1
fi
if [[ -z "${FTP_MOT_DE_PASSE:-}" ]]; then
    read -r -s -p "Mot de passe FTP de $FTP_UTILISATEUR : " FTP_MOT_DE_PASSE
    echo
fi

######################### Envoi
# Jamais envoyés ni supprimés : la configuration du site, les images et fichiers déposés (sauf leurs .htaccess)
MIRROR="mirror --reverse --delete --only-newer --no-perms --parallel=4 --verbose=1"
MIRROR+=" --exclude '^config/config\\.php\$' --exclude '^uploads/[^.]' --exclude '^stockage/[^.]'"
[[ $ESSAI -eq 1 ]] && MIRROR+=" --dry-run"
if [[ "${FTP_SECURITE:-ftps}" == "ftps" ]]; then
    SSL="set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate ${FTP_VERIFIER_CERTIFICAT:-yes};"
else
    echo "ATTENTION : FTP non chiffré, le mot de passe circule en clair"
    SSL="set ftp:ssl-allow false;"
fi
CIBLE="$FTP_HOTE${FTP_PORT:+:$FTP_PORT}"
# Le mot de passe passe par l'environnement, jamais sur la ligne de commande (visible dans la liste des processus)
export LFTP_PASSWORD="$FTP_MOT_DE_PASSE"
lftp -c "set net:max-retries 2; set net:timeout 20; $SSL
open --env-password -u '$FTP_UTILISATEUR' '$CIBLE';
$MIRROR '$SCRIPT_DIR/app/' '$FTP_DOSSIER'" || { echo "ERREUR : envoi FTP interrompu"; exit 1; }
unset LFTP_PASSWORD

if [[ $ESSAI -eq 1 ]]; then
    echo "Essai terminé : rien n'a été modifié"
    exit 0
fi

######################### Vérification
if [[ -n "${SITE_ADRESSE:-}" ]]; then
    # La première visite applique les mises à jour de la base ; un site neuf renvoie vers l'assistant d'installation
    read -r CODE FINALE < <(curl -s -L -o /dev/null -w '%{http_code} %{url_effective}' --max-time 60 "$SITE_ADRESSE/")
    if [[ "$CODE" != "200" ]]; then
        echo "ATTENTION : le site répond $CODE ; vérifier $SITE_ADRESSE"
        exit 1
    fi
    if [[ "$FINALE" == */installation* ]]; then
        echo "Site en ligne : terminer l'installation sur $FINALE"
    else
        echo "Site en ligne ($SITE_ADRESSE), base de données à jour"
    fi
fi
echo "Déploiement terminé"
