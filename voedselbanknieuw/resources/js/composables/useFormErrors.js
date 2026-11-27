/**
 * Foutafhandeling voor formulieren.
 *
 * De server stuurt bij foute invoer status 422 met {fouten: {veld: melding}}.
 * Deze composable zet die meldingen klaar zodat <FormField :fout="fouten.email">
 * ze onder het juiste veld laat zien. Andere fouten worden een toast.
 *
 *   const { fouten, verwerk, wis } = useFormErrors();
 *   try { await api.opslaan(form); } catch (e) { verwerk(e); }
 */
import { reactive } from 'vue';
import { useToast } from './useToast';

export function useFormErrors() {
  const fouten = reactive({});
  const toast = useToast();

  function wis() {
    Object.keys(fouten).forEach((k) => delete fouten[k]);
  }

  /** @returns {boolean} true als het veldfouten waren */
  function verwerk(e) {
    wis();
    if (e?.fouten) {
      Object.assign(fouten, e.fouten);
      return true;
    }
    toast.fout(e?.message || 'Er ging iets mis.');
    return false;
  }

  return { fouten, verwerk, wis };
}

/** Voor plekken zonder formulier: elke fout als toast. */
export function meldFout(e) {
  const toast = useToast();
  toast.fout(e?.fouten ? Object.values(e.fouten)[0] : e?.message || 'Er ging iets mis.');
}
