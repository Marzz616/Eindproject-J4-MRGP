<script setup>
/**
 * Inloggen (US-SEC-01). Na het inloggen gaat de gebruiker terug naar de pagina
 * waar hij heen wilde (?terug=), mits zijn rol die pagina mag zien.
 */
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { startpagina } from '@/router';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ gebruikersnaam: '', wachtwoord: '' });
const fout = ref('');
const bezig = ref(false);
const wachtwoordVeld = ref(null);

const TESTACCOUNTS = [
  ['p.abraham', 'Directie'],
  ['m.snel', 'Administratie'],
  ['r.veenstra', 'Magazijnmedewerker'],
  ['a.degroot', 'Vrijwilliger'],
  ['k.degraaf', 'Vrijwilliger (geblokkeerd)'],
];

async function inloggen() {
  fout.value = '';
  bezig.value = true;
  try {
    const g = await auth.login(form.gebruikersnaam.trim(), form.wachtwoord);
    const terug = String(route.query.terug || '');
    const doel = terug && router.resolve(terug);
    const magTerug = doel && doel.name !== 'login' && (!doel.meta.pagina || g.paginas.includes(doel.meta.pagina));
    router.replace(magTerug ? terug : startpagina(g));
  } catch (e) {
    fout.value = e.message || 'Inloggen is niet gelukt.';
    form.wachtwoord = '';
    wachtwoordVeld.value?.focus();
  } finally {
    bezig.value = false;
  }
}
</script>

<template>
  <div class="login">
    <div class="doos">
      <div class="merk">
        <div class="logo-groot">VB</div>
        <div>
          <div class="merknaam">Voedselbank Maaskantje</div>
          <div class="merksub">Back-office</div>
        </div>
      </div>

      <h1>Inloggen</h1>
      <div class="onder">Log in met je medewerkersaccount om verder te gaan.</div>

      <div v-if="fout" class="melding rood" role="alert"><div><b>{{ fout }}</b></div></div>

      <form novalidate @submit.prevent="inloggen">
        <div class="veld">
          <label for="gebruikersnaam">Gebruikersnaam of e-mailadres</label>
          <input id="gebruikersnaam" v-model="form.gebruikersnaam" name="gebruikersnaam" type="text"
                 autocomplete="username" autofocus required>
        </div>
        <div class="veld">
          <label for="wachtwoord">Wachtwoord</label>
          <input id="wachtwoord" ref="wachtwoordVeld" v-model="form.wachtwoord" name="wachtwoord" type="password"
                 autocomplete="current-password" required>
        </div>
        <button class="knop primair groot volle" type="submit" :disabled="bezig">
          {{ bezig ? 'Bezig…' : 'Inloggen' }}
        </button>
      </form>

      <p class="geenaccount">Nog geen account? Vraag de directie om er een voor je aan te maken.</p>

      <div class="demo">
        <b>Testaccounts</b> (wachtwoord voor alle accounts: <span class="mono">voedselbank</span>)
        <table>
          <tr v-for="[naam, rol] in TESTACCOUNTS" :key="naam"><td class="mono">{{ naam }}</td><td>{{ rol }}</td></tr>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.logo-groot {
  width: 44px; height: 44px; border-radius: 12px; background: var(--green); color: #fff;
  font-family: 'Zilla Slab', serif; font-weight: 700; display: grid; place-items: center; font-size: 17px;
}
.merknaam { font-family: 'Zilla Slab', serif; font-weight: 700; font-size: 17px; line-height: 1.1; }
.merksub { font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-3); font-weight: 600; margin-top: 3px; }
</style>
