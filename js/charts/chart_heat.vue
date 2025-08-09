<template>
	<div class="chartouterframe" :class="heatmap ? 'heatmap' : 'schichten'">
		<div class="chartheader">

			<h3>{{ title }}</h3>

			<div v-if="showDate && hoverData && hoverData.ts" class="latestdate">
				{{ displayutil.formatDateShort(hoverData.ts) }}
				<span class="time">{{ displayutil.formatDateTime(hoverData.ts) }}</span>
			</div>

			<div v-else-if="hoverData" class="latestdate">
				
			</div>

			<div v-else-if="showDate" class="latestdate">
				{{ displayutil.formatDateShort(latestTimestamp) }}
				<span class="time">{{ displayutil.formatDateTime(latestTimestamp) }}</span>
			</div>

		</div>


		<div class="scrollview chart-heat" :class="heatmap ? 'heatmap' : 'schichten'">
				
			<div class="scrollframe" :style="{ width: frameWidth + 'px', height: (rowHeight + rowMargin) * sensors.length + 'px'}">
				<div class="scrollinner" :style="{ width: chartWidth + 'px', marginLeft: -scrollLeft + 'px' }" v-if="dataPresent">
					<div
						v-for="(sensor, i) in sensors"
						:key="'chart-container-'+i"
						class="chartcontainer"
						:style="{ height: (rowHeight + rowMargin) + 'px', width: chartWidth + 'px'}"
						>

						<svg :width="chartWidth" :height="rowHeight" style="position:absolute;z-index:1">
							<defs>
								<pattern 
									v-for="(img, i) in heatmapImages"
									:key="'pattern'+i"
									:id="`heatmapPattern${componentId}-${i}`"
									patternUnits="userSpaceOnUse"
									:width="chartWidth" 
									:height="rowHeight"
									preserveAspectRatio="none"
									>
									<image 
										:href="img" 
										:width="chartWidth" 
										:height="rowHeight"
										preserveAspectRatio="none"
									/>
									</pattern>
							</defs>
						</svg>

						<svg
							:width="chartWidth"
							:height="rowHeight"
							class="sensor-chart-svg"
						>
							<path
								v-if="sensor.data && sensor.data.length"
								class="line-path"
								fill="none"
								stroke-width="2"
								:ref="el => linePathRefs[i] = el"
							/>
						
						</svg>
					
					</div>
					
				</div>

				<div class="hoverline" v-show="hoverData?.xpos" :style="{ left: (hoverData?.xpos ) + 'px' }"></div>
				
				<div class="scrolloverlay" v-if="dataPresent">
					
					<div
						class="sensorlabel"
						v-for="(sensor, i) in sensors"
						:key="'label-'+i"
						:style="{ height: ( rowHeight + rowMargin) + 'px'}">

						<div v-if="!heatmap && hoverData?.xpos"
							:style="{
								left: (hoverData?.xpos + 0.5) + 'px',
								top: getYPosition(hoverData[sensor.key].value) + 'px'
							}"
							class="hover-dot"></div>

						
						<div class="labelinner">

							<div class="depth">{{ getDisplayDepth(sensor.key) }}</div>

							<template v-if="title=='Bodenfeuchte'">
								
								<div class="bodenfeuchtedata" v-if="validData(sensor.key)">
	
									<div class="bodenfeuchteName">
									<span class="name">{{ getNFKName(sensor.key) }}</span>
									</div>
									<div class="bodenfeuchteNFK" v-if="hasSoilAttributes">
										<span class="value">{{ getNFKValue(sensor.key) }}</span>
										<span class="unit"><span class="unittype">nFK</span>%</span>
									</div>
									<div class="bodenfeuchteVol">
										<span class="value">{{formatNumber(getData(sensor.key))}}</span>
										<span class="unit"><span class="unittype">Vol</span>%</span>
									</div>
	
								</div>

								<div v-else class="data"><div class="value empty">-</div></div>
								
							</template>
							
							<template v-else>

								<div class="data" v-if="validData(sensor.key)">
	
									<span class="value">{{formatNumber(getData(sensor.key))}}</span>
									<span class="unit">{{ getDisplayUnit(sensor.key) }}</span>
								
								</div>

								<div v-else class="data"><div class="value empty">-</div></div>

								
							</template>

						</div>
						
					</div>
		
				</div>

			</div>

			<div class="loading" v-if="loading && title == 'Bodenfeuchte'"></div>

		</div>
		<ChartTime 
		:chart-width="chartWidth" 
		:frame-width="frameWidth" 
		:scroll-left="scrollLeft" 
		:start-timestamp="startTimestamp"
		:number-of-days="numberOfDays"
		:data-present="dataPresent"
		:hover-position="hoverPosition"
		></ChartTime>

	</div>
</template>

<script>
import { onMounted, onBeforeUnmount, ref, watch, computed, nextTick } from 'vue'
import ChartTime from './chart_timeaxis.vue'
import * as d3 from 'd3'
import { displayutil } from '@/displayutil.js'
import { dataModel } from '@/datamodel.js'
import { config } from '@/config.js'
import { state } from '@/state.js'

export default {
	name: 'ChartHeat',
	components: {
			ChartTime,
		},
	data() {
		return {
			componentId: this.generateUUID(),
			linePathRefs: [],
			heatmapImages: [],
			lastData: [],
			filteredSensors: [],
			displayutil,
			loading: true,
		}
	},
	props: {
		title: {
			type: String,
			default: ''
		},
		sensors: {
			type: Array,
			default: () => [],
		},
		device: {
			required: true,
			type: Object,
		},
		chartWidth: {
			type: Number,
			required: true
		},
		frameWidth: {
			type: Number,
			required: true
		},
		scrollLeft: {
			type: Number,
			required: true
		},
		hoverPosition: {
			type: Number,
			required: false
		},
		numberOfDays: {
			type: Number,
			required: true
		},
		startTimestamp: {
			type: Number,
			required: true
		},
		latestTimestamp: {
			type: Number,
			required: false,
			default: 0
		},
		baseline: {
			type: Number,
			required: false
		},
		ceiling: {
			type: Number,
			required: false
		},
		offsetTop: {
			type: Number,
			required: false,
			default: 5
		},
		offsetBottom: {
			type: Number,
			required: false,
			default: 0
		},
		heatmap: {
			type: Boolean,
			required: false,
			default: false
		},
		showDate: {
			type: Boolean,
			required: false,
			default: true
		},
		hoverData: {
			type: Object,
			required: false
		},
	},
	computed: {
		globalExtentY() {
			const allValues = this.filteredSensors.flatMap(s => s.data?.map(d => d.value) || [])
			if (!allValues.length) return [0, 100]

			let yMin = Math.min(...allValues)
			let yMax = Math.max(...allValues)
			if (this.baseline !== undefined) {
				yMin = this.baseline;
			}
			if (this.ceiling) {
				yMax = this.ceiling;
			}

			return [yMin, yMax]
		},
		dataTimeRange() {
			const allDataPoints = this.sensors.flatMap(sensor => sensor.data || []);
			if (!allDataPoints.length) return null;
			
			return {
				earliest: Math.min(...allDataPoints.map(d => new Date(d.ts).getTime())),
				latest: Math.max(...allDataPoints.map(d => new Date(d.ts).getTime()))
			};
		},
		globalExtentX() {
			const startDate = new Date(this.startTimestamp);
			const endDate = new Date(this.startTimestamp + (this.numberOfDays * 24 * 60 * 60 * 1000));
			
			return [startDate, endDate];
		},
		totalHeight() {
			return this.sensors.length * (this.rowHeight+this.rowMargin) + this.xAxisHeight
		},
		dataPresent() {
			return this.sensors.some(sensor => sensor.data?.length && sensor.data.length > 1);
		},
		rowMargin() {
			return this.heatmap ? 0 : 20;
		},
		rowHeight() {
			return 115 - this.rowMargin
			// return Math.max((this.frameWidth / 10), 100) - this.rowMargin;
		}, 
		hasSoilAttributes() {
			return (this.device.attributes.totwasserbereich && this.device.attributes.feldkapazität)
		},
		colorScheme() {
			return state.colorScheme;
		},
		filterFaultyValues() {
			return state.filterFaultyValues;
		},


	},
	methods: {
		drawCharts() {
			if (!this.filteredSensors.length || this.chartWidth <= 0 || this.frameWidth <= 0) {
				return;
			}

			let [yMin, yMax] = this.globalExtentY;
			const yScale = d3.scaleLinear().domain([yMin, yMax]).range([this.rowHeight-this.offsetBottom, this.offsetTop]);
			const [xStart, xEnd] = this.globalExtentX;

			const xScale = d3.scaleTime()
				.domain([xStart, xEnd])
				.range([0, this.chartWidth]);

			this.filteredSensors.forEach((sensor, i) => {
				if (!this.linePathRefs[i]) {
					return;
				}
				const parentNode = this.linePathRefs[i].parentNode;
				if (!parentNode) {
					return;
				}
				
				d3.select(parentNode)
					.selectAll('.top-line')
					.remove();
				d3.select(parentNode)
					.selectAll('.heat')
					.remove();

				this.heatmapImages[i] = null;

				if (!sensor.data?.length || sensor.data.length <= 1) {
					return;
				}


				// HEATMAP CANVAS
				const MAX_CANVAS_WIDTH = 30000;
				const CANVAS_HEIGHT = 1; // Minimum height for efficiency
				
				const canvas = document.createElement('canvas');
				canvas.width = Math.min(this.chartWidth, MAX_CANVAS_WIDTH);
				canvas.height = CANVAS_HEIGHT;
				const ctx = canvas.getContext('2d');
				ctx.clearRect(0, 0, canvas.width, canvas.height);

				if (sensor.data?.length) {
					const canvasScale = canvas.width / this.chartWidth;
					const GAP_COLOR = '#fff'; // TEMP: use light gray to see gaps

					for (let j = 0; j < sensor.data.length - 1; j++) {
						const currentPoint = sensor.data[j];
						const nextPoint = sensor.data[j + 1];

						const timeGap = new Date(nextPoint.ts) - new Date(currentPoint.ts);
						const xStart = Math.floor(xScale(new Date(currentPoint.ts)) * canvasScale);
						const xEnd = Math.floor(xScale(new Date(nextPoint.ts)) * canvasScale);
						const width = Math.max(1, Math.ceil(xEnd - xStart));

						let color;
						if (config.segmentation && timeGap > config.dataGapLength) {
							color = GAP_COLOR;
						} else if (this.title === 'Bodenfeuchte') {
							color = dataModel.get_vol_color(this.device, currentPoint.value);
						} else if (this.title === 'Bodentemperatur') {
							color = dataModel.get_temperature_color(this.device, currentPoint.value);
						} else {
							color = '#000'; // Fallback
						}

						ctx.fillStyle = color;
						ctx.fillRect(xStart, 0, width, CANVAS_HEIGHT);
					}
				}
				
				this.heatmapImages[i] = canvas.toDataURL();
				
			
				// CHART
				const areaPathEl = this.linePathRefs[i];
				if (!areaPathEl) return;

				if (sensor.data && sensor.data.length) {

					if (this.heatmap) {
						// Show full heatmap with rectangle
						d3.select(parentNode)
							.append('rect')
							.attr('x', 0)
							.attr('y', 0)
							.attr('class', 'heat')
							.attr('width', this.chartWidth)
							.attr('height', this.rowHeight)
							.style('fill', `url(#heatmapPattern${this.componentId}-${i})`);
							
					} else {

						const areaGen = d3.area()
							.x(d => xScale(new Date(d.ts)))
							.y0(this.rowHeight)
							.y1(d => yScale(d.value));

						const lineGen = d3.line()
							.x(d => xScale(new Date(d.ts)))
							.y(d => yScale(d.value));


						
						// const splitSegments = [];
						// let segment = [];

						// for (let j = 0; j < sensor.data.length; j++) {
						// 	const point = sensor.data[j];
						// 	if (segment.length > 0) {
						// 		const prev = segment[segment.length - 1];
						// 		if (new Date(point.ts) - new Date(prev.ts) > config.dataGapLength) {
						// 			splitSegments.push(segment);
						// 			segment = [];
						// 		}
						// 	}
						// 	segment.push(point);
						// }

						// if (segment.length) {
						// 	splitSegments.push(segment);
						// }

						let segments;
						if (config.segmentation) {
							segments = this.splitByGaps(sensor.data, config.dataGapLength);
						} else {
							segments = [sensor.data];
						}

						// Clear previous paths in case they exist
						d3.select(parentNode)
							.selectAll('.gap-aware-line, .gap-aware-area')
							.remove();

						// Draw each segment as its own line and area
						segments.forEach(seg => {
							if (seg.length < 2) return; // skip short segments

							d3.select(parentNode)
								.append('path')
								.datum(seg)
								.attr('class', 'gap-aware-area')
								.style('fill', `url(#heatmapPattern${this.componentId}-${i})`)
								.style('opacity', 1)
								.attr('d', areaGen);

							d3.select(parentNode)
								.append('path')
								.datum(seg)
								.attr('class', 'gap-aware-line')
								.attr('fill', 'none')
								.attr('stroke', '#000000')
								.attr('stroke-width', 1)
								.style('opacity', 0.2)
								.attr('d', lineGen);
						});
					}
				}
			});
		},
		splitByGaps(data, maxGap) {
			const segments = [];
			let segment = [];

			for (let i = 0; i < data.length; i++) {
				if (i === 0) {
					segment.push(data[i]);
				} else {
					const gap = data[i].ts - data[i - 1].ts;
					if (gap > maxGap) {
						segments.push(segment);
						segment = [];
					}
					segment.push(data[i]);
				}
			}

			if (segment.length > 0) {
				segments.push(segment);
			}

			return segments;
		},
		drawChartsNextTick() {
			console.log('drawChartsNextTick')
			nextTick(() => {
				this.drawCharts();
			});
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
		updateLastData() {
			this.lastData = this.sensors.map(sensor => {
				if (!sensor.data?.length) return null;
				return sensor.data[sensor.data.length - 1];
			});
		},
		filterSensors() {
			this.minValue = null;
			this.maxValue = null;
			if (state.filterFaultyValues) {
				this.minValue = config.minMaxValues[this.title].min;
				this.maxValue = config.minMaxValues[this.title].max;
			}
			
			this.filteredSensors = this.sensors.map(sensor => {
				if (!sensor.data?.length) return sensor;
				
				return {
					...sensor,
					data: sensor.data.filter(d => {
						if (this.minValue !== null && d.value < this.minValue) return false;
						if (this.maxValue !== null && d.value > this.maxValue) return false;
						return true;
					})
				};
			});
		},
		getData(key) {
			if (this.hoverData && this.hoverData[key]) {
				if (this.hoverData[key].value != '-') {
					return this.hoverData[key].value?.toFixed(1);
				} else {
					return this.hoverData[key].value;
				}
			} else {
				return this.getLastSensorData(key)?.value?.toFixed(1) ?? '-'
			}
		},
		validData(key) {
			return (this.getData(key) != '-')
		},
		validHoverData(key) {
			return this.hoverData && this.hoverData[key] && this.hoverData[key].valid
		},
		getLastSensorData(key) {
			return this.sensors.find(sensor => sensor.key === key)?.data[this.sensors.find(sensor => sensor.key === key)?.data.length - 1] || [];
		},
		getSoilMoistureLevelName(key) {
			return displayutil.getSoilMoistureLevelName(this.device.attributes.soilType, this.getData(key));
		},
		getSoilMoistureLevelNFK(key) {
			return displayutil.getSoilMoistureLevelNFK(this.device.attributes.soilType, this.getData(key));
		},
		getNFKValue(key) {
			return dataModel.vol_to_nfk(this.device, this.getData(key)).toFixed(0);
		},
		getNFKName(key) {
			return dataModel.get_vol_nfk_label(this.device, this.getData(key));
		},
		getYPosition(value) {
			const [yMin, yMax] = this.globalExtentY;
			return d3.scaleLinear()
				.domain([yMin, yMax])
				.range([this.rowHeight-this.offsetBottom, this.offsetTop])(value);
		},
		generateUUID() {
			return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
				const r = Math.random() * 16 | 0;
				const v = c === 'x' ? r : (r & 0x3 | 0x8);
				return v.toString(16);
			});
		},
		formatNumber(floatString) {
			return parseFloat(floatString).toFixed(1).replace('.', ',');
		},

	},
	watch: {
		sensors: {
			handler(newSensors) {
				
				this.filterSensors();
				this.updateLastData();
				nextTick(() => {
					this.drawCharts();
				});

				if (!this.dataPresent) {
					window.setTimeout(() => {
						if (!this.dataPresent) {
							this.loading = true;
						}
					}, 200);
				} else {
					this.loading = false;
				}
			},
			deep: true
		},
		chartWidth: {
			handler() {
				nextTick(() => {
					this.drawCharts();
				});
			},
			immediate: true
		},
		numberOfDays: {
			handler() {
				nextTick(() => {
					this.drawCharts();
				});
			},
			immediate: true
		},
		colorScheme() {
			this.drawCharts();
		},
		dataPresent: {
			handler() {
				this.loading = false;
			},
			immediate: true
		},
		filterFaultyValues() {
			this.filterSensors();
			this.drawCharts();
		}
	},
	mounted() {
		window.addEventListener('sidebar:toggleFullWindow', this.drawCharts);
		window.addEventListener('resize', this.drawCharts);
		window.addEventListener('chartstyleselected', this.drawCharts);
		this.updateLastData();
		this.filterSensors();
		nextTick(() => {
			this.drawCharts();
		});		
	},
	beforeUnmount() {
		window.removeEventListener('resize', this.drawCharts)
		window.removeEventListener('sidebar:toggleFullWindow', this.drawCharts)
		window.removeEventListener('chartstyleselected',this.drawCharts)
	},

}
</script>

<style lang="stylus" scoped>

.scrollview
	margin 0
	position relative
	filter var(--chartdropshadowfilter)

.scrollframe
	position: relative;
	overflow: hidden;
	// background var(--uibrighter)
	// background #fff
	border 1px solid transparent
	box-sizing content-box

.heatmap .scrollframe
	border-radius var(--chartborderradius)
	border var(--chartborderstyle)

.scrollinner
	height: 100%;
	position: relative;
	// background linear-gradient(to bottom, transparent, #fff)


.scrolloverlay
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;


.sensorlabel
	width 100%
	position relative

.heatmap .sensorlabel
	border-bottom 1px solid #00000022
.heatmap .sensorlabel:last-of-type
	border-bottom 1px solid transparent
.schichten .sensorlabel
	border-bottom 1px solid transparent
.heatmap .labelinner
	border-top 1px solid transparent
.schichten .labelinner
	border-top 1px solid #00000022
	background linear-gradient(to bottom, #00000008 0%, #00000000 25%, transparent 50%)
.labelinner
	position absolute		
	left 0
	right 0
	bottom 0
	margin 0
	display flex
	flex-direction row
	justify-content space-between
	align-items baseline
	padding 0 4px
	height 20px
	margin-bottom 0
	> *
		vertical-align baseline
	.depth
		font-size 8pt
		margin-right 1em
		position relative
	.value
		font-weight bold
		font-size 10.5pt
	.value.empty
		opacity 0
	.unit
		font-weight normal
		margin-left .2em
		font-size 9.5pt
		.unittype
			margin-right .25em
			font-size 85%
	.data
		display flex
		flex-direction row
		flex-basis 48px
		align-items baseline
		justify-content flex-end
		display flex
		justify-content flex-end
		flex-direction row
	.bodenfeuchtedata
		display flex
		flex-direction row
		flex-grow 1
		align-items baseline
		justify-content center
		white-space nowrap
		.bodenfeuchteName
			flex 2
			text-align left
			text-align right
			display block
			text-overflow ellipsis
			overflow hidden
			font-size 8pt
			opacity .7
		.bodenfeuchteNFK
			padding-right 4px
			margin-left 8px
			flex-basis 55px
			text-align right
			font-weight normal
			opacity .7
			font-size 8pt
			.value
				font-weight normal
				font-size 8pt
		.bodenfeuchteVol
			text-align right
			flex-basis 68px
			.unit
				opacity .7

.hover-value
	display inline-block
	margin-left 8px

.date
	position absolute
	top 1em
	right 0
	text-align center
	padding .2em .3em .1em
	font-size 7pt
	opacity .8
	display none

.hover-dot
	width 6px
	height 6px
	border-radius 50%
	transform translate(-50%, -50%)
	background #00000066
	position absolute

.schichten .hoverline
	bottom 20px
	top -20px


</style>