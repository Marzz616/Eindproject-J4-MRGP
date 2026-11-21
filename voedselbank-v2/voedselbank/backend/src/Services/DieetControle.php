<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Klant;
use App\Models\Wens;

/**
 * Controleert of een product past bij de dieetwensen van een gezin
 * (US-PAK-03, US-KLT-07 t/m 10, US-VRD-08).
 *
 * Elk product heeft kenmerken ("noten,gluten"); elke wens heeft een
 * conflict-tag ("noten"). Komen die overeen, dan botst het product met de wens.
 *
 * Drie uitkomsten:
 *   - over de houdbaarheidsdatum   -> nooit toegestaan
 *   - strenge allergie             -> nooit toegestaan (ook niet met "toch opslaan")
 *   - overige wens / overtuiging   -> mag, maar alleen na een bevestiging
 *
 * Een vastgelegde uitzondering mét reden ("alleen vader eet geen noten")
 * maakt van een strenge allergie een gewone waarschuwing.
 */
final class DieetControle
{
    /**
     * Het dieetprofiel van een gezin, per conflict-tag:
     *   ['noten' => ['tag' => 'noten', 'wens' => 'Notenallergie', 'streng' => true,
     *                'personen' => ['Bart Bakker'], 'gezinsgrootte' => 5,
     *                'uitzondering' => false, 'toelichting' => null]]
     */
    public static function profiel(int $klantId): array
    {
        $grootte = Klant::gezinsgrootte($klantId);
        $uit = [];

        foreach (Wens::conflictenVanGezin($klantId) as $r) {
            $tag = (string) $r['Conflict_tag'];
            $uit[$tag] ??= [
                'tag'           => $tag,
                'wens'          => $r['Wens'],
                'streng'        => false,
                'personen'      => [],
                'gezinsgrootte' => $grootte,
                'uitzondering'  => false,
                'toelichting'   => null,
            ];
            $uit[$tag]['personen'][] = $r['Lid'];

            // twee wensen op dezelfde tag: de strengste wint
            if ((bool) $r['Streng']) {
                $uit[$tag]['streng'] = true;
            }
            // uitzondering telt alleen mét reden
            if ((int) $r['Toch_toestaan'] === 1 && trim((string) $r['Toelichting']) !== '') {
                $uit[$tag]['uitzondering'] = true;
                $uit[$tag]['toelichting'] = $r['Toelichting'];
            }
        }

        // een uitzondering haalt de harde blokkade weg; de waarschuwing blijft
        foreach ($uit as $tag => $w) {
            if ($w['uitzondering']) {
                $uit[$tag]['streng'] = false;
            }
        }
        return $uit;
    }

    /** Welke wensen uit het profiel botsen met de kenmerken van dit product? */
    public static function botsingen(?string $allergenen, array $profiel): array
    {
        if (!$allergenen || !$profiel) {
            return [];
        }
        $uit = [];
        foreach (array_map('trim', explode(',', $allergenen)) as $tag) {
            if (isset($profiel[$tag])) {
                $uit[] = $profiel[$tag];
            }
        }
        return $uit;
    }

    public static function isVerlopen(?string $tht): bool
    {
        return $tht !== null && $tht < date('Y-m-d');
    }

    /**
     * Controleert alle producten van een pakket.
     *
     * @param array<int,array> $producten productId => productrij
     * @return array{geblokkeerd: array, waarschuwing: array}
     */
    public static function controleer(array $producten, array $profiel): array
    {
        $geblokkeerd = [];
        $waarschuwing = [];

        foreach ($producten as $p) {
            if (self::isVerlopen($p['Houdbaar_tot'])) {
                $geblokkeerd[] = [
                    'product' => $p['Productnaam'],
                    'soort'   => 'verlopen',
                    'uitleg'  => 'is over de houdbaarheidsdatum (' . $p['Houdbaar_tot'] . ')',
                ];
                continue;
            }

            foreach (self::botsingen($p['Allergenen'], $profiel) as $b) {
                $wie = count($b['personen']) . ' van de ' . $b['gezinsgrootte'] . ' (' . implode(', ', $b['personen']) . ')';

                if ($b['streng']) {
                    $geblokkeerd[] = [
                        'product' => $p['Productnaam'],
                        'soort'   => 'allergie',
                        'wens'    => $b['wens'],
                        'uitleg'  => 'botst met ' . $b['wens'] . ' van ' . $wie,
                    ];
                } else {
                    $waarschuwing[] = [
                        'product'     => $p['Productnaam'],
                        'wens'        => $b['wens'],
                        'uitleg'      => $b['wens'] . ' geldt voor ' . $wie
                                       . ($b['uitzondering'] ? ' — het gezin wil dit toch ontvangen' : ''),
                        'toelichting' => $b['toelichting'],
                    ];
                }
            }
        }

        return ['geblokkeerd' => $geblokkeerd, 'waarschuwing' => $waarschuwing];
    }
}
