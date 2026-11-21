/** Module G — Voedselpakketten samenstellen. */
async function kies(p, klantId) {
  await p.click(`[data-klant="${klantId}"]`);
  await p.waitForSelector('.profiel h3');
  await p.waitForTimeout(300);
  // eventuele melding "bestaand pakket geopend" opruimen, zodat de volgende toast klopt
  await p.evaluate(() => document.querySelectorAll('.toast').forEach((x) => x.remove()));
}

module.exports = {
  onderdeel: 'G. Voedselpakketten samenstellen',
  cases: [
    {
      id: 'TC-69', functie: 'Weekoverzicht: wie heeft nog een pakket nodig + advies', eisen: ['PAK-4', 'KLT-4', 'WRK-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: '8 geaccepteerde gezinnen; 3 hebben deze week al een pakket; Familie Veldhuis = 6 personen',
      stappen: ['Ga naar Voedselpakketten', 'Bekijk de groepen "Pakket nodig" en "Al een pakket deze week"'],
      verwacht: '"Pakket nodig (5)" en "Al een pakket deze week (3)"; per gezin aantal personen en adviesaantal (Veldhuis: 6 personen, advies 28); producten die binnen 3 dagen verlopen krijgen het label "THT over … dagen".',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        const groepen = await p.$$eval('.kgroep', (e) => e.map((x) => x.textContent.trim()));
        const veldhuis = (await p.textContent('[data-klant="7"]')).replace(/\s+/g, ' ');
        const tht = await p.$$eval('.pitem .pil2', (e) => e.map((x) => x.textContent.trim()).filter((t) => t.startsWith('THT over')));
        return { ok: groepen[0] === 'Pakket nodig (5)' && groepen[1] === 'Al een pakket deze week (3)' && veldhuis.includes('6 personen · advies 28') && tht.length > 0,
          werkelijk: `${groepen.join(' / ')}; Veldhuis: "${veldhuis.trim()}"; THT-labels: ${tht.length} (bijv. "${tht[0]}").`, screenshot: await foto(p, 'TC-69') };
      },
    },
    {
      id: 'TC-70', functie: 'Gezinsprofiel met allergieën en uitzondering', eisen: ['PAK-1', 'KLT-1', 'KLT-7'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Familie Bakker: Glutenvrij (2/5), Notenallergie met uitzondering',
      stappen: ['Klik op Familie Bakker'],
      verwacht: 'Profiel met de wensen als labels (aantal personen per wens), de uitzondering met reden, en alle 5 gezinsleden met relatie en leeftijd.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 1);
        const labels = await p.$$eval('.profiel .pil2', (e) => e.map((x) => x.textContent.replace(/\s+/g, ' ').trim()));
        const uitz = (await p.textContent('.profiel .melding.amber')).replace(/\s+/g, ' ').trim();
        const leden = (await p.$$('.profiel .irow')).length;
        return { ok: labels.length === 2 && uitz.includes('Uitzondering op Notenallergie') && leden === 5,
          werkelijk: `Labels: ${labels.join(' | ')}; "${uitz.slice(0, 70)}…"; ${leden} gezinsleden.`, screenshot: await foto(p, 'TC-70') };
      },
    },
    {
      id: 'TC-71', functie: 'Voorraad gefilterd op wat bij het gezin past', eisen: ['PAK-1', 'KLT-6'], soort: 'Positief + alternatief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'S. El Jafari (notenallergie, streng); Pindakaas bevat pinda,noten',
      stappen: ['Kies S. El Jafari', 'Zoek Pindakaas met het filter aan', 'Zet "Gefilterd op compatibiliteit" uit'],
      verwacht: 'Met filter: Pindakaas staat er niet tussen. Zonder filter: Pindakaas staat er, rood gemarkeerd, label "Allergie: Notenallergie (2×)", knop "Mag niet" (uitgeschakeld).',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 5);
        const met = await p.$('[data-voeg="9"]');
        await p.uncheck('.vinkje input');
        const knop = await p.waitForSelector('[data-voeg="9"]');
        const tekst = await knop.textContent(); const uit = await knop.isDisabled();
        const label = (await p.textContent('.pitem.bots:has([data-voeg="9"]) .pillen')).replace(/\s+/g, ' ').trim();
        await p.$eval('[data-voeg="9"]', (e) => e.scrollIntoView());
        return { ok: !met && uit && tekst.trim() === 'Mag niet' && label.includes('Allergie: Notenallergie'),
          werkelijk: `Met filter zichtbaar: ${met ? 'ja' : 'nee'}; zonder filter: knop "${tekst.trim()}" (uitgeschakeld: ${uit}), label "${label}".`, screenshot: await foto(p, 'TC-71') };
      },
    },
    {
      id: 'TC-72', functie: 'Producten toevoegen, aantal wijzigen en verwijderen', eisen: ['PAK-1', 'PAK-5'], soort: 'Positief + grensgeval', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Mevr. Jansen; Eieren 10st (voorraad 3) en Rijst 1kg',
      stappen: ['Kies Mevr. Jansen', 'Voeg Eieren 3× toe', 'Klik bij Eieren op "+" (4e)', 'Klik "−"', 'Voeg Rijst toe en verwijder Rijst met ×'],
      verwacht: 'Teller "3 van 8 items"; de 4e wordt geweigerd met "Er is niet genoeg voorraad van Eieren 10st."; na "−" 2 eieren; Rijst verdwijnt na ×.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 3);
        for (let i = 0; i < 3; i++) await p.click('[data-voeg="17"]');
        const teller = (await p.textContent('.card:has(.balk) .body div')).trim();
        await p.click('.mandje:has-text("Eieren") button[aria-label="Eén meer"]');
        const t = await ui.toast(p);
        await p.click('.mandje:has-text("Eieren") button[aria-label="Eén minder"]');
        const aantal = (await p.textContent('.mandje:has-text("Eieren") .aantalknop span')).trim();
        await p.click('[data-voeg="12"]');
        const shot = await foto(p, 'TC-72');
        await p.click('.mandje:has-text("Rijst") button[title=Verwijderen]');
        const rijst = await p.$('.mandje:has-text("Rijst")');
        return { ok: teller === '3 van 8 items toegevoegd' && t === 'Er is niet genoeg voorraad van Eieren 10st.' && aantal === '2' && !rijst,
          werkelijk: `"${teller}"; 4e ei: "${t}"; na −: ${aantal}; Rijst na ×: ${rijst ? 'nog aanwezig' : 'weg'}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-73', functie: 'Harde blokkade bij ernstige allergie (ook via omweg)', eisen: ['PAK-1', 'KLT-6', 'TEC-6'], soort: 'Beveiliging', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger', testdata: 'POST /api/pakketten: S. El Jafari + Pindakaas, forceer = true',
      stappen: ['Stuur een pakket met Pindakaas voor S. El Jafari en zet forceer aan (omzeilt de waarschuwing)'],
      verwacht: '409 reden "geblokkeerd" met uitleg "botst met Notenallergie van 2 van de 2 …"; er wordt geen pakket opgeslagen.',
      async run({ api, cookieVan, db }) {
        const voor = db.waarde('SELECT COUNT(*) FROM Pakket');
        const r = await api(await cookieVan('a.degroot'), 'POST', '/pakketten', { klant: 5, status: 'Concept', forceer: true, regels: [{ product: 9, aantal: 1 }] });
        const na = db.waarde('SELECT COUNT(*) FROM Pakket');
        return { ok: r.status === 409 && r.d.reden === 'geblokkeerd' && voor === na, werkelijk: `${r.status} ${r.d.reden}: "${r.d.geblokkeerd?.[0]?.product} ${r.d.geblokkeerd?.[0]?.uitleg}"; pakketten voor/na ${voor}/${na}.` };
      },
    },
    {
      id: 'TC-74', functie: 'Product over de houdbaarheidsdatum geblokkeerd', eisen: ['PAK-1', 'VRD-2'], soort: 'Negatief', testvorm: 'Functioneel (UI) + API',
      rol: 'Vrijwilliger', testdata: 'Campina Volle Melk (1L), THT verstreken; Familie Veldhuis (geen lactose-wens)',
      stappen: ['Kies Familie Veldhuis en zoek "Volle Melk" met het filter uit', 'Stuur via de API een pakket met dit product en forceer = true'],
      verwacht: 'Het verlopen product staat niet in de voorraadlijst; de API geeft 409 geblokkeerd "is over de houdbaarheidsdatum".',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 7);
        await p.uncheck('.vinkje input');
        const zichtbaar = await p.$('[data-voeg="1"]');
        const r = await api(await cookieVan('a.degroot'), 'POST', '/pakketten', { klant: 7, status: 'Concept', forceer: true, regels: [{ product: 1, aantal: 1 }] });
        return { ok: !zichtbaar && r.status === 409 && r.d.geblokkeerd?.[0]?.soort === 'verlopen',
          werkelijk: `In lijst: ${zichtbaar ? 'ja' : 'nee'}; API: ${r.status} "${r.d.geblokkeerd?.[0]?.product} ${r.d.geblokkeerd?.[0]?.uitleg}".` };
      },
    },
    {
      id: 'TC-75', functie: 'Waarschuwing bij dieetwens + bewust toch opslaan', eisen: ['PAK-1', 'KLT-6'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Familie Öztürk (Hatice: glutenvrij) + Halfbruin gesneden brood (gluten) en Rijst',
      stappen: ['Kies Familie Öztürk, zet het filter uit', 'Voeg brood en rijst toe', 'Klik "Pakket klaarzetten"', 'Kies in de waarschuwing "Toch opslaan"'],
      verwacht: 'Rode melding "Let op: … past niet bij de wensen"; dialoog "Past niet bij de wensen van dit gezin" met uitleg (1 van de 3, Hatice); na bevestigen "Pakket is klaargezet voor Familie Öztürk."',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 6);
        await p.uncheck('.vinkje input');
        await p.click('[data-voeg="3"]'); await p.click('[data-voeg="12"]');
        await p.click('button:has-text("Pakket klaarzetten")');
        const tekst = await ui.dialoogTekst(p);
        const shot = await foto(p, 'TC-75');
        await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const status = db.waarde("SELECT Status FROM Pakket WHERE Klant_idKlant = 6 ORDER BY idPakket DESC LIMIT 1");
        return { ok: tekst.includes('Past niet bij de wensen') && tekst.includes('Hatice') && t.startsWith('Pakket is klaargezet') && status === 'Klaar',
          werkelijk: `Dialoog: "${tekst.slice(0, 160)}…"; toast "${t}"; status in db: ${status}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-76', functie: 'Uitzondering heft de harde blokkade op', eisen: ['KLT-6', 'PAK-7'], soort: 'Alternatief pad', testvorm: 'Functioneel (UI) + API',
      rol: 'Vrijwilliger', testdata: 'Familie Bakker (notenallergie mét uitzondering) + Pindakaas',
      stappen: ['Kies Familie Bakker, filter uit, bekijk Pindakaas', 'Sla via de API op zonder en met forceer', 'Open de pakbon'],
      verwacht: 'Pindakaas is toe te voegen (label "mag toch"); zonder forceer 409 "conflict" (waarschuwing, geen blokkade); met forceer 201; de pakbon toont de uitzondering met reden.',
      async run({ pagina, ui, api, cookieVan, BASIS }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 1);
        await p.uncheck('.vinkje input');
        const knop = await p.waitForSelector('[data-voeg="9"]');
        const aan = !(await knop.isDisabled());
        const label = (await p.textContent('.pitem:has([data-voeg="9"]) .pillen')).replace(/\s+/g, ' ');
        const V = await cookieVan('a.degroot');
        const body = { klant: 1, status: 'Klaar', regels: [{ product: 9, aantal: 1 }, { product: 12, aantal: 2 }] };
        const zonder = await api(V, 'POST', '/pakketten', body);
        const met = await api(V, 'POST', '/pakketten', { ...body, forceer: true });
        await p.goto(BASIS + '/pakbon/' + met.d.id); await p.waitForSelector('.uitzondering');
        const bon = (await p.textContent('.uitzondering')).replace(/\s+/g, ' ').trim();
        return { ok: aan && label.includes('mag toch') && zonder.status === 409 && zonder.d.reden === 'conflict' && met.status === 201 && bon.includes('Bart eet het niet'),
          werkelijk: `Knop actief: ${aan}; label "${label.trim()}"; zonder forceer ${zonder.status} ${zonder.d.reden}; met forceer ${met.status}; pakbon: "${bon}".` };
      },
    },
    {
      id: 'TC-77', functie: 'Concept opslaan en later verder werken', eisen: ['PAK-1', 'PAK-5'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Familie Veldhuis: 2× Rijst 1kg, 1× Sinaasappelsap',
      stappen: ['Kies Veldhuis, voeg producten toe en klik "Concept opslaan"', 'Ververs de pagina en kies Veldhuis opnieuw'],
      verwacht: '"Concept opgeslagen."; na verversen melding "Bestaand pakket van deze week geopend (concept)." en dezelfde inhoud; status in de lijst "Concept".',
      async run({ pagina, ui, foto }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 7);
        await p.click('[data-voeg="12"]'); await p.click('[data-voeg="12"]'); await p.click('[data-voeg="16"]');
        await p.click('button:has-text("Concept opslaan")');
        const t1 = await ui.toast(p);
        await ui.naar(p, '/pakketten');
        await p.click('[data-klant="7"]');
        const t2 = await ui.toast(p);
        const rijst = (await p.textContent('.mandje:has-text("Rijst") .aantalknop span')).trim();
        const pil = (await p.textContent('[data-klant="7"] .pil2')).trim();
        return { ok: t1 === 'Concept opgeslagen.' && t2.includes('concept') && rijst === '2' && pil === 'Concept',
          werkelijk: `"${t1}"; na verversen: "${t2}"; Rijst ${rijst}×; status in lijst "${pil}".`, screenshot: await foto(p, 'TC-77') };
      },
    },
    {
      id: 'TC-78', functie: 'Pakket klaarzetten: voorraad af, nummer en datum', eisen: ['PAK-2', 'PAK-3', 'PAK-5', 'VRD-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Mevr. Jansen: 2× Rijst 1kg (voorraad 1200), 1× Koffiepads (520)',
      stappen: ['Kies Mevr. Jansen, voeg de producten toe', 'Klik "Pakket klaarzetten"'],
      verwacht: '"Pakket is klaargezet voor Mevr. Jansen."; voorraad Rijst −2 en Koffiepads −1; nieuw pakketnummer = hoogste + 1; samenstellingsdatum = vandaag; samensteller Ank de Groot.',
      async run({ pagina, ui, db }) {
        const hoogste = Number(db.waarde('SELECT MAX(idPakket) FROM Pakket'));
        const rijstVoor = Number(db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12'));
        const koffieVoor = Number(db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 15'));
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 3);
        await p.click('[data-voeg="12"]'); await p.click('[data-voeg="12"]'); await p.click('[data-voeg="15"]');
        await p.click('button:has-text("Pakket klaarzetten")');
        const t = await ui.toast(p);
        const rij = db.waarde("SELECT CONCAT(p.idPakket,'|',p.Status,'|',p.Samenstellingsdatum = CURDATE(),'|',g.Naam) FROM Pakket p JOIN Gebruiker g ON g.idGebruiker = p.Samengesteld_door WHERE Klant_idKlant = 3 ORDER BY idPakket DESC LIMIT 1").split('|');
        const rijst = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12');
        const koffie = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 15');
        return { ok: t === 'Pakket is klaargezet voor Mevr. Jansen.' && Number(rij[0]) === hoogste + 1 && rij[1] === 'Klaar' && rij[2] === '1' && Number(rijst) === rijstVoor - 2 && Number(koffie) === koffieVoor - 1,
          werkelijk: `"${t}"; pakket #${rij[0]} (vorige hoogste ${hoogste}), status ${rij[1]}, datum vandaag: ${rij[2] === '1' ? 'ja' : 'nee'}, door ${rij[3]}; Rijst ${rijstVoor} → ${rijst}, Koffiepads ${koffieVoor} → ${koffie}.` };
      },
    },
    {
      id: 'TC-79', functie: 'Maximaal één pakket per gezin per week', eisen: ['KLT-4', 'PAK-4'], soort: 'Negatief', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger', testdata: 'Gezin El Hassani heeft deze week al pakket #2862',
      stappen: ['POST /api/pakketten voor El Hassani'],
      verwacht: '409 reden "dubbel_deze_week": "Gezin El Hassani heeft deze week al een pakket."',
      async run({ api, cookieVan }) {
        const r = await api(await cookieVan('a.degroot'), 'POST', '/pakketten', { klant: 2, status: 'Concept', regels: [{ product: 12, aantal: 1 }] });
        return { ok: r.status === 409 && r.d.reden === 'dubbel_deze_week', werkelijk: `${r.status} ${r.d.reden}: "${r.d.fout}"` };
      },
    },
    {
      id: 'TC-80', functie: 'Te weinig voorraad: niets wordt opgeslagen', eisen: ['PAK-1', 'VRD-2', 'TEC-3'], soort: 'Grensgeval', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger', testdata: 'S. El Jafari: 50× Eieren 10st (voorraad 3)',
      stappen: ['POST /api/pakketten met meer eieren dan er zijn'],
      verwacht: '409 "te_weinig_voorraad" met "50 nodig, 3 op voorraad"; voorraad blijft 3 en er komt geen leeg pakket in de database (transactie teruggedraaid).',
      async run({ api, cookieVan, db }) {
        const voor = db.waarde('SELECT COUNT(*) FROM Pakket');
        const r = await api(await cookieVan('a.degroot'), 'POST', '/pakketten', { klant: 5, status: 'Concept', regels: [{ product: 17, aantal: 50 }] });
        const na = db.waarde('SELECT COUNT(*) FROM Pakket');
        const ei = db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 17');
        const tk = r.d.tekort?.[0];
        return { ok: r.status === 409 && r.d.reden === 'te_weinig_voorraad' && voor === na && ei === '3',
          werkelijk: `${r.status} ${r.d.reden}: ${tk?.product} ${tk?.gevraagd} nodig, ${tk?.voorraad} op voorraad; pakketten voor/na ${voor}/${na}; voorraad eieren ${ei}.` };
      },
    },
    {
      id: 'TC-81', functie: 'Pakket verwijderen: producten terug in de voorraad', eisen: ['PAK-5', 'VRD-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Concept van Familie Veldhuis uit TC-77 (2× Rijst, 1× Sinaasappelsap)',
      stappen: ['Kies Familie Veldhuis (concept wordt geopend)', 'Klik "Pakket verwijderen" en bevestig'],
      verwacht: '"Pakket verwijderd, de producten staan weer in de voorraad."; Rijst +2 en Sinaasappelsap +1 terug; Veldhuis weer bij "Pakket nodig".',
      async run({ pagina, ui, db }) {
        const rijstVoor = Number(db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12'));
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 7);
        await p.click('button:has-text("Pakket verwijderen")'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const rijstNa = Number(db.waarde('SELECT Aantal_voorraad FROM Product WHERE idProduct = 12'));
        const pil = (await p.textContent('[data-klant="7"] .pil2')).trim();
        return { ok: t.startsWith('Pakket verwijderd') && rijstNa === rijstVoor + 2 && pil === 'Pakket nodig',
          werkelijk: `"${t}"; Rijst ${rijstVoor} → ${rijstNa}; status Veldhuis "${pil}".` };
      },
    },
    {
      id: 'TC-82', functie: 'Leeg pakket kan niet klaargezet worden', eisen: ['PAK-5'], soort: 'Negatief', testvorm: 'Technisch (API) + UI',
      rol: 'Vrijwilliger', testdata: 'Familie Veldhuis, geen producten',
      stappen: ['Kies een gezin zonder producten toe te voegen (knoppen controleren)', 'POST /api/pakketten met status Klaar en lege regels'],
      verwacht: 'Knoppen "Concept opslaan" en "Pakket klaarzetten" zijn uitgeschakeld; API 422 "Een pakket dat klaar is moet minimaal één product bevatten."',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/pakketten');
        await kies(p, 7);
        const uit = await p.isDisabled('button:has-text("Pakket klaarzetten")');
        const r = await api(await cookieVan('a.degroot'), 'POST', '/pakketten', { klant: 7, status: 'Klaar', regels: [] });
        return { ok: uit && r.status === 422 && !!r.d.fouten?.regels, werkelijk: `Knop uitgeschakeld: ${uit}; API ${r.status} "${r.d.fouten?.regels}".` };
      },
    },
    {
      id: 'TC-83', functie: 'Pakbon / sticker afdrukken', eisen: ['PAK-6', 'PAK-7', 'PAK-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Pakket #2862 (Gezin El Hassani, 4 personen, Halal)',
      stappen: ['Open de pakbon van pakket 2862 (knop "Pakbon afdrukken")', 'Bekijk de afdrukweergave'],
      verwacht: 'Pakketnummer, datum, klantnaam en klantnummer, gezinsgrootte, dieetwensen, inhoud met aantallen/categorie/locatie, en een handtekeningregel. Knoppen verdwijnen bij afdrukken.',
      async run({ pagina, BASIS, foto }) {
        const p = await pagina('a.degroot');
        await p.goto(BASIS + '/pakbon/2862');
        await p.waitForSelector('.bonkop');
        const t = (await p.textContent('.bon')).replace(/\s+/g, ' ');
        const regels = (await p.$$('.regel')).length - 1;
        await p.emulateMedia({ media: 'print' });
        const knoppen = await p.isVisible('.nietprint');
        const shot = await foto(p, 'TC-83');
        const ok = ['Pakket #2862', 'Gezin El Hassani', 'K-002', '4 personen', 'Halal', 'Handtekening voor ontvangst'].every((x) => t.includes(x)) && regels > 0 && !knoppen;
        return { ok, werkelijk: `Pakbon bevat nummer, klant, K-002, 4 personen, Halal, ${regels} productregels en handtekening; knoppen zichtbaar bij afdrukken: ${knoppen ? 'ja' : 'nee'}.`, screenshot: shot };
      },
    },
  ],
};
