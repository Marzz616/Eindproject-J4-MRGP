@extends('layouts.app')

@section('title', 'Vrijdag uitgiftepunt')

@push('styles')
<style>
  .twee { display: grid; grid-template-columns: 1.2fr 1fr; gap: 18px; align-items: start; }
  @media (max-width: 1100px) { .twee { grid-template-columns: 1fr; } }
  .gegeven { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-top: 16px; }
  .gegeven > div { background: var(--row-alt); border: 1px solid var(--line); border-radius: 9px; padding: 12px 14px; }
  .gegeven .lab { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); font-weight: 700; }
  .gegeven .wrd { font-weight: 600; margin-top: 3px; }
  .wachtrij { display: flex; gap: 11px; align-items: center; padding: 12px 20px; border-bottom: 1px solid var(--line); }
  .wachtrij:last-child { border-bottom: 0; }
  .wachtrij:hover { background: var(--row-alt); cursor: pointer; }
</style>
@endpush

@section('body')
<div class="app" id="app"></div>
@endsection

@push('scripts')
<script>
let WACHTRIJ = null;
let HUIDIG = null;

(async function () {
  const ik = await start({
    slug: 'uitgifte',
    titel: 'Vrijdag uitgiftepunt',
    onder: 'Registratie en verificatie van pakketuitgifte aan cliënten',
  });
  if (!ik) { return; }
  await laden();
})();

async function laden() {
  try { WACHTRIJ = await api('uitgifte.php?actie=wachtrij'); }
  catch (e) { foutMelden(e); return; }
  teken();
}

function teken() {
  document.getElementById('inhoud').innerHTML = `
    <div class="twee">
      <div id="links"></div>
      <div id="rechts"></div>
    </div>`;
  tekenLinks();
  tekenRechts();
}

function tekenLinks() {
  document.getElementById('links').innerHTML = `
    <div class="card" style="margin-bottom:18px">
      <div class="kop"><h2>Cliënt zoeken</h2></div>
      <div class="body">
        <div class="veld" style="margin-bottom:0">
          <label for="q">Scan het pasje of typ het cliëntnummer of de naam</label>
          <div style="display:flex;gap:10px">
            <input type="text" id="q" placeholder="bijv. K-002, 2 of El Hassani" autofocus>
            <button class="knop primair" id="zoekKnop">Zoeken</button>
          </div>
          <div class="hint">Je vindt het cliëntnummer ook op de klantenpagina.</div>
        </div>
      </div>
    </div>
    <div id="kaart"></div>`;

  document.getElementById('zoekKnop').onclick = zoeken;
  document.getElementById('q').onkeydown = (e) => { if (e.key === 'Enter') { zoeken(); } };

  tekenKaart();
}

async function zoeken() {
  const q = document.getElementById('q').value.trim();
  if (!q) { melding.fout('Vul een cliëntnummer of naam in.'); return; }

  try {
    const r = await api('uitgifte.php?actie=zoek&q=' + encodeURIComponent(q));
    if (r.resultaten.length === 1) { HUIDIG = r.resultaten[0]; }
    else {
      const keuze = await formulierModal({
        titel: 'Meerdere cliënten gevonden',
        velden: [{ naam: 'k', label: 'Om welk gezin gaat het?', type: 'select',
          opties: r.resultaten.map((x, i) => ({ waarde: String(i), label: x.klant.nummer + ' · ' + x.klant.naam })) }],
        ja: 'Kiezen',
      });
      if (!keuze) { return; }
      HUIDIG = r.resultaten[Number(keuze.k)];
    }
    tekenKaart();
  } catch (e) { foutMelden(e); }
}

function tekenKaart() {
  const bak = document.getElementById('kaart');
  if (!HUIDIG) {
    bak.innerHTML = `<div class="card"><div class="leeg">
      <h3>Nog geen cliënt gekozen</h3>
      <div>Zoek hierboven een gezin, of klik rechts op een pakket uit de wachtrij.</div>
    </div></div>`;
    return;
  }

  const k = HUIDIG.klant;
  const totaal = HUIDIG.inhoud.reduce((s, r) => s + r.aantal, 0);

  if (HUIDIG.geenPakket) {
    bak.innerHTML = `
      <div class="card">
        <div class="kop"><h2>Cliëntidentificatie</h2></div>
        <div class="body">
          <div style="font-family:'Zilla Slab',serif;font-size:21px;font-weight:700">${esc(k.naam)}</div>
          <div class="mono" style="color:var(--ink-2)">${esc(k.nummer)} · ${esc(k.adres)}</div>
          <div class="melding amber" style="margin-top:16px"><div>
            <b>Er staat deze week geen pakket klaar voor dit gezin.</b><br>
            Laat het pakket eerst samenstellen op de pagina Voedselpakketten.
          </div></div>
          <a class="knop" href="/pakketten">Naar Voedselpakketten</a>
        </div>
      </div>`;
    return;
  }

  const uitgegeven = HUIDIG.status === 'Uitgegeven';
  const concept    = HUIDIG.status === 'Concept';

  bak.innerHTML = `
    <div class="card" style="margin-bottom:18px">
      <div class="kop"><h2>Cliëntidentificatie</h2>
        <span class="rechts">${uitgegeven ? '<span class="pil2 off">Al uitgegeven</span>'
          : concept ? '<span class="pil2 wacht">Nog concept</span>' : '<span class="pil2 ok">Klaar voor uitgifte</span>'}</span>
      </div>
      <div class="body">
        <div style="font-family:'Zilla Slab',serif;font-size:21px;font-weight:700">${esc(k.naam)}</div>
        <div class="mono" style="color:var(--ink-2)">${esc(k.nummer)} · ${esc(k.adres)}</div>

        <div class="gegeven">
          <div><div class="lab">Huishoudengrootte</div><div class="wrd">${k.gezinsgrootte} ${k.gezinsgrootte === 1 ? 'persoon' : 'personen'}</div></div>
          <div><div class="lab">Dieetrestrictie</div><div class="wrd">${HUIDIG.wensen.length ? HUIDIG.wensen.map(esc).join(', ') : 'Geen'}</div></div>
          <div><div class="lab">Identificatiestatus</div>
            <div class="wrd" style="${HUIDIG.gecontroleerd ? 'color:var(--green)' : 'color:var(--amber)'}">
              ${HUIDIG.gecontroleerd ? 'Geïdentificeerd met: ' + esc(HUIDIG.legitimatie) : 'Nog niet gecontroleerd'}
            </div></div>
        </div>

        ${!uitgegeven ? `<button class="knop" id="identiteit" style="margin-top:16px">
          ${HUIDIG.gecontroleerd ? 'Legitimatie wijzigen' : 'Legitimatie vastleggen'}</button>` : ''}
      </div>
    </div>

    <div class="card">
      <div class="kop"><h2>Gereedstaand voedselpakket</h2><span class="tel rechts">${totaal} stuks</span></div>
      <div style="border-top:0">
        ${HUIDIG.inhoud.map(r => `
          <div class="wachtrij" style="cursor:default">
            <div class="mono" style="width:38px;font-weight:500">${r.aantal}×</div>
            <div style="flex:1"><div style="font-weight:600">${esc(r.naam)}</div>
              <div style="font-size:12.5px;color:var(--ink-3)">${esc(r.categorie)}</div></div>
          </div>`).join('')}
      </div>
      <div class="body" style="border-top:1px solid var(--line)">
        ${uitgegeven
          ? `<div class="melding groen"><div>Dit pakket is uitgegeven op ${datumKort(HUIDIG.uitgifte)}.</div></div>
             <button class="knop" id="pakbon">Pakbon bekijken</button>`
          : `<p style="font-size:13.5px;color:var(--ink-2);margin:0 0 14px">
               Door op de onderstaande knop te klikken bevestigt u dat het pakket fysiek is overhandigd
               aan de gemachtigde vertegenwoordiger van het gezin.
             </p>
             <div style="display:flex;gap:10px;flex-wrap:wrap">
               <button class="knop primair groot" id="uitgeven">Pakket uitgegeven bevestigen</button>
               <button class="knop" id="pakbon">Pakbon afdrukken</button>
               <button class="knop gevaar" id="nietopgehaald">Niet opgehaald</button>
             </div>`}
      </div>
    </div>`;

  const i = document.getElementById('identiteit');
  if (i) { i.onclick = legitimatie; }
  const u = document.getElementById('uitgeven');
  if (u) { u.onclick = uitgeven; }
  const n = document.getElementById('nietopgehaald');
  if (n) { n.onclick = nietOpgehaald; }
  const pb = document.getElementById('pakbon');
  if (pb) { pb.onclick = () => window.open('/pakbon?id=' + HUIDIG.pakketId, '_blank'); }
}

function tekenRechts() {
  const w = WACHTRIJ;
  const blok = (titel, lijst, leeg) => `
    <div class="card" style="margin-bottom:18px">
      <div class="kop"><h2>${esc(titel)}</h2><span class="tel rechts">${lijst.length}</span></div>
      ${lijst.length ? lijst.map(p => `
        <div class="wachtrij" data-pakket="${p.pakketId}">
          <div style="flex:1">
            <div style="font-weight:600">${esc(p.klant)}</div>
            <div style="font-size:12.5px;color:var(--ink-3)">
              ${esc(p.nummer)} · ${p.gezinsgrootte} pers. · ${p.aantal} stuks
            </div>
          </div>
          ${p.status === 'Uitgegeven'
            ? '<span class="pil2 off">' + datumKort(p.uitgifte) + '</span>'
            : p.gecontroleerd ? '<span class="pil2 ok">Gelegitimeerd</span>' : '<span class="pil2 wacht">Wacht</span>'}
        </div>`).join('') : '<div class="leeg" style="padding:26px">' + esc(leeg) + '</div>'}
    </div>`;

  document.getElementById('rechts').innerHTML =
    blok('Klaar voor uitgifte', w.klaar, 'Er staat niets klaar.') +
    blok('Nog in concept', w.concept, 'Geen concepten.') +
    blok('Vandaag al uitgegeven', w.uitgegeven, 'Nog niets uitgegeven deze week.');

  document.querySelectorAll('[data-pakket]').forEach(el => el.onclick = async () => {
    try {
      HUIDIG = await api('uitgifte.php?actie=kaart&id=' + el.dataset.pakket);
      tekenKaart();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) { foutMelden(e); }
  });
}

/* ------------------------------------------------ acties */
async function legitimatie() {
  const uit = await formulierModal({
    titel: 'Legitimatie vastleggen',
    tekst: `<p>Controleer het legitimatiebewijs van de persoon die het pakket ophaalt
            voor <b>${esc(HUIDIG.klant.naam)}</b>.</p>`,
    velden: [{ naam: 'legitimatie', label: 'Soort legitimatiebewijs', type: 'select',
      waarde: HUIDIG.legitimatie || 'Paspoort',
      opties: [
        { waarde: 'Paspoort', label: 'Paspoort' },
        { waarde: 'Rijbewijs', label: 'Rijbewijs' },
        { waarde: 'ID-kaart', label: 'ID-kaart' },
      ] }],
    ja: 'Vastleggen',
  });
  if (!uit) { return; }

  try {
    const r = await api('uitgifte.php?actie=identiteit', {
      body: { pakketId: HUIDIG.pakketId, legitimatie: uit.legitimatie },
    });
    melding.goed(r.melding);
    HUIDIG.gecontroleerd = true;
    HUIDIG.legitimatie = uit.legitimatie;
    tekenKaart();
    await verversWachtrij();
  } catch (e) { foutMelden(e); }
}

async function uitgeven() {
  const ja = await bevestig({
    titel: 'Pakket uitgeven?',
    tekst: `<p>Bevestig dat het pakket voor <b>${esc(HUIDIG.klant.naam)}</b> fysiek is overhandigd.
            Daarna kan het pakket niet meer gewijzigd worden.</p>`,
    ja: 'Ja, uitgegeven',
  });
  if (!ja) { return; }

  try {
    const r = await api('uitgifte.php?actie=uitgeven', { body: { pakketId: HUIDIG.pakketId } });
    melding.goed(r.melding);
    HUIDIG.status = 'Uitgegeven';
    HUIDIG.uitgifte = new Date().toISOString().slice(0, 10);
    tekenKaart();
    await verversWachtrij();
  } catch (e) {
    const d = e.data || {};
    if (e.status === 409 && d.reden === 'geen_identiteit') {
      await bevestig({ titel: 'Eerst legitimeren', tekst: '<p>' + esc(d.fout) + '</p>', ja: 'Begrepen', nee: 'Sluiten' });
      return;
    }
    foutMelden(e);
  }
}

async function nietOpgehaald() {
  const ja = await bevestig({
    titel: 'Pakket niet opgehaald?',
    tekst: `<p>Het pakket voor <b>${esc(HUIDIG.klant.naam)}</b> wordt verwijderd en alle producten
            gaan terug naar de voorraad.</p>`,
    ja: 'Ja, terugboeken', gevaar: true,
  });
  if (!ja) { return; }

  try {
    const r = await api('uitgifte.php?actie=nietopgehaald', { body: { pakketId: HUIDIG.pakketId } });
    melding.goed(r.melding);
    HUIDIG = null;
    await laden();
  } catch (e) { foutMelden(e); }
}

async function verversWachtrij() {
  try { WACHTRIJ = await api('uitgifte.php?actie=wachtrij'); tekenRechts(); } catch (e) { /* stil */ }
}
</script>
@endpush
