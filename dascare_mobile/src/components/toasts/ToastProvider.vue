<!-- Copied from the web (dascare/src/components/toasts/ToastProvider.vue).
     Only change: offset below the phone status bar / notch. -->
<template>
    <Teleport to="body">
        <div class="fixed top-[calc(var(--safe-top)+0.75rem)] right-4 left-4 sm:left-auto z-[10060] items-end flex flex-col gap-2.5 sm:gap-3 pointer-events-none max-w-[calc(100vw-2rem)]">
            <Toast
                v-for="toast in toasts"
                :key="toast.id"
                :title="toast.title"
                :message="toast.message"
                :type="toast.type"
                :duration="toast.duration"
                :onClose="() => remove(toast.id)"
                class="pointer-events-auto"
            />
        </div>
    </Teleport>
</template>

<script setup>
import Toast from './Toast.vue'
import { useToast } from '@/composables/useToast'

const { toasts, remove } = useToast()
</script>