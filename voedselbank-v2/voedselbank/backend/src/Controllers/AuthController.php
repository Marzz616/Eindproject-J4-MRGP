<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Models\Gebruiker;
use App\Services\Wachtwoord;

/**
 * Inloggen, uitloggen en het eigen account (US-GBR-04, US-SEC-01, US-SEC-03).
 */
final class AuthController
{
    /** Na zoveel mislukte pogingen gaat het account op slot. */
    private const MAX_POGINGEN = 10;

    /** GET /auth/me — wie is er ingelogd? */
    public function me(Request $request): array
    {
        $g = Auth::user();
        return $g === null
            ? ['ingelogd' => false]
            : ['ingelogd' => true, 'gebruiker' => Auth::publicProfile($g)];
    }

    /** POST /auth/login {gebruikersnaam, wachtwoord} */
    public function login(Request $request): array
    {
        $naam = $request->string('gebruikersnaam');
        $wachtwoord = (string) $request->input('wachtwoord', '');

        if ($naam === '' || $wachtwoord === '') {
            throw new HttpException(422, 'Vul je gebruikersnaam en wachtwoord in.');
        }

        $g = Gebruiker::voorLogin($naam);

        // Bewust dezelfde melding bij een onbekende naam en een fout wachtwoord:
        // anders kun je uitproberen welke accounts bestaan.
        $onjuist = 'Gebruikersnaam of wachtwoord is onjuist.';

        if ($g === null) {
            usleep(300000);
            throw new HttpException(401, $onjuist);
        }
        if (!$g['Actief']) {
            throw new HttpException(403, 'Je account is geblokkeerd. Neem contact op met de directie.');
        }
        if ((int) $g['Mislukte_pogingen'] >= self::MAX_POGINGEN) {
            throw new HttpException(429, 'Te veel mislukte pogingen. Neem contact op met de directie.');
        }
        if (!password_verify($wachtwoord, $g['Wachtwoord_hash'])) {
            Gebruiker::mislukteLogin((int) $g['idGebruiker']);
            usleep(300000);
            throw new HttpException(401, $onjuist);
        }

        Session::login((int) $g['idGebruiker']);
        Gebruiker::geslaagdeLogin((int) $g['idGebruiker']);
        Auth::refresh();

        return ['ok' => true, 'gebruiker' => Auth::publicProfile(Auth::user())];
    }

    /** POST /auth/logout */
    public function logout(Request $request): array
    {
        Session::start();
        Session::destroy();
        return ['ok' => true];
    }

    /** GET /auth/rechten — mijn eigen rechten met leesbare namen. */
    public function rechten(Request $request): array
    {
        return ['rechten' => Gebruiker::permissieNamen(Auth::user()['permissies'])];
    }

    /** PUT /auth/wachtwoord {huidig, nieuw, herhaal} — US-GBR-04 */
    public function wachtwoord(Request $request): array
    {
        $huidig  = (string) $request->input('huidig', '');
        $nieuw   = (string) $request->input('nieuw', '');
        $herhaal = (string) $request->input('herhaal', '');

        $fouten = [];
        if (!password_verify($huidig, Gebruiker::hash(Auth::id()))) {
            $fouten['huidig'] = 'Je huidige wachtwoord klopt niet.';
        }
        if (!Wachtwoord::voldoet($nieuw)) {
            $fouten['nieuw'] = Wachtwoord::EIS;
        } elseif ($nieuw === $huidig) {
            $fouten['nieuw'] = 'Kies een ander wachtwoord dan je huidige.';
        }
        if ($nieuw !== $herhaal) {
            $fouten['herhaal'] = 'De twee wachtwoorden zijn niet gelijk.';
        }
        ValidationException::throwIf($fouten);

        Gebruiker::zetWachtwoord(Auth::id(), Wachtwoord::hash($nieuw));
        return ['ok' => true, 'melding' => 'Je wachtwoord is gewijzigd.'];
    }
}
