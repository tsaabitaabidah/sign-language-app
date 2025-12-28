<script setup lang="ts">
import { computed } from 'vue'

interface ProgressProps {
  value?: number
  class?: string
}

const props = withDefaults(defineProps<ProgressProps>(), {
  value: 0
})

const percentage = computed(() => {
  return Math.min(100, Math.max(0, props.value || 0))
})
</script>

<template>
  <div 
    :class="[
      'relative w-full overflow-hidden rounded-full bg-gray-200',
      props.class
    ]"
  >
    <div
      class="h-full bg-blue-600 transition-all duration-300 ease-in-out"
      :style="{ width: `${percentage}%` }"
    />
  </div>
</template>
