<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-[.68rem] font-black uppercase tracking-[.12em] text-red-700 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:key-round" width="14"/>Organization access</span>
          <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Roles & Permissions</h1>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/45">Customize permissions for every role. Starter role identities are protected, but their access can be adjusted to match your organization.</p>
        </div>
        <button v-if="canCreate" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-red-600/15 hover:bg-red-700" @click="openCreate"><Icon icon="lucide:plus" width="16"/>Create Role</button>
      </header>

      <div v-if="loading" class="mt-6 grid gap-4 lg:grid-cols-[340px_1fr]"><div class="h-[520px] animate-pulse rounded-3xl bg-base-100"></div><div class="h-[520px] animate-pulse rounded-3xl bg-base-100"></div></div>
      <div v-else-if="errorMessage" class="mt-6 rounded-3xl border border-red-200 bg-red-50 p-8 text-center"><Icon icon="lucide:triangle-alert" width="28" class="mx-auto text-red-500"/><p class="mt-2 text-sm font-semibold text-red-700">{{ errorMessage }}</p><button class="mt-3 text-xs font-bold text-red-700 underline" @click="load">Try again</button></div>

      <template v-else>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Stat label="Roles" :value="roles.length" icon="lucide:key-round"/>
          <Stat label="Starter roles" :value="roles.filter(r=>r.is_system).length" icon="lucide:shield-check"/>
          <Stat label="Custom roles" :value="roles.filter(r=>!r.is_system).length" icon="lucide:wand-sparkles"/>
          <Stat label="Permission actions" :value="permissions.length" icon="lucide:list-checks"/>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-[340px_minmax(0,1fr)]">
          <aside class="overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
            <div class="border-b border-base-300 p-4 dark:border-white/10"><p class="text-sm font-black text-slate-900 dark:text-white">Organization Roles</p><p class="mt-1 text-xs text-slate-400">Select a role to inspect or change its access.</p></div>
            <div class="max-h-[650px] overflow-y-auto p-2">
              <button v-for="role in roles" :key="role.id" class="mb-1 w-full rounded-2xl border p-3 text-left transition" :class="selectedRole?.id===role.id?'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10':'border-transparent hover:border-base-300 hover:bg-base-200 dark:hover:border-white/10 dark:hover:bg-base-100/5'" @click="selectedRoleId=role.id">
                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex items-center gap-2"><p class="truncate text-sm font-black text-slate-800 dark:text-white/80">{{ role.role_name }}</p><span v-if="role.is_system" class="rounded-full bg-blue-50 px-2 py-0.5 text-[.58rem] font-black uppercase tracking-wide text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">Starter</span></div><p class="mt-1 line-clamp-2 text-[.68rem] leading-5 text-slate-400">{{ role.description || 'No description provided.' }}</p></div><span class="flex-shrink-0 rounded-full bg-base-200 px-2 py-1 text-[.62rem] font-bold text-slate-500">{{ role.permission_ids.length }}</span></div>
              </button>
            </div>
          </aside>

          <main v-if="selectedRole" class="rounded-3xl border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
            <header class="flex flex-col gap-4 border-b border-base-300 p-5 dark:border-white/10 sm:flex-row sm:items-start sm:justify-between sm:p-6">
              <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-black text-slate-950 dark:text-white">{{ selectedRole.role_name }}</h2><span v-if="selectedRole.is_system" class="rounded-full bg-blue-50 px-2.5 py-1 text-[.62rem] font-black text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">Protected name · editable access</span></div><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-white/45">{{ selectedRole.description || 'No description provided.' }}</p></div>
              <div class="flex gap-2"><button v-if="canUpdate" class="rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-base-200 dark:text-white/60" @click="openEdit(selectedRole)"><Icon icon="lucide:sliders-horizontal" width="14" class="mr-1 inline"/>{{ selectedRole.is_system?'Edit permissions':'Edit role' }}</button><button v-if="canDelete&&!selectedRole.is_system" class="rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50" @click="removeRole(selectedRole)"><Icon icon="lucide:trash-2" width="14" class="mr-1 inline"/>Delete</button></div>
            </header>

            <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[1fr_310px]">
              <section><div class="flex items-center justify-between"><div><h3 class="text-sm font-black text-slate-900 dark:text-white">Granted permissions</h3><p class="mt-1 text-xs text-slate-400">{{ selectedRole.permission_ids.length }} of {{ permissions.length }} available actions.</p></div></div>
                <div class="mt-4 grid gap-3 md:grid-cols-2"><article v-for="group in selectedPermissionGroups" :key="group.module_key" class="rounded-2xl border border-base-300 bg-base-200/50 p-4 dark:border-white/10 dark:bg-white/[.025]"><div class="flex items-center justify-between gap-3"><p class="text-xs font-black text-slate-800 dark:text-white/70">{{ group.module_name }}</p><span class="text-[.62rem] font-bold text-slate-400">{{ group.items.length }}</span></div><div class="mt-3 flex flex-wrap gap-1.5"><span v-for="permission in group.items" :key="permission.id" class="rounded-lg border border-base-300 bg-base-100 px-2 py-1 text-[.61rem] font-semibold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-white/45">{{ pretty(permission.resource) }} · {{ permission.action }}</span></div></article></div>
                <div v-if="!selectedRole.permission_ids.length" class="mt-4 rounded-2xl border border-dashed border-base-300 p-6 text-center text-xs text-slate-400">This role currently grants no permissions.</div>
              </section>

              <aside><h3 class="text-sm font-black text-slate-900 dark:text-white">Assigned employees</h3><p class="mt-1 text-xs text-slate-400">Assign one or more roles to each operational employee.</p><div class="mt-4 space-y-2"><button v-for="member in operationalMembers" :key="member.id" class="flex w-full items-center gap-3 rounded-2xl border border-base-300 bg-base-200/50 p-3 text-left hover:bg-base-200 dark:border-white/10 dark:bg-white/[.025]" @click="openMemberAccess(member)"><span class="grid h-9 w-9 place-items-center rounded-xl bg-base-100 text-xs font-black text-red-700 dark:bg-white/5 dark:text-red-300">{{ initials(member) }}</span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-black text-slate-800 dark:text-white/70">{{ member.first_name }} {{ member.last_name }}</span><span class="mt-0.5 block truncate text-[.64rem] text-slate-400">{{ member.role_names?.join(', ') || 'No role assigned' }}</span></span><Icon icon="lucide:chevron-right" width="15" class="text-slate-300"/></button><div v-if="!operationalMembers.length" class="rounded-2xl border border-dashed border-base-300 p-5 text-center text-xs text-slate-400">No operational employees yet.</div></div></aside>
            </div>
          </main>
        </div>
      </template>

      <Teleport to="body">
        <div v-if="roleModal" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/60 p-3 backdrop-blur-sm" @click.self="roleModal=false">
          <section class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]">
            <header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10 sm:px-6"><div><p class="text-[.66rem] font-black uppercase tracking-[.14em] text-red-600">{{ editingRole?.is_system?'Starter role access':editingRoleId?'Custom role':'New custom role' }}</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ editingRole?.is_system?`Edit ${editingRole.role_name} Permissions`:editingRoleId?'Edit Role':'Create Role' }}</h2><p v-if="editingRole?.is_system" class="mt-1 text-xs text-slate-400">The starter role name is protected, but you can customize its permissions.</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200" @click="roleModal=false"><Icon icon="lucide:x" width="18"/></button></header>

            <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:px-6">
              <div v-if="!editingRole?.is_system" class="mb-5 grid gap-4 sm:grid-cols-2"><label><span class="label">Role name</span><input v-model.trim="roleForm.role_name" class="field" maxlength="100"/></label><label><span class="label">Description</span><input v-model.trim="roleForm.description" class="field" maxlength="1000"/></label></div>

              <div class="rounded-2xl border border-base-300 bg-base-200/40 p-3 dark:border-white/10 dark:bg-white/[.025]">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div class="relative min-w-0 flex-1"><Icon icon="lucide:search" width="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/><input v-model.trim="permissionSearch" class="field !py-2 !pl-9" placeholder="Search module, resource, or action…"/></div>
                  <div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded-full bg-base-100 px-3 py-2 font-bold text-slate-500 dark:bg-white/5">{{ roleForm.permission_ids.length }} selected</span><button class="rounded-lg border border-base-300 bg-base-100 px-3 py-2 font-bold text-slate-600 hover:bg-base-200 dark:border-white/10 dark:bg-white/5 dark:text-white/55" @click="toggleExpandedAll">{{ allVisibleExpanded?'Collapse all':'Expand all' }}</button><button class="rounded-lg bg-red-50 px-3 py-2 font-bold text-red-700 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-300" @click="toggleAssignableAll">{{ allAssignableSelected?'Clear assignable':'Select all assignable' }}</button></div>
                </div>
              </div>

              <div class="mt-3 max-h-[440px] space-y-2 overflow-y-auto pr-1">
                <article v-for="group in filteredPermissionGroups" :key="group.module_key" class="overflow-hidden rounded-2xl border border-base-300 bg-base-200/35 dark:border-white/10 dark:bg-white/[.02]">
                  <div class="flex items-center gap-2 px-3 py-2.5">
                    <button class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="toggleModule(group.module_key)"><span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-xl bg-base-100 text-slate-500 dark:bg-white/5"><Icon :icon="moduleIcon(group.module_key)" width="15"/></span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-black text-slate-800 dark:text-white/75">{{ group.module_name }}</span><span class="block text-[.62rem] text-slate-400">{{ selectedInGroup(group) }}/{{ group.items.length }} selected</span></span><Icon :icon="isExpanded(group.module_key)?'lucide:chevron-up':'lucide:chevron-down'" width="15" class="text-slate-400"/></button>
                    <button class="rounded-lg border border-base-300 bg-base-100 px-2.5 py-1.5 text-[.62rem] font-black text-slate-500 hover:bg-base-200 dark:border-white/10 dark:bg-white/5" @click="toggleGroup(group)">{{ groupAssignableSelected(group)?'Clear':'Select' }}</button>
                  </div>
                  <div v-if="isExpanded(group.module_key) || permissionSearch" class="border-t border-base-300 px-3 py-2.5 dark:border-white/10">
                    <div class="space-y-1.5"><div v-for="resource in group.resources" :key="resource.key" class="grid gap-2 rounded-xl bg-base-100 px-3 py-2 dark:bg-white/[.035] sm:grid-cols-[minmax(145px,1fr)_auto] sm:items-center"><div class="min-w-0"><p class="truncate text-[.68rem] font-black text-slate-700 dark:text-white/60">{{ pretty(resource.key) }}</p></div><div class="flex flex-wrap gap-1.5"><label v-for="permission in resource.items" :key="permission.id" class="cursor-pointer"><input v-model="roleForm.permission_ids" type="checkbox" :value="permission.id" :disabled="!permission.assignable" class="peer sr-only"/><span class="inline-flex min-w-[58px] justify-center rounded-lg border px-2 py-1 text-[.59rem] font-black uppercase tracking-wide transition peer-checked:border-red-500 peer-checked:bg-red-600 peer-checked:text-white" :class="permission.assignable?'border-base-300 bg-base-200 text-slate-500 hover:border-red-300 dark:border-white/10 dark:bg-white/5 dark:text-white/40':'cursor-not-allowed border-base-300 bg-base-200 text-slate-300 opacity-45'">{{ permission.action }}</span></label></div></div></div>
                  </div>
                </article>
                <div v-if="!filteredPermissionGroups.length" class="rounded-2xl border border-dashed border-base-300 p-8 text-center text-xs text-slate-400">No permissions match your search.</div>
              </div>
            </div>

            <footer class="flex items-center justify-between gap-3 border-t border-base-300 bg-base-100 p-5 dark:border-white/10 dark:bg-[#0d2943] sm:px-6"><p class="hidden text-xs text-slate-400 sm:block">Changes take effect the next time affected users refresh their session.</p><div class="ml-auto flex gap-2"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600 dark:text-white/55" @click="roleModal=false">Cancel</button><button class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving" @click="saveRole">{{ saving?'Saving…':'Save Permissions' }}</button></div></footer>
          </section>
        </div>

        <div v-if="memberModal" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="memberModal=false"><section class="w-full max-w-xl overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#0d2943]"><header class="flex items-start justify-between border-b border-base-300 p-5 dark:border-white/10"><div><p class="text-[.66rem] font-black uppercase tracking-[.14em] text-red-600">Employee access</p><h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">{{ memberTarget?.first_name }} {{ memberTarget?.last_name }}</h2><p class="mt-1 text-xs text-slate-400">Select the roles this employee should hold.</p></div><button class="rounded-xl p-2 text-slate-400 hover:bg-base-200" @click="memberModal=false"><Icon icon="lucide:x" width="18"/></button></header><div class="max-h-[55vh] space-y-2 overflow-y-auto p-5"><label v-for="role in roles" :key="role.id" class="flex items-start gap-3 rounded-2xl border border-base-300 bg-base-200/40 p-3"><input v-model="memberRoleIds" type="checkbox" :value="role.id" class="mt-1 accent-red-600"/><span class="min-w-0"><span class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-white/70">{{ role.role_name }}<span v-if="role.is_system" class="text-[.58rem] font-bold uppercase text-blue-500">Starter</span></span><span class="mt-1 block text-[.68rem] leading-5 text-slate-400">{{ role.description }}</span></span></label></div><footer class="flex justify-end gap-2 border-t border-base-300 p-5 dark:border-white/10"><button class="rounded-xl border border-base-300 px-4 py-2.5 text-sm font-bold text-slate-600" @click="memberModal=false">Cancel</button><button class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="saving" @click="saveMemberRoles">{{ saving?'Saving…':'Update Access' }}</button></footer></section></div>
      </Teleport>
    </div>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { createOrganizationRole, deleteOrganizationRole, fetchOrganizationAccessCatalog, fetchOrganizationMembers, updateOrganizationMemberRoles, updateOrganizationRole } from '@/services/organizationManagement'
import { useSession } from '@/composables/useSession'
import { useToast } from '@/composables/useToast'
import { useAlert } from '@/composables/useAlert'

const { hasPermission }=useSession(), toast=useToast(), alert=useAlert()
const loading=ref(true), errorMessage=ref(''), roles=ref([]), permissions=ref([]), members=ref([]), selectedRoleId=ref(null), saving=ref(false)
const roleModal=ref(false), editingRoleId=ref(null), editingRole=ref(null), roleForm=ref({role_name:'',description:'',permission_ids:[]}), permissionSearch=ref(''), expandedModules=ref(new Set())
const memberModal=ref(false), memberTarget=ref(null), memberRoleIds=ref([])
const canCreate=computed(()=>hasPermission('rbac.roles.create')), canUpdate=computed(()=>hasPermission('rbac.roles.update')), canDelete=computed(()=>hasPermission('rbac.roles.delete'))
const selectedRole=computed(()=>roles.value.find(r=>r.id===selectedRoleId.value)||roles.value[0]||null)
const operationalMembers=computed(()=>members.value.filter(m=>m.account_role!=='organization_admin'))
const permissionGroups=computed(()=>{const map=new Map();for(const p of permissions.value){if(!map.has(p.module_key))map.set(p.module_key,{module_key:p.module_key,module_name:p.module_name,items:[]});map.get(p.module_key).items.push(p)}return [...map.values()].map(g=>({...g,resources:Object.values(g.items.reduce((m,p)=>{(m[p.resource]??={key:p.resource,items:[]}).items.push(p);return m},{}))}))})
const filteredPermissionGroups=computed(()=>{const q=permissionSearch.value.toLowerCase();if(!q)return permissionGroups.value;return permissionGroups.value.map(g=>{const items=g.items.filter(p=>`${g.module_name} ${p.resource} ${p.action}`.toLowerCase().includes(q));return {...g,items,resources:Object.values(items.reduce((m,p)=>{(m[p.resource]??={key:p.resource,items:[]}).items.push(p);return m},{}))}}).filter(g=>g.items.length)})
const selectedPermissionGroups=computed(()=>permissionGroups.value.map(g=>({...g,items:g.items.filter(p=>selectedRole.value?.permission_ids?.includes(p.id))})).filter(g=>g.items.length))
const assignableIds=computed(()=>permissions.value.filter(p=>p.assignable).map(p=>p.id))
const allAssignableSelected=computed(()=>assignableIds.value.length>0&&assignableIds.value.every(id=>roleForm.value.permission_ids.includes(id)))
const allVisibleExpanded=computed(()=>filteredPermissionGroups.value.length>0&&filteredPermissionGroups.value.every(g=>expandedModules.value.has(g.module_key)))
const Stat=defineComponent({props:{label:String,value:[String,Number],icon:String},setup(p){return()=>h('article',{class:'rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943]'},[h('div',{class:'flex items-start justify-between'},[h('span',{class:'grid h-10 w-10 place-items-center rounded-2xl bg-base-200 text-slate-500'},[h(Icon,{icon:p.icon,width:18})]),h('span',{class:'text-2xl font-black text-slate-950 dark:text-white'},String(p.value??0))]),h('p',{class:'mt-4 text-sm font-black text-slate-800 dark:text-white/70'},p.label)])}})
const initials=m=>`${m.first_name?.[0]||''}${m.last_name?.[0]||''}`.toUpperCase();const pretty=v=>String(v||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase())
function moduleIcon(k){return {fleet:'lucide:ambulance',dispatch:'lucide:radio-tower',incidents:'lucide:siren',hr:'lucide:users-round',rbac:'lucide:key-round',hospital:'lucide:hospital',analytics:'lucide:chart-column',org_settings:'lucide:settings'}[k]||'lucide:box'}
async function load(){loading.value=true;errorMessage.value='';try{const [a,m]=await Promise.all([fetchOrganizationAccessCatalog(),fetchOrganizationMembers()]);roles.value=a.roles||[];permissions.value=a.permissions||[];members.value=m.members||[];if(!selectedRoleId.value||!roles.value.some(r=>r.id===selectedRoleId.value))selectedRoleId.value=roles.value[0]?.id||null}catch(e){errorMessage.value=e?.response?.data?.message||'Could not load organization access control.'}finally{loading.value=false}}
function openCreate(){editingRoleId.value=null;editingRole.value=null;roleForm.value={role_name:'',description:'',permission_ids:[]};permissionSearch.value='';expandedModules.value=new Set();roleModal.value=true}
function openEdit(role){editingRoleId.value=role.id;editingRole.value=role;roleForm.value={role_name:role.role_name,description:role.description||'',permission_ids:[...role.permission_ids]};permissionSearch.value='';expandedModules.value=new Set();roleModal.value=true}
function toggleAssignableAll(){roleForm.value.permission_ids=allAssignableSelected.value?roleForm.value.permission_ids.filter(id=>!assignableIds.value.includes(id)):[...new Set([...roleForm.value.permission_ids,...assignableIds.value])]}
function isExpanded(k){return expandedModules.value.has(k)}
function toggleModule(k){const n=new Set(expandedModules.value);n.has(k)?n.delete(k):n.add(k);expandedModules.value=n}
function toggleExpandedAll(){expandedModules.value=allVisibleExpanded.value?new Set():new Set(filteredPermissionGroups.value.map(g=>g.module_key))}
function selectedInGroup(g){return g.items.filter(p=>roleForm.value.permission_ids.includes(p.id)).length}
function groupAssignableSelected(g){const ids=g.items.filter(p=>p.assignable).map(p=>p.id);return ids.length&&ids.every(id=>roleForm.value.permission_ids.includes(id))}
function toggleGroup(g){const ids=g.items.filter(p=>p.assignable).map(p=>p.id);if(groupAssignableSelected(g))roleForm.value.permission_ids=roleForm.value.permission_ids.filter(id=>!ids.includes(id));else roleForm.value.permission_ids=[...new Set([...roleForm.value.permission_ids,...ids])]}
async function saveRole(){if(!editingRole.value?.is_system&&roleForm.value.role_name.trim().length<3){toast.error('Enter a role name of at least 3 characters.');return}saving.value=true;try{const payload={...roleForm.value,permission_ids:roleForm.value.permission_ids.map(Number)};const d=editingRoleId.value?await updateOrganizationRole({...payload,role_id:editingRoleId.value}):await createOrganizationRole(payload);toast.success(d.message);roleModal.value=false;await load();if(d.role_id)selectedRoleId.value=d.role_id}catch(e){toast.error(e?.response?.data?.message||'Could not save this role.')}finally{saving.value=false}}
async function removeRole(role){const ok=await alert.confirm(`Delete the custom role “${role.role_name}”?`,'Delete role');if(!ok)return;try{const d=await deleteOrganizationRole(role.id);toast.success(d.message);selectedRoleId.value=null;await load()}catch(e){toast.error(e?.response?.data?.message||'Could not delete this role.')}}
function openMemberAccess(member){memberTarget.value=member;memberRoleIds.value=roles.value.filter(r=>member.role_names?.includes(r.role_name)).map(r=>r.id);memberModal.value=true}
async function saveMemberRoles(){if(!memberTarget.value)return;saving.value=true;try{const d=await updateOrganizationMemberRoles(memberTarget.value.id,memberRoleIds.value.map(Number));toast.success(d.message);memberModal.value=false;await load()}catch(e){toast.error(e?.response?.data?.message||'Could not update employee access.')}finally{saving.value=false}}
onMounted(load)
</script>

<style scoped>
.label{display:block;margin-bottom:.375rem;font-size:.68rem;font-weight:900;text-transform:uppercase;letter-spacing:.025em;color:#94a3b8}.field{width:100%;border-radius:.75rem;border:1px solid var(--color-base-300);background:var(--color-base-100);padding:.625rem .875rem;font-size:.875rem;line-height:1.25rem;color:#1e293b;outline:none;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}.field:focus{border-color:#1976d2;box-shadow:0 0 0 2px rgba(25,118,210,.1)}:global([data-theme="dark"]) .label{color:rgba(255,255,255,.30)}:global([data-theme="dark"]) .field{border-color:rgba(255,255,255,.10);background:#071829;color:#fff}
</style>
