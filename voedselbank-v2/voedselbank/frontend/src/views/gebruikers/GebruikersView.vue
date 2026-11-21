<script setup>
/**
 * Gebruikers- en toegangsbeheer (US-GBR-01 t/m 09).
 * Rechten hangen aan de rol; de tabel onderaan laat zien welke rol wat mag.
 */
import { computed, onMounted, ref } from 'vue';
import { gebruikersApi } from '@/services/api';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { meldFout } from '@/composables/useFormErrors';
import { initialen, tijdstip } from '@/utils/format';
import SearchInput from '@/components/ui/SearchInput.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ExpandableRow from '@/components/ui/ExpandableRow.vue';
import GebruikerDrawer from '@/components/gebruikers/GebruikerDrawer.vue';

const dialog = useDialog();
const toast = useToast();

const data = ref({ gebruikers: [], rollen: [], permissies: [], ik: 0 });
const zoek = ref('');
const open = ref({});
const drawer = ref({ open: false, gebruiker: null });

async function laden() {
  try { data.value = await gebruikersApi.lijst(); } catch (e) { meldFout(e); }
}
onMounted(laden);

const zichtbaar = computed(() => {
  const t = zoek.value.trim().toLowerCase();
  return data.value.gebruikers.filter((g) => !t || `${g.naam} ${g.email} ${g.gnaam} ${g.rol}`.toLowerCase().includes(t));
});

const rolVan = (id) => data.value.rollen.find((r) => r.id === id) || { permissies: [] };
const permissieNaam = (id) => data.value.permissies.find((p) => p.id === id)?.naam;

function bewerk(g) { drawer.value = { open: true, gebruiker: g }; }

async function blokkeren(g, aan) {
  const ja = await dialog.bevestig({
    titel: aan ? 'Account deblokkeren?' : 'Account blokkeren?',
    tekst: aan
      ? `${g.naam} kan daarna weer inloggen.`
      : `${g.naam} kan daarna niet meer inloggen. Het account wordt niet verwijderd: wat deze persoon heeft gedaan blijft zichtbaar in de historie.`,
    ja: aan ? 'Ja, deblokkeren' : 'Ja, blokkeren',
    gevaar: !aan,
  });
  if (!ja) return;
  try {
    toast.goed((await gebruikersApi.zetActief(g.id, aan)).melding);
    await laden();
  } catch (e) { meldFout(e); }
}

async function resetten(g) {
  const uit = await dialog.formulier({
    titel: 'Wachtwoord resetten',
    tekst: `Stel een nieuw wachtwoord in voor ${g.naam} en geef dat persoonlijk door.`,
    velden: [{ naam: 'wachtwoord', label: 'Nieuw wachtwoord', type: 'password', hint: 'Minimaal 8 tekens met letters én cijfers.' }],
    ja: 'Wachtwoord instellen',
  });
  if (!uit) return;
  try {
    toast.goed((await gebruikersApi.resetWachtwoord(g.id, uit.wachtwoord)).melding);
    await laden();
  } catch (e) { meldFout(e); }
}

async function opgeslagen(melding) {
  drawer.value.open = false;
  toast.goed(melding);
  await laden();
}
</script>

<template>
  <div class="zoekbalk">
    <SearchInput v-model="zoek" placeholder="Zoek gebruiker op naam of e-mailadres…" />
    <button class="knop primair" @click="bewerk(null)">+ Nieuwe gebruiker</button>
  </div>

  <div id="lijst" class="tbl">
    <div class="thead">
      <span /><span>Naam</span><span class="c-mail">E-mail</span><span>Rol</span>
      <span class="c-login">Laatste login</span><span>Status</span>
    </div>

    <ExpandableRow v-for="g in zichtbaar" :key="g.id" v-model:open="open[g.id]">
      <template #rij>
        <div class="horizontaal">
          <div class="avatar">{{ initialen(g.naam) }}</div>
          <div>
            <span class="nm">{{ g.naam }}</span>
            <div class="mono gnaam">{{ g.gnaam }}{{ g.id === data.ik ? ' · jij' : '' }}</div>
          </div>
        </div>
        <div class="c-mail afkap" style="color:var(--ink-2)">{{ g.email }}</div>
        <div><span class="pil2" :class="g.rol === 'Directie' ? 'diet' : 'off'">{{ g.rol.toUpperCase() }}</span></div>
        <div class="c-login" style="color:var(--ink-2);font-size:13.5px">{{ tijdstip(g.laatste) }}</div>
        <div><span class="pil2" :class="g.actief ? 'ok' : 'nee'">{{ g.actief ? 'Actief' : 'Geblokkeerd' }}</span></div>
      </template>

      <h3>Toegewezen permissies &amp; rechten</h3>
      <ul class="lijstje rechtenlijst">
        <li v-for="p in data.permissies" :key="p.id" :class="{ uit: !rolVan(g.rolId).permissies.includes(p.id) }">
          <span v-if="rolVan(g.rolId).permissies.includes(p.id)" class="vink">✓</span>
          <span v-else class="kruis">✕</span>
          {{ p.naam }}
        </li>
      </ul>
      <div v-if="g.mislukt > 0" class="melding amber" style="margin-top:14px"><div>
        {{ g.mislukt }} mislukte inlogpoging{{ g.mislukt === 1 ? '' : 'en' }} sinds de laatste geslaagde login.
      </div></div>
      <div class="detailacts">
        <button class="knop klein" @click.stop="bewerk(g)">Account bewerken</button>
        <button class="knop klein" @click.stop="resetten(g)">Wachtwoord resetten</button>
        <button class="knop klein" :class="{ gevaar: g.actief }" @click.stop="blokkeren(g, !g.actief)">
          {{ g.actief ? 'Blokkeren' : 'Deblokkeren' }}
        </button>
      </div>
    </ExpandableRow>

    <EmptyState v-if="!zichtbaar.length" titel="Geen gebruikers gevonden" tekst="Pas je zoekopdracht aan." />
  </div>

  <div class="card" style="margin-top:18px">
    <div class="kop"><h2>Rollen en wat ze mogen</h2></div>
    <div class="body">
      <p style="margin:0 0 14px;color:var(--ink-2);font-size:14px">
        Rechten hangen aan de <b>rol</b>, niet aan de persoon. Wie een rol krijgt, krijgt automatisch de bijbehorende
        rechten — en verliest ze weer zodra de rol wijzigt.
      </p>
      <div class="inner" style="--subkolommen:1.1fr 3fr">
        <div class="ihead"><span>Rol</span><span>Rechten</span></div>
        <div v-for="r in data.rollen" :key="r.id" class="irow" style="align-items:flex-start;padding-top:12px;padding-bottom:12px">
          <div class="p">{{ r.naam }}<div class="oms">{{ r.omschrijving }}</div></div>
          <div class="pillen"><span v-for="pid in r.permissies" :key="pid" class="pil2 diet">{{ permissieNaam(pid) }}</span></div>
        </div>
      </div>
    </div>
  </div>

  <GebruikerDrawer :open="drawer.open" :gebruiker="drawer.gebruiker" :rollen="data.rollen" :permissies="data.permissies"
                   @sluit="drawer.open = false" @opgeslagen="opgeslagen" />
</template>

<style scoped>
#lijst { --kolommen: 22px 2fr 2.2fr 1.2fr 1.4fr .9fr; }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--green); color: #fff;
          display: grid; place-items: center; font-weight: 700; font-size: 13px; }
.gnaam { font-size: 12px; color: var(--ink-3); }
.afkap { overflow: hidden; text-overflow: ellipsis; }
.rechtenlijst { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 22px; }
.oms { font-weight: 400; font-size: 12.5px; color: var(--ink-3); }
@media (max-width: 1000px) {
  #lijst { --kolommen: 22px 2fr 1.2fr .9fr; }
  .c-mail, .c-login { display: none; }
  .rechtenlijst { grid-template-columns: 1fr; }
}
</style>
