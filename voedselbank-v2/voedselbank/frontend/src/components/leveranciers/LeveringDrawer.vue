<script setup>
/** Levering inboeken; de voorraad gaat automatisch omhoog (US-LEV-04/05). */
import { reactive, ref, watch } from 'vue';
import { leveranciersApi } from '@/services/api';
import { useFormErrors } from '@/composables/useFormErrors';
import { vandaagIso } from '@/utils/format';
import SideDrawer from '@/components/ui/SideDrawer.vue';
import FormField from '@/components/ui/FormField.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

const props = defineProps({
  open: Boolean,
  leverancier: { type: Object, default: null },
  producten: { type: Array, required: true },
});
const emit = defineEmits(['sluit', 'opgeslagen']);
const { fouten, verwerk, wis } = useFormErrors();

const form = reactive({ datum: '', tijd: '' });
const regels = ref([]);

const nieuweRegel = () => ({ sleutel: Math.random(), product: props.producten[0]?.id || 0, aantal: 1 });

watch(() => props.open, (open) => {
  if (!open) return;
  wis();
  form.datum = vandaagIso();
  form.tijd = '';
  regels.value = [nieuweRegel()];
});

async function boeken() {
  try {
    const r = await leveranciersApi.boekLevering({
      leverancier: props.leverancier.id,
      datum: form.datum,
      tijd: form.tijd,
      regels: regels.value.map((x) => ({ product: Number(x.product), aantal: Number(x.aantal) }))
        .filter((x) => x.product > 0 && x.aantal > 0),
    });
    emit('opgeslagen', r.melding);
  } catch (e) { verwerk(e); }
}
</script>

<template>
  <SideDrawer :open="open" titel="Levering inboeken" :onder="leverancier?.bedrijfsnaam || ''" @sluit="emit('sluit')">
    <form novalidate @submit.prevent="boeken">
      <div class="rij2">
        <FormField label="Leverdatum" for="lv-datum" :fout="fouten.datum">
          <input id="lv-datum" v-model="form.datum" name="datum" type="date">
        </FormField>
        <FormField label="Tijd" for="lv-tijd">
          <input id="lv-tijd" v-model="form.tijd" name="tijd" type="time">
        </FormField>
      </div>

      <h3 style="font-size:15px;margin:18px 0 12px">Wat is er geleverd?</h3>
      <div v-if="fouten.regels" class="melding rood"><div>{{ fouten.regels }}</div></div>
      <div v-for="(r, i) in regels" :key="r.sleutel" class="regel">
        <div class="veld"><select v-model="r.product" aria-label="Product" data-v="product">
          <option v-for="p in producten" :key="p.id" :value="p.id">{{ p.naam }} ({{ p.voorraad }} st.)</option>
        </select></div>
        <div class="veld"><input v-model="r.aantal" type="number" min="1" aria-label="Aantal" data-v="aantal"></div>
        <button type="button" class="icoonknop" title="Regel verwijderen" @click="regels.splice(i, 1)">
          <AppIcon naam="sluiten" :dikte="1.8" />
        </button>
      </div>
      <button type="button" class="knop klein" @click="regels.push(nieuweRegel())">+ Product toevoegen</button>
      <div class="hint">De voorraad van deze producten wordt automatisch opgehoogd.</div>
    </form>
    <template #voet>
      <button class="knop" type="button" @click="emit('sluit')">Annuleren</button>
      <button class="knop primair" type="button" @click="boeken">Levering boeken</button>
    </template>
  </SideDrawer>
</template>

<style scoped>
.regel { display: grid; grid-template-columns: 2.4fr .8fr auto; gap: 10px; align-items: start; margin-bottom: 10px; }
.regel .veld { margin-bottom: 0; }
.hint { font-size: 12px; color: var(--ink-3); margin-top: 8px; }
</style>
