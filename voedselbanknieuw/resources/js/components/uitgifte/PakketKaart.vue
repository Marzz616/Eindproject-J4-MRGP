<script setup>
/** Het gekozen pakket aan de balie: cliëntgegevens, legitimatie, inhoud en de acties. */
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { datumKort } from '@/utils/format';

const props = defineProps({ kaart: { type: Object, default: null } });
const emit = defineEmits(['legitimatie', 'uitgeven', 'niet-opgehaald']);
const router = useRouter();

const totaal = computed(() => (props.kaart?.inhoud || []).reduce((s, r) => s + r.aantal, 0));
const uitgegeven = computed(() => props.kaart?.status === 'Uitgegeven');

function pakbon() {
  window.open(router.resolve({ name: 'pakbon', params: { id: props.kaart.pakketId } }).href, '_blank');
}
</script>

<template>
  <div v-if="!kaart" class="card"><div class="leeg">
    <h3>Nog geen cliënt gekozen</h3>
    <div>Zoek hierboven een gezin, of klik rechts op een pakket uit de wachtrij.</div>
  </div></div>

  <div v-else-if="kaart.geenPakket" class="card">
    <div class="kop"><h2>Cliëntidentificatie</h2></div>
    <div class="body">
      <div class="naam">{{ kaart.klant.naam }}</div>
      <div class="mono" style="color:var(--ink-2)">{{ kaart.klant.nummer }} · {{ kaart.klant.adres }}</div>
      <div class="melding amber" style="margin-top:16px"><div>
        <b>Er staat deze week geen pakket klaar voor dit gezin.</b><br>
        Laat het pakket eerst samenstellen op de pagina Voedselpakketten.
      </div></div>
      <RouterLink class="knop" to="/pakketten">Naar Voedselpakketten</RouterLink>
    </div>
  </div>

  <template v-else>
    <div class="card" style="margin-bottom:18px">
      <div class="kop"><h2>Cliëntidentificatie</h2>
        <span class="rechts">
          <span v-if="uitgegeven" class="pil2 off">Al uitgegeven</span>
          <span v-else-if="kaart.status === 'Concept'" class="pil2 wacht">Nog concept</span>
          <span v-else class="pil2 ok">Klaar voor uitgifte</span>
        </span>
      </div>
      <div class="body">
        <div class="naam">{{ kaart.klant.naam }}</div>
        <div class="mono" style="color:var(--ink-2)">{{ kaart.klant.nummer }} · {{ kaart.klant.adres }}</div>
        <div class="gegeven">
          <div><div class="lab">Huishoudengrootte</div>
            <div class="wrd">{{ kaart.klant.gezinsgrootte }} {{ kaart.klant.gezinsgrootte === 1 ? 'persoon' : 'personen' }}</div></div>
          <div><div class="lab">Dieetrestrictie</div><div class="wrd">{{ kaart.wensen.length ? kaart.wensen.join(', ') : 'Geen' }}</div></div>
          <div><div class="lab">Identificatiestatus</div>
            <div class="wrd" :style="{ color: kaart.gecontroleerd ? 'var(--green)' : 'var(--amber)' }">
              {{ kaart.gecontroleerd ? 'Geïdentificeerd met: ' + kaart.legitimatie : 'Nog niet gecontroleerd' }}
            </div></div>
        </div>
        <button v-if="!uitgegeven" class="knop" style="margin-top:16px" @click="emit('legitimatie')">
          {{ kaart.gecontroleerd ? 'Legitimatie wijzigen' : 'Legitimatie vastleggen' }}
        </button>
      </div>
    </div>

    <div class="card">
      <div class="kop"><h2>Gereedstaand voedselpakket</h2><span class="tel rechts">{{ totaal }} stuks</span></div>
      <div v-for="(r, i) in kaart.inhoud" :key="i" class="regel">
        <div class="mono aantal">{{ r.aantal }}×</div>
        <div style="flex:1"><div style="font-weight:600">{{ r.naam }}</div>
          <div style="font-size:12.5px;color:var(--ink-3)">{{ r.categorie }}</div></div>
      </div>
      <div class="body" style="border-top:1px solid var(--line)">
        <template v-if="uitgegeven">
          <div class="melding groen"><div>Dit pakket is uitgegeven op {{ datumKort(kaart.uitgifte) }}.</div></div>
          <button class="knop" @click="pakbon">Pakbon bekijken</button>
        </template>
        <template v-else>
          <p class="uitleg">Door op de onderstaande knop te klikken bevestigt u dat het pakket fysiek is overhandigd aan de
            gemachtigde vertegenwoordiger van het gezin.</p>
          <div class="horizontaal" style="flex-wrap:wrap">
            <button class="knop primair groot" @click="emit('uitgeven')">Pakket uitgegeven bevestigen</button>
            <button class="knop" @click="pakbon">Pakbon afdrukken</button>
            <button class="knop gevaar" @click="emit('niet-opgehaald')">Niet opgehaald</button>
          </div>
        </template>
      </div>
    </div>
  </template>
</template>

<style scoped>
.naam { font-family: 'Zilla Slab', serif; font-size: 21px; font-weight: 700; }
.gegeven { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-top: 16px; }
.gegeven > div { background: var(--row-alt); border: 1px solid var(--line); border-radius: 9px; padding: 12px 14px; }
.lab { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); font-weight: 700; }
.wrd { font-weight: 600; margin-top: 3px; }
.regel { display: flex; gap: 11px; align-items: center; padding: 12px 20px; border-bottom: 1px solid var(--line); }
.aantal { width: 38px; font-weight: 500; }
.uitleg { font-size: 13.5px; color: var(--ink-2); margin: 0 0 14px; }
@media (max-width: 820px) { .gegeven { grid-template-columns: 1fr; } }
</style>
