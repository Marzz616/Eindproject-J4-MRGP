/**
 * Wie is er ingelogd, en wat mag hij?
 *
 * De server stuurt bij het inloggen de permissies en de pagina's mee die bij
 * de rol horen. De frontend gebruikt die om het menu op te bouwen en pagina's
 * af te schermen; de échte controle doet de server bij elk verzoek.
 */
import { defineStore } from 'pinia';
import { authApi } from '@/services/api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    gebruiker: null,
    geladen: false,
  }),

  getters: {
    ingelogd: (s) => s.gebruiker !== null,
    /** Heeft de ingelogde gebruiker deze permissie? */
    mag: (s) => (permissie) => !!s.gebruiker?.permissies.includes(permissie),
    /** Mag de ingelogde gebruiker deze pagina openen? */
    magPagina: (s) => (pagina) => !!s.gebruiker?.paginas.includes(pagina),
  },

  actions: {
    /** Eén keer per paginabezoek vragen wie er is ingelogd. */
    async laad(opnieuw = false) {
      if (this.geladen && !opnieuw) return this.gebruiker;
      try {
        const r = await authApi.ik();
        this.gebruiker = r.ingelogd ? r.gebruiker : null;
      } catch {
        this.gebruiker = null;
      }
      this.geladen = true;
      return this.gebruiker;
    },

    async login(gebruikersnaam, wachtwoord) {
      const r = await authApi.login(gebruikersnaam, wachtwoord);
      this.gebruiker = r.gebruiker;
      this.geladen = true;
      return r.gebruiker;
    },

    async logout() {
      try {
        await authApi.logout();
      } finally {
        this.vergeet();
      }
    },

    /** Lokaal uitloggen (bijv. als de server zegt dat de sessie verlopen is). */
    vergeet() {
      this.gebruiker = null;
      this.geladen = true;
    },
  },
});
