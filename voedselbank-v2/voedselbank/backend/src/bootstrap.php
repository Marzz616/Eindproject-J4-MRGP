<?php
declare(strict_types=1);

/**
 * Opstarten van de backend: tijdzone en autoloader.
 *
 * De autoloader volgt PSR-4: de klasse App\Controllers\KlantController
 * staat in src/Controllers/KlantController.php. Zo hoeft er nergens een
 * require te staan en is aan de naam te zien waar een bestand staat.
 */

// Een container draait standaard op UTC; Nederland is 1 of 2 uur later.
date_default_timezone_set(getenv('APP_TZ') ?: 'Europe/Amsterdam');

spl_autoload_register(static function (string $klasse): void {
    $prefix = 'App\\';
    if (!str_starts_with($klasse, $prefix)) {
        return;
    }
    $bestand = __DIR__ . '/' . str_replace('\\', '/', substr($klasse, strlen($prefix))) . '.php';
    if (is_file($bestand)) {
        require $bestand;
    }
});
