<script setup>
/** Rapportages en statistieken (US-RAP-01 t/m 06), met CSV-export per onderdeel. */
import { computed, onMounted, ref } from 'vue';
import { rapportagesApi } from '@/services/api';
import { meldFout } from '@/composables/useFormErrors';
import { datumKort, geld, getal } from '@/utils/format';
import BarChart from '@/components/charts/BarChart.vue';
import LineChart from '@/components/charts/LineChart.vue';

const d = ref(null);
onMounted(async () => {
  try { d.value = await rapportagesApi.overzicht(); } catch (e) { meldFout(e); }
});

const haaltDoel = computed(() => d.value && d.value.verspilling.percentage <= d.value.verspilling.doel);
const groeiTekst = computed(() => {
  const g = d.value?.pakketten.groei;
  if (g === null || g === undefined) return 'Geen vergelijkbare cijfers van vorig jaar';
  return `${g >= 0 ? '↑' : '↓'} ${Math.abs(g)}% t.o.v. vorig jaar (${getal(d.value.pakketten.vorigJaar)})`;
});
const csv = (rapport) => { window.location.href = rapportagesApi.csvUrl(rapport); };
const afdrukken = () => window.print();
</script>

<template>
  <div v-if="!d" class="skelet">Bezig met laden…</div>
  <template v-else>
    <div class="zoekbalk nietprint">
      <div style="flex:1;color:var(--ink-2);font-size:14px">Alle cijfers komen rechtstreeks uit de database en zijn te downloaden als CSV voor Excel.</div>
      <RouterLink class="knop primair" to="/rapportages/maand">Maandoverzichten</RouterLink>
      <button class="knop" @click="afdrukken">Rapport afdrukken</button>
    </div>

    <div class="rooster k3" style="margin-bottom:18px">
      <div class="card stat groen">
        <div class="label">Totaal uitgegeven pakketten (dit jaar)</div>
        <div class="waarde">{{ getal(d.pakketten.ytd) }}</div>
        <div class="voet">{{ groeiTekst }}</div>
      </div>
      <div class="card stat">
        <div class="label">Gemiddelde pakketwaarde</div>
        <div class="waarde">{{ geld(d.waarde.gemiddeld) }}</div>
        <div class="voet">berekend op winkelwaarde · totaal {{ geld(d.waarde.totaal) }} uitgedeeld</div>
      </div>
      <div class="card stat" :class="haaltDoel ? 'groen' : 'rood'">
        <div class="label">Verspillingspercentage</div>
        <div class="waarde">{{ d.verspilling.percentage }}%</div>
        <div class="voet">doelstelling is &lt; {{ d.verspilling.doel }}% · {{ d.verspilling.nuttig }}% nuttig besteed</div>
      </div>
    </div>

    <div class="rooster k2" style="margin-bottom:18px">
      <div class="card vul">
        <div class="kop"><h2>Wekelijkse uitgifte</h2>
          <span class="rechts nietprint"><button class="knop klein" @click="csv('weken')">CSV</button></span></div>
        <div class="body"><BarChart :reeks="d.weken" /></div>
      </div>
      <div class="card">
        <div class="kop"><h2>Voorraadverloop (laatste 30 dagen)</h2>
          <span class="rechts nietprint"><button class="knop klein" @click="csv('verloop')">CSV</button></span></div>
        <div class="body">
          <LineChart :punten="d.verloop" label="Voorraadverloop" />
          <div class="horizontaal as">
            <span>{{ datumKort(d.verloop[0].datum) }}</span>
            <span class="mono">nu {{ getal(d.verloop[d.verloop.length - 1].stand) }} stuks</span>
            <span>{{ datumKort(d.verloop[d.verloop.length - 1].datum) }}</span>
          </div>
          <div class="toelichting">Teruggerekend uit de geboekte leveringen, uitgegeven pakketten en afgevoerde producten.</div>
        </div>
      </div>
    </div>

    <div class="rooster k2">
      <div class="card">
        <div class="kop"><h2>Bestemming producten &amp; verspilling</h2>
          <span class="rechts nietprint"><button class="knop klein" @click="csv('bestemming')">CSV</button></span></div>
        <div class="body">
          <div v-for="b in d.bestemming" :key="b.label" class="verdeelrij">
            <div>{{ b.label }}</div>
            <div class="minibalk"><span :style="{ width: b.percentage + '%' }" /></div>
            <div class="mono" style="text-align:right">{{ b.percentage }}%</div>
          </div>
          <div class="toelichting">Gebaseerd op {{ getal(d.bestemming.reduce((s, b) => s + b.aantal, 0)) }} geregistreerde stuks.</div>
        </div>
      </div>
      <div class="card">
        <div class="kop"><h2>Gezinsgrootte-verdeling</h2>
          <span class="rechts nietprint"><button class="knop klein" @click="csv('verdeling')">CSV</button></span></div>
        <div class="body">
          <div v-for="v in d.verdeling" :key="v.groep" class="verdeelrij">
            <div>{{ v.groep }}</div>
            <div class="minibalk"><span :style="{ width: v.percentage + '%' }" /></div>
            <div class="mono" style="text-align:right">{{ v.aantal }} <span style="color:var(--ink-3)">({{ v.percentage }}%)</span></div>
          </div>
          <div v-if="!d.verdeling.length" class="leeg">Nog geen actieve klanten.</div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="kop"><h2>Meest uitgedeelde producten</h2>
        <span class="rechts nietprint"><button class="knop klein" @click="csv('topproducten')">CSV</button></span></div>
      <table v-if="d.topProducten.length" class="lijst">
        <thead><tr><th>Product</th><th>Categorie</th><th class="r">Aantal uitgegeven</th></tr></thead>
        <tbody>
          <tr v-for="p in d.topProducten" :key="p.naam">
            <td class="sterk">{{ p.naam }}</td>
            <td><span class="badge grijs">{{ p.categorie }}</span></td>
            <td class="r mono">{{ getal(p.aantal) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="leeg">Nog geen pakketten uitgegeven.</div>
    </div>
  </template>
</template>

<style scoped>
.verdeelrij { display: grid; grid-template-columns: 1fr 78px 52px; gap: 12px; align-items: center; padding: 10px 0;
              border-bottom: 1px solid var(--line); font-size: 14px; }
.verdeelrij:last-of-type { border-bottom: 0; }
.minibalk { height: 8px; border-radius: 999px; background: var(--grey-soft); overflow: hidden; }
.minibalk > span { display: block; height: 100%; background: var(--green); }
.as { justify-content: space-between; color: var(--ink-3); font-size: 12.5px; margin-top: 6px; }
.toelichting { font-size: 12px; color: var(--ink-3); margin-top: 8px; }
</style>
