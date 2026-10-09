<template>
  <div class="min-h-screen bg-base-200 text-base-content">
    <AppHeader />
    <DascareCursorTrail />
    <main>
      <RouterView />
    </main>
    <Footer />
  </div>
</template>

<script setup>
import AppHeader from '@/components/AppHeader.vue'
import Footer from '@/components/AppFooter.vue'
import DascareCursorTrail from '@/components/effects/DascareCursorTrail.vue'
</script>