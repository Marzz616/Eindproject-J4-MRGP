const API = '/api';

let huidigeCategorie = 'Alles'; // 'Alles' of een idCategorie
let huidigeZoek = '';

const body = document.getElementById('producten-body');
const filtersNav = document.getElementById('filters');
const nieuwKnop = document.getElementById('btn-nieuw');
const zoekveld = document.getElementById('zoekveld');

const overlay = document.getElementById('modal-overlay');
const form = document.getElementById('product-form');
const modalTitel = document.getElementById('modal-titel');
const formFout = document.getElementById('form-fout');

const veldId = document.getElementById('product-id');
const veldNaam = document.getElementById('veld-naam');
const veldCategorie = document.getElementById('veld-categorie');
const veldEan = document.getElementById('veld-ean');
const veldVoorraad = document.getElementById('veld-voorraad');
const veldTht = document.getElementById('veld-tht');
const veldLocatie = document.getElementById('veld-locatie');

// ------------------------------------------------------------
// Datums: "2026-08-14" handmatig verwerken (geen tijdzone-gedoe)
// ------------------------------------------------------------
function parseDatum(str) {
  const [j, m, d] = str.slice(0, 10).split('-').map(Number);
  return new Date(j, m - 1, d);
}

function formatDatum(str) {
  if (!str) return '–';
  const [j, m, d] = str.slice(0, 10).split('-');
  return `${d}-${m}-${j}`;
}

function bepaalStatus(thtStr) {
  if (!thtStr) return { klasse: '', label: '' };
  const vandaag = new Date();
  vandaag.setHours(0, 0, 0, 0);
  const dagen = Math.round((parseDatum(thtStr) - vandaag) / 86400000);

  if (dagen < 0) return { klasse: 'status-verlopen', label: 'VERLOPEN' };
  if (dagen <= 30) return { klasse: 'status-bijna', label: 'BIJNA VERLOPEN' };
  return { klasse: '', label: '' };
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

// ------------------------------------------------------------
// Categorieën uit de database (tabel Categorie)
// ------------------------------------------------------------
async function laadCategorieen() {
  try {
    const res = await fetch(`${API}/categorieen`);
    if (!res.ok) throw new Error('Serverfout');
    const categorieen = await res.json();

    // Pills, vóór de "+ Nieuw product" knop
    categorieen.forEach((c) => {
      const pill = document.createElement('button');
      pill.className = 'filter-pill';
      pill.dataset.categorie = c.id;
      pill.textContent = c.naam;
      if (c.omschrijving) pill.title = c.omschrijving;
      filtersNav.insertBefore(pill, nieuwKnop);
    });

    // Keuzelijst in het formulier
    veldCategorie.innerHTML = categorieen
      .map((c) => `<option value="${c.id}">${escapeHtml(c.naam)}</option>`)
      .join('');
  } catch (err) {
    console.error('Kon categorieën niet laden', err);
  }
}

// ------------------------------------------------------------
// Capaciteit
// ------------------------------------------------------------
async function laadCapaciteit() {
  try {
    const res = await fetch(`${API}/capaciteit`);
    const data = await res.json();
    document.getElementById('capaciteit-vulling').style.width = `${Math.min(data.percentage, 100)}%`;
    document.getElementById('capaciteit-tekst').textContent =
      `${data.percentage}% Vol (${data.totaal.toLocaleString('nl-NL')} / ${data.maxCapaciteit.toLocaleString('nl-NL')} stuks)`;
  } catch (err) {
    console.error('Kon capaciteit niet laden', err);
  }
}

// ------------------------------------------------------------
// Producten (Read)
// ------------------------------------------------------------
async function laadProducten() {
  try {
    const params = new URLSearchParams();
    if (huidigeCategorie !== 'Alles') params.set('categorie', huidigeCategorie);
    if (huidigeZoek) params.set('zoek', huidigeZoek);

    const res = await fetch(`${API}/producten?${params.toString()}`);
    if (!res.ok) throw new Error('Serverfout');
    const producten = await res.json();

    body.innerHTML = producten.length
      ? producten.map(rijHtml).join('')
      : `<tr><td colspan="6" class="lege-rij">Geen producten gevonden.</td></tr>`;
  } catch (err) {
    body.innerHTML = `<tr><td colspan="6" class="lege-rij">Kon producten niet laden. Draait de server en is de database bereikbaar?</td></tr>`;
    console.error(err);
  }
}

function rijHtml(p) {
  const status = bepaalStatus(p.tht);
  return `
    <tr class="${status.klasse}">
      <td>
        <span class="product-naam">${escapeHtml(p.naam)}</span>
        ${status.label ? `<span class="status-label">${status.label}</span>` : ''}
      </td>
      <td>${escapeHtml(p.categorie)}</td>
      <td class="voorraad-cel">${p.voorraad} st.</td>
      <td class="tht-cel">${formatDatum(p.tht)}</td>
      <td>${escapeHtml(p.locatie) || '–'}</td>
      <td class="acties-cel">
        <button class="icon-btn edit" title="Bewerken" data-id="${p.id}">✎</button>
        <button class="icon-btn delete" title="Verwijderen" data-id="${p.id}">✕</button>
      </td>
    </tr>
  `;
}

async function herlaad() {
  await Promise.all([laadProducten(), laadCapaciteit()]);
}

// ------------------------------------------------------------
// Filters & zoeken
// ------------------------------------------------------------
filtersNav.addEventListener('click', (e) => {
  const pill = e.target.closest('.filter-pill[data-categorie]');
  if (!pill) return;
  filtersNav.querySelectorAll('.filter-pill[data-categorie]').forEach((el) => el.classList.remove('active'));
  pill.classList.add('active');
  huidigeCategorie = pill.dataset.categorie;
  laadProducten();
});

let zoekTimer;
zoekveld.addEventListener('input', () => {
  clearTimeout(zoekTimer);
  zoekTimer = setTimeout(() => {
    huidigeZoek = zoekveld.value.trim();
    laadProducten();
  }, 250);
});

// ------------------------------------------------------------
// Modal
// ------------------------------------------------------------
function openModalNieuw() {
  modalTitel.textContent = 'Nieuw product';
  form.reset();
  veldId.value = '';
  formFout.textContent = '';
  // Standaard de categorie kiezen waarop nu gefilterd is
  if (huidigeCategorie !== 'Alles') veldCategorie.value = huidigeCategorie;
  overlay.classList.add('open');
  veldNaam.focus();
}

function openModalBewerken(p) {
  modalTitel.textContent = 'Product bewerken';
  veldId.value = p.id;
  veldNaam.value = p.naam;
  veldCategorie.value = p.categorieId;
  veldEan.value = p.ean ?? '';
  veldVoorraad.value = p.voorraad;
  veldTht.value = p.tht ? p.tht.slice(0, 10) : '';
  veldLocatie.value = p.locatie ?? '';
  formFout.textContent = '';
  overlay.classList.add('open');
  veldNaam.focus();
}

function sluitModal() {
  overlay.classList.remove('open');
}

nieuwKnop.addEventListener('click', openModalNieuw);
document.getElementById('btn-annuleren').addEventListener('click', sluitModal);
overlay.addEventListener('click', (e) => { if (e.target === overlay) sluitModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') sluitModal(); });

// ------------------------------------------------------------
// Aanmaken / bijwerken (Create / Update)
// ------------------------------------------------------------
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  formFout.textContent = '';

  const payload = {
    naam: veldNaam.value.trim(),
    categorieId: Number(veldCategorie.value),
    ean: veldEan.value.trim(),
    voorraad: veldVoorraad.value === '' ? '' : Number(veldVoorraad.value),
    tht: veldTht.value,
    locatie: veldLocatie.value.trim(),
  };

  const id = veldId.value;

  try {
    const res = await fetch(id ? `${API}/producten/${id}` : `${API}/producten`, {
      method: id ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Opslaan mislukt');

    sluitModal();
    await herlaad();
  } catch (err) {
    formFout.textContent = err.message;
  }
});

// ------------------------------------------------------------
// Bewerken / verwijderen vanuit de tabel
// ------------------------------------------------------------
body.addEventListener('click', async (e) => {
  const bewerkBtn = e.target.closest('.icon-btn.edit');
  const verwijderBtn = e.target.closest('.icon-btn.delete');

  if (bewerkBtn) {
    const res = await fetch(`${API}/producten/${bewerkBtn.dataset.id}`);
    if (res.ok) openModalBewerken(await res.json());
    return;
  }

  if (verwijderBtn) {
    const naam = verwijderBtn.closest('tr').querySelector('.product-naam').textContent;
    if (!confirm(`"${naam}" verwijderen uit de voorraad?`)) return;

    const res = await fetch(`${API}/producten/${verwijderBtn.dataset.id}`, { method: 'DELETE' });
    if (res.ok) {
      await herlaad();
    } else {
      const data = await res.json().catch(() => ({}));
      alert(data.error || 'Verwijderen is mislukt.');
    }
  }
});

// ------------------------------------------------------------
// Init: eerst categorieën (pills + keuzelijst), dan de rest
// ------------------------------------------------------------
laadCategorieen().then(herlaad);
