<script setup>
/**
 * Kolom 2: beschikbare voorraad, per categorie inklapbaar (US-PAK-03, US-VRD-08).
 * Standaard zie je alleen wat bij het gekozen gezin past.
 */
import { computed, reactive, ref } from 'vue';
import SearchInput from '@/components/ui/SearchInput.vue';
import AppIcon from '@/components/ui/AppIcon.vue';

const props = defineProps({ sp: { type: Object, required: true } });

const zoek = ref('');
const alleenPassend = ref(true);
const dicht = reactive(new Set());

const lijst = computed(() => {
  const t = zoek.value.trim().toLowerCase();
  const sp = props.sp;
  return sp.start.value.producten
    .filter((p) => p.voorraad > 0)
    .filter((p) => !t || `${p.naam} ${p.categorie}`.toLowerCase().includes(t))
    .filter((p) => !(sp.gekozen.value && p.verlopen))
    .filter((p) => !(alleenPassend.value && sp.gekozen.value && sp.botst(p).length));
});

const groepen = computed(() => {
  const g = new Map();
  lijst.value.forEach((p) => {
    if (!g.has(p.categorie)) g.set(p.categorie, []);
    g.get(p.categorie).push(p);
  });
  return [...g.entries()].sort((a, b) => a[0].localeCompare(b[0], 'nl'));
});

// tijdens het zoeken alles open, anders zie je alleen kopjes
const isOpen = (naam) => !!zoek.value || !dicht.has(naam);
const wissel = (naam) => (dicht.has(naam) ? dicht.delete(naam) : dicht.add(naam));
function allesWisselen() {
  if (dicht.size) dicht.clear();
  else groepen.value.forEach(([naam]) => dicht.add(naam));
}

function over(p) {
  return p.voorraad - (props.sp.mandje.get(p.id) || 0);
}

function labels(p) {
  const uit = props.sp.botst(p).map((w) => ({
    kleur: w.streng ? 'nee' : 'wacht',
    tekst: `${w.streng ? 'Allergie: ' : ''}${w.wens} (${w.personen.length}×)${w.uitzondering ? ' · mag toch' : ''}`,
    titel: `${w.personen.length} van de ${w.gezinsgrootte}: ${w.personen.join(', ')}${w.uitzondering ? ' — ' + w.toelichting : ''}`,
  }));
  if (p.verlopen) uit.unshift({ kleur: 'nee', tekst: 'Over de datum' });
  else if (p.thtDagen !== null && p.thtDagen <= 3) {
    uit.push({ kleur: 'wacht', tekst: `THT over ${p.thtDagen} dag${p.thtDagen === 1 ? '' : 'en'}` });
  }
  return uit;
}

function knopTitel(p) {
  const slot = props.sp.geblokkeerd(p);
  if (slot === 'verlopen') return 'Dit product is over de houdbaarheidsdatum';
  if (slot === 'allergie') return 'Dit product botst met een ernstige allergie in dit gezin';
  return over(p) <= 0 ? 'Niet meer op voorraad' : 'Toevoegen aan het pakket';
}
</script>

<template>
  <div class="card">
    <div class="kop">
      <h2>Beschikbare magazijnvoorraad</h2>
      <span class="tel rechts">{{ lijst.length }} producten in {{ groepen.length }} categorieën</span>
    </div>
    <div class="body" style="padding:14px 20px">
      <div class="zoekbalk" style="margin:0 0 11px"><SearchInput v-model="zoek" placeholder="Zoek product of categorie" /></div>
      <div class="horizontaal" style="gap:12px;flex-wrap:wrap">
        <label class="vinkje"><input v-model="alleenPassend" type="checkbox"> Gefilterd op compatibiliteit met dit gezin</label>
        <button class="knop klein rechts" @click="allesWisselen">{{ dicht.size ? 'Alles uitklappen' : 'Alles inklappen' }}</button>
      </div>
    </div>

    <div class="scroll">
      <div v-for="[naam, items] in groepen" :key="naam" class="catgroep">
        <button class="catkop" :aria-expanded="isOpen(naam)" @click="wissel(naam)">
          <AppIcon class="chev" naam="chevron" :grootte="13" :dikte="2.4" :style="{ transform: `rotate(${isOpen(naam) ? 0 : -90}deg)` }" />
          <span>{{ naam }}</span>
          <span class="tel">{{ items.length }}</span>
        </button>
        <template v-if="isOpen(naam)">
          <div v-for="p in items" :key="p.id" class="pitem" :class="{ bots: sp.geblokkeerd(p) }">
            <div style="flex:1;min-width:0">
              <div class="nm">{{ p.naam }}</div>
              <div class="sub">{{ p.locatie || p.categorie }}</div>
              <div v-if="labels(p).length" class="pillen" style="margin-top:5px">
                <span v-for="(l, i) in labels(p)" :key="i" class="pil2" :class="l.kleur" :title="l.titel">{{ l.tekst }}</span>
              </div>
            </div>
            <div style="text-align:right">
              <div class="mono" style="font-size:13.5px" :style="over(p) <= 0 ? 'color:var(--red)' : ''">{{ over(p) }} st.</div>
              <button class="knop klein" style="margin-top:5px" :data-voeg="p.id" :title="knopTitel(p)"
                      :disabled="!!sp.geblokkeerd(p) || over(p) <= 0" @click="sp.voegToe(p.id)">
                {{ sp.geblokkeerd(p) ? 'Mag niet' : '+ Voeg toe' }}
              </button>
            </div>
          </div>
        </template>
      </div>
      <div v-if="!lijst.length" class="leeg">Geen producten gevonden.</div>
    </div>
  </div>
</template>

<style scoped>
.vinkje { display: flex; gap: 8px; align-items: center; font-size: 13.5px; color: var(--ink-2); cursor: pointer; }
.scroll { max-height: 620px; overflow: auto; border-top: 1px solid var(--line); }
.catkop { display: flex; align-items: center; gap: 9px; width: 100%; text-align: left; padding: 10px 20px; border: 0;
          background: var(--row-alt); border-bottom: 1px solid var(--line); cursor: pointer; font: inherit;
          font-size: 13px; font-weight: 700; color: var(--ink-2); }
.catkop:hover { background: var(--grey-soft); }
.catkop .chev { transition: transform .16s ease; flex: 0 0 13px; color: var(--ink-3); }
.catkop .tel { margin-left: auto; font-family: 'IBM Plex Mono', monospace; font-weight: 500; color: var(--ink-3); }
.pitem { display: flex; gap: 11px; align-items: center; padding: 11px 20px; border-bottom: 1px solid var(--line); }
.pitem .nm { font-weight: 600; font-size: 14.5px; }
.pitem .sub { font-size: 12.5px; color: var(--ink-3); }
.pitem.bots { background: var(--red-soft); }
</style>
