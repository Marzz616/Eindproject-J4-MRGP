<script setup>
/**
 * Eén gezinslid in het klantformulier: naam, relatie, geboortedatum, dieetwensen
 * en per gekozen wens een optionele uitzondering mét reden.
 * Het lid-object wordt direct aangepast (het hoort bij het formulier van de ouder).
 */
import { computed, watch } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';

const props = defineProps({
  lid: { type: Object, required: true },
  wensen: { type: Array, required: true },
});
const emit = defineEmits(['verwijder']);

const RELATIES = ['Hoofdaanvrager', 'Partner', 'Ouder (Moeder)', 'Ouder (Vader)', 'Kind', 'Inwonend'];

const gekozen = computed(() => props.lid.wensen.map((id) => props.wensen.find((w) => w.id === id) || { id, naam: 'Wens ' + id }));

// voor elke gekozen wens een uitzondering-object klaarzetten (vóór het tekenen)
watch(() => [...props.lid.wensen], (ids) => {
  ids.forEach((id) => {
    props.lid.uitzonderingen[id] ??= { aan: false, reden: '' };
  });
}, { immediate: true });

const u = (id) => props.lid.uitzonderingen[id] || { aan: false, reden: '' };

function wissel(id, aan) {
  props.lid.uitzonderingen[id].aan = aan;
  if (!aan) props.lid.uitzonderingen[id].reden = '';
}
</script>

<template>
  <div class="lid">
    <div class="veld"><input v-model="lid.naam" type="text" placeholder="Naam" aria-label="Naam gezinslid" data-v="naam"></div>
    <div class="veld">
      <select v-model="lid.relatie" aria-label="Relatie" data-v="relatie">
        <option v-for="r in RELATIES" :key="r">{{ r }}</option>
      </select>
    </div>
    <div class="veld"><input v-model="lid.gb" type="date" aria-label="Geboortedatum" data-v="gb"></div>
    <div class="veld">
      <select v-model="lid.wensen" multiple size="3" class="meer" aria-label="Dieetwensen" data-v="wensen">
        <option v-for="w in wensen" :key="w.id" :value="w.id">{{ w.streng ? '⚠ ' : '' }}{{ w.naam }}</option>
      </select>
    </div>
    <button type="button" class="icoonknop weg" title="Gezinslid verwijderen" @click="emit('verwijder')">
      <AppIcon naam="sluiten" :dikte="1.8" />
    </button>

    <div v-if="gekozen.length" class="uitz">
      <div v-for="w in gekozen" :key="w.id" class="uitzrij"
           :class="{ mis: u(w.id).aan && !u(w.id).reden.trim() }">
        <label>
          <input type="checkbox" :checked="u(w.id).aan" @change="wissel(w.id, $event.target.checked)">
          <span><b>{{ w.naam }}</b> — het gezin wil dit tóch ontvangen
            <span v-if="w.streng" class="streng">(heft de blokkade op)</span></span>
        </label>
        <input v-if="lid.uitzonderingen[w.id]" v-model="lid.uitzonderingen[w.id].reden" type="text" maxlength="255" :disabled="!u(w.id).aan"
               placeholder="Reden, bijvoorbeeld: alleen vader eet het niet">
      </div>
    </div>
  </div>
</template>

<style scoped>
.lid { display: grid; grid-template-columns: 1.35fr 1.05fr .85fr 2.4fr auto; gap: 10px; align-items: start;
       margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed var(--line); }
.lid .veld { margin-bottom: 0; }
.lid .weg { margin-top: 4px; }
.meer { height: auto !important; padding: 6px 8px !important; }
.uitz { grid-column: 1 / -1; display: grid; gap: 6px; }
.uitzrij { background: var(--side); border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; }
.uitzrij label { display: flex; align-items: center; gap: 7px; font-size: 13.5px; cursor: pointer; margin: 0; }
.uitzrij input[type=checkbox] { width: 15px; height: 15px; accent-color: var(--green); margin: 0; }
.uitzrij input[type=text] { margin-top: 7px; width: 100%; height: 34px; font-size: 13.5px;
                            border: 1px solid var(--line-2); border-radius: 7px; padding: 0 9px; font-family: inherit; }
.uitzrij input[type=text][disabled] { background: #F4F3EE; color: var(--ink-3); }
.uitzrij.mis input[type=text] { border-color: var(--red); }
.streng { color: var(--red); font-weight: 600; }
@media (max-width: 820px) { .lid { grid-template-columns: 1fr 1fr; } }
</style>
