<script lang="ts">
export default {
    name: "generator-search",
};
</script>

<script setup lang="ts">
import { ref, watch } from "vue";
import debounce from "lodash/debounce";
import { useMapStore } from "@/scripts/stores/mapStore.js";
import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
    TransitionRoot,
} from "@headlessui/vue";
import {
    SelectorIcon,
    SearchIcon,
    LocationMarkerIcon,
} from "@heroicons/vue/outline";

const mapStore = useMapStore();

const results = ref<any[]>([]);
const selected = ref<any>(null);
const isSearching = ref<boolean>(false);

const search = debounce(async (e: any) => {
    let query = e.target.value;

    if (!query) {
        results.value = [];
        return;
    }

    isSearching.value = true;

    await mapStore
        .geocode(query)
        .then((res) => {
            if (res?.data?.features) {
                results.value = res.data.features;
            }
        })
        .finally(() => {
            isSearching.value = false;
        });
}, 500);

watch(selected, (value: any) => {
    if (value) {
        mapStore.selectSearchResult(value);
    }
});
</script>

<template>
    <div>
        <Combobox v-model="selected" nullable v-slot="{ open }">
            <div class="w-full dropdown dropdown-open">
                <div class="relative">
                    <div
                        class="absolute inset-y-0 left-0 flex items-center justify-center w-12"
                    >
                        <SearchIcon class="w-5 h-5"></SearchIcon>
                    </div>

                    <ComboboxInput
                        :displayValue="(place: any) => place?.place_name"
                        @input="search"
                        placeholder="Search place"
                        class="w-full px-12 rounded-box input input-bordered"
                        :class="{
                            'rounded-b-none': results.length > 0 && open,
                        }"
                    />

                    <div
                        v-if="results.length > 0"
                        class="absolute inset-y-0 right-0 flex items-center justify-center w-12"
                    >
                        <ComboboxButton class="btn btn-square btn-sm btn-ghost">
                            <SelectorIcon class="w-5 h-5" />
                        </ComboboxButton>
                    </div>
                </div>

                <TransitionRoot
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div v-if="results.length > 0">
                        <ComboboxOptions
                            static
                            class="w-full divide-y rounded-t-none shadow-xl rounded-box divide-base-content/5 dropdown-content menu bg-base-100"
                        >
                            <ComboboxOption
                                v-for="place in results"
                                as="template"
                                :key="place.id"
                                :value="place"
                                v-slot="{ selected, active }"
                            >
                                <li>
                                    <div
                                        class="flex items-center w-full gap-4 text-left"
                                        :class="[
                                            selected ? 'active' : '',
                                            active ? 'active' : '',
                                        ]"
                                    >
                                        <LocationMarkerIcon
                                            class="w-6 h-6 shrink-0 opacity-60"
                                        ></LocationMarkerIcon>

                                        <div class="flex-1 overflow-hidden">
                                            <p class="font-semibold truncate">
                                                {{ place.text }}
                                            </p>
                                            <p class="truncate opacity-60">
                                                {{ place.place_name }}
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            </ComboboxOption>
                        </ComboboxOptions>
                    </div>
                </TransitionRoot>
            </div>
        </Combobox>
    </div>
</template>
