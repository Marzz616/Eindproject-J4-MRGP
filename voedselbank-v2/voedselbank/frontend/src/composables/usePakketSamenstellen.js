/**
 * Toestand en regels van het samenstelscherm (US-PAK-01 t/m 08).
 *
 * Gedeeld door de drie kolommen (gezin, voorraad, pakketinhoud), zodat de
 * regels maar op één plek staan:
 *   - een verlopen product of een strenge allergie kan niet in het pakket;
 *   - een gewone dieetwens geeft een waarschuwing (de server vraagt dan om bevestiging);
 *   - je kunt niet meer toevoegen dan er op voorraad is.
 * De server controleert dit bij het opslaan nog eens.
 */
import { computed, reactive, ref } from 'vue';
import { pakkettenApi } from '@/services/api';
import { useToast } from './useToast';

export function usePakketSamenstellen() {
  const toast = useToast();

  const start = ref({ klanten: [], producten: [] });
  const gekozen = ref(null);        // { klant, gezin, wensen, advies, lopend }
  const mandje = reactive(new Map());   // productId -> aantal
  const pakketId = ref(0);

  async function laden() {
    start.value = await pakkettenApi.samenstellen();
  }

  const product = (id) => start.value.producten.find((p) => p.id === id);

  /** Welke dieetwensen van het gekozen gezin botsen met dit product? */
  function botst(p) {
    if (!gekozen.value || !p.allergenen) return [];
    const kenmerken = p.allergenen.split(',').map((s) => s.trim());
    return gekozen.value.wensen.filter((w) => kenmerken.includes(w.tag));
  }

  /** 'verlopen' | 'allergie' | null */
  function geblokkeerd(p) {
    if (p.verlopen) return 'verlopen';
    if (botst(p).some((w) => w.streng)) return 'allergie';
    return null;
  }

  /** Gezin kiezen; een bestaand pakket van deze week wordt direct geopend. */
  async function kiesKlant(id) {
    gekozen.value = await pakkettenApi.klant(id);
    mandje.clear();
    pakketId.value = 0;

    const lopend = gekozen.value.lopend;
    if (lopend && lopend.status !== 'Uitgegeven') {
      try {
        const p = await pakkettenApi.ophalen(lopend.id);
        pakketId.value = p.pakket.id;
        p.inhoud.forEach((r) => mandje.set(r.productId, r.aantal));
        toast.info(`Bestaand pakket van deze week geopend (${p.pakket.status.toLowerCase()}).`);
      } catch { /* dan beginnen we leeg */ }
    }
  }

  function voegToe(id) {
    if (!gekozen.value) { toast.fout('Kies eerst een gezin.'); return; }
    const p = product(id);
    const slot = geblokkeerd(p);
    if (slot === 'verlopen') {
      toast.fout(`${p.naam} is over de houdbaarheidsdatum en mag niet in een pakket.`);
      return;
    }
    if (slot === 'allergie') {
      const w = botst(p).find((x) => x.streng);
      toast.fout(`${p.naam} kan niet: ${w.wens} bij ${w.personen.join(' en ')}.`);
      return;
    }
    wijzig(id, 1);
  }

  function wijzig(id, delta) {
    const p = product(id);
    const nieuw = (mandje.get(id) || 0) + delta;
    if (nieuw <= 0) mandje.delete(id);
    else if (nieuw > p.voorraad) toast.fout(`Er is niet genoeg voorraad van ${p.naam}.`);
    else mandje.set(id, nieuw);
  }

  const regels = computed(() => [...mandje.entries()]
    .map(([id, aantal]) => ({ p: product(id), aantal }))
    .filter((r) => r.p));

  const totaal = computed(() => regels.value.reduce((s, r) => s + r.aantal, 0));

  /** Opslaan; geeft de fout van de server door zodat de pagina kan reageren (409 conflict e.d.). */
  async function opslaan(status, forceer = false) {
    const r = await pakkettenApi.opslaan({
      id: pakketId.value,
      klant: gekozen.value.klant.id,
      status,
      forceer,
      regels: [...mandje.entries()].map(([product, aantal]) => ({ product, aantal })),
    });
    pakketId.value = r.id;
    await laden();
    gekozen.value = await pakkettenApi.klant(gekozen.value.klant.id);
    return r;
  }

  async function verwijder() {
    const r = await pakkettenApi.verwijderen(pakketId.value);
    mandje.clear();
    pakketId.value = 0;
    await laden();
    gekozen.value = await pakkettenApi.klant(gekozen.value.klant.id);
    return r;
  }

  return {
    start, gekozen, mandje, pakketId, regels, totaal,
    laden, kiesKlant, botst, geblokkeerd, voegToe, wijzig, opslaan, verwijder,
  };
}
