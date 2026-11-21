/**
 * De ondertitel in de bovenbalk komt uit de route (meta.onder).
 * Een pagina kan hem tijdelijk vervangen, zoals het dashboard dat per rol uitlegt
 * wat je ziet.
 */
import { ref } from 'vue';

const vervanging = ref(null);

export function usePageSubtitle() {
  return {
    vervanging,
    zet: (tekst) => { vervanging.value = tekst; },
    herstel: () => { vervanging.value = null; },
  };
}
