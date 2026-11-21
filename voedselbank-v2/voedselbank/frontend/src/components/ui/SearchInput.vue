<script setup>
/** Zoekveld met vergrootglas. Gebruik met v-model. */
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: String, default: '' });
defineProps({ placeholder: { type: String, default: 'Zoeken…' } });
</script>

<template>
  <div class="zoek">
    <AppIcon naam="zoeken" :dikte="2" />
    <input v-model="model" type="search" :placeholder="placeholder" :aria-label="placeholder">
  </div>
</template>
