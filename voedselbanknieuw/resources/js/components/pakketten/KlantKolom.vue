<script setup>
/** Kolom 1: gezinnen van deze week en het profiel van het gekozen gezin (US-PAK-02/03/04/06). */
import { computed, ref } from 'vue';
import SearchInput from '@/components/ui/SearchInput.vue';

const props = defineProps({
  klanten: { type: Array, required: true },
  gekozen: { type: Object, default: null },
});
const emit = defineEmits(['kies']);
const zoek = ref('');

const filter = (lijst) => lijst.filter((k) => !zoek.value || k.naam.toLowerCase().includes(zoek.value.trim().toLowerCase()));
const nodig = computed(() => props.klanten.filter((k) => !k.dezeWeek));
const klaar = computed(() => props.klanten.filter((k) => k.dezeWeek));

function weekPil(k) {
  if (!k.dezeWeek) return ['ok', 'Pakket nodig'];
  if (k.weekStatus === 'Uitgegeven') return ['off', 'Uitgegeven'];
  if (k.weekStatus === 'Klaar') return ['wacht', 'Staat klaar'];
  return ['wacht', 'Concept'];
}
</script>

<template>
  <div class="card">
    <div class="kop"><h2>Gezinsprofiel</h2></div>
    <div class="body" style="padding:14px 20px">
      <div class="uitleg">{{ nodig.length }} {{ nodig.length === 1 ? 'gezin wacht' : 'gezinnen wachten' }} nog op een pakket deze week.</div>
      <div class="zoekbalk" style="margin:0"><SearchInput v-model="zoek" placeholder="Zoek klant op naam" /></div>
    </div>

    <div class="scroll">
      <div class="kgroep">Pakket nodig ({{ filter(nodig).length }})</div>
      <button v-for="k in filter(nodig)" :key="k.id" class="kitem" :class="{ aan: gekozen?.klant.id === k.id }"
              :data-klant="k.id" @click="emit('kies', k.id)">
        <div style="flex:1">
          <div class="nm">{{ k.naam }}</div>
          <div class="sub">{{ k.gezinsgrootte }} {{ k.gezinsgrootte === 1 ? 'persoon' : 'personen' }} · advies {{ k.advies }} producten</div>
        </div>
        <span class="pil2" :class="weekPil(k)[0]">{{ weekPil(k)[1] }}</span>
      </button>
      <div v-if="!filter(nodig).length" class="kitem leeg-item">Alle gezinnen hebben al een pakket.</div>

      <div class="kgroep">Al een pakket deze week ({{ filter(klaar).length }})</div>
      <button v-for="k in filter(klaar)" :key="k.id" class="kitem klaar" :class="{ aan: gekozen?.klant.id === k.id }"
              :data-klant="k.id" @click="emit('kies', k.id)">
        <div style="flex:1">
          <div class="nm">{{ k.naam }}</div>
          <div class="sub">{{ k.gezinsgrootte }} {{ k.gezinsgrootte === 1 ? 'persoon' : 'personen' }} · advies {{ k.advies }} producten</div>
        </div>
        <span class="pil2" :class="weekPil(k)[0]">{{ weekPil(k)[1] }}</span>
      </button>
      <div v-if="!filter(klaar).length" class="kitem leeg-item">Nog niemand.</div>
    </div>

    <div v-if="gekozen" class="profiel">
      <h3>{{ gekozen.klant.naam }}</h3>
      <div v-if="gekozen.klant.opmerkingen" class="melding grijs" style="margin-bottom:12px"><div>
        <b>Voorkeuren van dit gezin:</b> {{ gekozen.klant.opmerkingen }}
      </div></div>

      <template v-if="gekozen.wensen.length">
        <div class="pillen" style="margin-bottom:12px">
          <span v-for="w in gekozen.wensen" :key="w.tag" class="pil2" :class="w.streng ? 'nee' : 'diet'" :title="w.personen.join(', ')">
            {{ w.streng ? 'Allergie: ' : '' }}{{ w.wens }}
            <span class="teller">&nbsp;{{ w.personen.length }}/{{ w.gezinsgrootte }}</span>
          </span>
        </div>
        <div v-if="gekozen.wensen.some((w) => w.streng)" class="streng-uitleg">
          Producten met een ernstige allergie kun je niet toevoegen. De overige wensen mogen wel, maar vragen om een bevestiging.
        </div>
        <div v-for="w in gekozen.wensen.filter((x) => x.uitzondering)" :key="'u' + w.tag" class="melding amber" style="margin:-4px 0 12px">
          <div><b>Uitzondering op {{ w.wens }}:</b> {{ w.toelichting }}</div>
        </div>
      </template>
      <div v-else class="geen">Geen dieetbeperkingen.</div>

      <div class="inner" style="--subkolommen:1.6fr 1.2fr .8fr">
        <div class="ihead"><span>Naam</span><span>Relatie</span><span>Leeftijd</span></div>
        <div v-for="l in gekozen.gezin" :key="l.id" class="irow">
          <div class="p">{{ l.naam }}</div>
          <div style="color:var(--ink-2)">{{ l.relatie }}</div>
          <div :title="l.type">{{ l.leeftijd }} jr</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.uitleg { font-size: 13.5px; color: var(--ink-2); margin-bottom: 11px; }
.scroll { max-height: 560px; overflow: auto; border-top: 1px solid var(--line); }
.kgroep { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; color: var(--ink-3); padding: 14px 20px 7px; }
.kitem { display: flex; gap: 11px; align-items: center; width: 100%; text-align: left; padding: 11px 20px; border: 0;
         background: none; font: inherit; cursor: pointer; border-bottom: 1px solid var(--line); }
.kitem:hover { background: var(--row-alt); }
.kitem.aan { background: var(--green-soft); }
.kitem .nm { font-weight: 600; font-size: 14.5px; }
.kitem .sub { font-size: 12.5px; color: var(--ink-3); }
.kitem.klaar { opacity: .66; }
.leeg-item { color: var(--ink-3); cursor: default; }
.profiel { border-top: 1px solid var(--line); padding: 16px 20px; }
.profiel h3 { font-size: 15px; margin-bottom: 10px; }
.teller { opacity: .75; font-weight: 400; }
.streng-uitleg { font-size: 12.5px; color: var(--red); margin: -6px 0 12px; }
.geen { font-size: 13px; color: var(--ink-3); margin-bottom: 12px; }
</style>
