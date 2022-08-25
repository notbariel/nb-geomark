<script setup lang="ts">
import { computed, onMounted, watch } from "vue";
import { Head } from "@inertiajs/inertia-vue3";
import { useMapStore } from "@/scripts/stores/mapStore.js";
import debounce from "lodash/debounce";
import { LngLat } from "mapbox-gl";
import GeneratorSearch from "@/views/components/generator/search.vue";
import GeneratorForm from "@/views/components/generator/form.vue";

const mapStore = useMapStore();

const mapElementIsReady = computed(() => mapStore.mapElementIsReady);

// when the element is ready, create the map
watch(mapElementIsReady, (isReady: boolean) => {
    if (isReady) {
        mapStore.createInteractiveMap();
    }
});

onMounted(() => {
    if (mapElementIsReady.value) {
        mapStore.createInteractiveMap();
    }
});

const coordinates = computed(() => mapStore.coordinates);

// when coordinates is updated, fetch the closest place
watch(
    coordinates,
    debounce(async (value: LngLat) => {
        await mapStore.reverseGeocode(value, { limit: 1 }).then((res) => {
            if (res?.data?.features) {
                mapStore.setGeneratorPlace(res.data.features[0]);
            }
        });
    }, 500)
);
</script>

<template layout="default">
    <Head title="Tag Generator" />

    <generator-search></generator-search>

    <generator-form></generator-form>
</template>
