/**
 * Korte meldingen rechtsonder in beeld ("Klant opgeslagen.").
 *
 *   const toast = useToast();
 *   toast.goed('Opgeslagen.');
 *   toast.fout('Dat lukte niet.');
 */
import { reactive } from 'vue';

const meldingen = reactive([]);
let teller = 0;

function toon(tekst, soort = '') {
  const id = ++teller;
  meldingen.push({ id, tekst, soort });
  setTimeout(() => {
    const i = meldingen.findIndex((m) => m.id === id);
    if (i >= 0) meldingen.splice(i, 1);
  }, 4000);
}

export function useToast() {
  return {
    meldingen,
    goed: (t) => toon(t, 'goed'),
    fout: (t) => toon(t, 'fout'),
    info: (t) => toon(t),
  };
}
