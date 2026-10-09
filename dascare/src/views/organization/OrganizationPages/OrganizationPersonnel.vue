<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <div class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.12em] text-red-700 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:users-round" width="14" /> Organization resources</div>
          <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Personnel & Members</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/45">Invite organization employees, assign an operational role, and track who has joined your DASCARE workspace.</p>
        </div>
        <button v-if="canInvite" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-red-600/15 hover:bg-red-700" @click="openInvite"><Icon icon="lucide:user-plus" width="16" /> Invite Employee</button>
      </header>

      <div v-if="loading" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="n in 4" :key="n" class="h-32 animate-pulse rounded-3xl bg-base-100 dark:bg-[#0d2943]"></div></div>
      <template v-else>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Stat label="Organization Members" :value="stats.members" icon="lucide:users" hint="Active and administrative members" />
          <Stat label="Active Memberships" :value="stats.active" icon="lucide:user-check" hint="Members currently enabled" />
          <Stat label="Available Personnel" :value="stats.available" icon="lucide:badge-check" hint="Marked available for operations" />
          <Stat label="Pending Invitations" :value="stats.pending_invites" icon="lucide:mail-plus" hint="Invites awaiting acceptance" />
        </div>

        <div v-if="errorMessage" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">{{ errorMessage }}</div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
          <section class="overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
            <div class="flex flex-col gap-3 border-b border-base-300 p-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:p-6">
              <div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Team directory</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Organization Members</h2></div>
              <div class="relative w-full sm:w-64"><Icon icon="lucide:search" width="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="w-full rounded-xl border border-base-300 bg-base-200 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-[#1976D2] dark:border-white/10 dark:bg-white/[0.035] dark:text-white" placeholder="Search personnel" /></div>
            </div>

            <div v-if="!filteredMembers.length" class="p-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/25"><Icon icon="lucide:users" width="24" /></span><h3 class="mt-4 text-sm font-black text-slate-800 dark:text-white/70">No matching personnel</h3><p class="mt-1 text-xs text-slate-400">Invite employees to begin building the organization team.</p></div>
            <div v-else class="divide-y divide-base-300 dark:divide-white/5">
              <article v-for="member in filteredMembers" :key="member.id" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div class="flex min-w-0 items-center gap-4">
                  <div class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#1976D2]/10 text-sm font-black text-[#1976D2] dark:text-[#7fb3ec]">{{ initials(member) }}</div>
                  <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="truncate text-sm font-black text-slate-900 dark:text-white">{{ member.first_name }} {{ member.last_name }}</p><span v-if="member.account_role === 'organization_admin'" class="rounded-full bg-red-50 px-2 py-0.5 text-[0.62rem] font-black uppercase text-red-600 dark:bg-red-500/10 dark:text-red-300">Admin</span></div><p class="mt-1 truncate text-xs text-slate-400">{{ member.email }} · {{ member.employee_code || 'No employee code' }}</p><div class="mt-2 flex flex-wrap gap-1.5"><span v-for="role in member.role_names" :key="role" class="rounded-full bg-base-200 px-2.5 py-1 text-[0.64rem] font-bold text-slate-600 dark:bg-white/5 dark:text-white/40">{{ role }}</span><span v-if="!member.role_names.length && member.account_role !== 'organization_admin'" class="text-[0.66rem] text-amber-600 dark:text-amber-300">No role assigned</span></div></div>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:justify-end"><span class="rounded-full px-2.5 py-1 text-[0.65rem] font-bold" :class="member.membership_status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : member.membership_status === 'terminated' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40'">{{ member.membership_status }}</span><span v-if="member.availability_status" class="rounded-full bg-blue-50 px-2.5 py-1 text-[0.65rem] font-bold text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">{{ availabilityLabel(member.availability_status) }}</span><button v-if="member.account_role !== 'organization_admin' && (canManageMembers || canAvailability)" class="rounded-xl border border-base-300 bg-base-100 px-3 py-2 text-[0.68rem] font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:bg-white/5 dark:text-white/50" @click="openMemberManage(member)"><Icon icon="lucide:sliders-horizontal" width="13" class="mr-1 inline"/>Manage</button></div>
              </article>
            </div>
          </section>

          <aside class="space-y-6">
            <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
              <div class="flex items-center justify-between"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-[#1976D2] dark:text-[#7fb3ec]">Pending access</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Invitations</h2></div><Icon icon="lucide:mail-plus" width="20" class="text-slate-300 dark:text-white/20" /></div>
              <div class="mt-4 space-y-3">
                <div v-for="invite in invitations" :key="invite.id" class="rounded-2xl border border-base-300 p-3.5 dark:border-white/5"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-xs font-black text-slate-800 dark:text-white/65">{{ invite.first_name }} {{ invite.last_name }}</p><p class="mt-0.5 truncate text-[0.68rem] text-slate-400">{{ invite.email }}</p></div><button v-if="canInvite" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="Cancel invitation" @click="cancelInvite(invite)"><Icon icon="lucide:x" width="14" /></button></div><div class="mt-2 flex items-center justify-between gap-2"><span class="rounded-full bg-blue-50 px-2 py-1 text-[0.62rem] font-bold text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">{{ invite.role_name }}</span><span class="text-[0.62rem] text-slate-400">Expires {{ shortDate(invite.expires_at) }}</span></div></div>
                <p v-if="!invitations.length" class="rounded-2xl bg-base-200 p-4 text-xs leading-5 text-slate-400 dark:bg-white/[0.03]">No pending employee invitations.</p>
              </div>
            </section>

            <section class="rounded-[28px] border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6">
              <div class="flex items-center justify-between"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Starter access</p><h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Operational Roles</h2></div><Icon icon="lucide:key-round" width="20" class="text-slate-300 dark:text-white/20" /></div>
              <div class="mt-4 space-y-3"><div v-for="role in roles" :key="role.id" class="rounded-2xl bg-base-200 p-3.5 dark:bg-white/[0.035]"><div class="flex items-center justify-between gap-2"><p class="text-xs font-black text-slate-800 dark:text-white/65">{{ role.role_name }}</p><span class="text-[0.62rem] font-bold text-slate-400">{{ role.member_count }} member{{ role.member_count === 1 ? '' : 's' }}</span></div><p class="mt-1 text-[0.68rem] leading-5 text-slate-400 dark:text-white/30">{{ role.description }}</p></div></div>
              <p class="mt-4 text-[0.68rem] leading-5 text-slate-400 dark:text-white/30">Starter roles are protected defaults. Open Roles & Permissions to create custom access combinations and assign them to employees.</p>
            </section>
          </aside>
        </div>
      </template>

      <div v-if="inviteOpen" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="inviteOpen=false">
        <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]">
          <header class="flex items-start justify-between gap-4 border-b border-base-300 p-5 dark:border-white/10 sm:p-6"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Employee onboarding</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Invite Organization Employee</h2><p class="mt-1 text-xs leading-5 text-slate-400">The employee receives a secure activation link and creates their own password.</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200 dark:hover:bg-base-100/5" @click="inviteOpen=false"><Icon icon="lucide:x" width="18" /></button></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            <Field label="First name"><input v-model.trim="inviteForm.first_name" class="field" maxlength="80" /></Field>
            <Field label="Last name"><input v-model.trim="inviteForm.last_name" class="field" maxlength="80" /></Field>
            <Field label="Email address"><input v-model.trim="inviteForm.email" class="field" type="email" maxlength="190" /></Field>
            <Field label="Mobile number"><input v-model.trim="inviteForm.phone" class="field" maxlength="13" placeholder="09123456789" /></Field>
            <Field label="Operational role"><select v-model="inviteForm.role_id" class="field"><option value="" disabled>Select a role</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.role_name }}</option></select></Field>
            <Field label="Employee code" hint="Optional"><input v-model.trim="inviteForm.employee_code" class="field" maxlength="50" placeholder="Auto-generated if blank" /></Field>
            <Field label="License / credential number" hint="Optional"><input v-model.trim="inviteForm.license_number" class="field" maxlength="100" /></Field>
            <Field label="Certification / qualification" hint="Optional"><input v-model.trim="inviteForm.certification_details" class="field" maxlength="2000" placeholder="e.g. EMT-B, BLS, First Aid" /></Field>
          </div>
          <div v-if="inviteResult?.invite_url" class="mx-5 mb-4 rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/20 dark:bg-blue-500/10 sm:mx-6"><p class="text-xs font-black text-blue-700 dark:text-blue-200">Invitation created</p><p class="mt-1 text-[0.68rem] leading-5 text-blue-600 dark:text-blue-300">{{ inviteResult.email_sent ? 'The invite was emailed successfully. You can also copy the link below for testing.' : 'Email sending failed, but the invitation is valid. Copy this link to the employee.' }}</p><div class="mt-3 flex gap-2"><input :value="inviteResult.invite_url" readonly class="min-w-0 flex-1 rounded-xl border border-blue-200 bg-base-100 px-3 py-2 text-xs text-slate-600 dark:border-blue-500/20 dark:bg-[#071829] dark:text-white/60" /><button class="rounded-xl bg-[#1976D2] px-3 text-xs font-bold text-white" @click="copyInvite">Copy</button></div></div>
          <footer class="flex justify-end gap-3 border-t border-base-300 p-5 dark:border-white/10 sm:p-6"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/50" @click="inviteOpen=false">Close</button><button v-if="!inviteResult" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="inviting" @click="sendInvite">{{ inviting ? 'Sending…' : 'Send Invitation' }}</button></footer>
        </div>
      </div>

      <div v-if="memberManageOpen" class="fixed inset-0 z-[92] grid place-items-center bg-slate-950/55 p-4 backdrop-blur-sm" @click.self="memberManageOpen=false">
        <section class="w-full max-w-xl overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]">
          <header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10 sm:p-6"><div><p class="text-[0.68rem] font-black uppercase tracking-[0.14em] text-red-600">Personnel control</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ managedMember?.first_name }} {{ managedMember?.last_name }}</h2><p class="mt-1 text-xs text-slate-400">Update access status and operational availability.</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200" @click="memberManageOpen=false"><Icon icon="lucide:x" width="18"/></button></header>
          <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
            <Field label="Membership status"><select v-model="memberManageForm.membership_status" class="field" :disabled="!canManageMembers"><option value="active">Active</option><option value="inactive">Inactive / paused</option><option value="terminated">Terminated</option></select></Field>
            <Field label="Duty availability"><select v-model="memberManageForm.availability_status" class="field" :disabled="!canAvailability || memberManageForm.membership_status !== 'active'"><option value="available">Available</option><option value="assigned">Assigned</option><option value="off_duty">Off duty</option><option value="leave">On leave</option><option value="unavailable">Unavailable</option></select></Field>
            <label class="block sm:col-span-2"><span class="mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400">Administrative note</span><textarea v-model.trim="memberManageForm.note" rows="3" maxlength="255" class="field resize-none" placeholder="Required when pausing or terminating access"></textarea></label>
          </div>
          <footer class="flex justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10 sm:p-6"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600" @click="memberManageOpen=false">Cancel</button><button class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="memberSaving" @click="saveMemberManage">{{ memberSaving ? 'Saving…' : 'Save Changes' }}</button></footer>
        </section>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { cancelOrganizationInvitation, fetchOrganizationMembers, fetchOrganizationRoles, inviteOrganizationMember, updateOrganizationMemberAvailability, updateOrganizationMemberStatus } from '@/services/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const { hasPermission } = useSession(); const toast=useToast(); const alert=useAlert()
const loading=ref(true), errorMessage=ref(''), search=ref(''), members=ref([]), invitations=ref([]), roles=ref([]), stats=ref({members:0,active:0,available:0,pending_invites:0})
const inviteOpen=ref(false), inviting=ref(false), inviteResult=ref(null)
const memberManageOpen=ref(false), memberSaving=ref(false), managedMember=ref(null)
const memberManageForm=ref({membership_status:'active',availability_status:'off_duty',note:''})
const inviteForm=ref({first_name:'',last_name:'',email:'',phone:'',role_id:'',employee_code:'',license_number:'',certification_details:''})
const canInvite=computed(()=>hasPermission('hr.members.create'))
const canManageMembers=computed(()=>hasPermission('hr.members.update'))
const canAvailability=computed(()=>hasPermission('hr.availability.update'))
const filteredMembers=computed(()=>{ const q=search.value.trim().toLowerCase(); if(!q) return members.value; return members.value.filter(m=>`${m.first_name} ${m.last_name} ${m.email} ${m.employee_code||''} ${(m.role_names||[]).join(' ')}`.toLowerCase().includes(q)) })
const initials=m=>`${m.first_name?.[0]||''}${m.last_name?.[0]||''}`.toUpperCase()
const availabilityLabel=v=>({off_duty:'Off duty',available:'Available',assigned:'Assigned',leave:'On leave',unavailable:'Unavailable'}[v]||v)
const shortDate=v=>v?new Date(v.replace(' ','T')).toLocaleDateString([], {month:'short',day:'numeric'}):'—'
const Stat=defineComponent({props:{label:String,value:[String,Number],icon:String,hint:String},setup(p){return()=>h('article',{class:'rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]'},[h('div',{class:'flex items-start justify-between'},[h('span',{class:'grid h-10 w-10 place-items-center rounded-2xl bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40'},[h(Icon,{icon:p.icon,width:19})]),h('span',{class:'text-2xl font-black text-slate-950 dark:text-white'},String(p.value??0))]),h('p',{class:'mt-4 text-sm font-black text-slate-800 dark:text-white/70'},p.label),h('p',{class:'mt-1 text-xs leading-5 text-slate-400 dark:text-white/30'},p.hint)])}})
const Field=defineComponent({props:{label:String,hint:String},setup(p,{slots}){return()=>h('label',{class:'block'},[h('span',{class:'mb-1.5 block text-[0.68rem] font-black uppercase tracking-wide text-slate-400 dark:text-white/30'},p.label),slots.default?.(),p.hint?h('span',{class:'mt-1 block text-[0.64rem] text-slate-400 dark:text-white/25'},p.hint):null])}})

async function load(){ loading.value=true; errorMessage.value=''; try{ const [m,r]=await Promise.all([fetchOrganizationMembers(),fetchOrganizationRoles()]); members.value=m.members||[]; invitations.value=m.pending_invitations||[]; stats.value=m.stats||stats.value; roles.value=r.roles||[] }catch(e){errorMessage.value=e?.response?.data?.message||'Could not load organization personnel.'}finally{loading.value=false} }
function openInvite(){ inviteResult.value=null; inviteForm.value={first_name:'',last_name:'',email:'',phone:'',role_id:'',employee_code:'',license_number:'',certification_details:''}; inviteOpen.value=true }
async function sendInvite(){ inviting.value=true; try{ const r=await inviteOrganizationMember(inviteForm.value); inviteResult.value=r; toast.success(r.message); await load() }catch(e){toast.error(e?.response?.data?.message||'Could not send the invitation.')}finally{inviting.value=false} }
async function cancelInvite(invite){ const ok=await alert.confirm(`Cancel the invitation for ${invite.first_name} ${invite.last_name}?`, 'Cancel invitation'); if(!ok) return; try{ const r=await cancelOrganizationInvitation(invite.id); toast.success(r.message); await load() }catch(e){toast.error(e?.response?.data?.message||'Could not cancel the invitation.')} }
async function copyInvite(){ try{ await navigator.clipboard.writeText(inviteResult.value.invite_url); toast.success('Invite link copied.') }catch{ toast.error('Could not copy the invite link.') } }
function openMemberManage(member){ managedMember.value=member; memberManageForm.value={membership_status:member.membership_status||'active',availability_status:member.availability_status||'off_duty',note:''}; memberManageOpen.value=true }
async function saveMemberManage(){ if(!managedMember.value)return; const original=managedMember.value; if(memberManageForm.value.membership_status!=='active' && memberManageForm.value.membership_status!==original.membership_status && memberManageForm.value.note.trim().length<5){toast.error('Provide a short reason for restricting this member.');return} memberSaving.value=true; try{ if(canManageMembers.value && memberManageForm.value.membership_status!==original.membership_status){ await updateOrganizationMemberStatus(original.id,memberManageForm.value.membership_status,memberManageForm.value.note.trim()) } if(canAvailability.value && memberManageForm.value.membership_status==='active' && memberManageForm.value.availability_status!==(original.availability_status||'off_duty')){ await updateOrganizationMemberAvailability(original.id,memberManageForm.value.availability_status,memberManageForm.value.note.trim()) } toast.success('Personnel settings updated.'); memberManageOpen.value=false; await load() }catch(e){toast.error(e?.response?.data?.message||'Could not update this employee.')}finally{memberSaving.value=false} }
onMounted(load)
</script>

<style scoped>
.field {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid var(--color-base-300);
  background: var(--color-base-100);
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  line-height: 1.25rem;
  color: #1e293b;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
.field:focus {
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.10);
}
:global([data-theme="dark"]) .field {
  border-color: rgba(255, 255, 255, 0.10);
  background: #071829;
  color: #ffffff;
}
</style>
