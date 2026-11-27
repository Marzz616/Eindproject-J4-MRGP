/** Gebruikers- en toegangsbeheer (GBR). Routes: backend/routes/api.php */
import { http } from './http';

export const gebruikersApi = {
  lijst: () => http.get('/gebruikers'),
  opslaan: (g) => (g.id ? http.put(`/gebruikers/${g.id}`, g) : http.post('/gebruikers', g)),
  zetActief: (id, actief) => http.patch(`/gebruikers/${id}/actief`, { actief }),
  resetWachtwoord: (id, wachtwoord) => http.put(`/gebruikers/${id}/wachtwoord`, { wachtwoord }),
};
