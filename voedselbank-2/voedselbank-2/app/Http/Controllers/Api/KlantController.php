<?php

namespace App\Http\Controllers\Api;

use App\Models\Klant;
use Illuminate\Http\Request;

/**
 * Module 4: Klanten – Pheadon (US-KLT-01 t/m US-KLT-14, US-WRK-01, US-WRK-03, US-SEC-02)
 * Vervangt: public/api/klanten.php
 */
class KlantController
{
    /**
     * US-KLT-03: Klanten beheren en bekijken.
     * US-WRK-01: Zoeken/identificeren op rijbewijs of paspoort.
     * US-SEC-02: Privacygevoelige identificatiegegevens alleen tonen aan bevoegde gebruikers (directie).
     */
    public function index(Request $request)
    {
        $query = Klant::query();

        // US-WRK-01: Klant identificeren met rijbewijs of paspoort
        if ($request->filled('identificatie_nr')) {
            $query->where('identificatie_nr', $request->identificatie_nr);
        }

        $klanten = $query->orderBy('gezinsnaam', 'asc')->get();

        // US-SEC-02: Bescherm privacygevoelige identificatiegegevens voor niet-directieleden
        if ($request->user() && $request->user()->rol !== 'directie') {
            $klanten->makeHidden(['identificatie_type', 'identificatie_nr']);
        }

        return response()->json($klanten, 200);
    }

    /**
     * US-KLT-01, US-KLT-04 t/m US-KLT-10: Klant toevoegen met contactgegevens,
     * volwassenen/kinderen/baby's en voedingswensen/allergieën (varkensvlees, gluten, pinda's,
     * schaaldieren, hazelnoten, lactose, veganistisch, vegetarisch en eigen wens).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'gezinsnaam' => 'required|string|max:255',
            'adres' => 'required|string|max:255',
            'postcode' => 'required|string|max:10',
            'telefoonnummer' => 'required|string|max:50',
            'emailadres' => 'required|email|unique:klanten,emailadres',
            'aantal_volwassenen' => 'required|integer|min:1',
            'aantal_kinderen' => 'required|integer|min:0',
            'aantal_babys' => 'required|integer|min:0',
            'wensen' => 'nullable|array',
            'eigen_wens' => 'nullable|string|max:500',
            'identificatie_type' => 'nullable|in:paspoort,rijbewijs',
            'identificatie_nr' => 'nullable|string|max:100',
        ]);

        $validated['aanmelddatum'] = now()->toDateString();
        $validated['geaccepteerd'] = false;

        $klant = Klant::create($validated);

        return response()->json([
            'message' => 'Nieuwe klant succesvol geregistreerd (US-KLT-01 t/m US-KLT-10).',
            'data' => $klant,
        ], 201);
    }

    /**
     * US-KLT-02, US-KLT-05 t/m US-KLT-10: Klantgegevens, gezinssamenstelling en wensen wijzigen.
     */
    public function update(Request $request, Klant $klant)
    {
        $validated = $request->validate([
            'gezinsnaam' => 'sometimes|required|string|max:255',
            'adres' => 'sometimes|required|string|max:255',
            'postcode' => 'sometimes|required|string|max:10',
            'telefoonnummer' => 'sometimes|required|string|max:50',
            'emailadres' => 'sometimes|required|email|unique:klanten,emailadres,' . $klant->id,
            'aantal_volwassenen' => 'sometimes|required|integer|min:1',
            'aantal_kinderen' => 'sometimes|required|integer|min:0',
            'aantal_babys' => 'sometimes|required|integer|min:0',
            'wensen' => 'nullable|array',
            'eigen_wens' => 'nullable|string|max:500',
        ]);

        $klant->update($validated);

        return response()->json([
            'message' => 'Klantgegevens succesvol bijgewerkt (US-KLT-02).',
            'data' => $klant,
        ], 200);
    }

    /**
     * US-KLT-11 & US-WRK-03: Bekijken welke klanten zich vandaag hebben aangemeld.
     */
    public function aanmeldingenVandaag()
    {
        $aanmeldingen = Klant::whereDate('aanmelddatum', now()->toDateString())->get();

        return response()->json([
            'message' => $aanmeldingen->isEmpty()
                ? 'Er zijn vandaag nog geen nieuwe klantaanmeldingen (US-KLT-11).'
                : 'Dagelijkse aanmeldingen opgehaald.',
            'datum' => now()->toDateString(),
            'data' => $aanmeldingen,
        ], 200);
    }

    /**
     * US-KLT-12: Als directielid aangeven of een aangemelde klant geaccepteerd is.
     */
    public function updateAcceptatie(Request $request, Klant $klant)
    {
        $validated = $request->validate([
            'geaccepteerd' => 'required|boolean',
        ]);

        $klant->update(['geaccepteerd' => $validated['geaccepteerd']]);

        return response()->json([
            'message' => 'Acceptatiestatus van klant bijgewerkt (US-KLT-12).',
            'data' => $klant,
        ], 200);
    }

    /**
     * US-KLT-13 & US-KLT-14: Afgenomen pakketten van een klant bekijken inclusief samenstelling en datum.
     */
    public function pakketGeschiedenis(Klant $klant)
    {
        $pakketten = $klant->pakketten()
            ->with('producten')
            ->orderBy('samenstellingsdatum', 'desc')
            ->get();

        return response()->json([
            'klant' => $klant->gezinsnaam,
            'pakketgeschiedenis' => $pakketten,
        ], 200);
    }
}