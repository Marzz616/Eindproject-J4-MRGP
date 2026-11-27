SET NAMES utf8mb4;
-- =============================================================
--  Voedselbank Maaskantje — databaseschema
--  Gebaseerd op EindprojectERD.mwb, uitgebreid waar de user
--  stories dat nodig maakten. Elke afwijking staat in README.md.
-- =============================================================
CREATE DATABASE IF NOT EXISTS voedselbank
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voedselbank;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS Product_has_Pakket, Pakket, Levering_has_Product, Levering,
  Afvoer, Product, Categorie, Leverancier, Gezinslid_has_Wens, Gezinslid, Klant_has_Wens,
  Wens, Klant, Rol_has_Permissie, Permissie, Gebruiker, Rol, Instelling;
DROP VIEW IF EXISTS vw_Gezinslid, vw_Klant;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
--  Rollen, rechten en gebruikers
-- ---------------------------------------------------------------
CREATE TABLE Rol (
  idRol INT AUTO_INCREMENT PRIMARY KEY,
  Naam  VARCHAR(40) NOT NULL UNIQUE,
  Omschrijving VARCHAR(160) NULL
) ENGINE=InnoDB;

-- Rechten hangen aan een ROL, niet aan een persoon. Eén plek om te
-- bepalen wat een vrijwilliger mag (US-GBR-05 t/m 09, US-SEC-01).
CREATE TABLE Permissie (
  idPermissie INT AUTO_INCREMENT PRIMARY KEY,
  Code        VARCHAR(40)  NOT NULL UNIQUE,
  Naam        VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE Rol_has_Permissie (
  Rol_idRol             INT NOT NULL,
  Permissie_idPermissie INT NOT NULL,
  PRIMARY KEY (Rol_idRol, Permissie_idPermissie),
  CONSTRAINT fk_RhP_Rol       FOREIGN KEY (Rol_idRol) REFERENCES Rol(idRol) ON DELETE CASCADE,
  CONSTRAINT fk_RhP_Permissie FOREIGN KEY (Permissie_idPermissie) REFERENCES Permissie(idPermissie) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Gebruiker (
  idGebruiker     INT AUTO_INCREMENT PRIMARY KEY,
  Naam            VARCHAR(80)  NOT NULL,
  Gebruikersnaam  VARCHAR(60)  NOT NULL UNIQUE,
  Email           VARCHAR(120) NOT NULL UNIQUE,
  Wachtwoord_hash VARCHAR(255) NOT NULL,
  Actief          BOOLEAN      NOT NULL DEFAULT TRUE,
  Laatste_login   DATETIME     NULL,
  Mislukte_pogingen TINYINT    NOT NULL DEFAULT 0,
  Rol_idRol       INT          NOT NULL,
  CONSTRAINT fk_Gebruiker_Rol FOREIGN KEY (Rol_idRol) REFERENCES Rol(idRol),
  INDEX ix_Gebruiker_actief (Actief)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
--  Klanten en gezinnen
-- ---------------------------------------------------------------
CREATE TABLE Klant (
  idKlant      INT AUTO_INCREMENT PRIMARY KEY,
  Gezinsnaam   VARCHAR(100) NOT NULL,
  Adres        VARCHAR(120) NOT NULL,
  Postcode     VARCHAR(7)   NOT NULL,
  Plaats       VARCHAR(80)  NOT NULL DEFAULT 'Maaskantje',
  Telefoon     VARCHAR(20)  NOT NULL,
  Email        VARCHAR(120) NOT NULL,
  Aanmelddatum DATE         NOT NULL,
  -- NULL = nog niet beoordeeld, 1 = geaccepteerd, 0 = afgewezen (US-KLT-12)
  Geaccepteerd BOOLEAN      NULL DEFAULT NULL,
  -- Vrije notitie van de directie: voorkeuren en afspraken die de vrijwilliger
  -- moet weten bij het samenstellen ("liever rijst dan pasta, geen koffie").
  Opmerkingen  VARCHAR(500) NULL,
  INDEX ix_Klant_postcode (Postcode),
  INDEX ix_Klant_aanmeld (Aanmelddatum),
  INDEX ix_Klant_status (Geaccepteerd)
) ENGINE=InnoDB;

CREATE TABLE Gezinslid (
  idGezinslid   INT AUTO_INCREMENT PRIMARY KEY,
  Naam          VARCHAR(80) NOT NULL,
  Geboortedatum DATE        NOT NULL,
  Relatie       ENUM('Hoofdaanvrager','Partner','Ouder (Moeder)','Ouder (Vader)','Kind','Inwonend')
                NOT NULL DEFAULT 'Kind',
  Klant_idKlant INT NOT NULL,
  CONSTRAINT fk_Gezinslid_Klant FOREIGN KEY (Klant_idKlant)
    REFERENCES Klant(idKlant) ON DELETE CASCADE,
  INDEX ix_Gezinslid_klant (Klant_idKlant)
) ENGINE=InnoDB;

CREATE TABLE Wens (
  idWens       INT AUTO_INCREMENT PRIMARY KEY,
  Naam         VARCHAR(60)  NOT NULL UNIQUE,
  Omschrijving VARCHAR(255) NULL,
  -- producten met deze eigenschap zijn NIET geschikt voor de klant
  -- Streng = een ernstige allergie: zo'n product mag het huis niet in en
  -- wordt bij het samenstellen hard geblokkeerd. Niet streng = een wens of
  -- overtuiging; dat mag wel, maar alleen met een expliciete bevestiging.
  Streng       BOOLEAN      NOT NULL DEFAULT 0,
  Conflict_tag VARCHAR(40)  NULL
) ENGINE=InnoDB;

-- dieetwens per persoon (US-KLT-07 t/m 10)
CREATE TABLE Gezinslid_has_Wens (
  Gezinslid_idGezinslid INT NOT NULL,
  Wens_idWens           INT NOT NULL,
  -- Het gezin kan laten weten dat het product tóch welkom is, bijvoorbeeld
  -- omdat alleen één gezinslid de allergie heeft en de rest het wel eet.
  -- Dat heft de blokkade op, maar alleen mét een vastgelegde reden.
  Toch_toestaan         BOOLEAN NOT NULL DEFAULT 0,
  Toelichting           VARCHAR(255) NULL,
  PRIMARY KEY (Gezinslid_idGezinslid, Wens_idWens),
  CONSTRAINT fk_GhW_Gezinslid FOREIGN KEY (Gezinslid_idGezinslid)
    REFERENCES Gezinslid(idGezinslid) ON DELETE CASCADE,
  CONSTRAINT fk_GhW_Wens FOREIGN KEY (Wens_idWens) REFERENCES Wens(idWens)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
--  Producten en voorraad
-- ---------------------------------------------------------------
CREATE TABLE Categorie (
  idCategorie  INT AUTO_INCREMENT PRIMARY KEY,
  Naam         VARCHAR(60)  NOT NULL UNIQUE,
  Kort         VARCHAR(20)  NOT NULL,          -- korte naam voor filterknoppen
  Omschrijving VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE Product (
  idProduct             INT AUTO_INCREMENT PRIMARY KEY,
  Productnaam           VARCHAR(120) NOT NULL UNIQUE,   -- US-VRD-10
  EAN                   VARCHAR(13)  NOT NULL UNIQUE,   -- US-VRD-09
  Aantal_voorraad       INT          NOT NULL DEFAULT 0 CHECK (Aantal_voorraad >= 0),
  Min_voorraad          INT          NOT NULL DEFAULT 0,
  Waarde                DECIMAL(6,2) NOT NULL DEFAULT 0.00,  -- winkelwaarde per stuk (US-RAP-02)
  Houdbaar_tot          DATE         NULL,
  Locatie               VARCHAR(30)  NULL,
  -- tags waarop een dieetconflict wordt herkend, komma-gescheiden
  Allergenen            VARCHAR(160) NULL,
  Categorie_idCategorie INT          NOT NULL,
  CONSTRAINT fk_Product_Categorie FOREIGN KEY (Categorie_idCategorie)
    REFERENCES Categorie(idCategorie),
  INDEX ix_Product_cat (Categorie_idCategorie),
  INDEX ix_Product_naam (Productnaam),
  INDEX ix_Product_tht (Houdbaar_tot)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
--  Leveranciers en leveringen
-- ---------------------------------------------------------------
CREATE TABLE Leverancier (
  idLeverancier  INT AUTO_INCREMENT PRIMARY KEY,
  Bedrijfsnaam   VARCHAR(120) NOT NULL,
  Adres          VARCHAR(120) NOT NULL,
  Postcode       VARCHAR(7)   NULL,
  Plaats         VARCHAR(80)  NULL,
  Contactpersoon VARCHAR(80)  NOT NULL,
  Email          VARCHAR(120) NOT NULL,
  Telefoon       VARCHAR(20)  NOT NULL,
  Categorie      VARCHAR(40)  NOT NULL DEFAULT 'Overig',
  Actief         BOOLEAN      NOT NULL DEFAULT TRUE,
  -- eerstvolgende levering (US-LEV-06)
  Volgende_leverdatum DATE   NULL,
  Volgende_levertijd  TIME   NULL,
  INDEX ix_Lev_actief (Actief)
) ENGINE=InnoDB;

CREATE TABLE Levering (
  idLevering                INT AUTO_INCREMENT PRIMARY KEY,
  Leverancier_idLeverancier INT  NOT NULL,
  Leverdatum                DATE NOT NULL,
  Levertijd                 TIME NULL,
  Verwerkt_door             INT  NULL,
  CONSTRAINT fk_Levering_Leverancier FOREIGN KEY (Leverancier_idLeverancier)
    REFERENCES Leverancier(idLeverancier),
  CONSTRAINT fk_Levering_Gebruiker FOREIGN KEY (Verwerkt_door)
    REFERENCES Gebruiker(idGebruiker) ON DELETE SET NULL,
  INDEX ix_Levering_datum (Leverdatum)
) ENGINE=InnoDB;

CREATE TABLE Levering_has_Product (
  Levering_idLevering INT NOT NULL,
  Product_idProduct   INT NOT NULL,
  Aantal              INT NOT NULL CHECK (Aantal > 0),
  PRIMARY KEY (Levering_idLevering, Product_idProduct),
  CONSTRAINT fk_LhP_Levering FOREIGN KEY (Levering_idLevering)
    REFERENCES Levering(idLevering) ON DELETE CASCADE,
  -- RESTRICT: een product dat ooit geleverd is blijft bestaan (historie)
  CONSTRAINT fk_LhP_Product FOREIGN KEY (Product_idProduct)
    REFERENCES Product(idProduct) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
--  Voedselpakketten
-- ---------------------------------------------------------------
CREATE TABLE Pakket (
  idPakket            INT AUTO_INCREMENT PRIMARY KEY,
  Klant_idKlant       INT     NOT NULL,                  -- US-PAK-06
  Samenstellingsdatum DATE    NOT NULL,                  -- US-PAK-05
  Uitgiftedatum       DATE    NULL,                      -- US-PAK-09
  Opgehaald           BOOLEAN NOT NULL DEFAULT FALSE,
  Status              ENUM('Concept','Klaar','Uitgegeven') NOT NULL DEFAULT 'Concept',
  Identiteit_gecontroleerd BOOLEAN NOT NULL DEFAULT FALSE, -- US-WRK-01
  Legitimatie         ENUM('Paspoort','Rijbewijs','ID-kaart') NULL,
  Samengesteld_door   INT NULL,
  Uitgegeven_door     INT NULL,
  CONSTRAINT fk_Pakket_Klant FOREIGN KEY (Klant_idKlant) REFERENCES Klant(idKlant),
  CONSTRAINT fk_Pakket_Samensteller FOREIGN KEY (Samengesteld_door)
    REFERENCES Gebruiker(idGebruiker) ON DELETE SET NULL,
  CONSTRAINT fk_Pakket_Uitgever FOREIGN KEY (Uitgegeven_door)
    REFERENCES Gebruiker(idGebruiker) ON DELETE SET NULL,
  INDEX ix_Pakket_klant (Klant_idKlant),
  INDEX ix_Pakket_samen (Samenstellingsdatum),
  INDEX ix_Pakket_uitgifte (Uitgiftedatum),
  INDEX ix_Pakket_status (Status)
) ENGINE=InnoDB;

CREATE TABLE Product_has_Pakket (
  Product_idProduct INT NOT NULL,
  Pakket_idPakket   INT NOT NULL,
  Aantal            INT NOT NULL CHECK (Aantal > 0),
  PRIMARY KEY (Product_idProduct, Pakket_idPakket),
  -- RESTRICT is de kern van US-VRD-05: een product dat in een pakket
  -- zit kan de database zelf niet laten verwijderen.
  CONSTRAINT fk_PhP_Product FOREIGN KEY (Product_idProduct)
    REFERENCES Product(idProduct) ON DELETE RESTRICT,
  CONSTRAINT fk_PhP_Pakket FOREIGN KEY (Pakket_idPakket)
    REFERENCES Pakket(idPakket) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
--  Instellingen (magazijncapaciteit e.d.)
-- ---------------------------------------------------------------
-- ---------------------------------------------------------------
--  Afvoer: wat er NIET in een pakket terechtkomt.
--  Zonder deze tabel is het verspillingspercentage uit US-RAP-04
--  niet te berekenen; daarom is hij aan het model toegevoegd.
-- ---------------------------------------------------------------
CREATE TABLE Afvoer (
  idAfvoer          INT AUTO_INCREMENT PRIMARY KEY,
  Product_idProduct INT  NOT NULL,
  Aantal            INT  NOT NULL CHECK (Aantal > 0),
  Datum             DATE NOT NULL,
  Reden             ENUM('Verspild','Retour leverancier','Veeteelt') NOT NULL,
  Toelichting       VARCHAR(160) NULL,
  Gebruiker_idGebruiker INT NULL,
  CONSTRAINT fk_Afvoer_Product FOREIGN KEY (Product_idProduct)
    REFERENCES Product(idProduct) ON DELETE RESTRICT,
  CONSTRAINT fk_Afvoer_Gebruiker FOREIGN KEY (Gebruiker_idGebruiker)
    REFERENCES Gebruiker(idGebruiker) ON DELETE SET NULL,
  INDEX ix_Afvoer_datum (Datum),
  INDEX ix_Afvoer_reden (Reden)
) ENGINE=InnoDB;

CREATE TABLE Instelling (
  Sleutel VARCHAR(40) PRIMARY KEY,
  Waarde  VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
--  Views: leeftijd en type rekenen we uit, we slaan ze niet op.
--  Een baby van nu is over twee jaar een kind.
-- ---------------------------------------------------------------
CREATE VIEW vw_Gezinslid AS
SELECT g.*,
       TIMESTAMPDIFF(YEAR, g.Geboortedatum, CURDATE()) AS Leeftijd,
       CASE
         WHEN TIMESTAMPDIFF(YEAR, g.Geboortedatum, CURDATE()) <= 2  THEN 'Baby'
         WHEN TIMESTAMPDIFF(YEAR, g.Geboortedatum, CURDATE()) <  18 THEN 'Kind'
         ELSE 'Volwassene'
       END AS Type
FROM Gezinslid g;

CREATE VIEW vw_Klant AS
SELECT k.*,
       CONCAT('K-', LPAD(k.idKlant, 3, '0')) AS Klantnummer,
       (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte,
       (SELECT MAX(p.Uitgiftedatum) FROM Pakket p
         WHERE p.Klant_idKlant = k.idKlant AND p.Status = 'Uitgegeven') AS Laatste_uitgifte
FROM Klant k;
