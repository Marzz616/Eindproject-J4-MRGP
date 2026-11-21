/**
 * Snelle regressietest op de API van de voedselbank-applicatie.
 *
 * Test de dingen die je in de browser niet ziet: of de server zelf de rechten
 * bewaakt (US-SEC-01/02), of historische gegevens beschermd zijn en of een
 * harde allergieblokkade ook via een omweg niet te passeren is.
 *
 * Draaien (de applicatie moet aanstaan, Node 18 of nieuwer):
 *     node tests/api-test.js
 * Andere poort:
 *     VB_URL=http://localhost:5173 node tests/api-test.js
 *
 * De test maakt zelf een categorie aan en verwijdert die weer; verder laat hij
 * de data met rust. Afsluitcode 0 = alles goed, 1 = er is iets mis.
 */
const B = (process.env.VB_URL || 'http://localhost:8080') + '/api';
let ok = 0;
let fout = 0;

function check(naam, voorwaarde, extra = '') {
  if (voorwaarde) { ok++; console.log('  OK   ' + naam); } else { fout++; console.log('  FOUT ' + naam + (extra ? '  -> ' + extra : '')); }
}

async function login(gnaam) {
  const r = await fetch(B + '/auth/login', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ gebruikersnaam: gnaam, wachtwoord: 'voedselbank' }),
  });
  if (!r.ok) throw new Error('login mislukt voor ' + gnaam);
  // net als een browser: bij dezelfde cookienaam telt de laatste waarde
  const koekjes = {};
  r.headers.getSetCookie().forEach((c) => { const [naam, waarde] = c.split(';')[0].split('='); koekjes[naam] = waarde; });
  return Object.entries(koekjes).map(([n, w]) => `${n}=${w}`).join('; ');
}

async function api(cookie, methode, pad, body) {
  const r = await fetch(B + pad, {
    method: methode,
    headers: { 'Content-Type': 'application/json', Cookie: cookie },
    body: body ? JSON.stringify(body) : undefined,
  });
  let d = null;
  try { d = await r.json(); } catch { /* geen json */ }
  return { status: r.status, d };
}

(async () => {
  const D = await login('p.abraham');   // Directie
  const M = await login('r.veenstra');  // Magazijnmedewerker
  const V = await login('a.degroot');   // Vrijwilliger
  const A = await login('m.snel');      // Administratie

  console.log('\n== rechten (US-SEC-01/02) ==');
  check('vrijwilliger mag geen klant verwijderen', (await api(V, 'DELETE', '/klanten/999')).status === 403);
  check('vrijwilliger mag geen categorie verwijderen', (await api(V, 'DELETE', '/categorieen/1')).status === 403);
  check('magazijn mag geen klanten inzien', (await api(M, 'GET', '/klanten')).status === 403);
  check('administratie mag geen categorie aanmaken', (await api(A, 'POST', '/categorieen', { naam: 'Stiekem' })).status === 403);
  check('magazijn mag wel categorieen zien', (await api(M, 'GET', '/categorieen')).status === 200);
  check('zonder login: 401', (await api('', 'GET', '/dashboard')).status === 401);

  console.log('\n== privacy: contactgegevens (US-SEC-02) ==');
  const vl = await api(V, 'GET', '/klanten');
  check('vrijwilliger ziet geen adres', vl.d.klanten[0].adres === undefined);
  check('vrijwilliger ziet wel de voorkeuren', 'opmerkingen' in vl.d.klanten[0]);
  const dl = await api(D, 'GET', '/klanten');
  check('directie ziet wel adres', typeof dl.d.klanten[0].adres === 'string');

  console.log('\n== historie beschermen ==');
  const metHist = dl.d.klanten.find((k) => k.laatste);
  const vw = await api(D, 'DELETE', '/klanten/' + metHist.id);
  check('klant met pakketten kan niet weg', vw.status === 409 && vw.d.reden === 'heeft_historie', JSON.stringify(vw.d));
  const cats = await api(D, 'GET', '/categorieen');
  const volle = cats.d.find((c) => c.aantal > 0);
  const cv = await api(D, 'DELETE', '/categorieen/' + volle.id);
  check('categorie met producten kan niet weg', cv.status === 409 && cv.d.reden === 'heeft_producten', JSON.stringify(cv.d));

  console.log('\n== categorie toevoegen / verwijderen ==');
  const nieuw = await api(D, 'POST', '/categorieen', { naam: 'Testcat ' + Date.now(), kort: 'Test' });
  check('nieuwe categorie aangemaakt', nieuw.status === 201 && nieuw.d.id > 0);
  const weg = await api(D, 'DELETE', '/categorieen/' + nieuw.d.id);
  check('lege categorie mag weg', weg.status === 200, JSON.stringify(weg.d));

  console.log('\n== allergie, uitzondering en THT (US-PAK) ==');
  const start = await api(V, 'GET', '/pakketten/samenstellen');
  const bakker = start.d.klanten.find((k) => k.naam.includes('Bakker'));
  const jafari = start.d.klanten.find((k) => k.naam.includes('Jafari'));

  const kb = await api(V, 'GET', '/pakketten/klant/' + bakker.id);
  const noten = kb.d.wensen.find((w) => w.tag === 'noten');
  check('Bakker heeft een vastgelegde uitzondering', noten && noten.uitzondering === true);
  check('uitzondering heft de harde blokkade op', noten && noten.streng === false);
  check('de reden komt mee', noten && (noten.toelichting || '').includes('Bart'));

  const kj = await api(V, 'GET', '/pakketten/klant/' + jafari.id);
  const notenJ = kj.d.wensen.find((w) => w.tag === 'noten');
  check('El Jafari heeft wel een harde notenblokkade', notenJ && notenJ.streng === true);

  const pindakaas = start.d.producten.find((p) => (p.allergenen || '').includes('noten'));
  const verlopen = start.d.producten.find((p) => p.verlopen);
  const poging1 = await api(V, 'POST', '/pakketten', { klant: jafari.id, status: 'Concept', forceer: true, regels: [{ product: pindakaas.id, aantal: 1 }] });
  check('geblokkeerd product kan ook met forceer niet mee', poging1.status === 409 && poging1.d.reden === 'geblokkeerd', JSON.stringify(poging1.d));
  const poging2 = await api(V, 'POST', '/pakketten', { klant: bakker.id, status: 'Concept', forceer: true, regels: [{ product: verlopen.id, aantal: 1 }] });
  check('product over de THT kan ook met forceer niet mee', poging2.status === 409 && poging2.d.reden === 'geblokkeerd', JSON.stringify(poging2.d));

  console.log('\n== dashboard per rol ==');
  for (const [naam, c, verwacht, niet] of [
    ['directie', D, 'Medewerkers', null],
    ['magazijn', M, 'Voorraad totaal', 'Klanten actief'],
    ['vrijwilliger', V, 'Pakketten deze week', 'Voorraad totaal'],
    ['administratie', A, 'Klanten actief', 'Voorraad totaal'],
  ]) {
    const labels = (await api(c, 'GET', '/dashboard')).d.kaarten.map((k) => k.label);
    check(`${naam}: ziet "${verwacht}"`, labels.includes(verwacht), labels.join(', '));
    if (niet) check(`${naam}: ziet geen "${niet}"`, !labels.includes(niet), labels.join(', '));
  }

  console.log('\n== routes ==');
  check('onbekende route: 404', (await api(D, 'GET', '/bestaatniet')).status === 404);
  check('verkeerde methode: 405', (await api(D, 'GET', '/auth/login')).status === 405);

  console.log(`\n${ok} geslaagd, ${fout} mislukt`);
  process.exit(fout ? 1 : 0);
})();
