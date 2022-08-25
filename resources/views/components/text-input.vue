<script lang="ts" setup>
import { ref } from "vue";

defineProps(["modelValue"]);

defineEmits(["update:modelValue"]);

const input = ref<HTMLElement | null>(null);
</script>

<template>
    <input
        class="input"
        :value="modelValue"
        @input="(e: any) => $emit('update:modelValue', e.target.value)"
        ref="input"
    />
</template>
