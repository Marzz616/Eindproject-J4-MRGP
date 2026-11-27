/**
 * Vragen aan de gebruiker in een nette modal, in plaats van confirm() of prompt().
 * Beide functies geven een Promise terug, zodat je ze kunt awaiten:
 *
 *   const ja = await dialog.bevestig({ titel: 'Verwijderen?', tekst: '...', gevaar: true });
 *
 *   const uit = await dialog.formulier({
 *     titel: 'Voorraad corrigeren',
 *     velden: [{ naam: 'aantal', label: 'Nieuwe voorraad', type: 'number', waarde: 12 }],
 *   });   // -> { aantal: '12' } of null bij annuleren
 *
 * De modal zelf staat in components/ui/DialogHost.vue.
 */
import { reactive } from 'vue';

const huidige = reactive({ open: false, soort: null, opties: {}, waarden: {} });
let afronden = null;

function openen(soort, opties) {
  // een eventueel openstaande vraag eerst afsluiten
  if (afronden) afronden(soort === 'bevestig' ? false : null);

  huidige.soort = soort;
  huidige.opties = opties;
  huidige.waarden = Object.fromEntries((opties.velden || []).map((v) => [v.naam, v.waarde ?? '']));
  huidige.open = true;

  return new Promise((resolve) => {
    afronden = (uitkomst) => {
      huidige.open = false;
      afronden = null;
      resolve(uitkomst);
    };
  });
}

export function useDialog() {
  return {
    huidige,
    /**
     * @param {{titel:string, tekst?:string, punten?:string[], notitie?:string,
     *          ja?:string, nee?:string, gevaar?:boolean}} opties
     * @returns {Promise<boolean>}
     */
    bevestig: (opties) => openen('bevestig', { ja: 'Ja, doorgaan', nee: 'Annuleren', ...opties }),

    /** Informatie tonen met één knop. */
    melding: (opties) => openen('bevestig', { ja: 'Begrepen', alleenJa: true, ...opties }),

    /**
     * @param {{titel:string, tekst?:string, velden:Array, ja?:string, nee?:string}} opties
     * @returns {Promise<object|null>}
     */
    formulier: (opties) => openen('formulier', { ja: 'Opslaan', nee: 'Annuleren', ...opties }),

    /** Wordt aangeroepen door DialogHost. */
    sluit: (uitkomst) => afronden && afronden(uitkomst),
  };
}
