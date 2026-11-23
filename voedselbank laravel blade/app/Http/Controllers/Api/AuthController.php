<?php

namespace App\Http\Controllers\Api;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Authenticatie – poort van public/api/auth.php
 * Frontend: api('auth.php?actie=login'|ik|logout|rechten|wachtwoord)
 */
class AuthController
{
    public function __construct(private AuthService $auth) {}

    public function handle(Request $request)
    {
        $actie = $request->query('actie', 'ik');

        return match ($actie) {
            'ik'         => $this->ik(),
            'login'      => $this->login($request),
            'logout'     => $this->logout($request),
            'rechten'    => $this->rechten(),
            'wachtwoord' => $this->wachtwoord($request),
            default      => response()->json(['fout' => 'Onbekende actie.'], 400),
        };
    }

    protected function ik()
    {
        $g = $this->auth->huidigeGebruiker();
        if ($g === null) {
            return response()->json(['ingelogd' => false], 200);
        }
        return response()->json([
            'ingelogd'  => true,
            'gebruiker' => $this->auth->gebruikerPubliek($g),
        ]);
    }

    protected function login(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['fout' => 'Gebruik POST voor deze actie.'], 405);
        }

        $this->auth->startSessie();
        $in = $request->all();
        $gnaam = trim((string) ($in['gebruikersnaam'] ?? ''));
        $pw = (string) ($in['wachtwoord'] ?? '');

        if ($gnaam === '' || $pw === '') {
            return response()->json(['fout' => 'Vul je gebruikersnaam en wachtwoord in.'], 422);
        }

        $g = DB::selectOne("
            SELECT g.idGebruiker, g.Naam, g.Wachtwoord_hash, g.Actief, g.Mislukte_pogingen
              FROM Gebruiker g
             WHERE g.Gebruikersnaam = ? OR g.Email = ?
        ", [$gnaam, $gnaam]);

        $fout = ['fout' => 'Gebruikersnaam of wachtwoord is onjuist.'];

        if (!$g) {
            usleep(300000);
            return response()->json($fout, 401);
        }

        if (!(int) $g->Actief) {
            return response()->json(['fout' => 'Je account is geblokkeerd. Neem contact op met de directie.'], 403);
        }

        if ((int) $g->Mislukte_pogingen >= 10) {
            return response()->json(['fout' => 'Te veel mislukte pogingen. Neem contact op met de directie.'], 429);
        }

        if (!password_verify($pw, $g->Wachtwoord_hash)) {
            DB::update('UPDATE Gebruiker SET Mislukte_pogingen = Mislukte_pogingen + 1 WHERE idGebruiker = ?', [$g->idGebruiker]);
            usleep(300000);
            return response()->json($fout, 401);
        }

        if (password_needs_rehash($g->Wachtwoord_hash, PASSWORD_BCRYPT)) {
            DB::update('UPDATE Gebruiker SET Wachtwoord_hash = ? WHERE idGebruiker = ?', [
                password_hash($pw, PASSWORD_BCRYPT),
                $g->idGebruiker,
            ]);
        }

        $this->auth->login((int) $g->idGebruiker);
        $vol = $this->auth->huidigeGebruiker();

        return response()->json([
            'ok' => true,
            'gebruiker' => $this->auth->gebruikerPubliek($vol),
        ]);
    }

    protected function logout(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['fout' => 'Gebruik POST voor deze actie.'], 405);
        }
        $this->auth->startSessie();
        $this->auth->vernietigSessie();
        return response()->json(['ok' => true]);
    }

    protected function rechten()
    {
        $g = $this->auth->huidigeGebruiker();
        if ($g === null) {
            return response()->json(['fout' => 'Je bent niet (meer) ingelogd.', 'login_nodig' => true], 401);
        }
        if (!$g['permissies']) {
            return response()->json(['rechten' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($g['permissies']), '?'));
        $rows = DB::select("SELECT Code, Naam FROM Permissie WHERE Code IN ($placeholders) ORDER BY idPermissie", $g['permissies']);
        return response()->json(['rechten' => $rows]);
    }

    protected function wachtwoord(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['fout' => 'Gebruik POST voor deze actie.'], 405);
        }
        $g = $this->auth->huidigeGebruiker();
        if ($g === null) {
            return response()->json(['fout' => 'Je bent niet (meer) ingelogd.', 'login_nodig' => true], 401);
        }

        $in = $request->all();
        $huidig = (string) ($in['huidig'] ?? '');
        $nieuw = (string) ($in['nieuw'] ?? '');
        $herhaal = (string) ($in['herhaal'] ?? '');

        $hash = DB::selectOne('SELECT Wachtwoord_hash FROM Gebruiker WHERE idGebruiker = ?', [$g['idGebruiker']]);
        $hash = $hash ? (string) $hash->Wachtwoord_hash : '';

        $fouten = [];
        if (!password_verify($huidig, $hash)) {
            $fouten['huidig'] = 'Je huidige wachtwoord klopt niet.';
        }
        if (mb_strlen($nieuw) < 8) {
            $fouten['nieuw'] = 'Gebruik minimaal 8 tekens.';
        }
        if (!preg_match('/[A-Za-z]/', $nieuw) || !preg_match('/\d/', $nieuw)) {
            $fouten['nieuw'] = 'Gebruik minimaal 8 tekens met letters én cijfers.';
        }
        if ($nieuw !== $herhaal) {
            $fouten['herhaal'] = 'De twee wachtwoorden zijn niet gelijk.';
        }
        if ($nieuw !== '' && $nieuw === $huidig) {
            $fouten['nieuw'] = 'Kies een ander wachtwoord dan je huidige.';
        }
        if ($fouten) {
            return response()->json(['fouten' => $fouten], 422);
        }

        DB::update('UPDATE Gebruiker SET Wachtwoord_hash = ? WHERE idGebruiker = ?', [
            password_hash($nieuw, PASSWORD_BCRYPT),
            $g['idGebruiker'],
        ]);

        return response()->json(['ok' => true]);
    }
}
