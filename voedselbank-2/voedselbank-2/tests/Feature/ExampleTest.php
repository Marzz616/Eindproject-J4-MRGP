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
