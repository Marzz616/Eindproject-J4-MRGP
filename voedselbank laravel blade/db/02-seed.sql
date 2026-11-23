SET NAMES utf8mb4;
-- =============================================================
--  Testdata (TEC-7: "de ontwikkelaars zorgen voor testdata om de
--  applicatie te kunnen testen en presenteren")
--  Wachtwoord van alle accounts: voedselbank
-- =============================================================
USE voedselbank;

INSERT INTO Rol (idRol, Naam, Omschrijving) VALUES
  (1,'Directie','Volledige toegang tot alle onderdelen'),
  (2,'Magazijnmedewerker','Producten, voorraad en leveranciers'),
  (3,'Vrijwilliger','Pakketten samenstellen en uitgeven'),
  (4,'Administratie','Klantadministratie, rapportages en accounts');

INSERT INTO Permissie (idPermissie, Code, Naam) VALUES
  (1,'pakket_beheren',      'Pakketten samenstellen & bewerken'),
  (2,'klant_inzien',        'Cliëntenregistratie inzien'),
  (3,'levering_boeken',     'Leveringen registreren'),
  (4,'voorraad_corrigeren', 'Voorraadniveaus corrigeren (Magazijn)'),
  (5,'rapportage_export',   'Financiële rapportages genereren (Directie)'),
  (6,'gebruiker_beheren',   'Gebruikersaccounts beheren (Admin)'),
  (7,'klant_beheren',       'Klanten toevoegen en wijzigen'),
  (8,'product_beheren',     'Producten toevoegen, wijzigen en verwijderen'),
  (9,'leverancier_beheren', 'Leveranciers toevoegen, wijzigen en verwijderen'),
  (10,'pakket_uitgeven',    'Pakketten uitgeven aan klanten');

INSERT INTO Rol_has_Permissie (Rol_idRol, Permissie_idPermissie) VALUES
  -- Directie: alles (US-GBR-05)
  (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),
  -- Magazijnmedewerker: producten + leveranciers (US-GBR-06)
  (2,3),(2,4),(2,8),(2,9),
  -- Vrijwilliger: pakketten samenstellen/uitgeven, klant inzien,
  -- voorraad corrigeren tijdens samenstellen (US-GBR-07/08/09)
  (3,1),(3,2),(3,4),(3,10),
  -- Administratie: klanten, rapportages en accounts, geen magazijn
  (4,2),(4,5),(4,6),(4,7);

INSERT INTO Gebruiker (idGebruiker, Naam, Gebruikersnaam, Email, Wachtwoord_hash, Actief, Laatste_login, Rol_idRol) VALUES
  (1,'Peter Abraham','p.abraham','p.abraham@voedselbank.nl','$2y$12$GhRo48I.j4vvXV.3UfEXLeH0gHae3P.vL1c.RiZlRbbL5gxcl1ie2',1, NOW() - INTERVAL 1 DAY, 1),
  (2,'Rob Veenstra','r.veenstra','rob.veenstra@voedselbank.nl','$2y$12$GhRo48I.j4vvXV.3UfEXLeH0gHae3P.vL1c.RiZlRbbL5gxcl1ie2',1, NOW() - INTERVAL 5 DAY, 2),
  (3,'Ank de Groot','a.degroot','ank.degroot@voedselbank.nl','$2y$12$GhRo48I.j4vvXV.3UfEXLeH0gHae3P.vL1c.RiZlRbbL5gxcl1ie2',1, NOW(), 3),
  (4,'Mirjam Snel','m.snel','m.snel@voedselbank.nl','$2y$12$GhRo48I.j4vvXV.3UfEXLeH0gHae3P.vL1c.RiZlRbbL5gxcl1ie2',1, NOW() - INTERVAL 9 DAY, 4),
  (5,'Kees de Graaf','k.degraaf','k.degraaf@voedselbank.nl','$2y$12$GhRo48I.j4vvXV.3UfEXLeH0gHae3P.vL1c.RiZlRbbL5gxcl1ie2',0, NOW() - INTERVAL 16 DAY, 3);

INSERT INTO Instelling (Sleutel, Waarde) VALUES
  ('magazijn_capaciteit','6500'),
  ('tht_waarschuwing_dagen','3');

-- ---------------- wensen ----------------
-- Streng = 1 bij een ernstige allergie: zo'n product mag niet eens in huis
-- komen, ook niet voor de andere gezinsleden. De rest is een wens of
-- overtuiging; die mag wel in het pakket, maar alleen na bevestiging.
INSERT INTO Wens (idWens, Naam, Omschrijving, Streng, Conflict_tag) VALUES
  (1,'Halal','Uitsluitend halal producten',0,'varkensvlees'),
  (2,'Geen varkensvlees','Geen producten met varkensvlees',0,'varkensvlees'),
  (3,'Glutenvrij','Coeliakie of glutenintolerantie',0,'gluten'),
  (4,'Notenallergie','Ernstige allergie voor noten en hazelnoten',1,'noten'),
  (5,'Pinda-allergie','Ernstige allergie voor pinda''s',1,'pinda'),
  (6,'Schaaldierallergie','Ernstige allergie voor schaaldieren',1,'schaaldier'),
  (7,'Lactose-intolerant','Geen lactose',0,'lactose'),
  (8,'Vegetarisch','Geen vlees of vis',0,'vlees'),
  (9,'Veganistisch','Geen dierlijke producten',0,'dierlijk');

-- ---------------- categorieën ----------------
INSERT INTO Categorie (idCategorie, Naam, Kort, Omschrijving) VALUES
  (1,'Aardappelen, groente, fruit','Groente & fruit','Verse en houdbare groente en fruit'),
  (2,'Kaas, vleeswaren','Vlees & kaas','Koelverse kaas en vleeswaren'),
  (3,'Zuivel, plantaardig en eieren','Zuivel','Melk, yoghurt, eieren en plantaardige varianten'),
  (4,'Bakkerij en banket','Brood','Brood, beschuit en banket'),
  (5,'Frisdrank, sappen, koffie en thee','Dranken','Dranken'),
  (6,'Pasta, rijst en wereldkeuken','Pasta & rijst','Droge houdbare basisproducten'),
  (7,'Soepen, sauzen, kruiden en olie','Conserven','Conserven en smaakmakers'),
  (8,'Snoep, koek, chips en chocolade','Snoep & koek','Zoet en hartig tussendoor'),
  (9,'Baby, verzorging en hygiëne','Baby & verzorging','Non-food en babyvoeding');

-- ---------------- leveranciers ----------------
INSERT INTO Leverancier (idLeverancier, Bedrijfsnaam, Adres, Postcode, Plaats, Contactpersoon, Email, Telefoon, Categorie, Actief, Volgende_leverdatum, Volgende_levertijd) VALUES
  (1,'Albert Heijn Maaskant','Marktplein 1','5492AA','Maaskantje','Jan de Vries','jan@ahmaaskant.nl','06-12345678','Supermarkt',1, CURDATE() + INTERVAL 1 DAY,'08:30:00'),
  (2,'Bakkerij van de Ven','Dorpsstraat 30','5492AB','Maaskantje','Marten van de Ven','info@bakkerijvandeven.nl','06-87654321','Bakkerij',1, CURDATE() + INTERVAL 1 DAY,'11:00:00'),
  (3,'Lidl Maaskantje','Industrieweg 12','5493PL','Maaskantje','Sara Lindeman','sara@lidl-distr.nl','06-44556677','Supermarkt',1, CURDATE() + INTERVAL 4 DAY,'09:15:00'),
  (4,'Sligro Tilburg','Ringbaan 200','5021AB','Tilburg','Peter de Jong','pdejong@sligro.nl','013-987654','Groothandel',0, NULL, NULL),
  (5,'VDB Zuivel','Boerenweg 8','5492CD','Maaskantje','Annelies van Dam','zuivel@vdbros.nl','06-11223344','Zuivel',1, CURDATE() + INTERVAL 7 DAY,'07:45:00');

-- ---------------- producten ----------------
INSERT INTO Product (idProduct, Productnaam, EAN, Aantal_voorraad, Min_voorraad, Waarde, Houdbaar_tot, Locatie, Allergenen, Categorie_idCategorie) VALUES
  (1,'Campina Volle Melk (1L)','8712345678901',12,20, 1.29, CURDATE() - INTERVAL 10 DAY,'Vak A-3','lactose,dierlijk',3),
  (2,'Zaanse Hoeve Jong Belegen','8712345678902', 5,15, 3.49, CURDATE() + INTERVAL 2 DAY,'Koeling 2','lactose,dierlijk',2),
  (3,'Halfbruin gesneden brood','8712345678903',45,30, 1.89, CURDATE() + INTERVAL 2 DAY,'Krat B-1','gluten',4),
  (4,'Heinz Sperziebonen blik','8712345678904',120,50, 0.99, CURDATE() + INTERVAL 700 DAY,'Vak C-4',NULL,7),
  (5,'Hak Erwten & Wortelen','8712345678905',85,40, 1.19, CURDATE() + INTERVAL 440 DAY,'Vak C-4',NULL,7),
  (6,'Lidl Jonagold Appels (zak)','8712345678906',14,10, 2.49, CURDATE() + INTERVAL 6 DAY,'Krat F-2',NULL,1),
  (7,'Glutenvrij Meergranenbrood','8712345678907', 8,10, 2.99, CURDATE() + INTERVAL 3 DAY,'Krat B-2',NULL,4),
  (8,'Tarwebrood Gesneden','8712345678908',32,20, 1.79, CURDATE() + INTERVAL 2 DAY,'Krat B-1','gluten',4),
  (9,'Pindakaas Calvé (650g)','8712345678909',18,10, 2.89, CURDATE() + INTERVAL 300 DAY,'Vak D-1','pinda,noten',7),
  (10,'Campina Halfvolle Melk','8712345678910',24,20, 1.19, CURDATE() + INTERVAL 9 DAY,'Vak A-3','lactose,dierlijk',3),
  (11,'Hak Doperwtjes blik','8712345678911',45,25, 0.99, CURDATE() + INTERVAL 600 DAY,'Vak C-5',NULL,7),
  (12,'Rijst 1kg','8712345678912',1200,40, 1.49, CURDATE() + INTERVAL 500 DAY,'Vak E-1',NULL,6),
  (13,'Pasta penne 500g','8712345678913',1100,40, 0.89, CURDATE() + INTERVAL 450 DAY,'Vak E-2','gluten',6),
  (14,'Luiers maat 4','8712345678914',260,10, 9.99, NULL,'Vak G-1',NULL,9),
  (15,'Koffiepads 36st','8712345678915',520,15, 4.49, CURDATE() + INTERVAL 250 DAY,'Vak D-3',NULL,5),
  (16,'Sinaasappelsap 1L','8712345678916',700,20, 1.59, CURDATE() + INTERVAL 120 DAY,'Vak D-2',NULL,5),
  (17,'Eieren 10st','8712345678917',3,12, 2.39, CURDATE() + INTERVAL 14 DAY,'Koeling 1','dierlijk',3),
  (18,'Shampoo 300ml','8712345678918',450,10, 2.29, NULL,'Vak G-2',NULL,9),
  (19,'Kipfilet 300g','8712345678919',16,10, 3.99, CURDATE() + INTERVAL 4 DAY,'Koeling 3','vlees,dierlijk',2),
  (20,'Rookworst','8712345678920',22,10, 2.19, CURDATE() + INTERVAL 20 DAY,'Koeling 3','varkensvlees,vlees,dierlijk',2);

-- ---------------- leveringen ----------------
INSERT INTO Levering (idLevering, Leverancier_idLeverancier, Leverdatum, Levertijd, Verwerkt_door) VALUES
  (1,1, CURDATE(),                   '08:30:00',2),
  (2,2, CURDATE() - INTERVAL 1 DAY,  '11:00:00',2),
  (3,3, CURDATE() - INTERVAL 5 DAY,  '09:15:00',2),
  (4,1, CURDATE() - INTERVAL 8 DAY,  '08:30:00',2),
  (5,5, CURDATE() - INTERVAL 12 DAY, '07:45:00',3);

INSERT INTO Levering_has_Product (Levering_idLevering, Product_idProduct, Aantal) VALUES
  (1,1,150),(1,10,60),
  (2,3,60),(2,8,40),
  (3,6,45),(3,4,80),
  (4,1,120),(4,2,40),
  (5,10,25),(5,17,30);

-- ---------------- klanten ----------------
INSERT INTO Klant (idKlant, Gezinsnaam, Adres, Postcode, Plaats, Telefoon, Email, Aanmelddatum, Geaccepteerd) VALUES
  (1,'Familie Bakker','Dorpsstraat 12','5492AB','Maaskantje','06-12345678','a.bakker@voorbeeld.nl', CURDATE() - INTERVAL 900 DAY,1),
  (2,'Gezin El Hassani','Kerkplein 4','5492CD','Maaskantje','06-23456789','s.elhassani@voorbeeld.nl', CURDATE() - INTERVAL 750 DAY,1),
  (3,'Mevr. Jansen','Spoorlaan 88','5491XT','Maaskantje','0413-478900','g.jansen@voorbeeld.nl', CURDATE() - INTERVAL 1200 DAY,1),
  (4,'M. van Dongen','Molenweg 22','5493PL','Maaskantje','06-34567890','m.vandongen@voorbeeld.nl', CURDATE() - INTERVAL 1300 DAY,1),
  (5,'S. El Jafari','Beukenlaan 7','5492CD','Maaskantje','06-45678901','s.eljafari@voorbeeld.nl', CURDATE() - INTERVAL 600 DAY,1),
  (6,'Familie Öztürk','Kastanjehof 3','5492AB','Maaskantje','06-99887766','e.ozturk@voorbeeld.nl', CURDATE() - INTERVAL 400 DAY,1),
  (7,'Familie Veldhuis','Kastanjehof 18','5492AB','Maaskantje','06-56789012','m.veldhuis@voorbeeld.nl', CURDATE() - INTERVAL 300 DAY,1),
  (8,'Dhr. Kowalski','Industrieweg 2b','5493PL','Maaskantje','06-67890123','p.kowalski@voorbeeld.nl', CURDATE() - INTERVAL 200 DAY,1),
  (9,'Familie Diallo','Lindenstraat 30','5492CD','Maaskantje','06-78901234','f.diallo@voorbeeld.nl', CURDATE(), NULL),
  (10,'Dhr. Nowak','Zandstraat 9','5491XT','Maaskantje','06-89012345','t.nowak@voorbeeld.nl', CURDATE(), NULL),
  (11,'Familie Van Rooij','Pastoor Smitsstraat 5','5491XT','Maaskantje','06-90123456','p.vanrooij@voorbeeld.nl', CURDATE() - INTERVAL 500 DAY,0);

INSERT INTO Gezinslid (idGezinslid, Naam, Geboortedatum, Relatie, Klant_idKlant) VALUES
  (1,'Ank Bakker','1982-05-19','Ouder (Moeder)',1),
  (2,'Bart Bakker','1980-08-30','Ouder (Vader)',1),
  (3,'Cees Bakker','2012-01-22','Kind',1),
  (4,'Daan Bakker','2015-09-05','Kind',1),
  (5,'Elsa Bakker','2020-03-14','Kind',1),
  (6,'Samir El Hassani','1985-11-02','Ouder (Vader)',2),
  (7,'Nadia El Hassani','1988-02-17','Ouder (Moeder)',2),
  (8,'Yasmin El Hassani','2014-04-17','Kind',2),
  (9,'Adam El Hassani','2018-06-09','Kind',2),
  (10,'Greet Jansen','1956-05-14','Hoofdaanvrager',3),
  (11,'Marijke van Dongen','1979-09-12','Ouder (Moeder)',4),
  (12,'Bram van Dongen','1977-12-03','Ouder (Vader)',4),
  (13,'Lieke van Dongen','2009-06-28','Kind',4),
  (14,'Tess van Dongen','2012-03-19','Kind',4),
  (15,'Sem van Dongen','2016-10-08','Kind',4),
  (16,'Samira El Jafari','1991-01-19','Hoofdaanvrager',5),
  (17,'Rayan El Jafari','2017-05-16','Kind',5),
  (18,'Emre Öztürk','1977-01-19','Ouder (Vader)',6),
  (19,'Hatice Öztürk','1980-04-25','Ouder (Moeder)',6),
  (20,'Deniz Öztürk','2009-08-03','Kind',6),
  (21,'Marloes Veldhuis','1992-06-23','Hoofdaanvrager',7),
  (22,'Tijn Veldhuis','2020-01-11','Kind',7),
  (23,'Noor Veldhuis','2024-08-19','Kind',7),
  (24,'Jelle Veldhuis','1990-03-02','Partner',7),
  (25,'Sanne Veldhuis','2016-07-21','Kind',7),
  (26,'Luuk Veldhuis','2013-11-30','Kind',7),
  (27,'Piotr Kowalski','1995-11-27','Hoofdaanvrager',8),
  (28,'Anna Kowalska','1997-02-08','Partner',8),
  (29,'Fatou Diallo','1988-04-09','Hoofdaanvrager',9),
  (30,'Ibrahim Diallo','2014-10-21','Kind',9),
  (31,'Aminata Diallo','2023-03-15','Kind',9),
  (32,'Tomasz Nowak','1986-01-30','Hoofdaanvrager',10),
  (33,'Peter van Rooij','1983-07-04','Hoofdaanvrager',11),
  (34,'Sanne van Rooij','2011-09-28','Kind',11);

-- dieet per persoon
-- Opmerkingen bij een paar gezinnen, zodat het veld zichtbaar is in de demo
UPDATE Klant SET Opmerkingen = 'Liever rijst dan pasta. Geen koffie, wel thee.' WHERE idKlant = 1;
UPDATE Klant SET Opmerkingen = 'Grote verpakkingen graag, het gezin kookt voor zes.' WHERE idKlant = 7;
UPDATE Klant SET Opmerkingen = 'Mevrouw komt met de fiets, dus geen zware kratten.' WHERE idKlant = 3;

INSERT INTO Gezinslid_has_Wens (Gezinslid_idGezinslid, Wens_idWens) VALUES
  (2,4),(3,3),(5,3),
  (6,1),(7,1),(8,1),(9,1),
  (10,7),
  (11,7),(13,7),
  (16,4),(17,4),
  (18,1),(19,1),(19,3),(20,1),
  (27,8),(28,8),
  (29,1),(30,1);

-- ---------------- pakketten ----------------
INSERT INTO Pakket (idPakket, Klant_idKlant, Samenstellingsdatum, Uitgiftedatum, Opgehaald, Status,
                    Identiteit_gecontroleerd, Legitimatie, Samengesteld_door, Uitgegeven_door) VALUES
  (2701,6, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 35 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2702,2, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 35 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2703,4, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 35 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2704,1, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 35 DAY, 1,'Uitgegeven',1,'ID-kaart',3,3),
  (2705,5, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 35 DAY, 1,'Uitgegeven',1,'ID-kaart',3,1),
  (2706,3, CURDATE() - INTERVAL 39 DAY, CURDATE() - INTERVAL 35 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2707,1, CURDATE() - INTERVAL 32 DAY, CURDATE() - INTERVAL 28 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2708,6, CURDATE() - INTERVAL 32 DAY, CURDATE() - INTERVAL 28 DAY, 1,'Uitgegeven',1,'ID-kaart',3,1),
  (2709,8, CURDATE() - INTERVAL 32 DAY, CURDATE() - INTERVAL 28 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2710,5, CURDATE() - INTERVAL 32 DAY, CURDATE() - INTERVAL 28 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2711,3, CURDATE() - INTERVAL 32 DAY, CURDATE() - INTERVAL 28 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2712,1, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2713,4, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'ID-kaart',3,3),
  (2714,6, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'ID-kaart',3,3),
  (2715,5, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2716,7, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2717,2, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'ID-kaart',3,3),
  (2718,3, CURDATE() - INTERVAL 25 DAY, CURDATE() - INTERVAL 21 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2719,6, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 14 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2720,4, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 14 DAY, 1,'Uitgegeven',1,'Paspoort',3,1),
  (2721,8, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 14 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2722,3, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 14 DAY, 1,'Uitgegeven',1,'ID-kaart',3,1),
  (2723,5, CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 14 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2724,7, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 7 DAY, 1,'Uitgegeven',1,'ID-kaart',3,1),
  (2725,8, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 7 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2726,2, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 7 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2727,1, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 7 DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2728,3, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 7 DAY, 1,'Uitgegeven',1,'Paspoort',3,1),
  (2729,6, CURDATE() - INTERVAL 11 DAY, CURDATE() - INTERVAL 7 DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2860,4, CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY, CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY, 1,'Uitgegeven',1,'Paspoort',3,3),
  (2861,8, CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY, CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY, 1,'Uitgegeven',1,'Rijbewijs',3,3),
  (2862,2, CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY, NULL, 0,'Klaar',0,NULL,3,NULL);

INSERT INTO Product_has_Pakket (Pakket_idPakket, Product_idProduct, Aantal) VALUES
  (2701,20,1),(2701,11,2),(2701,6,2),(2701,3,1),
  (2702,3,3),(2702,19,3),(2702,12,2),(2702,4,1),(2702,6,1),(2702,16,1),(2702,20,3),
  (2703,5,1),(2703,11,3),(2703,4,3),(2703,12,3),(2703,7,1),(2703,19,2),(2703,20,1),
  (2704,12,3),(2704,6,2),(2704,10,2),(2704,13,2),
  (2705,7,3),(2705,6,2),(2705,16,3),(2705,5,2),(2705,19,2),(2705,4,3),
  (2706,4,2),(2706,11,1),(2706,9,2),(2706,5,2),
  (2707,12,3),(2707,16,3),(2707,10,1),(2707,4,1),(2707,15,3),(2707,7,3),(2707,19,2),
  (2708,15,2),(2708,9,1),(2708,13,3),(2708,8,1),(2708,3,2),(2708,10,1),
  (2709,15,1),(2709,6,1),(2709,9,2),(2709,16,2),(2709,10,3),
  (2710,19,1),(2710,11,1),(2710,7,1),(2710,15,1),(2710,9,1),(2710,8,1),(2710,13,3),
  (2711,19,2),(2711,12,3),(2711,5,2),(2711,7,3),(2711,15,3),(2711,3,2),(2711,16,1),
  (2712,13,1),(2712,9,1),(2712,3,2),(2712,6,3),(2712,4,1),(2712,15,1),(2712,10,1),
  (2713,8,1),(2713,12,3),(2713,3,2),(2713,4,1),
  (2714,12,2),(2714,8,2),(2714,10,2),(2714,4,2),(2714,15,1),(2714,16,1),
  (2715,10,1),(2715,19,3),(2715,15,2),(2715,5,1),(2715,11,3),(2715,3,3),
  (2716,15,2),(2716,19,1),(2716,7,2),(2716,11,1),
  (2717,12,2),(2717,16,3),(2717,19,1),(2717,6,1),(2717,15,3),
  (2718,3,2),(2718,16,1),(2718,7,3),(2718,10,3),
  (2719,6,1),(2719,10,2),(2719,20,3),(2719,8,3),
  (2720,16,3),(2720,13,1),(2720,4,2),(2720,19,1),(2720,20,2),(2720,9,3),
  (2721,10,1),(2721,9,1),(2721,15,3),(2721,4,2),(2721,5,3),(2721,13,1),(2721,12,3),
  (2722,5,3),(2722,11,3),(2722,19,1),(2722,20,3),(2722,3,3),(2722,13,1),
  (2723,3,1),(2723,7,3),(2723,6,2),(2723,19,2),(2723,11,3),
  (2724,11,2),(2724,5,1),(2724,20,3),(2724,16,1),(2724,3,1),
  (2725,12,3),(2725,15,3),(2725,4,2),(2725,11,1),(2725,3,3),(2725,8,1),(2725,19,1),
  (2726,16,3),(2726,4,1),(2726,11,1),(2726,10,2),
  (2727,10,3),(2727,11,3),(2727,19,2),(2727,20,3),(2727,16,1),(2727,6,2),
  (2728,9,3),(2728,10,1),(2728,8,2),(2728,4,1),
  (2729,20,3),(2729,16,3),(2729,5,2),(2729,15,1),
  (2860,12,2),(2860,4,2),(2860,11,2),(2860,16,1),
  (2861,13,2),(2861,5,2),(2861,3,1),(2861,15,1),
  (2862,4,2),(2862,5,2),(2862,12,2),(2862,11,2),(2862,19,1),(2862,3,2);

-- ---------------- afvoer (voor het verspillingsrapport) ----------------
INSERT INTO Afvoer (Product_idProduct, Aantal, Datum, Reden, Toelichting, Gebruiker_idGebruiker) VALUES
  (1,  3, CURDATE() - INTERVAL 26 DAY, 'Verspild',           'THT verstreken',            2),
  (3,  8, CURDATE() - INTERVAL 22 DAY, 'Veeteelt',           'Oud brood naar de boerderij',2),
  (2,  2, CURDATE() - INTERVAL 19 DAY, 'Verspild',           'Koeling uitgevallen',        2),
  (6, 14, CURDATE() - INTERVAL 15 DAY, 'Veeteelt',           'Appels met bruine plekken',  2),
  (4, 20, CURDATE() - INTERVAL 12 DAY, 'Retour leverancier', 'Verkeerd geleverd',          2),
  (3, 10, CURDATE() - INTERVAL 8  DAY, 'Veeteelt',           'Oud brood naar de boerderij',3),
  (1,  2, CURDATE() - INTERVAL 4  DAY, 'Verspild',           'THT verstreken',             2);

-- Eén uitzondering als voorbeeld: Bart Bakker heeft een notenallergie, maar het
-- gezin wil pindakaas toch ontvangen voor de kinderen. Met reden vastgelegd,
-- dus de blokkade vervalt alleen voor dit gezin.
UPDATE Gezinslid_has_Wens
   SET Toch_toestaan = 1,
       Toelichting   = 'Gezin wil het toch: Bart eet het niet, de kinderen wel. Apart bewaren.'
 WHERE Gezinslid_idGezinslid = 2 AND Wens_idWens = 4;
