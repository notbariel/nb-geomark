<script setup lang="ts">
import { toRefs, computed, watch, onMounted, nextTick } from "vue";
import { Head, useForm } from "@inertiajs/inertia-vue3";
import { useMapStore } from "@/scripts/stores/mapStore.js";
import has from "lodash/has";
import { LngLat } from "mapbox-gl";
import TextInput from "@/views/components/text-input.vue";
import LoadingIcon from "@/views/components/loading-icon.vue";
import {
    LinkIcon,
    ArrowRightIcon,
    ExclamationIcon,
    XIcon,
    QuestionMarkCircleIcon,
} from "@heroicons/vue/outline";
import { CheckCircleIcon, XCircleIcon } from "@heroicons/vue/solid";

const mapStore = useMapStore();

const mapElementIsReady = computed(() => mapStore.mapElementIsReady);

// create the map when the element is ready
watch(mapElementIsReady, (isReady: boolean) => {
    if (isReady) {
        mapStore.createStaticMap();
    }
});

onMounted(() => {
    if (mapElementIsReady.value) {
        mapStore.createStaticMap();
    }
});

const props = defineProps({
    url: {
        type: String || null,
        default: null,
    },
    results: {
        type: Object || null,
        default: null,
    },
});

const { url, results } = toRefs(props);

const form = useForm({
    url: url.value,
});

const isValidated = computed(() => {
    return results.value && !has(results.value, "is_halted");
});

const isHalted = computed(
    () => !isValidated.value && results.value?.is_halted === true
);

const isSuccessful = computed(() => results.value?.is_successful === true);

// page info
const title = computed(() => results.value?.title ?? "Untitled");

const favicon = computed(() => results.value?.favicon ?? "");

// metrics
const geoposition = computed(() =>
    isValidated.value && results.value.metrics
        ? results.value.metrics["geo.position"]
        : null
);

const georegion = computed(() =>
    isValidated.value && results.value.metrics
        ? results.value.metrics["geo.region"]
        : null
);

const geoplacename = computed(() =>
    isValidated.value && results.value.metrics
        ? results.value.metrics["geo.placename"]
        : null
);

const icbm = computed(() =>
    isValidated.value && results.value.metrics
        ? results.value.metrics["ICBM"]
        : null
);

const dctitle = computed(() =>
    isValidated.value && results.value.metrics
        ? results.value.metrics["DC.title"]
        : null
);

const plausibility = computed(() =>
    isValidated.value && results.value.metrics
        ? results.value.metrics["plausibility"]
        : null
);

function submitForm() {
    form.get("/validator", {
        preserveScroll: true,
        preserveState: true,
    });
}

const mapAndMarkerIsCreated = computed(() => mapStore.mapAndMarkerIsCreated);

watch(mapAndMarkerIsCreated, (isCreated: boolean) => {
    if (isCreated) {
        setMarkerCoordinates();
    }
});

const positionLngLat = computed(() => {
    if (geoposition.value?.data?.lng && geoposition.value?.data?.lat) {
        return new LngLat(
            geoposition.value.data.lng,
            geoposition.value.data.lat
        );
    }

    return null;
});

watch(positionLngLat, (value) => {
    setMarkerCoordinates();
});

const setMarkerCoordinates = async () => {
    if (positionLngLat.value) {
        mapStore.attachMarkerToMap(positionLngLat.value);
    }
};
</script>

<template layout="default">
    <Head title="Validate tag" />

    <div class="flex flex-col gap-3">
        <form @submit.prevent="submitForm" class="form-control">
            <div class="relative">
                <div
                    class="absolute inset-y-0 left-0 flex items-center justify-center w-12"
                >
                    <LinkIcon class="w-5 h-5"></LinkIcon>
                </div>

                <text-input
                    v-model="form.url"
                    placeholder="Website URL"
                    type="text"
                    class="w-full px-12 rounded-box input input-bordered"
                    required
                />

                <div
                    class="absolute inset-y-0 right-0 flex items-center justify-center w-12"
                >
                    <button
                        class="btn btn-square btn-sm btn-ghost"
                        :disabled="form.processing"
                    >
                        <ArrowRightIcon
                            v-if="!form.processing"
                            class="w-5 h-5"
                        />
                        <loading-icon v-else class="w-5 h-5"></loading-icon>
                    </button>
                </div>
            </div>
        </form>

        <template v-if="!form.processing">
            <div v-if="isHalted" for="" class="alert alert-error">
                <p>
                    {{ results?.halted_msg }}
                </p>
            </div>

            <template v-if="isValidated">
                <div v-if="isSuccessful" class="alert alert-success">
                    <p>
                        Congratulations! All required geo tag items on the page
                        are complete and valid!
                    </p>
                </div>
                <div v-else class="alert alert-error">
                    <p>
                        The page contains invalid geo tag items! Please see
                        errors below.
                    </p>
                </div>

                <div
                    class="flex items-center gap-3 p-3 border rounded-xl border-base-content/25 bg-base-300"
                >
                    <div class="avatar">
                        <div class="w-10 h-10 mask mask-squircle">
                            <img v-if="favicon" :src="favicon" :alt="title" />
                            <QuestionMarkCircleIcon
                                v-else
                                class="opacity-50"
                            ></QuestionMarkCircleIcon>
                        </div>
                    </div>
                    <div class="flex-1 truncate">
                        <div class="font-bold truncate">
                            {{ title }}
                        </div>
                        <a
                            :href="url"
                            target="_blank"
                            class="text-sm truncate link"
                        >
                            {{ url }}
                        </a>
                    </div>
                </div>

                <div
                    class="w-full border border-base-content/25 stats stats-vertical"
                >
                    <div v-if="geoposition" class="px-3 py-2 stat">
                        <div
                            class="flex items-center gap-2 font-bold opacity-100 stat-title"
                        >
                            <span>geo.position</span>

                            <CheckCircleIcon
                                v-if="geoposition.is_valid"
                                class="w-5 h-5 text-success"
                            ></CheckCircleIcon>
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-error"
                            ></XCircleIcon>
                        </div>
                        <pre class="whitespace-pre-wrap">{{
                            geoposition.context
                        }}</pre>
                        <p v-if="geoposition.data.lat">
                            <span class="text-base-content/90">Latitude</span>:
                            {{ geoposition.data.lat }}
                        </p>
                        <p v-if="geoposition.data.lng">
                            <span class="text-base-content/90">Longitude</span>:
                            {{ geoposition.data.lng }}
                        </p>
                        <p
                            v-for="error in geoposition.errors"
                            class="text-error"
                        >
                            {{ error }}
                        </p>
                    </div>

                    <div v-if="georegion" class="px-3 py-2 stat">
                        <div
                            class="flex items-center gap-2 font-bold opacity-100 stat-title"
                        >
                            <span>geo.region</span>

                            <CheckCircleIcon
                                v-if="georegion.is_valid"
                                class="w-5 h-5 text-success"
                            ></CheckCircleIcon>
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-error"
                            ></XCircleIcon>
                        </div>
                        <pre class="whitespace-pre-wrap">{{
                            georegion.context
                        }}</pre>
                        <p
                            v-if="
                                georegion.data.country ||
                                georegion.data.country_shortcode
                            "
                        >
                            <span class="text-base-content/90">Country</span>:
                            {{ georegion.data.country }}
                            {{ georegion.data.country_shortcode }}
                        </p>
                        <p
                            v-if="
                                georegion.data.region ||
                                georegion.data.region_shortcode
                            "
                        >
                            <span class="text-base-content/90">Region</span>:
                            {{ georegion.data.region }}
                            {{ georegion.data.region_shortcode }}
                        </p>
                        <p v-for="error in georegion.errors" class="text-error">
                            {{ error }}
                        </p>
                    </div>

                    <div v-if="geoplacename" class="px-3 py-2 stat">
                        <div
                            class="flex items-center gap-2 font-bold opacity-100 stat-title"
                        >
                            <span>geo.placename</span>

                            <CheckCircleIcon
                                v-if="geoplacename.is_valid"
                                class="w-5 h-5 text-success"
                            ></CheckCircleIcon>
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-error"
                            ></XCircleIcon>
                        </div>
                        <pre class="whitespace-pre-wrap">{{
                            geoplacename.context
                        }}</pre>
                        <p v-if="geoplacename.data.content">
                            <span class="text-base-content/90">Place</span>:
                            {{ geoplacename.data.content }}
                        </p>
                        <p
                            v-for="error in geoplacename.errors"
                            class="text-error"
                        >
                            {{ error }}
                        </p>
                    </div>

                    <div v-if="icbm" class="px-3 py-2 stat">
                        <div
                            class="flex items-center gap-2 font-bold opacity-100 stat-title"
                        >
                            <span>ICBM (optional)</span>

                            <CheckCircleIcon
                                v-if="icbm.is_valid"
                                class="w-5 h-5 text-success"
                            ></CheckCircleIcon>
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-error"
                            ></XCircleIcon>
                        </div>
                        <pre class="whitespace-pre-wrap">{{
                            icbm.context
                        }}</pre>
                        <p v-if="icbm.data.lat">
                            <span class="text-base-content/90">Latitude</span>:
                            {{ icbm.data.lat }}
                        </p>
                        <p v-if="icbm.data.lng">
                            <span class="text-base-content/90">Longitude</span>:
                            {{ icbm.data.lng }}
                        </p>
                        <p v-for="error in icbm.errors" class="text-error">
                            {{ error }}
                        </p>
                    </div>

                    <div v-if="dctitle" class="px-3 py-2 stat">
                        <div
                            class="flex items-center gap-2 font-bold opacity-100 stat-title"
                        >
                            <span>DC.Title (optional)</span>

                            <CheckCircleIcon
                                v-if="dctitle.is_valid"
                                class="w-5 h-5 text-success"
                            ></CheckCircleIcon>
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-error"
                            ></XCircleIcon>
                        </div>
                        <pre class="whitespace-pre-wrap">{{
                            dctitle.context
                        }}</pre>
                        <p v-if="dctitle.data.content">
                            <span class="text-base-content/90">Title</span>:
                            {{ dctitle.data.content }}
                        </p>
                        <p v-for="error in dctitle.errors" class="text-error">
                            {{ error }}
                        </p>
                    </div>

                    <div v-if="plausibility" class="px-3 py-2 stat">
                        <div
                            class="flex items-center gap-2 font-bold opacity-100 stat-title"
                        >
                            <span>Plausibility check</span>

                            <CheckCircleIcon
                                v-if="plausibility.is_valid"
                                class="w-5 h-5 text-success"
                            ></CheckCircleIcon>
                            <XCircleIcon
                                v-else
                                class="w-5 h-5 text-error"
                            ></XCircleIcon>
                        </div>
                        <pre class="whitespace-pre-wrap">{{
                            plausibility.context
                        }}</pre>
                        <p v-if="plausibility.data.query">
                            <span class="text-base-content/90">Query</span>:
                            {{ plausibility.data.query }}
                        </p>
                        <p v-if="plausibility.data.distance">
                            <span class="text-base-content/90">Distance</span>:
                            {{ plausibility.data.distance }}
                        </p>
                        <p v-if="plausibility.data.feature">
                            <span class="text-base-content/90"
                                >Geocoding result</span
                            >:
                            {{ plausibility.data.feature?.place_name }}
                        </p>
                        <p
                            v-for="error in plausibility.errors"
                            class="text-error"
                        >
                            {{ error }}
                        </p>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
