<script setup>
/** Pakketgeschiedenis van één gezin (US-KLT-13/14). */
import { ref, watch } from 'vue';
import { klantenApi } from '@/services/api';
import { meldFout } from '@/composables/useFormErrors';
import { datumKort } from '@/utils/format';
import BaseModal from '@/components/ui/BaseModal.vue';

const props = defineProps({ klant: { type: Object, default: null } });
const emit = defineEmits(['sluit']);
const lijst = ref(null);

watch(() => props.klant, async (k) => {
  lijst.value = null;
  if (!k) return;
  try { lijst.value = await klantenApi.pakketten(k.id); } catch (e) { meldFout(e); emit('sluit'); }
});
</script>

<template>
  <BaseModal :open="!!klant && lijst !== null" :titel="'Pakketgeschiedenis · ' + (klant?.naam || '')" breed @sluit="emit('sluit')">
    <div v-if="lijst?.length" class="inner" style="--subkolommen:1fr 1.2fr 2.4fr;max-height:50vh;overflow:auto">
      <div class="ihead"><span>Datum</span><span>Status</span><span>Inhoud</span></div>
      <div v-for="p in lijst" :key="p.id" class="irow">
        <div class="p">{{ datumKort(p.uitgifte || p.samen) }}</div>
        <div>{{ p.status }}</div>
        <div style="font-size:13px;color:var(--ink-2)">
          {{ p.regels.length ? p.regels.map((r) => `${r.aantal}× ${r.product}`).join(', ') : '—' }}
        </div>
      </div>
    </div>
    <p v-else>Dit gezin heeft nog geen pakket ontvangen.</p>
    <template #voet><button class="knop primair" @click="emit('sluit')">Sluiten</button></template>
  </BaseModal>
</template>
