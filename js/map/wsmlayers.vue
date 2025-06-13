<template
>
	<template v-for="layer in wsmLayers" :key="layer.name">
		
		<ol-tile-layer
		:visible="layer.visible"
		:opacity="state?.wsmtransparency ? 0.5 : 1"
		>
			<ol-source-tile-wms
			:url="layer.url"
			:layers="layer.name"
			:server-type="layer.serverType"
			:attributions="'© '+layer.attribution"
			/>
    	</ol-tile-layer>
	</template>
	
</template>

<script>
import { fromLonLat } from 'ol/proj.js';
import 'ol/ol.css';
import { state } from '../state.js';

export default {
	name: 'WSMLayers',
	components: {
	},
	data() {
		return {
			params: {
				
			}
		};
	},
	props: {
		devices: {
		}
	},
	computed: {
		state() {
			return state;
		},
		wsmLayers() {
			return state.wsmLayers
		}

	},
	methods: {
		
	},
	mounted() {
		// console.log('wsmLayers:', JSON.stringify(state.wsmLayers, null, 2));
	}
	
};
</script>

<style>

</style>
