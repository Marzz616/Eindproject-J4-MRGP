/** Producten en voorraad (PRD/VRD). Routes: backend/routes/api.php */
import { http } from './http';

export const productenApi = {
  lijst: () => http.get('/producten'),
  opslaan: (p) => (p.id ? http.put(`/producten/${p.id}`, p) : http.post('/producten', p)),
  verwijderen: (id) => http.delete(`/producten/${id}`),
  zetVoorraad: (id, aantal) => http.patch(`/producten/${id}/voorraad`, { aantal }),
  afvoeren: (id, afvoer) => http.post(`/producten/${id}/afvoer`, afvoer),
};
