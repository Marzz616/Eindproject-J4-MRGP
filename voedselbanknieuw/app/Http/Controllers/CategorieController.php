<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\ValidationException;
use App\Models\Categorie;

/**
 * Productcategorieën beheren — US-VRD-11 / PRD-5.
 * Een categorie met producten kan niet weg: eerst de producten verplaatsen.
 */
final class CategorieController
{
    /** GET /categorieen */
    public function index(Request $request): array
    {
        return array_map(static fn(array $r): array => [
            'id'     => (int) $r['idCategorie'],
            'naam'   => $r['Naam'],
            'kort'   => $r['Kort'],
            'oms'    => $r['Omschrijving'],
            'aantal' => (int) $r['Aantal'],
        ], Categorie::lijst());
    }

    /** POST /categorieen {naam, kort, oms} */
    public function store(Request $request): JsonResponse
    {
        [$naam, $kort, $oms] = $this->valideer($request, 0);
        return response()->json(['ok' => true, 'id' => Categorie::maak($naam, $kort, $oms), 'melding' => 'Categorie toegevoegd.'], 201);
    }

    /** PUT /categorieen/{id} */
    public function update(Request $request): array
    {
        $id = $request->routeId();
        Categorie::vind($id) ?? throw new HttpException(404, 'Categorie niet gevonden.');
        [$naam, $kort, $oms] = $this->valideer($request, $id);
        Categorie::wijzig($id, $naam, $kort, $oms);
        return ['ok' => true, 'id' => $id, 'melding' => 'Categorie bijgewerkt.'];
    }

    /** DELETE /categorieen/{id} */
    public function destroy(Request $request): array
    {
        $id = $request->routeId();
        $c = Categorie::vind($id) ?? throw new HttpException(404, 'Categorie niet gevonden.');

        // De database houdt dit met RESTRICT ook tegen; hier geven we er een begrijpelijke melding bij.
        $aantal = (int) $c['Aantal'];
        if ($aantal > 0) {
            throw new HttpException(409,
                'In "' . $c['Naam'] . '" ' . ($aantal === 1 ? 'staat nog 1 product' : 'staan nog ' . $aantal . ' producten') . '.', [
                'reden'     => 'heeft_producten',
                'aantal'    => $aantal,
                'categorie' => $c['Naam'],
                'suggestie' => 'Zet die producten eerst in een andere categorie. Daarna kan deze categorie weg.',
            ]);
        }

        Categorie::verwijder($id);
        return ['ok' => true, 'melding' => 'Categorie "' . $c['Naam'] . '" is verwijderd.'];
    }

    /** POST /categorieen/{id}/verplaatsen {naar} — producten overzetten vóór het verwijderen. */
    public function verplaatsen(Request $request): array
    {
        $van = $request->routeId();
        $naar = $request->getal('naar');

        if ($naar <= 0 || $naar === $van) {
            throw new ValidationException(['naar' => 'Kies een andere categorie om de producten in te zetten.']);
        }
        if (Categorie::vind($van) === null || Categorie::vind($naar) === null) {
            throw new HttpException(404, 'Categorie niet gevonden.');
        }

        return ['ok' => true, 'verplaatst' => Categorie::verplaatsProducten($van, $naar)];
    }

    /** @return array{0:string,1:string,2:string} */
    private function valideer(Request $request, int $id): array
    {
        $naam = $request->tekst('naam');
        $kort = $request->tekst('kort');
        $oms  = $request->tekst('oms');

        if (mb_strlen($naam) < 2) {
            throw new ValidationException(['naam' => 'Naam is verplicht.']);
        }
        if (Categorie::naamBestaat($naam, $id)) {
            throw new ValidationException(['naam' => 'Deze categorie bestaat al.']);
        }
        return [$naam, $kort !== '' ? mb_substr($kort, 0, 20) : mb_substr($naam, 0, 20), $oms];
    }
}
