<template>
  <!-- False alarm record (strikes + appeals). Signed-in citizens open it from
       Profile; a citizen suspended for false alarms lands here from Login,
       using the short-lived key the login refusal gave them. -->
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader :title="suspendedMode ? 'Account suspended' : 'False alarm record'" :fallback="suspendedMode ? '/login' : '/profile'" />
    <main class="space-y-4 px-4 pb-[calc(var(--safe-bottom)+6.5rem)] pt-4">
      <p v-if="suspendedMode" class="text-sm leading-6 text-slate-600 dark:text-white/60">{{ notice.message }}</p>
      <p v-else class="text-xs leading-5 text-slate-500 dark:text-white/45">Requests confirmed as false alarms count as strikes. You can appeal a strike once.</p>
      <section :class="[ui.card, 'p-4']">
        <FalseAlarmRecord v-if="suspendedMode" :token="notice.token" :initial="notice.record" />
        <FalseAlarmRecord v-else-if="isLoggedIn" />
        <p v-else class="text-sm text-slate-500">Log in to see your record.</p>
      </section>
      <RouterLink v-if="suspendedMode" :to="{ name: 'Sos' }" class="tap flex items-center justify-center gap-2 rounded-2xl bg-red-600 py-3.5 font-bold text-white no-underline shadow-lg shadow-red-600/25">
        <Icon icon="lucide:siren" width="18" /> Emergency? Send SOS as a guest
      </RouterLink>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import FalseAlarmRecord from '@/components/sanctions/FalseAlarmRecord.vue'
import * as ui from '@/components/ui/styles'
import { useSession } from '@/composables/useSession'
import { useSanctionNotice } from '@/composables/useSanctionNotice'

const { isLoggedIn } = useSession()
const { notice } = useSanctionNotice()
const suspendedMode = computed(() => !isLoggedIn.value && !!notice.value?.token)
</script>
