<template>

			<ol-overlay 
				ref="overlay"
				v-if="haslatlong && hasTelemetry"
				:key="device.id"
				:position="position(device)"
				>
				<div class="marker-outer" :class="{selectedSoil: (selectedSoil != 'Alle' && isSelectedSoil), notSelectedSoil: (selectedSoil != 'Alle' && !isSelectedSoil)}">

					<template v-if="selectedSoil != 'Alle' && !isSelectedSoil">

						<div class="map-marker disabled">
							<div class="disabled">
								
							</div>	
						</div>
					
					</template>

					<template v-else-if="markerStyle == 'Bodenfeuchte_Farbkreis'">

						<div 
							class="map-marker kreis"
							:class="{ mouseover, selected: isSelected }"
							@mouseenter="onMouseEnter"
							@mouseleave="onMouseLeave"
							@click="click"
							@wheel="wheelForward"
							>
							
							<div class="schichten">

							<div class="schicht" v-if="lastData.nfk_10cm != undefined" :style="'background:'+dataModel.get_nfk_color(lastData.nfk_10cm)"></div>
							<div class="schicht" v-if="lastData.nfk_30cm != undefined" :style="'background:'+dataModel.get_nfk_color(lastData.nfk_30cm)"></div>
							<div class="schicht" v-if="lastData.nfk_60cm != undefined" :style="'background:'+dataModel.get_nfk_color(lastData.nfk_60cm)"></div>
							<div class="schicht" v-if="lastData.nfk_80cm != undefined" :style="'background:'+dataModel.get_nfk_color(lastData.nfk_80cm)"></div>

							</div>

						</div>

					</template>
					
					<template v-else-if="markerStyle == 'Bodenfeuchte_vol'">
						
						<div 
							class="map-marker einzeln"
							:class="{ mouseover, selected: isSelected }"
							@mouseenter="onMouseEnter"
							@mouseleave="onMouseLeave"
							@click="click"
							@wheel="wheelForward"
							v-if="lastData"
							>
							<div v-if="vol && !Number.isNaN(vol)"  class="schicht" :style="'background:'+nfk_color">
								<div class="value">{{ vol.toFixed(0) }}<span class="unit">%</span></div>
							</div>	  
						</div>

					</template>

					<template v-else-if="markerStyle == 'Bodenfeuchte_nfk'">
						
						<div 
							class="map-marker einzeln"
							:class="{ mouseover, selected: isSelected }"
							@mouseenter="onMouseEnter"
							@mouseleave="onMouseLeave"
							@click="click"
							@wheel="wheelForward"
							v-if="lastData"
							>
							<div v-if="nfk && !Number.isNaN(nfk)" class="schicht" :style="'background:'+nfk_color">
								<div class="value">{{ nfk.toFixed(0) }}<span class="unit">%</span></div>
							</div>	  
						</div>

					</template>
					
				</div>
					
     		 </ol-overlay>

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
		return {dataModel, displayutil}
	},
	data() {
		return {
			mouseover: false,
			schichten: ['Bodenfeuchte_10cm','Bodenfeuchte_30cm','Bodenfeuchte_60cm','Bodenfeuchte_80cm'],
			popupBelow: false,
			vol: null,
			nfk: null,
			nfk_color: null,
		};
	},
	props: {
		device: Object,
		map: Object,
		noPopup: Boolean,
	},
	computed: {
		telemetry() {
			console.log(this.device.telemetry)
			return this.device.telemetry || {};
		},
		hasTelemetry() {
			// console.log(this.device)
			return Object.keys(this.device.telemetry).length > 0;
		},
		getLastValue() {
			return (array) => array ? array[array.length - 1].value : null;
		},
		markerStyle() {
			return state.markerStyle;
		},
		isSelected() {
			return (this.device.name == this.selectedDevice)
		},
		selectedSoil() {
			return state.selectedSoil;
		}, 
		selectedDevice(){
			return state.selectedDevice;
		},
		haslatlong() {
			return (this.device.attributes?.latitude && this.device.attributes?.longitude )
		},
		isSelectedSoil() {
			return (state.selectedSoil == this.device.attributes?.soilType)
		},
		displayData() {
			return this.lastData;
		},
		schema() {
			return this.device.telemetrySchema.schema;
		},
		bf10_index() {
			const i = this.schema.indexOf('Bodenfeuchte_10cm');
			return (i > 0) ? i : null;
		},
		lastData() {
			if (this.device.telemetrySchema && this.device.telemetrySchema.data) {
				return dataModel.rowToProps(this.device.telemetrySchema.data[0], this.schema)
			} else {
				return null;
			}
		},
	},
	methods: {
		position(device) {
			return fromLonLat([device.attributes?.longitude, device.attributes?.latitude])
		},
		getSensorValue() {
			return this.device.telemetry[schicht];
		},
		onMouseEnter(event) {
			this.mouseover = true;
			this.map.mouseoverDevice = this.device;
			this.setZindex()
		},
		calculateValues() {
			if (this.lastData) {
				this.vol = this.lastData?.vol_avg;
				this.nfk = this.lastData?.nfk_avg;
				this.nfk_color = dataModel.get_nfk_color(this.nfk);
			}
		},
		onMouseLeave(event) {
			this.mouseover = false;
			this.map.mouseoverDevice = null;
			this.setZindex()
		},
		click(event) {
			state.markerClicked = true;
			state.selectedDevice = this.device?.name || null;
		},
		wheelForward(event) {

			event.preventDefault();
			event.stopPropagation();
			const mapContainer = (this.map ? this.map.$el : document).querySelector('.ol-viewport');
			if (mapContainer) {
				const wheelEvent = new WheelEvent('wheel', {
					deltaX: event.deltaX,
					deltaY: event.deltaY,
					deltaMode: event.deltaMode,
					clientX: event.clientX,
					clientY: event.clientY,
					bubbles: true,
					cancelable: true
				});
				mapContainer.dispatchEvent(wheelEvent);
			}
		},
		setZindex() {
			const overlayContainer = this.$refs.overlay?.$el?.closest('.ol-overlay-container');
			if (overlayContainer) {
				if (this.mouseover) { 
					overlayContainer.style.zIndex = '11';
				} else if (this.isSelected) {
					overlayContainer.style.zIndex = '10';
				} else if (this.markerStyle != 'Bodenfeuchte_Farbkreis'
						&& this.markerStyle != 'Bodenfeuchte_vol' 
						&& this.markerStyle != 'Bodenfeuchte_nfk' 
						){
					if (this.telemetry[this.markerStyle]) {
						overlayContainer.style.zIndex = '10'
					} else {
						overlayContainer.style.zIndex = '0';
					}
				} else {
					overlayContainer.style.zIndex = '0';
				}
			}
		},
		
	},
	watch: {
		selectedDevice(newValue) {
			this.mouseoverDevice = null;
			this.setZindex();
		},
		selectedSoil() {
			this.setZindex();
		},
		markerStyle() {
			if (this.markerStyle == 'Bodenfeuchte_vol' || this.markerStyle == 'Bodenfeuchte_nfk' ) {
				this.calculateValues();
			}
			this.setZindex();
		},
		device() {
			this.calculateValues();
		},
	},
	mounted() {
		this.$nextTick(() => {
			setTimeout(() => {
				const el = this.$refs.popup;
				if (!el) return;

				const rect = el.getBoundingClientRect();
				if (rect.height === 0) {
					return;
				}

				const spaceAbove = rect.top;
				this.popupBelow = spaceAbove < rect.height + 20;
			}, 50);
		});
	}
	
};
</script>

<style lang="stylus">



</style>
