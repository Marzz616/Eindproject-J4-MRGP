<?php
declare(strict_types=1);

/**
 * Alle API-routes op één plek (alles hieronder begint met /api).
 *
 * Per route: HTTP-methode, pad, controller-methode en wat er nodig is om hem
 * te gebruiken. Zo is in één oogopslag te zien wie wat mag (US-SEC-01):
 *
 *   geen middleware        iedereen (alleen inloggen en "wie ben ik")
 *   ->middleware('ingelogd')      elke ingelogde gebruiker
 *   ->middleware('mag:klant_inzien')   deze permissie uit de tabel Permissie
 *   ->middleware('mag:a,b')            één van beide permissies is genoeg
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GebruikerController;
use App\Http\Controllers\KlantController;
use App\Http\Controllers\LeverancierController;
use App\Http\Controllers\PakketController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RapportageController;
use App\Http\Controllers\UitgifteController;
use Illuminate\Support\Facades\Route;

/* ---------------------------------------------------- inloggen & account */
Route::post('/auth/login',     [AuthController::class, 'login']);
Route::post('/auth/logout',    [AuthController::class, 'logout']);
Route::get('/auth/me',         [AuthController::class, 'me']);
Route::get('/auth/rechten',    [AuthController::class, 'rechten'])->middleware('ingelogd');
Route::put('/auth/wachtwoord', [AuthController::class, 'wachtwoord'])->middleware('ingelogd');

/* ---------------------------------------------------- dashboard */
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('ingelogd');

/* ---------------------------------------------------- gebruikers (GBR) */
Route::middleware('mag:gebruiker_beheren')->group(function (): void {
    Route::get('/gebruikers',                 [GebruikerController::class, 'index']);
    Route::post('/gebruikers',                [GebruikerController::class, 'store']);
    Route::put('/gebruikers/{id}',            [GebruikerController::class, 'update']);
    Route::patch('/gebruikers/{id}/actief',   [GebruikerController::class, 'actief']);
    Route::put('/gebruikers/{id}/wachtwoord', [GebruikerController::class, 'reset']);
});

/* ---------------------------------------------------- klanten & gezinnen (KLT) */
Route::get('/klanten',                [KlantController::class, 'index'])->middleware('mag:klant_inzien');
Route::post('/klanten',               [KlantController::class, 'store'])->middleware('mag:klant_beheren');
Route::put('/klanten/{id}',           [KlantController::class, 'update'])->middleware('mag:klant_inzien');
Route::delete('/klanten/{id}',        [KlantController::class, 'destroy'])->middleware('mag:klant_beheren');
Route::patch('/klanten/{id}/status',  [KlantController::class, 'status'])->middleware('mag:klant_beheren');
Route::get('/klanten/{id}/pakketten', [KlantController::class, 'pakketten'])->middleware('mag:klant_inzien');
Route::get('/wensen',                 [KlantController::class, 'wensen'])->middleware('mag:klant_inzien');
Route::post('/wensen',                [KlantController::class, 'wensToevoegen'])->middleware('mag:klant_inzien');

/* ---------------------------------------------------- leveranciers & leveringen (LEV) */
Route::get('/leveranciers',               [LeverancierController::class, 'index'])->middleware('ingelogd');
Route::post('/leveranciers',              [LeverancierController::class, 'store'])->middleware('mag:leverancier_beheren');
Route::put('/leveranciers/{id}',          [LeverancierController::class, 'update'])->middleware('mag:leverancier_beheren');
Route::patch('/leveranciers/{id}/actief', [LeverancierController::class, 'actief'])->middleware('mag:leverancier_beheren');
Route::delete('/leveranciers/{id}',       [LeverancierController::class, 'destroy'])->middleware('mag:leverancier_beheren');
Route::post('/leveringen',                [LeverancierController::class, 'levering'])->middleware('mag:levering_boeken');

/* ---------------------------------------------------- producten & voorraad (PRD/VRD) */
Route::get('/producten',                 [ProductController::class, 'index'])->middleware('ingelogd');
Route::post('/producten',                [ProductController::class, 'store'])->middleware('mag:product_beheren');
Route::put('/producten/{id}',            [ProductController::class, 'update'])->middleware('mag:product_beheren');
Route::delete('/producten/{id}',         [ProductController::class, 'destroy'])->middleware('mag:product_beheren');
Route::patch('/producten/{id}/voorraad', [ProductController::class, 'voorraad'])->middleware('mag:voorraad_corrigeren');
Route::post('/producten/{id}/afvoer',    [ProductController::class, 'afvoeren'])->middleware('mag:voorraad_corrigeren');

Route::get('/categorieen', [CategorieController::class, 'index'])->middleware('ingelogd');
Route::middleware('mag:product_beheren')->group(function (): void {
    Route::post('/categorieen',                  [CategorieController::class, 'store']);
    Route::put('/categorieen/{id}',              [CategorieController::class, 'update']);
    Route::delete('/categorieen/{id}',           [CategorieController::class, 'destroy']);
    Route::post('/categorieen/{id}/verplaatsen', [CategorieController::class, 'verplaatsen']);
});

/* ---------------------------------------------------- voedselpakketten (PAK) */
// eerst de vaste paden, daarna /pakketten/{id}
Route::get('/pakketten/samenstellen', [PakketController::class, 'samenstellen'])->middleware('mag:pakket_beheren');
Route::get('/pakketten/klant/{id}',   [PakketController::class, 'klant'])->middleware('mag:pakket_beheren');
Route::get('/pakketten',              [PakketController::class, 'index'])->middleware('mag:pakket_beheren');
Route::get('/pakketten/{id}',         [PakketController::class, 'show'])->middleware('ingelogd');
Route::post('/pakketten',             [PakketController::class, 'store'])->middleware('mag:pakket_beheren');
Route::put('/pakketten/{id}',         [PakketController::class, 'update'])->middleware('mag:pakket_beheren');
Route::delete('/pakketten/{id}',      [PakketController::class, 'destroy'])->middleware('mag:pakket_beheren');

/* ---------------------------------------------------- uitgifte (WRK) */
Route::middleware('mag:pakket_uitgeven')->group(function (): void {
    Route::get('/uitgifte/wachtrij',             [UitgifteController::class, 'wachtrij']);
    Route::get('/uitgifte/zoek',                 [UitgifteController::class, 'zoek']);
    Route::get('/uitgifte/{id}',                 [UitgifteController::class, 'show']);
    Route::post('/uitgifte/{id}/identiteit',     [UitgifteController::class, 'identiteit']);
    Route::post('/uitgifte/{id}/uitgeven',       [UitgifteController::class, 'uitgeven']);
    Route::post('/uitgifte/{id}/niet-opgehaald', [UitgifteController::class, 'nietOpgehaald']);
});

/* ---------------------------------------------------- rapportages (RAP) */
Route::middleware('mag:rapportage_export')->group(function (): void {
    Route::get('/rapportages',                  [RapportageController::class, 'index']);
    Route::get('/rapportages/maand',            [RapportageController::class, 'maand']);
    Route::get('/rapportages/export/{rapport}', [RapportageController::class, 'export']);
});
