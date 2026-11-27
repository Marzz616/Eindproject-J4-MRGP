<script setup>
/** Product toevoegen of bewerken (US-VRD-01/02/09/10). */
import { reactive, watch } from 'vue';
import { productenApi } from '@/services/api';
import { useFormErrors } from '@/composables/useFormErrors';
import SideDrawer from '@/components/ui/SideDrawer.vue';
import FormField from '@/components/ui/FormField.vue';

const props = defineProps({
  open: Boolean,
  product: { type: Object, default: null },
  categorieen: { type: Array, required: true },
});
const emit = defineEmits(['sluit', 'opgeslagen']);
const { fouten, verwerk, wis } = useFormErrors();

/** Kenmerken waarop de dieetcontrole bij het samenstellen let. */
const KENMERKEN = ['gluten', 'lactose', 'noten', 'pinda', 'schaaldier', 'varkensvlees', 'vlees', 'dierlijk'];

const form = reactive({ id: 0, naam: '', ean: '', categorie: 0, voorraad: 0, min: 0, waarde: 0, tht: '', locatie: '', allergenen: [] });

watch(() => props.open, (open) => {
  if (!open) return;
  wis();
  const p = props.product;
  Object.assign(form, {
    id: p?.id || 0, naam: p?.naam || '', ean: p?.ean || '', categorie: p?.categorie.id || props.categorieen[0]?.id || 0,
    voorraad: p?.voorraad ?? 0, min: p?.min ?? 0, waarde: p?.waarde ?? 0, tht: p?.tht || '', locatie: p?.locatie || '',
    allergenen: [...(p?.allergenen || [])],
  });
});

function wissel(tag) {
  const i = form.allergenen.indexOf(tag);
  if (i >= 0) form.allergenen.splice(i, 1); else form.allergenen.push(tag);
}

async function opslaan() {
  try {
    const r = await productenApi.opslaan({
      ...form,
      naam: form.naam.trim(), ean: String(form.ean).trim(), locatie: form.locatie.trim(),
      categorie: Number(form.categorie), voorraad: Number(form.voorraad), min: Number(form.min), waarde: Number(form.waarde),
      allergenen: form.allergenen.join(','),
    });
    emit('opgeslagen', r.melding || 'Product opgeslagen.');
  } catch (e) { verwerk(e); }
}
</script>

<template>
  <SideDrawer :open="open" :titel="product ? 'Product bewerken' : 'Nieuw product'"
              :onder="product ? product.naam : 'Voeg een product toe aan het magazijn'" @sluit="emit('sluit')">
    <form novalidate @submit.prevent="opslaan">
      <FormField label="Productnaam" for="p-naam" :fout="fouten.naam">
        <input id="p-naam" v-model="form.naam" name="naam" type="text">
      </FormField>
      <div class="rij2">
        <FormField label="Streepjescode (EAN, 13 cijfers)" for="p-ean" :fout="fouten.ean">
          <input id="p-ean" v-model="form.ean" name="ean" type="text" inputmode="numeric" maxlength="13" class="mono">
        </FormField>
        <FormField label="Categorie" for="p-cat" :fout="fouten.categorie">
          <select id="p-cat" v-model="form.categorie" name="categorie">
            <option v-for="c in categorieen" :key="c.id" :value="c.id">{{ c.naam }}</option>
          </select>
        </FormField>
      </div>
      <div class="rij3">
        <FormField label="Voorraad" for="p-vrd" :fout="fouten.voorraad">
          <input id="p-vrd" v-model="form.voorraad" name="voorraad" type="number" min="0">
        </FormField>
        <FormField label="Minimumvoorraad" for="p-min" :fout="fouten.min">
          <input id="p-min" v-model="form.min" name="min" type="number" min="0">
        </FormField>
        <FormField label="Winkelwaarde (€)" for="p-waarde" :fout="fouten.waarde">
          <input id="p-waarde" v-model="form.waarde" name="waarde" type="number" min="0" step="0.01">
        </FormField>
      </div>
      <div class="rij2">
        <FormField label="Houdbaar tot (THT)" for="p-tht" hint="Leeg laten bij producten zonder THT." :fout="fouten.tht">
          <input id="p-tht" v-model="form.tht" name="tht" type="date">
        </FormField>
        <FormField label="Magazijnlocatie" for="p-loc" :fout="fouten.locatie">
          <input id="p-loc" v-model="form.locatie" name="locatie" type="text" placeholder="Vak C-4">
        </FormField>
      </div>
      <FormField label="Allergenen / dieetkenmerken" hint="Hiermee waarschuwt de applicatie bij het samenstellen van een pakket.">
        <div class="chips">
          <button v-for="t in KENMERKEN" :key="t" type="button" class="chip" :class="{ aan: form.allergenen.includes(t) }"
                  :data-tag="t" @click="wissel(t)">{{ t }}</button>
        </div>
      </FormField>
      <div class="melding grijs"><div>De voedselbank neemt geen diepvriesproducten en geen alcohol aan. Voer die dus niet in.</div></div>
    </form>
    <template #voet>
      <button class="knop" type="button" @click="emit('sluit')">Annuleren</button>
      <button class="knop primair" type="button" @click="opslaan">Opslaan</button>
    </template>
  </SideDrawer>
</template>
