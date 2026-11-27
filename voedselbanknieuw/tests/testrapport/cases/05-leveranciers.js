/** Module E — Leveranciers en leveringen. */
async function openLev(p, naam) {
  if (!(await p.$(`.grp.open:has-text("${naam}")`))) await p.click(`.grp:has-text("${naam}") .trow`);
  await p.waitForTimeout(150);
}
const namen = (p) => p.$$eval('.grp .nm', (e) => e.map((x) => x.textContent.trim()));

module.exports = {
  onderdeel: 'E. Leveranciers en leveringen',
  cases: [
    {
      id: 'TC-45', functie: 'Leveranciersoverzicht: zoeken, filteren en sorteren', eisen: ['LEV-2', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: '5 leveranciers; zoekterm "bakkerij"; filter Zuivel; sorteer op bedrijfsnaam',
      stappen: ['Ga naar Leveranciers', 'Zoek op "bakkerij"', 'Wis en kies Categorie: Zuivel', 'Wis en kies Sorteer: bedrijfsnaam'],
      verwacht: 'Zoeken vindt Bakkerij van de Ven; filter toont VDB Zuivel; sorteren zet de actieve leveranciers op alfabet (inactieve onderaan).',
      async run({ pagina, ui, foto }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/leveranciers');
        const alle = await namen(p);
        await p.fill('.zoek input', 'bakkerij'); await p.waitForTimeout(150); const z = await namen(p);
        await p.fill('.zoek input', ''); await p.selectOption('.zoekbalk select >> nth=0', 'Zuivel'); await p.waitForTimeout(150); const f = await namen(p);
        await p.selectOption('.zoekbalk select >> nth=0', ''); await p.selectOption('.zoekbalk select >> nth=1', 'naam'); await p.waitForTimeout(150); const s = await namen(p);
        return { ok: alle.length === 5 && z.join() === 'Bakkerij van de Ven' && f.join() === 'VDB Zuivel' && s[s.length - 1] === 'Sligro Tilburg' && s[0] === 'Albert Heijn Maaskant',
          werkelijk: `Alle: ${alle.length}; zoeken → ${z}; filter Zuivel → ${f}; gesorteerd: ${s.join(', ')}.`, screenshot: await foto(p, 'TC-45') };
      },
    },
    {
      id: 'TC-46', functie: 'Leverancier toevoegen met volgende levering', eisen: ['LEV-1', 'LEV-3', 'LEV-4'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Jumbo Maaskantje, contact Sanne de Wit, 0412-555111, sanne@jumbo-test.nl, Kerkstraat 5, 5492 AA; volgende levering over 3 dagen 10:30',
      stappen: ['Klik "+ Nieuwe leverancier"', 'Vul alle gegevens en de volgende levering (datum + tijd) in', 'Opslaan'],
      verwacht: 'Melding "Leverancier toegevoegd."; in de lijst met "Volgende levering … om 10:30".',
      async run({ pagina, ui, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/leveranciers');
        const d = new Date(Date.now() + 3 * 86400000);
        const iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        await p.click('text=+ Nieuwe leverancier');
        await p.fill('#l-naam', 'Jumbo Maaskantje'); await p.fill('#l-contact', 'Sanne de Wit'); await p.fill('#l-tel', '0412-555111');
        await p.fill('#l-mail', 'sanne@jumbo-test.nl'); await p.fill('#l-adres', 'Kerkstraat 5'); await p.fill('#l-pc', '5492 AA');
        await p.fill('#l-datum', iso); await p.fill('#l-tijd', '10:30');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const regel = (await p.textContent('.grp:has-text("Jumbo Maaskantje") .gepland')).trim();
        return { ok: t === 'Leverancier toegevoegd.' && regel.includes('10:30'), werkelijk: `Toast "${t}"; lijst toont "${regel}".`, screenshot: await foto(p, 'TC-46') };
      },
    },
    {
      id: 'TC-47', functie: 'Leverancier: validatie en dubbele naam', eisen: ['LEV-3', 'TEC-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: '1) leeg formulier  2) naam "Albert Heijn Maaskant", e-mail "fout", telefoon "12", postcode "AB12"',
      stappen: ['Klik "+ Nieuwe leverancier" en direct Opslaan', 'Vul de foute gegevens in en sla op'],
      verwacht: 'Leeg: meldingen bij bedrijfsnaam, adres, contactpersoon, e-mail en telefoon. Daarna: "Er bestaat al een leverancier met deze naam.", e-mail, telefoon en postcode afgekeurd.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/leveranciers');
        await p.click('text=+ Nieuwe leverancier');
        await ui.drawerOpslaan(p);
        const leeg = await ui.veldfouten(p);
        await p.fill('#l-naam', 'Albert Heijn Maaskant'); await p.fill('#l-contact', 'X'); await p.fill('#l-adres', 'Y 1');
        await p.fill('#l-mail', 'fout'); await p.fill('#l-tel', '12'); await p.fill('#l-pc', 'AB12');
        await ui.drawerOpslaan(p);
        const fout = await ui.veldfouten(p);
        return { ok: leeg.length === 5 && fout.length === 4 && fout.includes('Er bestaat al een leverancier met deze naam.'),
          werkelijk: `Leeg: ${leeg.join(' / ')}. Fout: ${fout.join(' / ')}.`, screenshot: await foto(p, 'TC-47') };
      },
    },
    {
      id: 'TC-48', functie: 'Leverancier bewerken', eisen: ['LEV-1'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Bakkerij van de Ven: contactpersoon → "Jan van de Ven"',
      stappen: ['Klap Bakkerij van de Ven uit en klik Bewerken', 'Wijzig de contactpersoon', 'Opslaan'],
      verwacht: 'Melding "Leverancier bijgewerkt."; de nieuwe contactpersoon staat in de lijst.',
      async run({ pagina, ui }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/leveranciers');
        await openLev(p, 'Bakkerij van de Ven');
        await p.click('.grp.open >> text=Bewerken');
        await p.fill('#l-contact', 'Jan van de Ven');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const rij = await p.textContent('.grp:has-text("Bakkerij van de Ven") .trow');
        return { ok: t === 'Leverancier bijgewerkt.' && rij.includes('Jan van de Ven'), werkelijk: `Toast "${t}"; rij toont Jan van de Ven: ${rij.includes('Jan van de Ven') ? 'ja' : 'nee'}.` };
      },
    },
    {
      id: 'TC-49', functie: 'Leverancier op non-actief en weer activeren', eisen: ['LEV-1'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'VDB Zuivel',
      stappen: ['Klap VDB Zuivel uit en klik "Op non-actief zetten"', 'Controleer de knoppen', 'Klik "Weer activeren"'],
      verwacht: 'Status "Inactief" en geen knop "Levering inboeken"; na activeren weer "Actief" met de knop.',
      async run({ pagina, ui }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/leveranciers');
        await openLev(p, 'VDB Zuivel');
        await p.click('.grp.open >> text=Op non-actief zetten');
        const t1 = await ui.toast(p);
        await openLev(p, 'VDB Zuivel');
        const s1 = await p.textContent('.grp:has-text("VDB Zuivel") .trow .pil2');
        const knop1 = await p.$('.grp.open:has-text("VDB Zuivel") >> text=Levering inboeken');
        await p.click('.grp.open >> text=Weer activeren');
        const t2 = await ui.toast(p);
        const s2 = await p.textContent('.grp:has-text("VDB Zuivel") .trow .pil2');
        return { ok: s1 === 'Inactief' && !knop1 && s2 === 'Actief', werkelijk: `"${t1}" → ${s1}, inboekknop: ${knop1 ? 'ja' : 'nee'}; "${t2}" → ${s2}.` };
      },
    },
    {
      id: 'TC-50', functie: 'Leverancier verwijderen (met en zonder historie)', eisen: ['LEV-1', 'RAP-1'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Sligro Tilburg (0 leveringen) en Albert Heijn Maaskant (2 leveringen)',
      stappen: ['Verwijder Sligro Tilburg en bevestig', 'Verwijder Albert Heijn Maaskant en bevestig', 'Kies "Laten staan"'],
      verwacht: 'Sligro verdwijnt. Albert Heijn: melding dat de leveringsgeschiedenis niet mag verdwijnen + advies non-actief; leverancier blijft staan.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/leveranciers');
        await openLev(p, 'Sligro Tilburg');
        await p.click('.grp.open >> text=Verwijderen'); await ui.dialoog(p, true);
        const t1 = await ui.toast(p);
        await openLev(p, 'Albert Heijn Maaskant');
        await p.click('.grp.open:has-text("Albert Heijn") >> text=Verwijderen'); await ui.dialoog(p, true);
        const tekst = await ui.dialoogTekst(p);
        const shot = await foto(p, 'TC-50');
        await ui.dialoog(p, false);
        const s = db.waarde("SELECT COUNT(*) FROM Leverancier WHERE Bedrijfsnaam = 'Sligro Tilburg'");
        const ah = db.waarde("SELECT COUNT(*) FROM Leverancier WHERE Bedrijfsnaam = 'Albert Heijn Maaskant'");
        return { ok: t1 === 'Leverancier verwijderd.' && s === '0' && ah === '1' && tekst.includes('2 geregistreerde'),
          werkelijk: `Sligro: "${t1}" (in db: ${s}). Albert Heijn: "${tekst.slice(0, 120)}…" (in db: ${ah}).`, screenshot: shot };
      },
    },
    {
      id: 'TC-51', functie: 'Levering inboeken verhoogt de voorraad', eisen: ['LEV-1', 'VRD-2'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Lidl Maaskantje levert 10× Rijst 1kg (voorraad 1200) en 6× Eieren 10st (voorraad 3)',
      stappen: ['Klap Lidl Maaskantje uit en klik "Levering inboeken"', 'Kies Rijst 1kg, aantal 10; voeg regel toe: Eieren 10st, aantal 6', 'Klik "Levering boeken"'],
      verwacht: 'Melding "Levering geboekt en voorraad bijgewerkt."; Rijst 1210, Eieren 9; levering staat in de geschiedenis met "Ingeboekt door Rob Veenstra".',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/leveranciers');
        await openLev(p, 'Lidl Maaskantje');
        await p.click('.grp.open >> text=Levering inboeken');
        await p.selectOption('.drawer.open .regel >> nth=0 >> [data-v=product]', { label: 'Rijst 1kg (1200 st.)' });
        await p.fill('.drawer.open .regel >> nth=0 >> [data-v=aantal]', '10');
        await p.click('.drawer.open >> text=+ Product toevoegen');
        await p.selectOption('.drawer.open .regel >> nth=1 >> [data-v=product]', { label: 'Eieren 10st (3 st.)' });
        await p.fill('.drawer.open .regel >> nth=1 >> [data-v=aantal]', '6');
        const shot = await foto(p, 'TC-51');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const rijst = db.waarde("SELECT Aantal_voorraad FROM Product WHERE Productnaam = 'Rijst 1kg'");
        const eieren = db.waarde("SELECT Aantal_voorraad FROM Product WHERE Productnaam = 'Eieren 10st'");
        await openLev(p, 'Lidl Maaskantje');
        const hist = (await p.textContent('.grp.open .inner .irow')).replace(/\s+/g, ' ');
        return { ok: t === 'Levering geboekt en voorraad bijgewerkt.' && rijst === '1210' && eieren === '9' && hist.includes('Rob Veenstra'),
          werkelijk: `Toast "${t}"; Rijst ${rijst}, Eieren ${eieren}; geschiedenis: "${hist.trim()}".`, screenshot: shot };
      },
    },
    {
      id: 'TC-52', functie: 'Levering zonder producten wordt geweigerd', eisen: ['LEV-1'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Levering zonder regels',
      stappen: ['Open "Levering inboeken"', 'Verwijder de productregel', 'Klik "Levering boeken"'],
      verwacht: 'Melding "Voeg minimaal één product toe."; er wordt geen levering opgeslagen.',
      async run({ pagina, ui, db }) {
        const p = await pagina('r.veenstra');
        await ui.naar(p, '/leveranciers');
        const voor = db.waarde('SELECT COUNT(*) FROM Levering');
        await openLev(p, 'VDB Zuivel');
        await p.click('.grp.open >> text=Levering inboeken');
        await p.click('.drawer.open .regel .icoonknop');
        await ui.drawerOpslaan(p);
        const m = (await p.textContent('.drawer.open .melding.rood')).trim();
        const na = db.waarde('SELECT COUNT(*) FROM Levering');
        return { ok: m === 'Voeg minimaal één product toe.' && voor === na, werkelijk: `Melding "${m}"; leveringen voor/na: ${voor}/${na}.` };
      },
    },
    {
      id: 'TC-53', functie: 'Eerstvolgende levering op het dashboard', eisen: ['LEV-4'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Magazijnmedewerker', testdata: 'Testdata: Albert Heijn Maaskant heeft de eerstvolgende geplande levering',
      stappen: ['Open het dashboard als magazijnmedewerker'],
      verwacht: 'Kaart "Eerstvolgende levering" met bedrijfsnaam, datum en tijd; lijst "Verwachte leveringen" op datum gesorteerd.',
      async run({ pagina, foto }) {
        const p = await pagina('r.veenstra');
        await p.waitForSelector('.card.stat:has-text("Eerstvolgende levering")');
        const k = (await p.textContent('.card.stat:has-text("Eerstvolgende levering")')).replace(/\s+/g, ' ').trim();
        return { ok: /verwacht op \d\d-\d\d-\d{4} om \d\d:\d\d/.test(k), werkelijk: `Kaart: "${k}".`, screenshot: await foto(p, 'TC-53') };
      },
    },
    {
      id: 'TC-54', functie: 'Rechten leveranciers en leveringen', eisen: ['GBR-3', 'GBR-4'], soort: 'Beveiliging', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger, Magazijn', testdata: 'POST /api/leveringen en POST /api/leveranciers',
      stappen: ['Vrijwilliger boekt een levering', 'Vrijwilliger voegt een leverancier toe', 'Magazijnmedewerker boekt een levering'],
      verwacht: 'Vrijwilliger: 403 en 403. Magazijnmedewerker: 201.',
      async run({ api, cookieVan }) {
        const V = await cookieVan('a.degroot'); const M = await cookieVan('r.veenstra');
        const lev = { leverancier: 2, datum: '2026-11-19', regels: [{ product: 3, aantal: 1 }] };
        const a = await api(V, 'POST', '/leveringen', lev);
        const b = await api(V, 'POST', '/leveranciers', { bedrijfsnaam: 'X' });
        const c = await api(M, 'POST', '/leveringen', lev);
        return { ok: a.status === 403 && b.status === 403 && c.status === 201, werkelijk: `Vrijwilliger levering ${a.status}, leverancier ${b.status}; magazijn levering ${c.status}.` };
      },
    },
  ],
};
