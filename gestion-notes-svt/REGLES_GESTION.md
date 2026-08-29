# Règles de gestion et de calcul — PV S1/S2 Licence d'Éducation SVT

Ce document synthétise l'analyse du classeur `PV_S1S2_LE_SVT_2024_2025.xlsm` (macros VBA +
formules Excel) et sert de spécification pour l'application PHP/MySQL qui remplace le classeur.

## 1. Structure du classeur analysé

| Feuille | Contenu |
|---|---|
| `M1` … `M6` | Une feuille par module (Biologie cellulaire, Histologie et Embryologie, Géologie générale, Mathématiques, Physique, Chimie). Une ligne par étudiant, notes TP/CC/Examen, moyenne, décision, rattrapage, note finale, décision finale. |
| `M7` | Module composite « Méthodologie du travail universitaire, Digital Skills et Langue 1 », découpé en 3 éléments (dont l'élément Langue = moyenne Français/Anglais). |
| `Pv S1` | Procès-verbal de synthèse du semestre : reprend pour chaque étudiant les résultats des 7 modules et calcule la moyenne et la décision du semestre. |

Identifiants étudiant utilisés : N° d'ordre, CIN, CNE/Massar, Nom, Prénom.

## 2. Macros VBA présentes dans le fichier

Le projet VBA (`xl/vbaProject.bin`) contient 3 modules : `ThisWorkbook` (vide), `Feuil8` (vide) et
`Module1`, qui expose réellement seulement deux fonctions :

- **`CompterValeursCommunes(plageValeurs)`** : parcourt une plage de cellules par pas de 3 et
  compte celles dont la valeur est `"NV"`, `"NE"`, `"ABS"` ou `"NC"`. C'est le compteur de
  "modules non validés" utilisé sur la feuille `Pv S1` (colonne `AD`, "Module NV").
- **`GetNamesModules(plage_decisions, plage_references, plage_modules)`** : fonction utilitaire
  de diagnostic (liste les noms de modules correspondant à des décisions données) — elle n'est
  appelée par **aucune** cellule du classeur actuel.

### 2.1 Constat important : macros manquantes / classeur cassé

Les formules de la feuille `Pv S1` appellent en réalité **quatre fonctions supplémentaires** qui
n'existent plus dans le projet VBA actuel :

- `Calcul_Note_Semestre(...)`
- `Calcul_Decision_Semestre(...)`
- `Calcul_Note_Annee(...)`
- `Calcul_Decision_Annee(...)`

Ces noms — ainsi que des variables associées (`Nb_Modules_NV`, `Nb_Modules_NV_S1`,
`Nb_Modules_NV_S2`, `noteS1`, `noteS2`, `note_annee`…) et des feuilles fantômes (`Feuil9` à
`Feuil18`, correspondant probablement à un semestre 2 et à un PV annuel) — subsistent uniquement
dans la table interne des identifiants VBA (héritage d'une version antérieure/plus complète du
classeur), mais leur **code source et leur p-code compilé ont disparu**. Résultat : toutes les
cellules qui en dépendent renvoient `#VALUE!` ou `#REF!` dans le fichier actuel — le PV de
semestre n'est aujourd'hui plus calculable dans Excel sans macros.

C'est la démonstration concrète de la fragilité du système actuel (couplage à un classeur unique,
macros non versionnées, aucune sauvegarde du code source) et la justification principale du
passage à une application PHP/MySQL.

### 2.2 Bug identifié dans le calcul de la note finale après rattrapage

Sur les feuilles `M1`, `M2`, `M3`, `M5`, `M6`, la formule de la colonne **Note Finale** est :

```
=IF(OR(AND(F="",G="",H=""),AND(H="",J="")),"",
   IF(OR(ISTEXT(J),J=""),H,
      IF(AND(ISTEXT(H),ISTEXT(J)),"ABS",
         IF(AND(ISNUMBER(H),H>=10),H,
            IF(OR(ISTEXT(F),F=""),MIN(10,(EXAM*MAX(G,J))),
               MIN(10,(CC*F+EXAM*MAX(G,J))))))))
```

où `J` est la colonne **Décision** (toujours du texte `"V"` ou `"R"`), et non la colonne **K —
Rattrapage** qui contient la note de rattrapage. Comme `J` contient toujours du texte,
`ISTEXT(J)` est **toujours vrai** : la formule retourne systématiquement `H` (la note d'examen de
la session normale) et **ignore complètement la note de rattrapage saisie en colonne K**. Vérifié
sur les données réelles (ex. `M1!L12` = 5.25 = la note d'examen, alors que la note de rattrapage
saisie en `K12` est 13.5).

➜ Dans l'application PHP, ce bug **n'est pas reproduit** : la note de rattrapage saisie est bien
prise en compte (voir §4.3). Ce point est un choix de correction assumé, à valider avec le
responsable pédagogique.

## 3. Règles de calcul par module (feuilles M1–M7)

### 3.1 Pondérations (spécifiques à chaque module)

| Module | Intitulé | TP | CC | Examen |
|---|---|---|---|---|
| M1 | Biologie cellulaire | 0.2 | 0.2 | 0.6 |
| M2 | Histologie et Embryologie | 0.2 | 0.2 | 0.6 |
| M3 | Géologie générale | 0.2 | 0.2 | 0.6 |
| M4 | Mathématiques | — | 0.4 | 0.6 |
| M5 | Physique | 0.2 | 0.2 | 0.6 |
| M6 | Chimie | 0.2 | 0.2 | 0.6 |
| M7 | Méthodologie / Digital Skills / Langue 1 | — | 0.4 (par élément) | 0.6 (par élément) |

M4 n'a pas de composante TP. M7 est composé de 3 éléments, chacun noté CC 40% / Examen 60% ; la
"Langue 1" est elle-même la moyenne de Langue Française et Langue Anglaise (chacune CC 40% /
Examen 60%). La moyenne du module M7 est la moyenne simple des 3 éléments.

### 3.2 Moyenne du module (session normale)

```
moyenne = (TP × poids_TP) + (CC × poids_CC) + (Examen × poids_Examen)
```

Une note de composante non saisie est traitée comme 0 dans la moyenne (comportement Excel des
cellules vides dans une multiplication), sauf si **aucune** composante n'est saisie : dans ce cas
la moyenne reste "non renseignée" (pas de décision).

### 3.3 Décision initiale

```
décision_initiale = "V" (validé)   si moyenne >= 10
                   = "R" (à rattraper) sinon
```

### 3.4 Rattrapage et note finale (règle corrigée, cf. §2.2)

Seuls les étudiants en décision `"R"` passent le rattrapage.

```
si décision_initiale = "V" :
    note_finale = moyenne (le rattrapage n'a pas lieu d'être)
sinon si aucune note de rattrapage saisie :
    note_finale = moyenne   (candidat non présenté / pas encore rattrapé)
sinon :
    note_finale = MIN(10, MAX(moyenne, note_rattrapage))
```

Le plafond à 10 reprend le `MIN(10, …)` déjà présent dans les formules originales : la session de
rattrapage ne peut pas faire dépasser la moyenne du module au-delà de la moyenne "plancher" de
validation.

### 3.5 Décision finale du module

```
si moyenne >= 10            -> "V"    (Validé)
sinon si note_finale >= 10  -> "VAR"  (Validé Après Rattrapage)
sinon si note_finale >= 7   -> "NV"   (Non Validé, compensable)
sinon                       -> "NE"   (Non Validé, éliminatoire)
```

Ces 4 codes et ces seuils (10 et 7) sont repris tels quels des formules `M` de chaque feuille
module. Le seuil de 7 (`NV` vs `NE`) est un paramètre configurable dans l'application
(table `parametres`), au cas où il varierait selon la filière/l'année.

## 4. Règles de calcul du semestre (feuille "Pv S1")

**Important : cette partie de la logique originale n'est pas reconstituable** (fonctions VBA
manquantes, cf. §2.1). Les règles ci-dessous sont une **reconstruction raisonnée**, déduite :

- des en-têtes de colonnes de `Pv S1` (Moyenne / Décision / Année de Val par module, "Module NV"),
- du comportement de `CompterValeursCommunes` (compte des décisions `NV`/`NE`/`ABS`/`NC`),
- de la formule survivante `=IF(AND(C=…,…="NV",AB="V"),"VPC", …)` qui indique clairement qu'un
  module `"NV"` est requalifié `"VPC"` (Validé Par Compensation) dès lors que le semestre est
  validé,
- des règles usuelles de compensation semestrielle des Licences d'Éducation marocaines.

Elles sont **implémentées comme paramètres modifiables** dans l'application (table `parametres`)
et doivent être confirmées par le service pédagogique avant mise en production.

### 4.1 Moyenne du semestre

```
moyenne_semestre = moyenne arithmétique simple des 7 notes finales de module
```

(Aucune pondération inter-module n'a été retrouvée dans le classeur ; hypothèse : tous les
modules du semestre comptent à poids égal.)

### 4.2 Nombre de modules non validés

```
nb_modules_NV = nombre de modules dont la décision finale ∈ {NV, NE, ABS, NC}
```

(reproduction directe de `CompterValeursCommunes`)

### 4.3 Décision du semestre

```
si moyenne_semestre >= 10 ET nb_modules_NV <= seuil_compensation (paramètre, défaut = 2) :
    décision_semestre = "V"
    -> chaque module en "NV" est réaffiché "VPC" (Validé Par Compensation) dans le PV
sinon :
    décision_semestre = "R" (semestre non validé, ajourné)
```

Un module en `"NE"` reste éliminatoire même en cas de compensation (règle usuelle ; à confirmer).

## 4bis. Simplifications assumées pour le module M7

- Le sous-détail "Langue 1 = moyenne(Langue Française, Langue Anglaise)" n'est **pas répliqué**
  tel quel : le troisième élément de M7 est saisi directement comme un élément CC(40%)/Examen(60%)
  ordinaire, comme les deux autres. Cette subdivision supplémentaire de la feuille Excel ajoutait
  de la complexité sans changer la règle de calcul du module (qui reste "moyenne simple des 3
  éléments"), et la section rattrapage correspondante était de toute façon vide dans le classeur
  analysé (cf. §3.7).
- Le rattrapage de M7 est saisi **au niveau du module** (une seule note de rattrapage, comme pour
  les autres modules) et non élément par élément : le classeur source prévoyait des colonnes de
  rattrapage par élément (`U`→`AB`) mais elles n'étaient renseignées pour aucun étudiant dans le
  fichier fourni, donc aucune règle réelle n'a pu en être extraite.

## 5. Éléments volontairement non repris dans l'application

- Le mini-tableau `AG:AJ` et la formule `EXACT(AG;C)` de la feuille `M7` servent à comparer la
  liste Massar/CNE saisie avec une liste externe importée (contrôle de cohérence ponctuel, pas
  une règle de gestion pérenne). Non répliqué : l'unicité du code Massar est garantie par une
  contrainte `UNIQUE` en base à la place.
- Les liens externes (`xl/externalLinks/*`, `[1]M12!...`) pointent vers d'autres classeurs
  introuvables (probablement les feuilles Semestre 2 / PV annuel) et ne sont pas exploitables.
- L'intitulé "Module 5 : Chimie" sur la feuille `M6` (erreur de copier-coller dans le classeur
  d'origine, doublon avec M5-Physique) est corrigé en "Module 6 : Chimie" dans les données de
  seed de l'application.
- `Pv S1!A2` affiche "Année Universitaire : 2022-2023" alors que le classeur date de 2024-2025
  (texte non mis à jour lors d'une copie du fichier d'une année sur l'autre) — l'application tire
  l'année universitaire d'un champ de données unique, ce qui élimine ce type d'incohérence.

## 6. Codes de décision utilisés dans l'application

| Code | Signification |
|---|---|
| `V` | Validé (session normale) |
| `VAR` | Validé Après Rattrapage |
| `NV` | Non Validé, compensable en cas de validation du semestre |
| `NE` | Non Validé, éliminatoire |
| `VPC` | Validé Par Compensation (affiché au niveau du PV semestre uniquement, module initialement `NV` mais semestre validé) |
