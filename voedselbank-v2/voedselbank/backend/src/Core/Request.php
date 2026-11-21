<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Het binnenkomende HTTP-verzoek: methode, pad, route-parameters,
 * querystring en JSON-body op één plek.
 */
final class Request
{
    private ?array $body = null;

    /** @param array<string,string> $params waarden uit de URL, zoals {id} */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $params = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $pad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // alles na /api is het eigenlijke pad: /api/klanten/3 -> /klanten/3
        $pad = preg_replace('#^.*?/api(?=/|$)#', '', $pad) ?: '/';

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($pad, '/') ?: '/');
    }

    public function withParams(array $params): self
    {
        $kopie = clone $this;
        $kopie->params = $params;
        return $kopie;
    }

    /** Een parameter uit de URL, bijvoorbeeld het {id} in /klanten/{id}. */
    public function param(string $naam): string
    {
        return (string) ($this->params[$naam] ?? '');
    }

    public function id(): int
    {
        return (int) $this->param('id');
    }

    /** Een waarde uit de querystring (?maand=3). */
    public function query(string $naam, mixed $standaard = null): mixed
    {
        return $_GET[$naam] ?? $standaard;
    }

    /** De volledige JSON-body. */
    public function all(): array
    {
        if ($this->body === null) {
            $data = json_decode(file_get_contents('php://input') ?: '', true);
            $this->body = is_array($data) ? $data : [];
        }
        return $this->body;
    }

    public function input(string $naam, mixed $standaard = null): mixed
    {
        return $this->all()[$naam] ?? $standaard;
    }

    public function string(string $naam, string $standaard = ''): string
    {
        return trim((string) $this->input($naam, $standaard));
    }

    public function int(string $naam, int $standaard = 0): int
    {
        return (int) $this->input($naam, $standaard);
    }

    public function bool(string $naam, bool $standaard = false): bool
    {
        return (bool) $this->input($naam, $standaard);
    }

    public function array(string $naam): array
    {
        $waarde = $this->input($naam);
        return is_array($waarde) ? $waarde : [];
    }
}
