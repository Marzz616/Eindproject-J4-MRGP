<script setup>
/** Leverancier toevoegen of bewerken, met de volgende geplande levering (US-LEV-01/02/06). */
import { reactive, watch } from 'vue';
import { leveranciersApi } from '@/services/api';
import { useFormErrors } from '@/composables/useFormErrors';
import SideDrawer from '@/components/ui/SideDrawer.vue';
import FormField from '@/components/ui/FormField.vue';

const props = defineProps({ open: Boolean, leverancier: { type: Object, default: null } });
const emit = defineEmits(['sluit', 'opgeslagen']);
const { fouten, verwerk, wis } = useFormErrors();

const CATEGORIEEN = ['Supermarkt', 'Bakkerij', 'Groothandel', 'Zuivel', 'Kweker', 'Particulier', 'Overig'];
const LEEG = { id: 0, bedrijfsnaam: '', contactpersoon: '', categorie: 'Supermarkt', telefoon: '', email: '',
               adres: '', postcode: '', plaats: 'Maaskantje', volgendeDatum: '', volgendeTijd: '' };
const form = reactive({ ...LEEG });

watch(() => props.open, (open) => {
  if (!open) return;
  wis();
  const l = props.leverancier;
  Object.assign(form, LEEG, l ? {
    id: l.id, bedrijfsnaam: l.bedrijfsnaam, contactpersoon: l.contactpersoon, categorie: l.categorie,
    telefoon: l.telefoon, email: l.email, adres: l.adres, postcode: l.postcode || '', plaats: l.plaats || 'Maaskantje',
    volgendeDatum: l.volgendeDatum || '', volgendeTijd: l.volgendeTijd || '',
  } : {});
});

async function opslaan() {
  try {
    const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, typeof v === 'string' ? v.trim() : v]));
    emit('opgeslagen', (await leveranciersApi.opslaan(body)).melding);
  } catch (e) { verwerk(e); }
}
</script>

<template>
  <SideDrawer :open="open" :titel="leverancier ? 'Leverancier bewerken' : 'Nieuwe leverancier'"
              :onder="leverancier ? leverancier.bedrijfsnaam : 'Voeg een voedseldonateur toe'" @sluit="emit('sluit')">
    <form novalidate @submit.prevent="opslaan">
      <FormField label="Bedrijfsnaam" for="l-naam" :fout="fouten.bedrijfsnaam">
        <input id="l-naam" v-model="form.bedrijfsnaam" name="bedrijfsnaam" type="text" placeholder="Albert Heijn Maaskant">
      </FormField>
      <div class="rij2">
        <FormField label="Contactpersoon" for="l-contact" :fout="fouten.contactpersoon">
          <input id="l-contact" v-model="form.contactpersoon" name="contactpersoon" type="text">
        </FormField>
        <FormField label="Categorie" for="l-cat" :fout="fouten.categorie">
          <select id="l-cat" v-model="form.categorie" name="categorie">
            <option v-for="c in CATEGORIEEN" :key="c">{{ c }}</option>
          </select>
        </FormField>
      </div>
      <div class="rij2">
        <FormField label="Telefoonnummer" for="l-tel" :fout="fouten.telefoon">
          <input id="l-tel" v-model="form.telefoon" name="telefoon" type="text" placeholder="06-12345678">
        </FormField>
        <FormField label="E-mailadres" for="l-mail" :fout="fouten.email">
          <input id="l-mail" v-model="form.email" name="email" type="email">
        </FormField>
      </div>
      <FormField label="Adres" for="l-adres" :fout="fouten.adres">
        <input id="l-adres" v-model="form.adres" name="adres" type="text">
      </FormField>
      <div class="rij2">
        <FormField label="Postcode" for="l-pc" :fout="fouten.postcode">
          <input id="l-pc" v-model="form.postcode" name="postcode" type="text" placeholder="5492 AA" maxlength="7">
        </FormField>
        <FormField label="Plaats" for="l-plaats" :fout="fouten.plaats">
          <input id="l-plaats" v-model="form.plaats" name="plaats" type="text">
        </FormField>
      </div>
      <h3 style="font-size:15px;margin:20px 0 12px">Volgende levering (optioneel)</h3>
      <div class="rij2">
        <FormField label="Datum" for="l-datum" :fout="fouten.volgendeDatum">
          <input id="l-datum" v-model="form.volgendeDatum" name="volgendeDatum" type="date">
        </FormField>
        <FormField label="Tijd" for="l-tijd" :fout="fouten.volgendeTijd">
          <input id="l-tijd" v-model="form.volgendeTijd" name="volgendeTijd" type="time">
        </FormField>
      </div>
    </form>
    <template #voet>
      <button class="knop" type="button" @click="emit('sluit')">Annuleren</button>
      <button class="knop primair" type="button" @click="opslaan">Opslaan</button>
    </template>
  </SideDrawer>
</template>
