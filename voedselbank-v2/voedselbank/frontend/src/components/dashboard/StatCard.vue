<script setup>
/** Cijferkaart bovenaan het dashboard: label, waarde (of tekst) en voetregel. */
import { getal } from '@/utils/format';

defineProps({ kaart: { type: Object, required: true } });
</script>

<template>
  <div class="card stat" :class="kaart.kleur">
    <div class="label">{{ kaart.label }}</div>
    <div v-if="kaart.tekst !== undefined" class="tekst">{{ kaart.tekst }}</div>
    <div v-else class="waarde">{{ getal(kaart.waarde) }}</div>
    <div class="voet">
      <RouterLink v-if="kaart.link" :to="kaart.link">{{ kaart.voet }}</RouterLink>
      <template v-else>{{ kaart.voet }}</template>
    </div>
  </div>
</template>

<style scoped>
.tekst { font-family: 'Zilla Slab', serif; font-size: 23px; font-weight: 700; line-height: 1.2; margin: 2px 0 4px; }
.voet a { color: inherit; text-decoration: none; }
.voet a:hover { text-decoration: underline; }
</style>
