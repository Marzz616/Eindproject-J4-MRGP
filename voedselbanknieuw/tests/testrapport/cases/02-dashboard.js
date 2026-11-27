/** Module B — Dashboard per rol. */
async function labels(api, cookie) {
  const r = await api(cookie, 'GET', '/dashboard');
  return { r, kaarten: r.d.kaarten.map((k) => k.label), blokken: r.d.blokken.map((b) => b.titel) };
}

module.exports = {
  onderdeel: 'B. Dashboard',
  cases: [
    {
      id: 'TC-18', functie: 'Dashboard directie: volledig beeld', eisen: ['GBR-2', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'p.abraham + standaard testdata', stappen: ['Log in als directie', 'Bekijk het dashboard'],
      verwacht: 'Taken, cijferkaarten voor voorraad, pakketten, uitgifte, klanten en medewerkers, de grafiek wekelijkse uitgifte en de lijsten.',
      async run({ pagina, api, cookieVan, foto }) {
        const p = await pagina('p.abraham');
        await p.waitForSelector('.kaarten .card');
        const { kaarten, blokken } = await labels(api, await cookieVan('p.abraham'));
        const grafiek = await p.$('.staven');
        const nodig = ['Voorraad totaal', 'Pakketten deze week', 'Klaar voor uitgifte', 'Klanten actief', 'Medewerkers'];
        return { ok: nodig.every((n) => kaarten.includes(n)) && !!grafiek && blokken.length >= 6,
          werkelijk: `${kaarten.length} kaarten (${kaarten.join(', ')}); ${blokken.length} blokken; grafiek zichtbaar: ${grafiek ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-18') };
      },
    },
    {
      id: 'TC-19', functie: 'Dashboard magazijnmedewerker', eisen: ['GBR-3', 'TEC-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'r.veenstra', stappen: ['Log in als magazijnmedewerker', 'Bekijk het dashboard'],
      verwacht: 'Voorraad, minimumvoorraad, THT en leveringen; géén klanten-, pakket- of gebruikerskaarten.',
      async run({ pagina, api, cookieVan, foto }) {
        const p = await pagina('r.veenstra');
        await p.waitForSelector('.kaarten .card');
        const { kaarten } = await labels(api, await cookieVan('r.veenstra'));
        const ok = kaarten.includes('Voorraad totaal') && kaarten.includes('Eerstvolgende levering')
          && !kaarten.includes('Klanten actief') && !kaarten.includes('Pakketten deze week') && !kaarten.includes('Medewerkers');
        return { ok, werkelijk: `Kaarten: ${kaarten.join(', ')}.`, screenshot: await foto(p, 'TC-19') };
      },
    },
    {
      id: 'TC-20', functie: 'Dashboard vrijwilliger', eisen: ['GBR-4', 'TEC-2'], soort: 'Positief', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger', testdata: 'a.degroot', stappen: ['GET /api/dashboard als vrijwilliger'],
      verwacht: 'Pakketten deze week, nog samen te stellen, klaar voor uitgifte en THT; géén totale voorraad of minimumvoorraad.',
      async run({ api, cookieVan }) {
        const { kaarten } = await labels(api, await cookieVan('a.degroot'));
        const ok = kaarten.includes('Pakketten deze week') && kaarten.includes('Nog samen te stellen')
          && kaarten.includes('Bijna over de THT') && !kaarten.includes('Voorraad totaal') && !kaarten.includes('Onder minimumvoorraad');
        return { ok, werkelijk: `Kaarten: ${kaarten.join(', ')}.` };
      },
    },
    {
      id: 'TC-21', functie: 'Dashboard administratie', eisen: ['TEC-4'], soort: 'Positief', testvorm: 'Technisch (API)',
      rol: 'Administratie', testdata: 'm.snel', stappen: ['GET /api/dashboard als administratie'],
      verwacht: 'Klanten actief + de lijst "Aanvragen die wachten op beoordeling" (2 aanvragen), cijfers en medewerkers; géén voorraad.',
      async run({ api, cookieVan }) {
        const { kaarten, blokken, r } = await labels(api, await cookieVan('m.snel'));
        const wacht = r.d.blokken.find((b) => b.titel === 'Aanvragen die wachten op beoordeling');
        const ok = kaarten.includes('Klanten actief') && wacht && wacht.rijen.length === 2 && !kaarten.includes('Voorraad totaal');
        return { ok, werkelijk: `Kaarten: ${kaarten.join(', ')}. Blokken: ${blokken.join(', ')}. Wachtende aanvragen: ${wacht ? wacht.rijen.map((x) => x.naam).join(', ') : 'geen'}.` };
      },
    },
    {
      id: 'TC-22', functie: 'Taken en links op het dashboard', eisen: ['TEC-2', 'TEC-3', 'WRK-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'a.degroot; gezin Familie Bakker heeft deze week nog geen pakket',
      stappen: ['Klik in de takenlijst op "Bekijken →" bij de gezinnen zonder pakket', 'Ga terug en klik in "Gezinnen zonder pakket deze week" op Familie Bakker'],
      verwacht: 'Eerste klik opent Voedselpakketten; tweede klik opent Voedselpakketten met Familie Bakker al gekozen.',
      async run({ pagina, ui }) {
        const p = await pagina('a.degroot');
        await p.click('.taak:has-text("pakket deze week") a');
        await p.waitForURL(/\/pakketten/);
        const eerste = new URL(p.url()).pathname;
        await ui.naar(p, '/');
        await p.click('a:has-text("Familie Bakker")');
        await p.waitForSelector('.profiel h3');
        const gekozen = await p.textContent('.profiel h3');
        return { ok: eerste === '/pakketten' && gekozen === 'Familie Bakker', werkelijk: `Taak-link → ${eerste}; gezinslink → ${new URL(p.url()).pathname}${new URL(p.url()).search} met gekozen gezin "${gekozen}".` };
      },
    },
  ],
};
