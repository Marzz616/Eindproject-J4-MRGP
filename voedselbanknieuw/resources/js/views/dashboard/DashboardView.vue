<script setup>
/**
 * Dashboard per rol (US-WRK-02/03, US-VRD-03/12). De server bepaalt welke
 * kaarten, lijsten en taken iemand ziet; deze pagina tekent ze alleen.
 */
import { computed, onMounted, ref } from 'vue';
import { dashboardApi } from '@/services/api';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import StatCard from '@/components/dashboard/StatCard.vue';
import ListBlock from '@/components/dashboard/ListBlock.vue';
import TaskList from '@/components/dashboard/TaskList.vue';
import BarChart from '@/components/charts/BarChart.vue';

const data = ref(null);
const fout = ref(false);
const ondertitel = usePageSubtitle();

onMounted(async () => {
  try {
    data.value = await dashboardApi.ophalen();
    ondertitel.zet(data.value.intro);   // waarom dit dashboard er zo uitziet voor deze rol
  } catch {
    fout.value = true;
  }
});

// de grafiek is breed, de lijsten smal: samen in de 2-op-1-indeling
const grafiek = computed(() => data.value?.blokken.find((b) => b.soort === 'grafiek'));
const lijsten = computed(() => data.value?.blokken.filter((b) => b.soort !== 'grafiek') || []);
</script>

<template>
  <div v-if="fout" class="melding rood"><div>Het dashboard kon niet worden geladen.</div></div>
  <div v-else-if="!data" class="skelet">Bezig met laden…</div>

  <template v-else>
    <TaskList :taken="data.taken" />

    <div v-if="data.kaarten.length" class="kaarten">
      <StatCard v-for="k in data.kaarten" :key="k.sleutel" :kaart="k" />
    </div>

    <template v-if="grafiek">
      <div class="rooster k21">
        <div class="card vul">
          <div class="kop"><h2>{{ grafiek.titel }}</h2><span class="tel rechts">{{ grafiek.tel }}</span></div>
          <div class="body"><BarChart :reeks="grafiek.weken" /></div>
        </div>
        <ListBlock v-if="lijsten[0]" :blok="lijsten[0]" />
      </div>
      <div v-if="lijsten.length > 1" class="rooster k2" style="margin-top:18px">
        <ListBlock v-for="b in lijsten.slice(1)" :key="b.titel" :blok="b" />
      </div>
    </template>
    <div v-else-if="lijsten.length" class="rooster k2">
      <ListBlock v-for="b in lijsten" :key="b.titel" :blok="b" />
    </div>
  </template>
</template>

<style scoped>
/* het aantal cijferkaarten verschilt per rol, dus ze vullen zichzelf uit */
.kaarten { display: grid; gap: 18px; margin-bottom: 18px; grid-template-columns: repeat(auto-fit, minmax(232px, 1fr)); }
</style>
