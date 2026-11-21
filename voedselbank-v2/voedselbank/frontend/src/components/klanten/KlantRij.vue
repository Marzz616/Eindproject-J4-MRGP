<script setup>
/** Eén gezin in de klantenlijst, uitklapbaar met gezinsleden en acties. */
import { computed } from 'vue';
import { datumKort } from '@/utils/format';
import ExpandableRow from '@/components/ui/ExpandableRow.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

const props = defineProps({
  klant: { type: Object, required: true },
  rechten: { type: Object, required: true },
});
const emit = defineEmits(['bewerk', 'status', 'verwijder', 'geschiedenis']);
const open = defineModel('open', { type: Boolean, default: false });

const k = computed(() => props.klant);

const statusPil = computed(() => {
  if (k.value.status === null) return ['wacht', 'Wacht op beoordeling'];
  if (k.value.status === false) return ['nee', 'Afgewezen'];
  return ['ok', k.value.pakketDezeWeek ? 'Pakket deze week' : 'Actief'];
});

/** Alle wensen in het gezin, zonder dubbelen. */
const dieten = computed(() => [...new Set(k.value.leden.flatMap((l) => l.wensen.map((w) => w.naam)))]);
</script>

<template>
  <ExpandableRow v-model:open="open">
    <template #rij>
      <div><span class="nm">{{ k.naam }}</span><span class="code">{{ k.nummer }}</span></div>
      <div class="c-adres" style="color:var(--ink-2)">
        <template v-if="rechten.contact">{{ k.adres || '—' }}</template>
        <span v-else style="color:var(--ink-3)">Afgeschermd</span>
      </div>
      <div>{{ k.grootte }} {{ k.grootte === 1 ? 'persoon' : 'personen' }}</div>
      <div>
        <div v-if="dieten.length" class="pillen"><span v-for="w in dieten" :key="w" class="pil2 diet">{{ w }}</span></div>
        <span v-else style="color:var(--ink-3)">Geen</span>
      </div>
      <div class="c-laatste" style="color:var(--ink-2)">{{ datumKort(k.laatste) }}</div>
      <div><span class="pil2" :class="statusPil[0]">{{ statusPil[1] }}</span></div>
    </template>

    <h3>Samenstelling huishouden &amp; individuele behoeften</h3>
    <div v-if="rechten.contact" class="melding grijs" style="margin-bottom:14px"><div>
      <b>Contact:</b> {{ k.adres }}, {{ k.postcode }} {{ k.plaats }} · {{ k.telefoon }} · {{ k.email }} ·
      aangemeld {{ datumKort(k.aanmeld) }}
    </div></div>
    <div v-if="k.opmerkingen" class="melding grijs" style="margin-bottom:14px"><div>
      <b>Voorkeuren:</b> {{ k.opmerkingen }}
    </div></div>

    <div class="inner" style="--subkolommen: 2fr 1.3fr .9fr 1.8fr">
      <div class="ihead"><span>Naam lid</span><span>Relatie</span><span>Leeftijd</span><span>Specifiek dieet</span></div>
      <div v-for="l in k.leden" :key="l.id" class="irow">
        <div class="p">{{ l.naam }}</div>
        <div style="color:var(--ink-2)">{{ l.relatie }}</div>
        <div :title="l.type">{{ l.leeftijd }} jaar</div>
        <div>
          <template v-if="l.wensen.length">
            <span class="diet">{{ l.wensen.map((w) => w.naam).join(', ') }}</span>
            <div v-for="w in l.wensen.filter((x) => x.uitzondering)" :key="w.id" class="uitzondering">
              Uitzondering op {{ w.naam }}: {{ w.toelichting }}
            </div>
          </template>
          <span v-else class="niets">Geen</span>
        </div>
      </div>
      <div v-if="!k.leden.length" class="irow"><div class="niets">Nog geen gezinsleden ingevoerd.</div></div>
    </div>

    <div class="detailacts">
      <template v-if="rechten.beheren">
        <template v-if="k.status === null">
          <button class="knop klein primair" @click.stop="emit('status', true)">Aanvraag accepteren</button>
          <button class="knop klein gevaar" @click.stop="emit('status', false)">Afwijzen</button>
        </template>
        <button v-else-if="k.status === false" class="knop klein" @click.stop="emit('status', true)">Alsnog accepteren</button>
        <button v-else class="knop klein gevaar" @click.stop="emit('status', false)">Op non-actief zetten</button>
      </template>
      <button class="knop klein" @click.stop="emit('bewerk')">Gezin bewerken</button>
      <button class="knop klein" @click.stop="emit('geschiedenis')">Pakketgeschiedenis</button>
      <button v-if="rechten.beheren" class="knop klein gevaar rechts" @click.stop="emit('verwijder')">
        <AppIcon naam="prullenbak" :grootte="15" :dikte="1.8" /> Klant verwijderen
      </button>
    </div>
  </ExpandableRow>
</template>

<style scoped>
.uitzondering { font-size: 12.5px; color: var(--amber); margin-top: 3px; }
</style>
