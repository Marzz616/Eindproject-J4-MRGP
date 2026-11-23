<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Authenticatie & autorisatie – poort van src/auth.php naar Laravel.
 * Sessie bevat alleen gebruiker_id; rol/rechten worden elke request vers opgehaald.
 */
class AuthService
{
    public const SESSIE_DUUR = 3600;

    public function startSessie(): void
    {
        if (Session::has('laatste_actie') && time() - (int) Session::get('laatste_actie') > self::SESSIE_DUUR) {
            $this->vernietigSessie();
        }
        Session::put('laatste_actie', time());
    }

    public function vernietigSessie(): void
    {
        Session::forget(['gebruiker_id', 'laatste_actie']);
        Session::invalidate();
        Session::regenerateToken();
    }

    public function huidigeGebruiker(): ?array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache ?: null;
        }

        $this->startSessie();
        $id = Session::get('gebruiker_id');
        if (!$id) {
            $cache = false;
            return null;
        }

        $g = DB::selectOne("
            SELECT g.idGebruiker, g.Naam, g.Gebruikersnaam, g.Email, g.Actief,
                   g.Laatste_login, r.idRol, r.Naam AS RolNaam
              FROM Gebruiker g
              JOIN Rol r ON r.idRol = g.Rol_idRol
             WHERE g.idGebruiker = ?
        ", [$id]);

        if (!$g || !(int) $g->Actief) {
            $this->vernietigSessie();
            $cache = false;
            return null;
        }

        $g = (array) $g;
        $perms = DB::select("
            SELECT p.Code FROM Rol_has_Permissie rp
              JOIN Permissie p ON p.idPermissie = rp.Permissie_idPermissie
             WHERE rp.Rol_idRol = ?
        ", [$g['idRol']]);
        $g['permissies'] = array_map(fn ($r) => $r->Code, $perms);
        $cache = $g;
        return $g;
    }

    public function mag(string $permissie): bool
    {
        $g = $this->huidigeGebruiker();
        return $g !== null && in_array($permissie, $g['permissies'], true);
    }

    public function login(int $gebruikerId): void
    {
        Session::regenerate();
        Session::put('gebruiker_id', $gebruikerId);
        Session::put('laatste_actie', time());
        DB::update('UPDATE Gebruiker SET Laatste_login = NOW(), Mislukte_pogingen = 0 WHERE idGebruiker = ?', [$gebruikerId]);
    }

    public function toegestanePaginas(array $g): array
    {
        $p = $g['permissies'];
        $paginas = ['dashboard', 'account'];
        if (in_array('pakket_beheren', $p, true)) {
            $paginas[] = 'pakketten';
        }
        if (in_array('pakket_uitgeven', $p, true)) {
            $paginas[] = 'uitgifte';
        }
        if (in_array('product_beheren', $p, true) || in_array('voorraad_corrigeren', $p, true)) {
            $paginas[] = 'producten';
        }
        if (in_array('leverancier_beheren', $p, true) || in_array('levering_boeken', $p, true)) {
            $paginas[] = 'leveranciers';
        }
        if (in_array('klant_inzien', $p, true)) {
            $paginas[] = 'klanten';
        }
        if (in_array('rapportage_export', $p, true)) {
            $paginas[] = 'rapportages';
        }
        if (in_array('gebruiker_beheren', $p, true)) {
            $paginas[] = 'gebruikers';
        }
        return array_values(array_unique($paginas));
    }

    public function gebruikerPubliek(array $g): array
    {
        return [
            'id'         => (int) $g['idGebruiker'],
            'naam'       => $g['Naam'],
            'gnaam'      => $g['Gebruikersnaam'],
            'email'      => $g['Email'],
            'rol'        => $g['RolNaam'],
            'rolId'      => (int) $g['idRol'],
            'permissies' => $g['permissies'],
            'paginas'    => $this->toegestanePaginas($g),
        ];
    }
}
