<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            ['Peter Abraham', 'p.abraham', 'p.abraham@voedselbank.nl', 'directie', false],
            ['Mirjam Snel', 'm.snel', 'm.snel@voedselbank.nl', 'administratie', false],
            ['Rob Veenstra', 'r.veenstra', 'rob.veenstra@voedselbank.nl', 'magazijnmedewerker', false],
            ['Ank de Groot', 'a.degroot', 'ank.degroot@voedselbank.nl', 'vrijwilliger', false],
            ['Kees de Graaf', 'k.degraaf', 'kees.degraaf@voedselbank.nl', 'vrijwilliger', true],
        ];

        foreach ($accounts as [$naam, $gebruikersnaam, $email, $rol, $geblokkeerd]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'naam' => $naam,
                    'gebruikersnaam' => $gebruikersnaam,
                    'wachtwoord' => Hash::make('voedselbank'),
                    'rol' => $rol,
                    'is_geblokkeerd' => $geblokkeerd,
                ],
            );
        }
    }
}
