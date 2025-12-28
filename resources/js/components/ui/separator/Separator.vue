<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

interface SeparatorProps {
  orientation?: 'horizontal' | 'vertical'
  decorative?: boolean
  class?: string
}

const props = withDefaults(defineProps<SeparatorProps>(), {
  orientation: 'horizontal',
  decorative: true,
})

const separatorClass = computed(() => {
  const baseClasses = 'shrink-0 bg-border'
  const orientationClasses = props.orientation === 'horizontal' 
    ? 'h-[1px] w-full' 
    : 'h-full w-[1px]'
  
  return cn(baseClasses, orientationClasses, props.class)
})
</script>

<template>
  <div
    :role="decorative ? 'none' : 'separator'"
    :aria-orientation="orientation"
    :class="separatorClass"
  />
</template>
