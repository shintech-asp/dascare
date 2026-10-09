<template>
  <AlertModal
    v-model:visible="visible"
    :type="type"
    :title="title"
    :message="message"
    :show-cancel="type === 'confirm'"
    @confirm="handleConfirm"
  />
</template>

<script setup>
import AlertModal from './AlertModal.vue'
import { useAlert } from '@/composables/useAlert'

const {
  visible,
  type,
  title,
  message,
  confirmAction,
} = useAlert()

function handleConfirm(confirmed) {
  if (confirmed && confirmAction.value) {
    confirmAction.value()
  }
}
</script>