/** Module J — Technische tests: beveiliging, integriteit, prestaties, omgeving en regressie. */
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const OUD = process.env.VB_OUD_URL || 'http://localhost:8001';
const DOCKER = process.env.VB_DOCKER_URL || 'http://localhost:8080';

module.exports = {
  onderdeel: 'J. Technische tests (beveiliging, integriteit, omgeving)',
  cases: [
    {
      id: 'TC-99', functie: 'SQL-injectie wordt tegengehouden', eisen: ['TEC-6'], soort: 'Beveiliging', testvorm: 'Beveiliging (OWASP A03)',
      rol: '— / Vrijwilliger', testdata: `login "' OR '1'='1" ; zoekterm "' OR 'x'='x' -- " ; id "1 OR 1=1"`,
      stappen: ['Log in met een SQL-injectie als gebruikersnaam en wachtwoord', 'Zoek bij de uitgifte op een injectie-string', 'Vraag /api/pakketten/1%20OR%201=1 op'],
      verwacht: 'Inloggen 401; zoeken 404 "Geen cliënt gevonden"; id-injectie geeft geen extra gegevens; database ongewijzigd (prepared statements).',
      async run({ api, cookieVan, db }) {
        const voor = db.waarde('SELECT COUNT(*) FROM Klant');
        const a = await api(null, 'POST', '/auth/login', { gebruikersnaam: "' OR '1'='1", wachtwoord: "' OR '1'='1" });
        const V = await cookieVan('a.degroot');
        const b = await api(V, 'GET', '/uitgifte/zoek?q=' + encodeURIComponent("' OR 'x'='x' -- "));
        const c = await api(V, 'GET', '/pakketten/1%20OR%201=1');
        const na = db.waarde('SELECT COUNT(*) FROM Klant');
        return { ok: a.status === 401 && b.status === 404 && c.status === 404 && voor === na,
          werkelijk: `Login ${a.status}; zoeken ${b.status} "${b.d.fout}"; id-injectie ${c.status}; klanten voor/na ${voor}/${na}.` };
      },
    },
    {
      id: 'TC-100', functie: 'Cross-site scripting (XSS) wordt tegengehouden', eisen: ['TEC-6'], soort: 'Beveiliging', testvorm: 'Beveiliging (OWASP A03)',
      rol: 'Directie', testdata: 'Klant met gezinsnaam <img src=x onerror="window.__xss=1">',
      stappen: ['Maak via de API een klant aan met HTML/JavaScript in de naam', 'Open de klantenpagina'],
      verwacht: 'De naam staat letterlijk als tekst in beeld; er wordt geen script uitgevoerd (window.__xss bestaat niet).',
      async run({ api, cookieVan, pagina, ui, foto }) {
        const naam = '<img src=x onerror="window.__xss=1">';
        await api(await cookieVan('p.abraham'), 'POST', '/klanten', { naam, adres: 'Teststraat 1', postcode: '5492AB', telefoon: '0612345678', email: 'x@y.nl', leden: [{ naam: 'Test', gb: '1990-01-01', relatie: 'Hoofdaanvrager' }] });
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await p.waitForTimeout(500);
        const xss = await p.evaluate(() => window.__xss);
        const tekst = await p.textContent('.tbl');
        return { ok: xss === undefined && tekst.includes(naam), werkelijk: `Script uitgevoerd: ${xss ? 'ja' : 'nee'}; naam als tekst zichtbaar: ${tekst.includes(naam) ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-100') };
      },
    },
    {
      id: 'TC-101', functie: 'Wachtwoorden versleuteld opgeslagen', eisen: ['TEC-6'], soort: 'Beveiliging', testvorm: 'Beveiliging (OWASP A02)',
      rol: '—', testdata: 'Tabel Gebruiker', stappen: ['Bekijk de kolom Wachtwoord_hash in de database'],
      verwacht: 'Alle wachtwoorden zijn bcrypt-hashes ($2y$, 60 tekens); nergens staat "voedselbank" leesbaar; de API stuurt nooit een hash mee.',
      async run({ db, api, cookieVan }) {
        const n = db.waarde('SELECT COUNT(*) FROM Gebruiker');
        const bcrypt = db.waarde("SELECT COUNT(*) FROM Gebruiker WHERE Wachtwoord_hash LIKE '$2y$%' AND LENGTH(Wachtwoord_hash) = 60");
        const leesbaar = db.waarde("SELECT COUNT(*) FROM Gebruiker WHERE Wachtwoord_hash LIKE '%voedselbank%'");
        const lijst = JSON.stringify((await api(await cookieVan('p.abraham'), 'GET', '/gebruikers')).d);
        return { ok: n === bcrypt && leesbaar === '0' && !lijst.includes('$2y$'), werkelijk: `${bcrypt} van ${n} accounts bcrypt; leesbaar: ${leesbaar}; hash in API-antwoord: ${lijst.includes('$2y$') ? 'ja' : 'nee'}.` };
      },
    },
    {
      id: 'TC-102', functie: 'Veilige sessiecookie en nieuwe sessie-ID bij inloggen', eisen: ['TEC-6'], soort: 'Beveiliging', testvorm: 'Beveiliging (OWASP A07)',
      rol: 'Directie', testdata: 'Bestaande (anonieme) sessie, daarna inloggen',
      stappen: ['Vraag /api/auth/me op en bewaar de sessiecookie', 'Log in met diezelfde cookie', 'Vergelijk de sessie-ID\'s en de cookie-instellingen'],
      verwacht: 'Cookie is HttpOnly en SameSite=Strict; na inloggen krijgt de browser een nieuwe sessie-ID (bescherming tegen session fixation); precies één Set-Cookie per login.',
      async run({ BASIS }) {
        const r1 = await fetch(BASIS + '/api/auth/me');
        const oud = r1.headers.getSetCookie()[0].split(';')[0];
        const r2 = await fetch(BASIS + '/api/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: oud }, body: JSON.stringify({ gebruikersnaam: 'p.abraham', wachtwoord: 'voedselbank' }) });
        const sc = r2.headers.getSetCookie();
        const nieuw = (sc[sc.length - 1] || '').split(';')[0];
        const vers = await fetch(BASIS + '/api/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ gebruikersnaam: 'p.abraham', wachtwoord: 'voedselbank' }) });
        const ok = /HttpOnly/i.test(sc[0]) && /SameSite=Strict/i.test(sc[0]) && nieuw && nieuw !== oud && vers.headers.getSetCookie().length === 1;
        return { ok, werkelijk: `Cookie "${sc[0]}"; ID gewijzigd: ${nieuw !== oud ? 'ja' : 'nee'}; Set-Cookie-headers bij verse login: ${vers.headers.getSetCookie().length}.` };
      },
    },
    {
      id: 'TC-103', functie: 'Nette foutafhandeling zonder technische details', eisen: ['TEC-6', 'TEC-1'], soort: 'Negatief', testvorm: 'Technisch (API)',
      rol: 'Directie', testdata: 'Onbekende route, verkeerde HTTP-methode, kapotte JSON',
      stappen: ['GET /api/bestaatniet', 'GET /api/auth/login (moet POST zijn)', 'POST /api/klanten met ongeldige JSON'],
      verwacht: '404 en 405 met een Nederlandse JSON-melding; kapotte JSON geeft 422 (validatie), geen 500; nergens een stacktrace of bestandspad.',
      async run({ api, cookieVan, BASIS }) {
        const D = await cookieVan('p.abraham');
        const a = await api(D, 'GET', '/bestaatniet');
        const b = await api(D, 'GET', '/auth/login');
        const c = await fetch(BASIS + '/api/klanten', { method: 'POST', headers: { 'Content-Type': 'application/json', Cookie: D }, body: '{kapot' });
        const ct = await c.text();
        const lek = [JSON.stringify(a.d), JSON.stringify(b.d), ct].some((t) => /\.php|Stack trace|#0 /.test(t));
        return { ok: a.status === 404 && b.status === 405 && c.status === 422 && !lek, werkelijk: `${a.status} "${a.d.fout}"; ${b.status} "${b.d.fout}"; kapotte JSON ${c.status}; technische details gelekt: ${lek ? 'ja' : 'nee'}.` };
      },
    },
    {
      id: 'TC-104', functie: 'Database bewaakt de historie (foreign keys)', eisen: ['VRD-3', 'KLT-3'], soort: 'Grensgeval', testvorm: 'Technisch (database)',
      rol: '— (rechtstreeks in MySQL)', testdata: 'DELETE van product 3 (in pakketten) en klant 1 (met pakketten)',
      stappen: ['Voer in MySQL "DELETE FROM Product WHERE idProduct = 3" uit', 'Voer "DELETE FROM Klant WHERE idKlant = 1" uit'],
      verwacht: 'Beide worden door de database geweigerd (foreign key constraint), ook als iemand de applicatie omzeilt.',
      async run({ db }) {
        const probeer = (sql) => { try { db.uitvoeren(sql); return 'gelukt'; } catch (e) { return /foreign key/i.test(e.message) ? 'geweigerd (foreign key)' : 'fout: ' + e.message.split('\n')[0]; } };
        const a = probeer('DELETE FROM Product WHERE idProduct = 3');
        const b = probeer('DELETE FROM Klant WHERE idKlant = 1');
        return { ok: a.startsWith('geweigerd') && b.startsWith('geweigerd'), werkelijk: `Product 3: ${a}; klant 1: ${b}.` };
      },
    },
    {
      id: 'TC-105', functie: 'Bruikbaar op een smal scherm (tablet/telefoon)', eisen: ['TEC-1'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Schermbreedte 390 × 844 (telefoon)',
      stappen: ['Open dashboard, pakketten en uitgifte op 390 px breed'],
      verwacht: 'Zijbalk klapt in tot iconen; kolommen staan onder elkaar; de pagina zelf scrolt niet horizontaal.',
      async run({ pagina, ui, foto }) {
        const p = await pagina(null, { viewport: { width: 390, height: 844 } });
        await ui.login(p, 'a.degroot');
        const uit = [];
        let ok = true;
        for (const pad of ['/', '/pakketten', '/uitgifte']) {
          await ui.naar(p, pad);
          const breed = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
          if (breed > 2) ok = false;
          uit.push(`${pad}: ${breed > 2 ? breed + ' px te breed' : 'past'}`);
        }
        const menuTekst = await p.isVisible('.nav a span');
        await ui.naar(p, '/pakketten');
        return { ok: ok && !menuTekst, werkelijk: `${uit.join('; ')}; menutekst verborgen: ${!menuTekst ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-105') };
      },
    },
    {
      id: 'TC-106', functie: 'Toetsenbord en formulierlabels', eisen: ['TEC-1'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Producten: nieuw-product-paneel en bevestigingsvenster',
      stappen: ['Open "+ Nieuw product" en druk Escape', 'Open een verwijdervraag en druk Escape', 'Controleer of elk invoerveld een label heeft'],
      verwacht: 'Escape sluit paneel en venster zonder iets op te slaan; het eerste veld krijgt focus; alle velden in het paneel hebben een gekoppeld label.',
      async run({ pagina, ui }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/producten');
        await p.click('text=+ Nieuw product'); await p.waitForTimeout(400);
        const focus = await p.evaluate(() => document.activeElement?.id);
        const zonderLabel = await p.$$eval('.drawer.open input:not([type=hidden]), .drawer.open select', (e) => e.filter((x) => x.id && !document.querySelector(`label[for="${x.id}"]`)).length);
        await p.keyboard.press('Escape'); await p.waitForTimeout(300);
        const dicht = !(await p.$('.drawer.open'));
        await p.click('table.lijst tbody tr >> nth=0 >> button[title=Verwijderen]');
        await p.keyboard.press('Escape'); await p.waitForTimeout(250);
        const vraagWeg = !(await p.$('.modal'));
        return { ok: focus === 'p-naam' && zonderLabel === 0 && dicht && vraagWeg, werkelijk: `Focus op "${focus}"; velden zonder label: ${zonderLabel}; paneel dicht na Escape: ${dicht}; venster dicht na Escape: ${vraagWeg}.` };
      },
    },
    {
      id: 'TC-107', functie: 'Snelheid van pagina\'s en API', eisen: ['TEC-3'], soort: 'Positief', testvorm: 'Prestatie',
      rol: 'Directie', testdata: '10 API-overzichten, 3 pagina\'s',
      stappen: ['Meet de responstijd van de belangrijkste API-verzoeken', 'Meet de laadtijd van dashboard, producten en klanten'],
      verwacht: 'Elk API-verzoek binnen 500 ms; elke pagina binnen 2 seconden volledig geladen.',
      async run({ api, cookieVan, pagina, ui }) {
        const D = await cookieVan('p.abraham');
        const tijden = [];
        for (const pad of ['/dashboard', '/klanten', '/producten', '/leveranciers', '/pakketten/samenstellen', '/uitgifte/wachtrij', '/rapportages', '/gebruikers', '/categorieen', '/pakketten']) {
          const t = Date.now(); await api(D, 'GET', pad); tijden.push([pad, Date.now() - t]);
        }
        const p = await pagina('p.abraham');
        const pag = [];
        for (const pad of ['/', '/producten', '/klanten']) { const t = Date.now(); await ui.naar(p, pad); pag.push([pad, Date.now() - t]); }
        const maxApi = Math.max(...tijden.map((x) => x[1])); const maxPag = Math.max(...pag.map((x) => x[1]));
        return { ok: maxApi < 500 && maxPag < 2000, werkelijk: `Traagste API ${maxApi} ms (${tijden.find((x) => x[1] === maxApi)[0]}); pagina's: ${pag.map((x) => `${x[0]} ${x[1]} ms`).join(', ')}.` };
      },
    },
    {
      id: 'TC-108', functie: 'Regressie: nieuwe code geeft dezelfde gegevens als de oude', eisen: ['TEC-3'], soort: 'Regressie', testvorm: 'Regressie (API)',
      rol: 'Directie', testdata: '17 lees-endpoints, oude versie (19 okt) en nieuwe versie op dezelfde database',
      stappen: ['Start de oude en de nieuwe versie op dezelfde database', 'Vraag in beide alle overzichten op', 'Vergelijk de antwoorden veld voor veld'],
      verwacht: 'Alle antwoorden zijn gelijk, op drie bewuste verschillen na: links in het dashboard (nieuwe adressen), het nieuwe veld thtDagen bij het samenstellen en de volgorde van de paginalijst (nu gelijk aan het menu).',
      async run({ BASIS }) {
        const login = async (b, pad) => {
          const r = await fetch(b + pad, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ gebruikersnaam: 'p.abraham', wachtwoord: 'voedselbank' }) });
          const k = {}; r.headers.getSetCookie().forEach((c) => { const [n, w] = c.split(';')[0].split('='); k[n] = w; });
          return Object.entries(k).map(([n, w]) => `${n}=${w}`).join('; ');
        };
        const co = await login(OUD, '/api/auth.php?actie=login');
        const cn = await login(BASIS, '/api/auth/login');
        // bewuste verschillen: nieuwe links, extra veld thtDagen, paginalijst in menuvolgorde
        const opschonen = (x) => JSON.parse(JSON.stringify(x, (k, v) => (k === 'link' || k === 'thtDagen' ? undefined : k === 'paginas' ? [...v].sort()
          // bij gelijke aantallen sorteert de nieuwe versie ook op naam (bevinding B-03); vergelijk daarom de aantallen
          : k === 'topProducten' ? v.map((x) => x.aantal) : v)));
        const paren = [['klanten.php?actie=lijst', 'klanten'], ['klanten.php?actie=wensen', 'wensen'], ['producten.php?actie=lijst', 'producten'],
          ['producten.php?actie=categorieen', 'categorieen'], ['leveranciers.php?actie=lijst', 'leveranciers'], ['pakketten.php?actie=lijst', 'pakketten'],
          ['pakketten.php?actie=start', 'pakketten/samenstellen'], ['pakketten.php?actie=klant&id=1', 'pakketten/klant/1'], ['pakketten.php?actie=pakbon&id=2862', 'pakketten/2862'],
          ['uitgifte.php?actie=wachtrij', 'uitgifte/wachtrij'], ['uitgifte.php?actie=zoek&q=K-002', 'uitgifte/zoek?q=K-002'], ['rapportages.php?actie=overzicht', 'rapportages'],
          ['rapportages.php?actie=maand&soort=categorie', 'rapportages/maand?soort=categorie'], ['rapportages.php?actie=maand&soort=postcode', 'rapportages/maand?soort=postcode'],
          ['gebruikers.php?actie=lijst', 'gebruikers'], ['dashboard.php', 'dashboard'], ['auth.php?actie=rechten', 'auth/rechten']];
        const verschil = [];
        for (const [o, n] of paren) {
          const a = await (await fetch(OUD + '/api/' + o, { headers: { Cookie: co } })).json();
          const b = await (await fetch(BASIS + '/api/' + n, { headers: { Cookie: cn } })).json();
          if (JSON.stringify(opschonen(a)) !== JSON.stringify(opschonen(b))) verschil.push(n);
        }
        return { ok: verschil.length === 0, werkelijk: verschil.length ? `Verschillen bij: ${verschil.join(', ')}` : `${paren.length} van ${paren.length} endpoints geven identieke gegevens (links, thtDagen en volgorde paginalijst buiten beschouwing).` };
      },
    },
    {
      id: 'TC-109', functie: 'Tijdzone: "vandaag" en "deze week" in Nederlandse tijd', eisen: ['WRK-2'], soort: 'Grensgeval', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger', testdata: 'Weekstart volgens de server vs. maandag volgens Europe/Amsterdam',
      stappen: ['Vraag /api/pakketten/samenstellen op', 'Vergelijk "weekstart" met de maandag van deze week in Nederlandse tijd'],
      verwacht: 'Dezelfde datum; de database rekent met dezelfde tijdzone (CURDATE gelijk aan de Nederlandse datum).',
      async run({ api, cookieVan, db }) {
        const r = await api(await cookieVan('a.degroot'), 'GET', '/pakketten/samenstellen');
        const nl = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/Amsterdam' }));
        const ma = new Date(nl); ma.setDate(nl.getDate() - ((nl.getDay() + 6) % 7));
        const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        return { ok: r.d.weekstart === iso(ma), werkelijk: `Server weekstart ${r.d.weekstart}; maandag in Nederland ${iso(ma)}; vandaag NL ${iso(nl)}.` };
      },
    },
    {
      id: 'TC-110', functie: 'Installatie met Docker en testdata', eisen: ['TEC-5', 'TEC-7', 'TEC-8'], soort: 'Positief', testvorm: 'Installatie / omgeving',
      rol: '—', testdata: 'docker compose up -d --build (web, db, phpmyadmin)',
      stappen: ['Start de omgeving met docker compose', 'Controleer de containers en of de testdata is geladen', 'Open de app, een directe pagina-URL, de API en phpMyAdmin', 'Draai tests/api-test.js tegen de container'],
      verwacht: 'Drie containers draaien, database "healthy" en gevuld met de testdata (11 gezinnen, 20 producten, 5 accounts); app 200, /klanten 200 (Vue-router), API werkt, phpMyAdmin 200; regressietest 28/28 geslaagd.',
      async run() {
        const ps = execFileSync('docker', ['ps', '--format', '{{.Names}}={{.Status}}'], { encoding: 'utf8' }).trim().split('\n').filter((x) => x.startsWith('vb_'));
        const st = async (u) => (await fetch(u)).status;
        const tel = (sql) => execFileSync('docker', ['exec', 'vb_db', 'mysql', '-uvbuser', '-pvbpass', '-N', '-B', 'voedselbank', '-e', sql], { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] }).trim();
        const data = { klanten: tel('SELECT COUNT(*) FROM Klant'), producten: tel('SELECT COUNT(*) FROM Product'), accounts: tel('SELECT COUNT(*) FROM Gebruiker') };
        const s = { app: await st(DOCKER + '/'), diep: await st(DOCKER + '/klanten'), api: await st(DOCKER + '/api/auth/me'), pma: await st('http://localhost:8081/') };
        let reg = '';
        try {
          reg = execFileSync('node', [path.join(__dirname, '..', '..', 'api-test.js')], { env: { ...process.env, VB_URL: DOCKER }, encoding: 'utf8' }).trim().split('\n').pop();
        } catch (e) { reg = (e.stdout || '').trim().split('\n').pop(); }
        const ok = ps.length === 3 && ps.some((x) => x.includes('healthy')) && Object.values(s).every((x) => x === 200) && /28 geslaagd, 0 mislukt/.test(reg)
          && Number(data.klanten) >= 11 && Number(data.producten) >= 20 && Number(data.accounts) >= 5;
        return { ok, werkelijk: `Containers: ${ps.join(', ')}; testdata: ${data.klanten} klanten, ${data.producten} producten, ${data.accounts} accounts; app ${s.app}, /klanten ${s.diep}, API ${s.api}, phpMyAdmin ${s.pma}; regressietest: ${reg}.` };
      },
    },
  ],
};
