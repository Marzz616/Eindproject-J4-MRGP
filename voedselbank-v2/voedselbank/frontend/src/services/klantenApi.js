/** Klanten, gezinsleden en dieetwensen (KLT). Routes: backend/routes/api.php */
import { http } from './http';

export const klantenApi = {
  lijst: () => http.get('/klanten'),
  opslaan: (k) => (k.id ? http.put(`/klanten/${k.id}`, k) : http.post('/klanten', k)),
  verwijderen: (id) => http.delete(`/klanten/${id}`),
  zetStatus: (id, geaccepteerd) => http.patch(`/klanten/${id}/status`, { geaccepteerd }),
  pakketten: (id) => http.get(`/klanten/${id}/pakketten`),
  wensen: () => http.get('/wensen'),
  wensToevoegen: (wens) => http.post('/wensen', wens),
};
