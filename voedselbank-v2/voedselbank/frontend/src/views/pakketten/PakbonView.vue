<script setup>
/**
 * Pakbon / sticker bij een pakket (US-PAK-10/11). Losse afdrukpagina zonder menu.
 * Toont het gezin, de dieetwensen, vastgelegde uitzonderingen en de inhoud per categorie.
 */
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { pakkettenApi } from '@/services/api';
import { datumKort, klantnummer } from '@/utils/format';

const route = useRoute();
const d = ref(null);
const fout = ref('');

onMounted(async () => {
  try {
    d.value = await pakkettenApi.ophalen(route.params.id);
    document.title = `Pakbon ${d.value.pakket.klant} · Voedselbank Maaskantje`;
  } catch (e) {
    fout.value = e.message || 'Pakbon kon niet worden geladen.';
  }
});

const totaal = computed(() => d.value?.inhoud.reduce((s, r) => s + r.aantal, 0) || 0);
</script>

<template>
  <div class="vel">
    <div class="nietprint horizontaal" style="margin-bottom:14px">
      <button class="knop" onclick="history.back()">← Terug</button>
      <button class="knop primair" onclick="window.print()">Afdrukken</button>
    </div>

    <div class="bon">
      <div v-if="fout" class="melding rood"><div>{{ fout }}</div></div>
      <div v-else-if="!d" class="skelet">Bezig met laden…</div>

      <template v-else>
        <div class="bonkop">
          <div class="logo">VB</div>
          <div style="flex:1">
            <div class="titel">Voedselbank Maaskantje</div>
            <div style="color:var(--ink-2);font-size:13.5px">Pakbon voedselpakket</div>
          </div>
          <div style="text-align:right">
            <div class="mono" style="font-size:15px;font-weight:500">Pakket #{{ d.pakket.id }}</div>
            <div style="color:var(--ink-2);font-size:13px">{{ datumKort(d.pakket.uitgifte || d.pakket.datum) }}</div>
          </div>
        </div>

        <div class="sticker">
          <div class="voor">Voor</div>
          <div class="groot">{{ d.pakket.klant }}</div>
          <div class="mono" style="color:var(--ink-2);margin-top:2px">
            {{ klantnummer(d.pakket.klantId) }} · {{ d.gezin.length }} {{ d.gezin.length === 1 ? 'persoon' : 'personen' }}
          </div>
          <div v-if="d.wensen.length" class="pillen" style="margin-top:11px">
            <span v-for="w in d.wensen" :key="w" class="pil2 diet">{{ w }}</span>
          </div>
          <div v-else style="margin-top:9px;color:var(--ink-3);font-size:13.5px">Geen dieetbeperkingen</div>
          <div v-if="d.pakket.opmerkingen" style="margin-top:11px;font-size:13.5px"><b>Voorkeuren:</b> {{ d.pakket.opmerkingen }}</div>
          <div v-for="u in d.uitzonderingen" :key="u.wens" class="uitzondering">
            <b>Uitzondering op {{ u.wens }}:</b> {{ u.toelichting }}
          </div>
        </div>

        <h3 class="inhoudkop">Inhoud ({{ totaal }} stuks)</h3>
        <div class="regel kopregel"><div>Aantal</div><div>Product</div><div>Categorie / locatie</div></div>
        <div v-for="r in d.inhoud" :key="r.productId" class="regel">
          <div class="mono" style="font-weight:500">{{ r.aantal }}×</div>
          <div style="font-weight:600">{{ r.naam }}
            <span v-if="r.conflict.length" class="pil2 nee">let op: {{ r.conflict.map((c) => c.wens).join(', ') }}</span>
          </div>
          <div style="color:var(--ink-2)">{{ r.categorie }}{{ r.locatie ? ' · ' + r.locatie : '' }}</div>
        </div>

        <div class="onderkant">
          <div>
            <div><b>Status:</b> {{ d.pakket.status }}</div>
            <div><b>Samengesteld door:</b> {{ d.pakket.door || '—' }}</div>
            <div v-if="d.pakket.uitgever"><b>Uitgegeven door:</b> {{ d.pakket.uitgever }}</div>
            <div v-if="d.pakket.legitimatie"><b>Geïdentificeerd met:</b> {{ d.pakket.legitimatie }}</div>
          </div>
          <div>
            <div class="handtekening" />
            <div style="margin-top:6px">Handtekening voor ontvangst</div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.vel { max-width: 760px; margin: 28px auto; padding: 0 16px; }
.bon { background: #fff; border: 1px solid var(--line-2); border-radius: 12px; padding: 30px 34px; }
.bonkop { display: flex; align-items: flex-start; gap: 16px; border-bottom: 2px solid var(--ink); padding-bottom: 16px; }
.logo { width: 46px; height: 46px; border-radius: 11px; background: var(--green); color: #fff; font-family: 'Zilla Slab', serif;
        font-weight: 700; display: grid; place-items: center; font-size: 17px; }
.titel { font-family: 'Zilla Slab', serif; font-weight: 700; font-size: 20px; }
.sticker { border: 2px dashed var(--line-2); border-radius: 10px; padding: 18px 20px; margin-top: 22px; }
.voor { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-3); font-weight: 700; }
.groot { font-family: 'Zilla Slab', serif; font-size: 27px; font-weight: 700; }
.uitzondering { margin-top: 9px; font-size: 13.5px; border-left: 3px solid var(--amber); padding-left: 9px; }
.inhoudkop { font-family: 'Zilla Slab', serif; font-size: 17px; margin: 24px 0 8px; }
.regel { display: grid; grid-template-columns: 52px 1fr 1fr; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--line); }
.regel:last-child { border-bottom: 0; }
.kopregel { font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: var(--ink-3); font-weight: 700; border-bottom-color: var(--line-2); }
.onderkant { margin-top: 26px; display: grid; grid-template-columns: 1fr 1fr; gap: 26px; font-size: 13.5px; color: var(--ink-2); }
.handtekening { height: 46px; border-bottom: 1px solid var(--ink-3); }
@media print {
  .vel { margin: 0; max-width: none; padding: 0; }
  .bon { border: 0; border-radius: 0; padding: 0; }
}
</style>
