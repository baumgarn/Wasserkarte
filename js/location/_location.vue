<script>

	// This component contains all the displayed visualization for the individual location
	// Content can vary depending on context: Sidebar / Wideview / Iframe / Single location from Qr Code
	// The mouseover graph timeline date is handled here because this way we can handle consistent hover over multiple graphs

	import * as d3 from 'd3'
	import HeaderInfo from '@/location/headerinfo.vue';
	import OberbodenUebersicht from '@/location/oberboden.vue';
	import Beschreibung from '@/location/beschreibung.vue';
	import SoilProperties from '@/location/soilproperties.vue';
	import DebugInfo from '@/location/debuginfo.vue';
	import ChartHeat from '@/charts/chart_heat.vue';
	import ChartGraph from '@/charts/chart_graph.vue';
	import ChartRange from '@/charts/chart_rangebar.vue';
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
			SoilProperties,
			Beschreibung,
			ChartHeat,
			ChartGraph,
			ChartSettings,
			DebugInfo,
			ChartRange,
			SchichtenUebersicht,
			MiniMap,
			SelectGroup
		},
		setup() {
			return {dataModel, displayutil}
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
					sensorData: {},
					sensorDataLoading: false,
					graphScale: 1.0,
					graphPosition: 0,
				earliestTimestamp: 0,
				latestTimestamp: 0,
					frameMargin: 0,
					hoverPosition: -1,
					frameWidth: 0,
					dataPresent: false,
					sensorLoadRequestId: 0,
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
					return dataStore.getApiUrl(this.device.id, 'all', state.dataAggregation);
				},
				availableSensorKeys() {
					if (Array.isArray(this.sensorData?.schema) && this.sensorData.schema.length) {
						return this.sensorData.schema;
					}
					if (Array.isArray(this.device?.telemetrySchema?.schema) && this.device.telemetrySchema.schema.length) {
						return this.device.telemetrySchema.schema;
					}
					return Object.keys(this.device?.telemetry || {});
				},
				bodentemperaturSensors() {
					const sensorKeys = ["Bodentemperatur_10cm", "Bodentemperatur_30cm", "Bodentemperatur_60cm", "Bodentemperatur_80cm"];
					return this.filterSensors(sensorKeys);
				},
			bodenfeuchteSensors() {
				const sensorKeys = ["Bodenfeuchte_10cm", "Bodenfeuchte_30cm", "Bodenfeuchte_60cm", "Bodenfeuchte_80cm"];
				return this.filterSensors(sensorKeys);
			},
			nfk_avg() {
				const sensorKeys = ["nfk_avg"];
				return this.filterSensors(sensorKeys);
			},
			vol_avg() {
				const sensorKeys = ["vol_avg"];
				return this.filterSensors(sensorKeys);
			},
				allSensors() {
					const sensorKeys = ["Bodenfeuchte_10cm", "Bodenfeuchte_30cm", "Bodenfeuchte_60cm", "Bodenfeuchte_80cm", "Bodentemperatur_10cm", "Bodentemperatur_30cm", "Bodentemperatur_60cm", "Bodentemperatur_80cm"];
					return this.filterSensors(sensorKeys);
				},
				hasBodenfeuchteSensors() {
					return this.availableSensorKeys.some(key => key.startsWith('Bodenfeuchte_'));
				},
				hasBodentemperaturSensors() {
					return this.availableSensorKeys.some(key => key.startsWith('Bodentemperatur_'));
				},
			otherSensors() {
				return this.sensors.filter(
					(sensor) =>
						!sensor.sensore_title.includes("BF") &&
						!sensor.sensore_title.includes("BT")
				);
			},
			lastTelemetry() {
				return dataStore.lastTelemetry(this.device.id)
			},
			aggregatedEarliestTimestamp() {
				const rows = dataStore.fetchTelemetryCache(this.device.id)?.data;
				return Array.isArray(rows) && rows.length ? (rows[0]?.[0] ?? 0) : this.earliestTimestamp;
			},
			aggregatedLatestTimestamp() {
				const rows = dataStore.fetchTelemetryCache(this.device.id)?.data;
				return Array.isArray(rows) && rows.length ? (rows[rows.length - 1]?.[0] ?? 0) : this.latestTimestamp;
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
			globalExtentX() {
				return [this.startTimestamp, this.latestTimestamp];
			},
			sidebarFullView() {
				return state.sidebarFullView;
			},
			wideView() {
				return (this.frameWidth > 900);
			},
			timelineDate() {
				return state.timelineDate
			},
			hoverData() {

				if (!this.sensorData?.schema?.length || !this.sensorData?.data?.length) {
					return null;
				}

				const tsIndex = this.sensorData.schema.indexOf('ts');
				if (tsIndex < 0) {
					return null;
				}

				if ((this.hoverPosition < 0 || this.hoverPosition > this.chartWidth) && !this.timelineDate) {
					return null;	
				}
				if (this.hoverPosition < 0 && this.timelineDate) {
					if (this.timelineDate < this.earliestTimestamp || this.timelineDate > this.latestTimestamp + config.timelineHoverCutoff) {
						return [];
					}
				} 

				
				const xScale = d3.scaleTime().domain(this.globalExtentX).range([0, this.chartWidth]);
				let hovertime;

				if (state.timelineDate && this.hoverPosition < 0) {
					hovertime = state.timelineDate;
				} else {
					hovertime = xScale.invert(this.hoverPosition + this.scrollLeft).getTime();
					// state.timelineDate = hovertime;
				}

				// collect ts column once (coerce to milliseconds if needed)
				const tsArr = this.sensorData.data.map(row => {
					const v = row[tsIndex];
					return typeof v === 'number' ? v : new Date(v).getTime();
				});

				// binary search nearest timestamp
				let lo = 0, hi = tsArr.length - 1;
				while (lo < hi) {
					const mid = (lo + hi) >> 1;
					if (tsArr[mid] < hovertime) lo = mid + 1; else hi = mid;
				}
				// lo is first >= hovertime; choose closer of lo and lo-1
				let idx = lo;
				if (lo > 0 && Math.abs(tsArr[lo - 1] - hovertime) <= Math.abs(tsArr[lo] - hovertime)) idx = lo - 1;

				const closestTs = tsArr[idx];
				const gap = Math.abs(hovertime - closestTs);
				const isValidTime = !config.segmentation || gap <= config.dataGapLength;

				const result = {
					ts: closestTs,
					xpos: xScale(new Date(closestTs)) - this.scrollLeft - 1
				};

				if (this.hoverPosition >= 0) {
					state.timelineDate = closestTs;
				}

				const colIndex = Object.fromEntries(this.sensorData.schema.map((k, i) => [k, i]));

				for (const k of this.sensorData.schema) {
					if (k === 'ts') continue;
					const col = colIndex[k];
					result[k] = this.sensorData.data[idx][col];
				}

				return result;

			},
			debugAttributes() {
				return state.debugAttributes;
			}
		},
		methods: {
			async loadSensorData() {
				const requestId = ++this.sensorLoadRequestId;
				const deviceId = this.device?.id ?? null;
				const aggregation = state.dataAggregation;

				this.isVisible = true;
				this.$nextTick(() => {
					this.updateFrameWidth();
				});
				this.sensorDataLoading = true;
				this.dataPresent = false;
				await this.processSensorData({ requestId, deviceId, aggregation });
			},
			isCurrentSensorLoad({ requestId, deviceId, aggregation }) {
				return (
					requestId === this.sensorLoadRequestId &&
					deviceId === (this.device?.id ?? null) &&
					aggregation === state.dataAggregation
				);
			},
			async processSensorData(loadContext) { 
				if (!this.isCurrentSensorLoad(loadContext)) {
					return;
				}

				this.sensorData = this.device.telemetrySchema || { schema: [], data: [] };
				const cachedRows = dataStore.fetchTelemetryCache(loadContext.deviceId)?.data;
				if (Array.isArray(cachedRows) && cachedRows.length > 0) {
					this.earliestTimestamp = cachedRows[0]?.[0] ?? 0;
					this.latestTimestamp = cachedRows[cachedRows.length - 1]?.[0] ?? 0;
				} else {
					this.earliestTimestamp = 0;
					this.latestTimestamp = 0;
				}
				
				try {
					const telemetryData = await dataStore.fetchTelemetryData(loadContext.deviceId, 'all', loadContext.aggregation);
					if (!this.isCurrentSensorLoad(loadContext)) {
						return;
					}
		
					this.sensorData = telemetryData;
					const rows = telemetryData?.data;
					const hasRows = Array.isArray(rows) && rows.length > 0;
					this.dataPresent = hasRows;

					if (!hasRows) {
						return;
					}

					this.earliestTimestamp = rows[0]?.[0] ?? 0;
					this.latestTimestamp = rows[rows.length - 1]?.[0] ?? 0;

					if (this.chartTimeRange == 0 ) {
						this.chartTimeRange = -1
						this.graphScale = 1
						this.graphPosition = 0
					}
					if (this.chartTimeRange > 0) {
						this.selectTimeRange(this.chartTimeRange)
					}
				} finally {
					if (this.isCurrentSensorLoad(loadContext)) {
						this.sensorDataLoading = false;
					}
				}
			},
			filterSensors(sensorKeys) {
				const schema = Array.isArray(this.sensorData?.schema) ? this.sensorData.schema : [];
				if (!schema.length) return [];
				const present = new Set(schema);
				const filtered = sensorKeys
					.filter(k => k !== 'ts' && present.has(k))
					.map(k => ({ key: k, col: schema.indexOf(k) }))
					.sort((a, b) => a.key.localeCompare(b.key));
				return filtered;
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
				} else if (event.type === "touchmove" || event.type === "touchstart") {
					this.hoverPosition = event.touches[0].clientX; - rect.left;
				}
			},
			hoverOut() {
				this.hoverPosition = -1;
				state.timelineDate = null;
			},
			linktomap() {
				this.$router.push('/');
			}
		},
		watch: {
			devices() {
				this.loadSensorData();
			},
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

			// Use ResizeObserver to track frameRef size changes
			this.$nextTick(() => {
				if (this.$refs.frameRef) {
					this.resizeObserver = new ResizeObserver(() => {
						const frameRef = this.$refs.frameRef;
						if (frameRef) {
							this.frameWidth = frameRef.clientWidth - this.frameMargin;
						}
					});
					this.resizeObserver.observe(this.$refs.frameRef);
				}
			});
			},
			beforeUnmount() {
				this.sensorLoadRequestId++;
				window.removeEventListener('resize', this.updateFrameWidth);
				if (this.resizeObserver) {
					this.resizeObserver.disconnect();
			}
		},
	};



</script>

<template>

	
	<div class="location" :class="[ { wideView, sidebarFullView }, 'context-' + context ]">

	
	<div class="frameref" ref="frameRef"></div>

		<div v-if="wideView" class="headersplit">
			
			<div class="locationheader left">
				
				<h2>
					<span class="title">{{ device.attributes?.Anzeigename || device.name }}</span>
				</h2>
								
				<OberbodenUebersicht :device :hoverData />
				<SoilProperties :device />
				<Beschreibung :device :alwaysExpanded="true" />
				

			</div>
			
			<div class="right">

				<MiniMap :device v-if="device.attributes.latitude"/>	

			</div>
				
		</div>

		<div v-else class="locationheader">
			
			<h2>
				<span class="title">{{ device.attributes?.Anzeigename || device.name }}</span>
			</h2>
						
			<OberbodenUebersicht :device :hoverData />
			<SoilProperties :device />
			<Beschreibung :device />

		</div>
	
		<ChartSettings v-if="hasBodenfeuchteSensors" :graphScale :frameWidth :graphPosition :dataPresent @range-update="handleRangeUpdate" :earliestTimestamp="aggregatedEarliestTimestamp" :latestTimestamp="aggregatedLatestTimestamp" />

		<div class="scrollcontainer" @wheel="scrollWheel">
			
			<div class="hovercontainer" @mousemove="hover" @mouseleave="hoverOut" @touchstart="hoverOut" @touchmove="hover" @touchend="hoverOut" @touchcancel="hoverOut">
			
				<div v-if="hasBodenfeuchteSensors">
				<!-- <hr/> -->

				<div v-if="chartStyle === 'schichten'">

					<div class="graph">
						<ChartHeat 
						title="Bodenfeuchte"
						:sensors="bodenfeuchteSensors" 
						:sensorData
						:device
						:chartWidth 
						:frameWidth 
						:scrollLeft 
						:startTimestamp
						:latestTimestamp
						:numberOfDays
						:dataPresent
						:isLoading="sensorDataLoading"
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
						:sensorData
						:device
						:chartWidth 
						:frameWidth 
						:scrollLeft 
						:startTimestamp
						:latestTimestamp
						:numberOfDays
						:dataPresent
						:isLoading="sensorDataLoading"
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
							:sensorData
							:device
							:chartWidth 
							:frameWidth 
							:scrollLeft 
							:startTimestamp
							:numberOfDays
							:dataPresent
							:isLoading="sensorDataLoading"
							:hoverPosition
							:hoverData
							/>
							
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- <ChartRange 
		:dataPresent
		:graphScale
		:graphPosition
	/> -->

	<div v-if="context=='single'" class="linktomap" @click="linktomap">
		<div class="sensor">
			<img src="/img/sensor.png" >
		</div>
		<div class="text">
			Alle Standorte auf der Wasserkarte ansehen
		</div>
	</div>
	
	<div class="additional" v-if="context=='sidebar' && debugAttributes">
		<DebugInfo :device="device" :showTitle="false"></DebugInfo>
	</div>

</div>

</template>

<style lang="stylus" scoped>
	.location
		padding 10px 24px 0
		z-index 10
		position relative
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
		margin-bottom 32px
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
		height 340px
		.left
			// flex-basis calc(55% - 12px)
			flex-basis 600px
			flex-grow 0
			flex-shrink 0
			min-width: 0
			.beschreibung_container
				margin-top 12px
		.right
			// flex-basis calc(45% - 12px)
			flex-grow 1
			flex-shrink 0
			min-width: 0
	#mapcontainer
		width 100%
		margin 0 0 0
		height 340px
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
