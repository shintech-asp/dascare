<template>
  <section class="dark:bg-[#0a2038]">
    <Hero />
    <HowItWorks />
    <DecisionSupport />
    <RolesSection />
    <MobileApp />
    <CTASection />
  </section>
</template>

<script setup>
import Hero from './landing/hero.vue'
import HowItWorks from './landing/HowItWorks.vue'
import DecisionSupport from './landing/DecisionSupport.vue'
import RolesSection from './landing/RolesSection.vue'
import MobileApp from './landing/MobileApp.vue'
import CTASection from './landing/CTASection.vue'
import { onMounted, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'

// Same hash-scrolling approach as the other system's Home.vue: on load (and
// whenever the hash changes without a full page reload) scroll to the
// matching section, retrying briefly in case the target isn't mounted yet.
// Now load-bearing: #how-it-works, #decision-support, #roles, and
// #mobile-app are all real anchor targets used by the footer links.
const route = useRoute()

const scrollToHash = async () => {
  if (!route.hash) return
  const id = route.hash.replace('#', '')
  await nextTick()
  let tries = 0
  const maxTries = 30
  const attemptScroll = () => {
    const el = document.getElementById(id)
    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); return }
    tries++
    if (tries < maxTries) setTimeout(attemptScroll, 150)
  }
  attemptScroll()
}

onMounted(scrollToHash)
watch(() => route.hash, scrollToHash)
</script>