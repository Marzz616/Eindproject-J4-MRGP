<?php
declare(strict_types=1);

/**
 * Alle API-routes op één plek.
 *
 * Per route: HTTP-methode, pad, controller-methode en wat er nodig is
 * om hem te gebruiken (Router::PUBLIC, Router::LOGIN of een permissie).
 * Zo is in één oogopslag te zien wie wat mag (US-SEC-01).
 */

use App\Controllers\AuthController;
use App\Controllers\CategorieController;
use App\Controllers\DashboardController;
use App\Controllers\GebruikerController;
use App\Controllers\KlantController;
use App\Controllers\LeverancierController;
use App\Controllers\PakketController;
use App\Controllers\ProductController;
use App\Controllers\RapportageController;
use App\Controllers\UitgifteController;
use App\Core\Router;

$r = new Router();

/* ---------------------------------------------------- inloggen & account */
$r->post('/auth/login',       [AuthController::class, 'login'],          Router::PUBLIC);
$r->post('/auth/logout',      [AuthController::class, 'logout'],         Router::PUBLIC);
$r->get('/auth/me',           [AuthController::class, 'me'],             Router::PUBLIC);
$r->get('/auth/rechten',      [AuthController::class, 'rechten'],        Router::LOGIN);
$r->put('/auth/wachtwoord',   [AuthController::class, 'wachtwoord'],     Router::LOGIN);

/* ---------------------------------------------------- dashboard */
$r->get('/dashboard',         [DashboardController::class, 'index'],     Router::LOGIN);

/* ---------------------------------------------------- gebruikers (GBR) */
$r->get('/gebruikers',                   [GebruikerController::class, 'index'],    'gebruiker_beheren');
$r->post('/gebruikers',                  [GebruikerController::class, 'store'],    'gebruiker_beheren');
$r->put('/gebruikers/{id}',              [GebruikerController::class, 'update'],   'gebruiker_beheren');
$r->patch('/gebruikers/{id}/actief',     [GebruikerController::class, 'actief'],   'gebruiker_beheren');
$r->put('/gebruikers/{id}/wachtwoord',   [GebruikerController::class, 'reset'],    'gebruiker_beheren');

/* ---------------------------------------------------- klanten & gezinnen (KLT) */
$r->get('/klanten',                      [KlantController::class, 'index'],        'klant_inzien');
$r->post('/klanten',                     [KlantController::class, 'store'],        'klant_beheren');
$r->put('/klanten/{id}',                 [KlantController::class, 'update'],       'klant_inzien');
$r->delete('/klanten/{id}',              [KlantController::class, 'destroy'],      'klant_beheren');
$r->patch('/klanten/{id}/status',        [KlantController::class, 'status'],       'klant_beheren');
$r->get('/klanten/{id}/pakketten',       [KlantController::class, 'pakketten'],    'klant_inzien');
$r->get('/wensen',                       [KlantController::class, 'wensen'],       'klant_inzien');
$r->post('/wensen',                      [KlantController::class, 'wensToevoegen'],'klant_inzien');

/* ---------------------------------------------------- leveranciers & leveringen (LEV) */
$r->get('/leveranciers',                 [LeverancierController::class, 'index'],    Router::LOGIN);
$r->post('/leveranciers',                [LeverancierController::class, 'store'],    'leverancier_beheren');
$r->put('/leveranciers/{id}',            [LeverancierController::class, 'update'],   'leverancier_beheren');
$r->patch('/leveranciers/{id}/actief',   [LeverancierController::class, 'actief'],   'leverancier_beheren');
$r->delete('/leveranciers/{id}',         [LeverancierController::class, 'destroy'],  'leverancier_beheren');
$r->post('/leveringen',                  [LeverancierController::class, 'levering'], 'levering_boeken');

/* ---------------------------------------------------- producten & voorraad (PRD/VRD) */
$r->get('/producten',                    [ProductController::class, 'index'],     Router::LOGIN);
$r->post('/producten',                   [ProductController::class, 'store'],     'product_beheren');
$r->put('/producten/{id}',               [ProductController::class, 'update'],    'product_beheren');
$r->delete('/producten/{id}',            [ProductController::class, 'destroy'],   'product_beheren');
$r->patch('/producten/{id}/voorraad',    [ProductController::class, 'voorraad'],  'voorraad_corrigeren');
$r->post('/producten/{id}/afvoer',       [ProductController::class, 'afvoeren'],  'voorraad_corrigeren');

$r->get('/categorieen',                  [CategorieController::class, 'index'],      Router::LOGIN);
$r->post('/categorieen',                 [CategorieController::class, 'store'],      'product_beheren');
$r->put('/categorieen/{id}',             [CategorieController::class, 'update'],     'product_beheren');
$r->delete('/categorieen/{id}',          [CategorieController::class, 'destroy'],    'product_beheren');
$r->post('/categorieen/{id}/verplaatsen',[CategorieController::class, 'verplaatsen'],'product_beheren');

/* ---------------------------------------------------- voedselpakketten (PAK) */
// eerst de vaste paden, daarna /pakketten/{id}
$r->get('/pakketten/samenstellen',       [PakketController::class, 'samenstellen'], 'pakket_beheren');
$r->get('/pakketten/klant/{id}',         [PakketController::class, 'klant'],        'pakket_beheren');
$r->get('/pakketten',                    [PakketController::class, 'index'],        'pakket_beheren');
$r->get('/pakketten/{id}',               [PakketController::class, 'show'],         Router::LOGIN);
$r->post('/pakketten',                   [PakketController::class, 'store'],        'pakket_beheren');
$r->put('/pakketten/{id}',               [PakketController::class, 'update'],       'pakket_beheren');
$r->delete('/pakketten/{id}',            [PakketController::class, 'destroy'],      'pakket_beheren');

/* ---------------------------------------------------- uitgifte (WRK) */
$r->get('/uitgifte/wachtrij',            [UitgifteController::class, 'wachtrij'],       'pakket_uitgeven');
$r->get('/uitgifte/zoek',                [UitgifteController::class, 'zoek'],           'pakket_uitgeven');
$r->get('/uitgifte/{id}',                [UitgifteController::class, 'show'],           'pakket_uitgeven');
$r->post('/uitgifte/{id}/identiteit',    [UitgifteController::class, 'identiteit'],     'pakket_uitgeven');
$r->post('/uitgifte/{id}/uitgeven',      [UitgifteController::class, 'uitgeven'],       'pakket_uitgeven');
$r->post('/uitgifte/{id}/niet-opgehaald',[UitgifteController::class, 'nietOpgehaald'],  'pakket_uitgeven');

/* ---------------------------------------------------- rapportages (RAP) */
$r->get('/rapportages',                  [RapportageController::class, 'index'],  'rapportage_export');
$r->get('/rapportages/maand',            [RapportageController::class, 'maand'],  'rapportage_export');
$r->get('/rapportages/export/{rapport}', [RapportageController::class, 'export'], 'rapportage_export');

return $r;
