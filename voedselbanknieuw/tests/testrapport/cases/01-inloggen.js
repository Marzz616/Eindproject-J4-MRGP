/** Module A — Inloggen, sessie, rechten en eigen account. */
module.exports = {
  onderdeel: 'A. Inloggen, rechten en eigen account',
  cases: [
    {
      id: 'TC-01', functie: 'Inloggen met gebruikersnaam', eisen: ['GBR-1', 'TEC-6'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'p.abraham / voedselbank',
      stappen: ['Open /login', 'Vul gebruikersnaam p.abraham en wachtwoord voedselbank in', 'Klik op Inloggen'],
      verwacht: 'Dashboard opent; naam en rol staan linksonder in de zijbalk.',
      async run({ pagina, foto }) {
        const p = await pagina('p.abraham');
        const h1 = await p.textContent('h1');
        const naam = await p.textContent('.gebruiker .naam');
        return { ok: h1 === 'Dashboard' && naam.includes('Peter Abraham'), werkelijk: `Pagina "${h1}" geopend, zijbalk toont "${naam}".`, screenshot: await foto(p, 'TC-01') };
      },
    },
    {
      id: 'TC-02', functie: 'Inloggen met e-mailadres', eisen: ['GBR-1'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'p.abraham@voedselbank.nl / voedselbank',
      stappen: ['Open /login', 'Vul het e-mailadres in plaats van de gebruikersnaam in', 'Klik op Inloggen'],
      verwacht: 'Inloggen lukt ook met het e-mailadres.',
      async run({ pagina }) {
        const p = await pagina('p.abraham@voedselbank.nl');
        return { ok: (await p.textContent('h1')) === 'Dashboard', werkelijk: 'Ingelogd met e-mailadres, dashboard geopend.' };
      },
    },
    {
      id: 'TC-03', functie: 'Inloggen met fout wachtwoord', eisen: ['TEC-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: '—', testdata: 'p.abraham / fout123',
      stappen: ['Open /login', 'Vul p.abraham en een fout wachtwoord in', 'Klik op Inloggen'],
      verwacht: 'Melding "Gebruikersnaam of wachtwoord is onjuist."; je blijft op het inlogscherm; wachtwoordveld wordt leeggemaakt.',
      async run({ pagina, BASIS, foto }) {
        const p = await pagina();
        await p.goto(BASIS + '/login');
        await p.fill('#gebruikersnaam', 'p.abraham'); await p.fill('#wachtwoord', 'fout123'); await p.click('button[type=submit]');
        const m = (await (await p.waitForSelector('.melding.rood')).textContent()).trim();
        const pw = await p.inputValue('#wachtwoord');
        return { ok: m === 'Gebruikersnaam of wachtwoord is onjuist.' && pw === '' && p.url().includes('/login'),
          werkelijk: `Melding "${m}", wachtwoordveld leeg: ${pw === '' ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-03') };
      },
    },
    {
      id: 'TC-04', functie: 'Inloggen met onbekende gebruiker', eisen: ['TEC-6'], soort: 'Negatief', testvorm: 'Technisch (API)',
      rol: '—', testdata: 'bestaatniet / voedselbank',
      stappen: ['POST /api/auth/login met een onbekende gebruikersnaam'],
      verwacht: 'Status 401 met exact dezelfde melding als bij een fout wachtwoord (zo kun je niet raden welke accounts bestaan).',
      async run({ api }) {
        const a = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'bestaatniet', wachtwoord: 'voedselbank' });
        const b = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'p.abraham', wachtwoord: 'fout' });
        return { ok: a.status === 401 && a.d.fout === b.d.fout, werkelijk: `Onbekend: ${a.status} "${a.d.fout}"; fout wachtwoord: ${b.status} "${b.d.fout}".` };
      },
    },
    {
      id: 'TC-05', functie: 'Inloggen met lege velden', eisen: ['TEC-1'], soort: 'Negatief', testvorm: 'Technisch (API)',
      rol: '—', testdata: 'leeg / leeg', stappen: ['POST /api/auth/login zonder gebruikersnaam en wachtwoord'],
      verwacht: 'Status 422 "Vul je gebruikersnaam en wachtwoord in."',
      async run({ api }) {
        const r = await api(null, 'POST', '/auth/login', { gebruikersnaam: '', wachtwoord: '' });
        return { ok: r.status === 422 && r.d.fout === 'Vul je gebruikersnaam en wachtwoord in.', werkelijk: `${r.status} "${r.d.fout}"` };
      },
    },
    {
      id: 'TC-06', functie: 'Geblokkeerd account kan niet inloggen', eisen: ['GBR-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger (geblokkeerd)', testdata: 'k.degraaf / voedselbank (Actief = 0)',
      stappen: ['Open /login', 'Log in als k.degraaf'],
      verwacht: 'Melding "Je account is geblokkeerd. Neem contact op met de directie."',
      async run({ pagina, BASIS }) {
        const p = await pagina();
        await p.goto(BASIS + '/login');
        await p.fill('#gebruikersnaam', 'k.degraaf'); await p.fill('#wachtwoord', 'voedselbank'); await p.click('button[type=submit]');
        const m = (await (await p.waitForSelector('.melding.rood')).textContent()).trim();
        return { ok: m.startsWith('Je account is geblokkeerd'), werkelijk: `Melding: "${m}"` };
      },
    },
    {
      id: 'TC-07', functie: 'Account op slot na 10 mislukte pogingen', eisen: ['TEC-6'], soort: 'Grensgeval', testvorm: 'Beveiliging',
      rol: 'Magazijnmedewerker', testdata: 'r.veenstra, 10× fout wachtwoord, daarna het goede',
      stappen: ['Probeer 10 keer in te loggen met een fout wachtwoord', 'Probeer daarna met het goede wachtwoord'],
      verwacht: 'Na 10 pogingen status 429 "Te veel mislukte pogingen", ook met het goede wachtwoord.',
      async run({ api, db }) {
        for (let i = 0; i < 10; i++) await api(null, 'POST', '/auth/login', { gebruikersnaam: 'r.veenstra', wachtwoord: 'fout' + i });
        const r = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'r.veenstra', wachtwoord: 'voedselbank' });
        const teller = db.waarde("SELECT Mislukte_pogingen FROM Gebruiker WHERE Gebruikersnaam='r.veenstra'");
        db.uitvoeren("UPDATE Gebruiker SET Mislukte_pogingen = 0 WHERE Gebruikersnaam='r.veenstra'");
        return { ok: r.status === 429 && teller === '10', werkelijk: `Teller in database: ${teller}; 11e poging met goed wachtwoord: ${r.status} "${r.d.fout}".` };
      },
    },
    {
      id: 'TC-08', functie: 'Uitloggen met bevestiging', eisen: ['TEC-1', 'TEC-6'], soort: 'Positief + alternatief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'a.degroot',
      stappen: ['Klik op Uitloggen in de zijbalk', 'Kies Annuleren', 'Klik opnieuw op Uitloggen', 'Kies "Ja, uitloggen"'],
      verwacht: 'Na Annuleren blijf je ingelogd; na bevestigen ben je uitgelogd en sta je op het inlogscherm. De sessie is op de server vernietigd.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await p.click('.gebruiker .uit');
        const vraag = await ui.dialoogTekst(p);
        const shot = await foto(p, 'TC-08');
        await ui.dialoog(p, false);
        const nogIn = !p.url().includes('/login');
        await p.click('.gebruiker .uit'); await ui.dialoog(p, true);
        await p.waitForURL(/\/login/);
        const ik = await p.evaluate(() => fetch('/api/auth/me').then((r) => r.json()));
        return { ok: nogIn && ik.ingelogd === false, werkelijk: `Dialoog "${vraag.slice(0, 60)}…"; na Annuleren nog ingelogd: ${nogIn ? 'ja' : 'nee'}; na bevestigen /api/auth/me → ingelogd=${ik.ingelogd}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-09', functie: 'Doorsturen naar inloggen en terug naar gevraagde pagina', eisen: ['TEC-2', 'TEC-6'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'URL /klanten zonder sessie',
      stappen: ['Open /klanten zonder in te loggen', 'Log in als p.abraham'],
      verwacht: 'Eerst doorgestuurd naar /login?terug=/klanten; na inloggen direct op de klantenpagina.',
      async run({ pagina, BASIS }) {
        const p = await pagina();
        await p.goto(BASIS + '/klanten');
        await p.waitForURL(/\/login/);
        const eerst = new URL(p.url());
        await p.fill('#gebruikersnaam', 'p.abraham'); await p.fill('#wachtwoord', 'voedselbank'); await p.click('button[type=submit]');
        await p.waitForURL(/\/klanten$/);
        return { ok: eerst.searchParams.get('terug') === '/klanten', werkelijk: `Doorgestuurd naar ${eerst.pathname}${eerst.search}, na inloggen op ${new URL(p.url()).pathname}.` };
      },
    },
    {
      id: 'TC-10', functie: 'Menu toont alleen pagina\'s van de eigen rol', eisen: ['GBR-1', 'GBR-2', 'GBR-3', 'GBR-4'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Alle vier de rollen', testdata: 'p.abraham, r.veenstra, a.degroot, m.snel',
      stappen: ['Log per rol in', 'Lees de menu-items in de zijbalk'],
      verwacht: 'Directie: alles. Magazijn: dashboard, producten, leveranciers, account. Vrijwilliger: dashboard, pakketten, uitgifte, producten (voorraad), klanten, account. Administratie: dashboard, klanten, rapportages, gebruikers, account.',
      async run({ pagina }) {
        const verwacht = {
          'p.abraham': 'Dashboard,Voedselpakketten,Uitgifte,Producten,Leveranciers,Klanten,Rapportages,Gebruikers,Mijn account',
          'r.veenstra': 'Dashboard,Producten,Leveranciers,Mijn account',
          'a.degroot': 'Dashboard,Voedselpakketten,Uitgifte,Producten,Klanten,Mijn account',
          'm.snel': 'Dashboard,Klanten,Rapportages,Gebruikers,Mijn account',
        };
        const uit = [];
        let ok = true;
        for (const [g, menu] of Object.entries(verwacht)) {
          const p = await pagina(g);
          const items = (await p.$$eval('.nav a span', (e) => e.map((x) => x.textContent.trim()))).join(',');
          if (items !== menu) ok = false;
          uit.push(`${g}: ${items}`);
        }
        return { ok, werkelijk: uit.join(' | ') };
      },
    },
    {
      id: 'TC-11', functie: 'Pagina zonder recht via de URL openen', eisen: ['GBR-3', 'TEC-4'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'r.veenstra opent /klanten',
      stappen: ['Log in als magazijnmedewerker', 'Typ /klanten in de adresbalk'],
      verwacht: 'Pagina "Geen toegang" met de pagina\'s waar de rol wel bij mag; er worden geen klantgegevens geladen.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('r.veenstra');
        let klantVerzoek = false;
        p.on('request', (r) => { if (r.url().includes('/api/klanten')) klantVerzoek = true; });
        await ui.naar(p, '/klanten');
        const t = (await p.textContent('.melding.rood')).replace(/\s+/g, ' ').trim();
        return { ok: t.includes('geen rechten') && !klantVerzoek, werkelijk: `"${t}" — verzoek naar /api/klanten gedaan: ${klantVerzoek ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-11') };
      },
    },
    {
      id: 'TC-12', functie: 'Server weigert acties zonder permissie (403)', eisen: ['GBR-3', 'GBR-4', 'TEC-4', 'TEC-6'], soort: 'Negatief', testvorm: 'Beveiliging',
      rol: 'Vrijwilliger, Magazijn, Administratie', testdata: 'API-verzoeken buiten de eigen rol',
      stappen: ['Vrijwilliger: GET /api/gebruikers', 'Magazijn: GET /api/klanten', 'Administratie: POST /api/categorieen', 'Vrijwilliger: DELETE /api/klanten/9'],
      verwacht: 'Alle vier geven status 403 "Je hebt geen rechten voor deze actie." en er verandert niets.',
      async run({ api, cookieVan, db }) {
        const V = await cookieVan('a.degroot'); const M = await cookieVan('r.veenstra'); const A = await cookieVan('m.snel');
        const r = [await api(V, 'GET', '/gebruikers'), await api(M, 'GET', '/klanten'),
          await api(A, 'POST', '/categorieen', { naam: 'Stiekem' }), await api(V, 'DELETE', '/klanten/9')];
        const nogEr = db.waarde('SELECT COUNT(*) FROM Klant WHERE idKlant = 9');
        return { ok: r.every((x) => x.status === 403) && nogEr === '1', werkelijk: `Statussen: ${r.map((x) => x.status).join(', ')}; klant 9 bestaat nog: ${nogEr === '1' ? 'ja' : 'nee'}.` };
      },
    },
    {
      id: 'TC-13', functie: 'Server weigert verzoeken zonder sessie (401)', eisen: ['TEC-6'], soort: 'Negatief', testvorm: 'Beveiliging',
      rol: '—', testdata: 'Geen cookie', stappen: ['GET /api/dashboard, /api/producten en /api/klanten zonder sessie'],
      verwacht: 'Status 401 met login_nodig = true; de frontend stuurt dan naar het inlogscherm.',
      async run({ api }) {
        const r = [await api(null, 'GET', '/dashboard'), await api(null, 'GET', '/producten'), await api(null, 'GET', '/klanten')];
        return { ok: r.every((x) => x.status === 401 && x.d.login_nodig === true), werkelijk: r.map((x) => `${x.status} ${JSON.stringify(x.d)}`).join(' | ') };
      },
    },
    {
      id: 'TC-14', functie: 'Blokkeren werkt direct, ook bij een open sessie', eisen: ['GBR-6', 'TEC-6'], soort: 'Grensgeval', testvorm: 'Beveiliging',
      rol: 'Directie + Vrijwilliger', testdata: 'a.degroot is ingelogd; directie blokkeert haar',
      stappen: ['Vrijwilliger logt in', 'Directie blokkeert de vrijwilliger', 'Vrijwilliger doet een nieuw verzoek'],
      verwacht: 'Het volgende verzoek van de vrijwilliger geeft 401; de sessie is ongeldig.',
      async run({ api, cookieVan }) {
        const V = await cookieVan('a.degroot'); const D = await cookieVan('p.abraham');
        const voor = await api(V, 'GET', '/dashboard');
        await api(D, 'PATCH', '/gebruikers/3/actief', { actief: false });
        const na = await api(V, 'GET', '/dashboard');
        await api(D, 'PATCH', '/gebruikers/3/actief', { actief: true });
        return { ok: voor.status === 200 && na.status === 401, werkelijk: `Voor blokkeren: ${voor.status}; na blokkeren: ${na.status}.` };
      },
    },
    {
      id: 'TC-15', functie: 'Eigen wachtwoord wijzigen', eisen: ['GBR-5'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'huidig voedselbank → nieuw Vrijdag2026',
      stappen: ['Ga naar Mijn account', 'Vul huidig, nieuw en herhaling in', 'Klik op Wachtwoord opslaan', 'Log uit en in met het nieuwe wachtwoord'],
      verwacht: 'Melding "Je wachtwoord is gewijzigd."; inloggen lukt met het nieuwe en niet meer met het oude wachtwoord.',
      async run({ pagina, ui, api }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/account');
        await p.fill('#huidig', 'voedselbank'); await p.fill('#nieuw', 'Vrijdag2026'); await p.fill('#herhaal', 'Vrijdag2026');
        await p.click('form button[type=submit]');
        const t = await ui.toast(p);
        const oud = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'a.degroot', wachtwoord: 'voedselbank' });
        const nieuw = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'a.degroot', wachtwoord: 'Vrijdag2026' });
        return { ok: t === 'Je wachtwoord is gewijzigd.' && oud.status === 401 && nieuw.status === 200, werkelijk: `Toast "${t}"; oud wachtwoord: ${oud.status}, nieuw wachtwoord: ${nieuw.status}.` };
      },
    },
    {
      id: 'TC-16', functie: 'Wachtwoord wijzigen met foute invoer', eisen: ['GBR-5', 'TEC-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'huidig "fout", nieuw "kort", herhaling "anders"',
      stappen: ['Ga naar Mijn account', 'Vul een fout huidig wachtwoord, een te zwak nieuw wachtwoord en een afwijkende herhaling in', 'Klik op opslaan'],
      verwacht: 'Drie veldmeldingen: huidig klopt niet; minimaal 8 tekens met letters én cijfers; wachtwoorden niet gelijk. Wachtwoord blijft ongewijzigd.',
      async run({ pagina, ui, foto, api }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/account');
        await p.fill('#huidig', 'fout'); await p.fill('#nieuw', 'kort'); await p.fill('#herhaal', 'anders');
        await p.click('form button[type=submit]');
        await p.waitForSelector('.veld .fout');
        const f = await ui.veldfouten(p);
        const nogGoed = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'p.abraham', wachtwoord: 'voedselbank' });
        return { ok: f.length === 3 && nogGoed.status === 200, werkelijk: `Veldmeldingen: ${f.join(' / ')}. Oud wachtwoord werkt nog: ${nogGoed.status === 200 ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-16') };
      },
    },
    {
      id: 'TC-17', functie: 'Mijn account: gegevens en eigen rechten', eisen: ['GBR-1', 'TEC-1'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'r.veenstra', stappen: ['Ga naar Mijn account'],
      verwacht: 'Naam, gebruikersnaam, e-mail en rol staan er; de rechtenlijst bevat precies de rechten van de rol.',
      async run({ pagina, ui, db }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/account');
        await p.waitForSelector('.rechten li');
        const rechten = await p.$$eval('.rechten li', (e) => e.map((x) => x.textContent.replace('✓', '').trim()));
        const inDb = db.waarde('SELECT COUNT(*) FROM Rol_has_Permissie WHERE Rol_idRol = 2');
        const tekst = await p.textContent('.lijstje');
        return { ok: String(rechten.length) === inDb && tekst.includes('r.veenstra'), werkelijk: `${rechten.length} rechten getoond (database: ${inDb}): ${rechten.join(', ')}.` };
      },
    },
  ],
};
