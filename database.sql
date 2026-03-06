-- OMNES MARKETPLACE — Base de données
-- Compatible MySQL 8.0+ / MariaDB 10.4+
-- Après import, exécuter setup_passwords.php pour générer les vrais hashes bcrypt

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS omnes_marketplace;
CREATE DATABASE omnes_marketplace
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE omnes_marketplace;

-- ────────────────────────────────
--  TABLES UTILISATEURS
-- ────────────────────────────────

CREATE TABLE Administrateur (
  admin_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom           VARCHAR(80)  NOT NULL,
  nom              VARCHAR(80)  NOT NULL,
  email            VARCHAR(180) NOT NULL UNIQUE,
  mot_de_passe     VARCHAR(255) NOT NULL,
  date_inscription DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE Vendeur (
  vendeur_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom           VARCHAR(80)  NOT NULL,
  nom              VARCHAR(80)  NOT NULL,
  email            VARCHAR(180) NOT NULL UNIQUE,
  mot_de_passe     VARCHAR(255) NOT NULL,
  telephone        VARCHAR(20),
  pseudo           VARCHAR(100) DEFAULT NULL,
  description      TEXT,
  note_moyenne     DECIMAL(3,2) DEFAULT 0,
  date_inscription DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE Acheteur (
  acheteur_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom             VARCHAR(80)  NOT NULL,
  nom                VARCHAR(80)  NOT NULL,
  email              VARCHAR(180) NOT NULL UNIQUE,
  mot_de_passe       VARCHAR(255) NOT NULL,
  telephone          VARCHAR(20),
  adresse_livraison  TEXT,
  date_inscription   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ────────────────────────────────
--  ARTICLE
-- ────────────────────────────────

CREATE TABLE Article (
  article_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendeur_id          INT UNSIGNED NOT NULL,
  titre               VARCHAR(255) NOT NULL,
  description         TEXT,
  categorie           ENUM('regular','haut_de_gamme','rare') NOT NULL DEFAULT 'regular',
  sous_categorie      VARCHAR(80)  DEFAULT 'autre',
  etat                ENUM('neuf','tres_bon','bon','correct') DEFAULT 'bon',
  type_immediat       TINYINT(1)   NOT NULL DEFAULT 0,
  type_enchere        TINYINT(1)   NOT NULL DEFAULT 0,
  type_nego           TINYINT(1)   NOT NULL DEFAULT 0,
  prix_immediat       DECIMAL(10,2),
  prix_depart_enchere DECIMAL(10,2),
  prix_depart_nego    DECIMAL(10,2),
  image_url           VARCHAR(500),
  statut              ENUM('disponible','reserve','vendu','retire') DEFAULT 'disponible',
  date_creation       DATETIME DEFAULT CURRENT_TIMESTAMP,
  date_modification   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (vendeur_id) REFERENCES Vendeur(vendeur_id) ON DELETE CASCADE,
  INDEX idx_statut    (statut),
  INDEX idx_categorie (categorie),
  INDEX idx_vendeur   (vendeur_id)
) ENGINE=InnoDB;

-- ────────────────────────────────
--  ENCHÈRES
-- ────────────────────────────────

CREATE TABLE Enchere (
  enchere_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id   INT UNSIGNED NOT NULL UNIQUE,
  prix_depart  DECIMAL(10,2) NOT NULL,
  date_fin     DATETIME     NOT NULL,
  statut       ENUM('en_cours','terminee','annulee') DEFAULT 'en_cours',
  FOREIGN KEY (article_id) REFERENCES Article(article_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Asso17 (
  offre_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enchere_id    INT UNSIGNED NOT NULL,
  acheteur_id   INT UNSIGNED NOT NULL,
  montant_offre DECIMAL(10,2) NOT NULL,
  est_gagnant   TINYINT(1)   DEFAULT 0,
  date_offre    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enchere_id)  REFERENCES Enchere(enchere_id)   ON DELETE CASCADE,
  FOREIGN KEY (acheteur_id) REFERENCES Acheteur(acheteur_id) ON DELETE CASCADE,
  INDEX idx_enchere  (enchere_id),
  INDEX idx_acheteur (acheteur_id)
) ENGINE=InnoDB;

-- ────────────────────────────────
--  NÉGOCIATION
-- ────────────────────────────────

CREATE TABLE Negociation (
  negoc_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id   INT UNSIGNED NOT NULL,
  acheteur_id  INT UNSIGNED NOT NULL,
  vendeur_id   INT UNSIGNED NOT NULL,
  prix_propose DECIMAL(10,2) NOT NULL,
  message      TEXT,
  nb_tours     TINYINT UNSIGNED DEFAULT 1,
  statut       ENUM('en_cours','en_attente','acceptee','refusee','terminee') DEFAULT 'en_cours',
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (article_id)  REFERENCES Article(article_id)   ON DELETE CASCADE,
  FOREIGN KEY (acheteur_id) REFERENCES Acheteur(acheteur_id) ON DELETE CASCADE,
  FOREIGN KEY (vendeur_id)  REFERENCES Vendeur(vendeur_id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────
--  PANIER
-- ────────────────────────────────

CREATE TABLE Panier (
  panier_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  acheteur_id   INT UNSIGNED NOT NULL UNIQUE,
  date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (acheteur_id) REFERENCES Acheteur(acheteur_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Contenir (
  panier_id   INT UNSIGNED NOT NULL,
  article_id  INT UNSIGNED NOT NULL,
  prix_final  DECIMAL(10,2) NOT NULL,
  date_ajout  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (panier_id, article_id),
  FOREIGN KEY (panier_id)  REFERENCES Panier(panier_id)   ON DELETE CASCADE,
  FOREIGN KEY (article_id) REFERENCES Article(article_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────
--  COMMANDE
-- ────────────────────────────────

CREATE TABLE Commande (
  commande_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  acheteur_id       INT UNSIGNED NOT NULL,
  reference         VARCHAR(20)  NOT NULL UNIQUE,
  montant           DECIMAL(10,2) NOT NULL,
  adresse_livraison TEXT,
  mode_livraison    ENUM('standard','express','retrait') DEFAULT 'standard',
  statut            ENUM('confirmee','en_preparation','expediee','livree','annulee') DEFAULT 'confirmee',
  date_commande     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (acheteur_id) REFERENCES Acheteur(acheteur_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Commander (
  commande_id INT UNSIGNED NOT NULL,
  article_id  INT UNSIGNED NOT NULL,
  prix_achat  DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (commande_id, article_id),
  FOREIGN KEY (commande_id) REFERENCES Commande(commande_id) ON DELETE CASCADE,
  FOREIGN KEY (article_id)  REFERENCES Article(article_id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────
--  NOTIFICATIONS
-- ────────────────────────────────

CREATE TABLE Notification (
  notif_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  acheteur_id   INT UNSIGNED,
  vendeur_id    INT UNSIGNED,
  type_notif    ENUM('enchere','nego','commande','systeme') NOT NULL DEFAULT 'systeme',
  message       TEXT NOT NULL,
  lu            TINYINT(1) DEFAULT 0,
  date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (acheteur_id) REFERENCES Acheteur(acheteur_id) ON DELETE CASCADE,
  FOREIGN KEY (vendeur_id)  REFERENCES Vendeur(vendeur_id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────
--  COMPTES (hashes temporaires — exécuter setup_passwords.php après import !)
--  Mots de passe : admin123 / vendeur123 / acheteur123
-- ────────────────────────────────

INSERT INTO Administrateur (prenom, nom, email, mot_de_passe) VALUES
('Admin', 'OmnesMarket', 'admin@omnes.fr',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO Vendeur (prenom, nom, email, mot_de_passe, telephone, pseudo, description) VALUES
('Ndeye', 'Cheikh',  'ndeye.cheikh@omnes.fr',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+33 6 10 20 30 40', 'BoutiqueNdeye', 'Vendeuse spécialisée en mode et accessoires tendance.'),
('Awa',   'Traore',  'awa.traore@omnes.fr',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+33 7 50 60 70 80', 'AwaTech',       'Passionnée de technologie et objets high-tech.');

INSERT INTO Acheteur (prenom, nom, email, mot_de_passe, telephone, adresse_livraison) VALUES
('Yaye', 'Diarra', 'yaye.diarra@omnes.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+33 6 99 88 77 66', '10 rue de la Paix, 75001 Paris');

-- Panier pour l'acheteur
INSERT INTO Panier (acheteur_id) VALUES (1);

-- Notifications de bienvenue
INSERT INTO Notification (acheteur_id, type_notif, message, lu) VALUES
(1, 'systeme', 'Bienvenue sur Omnes MarketPlace ! Explorez les articles disponibles.', 0);

INSERT INTO Notification (vendeur_id, type_notif, message) VALUES
(1, 'systeme', 'Votre compte vendeur est actif. Publiez vos premiers articles !'),
(2, 'systeme', 'Votre compte vendeur est actif. Publiez vos premiers articles !');

SET FOREIGN_KEY_CHECKS = 1;

-- ────────────────────────────────
--  VUES
-- ────────────────────────────────

CREATE OR REPLACE VIEW v_articles_actifs AS
SELECT
  a.*,
  v.prenom AS v_prenom, v.nom AS v_nom, v.email AS v_email,
  e.enchere_id, e.date_fin,
  (SELECT MAX(ao.montant_offre) FROM Asso17 ao WHERE ao.enchere_id = e.enchere_id) AS prix_actuel,
  (SELECT COUNT(*) FROM Asso17 ao WHERE ao.enchere_id = e.enchere_id)              AS nb_offres
FROM Article a
JOIN Vendeur v ON v.vendeur_id = a.vendeur_id
LEFT JOIN Enchere e ON e.article_id = a.article_id
WHERE a.statut = 'disponible';

CREATE OR REPLACE VIEW v_stats AS
SELECT
  (SELECT COUNT(*) FROM Article WHERE statut = 'disponible') AS total_articles,
  (SELECT COUNT(*) FROM Vendeur)                              AS total_vendeurs,
  (SELECT COUNT(*) FROM Acheteur)                             AS total_membres,
  (SELECT COUNT(*) FROM Enchere WHERE statut = 'en_cours')    AS encheres_actives,
  (SELECT COUNT(*) FROM Article WHERE statut = 'vendu')       AS total_ventes;
