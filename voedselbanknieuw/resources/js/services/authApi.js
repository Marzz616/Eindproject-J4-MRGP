/** Inloggen, uitloggen en het eigen account. Routes: backend/routes/api.php */
import { http } from './http';

export const authApi = {
  ik: () => http.get('/auth/me'),
  login: (gebruikersnaam, wachtwoord) => http.post('/auth/login', { gebruikersnaam, wachtwoord }),
  logout: () => http.post('/auth/logout'),
  rechten: () => http.get('/auth/rechten'),
  wachtwoord: (huidig, nieuw, herhaal) => http.put('/auth/wachtwoord', { huidig, nieuw, herhaal }),
};
