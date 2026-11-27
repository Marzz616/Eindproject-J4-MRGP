<script setup>
/**
 * Producten en voorraad (US-VRD-01 t/m 12).
 * Sorteerbaar op elke kolom, filterbaar op categorie, met afvoeren en voorraadcorrectie.
 */
import { computed, onMounted, ref } from 'vue';
import { categorieenApi, productenApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import { datumKort, getal } from '@/utils/format';
import SearchInput from '@/components/ui/SearchInput.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import AppIcon from '@/components/ui/AppIcon.vue';
import ProductDrawer from '@/components/producten/ProductDrawer.vue';
import CategorieBeheer from '@/components/producten/CategorieBeheer.vue';

const dialog = useDialog();
const toast = useToast();

const data = ref({ producten: [], magazijn: {}, rechten: {} });
const categorieen = ref([]);
const zoek = ref('');
const filterCat = ref(0);
const sorteerOp = ref('naam');
const aflopend = ref(false);
const drawer = ref({ open: false, product: null });
const beheer = ref(false);

async function laden() {
  try {
    data.value = await productenApi.lijst();
    categorieen.value = await categorieenApi.lijst();
  } catch (e) { meldFout(e); }
}
onMounted(laden);

const KOLOMMEN = [
  { sleutel: 'naam', titel: 'Productnaam', waarde: (p) => p.naam.toLowerCase() },
  { sleutel: 'ean', titel: 'Streepjescode', waarde: (p) => p.ean },
  { sleutel: 'categorie', titel: 'Categorie', waarde: (p) => p.categorie.naam.toLowerCase() },
  { sleutel: 'voorraad', titel: 'Voorraad', waarde: (p) => p.voorraad, rechts: true },
  { sleutel: 'min', titel: 'Min. voorraad', waarde: (p) => p.min, rechts: true },
  { sleutel: 'tht', titel: 'Houdbaar tot (THT)', waarde: (p) => p.tht || '9999-12-31' },
  { sleutel: 'locatie', titel: 'Locatie', waarde: (p) => (p.locatie || 'zzz').toLowerCase() },
];

const zichtbaar = computed(() => {
  const t = zoek.value.trim().toLowerCase();
  const waarde = KOLOMMEN.find((k) => k.sleutel === sorteerOp.value).waarde;
  return data.value.producten
    .filter((p) => (!filterCat.value || p.categorie.id === filterCat.value)
      && (!t || `${p.naam} ${p.ean} ${p.locatie} ${p.categorie.naam}`.toLowerCase().includes(t)))
    .sort((a, b) => {
      const x = waarde(a);
      const y = waarde(b);
      const v = x < y ? -1 : x > y ? 1 : 0;
      return aflopend.value ? -v : v;
    });
});

function sorteer(sleutel) {
  if (sorteerOp.value === sleutel) aflopend.value = !aflopend.value;
  else { sorteerOp.value = sleutel; aflopend.value = false; }
}

async function voorraadCorrigeren(p) {
  const uit = await dialog.formulier({
    titel: 'Voorraad corrigeren',
    tekst: `${p.naam} — nu ${getal(p.voorraad)} stuks in ${p.locatie || 'het magazijn'}.`,
    velden: [{ naam: 'aantal', label: 'Nieuwe voorraad (stuks)', type: 'number', min: 0, waarde: p.voorraad }],
    ja: 'Voorraad bijwerken',
  });
  if (!uit) return;
  const n = Number(uit.aantal);
  if (!Number.isFinite(n) || n < 0) { toast.fout('Vul een getal van 0 of hoger in.'); return; }
  try {
    toast.goed((await productenApi.zetVoorraad(p.id, n)).melding);
    await laden();
  } catch (e) { meldFout(e); }
}

async function afvoeren(p) {
  const uit = await dialog.formulier({
    titel: 'Product afvoeren',
    tekst: 'Wat gaat er uit het magazijn zonder dat het in een pakket komt? Dit telt mee in het verspillingsrapport.',
    velden: [
      { naam: 'aantal', label: 'Aantal stuks', type: 'number', min: 1, waarde: 1, hint: `Er ligt ${p.voorraad} stuks van ${p.naam}.` },
      { naam: 'reden', label: 'Reden', type: 'select', waarde: 'Verspild', opties: [
        { waarde: 'Verspild', label: 'Verspild / afgevoerd' },
        { waarde: 'Retour leverancier', label: 'Retour naar de leverancier' },
        { waarde: 'Veeteelt', label: 'Naar de veeteelt' },
      ] },
      { naam: 'toelichting', label: 'Toelichting (optioneel)', type: 'text' },
    ],
    ja: 'Afvoeren',
  });
  if (!uit) return;
  try {
    toast.goed((await productenApi.afvoeren(p.id, { ...uit, aantal: Number(uit.aantal) })).melding);
    await laden();
  } catch (e) { meldFout(e); }
}

const HISTORIE = { in_pakket: 'uitgedeelde voedselpakketten', in_levering: 'de leveringshistorie', in_afvoer: 'de afvoerhistorie' };

async function verwijderen(p) {
  const ja = await dialog.bevestig({
    titel: 'Product verwijderen?',
    tekst: `Weet je zeker dat je ${p.naam} uit het magazijn wilt halen? Dit kan niet ongedaan worden gemaakt.`,
    ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) return;
  try {
    toast.goed((await productenApi.verwijderen(p.id)).melding);
    await laden();
  } catch (e) {
    if (e.status === 409 && e.reden) {
      await dialog.melding({
        titel: 'Dit product kan niet weg',
        tekst: `${p.naam} komt voor in ${HISTORIE[e.reden]} (${e.data.aantal} registratie${e.data.aantal === 1 ? '' : 's'}). `
          + 'Die geschiedenis moet bewaard blijven voor de rapportages, dus het product blijft in de database staan.',
        notitie: 'Tip: ' + (e.data.suggestie || 'Zet de voorraad op 0.'),
      });
      return;
    }
    meldFout(e);
  }
}

async function opgeslagen(melding) {
  drawer.value.open = false;
  toast.goed(melding);
  await laden();
}
</script>

<template>
  <div class="card" style="margin-bottom:18px">
    <div class="body cijfers">
      <div><div class="lab">In het magazijn</div><div class="mono getal">{{ getal(data.magazijn.totaal) }} <span class="eenheid">stuks</span></div></div>
      <div><div class="lab">Verschillende producten</div><div class="mono getal">{{ getal(data.magazijn.soorten) }}</div></div>
      <div v-if="data.magazijn.onderMin"><div class="lab">Onder de minimumvoorraad</div><div class="mono getal" style="color:var(--amber)">{{ data.magazijn.onderMin }}</div></div>
      <div v-if="data.magazijn.verlopen"><div class="lab">Over de datum</div><div class="mono getal" style="color:var(--red)">{{ data.magazijn.verlopen }}</div></div>
    </div>
  </div>

  <div class="zoekbalk">
    <SearchInput v-model="zoek" placeholder="Zoek product, streepjescode of locatie…" />
    <template v-if="data.rechten.beheren">
      <button class="knop primair" @click="drawer = { open: true, product: null }">+ Nieuw product</button>
      <button class="knop" @click="beheer = true">Categorieën</button>
    </template>
  </div>

  <div class="chips" style="margin-bottom:18px">
    <button class="chip" :class="{ aan: filterCat === 0 }" @click="filterCat = 0">Alles</button>
    <button v-for="c in categorieen" :key="c.id" class="chip" :class="{ aan: filterCat === c.id }" :title="c.naam"
            @click="filterCat = c.id">{{ c.kort }} <span style="opacity:.7">{{ c.aantal }}</span></button>
  </div>

  <div class="card">
    <div class="kop">
      <h2>Productenoverzicht</h2>
      <span class="tel rechts">{{ zichtbaar.length }} van {{ data.producten.length }} producten</span>
    </div>
    <table v-if="zichtbaar.length" class="lijst">
      <thead><tr>
        <th v-for="k in KOLOMMEN" :key="k.sleutel" :class="{ r: k.rechts, aan: sorteerOp === k.sleutel }"
            :title="'Sorteren op ' + k.titel.toLowerCase()" @click="sorteer(k.sleutel)">
          {{ k.titel }}{{ sorteerOp === k.sleutel ? (aflopend ? ' ↓' : ' ↑') : '' }}
        </th>
        <th style="width:1%" />
      </tr></thead>
      <tbody>
        <tr v-for="p in zichtbaar" :key="p.id">
          <td>
            <div class="stapel">
              <div class="sterk">{{ p.naam }}</div>
              <div class="horizontaal" style="gap:6px">
                <span v-if="p.verlopen" class="pil rood">Verlopen</span>
                <span v-else-if="p.bijnaVerlopen" class="pil amber">Bijna verlopen</span>
                <span v-if="p.allergenen.length" class="zacht">{{ p.allergenen.join(', ') }}</span>
              </div>
            </div>
          </td>
          <td class="mono zacht" style="font-size:12.5px">{{ p.ean }}</td>
          <td><span class="badge grijs" :title="p.categorie.naam">{{ p.categorie.kort }}</span></td>
          <td class="r mono" :style="p.onderMin ? 'color:var(--red);font-weight:600' : ''">{{ getal(p.voorraad) }} st.</td>
          <td class="r mono zacht">{{ getal(p.min) }} st.</td>
          <td>
            <template v-if="p.tht">{{ datumKort(p.tht) }}
              <span v-if="p.thtDagen !== null && p.thtDagen >= 0 && p.thtDagen <= 7" class="zacht">({{ p.thtDagen }}d)</span>
            </template>
            <span v-else class="zacht">geen THT</span>
          </td>
          <td class="mono zacht" style="white-space:nowrap">{{ p.locatie || '—' }}</td>
          <td>
            <div class="horizontaal" style="gap:6px;justify-content:flex-end">
              <template v-if="data.rechten.corrigeren">
                <button class="icoonknop" title="Voorraad corrigeren" aria-label="Voorraad corrigeren" @click="voorraadCorrigeren(p)">
                  <AppIcon naam="voorraad" :dikte="1.8" /></button>
                <button class="icoonknop" title="Afvoeren" aria-label="Afvoeren" @click="afvoeren(p)">
                  <AppIcon naam="prullenbak" :dikte="1.8" /></button>
              </template>
              <template v-if="data.rechten.beheren">
                <button class="icoonknop" title="Bewerken" aria-label="Bewerken" @click="drawer = { open: true, product: p }">
                  <AppIcon naam="bewerken" :dikte="1.8" /></button>
                <button class="icoonknop" style="color:var(--red)" title="Verwijderen" aria-label="Verwijderen" @click="verwijderen(p)">
                  <AppIcon naam="sluiten" :dikte="1.8" /></button>
              </template>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <EmptyState v-else titel="Geen producten gevonden" tekst="Pas je zoekopdracht of het filter aan." />
  </div>

  <ProductDrawer :open="drawer.open" :product="drawer.product" :categorieen="categorieen"
                 @sluit="drawer.open = false" @opgeslagen="opgeslagen" />
  <CategorieBeheer :open="beheer" :categorieen="categorieen" @sluit="beheer = false" @gewijzigd="laden" />
</template>

<style scoped>
.cijfers { display: flex; gap: 34px; flex-wrap: wrap; align-items: baseline; }
.lab { font-size: 12.5px; color: var(--ink-2); font-weight: 600; }
.getal { font-size: 27px; font-weight: 500; line-height: 1.2; }
.eenheid { font-size: 14px; color: var(--ink-3); font-family: 'Source Sans 3', sans-serif; }
th { cursor: pointer; user-select: none; }
th.aan { color: var(--green); }
</style>
