<template>

	<div class="schichten-uebersicht">
		<div class="schichten-container" > 
			<div class="schichten">
			
				<div class="schicht" v-for="depth in depths" :key="depth" :style="getBackgroundColorStyle('Bodenfeuchte_'+depth+'cm')">

					<template v-if="dataPresent">
				
						<div class="depth">
							{{ depth }} cm
						</div>

						<div class="bodentemperatur">
							<template v-if="hasSensorForDepth('Bodentemperatur',depth)">
								<span class="value">{{ getData('Bodentemperatur_'+depth+'cm') }}</span>
								<span class="unit">{{ getDisplayUnit('Bodentemperatur_'+depth+'cm') }}</span>
							</template>
						</div>

						<div class="bodenfeuchte">
							<template v-if="hasSensorForDepth('Bodenfeuchte',depth)" >
								<span class="value">{{ getData('Bodenfeuchte_'+depth+'cm') }}</span>
								<span class="unit"><span class="unittype">Vol</span>%</span>
							</template>
						</div>
						
						<div class="bodenfeuchteNFK">
							<template v-if="hasSensorForDepth('Bodenfeuchte',depth)" >
								<span class="value">{{ getSoilMoistureLevelNFK('Bodenfeuchte_'+depth+'cm') }}</span>
								<span class="unit"><span class="unittype">nFK</span>%</span>
							</template>
						</div>
						<div class="bodenfeuchteName">
							<template v-if="hasSensorForDepth('Bodenfeuchte',depth)" >
								<span class="name">{{ getSoilMoistureLevelName('Bodenfeuchte_'+depth+'cm') }}</span>
							</template>
						</div>

					</template>

				</div>
			</div>
			
		</div>
		<div class="date" v-if="hoverData && hoverData.ts">
			{{ hoverData && hoverData.ts ? new Date(hoverData.ts).toLocaleString(undefined, { 
				year: 'numeric',
				month: 'numeric',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
				hour12: false
			}).replace(',', '') : '' }}
		</div>


	</div>
</template>

<script>
import { displayutil } from '../displayutil.js'
import { config } from '../config.js'

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
			filteredSensors: []
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
			// get depths from locationdata.telemetry and filter by allowedTelemetryKeys, before actual sensordata is available
			const telemetryKeys = Object.keys(this.device.telemetry || {});
			return [...new Set(telemetryKeys
				.filter(key => config.allowedTelemetryKeys.includes(key))
				.map(key => this.getDepthValue(key))
			)];
		},
		dataPresent() {
			return this.sensors.some(sensor => sensor.data?.length && sensor.data.length > 1);
		},
		

	},
	methods: {
		getData(key) {
			if (this.hoverData && this.hoverData[key]) {
				return this.hoverData[key].value?.toFixed(1);
			} else {
				return this.getLastSensorData(key).value?.toFixed(1);
			}
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
		// getBackgroundColorStyle(key) {
		// 	if (this.dataPresent) {
		// 		let value = this.getData(key);
		// 		if (value) {
		// 			return displayutil.getBackgroundColorStyle(this.device.attributes.soilType, value);
		// 		}
		// 	}
		// },
		// getSoilMoistureLevelName(key) {
		// 	return displayutil.getSoilMoistureLevelName(this.device.attributes.soilType, this.getData(key));
		// },
		// getSoilMoistureLevelNFK(key) {
		// 	return displayutil.getSoilMoistureLevelNFK(this.device.attributes.soilType, this.getData(key));
		// },
	
	},
	mounted() {
	},
	beforeUnmount() {
	},

}
</script>

<style lang="stylus" scoped>

.schichten-uebersicht
	margin 14px 0 
	position relative

.schichten-container
	position relative
	filter var(--dropshadowfilter)

.schichten
	position: relative;
	overflow: hidden;
	border-radius var(--chartborderradius)
	background var(--uibrighter)
	border var(--chartborderstyle)


.schichten-header
.schicht
	display flex
	flex-direction row
	align-items center
	height 28px
	font-size 9pt
	> *
		flex 1
		padding 0
		// border-right 1px solid #ccc
		text-align right
		white-space nowrap
	.depth
		flex 0.2
		padding-left 8px
		opacity 1
		text-align left
	.value
		font-size 10pt
	.unit
		font-weight normal
		display inline-block
		margin-left .15em
		margin-left .25em
		font-size 9pt
		opacity .8
		.unittype
			font-size 8pt
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
		// opacity .8
	.bodenfeuchteNFK .value
		font-weight normal
		opacity .8
		font-size 9pt

// .schicht:first-of-type
// 	background red !important
	// border-top-left-radius 1em
	// border-top-right-radius 1em
	// border 1px solid red !important
	// overflow hidden

.schichten-header
	font-size 7pt
	font-weight bold
	opacity .9
	height auto
	padding 4px 0 3px
.date
	position absolute
	top 0
	right 0
	padding .3em .6em
	font-size 8pt
	opacity .8
	display none



</style>