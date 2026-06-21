-- ============================================================
--  SAE 2.03 - Base de donnees IPAM (centralisee)
--  A executer dans phpMyAdmin ou : mysql -u root -p < ipam.sql
-- ============================================================

DROP DATABASE IF EXISTS ipam;
CREATE DATABASE ipam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ipam;

-- ---------- Table SITE ----------
CREATE TABLE site (
    id_site   INT AUTO_INCREMENT PRIMARY KEY,
    nom_site  VARCHAR(50) NOT NULL,
    groupe    INT NOT NULL                 -- 1, 2 ou 3
);

-- ---------- Table ROUTEUR_PE ----------
CREATE TABLE routeur_pe (
    id_routeur_pe     INT AUTO_INCREMENT PRIMARY KEY,
    id_site           INT NOT NULL,
    nom               VARCHAR(100) NOT NULL,
    modele            VARCHAR(100) DEFAULT 'Cisco 4221',
    adresse_loopback  VARCHAR(20),
    CONSTRAINT fk_pe_site FOREIGN KEY (id_site) REFERENCES site(id_site)
);

-- ---------- Table PLAGE_ADRESSES ----------
CREATE TABLE plage_adresses (
    id_plage  INT AUTO_INCREMENT PRIMARY KEY,
    id_site   INT NOT NULL,
    reseau    VARCHAR(20) NOT NULL,        -- ex: 164.166.1.0
    prefixe   INT NOT NULL DEFAULT 24,
    CONSTRAINT fk_plage_site FOREIGN KEY (id_site) REFERENCES site(id_site)
);

-- ---------- Table SOUS_RESEAU ----------
CREATE TABLE sous_reseau (
    id_sous_reseau  INT AUTO_INCREMENT PRIMARY KEY,
    id_plage        INT NOT NULL,
    adresse_reseau  VARCHAR(20) NOT NULL,  -- ex: 164.166.1.16
    premiere_ip     VARCHAR(20) NOT NULL,  -- ex: 164.166.1.17 (1ere adresse utilisable)
    prefixe         INT NOT NULL DEFAULT 28,
    etat            ENUM('libre','reserve','alloue') NOT NULL DEFAULT 'libre',
    CONSTRAINT fk_sr_plage FOREIGN KEY (id_plage) REFERENCES plage_adresses(id_plage)
);

-- ---------- Table CLIENT ----------
CREATE TABLE client (
    id_client       INT AUTO_INCREMENT PRIMARY KEY,   -- = n VLAN = n sous-interface
    id_sous_reseau  INT NOT NULL,
    id_routeur_pe   INT NOT NULL,
    nom             VARCHAR(100) NOT NULL,            -- = nom de la VRF
    vlan            INT NOT NULL,
    nom_vrf         VARCHAR(100) NOT NULL,
    date_creation   DATE NOT NULL,
    CONSTRAINT fk_cli_sr FOREIGN KEY (id_sous_reseau) REFERENCES sous_reseau(id_sous_reseau),
    CONSTRAINT fk_cli_pe FOREIGN KEY (id_routeur_pe)  REFERENCES routeur_pe(id_routeur_pe)
);

-- ============================================================
--  DONNEES DE DEPART
-- ============================================================

-- 3 sites
INSERT INTO site (id_site, nom_site, groupe) VALUES
 (1, 'Site Groupe 1', 1),
 (2, 'Site Groupe 2', 2),
 (3, 'Site Groupe 3', 3);

-- 1 routeur PE par site
INSERT INTO routeur_pe (id_routeur_pe, id_site, nom, modele, adresse_loopback) VALUES
 (1, 1, 'PE1', 'Cisco 4221', '10.255.0.1'),
 (2, 2, 'PE2', 'Cisco 4221', '10.255.0.2'),
 (3, 3, 'PE3', 'Cisco 4221', '10.255.0.3');

-- 1 pool /24 par site
INSERT INTO plage_adresses (id_plage, id_site, reseau, prefixe) VALUES
 (1, 1, '164.166.1.0', 24),
 (2, 2, '164.166.2.0', 24),
 (3, 3, '164.166.3.0', 24);

-- Decoupage automatique de chaque /24 en 16 sous-reseaux /28

INSERT INTO sous_reseau (id_plage, adresse_reseau, premiere_ip, prefixe, etat) VALUES
 (1, '164.166.1.0', '164.166.1.1', 28, 'libre'),
 (1, '164.166.1.16', '164.166.1.17', 28, 'libre'),
 (1, '164.166.1.32', '164.166.1.33', 28, 'libre'),
 (1, '164.166.1.48', '164.166.1.49', 28, 'libre'),
 (1, '164.166.1.64', '164.166.1.65', 28, 'libre'),
 (1, '164.166.1.80', '164.166.1.81', 28, 'libre'),
 (1, '164.166.1.96', '164.166.1.97', 28, 'libre'),
 (1, '164.166.1.112', '164.166.1.113', 28, 'libre'),
 (1, '164.166.1.128', '164.166.1.129', 28, 'libre'),
 (1, '164.166.1.144', '164.166.1.145', 28, 'libre'),
 (1, '164.166.1.160', '164.166.1.161', 28, 'libre'),
 (1, '164.166.1.176', '164.166.1.177', 28, 'libre'),
 (1, '164.166.1.192', '164.166.1.193', 28, 'libre'),
 (1, '164.166.1.208', '164.166.1.209', 28, 'libre'),
 (1, '164.166.1.224', '164.166.1.225', 28, 'libre'),
 (1, '164.166.1.240', '164.166.1.241', 28, 'libre'),
 (2, '164.166.2.0', '164.166.2.1', 28, 'libre'),
 (2, '164.166.2.16', '164.166.2.17', 28, 'libre'),
 (2, '164.166.2.32', '164.166.2.33', 28, 'libre'),
 (2, '164.166.2.48', '164.166.2.49', 28, 'libre'),
 (2, '164.166.2.64', '164.166.2.65', 28, 'libre'),
 (2, '164.166.2.80', '164.166.2.81', 28, 'libre'),
 (2, '164.166.2.96', '164.166.2.97', 28, 'libre'),
 (2, '164.166.2.112', '164.166.2.113', 28, 'libre'),
 (2, '164.166.2.128', '164.166.2.129', 28, 'libre'),
 (2, '164.166.2.144', '164.166.2.145', 28, 'libre'),
 (2, '164.166.2.160', '164.166.2.161', 28, 'libre'),
 (2, '164.166.2.176', '164.166.2.177', 28, 'libre'),
 (2, '164.166.2.192', '164.166.2.193', 28, 'libre'),
 (2, '164.166.2.208', '164.166.2.209', 28, 'libre'),
 (2, '164.166.2.224', '164.166.2.225', 28, 'libre'),
 (2, '164.166.2.240', '164.166.2.241', 28, 'libre'),
 (3, '164.166.3.0', '164.166.3.1', 28, 'libre'),
 (3, '164.166.3.16', '164.166.3.17', 28, 'libre'),
 (3, '164.166.3.32', '164.166.3.33', 28, 'libre'),
 (3, '164.166.3.48', '164.166.3.49', 28, 'libre'),
 (3, '164.166.3.64', '164.166.3.65', 28, 'libre'),
 (3, '164.166.3.80', '164.166.3.81', 28, 'libre'),
 (3, '164.166.3.96', '164.166.3.97', 28, 'libre'),
 (3, '164.166.3.112', '164.166.3.113', 28, 'libre'),
 (3, '164.166.3.128', '164.166.3.129', 28, 'libre'),
 (3, '164.166.3.144', '164.166.3.145', 28, 'libre'),
 (3, '164.166.3.160', '164.166.3.161', 28, 'libre'),
 (3, '164.166.3.176', '164.166.3.177', 28, 'libre'),
 (3, '164.166.3.192', '164.166.3.193', 28, 'libre'),
 (3, '164.166.3.208', '164.166.3.209', 28, 'libre'),
 (3, '164.166.3.224', '164.166.3.225', 28, 'libre'),
 (3, '164.166.3.240', '164.166.3.241', 28, 'libre');
