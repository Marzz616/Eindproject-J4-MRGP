<script setup>
/**
 * Een venster midden in beeld met een grijze laag erachter.
 * Sluit met Escape, met een klik op de achtergrond of met de knoppen in #voet.
 *
 *   <BaseModal :open="open" titel="Categorieën" @sluit="open = false">
 *     inhoud...
 *     <template #voet><button class="knop">Sluiten</button></template>
 *   </BaseModal>
 */
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  titel: { type: String, default: '' },
  breed: { type: Boolean, default: false },
});
const emit = defineEmits(['sluit']);

function toets(e) {
  if (e.key === 'Escape') emit('sluit');
}
watch(() => props.open, (open) => {
  if (open) document.addEventListener('keydown', toets);
  else document.removeEventListener('keydown', toets);
}, { immediate: true });
onBeforeUnmount(() => document.removeEventListener('keydown', toets));
</script>

<template>
  <Teleport to="body">
    <template v-if="open">
      <div class="overlay open" @click="emit('sluit')" />
      <div class="modal open" :class="{ breed }" role="dialog" aria-modal="true" :aria-label="titel">
        <div class="kop"><h2>{{ titel }}</h2></div>
        <div class="body"><slot /></div>
        <div class="voet"><slot name="voet" /></div>
      </div>
    </template>
  </Teleport>
</template>

<style scoped>
.modal.breed { width: min(680px, calc(100% - 32px)); }
</style>
