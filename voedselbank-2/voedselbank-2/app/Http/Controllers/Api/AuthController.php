<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticatie & Wachtwoordbeheer (TECH-02, US-GBR-03, US-GBR-04, US-SEC-05)
 * Vervangt: public/api/auth.php en src/auth.php
 */
class AuthController
{
    /**
     * TECH-02 & US-GBR-03: Gebruiker inloggen en controleren op blokkade.
     */
    public function login(Request $request)
    {
<<<<<<< Updated upstream
        $credentials = $request->validate([
            'gebruikersnaam' => 'required_without:email|string',
            'email' => 'required_without:gebruikersnaam|email',
            'wachtwoord' => 'required|string',
        ]);

        $login = $credentials['gebruikersnaam'] ?? $credentials['email'] ?? null;
        $user = User::where('gebruikersnaam', $login)
            ->orWhere('email', $login)
            ->first();

        if (!$user || !Hash::check($credentials['wachtwoord'], $user->wachtwoord)) {
            return response()->json(['fout' => 'Ongeldige gebruikersnaam, e-mailadres of wachtwoord.'], 401);
=======
        $identifier = $request->input('email', $request->input('gebruikersnaam'));
        $wachtwoord = $request->input('wachtwoord');

        if (!$identifier || !$wachtwoord) {
            return response()->json(['error' => 'Vul een gebruikersnaam/e-mailadres en wachtwoord in.'], 422);
        }

        $user = User::where('email', $identifier)
            ->orWhere('gebruikersnaam', $identifier)
            ->first();

        if (!$user || !Hash::check($wachtwoord, $user->wachtwoord)) {
            return response()->json(['error' => 'Ongeldig e-mailadres of wachtwoord.'], 401);
>>>>>>> Stashed changes
        }

        // US-GBR-03: Geblokkeerde gebruikers hebben geen toegang meer tot het systeem
        if ($user->is_geblokkeerd) {
            Log::warning("Geblokkeerde gebruiker probeerde in te loggen: ID {$user->id}"); // TECH-08
            return response()->json(['fout' => 'Uw account is geblokkeerd.'], 403);
        }

        Auth::login($user);
        $token = $user->createToken('voedselbank-herd-token')->plainTextToken;

        if ($request->has('gebruikersnaam') || $request->query('actie') === 'login') {
            $user->setAttribute('paginas', $this->paginasVoorRol($user->rol));
            return response()->json([
                'ingelogd' => true,
                'gebruiker' => $user,
                'token' => $token,
            ], 200);
        }

        return response()->json([
            'ingelogd' => true,
            'gebruiker' => $this->gebruikerData($user),
            'token' => $token,
        ], 200);
    }

    /**
     * Geef de momenteel ingelogde gebruiker terug inclusief rol (US-SEC-01).
     */
    public function me(Request $request)
    {
<<<<<<< Updated upstream
        $user = $request->user();

        if (!$user) {
            return response()->json(['ingelogd' => false], 200);
        }

        return response()->json([
            'ingelogd' => true,
            'gebruiker' => $this->gebruikerData($user),
        ], 200);
    }

    private function gebruikerData(User $user): array
    {
        $paginas = match ($user->rol) {
            'directie' => ['dashboard', 'pakketten', 'uitgifte', 'producten', 'leveranciers', 'klanten', 'rapportages', 'gebruikers', 'account'],
            'administratie' => ['klanten', 'rapportages', 'gebruikers', 'account'],
            'magazijnmedewerker' => ['dashboard', 'producten', 'leveranciers', 'account'],
            'vrijwilliger' => ['pakketten', 'uitgifte', 'producten', 'klanten', 'account'],
            default => ['account'],
        };

        return [
            'id' => $user->id,
            'naam' => $user->naam,
            'gebruikersnaam' => $user->gebruikersnaam,
            'email' => $user->email,
            'rol' => match ($user->rol) {
                'directie' => 'Directie',
                'administratie' => 'Administratie',
                'magazijnmedewerker' => 'Magazijnmedewerker',
                'vrijwilliger' => 'Vrijwilliger',
                default => ucfirst($user->rol),
            },
            'paginas' => $paginas,
        ];
=======
        if ($request->query('actie') === 'ik') {
            if (!Auth::check()) {
                return response()->json(['ingelogd' => false], 200);
            }

            $user = Auth::user();
            $user->setAttribute('paginas', $this->paginasVoorRol($user->rol));
            return response()->json([
                'ingelogd' => true,
                'gebruiker' => $user,
            ], 200);
        }

        return response()->json($request->user(), 200);
>>>>>>> Stashed changes
    }

    private function paginasVoorRol(string $rol): array
    {
        return match ($rol) {
            'directie' => ['dashboard', 'pakketten', 'uitgifte', 'producten', 'leveranciers', 'klanten', 'gebruikers', 'account'],
            'administratie' => ['klanten', 'gebruikers', 'account'],
            'magazijnmedewerker' => ['dashboard', 'producten', 'leveranciers', 'account'],
            'vrijwilliger' => ['pakketten', 'uitgifte', 'klanten', 'producten', 'account'],
            default => ['account'],
        };
    }

    /**
     * US-GBR-04: Als gebruiker wil ik mijn eigen wachtwoord kunnen wijzigen.
     * Development tasks: controleer huidig wachtwoord, valideer nieuw wachtwoord, sla veilig gehasht op.
     */
    public function wijzigWachtwoord(Request $request)
    {
        $validated = $request->validate([
            'huidig_wachtwoord' => 'required|string',
            'nieuw_wachtwoord' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['huidig_wachtwoord'], $user->wachtwoord)) {
            return response()->json(['error' => 'Het huidige wachtwoord is niet juist.'], 422);
        }

        // US-SEC-05: Sla wachtwoord veilig gehasht op volgens beveiligingsrichtlijnen
        $user->update([
            'wachtwoord' => Hash::make($validated['nieuw_wachtwoord']),
        ]);

        Log::info("Gebruiker ID {$user->id} heeft het eigen wachtwoord gewijzigd (US-GBR-04)."); // TECH-08

        return response()->json(['message' => 'Wachtwoord succesvol gewijzigd (US-GBR-04).'], 200);
    }

    /**
     * TECH-02: Uitloggen en actieve token intrekken.
     */
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Succesvol uitgelogd.'], 200);
    }
}