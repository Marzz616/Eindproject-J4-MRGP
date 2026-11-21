<?php
declare(strict_types=1);

namespace App\Models;

/** Leveranciers (voedseldonateurs) en hun leveringen. */
final class Leverancier extends Model
{
    public static function lijst(): array
    {
        return self::all("
            SELECT l.*,
                   (SELECT COUNT(*) FROM Levering v WHERE v.Leverancier_idLeverancier = l.idLeverancier) AS Aantal_leveringen,
                   (SELECT MAX(v.Leverdatum) FROM Levering v WHERE v.Leverancier_idLeverancier = l.idLeverancier) AS Laatste_levering
              FROM Leverancier l
          ORDER BY l.Actief DESC, l.Bedrijfsnaam");
    }

    public static function vind(int $id): ?array
    {
        return self::one('SELECT * FROM Leverancier WHERE idLeverancier = ?', [$id]);
    }

    /** Alle leveringen met hun inhoud in één query. */
    public static function leveringen(): array
    {
        return self::all("
            SELECT v.idLevering, v.Leverancier_idLeverancier, v.Leverdatum, v.Levertijd,
                   g.Naam AS Verwerkt_door,
                   GROUP_CONCAT(CONCAT(p.Productnaam, ' \u{00d7} ', lp.Aantal) ORDER BY p.Productnaam SEPARATOR ', ') AS Inhoud,
                   COALESCE(SUM(lp.Aantal), 0) AS Totaal
              FROM Levering v
         LEFT JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
         LEFT JOIN Product p ON p.idProduct = lp.Product_idProduct
         LEFT JOIN Gebruiker g ON g.idGebruiker = v.Verwerkt_door
          GROUP BY v.idLevering
          ORDER BY v.Leverdatum DESC");
    }

    public static function naamBestaat(string $naam, int $behalveId): bool
    {
        return self::value('SELECT idLeverancier FROM Leverancier WHERE Bedrijfsnaam = ? AND idLeverancier <> ?',
            [$naam, $behalveId]) !== null;
    }

    private const VELDEN = ['Bedrijfsnaam', 'Adres', 'Postcode', 'Plaats', 'Contactpersoon', 'Email',
                            'Telefoon', 'Categorie', 'Volgende_leverdatum', 'Volgende_levertijd'];

    public static function maak(array $v): int
    {
        self::execute('INSERT INTO Leverancier (' . implode(', ', self::VELDEN) . ') VALUES (?,?,?,?,?,?,?,?,?,?)',
            self::waarden($v));
        return self::lastId();
    }

    public static function wijzig(int $id, array $v): void
    {
        $set = implode(', ', array_map(static fn(string $k): string => $k . ' = ?', self::VELDEN));
        self::execute("UPDATE Leverancier SET $set WHERE idLeverancier = ?", [...self::waarden($v), $id]);
    }

    private static function waarden(array $v): array
    {
        return [$v['bedrijfsnaam'], $v['adres'], $v['postcode'], $v['plaats'], $v['contactpersoon'],
                $v['email'], $v['telefoon'], $v['categorie'], $v['volgendeDatum'], $v['volgendeTijd']];
    }

    public static function zetActief(int $id, bool $actief): int
    {
        return self::execute('UPDATE Leverancier SET Actief = ? WHERE idLeverancier = ?', [$actief ? 1 : 0, $id]);
    }

    public static function aantalLeveringen(int $id): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Levering WHERE Leverancier_idLeverancier = ?', [$id]);
    }

    public static function verwijder(int $id): void
    {
        self::execute('DELETE FROM Leverancier WHERE idLeverancier = ?', [$id]);
    }

    /**
     * Boekt een levering en hoogt de voorraad op (US-LEV-04/05).
     * Moet binnen een transactie worden aangeroepen.
     *
     * @param array<int,int> $regels productId => aantal
     */
    public static function boekLevering(int $leverancierId, string $datum, ?string $tijd, int $gebruikerId, array $regels): int
    {
        self::execute('INSERT INTO Levering (Leverancier_idLeverancier, Leverdatum, Levertijd, Verwerkt_door) VALUES (?,?,?,?)',
            [$leverancierId, $datum, $tijd, $gebruikerId]);
        $leveringId = self::lastId();

        $regel = self::db()->prepare("
            INSERT INTO Levering_has_Product (Levering_idLevering, Product_idProduct, Aantal) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE Aantal = Aantal + VALUES(Aantal)");
        foreach ($regels as $productId => $aantal) {
            $regel->execute([$leveringId, $productId, $aantal]);
            Product::wijzigVoorraad($productId, $aantal);       // voorraad automatisch bij
        }

        // de geplande leverdatum is nu geweest: opschonen
        self::execute("
            UPDATE Leverancier SET Volgende_leverdatum = NULL, Volgende_levertijd = NULL
             WHERE idLeverancier = ? AND Volgende_leverdatum <= ?", [$leverancierId, $datum]);

        return $leveringId;
    }

    /** Leveranciers met een geplande levering vanaf vandaag. */
    public static function verwacht(int $limiet): array
    {
        return self::all("
            SELECT Bedrijfsnaam, Volgende_leverdatum, Volgende_levertijd, Contactpersoon
              FROM Leverancier
             WHERE Actief = 1 AND Volgende_leverdatum IS NOT NULL AND Volgende_leverdatum >= CURDATE()
          ORDER BY Volgende_leverdatum, Volgende_levertijd LIMIT " . (int) $limiet);
    }

    /** De laatste leveringen met het grootste product erin. */
    public static function recenteLeveringen(int $limiet): array
    {
        return self::all("
            SELECT v.idLevering, v.Leverdatum, l.Bedrijfsnaam,
                   COALESCE(SUM(lp.Aantal), 0) AS Aantal,
                   (SELECT p.Productnaam FROM Levering_has_Product x
                      JOIN Product p ON p.idProduct = x.Product_idProduct
                     WHERE x.Levering_idLevering = v.idLevering
                  ORDER BY x.Aantal DESC LIMIT 1) AS Grootste
              FROM Levering v
              JOIN Leverancier l ON l.idLeverancier = v.Leverancier_idLeverancier
         LEFT JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering
          GROUP BY v.idLevering
          ORDER BY v.Leverdatum DESC, v.idLevering DESC LIMIT " . (int) $limiet);
    }
}
