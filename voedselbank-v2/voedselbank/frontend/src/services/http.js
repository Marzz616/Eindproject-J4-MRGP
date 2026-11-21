/**
 * Eén plek voor alle communicatie met de PHP-API.
 *
 * - Stuurt en ontvangt JSON, met de sessiecookie.
 * - Een fout wordt een ApiError met de HTTP-status en het antwoord van de server,
 *   zodat een pagina kan reageren op bijvoorbeeld 409 + reden "heeft_historie".
 * - Bij 401 (sessie verlopen) roepen we de handler aan die de router instelt.
 */

const BASIS = '/api';

export class ApiError extends Error {
  constructor(status, data) {
    super(data?.fout || 'Er ging iets mis.');
    this.status = status;
    this.data = data || {};
  }

  /** Veldfouten uit een 422-antwoord, of null. */
  get fouten() {
    return this.data.fouten || null;
  }

  get reden() {
    return this.data.reden || null;
  }
}

let bijUitgelogd = () => {};

/** De router geeft hier door wat er moet gebeuren als de sessie verlopen is. */
export function onUnauthorized(handler) {
  bijUitgelogd = handler;
}

async function request(methode, pad, body) {
  const opties = { method: methode, credentials: 'same-origin', headers: {} };
  if (body !== undefined) {
    opties.headers['Content-Type'] = 'application/json';
    opties.body = JSON.stringify(body);
  }

  let antwoord;
  try {
    antwoord = await fetch(BASIS + pad, opties);
  } catch {
    throw new ApiError(0, { fout: 'Geen verbinding met de server.' });
  }

  const tekst = await antwoord.text();
  let data = {};
  try {
    data = tekst ? JSON.parse(tekst) : {};
  } catch {
    data = { fout: 'Onverwacht antwoord van de server.' };
  }

  if (antwoord.status === 401 && data.login_nodig) {
    bijUitgelogd();
  }
  if (!antwoord.ok) {
    throw new ApiError(antwoord.status, data);
  }
  return data;
}

/** Bouwt "?a=1&b=2" uit een object; lege waarden worden weggelaten. */
export function query(params = {}) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') q.set(k, v);
  });
  const s = q.toString();
  return s ? '?' + s : '';
}

export const http = {
  get: (pad) => request('GET', pad),
  post: (pad, body = {}) => request('POST', pad, body),
  put: (pad, body = {}) => request('PUT', pad, body),
  patch: (pad, body = {}) => request('PATCH', pad, body),
  delete: (pad) => request('DELETE', pad),
  /** Volledige URL, voor downloads (CSV) via een gewone link. */
  url: (pad) => BASIS + pad,
};
