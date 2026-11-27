<script setup>
/**
 * Zijbalk met het menu. Alleen pagina's waar de rol bij mag staan erin
 * (US-SEC-01); de server weigert de rest sowieso.
 */
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useUitloggen } from '@/composables/useUitloggen';
import { initialen } from '@/utils/format';
import AppIcon from '@/components/ui/AppIcon.vue';

const MENU = [
  { pagina: 'dashboard', naar: '/', naam: 'Dashboard' },
  { pagina: 'pakketten', naar: '/pakketten', naam: 'Voedselpakketten' },
  { pagina: 'uitgifte', naar: '/uitgifte', naam: 'Uitgifte', sub: true },
  { pagina: 'producten', naar: '/producten', naam: 'Producten' },
  { pagina: 'leveranciers', naar: '/leveranciers', naam: 'Leveranciers' },
  { pagina: 'klanten', naar: '/klanten', naam: 'Klanten' },
  { pagina: 'rapportages', naar: '/rapportages', naam: 'Rapportages' },
  { pagina: 'gebruikers', naar: '/gebruikers', naam: 'Gebruikers' },
  { pagina: 'account', naar: '/account', naam: 'Mijn account' },
];

const auth = useAuthStore();
const uitloggen = useUitloggen();

const items = computed(() => MENU.filter((m) => auth.magPagina(m.pagina)));
</script>

<template>
  <aside class="side">
    <div class="brand">
      <div class="logo">VB</div>
      <div>
        <div class="naam">Maaskantje</div>
        <div class="sub">Back-office</div>
      </div>
    </div>

    <nav class="nav">
      <RouterLink v-for="m in items" :key="m.pagina" :to="m.naar" class="nav-item"
                  :class="{ sub: m.sub }" :active-class="m.naar === '/' ? '' : 'actief'" exact-active-class="actief" :title="m.naam">
        <AppIcon v-if="!m.sub" :naam="m.pagina" />
        <span>{{ m.naam }}</span>
      </RouterLink>
    </nav>

    <div class="gebruiker">
      <div class="wie">
        <div class="avatar">{{ initialen(auth.gebruiker.naam) }}</div>
        <div class="stapel">
          <div class="naam">{{ auth.gebruiker.naam }}</div>
          <div class="rol">{{ auth.gebruiker.rol }}</div>
        </div>
      </div>
      <button class="uit" type="button" title="Uitloggen" @click="uitloggen">
        <AppIcon naam="uitloggen" /><span class="woord">Uitloggen</span>
      </button>
    </div>
  </aside>
</template>
