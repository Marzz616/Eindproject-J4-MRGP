/** Het dashboard; de inhoud verschilt per rol. Routes: backend/routes/api.php */
import { http } from './http';

export const dashboardApi = {
  ophalen: () => http.get('/dashboard'),
};
