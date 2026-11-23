<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LegacyApiController;

Route::match(['get', 'post'], '/auth.php', [AuthController::class, 'handle']);
Route::match(['get', 'post'], '/auth', [AuthController::class, 'handle']);

foreach ([
    'dashboard', 'gebruikers', 'klanten', 'leveranciers',
    'pakketten', 'producten', 'rapportages', 'uitgifte',
] as $script) {
    Route::match(['get', 'post', 'put', 'patch', 'delete'], "/{$script}.php", function () use ($script) {
        return app(LegacyApiController::class)->handle(request(), $script);
    });
    Route::match(['get', 'post', 'put', 'patch', 'delete'], "/{$script}", function () use ($script) {
        return app(LegacyApiController::class)->handle(request(), $script);
    });
}
