<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Een fout die als nette JSON-melding naar de browser gaat.
 *
 *   throw new HttpException(409, 'Dit pakket is al uitgegeven.', ['reden' => 'al_uitgegeven']);
 *
 * geeft status 409 met {"fout": "...", "reden": "al_uitgegeven"}.
 *
 * Het omzetten naar een JSON-antwoord gebeurt op één plek: bootstrap/app.php.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $melding,
        private readonly array $extra = []
    ) {
        parent::__construct($melding);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): array
    {
        return ['fout' => $this->getMessage()] + $this->extra;
    }
}
