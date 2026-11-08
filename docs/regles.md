# Règles

Quelques règles générales, valables pour toute installation. Le détail vit dans le code et les tests ; [decisions.md](decisions.md) explique les choix de conception, [personas.md](personas.md) dit qui relit quoi.

## Qui peut faire quoi, et par quel moyen

Trois moyens d'agir :
- **le site**, dans le navigateur ;
- **l'API et le MCP**, avec une clé d'API personnelle ;
- **le serveur** (hébergeur, ligne de commande), pour l'installation et les mises à jour.

Règles :
1. **Une clé d'API n'a jamais plus de droits que son propriétaire.** Elle a une portée (lecture, écriture, administration), une durée limitée, et elle est révocable.
2. **Par l'API, on peut faire ce que permettent ses droits sur le site, sauf toucher à la sécurité d'un compte**, même avec les droits maximums. La sécurité d'un compte, c'est son mot de passe, sa double authentification, ses clés de sécurité et ses clés d'API. Elle se règle seulement dans le site, par la personne elle-même ou par un administrateur.
3. **Ce qui ne se défait pas demande une confirmation explicite** : retaper le nom ou l'e-mail. Effacer un membre ou supprimer un espace est réservé à l'administrateur.
4. **Tout droit de gestion impose la double authentification.**
5. **Toute action de gestion laisse une trace** : le journal, et l'historique du compte concerné.
6. **Les comptes sont individuels.** Un compte de démonstration est l'exception : il est marqué comme tel, et son mot de passe est fixé par un administrateur. Ses utilisateurs ne peuvent changer ni ce mot de passe ni la sécurité du compte.

## Données
- Le minimum, effacé au plus tôt, avec des durées réglables.
- Le site public ne dit jamais où ni comment sont conservées les données des membres.
- Aucune adresse e-mail ni aucun numéro de téléphone publiés : le contact passe par le formulaire.

## Développement
- Tout est réglable par chaque association : rien de propre à une installation n'est écrit en dur.
- Testé sur la même base que la production (MySQL), avant toute fusion. La chaîne est : branche → dev → preprod → main.
- Ce qui se voit est relu par les personas concernés.
