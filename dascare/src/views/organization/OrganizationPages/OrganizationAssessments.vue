<template>
  <section class="min-h-screen bg-base-200 px-4 py-6 sm:px-6 lg:px-8"><div class="mx-auto max-w-[1450px] space-y-5">
    <header><p class="text-[.68rem] font-black uppercase tracking-[.16em] text-red-600">Incidents & Care</p><h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Patient Assessments</h1><p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-white/45">Record limited prehospital observations for the active incident. This is not a longitudinal medical record.</p></header>
    <div class="grid gap-5 xl:grid-cols-[340px_minmax(0,1fr)]">
      <aside class="rounded-3xl border border-base-300 bg-base-100 p-3 shadow-sm dark:border-white/10 dark:bg-[#0d2943]"><p class="px-2 pb-3 text-xs font-black uppercase tracking-[.12em] text-slate-400">Mission</p><button v-for="m in missions" :key="m.id" class="mb-1 w-full rounded-2xl border p-3 text-left" :class="selected?.id===m.id?'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/10':'border-transparent hover:bg-base-200 dark:hover:bg-white/[.03]'" @click="selectedId=m.id"><p class="text-sm font-black text-slate-900 dark:text-white">{{m.reference_number}}</p><p class="mt-1 text-xs text-slate-500 dark:text-white/45">{{m.unit_code}} · {{pretty(m.assignment_status)}}</p></button><div v-if="!missions.length" class="p-8 text-center text-sm text-slate-400">No mission records yet.</div></aside>
      <main v-if="selected" class="space-y-5">
        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6"><div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-black text-slate-950 dark:text-white">New assessment · {{selected.reference_number}}</h2><p class="mt-1 text-xs text-slate-400">{{selected.address_text}}</p></div><span class="rounded-full bg-red-50 px-2.5 py-1 text-[.62rem] font-black uppercase text-red-700 dark:bg-red-500/10 dark:text-red-300">{{pretty(selected.assignment_status)}}</span></div>
          <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field label="Patient name"><input v-model="form.patient_name" class="input" placeholder="If known"/></Field>
            <Field label="Approximate age"><input v-model="form.approximate_age" type="number" min="0" max="120" class="input" placeholder="Age"/></Field>
            <Field label="Sex"><select v-model="form.sex" class="input"><option value="unknown">Unknown</option><option value="male">Male</option><option value="female">Female</option></select></Field>
            <Field label="Consciousness"><select v-model="form.consciousness" class="input"><option value="alert">Alert</option><option value="responsive_to_voice">Responsive to voice</option><option value="responsive_to_pain">Responsive to pain</option><option value="unresponsive">Unresponsive</option><option value="unknown">Unknown</option></select></Field>
            <Field label="Breathing status"><input v-model="form.breathing_status" class="input" placeholder="e.g. normal, labored"/></Field>
            <Field label="Blood pressure"><input v-model="form.vital_signs.blood_pressure" class="input" placeholder="120/80"/></Field>
            <Field label="Pulse"><input v-model="form.vital_signs.pulse_bpm" class="input" placeholder="bpm"/></Field>
            <Field label="Respiratory rate"><input v-model="form.vital_signs.respiratory_rate" class="input" placeholder="breaths/min"/></Field>
            <Field label="SpO₂"><input v-model="form.vital_signs.spo2_percent" class="input" placeholder="%"/></Field>
            <Field label="Temperature"><input v-model="form.vital_signs.temperature_c" class="input" placeholder="°C"/></Field>
            <Field label="Condition summary" class="sm:col-span-2"><textarea v-model="form.condition_summary" class="input min-h-24" placeholder="Brief current condition and observations"></textarea></Field>
            <Field label="Visible injuries / notes" class="sm:col-span-2 lg:col-span-3"><textarea v-model="form.injuries" class="input min-h-20" placeholder="Optional"></textarea></Field>
          </div><div class="mt-5 flex justify-end"><button :disabled="saving" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white hover:bg-red-700 disabled:opacity-50" @click="save"><Icon icon="lucide:save" width="14" class="mr-1 inline"/>Record Assessment</button></div>
        </section>
        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm dark:border-white/10 dark:bg-[#0d2943] sm:p-6"><h2 class="text-sm font-black text-slate-900 dark:text-white">Assessment history</h2><div class="mt-4 space-y-3"><article v-for="a in assessments" :key="a.id" class="rounded-2xl border border-base-300 bg-base-200/35 p-4 dark:border-white/10 dark:bg-white/[.03]"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-black text-slate-800 dark:text-white/70">{{a.patient_name||'Patient'}} · {{pretty(a.consciousness)}}</p><span class="text-[.62rem] font-bold text-slate-400">{{formatDate(a.assessed_at)}}</span></div><p class="mt-2 text-xs text-slate-600 dark:text-white/50">{{a.condition_summary}}</p><p v-if="a.injuries" class="mt-2 text-xs text-slate-400">Injuries/notes: {{a.injuries}}</p><div v-if="Object.keys(a.vital_signs||{}).length" class="mt-3 flex flex-wrap gap-2"><span v-for="(v,k) in a.vital_signs" :key="k" class="rounded-lg bg-base-100 px-2.5 py-1 text-[.62rem] font-bold text-slate-500 dark:bg-white/5 dark:text-white/45">{{pretty(k)}}: {{v}}</span></div><p class="mt-3 text-[.62rem] text-slate-400">Recorded by {{a.recorded_by}}</p></article><div v-if="!assessments.length" class="rounded-2xl border border-dashed border-base-300 p-8 text-center text-sm text-slate-400 dark:border-white/10">No patient assessment has been recorded for this incident.</div></div></section>
      </main><div v-else class="grid min-h-[500px] place-items-center rounded-3xl border border-dashed border-base-300 bg-base-100 p-10 text-center text-sm font-bold text-slate-400 dark:border-white/10 dark:bg-[#0d2943]">Choose a mission to record or review assessments.</div>
    </div>
  </div></section>
</template>
<script setup>
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { fetchOrganizationMissions } from '@/services/dispatchOperations'
import { createAssessment, fetchAssessments } from '@/services/rescueOperations'
import { useAlert } from '@/composables/useAlert'
const alert=useAlert(),missions=ref([]),selectedId=ref(null),assessments=ref([]),saving=ref(false)
const selected=computed(()=>missions.value.find(m=>m.id===selectedId.value)||missions.value[0]||null)
const blank=()=>({patient_name:'',approximate_age:'',sex:'unknown',consciousness:'unknown',breathing_status:'',condition_summary:'',injuries:'',vital_signs:{pulse_bpm:'',respiratory_rate:'',blood_pressure:'',spo2_percent:'',temperature_c:''}});const form=ref(blank())
const Field=defineComponent({props:{label:String},setup(p,{slots}){return()=>h('label',{class:'block'},[h('span',{class:'mb-1.5 block text-[.65rem] font-black uppercase tracking-[.1em] text-slate-400'},p.label),slots.default?.()])}})
const pretty=v=>String(v||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());const formatDate=v=>v?new Date(String(v).replace(' ','T')).toLocaleString():'—'
async function load(){try{const d=await fetchOrganizationMissions();missions.value=d.missions||[];if(!selectedId.value)selectedId.value=missions.value[0]?.id||null}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to load missions.')}}
async function loadAssess(){if(!selected.value){assessments.value=[];return}try{const d=await fetchAssessments(selected.value.emergency_request_id);assessments.value=d.assessments||[]}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to load assessments.')}}
async function save(){if(!selected.value)return;saving.value=true;try{const d=await createAssessment({...form.value,assignment_id:selected.value.id});alert.success(d.message);form.value=blank();await loadAssess()}catch(e){alert.error(e?.response?.data?.message||e.message||'Unable to save assessment.')}finally{saving.value=false}}
watch(selectedId,loadAssess);onMounted(async()=>{await load();await loadAssess()})
</script>
<style scoped>.input{width:100%;border:1px solid hsl(var(--bc)/.12);background:hsl(var(--b1));border-radius:.75rem;padding:.7rem .8rem;font-size:.8rem;outline:none}.input:focus{border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,.08)}</style>
