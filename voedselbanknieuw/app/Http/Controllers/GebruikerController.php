<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Exceptions\HttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\ValidationException;
use App\Models\Gebruiker;
use App\Services\Wachtwoord;

/**
 * Gebruikers- en toegangsbeheer — US-GBR-01 t/m US-GBR-09.
 * Accounts worden nooit verwijderd, alleen geblokkeerd: wie ooit een pakket
 * samenstelde moet in de historie terug te vinden blijven.
 */
final class GebruikerController
{
    /** GET /gebruikers — gebruikers, rollen en de rechten per rol. */
    public function index(Request $request): array
    {
        $koppeling = Gebruiker::koppelingRolPermissie();

        return [
            'gebruikers' => array_map(static fn(array $g): array => [
                'id'      => (int) $g['idGebruiker'],
                'naam'    => $g['Naam'],
                'gnaam'   => $g['Gebruikersnaam'],
                'email'   => $g['Email'],
                'rol'     => $g['Rol'],
                'rolId'   => (int) $g['idRol'],
                'actief'  => (bool) $g['Actief'],
                'laatste' => $g['Laatste_login'],
                'mislukt' => (int) $g['Mislukte_pogingen'],
            ], Gebruiker::lijst()),
            'rollen' => array_map(static fn(array $r): array => [
                'id'           => (int) $r['idRol'],
                'naam'         => $r['Naam'],
                'omschrijving' => $r['Omschrijving'],
                'permissies'   => $koppeling[(int) $r['idRol']] ?? [],
            ], Gebruiker::rollen()),
            'permissies' => array_map(static fn(array $p): array => [
                'id' => (int) $p['idPermissie'], 'code' => $p['Code'], 'naam' => $p['Naam'],
            ], Gebruiker::permissies()),
            'ik' => AuthService::id(),
        ];
    }

    /** POST /gebruikers — US-GBR-01 */
    public function store(Request $request): JsonResponse
    {
        $v = $this->valideer($request, 0, true);
        $id = Gebruiker::maak($v['naam'], $v['gnaam'], $v['email'], Wachtwoord::hash($v['wachtwoord']), $v['rolId']);

        return response()->json(['ok' => true, 'id' => $id, 'melding' => 'Account aangemaakt voor ' . $v['naam'] . '.'], 201);
    }

    /** PUT /gebruikers/{id} — US-GBR-02 */
    public function update(Request $request): array
    {
        $id = $request->routeId();
        $huidig = Gebruiker::metRol($id) ?? throw new HttpException(404, 'Gebruiker niet gevonden.');
        $v = $this->valideer($request, $id, false);

        // De laatste directie mag zichzelf niet degraderen (US-GBR-05).
        if ($huidig['RolNaam'] === 'Directie' && $v['rolNaam'] !== 'Directie'
            && Gebruiker::andereActieveDirectie($id) === 0) {
            throw new HttpException(409, 'Dit is het laatste actieve directie-account. Maak eerst iemand anders directielid.');
        }

        Gebruiker::wijzig($id, $v['naam'], $v['gnaam'], $v['email'], $v['rolId']);
        if ($v['wachtwoord'] !== '') {
            Gebruiker::zetWachtwoord($id, Wachtwoord::hash($v['wachtwoord']));
        }
        return ['ok' => true, 'id' => $id, 'melding' => 'Account bijgewerkt.'];
    }

    /** PATCH /gebruikers/{id}/actief {actief} — US-GBR-03 */
    public function actief(Request $request): array
    {
        $id = $request->routeId();
        $aan = $request->vink('actief');

        if ($id === AuthService::id() && !$aan) {
            throw new HttpException(409, 'Je kunt je eigen account niet blokkeren.');
        }

        $g = Gebruiker::metRol($id) ?? throw new HttpException(404, 'Gebruiker niet gevonden.');
        if (!$aan && $g['RolNaam'] === 'Directie' && Gebruiker::andereActieveDirectie($id) === 0) {
            throw new HttpException(409, 'Dit is het laatste actieve directie-account en kan niet geblokkeerd worden.');
        }

        Gebruiker::zetActief($id, $aan);
        return [
            'ok'      => true,
            'melding' => $aan ? $g['Naam'] . ' kan weer inloggen.'
                              : $g['Naam'] . ' is geblokkeerd en kan niet meer inloggen.',
        ];
    }

    /** PUT /gebruikers/{id}/wachtwoord {wachtwoord} — US-GBR-04, door de beheerder */
    public function reset(Request $request): array
    {
        $pw = (string) $request->input('wachtwoord', '');
        if (!Wachtwoord::voldoet($pw)) {
            throw new ValidationException(['wachtwoord' => Wachtwoord::EIS]);
        }
        if (!Gebruiker::zetWachtwoord($request->routeId(), Wachtwoord::hash($pw))) {
            throw new HttpException(404, 'Gebruiker niet gevonden.');
        }
        return ['ok' => true, 'melding' => 'Nieuw wachtwoord ingesteld.'];
    }

    /** Controleert het formulier; gooit een ValidationException met alle fouten tegelijk. */
    private function valideer(Request $request, int $id, bool $nieuw): array
    {
        $v = [
            'naam'       => $request->tekst('naam'),
            'gnaam'      => $request->tekst('gnaam'),
            'email'      => $request->tekst('email'),
            'rolId'      => $request->getal('rolId'),
            'wachtwoord' => (string) $request->input('wachtwoord', ''),
        ];
        $v['rolNaam'] = Gebruiker::rolNaam($v['rolId']);

        $fouten = [];
        if ($v['naam'] === '') {
            $fouten['naam'] = 'Vul de naam in.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/i', $v['gnaam'])) {
            $fouten['gnaam'] = 'Gebruik 3 tot 60 tekens: letters, cijfers, punt, streepje of underscore.';
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $fouten['email'] = 'Vul een geldig e-mailadres in.';
        }
        if ($v['rolNaam'] === null) {
            $fouten['rolId'] = 'Kies een rol.';
        }
        // Bij wijzigen mag het wachtwoord leeg blijven (= niet veranderen).
        if (($nieuw || $v['wachtwoord'] !== '') && !Wachtwoord::voldoet($v['wachtwoord'])) {
            $fouten['wachtwoord'] = Wachtwoord::EIS;
        }
        foreach (Gebruiker::dubbel($v['gnaam'], $v['email'], $id) as $b) {
            if (strcasecmp($b['Gebruikersnaam'], $v['gnaam']) === 0) {
                $fouten['gnaam'] = 'Deze gebruikersnaam bestaat al.';
            }
            if (strcasecmp($b['Email'], $v['email']) === 0) {
                $fouten['email'] = 'Dit e-mailadres is al in gebruik.';
            }
        }

        ValidationException::throwIf($fouten);
        return $v;
    }
}
