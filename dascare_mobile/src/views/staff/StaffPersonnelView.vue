<template>
  <!-- Personnel (staff, Phase 4) — the web's Personnel: members, pending
       invitations, invite a member (link to share), and per member the
       membership status (with a reason when restricting) and availability. -->
  <div class="min-h-screen bg-base-200 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#050e1a]">
    <ScreenHeader title="Personnel" fallback="/staff/more">
      <template #actions>
        <button v-if="canInvite" type="button" class="tap grid h-11 w-11 place-items-center rounded-xl text-red-600 dark:text-red-300" aria-label="Invite member" @click="openInvite"><Icon icon="lucide:user-plus" width="21" /></button>
      </template>
    </ScreenHeader>
    <main class="space-y-3 px-4 pt-3">
      <div class="grid grid-cols-4 gap-2">
        <div v-for="s in statTiles" :key="s.label" :class="[ui.card, 'p-2.5 text-center']">
          <p class="text-lg font-black text-slate-950 dark:text-white">{{ s.value }}</p>
          <p class="text-[0.6rem] font-semibold leading-tight text-slate-500 dark:text-white/45">{{ s.label }}</p>
        </div>
      </div>
      <div class="relative">
        <Icon icon="lucide:search" width="18" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
        <input v-model="search" :class="[ui.input, 'pl-10']" placeholder="Name, email, role…" />
      </div>

      <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
        <button v-for="m in shown" :key="m.id" type="button" class="tap flex w-full items-center gap-3 p-4 text-left" @click="openManage(m)">
          <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-red-50 text-sm font-black text-red-600 dark:bg-red-500/10 dark:text-red-300">{{ initials(m) }}</span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-bold text-slate-900 dark:text-white">{{ m.first_name }} {{ m.last_name }}</span>
            <span class="block truncate text-xs text-slate-500 dark:text-white/45">{{ m.account_role === 'organization_admin' ? 'Organization admin' : (m.role_names || []).join(' · ') || 'No role yet' }}</span>
          </span>
          <span class="flex-shrink-0 text-right">
            <span class="block text-[0.62rem] font-black uppercase" :class="m.membership_status === 'active' ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-400'">{{ pretty(m.membership_status) }}</span>
            <span v-if="m.availability_status" class="block text-[0.62rem] capitalize text-slate-500">{{ pretty(m.availability_status) }}</span>
          </span>
        </button>
        <p v-if="!loading && !shown.length" class="p-6 text-center text-sm text-slate-500">No members match.</p>
      </section>

      <template v-if="invitations.length">
        <p class="px-2 pt-2 text-[0.68rem] font-black uppercase tracking-[0.16em] text-slate-400 dark:text-white/35">Pending invitations</p>
        <section :class="[ui.card, 'divide-y divide-base-300 dark:divide-white/10']">
          <div v-for="i in invitations" :key="i.id" class="flex items-center justify-between gap-3 p-4">
            <div class="min-w-0">
              <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ i.first_name }} {{ i.last_name }}</p>
              <p class="truncate text-xs text-slate-500 dark:text-white/45">{{ i.email }}<template v-if="i.role_name"> · {{ i.role_name }}</template></p>
            </div>
            <button v-if="canInvite" type="button" class="text-xs font-bold text-red-600 dark:text-red-300" @click="cancelInvite(i)">Cancel</button>
          </div>
        </section>
      </template>
    </main>

    <Teleport to="body">
      <div v-if="sheet" class="fixed inset-0 z-[10040] flex items-end bg-black/50" @click.self="sheet = null">
        <div class="max-h-[90vh] w-full space-y-3 overflow-y-auto rounded-t-[28px] bg-base-100 p-6 pb-[calc(var(--safe-bottom)+1.5rem)] dark:bg-[#0c1a2c]">
          <!-- Manage member -->
          <form v-if="sheet === 'manage'" class="space-y-3" @submit.prevent="saveManage">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ managed.first_name }} {{ managed.last_name }}</h2>
            <p class="text-xs text-slate-500">{{ managed.email }}<template v-if="managed.employee_code"> · {{ managed.employee_code }}</template></p>
            <div v-if="canManage && managed.account_role !== 'organization_admin'">
              <label :class="ui.label">Membership</label>
              <div class="grid grid-cols-3 gap-2"><button v-for="s in MEMBERSHIP" :key="s.value" type="button" class="rounded-xl border-2 py-2.5 text-xs font-black" :class="manageForm.membership_status === s.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="manageForm.membership_status = s.value">{{ s.label }}</button></div>
            </div>
            <div v-if="canAvailability && manageForm.membership_status === 'active' && managed.availability_status !== 'assigned'">
              <label :class="ui.label">Availability</label>
              <div class="grid grid-cols-2 gap-2"><button v-for="s in AVAILABILITY" :key="s.value" type="button" class="rounded-xl border-2 py-2.5 text-xs font-black" :class="manageForm.availability_status === s.value ? 'border-red-600 bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'border-base-300 text-slate-600 dark:border-white/10 dark:text-white/60'" @click="manageForm.availability_status = s.value">{{ s.label }}</button></div>
            </div>
            <p v-if="managed.availability_status === 'assigned'" class="text-xs text-blue-600 dark:text-blue-300">On a mission — availability frees up when it ends.</p>
            <div><label :class="ui.label">Note {{ needsReason ? '(required, min. 5 characters)' : '(optional)' }}</label><input v-model="manageForm.note" :class="ui.input" /></div>
            <div class="grid grid-cols-2 gap-2"><button type="button" :class="ui.secondaryButton" @click="sheet = null">Cancel</button><button type="submit" :disabled="saving" :class="ui.primaryButton">Save</button></div>
          </form>

          <!-- Invite -->
          <form v-else-if="sheet === 'invite' && !inviteResult" class="space-y-3" @submit.prevent="sendInvite">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Invite a member</h2>
            <div class="grid grid-cols-2 gap-3">
              <div><label :class="ui.label">First name</label><input v-model="invite.first_name" :class="ui.input" required /></div>
              <div><label :class="ui.label">Last name</label><input v-model="invite.last_name" :class="ui.input" required /></div>
            </div>
            <div><label :class="ui.label">Email</label><input v-model="invite.email" type="email" :class="ui.input" required /></div>
            <div><label :class="ui.label">Mobile number</label><input v-model="invite.phone" inputmode="tel" :class="ui.input" placeholder="09XXXXXXXXX" /></div>
            <div><label :class="ui.label">Role</label><select v-model="invite.role_id" :class="ui.input" required><option value="">Select a role</option><option v-for="r in roles" :key="r.id" :value="r.id">{{ r.role_name }}</option></select></div>
            <div class="grid grid-cols-2 gap-3">
              <div><label :class="ui.label">Employee code</label><input v-model="invite.employee_code" :class="ui.input" /></div>
              <div><label :class="ui.label">License no.</label><input v-model="invite.license_number" :class="ui.input" /></div>
            </div>
            <div><label :class="ui.label">Certifications</label><input v-model="invite.certification_details" :class="ui.input" /></div>
            <div class="grid grid-cols-2 gap-2"><button type="button" :class="ui.secondaryButton" @click="sheet = null">Cancel</button><button type="submit" :disabled="saving" :class="ui.primaryButton">Send invite</button></div>
          </form>
          <div v-else-if="sheet === 'invite' && inviteResult" class="space-y-3">
            <h2 class="text-lg font-black text-slate-900 dark:text-white">Invitation created</h2>
            <p class="text-sm text-slate-600 dark:text-white/60">{{ inviteResult.email_sent ? 'We emailed the invitation. You can also share the link:' : 'The email could not be sent — share this link with them:' }}</p>
            <p class="selectable break-all rounded-xl bg-base-200 p-3 font-mono text-xs dark:bg-white/5">{{ inviteResult.invite_url }}</p>
            <div class="grid grid-cols-2 gap-2"><button type="button" :class="ui.secondaryButton" @click="sheet = null">Done</button><button type="button" :class="ui.primaryButton" @click="shareInvite">Share link</button></div>
          </div>
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
import { cancelOrganizationInvitation, fetchOrganizationMembers, fetchOrganizationRoles, inviteOrganizationMember, updateOrganizationMemberAvailability, updateOrganizationMemberStatus } from '@/services/org/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const toast = useToast()
const alert = useAlert()
const { hasPermission } = useSession()
const canInvite = computed(() => hasPermission('hr.members.create'))
const canManage = computed(() => hasPermission('hr.members.update'))
const canAvailability = computed(() => hasPermission('hr.availability.update'))
const MEMBERSHIP = [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Paused' }, { value: 'terminated', label: 'Terminated' }]
const AVAILABILITY = [{ value: 'available', label: 'Available' }, { value: 'off_duty', label: 'Off duty' }, { value: 'leave', label: 'Leave' }, { value: 'unavailable', label: 'Unavailable' }]

const members = ref([])
const invitations = ref([])
const roles = ref([])
const stats = ref({})
const loading = ref(true)
const search = ref('')
const sheet = ref(null)
const saving = ref(false)
const managed = ref(null)
const manageForm = ref({})
const invite = ref({})
const inviteResult = ref(null)

const statTiles = computed(() => [{ label: 'Members', value: stats.value.members ?? 0 }, { label: 'Active', value: stats.value.active ?? 0 }, { label: 'Available', value: stats.value.available ?? 0 }, { label: 'Invites', value: stats.value.pending_invites ?? 0 }])
const shown = computed(() => { const q = search.value.trim().toLowerCase(); return members.value.filter((m) => !q || `${m.first_name} ${m.last_name} ${m.email} ${(m.role_names || []).join(' ')}`.toLowerCase().includes(q)) })
const pretty = (v) => String(v || '').replaceAll('_', ' ')
const initials = (m) => `${(m.first_name || '?')[0]}${(m.last_name || '')[0] || ''}`.toUpperCase()
const needsReason = computed(() => managed.value && manageForm.value.membership_status !== 'active' && manageForm.value.membership_status !== managed.value.membership_status)

function openManage(m) { managed.value = m; manageForm.value = { membership_status: m.membership_status || 'active', availability_status: m.availability_status || 'off_duty', note: '' }; sheet.value = 'manage' }
async function saveManage() {
  const m = managed.value
  if (needsReason.value && manageForm.value.note.trim().length < 5) { toast.error('Give a short reason for restricting this member.', 'Reason needed'); return }
  saving.value = true
  try {
    if (canManage.value && manageForm.value.membership_status !== m.membership_status) await updateOrganizationMemberStatus(m.id, manageForm.value.membership_status, manageForm.value.note.trim())
    if (canAvailability.value && manageForm.value.membership_status === 'active' && m.availability_status !== 'assigned' && manageForm.value.availability_status !== (m.availability_status || 'off_duty')) {
      await updateOrganizationMemberAvailability(m.id, manageForm.value.availability_status, manageForm.value.note.trim() || 'Updated from the staff app.')
    }
    toast.success('Member updated.', 'Saved')
    sheet.value = null
    await load()
  } catch (err) {
    toast.error(apiMessage(err, 'Could not update the member.'), 'Not saved')
  } finally {
    saving.value = false
  }
}
function openInvite() { inviteResult.value = null; invite.value = { first_name: '', last_name: '', email: '', phone: '', role_id: '', employee_code: '', license_number: '', certification_details: '' }; sheet.value = 'invite' }
async function sendInvite() {
  saving.value = true
  try { inviteResult.value = await inviteOrganizationMember(invite.value); toast.success(inviteResult.value.message || 'Invitation created.', 'Invited'); await load() } catch (err) { toast.error(apiMessage(err, 'Could not send the invitation.'), 'Not sent') } finally { saving.value = false }
}
async function shareInvite() {
  const url = inviteResult.value?.invite_url
  try { if (navigator.share) await navigator.share({ title: 'Join our team on DASCARE', url }); else { await navigator.clipboard.writeText(url); toast.success('Invite link copied.', 'Copied') } } catch { /* cancelled */ }
}
async function cancelInvite(i) {
  if (!(await alert.confirm(`Cancel the invitation for ${i.first_name} ${i.last_name}?`, 'Cancel invitation'))) return
  try { const r = await cancelOrganizationInvitation(i.id); toast.success(r.message || 'Invitation cancelled.', 'Cancelled'); await load() } catch (err) { toast.error(apiMessage(err, 'Could not cancel the invitation.'), 'Error') }
}
async function load() {
  try {
    const [m, r] = await Promise.all([fetchOrganizationMembers(), fetchOrganizationRoles().catch(() => ({}))])
    members.value = m.members || []
    invitations.value = m.pending_invitations || []
    stats.value = m.stats || {}
    roles.value = r.roles || []
  } catch (err) {
    toast.error(apiMessage(err, 'Could not load personnel.'), 'Error')
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
