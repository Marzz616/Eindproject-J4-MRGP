<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $aanmeldingenVandaag = DB::table('Klant')->whereDate('Aanmelddatum', $vandaag)->count();
        $klantenTotaal = DB::table('Klant')->count();
        $klantenGeaccepteerd = DB::table('Klant')->where('Geaccepteerd', true)->count();
        $productenTotaal = DB::table('Product')->count();
        $voorraadStuks = (int) DB::table('Product')->sum('Aantal_voorraad');
        $pakkettenKlaar = DB::table('Pakket')->where('Status', 'Klaar')->count();
        $pakkettenVandaag = DB::table('Pakket')->whereDate('Uitgiftedatum', $vandaag)->count();
        $leveranciersTotaal = DB::table('Leverancier')->count();

        $taken = [];
        if ($aanmeldingenVandaag > 0) {
            $taken[] = [
                'kleur' => 'amber',
                'tekst' => "{$aanmeldingenVandaag} nieuwe klant(en) hebben zich vandaag aangemeld.",
                'link' => 'klanten.html',
            ];
        }
        if ($pakkettenKlaar > 0) {
            $taken[] = [
                'kleur' => 'groen',
                'tekst' => "{$pakkettenKlaar} voedselpakket(ten) staan klaar voor uitgifte.",
                'link' => 'pakketten.html',
            ];
        }

        $klaarstaandePakketten = DB::table('Pakket')
            ->where('Status', 'Klaar')
            ->orderBy('Uitgiftedatum')
            ->limit(5)
            ->get(['idPakket', 'Uitgiftedatum'])
            ->map(fn ($pakket) => [
                'naam' => "Pakket #{$pakket->idPakket}",
                'onder' => 'Klaar voor uitgifte',
                'datum' => $pakket->Uitgiftedatum,
                'link' => 'pakketten.html',
            ]);

        $lageVoorraad = DB::table('Product')
            ->whereColumn('Aantal_voorraad', '<=', 'Min_voorraad')
            ->orderBy('Aantal_voorraad')
            ->limit(5)
            ->get(['Productnaam', 'Aantal_voorraad', 'Min_voorraad'])
            ->map(fn ($product) => [
                'naam' => $product->Productnaam,
                'onder' => "Minimumvoorraad: {$product->Min_voorraad}",
                'waarde' => (string) $product->Aantal_voorraad,
            ]);

        return response()->json([
            'intro' => 'Operationele status van Voedselbank Maaskantje',
            'taken' => $taken,
            'kaarten' => [
                [
                    'label' => 'Nieuwe aanmeldingen vandaag',
                    'waarde' => $aanmeldingenVandaag,
                    'voet' => 'Bekijk klanten',
                    'link' => 'klanten.html',
                    'kleur' => $aanmeldingenVandaag > 0 ? 'amber' : '',
                ],
                [
                    'label' => 'Goedgekeurde klanten',
                    'waarde' => $klantenGeaccepteerd,
                    'voet' => "van {$klantenTotaal} klanten",
                    'link' => 'klanten.html',
                ],
                [
                    'label' => 'Producten op voorraad',
                    'waarde' => $productenTotaal,
                    'voet' => "{$voorraadStuks} stuks totaal",
                    'link' => 'producten.html',
                ],
                [
                    'label' => 'Pakketten klaar voor uitgifte',
                    'waarde' => $pakkettenKlaar,
                    'voet' => "{$pakkettenVandaag} uitgifte(s) vandaag",
                    'link' => 'pakketten.html',
                    'kleur' => $pakkettenKlaar > 0 ? 'groen' : '',
                ],
                [
                    'label' => 'Leveranciers',
                    'waarde' => $leveranciersTotaal,
                    'voet' => 'Bekijk leveranciers',
                    'link' => 'leveranciers.html',
                ],
            ],
            'blokken' => [
                [
                    'soort' => 'lijst',
                    'titel' => 'Pakketten klaar voor uitgifte',
                    'tel' => (string) $pakkettenKlaar,
                    'rijen' => $klaarstaandePakketten,
                    'leeg' => 'Er staan geen pakketten klaar voor uitgifte.',
                ],
                [
                    'soort' => 'lijst',
                    'titel' => 'Producten met lage voorraad',
                    'tel' => (string) $lageVoorraad->count(),
                    'rijen' => $lageVoorraad,
                    'leeg' => 'Er zijn geen producten onder de minimumvoorraad.',
                ],
            ],
            // US-WRK-03 & US-KLT-11: Klanten die zich vandaag hebben aangemeld
            'aanmeldingen_vandaag' => $aanmeldingenVandaag,
            'klanten_totaal' => $klantenTotaal,
            'klanten_geaccepteerd' => $klantenGeaccepteerd,

            // US-VRD-06: Actuele voorraadstatus
            'producten_totaal' => $productenTotaal,
            'voorraad_stuks' => $voorraadStuks,

            // US-WRK-04 & US-WRK-05: Pakketten samengesteld (donderdag) en klaar voor uitgifte (vrijdag)
            'pakketten_klaar_voor_uitgifte' => $pakkettenKlaar,
            'pakketten_vandaag_uit_te_delen' => $pakkettenVandaag,

            // US-LEV-06: Aantal geregistreerde leveranciers
            'leveranciers_totaal' => $leveranciersTotaal,
        ], 200);
    }
}