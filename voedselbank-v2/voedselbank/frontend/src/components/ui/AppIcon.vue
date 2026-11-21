<script setup>
/**
 * Lijnicoontjes (SVG). Gebruik: <AppIcon naam="klanten" :grootte="17" />
 */
defineProps({
  naam: { type: String, required: true },
  grootte: { type: Number, default: 17 },
  dikte: { type: Number, default: 1.7 },
});

const PADEN = {
  dashboard: '<path d="M3 3h7v7H3zM14 3h7v5h-7zM14 11h7v10h-7zM3 13h7v8H3z"/>',
  pakketten: '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
  uitgifte: '<path d="M4 7h16v13H4z"/><path d="M9 7V4h6v3M9 13h6"/>',
  producten: '<path d="M4 6h16v14H4z"/><path d="M4 10h16M9 6V3h6v3"/>',
  leveranciers: '<path d="M2 8h11v9H2zM13 11h5l3 3v3h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
  klanten: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 8.5a3 3 0 100-5M18 20c0-2.5-1-4.2-2.5-5.2"/>',
  rapportages: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  gebruikers: '<circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
  account: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>',
  uitloggen: '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M15 4h4a1 1 0 011 1v14a1 1 0 01-1 1h-4"/>',
  sluiten: '<path d="M18 6L6 18M6 6l12 12"/>',
  zoeken: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
  chevron: '<path d="M6 9l6 6 6-6"/>',
  prullenbak: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
  bewerken: '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
  voorraad: '<path d="M8 9l4-4 4 4M8 15l4 4 4-4"/>',
};
</script>

<template>
  <svg :width="grootte" :height="grootte" viewBox="0 0 24 24" fill="none" stroke="currentColor"
       :stroke-width="dikte" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
       v-html="PADEN[naam] || PADEN.dashboard" />
</template>
