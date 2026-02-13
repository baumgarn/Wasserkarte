<template>
	<div id="mapcontainer" :class="{ focusmode: state.focusMode }">

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

			<ol-tile-layer :className="'osm-layer'">

				<ol-source-osm />

			</ol-tile-layer>

			<!-- <TintLayer color="rgba(0, 0, 0, 0.05)" /> -->

			<div class="wsmlayers">

				<MapWSMLayers />

			</div>

			<ol-zoom-control />

			<div class="markers">

				<MapMarker v-for="device in devices" :device="device" :map="this"/>
				
			</div>

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
import TintLayer from '@/map/tintlayer.vue'
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
		TintLayer,
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
			state,
			topLeftAnchor: null
		};
	},
	props: {
	},
	computed: {
		devices() {
			return state.devices;
		},
		focusMode() {
			return state.focusMode;
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
			if (! state.popupMenuOpen) {


				state.selectedDevice = null;
				for (const key in state.menuOpen) {
					if (Object.prototype.hasOwnProperty.call(state.menuOpen, key)) {
						state.menuOpen[key] = false;
					}
				}
				state.wsmlegends = false;
				state.mobilemenuOpen = false;

			}
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
		// saveTopLeftAnchor and restoreTopLeftAnchor help keep the map in position when parent container dimensions change
		saveTopLeftAnchor() { 
			const map = this.$refs.olMap?.map;
			if (!map) return;
			this.topLeftAnchor = map.getCoordinateFromPixel([0, 0]);
		},
		restoreTopLeftAnchor() {
			const map = this.$refs.olMap?.map;
			if (!map || !this.topLeftAnchor) return;

			map.updateSize();

			const view = map.getView();
			const size = map.getSize();
			const resolution = view.getResolution();

			if (!size || !resolution) return;

			const dx = (size[0] / 2) * resolution;
			const dy = (size[1] / 2) * resolution;

			const newCenter = [
				this.topLeftAnchor[0] + dx,
				this.topLeftAnchor[1] - dy
			];

			view.setCenter(newCenter);
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
	
				var padding = [100, 30, 50, 30];
			
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
		'state.sidebarOpen'() {
			this.saveTopLeftAnchor();
			this.$nextTick(this.restoreTopLeftAnchor);
		},
		'state.timelineRange'() {
			this.saveTopLeftAnchor();
			this.$nextTick(this.restoreTopLeftAnchor);
		},
		devices(val) {
			if (!this.hasFitRun && val?.length) {
				this.fitMarkers();
				this.hasFitRun = true;
			}
		},
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

	#mapcontainer, .map
		position: relative

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

	.map
		height: 100%;
		width: 100%;

	.osm-layer
		opacity .9
		filter saturate(90%)
	
	.focusmode .osm-layer
		filter saturate(0%)
		opacity 1
		
	// .map-tint
	// 	position: absolute
	// 	background: rgba(0, 120, 255, 0.15) /* <- pick your color/alpha */
	// 	// pointer-events: none
	// 	z-index: 1
	// 	display none


	// .wsmlayers
	// 	position: absolute
	// 	inset: 0
	// 	z-index: 20

	// .markers
	// 	position: absolute
	// 	inset: 0
	// 	z-index: 30

	// .markers * 
	// 	pointer-events: auto

</style>
