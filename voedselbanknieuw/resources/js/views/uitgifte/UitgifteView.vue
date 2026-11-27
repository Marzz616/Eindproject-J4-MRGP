<script setup>
/**
 * Vrijdag uitgiftepunt (US-PAK-09/12, US-WRK-01 t/m 06).
 * Links: klant zoeken en het pakket afhandelen. Rechts: de wachtrij van deze week.
 */
import { onMounted, ref } from 'vue';
import { uitgifteApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import PakketKaart from '@/components/uitgifte/PakketKaart.vue';
import Wachtrij from '@/components/uitgifte/Wachtrij.vue';

const dialog = useDialog();
const toast = useToast();

const wachtrij = ref(null);
const huidig = ref(null);
const zoekterm = ref('');

async function verversWachtrij() {
  try { wachtrij.value = await uitgifteApi.wachtrij(); } catch (e) { meldFout(e); }
}
onMounted(verversWachtrij);

async function zoeken() {
  const q = zoekterm.value.trim();
  if (!q) { toast.fout('Vul een cliëntnummer of naam in.'); return; }
  try {
    const { resultaten } = await uitgifteApi.zoek(q);
    if (resultaten.length === 1) { huidig.value = resultaten[0]; return; }
    const keuze = await dialog.formulier({
      titel: 'Meerdere cliënten gevonden',
      velden: [{ naam: 'k', label: 'Om welk gezin gaat het?', type: 'select', waarde: 0,
                 opties: resultaten.map((x, i) => ({ waarde: i, label: `${x.klant.nummer} · ${x.klant.naam}` })) }],
      ja: 'Kiezen',
    });
    if (keuze) huidig.value = resultaten[Number(keuze.k)];
  } catch (e) { meldFout(e); }
}

async function openPakket(id) {
  try {
    huidig.value = await uitgifteApi.kaart(id);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (e) { meldFout(e); }
}

async function legitimatie() {
  const uit = await dialog.formulier({
    titel: 'Legitimatie vastleggen',
    tekst: `Controleer het legitimatiebewijs van de persoon die het pakket ophaalt voor ${huidig.value.klant.naam}.`,
    velden: [{ naam: 'legitimatie', label: 'Soort legitimatiebewijs', type: 'select', waarde: huidig.value.legitimatie || 'Paspoort',
               opties: ['Paspoort', 'Rijbewijs', 'ID-kaart'].map((s) => ({ waarde: s, label: s })) }],
    ja: 'Vastleggen',
  });
  if (!uit) return;
  try {
    toast.goed((await uitgifteApi.legitimatie(huidig.value.pakketId, uit.legitimatie)).melding);
    huidig.value = await uitgifteApi.kaart(huidig.value.pakketId);
    await verversWachtrij();
  } catch (e) { meldFout(e); }
}

async function uitgeven() {
  const ja = await dialog.bevestig({
    titel: 'Pakket uitgeven?',
    tekst: `Bevestig dat het pakket voor ${huidig.value.klant.naam} fysiek is overhandigd. Daarna kan het pakket niet meer gewijzigd worden.`,
    ja: 'Ja, uitgegeven',
  });
  if (!ja) return;
  try {
    toast.goed((await uitgifteApi.uitgeven(huidig.value.pakketId)).melding);
    huidig.value = await uitgifteApi.kaart(huidig.value.pakketId);
    await verversWachtrij();
  } catch (e) {
    if (e.status === 409 && e.reden === 'geen_identiteit') {
      await dialog.melding({ titel: 'Eerst legitimeren', tekst: e.message });
      return;
    }
    meldFout(e);
  }
}

async function nietOpgehaald() {
  const ja = await dialog.bevestig({
    titel: 'Pakket niet opgehaald?',
    tekst: `Het pakket voor ${huidig.value.klant.naam} wordt verwijderd en alle producten gaan terug naar de voorraad.`,
    ja: 'Ja, terugboeken', gevaar: true,
  });
  if (!ja) return;
  try {
    toast.goed((await uitgifteApi.nietOpgehaald(huidig.value.pakketId)).melding);
    huidig.value = null;
    await verversWachtrij();
  } catch (e) { meldFout(e); }
}
</script>

<template>
  <div class="twee">
    <div>
      <div class="card" style="margin-bottom:18px">
        <div class="kop"><h2>Cliënt zoeken</h2></div>
        <div class="body">
          <div class="veld" style="margin-bottom:0">
            <label for="q">Scan het pasje of typ het cliëntnummer of de naam</label>
            <div class="horizontaal">
              <input id="q" v-model="zoekterm" type="text" placeholder="bijv. K-002, 2 of El Hassani" autofocus @keydown.enter="zoeken">
              <button class="knop primair" @click="zoeken">Zoeken</button>
            </div>
            <div class="hint">Je vindt het cliëntnummer ook op de klantenpagina.</div>
          </div>
        </div>
      </div>

      <PakketKaart :kaart="huidig" @legitimatie="legitimatie" @uitgeven="uitgeven" @niet-opgehaald="nietOpgehaald" />
    </div>

    <Wachtrij v-if="wachtrij" :wachtrij="wachtrij" @open="openPakket" />
  </div>
</template>

<style scoped>
.twee { display: grid; grid-template-columns: 1.2fr 1fr; gap: 18px; align-items: start; }
@media (max-width: 1100px) { .twee { grid-template-columns: 1fr; } }
</style>
