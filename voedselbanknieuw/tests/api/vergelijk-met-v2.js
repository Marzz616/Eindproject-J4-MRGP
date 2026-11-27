/**
 * Regressietest: geeft de Laravel-versie dezelfde gegevens als versie 2?
 *
 * Voor de herkansing is de code overgezet naar Laravel. De functionaliteit
 * moest daarbij precies hetzelfde blijven. Deze test vraagt in beide versies
 * dezelfde overzichten op (op dezelfde database) en vergelijkt het antwoord
 * veld voor veld.
 *
 * Draaien: zet beide versies aan op dezelfde database en start
 *     VB_URL=http://localhost:8000 VB_V2=http://localhost:8001 node tests/api/vergelijk-met-v2.js
 *
 * Afsluitcode 0 = alles gelijk, 1 = er zit een verschil in.
 */
const NIEUW = process.env.VB_URL || 'http://localhost:8000';
const V2 = process.env.VB_V2 || 'http://localhost:8001';

const PADEN = [
  'auth/rechten', 'dashboard', 'klanten', 'wensen', 'klanten/1/pakketten',
  'producten', 'categorieen', 'leveranciers', 'gebruikers',
  'pakketten', 'pakketten/samenstellen', 'pakketten/klant/1', 'pakketten/2862',
  'uitgifte/wachtrij', 'uitgifte/zoek?q=K-002',
  'rapportages', 'rapportages/maand?soort=categorie', 'rapportages/maand?soort=postcode',
];

async function login(basis) {
  const r = await fetch(basis + '/api/auth/login', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ gebruikersnaam: 'p.abraham', wachtwoord: 'voedselbank' }),
  });
  if (!r.ok) throw new Error('inloggen mislukt op ' + basis);
  const koekjes = {};
  r.headers.getSetCookie().forEach((c) => { const [n, w] = c.split(';')[0].split('='); koekjes[n] = w; });
  return Object.entries(koekjes).map(([n, w]) => `${n}=${w}`).join('; ');
}

(async () => {
  const cNieuw = await login(NIEUW);
  const cV2 = await login(V2);

  let gelijk = 0;
  const verschillen = [];

  for (const pad of PADEN) {
    const [a, b] = await Promise.all([
      fetch(V2 + '/api/' + pad, { headers: { Cookie: cV2 } }).then((r) => r.json()),
      fetch(NIEUW + '/api/' + pad, { headers: { Cookie: cNieuw } }).then((r) => r.json()),
    ]);
    if (JSON.stringify(a) === JSON.stringify(b)) {
      gelijk++;
      console.log('  OK   ' + pad);
    } else {
      verschillen.push(pad);
      console.log('  FOUT ' + pad);
    }
  }

  console.log(`\n${gelijk} van ${PADEN.length} overzichten geven exact dezelfde gegevens.`);
  process.exit(verschillen.length === 0 ? 0 : 1);
})();
