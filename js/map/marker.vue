<template>

	<ol-overlay 
		ref="overlay"
		v-if="haslatlong && hasTelemetry"
		:key="device.id"
		:position="position(device)"
		>
		<div class="marker-outer" :class="{telemetryloaded: telemetryLoaded, filtered: isFiltered}">

			<template v-if="showProperty">

				<div v-if="propertyObj" class="map-marker property"
						:class="{ mouseover, selected: isSelected }"
						@mouseenter="onMouseEnter"
						@mouseleave="onMouseLeave"
						@click="click"
						@wheel="wheelForward"
						>
					<div class="propertyicon">
						
						<Icon :obj="propertyObj" :size="36"/>

					</div>	
				</div>
				
			</template>

			<template v-else-if="isInactive">

				<div class="map-marker"
						:class="{ mouseover, selected: isSelected, warning: showErrors }"
						@mouseenter="onMouseEnter"
						@mouseleave="onMouseLeave"
						@click="click"
						@wheel="wheelForward"
						>
					<div class="disabled" :class="{warning: showErrors }">
						<template v-if="showErrors">
							!
						</template>
					</div>	
				</div>
				
			</template>
			

			<template v-else-if="isVisible">

				<template v-if="markerStyle == 'Bodenfeuchte_Farbkreis'">

					<div 
						class="map-marker kreis"
						:class="{ mouseover, selected: isSelected }"
						@mouseenter="onMouseEnter"
						@mouseleave="onMouseLeave"
						@click="click"
						@wheel="wheelForward"
						>
						
						<div class="schichten">

						<div class="schicht" v-if="nfk_10 != undefined" :style="'background:'+dataModel.get_nfk_color(nfk_10)"></div>
						<div class="schicht" v-if="nfk_30 != undefined" :style="'background:'+dataModel.get_nfk_color(nfk_30)"></div>
						<div class="schicht" v-if="nfk_60 != undefined" :style="'background:'+dataModel.get_nfk_color(nfk_60)"></div>
						<div class="schicht" v-if="nfk_80 != undefined" :style="'background:'+dataModel.get_nfk_color(nfk_80)"></div>

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
						<div v-if="vol_avg != null && !Number.isNaN(vol_avg)"  class="schicht" :style="'background:'+nfk_color">
							<div class="value">{{ vol_avg.toFixed(0) }}<span class="unit">%</span></div>
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
						<div v-if="nfk_avg != null && !Number.isNaN(nfk_avg)" class="schicht" :style="'background:'+nfk_color">
							<div class="value">{{ nfk_avg.toFixed(0) }}<span class="unit">%</span></div>
						</div>	  
					</div>

				</template>

			</template>

		</div>
			
		</ol-overlay>

</template>

<script>
import { fromLonLat } from 'ol/proj.js';
import { config } from '@/config.js';
import { displayutil } from '@/displayutil.js';
import { dataModel } from '@/dataModel.js';
import dataStore from '@/dataStore.js';
import {state} from '@/state.js'
import MapPopup from '@/map/popup.vue';
import Icon from '@/ui/Icon.vue';

export default {
	name: 'MapMarker',
	components: {
		MapPopup,
		Icon
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
		hasTelemetry() {
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
		schema() {
			return this.device.telemetrySchema.schema;
		},
		nfk10_index() {
			const i = this.schema.indexOf('nfk_10cm');
			return (i >= 0) ? i : null;
		},
		nfk30_index() {
			const i = this.schema.indexOf('nfk_30cm');
			return (i >= 0) ? i : null;
		},
		nfk60_index() {
			const i = this.schema.indexOf('nfk_60cm');
			return (i >= 0) ? i : null;
		},
		nfk80_index() {
			const i = this.schema.indexOf('nfk_80cm');
			return (i >= 0) ? i : null;
		},
		nfkavg_index() {
			const i = this.schema.indexOf('nfk_avg');
			return (i >= 0) ? i : null;
		},
		volavg_index() {
			const i = this.schema.indexOf('vol_avg');
			return (i >= 0) ? i : null;
		},
		nfk_10() {
			if (this.nfk10_index != null) {
				return this.displayData[this.nfk10_index];
			}
		},
		nfk_30() {
			if (this.nfk30_index != null) {
				return this.displayData[this.nfk30_index];
			}
		},
		nfk_60() {
			if (this.nfk60_index != null) {
				return this.displayData[this.nfk60_index];
			}
		},
		nfk_80() {
			if (this.nfk80_index != null) {
				return this.displayData[this.nfk80_index];
			}
		},
		nfk_avg() {
			if (this.nfkavg_index != null) {
				return Math.max(0,this.displayData[this.nfkavg_index]);
			}
		},
		vol_avg() {
			if (this.volavg_index != null) {
				return this.displayData[this.volavg_index];
			}
			},
			lastData() {
				const lastRow = this.device?.telemetrySchema?.data?.[0];
				if (Array.isArray(lastRow)) {
					return lastRow;
				} else {
					return null;
				}
			},
		filteredDevices() {
			return state.filteredDevices;
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
			sidebarFullView() {
				return state.sidebarFullView;
			},
			firstDate() {
				return this.telemetryData?.data?.[0]?.[0] ?? null;
			},
			lastDate() {
				const rows = this.telemetryData?.data;
				return rows?.length ? rows[rows.length - 1]?.[0] ?? null : null;
			},
		isVisible() {
			if (!this.timelineDate && this.hoursSinceLastTelemetry < config.noTelemetryCutoff ) {
				return true;
			} 
			if (this.timelineDate && this.timelineDate >= this.firstDate && this.timelineDate < this.lastDate + config.timeDisplayCutoff) {
				return true;
			}
		},
		cacheTime() {
			return state.cacheTime;
		},
		timelineDate() {
			return state.timelineDate;
		},
		hoursSinceLastTelemetry() {
			return dataStore.hoursSinceLastTelemetry(this.device.id);
		},
		isFiltered() {
			if (state.includeFilter.length == 0 && state.excludeFilter.length == 0) { 
				return false
			}
			var isfiltered = true;
			this.filteredDevices.forEach(device => {
				if (device.id == this.device.id) {
					isfiltered = false;
				}
			});
			return isfiltered;
		},
		isInactive() { // no current telemetry
			if (!this.timelineDate && this.hoursSinceLastTelemetry > config.noTelemetryCutoff ) {
				return true;
			} 
			// TODO return true if inactive on timeline
		},
		showErrors() {
			return state.showErrors;
		},
		displayData() {
			if (state.timelineDate && this.telemetryData){
				const data = dataStore.getDataAtTimestamp(this.device.index, state.timelineDate);
				if (data) {return data;}
			}
			return this.lastData;
		},
		nfk_color() {
			if (this.nfk_avg != null) {
				return dataModel.get_nfk_color(this.nfk_avg);
			}
		},
		usageObj() {
			return dataModel.get_usage_obj(this.device);
		},
		soilObj() {
			return dataModel.get_soil_obj(this.device);
		},
		humusObj() {
			return dataModel.get_humus_obj(this.device);
		},
		bewaessertObj() {
			if (this.device.attributes.Bewässerung) {
				return dataModel.bewaessert_obj;
			}
		},
		grundwasserObj() {
			if (this.device.attributes.Grundwasser) {
				return dataModel.grundwasser_obj;
			}
		},
		regenabhängigObj() {
			if (this.device.filterKeywords.indexOf(dataModel.regenabhängig_obj.name)>-1) {
				return dataModel.regenabhängig_obj;
			}
		},
		showProperty() {
			return (this.markerStyle == 'nutzungsart' || this.markerStyle == 'bodenart' || this.markerStyle == 'humusgehalt' || this.markerStyle == 'wasserhaushalt')
		},
		propertyObj() {
			if (this.markerStyle == 'nutzungsart') {return this.usageObj};
			if (this.markerStyle == 'bodenart') {return this.soilObj};
			if (this.markerStyle == 'humusgehalt') {return this.humusObj};
			if (this.markerStyle == 'wasserhaushalt' && this.bewaessertObj) {return this.bewaessertObj};
			if (this.markerStyle == 'wasserhaushalt' && this.grundwasserObj) {return this.grundwasserObj};
			if (this.markerStyle == 'wasserhaushalt' && this.regenabhängigObj) {return this.regenabhängigObj};
		}
	},
	methods: {
		position(device) {
			return fromLonLat([device.attributes?.longitude, device.attributes?.latitude])
		},
		getSensorValue() {
			return this.device.telemetry[schicht];
		},
		isTouchLikeInteraction(event) {
			if (state.isMobile) return true;
			if (event?.pointerType === 'touch') return true;
			if (event?.sourceCapabilities?.firesTouchEvents) return true;
			if (typeof window === 'undefined') return false;
			return window.matchMedia?.('(hover: none), (pointer: coarse)')?.matches || false;
		},
		clearHover() {
			this.mouseover = false;
			if (this.map?.mouseoverDevice === this.device) {
				this.map.mouseoverDevice = null;
			}
		},
		onMouseEnter(event) {
			if (this.isTouchLikeInteraction(event)) return;
			this.mouseover = true;
			this.map.mouseoverDevice = this.device;
			this.setZindex()
		},
		onMouseLeave(event) {
			this.clearHover();
			this.setZindex()
		},
		click(event) {
			if (this.isTouchLikeInteraction(event)) {
				this.clearHover();
			}
			state.markerClicked = true;
			state.selectedDevice = this.device?.name || null;
		},
		getTelemetry(){
			this.telemetryData = dataStore.fetchTelemetryCache(this.device.id);
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
				} else if (this.isInactive){
					overlayContainer.style.zIndex = '0';
				} else if (this.isFiltered){
					overlayContainer.style.zIndex = '-1';
				} else if (this.markerStyle != 'Bodenfeuchte_Farbkreis'
						&& this.markerStyle != 'Bodenfeuchte_vol' 
						&& this.markerStyle != 'Bodenfeuchte_nfk' 
						){
					if (this.telemetry[this.markerStyle]) {
						overlayContainer.style.zIndex = '10'
					} else {
						overlayContainer.style.zIndex = '1';
					}
				} else {
					overlayContainer.style.zIndex = '1';
				}
			}
		},
	},
	watch: {
		selectedDevice(newValue) {
			this.mouseoverDevice = null;
			this.setZindex();
		},
		timelineDate() {
			this.setZindex();
		},
		filteredDevices() {
			this.setZindex()
		},
		sidebarFullView() {
			this.setZindex()
		},
		telemetryLoaded() {
			this.getTelemetry();
			this.setZindex();
		},
		showErrors() {
			this.setZindex();
		},
		markerStyle() {
			this.setZindex();
		},
		device() {
		},
	},
	mounted() {
		this.getTelemetry();
		this.$nextTick(() => {
			this.setZindex();
		});
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
