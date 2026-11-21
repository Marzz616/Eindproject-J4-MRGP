<script setup>
/**
 * Categorieën beheren (US-VRD-11 / PRD-5): toevoegen, wijzigen en verwijderen.
 * Een categorie met producten kan pas weg als de producten verplaatst zijn;
 * dat wordt hier in één keer aangeboden.
 */
import { categorieenApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import BaseModal from '@/components/ui/BaseModal.vue';

const props = defineProps({ open: Boolean, categorieen: { type: Array, required: true } });
const emit = defineEmits(['sluit', 'gewijzigd']);
const dialog = useDialog();
const toast = useToast();

async function formulier(cat) {
  emit('sluit');
  const uit = await dialog.formulier({
    titel: cat ? 'Categorie wijzigen' : 'Nieuwe categorie',
    tekst: cat ? `${cat.naam} — ${cat.aantal} ${cat.aantal === 1 ? 'product' : 'producten'}.`
               : 'De categorie komt daarna in de keuzelijst bij elk product te staan.',
    velden: [
      { naam: 'naam', label: 'Volledige naam', type: 'text', waarde: cat?.naam || '' },
      { naam: 'kort', label: 'Korte naam voor de filterknop', type: 'text', waarde: cat?.kort || '',
        hint: 'Laat leeg om de eerste 20 tekens van de naam te gebruiken.' },
      { naam: 'oms', label: 'Omschrijving', type: 'text', waarde: cat?.oms || '' },
    ],
  });
  if (uit && uit.naam.trim()) {
    try {
      await categorieenApi.opslaan({ id: cat?.id || 0, naam: uit.naam.trim(), kort: uit.kort.trim(), oms: uit.oms.trim() });
      toast.goed(cat ? 'Categorie bijgewerkt.' : 'Categorie toegevoegd.');
      emit('gewijzigd');
    } catch (e) { meldFout(e); }
  }
}

async function verwijderen(cat) {
  emit('sluit');
  const ja = await dialog.bevestig({
    titel: 'Categorie verwijderen?',
    tekst: `${cat.naam} verdwijnt uit de keuzelijst en uit de filterknoppen. De producten zelf blijven staan.`,
    ja: 'Ja, verwijderen', gevaar: true,
  });
  if (!ja) return;

  try {
    toast.goed((await categorieenApi.verwijderen(cat.id)).melding);
    emit('gewijzigd');
  } catch (e) {
    if (e.status === 409 && e.reden === 'heeft_producten') await verhuizen(cat, e);
    else meldFout(e);
  }
}

/** Producten uit een volle categorie naar een andere zetten, en dan pas verwijderen. */
async function verhuizen(cat, e) {
  const andere = props.categorieen.filter((c) => c.id !== cat.id);
  if (!andere.length) {
    await dialog.melding({ titel: 'Dit is de laatste categorie', tekst: 'Maak eerst een andere categorie aan om de producten in te zetten.' });
    return;
  }
  const uit = await dialog.formulier({
    titel: 'Deze categorie is niet leeg',
    tekst: `${e.message} ${e.data.suggestie}`,
    velden: [{ naam: 'naar', label: 'Zet de producten in', type: 'select', waarde: andere[0].id,
               opties: andere.map((c) => ({ waarde: c.id, label: c.naam })) }],
    ja: 'Verplaatsen en verwijderen',
  });
  if (!uit) return;

  try {
    const v = await categorieenApi.verplaatsProducten(cat.id, Number(uit.naar));
    const r = await categorieenApi.verwijderen(cat.id);
    toast.goed(`${v.verplaatst} ${v.verplaatst === 1 ? 'product' : 'producten'} verplaatst. ${r.melding}`);
    emit('gewijzigd');
  } catch (err) { meldFout(err); }
}
</script>

<template>
  <BaseModal :open="open" titel="Categorieën beheren" breed @sluit="emit('sluit')">
    <p style="margin-top:0">Elk product hoort bij één categorie. De vrijwilliger ziet de voorraad per categorie
      gegroepeerd bij het samenstellen van een pakket.</p>
    <div class="inner" style="--subkolommen:2.2fr .8fr 1.5fr;max-height:46vh;overflow:auto">
      <div class="ihead"><span>Categorie</span><span>Producten</span><span /></div>
      <div v-for="c in categorieen" :key="c.id" class="irow">
        <div class="p">{{ c.naam }}<div v-if="c.oms" class="oms">{{ c.oms }}</div></div>
        <div class="mono">{{ c.aantal }}</div>
        <div class="horizontaal" style="gap:7px;justify-content:flex-end">
          <button class="knop klein" @click="formulier(c)">Bewerken</button>
          <button class="knop klein gevaar" @click="verwijderen(c)">Verwijderen</button>
        </div>
      </div>
    </div>
    <template #voet>
      <button class="knop" @click="emit('sluit')">Sluiten</button>
      <button class="knop primair" @click="formulier(null)">+ Nieuwe categorie</button>
    </template>
  </BaseModal>
</template>

<style scoped>
.oms { font-size: 12.5px; color: var(--ink-3); font-weight: 400; }
</style>
