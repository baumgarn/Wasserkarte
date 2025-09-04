<template>

	<div class="schichten-uebersicht">
		<div class="schichten-container" > 
				<div v-if="hoverData && hoverData.ts" class="latestdate">
					{{ displayutil.formatDateShort(hoverData.ts) }}
					<span v-if="dataAggregation != '1d'" class="time">{{ displayutil.formatDateTime(hoverData.ts) }}</span>
				</div>
			<div class="schichten">
				
				<div class="schicht" v-for="depth in depths" :key="depth" >

					<!-- <template v-if="dataPresent"> -->
				
						<div class="depth">
							<div class="line" 
							:style="getDepthLineStyle(depth)"></div>
							<div class="value">
								{{ depth }} cm
							</div>
						</div>

						<template v-if="hasSensorForDepth('Bodentemperatur',depth)">
						<div class="bodentemperatur">
								<span class="value">{{ formatNumber(getData('Bodentemperatur_'+depth+'cm')) }}</span>
								<span class="unit" v-if="validHoverData('Bodentemperatur_'+depth+'cm')">{{ getDisplayUnit('Bodentemperatur_'+depth+'cm') }}</span>
							</div>
						</template>

						<template v-if="hasSensorForDepth('Bodenfeuchte',depth)" >
						<div class="bodenfeuchte" >

								<span class="value">{{ formatNumber(getData('Bodenfeuchte_'+depth+'cm')) }}</span>
								<span class="unit" v-if="validHoverData('Bodenfeuchte_'+depth+'cm')">%</span>

							</div>
						</template>

					<!-- </template> -->

				</div>
			</div>
			
		</div>


	</div>
</template>

<script>
import { displayutil } from '../displayutil.js'
import { config } from '../config.js'
import { state } from '../state.js'

export default {
	name: 'SchichtenUebersicht',
	data() {
		return {
			rowHeight: 80,
			rowMargin: 0,
			linePathRefs: [],
			heatmapImages: [],
			hoverValidRange: false,
			lastData: [],
			filteredSensors: [],
			displayutil
		}
	},
	props: {
		sensors: {
			type: Object,
			default: () => {},
		},
		device: {
			required: true,
			type: Object,
		},
		hoverData: {
			type: Object,
			required: false
		},
	},
	computed: {
		depths() {
			// get depths from locationdata.latestTelemetry and filter by allowedTelemetryKeys, before actual sensordata is available
			const telemetryKeys = Object.keys(this.device.telemetry || {});
			return [...new Set(telemetryKeys
				.filter(key => config.allowedTelemetryKeys.includes(key))
				.map(key => this.getDepthValue(key))
			)];
		},
		dataAggregation () {
			return state.dataAggregation;
		}
	},
	methods: {
		getData(key) {
			if (this.hoverData && this.hoverData[key]) {
				if (this.validHoverData(key)) {
					return this.hoverData[key].value?.toFixed(1);
				} else {
					return '-';
				}
			} else {
				return this.getLastSensorData(key).value?.toFixed(1);
			}
		},
		validHoverData(key) {
			return this.hoverData && this.hoverData[key] && this.hoverData[key].valid
		},
		getSensorData(key) {
			return this.sensors.find(sensor => sensor.key === key)?.data || [];
		},
		getLastSensorData(key) {
			return this.sensors.find(sensor => sensor.key === key)?.data[this.sensors.find(sensor => sensor.key === key)?.data.length - 1] || [];
		},
		hasSensorForDepth(type, depth) {
			return this.sensors.some(sensor => sensor.key.startsWith(type) && this.getDepthValue(sensor.key) === depth)
		},
		getDepthValue(key) {
			return displayutil.depthValue(key)
		},
		getDisplayDepth(key) {
			return displayutil.depth(key)
		},
		getDisplayTitle(key) {
			return displayutil.title(key)
		},
		getDisplayUnit(key) {
			return displayutil.unit(key)
		},
		getBackgroundColorStyle(key) {
			if (this.dataPresent) {
				let value = this.getData(key);
				if (value) {
					return displayutil.getBackgroundColorStyle(this.device.attributes.soilType, value);
				}
			}
		},
		getDepthLineStyle(depth) {
			var s =	 "background:" + displayutil.getDepthColor(depth);
			return s;
		},
		getSoilMoistureLevelName(key) {
			return displayutil.getSoilMoistureLevelName(this.device.attributes.soilType, this.getData(key));
		},
		getSoilMoistureLevelNFK(key) {
			return displayutil.getSoilMoistureLevelNFK(this.device.attributes.soilType, this.getData(key));
		},
		formatNumber(floatString) {
			return parseFloat(floatString).toFixed(1).replace('.', ',');
		},
	
	},
	mounted() {
	},
	beforeUnmount() {
	},

}
</script>

<style lang="stylus" scoped>

.schichten-uebersicht
	margin 0 0 
	position relative

.schichten-container
	position relative
	// filter var(--dropshadowfilter)

.schichten
	position: relative;
	overflow: hidden;
	// border-radius var(--chartborderradius)
	// background var(--uibrighter)
	// border var(--chartborderstyle)


.schichten-header
.schicht
	display flex
	flex-direction row
	align-items center
	gap 12px
	font-size 8pt
	> *
		flex 1
		padding 0
		text-align right
		white-space nowrap
	.depth
		opacity 1
		text-align left
		display flex
		flex-direction row
		align-items center
		.line
			height 2px
			flex-basis 18px
			flex-grow 0
			flex-shrink 0
			margin-right 3px
			background #444
		.value
			font-size 8pt
			opacity .8
			font-weight normal
	.value
		font-size 9pt
	.unit
		font-weight normal
		display inline-block
		margin-left .15em
		margin-left .25em
		font-size 8pt
		opacity .8
		.unittype
			font-size 7pt
			display inline-block
			margin-right .25em
	.bodenfeuchteName
		flex 2
		text-align left
		text-align right
		display block
		text-overflow ellipsis
		overflow hidden
		padding-left 15px
		padding-right 12px
	.bodenfeuchteNFK .value
		font-weight normal
		opacity .8
		font-size 9pt

.latestdate
	padding 3px 0
	font-size 8pt
	opacity .8
	text-align center
	text-align right
	font-weight bold
	font-weight normal


</style>