<script setup>
/** "Dit staat er voor jou open" bovenaan het dashboard. */
defineProps({ taken: { type: Array, required: true } });
</script>

<template>
  <div v-if="taken.length" class="taken">
    <div v-for="(t, i) in taken" :key="i" class="taak" :class="t.kleur">
      <span>{{ t.tekst }}</span>
      <RouterLink :to="t.link">Bekijken →</RouterLink>
    </div>
  </div>
  <div v-else class="melding groen" style="margin-bottom:18px"><div>Er staat niets open voor jou. Mooi werk.</div></div>
</template>

<style scoped>
.taken { display: grid; gap: 8px; margin-bottom: 18px; }
.taak { display: flex; align-items: center; gap: 11px; background: #fff; border: 1px solid var(--line-2);
        border-left: 4px solid var(--line-2); border-radius: 10px; padding: 11px 15px; }
.taak.amber { border-left-color: var(--amber); }
.taak.rood  { border-left-color: var(--red); }
.taak.groen { border-left-color: var(--green); }
.taak span  { flex: 1; }
.taak a     { font-weight: 600; color: var(--green); text-decoration: none; white-space: nowrap; }
.taak a:hover { text-decoration: underline; }
</style>
