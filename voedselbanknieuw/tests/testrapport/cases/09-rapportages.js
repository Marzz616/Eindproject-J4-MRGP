/** Module I — Rapportages en maandoverzichten. */
module.exports = {
  onderdeel: 'I. Rapportages en managementinformatie',
  cases: [
    {
      id: 'TC-93', functie: 'Rapportageoverzicht met kerncijfers en grafieken', eisen: ['RAP-1', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Administratie', testdata: 'Standaard testdata (uitgegeven pakketten dit jaar, afvoer)',
      stappen: ['Log in als administratie en open Rapportages'],
      verwacht: 'Drie kerncijfers (uitgegeven pakketten dit jaar, gemiddelde pakketwaarde, verspilling t.o.v. doel 2%), staafdiagram, voorraadverloop, bestemming, gezinsgrootte en top-producten; aantallen kloppen met de database.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('m.snel');
        await ui.naar(p, '/rapportages');
        const kaart = (await p.textContent('.card.stat >> nth=0')).replace(/\s+/g, ' ');
        const ytdDb = db.waarde("SELECT COUNT(*) FROM Pakket WHERE Status='Uitgegeven' AND YEAR(Uitgiftedatum)=YEAR(CURDATE())");
        const delen = { staaf: !!(await p.$('.staven')), lijn: !!(await p.$('svg.lijngrafiek')), top: (await p.$$('table.lijst tbody tr')).length };
        return { ok: kaart.includes(ytdDb) && delen.staaf && delen.lijn && delen.top > 0,
          werkelijk: `Kaart "${kaart.trim().slice(0, 70)}…" (database: ${ytdDb}); staafdiagram ${delen.staaf ? 'ja' : 'nee'}, lijngrafiek ${delen.lijn ? 'ja' : 'nee'}, top-producten ${delen.top} rijen.`, screenshot: await foto(p, 'TC-93') };
      },
    },
    {
      id: 'TC-94', functie: 'Exporteren naar CSV (Excel)', eisen: ['RAP-1', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI) + API',
      rol: 'Directie', testdata: 'Rapporten weken, verloop, bestemming, verdeling, topproducten',
      stappen: ['Klik bij "Wekelijkse uitgifte" op CSV', 'Vraag alle vijf de exports op'],
      verwacht: 'Download "voedselbank-weken-<datum>.csv"; elke export is text/csv met BOM, kopregel en puntkomma als scheidingsteken.',
      async run({ pagina, ui, cookieVan, BASIS }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/rapportages');
        const [dl] = await Promise.all([p.waitForEvent('download'), p.click('.card:has-text("Wekelijkse uitgifte") button:has-text("CSV")')]);
        const D = await cookieVan('p.abraham');
        const uit = [];
        let ok = /^voedselbank-weken-\d{4}-\d\d-\d\d\.csv$/.test(dl.suggestedFilename());
        for (const r of ['weken', 'verloop', 'bestemming', 'verdeling', 'topproducten']) {
          const x = await fetch(BASIS + '/api/rapportages/export/' + r, { headers: { Cookie: D } });
          const bytes = Buffer.from(await x.arrayBuffer());
          const tekst = bytes.toString('utf8');
          const bom = bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF;
          const goed = x.status === 200 && x.headers.get('content-type').startsWith('text/csv') && bom && tekst.split('\n')[0].includes(';');
          if (!goed) ok = false;
          uit.push(`${r}: ${tekst.split('\n').filter(Boolean).length} regels`);
        }
        return { ok, werkelijk: `Download "${dl.suggestedFilename()}"; ${uit.join(', ')}.` };
      },
    },
    {
      id: 'TC-95', functie: 'Maandoverzicht per productcategorie', eisen: ['RAP-1'], soort: 'Positief + alternatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Huidige maand; daarna januari van 4 jaar geleden (geen gegevens)',
      stappen: ['Klik "Maandoverzichten"', 'Bekijk het overzicht per productcategorie', 'Kies januari en het oudste jaar'],
      verwacht: 'Tabel gegroepeerd per categorie met product, leverancier, geleverd en uitgegeven; totaal klopt met de database. Lege maand: "Geen gegevens in januari …".',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/rapportages');
        await p.click('text=Maandoverzichten');
        // eerst wachten tot de maandpagina er echt staat: het rapportagescherm
        // heeft zelf ook een table.lijst (top-10 producten)
        await p.waitForURL(/\/rapportages\/maand/);
        await p.waitForSelector('td.catkop');
        const koppen = await p.$$eval('td.catkop', (e) => e.map((x) => x.textContent.trim()));
        const tel = (await p.textContent('.card .kop .tel')).trim();
        const dbTotaal = db.waarde('SELECT COALESCE(SUM(lp.Aantal),0) FROM Levering v JOIN Levering_has_Product lp ON lp.Levering_idLevering = v.idLevering WHERE MONTH(v.Leverdatum)=MONTH(CURDATE()) AND YEAR(v.Leverdatum)=YEAR(CURDATE())');
        const shot = await foto(p, 'TC-95');
        await p.selectOption('select[aria-label=Maand]', '1');
        const jaren = await p.$$eval('select[aria-label=Jaar] option', (e) => e.map((x) => x.value));
        await p.selectOption('select[aria-label=Jaar]', jaren[jaren.length - 1]);
        await p.waitForSelector('.leeg h3');
        const leeg = (await p.textContent('.leeg h3')).trim();
        return { ok: koppen.length > 0 && tel.startsWith(Number(dbTotaal).toLocaleString('nl-NL')) && leeg.startsWith('Geen gegevens in januari'),
          werkelijk: `${koppen.length} categorieën (${koppen.join(', ')}); "${tel}" (database: ${dbTotaal}); lege maand: "${leeg}".`, screenshot: shot };
      },
    },
    {
      id: 'TC-96', functie: 'Maandoverzicht per postcode', eisen: ['RAP-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Huidige maand', stappen: ['Open Maandoverzichten', 'Klik "Per postcode"'],
      verwacht: 'Per postcode: aantal gezinnen, pakketten, aantallen per categorie en totaal; het eindtotaal en het aantal gezinnen per postcode kloppen met de database.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/rapportages/maand');
        await p.click('.chip:has-text("Per postcode")');
        // wachten tot de postcodetabel er staat; de categorietabel staat er al
        await p.waitForSelector('h2:has-text("Per postcode")');
        await p.waitForSelector('table.lijst tbody tr');
        const rijen = await p.$$eval('table.lijst tbody tr', (e) => e.map((x) => [...x.children].map((c) => c.textContent.trim())));
        const totaalUi = rijen.reduce((s, r) => s + Number(r[r.length - 1].replace(/\./g, '')), 0);
        const totaalDb = Number(db.waarde("SELECT COALESCE(SUM(pp.Aantal),0) FROM Pakket pk JOIN Product_has_Pakket pp ON pp.Pakket_idPakket = pk.idPakket WHERE pk.Status='Uitgegeven' AND MONTH(pk.Uitgiftedatum)=MONTH(CURDATE()) AND YEAR(pk.Uitgiftedatum)=YEAR(CURDATE())"));
        const pc = rijen[0][0];
        const gezDb = db.waarde(`SELECT COUNT(DISTINCT k.idKlant) FROM Pakket pk JOIN Klant k ON k.idKlant = pk.Klant_idKlant WHERE pk.Status='Uitgegeven' AND MONTH(pk.Uitgiftedatum)=MONTH(CURDATE()) AND YEAR(pk.Uitgiftedatum)=YEAR(CURDATE()) AND k.Postcode='${pc}'`);
        return { ok: rijen.length > 0 && totaalUi === totaalDb && rijen[0][1] === gezDb,
          werkelijk: `${rijen.length} postcodes; totaal ${totaalUi} (database ${totaalDb}); ${pc}: ${rijen[0][1]} gezinnen (database ${gezDb}).`, screenshot: await foto(p, 'TC-96') };
      },
    },
    {
      id: 'TC-97', functie: 'Maandoverzicht afdrukken', eisen: ['RAP-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Afdrukweergave (print media) van het maandoverzicht',
      stappen: ['Open Maandoverzichten', 'Klik "Afdrukken" (afdrukweergave)'],
      verwacht: 'Afdruk toont een kop met naam voedselbank, soort overzicht, maand en afdrukdatum; menu, filters en knoppen worden niet afgedrukt; A4 liggend.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/rapportages/maand');
        await p.emulateMedia({ media: 'print' });
        const kop = (await p.textContent('.printkop')).replace(/\s+/g, ' ').trim();
        const zichtbaar = { kop: await p.isVisible('.printkop'), menu: await p.isVisible('.side'), filters: await p.isVisible('.zoekbalk') };
        const shot = await foto(p, 'TC-97');
        const pdf = await p.pdf({ format: 'A4', landscape: true });
        return { ok: zichtbaar.kop && !zichtbaar.menu && !zichtbaar.filters && pdf.length > 1000,
          werkelijk: `Printkop "${kop}"; menu zichtbaar: ${zichtbaar.menu ? 'ja' : 'nee'}; filters zichtbaar: ${zichtbaar.filters ? 'ja' : 'nee'}; PDF gemaakt (${Math.round(pdf.length / 1024)} kB).`, screenshot: shot };
      },
    },
    {
      id: 'TC-98', functie: 'Rechten rapportages', eisen: ['GBR-3', 'GBR-4', 'TEC-4'], soort: 'Beveiliging', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger, Magazijn', testdata: 'GET /api/rapportages, /api/rapportages/export/weken, /api/rapportages/maand',
      stappen: ['Vraag de rapportages op als vrijwilliger en als magazijnmedewerker'],
      verwacht: 'Alle verzoeken 403.',
      async run({ api, cookieVan }) {
        const st = [];
        for (const g of ['a.degroot', 'r.veenstra']) {
          const c = await cookieVan(g);
          for (const pad of ['/rapportages', '/rapportages/export/weken', '/rapportages/maand']) st.push((await api(c, 'GET', pad)).status);
        }
        return { ok: st.every((s) => s === 403), werkelijk: `Statussen: ${st.join(', ')}.` };
      },
    },
  ],
};
