<script setup>
/**
 * Uitklapbare tabelrij (klanten, leveranciers, gebruikers).
 * De hoofdrij komt in #rij, de details in de standaard-slot.
 */
import AppIcon from './AppIcon.vue';

const open = defineModel('open', { type: Boolean, default: false });
</script>

<template>
  <div class="grp" :class="{ open }">
    <div class="trow" role="button" :aria-expanded="open" tabindex="0"
         @click="open = !open" @keydown.enter.prevent="open = !open">
      <AppIcon class="chev" naam="chevron" :grootte="14" :dikte="2.4" />
      <slot name="rij" />
    </div>
    <div class="detail">
      <slot />
    </div>
  </div>
</template>
