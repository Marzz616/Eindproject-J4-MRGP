/** Module F — Producten, voorraad en categorieën. */
const kolom = (p, n) => p.$$eval(`table.lijst tbody tr td:nth-child(${n})`, (e) => e.map((x) => x.textContent.trim()));
const rij = (p, naam) => `table.lijst tbody tr:has-text("${naam}")`;

module.exports = {
  onderdeel: 'F. Producten, voorraad en categorieën',
  cases: [
    {
      id: 'TC-55', functie: 'Productenoverzicht en zoeken op streepjescode', eisen: ['PRD-1', 'VRD-5'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: '20 producten; zoek op EAN 8712345678909 en op "melk"',
      stappen: ['Ga naar Producten', 'Typ 8712345678909 in het zoekveld', 'Typ daarna "melk"'],
      verwacht: 'Kerncijfers (totaal stuks, aantal producten); EAN-zoeken vindt precies Pindakaas; "melk" vindt de 2 melkproducten.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        const alle = (await p.$$('table.lijst tbody tr')).length;
        await p.fill('.zoek input', '8712345678909'); await p.waitForTimeout(150);
        const ean = await kolom(p, 1);
        const shot = await foto(p, 'TC-55');
        await p.fill('.zoek input', 'melk'); await p.waitForTimeout(150);
        const melk = await kolom(p, 1);
        return { ok: alle === 20 && ean.length === 1 && ean[0].includes('Pindakaas') && melk.length === 2,
          werkelijk: `${alle} producten; EAN-zoeken → ${ean.join()}; "melk" → ${melk.length} resultaten.`, screenshot: shot };
      },
    },
    {
      id: 'TC-56', functie: 'Sorteren op elke kolom', eisen: ['VRD-4'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Kolommen productnaam, streepjescode, categorie, voorraad',
      stappen: ['Klik op de kolomkop Productnaam (2×)', 'Klik op Streepjescode, Categorie en Voorraad'],
      verwacht: 'Elke klik sorteert op die kolom (oplopend, tweede klik aflopend); de actieve kolom krijgt een pijl.',
      async run({ pagina, ui }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        const gesorteerd = (a, op = (x, y) => x.localeCompare(y, 'nl', { numeric: true })) => a.every((x, i) => i === 0 || op(a[i - 1], x) <= 0);
        const uit = [];
        await p.click('th:has-text("Productnaam")'); const naamAf = (await kolom(p, 1)).map((x) => x.split('\n')[0]);
        uit.push(['naam ↓', naamAf.slice(0, 2).join(' > ')]);
        await p.click('th:has-text("Streepjescode")'); const ean = await kolom(p, 2);
        await p.click('th:has-text("Categorie")'); const cat = await kolom(p, 3);
        await p.click('th:has-text("Voorraad") >> nth=0'); const vrd = (await kolom(p, 4)).map((x) => Number(x.replace(/\D/g, '')));
        const pijl = await p.textContent('th.aan');
        const ok = naamAf[0].localeCompare(naamAf[naamAf.length - 1], 'nl') > 0 && gesorteerd(ean) && vrd.every((x, i) => i === 0 || vrd[i - 1] <= x);
        return { ok, werkelijk: `Productnaam aflopend begint met "${naamAf[0].slice(0, 30)}"; EAN oplopend: ${gesorteerd(ean) ? 'ja' : 'nee'}; categorie eerste "${cat[0]}"; voorraad oplopend ${vrd.slice(0, 4).join(', ')}…; actieve kop "${pijl.trim()}".` };
      },
    },
    {
      id: 'TC-57', functie: 'Filteren op categorie', eisen: ['PRD-4', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Filterknop "Zuivel" (3 producten)', stappen: ['Klik op de filterknop Zuivel', 'Klik op Alles'],
      verwacht: 'Alleen de 3 zuivelproducten; teller "3 van 20 producten"; "Alles" toont weer 20.',
      async run({ pagina, ui }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/producten');
        await p.click('.chip:has-text("Zuivel")');
        const n = (await p.$$('table.lijst tbody tr')).length;
        const tel = (await p.textContent('.card .kop .tel')).trim();
        await p.click('.chip:has-text("Alles")');
        const alle = (await p.$$('table.lijst tbody tr')).length;
        return { ok: n === 3 && alle === 20, werkelijk: `Zuivel: ${n} rijen ("${tel}"); Alles: ${alle}.` };
      },
    },
    {
      id: 'TC-58', functie: 'Nieuw product toevoegen', eisen: ['VRD-1', 'PRD-1', 'PRD-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Volkoren crackers, EAN 8711111111116, cat. Bakkerij en banket, voorraad 40, min 10, € 1,49, kenmerk gluten',
      stappen: ['Klik "+ Nieuw product"', 'Vul alle velden in en kies het kenmerk gluten', 'Opslaan'],
      verwacht: 'Melding "Product opgeslagen."; product in de lijst met kenmerk gluten; 21 producten.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        await p.click('text=+ Nieuw product');
        await p.fill('#p-naam', 'Volkoren crackers'); await p.fill('#p-ean', '8711111111116');
        await p.selectOption('#p-cat', { label: 'Bakkerij en banket' });
        await p.fill('#p-vrd', '40'); await p.fill('#p-min', '10'); await p.fill('#p-waarde', '1.49'); await p.fill('#p-loc', 'Krat B-3');
        await p.click('.drawer.open .chip[data-tag=gluten]');
        const shot = await foto(p, 'TC-58');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const r = (await p.textContent(rij(p, 'Volkoren crackers'))).replace(/\s+/g, ' ');
        const n = (await p.$$('table.lijst tbody tr')).length;
        return { ok: t === 'Product opgeslagen.' && r.includes('gluten') && n === 21, werkelijk: `Toast "${t}"; rij: "${r.trim().slice(0, 90)}"; aantal producten ${n}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-59', functie: 'Product: geldige EAN en geen dubbelen', eisen: ['PRD-2', 'PRD-3', 'VRD-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: '1) EAN "12345" 2) naam "Rijst 1kg" (bestaat) + EAN 8712345678913 (van Pasta penne)',
      stappen: ['Nieuw product met EAN van 5 cijfers', 'Nieuw product met een bestaande naam en een bestaande EAN'],
      verwacht: '"Een EAN bestaat uit 13 cijfers."; daarna "Dit product bestaat al in het magazijn." en "Deze streepjescode is al in gebruik."; niets opgeslagen.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/producten');
        const voor = db.waarde('SELECT COUNT(*) FROM Product');
        await p.click('text=+ Nieuw product');
        await p.fill('#p-naam', 'Test EAN'); await p.fill('#p-ean', '12345');
        await ui.drawerOpslaan(p);
        const f1 = await ui.veldfouten(p);
        await p.fill('#p-naam', 'Rijst 1kg'); await p.fill('#p-ean', '8712345678913');
        await ui.drawerOpslaan(p);
        const f2 = await ui.veldfouten(p);
        const n = db.waarde('SELECT COUNT(*) FROM Product');
        return { ok: f1.includes('Een EAN bestaat uit 13 cijfers.') && f2.includes('Dit product bestaat al in het magazijn.') && f2.includes('Deze streepjescode is al in gebruik.') && n === voor,
          werkelijk: `Korte EAN: ${f1.join(' / ')}. Dubbel: ${f2.join(' / ')}. Producten in db voor/na: ${voor}/${n}.`, screenshot: await foto(p, 'TC-59') };
      },
    },
    {
      id: 'TC-60', functie: 'Product bewerken', eisen: ['VRD-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Hak Doperwtjes blik: minimumvoorraad 25 → 60, locatie Vak C-6',
      stappen: ['Klik op het potlood bij Hak Doperwtjes blik', 'Wijzig minimumvoorraad en locatie', 'Opslaan'],
      verwacht: 'Melding "Product opgeslagen."; minimum 60 en locatie Vak C-6 in de lijst; voorraad 45 staat nu rood (onder minimum).',
      async run({ pagina, ui }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        await p.click(`${rij(p, 'Hak Doperwtjes blik')} button[title=Bewerken]`);
        await p.fill('#p-min', '60'); await p.fill('#p-loc', 'Vak C-6');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const r = (await p.textContent(rij(p, 'Hak Doperwtjes blik'))).replace(/\s+/g, ' ');
        const kleur = await p.$eval(`${rij(p, 'Hak Doperwtjes blik')} td:nth-child(4)`, (e) => e.getAttribute('style') || '');
        return { ok: t === 'Product opgeslagen.' && r.includes('60 st.') && r.includes('Vak C-6') && kleur.includes('red'),
          werkelijk: `Toast "${t}"; rij "${r.trim().slice(0, 100)}"; voorraad gemarkeerd: ${kleur.includes('red') ? 'rood' : 'nee'}.` };
      },
    },
    {
      id: 'TC-61', functie: 'Voorraad corrigeren', eisen: ['VRD-2', 'GBR-4'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI) + API',
      rol: 'Vrijwilliger', testdata: 'Shampoo 300ml: 450 → 440; daarna via API -5',
      stappen: ['Klik op het pijltjes-icoon bij Shampoo 300ml', 'Vul 440 in en bevestig', 'Stuur via de API een voorraad van -5'],
      verwacht: 'Melding "Voorraad bijgewerkt naar 440 stuks."; negatieve voorraad: 422 "De voorraad kan niet onder 0 komen."',
      async run({ pagina, ui, api, cookieVan, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/producten');
        await p.click(`${rij(p, 'Shampoo 300ml')} button[title="Voorraad corrigeren"]`);
        await p.fill('.modal input[name=aantal]', '440');
        const shot = await foto(p, 'TC-61');
        await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const r = await api(await cookieVan('a.degroot'), 'PATCH', '/producten/18/voorraad', { aantal: -5 });
        return { ok: t === 'Voorraad bijgewerkt naar 440 stuks.' && r.status === 422, werkelijk: `Toast "${t}"; -5 via API: ${r.status} "${r.d.fout}".`, screenshot: shot };
      },
    },
    {
      id: 'TC-62', functie: 'Product afvoeren met reden', eisen: ['VRD-2', 'RAP-1'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Campina Volle Melk (12 st., over de datum): 12× Verspild; daarna 1× extra',
      stappen: ['Klik op de prullenbak bij Campina Volle Melk', 'Aantal 12, reden Verspild, toelichting "THT verlopen"', 'Afvoeren', 'Probeer opnieuw 1 stuk af te voeren'],
      verwacht: 'Melding "12 x Campina Volle Melk (1L) afgevoerd (Verspild)."; voorraad 0; afvoerregel in de database. Tweede keer: "Er liggen maar 0 stuks in het magazijn."',
      async run({ pagina, ui, db }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        await p.click(`${rij(p, 'Campina Volle Melk (1L)')} button[title=Afvoeren]`);
        await p.fill('.modal input[name=aantal]', '12'); await p.fill('.modal input[name=toelichting]', 'THT verlopen');
        await ui.dialoog(p, true);
        const t1 = await ui.toast(p);
        await p.click(`${rij(p, 'Campina Volle Melk (1L)')} button[title=Afvoeren]`);
        await p.fill('.modal input[name=aantal]', '1');
        await ui.dialoog(p, true);
        const t2 = await ui.toast(p);
        const vrd = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 1');
        const afv = db.waarde("SELECT CONCAT(Aantal, ' ', Reden) FROM Afvoer WHERE Product_idProduct = 1 ORDER BY idAfvoer DESC LIMIT 1");
        return { ok: t1.startsWith('12 x Campina Volle Melk') && vrd === '0' && afv === '12 Verspild' && t2.includes('maar 0 stuks'),
          werkelijk: `"${t1}"; voorraad ${vrd}; afvoerregel "${afv}"; tweede poging: "${t2}".` };
      },
    },
    {
      id: 'TC-63', functie: 'Product verwijderen (met en zonder historie)', eisen: ['VRD-3'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Luiers maat 4 (nergens gebruikt) en Halfbruin gesneden brood (in 14 pakketten)',
      stappen: ['Verwijder Luiers maat 4 en bevestig', 'Verwijder Halfbruin gesneden brood en bevestig'],
      verwacht: 'Luiers: "Luiers maat 4 is verwijderd." Brood: melding "Dit product kan niet weg" (komt voor in uitgedeelde pakketten) met de tip de voorraad op 0 te zetten; product blijft bestaan.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/producten');
        await p.click(`${rij(p, 'Luiers maat 4')} button[title=Verwijderen]`); await ui.dialoog(p, true);
        const t1 = await ui.toast(p);
        await p.click(`${rij(p, 'Halfbruin gesneden brood')} button[title=Verwijderen]`); await ui.dialoog(p, true);
        const tekst = await ui.dialoogTekst(p);
        const shot = await foto(p, 'TC-63');
        await ui.dialoog(p, true);
        const brood = db.waarde('SELECT COUNT(*) FROM Product WHERE idProduct = 3');
        return { ok: t1 === 'Luiers maat 4 is verwijderd.' && tekst.includes('uitgedeelde voedselpakketten') && brood === '1',
          werkelijk: `Luiers: "${t1}". Brood: "${tekst.slice(0, 130)}…"; brood in db: ${brood}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-64', functie: 'Signalering houdbaarheid en minimumvoorraad', eisen: ['VRD-2', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Campina Volle Melk (THT verstreken), Halfbruin brood (THT binnen 3 dagen), Eieren (3 < min 12)',
      stappen: ['Bekijk het productenoverzicht'],
      verwacht: 'Melk: badge "Verlopen"; brood: badge "Bijna verlopen" + dagen; eieren: voorraad rood. Bovenaan "Over de datum" en "Onder de minimumvoorraad".',
      async run({ pagina, ui }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        const melk = await p.textContent(rij(p, 'Campina Volle Melk (1L)'));
        const brood = await p.textContent(rij(p, 'Halfbruin gesneden brood'));
        const ei = await p.$eval(`${rij(p, 'Eieren 10st')} td:nth-child(4)`, (e) => e.getAttribute('style') || '');
        const cijfers = (await p.textContent('.cijfers')).replace(/\s+/g, ' ');
        return { ok: melk.includes('Verlopen') && brood.includes('Bijna verlopen') && ei.includes('red') && cijfers.includes('Over de datum'),
          werkelijk: `Melk "Verlopen": ${melk.includes('Verlopen') ? 'ja' : 'nee'}; brood "Bijna verlopen": ${brood.includes('Bijna verlopen') ? 'ja' : 'nee'}; eieren rood: ${ei.includes('red') ? 'ja' : 'nee'}; kerncijfers: "${cijfers.trim()}".` };
      },
    },
    {
      id: 'TC-65', functie: 'Categorie toevoegen en omschrijving wijzigen', eisen: ['PRD-4', 'PRD-5'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Nieuwe categorie "Huisdiervoeding" (kort "Dieren"); daarna omschrijving "Voer voor huisdieren"',
      stappen: ['Klik "Categorieën"', 'Klik "+ Nieuwe categorie" en vul naam en korte naam in', 'Open opnieuw en wijzig bij Huisdiervoeding de omschrijving'],
      verwacht: '"Categorie toegevoegd." en daarna "Categorie bijgewerkt."; filterknop "Dieren" verschijnt; 10 categorieën.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/producten');
        const voor = (await p.$$('.chips .chip')).length - 1;
        await p.click('button:has-text("Categorieën")');
        const shot = await foto(p, 'TC-65');
        await p.click('.modal >> text=+ Nieuwe categorie');
        await p.fill('.modal input[name=naam]', 'Huisdiervoeding'); await p.fill('.modal input[name=kort]', 'Dieren');
        await ui.dialoog(p, true);
        const t1 = await ui.toast(p);
        await p.click('button:has-text("Categorieën")');
        await p.click('.modal .irow:has-text("Huisdiervoeding") >> text=Bewerken');
        await p.fill('.modal input[name=oms]', 'Voer voor huisdieren');
        await ui.dialoog(p, true);
        const t2 = await ui.toast(p);
        const na = (await p.$$('.chips .chip')).length - 1;
        const oms = db.waarde("SELECT Omschrijving FROM Categorie WHERE Naam = 'Huisdiervoeding'");
        return { ok: t1 === 'Categorie toegevoegd.' && t2 === 'Categorie bijgewerkt.' && na === voor + 1 && oms === 'Voer voor huisdieren',
          werkelijk: `"${t1}", "${t2}"; categorieën ${voor} → ${na}; omschrijving in db: "${oms}".`, screenshot: shot };
      },
    },
    {
      id: 'TC-66', functie: 'Categorie met producten verwijderen via verplaatsen', eisen: ['PRD-5', 'VRD-3'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Categorie "Kaas, vleeswaren" (3 producten) → producten naar "Zuivel, plantaardig en eieren"',
      stappen: ['Open Categorieën en klik Verwijderen bij Kaas, vleeswaren; bevestig', 'Kies in het vervolgvenster de doelcategorie', 'Klik "Verplaatsen en verwijderen"'],
      verwacht: 'Venster "Deze categorie is niet leeg"; daarna "3 producten verplaatst. Categorie … is verwijderd."; producten hangen nu onder Zuivel.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/producten');
        await p.click('button:has-text("Categorieën")');
        await p.click('.modal .irow:has-text("Kaas, vleeswaren") >> text=Verwijderen');
        await ui.dialoog(p, true);
        const tekst = await ui.dialoogTekst(p);
        await p.selectOption('.modal select', { label: 'Zuivel, plantaardig en eieren' });
        const shot = await foto(p, 'TC-66');
        await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const weg = db.waarde("SELECT COUNT(*) FROM Categorie WHERE Naam = 'Kaas, vleeswaren'");
        const zuivel = db.waarde('SELECT COUNT(*) FROM Product WHERE Categorie_idCategorie = 3');
        return { ok: tekst.includes('niet leeg') && t.startsWith('3 producten verplaatst') && weg === '0' && zuivel === '6',
          werkelijk: `Venster: "${tekst.slice(0, 80)}…"; toast "${t}"; categorie in db: ${weg}; producten in Zuivel: ${zuivel}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-67', functie: 'Vrijwilliger: voorraad wel, productbeheer niet', eisen: ['GBR-4'], soort: 'Beveiliging', testvorm: 'Functioneel (UI) + API',
      rol: 'Vrijwilliger', testdata: 'a.degroot',
      stappen: ['Open Producten als vrijwilliger', 'Kijk welke knoppen er zijn', 'Probeer POST /api/producten en DELETE /api/producten/14'],
      verwacht: 'Geen "+ Nieuw product", "Categorieën", bewerken of verwijderen; wel voorraad corrigeren. API: 403 en 403.',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/producten');
        const nieuw = await p.$('text=+ Nieuw product');
        const bewerk = await p.$$('button[title=Bewerken]');
        const corr = await p.$$('button[title="Voorraad corrigeren"]');
        const V = await cookieVan('a.degroot');
        const a = await api(V, 'POST', '/producten', { naam: 'X' });
        const b = await api(V, 'DELETE', '/producten/14');
        return { ok: !nieuw && bewerk.length === 0 && corr.length === 20 && a.status === 403 && b.status === 403,
          werkelijk: `Nieuw-knop: ${nieuw ? 'ja' : 'nee'}; bewerkknoppen: ${bewerk.length}; corrigeerknoppen: ${corr.length}; API: ${a.status}, ${b.status}.` };
      },
    },
    {
      id: 'TC-68', functie: 'Rechten categoriebeheer', eisen: ['PRD-5', 'GBR-3'], soort: 'Beveiliging', testvorm: 'Technisch (API)',
      rol: 'Magazijn, Administratie', testdata: 'POST /api/categorieen {naam: "Testcat"}',
      stappen: ['Magazijnmedewerker maakt een categorie aan', 'Administratie probeert hetzelfde'],
      verwacht: 'Magazijn 201; administratie 403.',
      async run({ api, cookieVan }) {
        const m = await api(await cookieVan('r.veenstra'), 'POST', '/categorieen', { naam: 'Testcat' });
        const a = await api(await cookieVan('m.snel'), 'POST', '/categorieen', { naam: 'Testcat 2' });
        return { ok: m.status === 201 && a.status === 403, werkelijk: `Magazijn ${m.status}; administratie ${a.status}.` };
      },
    },
  ],
};
