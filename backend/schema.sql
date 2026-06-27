-- =============================================================
-- SCHÉMA DE LA BASE DE DONNÉES — RS Planner (SQLite)
-- Géré par : DAREL
-- =============================================================

-- Suppression des tables (ordre inverse des dépendances)
DROP TABLE IF EXISTS competences_projet;
DROP TABLE IF EXISTS utilisateurs_competences;
DROP TABLE IF EXISTS membres_projet;
DROP TABLE IF EXISTS taches;
DROP TABLE IF EXISTS projets;
DROP TABLE IF EXISTS competences;
DROP TABLE IF EXISTS utilisateurs;

-- -------------------------------------------------------------
-- TABLE : utilisateurs
-- -------------------------------------------------------------
CREATE TABLE utilisateurs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nom         TEXT    NOT NULL,
    email       TEXT    NOT NULL UNIQUE,
    mot_de_passe TEXT   NOT NULL,  -- Stocké hashé (password_hash)
    photo       TEXT    DEFAULT NULL,
    bio         TEXT    DEFAULT NULL,
    cree_le     TEXT    DEFAULT (datetime('now'))
);

-- -------------------------------------------------------------
-- TABLE : competences (référentiel global)
-- -------------------------------------------------------------
CREATE TABLE competences (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    nom  TEXT    NOT NULL UNIQUE   -- ex: "React", "PHP", "Design"
);

-- Données de départ
INSERT INTO competences (nom) VALUES
    ('React'), ('PHP'), ('Python'), ('Design UI'), ('Base de données'),
    ('DevOps'), ('Node.js'), ('Gestion de projet');

-- -------------------------------------------------------------
-- TABLE : utilisateurs_competences  (liaison N-N)
-- -------------------------------------------------------------
CREATE TABLE utilisateurs_competences (
    id_utilisateur INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    id_competence  INTEGER NOT NULL REFERENCES competences(id)  ON DELETE CASCADE,
    niveau         TEXT    NOT NULL CHECK(niveau IN ('Débutant', 'Intermédiaire', 'Expert')),
    PRIMARY KEY (id_utilisateur, id_competence)
);

-- -------------------------------------------------------------
-- TABLE : projets
-- -------------------------------------------------------------
CREATE TABLE projets (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    id_proprietaire INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    titre           TEXT    NOT NULL,
    description     TEXT    DEFAULT NULL,
    statut          TEXT    NOT NULL DEFAULT 'En cours'
                            CHECK(statut IN ('En cours', 'Terminé', 'En pause')),
    cree_le         TEXT    DEFAULT (datetime('now'))
);

-- -------------------------------------------------------------
-- TABLE : membres_projet  (liaison N-N avec couleur)
-- -------------------------------------------------------------
CREATE TABLE membres_projet (
    id_projet      INTEGER NOT NULL REFERENCES projets(id)      ON DELETE CASCADE,
    id_utilisateur INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    couleur        TEXT    NOT NULL DEFAULT '#3498db',  -- Couleur unique par membre
    statut         TEXT    NOT NULL DEFAULT 'en_attente'
                            CHECK(statut IN ('en_attente', 'accepte', 'refuse')),
    PRIMARY KEY (id_projet, id_utilisateur)
);

-- -------------------------------------------------------------
-- TABLE : taches
-- -------------------------------------------------------------
CREATE TABLE taches (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    id_projet     INTEGER NOT NULL REFERENCES projets(id) ON DELETE CASCADE,
    id_parent     INTEGER DEFAULT NULL REFERENCES taches(id) ON DELETE SET NULL,
    id_assigne    INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    titre         TEXT    NOT NULL,
    description   TEXT    DEFAULT NULL,
    date_echeance TEXT    DEFAULT NULL,  -- Format ISO : 'YYYY-MM-DD'
    statut        TEXT    NOT NULL DEFAULT 'À faire'
                          CHECK(statut IN ('À faire', 'En cours', 'Terminé')),
    cree_le       TEXT    DEFAULT (datetime('now'))
);

-- -------------------------------------------------------------
-- TABLE : notifications
-- -------------------------------------------------------------
CREATE TABLE notifications (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    id_utilisateur INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    type           TEXT    NOT NULL,
    titre          TEXT    NOT NULL,
    message        TEXT    NOT NULL,
    lien           TEXT    DEFAULT NULL,
    lu             INTEGER DEFAULT 0,
    cree_le        TEXT    DEFAULT (datetime('now'))
);

-- -------------------------------------------------------------
-- TABLE : competences_projet  (besoins du projet pour le matching)
-- -------------------------------------------------------------
CREATE TABLE competences_projet (
    id_projet     INTEGER NOT NULL REFERENCES projets(id)    ON DELETE CASCADE,
    id_competence INTEGER NOT NULL REFERENCES competences(id) ON DELETE CASCADE,
    PRIMARY KEY (id_projet, id_competence)
);

-- -------------------------------------------------------------
-- TABLE : activites (flux d'activité des projets)
-- -------------------------------------------------------------
CREATE TABLE activites (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    id_projet      INTEGER NOT NULL REFERENCES projets(id) ON DELETE CASCADE,
    id_utilisateur INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    description    TEXT    NOT NULL,
    cree_le        TEXT    DEFAULT (datetime('now'))
);
