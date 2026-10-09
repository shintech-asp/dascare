<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 sm:top-5 sm:right-5 z-[10060] flex flex-col gap-2.5 sm:gap-3 pointer-events-none max-w-[calc(100vw-2rem)]">
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