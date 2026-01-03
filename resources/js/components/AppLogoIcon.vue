<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <div
        :class="['bg-gradient-to-br from-violet-600 to-indigo-600', className]"
        v-bind="$attrs"
        style="
            mask-image: url('/logo-hand.png');
            -webkit-mask-image: url('/logo-hand.png');
            mask-size: contain;
            -webkit-mask-size: contain;
            mask-repeat: no-repeat;
            -webkit-mask-repeat: no-repeat;
            mask-position: center;
            -webkit-mask-position: center;
        "
    ></div>
</template>
