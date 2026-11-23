<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Module 5: Gebruikers en Rechten – Pheadon (US-GBR-01 t/m US-GBR-09)
 * Vervangt: public/api/gebruikers.php
 */
class GebruikerController
{
    public function index()
    {
        return response()->json(User::orderBy('naam', 'asc')->get(), 200);
    }

    /**
     * US-GBR-01: Als directielid nieuwe gebruikers toevoegen met gehasht wachtwoord en rol.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'required|email|unique:gebruikers,email',
            'wachtwoord' => 'required|string|min:8',
            'rol' => 'required|in:directie,magazijnmedewerker,vrijwilliger',
        ]);

        $gebruiker = User::create([
            'naam' => $validated['naam'],
            'email' => $validated['email'],
            'wachtwoord' => Hash::make($validated['wachtwoord']),
            'rol' => $validated['rol'],
            'is_geblokkeerd' => false,
        ]);

        return response()->json([
            'message' => 'Gebruiker succesvol aangemaakt (US-GBR-01).',
            'data' => $gebruiker,
        ], 201);
    }

    /**
     * US-GBR-02: Als directielid gebruikersgegevens en rol wijzigen.
     */
    public function update(Request $request, User $gebruiker)
    {
        $validated = $request->validate([
            'naam' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:gebruikers,email,' . $gebruiker->id,
            'rol' => 'sometimes|required|in:directie,magazijnmedewerker,vrijwilliger',
        ]);

        $gebruiker->update($validated);

        return response()->json([
            'message' => 'Gebruikersgegevens succesvol gewijzigd (US-GBR-02).',
            'data' => $gebruiker,
        ], 200);
    }

    /**
     * US-GBR-03: Als directielid gebruikers blokkeren zodat zij geen toegang meer hebben.
     */
    public function blokkeer(Request $request, User $gebruiker)
    {
        $gebruiker->update([
            'is_geblokkeerd' => $request->boolean('is_geblokkeerd', true),
        ]);

        return response()->json([
            'message' => 'Gebruikersstatus bijgewerkt (US-GBR-03).',
            'data' => $gebruiker,
        ], 200);
    }
}