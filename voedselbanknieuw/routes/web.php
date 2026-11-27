<?php
declare(strict_types=1);

/**
 * De pagina's van de back-office.
 *
 * De schermen zijn gemaakt in Vue; die draaien samen in één pagina (een
 * single page application). Laravel levert daarom voor elke URL dezelfde
 * Blade-view, en de Vue-router bepaalt daarna welk scherm je ziet. Zo werkt
 * ook F5 op bijvoorbeeld /klanten of /uitgifte.
 *
 * Alles wat met /api begint staat in routes/api.php en komt hier dus niet.
 */

use Illuminate\Support\Facades\Route;

Route::view('/{pad?}', 'app')
    ->where('pad', '^(?!api|build|up).*$')
    ->name('app');
