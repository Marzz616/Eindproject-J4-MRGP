<script setup>
/**
 * Vaste schermindeling van de back-office: zijbalk links, bovenbalk en de
 * pagina zelf (RouterView) rechts.
 */
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';
</script>

<template>
  <div class="app">
    <AppSidebar />
    <main class="main">
      <AppTopbar />
      <div class="inhoud">
        <RouterView />
      </div>
    </main>
  </div>
</template>
