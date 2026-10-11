<template>
  <!-- Show/hide toggle for a password field — same eye icon as the login page.
       The page keeps its own <input> (and its scoped styles); bind the slot's
       `type` and give the input right padding (!pr-11) for the button:
         <PasswordReveal v-slot="{ type }"><input :type="type" class="… !pr-11" /></PasswordReveal> -->
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
</template>

<script setup>
import { ref } from 'vue'
import { Icon } from '@iconify/vue'

const shown = ref(false)
</script>
