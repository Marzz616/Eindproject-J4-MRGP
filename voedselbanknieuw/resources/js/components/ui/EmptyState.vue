<script setup>
/** Lege lijst: een kop en een korte uitleg. */
defineProps({
  titel: { type: String, default: '' },
  tekst: { type: String, default: '' },
});
</script>

<template>
  <div class="leeg">
    <h3 v-if="titel">{{ titel }}</h3>
    <div v-if="tekst">{{ tekst }}</div>
    <slot />
  </div>
</template>
