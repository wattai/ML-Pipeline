-- Schéma de la base de données : Gestion des notes et PV de semestre
-- Remplace les macros du classeur PV_S1S2_LE_SVT_2024_2025.xlsm
-- Voir REGLES_GESTION.md pour la justification des règles de calcul.

CREATE DATABASE IF NOT EXISTS gestion_notes_svt
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_notes_svt;

-- ---------------------------------------------------------------------------
-- Référentiel
-- ---------------------------------------------------------------------------

CREATE TABLE annees_universitaires (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(20) NOT NULL UNIQUE   -- ex: '2024-2025'
) ENGINE=InnoDB;

CREATE TABLE semestres (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(10) NOT NULL UNIQUE,  -- 'S1', 'S2'
    libelle VARCHAR(100) NOT NULL,
    ordre   TINYINT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE modules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    semestre_id  INT NOT NULL,
    code         VARCHAR(10) NOT NULL,     -- 'M1'..'M7'
    intitule     VARCHAR(150) NOT NULL,
    ordre        TINYINT NOT NULL,
    a_elements   TINYINT(1) NOT NULL DEFAULT 0,  -- 1 si le module est composé de plusieurs éléments (ex M7)
    FOREIGN KEY (semestre_id) REFERENCES semestres(id),
    UNIQUE KEY uniq_module (semestre_id, code)
) ENGINE=InnoDB;

CREATE TABLE elements_module (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    module_id  INT NOT NULL,
    intitule   VARCHAR(150) NOT NULL,
    ordre      TINYINT NOT NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pondérations TP/CC/Examen, rattachées soit à un module simple, soit à un élément
CREATE TABLE ponderations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    module_id        INT NULL,
    element_id       INT NULL,
    type_composante  ENUM('TP','CC','EXAM') NOT NULL,
    poids            DECIMAL(4,3) NOT NULL,  -- ex 0.200 / 0.600
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (element_id) REFERENCES elements_module(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_pond_module (module_id, type_composante),
    UNIQUE KEY uniq_pond_element (element_id, type_composante),
    CONSTRAINT chk_pond_cible CHECK (
        (module_id IS NOT NULL AND element_id IS NULL) OR
        (module_id IS NULL AND element_id IS NOT NULL)
    )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Étudiants et notes
-- ---------------------------------------------------------------------------

CREATE TABLE etudiants (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    cin                      VARCHAR(20),
    massar                   VARCHAR(20) NOT NULL UNIQUE,  -- code Massar / CNE
    nom                      VARCHAR(100) NOT NULL,
    prenom                   VARCHAR(100) NOT NULL,
    annee_universitaire_id   INT NOT NULL,
    numero_ordre             INT,
    actif                    TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (annee_universitaire_id) REFERENCES annees_universitaires(id)
) ENGINE=InnoDB;

-- Une ligne par (étudiant, module ou élément, composante, session)
CREATE TABLE notes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id         INT NOT NULL,
    module_id           INT NULL,
    element_id          INT NULL,
    type_composante     ENUM('TP','CC','EXAM') NOT NULL,
    session_evaluation  ENUM('normale','rattrapage') NOT NULL DEFAULT 'normale',
    valeur              DECIMAL(5,2) NULL,   -- NULL = non renseignée / absent
    date_saisie         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (element_id) REFERENCES elements_module(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_note_module (etudiant_id, module_id, type_composante, session_evaluation),
    UNIQUE KEY uniq_note_element (etudiant_id, element_id, type_composante, session_evaluation),
    CONSTRAINT chk_valeur_note CHECK (valeur IS NULL OR (valeur >= 0 AND valeur <= 20)),
    CONSTRAINT chk_note_cible CHECK (
        (module_id IS NOT NULL AND element_id IS NULL) OR
        (module_id IS NULL AND element_id IS NOT NULL)
    )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- Paramètres de gestion (seuils configurables, cf. REGLES_GESTION.md §3.5 et §4)
-- ---------------------------------------------------------------------------

CREATE TABLE parametres (
    cle         VARCHAR(50) PRIMARY KEY,
    valeur      VARCHAR(50) NOT NULL,
    description VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO parametres (cle, valeur, description) VALUES
    ('seuil_validation',       '10', 'Note minimale pour valider un module ou un semestre'),
    ('seuil_eliminatoire',     '7',  'Note finale minimale pour être "NV" (compensable) plutôt que "NE" (éliminatoire)'),
    ('seuil_compensation',     '2',  'Nombre maximum de modules NV/NE/ABS tolérés pour valider le semestre par compensation');

-- ---------------------------------------------------------------------------
-- Utilisateurs (accès à l'application)
-- ---------------------------------------------------------------------------

CREATE TABLE utilisateurs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    identifiant    VARCHAR(50) NOT NULL UNIQUE,
    mot_de_passe   VARCHAR(255) NOT NULL,  -- password_hash()
    nom_complet    VARCHAR(100),
    cree_le        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
