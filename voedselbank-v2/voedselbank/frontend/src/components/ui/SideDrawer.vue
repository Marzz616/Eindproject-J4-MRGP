<script setup>
/**
 * Paneel dat van rechts inschuift, voor formulieren (toevoegen / bewerken).
 *
 *   <SideDrawer :open="open" titel="Nieuwe klant" onder="..." @sluit="open = false">
 *     ...formulier...
 *     <template #voet>...knoppen...</template>
 *   </SideDrawer>
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  titel: { type: String, required: true },
  onder: { type: String, default: '' },
});
const emit = defineEmits(['sluit']);
const paneel = ref(null);

function toets(e) {
  if (e.key === 'Escape') emit('sluit');
}

watch(() => props.open, async (open) => {
  if (open) {
    document.addEventListener('keydown', toets);
    await nextTick();
    setTimeout(() => paneel.value?.querySelector('.body input, .body select, .body textarea')?.focus(), 180);
  } else {
    document.removeEventListener('keydown', toets);
  }
});
onBeforeUnmount(() => document.removeEventListener('keydown', toets));
</script>

<template>
  <Teleport to="body">
    <div class="overlay" :class="{ open }" @click="emit('sluit')" />
    <aside ref="paneel" class="drawer" :class="{ open }" :aria-hidden="!open" role="dialog" :aria-label="titel">
      <div class="kop">
        <div style="flex:1">
          <h2>{{ titel }}</h2>
          <div v-if="onder" class="onder">{{ onder }}</div>
        </div>
        <button class="icoonknop" type="button" aria-label="Sluiten" @click="emit('sluit')">
          <AppIcon naam="sluiten" :grootte="18" :dikte="2" />
        </button>
      </div>
      <div class="body"><slot /></div>
      <div class="voet"><slot name="voet" /></div>
    </aside>
  </Teleport>
</template>
