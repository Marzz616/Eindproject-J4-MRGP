<script setup>
/**
 * De buitenste component. De pagina zelf komt uit de router; meldingen
 * (toasts) en vragen (dialogen) liggen er overheen en zijn overal beschikbaar.
 */
import ToastHost from '@/components/ui/ToastHost.vue';
import DialogHost from '@/components/ui/DialogHost.vue';
</script>

<template>
  <RouterView />
  <ToastHost />
  <DialogHost />
</template>
