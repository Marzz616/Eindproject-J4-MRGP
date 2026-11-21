<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Producten, voorraad en afvoer. */
final class Product extends Model
{
    public static function lijst(): array
    {
        return self::all("
            SELECT p.idProduct, p.Productnaam, p.EAN, p.Aantal_voorraad, p.Min_voorraad,
                   p.Waarde, p.Houdbaar_tot, p.Locatie, p.Allergenen,
                   c.idCategorie, c.Naam AS CategorieNaam, c.Kort AS CategorieKort,
                   (SELECT COUNT(*) FROM Product_has_Pakket pp WHERE pp.Product_idProduct = p.idProduct) AS InPakketten
              FROM Product p
              JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
          ORDER BY p.Productnaam");
    }

    /** Producten voor het samenstelscherm, gegroepeerd op categorie. */
    public static function voorSamenstellen(): array
    {
        return self::all("
            SELECT p.idProduct, p.Productnaam, p.Aantal_voorraad, p.Houdbaar_tot,
                   p.Allergenen, p.Locatie, c.Naam AS Categorie, c.idCategorie
              FROM Product p JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
          ORDER BY c.Naam, p.Productnaam");
    }

    public static function vind(int $id): ?array
    {
        return self::one('SELECT * FROM Product WHERE idProduct = ?', [$id]);
    }

    /** @return array<int,array> productId => rij */
    public static function vindMeerdere(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $uit = [];
        foreach (self::all('SELECT * FROM Product WHERE idProduct IN (' . Database::placeholders($ids) . ')', $ids) as $r) {
            $uit[(int) $r['idProduct']] = $r;
        }
        return $uit;
    }

    public static function totaleVoorraad(): int
    {
        return (int) self::value('SELECT COALESCE(SUM(Aantal_voorraad),0) FROM Product');
    }

    public static function aantalSoorten(): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Product');
    }

    /** Producten met dezelfde naam of EAN (behalve $behalveId), voor US-VRD-09/10. */
    public static function dubbel(string $naam, string $ean, int $behalveId): array
    {
        return self::all('SELECT Productnaam, EAN FROM Product WHERE (Productnaam = ? OR EAN = ?) AND idProduct <> ?',
            [$naam, $ean, $behalveId]);
    }

    private const VELDEN = ['Productnaam', 'EAN', 'Aantal_voorraad', 'Min_voorraad', 'Waarde',
                            'Houdbaar_tot', 'Locatie', 'Allergenen', 'Categorie_idCategorie'];

    public static function maak(array $v): int
    {
        self::execute('INSERT INTO Product (' . implode(', ', self::VELDEN) . ') VALUES (?,?,?,?,?,?,?,?,?)', self::waarden($v));
        return self::lastId();
    }

    public static function wijzig(int $id, array $v): void
    {
        $set = implode(', ', array_map(static fn(string $k): string => $k . ' = ?', self::VELDEN));
        self::execute("UPDATE Product SET $set WHERE idProduct = ?", [...self::waarden($v), $id]);
    }

    private static function waarden(array $v): array
    {
        return [$v['naam'], $v['ean'], $v['voorraad'], $v['min'], $v['waarde'],
                $v['tht'] ?: null, $v['locatie'] ?: null, $v['allergenen'] ?: null, $v['categorie']];
    }

    /** Voorraad op een vast getal zetten (US-VRD-03). */
    public static function zetVoorraad(int $id, int $aantal): void
    {
        self::execute('UPDATE Product SET Aantal_voorraad = ? WHERE idProduct = ?', [$aantal, $id]);
    }

    /** Voorraad ophogen (+) of verlagen (-). */
    public static function wijzigVoorraad(int $id, int $delta): void
    {
        self::execute('UPDATE Product SET Aantal_voorraad = Aantal_voorraad + ? WHERE idProduct = ?', [$delta, $id]);
    }

    /** Voorraad met een rij-lock, binnen een transactie (voorkomt dubbel uitgeven). */
    public static function voorraadVoorUpdate(int $id): ?array
    {
        return self::one('SELECT Productnaam, Aantal_voorraad FROM Product WHERE idProduct = ? FOR UPDATE', [$id]);
    }

    /** In hoeveel pakketten, leveringen en afvoerregels dit product voorkomt. */
    public static function historie(int $id): array
    {
        return self::one("
            SELECT (SELECT COUNT(*) FROM Product_has_Pakket   WHERE Product_idProduct = ?) AS pakket,
                   (SELECT COUNT(*) FROM Levering_has_Product WHERE Product_idProduct = ?) AS levering,
                   (SELECT COUNT(*) FROM Afvoer               WHERE Product_idProduct = ?) AS afvoer", [$id, $id, $id]);
    }

    public static function verwijder(int $id): void
    {
        self::execute('DELETE FROM Product WHERE idProduct = ?', [$id]);
    }

    /** Afvoeren: blijft als regel bewaard voor het verspillingsrapport (US-RAP-04). */
    public static function voerAf(int $id, int $aantal, string $reden, ?string $toelichting, int $gebruikerId): void
    {
        self::execute("
            INSERT INTO Afvoer (Product_idProduct, Aantal, Datum, Reden, Toelichting, Gebruiker_idGebruiker)
            VALUES (?,?,CURDATE(),?,?,?)", [$id, $aantal, $reden, $toelichting, $gebruikerId]);
        self::wijzigVoorraad($id, -$aantal);
    }

    /* ------------------------------------------------ signalen voor het dashboard */

    public static function bijnaOverDatum(int $dagen, int $limiet): array
    {
        return self::all("
            SELECT idProduct, Productnaam, Houdbaar_tot, Locatie, Aantal_voorraad FROM Product
             WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot >= CURDATE() AND Houdbaar_tot <= CURDATE() + INTERVAL ? DAY
          ORDER BY Houdbaar_tot LIMIT " . (int) $limiet, [$dagen]);
    }

    public static function aantalBijnaOverDatum(int $dagen): int
    {
        return (int) self::value("
            SELECT COUNT(*) FROM Product
             WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot >= CURDATE() AND Houdbaar_tot <= CURDATE() + INTERVAL ? DAY", [$dagen]);
    }

    public static function overDatum(int $limiet): array
    {
        return self::all("
            SELECT idProduct, Productnaam, Houdbaar_tot, Locatie, Aantal_voorraad FROM Product
             WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot < CURDATE()
          ORDER BY Houdbaar_tot LIMIT " . (int) $limiet);
    }

    public static function aantalOverDatum(): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Product WHERE Houdbaar_tot IS NOT NULL AND Houdbaar_tot < CURDATE()');
    }

    public static function onderMinimum(int $limiet): array
    {
        return self::all("
            SELECT idProduct, Productnaam, Aantal_voorraad, Min_voorraad, Locatie FROM Product
             WHERE Aantal_voorraad < Min_voorraad
          ORDER BY (Min_voorraad - Aantal_voorraad) DESC LIMIT " . (int) $limiet);
    }

    public static function aantalOnderMinimum(): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Product WHERE Aantal_voorraad < Min_voorraad');
    }
}
