<template>
  <section class="min-h-screen flex items-center justify-center relative overflow-hidden py-16 px-4 bg-base-200 dark:bg-[#0a2038]">

    <!-- ambient wash, echoes hero.vue / CitizenDashboardHome.vue -->
    <div
      class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30"
      style="background: radial-gradient(ellipse at top left, rgba(220,38,38,0.06), transparent 55%);"
    ></div>

    <!-- Wider container so the form can sit next to the map instead of stacking tall -->
    <div class="relative z-10 w-full max-w-5xl mx-auto">
      <div class="relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-xl shadow-slate-200/60 dark:border-white/10 dark:bg-[#0F2A43] dark:shadow-black/30">

        <!-- subtle scanline texture, matching hero.vue's dark-mode panel -->
        <div
          class="pointer-events-none absolute inset-0 opacity-0 dark:opacity-[0.07]"
          style="background-image: repeating-linear-gradient(0deg, #fff 0px, #fff 1px, transparent 1px, transparent 3px);"
        ></div>

        <div class="relative p-8 sm:p-10">

          <div v-if="loading" class="text-center py-10 text-sm text-slate-500 dark:text-white/40">
            Loading your verification status…
          </div>

          <template v-else>
            <!-- Already approved -->
            <div v-if="status === 2" class="text-center max-w-sm mx-auto">
              <div class="w-20 h-20 mx-auto mb-6 rounded-2xl flex items-center justify-center bg-emerald-50 dark:bg-emerald-500/10">
                <Icon icon="lucide:shield-check" width="34" class="text-emerald-600 dark:text-emerald-400" />
              </div>
              <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-red-700 dark:text-red-300">
                <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
                Account Access
              </span>
              <h1 class="mt-4 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mb-2">You're already verified</h1>
              <p class="text-sm text-slate-500 dark:text-white/45 mb-6 leading-relaxed">Your account has full access to request emergency assistance.</p>
              <router-link :to="{ name: 'CitizenDashboardHome' }" class="text-[#1976D2] dark:text-[#7fb3ec] font-semibold hover:underline">Back to Dashboard</router-link>
            </div>

            <!-- Pending review -->
            <div v-else-if="status === 1" class="text-center max-w-sm mx-auto">
              <div class="w-20 h-20 mx-auto mb-6 rounded-2xl flex items-center justify-center bg-yellow-50 dark:bg-yellow-500/10">
                <Icon icon="lucide:hourglass" width="30" class="text-yellow-600 dark:text-yellow-400" />
              </div>
              <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-red-700 dark:text-red-300">
                <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
                Account Access
              </span>
              <h1 class="mt-4 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mb-2">Verification Under Review</h1>
              <p class="text-sm text-slate-500 dark:text-white/45 mb-2 leading-relaxed">
                Submitted on {{ formatDate(submittedAt) }}.
              </p>
              <p class="text-sm text-slate-500 dark:text-white/45 leading-relaxed">
                This usually takes <span class="font-semibold text-yellow-600 dark:text-yellow-400">24–48 hours</span>. You'll be notified once a decision is made.
              </p>
              <router-link to="/citizen/request" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white no-underline hover:bg-red-700"><Icon icon="lucide:siren" width="16"/>Request Emergency Assistance</router-link>
            </div>

            <!-- Not submitted yet, or rejected and needs resubmission -->
            <form v-else @submit.prevent="openConfirmModal">
              <div class="flex justify-center mb-6">
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center"
                  :class="status === 3 ? 'bg-red-50 dark:bg-red-500/10' : status === 4 ? 'bg-amber-50 dark:bg-amber-500/10' : 'bg-[#1976D2]/10'">
                  <Icon :icon="status === 3 ? 'lucide:x-circle' : status === 4 ? 'lucide:rotate-ccw' : 'lucide:shield-alert'" width="30"
                    :class="status === 3 ? 'text-red-600 dark:text-red-400' : status === 4 ? 'text-amber-600 dark:text-amber-400' : 'text-[#1976D2] dark:text-[#7fb3ec]'" />
                </div>
              </div>

              <div class="text-center">
                <span class="inline-flex items-center gap-2 rounded-full bg-red-50 dark:bg-red-500/10 px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-red-700 dark:text-red-300">
                  <span class="h-1.5 w-1.5 rounded-full bg-red-600 dark:bg-red-400"></span>
                  Account Access
                </span>

                <h1 class="mt-4 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white mb-2">
                  {{ status === 3 ? 'Re-submit Verification' : status === 4 ? 'Resubmission Requested' : 'Verify Your Account' }}
                </h1>
              </div>

              <div v-if="(status === 3 || status === 4) && rejectionNote"
                class="flex items-center justify-between gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl px-4 py-3 mt-4 mb-6 max-w-xl mx-auto">
                <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300 min-w-0">
                  <Icon icon="lucide:alert-circle" width="16" class="shrink-0" />
                  <span class="font-semibold truncate">{{ status === 4 ? 'Administrator requested a new submission' : 'Previous submission was rejected' }}</span>
                </div>
                <button type="button" @click="showRejectionModal = true"
                  class="text-xs font-bold text-red-700 dark:text-red-300 underline underline-offset-2 shrink-0">
                  View reason
                </button>
              </div>

              <p class="text-sm text-slate-500 dark:text-white/45 mb-6 text-center leading-relaxed max-w-xl mx-auto">
                Upload a valid government-issued ID to verify your DASCARE account and unlock verified-account features.
                Emergency assistance remains available even while verification is pending or requires correction.
              </p>
              <div class="mb-6 text-center"><router-link to="/citizen/request" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-black text-red-700 no-underline hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300"><Icon icon="lucide:siren" width="15"/>Need help now? Request emergency assistance</router-link></div>

              <!-- Wide layout: form fields on the left, map on the right -->
              <div class="bg-base-200 dark:bg-white/[0.03] border border-base-300 dark:border-white/10 rounded-2xl p-4 grid grid-cols-1 lg:grid-cols-5 gap-6">

                <!-- Left: form fields -->
                <div class="lg:col-span-3 space-y-4">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Birthdate</label>
                      <input v-model="birthdate" type="date" required :min="minBirthdate" :max="maxBirthdate"
                        class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white" />
                      <p class="text-xs text-slate-400 dark:text-white/30 mt-1.5">Must be at least 13 years old (max 85).</p>
                      <p v-if="birthdate && !isBirthdateValid" class="text-xs text-red-500 dark:text-red-400 mt-1.5">
                        You must be between 13 and 85 years old.
                      </p>
                      <div v-if="ageGroup && isBirthdateValid" class="mt-1.5">
                        <span v-if="ageGroup === 'minor'"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-800/40">
                          Minor (13–17) — limited IDs available
                        </span>
                        <span v-else-if="ageGroup === 'senior'"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-500/10 dark:text-purple-300 dark:border-purple-800/40">
                          Senior (60+) — Senior Citizen ID available
                        </span>
                        <span v-else
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-800/40">
                          Adult (18–59) — standard IDs available
                        </span>
                      </div>
                    </div>

                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Phone Number</label>
                      <input v-model="phoneNumber" type="tel" inputmode="numeric" required maxlength="11"
                        placeholder="09XXXXXXXXX"
                        @input="phoneNumber = phoneNumber.replace(/[^0-9]/g, '').slice(0, 11)"
                        class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white" />
                      <p v-if="phoneNumber && !isPhoneValid" class="text-xs text-red-500 dark:text-red-400 mt-1.5">
                        Enter an 11-digit PH mobile number starting with 09.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">ID Type</label>
                      <select v-model="idType" required :disabled="!isBirthdateValid"
                        class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white disabled:opacity-60">
                        <option value="" disabled>{{ !isBirthdateValid ? 'Enter your birthdate first' : 'Select an ID type' }}</option>
                        <option v-for="opt in eligibleIdTypes" :key="opt" :value="opt">{{ opt }}</option>
                      </select>
                      <p v-if="!isBirthdateValid" class="text-xs text-slate-400 dark:text-white/30 mt-1.5">
                        Available ID types depend on your age — enter your birthdate above first.
                      </p>
                    </div>

                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Government ID (image)</label>
                      <input type="file" accept="image/jpeg,image/png,image/webp" required :disabled="!idType" @change="onFileChange"
                        class="w-full text-sm text-slate-500 dark:text-white/45 file:mr-3 file:rounded-lg file:border-0 file:bg-red-600 file:text-white file:px-4 file:py-2 file:text-sm file:font-semibold disabled:opacity-60" />
                      <p class="text-xs text-slate-400 dark:text-white/30 mt-1.5">JPG, PNG, or WEBP, max 5MB.</p>
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Address / Subdivision</label>
                    <textarea v-model="address" required rows="2" maxlength="255" @blur="onAddressBlur"
                      placeholder="House/unit no., street, subdivision"
                      class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white resize-none"></textarea>
                    <p class="text-xs text-slate-400 dark:text-white/30 mt-1.5">
                      Just the house/unit no., street, and subdivision — barangay is picked separately below.
                    </p>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Barangay</label>
                      <select v-if="!barangaysLoadFailed" v-model="barangay" required :disabled="loadingBarangays" @change="onBarangayChange"
                        class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white disabled:opacity-60">
                        <option value="" disabled>{{ loadingBarangays ? 'Loading barangays…' : 'Select a barangay' }}</option>
                        <option v-for="b in barangaysList" :key="b" :value="b">{{ b }}</option>
                      </select>
                      <!-- Fallback to free text if the barangay API can't be reached -->
                      <input v-else v-model="barangay" type="text" required maxlength="120" @blur="onBarangayChange"
                        placeholder="Enter your barangay"
                        class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white" />
                      <p v-if="barangaysApiFailed" class="text-xs text-red-500 dark:text-red-400 mt-1.5">
                        Couldn't load barangays — enter yours manually, or
                        <button type="button" @click="retryBarangays" class="underline font-semibold hover:text-red-600 dark:hover:text-red-300">retry</button>.
                      </p>
                    </div>

                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">City / Municipality</label>
                      <input :value="city" type="text" disabled
                        class="w-full rounded-lg border border-slate-300 dark:border-white/15 bg-slate-100 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-500 dark:text-white/40" />
                      <p class="text-xs text-slate-400 dark:text-white/30 mt-1.5">Service area is currently Dasmariñas City only.</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Province</label>
                      <input :value="province" type="text" disabled
                        class="w-full rounded-lg border border-slate-300 dark:border-white/15 bg-slate-100 dark:bg-white/5 px-3 py-2.5 text-sm text-slate-500 dark:text-white/40" />
                    </div>

                    <div>
                      <label class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-white/70">Zip Code</label>
                      <select v-model="zipCode" required
                        class="w-full rounded-lg border border-base-300 dark:border-white/15 bg-base-100 dark:bg-[#0a2038] px-3 py-2.5 text-sm text-slate-900 dark:text-white">
                        <option value="" disabled>Select your zip code</option>
                        <option v-for="z in ALLOWED_ZIPS" :key="z" :value="z">{{ z }}</option>
                      </select>
                      <p class="text-xs text-slate-400 dark:text-white/30 mt-1.5">Auto-filled from your pin when possible.</p>
                    </div>
                  </div>
                </div>

                <!-- Right: map -->
                <div class="lg:col-span-2">
                  <div class="lg:sticky lg:top-6 space-y-1.5">
                    <div class="flex items-center justify-between mb-1.5">
                      <label class="block text-sm font-semibold text-slate-700 dark:text-white/70">Pin Your Location</label>
                      <button type="button" @click="useCurrentLocation" :disabled="locatingUser"
                        class="text-xs font-semibold text-[#1976D2] dark:text-[#7fb3ec] hover:underline disabled:opacity-50 inline-flex items-center gap-1">
                        <Icon icon="lucide:locate-fixed" width="13" />
                        {{ locatingUser ? 'Locating…' : 'Use my current location' }}
                      </button>
                    </div>
                    <div ref="mapContainer" class="w-full h-72 lg:h-[420px] rounded-lg border border-slate-300 dark:border-white/15 overflow-hidden"></div>
                    <p class="text-xs text-slate-400 dark:text-white/30">
                      Fill in your address/barangay to auto-pin a location, or tap the map to drop one yourself.
                    </p>
                    <p v-if="geocoding" class="text-xs text-[#1976D2] dark:text-[#7fb3ec]">
                      Looking up the location…
                    </p>
                    <p v-else-if="geocodeNote" class="text-xs text-[#1976D2] dark:text-[#7fb3ec]">
                      {{ geocodeNote }}
                    </p>
                  </div>
                </div>
              </div>

              <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-400 mt-4">{{ errorMessage }}</p>

              <button type="submit" :disabled="submitting || !isFormValid"
                class="w-full mt-6 py-3.5 rounded-xl font-bold text-white shadow-lg shadow-red-200 dark:shadow-red-950/40 transition-all active:scale-95 hover:bg-red-700 bg-red-600 disabled:opacity-50 disabled:shadow-none">
                {{ submitting ? 'Submitting…' : 'Submit for Verification' }}
              </button>
            </form>
          </template>
        </div>
      </div>
    </div>

    <!-- Confirmation modal — recap everything before it actually submits -->
    <Teleport to="body">
      <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeConfirmModal"></div>

        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-[#0F2A43] max-h-[90vh] overflow-y-auto">
          <div class="p-6 sm:p-7">
            <div class="flex items-center gap-3 mb-1">
              <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center bg-[#1976D2]/10">
                <Icon icon="lucide:shield-alert" width="20" class="text-[#1976D2] dark:text-[#7fb3ec]" />
              </div>
              <h2 class="text-lg font-bold text-slate-900 dark:text-white">Confirm your details</h2>
            </div>
            <p class="text-sm text-slate-500 dark:text-white/45 mb-5 leading-relaxed">
              Please review what you're about to submit for verification. You won't be able to edit this while it's under review.
            </p>

            <dl class="space-y-3 text-sm border-t border-slate-100 dark:border-white/10 pt-4">
              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Birthdate</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">
                  {{ formatBirthdate(birthdate) }}
                  <span v-if="ageGroup" class="text-slate-400 dark:text-white/35 font-normal">({{ ageGroup }})</span>
                </dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Phone Number</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">{{ phoneNumber }}</dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">ID Type</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">{{ idType }}</dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Government ID</dt>
                <dd class="text-right">
                  <div class="flex items-center gap-2 justify-end">
                    <img v-if="idFilePreviewUrl" :src="idFilePreviewUrl" alt="ID preview"
                      class="w-10 h-10 rounded-lg object-cover border border-slate-200 dark:border-white/15" />
                    <span class="font-medium text-slate-900 dark:text-white truncate max-w-[160px]">{{ idFile?.name }}</span>
                  </div>
                </dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Address</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white max-w-[65%]">{{ address }}</dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Barangay</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">{{ barangay }}</dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">City / Province</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">{{ city }}, {{ province }}</dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Zip Code</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">{{ zipCode }}</dd>
              </div>

              <div class="flex items-start justify-between gap-4">
                <dt class="text-slate-500 dark:text-white/45 shrink-0">Pinned Location</dt>
                <dd class="text-right font-medium text-slate-900 dark:text-white">
                  {{ latitude?.toFixed(5) }}, {{ longitude?.toFixed(5) }}
                </dd>
              </div>
            </dl>

            <p v-if="errorMessage" class="text-sm text-red-600 dark:text-red-400 mt-4">{{ errorMessage }}</p>

            <p class="text-sm font-semibold text-slate-800 dark:text-white/80 mt-5">
              Are you sure this information is correct?
            </p>

            <div class="flex gap-3 mt-4">
              <button type="button" @click="closeConfirmModal" :disabled="submitting"
                class="flex-1 py-2.5 rounded-xl font-semibold text-sm text-slate-700 dark:text-white/70 border border-slate-300 dark:border-white/15 hover:bg-slate-50 dark:hover:bg-white/5 disabled:opacity-50 transition-all">
                Go back &amp; edit
              </button>
              <button type="button" @click="submitConfirmed" :disabled="submitting"
                class="flex-1 py-2.5 rounded-xl font-bold text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 transition-all active:scale-95">
                {{ submitting ? 'Submitting…' : 'Yes, Submit' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Rejection reason modal — opens automatically on load if the most -->
    <!-- recent attempt was rejected, so the reason can't be missed; also -->
    <!-- re-openable via "View reason" on the inline banner above. -->
    <Teleport to="body">
      <div v-if="showRejectionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showRejectionModal = false"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-red-200 dark:border-red-500/20 bg-white shadow-2xl dark:border-red-500/20 dark:bg-[#0F2A43]">
          <div class="p-6 sm:p-7">
            <div class="flex items-center gap-3 mb-1">
              <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center bg-red-50 dark:bg-red-500/10">
                <Icon icon="lucide:x-circle" width="20" class="text-red-600 dark:text-red-400" />
              </div>
              <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ status === 4 ? 'Resubmission requested' : 'Submission rejected' }}</h2>
            </div>
            <p v-if="lastRejectedAt" class="text-xs text-slate-400 dark:text-white/35 mb-4">
              {{ formatDate(lastRejectedAt) }}{{ rejectionCount > 1 ? ` · attempt #${rejectionCount}` : '' }}
            </p>

            <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl p-4 text-sm text-red-700 dark:text-red-300 leading-relaxed whitespace-pre-line">
              {{ rejectionNote }}
            </div>

            <p class="text-sm text-slate-500 dark:text-white/45 mt-4 leading-relaxed">
              {{ status === 4 ? 'Please correct the issue above and submit a new verification.' : 'You may correct the issue above and submit a new verification.' }} Your previous answers weren't kept, so the form starts blank.
            </p>

            <button type="button" @click="showRejectionModal = false"
              class="w-full mt-5 py-2.5 rounded-xl font-bold text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 transition-all active:scale-95">
              {{ status === 4 ? 'Got it — I’ll resubmit' : 'Got it — I’ll review it' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import { Icon } from '@iconify/vue'
import { getSession, clearSessionCache, useSession } from '@/composables/useSession'

const API_BASE = import.meta.env.VITE_API_BASE_URL
const { fetchSession } = useSession()
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']

// PSGC (Philippine Standard Geographic Code) public API — no key required.
// Used to populate the Dasmariñas barangay dropdown. Same source as the
// Likhavite reference (psgc.gitlab.io — static JSON on GitLab Pages/CDN,
// no backend to rate-limit or cold-start).
const PSGC_BASE = 'https://psgc.gitlab.io/api'
// Dasmariñas City's fixed PSGC code — dascare serves this one city only,
// so unlike the reference there's no province-wide city picker, just a
// direct lookup of this city's barangays.
const DASMARINAS_CITY_CODE = '042106000'

// Zip codes valid for Dasmariñas City. Server-side ground truth lives in
// kyc_submit.php — keep this in sync with it.
const ALLOWED_ZIPS = ['4114', '4115', '4126']

// Which government IDs are realistically available at each age tier.
// Must stay in sync with $idTypesByAgeGroup in kyc_submit.php — the backend
// is the source of truth and re-validates this same mapping server-side, so
// a stale/edited list here just gets rejected rather than silently accepted.
const ID_TYPES_BY_AGE_GROUP = {
  minor: [
    'PhilSys (National ID)',
    'Passport',
    'Student ID',
    'PWD ID',
  ],
  adult: [
    'PhilSys (National ID)',
    'Passport',
    "Driver's License",
    'UMID',
    'SSS ID',
    'GSIS ID',
    'PhilHealth ID',
    'Pag-IBIG ID',
    'Postal ID',
    "Voter's ID",
    'PRC ID',
    'PWD ID',
    'OFW ID',
    "Seaman's Book",
  ],
  senior: [
    'PhilSys (National ID)',
    'Passport',
    "Driver's License",
    'UMID',
    'SSS ID',
    'GSIS ID',
    'PhilHealth ID',
    'Pag-IBIG ID',
    'Postal ID',
    "Voter's ID",
    'PRC ID',
    'PWD ID',
    'OFW ID',
    "Seaman's Book",
    'Senior Citizen ID',
  ],
}

// Loose bounding box around Dasmariñas City — mirrors the one enforced
// server-side in kyc_submit.php.
const DASMARINAS_BOUNDS = [[14.26, 120.87], [14.40, 121.00]]
const DASMARINAS_CENTER = [14.3294, 120.9367]

const loading = ref(true)
const submitting = ref(false)
const errorMessage = ref('')

const showConfirmModal = ref(false)
const showRejectionModal = ref(false)
const idFilePreviewUrl = ref(null)

const status = ref(0) // 0=unverified,1=pending,2=approved,3=rejected,4=resubmission requested
const submittedAt = ref(null)
const rejectionNote = ref('')
// Optional — only populated if the session payload includes them (mirrors
// kyc_verifications.rejection_count / last_rejected_at). Guarded with
// `?? null` / `?? 0` everywhere they're used so the modal still works fine
// if the session doesn't expose these yet.
const rejectionCount = ref(0)
const lastRejectedAt = ref(null)

const idType = ref('')
const idFile = ref(null)
const phoneNumber = ref('')
const birthdate = ref('')
const address = ref('')
const barangay = ref('')
const city = ref('Dasmariñas') // fixed — service area is Dasmariñas City only
const province = ref('Cavite') // fixed
const zipCode = ref('')
const latitude = ref(null)
const longitude = ref(null)
const locatingUser = ref(false)
const mapContainer = ref(null)

const barangaysList = ref([]) // [name, ...]
const loadingBarangays = ref(false)
const barangaysLoadFailed = ref(false)
// True only when a live PSGC barangay call was actually attempted and failed
// (as opposed to barangaysLoadFailed also being true simply because the
// city fetch hasn't run yet) — used to decide whether a "Retry" link is useful.
const barangaysApiFailed = ref(false)
const geocoding = ref(false)
const geocodeNote = ref('')
// 'manual' once the user taps the map / uses "current location" themselves;
// 'auto' when the pin was placed by forward-geocoding their typed address.
// A manual pin always wins — forward geocoding never overwrites it.
const pinSource = ref(null)

let leafletMap = null
let leafletMarker = null

// Bumped on every fetchBarangays call so a slow, stale request can't
// clobber a newer one's result when it finally resolves.
let barangaysRequestId = 0

// Public PSGC endpoints are occasionally flaky (cold starts, transient
// 5xx/rate-limits). Retry a couple of times with backoff before giving up
// and falling back — this is what a manual page refresh was doing anyway.
const FETCH_RETRIES = 2
const FETCH_RETRY_DELAY_MS = 700

// ─── Birthdate bounds (min age 13, max age 85) ────────────────
const today = new Date()
function isoDateMinusYears(years) {
  const d = new Date(today)
  d.setFullYear(d.getFullYear() - years)
  return d.toISOString().slice(0, 10)
}
const minBirthdate = isoDateMinusYears(85) // oldest allowed birthdate
const maxBirthdate = isoDateMinusYears(13) // youngest allowed birthdate

function calculateAge(dateStr) {
  const bd = new Date(dateStr)
  if (Number.isNaN(bd.getTime())) return null
  let age = today.getFullYear() - bd.getFullYear()
  const monthDiff = today.getMonth() - bd.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < bd.getDate())) age--
  return age
}

// ─── Client-side validation (server re-validates everything too) ─
const isPhoneValid = computed(() => /^09\d{9}$/.test(phoneNumber.value))

const isBirthdateValid = computed(() => {
  if (!birthdate.value) return false
  const age = calculateAge(birthdate.value)
  return age !== null && age >= 13 && age <= 85
})

// ─── Age-tiered ID type gating ─────────────────────────────────
// 'minor' (13-17), 'adult' (18-59), 'senior' (60+) — null until a valid
// birthdate is entered, which is what keeps the ID Type field locked.
const ageGroup = computed(() => {
  if (!isBirthdateValid.value) return null
  const age = calculateAge(birthdate.value)
  if (age < 18) return 'minor'
  if (age >= 60) return 'senior'
  return 'adult'
})

const eligibleIdTypes = computed(() => ID_TYPES_BY_AGE_GROUP[ageGroup.value] || [])

// If the birthdate changes (or is cleared) after an ID type was already
// picked, and that ID is no longer valid for the new age bracket, clear it
// rather than silently submitting a mismatched ID type.
watch(eligibleIdTypes, (list) => {
  if (idType.value && !list.includes(idType.value)) {
    idType.value = ''
    idFile.value = null
  }
})

const isFormValid = computed(() =>
  !!idType.value &&
  !!idFile.value &&
  isPhoneValid.value &&
  isBirthdateValid.value &&
  address.value.trim().length >= 5 &&
  !!barangay.value.trim() &&
  ALLOWED_ZIPS.includes(zipCode.value) &&
  latitude.value !== null &&
  longitude.value !== null
)

function setIdFilePreview(file) {
  if (idFilePreviewUrl.value) URL.revokeObjectURL(idFilePreviewUrl.value)
  idFilePreviewUrl.value = file ? URL.createObjectURL(file) : null
}

function onFileChange(e) {
  const file = e.target.files?.[0] ?? null
  errorMessage.value = ''

  if (file) {
    if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
      errorMessage.value = 'Only JPG, PNG, or WEBP images are allowed.'
      idFile.value = null
      setIdFilePreview(null)
      e.target.value = ''
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      errorMessage.value = 'File is too large — max 5MB.'
      idFile.value = null
      setIdFilePreview(null)
      e.target.value = ''
      return
    }
  }

  idFile.value = file
  setIdFilePreview(file)
}

function formatBirthdate(value) {
  if (!value) return ''
  // value is a plain 'YYYY-MM-DD' from <input type="date">, not UTC-safe
  // to hand straight to `new Date()` (can roll back a day depending on
  // timezone), so parse it as local explicitly.
  const [y, m, d] = value.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString(undefined, {
    year: 'numeric', month: 'long', day: 'numeric',
  })
}

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleString()
}

// ─── Name matching helper (PSGC labels don't always match casual typed
// guesses exactly, e.g. "Zone II" vs "Zone 2") ───
function normalizeName(str) {
  return (str || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // strip accents
    .toLowerCase()
    .replace(/\(.*?\)/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
}

function findMatchingBarangay(guess) {
  const target = normalizeName(guess)
  if (!target) return ''
  return barangaysList.value.find(b => normalizeName(b) === target)
    || barangaysList.value.find(b => normalizeName(b).includes(target) || target.includes(normalizeName(b)))
    || ''
}

// ─── PSGC: barangays of Dasmariñas City ───
async function fetchWithRetry(url) {
  let lastErr
  for (let attempt = 0; attempt <= FETCH_RETRIES; attempt++) {
    try {
      const res = await fetch(url)
      if (!res.ok) throw new Error(`PSGC request failed: ${res.status}`)
      return await res.json()
    } catch (err) {
      lastErr = err
      if (attempt < FETCH_RETRIES) {
        await new Promise(r => setTimeout(r, FETCH_RETRY_DELAY_MS * (attempt + 1)))
      }
    }
  }
  throw lastErr
}

async function fetchBarangays() {
  barangaysList.value = []
  barangaysLoadFailed.value = false
  barangaysApiFailed.value = false
  const myRequestId = ++barangaysRequestId
  loadingBarangays.value = true
  try {
    const data = await fetchWithRetry(`${PSGC_BASE}/cities-municipalities/${DASMARINAS_CITY_CODE}/barangays/`)
    const raw = Array.isArray(data) ? data : []
    const list = raw.map(b => b.name).sort()
    if (!list.length) throw new Error('empty barangay list')

    if (myRequestId !== barangaysRequestId) return
    barangaysList.value = list
  } catch (err) {
    console.error('Failed to load Dasmariñas barangays from PSGC', err)
    if (myRequestId !== barangaysRequestId) return
    barangaysLoadFailed.value = true
    barangaysApiFailed.value = true
  } finally {
    if (myRequestId === barangaysRequestId) loadingBarangays.value = false
  }
}

function retryBarangays() {
  fetchBarangays()
}

function onBarangayChange() {
  forwardGeocode()
}

function onAddressBlur() {
  forwardGeocode()
}

// ─── Reverse geocoding (OpenStreetMap Nominatim, free/no key) ─────
// Auto-fills street address, barangay, and zip from the dropped pin.
async function reverseGeocode(lat, lng) {
  geocoding.value = true
  geocodeNote.value = ''
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&addressdetails=1&zoom=18`
    )
    const data = await res.json()
    const addr = data.address || {}

    const streetParts = [addr.house_number, addr.road].filter(Boolean)
    if (streetParts.length) {
      address.value = streetParts.join(' ')
    }

    const brgyGuess = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || ''
    if (brgyGuess) {
      // give an in-flight barangay fetch a moment to resolve
      let attempts = 0
      while (loadingBarangays.value && attempts < 20) {
        await new Promise(r => setTimeout(r, 150))
        attempts++
      }
      const matchedBarangay = findMatchingBarangay(brgyGuess)
      if (matchedBarangay) barangay.value = matchedBarangay
    }

    if (addr.postcode && ALLOWED_ZIPS.includes(addr.postcode)) {
      zipCode.value = addr.postcode
    }

    geocodeNote.value = 'Address auto-filled from your pin — please double-check it.'
  } catch (err) {
    console.error('Reverse geocoding failed', err)
    geocodeNote.value = ''
  } finally {
    geocoding.value = false
  }
}

function isWithinDasmarinas(lat, lng) {
  const [[south, west], [north, east]] = DASMARINAS_BOUNDS
  return lat >= south && lat <= north && lng >= west && lng <= east
}

// ─── Forward geocoding (address/barangay → pin) ──────────────
// The reverse of reverseGeocode(): auto-places a pin from what the user has
// typed so far, so they rarely see a blank map. Never overrides a pin the
// user placed themselves (pinSource === 'manual') — a hand-placed pin is
// more trustworthy than a text-address guess, so it always wins.
async function forwardGeocode() {
  if (pinSource.value === 'manual') return

  const queryParts = [address.value.trim(), barangay.value, 'Dasmariñas', 'Cavite', 'Philippines'].filter(Boolean)
  const query = queryParts.join(', ')
  if (!query) return

  geocoding.value = true
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&countrycodes=ph&limit=1`
    )
    const data = await res.json()
    const hit = Array.isArray(data) ? data[0] : null
    if (!hit) return

    const lat = parseFloat(hit.lat)
    const lng = parseFloat(hit.lon)
    if (Number.isNaN(lat) || Number.isNaN(lng) || !isWithinDasmarinas(lat, lng)) return

    // A second guard against a stale/manual pin sneaking in while this
    // request was in flight (e.g. the user tapped the map mid-lookup).
    if (pinSource.value === 'manual') return

    setPin(lat, lng)
    pinSource.value = 'auto'
    if (leafletMap) leafletMap.setView([lat, lng], barangay.value ? 15 : 13)
    geocodeNote.value = 'Pinned an approximate location based on your address — tap the map to fine-tune it.'
  } catch (err) {
    console.error('Forward geocoding failed', err)
  } finally {
    geocoding.value = false
  }
}

// ─── Map (Leaflet loaded from CDN, no npm dependency needed) ──
function loadLeaflet() {
  return new Promise((resolve, reject) => {
    if (window.L) { resolve(window.L); return }

    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    document.head.appendChild(link)

    const script = document.createElement('script')
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
    script.onload = () => resolve(window.L)
    script.onerror = () => reject(new Error('Failed to load map'))
    document.head.appendChild(script)
  })
}

function setPin(lat, lng) {
  latitude.value = lat
  longitude.value = lng
  if (!leafletMap) return

  if (leafletMarker) {
    leafletMarker.setLatLng([lat, lng])
  } else {
    leafletMarker = window.L.marker([lat, lng]).addTo(leafletMap)
  }
}

async function initMap() {
  try {
    const L = await loadLeaflet()
    await nextTick()
    if (!mapContainer.value || leafletMap) return

    const bounds = L.latLngBounds(DASMARINAS_BOUNDS)

    leafletMap = L.map(mapContainer.value, {
      maxBounds: bounds.pad(0.05),
      maxBoundsViscosity: 1.0,
      minZoom: 11,
    }).setView(DASMARINAS_CENTER, 13)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(leafletMap)

    // Draw the Dasmariñas boundary box so it's visually obvious what's selectable
    L.rectangle(bounds, { color: '#1976D2', weight: 1, fillOpacity: 0.03, dashArray: '4 4' }).addTo(leafletMap)

    leafletMap.on('click', (e) => {
      const { lat, lng } = e.latlng
      if (!isWithinDasmarinas(lat, lng)) {
        errorMessage.value = 'Please pin a location within Dasmariñas City.'
        return
      }
      errorMessage.value = ''
      setPin(lat, lng)
      pinSource.value = 'manual'
      reverseGeocode(lat, lng)
    })
  } catch (err) {
    console.error('Map failed to load', err)
    errorMessage.value = 'Unable to load the map. You can still fill in the rest of the form.'
  }
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    errorMessage.value = 'Location is not supported on this device/browser.'
    return
  }

  locatingUser.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const { latitude: lat, longitude: lng } = pos.coords
      locatingUser.value = false
      if (!isWithinDasmarinas(lat, lng)) {
        errorMessage.value = 'Your current location is outside Dasmariñas City — please pin your address manually instead.'
        return
      }
      errorMessage.value = ''
      setPin(lat, lng)
      pinSource.value = 'manual'
      if (leafletMap) leafletMap.setView([lat, lng], 16)
      reverseGeocode(lat, lng)
    },
    () => {
      errorMessage.value = 'Unable to get your current location. Please pin it on the map instead.'
      locatingUser.value = false
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

// Same field-by-field checks as before, just no longer submitting directly —
// only opens the confirmation modal once everything checks out. Kept
// separate from isFormValid (which just gates the button) because these
// give a specific message for *why*, instead of the button merely staying
// disabled.
function validate() {
  errorMessage.value = ''

  if (!idFile.value) {
    errorMessage.value = 'Please attach a photo of your ID.'
    return false
  }
  if (!ALLOWED_IMAGE_TYPES.includes(idFile.value.type)) {
    errorMessage.value = 'Only JPG, PNG, or WEBP images are allowed.'
    return false
  }
  if (idFile.value.size > 5 * 1024 * 1024) {
    errorMessage.value = 'File is too large — max 5MB.'
    return false
  }
  if (!isPhoneValid.value) {
    errorMessage.value = 'Please enter a valid PH mobile number (e.g. 09123456789).'
    return false
  }
  if (!isBirthdateValid.value) {
    errorMessage.value = 'You must be between 13 and 85 years old to verify an account.'
    return false
  }
  if (address.value.trim().length < 5) {
    errorMessage.value = 'Please enter your house/unit no., street, and subdivision.'
    return false
  }
  if (!barangay.value.trim()) {
    errorMessage.value = 'Please select a barangay.'
    return false
  }
  if (!ALLOWED_ZIPS.includes(zipCode.value)) {
    errorMessage.value = 'Please select a valid zip code.'
    return false
  }
  if (latitude.value === null || longitude.value === null) {
    errorMessage.value = 'Please pin your location on the map.'
    return false
  }

  return true
}

function openConfirmModal() {
  if (!validate()) return
  showConfirmModal.value = true
}

function closeConfirmModal() {
  if (submitting.value) return // don't let a stray backdrop click cancel an in-flight submit
  showConfirmModal.value = false
}

async function submitConfirmed() {
  errorMessage.value = ''
  submitting.value = true
  try {
    const form = new FormData()
    form.append('id_type', idType.value)
    form.append('id_image', idFile.value)
    form.append('phone_number', phoneNumber.value)
    form.append('birthdate', birthdate.value)
    form.append('address', address.value.trim())
    form.append('barangay', barangay.value.trim())
    form.append('zip_code', zipCode.value)
    form.append('latitude', latitude.value)
    form.append('longitude', longitude.value)

    await axios.post(`${API_BASE}/citizen/kyc_submit.php`, form, {
      withCredentials: true,
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    clearSessionCache() // force the guard/dashboard wall to see the new pending status
    showConfirmModal.value = false
    showRejectionModal.value = false
    status.value = 1
    submittedAt.value = new Date().toISOString()
  } catch (err) {
    // Surfaced inside the modal (see errorMessage block there) so the user
    // can see what went wrong without losing the review context, and can
    // retry via the same "Yes, Submit" button without re-filling anything.
    errorMessage.value = err.response?.data?.message || 'Submission failed. Please try again.'
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  try {
    await fetchSession(true)
    const session = getSession()
    status.value = session.user?.kyc_status ?? 0
    submittedAt.value = session.user?.kyc_submitted_at ?? null
    rejectionNote.value = session.user?.kyc_note ?? ''
    rejectionCount.value = session.user?.kyc_rejection_count ?? 0
    lastRejectedAt.value = session.user?.kyc_last_rejected_at ?? null

    // Surface the reason automatically — this is the whole point of the
    // modal: someone landing here after a rejection shouldn't have to go
    // hunting for why before they can even see the resubmission form.
    if ((status.value === 3 || status.value === 4) && rejectionNote.value) {
      showRejectionModal.value = true
    }
  } catch (err) {
    console.error('Failed to load session for verification page', err)
  } finally {
    loading.value = false
    // Only the "not submitted / rejected" branch renders the form + map.
    if (status.value === 0 || status.value === 3 || status.value === 4) {
      await nextTick()
      initMap()
      fetchBarangays()
    }
  }
})

onBeforeUnmount(() => {
  if (leafletMap) {
    leafletMap.remove()
    leafletMap = null
    leafletMarker = null
  }
  if (idFilePreviewUrl.value) URL.revokeObjectURL(idFilePreviewUrl.value)
})
</script>