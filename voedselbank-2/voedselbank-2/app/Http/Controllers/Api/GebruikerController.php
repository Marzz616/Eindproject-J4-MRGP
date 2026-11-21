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
    public function index(Request $request)
    {
        $users = User::orderBy('naam', 'asc')->get();

        if ($request->query('actie') === 'lijst') {
            return response()->json([
                'gebruikers' => $users->map(fn (User $user) => [
                    'id' => $user->id,
                    'naam' => $user->naam,
                    'gnaam' => $user->gebruikersnaam,
                    'email' => $user->email,
                    'rolId' => $this->rolId($user->rol),
                    'rol' => $this->rolNaam($user->rol),
                    'laatste' => null,
                    'mislukt' => 0,
                    'actief' => !$user->is_geblokkeerd,
                ]),
                'rollen' => $this->rollen(),
                'permissies' => $this->permissies(),
                'ik' => $request->user()?->id,
            ], 200);
        }

        return response()->json($users, 200);
    }

    public function legacy(Request $request)
    {
        return match ($request->query('actie')) {
            'opslaan' => $this->legacyOpslaan($request),
            'blokkeren' => $this->legacyBlokkeren($request),
            'reset' => $this->legacyReset($request),
            default => response()->json(['fout' => 'Onbekende gebruikersactie.'], 400),
        };
    }

    private function legacyOpslaan(Request $request)
    {
        $id = (int) $request->input('id', 0);
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'gnaam' => 'required|string|max:60',
            'email' => 'required|email|unique:gebruikers,email' . ($id ? ',' . $id : ''),
            'rolId' => 'required|integer',
            'wachtwoord' => 'nullable|string|min:8',
        ]);

        $data = [
            'naam' => $validated['naam'],
            'gebruikersnaam' => $validated['gnaam'],
            'email' => $validated['email'],
            'rol' => $this->rolSlug((int) $validated['rolId']),
        ];

        if (!empty($validated['wachtwoord'])) {
            $data['wachtwoord'] = Hash::make($validated['wachtwoord']);
        }

        if ($id) {
            $gebruiker = User::findOrFail($id);
            $gebruiker->update($data);
            $melding = 'Gebruiker succesvol bijgewerkt.';
        } else {
            $gebruiker = User::create([
                ...$data,
                'wachtwoord' => Hash::make($validated['wachtwoord']),
                'is_geblokkeerd' => false,
            ]);
            $melding = 'Gebruiker succesvol aangemaakt.';
        }

        return response()->json([
            'melding' => $melding,
            'data' => $gebruiker,
        ], $id ? 200 : 201);
    }

    private function legacyBlokkeren(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:gebruikers,id',
            'actief' => 'required|boolean',
        ]);

        $gebruiker = User::findOrFail($validated['id']);
        $gebruiker->update(['is_geblokkeerd' => !$validated['actief']]);

        return response()->json([
            'melding' => $validated['actief'] ? 'Gebruiker gedeblokkeerd.' : 'Gebruiker geblokkeerd.',
        ], 200);
    }

    private function legacyReset(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:gebruikers,id',
            'wachtwoord' => 'required|string|min:8',
        ]);

        $gebruiker = User::findOrFail($validated['id']);
        $gebruiker->update(['wachtwoord' => Hash::make($validated['wachtwoord'])]);

        return response()->json(['melding' => 'Wachtwoord succesvol gereset.'], 200);
    }

    private function rolId(string $rol): int
    {
        return match ($rol) {
            'directie' => 1,
            'administratie' => 2,
            'magazijnmedewerker' => 3,
            'vrijwilliger' => 4,
            default => 4,
        };
    }

    private function rolSlug(int $rolId): string
    {
        return match ($rolId) {
            1 => 'directie',
            2 => 'administratie',
            3 => 'magazijnmedewerker',
            4 => 'vrijwilliger',
            default => 'vrijwilliger',
        };
    }

    private function rolNaam(string $rol): string
    {
        return match ($rol) {
            'directie' => 'Directie',
            'administratie' => 'Administratie',
            'magazijnmedewerker' => 'Magazijnmedewerker',
            'vrijwilliger' => 'Vrijwilliger',
            default => ucfirst($rol),
        };
    }

    private function rollen(): array
    {
        return [
            ['id' => 1, 'naam' => 'Directie', 'omschrijving' => 'Volledige toegang tot alle onderdelen', 'permissies' => [1,2,3,4,5,6,7,8,9,10]],
            ['id' => 2, 'naam' => 'Administratie', 'omschrijving' => 'Klanten, rapportages en accounts', 'permissies' => [2,5,6,7]],
            ['id' => 3, 'naam' => 'Magazijnmedewerker', 'omschrijving' => 'Producten, voorraad en leveranciers', 'permissies' => [3,4,8,9]],
            ['id' => 4, 'naam' => 'Vrijwilliger', 'omschrijving' => 'Pakketten samenstellen en uitgeven', 'permissies' => [1,2,4,10]],
        ];
    }

    private function permissies(): array
    {
        return [
            ['id' => 1, 'naam' => 'Pakketten beheren'],
            ['id' => 2, 'naam' => 'Klanten inzien'],
            ['id' => 3, 'naam' => 'Leveringen boeken'],
            ['id' => 4, 'naam' => 'Voorraad corrigeren'],
            ['id' => 5, 'naam' => 'Rapportages genereren'],
            ['id' => 6, 'naam' => 'Gebruikers beheren'],
            ['id' => 7, 'naam' => 'Klanten beheren'],
            ['id' => 8, 'naam' => 'Producten beheren'],
            ['id' => 9, 'naam' => 'Leveranciers beheren'],
            ['id' => 10, 'naam' => 'Pakketten uitgeven'],
        ];
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