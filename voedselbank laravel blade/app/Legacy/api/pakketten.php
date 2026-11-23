<?php
declare(strict_types=1);
/**
 * Voedselpakketten samenstellen — US-PAK-01 t/m US-PAK-12.
 *
 *   GET  ?actie=start        alles wat het samenstelscherm nodig heeft
 *   GET  ?actie=klant&id=    gezin, wensen, advies en conflicten voor één klant
 *   GET  ?actie=lijst        overzicht van pakketten
 *   GET  ?actie=pakket&id=   één pakket met inhoud
 *   GET  ?actie=pakbon&id=   gegevens voor de sticker / pakbon (US-PAK-10/11)
 *   POST ?actie=opslaan      pakket opslaan als Concept of Klaar
 *   POST ?actie=verwijderen  concept verwijderen (voorraad komt terug)
 */

$actie = $_GET['actie'] ?? 'start';

/** Maandag van de huidige week (de weekcyclus, US-WRK-01). */
function week_start(): string
{
    return (new DateTimeImmutable('monday this week'))->format('Y-m-d');
}

/**
 * Alle dieetwensen van een gezin, op conflict-tag.
 * Per tag: welke wens het is, of hij streng is, en om wie het gaat.
 *
 *   ['noten' => ['wens' => 'Notenallergie', 'streng' => true,
 *                'personen' => ['Bart Bakker'], 'gezinsgrootte' => 5]]
 */
function gezin_wensen(int $klantId): array
{
    $q = db()->prepare("
        SELECT w.Conflict_tag, w.Naam AS Wens, w.Streng, g.Naam AS Lid,
               gw.Toch_toestaan, gw.Toelichting
          FROM Gezinslid g
          JOIN Gezinslid_has_Wens gw ON gw.Gezinslid_idGezinslid = g.idGezinslid
          JOIN Wens w ON w.idWens = gw.Wens_idWens
         WHERE g.Klant_idKlant = ? AND w.Conflict_tag IS NOT NULL
         ORDER BY w.Streng DESC, w.Naam, g.Geboortedatum
    ");
    $q->execute([$klantId]);

    $g = db()->prepare("SELECT COUNT(*) FROM Gezinslid WHERE Klant_idKlant = ?");
    $g->execute([$klantId]);
    $grootte = (int) $g->fetchColumn();

    $uit = [];
    foreach ($q->fetchAll() as $r) {
        $tag = (string) $r['Conflict_tag'];
        if (!isset($uit[$tag])) {
            $uit[$tag] = [
                'tag'           => $tag,
                'wens'          => $r['Wens'],
                'streng'        => (bool) $r['Streng'],
                'personen'      => [],
                'gezinsgrootte' => $grootte,
                'uitzondering'  => false,
                'toelichting'   => null,
            ];
        }
        $uit[$tag]['personen'][] = $r['Lid'];
        // twee wensen op dezelfde tag: de strengste wint
        if ((bool) $r['Streng']) { $uit[$tag]['streng'] = true; }

        // Het gezin heeft laten weten dat het product toch welkom is.
        // Dat telt alleen als er ook een reden bij staat.
        if ((int) $r['Toch_toestaan'] === 1 && trim((string) $r['Toelichting']) !== '') {
            $uit[$tag]['uitzondering'] = true;
            $uit[$tag]['toelichting']  = $r['Toelichting'];
        }
    }

    // Een uitzondering haalt de harde blokkade weg, maar de waarschuwing blijft.
    foreach ($uit as $tag => $w) {
        if ($w['uitzondering']) { $uit[$tag]['streng'] = false; }
    }
    return $uit;
}

/**
 * Welke dieetwensen van het gezin botsen met dit product?
 * Geeft per botsing terug wie het betreft en of hij streng is.
 */
function botsingen(?string $allergenen, array $wensen): array
{
    if (!$allergenen || !$wensen) { return []; }

    $uit = [];
    foreach (array_map('trim', explode(',', $allergenen)) as $tag) {
        if (isset($wensen[$tag])) { $uit[] = $wensen[$tag]; }
    }
    return $uit;
}

/** Is dit product over de datum? */
function verlopen(?string $tht): bool
{
    return $tht !== null && $tht < date('Y-m-d');
}

/** Adviesaantal producten op basis van gezinsgrootte (US-PAK-04). */
function advies(int $gezinsgrootte): int
{
    return max(8, 8 + ($gezinsgrootte - 1) * 4);
}

/* ------------------------------------------------------------------ */
if ($actie === 'start') {
    vereis_permissie('pakket_beheren');
    $maandag = week_start();

    // klantenlijst met de vlag "heeft deze week al een pakket" (US-PAK-06)
    $q = db()->prepare("
        SELECT k.idKlant, k.Gezinsnaam, k.Geaccepteerd,
               (SELECT COUNT(*) FROM Gezinslid g WHERE g.Klant_idKlant = k.idKlant) AS Gezinsgrootte,
               (SELECT COUNT(*) FROM Pakket p
                 WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?) AS Deze_week,
               (SELECT p.Status FROM Pakket p
                 WHERE p.Klant_idKlant = k.idKlant AND p.Samenstellingsdatum >= ?
                 ORDER BY p.idPakket DESC LIMIT 1) AS Week_status,
               (SELECT MAX(p.Uitgiftedatum) FROM Pakket p
                 WHERE p.Klant_idKlant = k.idKlant AND p.Status = 'Uitgegeven') AS Laatste
          FROM Klant k
         WHERE k.Geaccepteerd = 1
         ORDER BY k.Gezinsnaam
    ");
    $q->execute([$maandag, $maandag]);

    $klanten = array_map(static fn(array $k): array => [
        'id'            => (int) $k['idKlant'],
        'naam'          => $k['Gezinsnaam'],
        'gezinsgrootte' => (int) $k['Gezinsgrootte'],
        'dezeWeek'      => (int) $k['Deze_week'] > 0,
        'weekStatus'    => $k['Week_status'],
        'laatste'       => $k['Laatste'],
        'advies'        => advies((int) $k['Gezinsgrootte']),
    ], $q->fetchAll());

    $producten = db()->query("
        SELECT p.idProduct, p.Productnaam, p.Aantal_voorraad, p.Houdbaar_tot,
               p.Allergenen, p.Locatie, c.Naam AS Categorie, c.idCategorie
          FROM Product p JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie
         ORDER BY c.Naam, p.Productnaam
    ")->fetchAll();

    json_out([
        'weekstart' => $maandag,
        'klanten'   => $klanten,
        'producten' => array_map(static fn(array $p): array => [
            'id'         => (int) $p['idProduct'],
            'naam'       => $p['Productnaam'],
            'voorraad'   => (int) $p['Aantal_voorraad'],
            'tht'        => $p['Houdbaar_tot'],
            'verlopen'   => verlopen($p['Houdbaar_tot']),
            'allergenen' => $p['Allergenen'],
            'locatie'    => $p['Locatie'],
            'categorie'  => $p['Categorie'],
            'categorieId'=> (int) $p['idCategorie'],
        ], $producten),
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'klant') {                          // US-PAK-02 / US-PAK-03
    vereis_permissie('pakket_beheren');
    $id = (int) ($_GET['id'] ?? 0);

    $q = db()->prepare("SELECT idKlant, Gezinsnaam, Geaccepteerd, Opmerkingen FROM Klant WHERE idKlant = ?");
    $q->execute([$id]);
    $klant = $q->fetch();
    if (!$klant) {
        json_out(['fout' => 'Klant niet gevonden.'], 404);
    }

    $l = db()->prepare("SELECT * FROM vw_Gezinslid WHERE Klant_idKlant = ? ORDER BY Geboortedatum");
    $l->execute([$id]);
    $leden = $l->fetchAll();

    $w = db()->prepare("
        SELECT gw.Gezinslid_idGezinslid AS lid, w.Naam, w.Conflict_tag
          FROM Gezinslid_has_Wens gw
          JOIN Wens w ON w.idWens = gw.Wens_idWens
          JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid
         WHERE g.Klant_idKlant = ?
    ");
    $w->execute([$id]);

    $perLid = [];
    foreach ($w->fetchAll() as $r) {
        $perLid[(int) $r['lid']][] = $r['Naam'];
    }

    $maandag = week_start();
    $p = db()->prepare("
        SELECT idPakket, Status, Samenstellingsdatum FROM Pakket
         WHERE Klant_idKlant = ? AND Samenstellingsdatum >= ?
         ORDER BY idPakket DESC LIMIT 1
    ");
    $p->execute([$id, $maandag]);
    $lopend = $p->fetch();

    json_out([
        'klant' => [
            'id'          => (int) $klant['idKlant'],
            'naam'        => $klant['Gezinsnaam'],
            'opmerkingen' => $klant['Opmerkingen'],
        ],
        'gezin' => array_map(static fn(array $g): array => [
            'id'       => (int) $g['idGezinslid'],
            'naam'     => $g['Naam'],
            'leeftijd' => (int) $g['Leeftijd'],
            'type'     => $g['Type'],
            'relatie'  => $g['Relatie'],
            'wensen'   => $perLid[(int) $g['idGezinslid']] ?? [],
        ], $leden),
        'wensen'  => array_values(gezin_wensen($id)),
        'advies'  => advies(count($leden)),
        'lopend'  => $lopend ? [
            'id'     => (int) $lopend['idPakket'],
            'status' => $lopend['Status'],
            'datum'  => $lopend['Samenstellingsdatum'],
        ] : null,
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'lijst') {
    vereis_permissie('pakket_beheren');

    $rijen = db()->query("
        SELECT p.idPakket, p.Status, p.Samenstellingsdatum, p.Uitgiftedatum, p.Opgehaald,
               k.Gezinsnaam, k.idKlant,
               s.Naam AS Samensteller, u.Naam AS Uitgever,
               COALESCE(SUM(pp.Aantal), 0) AS Aantal_producten
          FROM Pakket p
          JOIN Klant k ON k.idKlant = p.Klant_idKlant
          LEFT JOIN Gebruiker s ON s.idGebruiker = p.Samengesteld_door
          LEFT JOIN Gebruiker u ON u.idGebruiker = p.Uitgegeven_door
          LEFT JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = p.idPakket
         GROUP BY p.idPakket
         ORDER BY p.Samenstellingsdatum DESC, p.idPakket DESC
    ")->fetchAll();

    json_out(['pakketten' => array_map(static fn(array $r): array => [
        'id'        => (int) $r['idPakket'],
        'klantId'   => (int) $r['idKlant'],
        'klant'     => $r['Gezinsnaam'],
        'status'    => $r['Status'],
        'datum'     => $r['Samenstellingsdatum'],
        'uitgifte'  => $r['Uitgiftedatum'],
        'opgehaald' => (bool) $r['Opgehaald'],
        'aantal'    => (int) $r['Aantal_producten'],
        'door'      => $r['Samensteller'],
        'uitgever'  => $r['Uitgever'],
    ], $rijen)]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'pakket' || $actie === 'pakbon') {
    vereis_login();
    $id = (int) ($_GET['id'] ?? 0);

    $q = db()->prepare("
        SELECT p.*, k.Gezinsnaam, k.idKlant, k.Opmerkingen,
               s.Naam AS Samensteller, u.Naam AS Uitgever
          FROM Pakket p
          JOIN Klant k ON k.idKlant = p.Klant_idKlant
          LEFT JOIN Gebruiker s ON s.idGebruiker = p.Samengesteld_door
          LEFT JOIN Gebruiker u ON u.idGebruiker = p.Uitgegeven_door
         WHERE p.idPakket = ?
    ");
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) {
        json_out(['fout' => 'Pakket niet gevonden.'], 404);
    }

    $i = db()->prepare("
        SELECT pp.Aantal, pr.idProduct, pr.Productnaam, pr.Allergenen, pr.Locatie,
               c.Naam AS Categorie
          FROM Product_has_Pakket pp
          JOIN Product pr ON pr.idProduct = pp.Product_idProduct
          JOIN Categorie c ON c.idCategorie = pr.Categorie_idCategorie
         WHERE pp.Pakket_idPakket = ?
         ORDER BY c.Naam, pr.Productnaam
    ");
    $i->execute([$id]);
    $inhoud = $i->fetchAll();

    $wensen = gezin_wensen((int) $p['idKlant']);

    $g = db()->prepare("SELECT Naam, Leeftijd, Type FROM vw_Gezinslid WHERE Klant_idKlant = ? ORDER BY Geboortedatum");
    $g->execute([(int) $p['idKlant']]);

    $w = db()->prepare("
        SELECT DISTINCT w.Naam FROM Gezinslid_has_Wens gw
          JOIN Wens w ON w.idWens = gw.Wens_idWens
          JOIN Gezinslid gl ON gl.idGezinslid = gw.Gezinslid_idGezinslid
         WHERE gl.Klant_idKlant = ?
    ");
    $w->execute([(int) $p['idKlant']]);

    json_out([
        'pakket' => [
            'id'         => (int) $p['idPakket'],
            'klantId'    => (int) $p['idKlant'],
            'klant'      => $p['Gezinsnaam'],
            'status'     => $p['Status'],
            'datum'      => $p['Samenstellingsdatum'],
            'uitgifte'   => $p['Uitgiftedatum'],
            'opgehaald'  => (bool) $p['Opgehaald'],
            'gecontroleerd' => (bool) $p['Identiteit_gecontroleerd'],
            'legitimatie'=> $p['Legitimatie'],
            'door'       => $p['Samensteller'],
            'uitgever'   => $p['Uitgever'],
            'opmerkingen'=> $p['Opmerkingen'],
        ],
        // Vastgelegde uitzonderingen: waarom een geblokkeerd product toch mee mag.
        // Staat op de pakbon zodat de uitgifte het kan nalezen.
        'uitzonderingen' => array_values(array_map(
            static fn(array $w): array => ['wens' => $w['wens'], 'toelichting' => $w['toelichting']],
            array_filter($wensen, static fn(array $w): bool => $w['uitzondering'])
        )),
        'inhoud' => array_map(static fn(array $r): array => [
            'productId' => (int) $r['idProduct'],
            'naam'      => $r['Productnaam'],
            'aantal'    => (int) $r['Aantal'],
            'categorie' => $r['Categorie'],
            'locatie'   => $r['Locatie'],
            'conflict'  => botsingen($r['Allergenen'], $wensen),
        ], $inhoud),
        'gezin'  => $g->fetchAll(),
        'wensen' => $w->fetchAll(PDO::FETCH_COLUMN),
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'opslaan') {                        // US-PAK-05 / US-PAK-07 / US-PAK-08
    alleen_post();
    $gebruiker = vereis_permissie('pakket_beheren');

    $in      = json_in();
    $id      = (int) ($in['id']      ?? 0);
    $klantId = (int) ($in['klant']   ?? 0);
    $status  = (string) ($in['status'] ?? 'Concept');
    $regels  = is_array($in['regels'] ?? null) ? $in['regels'] : [];
    $forceer = (bool) ($in['forceer'] ?? false);

    if (!in_array($status, ['Concept', 'Klaar'], true)) {
        json_out(['fout' => 'Ongeldige status.'], 422);
    }

    $fouten = [];
    if ($klantId <= 0) { $fouten['klant'] = 'Kies een klant.'; }
    if (!$regels && $status === 'Klaar') {
        $fouten['regels'] = 'Een pakket dat klaar is moet minimaal één product bevatten.';
    }
    if ($fouten) {
        json_out(['fouten' => $fouten], 422);
    }

    // klant moet geaccepteerd zijn (US-KLT-08)
    $q = db()->prepare("SELECT Geaccepteerd, Gezinsnaam FROM Klant WHERE idKlant = ?");
    $q->execute([$klantId]);
    $klant = $q->fetch();
    if (!$klant) {
        json_out(['fout' => 'Klant niet gevonden.'], 404);
    }
    if ((int) $klant['Geaccepteerd'] !== 1) {
        json_out(['fout' => 'Deze klant is nog niet geaccepteerd en mag geen pakket krijgen.'], 422);
    }

    // uitgegeven pakketten zijn historie en worden nooit meer aangepast
    if ($id > 0) {
        $q = db()->prepare("SELECT Status FROM Pakket WHERE idPakket = ?");
        $q->execute([$id]);
        $huidig = $q->fetchColumn();
        if ($huidig === false) {
            json_out(['fout' => 'Pakket niet gevonden.'], 404);
        }
        if ($huidig === 'Uitgegeven') {
            json_out(['fout' => 'Dit pakket is al uitgegeven en kan niet meer worden gewijzigd.'], 409);
        }
    }

    // één pakket per klant per week (US-PAK-06)
    $maandag = week_start();
    $q = db()->prepare("
        SELECT idPakket FROM Pakket
         WHERE Klant_idKlant = ? AND Samenstellingsdatum >= ? AND idPakket <> ?
    ");
    $q->execute([$klantId, $maandag, $id]);
    if ($q->fetch()) {
        json_out([
            'fout'  => $klant['Gezinsnaam'] . ' heeft deze week al een pakket.',
            'reden' => 'dubbel_deze_week',
        ], 409);
    }

    // regels opschonen en optellen
    $gewenst = [];
    foreach ($regels as $r) {
        $pid = (int) ($r['product'] ?? 0);
        $aan = (int) ($r['aantal']  ?? 0);
        if ($pid > 0 && $aan > 0) {
            $gewenst[$pid] = ($gewenst[$pid] ?? 0) + $aan;
        }
    }

    // Controle op dieetwensen en houdbaarheid (US-PAK-03, US-VRD-08).
    // Drie uitkomsten per product:
    //   - over de datum          -> nooit toegestaan
    //   - strenge allergie       -> nooit toegestaan, ook niet met forceer
    //   - overige dieetwens      -> mag wel, maar alleen na bevestiging
    $wensen     = gezin_wensen($klantId);
    $geblokkeerd = [];     // kan echt niet
    $waarschuwing = [];    // mag met bevestiging

    if ($gewenst) {
        $in_ = implode(',', array_fill(0, count($gewenst), '?'));
        $c = db()->prepare("
            SELECT idProduct, Productnaam, Allergenen, Houdbaar_tot
              FROM Product WHERE idProduct IN ($in_)
        ");
        $c->execute(array_keys($gewenst));

        foreach ($c->fetchAll() as $p) {
            if (verlopen($p['Houdbaar_tot'])) {
                $geblokkeerd[] = [
                    'product' => $p['Productnaam'],
                    'soort'   => 'verlopen',
                    'uitleg'  => 'is over de houdbaarheidsdatum (' . $p['Houdbaar_tot'] . ')',
                ];
                continue;
            }

            foreach (botsingen($p['Allergenen'], $wensen) as $b) {
                $wie = count($b['personen']) . ' van de ' . $b['gezinsgrootte']
                     . ' (' . implode(', ', $b['personen']) . ')';

                if ($b['streng']) {
                    $geblokkeerd[] = [
                        'product' => $p['Productnaam'],
                        'soort'   => 'allergie',
                        'wens'    => $b['wens'],
                        'uitleg'  => 'botst met ' . $b['wens'] . ' van ' . $wie,
                    ];
                } else {
                    $waarschuwing[] = [
                        'product'     => $p['Productnaam'],
                        'wens'        => $b['wens'],
                        'uitleg'      => $b['wens'] . ' geldt voor ' . $wie
                                       . ($b['uitzondering'] ? ' \u2014 het gezin wil dit toch ontvangen' : ''),
                        'toelichting' => $b['toelichting'],
                    ];
                }
            }
        }
    }

    // Een ernstige allergie of een verlopen product kan de vrijwilliger niet
    // wegklikken: dat product hoort simpelweg niet in dit pakket.
    if ($geblokkeerd) {
        json_out([
            'fout'        => 'Een of meer producten mogen niet in dit pakket.',
            'reden'       => 'geblokkeerd',
            'geblokkeerd' => $geblokkeerd,
        ], 409);
    }

    if ($waarschuwing && !$forceer) {
        json_out([
            'fout'       => 'Een of meer producten passen niet bij de wensen van dit gezin.',
            'reden'      => 'conflict',
            'conflicten' => $waarschuwing,
        ], 409);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // huidige inhoud (om het verschil in de voorraad te verwerken)
        $oud = [];
        if ($id > 0) {
            $o = $pdo->prepare("SELECT Product_idProduct, Aantal FROM Product_has_Pakket WHERE Pakket_idPakket = ?");
            $o->execute([$id]);
            foreach ($o->fetchAll() as $r) {
                $oud[(int) $r['Product_idProduct']] = (int) $r['Aantal'];
            }
        } else {
            $pdo->prepare("
                INSERT INTO Pakket (Klant_idKlant, Samenstellingsdatum, Status, Samengesteld_door)
                VALUES (?, CURDATE(), ?, ?)
            ")->execute([$klantId, $status, $gebruiker['idGebruiker']]);
            $id = (int) $pdo->lastInsertId();
        }

        // voorraad controleren op het verschil (US-PAK-07)
        $tekort = [];
        foreach ($gewenst as $pid => $aantal) {
            $delta = $aantal - ($oud[$pid] ?? 0);
            if ($delta <= 0) { continue; }
            $v = $pdo->prepare("SELECT Productnaam, Aantal_voorraad FROM Product WHERE idProduct = ? FOR UPDATE");
            $v->execute([$pid]);
            $p = $v->fetch();
            if (!$p || (int) $p['Aantal_voorraad'] < $delta) {
                $tekort[] = [
                    'product'  => $p['Productnaam'] ?? ('#' . $pid),
                    'gevraagd' => $delta,
                    'voorraad' => (int) ($p['Aantal_voorraad'] ?? 0),
                ];
            }
        }
        if ($tekort) {
            $pdo->rollBack();
            json_out([
                'fout'   => 'Er is niet genoeg voorraad voor dit pakket.',
                'reden'  => 'te_weinig_voorraad',
                'tekort' => $tekort,
            ], 409);
        }

        // oude inhoud terug in de voorraad, nieuwe eraf
        $bij = $pdo->prepare("UPDATE Product SET Aantal_voorraad = Aantal_voorraad + ? WHERE idProduct = ?");
        foreach ($oud as $pid => $aantal) { $bij->execute([$aantal, $pid]); }

        $pdo->prepare("DELETE FROM Product_has_Pakket WHERE Pakket_idPakket = ?")->execute([$id]);

        $ins = $pdo->prepare("INSERT INTO Product_has_Pakket (Pakket_idPakket, Product_idProduct, Aantal) VALUES (?,?,?)");
        $af  = $pdo->prepare("UPDATE Product SET Aantal_voorraad = Aantal_voorraad - ? WHERE idProduct = ?");
        foreach ($gewenst as $pid => $aantal) {
            $ins->execute([$id, $pid, $aantal]);
            $af->execute([$aantal, $pid]);            // voorraad automatisch af (US-PAK-08)
        }

        $pdo->prepare("UPDATE Pakket SET Klant_idKlant = ?, Status = ? WHERE idPakket = ?")
            ->execute([$klantId, $status, $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    json_out([
        'ok'      => true,
        'id'      => $id,
        'melding' => $status === 'Klaar'
            ? 'Pakket is klaargezet voor ' . $klant['Gezinsnaam'] . '.'
            : 'Concept opgeslagen.',
    ]);
}

/* ------------------------------------------------------------------ */
if ($actie === 'verwijderen') {
    alleen_post();
    vereis_permissie('pakket_beheren');

    $id = (int) (json_in()['id'] ?? 0);

    $q = db()->prepare("SELECT Status FROM Pakket WHERE idPakket = ?");
    $q->execute([$id]);
    $status = $q->fetchColumn();

    if ($status === false) {
        json_out(['fout' => 'Pakket niet gevonden.'], 404);
    }
    // Historie beschermen: uitgegeven pakketten blijven bestaan.
    if ($status === 'Uitgegeven') {
        json_out([
            'fout'  => 'Dit pakket is al uitgegeven. Uitgegeven pakketten blijven bewaard voor de rapportages.',
            'reden' => 'is_historie',
        ], 409);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = $pdo->prepare("SELECT Product_idProduct, Aantal FROM Product_has_Pakket WHERE Pakket_idPakket = ?");
        $o->execute([$id]);
        $bij = $pdo->prepare("UPDATE Product SET Aantal_voorraad = Aantal_voorraad + ? WHERE idProduct = ?");
        foreach ($o->fetchAll() as $r) {
            $bij->execute([(int) $r['Aantal'], (int) $r['Product_idProduct']]);
        }
        $pdo->prepare("DELETE FROM Pakket WHERE idPakket = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_out(['ok' => true, 'melding' => 'Pakket verwijderd, de producten staan weer in de voorraad.']);
}

json_out(['fout' => 'Onbekende actie.'], 400);
