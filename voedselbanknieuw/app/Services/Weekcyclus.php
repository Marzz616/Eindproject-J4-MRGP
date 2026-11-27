<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * De weekcyclus van de voedselbank (US-WRK-01, US-PAK-04/06):
 * pakketten worden per week samengesteld (vanaf maandag) en op vrijdag uitgegeven.
 */
final class Weekcyclus
{
    /** Maandag van de huidige week, als Y-m-d. */
    public static function weekStart(): string
    {
        return (new DateTimeImmutable('monday this week'))->format('Y-m-d');
    }

    /**
     * Adviesaantal producten op basis van de gezinsgrootte (US-PAK-04):
     * 8 voor één persoon, plus 4 per extra gezinslid.
     */
    public static function advies(int $gezinsgrootte): int
    {
        return max(8, 8 + ($gezinsgrootte - 1) * 4);
    }
}
