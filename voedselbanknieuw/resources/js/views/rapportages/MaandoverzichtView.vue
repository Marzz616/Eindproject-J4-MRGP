<script setup>
/**
 * De twee vaste maandoverzichten uit de casus (RAP-1, RAP-2, RAP-3):
 * per productcategorie (geleverd en uitgegeven) en per postcode. Te filteren en af te drukken.
 */
import { computed, ref, watch } from 'vue';
import { rapportagesApi } from '@/services/api';
import { meldFout } from '@/composables/useFormErrors';
import { MAANDEN_VOL, datumKort, getal, vandaagIso } from '@/utils/format';

const nu = new Date();
const soort = ref('categorie');
const maand = ref(nu.getMonth() + 1);
const jaar = ref(nu.getFullYear());
const d = ref(null);
const laden = ref(false);

const jaren = Array.from({ length: 5 }, (_, i) => nu.getFullYear() - i);

watch([soort, maand, jaar], async () => {
  laden.value = true;
  try { d.value = await rapportagesApi.maand(soort.value, maand.value, jaar.value); } catch (e) { meldFout(e); }
  laden.value = false;
}, { immediate: true });

/** RAP-1: rijen met een kopregel per categorie. */
const categorieRijen = computed(() => {
  if (d.value?.soort !== 'categorie') return [];
  let vorige = null;
  return d.value.rijen.map((r) => {
    const kop = r.categorie !== vorige ? r.categorie : null;
    vorige = r.categorie;
    return { ...r, kop };
  });
});

const totaalPostcode = computed(() => (d.value?.soort === 'postcode' ? d.value.rijen.reduce((s, r) => s + r.totaal, 0) : 0));
const periode = computed(() => `${MAANDEN_VOL[maand.value - 1]} ${jaar.value}`);
</script>

<template>
  <div class="zoekbalk nietprint">
    <div class="chips" style="flex:1">
      <button class="chip" :class="{ aan: soort === 'categorie' }" @click="soort = 'categorie'">Per productcategorie</button>
      <button class="chip" :class="{ aan: soort === 'postcode' }" @click="soort = 'postcode'">Per postcode</button>
    </div>
    <select v-model="maand" class="filter" aria-label="Maand">
      <option v-for="(m, i) in MAANDEN_VOL" :key="m" :value="i + 1">{{ m }}</option>
    </select>
    <select v-model="jaar" class="filter" aria-label="Jaar">
      <option v-for="j in jaren" :key="j" :value="j">{{ j }}</option>
    </select>
    <RouterLink class="knop" to="/rapportages">← Rapportages</RouterLink>
    <button class="knop primair" onclick="window.print()">Afdrukken</button>
  </div>

  <div class="printkop">
    <h1>Voedselbank Maaskantje — maandoverzicht {{ soort === 'categorie' ? 'per productcategorie' : 'per postcode' }}</h1>
    <div class="onder">{{ periode }} · afgedrukt op {{ datumKort(vandaagIso()) }}</div>
  </div>

  <div v-if="laden && !d" class="skelet">Bezig met laden…</div>

  <div v-else-if="d && !d.rijen.length" class="card"><div class="leeg">
    <h3>Geen gegevens in {{ periode }}</h3><div>Kies een andere maand of een ander jaar.</div>
  </div></div>

  <div v-else-if="d?.soort === 'categorie'" class="card">
    <div class="kop"><h2>Per productcategorie — {{ periode }}</h2><span class="tel rechts">{{ getal(d.totaal) }} stuks binnengekomen</span></div>
    <table class="lijst">
      <thead><tr><th>Product</th><th>Leverancier</th><th class="r">Geleverd deze maand</th><th class="r">Uitgegeven deze maand</th></tr></thead>
      <tbody>
        <template v-for="(r, i) in categorieRijen" :key="i">
          <tr v-if="r.kop"><td colspan="4" class="catkop">{{ r.kop }}</td></tr>
          <tr>
            <td class="sterk" style="padding-left:34px">{{ r.product }}</td>
            <td>{{ r.leverancier }}</td>
            <td class="r mono">{{ getal(r.geleverd) }}</td>
            <td class="r mono zacht">{{ getal(r.uitgegeven) }}</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>

  <div v-else-if="d?.soort === 'postcode'" class="card">
    <div class="kop"><h2>Per postcode — {{ periode }}</h2><span class="tel rechts">{{ getal(totaalPostcode) }} stuks uitgegeven</span></div>
    <table class="lijst">
      <thead><tr>
        <th>Postcode</th><th class="r">Gezinnen</th><th class="r">Pakketten</th>
        <th v-for="c in d.categorieen" :key="c" class="r" style="font-size:10.5px">{{ c }}</th>
        <th class="r">Totaal</th>
      </tr></thead>
      <tbody>
        <tr v-for="r in d.rijen" :key="r.postcode">
          <td class="sterk mono">{{ r.postcode }}</td>
          <td class="r mono zacht">{{ r.gezinnen }}</td>
          <td class="r mono zacht">{{ r.pakketten }}</td>
          <td v-for="c in d.categorieen" :key="c" class="r mono">
            <template v-if="r.per[c]">{{ getal(r.per[c]) }}</template><span v-else class="zacht">—</span>
          </td>
          <td class="r mono sterk">{{ getal(r.totaal) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.filter { height: 42px; border: 1px solid var(--line-2); border-radius: 9px; padding: 0 12px; font: inherit; background: #fff; }
.catkop { height: 44px; background: var(--row-alt); font-weight: 700; font-size: 13px; }
.printkop { display: none; }
@media print {
  .printkop { display: block; margin-bottom: 14px; }
  .printkop h1 { font-size: 20px; }
  .printkop .onder { color: #555; font-size: 12px; }
  @page { size: A4 landscape; margin: 14mm; }
}
</style>
