<?php
declare(strict_types=1);

/**
 * Databaseverbinding. De waarden komen uit omgevingsvariabelen
 * (docker-compose.yml zet ze); de standaardwaarden passen bij Docker.
 */
return [
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'name' => getenv('DB_NAME') ?: 'voedselbank',
    'user' => getenv('DB_USER') ?: 'vbuser',
    'pass' => getenv('DB_PASS') ?: 'vbpass',
];
