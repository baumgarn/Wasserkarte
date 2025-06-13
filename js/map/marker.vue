<template>

			<ol-overlay 
				ref="overlay"
				v-if="haslatlong"
				:key="device.id.id"
				:position="position(device)"
				>
				<div class="marker-outer" :class="{selectedSoil: (selectedSoil != 'Alle' && isSelectedSoil), notSelectedSoil: (selectedSoil != 'Alle' && !isSelectedSoil)}">

					<template v-if="selectedSoil != 'Alle' && !isSelectedSoil">

						<div class="map-marker disabled">
							<!-- <div class="disabled">
								
							</div>	 -->
						</div>
					
					</template>

					<template v-else-if="markerStyle == 'Bodenfeuchte Alle'">

						<div 
							v-if="bodenfeuchteSensors.length > 0"
							class="map-marker alle"
							:class="{ mouseover, selected: isSelected }"
							@mouseenter="onMouseEnter"
							@mouseleave="onMouseLeave"
							@click="click"
							@wheel="wheelForward"
							>

							<div class="schichten">

								<div class="schicht" v-if="telemetry.Bodenfeuchte_10cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_10cm))">
									<div class="value">{{ parseFloat(getLastValue(telemetry.Bodenfeuchte_10cm)).toFixed(0) }}<span class="unit">%</span></div>
								</div>
								<div class="schicht" v-if="telemetry.Bodenfeuchte_30cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_30cm))">
									<div class="value">{{ parseFloat(getLastValue(telemetry.Bodenfeuchte_30cm)).toFixed(0) }}<span class="unit">%</span></div>
								</div>
								<div class="schicht" v-if="telemetry.Bodenfeuchte_60cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_60cm))">
									<div class="value">{{ parseFloat(getLastValue(telemetry.Bodenfeuchte_60cm)).toFixed(0) }}<span class="unit">%</span></div>
								</div>
								<div class="schicht" v-if="telemetry.Bodenfeuchte_80cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_80cm))">
									<div class="value">{{ parseFloat(getLastValue(telemetry.Bodenfeuchte_80cm)).toFixed(0) }}<span class="unit">%</span></div>
								</div>

							</div>
							
						</div>

					</template>

					<template v-else-if="markerStyle == 'Bodenfeuchte Farbkreis'">

						<div 
							class="map-marker kreis"
							:class="{ mouseover, selected: isSelected }"
							@mouseenter="onMouseEnter"
							@mouseleave="onMouseLeave"
							@click="click"
							@wheel="wheelForward"
							>
							
							<div class="schichten">

								<div class="schicht" v-if="telemetry.Bodenfeuchte_10cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_10cm))">
								</div>
								<div class="schicht" v-if="telemetry.Bodenfeuchte_30cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_30cm))">
								</div>
								<div class="schicht" v-if="telemetry.Bodenfeuchte_60cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_60cm))">
								</div>
								<div class="schicht" v-if="telemetry.Bodenfeuchte_80cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_80cm))">
								</div>

							</div>

						</div>

					</template>

					<template v-else>

						<div 
							class="map-marker einzeln"
							:class="{ mouseover, selected: isSelected }"
							@mouseenter="onMouseEnter"
							@mouseleave="onMouseLeave"
							@click="click"
							@wheel="wheelForward"
							>
							<div class="schicht" v-if="telemetry[markerStyle]" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry[[markerStyle]]))">
								<div class="value">{{ parseFloat(getLastValue(telemetry[[markerStyle]])).toFixed(0) }}<span class="unit">%</span></div>
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
	data() {
		return {
			mouseover: false,
			schichten: ['Bodenfeuchte_10cm','Bodenfeuchte_30cm','Bodenfeuchte_60cm','Bodenfeuchte_80cm'],
			displayutil,
			dataModel,
			popupBelow: false,
		};
	},
	props: {
		device: Object,
		map: Object,
		noPopup: Boolean,
	},
	computed: {
		telemetry() {
			return this.device.telemetry || {};
		},
		bodenfeuchteSensors() {
			return Object.entries(this.telemetry)
				.filter(([key]) => key.startsWith("Bodenfeuchte"))
				.map(([key, data]) => ({ key, data }));
		},
		attributes() {
			return this.device.attributes || {};
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
		}
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
			// if (state.selectedDevice != this.device.name) {
				this.map.mouseoverDevice = this.device;
			// } else {
				// this.map.mouseoverDevice = null;
			// }
			this.setZindex()
		},
		onMouseLeave(event) {
			this.mouseover = false;
			this.map.mouseoverDevice = null;
			this.setZindex()
			// if (this.selectedDevice != this.device) {
			// }
		},
		click(event) {
			// this.map.mouseoverDevice = null;
			state.markerClicked = true;
			state.selectedDevice = this.device?.name || null;
		},
		wheelForward(event) {

			event.preventDefault(); // block scrollview
			event.stopPropagation(); // block scrollview
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
				if (this.mouseover && (this.selectedSoil == "Alle" || !this.isSelectedSoil )) { 
					overlayContainer.style.zIndex = '11';
				} else if (this.isSelected) {
					overlayContainer.style.zIndex = '10';
				} else if (this.selectedSoil != "Alle" && this.isSelectedSoil) { 
					overlayContainer.style.zIndex = '9';
				} else {
					overlayContainer.style.zIndex = '0';
				}
			}
		}
	},
	watch: {
		selectedDevice(newValue) {
			this.mouseoverDevice = null;
			this.setZindex()
		},
		selectedSoil() {
			this.setZindex()
		}
	},
	mounted() {
		this.$nextTick(() => {
			setTimeout(() => {
				const el = this.$refs.popup;
				if (!el) return;

				const rect = el.getBoundingClientRect();
				if (rect.height === 0) {
					console.warn('Popup height still 0');
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
