<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Klanten (gezinnen), gezinsleden en hun dieetwensen. */
final class Klant extends Model
{
    /** Alle klanten met gezinsgrootte, laatste uitgifte en "pakket deze week". */
    public static function lijst(): array
    {
        return self::all("
            SELECT k.idKlant, k.Klantnummer, k.Gezinsnaam, k.Adres, k.Postcode, k.Plaats,
                   k.Telefoon, k.Email, k.Aanmelddatum, k.Geaccepteerd, k.Opmerkingen,
                   k.Gezinsgrootte, k.Laatste_uitgifte,
                   (SELECT COUNT(*) FROM Pakket p
                     WHERE p.Klant_idKlant = k.idKlant
                       AND p.Samenstellingsdatum >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)
                   ) AS PakketDezeWeek
              FROM vw_Klant k
          ORDER BY k.Gezinsnaam");
    }

    public static function vind(int $id): ?array
    {
        return self::one('SELECT * FROM Klant WHERE idKlant = ?', [$id]);
    }

    /** Gezinsleden van meerdere klanten tegelijk (één query in plaats van één per klant). */
    public static function ledenVan(array $klantIds): array
    {
        if (!$klantIds) {
            return [];
        }
        return self::all("
            SELECT idGezinslid, Naam, Geboortedatum, Relatie, Klant_idKlant, Leeftijd, Type
              FROM vw_Gezinslid
             WHERE Klant_idKlant IN (" . Database::placeholders($klantIds) . ")
          ORDER BY FIELD(Relatie,'Hoofdaanvrager','Ouder (Vader)','Ouder (Moeder)','Partner','Kind','Inwonend'),
                   Geboortedatum", $klantIds);
    }

    /** Dieetwensen per gezinslid van meerdere klanten tegelijk. */
    public static function wensenVan(array $klantIds): array
    {
        if (!$klantIds) {
            return [];
        }
        return self::all("
            SELECT gw.Gezinslid_idGezinslid AS lid, w.idWens, w.Naam, w.Streng,
                   gw.Toch_toestaan, gw.Toelichting
              FROM Gezinslid_has_Wens gw
              JOIN Wens w      ON w.idWens = gw.Wens_idWens
              JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid
             WHERE g.Klant_idKlant IN (" . Database::placeholders($klantIds) . ")
          ORDER BY w.Naam", $klantIds);
    }

    /** Gezinsleden van één klant (met leeftijd en type uit de view). */
    public static function leden(int $klantId): array
    {
        return self::all('SELECT * FROM vw_Gezinslid WHERE Klant_idKlant = ? ORDER BY Geboortedatum', [$klantId]);
    }

    public static function gezinsgrootte(int $klantId): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Gezinslid WHERE Klant_idKlant = ?', [$klantId]);
    }

    /** Namen van alle wensen in het gezin (zonder dubbelen). */
    public static function wensNamen(int $klantId): array
    {
        return self::column("
            SELECT DISTINCT w.Naam FROM Gezinslid_has_Wens gw
              JOIN Wens w ON w.idWens = gw.Wens_idWens
              JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid
             WHERE g.Klant_idKlant = ?", [$klantId]);
    }

    public static function aantalPakketten(int $klantId): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Pakket WHERE Klant_idKlant = ?', [$klantId]);
    }

    public static function maak(array $v): int
    {
        self::execute("
            INSERT INTO Klant (Gezinsnaam, Adres, Postcode, Plaats, Telefoon, Email,
                               Opmerkingen, Aanmelddatum, Geaccepteerd)
            VALUES (?,?,?,?,?,?,?,CURDATE(),NULL)",
            [$v['naam'], $v['adres'], $v['postcode'], $v['plaats'], $v['telefoon'], $v['email'], $v['opmerkingen'] ?: null]);
        return self::lastId();
    }

    public static function wijzigContact(int $id, array $v): void
    {
        self::execute("
            UPDATE Klant SET Gezinsnaam=?, Adres=?, Postcode=?, Plaats=?, Telefoon=?, Email=?, Opmerkingen=?
             WHERE idKlant=?",
            [$v['naam'], $v['adres'], $v['postcode'], $v['plaats'], $v['telefoon'], $v['email'], $v['opmerkingen'] ?: null, $id]);
    }

    /**
     * Vervangt de gezinssamenstelling. Gezinsleden en hun wensen worden
     * opnieuw opgebouwd; dat is eenvoudiger en veiliger dan rij voor rij vergelijken.
     *
     * Een uitzondering ("het gezin wil dit tóch") telt alleen als er een reden bij staat.
     */
    public static function vervangLeden(int $klantId, array $leden): void
    {
        self::execute('DELETE FROM Gezinslid WHERE Klant_idKlant = ?', [$klantId]);

        $insLid = self::db()->prepare('INSERT INTO Gezinslid (Naam, Geboortedatum, Relatie, Klant_idKlant) VALUES (?,?,?,?)');
        $insWens = self::db()->prepare("
            INSERT IGNORE INTO Gezinslid_has_Wens (Gezinslid_idGezinslid, Wens_idWens, Toch_toestaan, Toelichting)
            VALUES (?,?,?,?)");

        foreach ($leden as $lid) {
            $insLid->execute([$lid['naam'], $lid['gb'], $lid['relatie'], $klantId]);
            $lidId = (int) self::db()->lastInsertId();

            foreach ($lid['wensen'] as $wensId) {
                $reden = trim((string) ($lid['uitzonderingen'][$wensId] ?? ''));
                $insWens->execute([$lidId, $wensId, $reden !== '' ? 1 : 0, $reden !== '' ? $reden : null]);
            }
        }
    }

    /** null = wacht op beoordeling, true = geaccepteerd, false = afgewezen/non-actief. */
    public static function zetStatus(int $id, ?bool $geaccepteerd): void
    {
        self::execute('UPDATE Klant SET Geaccepteerd = ? WHERE idKlant = ?',
            [$geaccepteerd === null ? null : (int) $geaccepteerd, $id]);
    }

    /** Gezinsleden en wensen gaan mee via ON DELETE CASCADE. */
    public static function verwijder(int $id): void
    {
        self::execute('DELETE FROM Klant WHERE idKlant = ?', [$id]);
    }

    /** Pakketgeschiedenis van één klant, nieuwste eerst. */
    public static function pakketten(int $klantId): array
    {
        return self::all("
            SELECT idPakket, Samenstellingsdatum, Uitgiftedatum, Opgehaald, Status
              FROM Pakket WHERE Klant_idKlant = ?
          ORDER BY Samenstellingsdatum DESC", [$klantId]);
    }

    public static function aantalMetStatus(?bool $geaccepteerd): int
    {
        return $geaccepteerd === null
            ? (int) self::value('SELECT COUNT(*) FROM Klant WHERE Geaccepteerd IS NULL')
            : (int) self::value('SELECT COUNT(*) FROM Klant WHERE Geaccepteerd = ?', [(int) $geaccepteerd]);
    }

    /** Aanvragen die nog beoordeeld moeten worden, oudste eerst. */
    public static function wachtendOpBeoordeling(int $limiet): array
    {
        return self::all("
            SELECT idKlant, Gezinsnaam, Aanmelddatum,
                   (SELECT COUNT(*) FROM Gezinslid gl WHERE gl.Klant_idKlant = Klant.idKlant) AS Grootte
              FROM Klant WHERE Geaccepteerd IS NULL ORDER BY Aanmelddatum LIMIT " . (int) $limiet);
    }
}
