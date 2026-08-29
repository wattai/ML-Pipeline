-- Données de référence : Semestre 1, année universitaire 2024-2025
-- Licence d'Éducation - Spécialité Enseignement Secondaire - SVT
-- Pondérations reprises telles quelles du classeur Excel (voir REGLES_GESTION.md §3.1)

USE gestion_notes_svt;

INSERT INTO annees_universitaires (libelle) VALUES ('2024-2025');

INSERT INTO semestres (code, libelle, ordre) VALUES
    ('S1', 'Semestre 1', 1),
    ('S2', 'Semestre 2', 2);

SET @s1 = (SELECT id FROM semestres WHERE code = 'S1');

INSERT INTO modules (semestre_id, code, intitule, ordre, a_elements) VALUES
    (@s1, 'M1', 'Biologie cellulaire',                                              1, 0),
    (@s1, 'M2', 'Histologie et Embryologie',                                        2, 0),
    (@s1, 'M3', 'Géologie générale',                                                3, 0),
    (@s1, 'M4', 'Mathématiques',                                                    4, 0),
    (@s1, 'M5', 'Physique',                                                         5, 0),
    (@s1, 'M6', 'Chimie',                                                           6, 0),
    (@s1, 'M7', 'Méthodologie du travail universitaire, Digital Skills et Langue 1', 7, 1);

-- Pondérations des modules simples (TP 20% / CC 20% / Examen 60%)
INSERT INTO ponderations (module_id, type_composante, poids)
SELECT id, 'TP', 0.200 FROM modules WHERE semestre_id = @s1 AND code IN ('M1','M2','M3','M5','M6')
UNION ALL
SELECT id, 'CC', 0.200 FROM modules WHERE semestre_id = @s1 AND code IN ('M1','M2','M3','M5','M6')
UNION ALL
SELECT id, 'EXAM', 0.600 FROM modules WHERE semestre_id = @s1 AND code IN ('M1','M2','M3','M5','M6');

-- Mathématiques (M4) : pas de TP, CC 40% / Examen 60%
INSERT INTO ponderations (module_id, type_composante, poids)
SELECT id, 'CC', 0.400 FROM modules WHERE semestre_id = @s1 AND code = 'M4'
UNION ALL
SELECT id, 'EXAM', 0.600 FROM modules WHERE semestre_id = @s1 AND code = 'M4';

-- M7 : 3 éléments, chacun CC 40% / Examen 60%
SET @m7 = (SELECT id FROM modules WHERE semestre_id = @s1 AND code = 'M7');

INSERT INTO elements_module (module_id, intitule, ordre) VALUES
    (@m7, 'Méthodologie du travail universitaire', 1),
    (@m7, 'Digital Skills',                         2),
    (@m7, 'Langue 1',                               3);

INSERT INTO ponderations (element_id, type_composante, poids)
SELECT id, 'CC', 0.400 FROM elements_module WHERE module_id = @m7
UNION ALL
SELECT id, 'EXAM', 0.600 FROM elements_module WHERE module_id = @m7;

-- Utilisateur par défaut : identifiant "admin", mot de passe "admin123"
-- (à changer immédiatement après la première connexion)
INSERT INTO utilisateurs (identifiant, mot_de_passe, nom_complet) VALUES
    ('admin', '$2y$12$TRguLFKalDvyKte5N5LoCO3llC5Y0/xQogH1z7COAxT5SbugFGqyu', 'Administrateur');
