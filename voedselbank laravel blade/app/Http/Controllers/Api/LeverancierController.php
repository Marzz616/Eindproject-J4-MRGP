<?php

namespace App\Http\Controllers\Api;

use App\Models\Leverancier;
use App\Models\Levering;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Module 1: Leveranciers – Guillian (US-LEV-01 t/m US-LEV-06 & US-WRK-02)
 * Vervangt: public/api/leveranciers.php
 */
class LeverancierController
{
    /**
     * US-LEV-04: Overzicht van alle leveranciers bekijken voor directielid en magazijnmedewerker.
     * US-LEV-05: Toon bedrijfsnaam, adres, contactpersoon, e-mailadres en telefoonnummer.
     * US-LEV-06: Toon eerstvolgende levering met datum en tijd, chronologisch gesorteerd.
     */
    public function index()
    {
        $leveranciers = Leverancier::with(['leveringen' => function ($query) {
            $query->where('leverdatum', '>=', now())
                  ->orderBy('leverdatum', 'asc');
        }])->orderBy('bedrijfsnaam', 'asc')->get();

        return response()->json([
            'message' => $leveranciers->isEmpty() ? 'Geen leveranciers geregistreerd.' : 'Leveranciers opgehaald.',
            'data' => $leveranciers,
        ], 200);
    }

    /**
     * US-LEV-01: Als directielid leveranciers toevoegen met formuliervalidatie en bevestiging.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bedrijfsnaam' => 'required|string|max:255',
            'adres' => 'required|string|max:255',
            'contactpersoon' => 'required|string|max:255',
            'emailadres' => 'required|email|max:255',
            'telefoonnummer' => 'required|string|max:50',
            'volgende_levering' => 'nullable|date',
        ]);

        $leverancier = Leverancier::create($validated);

        return response()->json([
            'message' => 'Leverancier succesvol geregistreerd (US-LEV-01).',
            'data' => $leverancier,
        ], 201);
    }

    /**
     * US-LEV-05 & US-LEV-06: Detailweergave van één leverancier inclusief eerstvolgende levering.
     */
    public function show(Leverancier $leverancier)
    {
        $eerstvolgende = $leverancier->leveringen()
            ->where('leverdatum', '>=', now())
            ->orderBy('leverdatum', 'asc')
            ->first();

        return response()->json([
            'data' => $leverancier,
            'eerstvolgende_levering' => $eerstvolgende ?? 'Geen aankomende levering gepland (US-LEV-06).',
        ], 200);
    }

    /**
     * US-LEV-02: Als directielid leveranciersgegevens wijzigen zodat de administratie actueel blijft.
     */
    public function update(Request $request, Leverancier $leverancier)
    {
        $validated = $request->validate([
            'bedrijfsnaam' => 'sometimes|required|string|max:255',
            'adres' => 'sometimes|required|string|max:255',
            'contactpersoon' => 'sometimes|required|string|max:255',
            'emailadres' => 'sometimes|required|email|max:255',
            'telefoonnummer' => 'sometimes|required|string|max:50',
            'volgende_levering' => 'nullable|date',
        ]);

        $leverancier->update($validated);

        return response()->json([
            'message' => 'Leveranciersgegevens succesvol gewijzigd (US-LEV-02).',
            'data' => $leverancier,
        ], 200);
    }

    /**
     * US-LEV-03 & TECH-07: Leverancier verwijderen met controle op gekoppelde leveringen.
     */
    public function destroy(Leverancier $leverancier)
    {
        if ($leverancier->leveringen()->exists()) {
            return response()->json([
                'error' => 'Leverancier is gekoppeld aan bestaande leveringen en kan niet worden verwijderd (US-LEV-03).'
            ], 409);
        }

        $leverancier->delete();

        return response()->json([
            'message' => 'Leverancier succesvol uit het systeem verwijderd (US-LEV-03).'
        ], 200);
    }

    /**
     * US-WRK-02: Binnengekomen levering verwerken en voorraad direct bijwerken.
     */
    public function verwerkLevering(Request $request, Leverancier $leverancier)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:producten,id',
            'aantal' => 'required|integer|min:1',
            'leverdatum' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($leverancier, $validated) {
            $levering = Levering::create([
                'leverancier_id' => $leverancier->id,
                'product_id' => $validated['product_id'],
                'aantal' => $validated['aantal'],
                'leverdatum' => $validated['leverdatum'] ?? now(),
                'verwerkt' => true,
            ]);

            $product = Product::findOrFail($validated['product_id']);
            $product->increment('aantal', $validated['aantal']);

            return response()->json([
                'message' => 'Levering verwerkt en productvoorraad automatisch verhoogd (US-WRK-02).',
                'levering' => $levering,
                'nieuwe_voorraad' => $product->aantal,
            ], 201);
        });
    }
}