/** Productcategorieën (PRD-5). Routes: backend/routes/api.php */
import { http } from './http';

export const categorieenApi = {
  lijst: () => http.get('/categorieen'),
  opslaan: (c) => (c.id ? http.put(`/categorieen/${c.id}`, c) : http.post('/categorieen', c)),
  verwijderen: (id) => http.delete(`/categorieen/${id}`),
  verplaatsProducten: (van, naar) => http.post(`/categorieen/${van}/verplaatsen`, { naar }),
};
