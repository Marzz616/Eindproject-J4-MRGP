<script setup>
/**
 * Voedselpakket samenstellen (US-PAK-01 t/m 12), in drie kolommen:
 *   1. gezinsprofiel  — wie heeft deze week nog een pakket nodig, met allergieën
 *   2. voorraad       — per categorie, gefilterd op wat bij dit gezin past
 *   3. pakketinhoud   — wat er in zit, met opslaan als concept of klaarzetten
 *
 * ?klant=3 of ?pakket=12 in de URL (links vanaf het dashboard) opent direct dat gezin.
 */
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { pakkettenApi } from '@/services/api';
import { usePakketSamenstellen } from '@/composables/usePakketSamenstellen';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import KlantKolom from '@/components/pakketten/KlantKolom.vue';
import VoorraadKolom from '@/components/pakketten/VoorraadKolom.vue';
import PakketKolom from '@/components/pakketten/PakketKolom.vue';

const sp = usePakketSamenstellen();
const dialog = useDialog();
const toast = useToast();
const route = useRoute();
const router = useRouter();

onMounted(async () => {
  try {
    await sp.laden();
    let klantId = Number(route.query.klant) || 0;
    if (route.query.pakket) klantId = (await pakkettenApi.ophalen(route.query.pakket)).pakket.klantId;
    if (klantId) await sp.kiesKlant(klantId);
  } catch (e) { meldFout(e); }
});

async function kiesKlant(id) {
  if (sp.mandje.size && sp.gekozen.value?.klant.id !== id) {
    const ja = await dialog.bevestig({
      titel: 'Ander gezin kiezen?', tekst: 'Het pakket dat je nu aan het samenstellen bent wordt leeggemaakt.',
      ja: 'Ja, ander gezin', nee: 'Terug',
    });
    if (!ja) return;
  }
  try { await sp.kiesKlant(id); } catch (e) { meldFout(e); }
}

async function opslaan(status, forceer = false) {
  try {
    toast.goed((await sp.opslaan(status, forceer)).melding);
  } catch (e) {
    if (e.status !== 409) { meldFout(e); return; }

    if (e.reden === 'geblokkeerd') {
      await dialog.melding({
        titel: 'Dit pakket kan zo niet',
        tekst: 'Deze producten mogen niet in het pakket van dit gezin. Haal ze eruit en sla daarna opnieuw op.',
        punten: e.data.geblokkeerd.map((g) => `${g.product} ${g.uitleg}`),
      });
    } else if (e.reden === 'conflict') {
      const ja = await dialog.bevestig({
        titel: 'Past niet bij de wensen van dit gezin',
        tekst: 'Deze producten botsen met een dieetwens. Dat mag wel — het gezin kan ze onderling verdelen — maar bevestig het even:',
        punten: e.data.conflicten.map((c) => `${c.product} — ${c.uitleg}`),
        ja: 'Toch opslaan', nee: 'Aanpassen', gevaar: true,
      });
      if (ja) await opslaan(status, true);
    } else if (e.reden === 'te_weinig_voorraad') {
      await dialog.melding({
        titel: 'Niet genoeg voorraad',
        punten: e.data.tekort.map((t) => `${t.product}: ${t.gevraagd} nodig, ${t.voorraad} op voorraad`),
      });
      await sp.laden();
    } else {
      meldFout(e);
    }
  }
}

async function verwijderen() {
  const ja = await dialog.bevestig({
    titel: 'Pakket verwijderen?', tekst: 'De producten gaan terug naar de voorraad.', ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) return;
  try { toast.goed((await sp.verwijder()).melding); } catch (e) { meldFout(e); }
}

function pakbon() {
  window.open(router.resolve({ name: 'pakbon', params: { id: sp.pakketId.value } }).href, '_blank');
}
</script>

<template>
  <div class="drie">
    <KlantKolom :klanten="sp.start.value.klanten" :gekozen="sp.gekozen.value" @kies="kiesKlant" />
    <VoorraadKolom :sp="sp" />
    <PakketKolom :sp="sp" @opslaan="opslaan" @verwijder="verwijderen" @pakbon="pakbon" />
  </div>
</template>

<style scoped>
.drie { display: grid; grid-template-columns: 1fr 1.15fr 1fr; gap: 18px; align-items: start; }
@media (max-width: 1240px) { .drie { grid-template-columns: 1fr; } }
</style>
