/**
 * Startpunt van de Vue-applicatie.
 *   - Pinia      gedeelde toestand (wie is er ingelogd)
 *   - router     welke pagina bij welke URL hoort, met rechtencontrole
 *   - main.css   de stijlen, opgesplitst per onderwerp
 */
import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import './assets/styles/main.css';

createApp(App)
  .use(createPinia())
  .use(router)
  .mount('#app');
