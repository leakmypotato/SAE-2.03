-- ============================================================
--  SAE 2.03 - Base de donnees IPAM (centralisee)
--  Structure uniquement - les donnees sont inserees par init.py
--  Usage : mysql -u root < ipam.sql
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
