<?php
declare(strict_types=1);

namespace App\Models;

/** Productcategorieën (US-VRD-11 / PRD-5). */
final class Categorie extends Basis
{
    public static function lijst(): array
    {
        return self::all("
            SELECT c.idCategorie, c.Naam, c.Kort, c.Omschrijving,
                   (SELECT COUNT(*) FROM Product p WHERE p.Categorie_idCategorie = c.idCategorie) AS Aantal
              FROM Categorie c ORDER BY c.idCategorie");
    }

    public static function namen(): array
    {
        return self::column('SELECT Naam FROM Categorie ORDER BY Naam');
    }

    public static function vind(int $id): ?array
    {
        return self::one("
            SELECT c.*, (SELECT COUNT(*) FROM Product p WHERE p.Categorie_idCategorie = c.idCategorie) AS Aantal
              FROM Categorie c WHERE c.idCategorie = ?", [$id]);
    }

    public static function naamBestaat(string $naam, int $behalveId): bool
    {
        return self::value('SELECT idCategorie FROM Categorie WHERE Naam = ? AND idCategorie <> ?', [$naam, $behalveId]) !== null;
    }

    public static function maak(string $naam, string $kort, string $oms): int
    {
        self::execute('INSERT INTO Categorie (Naam, Kort, Omschrijving) VALUES (?,?,?)', [$naam, $kort, $oms]);
        return self::lastId();
    }

    public static function wijzig(int $id, string $naam, string $kort, string $oms): void
    {
        self::execute('UPDATE Categorie SET Naam=?, Kort=?, Omschrijving=? WHERE idCategorie=?', [$naam, $kort, $oms, $id]);
    }

    public static function verwijder(int $id): void
    {
        self::execute('DELETE FROM Categorie WHERE idCategorie = ?', [$id]);
    }

    /** Zet alle producten van de ene naar de andere categorie; geeft het aantal terug. */
    public static function verplaatsProducten(int $van, int $naar): int
    {
        return self::execute('UPDATE Product SET Categorie_idCategorie = ? WHERE Categorie_idCategorie = ?', [$naar, $van]);
    }
}
