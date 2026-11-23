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
<<<<<<< Updated upstream
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
=======
            ['naam' => 'Peter Abraham', 'gebruikersnaam' => 'p.abraham', 'email' => 'p.abraham@voedselbank.nl', 'rol' => 'directie', 'is_geblokkeerd' => false],
            ['naam' => 'Mirjam Snel', 'gebruikersnaam' => 'm.snel', 'email' => 'm.snel@voedselbank.nl', 'rol' => 'administratie', 'is_geblokkeerd' => false],
            ['naam' => 'Rob Veenstra', 'gebruikersnaam' => 'r.veenstra', 'email' => 'rob.veenstra@voedselbank.nl', 'rol' => 'magazijnmedewerker', 'is_geblokkeerd' => false],
            ['naam' => 'Ank de Groot', 'gebruikersnaam' => 'a.degroot', 'email' => 'ank.degroot@voedselbank.nl', 'rol' => 'vrijwilliger', 'is_geblokkeerd' => false],
            ['naam' => 'Kees de Graaf', 'gebruikersnaam' => 'k.degraaf', 'email' => 'k.degraaf@voedselbank.nl', 'rol' => 'vrijwilliger', 'is_geblokkeerd' => true],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                array_merge($account, ['wachtwoord' => Hash::make('voedselbank')]),
>>>>>>> Stashed changes
            );
        }
    }
}
