<template>
  <RouterView />
  <AlertProvider />
  <ToastProvider />
</template>

<script setup>
import AlertProvider from '@/components/modals/AlertProvider.vue'
import ToastProvider from '@/components/toasts/ToastProvider.vue'
</script>