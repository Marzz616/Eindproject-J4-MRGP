/**
 * Alle pagina's van de back-office en wie ze mag openen.
 *
 * Elke pagina binnen de vaste schermindeling (AppLayout) heeft in meta:
 *   pagina  de sleutel die de server per rol meestuurt (gebruiker.paginas)
 *   titel   de kop in de bovenbalk
 *   onder   de ondertitel
 *
 * De routebewaking (beforeEach) stuurt niet-ingelogde bezoekers naar het
 * inlogscherm en laat een "geen toegang"-pagina zien als de rol een pagina
 * niet mag openen. De server controleert dat daarna nog eens per API-verzoek.
 */
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { onUnauthorized } from '@/services/http';

import AppLayout from '@/components/layout/AppLayout.vue';

const routes = [
  { path: '/login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { publiek: true } },
  // de pakbon is een losse afdrukpagina, zonder menu
  { path: '/pakbon/:id', name: 'pakbon', component: () => import('@/views/pakketten/PakbonView.vue'), meta: { pagina: null } },

  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/dashboard/DashboardView.vue'),
        meta: { pagina: 'dashboard', titel: 'Dashboard', onder: 'Operationele status van Voedselbank Maaskantje' } },

      { path: 'pakketten', name: 'pakketten', component: () => import('@/views/pakketten/PakkettenView.vue'),
        meta: { pagina: 'pakketten', titel: 'Voedselpakket samenstellen', onder: 'Samenstellen op basis van gezinssamenstelling en allergieprofiel' } },

      { path: 'uitgifte', name: 'uitgifte', component: () => import('@/views/uitgifte/UitgifteView.vue'),
        meta: { pagina: 'uitgifte', titel: 'Vrijdag uitgiftepunt', onder: 'Registratie en verificatie van pakketuitgifte aan cliënten' } },

      { path: 'producten', name: 'producten', component: () => import('@/views/producten/ProductenView.vue'),
        meta: { pagina: 'producten', titel: 'Producten & Voorraad', onder: 'Real-time overzicht van magazijnlocaties, voorraadstanden en THT-data' } },

      { path: 'leveranciers', name: 'leveranciers', component: () => import('@/views/leveranciers/LeveranciersView.vue'),
        meta: { pagina: 'leveranciers', titel: 'Leveranciersbeheer', onder: 'Beheer de toelevering, contacten en donatiegeschiedenis van voedseldonateurs' } },

      { path: 'klanten', name: 'klanten', component: () => import('@/views/klanten/KlantenView.vue'),
        meta: { pagina: 'klanten', titel: 'Klanten- & Gezinnenadministratie', onder: 'Inzicht in huishoudens en individuele dieetbehoeften' } },

      { path: 'rapportages', name: 'rapportages', component: () => import('@/views/rapportages/RapportagesView.vue'),
        meta: { pagina: 'rapportages', titel: 'Rapportages & statistieken', onder: 'Analyses over uitgiftevolumes, voorraadverloop en operationele efficiëntie' } },

      { path: 'rapportages/maand', name: 'maandoverzicht', component: () => import('@/views/rapportages/MaandoverzichtView.vue'),
        meta: { pagina: 'rapportages', titel: 'Maandoverzichten', onder: 'De twee vaste maandrapportages uit de casus, te filteren en af te drukken' } },

      { path: 'gebruikers', name: 'gebruikers', component: () => import('@/views/gebruikers/GebruikersView.vue'),
        meta: { pagina: 'gebruikers', titel: 'Gebruikers & toegangsbeheer', onder: 'Beheer de rollen, permissies en toegangsrechten van medewerkers en vrijwilligers' } },

      { path: 'account', name: 'account', component: () => import('@/views/auth/AccountView.vue'),
        meta: { pagina: 'account', titel: 'Mijn account', onder: 'Je gegevens, je rechten en je wachtwoord' } },

      { path: 'geen-toegang', name: 'geen-toegang', component: () => import('@/views/GeenToegangView.vue'),
        meta: { pagina: null, titel: 'Geen toegang', onder: 'Deze pagina hoort niet bij jouw rol' } },

      { path: ':pad(.*)*', name: 'niet-gevonden', component: () => import('@/views/NietGevondenView.vue'),
        meta: { pagina: null, titel: 'Pagina niet gevonden', onder: 'Dit adres bestaat niet' } },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
});

/** Waar komt iemand terecht na het inloggen? De eerste pagina die hij mag zien. */
export function startpagina(gebruiker) {
  const volgorde = ['dashboard', 'pakketten', 'uitgifte', 'producten', 'leveranciers', 'klanten', 'gebruikers', 'account'];
  const eerste = volgorde.find((p) => gebruiker.paginas.includes(p)) || 'account';
  return { name: eerste };
}

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  await auth.laad();

  if (to.meta.publiek) {
    // al ingelogd? dan niet nog eens het inlogscherm tonen
    return to.name === 'login' && auth.ingelogd ? startpagina(auth.gebruiker) : true;
  }

  if (!auth.ingelogd) {
    return { name: 'login', query: to.fullPath !== '/' ? { terug: to.fullPath } : {} };
  }

  if (to.meta.pagina && !auth.magPagina(to.meta.pagina)) {
    return { name: 'geen-toegang', query: { pagina: to.meta.pagina } };
  }
  return true;
});

// Sessie verlopen tijdens het werken: terug naar het inlogscherm.
onUnauthorized(() => {
  const auth = useAuthStore();
  auth.vergeet();
  const hier = router.currentRoute.value;
  if (hier.name !== 'login') {
    router.push({ name: 'login', query: { terug: hier.fullPath } });
  }
});

export default router;
