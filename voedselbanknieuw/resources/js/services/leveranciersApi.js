/** Leveranciers en het inboeken van leveringen (LEV). Routes: backend/routes/api.php */
import { http } from './http';

export const leveranciersApi = {
  lijst: () => http.get('/leveranciers'),
  opslaan: (l) => (l.id ? http.put(`/leveranciers/${l.id}`, l) : http.post('/leveranciers', l)),
  zetActief: (id, actief) => http.patch(`/leveranciers/${id}/actief`, { actief }),
  verwijderen: (id) => http.delete(`/leveranciers/${id}`),
  boekLevering: (levering) => http.post('/leveringen', levering),
};
