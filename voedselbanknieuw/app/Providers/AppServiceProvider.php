<?php

namespace App\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Hier breiden we de Request van Laravel uit met een paar korte
     * hulpmethodes (macro's). De controllers lezen daarmee overal op
     * dezelfde manier waarden uit het verzoek:
     *
     *   $request->tekst('naam')        tekst, zonder spaties aan de randen
     *   $request->getal('aantal')      heel getal
     *   $request->vink('actief')       ja/nee
     *   $request->lijst('regels')      een array (bijv. de regels van een pakket)
     *   $request->routeId()            het {id} uit de URL, als getal
     *   $request->routeWaarde('x')     een andere waarde uit de URL
     */
    public function boot(): void
    {
        $this->zetDatabaseTijdzone();

        Request::macro('tekst', function (string $naam, string $standaard = ''): string {
            return trim((string) $this->input($naam, $standaard));
        });

        Request::macro('getal', function (string $naam, int $standaard = 0): int {
            return (int) $this->input($naam, $standaard);
        });

        Request::macro('vink', function (string $naam, bool $standaard = false): bool {
            return (bool) $this->input($naam, $standaard);
        });

        Request::macro('lijst', function (string $naam): array {
            $waarde = $this->input($naam);
            return is_array($waarde) ? $waarde : [];
        });

        Request::macro('routeId', function (): int {
            return (int) $this->route('id');
        });

        Request::macro('routeWaarde', function (string $naam): string {
            return (string) $this->route($naam);
        });
    }

    /**
     * Zet de tijdzone van MySQL gelijk aan die van PHP (Europe/Amsterdam).
     * Anders kloppen CURDATE() en NOW() in de queries niet met "vandaag" en
     * "deze week" in de applicatie — bijvoorbeeld bij "vandaag aangemeld"
     * en bij de weekcyclus van de pakketten.
     *
     * We sturen de actuele afwijking door (+01:00 in de winter, +02:00 in de
     * zomer), want de namen van tijdzones kent MySQL niet altijd.
     */
    private function zetDatabaseTijdzone(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'mysql') {
                $event->connection->statement("SET time_zone = '" . now()->format('P') . "'");
            }
        });
    }
}
