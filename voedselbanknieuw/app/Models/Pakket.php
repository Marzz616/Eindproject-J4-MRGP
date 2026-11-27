<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Facades\DB;

/**
 * Voedselpakketten en hun inhoud.
 * Statussen: Concept -> Klaar -> Uitgegeven. Uitgegeven pakketten zijn historie.
 */
final class Pakket extends Basis
{
    /** Geaccepteerde klanten met de stand van deze week (voor het samenstelscherm). */
    public static function klantenVoorWeek(string $maandag): array
    {
        return self::all("
            SELECT k.idKlant, k.Gezinsnaam,
                   (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte,
                   (SELECT COUNT(*) FROM Pakket p WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?) AS Deze_week,
                   (SELECT p.Status FROM Pakket p WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?
                     ORDER BY p.idPakket DESC LIMIT 1) AS Week_status,
                   (SELECT MAX(p.Uitgiftedatum) FROM Pakket p WHERE p.Klant_idKlant = k.idKlant AND p.Status = 'Uitgegeven') AS Laatste
              FROM Klant k
             WHERE k.Geaccepteerd = 1
          ORDER BY k.Gezinsnaam", [$maandag, $maandag]);
    }

    /** Het meest recente pakket van deze klant in deze week, of null. */
    public static function vanDezeWeek(int $klantId, string $maandag): ?array
    {
        return self::one("
            SELECT idPakket, Status, Samenstellingsdatum FROM Pakket
             WHERE Klant_idKlant = ? AND Samenstellingsdatum >= ?
          ORDER BY idPakket DESC LIMIT 1", [$klantId, $maandag]);
    }

    /** Heeft deze klant deze week al een (ander) pakket? (US-PAK-06) */
    public static function anderPakketDezeWeek(int $klantId, string $maandag, int $behalveId): bool
    {
        return self::value("
            SELECT idPakket FROM Pakket WHERE Klant_idKlant = ? AND Samenstellingsdatum >= ? AND idPakket <> ?",
            [$klantId, $maandag, $behalveId]) !== null;
    }

    public static function lijst(): array
    {
        return self::all("
            SELECT p.idPakket, p.Status, p.Samenstellingsdatum, p.Uitgiftedatum, p.Opgehaald,
                   k.Gezinsnaam, k.idKlant, s.Naam AS Samensteller, u.Naam AS Uitgever,
                   COALESCE(SUM(pp.Aantal), 0) AS Aantal_producten
              FROM Pakket p
              JOIN Klant k ON k.idKlant = p.Klant_idKlant
         LEFT JOIN Gebruiker s ON s.idGebruiker = p.Samengesteld_door
         LEFT JOIN Gebruiker u ON u.idGebruiker = p.Uitgegeven_door
         LEFT JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
          GROUP BY p.idPakket
          ORDER BY p.Samenstellingsdatum DESC, p.idPakket DESC");
    }

    /** Eén pakket met klant, samensteller en uitgever. */
    public static function vind(int $id): ?array
    {
        return self::one("
            SELECT p.*, k.Gezinsnaam, k.idKlant, k.Opmerkingen, k.Adres, k.Plaats,
                   CONCAT('K-', LPAD(k.idKlant, 3, '0')) AS Klantnummer,
                   s.Naam AS Samensteller, u.Naam AS Uitgever,
                   (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte
              FROM Pakket p
              JOIN Klant k ON k.idKlant = p.Klant_idKlant
         LEFT JOIN Gebruiker s ON s.idGebruiker = p.Samengesteld_door
         LEFT JOIN Gebruiker u ON u.idGebruiker = p.Uitgegeven_door
             WHERE p.idPakket = ?", [$id]);
    }

    public static function status(int $id): ?string
    {
        $s = self::value('SELECT Status FROM Pakket WHERE idPakket = ?', [$id]);
        return $s === null ? null : (string) $s;
    }

    /** Inhoud van één pakket, op categorie gesorteerd. */
    public static function inhoud(int $id): array
    {
        return self::all("
            SELECT pp.Aantal, pr.idProduct, pr.Productnaam, pr.Allergenen, pr.Locatie, c.Naam AS Categorie
              FROM Product_has_Pakket pp
              JOIN Product pr ON pr.idProduct = pp.Product_idProduct
              JOIN Categorie c ON c.idCategorie = pr.Categorie_idCategorie
             WHERE pp.Pakket_idPakket = ?
          ORDER BY c.Naam, pr.Productnaam", [$id]);
    }

    /** @return array<int,int> productId => aantal */
    public static function aantallen(int $id): array
    {
        $uit = [];
        foreach (self::all('SELECT Product_idProduct, Aantal FROM Product_has_Pakket WHERE Pakket_idPakket = ?', [$id]) as $r) {
            $uit[(int) $r['Product_idProduct']] = (int) $r['Aantal'];
        }
        return $uit;
    }

    /** Regels van meerdere pakketten tegelijk: pakketId => [{product, aantal}]. */
    public static function regelsVan(array $pakketIds): array
    {
        if (!$pakketIds) {
            return [];
        }
        $uit = [];
        foreach (self::all("
            SELECT pp.Pakket_idPakket AS pakket, p.Productnaam, pp.Aantal
              FROM Product_has_Pakket pp JOIN Product p ON p.idProduct = pp.Product_idProduct
             WHERE pp.Pakket_idPakket IN (" . self::plaatshouders($pakketIds) . ")
          ORDER BY p.Productnaam", $pakketIds) as $r) {
            $uit[(int) $r['pakket']][] = ['product' => $r['Productnaam'], 'aantal' => (int) $r['Aantal']];
        }
        return $uit;
    }

    public static function maak(int $klantId, string $status, int $gebruikerId): int
    {
        self::execute("INSERT INTO Pakket (Klant_idKlant, Samenstellingsdatum, Status, Samengesteld_door) VALUES (?, CURDATE(), ?, ?)",
            [$klantId, $status, $gebruikerId]);
        return self::lastId();
    }

    public static function zetStatus(int $id, string $status): void
    {
        self::execute('UPDATE Pakket SET Status = ? WHERE idPakket = ?', [$status, $id]);
    }

    /**
     * Vervangt de inhoud en verwerkt het verschil in de voorraad (US-PAK-07/08):
     * de oude inhoud gaat terug in de voorraad, de nieuwe gaat eraf.
     *
     * @param array<int,int> $oud   productId => aantal
     * @param array<int,int> $nieuw productId => aantal
     */
    public static function vervangInhoud(int $id, array $oud, array $nieuw): void
    {
        foreach ($oud as $pid => $aantal) {
            Product::wijzigVoorraad($pid, $aantal);
        }
        self::execute('DELETE FROM Product_has_Pakket WHERE Pakket_idPakket = ?', [$id]);

        foreach ($nieuw as $pid => $aantal) {
            self::execute('INSERT INTO Product_has_Pakket (Pakket_idPakket, Product_idProduct, Aantal) VALUES (?,?,?)',
                [$id, $pid, $aantal]);
            Product::wijzigVoorraad($pid, -$aantal);
        }
    }

    /** Verwijdert het pakket en zet de producten terug in de voorraad. */
    public static function verwijderEnTerugboeken(int $id): void
    {
        foreach (self::aantallen($id) as $pid => $aantal) {
            Product::wijzigVoorraad($pid, $aantal);
        }
        self::execute('DELETE FROM Pakket WHERE idPakket = ?', [$id]);
    }

    /* ------------------------------------------------ uitgifte */

    /** Alle pakketten van deze week voor de uitgiftewachtrij. */
    public static function wachtrij(string $maandag): array
    {
        return self::all("
            SELECT p.idPakket, p.Status, p.Uitgiftedatum, p.Identiteit_gecontroleerd,
                   k.Gezinsnaam, CONCAT('K-', LPAD(k.idKlant, 3, '0')) AS Klantnummer,
                   (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte,
                   COALESCE(SUM(pp.Aantal), 0) AS Aantal
              FROM Pakket p
              JOIN Klant k ON k.idKlant = p.Klant_idKlant
         LEFT JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
             WHERE p.Samenstellingsdatum >= ?
          GROUP BY p.idPakket
          ORDER BY FIELD(p.Status,'Klaar','Concept','Uitgegeven'), k.Gezinsnaam", [$maandag]);
    }

    /** Klant-ids op naam of klantnummer (8, K-008, k008, "El Hassani"). */
    public static function zoekKlanten(string $term): array
    {
        $cijfers = preg_replace('/\D/', '', $term);
        return array_map('intval', self::column("
            SELECT k.idKlant FROM Klant k
             WHERE k.Gezinsnaam LIKE ?
                OR CONCAT('K-', LPAD(k.idKlant, 3, '0')) = UPPER(?)
                OR (? <> '' AND k.idKlant = ?)
          ORDER BY k.Gezinsnaam LIMIT 10",
            ['%' . $term . '%', $term, $cijfers, $cijfers === '' ? 0 : (int) $cijfers]));
    }

    /** Het pakket dat voor de uitgifte het meest relevant is: eerst Klaar, dan Uitgegeven, dan Concept. */
    public static function relevantVoorUitgifte(int $klantId, string $maandag): ?int
    {
        $id = self::value("
            SELECT idPakket FROM Pakket WHERE Klant_idKlant = ? AND Samenstellingsdatum >= ?
          ORDER BY FIELD(Status,'Klaar','Uitgegeven','Concept'), idPakket DESC LIMIT 1", [$klantId, $maandag]);
        return $id === null ? null : (int) $id;
    }

    /** Legitimatie vastleggen; lukt alleen bij een pakket dat nog niet is uitgegeven. */
    public static function legitimeer(int $id, string $soort): bool
    {
        return self::execute("
            UPDATE Pakket SET Identiteit_gecontroleerd = 1, Legitimatie = ?
             WHERE idPakket = ? AND Status <> 'Uitgegeven'", [$soort, $id]) > 0;
    }

    public static function geefUit(int $id, int $gebruikerId): void
    {
        self::execute("
            UPDATE Pakket SET Status = 'Uitgegeven', Opgehaald = 1, Uitgiftedatum = CURDATE(), Uitgegeven_door = ?
             WHERE idPakket = ?", [$gebruikerId, $id]);
    }

    /* ------------------------------------------------ dashboard */

    public static function aantalVanaf(string $maandag, ?string $status = null): int
    {
        return $status === null
            ? (int) self::value('SELECT COUNT(*) FROM Pakket WHERE Samenstellingsdatum >= ?', [$maandag])
            : (int) self::value('SELECT COUNT(*) FROM Pakket WHERE Samenstellingsdatum >= ? AND Status = ?', [$maandag, $status]);
    }

    public static function aantalMetStatus(string $status): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Pakket WHERE Status = ?', [$status]);
    }

    /** Geaccepteerde gezinnen zonder pakket deze week; langst geleden bovenaan. */
    public static function gezinnenZonderPakket(string $maandag, ?int $limiet = null): array
    {
        return self::all("
            SELECT k.idKlant, k.Gezinsnaam,
                   (SELECT COUNT(*) FROM Gezinslid gl WHERE gl.Klant_idKlant = k.idKlant) AS Grootte,
                   (SELECT MAX(p.Uitgiftedatum) FROM Pakket p WHERE p.Klant_idKlant = k.idKlant AND p.Status = 'Uitgegeven') AS Laatste
              FROM Klant k
             WHERE k.Geaccepteerd = 1
               AND NOT EXISTS (SELECT 1 FROM Pakket p WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?)
          ORDER BY Laatste IS NULL DESC, Laatste, k.Gezinsnaam" . ($limiet ? ' LIMIT ' . (int) $limiet : ''), [$maandag]);
    }

    /** Concepten van één gebruiker die nog afgemaakt moeten worden. */
    public static function conceptenVan(int $gebruikerId, int $limiet): array
    {
        return self::all("
            SELECT p.idPakket, k.Gezinsnaam, p.Samenstellingsdatum,
                   (SELECT COALESCE(SUM(pp.Aantal),0) FROM Product_has_Pakket pp WHERE pp.Pakket_idPakket = p.idPakket) AS Stuks
              FROM Pakket p JOIN Klant k ON k.idKlant = p.Klant_idKlant
             WHERE p.Status = 'Concept' AND p.Samengesteld_door = ?
          ORDER BY p.Samenstellingsdatum DESC LIMIT " . (int) $limiet, [$gebruikerId]);
    }

    /** Pakketten met status Klaar, oudste eerst. */
    public static function klaarVoorUitgifte(int $limiet): array
    {
        return self::all("
            SELECT p.idPakket, k.Gezinsnaam, p.Samenstellingsdatum,
                   (SELECT COALESCE(SUM(pp.Aantal),0) FROM Product_has_Pakket pp WHERE pp.Pakket_idPakket = p.idPakket) AS Stuks
              FROM Pakket p JOIN Klant k ON k.idKlant = p.Klant_idKlant
             WHERE p.Status = 'Klaar'
          ORDER BY p.Samenstellingsdatum LIMIT " . (int) $limiet);
    }
}
