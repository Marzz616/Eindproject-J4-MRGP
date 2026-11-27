<script setup>
/** De pakketten van deze week, per status (US-WRK-02). Klik opent het pakket links. */
import { datumKort } from '@/utils/format';

defineProps({ wachtrij: { type: Object, required: true } });
const emit = defineEmits(['open']);
</script>

<template>
  <div>
    <div v-for="blok in [
      { titel: 'Klaar voor uitgifte', lijst: wachtrij.klaar, leeg: 'Er staat niets klaar.' },
      { titel: 'Nog in concept', lijst: wachtrij.concept, leeg: 'Geen concepten.' },
      { titel: 'Deze week al uitgegeven', lijst: wachtrij.uitgegeven, leeg: 'Nog niets uitgegeven deze week.' },
    ]" :key="blok.titel" class="card" style="margin-bottom:18px">
      <div class="kop"><h2>{{ blok.titel }}</h2><span class="tel rechts">{{ blok.lijst.length }}</span></div>
      <button v-for="p in blok.lijst" :key="p.pakketId" class="rij" :data-pakket="p.pakketId" @click="emit('open', p.pakketId)">
        <div style="flex:1">
          <div style="font-weight:600">{{ p.klant }}</div>
          <div class="sub">{{ p.nummer }} · {{ p.gezinsgrootte }} pers. · {{ p.aantal }} stuks</div>
        </div>
        <span v-if="p.status === 'Uitgegeven'" class="pil2 off">{{ datumKort(p.uitgifte) }}</span>
        <span v-else-if="p.gecontroleerd" class="pil2 ok">Gelegitimeerd</span>
        <span v-else class="pil2 wacht">Wacht</span>
      </button>
      <div v-if="!blok.lijst.length" class="leeg" style="padding:26px">{{ blok.leeg }}</div>
    </div>
  </div>
</template>

<style scoped>
.rij { display: flex; gap: 11px; align-items: center; width: 100%; padding: 12px 20px; border: 0; border-bottom: 1px solid var(--line);
       background: none; font: inherit; text-align: left; cursor: pointer; }
.rij:hover { background: var(--row-alt); }
.sub { font-size: 12.5px; color: var(--ink-3); }
</style>
