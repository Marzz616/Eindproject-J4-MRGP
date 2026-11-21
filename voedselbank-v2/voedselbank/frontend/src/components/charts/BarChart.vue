<script setup>
/** Eenvoudig staafdiagram zonder externe bibliotheek. reeks = [{label, aantal}] */
import { computed } from 'vue';
import { getal } from '@/utils/format';

const props = defineProps({ reeks: { type: Array, required: true } });
const max = computed(() => Math.max(...props.reeks.map((r) => r.aantal), 1));
</script>

<template>
  <div v-if="!reeks.length" class="leeg">Nog geen gegevens.</div>
  <div v-else class="staven">
    <div v-for="r in reeks" :key="r.label" class="kolom">
      <div class="getal">{{ getal(r.aantal) }}</div>
      <div class="staaf" :style="{ height: Math.max(4, Math.round((r.aantal / max) * 130)) + 'px' }"
           :title="`${r.label}: ${getal(r.aantal)}`" />
      <div class="naam">{{ r.label }}</div>
    </div>
  </div>
</template>
