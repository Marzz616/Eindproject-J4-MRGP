<script setup>
/** Lijngrafiek als SVG, voor het voorraadverloop. punten = [{datum, stand}] */
import { computed } from 'vue';

const props = defineProps({
  punten: { type: Array, required: true },
  label: { type: String, default: 'Verloop' },
});

const B = 620;
const H = 180;
const PAD = 8;

const paden = computed(() => {
  const p = props.punten;
  if (p.length < 2) return null;
  const waarden = p.map((x) => x.stand);
  const min = Math.min(...waarden);
  const bereik = Math.max(1, Math.max(...waarden) - min);
  const xy = p.map((x, i) => [
    PAD + i * ((B - PAD * 2) / (p.length - 1)),
    H - PAD - ((x.stand - min) / bereik) * (H - PAD * 2),
  ]);
  const lijn = xy.map(([x, y], i) => (i ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1)).join(' ');
  const vlak = `${lijn} L${xy[xy.length - 1][0].toFixed(1)} ${H} L${xy[0][0].toFixed(1)} ${H} Z`;
  return { lijn, vlak };
});
</script>

<template>
  <div v-if="!paden" class="leeg">Nog geen gegevens.</div>
  <svg v-else class="lijngrafiek" :viewBox="`0 0 ${B} ${H}`" preserveAspectRatio="none" role="img" :aria-label="label">
    <defs>
      <linearGradient id="verloopvlak" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2E6B4A" stop-opacity=".22" />
        <stop offset="100%" stop-color="#2E6B4A" stop-opacity="0" />
      </linearGradient>
    </defs>
    <path :d="paden.vlak" fill="url(#verloopvlak)" />
    <path :d="paden.lijn" fill="none" stroke="#2E6B4A" stroke-width="2" stroke-linejoin="round" />
  </svg>
</template>
