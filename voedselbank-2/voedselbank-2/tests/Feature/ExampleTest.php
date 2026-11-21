<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('a test account can log in and retrieve the current user', function () {
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'p.abraham@voedselbank.nl'],
        [
            'naam' => 'Peter Abraham',
            'gebruikersnaam' => 'p.abraham',
            'wachtwoord' => \Illuminate\Support\Facades\Hash::make('voedselbank'),
            'rol' => 'directie',
            'is_geblokkeerd' => false,
        ],
    );

    $login = $this->postJson('/api/auth.php?actie=login', [
        'gebruikersnaam' => 'p.abraham',
        'wachtwoord' => 'voedselbank',
    ]);

    $login->assertOk()->assertJson([
        'ingelogd' => true,
        'gebruiker' => ['gebruikersnaam' => 'p.abraham'],
    ]);

    $token = $login->json('token');

    $this->withToken($token)
        ->getJson('/api/auth.php?actie=ik')
        ->assertOk()
        ->assertJson([
            'ingelogd' => true,
            'gebruiker' => ['gebruikersnaam' => 'p.abraham'],
        ]);
});

test('the users API returns the legacy list shape', function () {
    $user = \App\Models\User::updateOrCreate(
        ['email' => 'p.abraham@voedselbank.nl'],
        [
            'naam' => 'Peter Abraham',
            'gebruikersnaam' => 'p.abraham',
            'wachtwoord' => \Illuminate\Support\Facades\Hash::make('voedselbank'),
            'rol' => 'directie',
            'is_geblokkeerd' => false,
        ],
    );

    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/gebruikers.php?actie=lijst')
        ->assertOk()
        ->assertJsonStructure([
            'gebruikers',
            'rollen',
            'permissies',
            'ik',
        ]);
});

test('the dashboard reads the database schema used by the app', function () {
    \Illuminate\Support\Facades\Schema::create('Klant', function ($table) {
        $table->id('idKlant');
        $table->date('Aanmelddatum');
        $table->boolean('Geaccepteerd')->nullable();
    });
    \Illuminate\Support\Facades\Schema::create('Product', function ($table) {
        $table->id('idProduct');
        $table->string('Productnaam');
        $table->unsignedInteger('Aantal_voorraad')->default(0);
        $table->unsignedInteger('Min_voorraad')->default(0);
    });
    \Illuminate\Support\Facades\Schema::create('Pakket', function ($table) {
        $table->id('idPakket');
        $table->string('Status');
        $table->date('Uitgiftedatum')->nullable();
    });
    \Illuminate\Support\Facades\Schema::create('Leverancier', function ($table) {
        $table->id('idLeverancier');
    });

    \Illuminate\Support\Facades\DB::table('Klant')->insert([
        ['Aanmelddatum' => now()->toDateString(), 'Geaccepteerd' => true],
        ['Aanmelddatum' => now()->subDay()->toDateString(), 'Geaccepteerd' => false],
    ]);
    \Illuminate\Support\Facades\DB::table('Product')->insert([
        ['Productnaam' => 'Product A', 'Aantal_voorraad' => 12, 'Min_voorraad' => 2],
        ['Productnaam' => 'Product B', 'Aantal_voorraad' => 8, 'Min_voorraad' => 10],
    ]);
    \Illuminate\Support\Facades\DB::table('Pakket')->insert([
        ['Status' => 'Klaar', 'Uitgiftedatum' => now()->toDateString()],
    ]);
    \Illuminate\Support\Facades\DB::table('Leverancier')->insert(['idLeverancier' => 1]);

    $user = \App\Models\User::updateOrCreate(
        ['email' => 'p.abraham@voedselbank.nl'],
        [
            'naam' => 'Peter Abraham',
            'gebruikersnaam' => 'p.abraham',
            'wachtwoord' => \Illuminate\Support\Facades\Hash::make('voedselbank'),
            'rol' => 'directie',
            'is_geblokkeerd' => false,
        ],
    );
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/dashboard.php')
        ->assertOk()
        ->assertJsonStructure([
            'taken',
            'kaarten',
            'blokken',
        ])
        ->assertJson([
            'aanmeldingen_vandaag' => 1,
            'klanten_totaal' => 2,
            'klanten_geaccepteerd' => 1,
            'producten_totaal' => 2,
            'voorraad_stuks' => 20,
            'pakketten_klaar_voor_uitgifte' => 1,
            'pakketten_vandaag_uit_te_delen' => 1,
            'leveranciers_totaal' => 1,
            'blokken' => [
                ['titel' => 'Pakketten klaar voor uitgifte', 'rijen' => [['naam' => 'Pakket #1']]],
                ['titel' => 'Producten met lage voorraad', 'rijen' => [['naam' => 'Product B']]],
            ],
        ]);
});

test('the package builder start endpoint returns customers and products', function () {
    \Illuminate\Support\Facades\Schema::create('Klant', function ($table) {
        $table->id('idKlant');
        $table->string('Gezinsnaam');
        $table->boolean('Geaccepteerd')->nullable();
    });
    \Illuminate\Support\Facades\Schema::create('Gezinslid', function ($table) {
        $table->id('idGezinslid');
        $table->unsignedBigInteger('Klant_idKlant');
    });
    \Illuminate\Support\Facades\Schema::create('Pakket', function ($table) {
        $table->id('idPakket');
        $table->unsignedBigInteger('Klant_idKlant');
        $table->date('Samenstellingsdatum');
        $table->string('Status');
    });
    \Illuminate\Support\Facades\Schema::create('Categorie', function ($table) {
        $table->id('idCategorie');
        $table->string('Naam');
        $table->string('Kort');
    });
    \Illuminate\Support\Facades\Schema::create('Product', function ($table) {
        $table->id('idProduct');
        $table->string('Productnaam');
        $table->unsignedInteger('Aantal_voorraad');
        $table->date('Houdbaar_tot')->nullable();
        $table->string('Locatie')->nullable();
        $table->string('Allergenen')->nullable();
        $table->unsignedBigInteger('Categorie_idCategorie');
    });

    \Illuminate\Support\Facades\DB::table('Klant')->insert([
        'idKlant' => 1,
        'Gezinsnaam' => 'Familie Test',
        'Geaccepteerd' => true,
    ]);
    \Illuminate\Support\Facades\DB::table('Gezinslid')->insert([
        'idGezinslid' => 1,
        'Klant_idKlant' => 1,
    ]);
    \Illuminate\Support\Facades\DB::table('Categorie')->insert([
        'idCategorie' => 1,
        'Naam' => 'Conserven',
        'Kort' => 'Blik',
    ]);
    \Illuminate\Support\Facades\DB::table('Product')->insert([
        'idProduct' => 1,
        'Productnaam' => 'Bonen',
        'Aantal_voorraad' => 4,
        'Houdbaar_tot' => now()->addYear()->toDateString(),
        'Locatie' => 'Vak A',
        'Allergenen' => null,
        'Categorie_idCategorie' => 1,
    ]);

    $user = \App\Models\User::updateOrCreate(
        ['email' => 'p.abraham@voedselbank.nl'],
        [
            'naam' => 'Peter Abraham',
            'gebruikersnaam' => 'p.abraham',
            'wachtwoord' => \Illuminate\Support\Facades\Hash::make('voedselbank'),
            'rol' => 'directie',
            'is_geblokkeerd' => false,
        ],
    );
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/pakketten.php?actie=start')
        ->assertOk()
        ->assertJson([
            'klanten' => [[
                'id' => 1,
                'naam' => 'Familie Test',
                'gezinsgrootte' => 1,
                'dezeWeek' => false,
            ]],
            'producten' => [[
                'id' => 1,
                'naam' => 'Bonen',
                'categorie' => 'Blik',
                'voorraad' => 4,
                'verlopen' => false,
            ]],
        ]);
});
