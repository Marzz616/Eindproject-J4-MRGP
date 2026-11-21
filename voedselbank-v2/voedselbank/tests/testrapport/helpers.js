/**
 * Hulpfuncties voor de testuitvoering (tests/testrapport/run.js).
 * - db.reset()       zet de database terug naar de vaste testdata (database/*.sql)
 * - api(...)         API-verzoek als een bepaalde gebruiker
 * - ui.login(...)    inloggen in de browser
 */
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const BASIS = process.env.VB_URL || 'http://localhost:4173';
// Docker: DB_HOST=127.0.0.1 DB_PORT=3307 DB_ROOT_PASS=rootpass
const DB = {
  host: process.env.DB_HOST || 'localhost',
  port: process.env.DB_PORT || '',
  user: process.env.DB_ROOT || 'root',
  pass: process.env.DB_ROOT_PASS || '',
};
const SQL = path.join(__dirname, '..', '..', 'database');

function mysql(args, invoer) {
  const basis = ['-h', DB.host, '-u', DB.user, '--default-character-set=utf8mb4'];
  if (DB.port) basis.push('-P', DB.port);
  if (DB.pass) basis.push('-p' + DB.pass);
  return execFileSync('mysql', [...basis, ...args], { input: invoer, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
}

const db = {
  reset() {
    const fs = require('node:fs');
    mysql([], fs.readFileSync(path.join(SQL, '01-schema.sql')));
    mysql(['voedselbank'], fs.readFileSync(path.join(SQL, '02-seed.sql')));
  },
  waarde(sql) {
    return mysql(['voedselbank', '-N', '-B', '-e', sql]).trim();
  },
  uitvoeren(sql) {
    return mysql(['voedselbank', '-e', sql]);
  },
};

/** Inloggen via de API; geeft de sessiecookie terug. */
async function cookieVan(gebruikersnaam, wachtwoord = 'voedselbank') {
  const r = await fetch(BASIS + '/api/auth/login', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ gebruikersnaam, wachtwoord }),
  });
  if (!r.ok) throw new Error('login mislukt voor ' + gebruikersnaam + ' (' + r.status + ')');
  // net als een browser: bij dezelfde cookienaam telt de laatste waarde
  const koekjes = {};
  r.headers.getSetCookie().forEach((c) => { const [naam, waarde] = c.split(';')[0].split('='); koekjes[naam] = waarde; });
  return Object.entries(koekjes).map(([n, w]) => `${n}=${w}`).join('; ');
}

async function api(cookie, methode, pad, body) {
  const r = await fetch(BASIS + '/api' + pad, {
    method: methode,
    headers: { 'Content-Type': 'application/json', ...(cookie ? { Cookie: cookie } : {}) },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const tekst = await r.text();
  let d = null;
  try { d = JSON.parse(tekst); } catch { d = tekst; }
  return { status: r.status, d, headers: r.headers };
}

const ui = {
  async login(page, gebruikersnaam, wachtwoord = 'voedselbank') {
    await page.goto(BASIS + '/login');
    await page.fill('#gebruikersnaam', gebruikersnaam);
    await page.fill('#wachtwoord', wachtwoord);
    await page.click('button[type=submit]');
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 5000 });
  },
  async naar(page, pad) {
    await page.goto(BASIS + pad);
    await page.waitForLoadState('networkidle');
  },
  /** Klikt de ja- of nee-knop van de open dialoog. */
  async dialoog(page, ja = true) {
    await page.click(ja ? '.modal [data-ja]' : '.modal [data-nee]');
    await page.waitForTimeout(250);
  },
  async dialoogTekst(page) {
    await page.waitForSelector('.modal');
    return (await page.textContent('.modal')).replace(/\s+/g, ' ').trim();
  },
  /** Wacht op een toast en geeft de tekst terug. */
  async toast(page) {
    const t = await page.waitForSelector('.toast', { timeout: 5000 });
    const tekst = (await t.textContent()).trim();
    await page.evaluate(() => document.querySelectorAll('.toast').forEach((x) => x.remove()));
    return tekst;
  },
  async veldfouten(page) {
    return page.$$eval('.veld .fout', (els) => els.map((e) => e.textContent.trim()));
  },
  async drawerOpslaan(page) {
    await page.click('.drawer.open .voet .knop.primair');
    await page.waitForTimeout(400);
  },
};

module.exports = { BASIS, db, cookieVan, api, ui };
