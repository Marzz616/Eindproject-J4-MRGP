<?php $__env->startSection('title', 'Producten & Voorraad'); ?>

<?php $__env->startSection('body'); ?>
<div class="app" id="app"></div>

<!-- toevoegen / bewerken -->
<div class="drawer" id="drawer">
  <div class="kop">
    <div style="flex:1">
      <h2 id="drawerTitel">Nieuw product</h2>
      <div class="onder" id="drawerOnder">Voeg een product toe aan het magazijn</div>
    </div>
    <button class="icoonknop" id="drawerDicht" aria-label="Sluiten">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="body">
    <form id="vorm" novalidate>
      <input type="hidden" name="id">
      <div class="veld">
        <label for="p-naam">Productnaam</label>
        <input type="text" id="p-naam" name="naam" required>
      </div>
      <div class="rij2">
        <div class="veld">
          <label for="p-ean">Streepjescode (EAN, 13 cijfers)</label>
          <input type="text" id="p-ean" name="ean" inputmode="numeric" maxlength="13" class="mono">
        </div>
        <div class="veld">
          <label for="p-cat">Categorie</label>
          <select id="p-cat" name="categorie"></select>
        </div>
      </div>
      <div class="rij3">
        <div class="veld">
          <label for="p-vrd">Voorraad</label>
          <input type="number" id="p-vrd" name="voorraad" min="0" value="0">
        </div>
        <div class="veld">
          <label for="p-min">Minimumvoorraad</label>
          <input type="number" id="p-min" name="min" min="0" value="0">
        </div>
        <div class="veld">
          <label for="p-waarde">Winkelwaarde (€)</label>
          <input type="number" id="p-waarde" name="waarde" min="0" step="0.01" value="0">
        </div>
      </div>
      <div class="rij2">
        <div class="veld">
          <label for="p-tht">Houdbaar tot (THT)</label>
          <input type="date" id="p-tht" name="tht">
          <div class="hint">Leeg laten bij producten zonder THT.</div>
        </div>
        <div class="veld">
          <label for="p-loc">Magazijnlocatie</label>
          <input type="text" id="p-loc" name="locatie" placeholder="Vak C-4">
        </div>
      </div>
      <div class="veld">
        <label>Allergenen / dieetkenmerken</label>
        <div class="chips" id="allergenen"></div>
        <div class="hint">Hiermee waarschuwt de applicatie bij het samenstellen van een pakket.</div>
      </div>
      <div class="melding grijs"><div>
        De voedselbank neemt geen diepvriesproducten en geen alcohol aan. Voer die dus niet in.
      </div></div>
    </form>
  </div>
  <div class="voet">
    <button class="knop" id="annuleer">Annuleren</button>
    <button class="knop primair" id="bewaar">Opslaan</button>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
const TAGS = ['gluten', 'lactose', 'noten', 'pinda', 'schaaldier', 'varkensvlees', 'vlees', 'dierlijk'];

let DATA = { producten: [], magazijn: {}, rechten: {} };
let CATS = [];
let filterCat = 0;
let term = '';
let sorteerOp = 'naam';      // US-VRD-04: sorteerbaar op alle getoonde kolommen
let sorteerOm = false;

(async function () {
  const ik = await start({
    slug: 'producten',
    titel: 'Producten & Voorraad',
    onder: 'Real-time overzicht van magazijnlocaties, voorraadstanden en THT-data',
  });
  if (!ik) { return; }

  document.getElementById('drawerDicht').onclick = () => drawer.dicht(document.getElementById('drawer'));
  document.getElementById('annuleer').onclick = () => drawer.dicht(document.getElementById('drawer'));
  document.getElementById('bewaar').onclick = bewaren;

  document.getElementById('allergenen').innerHTML = TAGS.map(t =>
    `<button type="button" class="chip" data-tag="${t}">${t}</button>`).join('');
  document.getElementById('allergenen').onclick = (e) => {
    const c = e.target.closest('.chip');
    if (c) { c.classList.toggle('aan'); }
  };

  await laden();
})();

async function laden() {
  try {
    DATA = await api('producten.php?actie=lijst');
    CATS = await api('producten.php?actie=categorieen');
  } catch (e) { foutMelden(e); return; }
  teken();
}

function teken() {
  const m = DATA.magazijn;
  const r = DATA.rechten;

  const zichtbaar = DATA.producten.filter(p =>
    (!filterCat || p.categorie.id === filterCat) &&
    (!term || (p.naam + ' ' + p.ean + ' ' + p.locatie + ' ' + p.categorie.naam).toLowerCase().includes(term)));

  const sleutel = {
    naam:      p => p.naam.toLowerCase(),
    ean:       p => p.ean,
    categorie: p => p.categorie.naam.toLowerCase(),
    voorraad:  p => p.voorraad,
    min:       p => p.min,
    tht:       p => p.tht || '9999-12-31',
    locatie:   p => (p.locatie || 'zzz').toLowerCase(),
  }[sorteerOp];

  zichtbaar.sort((a, b) => {
    const x = sleutel(a), y = sleutel(b);
    const v = x < y ? -1 : x > y ? 1 : 0;
    return sorteerOm ? -v : v;
  });

  document.getElementById('inhoud').innerHTML = `
    <div class="card" style="margin-bottom:18px">
      <div class="body" style="display:flex;gap:34px;flex-wrap:wrap;align-items:baseline">
        <div>
          <div style="font-size:12.5px;color:var(--ink-2);font-weight:600">In het magazijn</div>
          <div class="mono" style="font-size:27px;font-weight:500;line-height:1.2">${getal(m.totaal)}
            <span style="font-size:14px;color:var(--ink-3);font-family:'Source Sans 3',sans-serif">stuks</span></div>
        </div>
        <div>
          <div style="font-size:12.5px;color:var(--ink-2);font-weight:600">Verschillende producten</div>
          <div class="mono" style="font-size:27px;font-weight:500;line-height:1.2">${getal(m.soorten)}</div>
        </div>
        ${m.onderMin ? `<div>
          <div style="font-size:12.5px;color:var(--ink-2);font-weight:600">Onder de minimumvoorraad</div>
          <div class="mono" style="font-size:27px;font-weight:500;line-height:1.2;color:var(--amber)">${m.onderMin}</div>
        </div>` : ''}
        ${m.verlopen ? `<div>
          <div style="font-size:12.5px;color:var(--ink-2);font-weight:600">Over de datum</div>
          <div class="mono" style="font-size:27px;font-weight:500;line-height:1.2;color:var(--red)">${m.verlopen}</div>
        </div>` : ''}
      </div>
    </div>

    <div class="zoekbalk">
      <div class="zoek">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="zoek" placeholder="Zoek product, streepjescode of locatie…" value="${esc(term)}">
      </div>
      ${r.beheren ? '<button class="knop primair" id="nieuw">+ Nieuw product</button>' : ''}
      ${r.beheren ? '<button class="knop" id="catBeheer">Categorie\u00ebn</button>' : ''}
    </div>

    <div class="chips" style="margin-bottom:18px">
      <button class="chip ${filterCat === 0 ? 'aan' : ''}" data-cat="0">Alles</button>
      ${CATS.map(c => `<button class="chip ${filterCat === c.id ? 'aan' : ''}" data-cat="${c.id}" title="${esc(c.naam)}">${esc(c.kort)} <span style="opacity:.7">${c.aantal}</span></button>`).join('')}
    </div>

    <div class="card">
      <div class="kop">
        <h2>Productenoverzicht</h2>
        <span class="tel rechts">${zichtbaar.length} van ${DATA.producten.length} producten</span>
      </div>
      ${zichtbaar.length ? `
      <table class="lijst">
        <thead><tr>
          ${kolom('naam', 'Productnaam', 'width:30%')}
          ${kolom('ean', 'Streepjescode')}
          ${kolom('categorie', 'Categorie')}
          ${kolom('voorraad', 'Voorraad', '', true)}
          ${kolom('min', 'Min. voorraad', '', true)}
          ${kolom('tht', 'Houdbaar tot (THT)')}
          ${kolom('locatie', 'Locatie')}
          <th style="width:1%"></th>
        </tr></thead>
        <tbody>${zichtbaar.map(rij).join('')}</tbody>
      </table>` : '<div class="leeg"><h3>Geen producten gevonden</h3><div>Pas je zoekopdracht of het filter aan.</div></div>'}
    </div>`;

  document.getElementById('zoek').oninput = (e) => {
    term = e.target.value.trim().toLowerCase();
    const pos = e.target.selectionStart;
    teken();
    const veld = document.getElementById('zoek');
    veld.focus(); veld.setSelectionRange(pos, pos);
  };
  document.querySelectorAll('[data-cat]').forEach(b => b.onclick = () => {
    filterCat = Number(b.dataset.cat); teken();
  });
  document.querySelectorAll('[data-sorteer]').forEach(th => th.onclick = () => {
    const k = th.dataset.sorteer;
    if (sorteerOp === k) { sorteerOm = !sorteerOm; } else { sorteerOp = k; sorteerOm = false; }
    teken();
  });
  const nieuw = document.getElementById('nieuw');
  if (nieuw) { nieuw.onclick = () => openDrawer(null); }
  const cb = document.getElementById('catBeheer');
  if (cb) { cb.onclick = categorieBeheer; }

  document.querySelectorAll('[data-actie]').forEach(b => b.onclick = (e) => {
    e.stopPropagation();
    const p = DATA.producten.find(x => x.id === Number(b.dataset.id));
    if (b.dataset.actie === 'bewerk')     { openDrawer(p); }
    if (b.dataset.actie === 'voorraad')   { voorraadWijzigen(p); }
    if (b.dataset.actie === 'afvoeren')   { afvoeren(p); }
    if (b.dataset.actie === 'verwijder')  { verwijderen(p); }
  });
}

function kolom(sleutel, titel, stijl, rechts) {
  const aan = sorteerOp === sleutel;
  const pijl = aan ? (sorteerOm ? ' \u2193' : ' \u2191') : '';
  return `<th data-sorteer="${sleutel}" class="${rechts ? 'r' : ''}"
    style="cursor:pointer;user-select:none;${stijl || ''}${aan ? 'color:var(--green)' : ''}"
    title="Sorteren op ${esc(titel.toLowerCase())}">${esc(titel)}${pijl}</th>`;
}

function ico(actie, id, titel, pad, gevaar) {
  return `<button class="icoonknop" data-actie="${actie}" data-id="${id}" title="${esc(titel)}"
    aria-label="${esc(titel)}"${gevaar ? ' style="color:var(--red)"' : ''}>
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${pad}</svg></button>`;
}

function rij(p) {
  const r = DATA.rechten;
  const badge = p.verlopen
    ? '<span class="pil rood">Verlopen</span>'
    : p.bijnaVerlopen ? '<span class="pil amber">Bijna verlopen</span>' : '';

  const knoppen = [];
  if (r.corrigeren) { knoppen.push(ico('voorraad', p.id, 'Voorraad corrigeren', '<path d="M8 9l4-4 4 4M8 15l4 4 4-4"/>')); }
  if (r.corrigeren) { knoppen.push(ico('afvoeren', p.id, 'Afvoeren', '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>')); }
  if (r.beheren)    { knoppen.push(ico('bewerk',   p.id, 'Bewerken', '<path d="M4 20h4L19 9l-4-4L4 16z"/>')); }
  if (r.beheren)    { knoppen.push(ico('verwijder',p.id, 'Verwijderen', '<path d="M18 6L6 18M6 6l12 12"/>', true)); }

  return `<tr>
    <td>
      <div class="stapel">
        <div class="sterk">${esc(p.naam)}</div>
        <div class="horizontaal" style="gap:6px">
          ${badge}
          ${p.allergenen.length ? '<span class="zacht">' + p.allergenen.map(esc).join(', ') + '</span>' : ''}
        </div>
      </div>
    </td>
    <td class="mono zacht" style="font-size:12.5px">${esc(p.ean)}</td>
    <td><span class="badge grijs" title="${esc(p.categorie.naam)}">${esc(p.categorie.kort)}</span></td>
    <td class="r mono ${p.onderMin ? 'style-min' : ''}" ${p.onderMin ? 'style="color:var(--red);font-weight:600"' : ''}>${getal(p.voorraad)} st.</td>
    <td class="r mono zacht">${getal(p.min)} st.</td>
    <td>${p.tht ? datumKort(p.tht) + (p.thtDagen !== null && p.thtDagen >= 0 && p.thtDagen <= 7 ? ' <span class="zacht">(' + p.thtDagen + 'd)</span>' : '') : '<span class="zacht">geen THT</span>'}</td>
    <td class="mono zacht" style="white-space:nowrap">${esc(p.locatie || '—')}</td>
    <td><div class="horizontaal" style="gap:6px;justify-content:flex-end">${knoppen.join('')}</div></td>
  </tr>`;
}

/* ------------------------------------------------ toevoegen / bewerken */
function openDrawer(p) {
  const d = document.getElementById('drawer');
  const v = document.getElementById('vorm');
  v.querySelectorAll('.veld.mis').forEach(x => x.classList.remove('mis'));
  v.querySelectorAll('.fout').forEach(x => x.remove());

  document.getElementById('p-cat').innerHTML = CATS.map(c =>
    `<option value="${c.id}">${esc(c.naam)}</option>`).join('');

  document.getElementById('drawerTitel').textContent = p ? 'Product bewerken' : 'Nieuw product';
  document.getElementById('drawerOnder').textContent = p ? p.naam : 'Voeg een product toe aan het magazijn';

  v.id.value        = p ? p.id : '';
  v.naam.value      = p ? p.naam : '';
  v.ean.value       = p ? p.ean : '';
  v.categorie.value = p ? p.categorie.id : (CATS[0] ? CATS[0].id : '');
  v.voorraad.value  = p ? p.voorraad : 0;
  v.min.value       = p ? p.min : 0;
  v.waarde.value    = p ? p.waarde : 0;
  v.tht.value       = p && p.tht ? p.tht : '';
  v.locatie.value   = p && p.locatie ? p.locatie : '';

  document.querySelectorAll('#allergenen .chip').forEach(c => {
    c.classList.toggle('aan', !!(p && p.allergenen.includes(c.dataset.tag)));
  });

  drawer.open(d);
}

async function bewaren() {
  const v = document.getElementById('vorm');
  const tags = [...document.querySelectorAll('#allergenen .chip.aan')].map(c => c.dataset.tag);

  const lichaam = {
    id: Number(v.id.value) || 0,
    naam: v.naam.value.trim(),
    ean: v.ean.value.trim(),
    categorie: Number(v.categorie.value),
    voorraad: Number(v.voorraad.value),
    min: Number(v.min.value),
    waarde: Number(v.waarde.value),
    tht: v.tht.value,
    locatie: v.locatie.value.trim(),
    allergenen: tags.join(','),
  };

  try {
    const r = await api('producten.php?actie=opslaan', { body: lichaam });
    drawer.dicht(document.getElementById('drawer'));
    melding.goed(r.melding || 'Product opgeslagen.');
    await laden();
  } catch (e) {
    foutMelden(e, v);
  }
}

/* ------------------------------------------------ categorieën (PRD-5 / US-VRD-11) */
/**
 * Beheerpaneel: toevoegen, wijzigen en verwijderen. Het paneel blijft open tot
 * de gebruiker sluit, zodat je meerdere categorieën achter elkaar kunt aanpassen.
 */
async function categorieBeheer() {
  for (;;) {
    const keuze = await categoriePaneel();
    if (!keuze) { return; }

    const cat = CATS.find(c => c.id === keuze.id);
    if (keuze.actie === 'nieuw')      { await categorieVorm(null); }
    if (keuze.actie === 'bewerk')     { await categorieVorm(cat); }
    if (keuze.actie === 'verwijder')  { await categorieVerwijderen(cat); }
    await laden();
  }
}

/** Lijst met alle categorieën; geeft de aangeklikte actie terug, of null. */
function categoriePaneel() {
  return new Promise(resolve => {
    const o = overlay();
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = `
      <div class="kop"><h2>Categorie\u00ebn beheren</h2></div>
      <div class="body">
        <p style="margin-top:0">Elk product hoort bij \u00e9\u00e9n categorie. De vrijwilliger ziet de
        voorraad per categorie gegroepeerd bij het samenstellen van een pakket.</p>
        <div class="inner" style="--subkolommen:2.2fr .8fr 1.5fr;max-height:46vh;overflow:auto">
          <div class="ihead"><span>Categorie</span><span>Producten</span><span></span></div>
          ${CATS.map(c => `<div class="irow">
            <div class="p">${esc(c.naam)}
              ${c.oms ? `<div style="font-size:12.5px;color:var(--ink-3);font-weight:400">${esc(c.oms)}</div>` : ''}</div>
            <div class="mono">${c.aantal}</div>
            <div style="display:flex;gap:7px;justify-content:flex-end">
              <button class="knop klein" data-actie="bewerk" data-id="${c.id}">Bewerken</button>
              <button class="knop klein gevaar" data-actie="verwijder" data-id="${c.id}">Verwijderen</button>
            </div></div>`).join('')}
        </div>
      </div>
      <div class="voet">
        <button class="knop" data-nee>Sluiten</button>
        <button class="knop primair" data-actie="nieuw">+ Nieuwe categorie</button>
      </div>`;
    document.body.appendChild(m);
    o.classList.add('open');
    requestAnimationFrame(() => m.classList.add('open'));

    const sluit = (uit) => {
      m.classList.remove('open');
      o.classList.remove('open');
      o.onclick = null;
      document.onkeydown = null;
      setTimeout(() => m.remove(), 200);
      resolve(uit);
    };
    m.querySelectorAll('[data-actie]').forEach(b => b.onclick = () =>
      sluit({ actie: b.dataset.actie, id: Number(b.dataset.id) || 0 }));
    m.querySelector('[data-nee]').onclick = () => sluit(null);
    o.onclick = () => sluit(null);
    document.onkeydown = (e) => { if (e.key === 'Escape') { sluit(null); } };
  });
}

/** Nieuwe categorie of een bestaande wijzigen. */
async function categorieVorm(cat) {
  const uit = await formulierModal({
    titel: cat ? 'Categorie wijzigen' : 'Nieuwe categorie',
    tekst: cat
      ? `<p>${esc(cat.naam)} \u2014 ${cat.aantal} ${cat.aantal === 1 ? 'product' : 'producten'}.</p>`
      : '<p>De categorie komt daarna in de keuzelijst bij elk product te staan.</p>',
    velden: [
      { naam: 'naam', label: 'Volledige naam', type: 'text', waarde: cat ? cat.naam : '' },
      { naam: 'kort', label: 'Korte naam voor de filterknop', type: 'text', waarde: cat ? cat.kort : '',
        hint: 'Laat leeg om de eerste 20 tekens van de naam te gebruiken.' },
      { naam: 'oms', label: 'Omschrijving', type: 'text', waarde: cat && cat.oms ? cat.oms : '' },
    ],
    ja: 'Opslaan',
  });
  if (!uit || !uit.naam.trim()) { return; }

  try {
    await api('producten.php?actie=categorie', {
      body: { id: cat ? cat.id : 0, naam: uit.naam.trim(), kort: uit.kort.trim(), oms: uit.oms.trim() },
    });
    melding.goed(cat ? 'Categorie bijgewerkt.' : 'Categorie toegevoegd.');
  } catch (e) {
    const f = (e.data || {}).fouten;
    foutMelden(f ? { ...e, data: { fout: Object.values(f)[0] } } : e);
  }
}

/**
 * Verwijderen mag alleen als de categorie leeg is: anders zouden de producten
 * meeverdwijnen, en daarmee de pakketten waar ze in zaten. Staan er nog
 * producten in, dan bieden we aan ze eerst te verhuizen.
 */
async function categorieVerwijderen(cat) {
  if (!cat) { return; }

  const ja = await bevestig({
    titel: 'Categorie verwijderen?',
    tekst: `<p><b>${esc(cat.naam)}</b> verdwijnt uit de keuzelijst en uit de filterknoppen.
            De producten zelf blijven staan.</p>`,
    ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) { return; }

  try {
    const r = await api('producten.php?actie=categorie_verwijderen', { body: { id: cat.id } });
    melding.goed(r.melding || 'Categorie verwijderd.');
  } catch (e) {
    const d = e.data || {};
    if (e.status === 409 && d.reden === 'heeft_producten') { await categorieVerhuizen(cat, d); return; }
    foutMelden(e);
  }
}

/** Producten uit een volle categorie naar een andere zetten, en dan pas weg. */
async function categorieVerhuizen(cat, d) {
  const andere = CATS.filter(c => c.id !== cat.id);
  if (!andere.length) {
    await bevestig({ titel: 'Dit is de laatste categorie',
      tekst: '<p>Maak eerst een andere categorie aan om de producten in te zetten.</p>',
      ja: 'Sluiten', nee: 'Terug' });
    return;
  }

  const uit = await formulierModal({
    titel: 'Deze categorie is niet leeg',
    tekst: `<p>${esc(d.fout)} ${esc(d.suggestie)}</p>`,
    velden: [{ naam: 'naar', label: 'Zet de producten in', type: 'select', waarde: String(andere[0].id),
               opties: andere.map(c => ({ waarde: String(c.id), label: c.naam })) }],
    ja: 'Verplaatsen en verwijderen', nee: 'Annuleren',
  });
  if (!uit) { return; }

  try {
    const v = await api('producten.php?actie=categorie_verplaatsen',
                        { body: { van: cat.id, naar: Number(uit.naar) } });
    const r = await api('producten.php?actie=categorie_verwijderen', { body: { id: cat.id } });
    melding.goed(v.verplaatst + (v.verplaatst === 1 ? ' product verplaatst. ' : ' producten verplaatst. ') +
                 (r.melding || 'Categorie verwijderd.'));
  } catch (e) { foutMelden(e); }
}

/* ------------------------------------------------ voorraad corrigeren */
async function voorraadWijzigen(p) {
  const uit = await formulierModal({
    titel: 'Voorraad corrigeren',
    tekst: `<p>${esc(p.naam)} — nu <b class="mono">${getal(p.voorraad)}</b> stuks in ${esc(p.locatie || 'het magazijn')}.</p>`,
    velden: [{ naam: 'aantal', label: 'Nieuwe voorraad (stuks)', type: 'number', min: 0, waarde: p.voorraad }],
    ja: 'Voorraad bijwerken',
  });
  if (!uit) { return; }

  const n = Number(uit.aantal);
  if (!Number.isFinite(n) || n < 0) { melding.fout('Vul een getal van 0 of hoger in.'); return; }

  try {
    const r = await api('producten.php?actie=voorraad', { body: { id: p.id, aantal: n } });
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e); }
}

/* ------------------------------------------------ afvoeren */
async function afvoeren(p) {
  const uit = await formulierModal({
    titel: 'Product afvoeren',
    tekst: `<p>Wat gaat er uit het magazijn zonder dat het in een pakket komt?
            Dit telt mee in het verspillingsrapport.</p>`,
    velden: [
      { naam: 'aantal', label: 'Aantal stuks', type: 'number', min: 1, waarde: 1,
        hint: 'Er ligt ' + p.voorraad + ' stuks van ' + p.naam + '.' },
      { naam: 'reden', label: 'Reden', type: 'select', waarde: 'Verspild', opties: [
        { waarde: 'Verspild', label: 'Verspild / afgevoerd' },
        { waarde: 'Retour leverancier', label: 'Retour naar de leverancier' },
        { waarde: 'Veeteelt', label: 'Naar de veeteelt' },
      ] },
      { naam: 'toelichting', label: 'Toelichting (optioneel)', type: 'text', waarde: '' },
    ],
    ja: 'Afvoeren',
  });
  if (!uit) { return; }

  try {
    const r = await api('producten.php?actie=afvoeren', {
      body: { id: p.id, aantal: Number(uit.aantal), reden: uit.reden, toelichting: uit.toelichting },
    });
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e); }
}

/* ------------------------------------------------ verwijderen */
async function verwijderen(p) {
  const ja = await bevestig({
    titel: 'Product verwijderen?',
    tekst: `<p>Weet je zeker dat je <b>${esc(p.naam)}</b> uit het magazijn wilt halen?
            Dit kan niet ongedaan worden gemaakt.</p>`,
    ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) { return; }

  try {
    const r = await api('producten.php?actie=verwijderen', { body: { id: p.id } });
    melding.goed(r.melding || 'Product verwijderd.');
    await laden();
  } catch (e) {
    const d = e.data || {};
    if (e.status === 409 && d.reden) {
      await bevestig({
        titel: 'Dit product kan niet weg',
        tekst: `<p><b>${esc(d.product || p.naam)}</b> komt voor in
                ${d.reden === 'in_pakket' ? 'uitgedeelde voedselpakketten'
                  : d.reden === 'in_levering' ? 'de leveringshistorie' : 'de afvoerhistorie'}
                (${d.aantal || ''} registratie${d.aantal === 1 ? '' : 's'}).</p>
                <p>Die geschiedenis moet bewaard blijven voor de rapportages, dus het product
                blijft in de database staan.</p>
                <p><b>Tip:</b> ${esc(d.suggestie || 'Zet de voorraad op 0.')}</p>`,
        ja: 'Begrepen', nee: 'Sluiten',
      });
      return;
    }
    foutMelden(e);
  }
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\guill\Herd\voedselbank-2\resources\views/pages/producten.blade.php ENDPATH**/ ?>