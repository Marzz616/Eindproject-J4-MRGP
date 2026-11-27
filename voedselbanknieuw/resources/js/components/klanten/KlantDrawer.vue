<script setup>
/**
 * Klant registreren of gezin bewerken (US-KLT-01/02/04 t/m 10).
 * - Directie/administratie: contactgegevens, voorkeuren én gezinsleden.
 * - Vrijwilliger: alleen gezinsleden en dieetwensen.
 */
import { reactive, ref, watch } from 'vue';
import { klantenApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useFormErrors, meldFout } from '@/composables/useFormErrors';
import { useToast } from '@/composables/useToast';
import SideDrawer from '@/components/ui/SideDrawer.vue';
import FormField from '@/components/ui/FormField.vue';
import GezinslidRij from './GezinslidRij.vue';

const props = defineProps({
  open: Boolean,
  klant: { type: Object, default: null },
  wensen: { type: Array, required: true },
  rechten: { type: Object, required: true },
});
const emit = defineEmits(['sluit', 'opgeslagen', 'wens-toegevoegd']);

const dialog = useDialog();
const toast = useToast();
const { fouten, verwerk, wis } = useFormErrors();

const form = reactive({ id: 0, naam: '', adres: '', postcode: '', plaats: 'Maaskantje', telefoon: '', email: '', opmerkingen: '' });
const leden = ref([]);

/** Een gezinslid in het formulier; uitzonderingen per wens: {aan, reden}. */
function nieuwLid(l = null) {
  const uitzonderingen = {};
  (l?.wensen || []).forEach((w) => {
    uitzonderingen[w.id] = { aan: !!w.uitzondering, reden: w.toelichting || '' };
  });
  return {
    sleutel: Math.random().toString(36).slice(2),
    naam: l?.naam || '',
    relatie: l?.relatie || (leden.value.length ? 'Kind' : 'Hoofdaanvrager'),
    gb: l?.gb || '',
    wensen: (l?.wensen || []).map((w) => w.id),
    uitzonderingen,
  };
}

watch(() => props.open, (open) => {
  if (!open) return;
  wis();
  const k = props.klant;
  Object.assign(form, {
    id: k?.id || 0, naam: k?.naam || '', adres: k?.adres || '', postcode: k?.postcode || '',
    plaats: k?.plaats || 'Maaskantje', telefoon: k?.telefoon || '', email: k?.email || '', opmerkingen: k?.opmerkingen || '',
  });
  leden.value = [];
  leden.value = k?.leden.length ? k.leden.map(nieuwLid) : [nieuwLid()];
});

async function eigenWens() {
  const uit = await dialog.formulier({
    titel: 'Eigen dieetwens toevoegen',
    tekst: 'Bijvoorbeeld "Geen vis" of "Zoutarm". De wens komt daarna bij elk gezinslid in de keuzelijst te staan.',
    velden: [
      { naam: 'naam', label: 'Omschrijving van de wens', type: 'text' },
      { naam: 'tag', label: 'Waar botst het mee? (optioneel)', type: 'text',
        hint: 'Bijvoorbeeld vis of zout. Producten met dit kenmerk geven dan een waarschuwing.' },
      { naam: 'streng', label: 'Hoe streng is deze wens?', type: 'select', waarde: '0', opties: [
        { waarde: '0', label: 'Wens of overtuiging — mag wel, met bevestiging' },
        { waarde: '1', label: 'Ernstige allergie — mag helemaal niet in het pakket' },
      ] },
    ],
    ja: 'Toevoegen',
  });
  if (!uit || !uit.naam.trim()) return;

  try {
    const w = await klantenApi.wensToevoegen({ naam: uit.naam.trim(), tag: uit.tag.trim(), streng: uit.streng === '1' });
    emit('wens-toegevoegd', { id: w.id, naam: w.naam, streng: !!w.streng });
    toast.goed(`"${w.naam}" staat nu in de lijst.`);
  } catch (e) { meldFout(e); }
}

async function opslaan() {
  // Een aangevinkte uitzondering zonder reden slaan we niet op: zonder uitleg weet
  // de vrijwilliger niet waarom hij een allergieproduct toch mag meegeven.
  const zonderReden = leden.value.some((l) =>
    l.wensen.some((id) => l.uitzonderingen[id]?.aan && !l.uitzonderingen[id].reden.trim()));
  if (zonderReden) {
    toast.fout('Vul een reden in bij elke uitzondering, of haal het vinkje weg.');
    return;
  }

  const body = {
    id: form.id,
    leden: leden.value
      .filter((l) => l.naam.trim() !== '' || l.gb !== '')
      .map((l) => ({
        naam: l.naam.trim(),
        relatie: l.relatie,
        gb: l.gb,
        wensen: l.wensen,
        uitzonderingen: Object.fromEntries(l.wensen
          .filter((id) => l.uitzonderingen[id]?.aan)
          .map((id) => [id, l.uitzonderingen[id].reden.trim()])),
      })),
  };
  if (props.rechten.contact) {
    Object.assign(body, {
      naam: form.naam.trim(), adres: form.adres.trim(), postcode: form.postcode.trim(), plaats: form.plaats.trim(),
      telefoon: form.telefoon.trim(), email: form.email.trim(), opmerkingen: form.opmerkingen.trim(),
    });
  }

  try {
    const r = await klantenApi.opslaan(body);
    emit('opgeslagen', r.melding || 'Klant opgeslagen.');
  } catch (e) { verwerk(e); }
}
</script>

<template>
  <SideDrawer :open="open" :titel="klant ? 'Gezin bewerken' : 'Nieuwe klant'"
              :onder="klant ? `${klant.naam} · ${klant.nummer}` : 'Registreer een gezin bij de voedselbank'" @sluit="emit('sluit')">
    <form novalidate @submit.prevent="opslaan">
      <template v-if="rechten.contact">
        <h3 class="sectie">Contactgegevens</h3>
        <FormField label="Gezinsnaam" for="k-naam" :fout="fouten.naam">
          <input id="k-naam" v-model="form.naam" name="naam" type="text" placeholder="Familie Bakker">
        </FormField>
        <FormField label="Adres" for="k-adres" :fout="fouten.adres">
          <input id="k-adres" v-model="form.adres" name="adres" type="text" placeholder="Dorpsstraat 12">
        </FormField>
        <div class="rij2">
          <FormField label="Postcode" for="k-postcode" :fout="fouten.postcode">
            <input id="k-postcode" v-model="form.postcode" name="postcode" type="text" placeholder="5492 AB" maxlength="7">
          </FormField>
          <FormField label="Plaats" for="k-plaats" :fout="fouten.plaats">
            <input id="k-plaats" v-model="form.plaats" name="plaats" type="text">
          </FormField>
        </div>
        <div class="rij2">
          <FormField label="Telefoonnummer" for="k-tel" :fout="fouten.telefoon">
            <input id="k-tel" v-model="form.telefoon" name="telefoon" type="text" placeholder="06-12345678">
          </FormField>
          <FormField label="E-mailadres" for="k-mail" :fout="fouten.email">
            <input id="k-mail" v-model="form.email" name="email" type="email" placeholder="naam@voorbeeld.nl">
          </FormField>
        </div>
      </template>

      <template v-if="rechten.beheren">
        <h3 class="sectie">Voorkeuren van dit gezin</h3>
        <FormField label="Wat moet de vrijwilliger weten bij het samenstellen?" for="k-opm" :fout="fouten.opmerkingen"
                   hint="Vrije tekst, maximaal 500 tekens. Dit is géén allergie — die hoort bij het gezinslid zelf. De vrijwilliger ziet deze tekst bij het samenstellen van het pakket.">
          <textarea id="k-opm" v-model="form.opmerkingen" name="opmerkingen" rows="3" maxlength="500"
                    placeholder="Liever rijst dan pasta. Geen koffie, wel thee." />
        </FormField>
      </template>

      <h3 class="sectie">Gezinssamenstelling</h3>
      <div v-if="fouten.leden" class="melding rood"><div>{{ fouten.leden }}</div></div>
      <GezinslidRij v-for="(lid, i) in leden" :key="lid.sleutel" :lid="lid" :wensen="wensen"
                    @verwijder="leden.splice(i, 1)" />

      <div class="horizontaal" style="flex-wrap:wrap">
        <button type="button" class="knop klein" @click="leden.push(nieuwLid())">+ Gezinslid toevoegen</button>
        <button type="button" class="knop klein" @click="eigenWens">+ Eigen dieetwens toevoegen</button>
      </div>
      <div class="hint">
        Het type (baby, kind of volwassene) wordt automatisch bepaald uit de geboortedatum. Een ⚠ voor een wens betekent
        een ernstige allergie: zo'n product wordt hard geblokkeerd. Wil het gezin een geblokkeerd product tóch ontvangen,
        vink dan de uitzondering aan en noteer waarom — die reden komt bij het samenstellen en op de pakbon te staan.
      </div>
    </form>

    <template #voet>
      <button class="knop" type="button" @click="emit('sluit')">Annuleren</button>
      <button class="knop primair" type="button" @click="opslaan">Opslaan</button>
    </template>
  </SideDrawer>
</template>

<style scoped>
.sectie { font-size: 15px; margin: 22px 0 12px; }
.sectie:first-child { margin-top: 0; }
.hint { font-size: 12px; color: var(--ink-3); margin-top: 8px; }
textarea { width: 100%; font: inherit; padding: 9px 11px; border: 1px solid var(--line-2); border-radius: 9px; resize: vertical; }
</style>
