<script>
	import * as d3 from 'd3'
	import HeaderInfo from '@/location/headerinfo.vue';
	import OberbodenUebersicht from '@/location/oberboden.vue';
	import DateInfo from '@/location/dateinfo.vue';
	import SensorData from '@/location/sensordata.vue';
	import ChartTime from '@/charts/chart_timeaxis.vue';
	import ChartHeat from '@/charts/chart_heat.vue';
	import ChartGraph from '@/charts/chart_graph.vue';
	import ChartRange from '@/charts/chart_rangebar.vue';
	import ToolTip from '@/charts/tooltip.vue';
	import ChartSettings from '@/charts/chart_settings.vue';
	import SchichtenUebersicht from '@/location/schichten_uebersicht.vue';
	import SelectGroup from '@/ui/selectgroup.vue';
	import MiniMap from '@/map/minimap.vue';
	import dataStore from '@/datastore.js';
	import { state } from '@/state.js';
	import { dataModel } from '@/dataModel.js'
	import { displayutil } from '@/displayutil.js'
	import { config } from '@/config.js';
	export default {

		name: 'LocationView',
		
		components: {
			HeaderInfo,
			OberbodenUebersicht,
			DateInfo,
			ChartHeat,
			ChartGraph,
			ChartTime,
			ChartSettings,
			SensorData,
			ChartRange,
			SchichtenUebersicht,
			ToolTip,
			MiniMap,
			SelectGroup
		},
		setup() {
			return {dataModel}
		},

		props: {
			device: {
				type: Object,
				required: true
			},
			context: {
				type: String,
				default: "sidebar"
			}
		},
		data() {
			return {
				displayutil,
				sensorData: {},
				graphScale: 1.0,
				graphPosition: 0,
				earliestDate: 0,
				latestDate: 0,
				earliestTimestamp: 0,
				latestTimestamp: 0,
				frameMargin: 0,
				hoverPosition: -1,
				frameWidth: 0,
			};
		},
		computed: {
			nfk() {
				const nfk = dataModel.nfk(this.device, this.hoverData);
				if (isNaN(nfk)) return '–'
				return parseFloat(nfk.toFixed(0));
			},
			chartStyle() {
				return state.chartStyle;
			},
			dataAggregation() {
				return state.dataAggregation;
			},
			chartTimeRange: {
				get() {
					return state.chartTimeRange;
				},
				set(value) {
					state.chartTimeRange = value;
				}
			},
			apiUrl() {
				return dataStore.getApiUrl(this.device.id.id, 'all', state.dataAggregation);
			},
			bodentemperaturSensors() {
				const sensorKeys = ["Bodentemperatur_10cm", "Bodentemperatur_30cm", "Bodentemperatur_60cm", "Bodentemperatur_80cm"];
				return this.filterSensors(sensorKeys);
			},
			bodenfeuchteSensors() {
				const sensorKeys = ["Bodenfeuchte_10cm", "Bodenfeuchte_30cm", "Bodenfeuchte_60cm", "Bodenfeuchte_80cm"];
				return this.filterSensors(sensorKeys);
			},
			allSensors() {
				const sensorKeys = ["Bodenfeuchte_10cm", "Bodenfeuchte_30cm", "Bodenfeuchte_60cm", "Bodenfeuchte_80cm", "Bodentemperatur_10cm", "Bodentemperatur_30cm", "Bodentemperatur_60cm", "Bodentemperatur_80cm"];
				return this.filterSensors(sensorKeys);
			},
			hasBodenfeuchteSensors() {
				return this.bodenfeuchteSensors.length > 0;
			},
			hasBodentemperaturSensors() {
				return this.bodentemperaturSensors.length > 0;
			},
			otherSensors() {
				return this.sensors.filter(
					(sensor) =>
						!sensor.sensore_title.includes("BF") &&
						!sensor.sensore_title.includes("BT")
				);
			},
			lastTelemetry() {
				return dataStore.lastTelemetry(this.device.id.id)
			},
			numberOfDays() {
				return Math.max(this.chartTimeRange,(this.latestTimestamp - this.earliestTimestamp) / (1000 * 60 * 60 * 24));
			},
			startTimestamp() {
				return this.latestTimestamp - this.numberOfDays * 24 * 60 * 60 * 1000;
			},
			chartWidth() {
				return this.frameWidth / this.graphScale;
			},
			scrollLeft(){
				return (this.chartWidth - this.frameWidth) * (this.graphPosition / (1 - this.graphScale)) || 0;
			},
			dataPresent() {
				return Object.values(this.sensorData).some(data => Array.isArray(data) && data.length > 1);
			},
			globalExtentX() {
				return [this.startTimestamp, this.latestTimestamp];
			},
			sidebarFullView() {
				return state.sidebarFullView;
			},
			wideView() {
				return (this.frameWidth > 900);
			},
			hoverData() {
				if (this.hoverPosition < 0 || this.hoverPosition > this.chartWidth) {
					return null;
				}

				const xScale = d3.scaleTime()
					.domain(this.globalExtentX)
					.range([0, this.chartWidth]);

				const hovertime = xScale.invert(this.hoverPosition + this.scrollLeft).getTime();

				let hoverdata = {};

				for (let key in this.sensorData) {
					const data = this.sensorData[key];

					if (!data || data.length === 0) {
						hoverdata[key] = { value: '-', valid: false };
						continue;
					}

					// Case: After last point
					if (hovertime > data[data.length - 1].ts) {
						const gap = hovertime - data[data.length - 1].ts;
						hoverdata[key] = {
							ts: hovertime,
							value: (gap > config.dataGapLength) ? '-' : data[data.length - 1].value,
							valid: (gap <= config.dataGapLength)
						};
						continue;
					}

					// Case: Before first point
					if (hovertime < data[0].ts) {
						hoverdata[key] = { value: '-', valid: false };
						continue;
					}

					// Find the closest point
					let closest = null;
					let minDiff = Infinity;

					for (let i = 0; i < data.length; i++) {
						const point = data[i];
						const diff = Math.abs(point.ts - hovertime);

						// If equal diff, prefer the *later* point
						if (diff < minDiff || (diff === minDiff && point.ts > closest.ts)) {
							closest = point;
							minDiff = diff;
						}
					}

					const isValid = (! config.segmentation || minDiff <= config.dataGapLength);
					hoverdata[key] = {
						ts: closest.ts,
						value: isValid ? closest.value : '-',
						valid: isValid
					};

					if (isValid && !hoverdata.ts) {
						hoverdata.ts = closest.ts;
						hoverdata.xpos = xScale(new Date(closest.ts)) - this.scrollLeft - 1;
					}
				}

				return hoverdata;
			}
		},
		methods: {
			async loadSensorData() {
				this.isVisible = true;
				this.$nextTick(() => {
					this.updateFrameWidth();
					
				});
				await this.processSensorData();
			},
			async processSensorData() { 

				this.sensorData = this.device.telemetry;
				this.earliestTimestamp = 0;
				this.latestTimestamp = 0;
				
				const rawData = await dataStore.fetchTelemetryData(this.device.id.id, 'all', state.dataAggregation);
				
				this.earliestDate = rawData.earliestDate;
				this.latestDate = rawData.latestDate;
				this.earliestTimestamp = rawData.earliestTimestamp;
				this.latestTimestamp = rawData.latestTimestamp;
				
				this.sensorData = {};

				for (const [key, values] of Object.entries(rawData.data)) {
					if (config.allowedTelemetryKeys.includes(key)) {
						this.sensorData[key] = values.map(item => ({
							ts: item.ts,
							value: parseFloat(item.value)
						}));
					}
				}

				if (this.chartTimeRange == 0 ) {
					this.chartTimeRange = -1
					this.graphScale = 1
					this.graphPosition = 0
				}
				if (this.chartTimeRange > 0) {
					this.selectTimeRange(this.chartTimeRange)
				}

			},
			filterSensors(sensorKeys) {
				return Object.entries(this.sensorData)
					.filter(([key]) => sensorKeys.includes(key))
					.map(([key, data]) => ({ key, data }))
					.sort((a, b) => a.key.localeCompare(b.key));
			},
			formatNumber(floatString) {
				return parseFloat(floatString).toFixed(1).replace('.', ',');
			},
			updateGraphPosition(newPosition) {
				this.graphPosition = newPosition;
			},
			handleRangeUpdate(rangeData) {
				this.graphScale = rangeData.scale;
				this.graphPosition = rangeData.position;
				this.chartTimeRange = 0;
			},
			selectTimeRange(rangeValue) {
				if (this.chartTimeRange === -1) {
					this.graphScale = 1;
					this.graphPosition = 0;
				} else {
					this.graphScale = rangeValue / this.numberOfDays;
					this.graphPosition = 1 - this.graphScale;
				}
			},
			selectChartStyle(chartStyleValue) {
				this.chartStyle = chartStyleValue;
				window.dispatchEvent(new CustomEvent('chartstyleselected', { detail: chartStyleValue }));
			},
			updateFrameWidth() {
				this.$nextTick(() => {

				const frameRef = this.$refs.frameRef;
				this.frameWidth = frameRef ? (frameRef.clientWidth - this.frameMargin) : 0;
				})
			},
			scrollWheel(event) {
				if (this.graphScale < 1) {
					let newPosition = ((this.scrollLeft + event.deltaX) / (this.chartWidth - this.frameWidth)) * (1-this.graphScale);
					if (newPosition < 0) {
						newPosition = 0;
					}
					if (newPosition + this.graphScale > 1) {
						newPosition = 1 - this.graphScale;
					}
					this.graphPosition = newPosition;
				}
			},
			hover(event) {
				const rect = this.$refs.frameRef.getBoundingClientRect();
				if (event.type === "mousemove") {
					this.hoverPosition = event.clientX - rect.left;
				} else if (event.type === "touchmove") {
					this.hoverPosition = event.touches[0].clientX; - rect.left;
				}
			},
			hoverOut() {
				this.hoverPosition = -1;
			},
			linktomap() {
				this.$router.push('/');
			}
		},
		watch: {
			device() {
				this.loadSensorData();
			},
			dataAggregation() {
				this.loadSensorData();
			},
			chartTimeRange: {
				handler(newValue) {
					this.selectTimeRange(newValue);
				},
				immediate: true
			},
			sidebarFullView() {
				this.updateFrameWidth();
			},

		},
		mounted() {
			this.loadSensorData();
			state.minimapZoom = config.minimapZoom;
			window.addEventListener('resize', this.updateFrameWidth);
		},
		beforeUnmount() {
			window.removeEventListener('resize', this.updateFrameWidth);
		},
	};



</script>

<template>

	
	<div class="location" :class="[ { wideView, sidebarFullView }, 'context-' + context ]">

	
	<div class="framered" ref="frameRef"></div>

		<div v-if="wideView" class="headersplit">
			
			<div class="locationheader left">
				
				<h2>
					<span class="title">{{ device.attributes?.Anzeigename || device.name }}</span>
				</h2>
				
				<DateInfo :device :hoverData :earliestTimestamp :latestTimestamp/>
				
				<OberbodenUebersicht :device :hoverData />
				
			</div>
			
			<div class="right">

				<MiniMap :device v-if="device.attributes.latitude"/>	

			</div>
				
		</div>

		<div v-else class="locationheader">
			
			<h2>
				<span class="title">{{ device.attributes?.Anzeigename || device.name }}</span>
			</h2>
			
			<DateInfo :device :hoverData :earliestTimestamp :latestTimestamp/>
			
			<OberbodenUebersicht :device :hoverData />
			
		</div>
		
		<ChartSettings :graphScale :frameWidth :graphPosition :dataPresent @range-update="handleRangeUpdate" :earliestTimestamp :latestTimestamp />

		<div class="scrollcontainer" @wheel="scrollWheel">
			
			<div class="hovercontainer" @mousemove="hover" @mouseleave="hoverOut" @touchmove="hover" @touchend="hoverOut">
			
			<div v-if="hasBodenfeuchteSensors">
				<!-- <hr/> -->

				<div v-if="chartStyle === 'schichten'">

					<div class="graph">
						<ChartHeat 
						title="Bodenfeuchte"
						:sensors="bodenfeuchteSensors" 
						:device
						:chartWidth 
						:frameWidth 
						:scrollLeft 
						:startTimestamp
						:latestTimestamp
						:numberOfDays
						:dataPresent
						:hoverPosition
						:hoverData
						:baseline="0"
						/>
					</div>

				</div>

				<div v-else-if="chartStyle === 'heatmap'">
					
					<div class="graph">

					<ChartHeat 
						title="Bodenfeuchte"
						:sensors="bodenfeuchteSensors" 
						:device
						:chartWidth 
						:frameWidth 
						:scrollLeft 
						:startTimestamp
						:latestTimestamp
						:numberOfDays
						:dataPresent
						:hoverPosition
						:hoverData
						:baseline="0"
						:heatmap="true"
						/>

					</div>
				</div>

				<div v-else-if="chartStyle === 'ueberlagert'">
					
					<div class="graph">

						<ChartGraph 	
							title="Bodenfeuchte"
							:sensors="bodenfeuchteSensors" 
							:device
							:chartWidth 
							:frameWidth 
							:scrollLeft 
							:startTimestamp
							:numberOfDays
							:dataPresent
							:hoverPosition
							:hoverData
							/>
							<!-- :ceiling="35"
							:baseline="0" -->

					</div>
				</div>
			</div>

			<!-- <div v-if="hasBodentemperaturSensors">


				<div v-if="chartStyle === 'schichten'">

					<div class="graph">
						<ChartHeat 
							title="Bodentemperatur"
							:sensors="bodentemperaturSensors" 
							:device
							:chartWidth 
							:frameWidth 
							:scrollLeft 
							:startTimestamp
							:latestTimestamp
							:numberOfDays
							:dataPresent
							:hoverPosition
							:hoverData
							:coloring="'temperatur'"
							:showDate="false"
						/>
					</div>

				</div>

				<div v-else-if="chartStyle === 'heatmap'">
					
					<div class="graph">

					<ChartHeat 
						title="Bodentemperatur"
						:sensors="bodentemperaturSensors" 
						:device
						:chartWidth 
						:frameWidth 
						:scrollLeft 
						:startTimestamp
						:latestTimestamp
						:numberOfDays
						:dataPresent
						:hoverPosition
						:hoverData
						:heatmap="true"
						:coloring="'temperatur'"
						:showDate="false"
						/>

					</div>
				</div>

				<div v-else-if="chartStyle === 'ueberlagert'">
					
					<div class="graph">

						<ChartGraph 
							title="Bodentemperatur"
							:sensors="bodentemperaturSensors" 
							:device
							:chartWidth 
							:frameWidth 
							:scrollLeft 
							:startTimestamp
							:numberOfDays
							:dataPresent
							:hoverPosition
							:hoverData
							:coloring="'temperatur'"
							/>

					</div>
				</div>
			</div> -->
		</div>
	</div>

	<ChartRange 
		:dataPresent
		:graphScale
		:graphPosition
	/>

	<div v-if="context=='single'" class="linktomap" @click="linktomap">
		<div class="sensor">
			<img src="/img/sensor.png" >
		</div>
		<div class="text">
			Alle Standorte auf der Wasserkarte ansehen
		</div>
	</div>

	
	<!-- <div class="additional" v-if="context=='sidebar'">
		<a :href="apiUrl" class="apiurl">API</a>
		<SensorData :device="device" :showTitle="false"></SensorData>
	</div> -->

</div>

</template>

<style lang="stylus" scoped>
	.location
		padding 12px 24px 0
	@media (max-width: 600px)
		.location
			padding 12px 16px 0
	h2
		margin 0 0 .2em
		font-size 18pt
		color #000000cc
	.description
		font-size 10pt
		opacity .9
	.context-single
		.mapcontainer *
			pointer-events none
	.linktomap
		position fixed
		padding 8px
		left 0
		cursor pointer
		right 0
		bottom 0
		background #dcd8ec
		border-top 1px solid #00000022
		box-shadow 0 0 4px #00000011
		display flex
		flex-direction row
		align-items center
		z-index 10000
		.sensor
			margin-right 8px
			filter: grayscale(50%);
			opacity .5
			img
				height 32px
				width 32px
		.text
			font-weight bold
			opacity .8
	.headersplit
		display flex
		flex-direction row
		gap 24px
		height 322px
		// margin-top 24px
		// margin-bottom 24px
		// .locationheader
			// min-height 280px
		.left
			flex-basis calc(50% - 12px)
			flex-grow 0
			flex-shrink 0
		.right
			flex-basis calc(50% - 12px)
			flex-grow 0
			flex-shrink 0
	
	// .map
	// 	height 200px
	// 	border 1px solid red

	#mapcontainer
		width 100%
		margin 0 0 0
		height 320px
		// border 1px solid red
		border-radius var(--chartborderradius)
		// filter var(--dropshadowfilter)
		overflow hidden

	.additional
		position relative
		margin-bottom 12px
	.apiurl
		display inline-block
		margin-top 0em
		font-size 10pt
		position absolute
		right 0
		top 0
	.apiurl
	.sensordata
		margin-left var(--sidebartextmargin)

</style>
