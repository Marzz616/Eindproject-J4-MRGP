/** Module C — Gebruikers- en toegangsbeheer. */
/** Klapt de rij van een gebruiker uit (als hij nog dicht is). */
async function openGebruiker(p, naam) {
  if (!(await p.$(`.grp.open:has-text("${naam}")`))) await p.click(`.grp:has-text("${naam}") .trow`);
  await p.waitForTimeout(150);
}

module.exports = {
  onderdeel: 'C. Gebruikers en toegangsbeheer',
  cases: [
    {
      id: 'TC-23', functie: 'Overzicht gebruikers, rollen en rechten', eisen: ['GBR-1', 'GBR-6'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: '5 accounts, 4 rollen', stappen: ['Ga naar Gebruikers', 'Klap Ank de Groot uit', 'Bekijk de tabel "Rollen en wat ze mogen"'],
      verwacht: 'Alle 5 accounts met rol, laatste login en status; per gebruiker de rechten met ✓/✕; per rol de rechten.',
      async run({ pagina, ui, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/gebruikers');
        const rijen = await p.$$('.grp');
        await openGebruiker(p, 'Ank de Groot');
        const vink = await p.$$eval('.grp.open .rechtenlijst .vink', (e) => e.length);
        const kruis = await p.$$eval('.grp.open .rechtenlijst .kruis', (e) => e.length);
        const rollen = await p.$$eval('.inner .irow', (e) => e.length);
        return { ok: rijen.length === 5 && vink > 0 && kruis > 0 && rollen === 4, werkelijk: `${rijen.length} gebruikers; Ank de Groot: ${vink} rechten ✓ en ${kruis} ✕; ${rollen} rollen in de rechtentabel.`, screenshot: await foto(p, 'TC-23') };
      },
    },
    {
      id: 'TC-24', functie: 'Nieuwe gebruiker aanmaken', eisen: ['GBR-6'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Rick Test, r.test, rick.test@voedselbank.nl, Vrijwilliger, wachtwoord Welkom2026',
      stappen: ['Klik op "+ Nieuwe gebruiker"', 'Vul alle velden in en kies rol Vrijwilliger', 'Klik op Opslaan', 'Log in met het nieuwe account'],
      verwacht: 'Melding "Account aangemaakt voor Rick Test."; account staat in de lijst; inloggen lukt en de gebruiker krijgt het vrijwilligersmenu.',
      async run({ pagina, ui, foto, api }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/gebruikers');
        await p.click('text=+ Nieuwe gebruiker');
        await p.fill('#g-naam', 'Rick Test'); await p.fill('#g-gnaam', 'r.test'); await p.fill('#g-mail', 'rick.test@voedselbank.nl');
        await p.selectOption('#g-rol', { label: 'Vrijwilliger' }); await p.fill('#g-pw', 'Welkom2026');
        const shot = await foto(p, 'TC-24');
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const inLijst = await p.$('.grp:has-text("Rick Test")');
        const login = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'r.test', wachtwoord: 'Welkom2026' });
        return { ok: t === 'Account aangemaakt voor Rick Test.' && !!inLijst && login.status === 200 && login.d.gebruiker.rol === 'Vrijwilliger',
          werkelijk: `Toast "${t}"; in lijst: ${inLijst ? 'ja' : 'nee'}; inloggen: ${login.status} als ${login.d.gebruiker?.rol}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-25', functie: 'Nieuwe gebruiker: validatie en unieke gebruikersnaam', eisen: ['GBR-6', 'TEC-6'], soort: 'Negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: '1) alles leeg  2) gebruikersnaam a.degroot (bestaat al), e-mail "geen-mail", wachtwoord "abc"',
      stappen: ['Klik op "+ Nieuwe gebruiker" en direct op Opslaan', 'Vul een bestaande gebruikersnaam, ongeldig e-mailadres en zwak wachtwoord in', 'Klik op Opslaan'],
      verwacht: 'Leeg: meldingen bij naam, gebruikersnaam, e-mail en wachtwoord. Daarna: "Deze gebruikersnaam bestaat al.", "Vul een geldig e-mailadres in.", wachtwoordeis. Er wordt niets opgeslagen.',
      async run({ pagina, ui, foto, db }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/gebruikers');
        const voor = db.waarde('SELECT COUNT(*) FROM Gebruiker');
        await p.click('text=+ Nieuwe gebruiker');
        await ui.drawerOpslaan(p);
        const leeg = await ui.veldfouten(p);
        await p.fill('#g-naam', 'Dubbel'); await p.fill('#g-gnaam', 'a.degroot'); await p.fill('#g-mail', 'geen-mail'); await p.fill('#g-pw', 'abc');
        await ui.drawerOpslaan(p);
        const fout = await ui.veldfouten(p);
        const aantal = db.waarde('SELECT COUNT(*) FROM Gebruiker');
        return { ok: leeg.length === 4 && fout.includes('Deze gebruikersnaam bestaat al.') && fout.length === 3 && aantal === voor,
          werkelijk: `Leeg: ${leeg.join(' / ')}. Foute invoer: ${fout.join(' / ')}. Aantal gebruikers voor/na: ${voor}/${aantal}.`, screenshot: await foto(p, 'TC-25') };
      },
    },
    {
      id: 'TC-26', functie: 'Gebruiker bewerken en rol wijzigen', eisen: ['GBR-6', 'GBR-1'], soort: 'Positief', testvorm: 'Functioneel (UI) + API',
      rol: 'Directie', testdata: 'Ank de Groot: Vrijwilliger → Magazijnmedewerker',
      stappen: ['Klap Ank de Groot uit en klik op "Account bewerken"', 'Kies rol Magazijnmedewerker en sla op', 'Ank doet een verzoek naar pakketten en producten'],
      verwacht: 'Melding "Account bijgewerkt."; rolpil toont MAGAZIJNMEDEWERKER; de nieuwe rechten gelden direct (pakketten 403, producten beheren mag).',
      async run({ pagina, ui, api, cookieVan }) {
        const A = await cookieVan('a.degroot');
        const p = await pagina('p.abraham');
        await ui.naar(p, '/gebruikers');
        await openGebruiker(p, 'Ank de Groot');
        await p.click('.grp.open >> text=Account bewerken');
        await p.selectOption('#g-rol', { label: 'Magazijnmedewerker' });
        await ui.drawerOpslaan(p);
        const t = await ui.toast(p);
        const pil = await p.textContent('.grp:has-text("Ank de Groot") .pil2');
        const pak = await api(A, 'GET', '/pakketten/samenstellen');
        const prod = await api(A, 'GET', '/producten');
        return { ok: t === 'Account bijgewerkt.' && pil.includes('MAGAZIJN') && pak.status === 403 && prod.d.rechten.beheren === true,
          werkelijk: `Toast "${t}"; rol nu ${pil}; bestaande sessie van Ank: pakketten ${pak.status}, producten beheren ${prod.d.rechten.beheren}.` };
      },
    },
    {
      id: 'TC-27', functie: 'Account blokkeren en deblokkeren', eisen: ['GBR-6'], soort: 'Positief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Mark Snel (m.snel)',
      stappen: ['Klap Mark Snel uit, klik Blokkeren en bevestig', 'Probeer in te loggen als m.snel', 'Klik Deblokkeren en bevestig', 'Probeer opnieuw in te loggen'],
      verwacht: 'Na blokkeren status "Geblokkeerd" en inloggen geweigerd (403); na deblokkeren status "Actief" en inloggen lukt. Account wordt niet verwijderd.',
      async run({ pagina, ui, api, foto }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/gebruikers');
        const naam = (await p.textContent('.grp:has-text("m.snel") .nm')).trim();
        await openGebruiker(p, 'm.snel');
        await p.click('.grp.open >> text=Blokkeren'); await ui.dialoog(p, true);
        const t1 = await ui.toast(p);
        const s1 = await p.textContent('.grp:has-text("m.snel") .pil2.nee');
        const shot = await foto(p, 'TC-27');
        const l1 = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'm.snel', wachtwoord: 'voedselbank' });
        await openGebruiker(p, 'm.snel');
        await p.click('.grp.open >> text=Deblokkeren'); await ui.dialoog(p, true);
        const t2 = await ui.toast(p);
        const l2 = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'm.snel', wachtwoord: 'voedselbank' });
        return { ok: s1.includes('Geblokkeerd') && l1.status === 403 && l2.status === 200,
          werkelijk: `${naam}: "${t1}" → login ${l1.status}; "${t2}" → login ${l2.status}.`, screenshot: shot };
      },
    },
    {
      id: 'TC-28', functie: 'Eigen account kan niet geblokkeerd worden', eisen: ['GBR-6'], soort: 'Negatief', testvorm: 'Technisch (API)',
      rol: 'Directie', testdata: 'p.abraham blokkeert zichzelf', stappen: ['PATCH /api/gebruikers/1/actief {actief:false} als p.abraham'],
      verwacht: 'Status 409 "Je kunt je eigen account niet blokkeren."',
      async run({ api, cookieVan }) {
        const r = await api(await cookieVan('p.abraham'), 'PATCH', '/gebruikers/1/actief', { actief: false });
        return { ok: r.status === 409 && r.d.fout === 'Je kunt je eigen account niet blokkeren.', werkelijk: `${r.status} "${r.d.fout}"` };
      },
    },
    {
      id: 'TC-29', functie: 'Laatste directielid blijft beschermd', eisen: ['GBR-2', 'GBR-6'], soort: 'Grensgeval', testvorm: 'Technisch (API)',
      rol: 'Administratie (heeft gebruiker_beheren)', testdata: 'm.snel probeert p.abraham te blokkeren en te degraderen',
      stappen: ['PATCH /api/gebruikers/1/actief {actief:false}', 'PUT /api/gebruikers/1 met rol Vrijwilliger'],
      verwacht: 'Beide 409: het laatste actieve directie-account kan niet geblokkeerd of gedegradeerd worden.',
      async run({ api, cookieVan }) {
        const A = await cookieVan('m.snel');
        const b = await api(A, 'PATCH', '/gebruikers/1/actief', { actief: false });
        const d = await api(A, 'PUT', '/gebruikers/1', { naam: 'Peter Abraham', gnaam: 'p.abraham', email: 'p.abraham@voedselbank.nl', rolId: 3 });
        return { ok: b.status === 409 && d.status === 409, werkelijk: `Blokkeren: ${b.status} "${b.d.fout}". Degraderen: ${d.status} "${d.d.fout}".` };
      },
    },
    {
      id: 'TC-30', functie: 'Wachtwoord resetten door de beheerder', eisen: ['GBR-6', 'GBR-5'], soort: 'Positief + negatief', testvorm: 'Functioneel (UI)',
      rol: 'Directie', testdata: 'Rob Veenstra: eerst "kort" (te zwak), dan Magazijn2026',
      stappen: ['Klap Rob Veenstra uit en klik "Wachtwoord resetten"', 'Vul "kort" in en bevestig', 'Probeer opnieuw met Magazijn2026', 'Log in als r.veenstra met het nieuwe wachtwoord'],
      verwacht: 'Zwak wachtwoord geweigerd met de wachtwoordeis; sterk wachtwoord geeft "Nieuw wachtwoord ingesteld." en inloggen lukt.',
      async run({ pagina, ui, api }) {
        const p = await pagina('p.abraham');
        await ui.naar(p, '/gebruikers');
        await openGebruiker(p, 'r.veenstra');
        await p.click('.grp.open >> text=Wachtwoord resetten');
        await p.fill('.modal input[name=wachtwoord]', 'kort'); await ui.dialoog(p, true);
        const t1 = await ui.toast(p);
        await p.click('.grp.open >> text=Wachtwoord resetten');
        await p.fill('.modal input[name=wachtwoord]', 'Magazijn2026'); await ui.dialoog(p, true);
        const t2 = await ui.toast(p);
        const l = await api(null, 'POST', '/auth/login', { gebruikersnaam: 'r.veenstra', wachtwoord: 'Magazijn2026' });
        return { ok: t1.includes('minimaal 8 tekens') && t2 === 'Nieuw wachtwoord ingesteld.' && l.status === 200,
          werkelijk: `Zwak: "${t1}". Sterk: "${t2}". Inloggen met nieuw wachtwoord: ${l.status}.` };
      },
    },
  ],
};
