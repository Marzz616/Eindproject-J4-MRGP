-- ============================================================
-- Voorraadbeheer database (gebaseerd op jouw ERD)
-- Uitvoeren in MySQL Workbench: File > Open SQL Script > bliksem-icoon
--
-- Alle tabellen en kolomnamen komen uit het ERD. Afwijkingen:
--   1. Product heeft twee extra kolommen: THT en Locatie
--      (de pagina toont die, ze stonden nog niet in het ERD)
--   2. Product.Aantal_voorraad is INT in plaats van VARCHAR(45)
--   3. De foreign keys naar Product hebben ON UPDATE CASCADE, zodat je
--      de categorie van een product kunt wijzigen (die zit in de
--      samengestelde primary key van Product)
--   4. Gebruiker.Wachtwoord_hash is VARCHAR(255): een hash past niet
--      in 45 tekens
--
-- Het script kan veilig opnieuw worden uitgevoerd: er wordt niets verwijderd.
-- Heb je de oude tabellen "producten" en "instellingen" uit de vorige
-- versie? Die worden niet meer gebruikt en mogen weg:
--   DROP TABLE IF EXISTS producten, instellingen;
-- ============================================================

CREATE DATABASE IF NOT EXISTS voorraadbeheer
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE voorraadbeheer;

-- ------------------------------------------------------------
-- Klanten, gezinsleden en wensen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Klant (
  idKlant       INT NOT NULL AUTO_INCREMENT,
  Gezinsnaam    VARCHAR(45) NOT NULL,
  Adres         VARCHAR(45) NULL,
  Postcode      VARCHAR(45) NULL,
  Telefoon      VARCHAR(45) NULL,
  `E-mail`      VARCHAR(45) NULL,
  Aanmelddatum  VARCHAR(45) NULL,
  Geaccepteerd  VARCHAR(45) NULL,
  PRIMARY KEY (idKlant)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Gezinslid (
  idGezinslid    INT NOT NULL AUTO_INCREMENT,
  Naam           VARCHAR(45) NOT NULL,
  Geboortedatum  VARCHAR(45) NULL,
  Type           VARCHAR(45) NULL,
  Klant_idKlant  INT NOT NULL,
  PRIMARY KEY (idGezinslid),
  INDEX fk_Gezinslid_Klant_idx (Klant_idKlant),
  CONSTRAINT fk_Gezinslid_Klant
    FOREIGN KEY (Klant_idKlant) REFERENCES Klant (idKlant)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Wens (
  idWens        INT NOT NULL AUTO_INCREMENT,
  Naam          VARCHAR(45) NOT NULL,
  Omschrijving  VARCHAR(45) NULL,
  PRIMARY KEY (idWens)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Klant_has_Wens (
  Klant_idKlant  INT NOT NULL,
  Wens_idWens    INT NOT NULL,
  PRIMARY KEY (Klant_idKlant, Wens_idWens),
  INDEX fk_Klant_has_Wens_Wens_idx (Wens_idWens),
  INDEX fk_Klant_has_Wens_Klant_idx (Klant_idKlant),
  CONSTRAINT fk_Klant_has_Wens_Klant
    FOREIGN KEY (Klant_idKlant) REFERENCES Klant (idKlant)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT fk_Klant_has_Wens_Wens
    FOREIGN KEY (Wens_idWens) REFERENCES Wens (idWens)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Categorieen en producten
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Categorie (
  idCategorie   INT NOT NULL AUTO_INCREMENT,
  Naam          VARCHAR(45) NOT NULL,
  Omschrijving  VARCHAR(45) NULL,
  PRIMARY KEY (idCategorie)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Product (
  idProduct              INT NOT NULL AUTO_INCREMENT,
  Productnaam            VARCHAR(45) NOT NULL,
  EAN                    VARCHAR(45) NULL,
  Aantal_voorraad        INT NOT NULL DEFAULT 0,
  Categorie_idCategorie  INT NOT NULL,
  THT                    DATE NULL,          -- extra: houdbaar tot
  Locatie                VARCHAR(45) NULL,   -- extra: magazijnlocatie
  PRIMARY KEY (idProduct, Categorie_idCategorie),
  INDEX fk_Product_Categorie_idx (Categorie_idCategorie),
  CONSTRAINT fk_Product_Categorie
    FOREIGN KEY (Categorie_idCategorie) REFERENCES Categorie (idCategorie)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Pakketten
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Pakket (
  idPakket             INT NOT NULL AUTO_INCREMENT,
  Klant_idKlant        INT NOT NULL,
  Samenstellingsdatum  VARCHAR(45) NULL,
  Uitgiftedatum        VARCHAR(45) NULL,
  Opgehaald            VARCHAR(45) NULL,
  PRIMARY KEY (idPakket, Klant_idKlant),
  INDEX fk_Pakket_Klant_idx (Klant_idKlant),
  CONSTRAINT fk_Pakket_Klant
    FOREIGN KEY (Klant_idKlant) REFERENCES Klant (idKlant)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Product_has_Pakket (
  Product_idProduct              INT NOT NULL,
  Product_Categorie_idCategorie  INT NOT NULL,
  Pakket_idPakket                INT NOT NULL,
  Pakket_Klant_idKlant           INT NOT NULL,
  Aantal                         VARCHAR(45) NULL,
  PRIMARY KEY (Product_idProduct, Product_Categorie_idCategorie, Pakket_idPakket, Pakket_Klant_idKlant),
  INDEX fk_PhP_Pakket_idx (Pakket_idPakket, Pakket_Klant_idKlant),
  INDEX fk_PhP_Product_idx (Product_idProduct, Product_Categorie_idCategorie),
  CONSTRAINT fk_PhP_Product
    FOREIGN KEY (Product_idProduct, Product_Categorie_idCategorie)
    REFERENCES Product (idProduct, Categorie_idCategorie)
    ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT fk_PhP_Pakket
    FOREIGN KEY (Pakket_idPakket, Pakket_Klant_idKlant)
    REFERENCES Pakket (idPakket, Klant_idKlant)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Gebruikers en rollen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Rol (
  idRol  INT NOT NULL AUTO_INCREMENT,
  Naam   VARCHAR(45) NOT NULL,
  PRIMARY KEY (idRol)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Gebruiker (
  idGebruiker      INT NOT NULL AUTO_INCREMENT,
  Naam             VARCHAR(45) NOT NULL,
  Gebruikersnaam   VARCHAR(45) NOT NULL,
  Wachtwoord_hash  VARCHAR(255) NOT NULL,
  Actief           VARCHAR(45) NULL,
  Rol_idRol        INT NOT NULL,
  PRIMARY KEY (idGebruiker),
  INDEX fk_Gebruiker_Rol_idx (Rol_idRol),
  CONSTRAINT fk_Gebruiker_Rol
    FOREIGN KEY (Rol_idRol) REFERENCES Rol (idRol)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Leveranciers en leveringen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Leverancier (
  idLeverancier   INT NOT NULL AUTO_INCREMENT,
  Bedrijfsnaam    VARCHAR(45) NOT NULL,
  Adres           VARCHAR(45) NULL,
  Contactpersoon  VARCHAR(45) NULL,
  `E-mail`        VARCHAR(45) NULL,
  Telefoon        VARCHAR(45) NULL,
  PRIMARY KEY (idLeverancier)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Levering (
  idLevering                  INT NOT NULL AUTO_INCREMENT,
  Leverancier_idLeverancier   INT NOT NULL,
  Leverdatum                  VARCHAR(45) NULL,
  Levertijd                   VARCHAR(45) NULL,
  PRIMARY KEY (idLevering, Leverancier_idLeverancier),
  INDEX fk_Levering_Leverancier_idx (Leverancier_idLeverancier),
  CONSTRAINT fk_Levering_Leverancier
    FOREIGN KEY (Leverancier_idLeverancier) REFERENCES Leverancier (idLeverancier)
    ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS Levering_has_Product (
  Levering_idLevering                 INT NOT NULL,
  Levering_Leverancier_idLeverancier  INT NOT NULL,
  Product_idProduct                   INT NOT NULL,
  Product_Categorie_idCategorie       INT NOT NULL,
  Aantal                              VARCHAR(45) NULL,
  PRIMARY KEY (Levering_idLevering, Levering_Leverancier_idLeverancier, Product_idProduct, Product_Categorie_idCategorie),
  INDEX fk_LhP_Product_idx (Product_idProduct, Product_Categorie_idCategorie),
  INDEX fk_LhP_Levering_idx (Levering_idLevering, Levering_Leverancier_idLeverancier),
  CONSTRAINT fk_LhP_Levering
    FOREIGN KEY (Levering_idLevering, Levering_Leverancier_idLeverancier)
    REFERENCES Levering (idLevering, Leverancier_idLeverancier)
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT fk_LhP_Product
    FOREIGN KEY (Product_idProduct, Product_Categorie_idCategorie)
    REFERENCES Product (idProduct, Categorie_idCategorie)
    ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Voorbeelddata (zelfde producten als in het ontwerp)
-- INSERT IGNORE met vaste id's, dus opnieuw uitvoeren geeft geen dubbelen.
-- ------------------------------------------------------------
INSERT IGNORE INTO Categorie (idCategorie, Naam, Omschrijving) VALUES
  (1, 'Zuivel',    'Melk, kaas en yoghurt'),
  (2, 'Brood',     'Brood en beleg'),
  (3, 'Groente',   'Verse en diepgevroren groente'),
  (4, 'Fruit',     'Vers fruit'),
  (5, 'Conserven', 'Blik en pot');

INSERT IGNORE INTO Product (idProduct, Productnaam, EAN, Aantal_voorraad, Categorie_idCategorie, THT, Locatie) VALUES
  (1, 'Campina Volle Melk (1L)',  NULL, 12,  1, '2023-10-12', 'Vak A-3'),
  (2, 'Halfbruin gesneden brood', NULL, 45,  2, '2024-10-25', 'Krat B-1'),
  (3, 'Heinz Sperziebonen blik',  NULL, 120, 5, '2026-08-14', 'Vak C-4');
