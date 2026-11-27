<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Welke pagina's iemand mag openen, afgeleid van zijn permissies.
 * De frontend bouwt hiermee het menu en de routebewaking op; de echte
 * bewaking zit op elke API-route (zie routes/api.php).
 */
final class Pages
{
    /** pagina => permissies waarvan er minstens één nodig is */
    private const PAGINAS = [
        'dashboard'    => [],
        'pakketten'    => ['pakket_beheren'],
        'uitgifte'     => ['pakket_uitgeven'],
        'producten'    => ['product_beheren', 'voorraad_corrigeren'],
        'leveranciers' => ['leverancier_beheren', 'levering_boeken'],
        'klanten'      => ['klant_inzien'],
        'rapportages'  => ['rapportage_export'],
        'gebruikers'   => ['gebruiker_beheren'],
        'account'      => [],
    ];

    /** @return string[] */
    public static function allowedFor(array $permissies): array
    {
        $uit = [];
        foreach (self::PAGINAS as $pagina => $nodig) {
            if (!$nodig || array_intersect($nodig, $permissies)) {
                $uit[] = $pagina;
            }
        }
        return $uit;
    }
}
