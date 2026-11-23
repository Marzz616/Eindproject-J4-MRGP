<?php

namespace App\Http\Controllers\Api;

use App\Models\Klant;
use App\Models\Leverancier;
use App\Models\Pakket;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Werkproces / Weekcyclus & Dashboard Overzicht – Mario (US-WRK-02 t/m US-WRK-06, US-SEC-04)
 * Vervangt: public/api/dashboard.php
 */
class DashboardController
{
    /**
     * US-WRK-03, US-WRK-04, US-WRK-05, US-WRK-06:
     * Toon direct de dagelijkse werkzaamheden passend bij de weekcyclus en rol van de medewerker.
     */
    public function index(Request $request)
    {
        $vandaag = now()->toDateString();

        return response()->json([
            // US-WRK-03 & US-KLT-11: Klanten die zich vandaag hebben aangemeld
            'aanmeldingen_vandaag' => Klant::whereDate('aanmelddatum', $vandaag)->count(),
            'klanten_totaal' => Klant::count(),
            'klanten_geaccepteerd' => Klant::where('geaccepteerd', true)->count(),

            // US-VRD-06: Actuele voorraadstatus
            'producten_totaal' => Product::count(),
            'voorraad_stuks' => (int) Product::sum('aantal'),

            // US-WRK-04 & US-WRK-05: Pakketten samengesteld (donderdag) en klaar voor uitgifte (vrijdag)
            'pakketten_klaar_voor_uitgifte' => Pakket::where('status', 'samengesteld')->count(),
            'pakketten_vandaag_uit_te_delen' => Pakket::whereDate('uitgiftedatum', $vandaag)->count(),

            // US-LEV-06: Aantal geregistreerde leveranciers
            'leveranciers_totaal' => Leverancier::count(),
        ], 200);
    }
}