<script setup>
/**
 * Toont de vraag die via useDialog() is gesteld: een bevestiging (ja/nee)
 * of een klein formulier. Staat één keer in App.vue.
 */
import { nextTick, ref, watch } from 'vue';
import { useDialog } from '@/composables/useDialog';
import BaseModal from './BaseModal.vue';

const dialog = useDialog();
const h = dialog.huidige;
const jaKnop = ref(null);
const vorm = ref(null);

function annuleer() {
  dialog.sluit(h.soort === 'bevestig' ? false : null);
}
function bevestig() {
  dialog.sluit(h.soort === 'bevestig' ? true : { ...h.waarden });
}

// focus: bij een formulier op het eerste veld, anders op de ja-knop
watch(() => h.open, async (open) => {
  if (!open) return;
  await nextTick();
  const veld = vorm.value?.querySelector('input, select');
  if (veld) { veld.focus(); veld.select?.(); } else { jaKnop.value?.focus(); }
});
</script>

<template>
  <BaseModal :open="h.open" :titel="h.opties.titel" @sluit="annuleer">
    <p v-if="h.opties.tekst" class="tekst">{{ h.opties.tekst }}</p>
    <ul v-if="h.opties.punten?.length" class="punten">
      <li v-for="(p, i) in h.opties.punten" :key="i">{{ p }}</li>
    </ul>
    <div v-if="h.opties.notitie" class="melding grijs"><div>{{ h.opties.notitie }}</div></div>

    <form v-if="h.soort === 'formulier'" ref="vorm" @submit.prevent="bevestig">
      <div v-for="v in h.opties.velden" :key="v.naam" class="veld">
        <label :for="'dlg-' + v.naam">{{ v.label }}</label>
        <select v-if="v.type === 'select'" :id="'dlg-' + v.naam" v-model="h.waarden[v.naam]" :name="v.naam">
          <option v-for="o in v.opties" :key="o.waarde" :value="o.waarde">{{ o.label }}</option>
        </select>
        <input v-else :id="'dlg-' + v.naam" v-model="h.waarden[v.naam]" :name="v.naam" :type="v.type || 'text'"
               :min="v.min" :step="v.stap">
        <div v-if="v.hint" class="hint">{{ v.hint }}</div>
      </div>
    </form>

    <template #voet>
      <button v-if="!h.opties.alleenJa" class="knop" type="button" data-nee @click="annuleer">{{ h.opties.nee }}</button>
      <button ref="jaKnop" class="knop" :class="h.opties.gevaar ? 'gevaar' : 'primair'" type="button" data-ja
              @click="bevestig">{{ h.opties.ja }}</button>
    </template>
  </BaseModal>
</template>

<style scoped>
.tekst { margin: 0 0 12px; }
.punten { margin: 0 0 12px; padding-left: 20px; }
.punten li { margin-bottom: 4px; }
</style>
