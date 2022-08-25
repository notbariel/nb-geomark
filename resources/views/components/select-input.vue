<script lang="ts" setup>
import { ref } from "vue";

defineProps(["modelValue", "items", "emptyOptionText"]);

defineEmits(["update:modelValue"]);

const select = ref<HTMLElement | null>(null);
</script>

<template>
    <select
        :value="modelValue"
        @change="(e: any) => $emit('update:modelValue', e.target.value)"
        ref="select"
        class="select"
    >
        <option v-if="emptyOptionText" value="">{{ emptyOptionText }}</option>
        <option v-for="item in items" :value="item[1]">
            {{ item[0] }}
        </option>
    </select>
</template>
