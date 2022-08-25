import { defineStore } from "pinia";
import { WritableComputedRef } from "vue";
import { useDark, useToggle } from "@vueuse/core";
import axios, { AxiosResponse } from "axios";
import mapboxgl, { LngLat, Map, Marker } from "mapbox-gl";

mapboxgl.accessToken = import.meta.env.VITE_MAPBOX_API_TOKEN;

const isDark = useDark({
    selector: "html",
    attribute: "data-theme",
    valueDark: "night",
    valueLight: "winter",
});

const toggleDark = useToggle(isDark);

type State = {
    isDarkMode: WritableComputedRef<boolean>;
    toggleDark: Function;

    mapboxgl: any;
    map: Map | null;
    marker: Marker | null;

    mapElement: HTMLElement | null;
    mapIsLoaded: boolean;
    mapEaseComplete: boolean;

    coordinates: LngLat;

    generator: { selected: any; accuracy: number; place: any };
    validator: any;
};

export const useMapStore = defineStore("mapStore", {
    state: (): State => ({
        isDarkMode: isDark,
        toggleDark: toggleDark,

        mapboxgl: mapboxgl,
        map: null,
        marker: null,

        mapElement: null,
        mapIsLoaded: false,
        mapEaseComplete: false,

        coordinates: new LngLat(120.9445404, 14.5965788),

        generator: {
            selected: null,
            accuracy: 6,
            place: null,
        },
        validator: {},
    }),

    getters: {
        accessToken: (state) => state.mapboxgl.accessToken,

        mapStyle: (state) =>
            state.isDarkMode
                ? "mapbox://styles/mapbox/dark-v10"
                : "mapbox://styles/mapbox/light-v10",

        markerColor: (state) => (state.isDarkMode ? "#3ABFF8" : "#057AFF"),

        mapElementIsReady: (state) => state.mapElement !== null,

        mapAndMarkerIsCreated: (state) =>
            state.map !== null &&
            state.marker !== null &&
            state.mapEaseComplete,

        lng: (state) => state.coordinates.lng.toFixed(state.generator.accuracy),

        lat: (state) => state.coordinates.lat.toFixed(state.generator.accuracy),

        isMaxZoom: (state) => state.map?.getZoom() === state.map?.getMaxZoom(),

        isMinZoom: (state) => state.map?.getZoom() === state.map?.getMinZoom(),

        generatorPlaceData: (state) => {
            let data: { country: string; region: string; place: string } = {
                country: "",
                region: "",
                place: "",
            };

            // country
            let country = state.generator.place.context.find((item: any) => {
                return item.id.includes("country.");
            });

            if (country && country.short_code) {
                data.country = country.short_code.toUpperCase();
            }

            // region
            let region = state.generator.place.context.find((item: any) => {
                return item.id.includes("region.");
            });

            if (region && region.short_code) {
                let s = region.short_code.split("-");
                data.region = s[s.length - 1].toUpperCase();
            }

            // place
            let place = state.generator.place.context.find((item: any) => {
                return item.id.includes("place.");
            });

            if (place && place.text) {
                data.place = place.text;
            }

            return data;
        },
    },

    actions: {
        setMapElement(el: HTMLElement): void {
            this.mapElement = el;
        },

        createInteractiveMap(): void {
            if (this.mapElementIsReady) {
                const map = this.createMap();

                this.createDraggableMarker();
            }
        },

        createStaticMap(): void {
            if (this.mapElementIsReady) {
                const map = this.createMap({
                    interactive: false,
                    zoom: 14,
                });

                this.createStaticMarker();
            }
        },

        createMap(options: any = {}): Map {
            // remove current map
            this.map?.remove();
            this.map = null;
            this.mapIsLoaded = false;

            this.map = new Map({
                container: this.mapElement,
                center: this.coordinates,
                style: this.mapStyle,
                zoom: 12,
                language: "en-US",
                maxZoom: 20,
                minZoom: 5,
                ...options,
            });

            this.map
                .once("moveend", (e) => {
                    this.mapEaseComplete = true;
                })
                .easeTo({
                    padding: {
                        left: 448,
                    },
                    duration: 300,
                });

            this.map.on("load", (e: any) => {
                if (!this.mapIsLoaded) {
                    setTimeout(() => {
                        this.mapIsLoaded = true;
                    }, 500);
                }
            });

            this.map.on("styledataloading", (e: any) => {
                this.mapIsLoaded = false;
            });

            this.map.on("styledata", (e: any) => {
                if (!this.mapIsLoaded) {
                    setTimeout(() => {
                        this.mapIsLoaded = true;
                    }, 500);
                }
            });

            return this.map;
        },

        createDraggableMarker(): Marker {
            return this.createMarker();
        },

        createStaticMarker(): Marker {
            return this.createMarker(
                {
                    draggable: false,
                },
                false
            );
        },

        createMarker(options: any = {}, attachToMap: boolean = true): Marker {
            // remove current marker
            this.marker?.remove();
            this.marker = null;

            this.marker = new Marker({
                draggable: true,
                color: this.markerColor,
                ...options,
            });

            if (this.map && attachToMap) {
                this.marker.setLngLat(this.coordinates).addTo(this.map);
            }

            // follow marker on dragend
            this.marker.on("dragend", (e: any) => {
                this.setCoordinates(e.target.getLngLat());

                this.map?.panTo(this.coordinates);
            });

            return this.marker;
        },

        attachMarkerToMap(coordinates: LngLat | null): void {
            if (this.map && this.marker && coordinates) {
                this.marker.setLngLat(coordinates).addTo(this.map);

                this.map
                    ?.once("moveend", (e: any) => {
                        this.setCoordinates(e.target.getCenter());
                    })
                    .panTo(coordinates);
            }
        },

        setCoordinates(value: LngLat) {
            this.coordinates = value;
        },

        // controls
        toggleDarkMode(): void {
            this.toggleDark();

            if (this.map) {
                // change style
                this.map.setStyle(this.mapStyle);

                // recreate marker
                if (this.marker) {
                    if (this.marker.isDraggable()) {
                        this.createDraggableMarker()
                            .setLngLat(this.coordinates)
                            .addTo(this.map);
                    } else {
                        this.createStaticMarker()
                            .setLngLat(this.coordinates)
                            .addTo(this.map);
                    }
                }
            }
        },

        zoomIn(): void {
            this.map?.zoomIn();
        },

        zoomOut(): void {
            this.map?.zoomOut();
        },

        moveToMarker(): void {
            if (this.map && this.marker) {
                this.map.panTo(this.marker.getLngLat());
            }
        },

        // api calls
        async geocode(query: string, params: {} = {}): Promise<AxiosResponse> {
            let term = encodeURI(query.trim());
            term = term.replace(/#/g, "%23");

            return await axios.get(
                `https://api.mapbox.com/geocoding/v5/mapbox.places/${term}.json`,
                {
                    params: {
                        language: "en-US",
                        access_token: this.accessToken,
                        ...params,
                    },
                }
            );
        },

        async reverseGeocode(
            lngLat: LngLat,
            params: {} = {}
        ): Promise<AxiosResponse> {
            let { lng, lat } = lngLat;

            return await axios.get(
                `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json`,
                {
                    params: {
                        language: "en-US",
                        access_token: this.accessToken,
                        ...params,
                    },
                }
            );
        },

        // generator
        selectSearchResult(item: any = {}): void {
            this.generator.selected = item;
            this.marker?.setLngLat(item.center);

            this.map
                ?.once("moveend", (e: any) => {
                    this.setCoordinates(e.target.getCenter());
                })
                .panTo(item.center);
        },

        increaseAccuracy(): void {
            if (this.generator.accuracy >= 6) {
                return;
            }
            this.generator.accuracy++;
        },

        decreaseAccuracy(): void {
            if (this.generator.accuracy <= 0) {
                return;
            }
            this.generator.accuracy--;
        },

        setGeneratorPlace(place: any): void {
            this.generator.place = place;
        },
    },
});
