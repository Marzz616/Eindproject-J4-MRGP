<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeverancierController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PakketController;
use App\Http\Controllers\Api\KlantController;
use App\Http\Controllers\Api\GebruikerController;
use App\Http\Controllers\Api\RapportageController;

/*
|--------------------------------------------------------------------------
| API Routes - Voedselbank Laravel Herd (TECH-04 & US-SEC-01)
| Ondersteunt zowel moderne Laravel routes (/api/klanten) als de bestaande
| frontend-aanroepen (/api/klanten.php) uit public/assets/app.js.
|--------------------------------------------------------------------------
*/

// Authenticatie (TECH-02)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth.php', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/auth.php', [AuthController::class, 'me']);
    Route::get('/auth.php', [AuthController::class, 'me']);
    Route::patch('/wachtwoord', [AuthController::class, 'wijzigWachtwoord']);

    // Dashboard & Weekcyclus (US-WRK-02 t/m 06)
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard.php', [DashboardController::class, 'index']);

    // 1. Leveranciers (US-LEV-01 t/m 06 & US-GBR-06)
    Route::middleware('role:directie,magazijnmedewerker')->group(function () {
        Route::get('/leveranciers', [LeverancierController::class, 'index']);
        Route::get('/leveranciers.php', [LeverancierController::class, 'index']);
        Route::get('/leveranciers/{leverancier}', [LeverancierController::class, 'show']);
        Route::post('/leveranciers/{leverancier}/leveringen', [LeverancierController::class, 'verwerkLevering']);

    Route::middleware('role:directie')->group(function () {
        Route::post('/leveranciers', [LeverancierController::class, 'store']);
        Route::post('/leveranciers.php', [LeverancierController::class, 'store']);
        Route::put('/leveranciers/{leverancier}', [LeverancierController::class, 'update']);
        Route::delete('/leveranciers/{leverancier}', [LeverancierController::class, 'destroy']);
    });

    // 2. Voorraad en Producten (US-VRD-01 t/m 12 & US-GBR-06, US-GBR-08, US-GBR-09)
    Route::middleware('role:directie,magazijnmedewerker,vrijwilliger')->group(function () {
        Route::get('/producten', [ProductController::class, 'index']);
        Route::get('/producten.php', [ProductController::class, 'index']);
        Route::get('/producten/{product}', [ProductController::class, 'show']);
        Route::get('/categorieen', [ProductController::class, 'categorieen']);
        Route::patch('/producten/{product}/voorraad', [ProductController::class, 'updateVoorraad']);
    });

    Route::middleware('role:directie,magazijnmedewerker')->group(function () {
        Route::post('/producten', [ProductController::class, 'store']);
        Route::post('/producten.php', [ProductController::class, 'store']);
        Route::put('/producten/{product}', [ProductController::class, 'update']);
        Route::delete('/producten/{product}', [ProductController::class, 'destroy']);
    });

    Route::middleware('role:directie')->group(function () {
        Route::post('/categorieen', [ProductController::class, 'opslaanCategorie']);
        Route::put('/categorieen/{categorie}', [ProductController::class, 'opslaanCategorie']);
    });

    // 3. Voedselpakketten & Uitgifte (US-PAK-01 t/m 12 & US-GBR-07)
    Route::middleware('role:directie,vrijwilliger')->group(function () {
        Route::get('/pakketten', [PakketController::class, 'index']);
        Route::get('/pakketten.php', [PakketController::class, 'index']);
        Route::get('/uitgifte.php', [PakketController::class, 'index']);
        Route::post('/pakketten', [PakketController::class, 'store']);
        Route::post('/pakketten.php', [PakketController::class, 'store']);
        Route::patch('/pakketten/{pakket}/uitgifte', [PakketController::class, 'registreerUitgifte']);
        Route::post('/uitgifte.php', [PakketController::class, 'registreerUitgifte']);
        Route::get('/pakketten/{pakket}/print', [PakketController::class, 'printPakbonEnSticker']);
    });

    // 4. Klanten (US-KLT-01 t/m 14)
    Route::middleware('role:directie,vrijwilliger')->group(function () {
        Route::get('/klanten', [KlantController::class, 'index']);
        Route::get('/klanten.php', [KlantController::class, 'index']);
        Route::get('/klanten-vandaag', [KlantController::class, 'aanmeldingenVandaag']);
        Route::put('/klanten/{klant}', [KlantController::class, 'update']);
        Route::get('/klanten/{klant}/pakketten', [KlantController::class, 'pakketGeschiedenis']);
    });

    Route::middleware('role:directie')->group(function () {
        Route::post('/klanten', [KlantController::class, 'store']);
        Route::post('/klanten.php', [KlantController::class, 'store']);
        Route::patch('/klanten/{klant}/acceptatie', [KlantController::class, 'updateAcceptatie']);
    });

    // 5. Gebruikers & 7. Rapportages (US-GBR-01 t/m 03 & US-RAP-01 t/m 06)
    Route::middleware('role:directie')->group(function () {
        Route::get('/gebruikers', [GebruikerController::class, 'index']);
        Route::get('/gebruikers.php', [GebruikerController::class, 'index']);
        Route::post('/gebruikers', [GebruikerController::class, 'store']);
        Route::post('/gebruikers.php', [GebruikerController::class, 'legacy']);
        Route::put('/gebruikers/{gebruiker}', [GebruikerController::class, 'update']);
        Route::patch('/gebruikers/{gebruiker}/blokkeer', [GebruikerController::class, 'blokkeer']);

        Route::get('/rapportages/categorieen', [RapportageController::class, 'maandoverzichtPerCategorie']);
        Route::get('/rapportages.php', [RapportageController::class, 'maandoverzichtPerCategorie']);
        Route::get('/rapportages/leveranciers', [RapportageController::class, 'leveranciersPerCategorie']);
        Route::get('/rapportages/postcodes', [RapportageController::class, 'maandoverzichtPerPostcode']);
    });
});
});