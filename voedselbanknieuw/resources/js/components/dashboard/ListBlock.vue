<script setup>
/** Lijstblok op het dashboard ("Let op de houdbaarheid", "Klaar voor uitgifte", ...). */
import { datumKort } from '@/utils/format';

defineProps({ blok: { type: Object, required: true } });

const PIL = { rood: 'nee', groen: 'ok', amber: 'wacht' };
</script>

<template>
  <div class="card">
    <div class="kop"><h2>{{ blok.titel }}</h2><span class="tel rechts">{{ blok.tel }}</span></div>
    <div class="body" style="padding:6px 20px 14px">
      <ul v-if="blok.rijen.length" class="lijstje">
        <li v-for="(r, i) in blok.rijen" :key="i">
          <div class="stapel" style="flex:1">
            <div style="font-weight:600">
              <RouterLink v-if="r.link" :to="r.link" class="link">{{ r.naam }}</RouterLink>
              <template v-else>{{ r.naam }}</template>
              <span v-if="r.kleur && r.rechts" class="pil2" :class="PIL[r.kleur] || 'wacht'" style="margin-left:7px">{{ r.rechts }}</span>
            </div>
            <div class="sub">{{ r.onder }}</div>
          </div>
          <div class="stapel" style="text-align:right">
            <div v-if="r.waarde" class="mono" style="font-weight:500">{{ r.waarde }}</div>
            <div v-if="r.datum" class="sub">{{ datumKort(r.datum) }}</div>
            <div v-else-if="r.rechts && !r.kleur" class="sub">{{ r.rechts }}</div>
          </div>
        </li>
      </ul>
      <div v-else class="leeg">{{ blok.leeg }}</div>
    </div>
  </div>
</template>

<style scoped>
.sub { color: var(--ink-3); font-size: 12.5px; }
.link { color: inherit; text-decoration: none; }
.link:hover { text-decoration: underline; }
</style>
