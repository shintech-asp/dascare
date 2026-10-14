<template>
  <!-- Roles & Permissions (staff, Phase 4) — the web's Access Control:
       the organization's roles (system starter roles + custom ones) and each
       operational member's roles. Role editing is its own screen. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Roles & Permissions" fallback="/staff/more">
      <template #actions>
        <RouterLink v-if="canCreate" :to="{ name: 'StaffRoleEdit', params: { id: 'new' } }" class="tap grid h-11 w-11 place-items-center rounded-xl text-red-600 dark:text-red-300" aria-label="New role"><Icon icon="lucide:plus" width="22" /></RouterLink>
      </template>
    </ScreenHeader>
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-2 gap-2 rounded-2xl bg-base-100 p-1 dark:bg-white/5">
        <button v-for="t in ['roles', 'members']" :key="t" type="button" class="rounded-xl py-2.5 text-xs font-black capitalize" :class="tab === t ? 'bg-red-600 text-white' : 'text-slate-500 dark:text-white/50'" @click="tab = t">{{ t === 'roles' ? 'Roles' : 'Member access' }}</button>
      </div>

      <template v-if="tab === 'roles'">
        <RouterLink v-for="r in roles" :key="r.id" :to="{ name: 'StaffRoleEdit', params: { id: r.id } }" :class="[ui.card, 'tap block p-4 no-underline']">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-black text-slate-900 dark:text-white">{{ r.role_name }}</p>
              <p class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-white/45">{{ r.description || 'No description' }}</p>
            </div>
            <span v-if="r.is_system" class="flex-shrink-0 rounded-full bg-base-200 px-2 py-0.5 text-[0.6rem] font-black uppercase text-slate-500 dark:bg-white/5 dark:text-white/45">Starter</span>
          </div>
          <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-white/45">{{ (r.permission_ids || []).length }} permissions<template v-if="r.member_count != null"> · {{ r.member_count }} member{{ r.member_count === 1 ? '' : 's' }}</template></p>
        </RouterLink>
      </template>

      <template v-else>
        <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
          <button v-for="m in operational" :key="m.id" type="button" class="tap flex w-full items-center justify-between gap-3 p-4 text-left" :disabled="!canUpdate" @click="openMember(m)">
            <span class="min-w-0">
              <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ m.first_name }} {{ m.last_name }}</span>
              <span class="block truncate text-xs text-slate-500 dark:text-white/45">{{ (m.role_names || []).join(' · ') || 'No role — can only acknowledge assigned missions' }}</span>
            </span>
            <Icon v-if="canUpdate" icon="lucide:chevron-right" width="18" class="text-slate-400" />
          </button>
          <p v-if="!operational.length" class="p-6 text-center text-sm text-slate-500">No operational members yet.</p>
        </section>
        <p class="px-2 text-xs text-slate-400">Organization admins have every permission and aren't listed.</p>
      </template>
    </main>

    <Teleport to="body">
      <div v-if="member" class="fixed inset-0 z-[10040] flex items-end bg-black/50" @click.self="member = null">
        <div class="max-h-[85vh] w-full space-y-3 overflow-y-auto rounded-t-[28px] bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#0c1a2c]">
          <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ member.first_name }} {{ member.last_name }}'s roles</h2>
          <label v-for="r in roles" :key="r.id" class="flex items-center gap-3 rounded-2xl border border-base-300 p-3 dark:border-white/10">
            <input v-model="memberRoleIds" type="checkbox" :value="r.id" class="h-5 w-5 accent-red-600" />
            <span class="min-w-0 flex-1"><span class="block text-sm font-bold text-slate-800 dark:text-white/80">{{ r.role_name }}</span><span class="block truncate text-xs text-slate-500">{{ (r.permission_ids || []).length }} permissions</span></span>
          </label>
          <div class="grid grid-cols-2 gap-2"><button type="button" :class="ui.secondaryButton" @click="member = null">Cancel</button><button type="button" :disabled="saving" :class="ui.primaryButton" @click="saveMember">Save</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { fetchOrganizationAccessCatalog, fetchOrganizationMembers, updateOrganizationMemberRoles } from '@/services/org/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const { hasPermission } = useSession()
const canCreate = computed(() => hasPermission('rbac.roles.create'))
const canUpdate = computed(() => hasPermission('rbac.roles.update'))
const tab = ref('roles')
const roles = ref([])
const members = ref([])
const member = ref(null)
const memberRoleIds = ref([])
const saving = ref(false)
const operational = computed(() => members.value.filter((m) => m.account_role !== 'organization_admin'))

function openMember(m) { member.value = m; memberRoleIds.value = roles.value.filter((r) => (m.role_names || []).includes(r.role_name)).map((r) => r.id) }
async function saveMember() {
  saving.value = true
  try {
    const d = await updateOrganizationMemberRoles(member.value.id, memberRoleIds.value.map(Number))
    toast.success(d.message || 'Access updated.', 'Saved')
    member.value = null
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Could not update access.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
async function load() {
  try {
    const [a, m] = await Promise.all([fetchOrganizationAccessCatalog(), fetchOrganizationMembers()])
    roles.value = a.roles || []
    members.value = m.members || []
  } catch (err) {
    toast.error(apiMessage(err, 'Could not load roles.'), 'Error')
  }
}
onMounted(load)
</script>
