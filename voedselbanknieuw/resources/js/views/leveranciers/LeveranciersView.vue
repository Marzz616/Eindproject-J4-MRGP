<script setup>
/** Leveranciersbeheer en leveringen inboeken (US-LEV-01 t/m 06). */
import { computed, onMounted, ref } from 'vue';
import { leveranciersApi, productenApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import { datumKort, getal } from '@/utils/format';
import SearchInput from '@/components/ui/SearchInput.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ExpandableRow from '@/components/ui/ExpandableRow.vue';
import LeverancierDrawer from '@/components/leveranciers/LeverancierDrawer.vue';
import LeveringDrawer from '@/components/leveranciers/LeveringDrawer.vue';

const dialog = useDialog();
const toast = useToast();

const leveranciers = ref([]);
const producten = ref([]);
const rechten = ref({});
const zoek = ref('');
const categorie = ref('');
const sorteer = ref('volgende');
const open = ref({});
const bewerken = ref({ open: false, leverancier: null });
const levering = ref({ open: false, leverancier: null });

async function laden() {
  try {
    const r = await leveranciersApi.lijst();
    leveranciers.value = r.leveranciers;
    rechten.value = r.rechten;
    producten.value = (await productenApi.lijst()).producten;
  } catch (e) { meldFout(e); }
}
onMounted(laden);

const categorieen = computed(() => [...new Set(leveranciers.value.map((l) => l.categorie))].sort());

// Bij "eerstvolgende levering" komen leveranciers zonder geplande datum onderaan.
const SORTEERSLEUTEL = {
  volgende: (l) => (l.actief ? '0' : '1') + (l.volgendeDatum || '9999-12-31') + (l.volgendeTijd || '99:99'),
  naam: (l) => (l.actief ? '0' : '1') + l.bedrijfsnaam.toLowerCase(),
  laatste: (l) => l.laatste || '0000-00-00',
  categorie: (l) => l.categorie.toLowerCase() + l.bedrijfsnaam.toLowerCase(),
};

const zichtbaar = computed(() => {
  const t = zoek.value.trim().toLowerCase();
  const sleutel = SORTEERSLEUTEL[sorteer.value];
  return leveranciers.value
    .filter((l) => (!categorie.value || l.categorie === categorie.value)
      && (!t || [l.bedrijfsnaam, l.contactpersoon, l.email, l.categorie, l.plaats].join(' ').toLowerCase().includes(t)))
    .sort((a, b) => {
      const v = sleutel(a).localeCompare(sleutel(b));
      return sorteer.value === 'laatste' ? -v : v;   // laatste levering: nieuwste eerst
    });
});

async function zetActief(l, aan) {
  try {
    toast.goed((await leveranciersApi.zetActief(l.id, aan)).melding);
    await laden();
  } catch (e) { meldFout(e); }
}

async function verwijderen(l) {
  const ja = await dialog.bevestig({
    titel: 'Leverancier verwijderen?', tekst: `Weet je zeker dat je ${l.bedrijfsnaam} wilt verwijderen?`,
    ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) return;
  try {
    toast.goed((await leveranciersApi.verwijderen(l.id)).melding);
    await laden();
  } catch (e) {
    if (e.status === 409 && e.reden === 'heeft_historie') {
      const nonActief = await dialog.bevestig({
        titel: 'Deze leverancier kan niet weg', tekst: e.message, notitie: e.data.suggestie,
        ja: 'Op non-actief zetten', nee: 'Laten staan',
      });
      if (nonActief) await zetActief(l, false);
      return;
    }
    meldFout(e);
  }
}

async function opgeslagen(melding) {
  bewerken.value.open = false;
  levering.value.open = false;
  toast.goed(melding);
  await laden();
}
</script>

<template>
  <div class="zoekbalk">
    <SearchInput v-model="zoek" placeholder="Zoek leverancier op naam, contactpersoon of categorie…" />
    <select v-model="categorie" class="filter" aria-label="Filter op categorie">
      <option value="">Categorie: alle</option>
      <option v-for="c in categorieen" :key="c">{{ c }}</option>
    </select>
    <select v-model="sorteer" class="filter" aria-label="Sorteren">
      <option value="volgende">Sorteer: eerstvolgende levering</option>
      <option value="naam">Sorteer: bedrijfsnaam</option>
      <option value="laatste">Sorteer: laatste levering</option>
      <option value="categorie">Sorteer: categorie</option>
    </select>
    <button v-if="rechten.beheren" class="knop primair" @click="bewerken = { open: true, leverancier: null }">+ Nieuwe leverancier</button>
  </div>

  <div id="lijst" class="tbl">
    <div class="thead">
      <span /><span>Leverancier</span><span>Contactpersoon</span><span class="c-tel">Telefoon</span>
      <span>E-mail</span><span>Categorie</span><span class="c-laatste">Laatste levering</span><span>Status</span>
    </div>

    <ExpandableRow v-for="l in zichtbaar" :key="l.id" v-model:open="open[l.id]">
      <template #rij>
        <div>
          <div class="nm">{{ l.bedrijfsnaam }}</div>
          <div v-if="l.volgendeDatum" class="gepland">
            Volgende levering {{ datumKort(l.volgendeDatum) }}{{ l.volgendeTijd ? ' om ' + l.volgendeTijd : '' }}
          </div>
          <div v-else-if="l.actief" class="niet-gepland">Nog geen levering gepland</div>
        </div>
        <div style="color:var(--ink-2)">{{ l.contactpersoon }}</div>
        <div class="c-tel mono" style="font-size:13px;color:var(--ink-2)">{{ l.telefoon }}</div>
        <div class="afkap" style="color:var(--ink-2)">{{ l.email }}</div>
        <div><span class="badge grijs">{{ l.categorie }}</span></div>
        <div class="c-laatste" style="color:var(--ink-2)">{{ datumKort(l.laatste) }}</div>
        <div><span class="pil2" :class="l.actief ? 'ok' : 'off'">{{ l.actief ? 'Actief' : 'Inactief' }}</span></div>
      </template>

      <h3>Leveringsgeschiedenis (laatste 5 leveringen)</h3>
      <div class="inner" style="--subkolommen: 1fr 2.4fr .8fr 1.2fr">
        <div class="ihead"><span>Datum</span><span>Producten</span><span>Aantal</span><span>Ingeboekt door</span></div>
        <div v-for="v in l.leveringen.slice(0, 5)" :key="v.id" class="irow">
          <div class="p">{{ datumKort(v.datum) }}</div>
          <div style="color:var(--ink-2);font-size:14px">{{ v.inhoud || '—' }}</div>
          <div class="mono">{{ getal(v.totaal) }} st.</div>
          <div style="color:var(--ink-2)">{{ v.door || '—' }}</div>
        </div>
        <div v-if="!l.leveringen.length" class="irow"><div class="niets">Nog geen leveringen geregistreerd.</div></div>
      </div>
      <div class="adres">
        {{ l.adres }}{{ l.postcode ? ', ' + l.postcode : '' }} {{ l.plaats }} ·
        totaal {{ l.aantal }} levering{{ l.aantal === 1 ? '' : 'en' }} geregistreerd
      </div>
      <div class="detailacts">
        <button v-if="rechten.boeken && l.actief" class="knop klein primair" @click.stop="levering = { open: true, leverancier: l }">
          Levering inboeken</button>
        <template v-if="rechten.beheren">
          <button class="knop klein" @click.stop="bewerken = { open: true, leverancier: l }">Bewerken</button>
          <button class="knop klein" @click.stop="zetActief(l, !l.actief)">{{ l.actief ? 'Op non-actief zetten' : 'Weer activeren' }}</button>
          <button class="knop klein gevaar" @click.stop="verwijderen(l)">Verwijderen</button>
        </template>
      </div>
    </ExpandableRow>

    <EmptyState v-if="!zichtbaar.length" titel="Geen leveranciers gevonden" tekst="Pas je zoekopdracht of het filter aan." />
  </div>

  <LeverancierDrawer :open="bewerken.open" :leverancier="bewerken.leverancier"
                     @sluit="bewerken.open = false" @opgeslagen="opgeslagen" />
  <LeveringDrawer :open="levering.open" :leverancier="levering.leverancier" :producten="producten"
                  @sluit="levering.open = false" @opgeslagen="opgeslagen" />
</template>

<style scoped>
#lijst { --kolommen: 22px 2fr 1.4fr 1.2fr 1.9fr 1.1fr 1.1fr .8fr; }
.filter { height: 42px; border: 1px solid var(--line-2); border-radius: 9px; padding: 0 12px; font: inherit; background: #fff; }
.gepland { font-size: 12.5px; color: var(--green); font-weight: 600; }
.niet-gepland { font-size: 12.5px; color: var(--ink-3); }
.afkap { overflow: hidden; text-overflow: ellipsis; }
.adres { margin-top: 12px; font-size: 13.5px; color: var(--ink-2); }
@media (max-width: 1280px) {
  #lijst { --kolommen: 22px 2fr 1.4fr 1.9fr 1.1fr .8fr; }
  .c-tel, .c-laatste { display: none; }
}
</style>
