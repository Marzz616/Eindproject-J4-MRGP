<script setup>
/** Mijn account: eigen gegevens, eigen rechten en wachtwoord wijzigen (US-GBR-04). */
import { onMounted, reactive, ref } from 'vue';
import { authApi } from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { useFormErrors } from '@/composables/useFormErrors';
import { useToast } from '@/composables/useToast';
import { useUitloggen } from '@/composables/useUitloggen';
import { initialen } from '@/utils/format';
import FormField from '@/components/ui/FormField.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

const auth = useAuthStore();
const toast = useToast();
const uitloggen = useUitloggen();
const { fouten, verwerk, wis } = useFormErrors();

const rechten = ref([]);
const form = reactive({ huidig: '', nieuw: '', herhaal: '' });

onMounted(async () => {
  try {
    rechten.value = (await authApi.rechten()).rechten.map((r) => r.Naam);
  } catch {
    rechten.value = auth.gebruiker.permissies.map((c) => c.replace(/_/g, ' '));
  }
});

async function wachtwoordOpslaan() {
  try {
    await authApi.wachtwoord(form.huidig, form.nieuw, form.herhaal);
    Object.assign(form, { huidig: '', nieuw: '', herhaal: '' });
    wis();
    toast.goed('Je wachtwoord is gewijzigd.');
  } catch (e) {
    verwerk(e);
  }
}
</script>

<template>
  <div class="rooster k2">
    <div class="card">
      <div class="kop"><h2>Mijn gegevens</h2></div>
      <div class="body">
        <div class="horizontaal" style="gap:14px;margin-bottom:18px">
          <div class="groot-avatar">{{ initialen(auth.gebruiker.naam) }}</div>
          <div>
            <div class="naam">{{ auth.gebruiker.naam }}</div>
            <div style="color:var(--ink-2)">{{ auth.gebruiker.rol }}</div>
          </div>
        </div>
        <ul class="lijstje">
          <li><span class="sleutel">Gebruikersnaam</span><span class="mono">{{ auth.gebruiker.gnaam }}</span></li>
          <li><span class="sleutel">E-mailadres</span><span>{{ auth.gebruiker.email }}</span></li>
          <li><span class="sleutel">Rol</span><span>{{ auth.gebruiker.rol }}</span></li>
        </ul>
        <div class="melding grijs" style="margin-top:16px"><div>
          Kloppen je naam, gebruikersnaam of rol niet? Alleen de directie of de administratie kan die aanpassen.
        </div></div>
        <button type="button" class="knop" style="margin-top:4px" @click="uitloggen">
          <AppIcon naam="uitloggen" /> Uitloggen
        </button>
      </div>
    </div>

    <div class="card">
      <div class="kop"><h2>Wachtwoord wijzigen</h2></div>
      <div class="body">
        <form novalidate @submit.prevent="wachtwoordOpslaan">
          <FormField label="Huidig wachtwoord" for="huidig" :fout="fouten.huidig">
            <input id="huidig" v-model="form.huidig" name="huidig" type="password" autocomplete="current-password">
          </FormField>
          <FormField label="Nieuw wachtwoord" for="nieuw" hint="Minimaal 8 tekens met letters én cijfers." :fout="fouten.nieuw">
            <input id="nieuw" v-model="form.nieuw" name="nieuw" type="password" autocomplete="new-password">
          </FormField>
          <FormField label="Nieuw wachtwoord herhalen" for="herhaal" :fout="fouten.herhaal">
            <input id="herhaal" v-model="form.herhaal" name="herhaal" type="password" autocomplete="new-password">
          </FormField>
          <button type="submit" class="knop primair volle">Wachtwoord opslaan</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="kop"><h2>Wat mag ik in deze applicatie?</h2></div>
    <div class="body">
      <ul class="lijstje rechten">
        <li v-for="r in rechten" :key="r"><span class="vink">✓</span>{{ r }}</li>
      </ul>
      <div style="margin-top:14px;font-size:13.5px;color:var(--ink-2)">
        Deze rechten horen bij de rol <b>{{ auth.gebruiker.rol }}</b>. De server controleert ze bij elke actie,
        dus wat hier niet staat lukt ook niet via een omweg.
      </div>
    </div>
  </div>
</template>

<style scoped>
.groot-avatar { width: 54px; height: 54px; border-radius: 50%; background: var(--green); color: #fff;
                display: grid; place-items: center; font-weight: 700; font-size: 21px; }
.naam { font-family: 'Zilla Slab', serif; font-size: 20px; font-weight: 700; }
.sleutel { color: var(--ink-3); width: 130px; }
.rechten { display: grid; grid-template-columns: 1fr 1fr; gap: 0 22px; }
@media (max-width: 820px) { .rechten { grid-template-columns: 1fr; } }
</style>
