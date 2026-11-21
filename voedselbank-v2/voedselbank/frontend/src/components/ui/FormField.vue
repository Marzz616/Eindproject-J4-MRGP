<script setup>
/**
 * Eén formulierveld met label, hulptekst en foutmelding van de server.
 * Het invoerelement zelf zet je in de slot:
 *
 *   <FormField label="E-mailadres" for="k-mail" :fout="fouten.email">
 *     <input id="k-mail" v-model="form.email" type="email">
 *   </FormField>
 */
defineProps({
  label: { type: String, default: '' },
  for: { type: String, default: '' },
  hint: { type: String, default: '' },
  fout: { type: String, default: '' },
});
</script>

<template>
  <div class="veld" :class="{ mis: !!fout }">
    <label v-if="label" :for="$props.for">{{ label }}</label>
    <slot />
    <div v-if="hint && !fout" class="hint">{{ hint }}</div>
    <div v-if="fout" class="fout">{{ fout }}</div>
  </div>
</template>
