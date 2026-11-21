<script setup>
/** Account toevoegen of bewerken (US-GBR-01/02). */
import { computed, reactive, watch } from 'vue';
import { gebruikersApi } from '@/services/api';
import { useFormErrors } from '@/composables/useFormErrors';
import SideDrawer from '@/components/ui/SideDrawer.vue';
import FormField from '@/components/ui/FormField.vue';

const props = defineProps({
  open: Boolean,
  gebruiker: { type: Object, default: null },
  rollen: { type: Array, required: true },
  permissies: { type: Array, required: true },
});
const emit = defineEmits(['sluit', 'opgeslagen']);
const { fouten, verwerk, wis } = useFormErrors();

const form = reactive({ id: 0, naam: '', gnaam: '', email: '', rolId: 0, wachtwoord: '' });

watch(() => props.open, (open) => {
  if (!open) return;
  wis();
  const g = props.gebruiker;
  Object.assign(form, {
    id: g?.id || 0,
    naam: g?.naam || '',
    gnaam: g?.gnaam || '',
    email: g?.email || '',
    rolId: g?.rolId || props.rollen[0]?.id || 0,
    wachtwoord: '',
  });
});

// laat direct zien welke rechten bij de gekozen rol horen
const rolUitleg = computed(() => {
  const rol = props.rollen.find((r) => r.id === Number(form.rolId));
  if (!rol) return '';
  const namen = rol.permissies.map((id) => props.permissies.find((p) => p.id === id)?.naam).filter(Boolean);
  return `${rol.naam} krijgt: ${namen.length ? namen.join(', ') : 'geen bijzondere rechten'}.`;
});

async function opslaan() {
  try {
    const r = await gebruikersApi.opslaan({ ...form, rolId: Number(form.rolId) });
    emit('opgeslagen', r.melding);
  } catch (e) { verwerk(e); }
}
</script>

<template>
  <SideDrawer :open="open" :titel="gebruiker ? 'Account bewerken' : 'Nieuwe gebruiker'"
              :onder="gebruiker ? gebruiker.naam : 'Maak een back-office account aan'" @sluit="emit('sluit')">
    <form novalidate @submit.prevent="opslaan">
      <FormField label="Volledige naam" for="g-naam" :fout="fouten.naam">
        <input id="g-naam" v-model="form.naam" name="naam" type="text" placeholder="Ank de Groot">
      </FormField>
      <div class="rij2">
        <FormField label="Gebruikersnaam" for="g-gnaam" :fout="fouten.gnaam">
          <input id="g-gnaam" v-model="form.gnaam" name="gnaam" type="text" placeholder="a.degroot" autocomplete="off">
        </FormField>
        <FormField label="Rol" for="g-rol" :fout="fouten.rolId">
          <select id="g-rol" v-model="form.rolId" name="rolId">
            <option v-for="r in rollen" :key="r.id" :value="r.id">{{ r.naam }}</option>
          </select>
        </FormField>
      </div>
      <FormField label="E-mailadres" for="g-mail" :fout="fouten.email">
        <input id="g-mail" v-model="form.email" name="email" type="email" placeholder="naam@voedselbank.nl">
      </FormField>
      <FormField label="Wachtwoord" for="g-pw" :fout="fouten.wachtwoord"
                 :hint="gebruiker ? 'Laat leeg om het huidige wachtwoord te laten staan. Anders: minimaal 8 tekens met letters én cijfers.'
                                  : 'Minimaal 8 tekens met letters én cijfers.'">
        <input id="g-pw" v-model="form.wachtwoord" name="wachtwoord" type="password" autocomplete="new-password">
      </FormField>
      <div class="melding grijs"><div>{{ rolUitleg }}</div></div>
    </form>
    <template #voet>
      <button class="knop" type="button" @click="emit('sluit')">Annuleren</button>
      <button class="knop primair" type="button" @click="opslaan">Opslaan</button>
    </template>
  </SideDrawer>
</template>
