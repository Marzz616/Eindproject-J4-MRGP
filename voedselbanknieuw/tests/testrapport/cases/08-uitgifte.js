/** Module H — Uitgifte op vrijdag. */
async function openUitWachtrij(p, pakketId) {
  await p.click(`[data-pakket="${pakketId}"]`);
  await p.waitForTimeout(300);
}

module.exports = {
  onderdeel: 'H. Uitgifte (vrijdag)',
  cases: [
    {
      id: 'TC-84', functie: 'Wachtrij van deze week', eisen: ['WRK-2', 'PAK-8'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Week: #2862 Klaar (El Hassani), #2860 en #2861 uitgegeven',
      stappen: ['Ga naar Uitgifte', 'Bekijk de drie blokken rechts'],
      verwacht: '"Klaar voor uitgifte" 1 (El Hassani, Wacht), "Nog in concept" 0, "Deze week al uitgegeven" 2 met datum.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        const tellers = await p.$$eval('.card .kop', (e) => e.map((x) => `${x.querySelector('h2').textContent.trim()} ${x.querySelector('.tel')?.textContent.trim() ?? ''}`.trim()).filter((x) => /Klaar voor|concept|uitgegeven/.test(x)));
        return { ok: tellers.join('|') === 'Klaar voor uitgifte 1|Nog in concept 0|Deze week al uitgegeven 2', werkelijk: tellers.join(' · '), screenshot: await foto(p, 'TC-84') };
      },
    },
    {
      id: 'TC-85', functie: 'Cliënt zoeken op nummer of naam', eisen: ['WRK-1', 'TEC-3'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: '"K-002", "2", "hassani", "xyz"',
      stappen: ['Typ K-002 en druk Enter', 'Herhaal met 2 en hassani', 'Zoek op xyz'],
      verwacht: 'De eerste drie tonen de kaart van Gezin El Hassani; "xyz" geeft "Geen cliënt gevonden voor "xyz"."',
      async run({ pagina, ui }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        const zoek = async (q) => { await p.fill('#q', q); await p.press('#q', 'Enter'); await p.waitForTimeout(400); };
        const namen = [];
        for (const q of ['K-002', '2', 'hassani']) {
          await zoek(q);
          namen.push((await p.textContent('.inhoud .card .naam')).trim());
        }
        await zoek('xyz');
        const t = await ui.toast(p);
        return { ok: namen.every((n) => n === 'Gezin El Hassani') && t === 'Geen cliënt gevonden voor "xyz".', werkelijk: `K-002/2/hassani → ${namen.join(', ')}; xyz → "${t}".` };
      },
    },
    {
      id: 'TC-86', functie: 'Gezin zonder pakket deze week', eisen: ['WRK-2'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Familie Bakker (nog geen pakket deze week)',
      stappen: ['Zoek op "Bakker"'],
      verwacht: 'Kaart met "Er staat deze week geen pakket klaar voor dit gezin." en een knop naar Voedselpakketten.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        await p.fill('#q', 'Bakker'); await p.press('#q', 'Enter'); await p.waitForTimeout(400);
        const m = (await p.textContent('.melding.amber')).replace(/\s+/g, ' ').trim();
        const knop = await p.$('a:has-text("Naar Voedselpakketten")');
        return { ok: m.includes('geen pakket klaar') && !!knop, werkelijk: `"${m}"; knop: ${knop ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-86') };
      },
    },
    {
      id: 'TC-87', functie: 'Uitgeven zonder legitimatie wordt geblokkeerd', eisen: ['WRK-1'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Pakket #2862, identiteit nog niet gecontroleerd',
      stappen: ['Klik in de wachtrij op El Hassani', 'Klik "Pakket uitgegeven bevestigen" en bevestig'],
      verwacht: 'Melding "Eerst legitimeren: Leg eerst vast met welk legitimatiebewijs…"; status blijft Klaar.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        await openUitWachtrij(p, 2862);
        await p.click('button:has-text("Pakket uitgegeven bevestigen")'); await ui.dialoog(p, true);
        const tekst = await ui.dialoogTekst(p);
        const shot = await foto(p, 'TC-87');
        await ui.dialoog(p, true);
        const s = db.waarde('SELECT Status FROM Pakket WHERE idPakket = 2862');
        return { ok: tekst.includes('Eerst legitimeren') && s === 'Klaar', werkelijk: `"${tekst.slice(0, 120)}"; status ${s}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-88', functie: 'Legitimatie vastleggen', eisen: ['WRK-1'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI) + API',
      rol: 'Vrijwilliger', testdata: 'Pakket #2862: Paspoort; via API "Bibliotheekpas"',
      stappen: ['Open El Hassani, klik "Legitimatie vastleggen", kies Paspoort', 'Stuur via de API een niet-toegestaan legitimatiebewijs'],
      verwacht: '"Geïdentificeerd met: Paspoort"; kaart en wachtrij tonen gelegitimeerd. Bibliotheekpas: 422 "Kies paspoort, rijbewijs of ID-kaart."',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        await openUitWachtrij(p, 2862);
        await p.click('button:has-text("Legitimatie vastleggen")');
        await p.selectOption('.modal select', 'Paspoort'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const status = (await p.textContent('.gegeven')).replace(/\s+/g, ' ');
        const pil = (await p.textContent('[data-pakket="2862"] .pil2')).trim();
        const r = await api(await cookieVan('a.degroot'), 'POST', '/uitgifte/2862/identiteit', { legitimatie: 'Bibliotheekpas' });
        return { ok: t === 'Geïdentificeerd met: Paspoort' && status.includes('Geïdentificeerd met: Paspoort') && pil === 'Gelegitimeerd' && r.status === 422,
          werkelijk: `Toast "${t}"; wachtrij "${pil}"; Bibliotheekpas: ${r.status} "${r.d.fouten?.legitimatie}".` };
      },
    },
    {
      id: 'TC-89', functie: 'Pakket uitgeven', eisen: ['PAK-8', 'PAK-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Pakket #2862 (gelegitimeerd in TC-88)',
      stappen: ['Open El Hassani', 'Klik "Pakket uitgegeven bevestigen" en bevestig'],
      verwacht: '"Pakket uitgegeven aan Gezin El Hassani."; status Uitgegeven, uitgiftedatum vandaag, opgehaald = ja, uitgegeven door Ank de Groot; "Deze week al uitgegeven" wordt 3.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        await openUitWachtrij(p, 2862);
        await p.click('button:has-text("Pakket uitgegeven bevestigen")'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        await p.waitForTimeout(300);
        const rij = db.waarde("SELECT CONCAT(p.Status,'|',p.Uitgiftedatum = CURDATE(),'|',p.Opgehaald,'|',g.Naam) FROM Pakket p JOIN Gebruiker g ON g.idGebruiker = p.Uitgegeven_door WHERE idPakket = 2862").split('|');
        const kop = (await p.textContent('.card .kop:has-text("Deze week al uitgegeven")')).replace(/\s+/g, ' ').trim();
        return { ok: t === 'Pakket uitgegeven aan Gezin El Hassani.' && rij[0] === 'Uitgegeven' && rij[1] === '1' && rij[2] === '1' && kop.endsWith('3'),
          werkelijk: `"${t}"; status ${rij[0]}, datum vandaag ${rij[1] === '1' ? 'ja' : 'nee'}, opgehaald ${rij[2]}, door ${rij[3]}; "${kop}".`, screenshot: await foto(p, 'TC-89') };
      },
    },
    {
      id: 'TC-90', functie: 'Uitgegeven pakket is historie (niet meer te wijzigen)', eisen: ['PAK-5', 'KLT-3'], soort: 'Negatief', testvorm: 'Technisch (API)',
      rol: 'Directie', testdata: 'Pakket #2862 (uitgegeven in TC-89)',
      stappen: ['PUT /api/pakketten/2862', 'DELETE /api/pakketten/2862', 'POST /api/uitgifte/2862/uitgeven', 'POST /api/uitgifte/2862/niet-opgehaald'],
      verwacht: 'Alle vier 409 (al uitgegeven / blijft bewaard voor de rapportages).',
      async run({ api, cookieVan }) {
        const D = await cookieVan('p.abraham');
        const r = [await api(D, 'PUT', '/pakketten/2862', { klant: 2, status: 'Klaar', regels: [{ product: 12, aantal: 1 }] }),
          await api(D, 'DELETE', '/pakketten/2862'), await api(D, 'POST', '/uitgifte/2862/uitgeven'), await api(D, 'POST', '/uitgifte/2862/niet-opgehaald')];
        return { ok: r.every((x) => x.status === 409), werkelijk: r.map((x) => `${x.status} "${x.d.fout}"`).join(' | ') };
      },
    },
    {
      id: 'TC-91', functie: 'Niet opgehaald: terugboeken naar de voorraad', eisen: ['PAK-8', 'VRD-2'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Klaargezet pakket voor Mevr. Jansen met 3× Rijst 1kg (via API aangemaakt)',
      stappen: ['Open het pakket van Mevr. Jansen in de wachtrij', 'Klik "Niet opgehaald" en bevestig'],
      verwacht: '"Pakket teruggeboekt, de producten staan weer in de voorraad."; Rijst weer op de oude stand; het pakket bestaat niet meer.',
      async run({ pagina, ui, api, cookieVan, db }) {
        const rijstVoor = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12');
        const nieuw = await api(await cookieVan('a.degroot'), 'POST', '/pakketten', { klant: 3, status: 'Klaar', regels: [{ product: 12, aantal: 3 }] });
        const tussen = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12');
        const p = await pagina('a.degroot');
        await ui.naar(p, '/uitgifte');
        await openUitWachtrij(p, nieuw.d.id);
        await p.click('button:has-text("Niet opgehaald")'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const na = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12');
        const bestaat = db.waarde(`SELECT COUNT(*) FROM Pakket WHERE idPakket = ${nieuw.d.id}`);
        return { ok: t.startsWith('Pakket teruggeboekt') && na === rijstVoor && bestaat === '0',
          werkelijk: `Rijst ${rijstVoor} → ${tussen} (klaargezet) → ${na} (teruggeboekt); "${t}"; pakket #${nieuw.d.id} bestaat nog: ${bestaat}.` };
      },
    },
    {
      id: 'TC-92', functie: 'Rechten uitgifte', eisen: ['GBR-3', 'GBR-4'], soort: 'Beveiliging', testvorm: 'Technisch (API)',
      rol: 'Magazijn, Vrijwilliger', testdata: 'GET /api/uitgifte/wachtrij',
      stappen: ['Magazijnmedewerker vraagt de wachtrij op', 'Vrijwilliger vraagt de wachtrij op'],
      verwacht: 'Magazijn 403 (uitgifte hoort niet bij die rol); vrijwilliger 200.',
      async run({ api, cookieVan }) {
        const m = await api(await cookieVan('r.veenstra'), 'GET', '/uitgifte/wachtrij');
        const v = await api(await cookieVan('a.degroot'), 'GET', '/uitgifte/wachtrij');
        return { ok: m.status === 403 && v.status === 200, werkelijk: `Magazijn ${m.status}; vrijwilliger ${v.status}.` };
      },
    },
  ],
};
