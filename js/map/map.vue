<template>
	<div id="mapcontainer">

		 <ol-map 
		 	class="map" 
			ref="olMap"
			:class="{'sidebaropen': state.sidebarOpen}"
			:controls="[]" 
			:loadTilesWhileAnimating="true" 
			:loadTilesWhileInteracting="true"
			@moveend="onMoveEnd"
			@click="click"
			>

			<ol-view :center="center" :zoom="zoom" />

			<ol-tile-layer :opacity=".9" >

				<ol-source-osm />

			</ol-tile-layer>
			
			<div class="wsmlayers">

				<MapWSMLayers />

			</div>

			<ol-zoom-control />

			<MapMarker v-for="device in devices" :device="device" :map="this"/>
					
			<MapPopup v-if="mouseoverDevice" :device="mouseoverDevice"/>

			<ol-attribution-control />

		</ol-map>

	


	</div>
</template>


<script>
import { fromLonLat } from 'ol/proj.js';
import { config } from '@/config.js';
import MapMarker from '@/map/marker.vue';
import MapPopup from '@/map/popup.vue';
import MapWSMLayers from '@/map/wsmlayers.vue';
import 'ol/ol.css';
import { state } from '@/state.js';
import dataStore from '@/datastore.js';

import { nextTick } from 'vue';
import { boundingExtent } from 'ol/extent.js';

export default {
	name: 'Map',
	components: {
		MapMarker,
		MapPopup,
		MapWSMLayers,
	},
	setup() {
		return {
			state
		};
	},
	data() {
		return {
			hasFitRun: false,
			state
		};
	},
	props: {
	},
	computed: {
		devices() {
			return state.devices;
		},
		infoArrowDevice() {
			return dataStore.getDeviceByName(config.infoArrowDevice);
		},
		center: {
			get() {
				return state.mapCenter;
			},
			set(value) {
				state.mapCenter = value;
			}
		},
		zoom: {
			get() {
				return state.mapZoom;
			},
			set(value) {
				state.mapZoom = value;
			}
		},
		mouseoverDevice: {
			get() {
				return state.mouseoverDevice;
			},
			set(value) {
				state.mouseoverDevice = value;
			}
		},
	},
	methods: {
		position(device) {
			return fromLonLat([device.attributes?.longitude, device.attributes?.latitude])
		},
		onMoveEnd(event) {
			const view = event.map.getView();
			this.center = view.getCenter();
			this.zoom = view.getZoom();
		},
		click() {
			state.selectedDevice = null;
			for (const key in state.menuOpen) {
				if (Object.prototype.hasOwnProperty.call(state.menuOpen, key)) {
					state.menuOpen[key] = false;
				}
			}
			state.wsmlegends = false;
			state.mobilemenuOpen = false;
		},
		offsetCenter() {
			const mapComponent = this.$refs.olMap;
			if (mapComponent && mapComponent.map) {
				const view = mapComponent.map.getView();
				const resolution = view.getResolution();
				const pixelOffset = -300;
				const mapUnitsPerPixel = resolution;
				const mapOffset = pixelOffset * mapUnitsPerPixel;
				const center = view.getCenter();
				if (center) {
				const newCenter = [
					center[0] - mapOffset,
					center[1]
				];
				view.setCenter(newCenter);
				}
			} else {
				console.warn('Map not ready');
			}
		},
		fitMarkers() {
			const mapComponent = this.$refs.olMap;
			if (mapComponent && mapComponent.map) {
				const map = mapComponent.map;
				const view = map.getView();
				const size = map.getSize();
	
				if (!this.devices || this.devices.length === 0) {
					console.warn('No devices to fit');
					return;
				}
	
				const includedDevices = this.devices.filter(device => {
					return !config.excludeFromMapFit.includes(device.name);
				});
	
				if (includedDevices.length === 0) {
					console.warn('All devices excluded from fit');
					return;
				}
	
				const points = includedDevices.map(device => {
					const lon = device.attributes?.longitude;
					const lat = device.attributes?.latitude;
					return fromLonLat([lon, lat]);
				});
	
				const extent = boundingExtent(points);
	
				var padding = [25, 25, 70, 25];
				if (state.menuOpen.info) {
					padding = [25, 625, 70, 25];
				}
				view.fit(extent, {
					size: size,
					padding: padding
				});
	
			} else {
				console.warn('Map not ready');
			}
		},
		panToDevice() {
			let device = dataStore.getDeviceByName(state.selectedDevice)
			const lon = device.attributes?.longitude;
			const lat = device.attributes?.latitude;
			if (lon != null && lat != null) {
				const mapComponent = this.$refs.olMap;
				if (mapComponent && mapComponent.map) {
					const view = mapComponent.map.getView();
					const center = fromLonLat([lon, lat]);
					view.animate({ center, duration: 500 });
				} else {

				}
			} else {

			}
		},
	
	},
	watch: {
		devices(val) {
			if (!this.hasFitRun && val?.length) {
				this.fitMarkers();
				this.hasFitRun = true;
			}
		}
	},
	mounted() {
		window.addEventListener('panToDevice', this.panToDevice);
	},
	beforeUnmount() {
		window.removeEventListener('panToDevice', this.panToDevice);
	}
	
};
</script>

<style lang="stylus">

	.device-marker {
		position: absolute;
		background-color: red;
		width: 10px;
		height: 10px;
	}

	#mapcontainer {
		height: 100%;
		width: 100%;
	}

	.map {
		height: 100%;
		width: 100%;
	}

</style>
