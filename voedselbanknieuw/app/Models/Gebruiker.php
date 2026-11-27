<?php
declare(strict_types=1);

namespace App\Models;

/** Gebruikers, rollen en permissies. */
final class Gebruiker extends Basis
{
    public static function metRol(int $id): ?array
    {
        return self::one("
            SELECT g.idGebruiker, g.Naam, g.Gebruikersnaam, g.Email, g.Actief,
                   g.Laatste_login, r.idRol, r.Naam AS RolNaam
              FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol
             WHERE g.idGebruiker = ?", [$id]);
    }

    /** Op gebruikersnaam of e-mailadres, voor het inloggen. */
    public static function voorLogin(string $naam): ?array
    {
        return self::one("
            SELECT idGebruiker, Naam, Wachtwoord_hash, Actief, Mislukte_pogingen
              FROM Gebruiker WHERE Gebruikersnaam = ? OR Email = ?", [$naam, $naam]);
    }

    /** @return string[] permissie-codes */
    public static function permissiesVanRol(int $rolId): array
    {
        return self::column("
            SELECT p.Code FROM Rol_has_Permissie rp
              JOIN Permissie p ON p.idPermissie = rp.Permissie_idPermissie
             WHERE rp.Rol_idRol = ?", [$rolId]);
    }

    public static function permissieNamen(array $codes): array
    {
        if (!$codes) {
            return [];
        }
        $in = implode(',', array_fill(0, count($codes), '?'));
        return self::all("SELECT Code, Naam FROM Permissie WHERE Code IN ($in) ORDER BY idPermissie", $codes);
    }

    public static function lijst(): array
    {
        return self::all("
            SELECT g.idGebruiker, g.Naam, g.Gebruikersnaam, g.Email, g.Actief,
                   g.Laatste_login, g.Mislukte_pogingen, r.idRol, r.Naam AS Rol
              FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol
          ORDER BY g.Actief DESC, g.Naam");
    }

    public static function rollen(): array
    {
        return self::all('SELECT idRol, Naam, Omschrijving FROM Rol ORDER BY idRol');
    }

    public static function rolNaam(int $rolId): ?string
    {
        $n = self::value('SELECT Naam FROM Rol WHERE idRol = ?', [$rolId]);
        return $n === null ? null : (string) $n;
    }

    public static function permissies(): array
    {
        return self::all('SELECT idPermissie, Code, Naam FROM Permissie ORDER BY idPermissie');
    }

    /** @return array<int,int[]> rol-id => permissie-ids */
    public static function koppelingRolPermissie(): array
    {
        $uit = [];
        foreach (self::all('SELECT Rol_idRol, Permissie_idPermissie FROM Rol_has_Permissie') as $r) {
            $uit[(int) $r['Rol_idRol']][] = (int) $r['Permissie_idPermissie'];
        }
        return $uit;
    }

    /** Bestaande accounts met dezelfde gebruikersnaam of e-mail (behalve $behalveId). */
    public static function dubbel(string $gnaam, string $email, int $behalveId): array
    {
        return self::all("
            SELECT Gebruikersnaam, Email FROM Gebruiker
             WHERE (Gebruikersnaam = ? OR Email = ?) AND idGebruiker <> ?", [$gnaam, $email, $behalveId]);
    }

    /** Hoeveel actieve directieleden er zijn naast deze gebruiker. */
    public static function andereActieveDirectie(int $behalveId): int
    {
        return (int) self::value("
            SELECT COUNT(*) FROM Gebruiker g JOIN Rol r ON r.idRol = g.Rol_idRol
             WHERE r.Naam = 'Directie' AND g.Actief = 1 AND g.idGebruiker <> ?", [$behalveId]);
    }

    public static function maak(string $naam, string $gnaam, string $email, string $hash, int $rolId): int
    {
        self::execute("
            INSERT INTO Gebruiker (Naam, Gebruikersnaam, Email, Wachtwoord_hash, Actief, Rol_idRol)
            VALUES (?,?,?,?,1,?)", [$naam, $gnaam, $email, $hash, $rolId]);
        return self::lastId();
    }

    public static function wijzig(int $id, string $naam, string $gnaam, string $email, int $rolId): void
    {
        self::execute('UPDATE Gebruiker SET Naam=?, Gebruikersnaam=?, Email=?, Rol_idRol=? WHERE idGebruiker=?',
            [$naam, $gnaam, $email, $rolId, $id]);
    }

    public static function hash(int $id): string
    {
        return (string) self::value('SELECT Wachtwoord_hash FROM Gebruiker WHERE idGebruiker = ?', [$id]);
    }

    /** Nieuw wachtwoord; telt mislukte pogingen terug naar 0. Geeft false als de gebruiker niet bestaat. */
    public static function zetWachtwoord(int $id, string $hash): bool
    {
        return self::execute('UPDATE Gebruiker SET Wachtwoord_hash = ?, Mislukte_pogingen = 0 WHERE idGebruiker = ?',
            [$hash, $id]) > 0;
    }

    public static function zetActief(int $id, bool $actief): void
    {
        self::execute('UPDATE Gebruiker SET Actief = ?, Mislukte_pogingen = 0 WHERE idGebruiker = ?',
            [$actief ? 1 : 0, $id]);
    }

    public static function mislukteLogin(int $id): void
    {
        self::execute('UPDATE Gebruiker SET Mislukte_pogingen = Mislukte_pogingen + 1 WHERE idGebruiker = ?', [$id]);
    }

    public static function geslaagdeLogin(int $id): void
    {
        self::execute('UPDATE Gebruiker SET Laatste_login = NOW(), Mislukte_pogingen = 0 WHERE idGebruiker = ?', [$id]);
    }

    public static function aantal(bool $actief): int
    {
        return (int) self::value('SELECT COUNT(*) FROM Gebruiker WHERE Actief = ?', [$actief ? 1 : 0]);
    }
}
