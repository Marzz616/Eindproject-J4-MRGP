<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Module 7: Managementinformatie / Rapportages – Mario (US-RAP-01 t/m US-RAP-06)
 * Vervangt: public/api/rapportages.php
 */
class RapportageController
{
    /**
     * US-RAP-01 & US-RAP-03: Maandoverzicht per productcategorie gefilterd op maand en jaar.
     */
    public function maandoverzichtPerCategorie(Request $request)
    {
        $maand = (int) $request->get('maand', now()->month);
        $jaar = (int) $request->get('jaar', now()->year);

        $data = DB::table('pakketten')
            ->join('pakket_product', 'pakketten.id', '=', 'pakket_product.pakket_id')
            ->join('producten', 'pakket_product.product_id', '=', 'producten.id')
            ->join('categorieen', 'producten.categorie_id', '=', 'categorieen.id')
            ->whereMonth('pakketten.uitgiftedatum', $maand)
            ->whereYear('pakketten.uitgiftedatum', $jaar)
            ->where('pakketten.status', 'uitgegeven')
            ->select(
                'categorieen.naam as categorie',
                DB::raw('SUM(pakket_product.aantal) as totaal_uitgegeven')
            )
            ->groupBy('categorieen.naam')
            ->get();

        return response()->json([
            'periode' => ['maand' => $maand, 'jaar' => $jaar],
            'data' => $data,
        ], 200);
    }

    /**
     * US-RAP-02 & US-RAP-03: Per productcategorie zien welke leveranciers producten hebben geleverd.
     */
    public function leveranciersPerCategorie(Request $request)
    {
        $maand = (int) $request->get('maand', now()->month);
        $jaar = (int) $request->get('jaar', now()->year);

        $data = DB::table('leveringen')
            ->join('leveranciers', 'leveringen.leverancier_id', '=', 'leveranciers.id')
            ->join('producten', 'leveringen.product_id', '=', 'producten.id')
            ->join('categorieen', 'producten.categorie_id', '=', 'categorieen.id')
            ->whereMonth('leveringen.leverdatum', $maand)
            ->whereYear('leveringen.leverdatum', $jaar)
            ->select(
                'categorieen.naam as categorie',
                'leveranciers.bedrijfsnaam as leverancier',
                DB::raw('SUM(leveringen.aantal) as totaal_geleverd')
            )
            ->groupBy('categorieen.naam', 'leveranciers.bedrijfsnaam')
            ->get();

        return response()->json([
            'periode' => ['maand' => $maand, 'jaar' => $jaar],
            'data' => $data,
        ], 200);
    }

    /**
     * US-RAP-04 & US-RAP-05: Maandoverzicht per postcode én aantallen per productcategorie per postcode.
     */
    public function maandoverzichtPerPostcode(Request $request)
    {
        $maand = (int) $request->get('maand', now()->month);
        $jaar = (int) $request->get('jaar', now()->year);

        // US-RAP-04: Aantal pakketten per postcode
        $pakkettenPerPostcode = DB::table('pakketten')
            ->join('klanten', 'pakketten.klant_id', '=', 'klanten.id')
            ->whereMonth('pakketten.uitgiftedatum', $maand)
            ->whereYear('pakketten.uitgiftedatum', $jaar)
            ->where('pakketten.status', 'uitgegeven')
            ->select('klanten.postcode', DB::raw('COUNT(pakketten.id) as aantal_pakketten'))
            ->groupBy('klanten.postcode')
            ->get();

        // US-RAP-05: Aantallen per productcategorie per postcode
        $categorieenPerPostcode = DB::table('pakketten')
            ->join('klanten', 'pakketten.klant_id', '=', 'klanten.id')
            ->join('pakket_product', 'pakketten.id', '=', 'pakket_product.pakket_id')
            ->join('producten', 'pakket_product.product_id', '=', 'producten.id')
            ->join('categorieen', 'producten.categorie_id', '=', 'categorieen.id')
            ->whereMonth('pakketten.uitgiftedatum', $maand)
            ->whereYear('pakketten.uitgiftedatum', $jaar)
            ->where('pakketten.status', 'uitgegeven')
            ->select(
                'klanten.postcode',
                'categorieen.naam as categorie',
                DB::raw('SUM(pakket_product.aantal) as aantal_producten')
            )
            ->groupBy('klanten.postcode', 'categorieen.naam')
            ->get();

        return response()->json([
            'periode' => ['maand' => $maand, 'jaar' => $jaar],
            'pakketten_per_postcode' => $pakkettenPerPostcode,
            'categorieen_per_postcode' => $categorieenPerPostcode,
        ], 200);
    }
}