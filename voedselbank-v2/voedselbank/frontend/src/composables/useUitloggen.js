/**
 * Uitloggen met een bevestiging. Gebruikt door de zijbalk en "Mijn account".
 */
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useDialog } from './useDialog';

export function useUitloggen() {
  const auth = useAuthStore();
  const router = useRouter();
  const dialog = useDialog();

  return async function uitloggen() {
    const ja = await dialog.bevestig({
      titel: 'Uitloggen?',
      tekst: 'Je wordt afgemeld en komt terug op het inlogscherm.',
      ja: 'Ja, uitloggen',
    });
    if (!ja) return;
    await auth.logout();
    router.push({ name: 'login' });
  };
}
