<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Categorie;
use App\Models\Product;
use App\Models\Rapportage;
use DateTimeImmutable;

/**
 * Rapportages en statistieken — US-RAP-01 t/m US-RAP-06.
 * Alleen voor rollen met rapportage_export (Directie, Administratie).
 */
final class RapportageController
{
    /** Doel uit de casus: minder dan 2% verspilling. */
    private const VERSPILLING_DOEL = 2.0;

    /** GET /rapportages — alle cijfers voor het rapportagescherm. */
    public function index(Request $request): array
    {
        return $this->rapport();
    }

    /** GET /rapportages/export/{rapport} — dezelfde cijfers als CSV (US-RAP-06). */
    public function export(Request $request): Response
    {
        $r = $this->rapport();
        $tabellen = [
            'weken'        => [['Week', 'Uitgegeven pakketten'],
                               array_map(static fn($x) => [$x['label'], $x['aantal']], $r['weken'])],
            'verloop'      => [['Datum', 'Voorraad (stuks)'],
                               array_map(static fn($x) => [$x['datum'], $x['stand']], $r['verloop'])],
            'bestemming'   => [['Bestemming', 'Aantal', 'Percentage'],
                               array_map(static fn($x) => [$x['label'], $x['aantal'], $x['percentage']], $r['bestemming'])],
            'verdeling'    => [['Gezinsgrootte', 'Aantal gezinnen', 'Percentage'],
                               array_map(static fn($x) => [$x['groep'], $x['aantal'], $x['percentage']], $r['verdeling'])],
            'topproducten' => [['Product', 'Categorie', 'Aantal uitgegeven'],
                               array_map(static fn($x) => [$x['naam'], $x['categorie'], $x['aantal']], $r['topProducten'])],
        ];

        $welke = $request->param('rapport');
        if (!isset($tabellen[$welke])) {
            throw new HttpException(404, 'Onbekend rapport.');
        }

        [$kop, $rijen] = $tabellen[$welke];
        return Response::csv('voedselbank-' . $welke . '-' . date('Y-m-d') . '.csv', $kop, $rijen);
    }

    /** GET /rapportages/maand?soort=categorie|postcode&maand=&jaar= — RAP-1 en RAP-2. */
    public function maand(Request $request): array
    {
        $maand = (int) $request->query('maand', date('n'));
        $jaar  = (int) $request->query('jaar', date('Y'));
        $soort = (string) $request->query('soort', 'categorie');

        if ($maand < 1 || $maand > 12 || $jaar < 2000 || $jaar > 2100) {
            throw new ValidationException(['maand' => 'Kies een geldige maand en een geldig jaar.']);
        }

        return match ($soort) {
            'categorie' => $this->perCategorie($maand, $jaar),
            'postcode'  => $this->perPostcode($maand, $jaar),
            default     => throw new HttpException(404, 'Onbekend maandoverzicht.'),
        };
    }

    /* ------------------------------------------------------------ opbouw */

    private function rapport(): array
    {
        $ytd = Rapportage::uitgegevenDitJaar();
        $vorig = Rapportage::uitgegevenVorigJaarTotNu();
        $waarde = Rapportage::pakketWaarde();
        $pakketten = (int) $waarde['Pakketten'];

        // bestemming van alles wat het magazijn uit ging (US-RAP-04)
        $inPakket = Rapportage::stuksInUitgegevenPakketten();
        $afvoer = Rapportage::afvoerPerReden();
        $verspild = $afvoer['Verspild'] ?? 0;
        $retour = ($afvoer['Retour leverancier'] ?? 0) + ($afvoer['Veeteelt'] ?? 0);
        $som = $inPakket + $verspild + $retour;
        $pct = static fn(int $n): float => $som > 0 ? round($n / $som * 100, 1) : 0.0;

        $verdeling = Rapportage::gezinsgrootteVerdeling();
        $klanten = array_sum(array_map(static fn(array $r): int => (int) $r['Aantal'], $verdeling));

        return [
            'pakketten' => [
                'ytd'       => $ytd,
                'vorigJaar' => $vorig,
                'groei'     => $vorig > 0 ? round(($ytd - $vorig) / $vorig * 100, 1) : null,
            ],
            'waarde' => [
                'gemiddeld' => $pakketten > 0 ? round((float) $waarde['Waarde'] / $pakketten, 2) : 0.0,
                'totaal'    => round((float) $waarde['Waarde'], 2),
            ],
            'weken' => array_map(static fn(array $r): array => [
                'label' => 'W' . (int) $r['Weeknr'], 'aantal' => (int) $r['Aantal'], 'van' => $r['Van'], 'tot' => $r['Tot'],
            ], Rapportage::perWeek()),
            'verloop' => $this->voorraadverloop(),
            'bestemming' => [
                ['label' => 'Uitgegeven in pakket',         'aantal' => $inPakket, 'percentage' => $pct($inPakket)],
                ['label' => 'Retour leverancier / veeteelt', 'aantal' => $retour,   'percentage' => $pct($retour)],
                ['label' => 'Verspild / afgevoerd',          'aantal' => $verspild, 'percentage' => $pct($verspild)],
            ],
            'verspilling' => ['percentage' => $pct($verspild), 'doel' => self::VERSPILLING_DOEL, 'nuttig' => round(100 - $pct($verspild), 1)],
            'verdeling' => array_map(static fn(array $r): array => [
                'groep'      => $r['Groep'],
                'aantal'     => (int) $r['Aantal'],
                'percentage' => $klanten > 0 ? (int) round((int) $r['Aantal'] / $klanten * 100) : 0,
            ], $verdeling),
            'topProducten' => array_map(static fn(array $r): array => [
                'naam' => $r['Productnaam'], 'categorie' => $r['Categorie'], 'aantal' => (int) $r['Aantal'],
            ], Rapportage::topProducten(10)),
        ];
    }

    /**
     * Voorraadverloop van de laatste 30 dagen (US-RAP-03).
     * Er wordt geen dagelijkse stand bewaard, dus rekenen we terug vanaf vandaag:
     * per dag terug gaat eraf wat er geleverd is en komt erbij wat er uit ging.
     */
    private function voorraadverloop(): array
    {
        $in = Rapportage::geleverdPerDag();
        $uit = Rapportage::uitgegaanPerDag();
        $stand = Product::totaleVoorraad();

        $verloop = [];
        for ($i = 0; $i <= 30; $i++) {
            $dag = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            array_unshift($verloop, ['datum' => $dag, 'stand' => max(0, $stand)]);
            $stand = $stand - ($in[$dag] ?? 0) + ($uit[$dag] ?? 0);
        }
        return $verloop;
    }

    private function perCategorie(int $maand, int $jaar): array
    {
        $uitgegeven = Rapportage::uitgegevenPerProductInMaand($maand, $jaar);
        $rijen = array_map(static fn(array $r): array => [
            'categorie'   => $r['Categorie'],
            'product'     => $r['Productnaam'],
            'leverancier' => $r['Leverancier'],
            'geleverd'    => (int) $r['Geleverd'],
            'uitgegeven'  => $uitgegeven[(int) $r['idProduct']] ?? 0,
        ], Rapportage::geleverdInMaand($maand, $jaar));

        return [
            'soort' => 'categorie', 'maand' => $maand, 'jaar' => $jaar,
            'rijen' => $rijen, 'totaal' => array_sum(array_column($rijen, 'geleverd')),
        ];
    }

    private function perPostcode(int $maand, int $jaar): array
    {
        $totalen = Rapportage::postcodeTotalen($maand, $jaar);
        $perPostcode = [];
        foreach (Rapportage::uitgegevenPerPostcode($maand, $jaar) as $r) {
            $pc = $r['Postcode'];
            $perPostcode[$pc] ??= ['postcode' => $pc] + ($totalen[$pc] ?? ['pakketten' => 0, 'gezinnen' => 0]) + ['per' => [], 'totaal' => 0];
            $perPostcode[$pc]['per'][$r['Categorie']] = (int) $r['Aantal'];
            $perPostcode[$pc]['totaal'] += (int) $r['Aantal'];
        }

        return [
            'soort' => 'postcode', 'maand' => $maand, 'jaar' => $jaar,
            'categorieen' => Categorie::namen(), 'rijen' => array_values($perPostcode),
        ];
    }
}
