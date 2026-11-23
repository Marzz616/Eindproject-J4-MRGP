<?php $__env->startSection('title', 'Leveranciersbeheer'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  #lijst { --kolommen: 22px 2fr 1.4fr 1.2fr 1.9fr 1.1fr 1.1fr .8fr; }
  #lijst .inner { --subkolommen: 1fr 2.4fr .8fr 1.2fr; }
  .regel { display: grid; grid-template-columns: 2.4fr .8fr auto; gap: 10px; align-items: start; margin-bottom: 10px; }
  @media (max-width: 1280px) {
    #lijst { --kolommen: 22px 2fr 1.4fr 1.9fr 1.1fr .8fr; }
    #lijst .c-tel, .thead span.c-tel, #lijst .c-laatste, .thead span.c-laatste { display: none; }
  }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('body'); ?>
<div class="app" id="app"></div>

<!-- leverancier toevoegen / bewerken -->
<div class="drawer" id="drawer">
  <div class="kop">
    <div style="flex:1">
      <h2 id="drawerTitel">Nieuwe leverancier</h2>
      <div class="onder" id="drawerOnder">Voeg een voedseldonateur toe</div>
    </div>
    <button class="icoonknop" id="drawerDicht" aria-label="Sluiten">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="body">
    <form id="vorm" novalidate>
      <input type="hidden" name="id">
      <div class="veld">
        <label for="l-naam">Bedrijfsnaam</label>
        <input type="text" id="l-naam" name="bedrijfsnaam" placeholder="Albert Heijn Maaskant">
      </div>
      <div class="rij2">
        <div class="veld">
          <label for="l-contact">Contactpersoon</label>
          <input type="text" id="l-contact" name="contactpersoon">
        </div>
        <div class="veld">
          <label for="l-cat">Categorie</label>
          <select id="l-cat" name="categorie">
            <option>Supermarkt</option><option>Bakkerij</option><option>Groothandel</option>
            <option>Zuivel</option><option>Kweker</option><option>Particulier</option><option>Overig</option>
          </select>
        </div>
      </div>
      <div class="rij2">
        <div class="veld">
          <label for="l-tel">Telefoonnummer</label>
          <input type="text" id="l-tel" name="telefoon" placeholder="06-12345678">
        </div>
        <div class="veld">
          <label for="l-mail">E-mailadres</label>
          <input type="email" id="l-mail" name="email">
        </div>
      </div>
      <div class="veld">
        <label for="l-adres">Adres</label>
        <input type="text" id="l-adres" name="adres">
      </div>
      <div class="rij2">
        <div class="veld">
          <label for="l-pc">Postcode</label>
          <input type="text" id="l-pc" name="postcode" placeholder="5492 AA" maxlength="7">
        </div>
        <div class="veld">
          <label for="l-plaats">Plaats</label>
          <input type="text" id="l-plaats" name="plaats" value="Maaskantje">
        </div>
      </div>
      <h3 style="font-size:15px;margin:20px 0 12px">Volgende levering (optioneel)</h3>
      <div class="rij2">
        <div class="veld">
          <label for="l-datum">Datum</label>
          <input type="date" id="l-datum" name="volgendeDatum">
        </div>
        <div class="veld">
          <label for="l-tijd">Tijd</label>
          <input type="time" id="l-tijd" name="volgendeTijd">
        </div>
      </div>
    </form>
  </div>
  <div class="voet">
    <button class="knop" id="annuleer">Annuleren</button>
    <button class="knop primair" id="bewaar">Opslaan</button>
  </div>
</div>

<!-- levering boeken -->
<div class="drawer" id="drawerLev">
  <div class="kop">
    <div style="flex:1">
      <h2>Levering inboeken</h2>
      <div class="onder" id="levOnder"></div>
    </div>
    <button class="icoonknop" id="levDicht" aria-label="Sluiten">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="body">
    <form id="levVorm" novalidate>
      <input type="hidden" name="leverancier">
      <div class="rij2">
        <div class="veld"><label for="lv-datum">Leverdatum</label><input type="date" id="lv-datum" name="datum"></div>
        <div class="veld"><label for="lv-tijd">Tijd</label><input type="time" id="lv-tijd" name="tijd"></div>
      </div>
      <h3 style="font-size:15px;margin:18px 0 12px">Wat is er geleverd?</h3>
      <div id="regels"></div>
      <button type="button" class="knop klein" id="regelErbij">+ Product toevoegen</button>
      <div class="hint" style="margin-top:8px">De voorraad van deze producten wordt automatisch opgehoogd.</div>
    </form>
  </div>
  <div class="voet">
    <button class="knop" id="levAnnuleer">Annuleren</button>
    <button class="knop primair" id="levBewaar">Levering boeken</button>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
let LEV = [], RECHTEN = {}, PRODUCTEN = [];
let term = '', filterCat = '';
let sorteer = 'volgende';     // standaard: wie het eerst komt leveren, bovenaan
const open = new Set();

(async function () {
  const ik = await start({
    slug: 'leveranciers',
    titel: 'Leveranciersbeheer',
    onder: 'Beheer de toelevering, contacten en donatiegeschiedenis van voedseldonateurs',
  });
  if (!ik) { return; }

  document.getElementById('drawerDicht').onclick = () => drawer.dicht(document.getElementById('drawer'));
  document.getElementById('annuleer').onclick   = () => drawer.dicht(document.getElementById('drawer'));
  document.getElementById('bewaar').onclick     = bewaren;

  document.getElementById('levDicht').onclick     = () => drawer.dicht(document.getElementById('drawerLev'));
  document.getElementById('levAnnuleer').onclick  = () => drawer.dicht(document.getElementById('drawerLev'));
  document.getElementById('levBewaar').onclick    = () => boekLevering();
  document.getElementById('regelErbij').onclick   = () => regelRij();

  await laden();
})();

async function laden() {
  try {
    const r = await api('leveranciers.php?actie=lijst');
    LEV = r.leveranciers;
    RECHTEN = r.rechten;
    const p = await api('producten.php?actie=lijst');
    PRODUCTEN = p.producten;
  } catch (e) { foutMelden(e); return; }
  teken();
}

function teken() {
  const cats = [...new Set(LEV.map(l => l.categorie))].sort();
  const zichtbaar = LEV.filter(l =>
    (!filterCat || l.categorie === filterCat) &&
    (!term || [l.bedrijfsnaam, l.contactpersoon, l.email, l.categorie, l.plaats]
      .join(' ').toLowerCase().includes(term)));

  // Sorteren. Bij "eerstvolgende levering" komen leveranciers zonder geplande
  // datum onderaan, anders zouden ze de agenda bovenaan vullen.
  const ver = '9999-12-31';
  const sleutels = {
    volgende: l => (l.actief ? '0' : '1') + (l.volgendeDatum || ver) + (l.volgendeTijd || '99:99'),
    naam:     l => (l.actief ? '0' : '1') + l.bedrijfsnaam.toLowerCase(),
    laatste:  l => (l.laatste ? l.laatste : '0000-00-00'),
    categorie:l => l.categorie.toLowerCase() + l.bedrijfsnaam.toLowerCase(),
  };
  const sleutel = sleutels[sorteer] || sleutels.volgende;
  zichtbaar.sort((a, b) => {
    const x = sleutel(a), y = sleutel(b);
    const v = x < y ? -1 : x > y ? 1 : 0;
    return sorteer === 'laatste' ? -v : v;    // laatste levering: nieuwste eerst
  });

  document.getElementById('inhoud').innerHTML = `
    <div class="zoekbalk">
      <div class="zoek">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="zoek" placeholder="Zoek leverancier op naam, contactpersoon of categorie…" value="${esc(term)}">
      </div>
      <select id="cat" style="height:42px;border:1px solid var(--line-2);border-radius:9px;padding:0 12px;font:inherit;background:#fff">
        <option value="">Categorie: alle</option>
        ${cats.map(c => `<option${filterCat === c ? ' selected' : ''}>${esc(c)}</option>`).join('')}
      </select>
      <select id="sorteer" style="height:42px;border:1px solid var(--line-2);border-radius:9px;padding:0 12px;font:inherit;background:#fff">
        <option value="volgende"${sorteer === 'volgende' ? ' selected' : ''}>Sorteer: eerstvolgende levering</option>
        <option value="naam"${sorteer === 'naam' ? ' selected' : ''}>Sorteer: bedrijfsnaam</option>
        <option value="laatste"${sorteer === 'laatste' ? ' selected' : ''}>Sorteer: laatste levering</option>
        <option value="categorie"${sorteer === 'categorie' ? ' selected' : ''}>Sorteer: categorie</option>
      </select>
      ${RECHTEN.beheren ? '<button class="knop primair" id="nieuw">+ Nieuwe leverancier</button>' : ''}
    </div>

    <div class="tbl" id="lijst">
      <div class="thead">
        <span></span><span>Leverancier</span><span>Contactpersoon</span><span class="c-tel">Telefoon</span>
        <span>E-mail</span><span>Categorie</span><span class="c-laatste">Laatste levering</span><span>Status</span>
      </div>
      ${zichtbaar.length ? zichtbaar.map(groep).join('')
        : '<div class="leeg"><h3>Geen leveranciers gevonden</h3><div>Pas je zoekopdracht of het filter aan.</div></div>'}
    </div>`;

  document.getElementById('zoek').oninput = (e) => {
    term = e.target.value.trim().toLowerCase();
    const pos = e.target.selectionStart;
    teken();
    const v = document.getElementById('zoek'); v.focus(); v.setSelectionRange(pos, pos);
  };
  document.getElementById('cat').onchange = (e) => { filterCat = e.target.value; teken(); };
  document.getElementById('sorteer').onchange = (e) => { sorteer = e.target.value; teken(); };
  const n = document.getElementById('nieuw');
  if (n) { n.onclick = () => openDrawer(null); }

  document.querySelectorAll('.grp .trow').forEach(r => r.onclick = () => {
    const id = Number(r.closest('.grp').dataset.id);
    if (open.has(id)) { open.delete(id); } else { open.add(id); }
    r.closest('.grp').classList.toggle('open');
  });

  document.querySelectorAll('[data-actie]').forEach(b => b.onclick = (e) => {
    e.stopPropagation();
    const l = LEV.find(x => x.id === Number(b.dataset.id));
    if (b.dataset.actie === 'bewerk')    { openDrawer(l); }
    if (b.dataset.actie === 'levering')  { openLevering(l); }
    if (b.dataset.actie === 'actief')    { zetActief(l, !l.actief); }
    if (b.dataset.actie === 'verwijder') { verwijderen(l); }
  });
}

function groep(l) {
  const acties = [];
  if (RECHTEN.boeken && l.actief) {
    acties.push(`<button class="knop klein primair" data-actie="levering" data-id="${l.id}">Levering inboeken</button>`);
  }
  if (RECHTEN.beheren) {
    acties.push(`<button class="knop klein" data-actie="bewerk" data-id="${l.id}">Bewerken</button>`);
    acties.push(`<button class="knop klein" data-actie="actief" data-id="${l.id}">${l.actief ? 'Op non-actief zetten' : 'Weer activeren'}</button>`);
    acties.push(`<button class="knop klein gevaar" data-actie="verwijder" data-id="${l.id}">Verwijderen</button>`);
  }

  const vijf = l.leveringen.slice(0, 5);

  return `
  <div class="grp${open.has(l.id) ? ' open' : ''}" data-id="${l.id}">
    <div class="trow">
      <svg class="chev" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
      <div>
        <div class="nm">${esc(l.bedrijfsnaam)}</div>
        ${l.volgendeDatum
          ? `<div style="font-size:12.5px;color:var(--green);font-weight:600">Volgende levering ${datumKort(l.volgendeDatum)}${l.volgendeTijd ? ' om ' + esc(l.volgendeTijd) : ''}</div>`
          : (l.actief ? '<div style="font-size:12.5px;color:var(--ink-3)">Nog geen levering gepland</div>' : '')}
      </div>
      <div style="color:var(--ink-2)">${esc(l.contactpersoon)}</div>
      <div class="c-tel mono" style="font-size:13px;color:var(--ink-2)">${esc(l.telefoon)}</div>
      <div style="color:var(--ink-2);overflow:hidden;text-overflow:ellipsis">${esc(l.email)}</div>
      <div><span class="badge grijs">${esc(l.categorie)}</span></div>
      <div class="c-laatste" style="color:var(--ink-2)">${datumKort(l.laatste)}</div>
      <div><span class="pil2 ${l.actief ? 'ok' : 'off'}">${l.actief ? 'Actief' : 'Inactief'}</span></div>
    </div>
    <div class="detail">
      <h3>Leveringsgeschiedenis (laatste 5 leveringen)</h3>
      <div class="inner">
        <div class="ihead"><span>Datum</span><span>Producten</span><span>Aantal</span><span>Ingeboekt door</span></div>
        ${vijf.length ? vijf.map(v => `
          <div class="irow">
            <div class="p">${datumKort(v.datum)}</div>
            <div style="color:var(--ink-2);font-size:14px">${esc(v.inhoud || '—')}</div>
            <div class="mono">${getal(v.totaal)} st.</div>
            <div style="color:var(--ink-2)">${esc(v.door || '—')}</div>
          </div>`).join('')
          : '<div class="irow"><div class="niets">Nog geen leveringen geregistreerd.</div></div>'}
      </div>
      <div style="margin-top:12px;font-size:13.5px;color:var(--ink-2)">
        ${esc(l.adres)}${l.postcode ? ', ' + esc(l.postcode) : ''} ${esc(l.plaats || '')} ·
        totaal ${l.aantal} levering${l.aantal === 1 ? '' : 'en'} geregistreerd
      </div>
      <div class="detailacts">${acties.join('')}</div>
    </div>
  </div>`;
}

/* ------------------------------------------------ leverancier opslaan */
function openDrawer(l) {
  const v = document.getElementById('vorm');
  v.querySelectorAll('.veld.mis').forEach(x => x.classList.remove('mis'));
  v.querySelectorAll('.fout').forEach(x => x.remove());

  document.getElementById('drawerTitel').textContent = l ? 'Leverancier bewerken' : 'Nieuwe leverancier';
  document.getElementById('drawerOnder').textContent = l ? l.bedrijfsnaam : 'Voeg een voedseldonateur toe';

  v.id.value             = l ? l.id : '';
  v.bedrijfsnaam.value   = l ? l.bedrijfsnaam : '';
  v.contactpersoon.value = l ? l.contactpersoon : '';
  v.categorie.value      = l ? l.categorie : 'Supermarkt';
  v.telefoon.value       = l ? l.telefoon : '';
  v.email.value          = l ? l.email : '';
  v.adres.value          = l ? l.adres : '';
  v.postcode.value       = l && l.postcode ? l.postcode : '';
  v.plaats.value         = l && l.plaats ? l.plaats : 'Maaskantje';
  v.volgendeDatum.value  = l && l.volgendeDatum ? l.volgendeDatum : '';
  v.volgendeTijd.value   = l && l.volgendeTijd ? l.volgendeTijd : '';

  drawer.open(document.getElementById('drawer'));
}

async function bewaren() {
  const v = document.getElementById('vorm');
  const lichaam = {};
  ['id', 'bedrijfsnaam', 'contactpersoon', 'categorie', 'telefoon', 'email',
   'adres', 'postcode', 'plaats', 'volgendeDatum', 'volgendeTijd']
    .forEach(n => { lichaam[n] = v[n].value.trim(); });
  lichaam.id = Number(lichaam.id) || 0;

  try {
    const r = await api('leveranciers.php?actie=opslaan', { body: lichaam });
    drawer.dicht(document.getElementById('drawer'));
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e, v); }
}

async function zetActief(l, aan) {
  try {
    const r = await api('leveranciers.php?actie=actief', { body: { id: l.id, actief: aan } });
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e); }
}

async function verwijderen(l) {
  const ja = await bevestig({
    titel: 'Leverancier verwijderen?',
    tekst: `<p>Weet je zeker dat je <b>${esc(l.bedrijfsnaam)}</b> wilt verwijderen?</p>`,
    ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) { return; }

  try {
    const r = await api('leveranciers.php?actie=verwijderen', { body: { id: l.id } });
    melding.goed(r.melding);
    await laden();
  } catch (e) {
    const d = e.data || {};
    if (e.status === 409 && d.reden === 'heeft_historie') {
      const ja2 = await bevestig({
        titel: 'Deze leverancier kan niet weg',
        tekst: `<p>${esc(d.fout)}</p><p>${esc(d.suggestie)}</p>`,
        ja: 'Op non-actief zetten', nee: 'Laten staan',
      });
      if (ja2) { await zetActief(l, false); }
      return;
    }
    foutMelden(e);
  }
}

/* ------------------------------------------------ levering boeken */
function regelRij() {
  const bak = document.getElementById('regels');
  const el = document.createElement('div');
  el.className = 'regel';
  el.innerHTML = `
    <div class="veld" style="margin-bottom:0"><select data-v="product">
      ${PRODUCTEN.map(p => `<option value="${p.id}">${esc(p.naam)} (${p.voorraad} st.)</option>`).join('')}
    </select></div>
    <div class="veld" style="margin-bottom:0"><input type="number" min="1" value="1" data-v="aantal"></div>
    <button type="button" class="icoonknop" style="margin-top:4px" title="Regel verwijderen">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>`;
  el.querySelector('button').onclick = () => el.remove();
  bak.appendChild(el);
}

function openLevering(l) {
  const v = document.getElementById('levVorm');
  v.leverancier.value = l.id;
  v.datum.value = new Date().toISOString().slice(0, 10);
  v.tijd.value = '';
  document.getElementById('levOnder').textContent = l.bedrijfsnaam;
  document.getElementById('regels').innerHTML = '';
  regelRij();
  drawer.open(document.getElementById('drawerLev'));
}

async function boekLevering() {
  const v = document.getElementById('levVorm');
  const regels = [...document.querySelectorAll('#regels .regel')].map(el => ({
    product: Number(el.querySelector('[data-v=product]').value),
    aantal: Number(el.querySelector('[data-v=aantal]').value),
  })).filter(r => r.product > 0 && r.aantal > 0);

  try {
    const r = await api('leveranciers.php?actie=levering', {
      body: {
        leverancier: Number(v.leverancier.value),
        datum: v.datum.value,
        tijd: v.tijd.value,
        regels,
      },
    });
    drawer.dicht(document.getElementById('drawerLev'));
    melding.goed(r.melding);
    await laden();
  } catch (e) {
    foutMelden(e, v);
  }
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\guill\Herd\voedselbank-2\resources\views/pages/leveranciers.blade.php ENDPATH**/ ?>