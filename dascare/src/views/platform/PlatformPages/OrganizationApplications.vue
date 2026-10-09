<template>
  <section class="min-h-screen bg-base-200 pb-10 dark:bg-[#050e1a]">
    <section class="relative overflow-hidden border-b border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
      <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30" style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.07), transparent 55%);"></div>
      <div class="relative mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div class="min-w-0">
          <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-red-700 dark:bg-red-500/10 dark:text-red-300">
            <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
            Platform Executive
          </span>
          <h1 class="mt-2 text-xl font-black text-slate-900 dark:text-white sm:text-2xl">Organization Applications</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-white/45">Verify rescue organizations before operational access is activated.</p>
        </div>
        <button type="button" class="inline-flex w-fit items-center gap-2 rounded-xl border border-base-300 bg-base-100 px-4 py-2.5 text-xs font-bold text-slate-600 shadow-sm transition hover:border-red-200 hover:text-red-600 dark:border-white/10 dark:bg-white/5 dark:text-white/55 dark:hover:border-red-500/30 dark:hover:text-red-300" @click="loadQueue">
          <Icon icon="lucide:refresh-cw" width="14" /> Refresh queue
        </button>
      </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <button
          v-for="card in statCards"
          :key="card.key"
          type="button"
          class="rounded-2xl border border-base-300 bg-base-100 p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-[#071829]"
          @click="setStatus(card.tab)"
        >
          <div class="flex items-center justify-between gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="card.iconClass"><Icon :icon="card.icon" width="18" /></span>
            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ stats[card.key] ?? 0 }}</span>
          </div>
          <p class="mt-3 text-[0.7rem] font-bold uppercase tracking-[0.08em] text-slate-400 dark:text-white/35">{{ card.label }}</p>
        </button>
      </div>

      <section class="mt-6 overflow-hidden rounded-3xl border border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
        <div class="border-b border-base-300 p-4 dark:border-white/10 sm:p-5">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors"
                :class="activeStatus === tab.value ? 'bg-red-600 text-white' : 'border border-base-300 bg-base-100 text-slate-500 hover:border-red-200 hover:text-red-600 dark:border-white/10 dark:bg-white/5 dark:text-white/45 dark:hover:border-red-500/30 dark:hover:text-red-300'"
                @click="setStatus(tab.value)"
              >
                {{ tab.label }}
                <span class="rounded-full px-1.5 text-[0.62rem]" :class="activeStatus === tab.value ? 'bg-base-100/20 text-white' : 'bg-base-200 text-slate-400 dark:bg-white/10 dark:text-white/40'">{{ tabCount(tab.value) }}</span>
              </button>
            </div>

            <form class="flex w-full gap-2 xl:max-w-md" @submit.prevent="applySearch">
              <label class="relative flex-1">
                <Icon icon="lucide:search" width="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input v-model="searchInput" type="search" placeholder="Search organization or applicant..." class="w-full rounded-xl border border-base-300 bg-base-100 py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-red-300 focus:ring-2 focus:ring-red-100 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-red-500/40 dark:focus:ring-red-500/10" />
              </label>
              <button type="submit" class="rounded-xl bg-slate-900 px-4 text-xs font-bold text-white transition hover:bg-red-600 dark:bg-white/10 dark:hover:bg-red-600">Search</button>
            </form>
          </div>
        </div>

        <div v-if="loading" class="grid place-items-center py-20">
          <div class="text-center">
            <div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div>
            <p class="mt-3 text-sm text-slate-400 dark:text-white/35">Loading organization applications…</p>
          </div>
        </div>

        <div v-else-if="loadError" class="py-16 text-center">
          <Icon icon="lucide:triangle-alert" width="28" class="mx-auto text-red-400" />
          <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-white/50">{{ loadError }}</p>
          <button type="button" class="mt-3 text-xs font-bold text-red-600 hover:underline dark:text-red-300" @click="loadQueue">Try again</button>
        </div>

        <div v-else-if="items.length" class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left">
            <thead class="bg-base-200/80 dark:bg-white/[0.025]">
              <tr class="text-[0.65rem] font-black uppercase tracking-[0.08em] text-slate-400 dark:text-white/30">
                <th class="px-5 py-3.5">Organization</th>
                <th class="px-4 py-3.5">Representative</th>
                <th class="px-4 py-3.5">Type</th>
                <th class="px-4 py-3.5">Submitted</th>
                <th class="px-4 py-3.5">Status</th>
                <th class="px-5 py-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-base-300 dark:divide-white/5">
              <tr v-for="item in items" :key="item.id" class="transition-colors hover:bg-base-200/70 dark:hover:bg-base-100/[0.025]">
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:building-2" width="17" /></span>
                    <div class="min-w-0">
                      <p class="max-w-64 truncate text-sm font-black text-slate-900 dark:text-white">{{ item.name }}</p>
                      <p class="mt-0.5 font-mono text-[0.65rem] text-slate-400 dark:text-white/30">{{ item.application_reference }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4">
                  <p class="text-xs font-bold text-slate-700 dark:text-white/60">{{ item.admin_name || '—' }}</p>
                  <p class="mt-0.5 max-w-52 truncate text-[0.68rem] text-slate-400 dark:text-white/30">{{ item.admin_email || '—' }}</p>
                </td>
                <td class="px-4 py-4 text-xs font-semibold text-slate-600 dark:text-white/50">{{ typeLabel(item.organization_type) }}</td>
                <td class="px-4 py-4 text-xs text-slate-500 dark:text-white/40">{{ formatDate(item.application_submitted_at) }}</td>
                <td class="px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.68rem] font-bold" :class="statusBadgeClass(item.application_status)"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ statusLabel(item.application_status) }}</span></td>
                <td class="px-5 py-4 text-right"><button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-base-300 px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-white/10 dark:text-white/50 dark:hover:border-red-500/30 dark:hover:bg-red-500/10 dark:hover:text-red-300" @click="openReview(item.id)"><Icon icon="lucide:scan-search" width="14" /> Review</button></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="py-20 text-center">
          <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-base-200 text-slate-300 dark:bg-white/5 dark:text-white/20"><Icon icon="lucide:building-2" width="21" /></span>
          <p class="mt-3 text-sm font-bold text-slate-500 dark:text-white/40">No organization applications in this view</p>
          <p class="mt-1 text-xs text-slate-400 dark:text-white/30">New public applications will appear here automatically.</p>
        </div>

        <div v-if="pagination.pages > 1" class="flex items-center justify-between gap-3 border-t border-base-300 px-5 py-4 dark:border-white/10">
          <p class="text-xs text-slate-400 dark:text-white/30">Page {{ pagination.page }} of {{ pagination.pages }} · {{ pagination.total }} result{{ pagination.total === 1 ? '' : 's' }}</p>
          <div class="flex gap-2">
            <button type="button" :disabled="pagination.page <= 1" class="rounded-lg border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-500 disabled:opacity-40 dark:border-white/10 dark:text-white/45" @click="goPage(pagination.page - 1)">Previous</button>
            <button type="button" :disabled="pagination.page >= pagination.pages" class="rounded-lg border border-base-300 px-3 py-1.5 text-xs font-bold text-slate-500 disabled:opacity-40 dark:border-white/10 dark:text-white/45" @click="goPage(pagination.page + 1)">Next</button>
          </div>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="reviewOpen" class="fixed inset-0 z-[75] flex items-center justify-center p-3 sm:p-5">
        <button type="button" aria-label="Close organization review" class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" @click="closeReview"></button>
        <section class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-2xl dark:border-white/10 dark:bg-[#071829]">
          <header class="flex flex-shrink-0 items-start justify-between gap-4 border-b border-base-300 px-5 py-4 dark:border-white/10 sm:px-6">
            <div class="min-w-0">
              <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.14em] text-red-600 dark:text-red-300">Organization review</p>
              <h2 class="mt-1 truncate text-lg font-black text-slate-900 dark:text-white">{{ detail?.name || 'Loading application…' }}</h2>
              <p v-if="detail?.application_reference" class="mt-0.5 font-mono text-[0.67rem] text-slate-400 dark:text-white/30">{{ detail.application_reference }}</p>
            </div>
            <button type="button" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-base-200 dark:text-white/40 dark:hover:bg-base-100/10" @click="closeReview"><Icon icon="lucide:x" width="18" /></button>
          </header>

          <div v-if="detailLoading" class="grid flex-1 place-items-center py-24"><div class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div></div>
          <div v-else-if="detailError" class="grid flex-1 place-items-center p-10 text-center"><div><Icon icon="lucide:triangle-alert" width="28" class="mx-auto text-red-400" /><p class="mt-3 text-sm font-semibold text-slate-600 dark:text-white/50">{{ detailError }}</p></div></div>

          <template v-else-if="detail">
            <div class="flex-1 overflow-y-auto p-5 sm:p-6">
              <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-5">
                  <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-base-300 p-4 dark:border-white/10">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.68rem] font-bold" :class="statusBadgeClass(detail.application_status)"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ statusLabel(detail.application_status) }}</span>
                    <span class="text-xs text-slate-400 dark:text-white/30">Submitted {{ formatDate(detail.application_submitted_at) }}</span>
                    <span v-if="detail.application_reviewed_at" class="text-xs text-slate-400 dark:text-white/30">Last reviewed {{ formatDate(detail.application_reviewed_at) }} by {{ detail.reviewer_name || 'Platform Executive' }}</span>
                  </div>

                  <section class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex items-center gap-2"><Icon icon="lucide:building" width="16" class="text-red-600 dark:text-red-300" /><h3 class="text-sm font-black text-slate-900 dark:text-white">Organization information</h3></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                      <InfoField label="Organization type" :value="typeLabel(detail.organization_type)" />
                      <InfoField label="Registration number" :value="detail.registration_number || 'Not provided'" />
                      <InfoField label="Accreditation body" :value="detail.accreditation_body || 'Not provided'" />
                      <InfoField label="Organization email" :value="detail.email || '—'" />
                      <InfoField label="Organization phone" :value="detail.phone || '—'" />
                      <InfoField label="Base / station address" :value="detail.address_line || '—'" class="sm:col-span-2" />
                    </div>
                    <div v-if="detail.latitude != null && detail.longitude != null" class="mt-4 rounded-xl bg-base-200 p-3 text-xs text-slate-500 dark:bg-white/[0.035] dark:text-white/40">
                      <div class="flex items-center gap-2"><Icon icon="lucide:map-pin" width="14" /> Base coordinates: {{ Number(detail.latitude).toFixed(6) }}, {{ Number(detail.longitude).toFixed(6) }}</div>
                    </div>
                  </section>

                  <section class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex items-center gap-2"><Icon icon="lucide:user-round-check" width="16" class="text-red-600 dark:text-red-300" /><h3 class="text-sm font-black text-slate-900 dark:text-white">Primary representative</h3></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                      <InfoField label="Representative" :value="detail.admin_name || '—'" />
                      <InfoField label="Account status" :value="detail.admin_account_status || '—'" />
                      <InfoField label="Email" :value="detail.admin_email || '—'" />
                      <InfoField label="Phone" :value="detail.admin_phone || '—'" />
                    </div>
                  </section>

                  <section class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><Icon icon="lucide:map-pinned" width="16" class="text-red-600 dark:text-red-300" /><h3 class="text-sm font-black text-slate-900 dark:text-white">Declared service areas</h3></div><span class="text-xs font-bold text-slate-400 dark:text-white/30">{{ serviceAreas.length }}</span></div>
                    <div class="mt-4 flex flex-wrap gap-2"><span v-for="area in serviceAreas" :key="area.id" class="rounded-full border border-base-300 bg-base-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-white/50">{{ area.barangay }}</span><span v-if="!serviceAreas.length" class="text-xs text-slate-400">No service areas submitted.</span></div>
                  </section>

                  <section v-if="history.length" class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex items-center gap-2"><Icon icon="lucide:history" width="16" class="text-red-600 dark:text-red-300" /><h3 class="text-sm font-black text-slate-900 dark:text-white">Application history</h3></div>
                    <div class="mt-4 space-y-3">
                      <div v-for="event in history" :key="event.id" class="flex gap-3 rounded-xl bg-base-200 p-3 dark:bg-white/[0.035]">
                        <span class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-red-500"></span>
                        <div class="min-w-0"><p class="text-xs font-bold text-slate-700 dark:text-white/60">{{ historyLabel(event.action) }}</p><p class="mt-0.5 text-[0.68rem] text-slate-400 dark:text-white/30">{{ event.actor_name }} · {{ formatDate(event.created_at) }}</p><p v-if="event.new_values?.review_note" class="mt-1 text-[0.7rem] leading-5 text-slate-500 dark:text-white/40">{{ event.new_values.review_note }}</p></div>
                      </div>
                    </div>
                  </section>
                </div>

                <aside class="space-y-5">
                  <section class="rounded-2xl border border-base-300 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><Icon icon="lucide:files" width="16" class="text-red-600 dark:text-red-300" /><h3 class="text-sm font-black text-slate-900 dark:text-white">Submitted documents</h3></div><span class="text-xs font-bold text-slate-400 dark:text-white/30">{{ documents.length }}</span></div>
                    <div class="mt-4 space-y-3">
                      <div v-for="document in documents" :key="document.id" class="rounded-xl border border-base-300 p-3 dark:border-white/10">
                        <div class="flex items-start gap-3">
                          <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-base-200 text-slate-400 dark:bg-white/5 dark:text-white/35"><Icon :icon="documentIcon(document)" width="16" /></span>
                          <div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-slate-700 dark:text-white/60">{{ document.original_name || documentLabel(document.doc_type) }}</p><p class="mt-0.5 text-[0.65rem] text-slate-400 dark:text-white/30">{{ documentLabel(document.doc_type) }} · {{ formatFileSize(document.file_size) }}</p></div>
                          <span class="rounded-full px-2 py-0.5 text-[0.6rem] font-bold capitalize" :class="documentStatusClass(document.status)">{{ document.status }}</span>
                        </div>
                        <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-[0.68rem] font-bold text-red-600 hover:underline dark:text-red-300" @click="previewDocument(document)"><Icon icon="lucide:eye" width="13" /> View document</button>
                      </div>
                      <p v-if="!documents.length" class="py-5 text-center text-xs text-slate-400">No documents were submitted.</p>
                    </div>
                  </section>

                  <section v-if="detail.verification_note" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <div class="flex gap-2"><Icon icon="lucide:message-square-warning" width="16" class="mt-0.5 flex-shrink-0 text-amber-600 dark:text-amber-300" /><div><p class="text-[0.68rem] font-black uppercase tracking-wide text-amber-700 dark:text-amber-200">Current reviewer note</p><p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-100">{{ detail.verification_note }}</p></div></div>
                  </section>

                  <section v-if="canReview" class="rounded-2xl border border-red-200 bg-red-50/50 p-4 dark:border-red-500/20 dark:bg-red-500/5 sm:p-5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">Review decision</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-white/40">Approval unlocks the organization workspace. Revision and rejection require a clear reason.</p>
                    <textarea v-model="decisionNote" rows="5" maxlength="2000" placeholder="Add review notes or explain required changes..." class="mt-4 w-full resize-none rounded-xl border border-base-300 bg-base-100 px-3.5 py-3 text-sm text-slate-800 outline-none transition focus:border-red-300 focus:ring-2 focus:ring-red-100 dark:border-white/10 dark:bg-[#081b2e] dark:text-white dark:focus:border-red-500/40 dark:focus:ring-red-500/10"></textarea>
                    <div class="mt-4 grid gap-2">
                      <button type="button" :disabled="reviewSaving" class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-bold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200" @click="chooseDecision('revision')"><Icon icon="lucide:rotate-ccw" width="14" /> Request Revision</button>
                      <button type="button" :disabled="reviewSaving" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-bold text-red-700 transition hover:bg-red-100 disabled:opacity-50 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300" @click="chooseDecision('reject')"><Icon icon="lucide:x-circle" width="14" /> Reject Application</button>
                      <button type="button" :disabled="reviewSaving" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50" @click="chooseDecision('approve')"><Icon icon="lucide:badge-check" width="14" /> Approve Organization</button>
                    </div>
                  </section>
                </aside>
              </div>
            </div>
          </template>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="confirmDecision" class="fixed inset-0 z-[85] flex items-center justify-center p-4">
        <button type="button" aria-label="Cancel decision" class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="confirmDecision = ''"></button>
        <div class="relative w-full max-w-md rounded-2xl border border-base-300 bg-base-100 p-6 shadow-2xl dark:border-white/10 dark:bg-[#0f2a43]">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl" :class="confirmIconClass"><Icon :icon="confirmIcon" width="20" /></div>
          <h3 class="mt-4 text-lg font-black text-slate-900 dark:text-white">{{ confirmTitle }}</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-white/45">{{ confirmText }}</p>
          <div v-if="decisionNote.trim()" class="mt-4 rounded-xl bg-base-200 p-3 text-sm leading-6 text-slate-600 dark:bg-white/5 dark:text-white/55">{{ decisionNote.trim() }}</div>
          <div class="mt-5 flex gap-3"><button type="button" :disabled="reviewSaving" class="flex-1 rounded-xl border border-base-300 py-2.5 text-sm font-bold text-slate-600 dark:border-white/10 dark:text-white/55" @click="confirmDecision = ''">Cancel</button><button type="button" :disabled="reviewSaving" class="flex-1 rounded-xl py-2.5 text-sm font-bold text-white disabled:opacity-50" :class="confirmButtonClass" @click="saveDecision">{{ reviewSaving ? 'Saving…' : 'Confirm' }}</button></div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="previewOpen" class="fixed inset-0 z-[90] flex items-center justify-center p-3 sm:p-6">
        <button type="button" aria-label="Close document preview" class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" @click="closePreview"></button>
        <section class="relative flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-base-100 shadow-2xl dark:bg-[#071829]">
          <header class="flex items-center justify-between gap-3 border-b border-base-300 px-4 py-3 dark:border-white/10"><div class="min-w-0"><p class="truncate text-sm font-black text-slate-900 dark:text-white">{{ previewName }}</p><p class="text-[0.65rem] text-slate-400 dark:text-white/30">Protected application document</p></div><button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-base-200 dark:hover:bg-base-100/10" @click="closePreview"><Icon icon="lucide:x" width="18" /></button></header>
          <div class="grid flex-1 place-items-center overflow-auto bg-base-200 p-3 dark:bg-[#050e1a]">
            <div v-if="previewLoading" class="h-8 w-8 animate-spin rounded-full border-4 border-red-600 border-t-transparent"></div>
            <img v-else-if="previewUrl && previewMime.startsWith('image/')" :src="previewUrl" :alt="previewName" class="max-h-full max-w-full rounded-xl object-contain shadow-lg" />
            <iframe v-else-if="previewUrl && previewMime === 'application/pdf'" :src="previewUrl" :title="previewName" class="h-full min-h-[65vh] w-full rounded-xl bg-base-100"></iframe>
            <p v-else class="text-sm font-semibold text-slate-500 dark:text-white/45">Could not preview this document.</p>
          </div>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useToast } from '@/composables/useToast'
import { fetchOrganizationApplicationDetail, fetchOrganizationApplications, fetchOrganizationDocument, submitOrganizationReview } from '@/services/platformOrganizations'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(true)
const loadError = ref('')
const items = ref([])
const stats = ref({ pending: 0, revision_requested: 0, approved: 0, rejected: 0, total: 0 })
const pagination = ref({ page: 1, per_page: 20, total: 0, pages: 1 })
const activeStatus = ref('pending')
const searchInput = ref('')
const appliedSearch = ref('')

const reviewOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detail = ref(null)
const serviceAreas = ref([])
const documents = ref([])
const history = ref([])
const decisionNote = ref('')
const confirmDecision = ref('')
const reviewSaving = ref(false)

const previewOpen = ref(false)
const previewLoading = ref(false)
const previewUrl = ref('')
const previewMime = ref('')
const previewName = ref('')

const tabs = [
  { value: 'pending', label: 'Pending' },
  { value: 'revision_requested', label: 'Needs Revision' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'all', label: 'All' },
]

const statCards = [
  { key: 'pending', tab: 'pending', label: 'Pending review', icon: 'lucide:clock-3', iconClass: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' },
  { key: 'revision_requested', tab: 'revision_requested', label: 'Needs revision', icon: 'lucide:rotate-ccw', iconClass: 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-300' },
  { key: 'approved', tab: 'approved', label: 'Approved', icon: 'lucide:badge-check', iconClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300' },
  { key: 'rejected', tab: 'rejected', label: 'Rejected', icon: 'lucide:x-circle', iconClass: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' },
  { key: 'total', tab: 'all', label: 'Total applications', icon: 'lucide:files', iconClass: 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/40' },
]

const typeMap = { city_rescue: 'City Rescue / LGU Unit', barangay_rescue: 'Barangay Rescue Unit', hospital: 'Hospital Ambulance Service', private_ambulance: 'Private Ambulance / Rescue Service', other: 'Other Rescue Organization' }
const typeLabel = value => typeMap[value] || value || '—'
const statusLabel = status => ({ pending: 'Pending Review', revision_requested: 'Revision Requested', approved: 'Approved', rejected: 'Rejected' }[status] || status || 'Unknown')
const statusBadgeClass = status => status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : status === 'revision_requested' ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300' : status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
const tabCount = value => value === 'all' ? stats.value.total : (stats.value[value] ?? 0)
const canReview = computed(() => ['pending', 'revision_requested'].includes(detail.value?.application_status))

const InfoField = defineComponent({
  props: { label: String, value: [String, Number] },
  setup(props, { attrs }) {
    return () => h('div', { class: attrs.class }, [h('p', { class: 'text-[0.64rem] font-black uppercase tracking-[0.08em] text-slate-400 dark:text-white/30' }, props.label), h('p', { class: 'mt-1 text-sm font-semibold leading-6 text-slate-700 dark:text-white/60' }, String(props.value ?? '—'))])
  },
})

const formatDate = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—'
const formatFileSize = bytes => { const value = Number(bytes || 0); if (!value) return '—'; if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`; return `${(value / 1024 / 1024).toFixed(1)} MB` }
const documentLabel = type => ({ registration_document: 'Registration / authorization', operating_authority: 'Operating / accreditation', supporting_document: 'Additional supporting document' }[type] || type || 'Document')
const documentStatusClass = status => status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
const documentIcon = document => document.mime_type === 'application/pdf' ? 'lucide:file-text' : 'lucide:image'
const historyLabel = action => ({ 'organization.application_submitted': 'Application submitted', 'organization.application_approve': 'Application approved', 'organization.application_revision': 'Revision requested', 'organization.application_reject': 'Application rejected' }[action] || action.replaceAll('.', ' ').replaceAll('_', ' '))

const confirmTitle = computed(() => ({ approve: 'Approve this organization?', revision: 'Request application revision?', reject: 'Reject this organization application?' }[confirmDecision.value] || 'Confirm decision'))
const confirmText = computed(() => ({ approve: 'The organization becomes active immediately and the Organization Admin can access operational setup modules.', revision: 'The organization stays locked and the applicant will receive your review note.', reject: 'The organization stays inactive and the applicant will receive the rejection reason.' }[confirmDecision.value] || ''))
const confirmIcon = computed(() => ({ approve: 'lucide:badge-check', revision: 'lucide:rotate-ccw', reject: 'lucide:x-circle' }[confirmDecision.value] || 'lucide:circle-help'))
const confirmIconClass = computed(() => ({ approve: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300', revision: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300', reject: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' }[confirmDecision.value] || 'bg-base-200 text-slate-500'))
const confirmButtonClass = computed(() => ({ approve: 'bg-emerald-600 hover:bg-emerald-700', revision: 'bg-amber-600 hover:bg-amber-700', reject: 'bg-red-600 hover:bg-red-700' }[confirmDecision.value] || 'bg-slate-700'))

async function loadQueue() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchOrganizationApplications({ status: activeStatus.value, search: appliedSearch.value, page: pagination.value.page, per_page: pagination.value.per_page })
    items.value = data.items || []
    stats.value = data.stats || stats.value
    pagination.value = data.pagination || pagination.value
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'Could not load organization applications.'
  } finally {
    loading.value = false
  }
}

function setStatus(status) { activeStatus.value = status; pagination.value.page = 1; loadQueue() }
function applySearch() { appliedSearch.value = searchInput.value.trim(); pagination.value.page = 1; loadQueue() }
function goPage(page) { pagination.value.page = Math.min(Math.max(1, page), pagination.value.pages); loadQueue() }

async function openReview(id, updateQuery = true) {
  reviewOpen.value = true
  detailLoading.value = true
  detailError.value = ''
  detail.value = null
  serviceAreas.value = []
  documents.value = []
  history.value = []
  decisionNote.value = ''
  if (updateQuery && String(route.query.organization || '') !== String(id)) router.replace({ query: { ...route.query, organization: String(id) } })
  try {
    const data = await fetchOrganizationApplicationDetail(id)
    detail.value = data.organization
    serviceAreas.value = data.service_areas || []
    documents.value = data.documents || []
    history.value = data.history || []
  } catch (error) {
    detailError.value = error?.response?.data?.message || 'Could not load this organization application.'
  } finally {
    detailLoading.value = false
  }
}

function closeReview() {
  reviewOpen.value = false
  detail.value = null
  decisionNote.value = ''
  confirmDecision.value = ''
  if (route.query.organization) { const query = { ...route.query }; delete query.organization; router.replace({ query }) }
}

function chooseDecision(action) {
  if (['revision', 'reject'].includes(action) && decisionNote.value.trim().length < 8) { toast.error('Please provide a clear reason of at least 8 characters.'); return }
  confirmDecision.value = action
}

async function saveDecision() {
  if (!detail.value || !confirmDecision.value || reviewSaving.value) return
  reviewSaving.value = true
  try {
    const data = await submitOrganizationReview({ organization_id: detail.value.id, action: confirmDecision.value, note: decisionNote.value.trim() })
    toast.success(data.message || 'Organization review saved.')
    confirmDecision.value = ''
    await loadQueue()
    await openReview(detail.value.id, false)
    window.dispatchEvent(new Event('notifications-updated'))
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Could not save this organization decision.')
  } finally {
    reviewSaving.value = false
  }
}

async function previewDocument(document) {
  closePreview()
  previewOpen.value = true
  previewLoading.value = true
  previewName.value = document.original_name || documentLabel(document.doc_type)
  previewMime.value = document.mime_type || ''
  try {
    const blob = await fetchOrganizationDocument(document.id)
    previewMime.value = blob.type || previewMime.value
    previewUrl.value = URL.createObjectURL(blob)
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Could not open this document.')
    previewOpen.value = false
  } finally {
    previewLoading.value = false
  }
}

function closePreview() {
  previewOpen.value = false
  previewLoading.value = false
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  previewMime.value = ''
  previewName.value = ''
}

watch(() => route.query.organization, async (value) => { if (value && !reviewOpen.value) await openReview(Number(value), false) })

onMounted(async () => {
  await loadQueue()
  if (route.query.organization) await openReview(Number(route.query.organization), false)
})

onBeforeUnmount(() => closePreview())
</script>
