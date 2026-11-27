/** Voedselpakketten samenstellen (PAK). Routes: backend/routes/api.php */
import { http } from './http';

export const pakkettenApi = {
  samenstellen: () => http.get('/pakketten/samenstellen'),
  klant: (id) => http.get(`/pakketten/klant/${id}`),
  lijst: () => http.get('/pakketten'),
  ophalen: (id) => http.get(`/pakketten/${id}`),
  opslaan: (p) => (p.id ? http.put(`/pakketten/${p.id}`, p) : http.post('/pakketten', p)),
  verwijderen: (id) => http.delete(`/pakketten/${id}`),
};
