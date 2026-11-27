/** Module D — Klanten- en gezinnenadministratie. */
async function openKlant(p, naam) {
  if (!(await p.$(`.grp.open:has-text("${naam}")`))) await p.click(`.grp:has-text("${naam}") .trow`);
  await p.waitForTimeout(150);
}

module.exports = {
  onderdeel: 'D. Klanten en gezinnen',
  cases: [
    {
      id: 'TC-31', functie: 'Klantenoverzicht, zoeken en statusfilter', eisen: ['KLT-1', 'TEC-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: '11 gezinnen; zoektermen "Bakker", "Bart" (gezinslid), "K-003"; filter "wacht op beoordeling"',
      stappen: ['Ga naar Klanten', 'Zoek op "Bakker", daarna op "Bart" en "K-003"', 'Wis de zoekterm en kies Status: wacht op beoordeling'],
      verwacht: 'Alle 11 gezinnen; "Bakker" en "Bart" vinden Familie Bakker; "K-003" vindt Mevr. Jansen; het filter toont de 2 wachtende aanvragen.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        const alle = (await p.$$('.grp')).length;
        const zoek = async (t) => { await p.fill('.zoek input', t); await p.waitForTimeout(150); return p.$$eval('.grp .nm', (e) => e.map((x) => x.textContent)); };
        const a = await zoek('Bakker'); const b = await zoek('Bart'); const c = await zoek('K-003');
        await zoek('');
        await p.selectOption('.zoekbalk select', 'wacht'); await p.waitForTimeout(150);
        const w = await p.$$eval('.grp .nm', (e) => e.map((x) => x.textContent));
        const shot = await foto(p, 'TC-31');
        return { ok: alle === 11 && a.join() === 'Familie Bakker' && b.join() === 'Familie Bakker' && c.join() === 'Mevr. Jansen' && w.length === 2,
          werkelijk: `Totaal ${alle}; "Bakker" → ${a}; "Bart" → ${b}; "K-003" → ${c}; filter wacht → ${w.join(', ')}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-32', functie: 'Gezinsdetails: leden, leeftijd, type en wensen', eisen: ['KLT-1', 'KLT-5', 'KLT-7'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Familie Bakker (5 personen, glutenvrij + notenallergie met uitzondering)',
      stappen: ['Klap Familie Bakker uit'],
      verwacht: 'Contactgegevens, 5 gezinsleden met relatie en leeftijd, per lid de dieetwens, en de uitzondering met reden.',
      async run({ pagina, ui, foto, api, cookieVan }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Familie Bakker');
        const leden = await p.$$eval('.grp.open .irow', (e) => e.length);
        const tekst = (await p.textContent('.grp.open .detail')).replace(/\s+/g, ' ');
        const k = (await api(await cookieVan('p.abraham'), 'GET', '/klanten')).d.klanten.find((x) => x.naam === 'Familie Bakker');
        const types = [...new Set(k.leden.map((l) => l.type))].join(', ');
        return { ok: leden === 5 && tekst.includes('Uitzondering op Notenallergie') && tekst.includes('Contact:'),
          werkelijk: `${leden} gezinsleden getoond; types uit geboortedatum: ${types}; uitzondering zichtbaar: ${tekst.includes('Uitzondering op Notenallergie') ? 'ja' : 'nee'}.`, screenshot: await foto(p, 'TC-32') };
      },
    },
    {
      id: 'TC-33', functie: 'Nieuwe klant registreren', eisen: ['KLT-1', 'KLT-2', 'KLT-4', 'KLT-5'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Familie Testers, Teststraat 1, 5492 TT, 0612345678, test@voorbeeld.nl; leden Tim (1985, hoofdaanvrager) en Saar (2025, kind)',
      stappen: ['Klik "+ Nieuwe klant"', 'Vul contactgegevens in', 'Vul twee gezinsleden in', 'Klik Opslaan'],
      verwacht: 'Melding "Klantgegevens opgeslagen."; gezin staat in de lijst met status "Wacht op beoordeling" en 2 personen; Tim is volwassene, Saar baby (≤ 2 jaar).',
      async run({ pagina, ui, foto, api, cookieVan }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await p.click('text=+ Nieuwe klant');
        await p.fill('#k-naam', 'Familie Testers'); await p.fill('#k-adres', 'Teststraat 1'); await p.fill('#k-postcode', '5492 TT');
        await p.fill('#k-tel', '0612345678'); await p.fill('#k-mail', 'test@voorbeeld.nl');
        await p.fill('.lid >> nth=0 >> [data-v=naam]', 'Tim Testers'); await p.fill('.lid >> nth=0 >> [data-v=gb]', '1985-04-12');
        await p.click('text=+ Gezinslid toevoegen');
        await p.fill('.lid >> nth=1 >> [data-v=naam]', 'Saar Testers'); await p.fill('.lid >> nth=1 >> [data-v=gb]', '2025-09-01');
        const shot = await foto(p, 'TC-33');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const pil = await p.textContent('.grp:has-text("Familie Testers") .pil2:not(.nieuw)');
        const k = (await api(await cookieVan('p.abraham'), 'GET', '/klanten')).d.klanten.find((x) => x.naam === 'Familie Testers');
        return { ok: t === 'Klantgegevens opgeslagen.' && pil.includes('Wacht op beoordeling') && k.grootte === 2 && k.leden.map((l) => l.type).sort().join() === 'Baby,Volwassene',
          werkelijk: `Toast "${t}"; status "${pil.trim()}"; ${k.grootte} personen; types: ${k.leden.map((l) => `${l.naam}=${l.type}`).join(', ')}; klantnummer ${k.nummer}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-34', functie: 'Nieuwe klant: validatie', eisen: ['KLT-2', 'TEC-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'postcode "12AB", e-mail "geen-email", geen gezinsleden',
      stappen: ['Klik "+ Nieuwe klant"', 'Vul naam en adres in, foute postcode en e-mail, geen gezinslid', 'Klik Opslaan'],
      verwacht: 'Veldmeldingen bij postcode ("Bijvoorbeeld 5492 AB."), telefoon, e-mail en melding "Voeg minimaal één gezinslid toe."; niets opgeslagen.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await p.click('text=+ Nieuwe klant');
        await p.fill('#k-naam', 'Familie Fout'); await p.fill('#k-adres', 'Foutstraat 2'); await p.fill('#k-postcode', '12AB'); await p.fill('#k-mail', 'geen-email');
        await ui.drawerOpslaan(p);
        const f = await ui.veldfouten(p);
        const leden = await p.textContent('.drawer.open .melding.rood').catch(() => '');
        const n = db.waarde("SELECT COUNT(*) FROM Klant WHERE Gezinsnaam = 'Familie Fout'");
        return { ok: f.includes('Bijvoorbeeld 5492 AB.') && f.includes('Vul een geldig e-mailadres in.') && leden.includes('minimaal één gezinslid') && n === '0',
          werkelijk: `Veldmeldingen: ${f.join(' / ')}; gezinsleden: "${leden.trim()}"; opgeslagen: ${n}.`, screenshot: await foto(p, 'TC-34') };
      },
    },
    {
      id: 'TC-35', functie: 'Gezin bewerken: gezinslid en dieetwens toevoegen', eisen: ['KLT-1', 'KLT-6', 'KLT-7'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Familie Veldhuis + nieuw lid "Noor Veldhuis" (2018-03-03) met wens Vegetarisch',
      stappen: ['Klap Familie Veldhuis uit en klik "Gezin bewerken"', 'Klik "+ Gezinslid toevoegen", vul het lid in en kies Vegetarisch', 'Opslaan'],
      verwacht: 'Gezin heeft 7 personen; Vegetarisch staat bij de dieetbeperkingen van het gezin; Noor is een kind.',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Familie Veldhuis');
        await p.click('.grp.open >> text=Gezin bewerken');
        await p.click('text=+ Gezinslid toevoegen');
        const nieuw = p.locator('.drawer.open .lid').last();
        await nieuw.locator('[data-v=naam]').fill('Noor Veldhuis'); await nieuw.locator('[data-v=gb]').fill('2018-03-03');
        await nieuw.locator('[data-v=wensen]').selectOption({ label: 'Vegetarisch' });
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const k = (await api(await cookieVan('p.abraham'), 'GET', '/klanten')).d.klanten.find((x) => x.naam === 'Familie Veldhuis');
        const noor = k.leden.find((l) => l.naam === 'Noor Veldhuis');
        return { ok: k.grootte === 7 && noor?.type === 'Kind' && noor.wensen[0]?.naam === 'Vegetarisch',
          werkelijk: `Toast "${t}"; gezinsgrootte ${k.grootte}; Noor: type ${noor?.type}, wens ${noor?.wensen.map((w) => w.naam)}.` };
      },
    },
    {
      id: 'TC-36', functie: 'Eigen dieetwens toevoegen', eisen: ['KLT-6'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Wens "Geen vis", conflict-tag "vis", niet streng',
      stappen: ['Open "Gezin bewerken" bij een gezin', 'Klik "+ Eigen dieetwens toevoegen"', 'Vul "Geen vis" en tag "vis" in, kies "Wens of overtuiging"', 'Klik Toevoegen'],
      verwacht: 'Melding "“Geen vis” staat nu in de lijst."; de wens is direct te kiezen bij elk gezinslid.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Mevr. Jansen');
        await p.click('.grp.open >> text=Gezin bewerken');
        await p.click('text=+ Eigen dieetwens toevoegen');
        await p.fill('.modal input[name=naam]', 'Geen vis'); await p.fill('.modal input[name=tag]', 'vis');
        const shot = await foto(p, 'TC-36');
        await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const optie = await p.$('.drawer.open [data-v=wensen] option:has-text("Geen vis")');
        const tag = db.waarde("SELECT CONCAT(Conflict_tag, '/', Streng) FROM Wens WHERE Naam = 'Geen vis'");
        return { ok: t.includes('Geen vis') && !!optie && tag === 'vis/0', werkelijk: `Toast "${t}"; optie in keuzelijst: ${optie ? 'ja' : 'nee'}; database tag/streng: ${tag}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-37', functie: 'Uitzondering op allergie vastleggen (reden verplicht)', eisen: ['KLT-6', 'PAK-1'], soort: 'Negatief + positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'S. El Jafari, Samira: Notenallergie; reden "Alleen Rayan reageert, apart bewaren"',
      stappen: ['Bewerk S. El Jafari', 'Vink bij Samira "het gezin wil dit tóch ontvangen" aan zonder reden en sla op', 'Vul een reden in en sla opnieuw op'],
      verwacht: 'Zonder reden: melding "Vul een reden in bij elke uitzondering…" en niets opgeslagen. Met reden: opgeslagen; de uitzondering staat in de gezinsdetails.',
      async run({ pagina, ui, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'S. El Jafari');
        await p.click('.grp.open >> text=Gezin bewerken');
        const lid = p.locator('.drawer.open .uitzrij').first();
        await lid.locator('input[type=checkbox]').check();
        await ui.drawerOpslaan(p);
        const t1 = await ui.toast(p);
        const voor = db.waarde('SELECT COUNT(*) FROM Gezinslid_has_Wens WHERE Toch_toestaan = 1');
        await lid.locator('input[type=text]').fill('Alleen Rayan reageert, apart bewaren');
        await ui.drawerOpslaan(p);
        const t2 = await ui.toast(p);
        const na = db.waarde('SELECT COUNT(*) FROM Gezinslid_has_Wens WHERE Toch_toestaan = 1');
        return { ok: t1.startsWith('Vul een reden in') && Number(na) === Number(voor) + 1,
          werkelijk: `Zonder reden: "${t1}" (uitzonderingen in db: ${voor}). Met reden: "${t2}" (uitzonderingen in db: ${na}).` };
      },
    },
    {
      id: 'TC-38', functie: 'Aanmelding accepteren', eisen: ['KLT-4'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Familie Diallo (wacht op beoordeling)',
      stappen: ['Klap Familie Diallo uit', 'Klik "Aanvraag accepteren" en bevestig'],
      verwacht: 'Melding "Familie Diallo is geaccepteerd en kan pakketten ontvangen."; status "Actief"; gezin verschijnt bij het samenstellen van pakketten.',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Familie Diallo');
        await p.click('.grp.open >> text=Aanvraag accepteren'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const pil = await p.textContent('.grp:has-text("Familie Diallo") .pil2.ok');
        const start = await api(await cookieVan('a.degroot'), 'GET', '/pakketten/samenstellen');
        const erin = start.d.klanten.some((k) => k.naam === 'Familie Diallo');
        return { ok: t.includes('geaccepteerd') && pil.includes('Actief') && erin, werkelijk: `Toast "${t}"; status "${pil}"; zichtbaar bij samenstellen: ${erin ? 'ja' : 'nee'}.` };
      },
    },
    {
      id: 'TC-39', functie: 'Aanmelding afwijzen / op non-actief', eisen: ['KLT-4', 'PAK-4'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI) + API',
      rol: 'Directie + Vrijwilliger', testdata: 'Dhr. Nowak (wacht op beoordeling)',
      stappen: ['Klap Dhr. Nowak uit, klik "Afwijzen" en bevestig', 'Vrijwilliger probeert via de API een pakket voor Nowak op te slaan'],
      verwacht: 'Status "Afgewezen"; Nowak staat niet in de samenstellijst; pakket opslaan geeft 422 "Deze klant is nog niet geaccepteerd…".',
      async run({ pagina, ui, api, cookieVan }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Dhr. Nowak');
        await p.click('.grp.open >> text=Afwijzen'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const V = await cookieVan('a.degroot');
        const start = await api(V, 'GET', '/pakketten/samenstellen');
        const r = await api(V, 'POST', '/pakketten', { klant: 10, status: 'Concept', regels: [{ product: 12, aantal: 1 }] });
        return { ok: t.includes('non-actief') && !start.d.klanten.some((k) => k.id === 10) && r.status === 422,
          werkelijk: `Toast "${t}"; in samenstellijst: nee; pakket opslaan: ${r.status} "${r.d.fout}".` };
      },
    },
    {
      id: 'TC-40', functie: 'Klant zonder historie verwijderen', eisen: ['KLT-1'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Familie Van Rooij (geen pakketten, 2 gezinsleden)',
      stappen: ['Klap Familie Van Rooij uit', 'Klik "Klant verwijderen" en bevestig'],
      verwacht: 'Melding "…is verwijderd, inclusief de gezinsleden en hun dieetwensen."; gezin en gezinsleden zijn weg uit de database.',
      async run({ pagina, ui, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Familie Van Rooij');
        await p.click('.grp.open >> text=Klant verwijderen'); await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const k = db.waarde('SELECT COUNT(*) FROM Klant WHERE idKlant = 11');
        const l = db.waarde('SELECT COUNT(*) FROM Gezinslid WHERE Klant_idKlant = 11');
        return { ok: t.includes('is verwijderd') && k === '0' && l === '0', werkelijk: `Toast "${t}"; klant in db: ${k}; gezinsleden in db: ${l}.` };
      },
    },
    {
      id: 'TC-41', functie: 'Klant met pakkethistorie kan niet verwijderd worden', eisen: ['KLT-3', 'RAP-1'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Familie Bakker (4 pakketten)',
      stappen: ['Klap Familie Bakker uit', 'Klik "Klant verwijderen" en bevestig', 'Kies in de melding "Op non-actief zetten"'],
      verwacht: 'Dialoog "Dit gezin kan niet weg" met het aantal pakketten en het advies non-actief; daarna staat de klant op non-actief en zijn de pakketten bewaard.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Familie Bakker');
        await p.click('.grp.open >> text=Klant verwijderen'); await ui.dialoog(p, true);
        const tekst = await ui.dialoogTekst(p);
        const shot = await foto(p, 'TC-41');
        await ui.dialoog(p, true);
        const t = await ui.toast(p);
        const pk = db.waarde('SELECT COUNT(*) FROM Pakket WHERE Klant_idKlant = 1');
        return { ok: tekst.includes('Dit gezin kan niet weg') && tekst.includes('4 pakketten') && t.includes('non-actief') && pk === '4',
          werkelijk: `Dialoog: "${tekst.slice(0, 110)}…"; daarna "${t}"; pakketten nog in db: ${pk}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-42', functie: 'Privacy: vrijwilliger ziet geen contactgegevens', eisen: ['TEC-4', 'TEC-6'], soort: 'Beveiliging', testvorm: 'Functioneel (UI) + API',
      rol: 'Vrijwilliger', testdata: 'a.degroot',
      stappen: ['Log in als vrijwilliger en open Klanten', 'Bekijk de API-respons van /api/klanten', 'Probeer POST /api/klanten'],
      verwacht: 'Adres "Afgeschermd", uitleg over privacy, geen knop "+ Nieuwe klant"; de API stuurt geen adres/telefoon/e-mail mee; nieuwe klant via API: 403.',
      async run({ pagina, ui, foto, api, cookieVan }) {
        const p = await pagina('a.degroot');
        await ui.naar(p, '/klanten');
        const afgeschermd = (await p.$$('text=Afgeschermd')).length;
        const knop = await p.$('text=+ Nieuwe klant');
        const V = await cookieVan('a.degroot');
        const lijst = await api(V, 'GET', '/klanten');
        const velden = ['adres', 'telefoon', 'email', 'postcode'].filter((v) => v in lijst.d.klanten[0]);
        const nieuw = await api(V, 'POST', '/klanten', { naam: 'X', leden: [] });
        return { ok: afgeschermd === 11 && !knop && velden.length === 0 && nieuw.status === 403,
          werkelijk: `"Afgeschermd" ${afgeschermd}×; knop nieuwe klant: ${knop ? 'ja' : 'nee'}; contactvelden in API: ${velden.length ? velden.join(',') : 'geen'}; POST /klanten: ${nieuw.status}.`, screenshot: await foto(p, 'TC-42') };
      },
    },
    {
      id: 'TC-43', functie: 'Vrijwilliger wijzigt gezin, niet de contactgegevens', eisen: ['TEC-4', 'GBR-4'], soort: 'Beveiliging', testvorm: 'Technisch (API)',
      rol: 'Vrijwilliger', testdata: 'PUT /api/klanten/3 met adres "Gehackt 1" en een extra gezinslid',
      stappen: ['Stuur als vrijwilliger een wijziging voor Mevr. Jansen met een ander adres én een extra gezinslid'],
      verwacht: 'Het gezinslid wordt toegevoegd; het adres blijft ongewijzigd (server negeert contactvelden zonder klant_beheren).',
      async run({ api, cookieVan, db }) {
        const V = await cookieVan('a.degroot');
        const r = await api(V, 'PUT', '/klanten/3', { adres: 'Gehackt 1', leden: [
          { naam: 'Greet Jansen', gb: '1948-02-11', relatie: 'Hoofdaanvrager', wensen: [7] },
          { naam: 'Kleinzoon Jansen', gb: '2012-06-01', relatie: 'Inwonend', wensen: [] }] });
        const adres = db.waarde('SELECT Adres FROM Klant WHERE idKlant = 3');
        const leden = db.waarde('SELECT COUNT(*) FROM Gezinslid WHERE Klant_idKlant = 3');
        return { ok: r.status === 200 && adres !== 'Gehackt 1' && leden === '2', werkelijk: `Status ${r.status}; adres in db: "${adres}"; gezinsleden: ${leden}.` };
      },
    },
    {
      id: 'TC-44', functie: 'Pakketgeschiedenis van een gezin', eisen: ['KLT-3'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Mevr. Jansen (5 pakketten)',
      stappen: ['Klap Mevr. Jansen uit', 'Klik "Pakketgeschiedenis"'],
      verwacht: 'Venster met alle 5 pakketten: datum, status en inhoud (aantal × product).',
      async run({ pagina, ui, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Mevr. Jansen');
        await p.click('.grp.open >> text=Pakketgeschiedenis');
        await p.waitForSelector('.modal .irow');
        const rijen = await p.$$eval('.modal .irow', (e) => e.map((x) => x.textContent.replace(/\s+/g, ' ').trim()));
        return { ok: rijen.length === 5 && rijen.every((r) => r.includes('×')), werkelijk: `${rijen.length} pakketten, bijv. "${rijen[0]}".`, screenshot: await foto(p, 'TC-44') };
      },
    },
    {
      id: 'TC-111', functie: 'Allergieën en voedingsbeperkingen uit de casus aanvinken (meerdere tegelijk)', eisen: ['KLT-6', 'KLT-7'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Mevr. Jansen (1 gezinslid, nu lactose-intolerant) → Lactose-intolerant + Glutenvrij + Pinda-allergie',
      stappen: ['Klap Mevr. Jansen uit en klik "Gezin bewerken"', 'Bekijk de keuzelijst met dieetwensen', 'Kies Lactose-intolerant, Glutenvrij en Pinda-allergie tegelijk', 'Klik Opslaan'],
      verwacht: 'De lijst bevat alle wensen uit de casus: geen varkensvlees, glutenvrij, pinda-allergie, schaaldierallergie, notenallergie (hazelnoten), lactose-intolerant, vegetarisch en veganistisch; ernstige allergieën hebben een ⚠. Na opslaan heeft het gezinslid 3 wensen.',
      async run({ pagina, ui, db, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/klanten');
        await openKlant(p, 'Mevr. Jansen');
        await p.click('.grp.open >> text=Gezin bewerken');
        const opties = await p.$$eval('.drawer.open .lid >> nth=0 >> [data-v=wensen] option', (e) => e.map((x) => x.textContent.trim()));
        const nodig = ['Geen varkensvlees', 'Glutenvrij', 'Pinda-allergie', 'Schaaldierallergie', 'Notenallergie', 'Lactose-intolerant', 'Vegetarisch', 'Veganistisch'];
        const mist = nodig.filter((n) => !opties.some((o) => o.includes(n)));
        await p.selectOption('.drawer.open .lid >> nth=0 >> [data-v=wensen]', ['7', '3', '5']);
        const shot = await foto(p, 'TC-111');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const n = db.waarde('SELECT COUNT(*) FROM Gezinslid_has_Wens gw JOIN Gezinslid g ON g.idGezinslid = gw.Gezinslid_idGezinslid WHERE g.Klant_idKlant = 3');
        return { ok: mist.length === 0 && n === '3' && t === 'Klantgegevens opgeslagen.',
          werkelijk: `Keuzelijst: ${opties.join(', ')}; ontbreekt: ${mist.length ? mist.join(', ') : 'niets'}. Na opslaan: "${t}", ${n} wensen bij het gezinslid.`, screenshot: shot };
      },
    },
    {
      id: 'TC-113', functie: 'Klanten zien die zich vandaag hebben aangemeld', eisen: ['KLT-4'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Vrijwilliger', testdata: 'Aanmeldingen met de datum van vandaag (o.a. Familie Diallo en Dhr. Nowak uit de testdata)',
      stappen: ['Log in als vrijwilliger en open Klanten', 'Lees de melding bovenaan', 'Klik "Toon aanmeldingen van vandaag"'],
      verwacht: 'Melding "… hebben zich vandaag aangemeld" met de namen; na de klik staan alleen de gezinnen met de aanmelddatum van vandaag in de lijst, elk met het label "Vandaag aangemeld". Oudere aanmeldingen staan er niet tussen.',
      async run({ pagina, ui, db, foto }) {
        const verwacht = db.waarde('SELECT COUNT(*) FROM Klant WHERE Aanmelddatum = CURDATE()');
        const p = await pagina('a.degroot');
        await ui.naar(p, '/klanten');
        const melding = (await p.textContent('.melding.vandaag')).replace(/\s+/g, ' ').trim();
        await p.click('text=Toon aanmeldingen van vandaag');
        await p.waitForTimeout(200);
        const rijen = (await p.$$('.grp')).length;
        const labels = (await p.$$('.grp .pil2.nieuw')).length;
        const shot = await foto(p, 'TC-113');
        return { ok: Number(verwacht) > 0 && rijen === Number(verwacht) && labels === rijen && melding.includes('vandaag aangemeld'),
          werkelijk: `Melding: "${melding.replace('Toon aanmeldingen van vandaag', '').trim()}"; na filter ${rijen} gezinnen (database: ${verwacht} aanmeldingen vandaag), allemaal met label "Vandaag aangemeld": ${labels === rijen ? 'ja' : 'nee'}.`, screenshot: shot };
      },
    },
  ],
};
