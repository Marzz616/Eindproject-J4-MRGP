<script setup>
/** Getoond als een rol een pagina opent die er niet bij hoort (US-SEC-01). */
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const NAMEN = {
  dashboard: 'Dashboard', pakketten: 'Voedselpakketten', uitgifte: 'Uitgifte', producten: 'Producten',
  leveranciers: 'Leveranciers', klanten: 'Klanten', rapportages: 'Rapportages', gebruikers: 'Gebruikers', account: 'Mijn account',
};
const wel = computed(() => auth.gebruiker.paginas.map((p) => NAMEN[p] || p).join(', '));
</script>

<template>
  <div class="melding rood" role="alert">
    <div>
      <b>Je hebt geen rechten voor deze pagina.</b><br>
      Als {{ auth.gebruiker.rol.toLowerCase() }} kun je wel bij: {{ wel }}.
    </div>
  </div>
  <RouterLink class="knop primair" :to="auth.magPagina('dashboard') ? '/' : '/account'">Terug naar het begin</RouterLink>
</template>
