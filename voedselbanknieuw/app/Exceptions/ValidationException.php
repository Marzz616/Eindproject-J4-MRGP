<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Invoer klopt niet: status 422 met per veld een melding.
 * De frontend zet die meldingen direct onder het juiste invoerveld.
 *
 *   {"fouten": {"email": "Vul een geldig e-mailadres in."}}
 */
final class ValidationException extends HttpException
{
    /** @param array<string,string> $fouten */
    public function __construct(private readonly array $fouten)
    {
        parent::__construct(422, (string) (array_values($fouten)[0] ?? 'Controleer de invoer.'));
    }

    public function body(): array
    {
        return ['fouten' => $this->fouten];
    }

    /** Gooit alleen als er echt fouten zijn. */
    public static function throwIf(array $fouten): void
    {
        if ($fouten) {
            throw new self($fouten);
        }
    }
}
