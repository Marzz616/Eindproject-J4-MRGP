/**
 * Voert alle testcases uit het testplan uit en schrijft de uitkomsten weg.
 *
 *   node tests/testrapport/run.js
 *
 * Vereist: de app draait (frontend + API op VB_URL, standaard http://localhost:4173)
 * en de testdatabase is bereikbaar voor het terugzetten van de testdata.
 *
 * Elke module begint met verse testdata, zodat de tests elkaar niet beïnvloeden.
 * Uitkomst: resultaten.json en screenshots/TC-xx.png in deze map.
 */
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { BASIS, db, cookieVan, api, ui } = require('./helpers');
const { US, koppeling } = require('./userstories');

const MODULES = fs.readdirSync(path.join(__dirname, 'cases')).filter((f) => f.endsWith('.js'))
  .filter((f) => !process.env.VB_MODULES || process.env.VB_MODULES.split(',').some((m) => f.startsWith(m)))
  .sort();
const SCHERM = path.join(__dirname, 'screenshots');
fs.mkdirSync(SCHERM, { recursive: true });

(async () => {
  const browser = await chromium.launch();
  const resultaten = [];
  const gestart = new Date();

  for (const bestand of MODULES) {
    const module = require('./cases/' + bestand);
    db.reset();
    console.log(`\n== ${module.onderdeel} ==`);

    const contexten = [];
    const ctx = {
      BASIS, db, api, ui, cookieVan,
      /** Nieuwe, schone browser (eigen cookies); optioneel direct ingelogd. */
      async pagina(gebruiker, opties = {}) {
        const c = await browser.newContext({ viewport: { width: 1440, height: 900 }, ...opties });
        contexten.push(c);
        const p = await c.newPage();
        p.consolefouten = [];
        p.on('pageerror', (e) => p.consolefouten.push(e.message));
        p.on('console', (m) => m.type() === 'error' && !/status of 4\d\d/.test(m.text()) && p.consolefouten.push(m.text()));
        if (gebruiker) await ui.login(p, gebruiker);
        return p;
      },
      async foto(page, id) {
        await page.screenshot({ path: path.join(SCHERM, id + '.png') });
        return id + '.png';
      },
    };

    for (const tc of module.cases) {
      const start = Date.now();
      let uitkomst;
      try {
        uitkomst = await tc.run(ctx);
      } catch (e) {
        uitkomst = { ok: false, werkelijk: 'Fout tijdens uitvoeren: ' + e.message.split('\n')[0] };
      }
      const { run, ...meta } = tc;
      const [stories, pagina] = koppeling[tc.id] || [[], ''];
      resultaten.push({
        ...meta,
        userStories: stories.map((id) => ({ id, titel: US[id][0] })),
        pagina,
        onderdeel: module.onderdeel,
        module: bestand.replace('.js', ''),
        resultaat: uitkomst.ok ? 'Geslaagd' : 'Niet geslaagd',
        werkelijk: uitkomst.werkelijk,
        screenshot: uitkomst.screenshot || null,
        duurMs: Date.now() - start,
      });
      console.log(`${uitkomst.ok ? '  OK  ' : '  FOUT'} ${tc.id} ${tc.functie}${uitkomst.ok ? '' : '\n        -> ' + uitkomst.werkelijk}`);
    }
    for (const c of contexten) await c.close();
  }

  await browser.close();
  db.reset();

  const geslaagd = resultaten.filter((r) => r.resultaat === 'Geslaagd').length;
  fs.writeFileSync(path.join(__dirname, 'resultaten.json'), JSON.stringify({
    uitgevoerd: gestart.toISOString(), omgeving: BASIS, totaal: resultaten.length, geslaagd, resultaten,
  }, null, 2));
  console.log(`\n${geslaagd} van ${resultaten.length} testcases geslaagd.`);
  process.exit(geslaagd === resultaten.length ? 0 : 1);
})();
