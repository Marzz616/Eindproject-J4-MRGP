<script setup>
/** Kolom 3: lopende pakketinhoud met opslaan, klaarzetten, verwijderen en pakbon (US-PAK-04/05/10). */
import { computed } from 'vue';
import AppIcon from '@/components/ui/AppIcon.vue';

const props = defineProps({ sp: { type: Object, required: true } });
const emit = defineEmits(['opslaan', 'verwijder', 'pakbon']);

const advies = computed(() => props.sp.gekozen.value?.advies || 0);
const voortgang = computed(() => Math.min(100, advies.value ? (props.sp.totaal.value / advies.value) * 100 : 0));
// producten die met een (niet-strenge) wens botsen: waarschuwing tonen
const waarschuwing = computed(() => props.sp.regels.value.filter((r) => props.sp.botst(r.p).length));
</script>

<template>
  <div class="card">
    <div class="kop"><h2>Lopend overzicht pakketinhoud</h2></div>
    <div class="body" style="padding:14px 20px">
      <template v-if="sp.gekozen.value">
        <div style="font-weight:600;margin-bottom:8px">{{ sp.totaal.value }} van {{ advies }} items toegevoegd</div>
        <div class="balk" :class="{ amber: sp.totaal.value < advies }"><span :style="{ width: voortgang + '%' }" /></div>
      </template>
      <div v-else style="color:var(--ink-3);font-size:13.5px">Kies links een gezin om te beginnen.</div>

      <div v-if="waarschuwing.length" class="melding rood" style="margin:12px 0 0"><div>
        <b>Let op:</b> {{ waarschuwing.map((r) => r.p.naam).join(', ') }}
        {{ waarschuwing.length === 1 ? 'past' : 'passen' }} niet bij de wensen van dit gezin.
      </div></div>
    </div>

    <div class="scroll">
      <div v-for="r in sp.regels.value" :key="r.p.id" class="mandje">
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:14px">{{ r.p.naam }}</div>
          <div style="font-size:12.5px;color:var(--ink-3)">{{ r.p.categorie }}</div>
        </div>
        <div class="aantalknop">
          <button aria-label="Eén minder" @click="sp.wijzig(r.p.id, -1)">−</button>
          <span>{{ r.aantal }}</span>
          <button aria-label="Eén meer" @click="sp.wijzig(r.p.id, 1)">+</button>
        </div>
        <button class="icoonknop" title="Verwijderen" @click="sp.mandje.delete(r.p.id)">
          <AppIcon naam="sluiten" :grootte="16" :dikte="1.8" />
        </button>
      </div>
      <div v-if="!sp.regels.value.length" class="leeg" style="padding:30px 20px">Nog niets toegevoegd.</div>
    </div>

    <div v-if="sp.gekozen.value" class="acties">
      <button class="knop" :disabled="!sp.regels.value.length" @click="emit('opslaan', 'Concept')">Concept opslaan</button>
      <button class="knop primair" :disabled="!sp.regels.value.length" @click="emit('opslaan', 'Klaar')">Pakket klaarzetten</button>
      <template v-if="sp.pakketId.value">
        <button class="knop gevaar" @click="emit('verwijder')">Pakket verwijderen</button>
        <button class="knop" @click="emit('pakbon')">Pakbon afdrukken</button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.scroll { border-top: 1px solid var(--line); max-height: 480px; overflow: auto; }
.mandje { display: flex; gap: 10px; align-items: center; padding: 10px 20px; border-bottom: 1px solid var(--line); }
.mandje:last-child { border-bottom: 0; }
.aantalknop { display: flex; align-items: center; gap: 2px; }
.aantalknop button { width: 26px; height: 26px; border: 1px solid var(--line-2); background: #fff; border-radius: 6px;
                     cursor: pointer; font: inherit; line-height: 1; }
.aantalknop button:hover { background: var(--row-alt); }
.aantalknop span { min-width: 30px; text-align: center; font-family: 'IBM Plex Mono', monospace; font-weight: 500; }
.acties { padding: 16px 20px; border-top: 1px solid var(--line); display: flex; gap: 9px; flex-wrap: wrap; }
</style>
