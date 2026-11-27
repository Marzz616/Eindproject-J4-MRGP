<?php

namespace Database\Seeders;

use App\Support\SqlBestand;
use Illuminate\Database\Seeder;

/**
 * Vult de database met testdata (TEC-7: "de ontwikkelaars zorgen voor
 * testdata om de applicatie te kunnen testen en presenteren").
 *
 *   php artisan db:seed              testdata erbij zetten
 *   php artisan migrate:fresh --seed  database leegmaken en opnieuw vullen
 *
 * Het wachtwoord van alle testaccounts is: voedselbank
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SqlBestand::voerUit(database_path('schema/02-seed.sql'));

        $this->command?->info('Testdata geladen. Inloggen kan met p.abraham / voedselbank.');
    }
}
