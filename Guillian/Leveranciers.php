<?php
session_start();

require_once 'data_base.php';
require_once 'functions.php';

// Rol van de ingelogde gebruiker
$rol = $_SESSION['rol'] ?? '';

// Rechten per functie
$magBeheren           = ($rol === 'Directie');
$magBekijken          = in_array($rol, ['Directie', 'Magazijnmedewerker']);
$magKlantgegevensZien = ($rol === 'Directie');

// Zonder geldige rol geen toegang
if (!$magBekijken) {
    die('Je hebt geen toegang tot deze pagina.');
}

// POST-acties verwerken
$melding = verwerkActies($pdo, $magBeheren);

// Actief tabblad
$actieveTab = (getTekst('tab') === 'klanten') ? 'klanten' : 'leveranciers';

// Leveringen ophalen
$leveringenData        = haalLeveringenOp($pdo);
$volgendeLevering      = $leveringenData['volgendeLevering'];
$leveringenKomendeWeek = $leveringenData['leveringenKomendeWeek'];
$binnenWeek            = $leveringenData['binnenWeek'];

// Samenvatting
$samenvatting = haalSamenvattingOp($pdo, $volgendeLevering);
extract($samenvatting);

// Leveranciers
$levData = haalLeveranciersOp($pdo, $volgendeLevering);
extract($levData);

// Klanten
$klantData = haalKlantenOp($pdo, $magKlantgegevensZien);
extract($klantData);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leveranciersbeheer</title>
<link rel="stylesheet" href="style.css">
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

<!-- ===================== SAMENVATTING ===================== -->
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

    <?php if ($magBeheren): ?>
    <div class="kaart">
        <button class="knop knop-primair" onclick="togglePaneel('toevoegenForm')">
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
                        <button class="knop knop-secundair" onclick="togglePaneel('bekijkDetails<?= (int)$l['idLeverancier'] ?>')">
                            Bekijken
                        </button>

                        <?php if ($magBeheren): ?>
                        <button class="knop knop-secundair" onclick="togglePaneel('bewerkForm<?= (int)$l['idLeverancier'] ?>')">
                            Aanpassen
                        </button>

                        <form method="POST" style="display:inline"
                              onsubmit="return confirm(<?= h(json_encode("Weet je zeker dat je '" . $l['Bedrijfsnaam'] . "' wilt verwijderen?")) ?>);">
                            <input type="hidden" name="idLeverancier" value="<?= (int)$l['idLeverancier'] ?>">
                            <button type="submit" name="verwijderen" class="knop knop-gevaar">Verwijderen</button>
                        </form>
                        <?php endif; ?>

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

<script src="script.js"></script>
</body>
</html>