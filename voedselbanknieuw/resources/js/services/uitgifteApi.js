/** Uitgifte aan de balie (WRK). Routes: backend/routes/api.php */
import { http, query } from './http';

export const uitgifteApi = {
  wachtrij: () => http.get('/uitgifte/wachtrij'),
  zoek: (q) => http.get('/uitgifte/zoek' + query({ q })),
  kaart: (id) => http.get(`/uitgifte/${id}`),
  legitimatie: (id, legitimatie) => http.post(`/uitgifte/${id}/identiteit`, { legitimatie }),
  uitgeven: (id) => http.post(`/uitgifte/${id}/uitgeven`),
  nietOpgehaald: (id) => http.post(`/uitgifte/${id}/niet-opgehaald`),
};
