/* =============================================================
   Gedeelde frontend-laag voor de hele back-office.
   Regelt: inloggen bewaken, navigatie opbouwen op basis van
   rechten, API-aanroepen, meldingen en kleine hulpjes.
   ============================================================= */

/* ---------------------------------------------- API */

/** Roept de API aan en vertaalt fouten naar iets bruikbaars. */
async function api(pad, opties = {}) {
  const conf = { headers: {}, credentials: 'same-origin', ...opties };
  const token = localStorage.getItem('voedselbank_token');
  if (token) {
    conf.headers.Authorization = 'Bearer ' + token;
  }
  if (conf.body !== undefined && typeof conf.body !== 'string') {
    conf.method = conf.method || 'POST';
    conf.headers['Content-Type'] = 'application/json';
    conf.body = JSON.stringify(conf.body);
  }

  let res;
  try {
    res = await fetch('api/' + pad, conf);
  } catch (e) {
    throw { status: 0, data: { fout: 'Geen verbinding met de server.' } };
  }

  const tekst = await res.text();
  let data = {};
  try { data = tekst ? JSON.parse(tekst) : {}; } catch (e) { data = { fout: 'Onverwacht antwoord van de server.' }; }

  if (res.status === 401) {
    localStorage.removeItem('voedselbank_token');
    if (data.login_nodig) { naarLogin(); }
    throw { status: 401, data };
  }
  if (!res.ok) { throw { status: res.status, data }; }
  return data;
}

function naarLogin() {
  const hier = location.pathname.split('/').pop() || 'index.html';
  location.href = 'login.html?terug=' + encodeURIComponent(hier);
}

/* ---------------------------------------------- meldingen */

function toast(tekst, soort = '') {
  let bak = document.getElementById('toasts');
  if (!bak) {
    bak = document.createElement('div');
    bak.id = 'toasts';
    document.body.appendChild(bak);
  }
  const t = document.createElement('div');
  t.className = 'toast ' + soort;
  t.textContent = tekst;
  bak.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 3600);
  setTimeout(() => t.remove(), 4000);
}

const melding = {
  goed: (t) => toast(t, 'goed'),
  fout: (t) => toast(t, 'fout'),
  info: (t) => toast(t),
};

/** Zet de veldfouten uit een 422-antwoord onder de juiste invoervelden. */
function toonVeldfouten(vorm, fouten) {
  vorm.querySelectorAll('.veld.mis').forEach(v => v.classList.remove('mis'));
  vorm.querySelectorAll('.veld .fout').forEach(v => v.remove());

  let eerste = null;
  Object.entries(fouten || {}).forEach(([naam, tekst]) => {
    const veld = vorm.querySelector('[name="' + naam + '"]');
    if (!veld) { melding.fout(tekst); return; }
    const wrap = veld.closest('.veld') || veld.parentElement;
    wrap.classList.add('mis');
    const p = document.createElement('div');
    p.className = 'fout';
    p.textContent = tekst;
    wrap.appendChild(p);
    if (!eerste) { eerste = veld; }
  });
  if (eerste) { eerste.focus(); }
}

/** Standaardafhandeling van een fout uit api(). */
function foutMelden(e, vorm) {
  const d = (e && e.data) || {};
  if (d.fouten && vorm) { toonVeldfouten(vorm, d.fouten); return; }
  if (d.fouten) { melding.fout(Object.values(d.fouten)[0]); return; }
  melding.fout(d.fout || 'Er ging iets mis.');
}

/* ---------------------------------------------- opmaak */

const MAANDEN = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

function datum(s) {
  if (!s) { return '—'; }
  const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
  if (isNaN(d)) { return '—'; }
  const vandaag = new Date(); vandaag.setHours(0, 0, 0, 0);
  const dagen = Math.round((d - vandaag) / 86400000);
  if (dagen === 0) { return 'Vandaag'; }
  if (dagen === -1) { return 'Gisteren'; }
  if (dagen === 1) { return 'Morgen'; }
  return d.getDate() + ' ' + MAANDEN[d.getMonth()] + ' ' + d.getFullYear();
}

function datumKort(s) {
  if (!s) { return '—'; }
  const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
  if (isNaN(d)) { return '—'; }
  return String(d.getDate()).padStart(2, '0') + '-' +
         String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear();
}

function tijdstip(s) {
  if (!s) { return 'Nog nooit'; }
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) { return '—'; }
  const uur = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  return datum(s) + ', ' + uur;
}

function geld(n) {
  return '€ ' + Number(n || 0).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getal(n) { return Number(n || 0).toLocaleString('nl-NL'); }

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function initialen(naam) {
  return String(naam || '?').trim().charAt(0).toUpperCase();
}

/* ---------------------------------------------- navigatie */

const ICOON = {
  dashboard:  '<path d="M3 3h7v7H3zM14 3h7v5h-7zM14 11h7v10h-7zM3 13h7v8H3z"/>',
  pakketten:  '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
  uitgifte:   '<path d="M4 7h16v13H4z"/><path d="M9 7V4h6v3M9 13h6"/>',
  producten:  '<path d="M4 6h16v14H4z"/><path d="M4 10h16M9 6V3h6v3"/>',
  leveranciers: '<path d="M2 8h11v9H2zM13 11h5l3 3v3h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
  klanten:    '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 8.5a3 3 0 100-5M18 20c0-2.5-1-4.2-2.5-5.2"/>',
  rapportages:'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  gebruikers: '<circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
  account:    '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>',
};

const PAGINAS = [
  { slug: 'dashboard',    href: 'index.html',        naam: 'Dashboard' },
  { slug: 'pakketten',    href: 'pakketten.html',    naam: 'Voedselpakketten' },
  { slug: 'uitgifte',     href: 'uitgifte.html',     naam: 'Uitgifte', sub: true },
  { slug: 'producten',    href: 'producten.html',    naam: 'Producten' },
  { slug: 'leveranciers', href: 'leveranciers.html', naam: 'Leveranciers' },
  { slug: 'klanten',      href: 'klanten.html',      naam: 'Klanten' },
  { slug: 'rapportages',  href: 'rapportages.html',  naam: 'Rapportages' },
  { slug: 'gebruikers',   href: 'gebruikers.html',   naam: 'Gebruikers' },
  { slug: 'account',      href: 'account.html',      naam: 'Mijn account' },
];

const ICOON_UIT = '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>' +
                  '<path d="M15 4h4a1 1 0 011 1v14a1 1 0 01-1 1h-4"/>';

function svg(pad) {
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" ' +
         'stroke-linecap="round" stroke-linejoin="round">' + pad + '</svg>';
}

/**
 * Bouwt de zijbalk. Zoals in het ontwerp staan er alleen pagina's in
 * waar de ingelogde rol ook echt bij mag; de rest is niet zichtbaar.
 * De server weigert die pagina's sowieso (US-SEC-01).
 */
function bouwZijbalk(gebruiker, actief) {
  const links = PAGINAS.filter(p => gebruiker.paginas.includes(p.slug)).map(p => {
    const klas = ['nav-item'];
    if (p.sub) { klas.push('sub'); }
    if (p.slug === actief) { klas.push('actief'); }

    const icoon = p.sub ? '' : svg(ICOON[p.slug] || ICOON.dashboard);
    return '<a class="' + klas.join(' ') + '" href="' + p.href + '">' +
           icoon + '<span>' + esc(p.naam) + '</span></a>';
  }).join('');

  return `
  <aside class="side">
    <div class="brand">
      <div class="logo">VB</div>
      <div>
        <div class="naam">Maaskantje</div>
        <div class="sub">Back-office</div>
      </div>
    </div>
    <nav class="nav">
      ${links}
    </nav>
    <div class="gebruiker">
      <div class="wie">
        <div class="avatar">${esc(initialen(gebruiker.naam))}</div>
        <div class="stapel">
          <div class="naam">${esc(gebruiker.naam)}</div>
          <div class="rol">${esc(gebruiker.rol)}</div>
        </div>
      </div>
      <button class="uit" id="uitloggen" type="button" title="Uitloggen">
        ${svg(ICOON_UIT)}<span class="woord">Uitloggen</span>
      </button>
    </div>
  </aside>`;
}

function bouwTopbar(titel, onder, gebruiker) {
  return `
  <header class="topbar">
    <div>
      <h1>${esc(titel)}</h1>
      <div class="onder">${esc(onder)}</div>
    </div>
    <div class="rolpil">${esc(gebruiker.rol)} view</div>
  </header>`;
}

/**
 * Elke pagina begint hiermee. Controleert of je bent ingelogd, of je
 * deze pagina mag zien, en tekent de vaste onderdelen.
 * Geeft de ingelogde gebruiker terug.
 */
async function start({ slug, titel, onder }) {
  let ik;
  try {
    const r = await api('auth.php?actie=ik');
    if (!r.ingelogd) { naarLogin(); return null; }
    ik = r.gebruiker;
  } catch (e) {
    naarLogin();
    return null;
  }

  if (!ik.paginas.includes(slug)) {
    document.body.innerHTML = `
      <div class="app">
        ${bouwZijbalk(ik, null)}
        <main class="main">
          ${bouwTopbar('Geen toegang', 'Deze pagina hoort niet bij jouw rol', ik)}
          <div class="inhoud">
            <div class="melding rood">
              <div><b>Je hebt geen rechten voor deze pagina.</b><br>
              Als ${esc(ik.rol.toLowerCase())} kun je wel bij:
              ${ik.paginas.map(p => esc((PAGINAS.find(x => x.slug === p) || {}).naam || p)).join(', ')}.</div>
            </div>
            <a class="knop primair" href="${ik.paginas.includes('dashboard') ? 'index.html' : 'account.html'}">Terug naar het begin</a>
          </div>
        </main>
      </div>`;
    koppelUitloggen();
    return null;
  }

  const houder = document.getElementById('app');
  houder.innerHTML = bouwZijbalk(ik, slug) +
    '<main class="main">' + bouwTopbar(titel, onder, ik) +
    '<div class="inhoud" id="inhoud"><div class="skelet">Bezig met laden…</div></div></main>';

  koppelUitloggen();
  window.IK = ik;
  return ik;
}

/** Uitloggen, met bevestiging. Werkt voor elke knop met class "uit". */
async function uitloggen() {
  const ja = await bevestig({
    titel: 'Uitloggen?',
    tekst: '<p>Je wordt afgemeld en komt terug op het inlogscherm.</p>',
    ja: 'Ja, uitloggen',
    nee: 'Annuleren',
  });
  if (!ja) { return; }

  try { await api('auth.php?actie=logout', { method: 'POST', body: {} }); } catch (e) { /* toch uitloggen */ }
  localStorage.removeItem('voedselbank_token');
  location.href = 'login.html';
}

/**
 * Koppelt elke uitlogknop. Mag meerdere keren worden aangeroepen: pagina's
 * die zelf nog een knop tekenen (Mijn account) roepen dit opnieuw aan.
 */
function koppelUitloggen() {
  document.querySelectorAll('button.uit').forEach(knop => {
    if (knop.dataset.gekoppeld) { return; }
    knop.dataset.gekoppeld = '1';
    knop.addEventListener('click', uitloggen);
  });
}

/* ---------------------------------------------- drawer & modal */

function overlay() {
  let o = document.querySelector('.overlay');
  if (!o) {
    o = document.createElement('div');
    o.className = 'overlay';
    document.body.appendChild(o);
  }
  return o;
}

const drawer = {
  open(el) {
    overlay().classList.add('open');
    el.classList.add('open');
    overlay().onclick = () => drawer.dicht(el);
    document.onkeydown = (e) => { if (e.key === 'Escape') { drawer.dicht(el); } };
    const eerste = el.querySelector('input, select, textarea');
    if (eerste) { setTimeout(() => eerste.focus(), 180); }
  },
  dicht(el) {
    overlay().classList.remove('open');
    el.classList.remove('open');
    document.onkeydown = null;
  },
};

/** Ja/nee-vraag als nette modal in plaats van een kale confirm(). */
function bevestig({ titel, tekst, ja = 'Ja, doorgaan', nee = 'Annuleren', gevaar = false }) {
  return new Promise(resolve => {
    const o = overlay();
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = `
      <div class="kop"><h2>${esc(titel)}</h2></div>
      <div class="body">${tekst}</div>
      <div class="voet">
        <button class="knop" data-nee>${esc(nee)}</button>
        <button class="knop ${gevaar ? 'gevaar' : 'primair'}" data-ja>${esc(ja)}</button>
      </div>`;
    document.body.appendChild(m);
    o.classList.add('open');
    requestAnimationFrame(() => m.classList.add('open'));

    const sluit = (uitkomst) => {
      m.classList.remove('open');
      o.classList.remove('open');
      o.onclick = null;
      document.onkeydown = null;
      setTimeout(() => m.remove(), 200);
      resolve(uitkomst);
    };
    m.querySelector('[data-ja]').onclick = () => sluit(true);
    m.querySelector('[data-nee]').onclick = () => sluit(false);
    o.onclick = () => sluit(false);
    document.onkeydown = (e) => { if (e.key === 'Escape') { sluit(false); } };
    setTimeout(() => m.querySelector('[data-ja]').focus(), 120);
  });
}

/**
 * Klein formulier in een modal. velden = [{naam, label, type, waarde, opties, hint}]
 * Geeft een object met de ingevulde waarden terug, of null bij annuleren.
 */
function formulierModal({ titel, tekst = '', velden, ja = 'Opslaan', nee = 'Annuleren' }) {
  return new Promise(resolve => {
    const o = overlay();
    const m = document.createElement('div');
    m.className = 'modal';

    const html = velden.map(v => {
      if (v.type === 'select') {
        return `<div class="veld"><label for="m-${v.naam}">${esc(v.label)}</label>
          <select id="m-${v.naam}" name="${v.naam}">${v.opties.map(o =>
            `<option value="${esc(o.waarde)}"${o.waarde === v.waarde ? ' selected' : ''}>${esc(o.label)}</option>`).join('')}</select>
          ${v.hint ? '<div class="hint">' + esc(v.hint) + '</div>' : ''}</div>`;
      }
      return `<div class="veld"><label for="m-${v.naam}">${esc(v.label)}</label>
        <input id="m-${v.naam}" name="${v.naam}" type="${v.type || 'text'}"
               value="${esc(v.waarde ?? '')}"${v.min !== undefined ? ' min="' + v.min + '"' : ''}
               ${v.stap ? 'step="' + v.stap + '"' : ''}>
        ${v.hint ? '<div class="hint">' + esc(v.hint) + '</div>' : ''}</div>`;
    }).join('');

    m.innerHTML = `
      <div class="kop"><h2>${esc(titel)}</h2></div>
      <div class="body">${tekst}<form id="modalVorm">${html}</form></div>
      <div class="voet">
        <button class="knop" data-nee>${esc(nee)}</button>
        <button class="knop primair" data-ja>${esc(ja)}</button>
      </div>`;
    document.body.appendChild(m);
    o.classList.add('open');
    requestAnimationFrame(() => m.classList.add('open'));

    const sluit = (uitkomst) => {
      m.classList.remove('open');
      o.classList.remove('open');
      o.onclick = null;
      document.onkeydown = null;
      setTimeout(() => m.remove(), 200);
      resolve(uitkomst);
    };
    const opslaan = () => {
      const uit = {};
      velden.forEach(v => { uit[v.naam] = m.querySelector('[name="' + v.naam + '"]').value; });
      sluit(uit);
    };
    m.querySelector('[data-ja]').onclick = opslaan;
    m.querySelector('[data-nee]').onclick = () => sluit(null);
    m.querySelector('#modalVorm').onsubmit = (e) => { e.preventDefault(); opslaan(); };
    o.onclick = () => sluit(null);
    document.onkeydown = (e) => { if (e.key === 'Escape') { sluit(null); } };
    setTimeout(() => { const f = m.querySelector('input, select'); if (f) { f.focus(); f.select && f.select(); } }, 120);
  });
}

/* ---------------------------------------------- kleine hulpjes */

/** Zoekt in een lijst objecten op meerdere velden tegelijk. */
function zoekt(item, term, velden) {
  if (!term) { return true; }
  const t = term.toLowerCase();
  return velden.some(v => String(item[v] ?? '').toLowerCase().includes(t));
}

/** Eenvoudig staafdiagram, zonder externe bibliotheek. */
function staafdiagram(reeks) {
  if (!reeks.length) { return '<div class="leeg">Nog geen gegevens.</div>'; }
  const max = Math.max(...reeks.map(r => r.aantal), 1);
  return '<div class="staven">' + reeks.map(r => `
    <div class="kolom">
      <div class="getal">${getal(r.aantal)}</div>
      <div class="staaf" style="height:${Math.max(4, Math.round(r.aantal / max * 130))}px"
           title="${esc(r.label)}: ${getal(r.aantal)}"></div>
      <div class="naam">${esc(r.label)}</div>
    </div>`).join('') + '</div>';
}

/** Lijngrafiek als SVG, voor het voorraadverloop. */
function lijngrafiek(punten, opties = {}) {
  if (punten.length < 2) { return '<div class="leeg">Nog geen gegevens.</div>'; }
  const b = 620, h = 180, pad = 8;
  const waarden = punten.map(p => p.stand);
  const max = Math.max(...waarden), min = Math.min(...waarden);
  const bereik = Math.max(1, max - min);
  const xy = punten.map((p, i) => [
    pad + i * ((b - pad * 2) / (punten.length - 1)),
    h - pad - ((p.stand - min) / bereik) * (h - pad * 2),
  ]);
  const lijn = xy.map(([x, y], i) => (i ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1)).join(' ');
  const vlak = lijn + ` L${xy[xy.length - 1][0].toFixed(1)} ${h} L${xy[0][0].toFixed(1)} ${h} Z`;

  return `<svg class="lijngrafiek" viewBox="0 0 ${b} ${h}" preserveAspectRatio="none" role="img"
              aria-label="${esc(opties.label || 'Verloop')}">
    <defs><linearGradient id="vl" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#2E6B4A" stop-opacity=".22"/>
      <stop offset="100%" stop-color="#2E6B4A" stop-opacity="0"/>
    </linearGradient></defs>
    <path d="${vlak}" fill="url(#vl)"/>
    <path d="${lijn}" fill="none" stroke="#2E6B4A" stroke-width="2" stroke-linejoin="round"/>
  </svg>`;
}
