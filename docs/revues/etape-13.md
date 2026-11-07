# Relecture du panel : étape 13 (second facteur sans application : clés FIDO2, code par e-mail)

Captures : `docs/revues/etape-13/`.

| Personnage | Retour | Décision |
|---|---|---|
| Thomas (sécurité) | Vérification WebAuthn écrite sans bibliothèque : domaine et origine tirés de l'adresse du site (jamais de la requête), défi à usage unique de 5 minutes, signature vérifiée, compteur contre la copie de clé ; testée avec un authentificateur simulé (signature altérée refusée, autre appareil refusé) | Rien à changer |
| Thomas (sécurité) | Le secours par e-mail rend la sécurité égale à celle de la boîte mail | Choix assumé (demande du responsable) ; code de 6 chiffres, 10 minutes, 5 essais, 3 envois par 15 minutes |
| Thomas (sécurité) | Quelqu'un qui n'a que le mot de passe ne doit pas pouvoir ajouter sa propre clé | Ajouter ou retirer une clé, activer l'application, changer le mot de passe : deux preuves exigées pour les comptes qui gèrent |
| Sophie (données) | Aucune donnée biométrique ne quitte l'appareil : seule une clé publique est gardée | Rien à changer |
| Monique (74 ans) | « Utiliser ma clé de sécurité », sinon « Je n'ai pas ma clé : code par e-mail » | Corrigé : après « M'envoyer un code », la zone du code reste ouverte |
| Paul (simplicité) | Plus d'application obligatoire sur le téléphone | Rien à changer |
