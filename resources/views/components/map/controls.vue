<script setup lang="ts">
import { computed } from "vue";
import { useMapStore } from "@/scripts/stores/mapStore.js";
import {
    PlusIcon,
    MinusIcon,
    SunIcon,
    MoonIcon,
    LocationMarkerIcon,
} from "@heroicons/vue/outline";
import { usePage } from "@inertiajs/inertia-vue3";

const mapStore = useMapStore();

const currentPage = computed(() => usePage().component.value);
</script>

<template>
    <div class="absolute bottom-0 right-0 z-10 p-3 mb-10">
        <div class="flex flex-col items-center gap-3">
            <div class="rounded-full shadow-xl btn-group btn-group-vertical">
                <button
                    @click="mapStore.toggleDarkMode()"
                    class="btn btn-circle"
                >
                    <SunIcon
                        v-if="mapStore.isDarkMode"
                        class="w-6 h-6"
                    ></SunIcon>
                    <MoonIcon v-else class="w-6 h-6"></MoonIcon>
                    <span class="sr-only">
                        {{
                            mapStore.isDarkMode
                                ? `Change map to light mode`
                                : `Change map to dark mode`
                        }}
                    </span>
                </button>
            </div>

            <div
                v-if="currentPage !== 'validator'"
                class="rounded-full shadow-xl btn-group btn-group-vertical"
            >
                <button @click="mapStore.moveToMarker()" class="btn btn-circle">
                    <LocationMarkerIcon class="w-6 h-6"></LocationMarkerIcon>
                    <span class="sr-only"> Move to marker </span>
                </button>
            </div>

            <div class="rounded-full shadow-xl btn-group btn-group-vertical">
                <button
                    @click="mapStore.zoomIn()"
                    class="btn btn-circle"
                    :disabled="mapStore.isMaxZoom"
                >
                    <PlusIcon class="w-6 h-6"></PlusIcon>
                    <span class="sr-only"> Zoom in </span>
                </button>

                <button
                    @click="mapStore.zoomOut()"
                    class="btn btn-circle"
                    :disabled="mapStore.isMinZoom"
                >
                    <MinusIcon class="w-6 h-6"></MinusIcon>
                    <span class="sr-only"> Zoom out </span>
                </button>
            </div>
        </div>
    </div>
</template>
