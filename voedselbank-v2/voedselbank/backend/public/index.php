<?php
declare(strict_types=1);

/**
 * Front controller: elk verzoek naar /api/... komt hier binnen.
 *
 *   1. bootstrap    tijdzone en autoloader
 *   2. routes       welke URL bij welke controller hoort (routes/api.php)
 *   3. dispatch     rechten controleren, controller uitvoeren, JSON terugsturen
 */

use App\Core\Request;

require __DIR__ . '/../src/bootstrap.php';

$router = require __DIR__ . '/../routes/api.php';
$router->dispatch(Request::fromGlobals())->send();
