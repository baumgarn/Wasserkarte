<template>

	<template v-if="markerStyle == 'Bodenfeuchte Farbkreis'">

		<div 
			class="map-marker kreis faux"
			:class="{ mouseover, selected: isSelected }"
			@mouseenter="onMouseEnter"
			@mouseleave="onMouseLeave"
			@click="click"
			@wheel="wheelForward"
			>
			
			<div class="schichten">

				<div class="schicht" :style="'background:'+dataModel.get_nfk_color(60)"></div>
				<div class="schicht" :style="'background:'+dataModel.get_nfk_color(8)"></div>
				<div class="schicht" :style="'background:'+dataModel.get_nfk_color(30)"></div>
				<div class="schicht" :style="'background:'+dataModel.get_nfk_color(2)"></div>
			
			</div>

		</div>

	</template>

	<template v-else>

		<div 
			class="map-marker einzeln"
			>
			<!-- <div class="schicht" v-if="telemetry[markerStyle]" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry[[markerStyle]]))">
				<div class="value">{{ parseFloat(getLastValue(telemetry[[markerStyle]])).toFixed(0) }}<span class="unit">%</span></div>
			</div>	   -->
		</div>
		
	</template>

</template>

<script>
import { fromLonLat } from 'ol/proj.js';
import { config } from '@/config.js';
import { displayutil } from '@/displayutil.js';
import { dataModel } from '@/dataModel.js';
import {state} from '@/state.js'
import MapPopup from '@/map/popup.vue';

export default {
	name: 'MapMarker',
	components: {
		MapPopup
	},
	setup() {
		return {dataModel}
	},
	data() {
		return {
		
		};
	},
	props: {
		markerStyle: {
			type: String,
			default: 'Bodenfeuchte Farbkreis'
		},
	},
	computed: {
	
	},
	methods: {
		
	},
	watch: {
		
	},
	mounted() {
		
	}
	
};
</script>

<style lang="stylus" scoped>
	.map-marker
		pointer-events none

	.map-marker.kreis
		.schichten
			border 2px solid #66666688


</style>
