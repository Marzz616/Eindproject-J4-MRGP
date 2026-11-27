<script setup>
/** Korte meldingen rechtsonder; zie composables/useToast.js. */
import { useToast } from '@/composables/useToast';

const { meldingen } = useToast();
</script>

<template>
  <div id="toasts" aria-live="polite">
    <div v-for="m in meldingen" :key="m.id" class="toast" :class="m.soort">{{ m.tekst }}</div>
  </div>
</template>
