<script lang="ts">
export default {
    name: "generator-form",
};
</script>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useClipboard } from "@vueuse/core";
import { useMapStore } from "@/scripts/stores/mapStore.js";
import { allCountries } from "country-region-data";
import TextInput from "@/views/components/text-input.vue";
import SelectInput from "@/views/components/select-input.vue";
import { PlusIcon, MinusIcon } from "@heroicons/vue/outline";

const { copy, copied, isSupported } = useClipboard();

const mapStore = useMapStore();

const selectedCountry = ref<string>("");
const selectedRegion = ref<string>("");
const selectedPlace = ref<string>("");
const dublinCoreTitle = ref<string>("");

const coordinates = computed(() => mapStore.coordinates);

const countries = allCountries;

const regions = computed(() => {
    if (!selectedCountry.value) {
        return [];
    }

    let region = countries.find((item: any) => {
        return item[1] == selectedCountry.value;
    });

    if (!region) {
        return [];
    }

    return region[2].filter((item: any) => {
        return item[1] != "undefined";
    });
});

const generatorPlaceData = computed(() => {
    let data: { country: string; region: string; place: string } = {
        country: "",
        region: "",
        place: "",
    };

    if (!mapStore.generator.place) {
        return data;
    }

    // country
    let country = mapStore.generator.place.context.find((item: any) => {
        return item.id.includes("country.");
    });

    if (country && country.short_code) {
        data.country = country.short_code.toUpperCase();
    }

    // region
    let region = mapStore.generator.place.context.find((item: any) => {
        return item.id.includes("region.");
    });

    if (region && region.short_code) {
        let s = region.short_code.split("-");
        data.region = s[s.length - 1].toUpperCase();
    }

    // place
    let place = mapStore.generator.place.context.find((item: any) => {
        return item.id.includes("place.");
    });

    if (place && place.text) {
        data.place = place.text;
    }

    return data;
});

// when place updates,
// sync form inputs
watch(
    generatorPlaceData,
    (value: any) => {
        selectedCountry.value = countries.find(
            (item: any) => item[1] == generatorPlaceData.value.country
        )
            ? generatorPlaceData.value.country
            : "";

        selectedRegion.value = regions.value.find(
            (item: any) => item[1] == generatorPlaceData.value.region
        )
            ? generatorPlaceData.value.region
            : "";

        selectedPlace.value = generatorPlaceData.value.place;
    },
    { deep: true }
);

const codeRef = ref();

const generatorCode = computed(() => {
    let code: string = "";

    if (dublinCoreTitle.value) {
        code += `<meta name="DC.title" content="${dublinCoreTitle.value}" />\n`;
    }

    if (selectedCountry.value) {
        let shortCode = selectedCountry.value;

        if (selectedRegion.value) {
            shortCode += `-${selectedRegion.value}`;
        }

        code += `<meta name="geo.region" content="${shortCode}" />\n`;
    }

    if (selectedPlace.value) {
        code += `<meta name="geo.placename" content="${selectedPlace.value}" />\n`;
    }

    if (coordinates?.value) {
        code += `<meta name="geo.position" content="${mapStore.lat};${mapStore.lng}" />\n`;
        code += `<meta name="ICBM" content="${mapStore.lat}, ${mapStore.lng}" />\n`;
    }

    return code;
});

function copyCode() {
    if (isSupported) {
        copy(generatorCode.value);
    } else {
        let target = codeRef.value;

        if (target) {
            if (document.body.createTextRange) {
                let range = document.body.createTextRange();
                range.moveToElementText(target);
                range.select();
            } else if (window.getSelection) {
                let selection = window.getSelection();
                let range = document.createRange();
                range.selectNodeContents(target);
                selection?.removeAllRanges();
                selection?.addRange(range);
            }
        }
    }
}
</script>

<template>
    <div>
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <div
                    class="flex-1 w-full border stats stats-vertical sm:stats-horizontal border-base-content/10 bg-base-200"
                >
                    <div class="px-3 py-2 stat">
                        <div class="stat-title">Longitude</div>
                        <div class="text-2xl stat-value text-primary">
                            {{ mapStore.lng }}
                        </div>
                    </div>

                    <div class="px-3 py-2 stat">
                        <div class="stat-title">Latitude</div>
                        <div class="text-2xl stat-value text-primary">
                            {{ mapStore.lat }}
                        </div>
                    </div>
                </div>

                <div class="btn-group btn-group-vertical">
                    <button
                        @click="mapStore.increaseAccuracy()"
                        class="btn btn-ghost btn-sm btn-circle"
                        title="Increase accuracy"
                    >
                        <PlusIcon class="w-4 h-4"></PlusIcon>
                        <span class="sr-only">Increase accuracy</span>
                    </button>
                    <button
                        @click="mapStore.decreaseAccuracy()"
                        class="btn btn-ghost btn-sm btn-circle"
                        title="Decrease accuracy"
                    >
                        <MinusIcon class="w-4 h-4"></MinusIcon>
                        <span class="sr-only">Decrease accuracy</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <div class="form-control">
                    <label class="label" for="">
                        <span class="label-text">Country</span>
                        <span
                            v-if="selectedCountry"
                            class="font-bold label-text-alt"
                            >{{ selectedCountry }}</span
                        >
                    </label>
                    <select-input
                        :items="countries"
                        emptyOptionText="Select country"
                        v-model="selectedCountry"
                        @change="selectedRegion = ''"
                        class="w-full select-bordered select-sm sm:select-md"
                    >
                    </select-input>
                </div>

                <div class="form-control">
                    <label class="label" for="">
                        <span class="label-text">Region</span>
                        <span
                            v-if="selectedRegion"
                            class="font-bold label-text-alt"
                            >{{ selectedRegion }}</span
                        >
                    </label>
                    <select-input
                        :items="regions"
                        emptyOptionText="Select region"
                        v-model="selectedRegion"
                        class="w-full select-bordered select-sm sm:select-md"
                    >
                    </select-input>
                </div>

                <div class="form-control">
                    <label class="label" for="">
                        <span class="label-text">Place</span>
                    </label>
                    <text-input
                        v-model="selectedPlace"
                        class="w-full input-bordered input-sm sm:input-md"
                        placeholder="Place"
                    >
                    </text-input>
                </div>

                <div class="form-control">
                    <label class="label" for="">
                        <span class="label-text"
                            >Dublic Core Title (optional)</span
                        >
                    </label>
                    <text-input
                        v-model="dublinCoreTitle"
                        class="w-full input-bordered input-sm sm:input-md"
                        placeholder="Dublic Core Title"
                    >
                    </text-input>
                </div>

                <div class="mt-2 form-control">
                    <label class="label" for="">
                        <span class="label-text">Generated tag</span>
                    </label>
                    <div
                        class="p-4 overflow-x-auto overflow-y-hidden rounded-box bg-base-300 text-base-content"
                    >
                        <pre v-text="generatorCode" ref="codeRef"></pre>
                    </div>
                </div>

                <div class="mt-2 form-control">
                    <button
                        @click="copyCode()"
                        type="button"
                        class="normal-case btn btn-block btn-lg"
                        :class="[copied ? 'btn-success' : 'btn-primary']"
                    >
                        {{ !copied ? `Copy tag` : `Copied!` }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
