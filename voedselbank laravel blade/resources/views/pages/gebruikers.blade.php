@extends('layouts.app')

@section('title', 'Gebruikers & toegangsbeheer')

@push('styles')
<style>
  #lijst { --kolommen: 22px 2fr 2.2fr 1.2fr 1.4fr .9fr; }
  .rechtenlijst { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 22px; }
  @media (max-width: 1000px) {
    #lijst { --kolommen: 22px 2fr 1.2fr .9fr; }
    #lijst .c-mail, .thead span.c-mail, #lijst .c-login, .thead span.c-login { display: none; }
    .rechtenlijst { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('body')
<div class="app" id="app"></div>

<div class="drawer" id="drawer">
  <div class="kop">
    <div style="flex:1">
      <h2 id="drawerTitel">Nieuwe gebruiker</h2>
      <div class="onder" id="drawerOnder">Maak een back-office account aan</div>
    </div>
    <button class="icoonknop" id="drawerDicht" aria-label="Sluiten">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="body">
    <form id="vorm" novalidate>
      <input type="hidden" name="id">
      <div class="veld">
        <label for="g-naam">Volledige naam</label>
        <input type="text" id="g-naam" name="naam" placeholder="Ank de Groot">
      </div>
      <div class="rij2">
        <div class="veld">
          <label for="g-gnaam">Gebruikersnaam</label>
          <input type="text" id="g-gnaam" name="gnaam" placeholder="a.degroot" autocomplete="off">
        </div>
        <div class="veld">
          <label for="g-rol">Rol</label>
          <select id="g-rol" name="rolId"></select>
        </div>
      </div>
      <div class="veld">
        <label for="g-mail">E-mailadres</label>
        <input type="email" id="g-mail" name="email" placeholder="naam@voedselbank.nl">
      </div>
      <div class="veld">
        <label for="g-pw">Wachtwoord</label>
        <input type="password" id="g-pw" name="wachtwoord" autocomplete="new-password">
        <div class="hint" id="pwHint">Minimaal 8 tekens met letters én cijfers.</div>
      </div>
      <div class="melding grijs" id="rolUitleg"><div></div></div>
    </form>
  </div>
  <div class="voet">
    <button class="knop" id="annuleer">Annuleren</button>
    <button class="knop primair" id="bewaar">Opslaan</button>
  </div>
</div>
@endsection

@push('scripts')
<script>
let D = { gebruikers: [], rollen: [], permissies: [], ik: 0 };
let term = '';
const open = new Set();

(async function () {
  const ik = await start({
    slug: 'gebruikers',
    titel: 'Gebruikers & toegangsbeheer',
    onder: 'Beheer de rollen, permissies en toegangsrechten van medewerkers en vrijwilligers',
  });
  if (!ik) { return; }

  document.getElementById('drawerDicht').onclick = () => drawer.dicht(document.getElementById('drawer'));
  document.getElementById('annuleer').onclick = () => drawer.dicht(document.getElementById('drawer'));
  document.getElementById('bewaar').onclick = bewaren;
  await laden();
})();

async function laden() {
  try { D = await api('gebruikers.php?actie=lijst'); }
  catch (e) { foutMelden(e); return; }
  teken();
}

function rolVan(id) { return D.rollen.find(r => r.id === id) || { permissies: [], naam: '?' }; }

function teken() {
  const zichtbaar = D.gebruikers.filter(g => !term ||
    (g.naam + ' ' + g.email + ' ' + g.gnaam + ' ' + g.rol).toLowerCase().includes(term));

  document.getElementById('inhoud').innerHTML = `
    <div class="zoekbalk">
      <div class="zoek">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="zoek" placeholder="Zoek gebruiker op naam of e-mailadres…" value="${esc(term)}">
      </div>
      <button class="knop primair" id="nieuw">+ Nieuwe gebruiker</button>
    </div>

    <div class="tbl" id="lijst">
      <div class="thead">
        <span></span><span>Naam</span><span class="c-mail">E-mail</span><span>Rol</span>
        <span class="c-login">Laatste login</span><span>Status</span>
      </div>
      ${zichtbaar.length ? zichtbaar.map(groep).join('')
        : '<div class="leeg"><h3>Geen gebruikers gevonden</h3><div>Pas je zoekopdracht aan.</div></div>'}
    </div>

    <div class="card" style="margin-top:18px">
      <div class="kop"><h2>Rollen en wat ze mogen</h2></div>
      <div class="body">
        <p style="margin:0 0 14px;color:var(--ink-2);font-size:14px">
          Rechten hangen aan de <b>rol</b>, niet aan de persoon. Wie een rol krijgt, krijgt automatisch
          de bijbehorende rechten — en verliest ze weer zodra de rol wijzigt.
        </p>
        <div class="inner" style="--subkolommen:1.1fr 3fr">
          <div class="ihead"><span>Rol</span><span>Rechten</span></div>
          ${D.rollen.map(r => `<div class="irow" style="align-items:flex-start;padding-top:12px;padding-bottom:12px">
            <div class="p">${esc(r.naam)}<div style="font-weight:400;font-size:12.5px;color:var(--ink-3)">${esc(r.omschrijving || '')}</div></div>
            <div class="pillen">${r.permissies.map(pid => {
              const p = D.permissies.find(x => x.id === pid);
              return p ? `<span class="pil2 diet">${esc(p.naam)}</span>` : '';
            }).join('')}</div>
          </div>`).join('')}
        </div>
      </div>
    </div>`;

  document.getElementById('zoek').oninput = (e) => {
    term = e.target.value.trim().toLowerCase();
    const pos = e.target.selectionStart;
    teken();
    const v = document.getElementById('zoek'); v.focus(); v.setSelectionRange(pos, pos);
  };
  document.getElementById('nieuw').onclick = () => openDrawer(null);

  document.querySelectorAll('.grp .trow').forEach(r => r.onclick = () => {
    const id = Number(r.closest('.grp').dataset.id);
    if (open.has(id)) { open.delete(id); } else { open.add(id); }
    r.closest('.grp').classList.toggle('open');
  });

  document.querySelectorAll('[data-actie]').forEach(b => b.onclick = (e) => {
    e.stopPropagation();
    const g = D.gebruikers.find(x => x.id === Number(b.dataset.id));
    if (b.dataset.actie === 'bewerk')  { openDrawer(g); }
    if (b.dataset.actie === 'blok')    { blokkeren(g, !g.actief); }
    if (b.dataset.actie === 'reset')   { resetten(g); }
  });
}

function groep(g) {
  const rol = rolVan(g.rolId);
  const heeft = new Set(rol.permissies);

  return `
  <div class="grp${open.has(g.id) ? ' open' : ''}" data-id="${g.id}">
    <div class="trow">
      <svg class="chev" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
      <div class="horizontaal">
        <div class="avatar" style="width:32px;height:32px;border-radius:50%;background:var(--green);color:#fff;display:grid;place-items:center;font-weight:700;font-size:13px">${esc(initialen(g.naam))}</div>
        <div><span class="nm">${esc(g.naam)}</span>
          <div class="mono" style="font-size:12px;color:var(--ink-3)">${esc(g.gnaam)}${g.id === D.ik ? ' · jij' : ''}</div></div>
      </div>
      <div class="c-mail" style="color:var(--ink-2);overflow:hidden;text-overflow:ellipsis">${esc(g.email)}</div>
      <div><span class="pil2 ${g.rol === 'Directie' ? 'diet' : 'off'}">${esc(g.rol.toUpperCase())}</span></div>
      <div class="c-login" style="color:var(--ink-2);font-size:13.5px">${tijdstip(g.laatste)}</div>
      <div><span class="pil2 ${g.actief ? 'ok' : 'nee'}">${g.actief ? 'Actief' : 'Geblokkeerd'}</span></div>
    </div>
    <div class="detail">
      <h3>Toegewezen permissies &amp; rechten</h3>
      <ul class="lijstje rechtenlijst">
        ${D.permissies.map(p => `
          <li class="${heeft.has(p.id) ? '' : 'uit'}">
            <span class="${heeft.has(p.id) ? 'vink' : 'kruis'}">${heeft.has(p.id) ? '✓' : '✕'}</span>
            ${esc(p.naam)}
          </li>`).join('')}
      </ul>
      ${g.mislukt > 0 ? `<div class="melding amber" style="margin-top:14px"><div>
        ${g.mislukt} mislukte inlogpoging${g.mislukt === 1 ? '' : 'en'} sinds de laatste geslaagde login.
      </div></div>` : ''}
      <div class="detailacts">
        <button class="knop klein" data-actie="bewerk" data-id="${g.id}">Account bewerken</button>
        <button class="knop klein" data-actie="reset" data-id="${g.id}">Wachtwoord resetten</button>
        <button class="knop klein ${g.actief ? 'gevaar' : ''}" data-actie="blok" data-id="${g.id}">
          ${g.actief ? 'Blokkeren' : 'Deblokkeren'}
        </button>
      </div>
    </div>
  </div>`;
}

/* ------------------------------------------------ drawer */
function openDrawer(g) {
  const v = document.getElementById('vorm');
  v.querySelectorAll('.veld.mis').forEach(x => x.classList.remove('mis'));
  v.querySelectorAll('.fout').forEach(x => x.remove());

  document.getElementById('g-rol').innerHTML = D.rollen.map(r =>
    `<option value="${r.id}">${esc(r.naam)}</option>`).join('');

  document.getElementById('drawerTitel').textContent = g ? 'Account bewerken' : 'Nieuwe gebruiker';
  document.getElementById('drawerOnder').textContent = g ? g.naam : 'Maak een back-office account aan';
  document.getElementById('pwHint').textContent = g
    ? 'Laat leeg om het huidige wachtwoord te laten staan. Anders: minimaal 8 tekens met letters én cijfers.'
    : 'Minimaal 8 tekens met letters én cijfers.';

  v.id.value    = g ? g.id : '';
  v.naam.value  = g ? g.naam : '';
  v.gnaam.value = g ? g.gnaam : '';
  v.email.value = g ? g.email : '';
  v.rolId.value = g ? g.rolId : (D.rollen[0] ? D.rollen[0].id : '');
  v.wachtwoord.value = '';

  v.rolId.onchange = toonRol;
  toonRol();

  drawer.open(document.getElementById('drawer'));
}

function toonRol() {
  const v = document.getElementById('vorm');
  const rol = rolVan(Number(v.rolId.value));
  const namen = rol.permissies.map(pid => (D.permissies.find(x => x.id === pid) || {}).naam).filter(Boolean);
  document.querySelector('#rolUitleg div').innerHTML =
    '<b>' + esc(rol.naam) + '</b> krijgt: ' + (namen.length ? esc(namen.join(', ')) : 'geen bijzondere rechten') + '.';
}

async function bewaren() {
  const v = document.getElementById('vorm');
  try {
    const r = await api('gebruikers.php?actie=opslaan', {
      body: {
        id: Number(v.id.value) || 0,
        naam: v.naam.value.trim(),
        gnaam: v.gnaam.value.trim(),
        email: v.email.value.trim(),
        rolId: Number(v.rolId.value),
        wachtwoord: v.wachtwoord.value,
      },
    });
    drawer.dicht(document.getElementById('drawer'));
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e, v); }
}

/* ------------------------------------------------ blokkeren */
async function blokkeren(g, aan) {
  const ja = await bevestig({
    titel: aan ? 'Account deblokkeren?' : 'Account blokkeren?',
    tekst: aan
      ? `<p><b>${esc(g.naam)}</b> kan daarna weer inloggen.</p>`
      : `<p><b>${esc(g.naam)}</b> kan daarna niet meer inloggen. Het account wordt niet verwijderd:
         wat deze persoon heeft gedaan blijft zichtbaar in de historie.</p>`,
    ja: aan ? 'Ja, deblokkeren' : 'Ja, blokkeren', gevaar: !aan,
  });
  if (!ja) { return; }

  try {
    const r = await api('gebruikers.php?actie=blokkeren', { body: { id: g.id, actief: aan } });
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e); }
}

/* ------------------------------------------------ wachtwoord resetten */
async function resetten(g) {
  const uit = await formulierModal({
    titel: 'Wachtwoord resetten',
    tekst: `<p>Stel een nieuw wachtwoord in voor <b>${esc(g.naam)}</b> en geef dat persoonlijk door.</p>`,
    velden: [{ naam: 'wachtwoord', label: 'Nieuw wachtwoord', type: 'password',
               hint: 'Minimaal 8 tekens met letters én cijfers.' }],
    ja: 'Wachtwoord instellen',
  });
  if (!uit) { return; }

  try {
    const r = await api('gebruikers.php?actie=reset', { body: { id: g.id, wachtwoord: uit.wachtwoord } });
    melding.goed(r.melding);
    await laden();
  } catch (e) { foutMelden(e); }
}
</script>
@endpush
