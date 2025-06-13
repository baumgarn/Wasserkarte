<template>
	<div id="mapcontainer">
		 <ol-map 
		 	class="map minimap" 
			v-show="devices" 
			:controls="[]" 
			:loadTilesWhileAnimating="true" 
			:loadTilesWhileInteracting="true"
			@moveend="onMoveEnd"
			@click="click"
			>

			<ol-view :center="center" :zoom="zoom" />
			
			<ol-tile-layer>
				<ol-source-osm />
			</ol-tile-layer>
			
			<!-- <MapWSMLayers /> -->

			<ol-zoom-control />
			
			<MapMarker v-for="device in devices" :device="device" :map="this" />
			
			<!-- <MapPopup v-if="mouseoverDevice" :device="mouseoverDevice"/> -->

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

export default {
	name: 'MiniMap',
	components: {
		MapMarker,
		MapPopup,
		MapWSMLayers,
	},
	data() {
		return {
			center: this.position(this.device),
			// zoom: 15,
			mouseoverDevice: null,
		};
	},
	props: {
		device: Object,
	},
	computed: {
		devices() {
			return state.devices;
		},
		zoom: {
			get() {
				return state.minimapZoom;
			},
			set(value) {
				state.minimapZoom = value;
			}
		},
	},
	methods: {
		position(device) {
			return fromLonLat([device?.attributes?.longitude, device?.attributes?.latitude])
		},
		onMoveEnd(event) {
			const view = event.map.getView();
			this.zoom = view.getZoom();
			this.center = view.getCenter();
		},
		click() {
		},
	},
	mounted() {
	},
	watch: {
		device() {
			this.center = this.position(this.device)
		}
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

	.minimap#mapcontainer {
		height: 100%;
		width: 100%;
		border-radius 8px
		overflow hidden
	}

	.map {
		height: 100%;
		width: 100%;
	}

</style>
