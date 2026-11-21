<script setup>
/** Bovenbalk met de titel van de pagina en de rol van de gebruiker. */
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { usePageSubtitle } from '@/composables/usePageSubtitle';

const route = useRoute();
const auth = useAuthStore();
const ondertitel = usePageSubtitle();

// bij een andere pagina weer de standaard-ondertitel
watch(() => route.name, () => ondertitel.herstel());

const onder = computed(() => ondertitel.vervanging.value || route.meta.onder || '');
</script>

<template>
  <header class="topbar">
    <div>
      <h1>{{ route.meta.titel }}</h1>
      <div class="onder">{{ onder }}</div>
    </div>
    <div class="rolpil">{{ auth.gebruiker.rol }} view</div>
  </header>
</template>
