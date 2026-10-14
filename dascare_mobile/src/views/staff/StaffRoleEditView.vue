<template>
  <!-- Create / edit an organization role (staff, Phase 4) — the web's role
       editor: name, description, permissions grouped by module with
       select-all per module; delete for custom roles. Starter (system)
       roles keep their name. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+6.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader :title="isNew ? 'New role' : (role?.role_name || 'Role')" fallback="/staff/roles" />
    <main class="space-y-3 px-4 pt-3">
      <section :class="[ui.card, 'space-y-3 p-4']">
        <div><label :class="ui.label">Role name</label><input v-model="form.role_name" :disabled="role?.is_system || !canEdit" :class="ui.input" placeholder="e.g. Night-shift driver" /></div>
        <div><label :class="ui.label">Description</label><textarea v-model="form.description" rows="2" :disabled="!canEdit" :class="ui.input"></textarea></div>
        <p v-if="role?.is_system" class="text-xs text-slate-500">Starter role — its name stays; you can still adjust its permissions.</p>
      </section>

      <div class="relative">
        <Icon icon="lucide:search" width="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" :class="[ui.input, 'pl-10']" placeholder="Search permissions…" />
      </div>

      <section v-for="g in groups" :key="g.module_key" :class="[ui.card, 'overflow-hidden']">
        <button type="button" class="tap flex w-full items-center gap-3 p-4 text-left" @click="toggleOpen(g.module_key)">
          <span class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="moduleIcon(g.module_key)" width="17" /></span>
          <span class="min-w-0 flex-1"><span class="block text-sm font-bold text-slate-900 dark:text-white">{{ g.module_name }}</span><span class="block text-xs text-slate-500">{{ selectedIn(g) }} of {{ g.items.length }} selected</span></span>
          <Icon :icon="open.has(g.module_key) ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="18" class="text-slate-400" />
        </button>
        <div v-if="open.has(g.module_key) || search" class="border-t border-base-300 px-4 pb-3 dark:border-white/10">
          <button v-if="canEdit" type="button" class="mt-3 text-xs font-bold text-red-600 dark:text-red-300" @click="toggleGroup(g)">{{ allIn(g) ? 'Clear all' : 'Select all' }}</button>
          <label v-for="p in g.items" :key="p.id" class="flex items-center gap-3 py-2.5" :class="!p.assignable ? 'opacity-50' : ''">
            <input v-model="form.permission_ids" type="checkbox" :value="p.id" :disabled="!canEdit || !p.assignable" class="h-5 w-5 accent-red-600" />
            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold capitalize text-slate-800 dark:text-white/80">{{ pretty(p.resource) }} · {{ pretty(p.action) }}</span><span v-if="p.description" class="block text-xs text-slate-500">{{ p.description }}</span></span>
          </label>
        </div>
      </section>

      <button v-if="!isNew && canDelete && role && !role.is_system" type="button" :class="[ui.secondaryButton, 'border-red-200 text-red-600 dark:border-red-500/25 dark:text-red-300']" @click="remove"><Icon icon="lucide:trash-2" width="16" /> Delete role</button>
    </main>

    <div v-if="canEdit" class="fixed inset-x-0 bottom-0 z-30 border-t border-base-300 bg-base-100/95 p-4 pb-[calc(var(--safe-bottom)+1rem)] backdrop-blur dark:border-white/10 dark:bg-[#071829]/95">
      <button type="button" :disabled="saving" :class="ui.primaryButton" @click="save"><Icon :icon="saving ? 'lucide:loader-circle' : 'lucide:save'" width="18" :class="saving ? 'animate-spin' : ''" /> {{ isNew ? 'Create role' : 'Save role' }} · {{ form.permission_ids.length }} permissions</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'
import * as ui from '@/components/ui/styles'
import { apiMessage } from '@/services/api'
import { createOrganizationRole, deleteOrganizationRole, fetchOrganizationAccessCatalog, updateOrganizationRole } from '@/services/org/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()
const isNew = computed(() => route.params.id === 'new')
const canEdit = computed(() => hasPermission(isNew.value ? 'rbac.roles.create' : 'rbac.roles.update'))
const canDelete = computed(() => hasPermission('rbac.roles.delete'))
const role = ref(null)
const permissions = ref([])
const form = ref({ role_name: '', description: '', permission_ids: [] })
const search = ref('')
const open = ref(new Set())
const saving = ref(false)

const pretty = (v) => String(v || '').replaceAll('_', ' ')
const moduleIcon = (k) => ({ fleet: 'lucide:ambulance', dispatch: 'lucide:radio-tower', incidents: 'lucide:siren', hr: 'lucide:users-round', rbac: 'lucide:key-round', hospital: 'lucide:hospital', analytics: 'lucide:chart-column', org_settings: 'lucide:settings' }[k] || 'lucide:box')
const groups = computed(() => {
  const q = search.value.trim().toLowerCase()
  const map = new Map()
  for (const p of permissions.value) {
    if (q && !`${p.module_name} ${p.resource} ${p.action}`.toLowerCase().includes(q)) continue
    if (!map.has(p.module_key)) map.set(p.module_key, { module_key: p.module_key, module_name: p.module_name, items: [] })
    map.get(p.module_key).items.push(p)
  }
  return [...map.values()]
})
const selectedIn = (g) => g.items.filter((p) => form.value.permission_ids.includes(p.id)).length
const assignableIds = (g) => g.items.filter((p) => p.assignable).map((p) => p.id)
const allIn = (g) => { const ids = assignableIds(g); return ids.length > 0 && ids.every((id) => form.value.permission_ids.includes(id)) }
function toggleGroup(g) {
  const ids = assignableIds(g)
  form.value.permission_ids = allIn(g) ? form.value.permission_ids.filter((id) => !ids.includes(id)) : [...new Set([...form.value.permission_ids, ...ids])]
}
function toggleOpen(k) { const n = new Set(open.value); n.has(k) ? n.delete(k) : n.add(k); open.value = n }

async function save() {
  if (!role.value?.is_system && form.value.role_name.trim().length < 3) { toast.error('Enter a role name of at least 3 characters.', 'Name needed'); return }
  saving.value = true
  try {
    const payload = { ...form.value, permission_ids: form.value.permission_ids.map(Number) }
    const d = isNew.value ? await createOrganizationRole(payload) : await updateOrganizationRole({ ...payload, role_id: role.value.id })
    toast.success(d.message || 'Role saved.', 'Saved')
    router.replace({ name: 'StaffRoles' })
  } catch (err) {
    toast.error(apiMessage(err, 'Could not save this role.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
async function remove() {
  if (!(await alert.confirm(`Delete the custom role "${role.value.role_name}"? Members keep their other roles.`, 'Delete role'))) return
  try {
    const d = await deleteOrganizationRole(role.value.id)
    toast.success(d.message || 'Role deleted.', 'Deleted')
    router.replace({ name: 'StaffRoles' })
  } catch (err) {
    toast.error(apiMessage(err, 'Could not delete this role.'), 'Error')
  }
}
onMounted(async () => {
  try {
    const a = await fetchOrganizationAccessCatalog()
    permissions.value = a.permissions || []
    if (!isNew.value) {
      role.value = (a.roles || []).find((r) => String(r.id) === String(route.params.id)) || null
      if (role.value) form.value = { role_name: role.value.role_name, description: role.value.description || '', permission_ids: [...(role.value.permission_ids || [])] }
    }
  } catch (err) {
    toast.error(apiMessage(err, 'Could not load the role.'), 'Error')
  }
})
</script>
