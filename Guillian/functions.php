<?php

// ---------------------------------------------------------------------------
// Hulpfuncties
// ---------------------------------------------------------------------------

// Korte versie van htmlspecialchars
function h($waarde): string
{
    return htmlspecialchars((string)$waarde, ENT_QUOTES, 'UTF-8');
}

// Haalt een GET-parameter veilig op als tekst
function getTekst(string $sleutel, string $standaard = ''): string
{
    return (isset($_GET[$sleutel]) && is_string($_GET[$sleutel])) ? trim($_GET[$sleutel]) : $standaard;
}

// Is de klant geaccepteerd?
function isGeaccepteerd($waarde): bool
{
    return in_array(strtolower(trim((string)$waarde)), ['1', 'ja', 'true', 'yes'], true);
}

// Bouwt de link voor een sorteerbare kolomkop
function sorteerUrl(string $tab, string $voorvoegsel, string $kolom, string $huidig, string $richting): string
{
    $nieuweRichting = ($huidig === $kolom && $richting === 'asc') ? 'desc' : 'asc';
    $p = $_GET;
    $p['tab'] = $tab;
    $p[$voorvoegsel . 'sorteer']  = $kolom;
    $p[$voorvoegsel . 'richting'] = $nieuweRichting;
    return '?' . http_build_query($p);
}

// Toont een sorteerbare kolomkop met pijltje
function sorteerKop(string $label, string $tab, string $voorvoegsel, string $kolom, string $huidig, string $richting): string
{
    $actief = ($huidig === $kolom);
    $pijl   = $actief ? ($richting === 'asc' ? '▲' : '▼') : '↕';
    $aria   = $actief ? ($richting === 'asc' ? 'ascending' : 'descending') : 'none';

    return '<th aria-sort="' . $aria . '"><a class="sorteer' . ($actief ? ' actief' : '') . '" href="'
        . h(sorteerUrl($tab, $voorvoegsel, $kolom, $huidig, $richting)) . '">'
        . h($label) . ' <span aria-hidden="true">' . $pijl . '</span></a></th>';
}

// Bewaart overige GET-parameters als verborgen velden
function behoudParameters(array $uitgezonderd): string
{
    $html = '';
    foreach ($_GET as $sleutel => $waarde) {
        if (in_array($sleutel, $uitgezonderd, true) || !is_string($waarde) || $waarde === '') {
            continue;
        }
        $html .= '<input type="hidden" name="' . h($sleutel) . '" value="' . h($waarde) . '">';
    }
    return $html;
}

// Link die alleen de opgegeven parameters wist (voor "Wis filters")
function wisUrl(string $tab, array $sleutels): string
{
    $p = $_GET;
    foreach ($sleutels as $s) {
        unset($p[$s]);
    }
    $p['tab'] = $tab;
    return '?' . http_build_query($p);
}

// ---------------------------------------------------------------------------
// Leveringen: één keer ophalen en per leverancier de eerstvolgende bepalen
// ---------------------------------------------------------------------------
function haalLeveringenOp(PDO $pdo): array
{
    $nu         = new DateTime();
    $binnenWeek = (new DateTime())->modify('+7 days');

    $volgendeLevering      = [];
    $leveringenKomendeWeek = 0;

    $rijen = $pdo->query("SELECT Leverancier_idLeverancier, Leverdatum, Levertijd FROM Levering")->fetchAll();

    foreach ($rijen as $lev) {
        $datumTijd = DateTime::createFromFormat('Y-m-d H:i:s', $lev['Leverdatum'] . ' ' . $lev['Levertijd']);
        if (!$datumTijd) {
            try {
                $datumTijd = new DateTime($lev['Leverdatum'] . ' ' . $lev['Levertijd']);
            } catch (Exception $e) {
                continue;
            }
        }

        if ($datumTijd <= $nu) {
            continue;
        }

        $lid = $lev['Leverancier_idLeverancier'];
        if (!isset($volgendeLevering[$lid]) || $datumTijd < $volgendeLevering[$lid]) {
            $volgendeLevering[$lid] = $datumTijd;
        }
        if ($datumTijd <= $binnenWeek) {
            $leveringenKomendeWeek++;
        }
    }

    return [
        'volgendeLevering'      => $volgendeLevering,
        'leveringenKomendeWeek' => $leveringenKomendeWeek,
        'binnenWeek'            => $binnenWeek,
    ];
}

// ---------------------------------------------------------------------------
// Verwerkt POST-acties (toevoegen / aanpassen / verwijderen)
// ---------------------------------------------------------------------------
function verwerkActies(PDO $pdo, bool $magBeheren): string
{
    $melding = '';

    // --- Toevoegen ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toevoegen'])) {
        if (!$magBeheren) {
            die('Je hebt geen rechten om een leverancier toe te voegen.');
        }
        $stmt = $pdo->prepare(
            "INSERT INTO Leverancier (Bedrijfsnaam, Adres, Contactpersoon, `E-mail`, Telefoon)
             VALUES (:bedrijfsnaam, :adres, :contactpersoon, :email, :telefoon)"
        );
        $stmt->execute([
            ':bedrijfsnaam'   => trim($_POST['bedrijfsnaam']),
            ':adres'          => trim($_POST['adres']),
            ':contactpersoon' => trim($_POST['contactpersoon']),
            ':email'          => trim($_POST['email']),
            ':telefoon'       => trim($_POST['telefoon']),
        ]);
        $melding = "Leverancier '" . htmlspecialchars($_POST['bedrijfsnaam']) . "' is succesvol toegevoegd!";
    }

    // --- Aanpassen ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aanpassen'])) {
        if (!$magBeheren) {
            die('Je hebt geen rechten om leveranciersgegevens aan te passen.');
        }
        $stmt = $pdo->prepare(
            "UPDATE Leverancier
             SET Bedrijfsnaam = :bedrijfsnaam,
                 Adres = :adres,
                 Contactpersoon = :contactpersoon,
                 `E-mail` = :email,
                 Telefoon = :telefoon
             WHERE idLeverancier = :id"
        );
        $stmt->execute([
            ':bedrijfsnaam'   => trim($_POST['bedrijfsnaam']),
            ':adres'          => trim($_POST['adres']),
            ':contactpersoon' => trim($_POST['contactpersoon']),
            ':email'          => trim($_POST['email']),
            ':telefoon'       => trim($_POST['telefoon']),
            ':id'             => $_POST['idLeverancier'],
        ]);
        $melding = "Gegevens van '" . htmlspecialchars($_POST['bedrijfsnaam']) . "' zijn succesvol aangepast!";
    }

    // --- Verwijderen ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verwijderen'])) {
        if (!$magBeheren) {
            die('Je hebt geen rechten om een leverancier te verwijderen.');
        }
        $id = $_POST['idLeverancier'];

        $stmt = $pdo->prepare("SELECT Bedrijfsnaam FROM Leverancier WHERE idLeverancier = :id");
        $stmt->execute([':id' => $id]);
        $naam = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM Leverancier WHERE idLeverancier = :id");
        $stmt->execute([':id' => $id]);

        $melding = "Leverancier '" . htmlspecialchars($naam) . "' is succesvol verwijderd!";
    }

    return $melding;
}

// ---------------------------------------------------------------------------
// Samenvatting (ongefilterde totalen)
// ---------------------------------------------------------------------------
function haalSamenvattingOp(PDO $pdo, array $volgendeLevering): array
{
    $leverancierNamen = [];
    foreach ($pdo->query("SELECT idLeverancier, Bedrijfsnaam FROM Leverancier")->fetchAll() as $rij) {
        $leverancierNamen[$rij['idLeverancier']] = $rij['Bedrijfsnaam'];
    }
    $aantalLeveranciers = count($leverancierNamen);

    $aantalKlanten      = 0;
    $aantalGeaccepteerd = 0;
    foreach ($pdo->query("SELECT Geaccepteerd FROM Klant")->fetchAll() as $rij) {
        $aantalKlanten++;
        if (isGeaccepteerd($rij['Geaccepteerd'])) {
            $aantalGeaccepteerd++;
        }
    }

    $eerstvolgendeNaam   = null;
    $eerstvolgendeMoment = null;
    foreach ($volgendeLevering as $lid => $moment) {
        if ($eerstvolgendeMoment === null || $moment < $eerstvolgendeMoment) {
            $eerstvolgendeMoment = $moment;
            $eerstvolgendeNaam   = $leverancierNamen[$lid] ?? 'Onbekende leverancier';
        }
    }

    return compact(
        'aantalLeveranciers',
        'aantalKlanten',
        'aantalGeaccepteerd',
        'eerstvolgendeNaam',
        'eerstvolgendeMoment'
    );
}

// ---------------------------------------------------------------------------
// Leveranciers: zoeken, filteren en sorteren
// ---------------------------------------------------------------------------
function haalLeveranciersOp(PDO $pdo, array $volgendeLevering): array
{
    $lZoek   = getTekst('lzoek');
    $lFilter = getTekst('lfilter', 'alle');
    if (!in_array($lFilter, ['alle', 'gepland', 'geen'], true)) {
        $lFilter = 'alle';
    }

    $lKolommen = [
        'bedrijfsnaam'   => 'Bedrijfsnaam',
        'adres'          => 'Adres',
        'contactpersoon' => 'Contactpersoon',
        'email'          => '`E-mail`',
        'telefoon'       => 'Telefoon',
    ];
    $lSorteer = getTekst('lsorteer', 'bedrijfsnaam');
    if (!isset($lKolommen[$lSorteer]) && $lSorteer !== 'levering') {
        $lSorteer = 'bedrijfsnaam';
    }
    $lRichting = (getTekst('lrichting') === 'desc') ? 'desc' : 'asc';

    $where  = '';
    $params = [];
    if ($lZoek !== '') {
        $where = "WHERE Bedrijfsnaam LIKE :z1 OR Contactpersoon LIKE :z2 OR `E-mail` LIKE :z3";
        $params = [
            ':z1' => '%' . $lZoek . '%',
            ':z2' => '%' . $lZoek . '%',
            ':z3' => '%' . $lZoek . '%',
        ];
    }

    $orderKolom    = ($lSorteer === 'levering') ? $lKolommen['bedrijfsnaam'] : $lKolommen[$lSorteer];
    $orderRichting = ($lSorteer === 'levering') ? 'ASC' : strtoupper($lRichting);

    $stmt = $pdo->prepare("SELECT * FROM Leverancier $where ORDER BY $orderKolom $orderRichting");
    $stmt->execute($params);
    $leveranciers = $stmt->fetchAll();

    if ($lFilter === 'gepland') {
        $leveranciers = array_values(array_filter($leveranciers, fn($l) => isset($volgendeLevering[$l['idLeverancier']])));
    } elseif ($lFilter === 'geen') {
        $leveranciers = array_values(array_filter($leveranciers, fn($l) => !isset($volgendeLevering[$l['idLeverancier']])));
    }

    if ($lSorteer === 'levering') {
        usort($leveranciers, function ($a, $b) use ($volgendeLevering, $lRichting) {
            $da = $volgendeLevering[$a['idLeverancier']] ?? null;
            $db = $volgendeLevering[$b['idLeverancier']] ?? null;
            if ($da === null && $db === null) return 0;
            if ($da === null) return 1;
            if ($db === null) return -1;
            return ($lRichting === 'desc' ? -1 : 1) * ($da <=> $db);
        });
    }

    $lFilterActief = ($lZoek !== '' || $lFilter !== 'alle');

    return compact('leveranciers', 'lZoek', 'lFilter', 'lSorteer', 'lRichting', 'lFilterActief');
}

// ---------------------------------------------------------------------------
// Klanten: zoeken, filteren en sorteren
// ---------------------------------------------------------------------------
function haalKlantenOp(PDO $pdo, bool $magKlantgegevensZien): array
{
    $kZoek   = getTekst('kzoek');
    $kFilter = getTekst('kfilter', 'alle');
    if (!in_array($kFilter, ['alle', 'ja', 'nee'], true)) {
        $kFilter = 'alle';
    }

    $kKolommen = [
        'gezinsnaam'   => 'Gezinsnaam',
        'aanmelddatum' => 'Aanmelddatum',
        'geaccepteerd' => 'Geaccepteerd',
    ];
    if ($magKlantgegevensZien) {
        $kKolommen += [
            'adres'    => 'Adres',
            'postcode' => 'Postcode',
            'telefoon' => 'Telefoon',
            'email'    => '`E-mail`',
        ];
    }
    $kSorteer = getTekst('ksorteer', 'gezinsnaam');
    if (!isset($kKolommen[$kSorteer])) {
        $kSorteer = 'gezinsnaam';
    }
    $kRichting = (getTekst('krichting') === 'desc') ? 'desc' : 'asc';

    $kZoekVelden = $magKlantgegevensZien
        ? ['Gezinsnaam', 'Adres', 'Postcode', 'Telefoon', '`E-mail`']
        : ['Gezinsnaam'];

    $kWhere  = '';
    $kParams = [];
    if ($kZoek !== '') {
        $delen = [];
        foreach ($kZoekVelden as $i => $veld) {
            $delen[] = "$veld LIKE :k$i";
            $kParams[":k$i"] = '%' . $kZoek . '%';
        }
        $kWhere = 'WHERE ' . implode(' OR ', $delen);
    }

    $kSelect = $magKlantgegevensZien
        ? "idKlant, Gezinsnaam, Adres, Postcode, Telefoon, `E-mail`, Aanmelddatum, Geaccepteerd"
        : "idKlant, Gezinsnaam, Aanmelddatum, Geaccepteerd";

    $stmt = $pdo->prepare(
        "SELECT $kSelect FROM Klant $kWhere ORDER BY " . $kKolommen[$kSorteer] . ' ' . strtoupper($kRichting)
    );
    $stmt->execute($kParams);
    $klanten = $stmt->fetchAll();

    if ($kFilter === 'ja') {
        $klanten = array_values(array_filter($klanten, fn($k) => isGeaccepteerd($k['Geaccepteerd'])));
    } elseif ($kFilter === 'nee') {
        $klanten = array_values(array_filter($klanten, fn($k) => !isGeaccepteerd($k['Geaccepteerd'])));
    }

    $kFilterActief = ($kZoek !== '' || $kFilter !== 'alle');

    return compact('klanten', 'kZoek', 'kFilter', 'kSorteer', 'kRichting', 'kFilterActief');
}