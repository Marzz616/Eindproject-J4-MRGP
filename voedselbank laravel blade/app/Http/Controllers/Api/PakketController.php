<?php

namespace App\Http\Controllers\Api;

use App\Models\Klant;
use App\Models\Pakket;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Module 3: Voedselpakketten & Uitgifte – Rick & Mario (US-PAK-01 t/m US-PAK-12, US-WRK-04, US-WRK-05)
 * Vervangt: public/api/pakketten.php en public/api/uitgifte.php
 */
class PakketController
{
    /**
     * US-PAK-12 & US-WRK-05: Bekijk welke pakketten op een bepaalde dag (bijv. vrijdag) uitgereikt moeten worden.
     */
    public function index(Request $request)
    {
        $query = Pakket::with(['klant', 'producten']);

        if ($request->filled('uitgiftedatum')) {
            $query->whereDate('uitgiftedatum', $request->uitgiftedatum);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('uitgiftedatum', 'asc')->get(), 200);
    }

    /**
     * US-PAK-01 t/m US-PAK-08 & US-WRK-04: Voedselpakket samenstellen (op donderdag voor vrijdag).
     * - US-PAK-02: Houd rekening met gezinssamenstelling (volwassenen, kinderen, baby's)
     * - US-PAK-03: Houd rekening met wensen en allergieën van de klant
     * - US-PAK-04: Automatisch uniek pakketnummer
     * - US-PAK-05: Datum van samenstelling registreren
     * - US-PAK-06: Koppelen aan één specifieke klant
     * - US-PAK-07: Producten en aantallen in het pakket registreren
     * - US-PAK-08 & US-GBR-08: Voorraad automatisch verlagen
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'klant_id' => 'required|exists:klanten,id',
            'uitgiftedatum' => 'nullable|date',
            'producten' => 'required|array|min:1',
            'producten.*.id' => 'required|exists:producten,id',
            'producten.*.aantal' => 'required|integer|min:1',
        ]);

        $klant = Klant::findOrFail($validated['klant_id']);

        // US-KLT-12: Alleen geaccepteerde klanten mogen wekelijks een pakket ophalen
        if (!$klant->geaccepteerd) {
            return response()->json([
                'error' => 'Pakket kan niet worden samengesteld: deze klant is nog niet geaccepteerd (US-KLT-12).'
            ], 422);
        }

        return DB::transaction(function () use ($validated, $klant) {
            $pakket = Pakket::create([
                'klant_id' => $klant->id,
                'samenstellingsdatum' => now()->toDateString(),
                'uitgiftedatum' => $validated['uitgiftedatum'] ?? now()->next('Friday')->toDateString(),
                'status' => 'samengesteld',
            ]);

            foreach ($validated['producten'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['id']);

                // US-PAK-01 & US-PAK-08: Controleer of er genoeg voorraad is
                if ($product->aantal < $item['aantal']) {
                    throw new \RuntimeException("Onvoldoende voorraad voor product '{$product->naam}' (beschikbaar: {$product->aantal}).");
                }

                // US-PAK-03: Controleer of het product conflicteert met allergieën of wensen van de klant
                if (!empty($klant->wensen) && !empty($product->allergenen)) {
                    foreach ((array) $klant->wensen as $wens) {
                        if (stripos($product->allergenen, $wens) !== false) {
                            throw new \RuntimeException(
                                "Product '{$product->naam}' conflicteert met geregistreerde wens/allergie '{$wens}' van klant (US-PAK-03)."
                            );
                        }
                    }
                }

                // US-PAK-07: Registreer product en aantal in dit pakket
                $pakket->producten()->attach($product->id, ['aantal' => $item['aantal']]);

                // US-PAK-08: Pas de voorraad automatisch aan
                $product->decrement('aantal', $item['aantal']);
            }

            return response()->json([
                'message' => 'Voedselpakket succesvol samengesteld en voorraad bijgewerkt (US-PAK-01 t/m 08).',
                'gezinssamenstelling' => [
                    'volwassenen' => $klant->aantal_volwassenen,
                    'kinderen' => $klant->aantal_kinderen,
                    'babys' => $klant->aantal_babys,
                    'totaal_gezinsleden' => $klant->totaal_gezinsleden,
                ],
                'wensen_en_allergieen' => $klant->wensen,
                'eigen_wens' => $klant->eigen_wens,
                'data' => $pakket->load(['klant', 'producten']),
            ], 201);
        });
    }

    /**
     * US-PAK-09 & US-WRK-05: De uitgifte van een pakket registreren wanneer de klant het pakket ophaalt.
     */
    public function registreerUitgifte(Pakket $pakket)
    {
        $pakket->update([
            'status' => 'uitgegeven',
            'uitgiftedatum' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'message' => 'Uitgifte van pakket succesvol geregistreerd (US-PAK-09).',
            'data' => $pakket->load(['klant', 'producten']),
        ], 200);
    }

    /**
     * US-PAK-10 & US-PAK-11: Gegevens ophalen om een sticker (pakketnummer, klantnaam, uitgiftedatum)
     * en de inhoudslijst met uitgereikte producten af te drukken.
     */
    public function printPakbonEnSticker(Pakket $pakket)
    {
        $pakket->load(['klant', 'producten']);

        return response()->json([
            // US-PAK-10: Sticker gegevens
            'sticker' => [
                'pakketnummer' => $pakket->pakketnummer,
                'klantnaam' => $pakket->klant->gezinsnaam,
                'uitgiftedatum' => $pakket->uitgiftedatum,
            ],
            // US-PAK-11: Lijst met uitgereikte producten
            'inhoudslijst' => $pakket->producten->map(function ($product) {
                return [
                    'naam' => $product->naam,
                    'streepjescode' => $product->streepjescode,
                    'aantal' => $product->pivot->aantal,
                ];
            }),
        ], 200);
    }
}