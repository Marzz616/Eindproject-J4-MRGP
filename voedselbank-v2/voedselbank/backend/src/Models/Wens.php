<?php
declare(strict_types=1);

namespace App\Models;

/** Dieetwensen en allergieën (de keuzelijst). */
final class Wens extends Model
{
    public static function lijst(): array
    {
        return self::all('SELECT idWens, Naam, Streng FROM Wens ORDER BY Streng DESC, Naam');
    }

    public static function opNaam(string $naam): ?array
    {
        return self::one('SELECT idWens, Naam, Streng FROM Wens WHERE Naam = ?', [$naam]);
    }

    /** Zonder conflict-tag is de wens puur informatief en geeft hij nergens een waarschuwing. */
    public static function maak(string $naam, bool $streng, ?string $tag): int
    {
        self::execute("
            INSERT INTO Wens (Naam, Omschrijving, Streng, Conflict_tag)
            VALUES (?, 'Eigen wens, ingevoerd bij een klant', ?, ?)",
            [$naam, $streng ? 1 : 0, $tag]);
        return self::lastId();
    }

    /**
     * Alle wensen van een gezin die met producten kunnen botsen, per conflict-tag.
     * Ruwe rijen; de DieetControle-service maakt er een profiel van.
     */
    public static function conflictenVanGezin(int $klantId): array
    {
        return self::all("
            SELECT w.Conflict_tag, w.Naam AS Wens, w.Streng, g.Naam AS Lid,
                   gw.Toch_toestaan, gw.Toelichting
              FROM Gezinslid g
              JOIN Gezinslid_has_Wens gw ON gw.Gezinslid_idGezinslid = g.idGezinslid
              JOIN Wens w ON w.idWens = gw.Wens_idWens
             WHERE g.Klant_idKlant = ? AND w.Conflict_tag IS NOT NULL
          ORDER BY w.Streng DESC, w.Naam, g.Geboortedatum", [$klantId]);
    }

    /** Wensnamen per gezinslid van één klant. */
    public static function perLid(int $klantId): array
    {
        $uit = [];
        foreach (self::all("
            SELECT gw.Gezinslid_idGezinslid AS lid, w.Naam
              FROM Gezinslid_has_Wens gw
              JOIN Wens w ON w.idWens = gw.Wens_idWens
              JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid
             WHERE g.Klant_idKlant = ?", [$klantId]) as $r) {
            $uit[(int) $r['lid']][] = $r['Naam'];
        }
        return $uit;
    }
}
