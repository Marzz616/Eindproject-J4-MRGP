<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
        $credentials = $request->validate([
            'email' => 'required|email',
            'wachtwoord' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['wachtwoord'], $user->wachtwoord)) {
            return response()->json(['error' => 'Ongeldig e-mailadres of wachtwoord.'], 401);
        }

        // US-GBR-03: Geblokkeerde gebruikers hebben geen toegang meer tot het systeem
        if ($user->is_geblokkeerd) {
            Log::warning("Geblokkeerde gebruiker probeerde in te loggen: ID {$user->id}"); // TECH-08
            return response()->json(['error' => 'Uw account is geblokkeerd (US-GBR-03).'], 403);
        }

        $token = $user->createToken('voedselbank-herd-token')->plainTextToken;

        return response()->json([
            'message' => 'Succesvol ingelogd.',
            'token' => $token,
            'user' => $user,
        ], 200);
    }

    /**
     * Geef de momenteel ingelogde gebruiker terug inclusief rol (US-SEC-01).
     */
    public function me(Request $request)
    {
        return response()->json($request->user(), 200);
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