# Gestion des notes — Licence d'Éducation SVT

Application PHP/MySQL de gestion des notes, du rattrapage et du procès-verbal (PV) de semestre,
qui remplace le classeur Excel macros `PV_S1S2_LE_SVT_2024_2025.xlsm`.

**Voir [`REGLES_GESTION.md`](REGLES_GESTION.md) pour l'analyse complète du classeur d'origine
(structure, macros VBA, règles de calcul extraites, bugs identifiés et corrigés).**

## Pile technique

- PHP 8.1+ (testé avec PHP 8.4), PDO/MySQL, sans framework ni dépendance externe (`composer`
  non requis).
- MySQL 5.7+/MariaDB 10.3+ (testé avec MariaDB 10.11).
- Bootstrap 5 (CDN) pour l'interface.

## Installation

1. Créer la base et les tables :
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
2. Charger les données de référence (modules et pondérations du S1 2024-2025, utilisateur
   `admin` / mot de passe `admin123`) :
   ```bash
   mysql -u root -p < sql/seed_s1_2024_2025.sql
   ```
3. Configurer l'accès base de données, soit par variables d'environnement (`DB_HOST`, `DB_NAME`,
   `DB_USER`, `DB_PASS`), soit en éditant directement `config/config.php`.
4. Servir le dossier avec Apache/Nginx+PHP-FPM, ou en test local :
   ```bash
   php -S localhost:8000
   ```
5. Se connecter sur `/login.php` avec `admin` / `admin123`, puis **changer immédiatement le mot
   de passe** (directement en base : `UPDATE utilisateurs SET mot_de_passe = ... WHERE identifiant = 'admin';`
   avec un hash généré par `password_hash()`).

## Utilisation

1. **Étudiants** : ajouter les étudiants de l'année universitaire en cours.
2. **Modules → Saisie** : saisir les notes TP/Contrôle Continu/Examen (session normale) de chaque
   module. La moyenne et la décision (V/R) sont calculées automatiquement.
3. **Modules → Rattrapage** : pour les étudiants en décision "R", saisir la note de rattrapage.
   La note finale et la décision finale (V/VAR/NV/NE) sont recalculées automatiquement.
4. **PV de semestre** : synthèse automatique des 7 modules pour chaque étudiant, avec moyenne du
   semestre, nombre de modules non validés et décision du semestre (validation directe ou par
   compensation — badge "VPC"). Export CSV disponible (compatible Excel).

## Organisation du code

```
config/            connexion base de données et constantes
classes/
  CalculNotes.php     moteur de calcul pur (règles de gestion, testable sans BDD)
  Repository.php      accès aux données (PDO)
  ResultatsService.php orchestration : notes brutes -> résultats calculés
  Parametres.php      lecture des seuils configurables (table `parametres`)
includes/           gabarits communs (en-tête, pied de page, authentification, contexte)
sql/                schéma + données de départ
*.php (racine)      un contrôleur par page (pas de routeur, volontairement simple)
```

## Limites connues / évolutions possibles

- Pas de gestion de rôles (un seul niveau d'accès une fois connecté).
- Pas d'import CSV/Excel en masse des étudiants (ajout un par un pour l'instant).
- Le semestre 2 et le PV annuel ne sont pas implémentés : le classeur d'origine ne contenait plus
  les macros correspondantes (`Calcul_Note_Annee`, `Calcul_Decision_Annee`, cf.
  `REGLES_GESTION.md` §2.1) et aucune donnée S2 n'était présente dans le fichier fourni. La table
  `semestres` contient déjà "S2" et le schéma est prêt à l'accueillir dès que les modules et
  pondérations du semestre 2 seront communiqués.
- Export PV limité au CSV (pas de génération PDF).
