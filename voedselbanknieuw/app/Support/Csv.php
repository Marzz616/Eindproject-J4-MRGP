<?php
declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * CSV-download voor de rapportages (RAP-3).
 *
 * Puntkomma's als scheidingsteken en een BOM vooraan, zodat Excel in het
 * Nederlands de kolommen goed splitst en de accenten goed laat zien.
 */
final class Csv
{
    public static function download(string $bestandsnaam, array $kop, array $rijen): Response
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

        return response($inhoud, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $bestandsnaam . '"',
        ]);
    }
}
