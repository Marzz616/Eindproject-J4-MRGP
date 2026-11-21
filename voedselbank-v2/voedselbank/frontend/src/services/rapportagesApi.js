/** Rapportages en maandoverzichten (RAP). Routes: backend/routes/api.php */
import { http, query } from './http';

export const rapportagesApi = {
  overzicht: () => http.get('/rapportages'),
  maand: (soort, maand, jaar) => http.get('/rapportages/maand' + query({ soort, maand, jaar })),
  csvUrl: (rapport) => http.url(`/rapportages/export/${rapport}`),
};
