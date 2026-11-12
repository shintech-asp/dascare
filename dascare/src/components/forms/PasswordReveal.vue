<template>
  <!-- Same file in dascare_mobile/src/components/PasswordReveal.vue — keep in sync.
       Show/hide toggle (+ Caps Lock warning) for a password field — same eye icon as the login page.
       The page keeps its own <input> (and its scoped styles); bind the slot's
       `type` and give the input right padding (!pr-11) for the button:
         <PasswordReveal v-slot="{ type }"><input :type="type" class="… !pr-11" /></PasswordReveal> -->
  <div @keydown="onKey" @keyup="onKey" @focusout="capsOn = false">
   <div class="relative">
    <slot :type="shown ? 'text' : 'password'" />
    <button
      type="button"
      class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-400 hover:text-red-600 dark:text-white/40 dark:hover:text-red-400"
      :aria-label="shown ? 'Hide password' : 'Show password'"
      :title="shown ? 'Hide password' : 'Show password'"
      @click="shown = !shown"
    >
      <Icon :icon="shown ? 'line-md:watch' : 'line-md:watch-off'" width="20" height="20" />
    </button>
   </div>
   <p v-if="capsOn" class="mt-1 flex items-center gap-1 text-[0.7rem] font-bold text-amber-600 dark:text-amber-400" role="status"><Icon icon="lucide:arrow-big-up-dash" width="13" />Caps Lock is on</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Icon } from '@iconify/vue'

const shown = ref(false)
const capsOn = ref(false)
// Browsers report Caps Lock on any key event while the field is focused.
const onKey = (e) => { if (typeof e.getModifierState === 'function') capsOn.value = e.getModifierState('CapsLock') }
</script>
