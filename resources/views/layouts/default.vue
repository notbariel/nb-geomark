<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Link, usePage } from "@inertiajs/inertia-vue3";
import { useMapStore } from "@/scripts/stores/mapStore.js";
import MapControls from "@/views/components/map/controls.vue";
import LoadingIcon from "@/views/components/loading-icon.vue";
import { LightningBoltIcon, BadgeCheckIcon } from "@heroicons/vue/outline";

const mapStore = useMapStore();

const currentPage = computed(() => usePage().component.value);

const mapElement = ref<HTMLElement>();

onMounted(() => {
    mapStore.setMapElement(mapElement.value!);
});
</script>

<template>
    <div class="relative w-screen h-screen overflow-hidden">
        <transition
            enter-active-class="transition ease-out duration-250"
            enter-from-class="-translate-x-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition ease-in duration-250"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-x-full opacity-0"
            appear
        >
            <div
                class="absolute top-0 left-0 z-20 flex flex-col w-full max-w-md max-h-screen p-3 transition-transform"
            >
                <div
                    class="shadow-xl card rounded-box bg-base-100 text-base-content"
                >
                    <ul
                        class="z-10 w-full rounded-b-none shadow menu menu-horizontal bg-base-100 rounded-box"
                    >
                        <li
                            class="w-1/2"
                            :class="[
                                currentPage === 'generator'
                                    ? 'bordered text-primary font-semibold'
                                    : '',
                            ]"
                        >
                            <Link
                                href="/generator"
                                class="flex items-center flex-1 gap-2"
                            >
                                <LightningBoltIcon
                                    class="w-8 h-8 opacity-80"
                                ></LightningBoltIcon>

                                <span>Tag Generator</span>
                            </Link>
                        </li>
                        <li
                            class="w-1/2"
                            :class="[
                                currentPage === 'validator'
                                    ? 'bordered text-primary font-semibold'
                                    : '',
                            ]"
                        >
                            <Link
                                href="/validator"
                                class="flex items-center flex-1 gap-2"
                            >
                                <BadgeCheckIcon
                                    class="w-8 h-8 opacity-80"
                                ></BadgeCheckIcon>

                                <span>Tag Validator</span>
                            </Link>
                        </li>
                    </ul>

                    <div class="gap-4 overflow-y-auto card-body">
                        <slot />
                    </div>
                </div>
            </div>
        </transition>

        <div class="relative w-full h-full">
            <map-controls></map-controls>

            <div class="w-full h-full" ref="mapElement"></div>

            <div
                v-if="!mapStore.mapIsLoaded"
                class="absolute inset-0 flex items-start justify-end p-3 bg-base-200 text-primary"
            >
                <loading-icon class="w-8 h-8"></loading-icon>
            </div>
        </div>
    </div>
</template>
