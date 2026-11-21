<script setup>
/**
 * Klanten- en gezinnenadministratie (US-KLT-01 t/m 14, US-SEC-02).
 * Zonder klant_beheren stuurt de server geen contactgegevens mee; deze pagina
 * laat dan alleen de gezinssamenstelling en de dieetwensen zien.
 */
import { computed, onMounted, ref } from 'vue';
import { klantenApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import SearchInput from '@/components/ui/SearchInput.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import KlantRij from '@/components/klanten/KlantRij.vue';
import KlantDrawer from '@/components/klanten/KlantDrawer.vue';
import PakketGeschiedenis from '@/components/klanten/PakketGeschiedenis.vue';

const dialog = useDialog();
const toast = useToast();

const klanten = ref([]);
const wensen = ref([]);
const rechten = ref({});
const zoek = ref('');
const status = ref('');
const open = ref({});
const drawer = ref({ open: false, klant: null });
const geschiedenis = ref(null);

async function laden() {
  try {
    const r = await klantenApi.lijst();
    klanten.value = r.klanten;
    rechten.value = r.rechten;
    wensen.value = await klantenApi.wensen();
  } catch (e) { meldFout(e); }
}
onMounted(laden);

const FILTER = { actief: true, wacht: null, afgewezen: false };

const zichtbaar = computed(() => {
  const t = zoek.value.trim().toLowerCase();
  return klanten.value.filter((k) => {
    if (status.value && k.status !== FILTER[status.value]) return false;
    if (!t) return true;
    return [k.naam, k.nummer, k.adres, k.plaats, k.postcode, ...k.leden.map((l) => l.naam)].join(' ').toLowerCase().includes(t);
  });
});

async function zetStatus(k, akkoord, alBevestigd = false) {
  if (!alBevestigd) {
    const ja = await dialog.bevestig({
      titel: akkoord ? 'Aanvraag accepteren?' : 'Klant op non-actief zetten?',
      tekst: akkoord
        ? `${k.naam} kan daarna voedselpakketten ontvangen.`
        : `${k.naam} kan daarna geen nieuwe pakketten meer krijgen. De gegevens en de pakketgeschiedenis blijven bewaard.`,
      ja: akkoord ? 'Ja, accepteren' : 'Ja, op non-actief',
      gevaar: !akkoord,
    });
    if (!ja) return;
  }
  try {
    toast.goed((await klantenApi.zetStatus(k.id, akkoord)).melding);
    await laden();
  } catch (e) { meldFout(e); }
}

async function verwijderen(k) {
  const ja = await dialog.bevestig({
    titel: 'Klant verwijderen?',
    tekst: `${k.naam} wordt uit de administratie gehaald. De ${k.grootte} ${k.grootte === 1 ? 'persoon' : 'gezinsleden'} en hun dieetwensen verdwijnen mee. Dit kan niet ongedaan worden gemaakt.`,
    ja: 'Ja, verwijderen',
    gevaar: true,
  });
  if (!ja) return;

  try {
    toast.goed((await klantenApi.verwijderen(k.id)).melding);
    delete open.value[k.id];
    await laden();
  } catch (e) {
    // historie beschermen: bied non-actief aan in plaats van verwijderen
    if (e.status === 409 && e.reden === 'heeft_historie') {
      const nonActief = await dialog.bevestig({
        titel: 'Dit gezin kan niet weg', tekst: e.message, notitie: e.data.suggestie,
        ja: 'Op non-actief zetten', nee: 'Sluiten',
      });
      if (nonActief) await zetStatus(k, false, true);
      return;
    }
    meldFout(e);
  }
}

function wensToegevoegd(w) {
  if (!wensen.value.some((x) => x.id === w.id)) wensen.value.push(w);
}

async function opgeslagen(melding) {
  drawer.value.open = false;
  toast.goed(melding);
  await laden();
}
</script>

<template>
  <div v-if="!rechten.contact" class="melding grijs"><div>
    Je ziet de gezinssamenstelling en de dieetwensen, maar geen adres-, telefoon- of e-mailgegevens. Die zijn
    privacygevoelig en alleen zichtbaar voor de directie en de administratie.
  </div></div>

  <div class="zoekbalk">
    <SearchInput v-model="zoek" placeholder="Zoek op achternaam, registratienummer of adres…" />
    <select v-model="status" class="filter" aria-label="Filter op status">
      <option value="">Status: alle</option>
      <option value="actief">Status: actief</option>
      <option value="wacht">Status: wacht op beoordeling</option>
      <option value="afgewezen">Status: afgewezen</option>
    </select>
    <button v-if="rechten.beheren" class="knop primair" @click="drawer = { open: true, klant: null }">+ Nieuwe klant</button>
  </div>

  <div id="lijst" class="tbl">
    <div class="thead">
      <span /><span>Naam</span><span class="c-adres">Adres</span><span>Gezinsgrootte</span>
      <span>Dieetbeperkingen</span><span class="c-laatste">Laatste uitgifte</span><span>Status</span>
    </div>

    <KlantRij v-for="k in zichtbaar" :key="k.id" v-model:open="open[k.id]" :klant="k" :rechten="rechten"
              @bewerk="drawer = { open: true, klant: k }" @status="(akkoord) => zetStatus(k, akkoord)"
              @verwijder="verwijderen(k)" @geschiedenis="geschiedenis = k" />

    <EmptyState v-if="!zichtbaar.length" titel="Geen gezinnen gevonden" tekst="Pas je zoekopdracht of het filter aan." />
  </div>

  <KlantDrawer :open="drawer.open" :klant="drawer.klant" :wensen="wensen" :rechten="rechten"
               @sluit="drawer.open = false" @opgeslagen="opgeslagen" @wens-toegevoegd="wensToegevoegd" />

  <PakketGeschiedenis :klant="geschiedenis" @sluit="geschiedenis = null" />
</template>

<style scoped>
#lijst { --kolommen: 22px 2.05fr 1.5fr 1.1fr 1.95fr 1.25fr .9fr; }
.filter { height: 42px; border: 1px solid var(--line-2); border-radius: 9px; padding: 0 12px; font: inherit; background: #fff; }
@media (max-width: 1180px) {
  #lijst { --kolommen: 22px 2fr 1.05fr 1.8fr 1fr; }
  #lijst :deep(.c-adres), #lijst :deep(.c-laatste) { display: none; }
}
</style>
