/**
 * Opmaak van datums, bedragen en getallen, overal in de app hetzelfde.
 */

const MAANDEN = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
export const MAANDEN_VOL = ['januari', 'februari', 'maart', 'april', 'mei', 'juni',
  'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

function alsDatum(s) {
  if (!s) return null;
  const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
  return isNaN(d) ? null : d;
}

/** "Vandaag", "Gisteren", "Morgen" of "5 nov 2026". */
export function datum(s) {
  const d = alsDatum(s);
  if (!d) return '—';
  const vandaag = new Date();
  vandaag.setHours(0, 0, 0, 0);
  const dagen = Math.round((d - vandaag) / 86400000);
  if (dagen === 0) return 'Vandaag';
  if (dagen === -1) return 'Gisteren';
  if (dagen === 1) return 'Morgen';
  return `${d.getDate()} ${MAANDEN[d.getMonth()]} ${d.getFullYear()}`;
}

/** 05-11-2026 */
export function datumKort(s) {
  const d = alsDatum(s);
  if (!d) return '—';
  return String(d.getDate()).padStart(2, '0') + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear();
}

/** "Vandaag, 14:05" of "Nog nooit". */
export function tijdstip(s) {
  if (!s) return 'Nog nooit';
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return '—';
  return datum(s) + ', ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

/** € 12,50 */
export function geld(n) {
  return '€ ' + Number(n || 0).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** 1.234 */
export function getal(n) {
  return Number(n || 0).toLocaleString('nl-NL');
}

export function initialen(naam) {
  return String(naam || '?').trim().charAt(0).toUpperCase();
}

/** K-002 */
export function klantnummer(id) {
  return 'K-' + String(id).padStart(3, '0');
}

/** "1 persoon" / "3 personen" */
export function personen(n) {
  return `${n} ${n === 1 ? 'persoon' : 'personen'}`;
}

/** De datum van vandaag als 2026-11-19 (lokale tijd, niet UTC). */
export function vandaagIso() {
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
