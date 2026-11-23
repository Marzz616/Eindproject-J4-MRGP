<?php

namespace App\Http\Controllers\Api;

use App\Models\Categorie;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Module 2: Voorraad en Producten – Rick (US-VRD-01 t/m US-VRD-12)
 * Vervangt: public/api/producten.php
 */
class ProductController
{
    /**
     * US-VRD-06: Overzicht van alle producten bekijken.
     * US-VRD-07: Sorteren op streepjescode, productnaam, categorie en aantal (oplopend/aflopend).
     * US-VRD-08: Zoeken op streepjescode.
     * US-VRD-12: Per product de naam, categorie, EAN-code en voorraad tonen.
     */
    public function index(Request $request)
    {
        $query = Product::with('categorie');

        // US-VRD-08: Zoeken op streepjescode (EAN-code)
        if ($request->filled('streepjescode')) {
            $query->where('streepjescode', 'like', '%' . $request->streepjescode . '%');
        }

        // US-VRD-07: Sorteren op streepjescode, naam, categorie_id of aantal
        $toegestaneKolommen = ['streepjescode', 'naam', 'categorie_id', 'aantal'];
        $sorteerOp = in_array($request->get('sorteer_op'), $toegestaneKolommen, true)
            ? $request->get('sorteer_op')
            : 'naam';
        $richting = strtolower($request->get('richting', 'asc')) === 'desc' ? 'desc' : 'asc';

        $producten = $query->orderBy($sorteerOp, $richting)->get();

        return response()->json([
            'message' => $producten->isEmpty() ? 'Geen producten gevonden.' : 'Productenoverzicht geladen.',
            'data' => $producten,
        ], 200);
    }

    /**
     * US-VRD-01: Nieuwe producten toevoegen.
     * US-VRD-09: Streepjescodes (EAN) zijn uniek.
     * US-VRD-10: Productnamen zijn uniek.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255|unique:producten,naam',
            'streepjescode' => 'required|string|max:64|unique:producten,streepjescode',
            'categorie_id' => 'required|exists:categorieen,id',
            'aantal' => 'required|integer|min:0',
            'allergenen' => 'nullable|string',
        ], [
            'naam.unique' => 'Dit product bestaat al. Productnamen moeten uniek zijn (US-VRD-10).',
            'streepjescode.unique' => 'Deze streepjescode/EAN is al in gebruik (US-VRD-09).',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Nieuw product succesvol geregistreerd (US-VRD-01).',
            'data' => $product->load('categorie'),
        ], 201);
    }

    /**
     * US-VRD-12: Bekijk productdetails (naam, categorie, EAN-code en voorraad).
     */
    public function show(Product $product)
    {
        return response()->json($product->load('categorie'), 200);
    }

    /**
     * US-VRD-02: Productgegevens wijzigen met controle op unieke naam (US-VRD-10) en streepjescode (US-VRD-09).
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'naam' => 'sometimes|required|string|max:255|unique:producten,naam,' . $product->id,
            'streepjescode' => 'sometimes|required|string|max:64|unique:producten,streepjescode,' . $product->id,
            'categorie_id' => 'sometimes|required|exists:categorieen,id',
            'aantal' => 'sometimes|required|integer|min:0',
            'allergenen' => 'nullable|string',
        ], [
            'naam.unique' => 'Deze productnaam is al in gebruik (US-VRD-10).',
            'streepjescode.unique' => 'Deze streepjescode is al in gebruik (US-VRD-09).',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Productgegevens succesvol gewijzigd (US-VRD-02).',
            'data' => $product->load('categorie'),
        ], 200);
    }

    /**
     * US-VRD-03 & US-GBR-08: Voorraadaantallen aanpassen zonder negatieve waarden.
     */
    public function updateVoorraad(Request $request, Product $product)
    {
        $validated = $request->validate([
            'aantal' => 'required|integer|min:0',
        ]);

        $product->update(['aantal' => $validated['aantal']]);

        return response()->json([
            'message' => 'Voorraadaantal succesvol aangepast (US-VRD-03).',
            'data' => $product,
        ], 200);
    }

    /**
     * US-VRD-04 & US-VRD-05: Product alleen verwijderen als het nog NIET in een pakket zit.
     */
    public function destroy(Product $product)
    {
        // US-VRD-05: Blokkeer verwijdering wanneer het product al in een pakket gebruikt is
        if ($product->pakketten()->exists()) {
            return response()->json([
                'error' => 'Dit product zit al in een voedselpakket en kan niet worden verwijderd zodat pakkethistorie behouden blijft (US-VRD-05).'
            ], 403);
        }

        // US-VRD-04: Verwijder product als het nog nooit in een pakket gebruikt is
        $product->delete();

        return response()->json([
            'message' => 'Product succesvol verwijderd (US-VRD-04).'
        ], 200);
    }

    /**
     * US-VRD-11: Overzicht van alle categorieën ophalen.
     */
    public function categorieen()
    {
        return response()->json(Categorie::orderBy('naam', 'asc')->get(), 200);
    }

    /**
     * US-VRD-11: Als directielid categorieën toevoegen en omschrijvingen aanpassen.
     */
    public function opslaanCategorie(Request $request, ?Categorie $categorie = null)
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'omschrijving' => 'nullable|string',
        ]);

        if ($categorie && $categorie->exists) {
            $categorie->update($validated);
            return response()->json([
                'message' => 'Categorieomschrijving succesvol aangepast (US-VRD-11).',
                'data' => $categorie,
            ], 200);
        }

        $nieuweCategorie = Categorie::create($validated);
        return response()->json([
            'message' => 'Nieuwe productcategorie toegevoegd (US-VRD-11).',
            'data' => $nieuweCategorie,
        ], 201);
    }
}