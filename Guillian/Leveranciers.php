<?php
session_start();

// Rol van de ingelogde gebruiker (wordt bij het inloggen in login.php in de sessie gezet)
$rol = $_SESSION['rol'] ?? '';

// Rechten per functie: Directie mag alles, Magazijnmedewerker mag alleen bekijken/zoeken
$magBeheren  = ($rol === 'Directie');                                  // toevoegen, aanpassen, verwijderen
$magBekijken = in_array($rol, ['Directie', 'Magazijnmedewerker']);     // bekijken, zoeken, eerstvolgende levering

// Alleen Directie mag privacygevoelige klantgegevens zien (Adres, Postcode, Telefoon, E-mail)
$magKlantgegevensZien = ($rol === 'Directie');

// Zonder geldige rol geen toegang tot deze pagina
if (!$magBekijken) {
    die('Je hebt geen toegang tot deze pagina.');
}

// Verbinding met de database
$host = 'mysql';
$db   = 'mydb';
$user = 'student';
$pass = 'veiligwachtwoord';

$pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------------------------------------------------------------------
// Hulpfuncties
// ---------------------------------------------------------------------------

// Korte versie van htmlspecialchars
function h($waarde): string
{
    return htmlspecialchars((string)$waarde, ENT_QUOTES, 'UTF-8');
}

// Haalt een GET-parameter veilig op als tekst (arrays of ontbrekende waarden geven de standaardwaarde)
function getTekst(string $sleutel, string $standaard = ''): string
{
    return (isset($_GET[$sleutel]) && is_string($_GET[$sleutel])) ? trim($_GET[$sleutel]) : $standaard;
}

// Is de klant geaccepteerd? Werkt zowel voor 1/0 als voor Ja/Nee.
function isGeaccepteerd($waarde): bool
{
    return in_array(strtolower(trim((string)$waarde)), ['1', 'ja', 'true', 'yes'], true);
}

// Bouwt de link voor een sorteerbare kolomkop; klikken op dezelfde kolom draait de richting om
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

// Bewaart alle overige GET-parameters als verborgen velden, zodat het zoeken/filteren
// op het ene tabblad de instellingen van het andere tabblad niet wist
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

// ---------------------------------------------------------------------------
// Actief tabblad (blijft behouden na zoeken, filteren of sorteren)
// ---------------------------------------------------------------------------
$actieveTab = (getTekst('tab') === 'klanten') ? 'klanten' : 'leveranciers';

// ---------------------------------------------------------------------------
// Leveringen: één keer ophalen en per leverancier de eerstvolgende bepalen
// ---------------------------------------------------------------------------
$nu         = new DateTime();
$binnenWeek = (new DateTime())->modify('+7 days');

$volgendeLevering      = [];   // [idLeverancier => DateTime van eerstvolgende levering]
$leveringenKomendeWeek = 0;

foreach ($pdo->query("SELECT Leverancier_idLeverancier, Leverdatum, Levertijd FROM Levering")->fetchAll() as $lev) {
    $datumTijd = DateTime::createFromFormat('Y-m-d H:i:s', $lev['Leverdatum'] . ' ' . $lev['Levertijd']);
    if (!$datumTijd) {
        // Val terug op een losse parse als het formaat afwijkt
        try {
            $datumTijd = new DateTime($lev['Leverdatum'] . ' ' . $lev['Levertijd']);
        } catch (Exception $e) {
            continue;
        }
    }

    if ($datumTijd <= $nu) {
        continue; // alleen toekomstige leveringen
    }

    $lid = $lev['Leverancier_idLeverancier'];
    if (!isset($volgendeLevering[$lid]) || $datumTijd < $volgendeLevering[$lid]) {
        $volgendeLevering[$lid] = $datumTijd;
    }
    if ($datumTijd <= $binnenWeek) {
        $leveringenKomendeWeek++;
    }
}

// ---------------------------------------------------------------------------
// Samenvatting bovenaan (ongefilterde totalen)
// ---------------------------------------------------------------------------
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

// Eerstvolgende levering over alle leveranciers heen
$eerstvolgendeNaam  = null;
$eerstvolgendeMoment = null;
foreach ($volgendeLevering as $lid => $moment) {
    if ($eerstvolgendeMoment === null || $moment < $eerstvolgendeMoment) {
        $eerstvolgendeMoment = $moment;
        $eerstvolgendeNaam   = $leverancierNamen[$lid] ?? 'Onbekende leverancier';
    }
}

// ---------------------------------------------------------------------------
// Leveranciers: zoeken, filteren en sorteren
// ---------------------------------------------------------------------------
$lZoek   = getTekst('lzoek');
$lFilter = getTekst('lfilter', 'alle');
if (!in_array($lFilter, ['alle', 'gepland', 'geen'], true)) {
    $lFilter = 'alle';
}

// Toegestane sorteerkolommen (whitelist, zodat er nooit gebruikersinvoer in de ORDER BY komt)
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

// Sorteren op eerstvolgende levering gebeurt in PHP (die waarde staat niet in de database)
$orderKolom    = ($lSorteer === 'levering') ? $lKolommen['bedrijfsnaam'] : $lKolommen[$lSorteer];
$orderRichting = ($lSorteer === 'levering') ? 'ASC' : strtoupper($lRichting);

$stmt = $pdo->prepare("SELECT * FROM Leverancier $where ORDER BY $orderKolom $orderRichting");
$stmt->execute($params);
$leveranciers = $stmt->fetchAll();

// Filter op geplande levering
if ($lFilter === 'gepland') {
    $leveranciers = array_values(array_filter($leveranciers, fn($l) => isset($volgendeLevering[$l['idLeverancier']])));
} elseif ($lFilter === 'geen') {
    $leveranciers = array_values(array_filter($leveranciers, fn($l) => !isset($volgendeLevering[$l['idLeverancier']])));
}

// Sorteren op eerstvolgende levering (leveranciers zonder levering staan altijd onderaan)
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

// ---------------------------------------------------------------------------
// Klanten: zoeken, filteren en sorteren
// Onbevoegde gebruikers krijgen de gevoelige kolommen hier al niet mee: die staan
// dan zelfs niet in de query (en ook niet in de zoekvelden of sorteerkolommen),
// dus ze kunnen ook niet via de HTML-broncode of via zoeken/sorteren gelekt worden.
// ---------------------------------------------------------------------------
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

// Zoekvelden: Directie mag ook op privacygevoelige velden zoeken, anderen alleen op gezinsnaam
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
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leveranciersbeheer</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        background: #f4f5f7;
        margin: 0;
        color: #222;
    }
    header {
        background: #1f2d3d;
        color: #fff;
        padding: 14px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    header h1 {
        font-size: 20px;
        margin: 0;
    }
    header .gebruiker {
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    header .gebruiker a {
        color: #fff;
        background: #35495e;
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
    }
    nav.tabs {
        display: flex;
        gap: 8px;
        background: #fff;
        padding: 0 24px;
        border-bottom: 1px solid #ddd;
    }
    nav.tabs button {
        background: none;
        border: none;
        padding: 14px 10px;
        font-size: 15px;
        cursor: pointer;
        color: #555;
        border-bottom: 3px solid transparent;
    }
    nav.tabs button.actief {
        color: #1f2d3d;
        border-bottom: 3px solid #2f7bde;
        font-weight: bold;
    }
    main {
        max-width: 1100px;
        margin: 20px auto;
        padding: 0 20px;
    }
    section.tabblad {
        display: none;
    }
    section.tabblad.actief {
        display: block;
    }
    .melding {
        background: #e6f7ec;
        color: #1e7b3c;
        border: 1px solid #b6e6c6;
        padding: 10px 14px;
        border-radius: 6px;
        margin-bottom: 16px;
        font-weight: bold;
    }
    .waarschuwing {
        background: #fff7e0;
        color: #8a6300;
        border: 1px solid #f0dca0;
        padding: 10px 14px;
        border-radius: 6px;
        margin-bottom: 16px;
    }
    .kaart {
        background: #fff;
        border: 1px solid #e2e2e2;
        border-radius: 8px;
        padding: 18px;
        margin-bottom: 20px;
    }
    .kaart h2 {
        margin-top: 0;
        font-size: 17px;
    }

    /* Samenvatting */
    .samenvatting {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .tegel {
        background: #fff;
        border: 1px solid #e2e2e2;
        border-left: 4px solid #2f7bde;
        border-radius: 8px;
        padding: 14px 16px;
    }
    .tegel .waarde {
        font-size: 26px;
        font-weight: bold;
        color: #1f2d3d;
        line-height: 1.2;
    }
    .tegel .waarde.klein {
        font-size: 17px;
    }
    .tegel .label {
        font-size: 13px;
        color: #666;
        margin-top: 4px;
    }

    /* Zoeken en filteren */
    .filterbalk {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
        align-items: center;
    }
    .filterbalk input[type=text] {
        flex: 1;
        min-width: 220px;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .filterbalk select {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        background: #fff;
    }
    .telling {
        font-size: 13px;
        color: #666;
        margin: 0 0 10px 0;
    }
    .leeg {
        padding: 18px 10px;
        color: #666;
    }
    .tabelwrap {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        text-align: left;
        padding: 10px;
        border-bottom: 1px solid #eee;
        vertical-align: top;
    }
    th {
        background: #f7f8fa;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #555;
        white-space: nowrap;
    }
    th a.sorteer {
        color: inherit;
        text-decoration: none;
    }
    th a.sorteer:hover,
    th a.sorteer:focus-visible {
        color: #2f7bde;
        text-decoration: underline;
    }
    th a.sorteer.actief {
        color: #1f2d3d;
    }
    tbody tr:hover td {
        background: #fafbfc;
    }
    .gedempt { color: #888; }
    .badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: bold;
    }
    .badge-ja      { background: #e6f7ec; color: #1e7b3c; }
    .badge-nee     { background: #f1f2f4; color: #666; }
    .badge-binnenkort { background: #fff2e0; color: #a35a00; margin-left: 4px; }

    .knop {
        display: inline-block;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
        margin: 2px 2px 2px 0;
    }
    .knop-primair   { background: #2f7bde; color: #fff; }
    .knop-secundair { background: #eef1f5; color: #333; }
    .knop-gevaar    { background: #e5484d; color: #fff; }
    .knop:hover { opacity: 0.9; }
    .acties { white-space: nowrap; }
    .detailpaneel {
        display: none;
        margin-top: 10px;
        border: 1px solid #e2e2e2;
        border-radius: 6px;
        padding: 12px;
        background: #fafbfc;
        white-space: normal;
    }
    .formulier label {
        display: block;
        font-size: 13px;
        font-weight: bold;
        margin-top: 10px;
        margin-bottom: 4px;
    }
    .formulier input {
        width: 100%;
        padding: 7px 9px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .formulier .knop { margin-top: 14px; }
</style>
</head>
<body>

<header>
    <h1>Leveranciersbeheer</h1>
    <div class="gebruiker">
        <?php if (isset($_SESSION['naam'])): ?>
            <span><?= htmlspecialchars($_SESSION['naam']) ?> (<?= htmlspecialchars($rol) ?>)</span>
            <a href="logout.php">Uitloggen</a>
        <?php endif; ?>
    </div>
</header>

<nav class="tabs">
    <button type="button" class="<?= $actieveTab === 'leveranciers' ? 'actief' : '' ?>" onclick="toonTab('leveranciers', this)">Leveranciers</button>
    <button type="button" class="<?= $actieveTab === 'klanten' ? 'actief' : '' ?>" onclick="toonTab('klanten', this)">Klanten</button>
</nav>

<main>

<?php if ($melding): ?>
    <p class="melding"><?= $melding ?></p>
<?php endif; ?>

<!-- ===================== SAMENVATTING (snel overzicht) ===================== -->
<div class="samenvatting">
    <div class="tegel">
        <div class="waarde"><?= $aantalLeveranciers ?></div>
        <div class="label">Leveranciers</div>
    </div>
    <div class="tegel">
        <div class="waarde"><?= $aantalKlanten ?></div>
        <div class="label">Klanten, waarvan <?= $aantalGeaccepteerd ?> geaccepteerd</div>
    </div>
    <div class="tegel">
        <div class="waarde"><?= $leveringenKomendeWeek ?></div>
        <div class="label">Leveringen in de komende 7 dagen</div>
    </div>
    <div class="tegel">
        <?php if ($eerstvolgendeMoment !== null): ?>
            <div class="waarde klein"><?= h($eerstvolgendeMoment->format('d-m-Y')) ?> om <?= h($eerstvolgendeMoment->format('H:i')) ?></div>
            <div class="label">Eerstvolgende levering: <?= h($eerstvolgendeNaam) ?></div>
        <?php else: ?>
            <div class="waarde klein">Geen</div>
            <div class="label">Er staat geen levering gepland</div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== TABBLAD: LEVERANCIERS ===================== -->
<section id="tab-leveranciers" class="tabblad <?= $actieveTab === 'leveranciers' ? 'actief' : '' ?>">

    <!-- Toevoegen: alleen zichtbaar voor Directie -->
    <?php if ($magBeheren): ?>
    <div class="kaart">
        <button class="knop knop-primair" onclick="document.getElementById('toevoegenForm').style.display='block'">
            + Nieuwe leverancier toevoegen
        </button>

        <div id="toevoegenForm" class="detailpaneel formulier">
            <form method="POST">
                <label>Bedrijfsnaam</label>
                <input type="text" name="bedrijfsnaam" required>

                <label>Adres</label>
                <input type="text" name="adres" required>

                <label>Contactpersoon</label>
                <input type="text" name="contactpersoon" required>

                <label>E-mail</label>
                <input type="email" name="email" required>

                <label>Telefoon</label>
                <input type="text" name="telefoon" required>

                <button type="submit" name="toevoegen" class="knop knop-primair">Opslaan</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="kaart">
        <h2>Overzicht leveranciers</h2>

        <form method="GET" class="filterbalk">
            <input type="hidden" name="tab" value="leveranciers">
            <?= behoudParameters(['tab', 'lzoek', 'lfilter']) ?>
            <input type="text" name="lzoek" placeholder="Zoek op bedrijfsnaam, contactpersoon of e-mail" value="<?= h($lZoek) ?>">
            <select name="lfilter" onchange="this.form.submit()" aria-label="Filter op levering">
                <option value="alle"    <?= $lFilter === 'alle'    ? 'selected' : '' ?>>Alle leveranciers</option>
                <option value="gepland" <?= $lFilter === 'gepland' ? 'selected' : '' ?>>Met geplande levering</option>
                <option value="geen"    <?= $lFilter === 'geen'    ? 'selected' : '' ?>>Zonder geplande levering</option>
            </select>
            <button type="submit" class="knop knop-secundair">Zoeken</button>
            <?php if ($lFilterActief): ?>
                <a href="<?= h(wisUrl('leveranciers', ['lzoek', 'lfilter'])) ?>" class="knop knop-secundair" style="text-decoration:none;">Wis filters</a>
            <?php endif; ?>
        </form>

        <p class="telling"><?= count($leveranciers) ?> van <?= $aantalLeveranciers ?> leveranciers getoond</p>

        <?php if (count($leveranciers) === 0): ?>
            <p class="leeg">
                <?php if ($lFilterActief): ?>
                    Geen leveranciers gevonden met deze zoekterm en dit filter. Pas je zoekopdracht aan of wis de filters.
                <?php else: ?>
                    Er zijn nog geen leveranciers toegevoegd.
                <?php endif; ?>
            </p>
        <?php else: ?>
        <div class="tabelwrap">
        <table>
            <thead>
            <tr>
                <?= sorteerKop('Bedrijfsnaam',   'leveranciers', 'l', 'bedrijfsnaam',   $lSorteer, $lRichting) ?>
                <?= sorteerKop('Adres',          'leveranciers', 'l', 'adres',          $lSorteer, $lRichting) ?>
                <?= sorteerKop('Contactpersoon', 'leveranciers', 'l', 'contactpersoon', $lSorteer, $lRichting) ?>
                <?= sorteerKop('E-mail',         'leveranciers', 'l', 'email',          $lSorteer, $lRichting) ?>
                <?= sorteerKop('Telefoon',       'leveranciers', 'l', 'telefoon',       $lSorteer, $lRichting) ?>
                <?= sorteerKop('Volgende levering', 'leveranciers', 'l', 'levering',    $lSorteer, $lRichting) ?>
                <th>Acties</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($leveranciers as $l): ?>
                <?php $moment = $volgendeLevering[$l['idLeverancier']] ?? null; ?>
                <tr>
                    <td><?= h($l['Bedrijfsnaam']) ?></td>
                    <td><?= h($l['Adres']) ?></td>
                    <td><?= h($l['Contactpersoon']) ?></td>
                    <td><?= h($l['E-mail']) ?></td>
                    <td><?= h($l['Telefoon']) ?></td>
                    <td>
                        <?php if ($moment !== null): ?>
                            <?= h($moment->format('d-m-Y')) ?> <?= h($moment->format('H:i')) ?>
                            <?php if ($moment <= $binnenWeek): ?>
                                <span class="badge badge-binnenkort">Binnenkort</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="gedempt">Niet gepland</span>
                        <?php endif; ?>
                    </td>
                    <td class="acties">
                        <button class="knop knop-secundair" onclick="document.getElementById('bekijkDetails<?= (int)$l['idLeverancier'] ?>').style.display='block'">
                            Bekijken
                        </button>

                        <?php if ($magBeheren): ?>
                        <button class="knop knop-secundair" onclick="document.getElementById('bewerkForm<?= (int)$l['idLeverancier'] ?>').style.display='block'">
                            Aanpassen
                        </button>

                        <form method="POST" style="display:inline"
                              onsubmit="return confirm(<?= h(json_encode("Weet je zeker dat je '" . $l['Bedrijfsnaam'] . "' wilt verwijderen?")) ?>);">
                            <input type="hidden" name="idLeverancier" value="<?= (int)$l['idLeverancier'] ?>">
                            <button type="submit" name="verwijderen" class="knop knop-gevaar">Verwijderen</button>
                        </form>
                        <?php endif; ?>

                        <!-- Gegevens bekijken -->
                        <div id="bekijkDetails<?= (int)$l['idLeverancier'] ?>" class="detailpaneel">
                            <p><strong>Bedrijfsnaam:</strong> <?= h($l['Bedrijfsnaam']) ?></p>
                            <p><strong>Adres:</strong> <?= h($l['Adres']) ?></p>
                            <p><strong>Contactpersoon:</strong> <?= h($l['Contactpersoon']) ?></p>
                            <p><strong>E-mail:</strong> <?= h($l['E-mail']) ?></p>
                            <p><strong>Telefoon:</strong> <?= h($l['Telefoon']) ?></p>
                            <p><strong>Eerstvolgende levering:</strong>
                                <?php if ($moment !== null): ?>
                                    <?= h($moment->format('d-m-Y')) ?> om <?= h($moment->format('H:i')) ?>
                                <?php else: ?>
                                    Er staat geen toekomstige levering gepland voor deze leverancier.
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Bewerkformulier, alleen voor Directie -->
                        <?php if ($magBeheren): ?>
                        <div id="bewerkForm<?= (int)$l['idLeverancier'] ?>" class="detailpaneel formulier">
                            <form method="POST">
                                <input type="hidden" name="idLeverancier" value="<?= (int)$l['idLeverancier'] ?>">

                                <label>Bedrijfsnaam</label>
                                <input type="text" name="bedrijfsnaam" value="<?= h($l['Bedrijfsnaam']) ?>" required>

                                <label>Adres</label>
                                <input type="text" name="adres" value="<?= h($l['Adres']) ?>" required>

                                <label>Contactpersoon</label>
                                <input type="text" name="contactpersoon" value="<?= h($l['Contactpersoon']) ?>" required>

                                <label>E-mail</label>
                                <input type="email" name="email" value="<?= h($l['E-mail']) ?>" required>

                                <label>Telefoon</label>
                                <input type="text" name="telefoon" value="<?= h($l['Telefoon']) ?>" required>

                                <button type="submit" name="aanpassen" class="knop knop-primair">Opslaan</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== TABBLAD: KLANTEN ===================== -->
<section id="tab-klanten" class="tabblad <?= $actieveTab === 'klanten' ? 'actief' : '' ?>">
    <div class="kaart">
        <h2>Klantoverzicht</h2>

        <?php if (!$magKlantgegevensZien): ?>
            <p class="waarschuwing">Je rol geeft geen toegang tot privacygevoelige klantgegevens (adres, postcode, telefoon, e-mail). Deze worden hieronder afgeschermd.</p>
        <?php endif; ?>

        <form method="GET" class="filterbalk">
            <input type="hidden" name="tab" value="klanten">
            <?= behoudParameters(['tab', 'kzoek', 'kfilter']) ?>
            <input type="text" name="kzoek"
                   placeholder="<?= $magKlantgegevensZien ? 'Zoek op gezinsnaam, adres, postcode, telefoon of e-mail' : 'Zoek op gezinsnaam' ?>"
                   value="<?= h($kZoek) ?>">
            <select name="kfilter" onchange="this.form.submit()" aria-label="Filter op acceptatie">
                <option value="alle" <?= $kFilter === 'alle' ? 'selected' : '' ?>>Alle klanten</option>
                <option value="ja"   <?= $kFilter === 'ja'   ? 'selected' : '' ?>>Alleen geaccepteerd</option>
                <option value="nee"  <?= $kFilter === 'nee'  ? 'selected' : '' ?>>Nog niet geaccepteerd</option>
            </select>
            <button type="submit" class="knop knop-secundair">Zoeken</button>
            <?php if ($kFilterActief): ?>
                <a href="<?= h(wisUrl('klanten', ['kzoek', 'kfilter'])) ?>" class="knop knop-secundair" style="text-decoration:none;">Wis filters</a>
            <?php endif; ?>
        </form>

        <p class="telling"><?= count($klanten) ?> van <?= $aantalKlanten ?> klanten getoond</p>

        <?php if (count($klanten) === 0): ?>
            <p class="leeg">
                <?php if ($kFilterActief): ?>
                    Geen klanten gevonden met deze zoekterm en dit filter. Pas je zoekopdracht aan of wis de filters.
                <?php else: ?>
                    Er zijn nog geen klanten geregistreerd.
                <?php endif; ?>
            </p>
        <?php else: ?>
        <div class="tabelwrap">
        <table>
            <thead>
            <tr>
                <?= sorteerKop('Gezinsnaam',    'klanten', 'k', 'gezinsnaam',   $kSorteer, $kRichting) ?>
                <?= sorteerKop('Aanmelddatum',  'klanten', 'k', 'aanmelddatum', $kSorteer, $kRichting) ?>
                <?= sorteerKop('Geaccepteerd',  'klanten', 'k', 'geaccepteerd', $kSorteer, $kRichting) ?>
                <?php if ($magKlantgegevensZien): ?>
                    <?= sorteerKop('Adres',     'klanten', 'k', 'adres',    $kSorteer, $kRichting) ?>
                    <?= sorteerKop('Postcode',  'klanten', 'k', 'postcode', $kSorteer, $kRichting) ?>
                    <?= sorteerKop('Telefoon',  'klanten', 'k', 'telefoon', $kSorteer, $kRichting) ?>
                    <?= sorteerKop('E-mail',    'klanten', 'k', 'email',    $kSorteer, $kRichting) ?>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($klanten as $k): ?>
                <tr>
                    <td><?= h($k['Gezinsnaam']) ?></td>
                    <td><?= h($k['Aanmelddatum']) ?></td>
                    <td>
                        <?php if (isGeaccepteerd($k['Geaccepteerd'])): ?>
                            <span class="badge badge-ja">Ja</span>
                        <?php else: ?>
                            <span class="badge badge-nee">Nee</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($magKlantgegevensZien): ?>
                        <td><?= h($k['Adres']) ?></td>
                        <td><?= h($k['Postcode']) ?></td>
                        <td><?= h($k['Telefoon']) ?></td>
                        <td><?= h($k['E-mail']) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</section>

</main>

<script>
function toonTab(naam, knop) {
    document.querySelectorAll('section.tabblad').forEach(function (s) { s.classList.remove('actief'); });
    document.querySelectorAll('nav.tabs button').forEach(function (b) { b.classList.remove('actief'); });
    document.getElementById('tab-' + naam).classList.add('actief');
    knop.classList.add('actief');
}
</script>

</body>
</html>