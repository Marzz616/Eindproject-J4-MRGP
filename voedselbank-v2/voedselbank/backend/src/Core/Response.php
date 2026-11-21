<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Het antwoord van de API. Controllers geven een array terug (wordt JSON met
 * status 200) of maken zelf een Response, bijvoorbeeld voor status 201 of CSV.
 */
class Response
{
    public function __construct(
        protected mixed $data,
        protected int $status = 200,
        protected array $headers = []
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($data, $status);
    }

    /** CSV-download met puntkomma's en een BOM, zodat Excel de accenten goed leest. */
    public static function csv(string $bestandsnaam, array $kop, array $rijen): self
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $kop, ';');
        foreach ($rijen as $rij) {
            fputcsv($stream, $rij, ';');
        }
        rewind($stream);
        $inhoud = stream_get_contents($stream);
        fclose($stream);

        return new self($inhoud, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $bestandsnaam . '"',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('X-Content-Type-Options: nosniff');

        if (is_string($this->data) && isset($this->headers['Content-Type'])) {
            foreach ($this->headers as $naam => $waarde) {
                header($naam . ': ' . $waarde);
            }
            echo $this->data;
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
