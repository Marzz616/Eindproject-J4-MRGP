<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Cijfers voor rapportages en het dashboard (US-RAP-01 t/m 06).
 * Alleen lezen; elke methode is één afgebakende vraag aan de database.
 */
final class Rapportage extends Model
{
    public static function uitgegevenDitJaar(): int
    {
        return (int) self::value("SELECT COUNT(*) FROM Pakket WHERE Status = 'Uitgegeven' AND YEAR(Uitgiftedatum) = YEAR(CURDATE())");
    }

    /** Zelfde periode vorig jaar (1 jan t/m dezelfde dag), voor de groei. */
    public static function uitgegevenVorigJaarTotNu(): int
    {
        return (int) self::value("
            SELECT COUNT(*) FROM Pakket
             WHERE Status = 'Uitgegeven' AND YEAR(Uitgiftedatum) = YEAR(CURDATE()) - 1
               AND DAYOFYEAR(Uitgiftedatum) <= DAYOFYEAR(CURDATE())");
    }

    /** Aantal uitgegeven pakketten en hun totale winkelwaarde (US-RAP-02). */
    public static function pakketWaarde(): array
    {
        return self::one("
            SELECT COUNT(DISTINCT p.idPakket) AS Pakketten, COALESCE(SUM(pp.Aantal * pr.Waarde), 0) AS Waarde
              FROM Pakket p
              JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
              JOIN Product pr ON pr.idProduct = pp.Product_idProduct
             WHERE p.Status = 'Uitgegeven'");
    }

    /** Uitgegeven pakketten per ISO-week, laatste 5 weken (US-RAP-01). */
    public static function perWeek(): array
    {
        return self::all("
            SELECT WEEK(Uitgiftedatum, 3) AS Weeknr, COUNT(*) AS Aantal,
                   MIN(Uitgiftedatum) AS Van, MAX(Uitgiftedatum) AS Tot
              FROM Pakket
             WHERE Status = 'Uitgegeven' AND Uitgiftedatum >= CURDATE() - INTERVAL 35 DAY
          GROUP BY YEARWEEK(Uitgiftedatum, 3), Weeknr
          ORDER BY YEARWEEK(Uitgiftedatum, 3)");
    }

    /** @return array<string,int> datum => stuks geleverd, laatste 30 dagen */
    public static function geleverdPerDag(): array
    {
        $uit = [];
        foreach (self::all("
            SELECT v.Leverdatum AS D, COALESCE(SUM(lp.Aantal),0) AS N
              FROM Levering v JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
             WHERE v.Leverdatum >= CURDATE() - INTERVAL 30 DAY GROUP BY D") as $r) {
            $uit[$r['D']] = (int) $r['N'];
        }
        return $uit;
    }

    /** @return array<string,int> datum => stuks die het magazijn uit gingen (pakketten + afvoer) */
    public static function uitgegaanPerDag(): array
    {
        $uit = [];
        foreach (self::all("
            SELECT p.Uitgiftedatum AS D, COALESCE(SUM(pp.Aantal),0) AS N
              FROM Pakket p JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
             WHERE p.Status = 'Uitgegeven' AND p.Uitgiftedatum >= CURDATE() - INTERVAL 30 DAY GROUP BY D") as $r) {
            $uit[$r['D']] = (int) $r['N'];
        }
        foreach (self::all("SELECT Datum AS D, SUM(Aantal) AS N FROM Afvoer WHERE Datum >= CURDATE() - INTERVAL 30 DAY GROUP BY D") as $r) {
            $uit[$r['D']] = ($uit[$r['D']] ?? 0) + (int) $r['N'];
        }
        return $uit;
    }

    public static function stuksInUitgegevenPakketten(): int
    {
        return (int) self::value("
            SELECT COALESCE(SUM(pp.Aantal),0) FROM Pakket p
              JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
             WHERE p.Status = 'Uitgegeven'");
    }

    /** @return array<string,int> reden => aantal */
    public static function afvoerPerReden(): array
    {
        $uit = [];
        foreach (self::all('SELECT Reden, SUM(Aantal) AS N FROM Afvoer GROUP BY Reden') as $r) {
            $uit[$r['Reden']] = (int) $r['N'];
        }
        return $uit;
    }

    /** Geaccepteerde gezinnen ingedeeld naar grootte (US-RAP-05). */
    public static function gezinsgrootteVerdeling(): array
    {
        return self::all("
            SELECT CASE WHEN Grootte <= 1 THEN '1 persoon (alleenstaand)'
                        WHEN Grootte <= 3 THEN '2-3 personen (klein huishouden)'
                        ELSE '4+ personen (groot gezin)' END AS Groep,
                   COUNT(*) AS Aantal
              FROM (SELECT k.idKlant, (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Grootte
                      FROM Klant k WHERE k.Geaccepteerd = 1) t
          GROUP BY Groep
          ORDER BY FIELD(Groep,'1 persoon (alleenstaand)','2-3 personen (klein huishouden)','4+ personen (groot gezin)')");
    }

    public static function topProducten(int $limiet): array
    {
        return self::all("
            SELECT pr.Productnaam, c.Naam AS Categorie, SUM(pp.Aantal) AS Aantal
              FROM Pakket p
              JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
              JOIN Product pr ON pr.idProduct = pp.Product_idProduct
              JOIN Categorie c ON c.idCategorie = pr.Categorie_idCategorie
             WHERE p.Status = 'Uitgegeven'
          GROUP BY pr.idProduct ORDER BY Aantal DESC, pr.Productnaam LIMIT " . (int) $limiet);
    }

    /* ------------------------------------------------ maandoverzichten (RAP-1 / RAP-2) */

    /** RAP-1: geleverd per categorie, product en leverancier in een maand. */
    public static function geleverdInMaand(int $maand, int $jaar): array
    {
        return self::all("
            SELECT c.Naam AS Categorie, p.idProduct, p.Productnaam, l.Bedrijfsnaam AS Leverancier, SUM(lp.Aantal) AS Geleverd
              FROM Levering v
              JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
              JOIN Product p     ON p.idProduct = lp.Product_idProduct
              JOIN Categorie c   ON c.idCategorie = p.Categorie_idCategorie
              JOIN Leverancier l ON l.idLeverancier = v.Leverancier_idLeverancier
             WHERE MONTH(v.Leverdatum) = ? AND YEAR(v.Leverdatum) = ?
          GROUP BY c.idCategorie, p.idProduct, l.idLeverancier
          ORDER BY c.Naam, p.Productnaam, l.Bedrijfsnaam", [$maand, $jaar]);
    }

    /** @return array<int,int> productId => uitgegeven in die maand */
    public static function uitgegevenPerProductInMaand(int $maand, int $jaar): array
    {
        $uit = [];
        foreach (self::all("
            SELECT pp.Product_idProduct AS id, SUM(pp.Aantal) AS N
              FROM Pakket pk JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = pk.idPakket
             WHERE pk.Status = 'Uitgegeven' AND MONTH(pk.Uitgiftedatum) = ? AND YEAR(pk.Uitgiftedatum) = ?
          GROUP BY pp.Product_idProduct", [$maand, $jaar]) as $r) {
            $uit[(int) $r['id']] = (int) $r['N'];
        }
        return $uit;
    }

    /** RAP-2: uitgegeven per postcode en categorie in een maand. */
    public static function uitgegevenPerPostcode(int $maand, int $jaar): array
    {
        return self::all("
            SELECT k.Postcode, c.Naam AS Categorie, SUM(pp.Aantal) AS Aantal,
                   COUNT(DISTINCT pk.idPakket) AS Pakketten, COUNT(DISTINCT k.idKlant) AS Gezinnen
              FROM Pakket pk
              JOIN Klant k ON k.idKlant = pk.Klant_idKlant
              JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = pk.idPakket
              JOIN Product p   ON p.idProduct = pp.Product_idProduct
              JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
             WHERE pk.Status = 'Uitgegeven' AND MONTH(pk.Uitgiftedatum) = ? AND YEAR(pk.Uitgiftedatum) = ?
          GROUP BY k.Postcode, c.idCategorie
          ORDER BY k.Postcode, c.Naam", [$maand, $jaar]);
    }

    /** Totalen per postcode (pakketten en gezinnen over alle categorieën samen). */
    public static function postcodeTotalen(int $maand, int $jaar): array
    {
        $uit = [];
        foreach (self::all("
            SELECT k.Postcode, COUNT(DISTINCT pk.idPakket) AS Pakketten, COUNT(DISTINCT k.idKlant) AS Gezinnen
              FROM Pakket pk JOIN Klant k ON k.idKlant = pk.Klant_idKlant
             WHERE pk.Status = 'Uitgegeven' AND MONTH(pk.Uitgiftedatum) = ? AND YEAR(pk.Uitgiftedatum) = ?
          GROUP BY k.Postcode", [$maand, $jaar]) as $r) {
            $uit[$r['Postcode']] = ['pakketten' => (int) $r['Pakketten'], 'gezinnen' => (int) $r['Gezinnen']];
        }
        return $uit;
    }
}
