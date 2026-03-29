<template>
	<div class="chartouterframe">
		<div class="chartheader">
			<h3>{{ title }}</h3>

		</div>

		<div class="scrollview chart-graph" @mouseenter="mouseOverChart = true" @mouseleave="mouseOverChart = false">
			<div class="hovertriangle" v-show="hoverData?.xpos" :style="{ left: (hoverData?.xpos ) + 'px' }"></div>
			<div class="hovertrianglebottom" v-show="hoverData?.xpos" :style="{ left: (hoverData?.xpos ) + 'px' }"></div>

			<div class="scrollframe" :style="{ width: frameWidth + 'px', height: rowHeight + 'px'}">

				<div class="scrollinner" :style="{ width: chartWidth + 'px', marginLeft: -scrollLeft + 'px' }"
					v-if="dataPresent">

					<!-- Add canvas element at the top -->
					<canvas ref="heatmapCanvas" :style="{
						position: 'absolute',
						top: 0,
						left: 0,
						width: '100%',
						height: '100%',
						zIndex: 0,
						opacity: this.backgroundOpacity
					}" :width="1" :height="rowHeight"></canvas>

					<div v-for="(sensor, i) in sensors" :key="'chart-container-'+i" class="chartcontainer"
						:style="{ height: rowHeight + 'px', width: chartWidth + 'px'}">

						<svg
							:width="chartWidth"
							:height="rowHeight"
							class="sensor-chart-svg"
							:ref="el => linePathRefs[i] = el"></svg>
					</div>

				</div>
				<!-- <div class="dotcontainer" :style="{ height: rowHeight + 'px', width: frameWidth + 'px'}">

					<svg :width="chartWidth" :height="rowHeight" class="dot-svg">
						<template v-for="(sensor, i) in sensors">
							<circle v-if="hoverData?.xpos && !timelineDate" :cx="(hoverData?.xpos + .5)"
								:cy="getYPosition(hoverData[sensor.key])" r="3" class="hover-dot"
								:style="{ fill: getDepthColor(getDepthValue(sensor.key))}" />
						</template>
					</svg>

				</div> -->
				<div class="hoverline" v-show="hoverData?.xpos" :style="{ left: (hoverData?.xpos ) + 'px' }"></div>

				<div class="scrolloverlay" v-if="dataPresent">
					<div class="date" v-if="hoverData?.ts">
						{{ hoverData?.ts ? new Date(hoverData.ts).toLocaleString(undefined, { 
						year: 'numeric',
						month: 'numeric',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit',
						hour12: false
					}).replace(',', '') : '' }}
					</div>

				</div>

			</div>

			<!-- y-axis -->
			<div class="y-axis" :style="{ height: rowHeight + 'px' }">
				<div v-for="tick in yAxisTicks" :key="tick.value" class="y-axis-tick" :style="{ 
					top: tick.position + 'px',
					transform: 'translateY(-50%)'
				}">
					{{ tick.value }}
				</div>
			</div>

			<GraphToolTip v-if="hoverData && hoverData.ts" :mouseOverChart :sensors="sensors" :device :hoverData :hoverPosition />

			<div class="loading" v-if="loading && title == 'Bodenfeuchte'"></div>

		</div>
		<DateAxis :chart-width="chartWidth" :frame-width="frameWidth" :scroll-left="scrollLeft"
			:start-timestamp="startTimestamp" :number-of-days="numberOfDays" :data-present="dataPresent"
			:hover-position="hoverPosition"></DateAxis>

		<div class="depths" >
			<div class="sensor"
				v-for="(sensor, i) in sensors"
				:key="'label-'+i"
				>
				<div class="graphcolor" :style="{ backgroundColor: getDepthColor(getDepthValue(sensor.key)) }"></div>
				<div class="depth">{{ getDisplayDepth(sensor.key) }}</div>
			</div>
		</div>

	</div>



</template>

<script>
import { onMounted, onBeforeUnmount, ref, watch, computed, nextTick } from 'vue'
import GraphToolTip from './graph_tooltip.vue'
import DateAxis from './dateaxis.vue'
import * as d3 from 'd3'
import { displayutil } from '../displayutil.js'
import { config } from '../config.js'
import { dataModel } from '@/datamodel.js'
import { state } from '@/state.js'

export default {
	name: 'ChartGraph',
	components: {
		DateAxis,
		GraphToolTip,
	},
	data() {
		return {
			strokeWidth: 1.5,
			backgroundOpacity: .4,
			rowMargin: 0,
			linePathRefs: [],
			heatmapImages: [],
			lastData: [],
			displayutil,
			filteredSensors: [],
			mouseOverChart: false,
			loading: true,
		}
	},
	props: {
		title: { type: String, default: '' },
		sensors: { type: Array, default: () => [] },
		sensorData: { type: Object, required: true }, 
		device: { required: true, type: Object },
		chartWidth: { type: Number, required: true },
		frameWidth: { type: Number, required: true },
		scrollLeft: { type: Number, required: true },
		hoverPosition: { type: Number, required: false },
		numberOfDays: { type: Number, required: true },
		startTimestamp: { type: Number, required: true },
		latestTimestamp: { type: Number, required: false, default: 0 },
		baseline: { type: Number, required: false },
		ceiling: { type: Number, required: false },
		offsetTop: { type: Number, required: false, default: 10 },
		offsetBottom: { type: Number, required: false, default: 10 },
		coloring: { type: String, required: false, default: 'bodenfeuchteflat' },
		hoverData: { type: Object, required: false },
		dataPresent: { type: Boolean, required: false },
	},
	computed: {
		globalExtentY() {
			const tele = this.sensorData;
			if (!tele?.schema?.length || !tele?.data?.length) return [0, 100];

			let min = +Infinity, max = -Infinity;
			for (const s of this.sensors) {
				const col = s.col;
				for (let r = 0; r < tele.data.length; r++) {
				const y = tele.data[r][col];
				if (!Number.isFinite(y)) continue;
				if (y < min) min = y;
				if (y > max) max = y;
				}
			}
			if (min === +Infinity) [min, max] = [0, 100];
			if (this.baseline !== undefined) min = this.baseline;
			if (this.ceiling  !== undefined) max = this.ceiling;
			return [min, max];
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
		yAxisTicks() {
			const [yMin, yMax] = this.globalExtentY;
			const numberOfTicks = 5;

			// Use rounded inputs for "nice" tick spacing, but clamp output to actual extent
			const rawTicks = d3.ticks(Math.round(yMin), Math.round(yMax), numberOfTicks);

			// Filter: must be integer, and within the exact data extent
			const tickValues = rawTicks.filter(v => 
				Number.isInteger(v) && v >= yMin && v <= yMax
			);

			return tickValues.map(value => ({
				value,
				position: this.getYPosition(value)
			}));
		},
		rowHeight() {
			if (state.sidebarFullView) {
				return 500
			}

			return 460
		}, 
		colorScheme() {
			return state.colorScheme;
		},
		daysSinceLastTelemetry() {
			const latestTimestamp = this.getLastTimestamp();
			if (latestTimestamp) {
				const hours = (Date.now() - latestTimestamp) / (1000 * 60 * 60);
				const days = Math.floor(hours / 24);
				return days;
			}
		},
		timelineDate() {
			return state.timelineDate;
		},
		showDataGaps() {
			return state.showDataGaps
		}

	},
	methods: {
		
	
		drawCharts() {
			this.drawHeatmap();

			const tele = this.sensorData;
			if (!this.sensors.length || this.chartWidth <= 0 || this.frameWidth <= 0) return;
			if (!tele?.schema?.length || !tele?.data?.length) return;

			const idxTs = tele.schema.indexOf('ts');
			if (idxTs < 0) return;

			const [yMin, yMax] = this.globalExtentY;
			const yScale = d3.scaleLinear()
				.domain([yMin, yMax])
				.range([this.rowHeight - this.offsetBottom, this.offsetTop]);

			const [xStart, xEnd] = this.globalExtentX;
			const xScale = d3.scaleTime()
				.domain([xStart, xEnd])
				.range([0, this.chartWidth]);

			// Pre-split by gaps once (shared)
			const segments = this.splitByGapsRows(
				tele.data, idxTs, 
				this.showDataGaps ? config.dataGapLength : Infinity
			);

			this.sensors.forEach((sensor, i) => {
				const svg = this.linePathRefs[i];
				if (!svg) return;

				const sel = d3.select(svg);
				sel.selectAll('.top-line,.area').remove();

				const depth = this.getDepthValue(sensor.key);
				const lineColor = this.getDepthColor(depth);

				const lineGen = d3.line()
				.x(row => xScale(new Date(row[idxTs])))
				.y(row => yScale(row[sensor.col]))
				.defined(row => Number.isFinite(row[sensor.col]));

				const areaGen = d3.area()
				.x(row => xScale(new Date(row[idxTs])))
				.y0(this.rowHeight)
				.y1(row => yScale(row[sensor.col]))
				.defined(row => Number.isFinite(row[sensor.col]));

				segments.forEach(seg => {
				const validPoints = seg.filter(row => Number.isFinite(row[sensor.col]));

				if (validPoints.length === 1) {
					const row = validPoints[0];
					sel.append('circle')
						.attr('class', 'top-line')
						.attr('cx', xScale(new Date(row[idxTs])))
						.attr('cy', yScale(row[sensor.col]))
						.attr('r', this.strokeWidth)
						.attr('fill', lineColor)
						.style('opacity', 1);
					return;
				}

				sel.append('path')
					.datum(seg)
					.attr('class', 'area')
					.attr('fill', 'none')
					.attr('d', areaGen);

				sel.append('path')
					.datum(seg)
					.attr('class', 'top-line')
					.attr('fill', 'none')
					.attr('stroke', lineColor)
					.attr('stroke-width', this.strokeWidth)
					.style('opacity', 1)
					.attr('d', lineGen);
				});
			});
		},
		drawChartsNextTick() {
			nextTick(() => {
				this.drawCharts();
			});
		},
		getDepthValue(key) {
			return displayutil.depthValue(key)
		},
		getDepthColor(depth) {
			const match = config.graphColors.find(dc => dc.depth === depth);
			return match ? match.color : '#000000';
		},
		getDisplayDepth(key) {
			return displayutil.depth(key)
		},
		getLastTimestamp() {
			return this.sensorData.data[this.sensorData.data.length - 1][0];
		},
		getYPosition(value) {
			const [yMin, yMax] = this.globalExtentY;
			return d3.scaleLinear()
				.domain([yMin, yMax])
				.range([this.rowHeight-this.offsetBottom, this.offsetTop])(value);
		},
		validHoverData(key) {
			return this.hoverData && this.hoverData[key] && this.hoverData[key].valid
		},
		drawHeatmap() {
			const canvas = this.$refs.heatmapCanvas;
			if (!canvas) return;

			const ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);

			const [yMin, yMax] = this.globalExtentY;
			
			for (let y = 0; y < this.rowHeight; y++) {

				const value = d3.scaleLinear()
					.domain([this.rowHeight - this.offsetBottom, this.offsetTop])
					.range([yMin, yMax])(y);

				let color;
				if (this.title === 'Bodenfeuchte') {
					color = dataModel.get_vol_color_flat(this.device, value);
				} else if (this.title === 'Bodentemperatur') {
					color = dataModel.get_temperature_color_flat(this.device, value);
				}
				ctx.fillStyle = color;
				ctx.fillRect(0, y, 1, 1);
			}
		},
		splitByGapsRows(rows, idxTs, maxGap) {
			if (!rows?.length || !Number.isFinite(maxGap)) return [rows || []];
			const segs = [];
			let seg = [rows[0]];
			for (let i = 1; i < rows.length; i++) {
				const prev = rows[i - 1][idxTs];
				const cur  = rows[i][idxTs];
				if ((cur - prev) > maxGap) {
				segs.push(seg);
				seg = [];
				}
				seg.push(rows[i]);
			}
			if (seg.length) segs.push(seg);
			return segs;
		},
	},
	watch: {
		sensorData: {
			handler() {
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
			immediate: true
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
		showDataGaps() {
			nextTick(() => {
				this.drawCharts();
			});
		}
		// filterFaultyValues() {
		// 	// this.filterSensors();
		// 	this.drawCharts();
		// }
	},
	mounted() {
		window.addEventListener('sidebar:toggleFullWindow', this.drawCharts);
		window.addEventListener('resize', this.drawCharts);
		window.addEventListener('chartstyleselected', this.drawCharts);
		// this.updateLastData();
		// this.filterSensors();
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

.dotcontainer
.chartcontainer
	position absolute
	top 0
	left 0
	width 100%
	height 100%

.scrollframe
	filter var(--chartdropshadowfilter)
	border-radius var(--chartborderradius)
	position: relative;
	overflow: hidden;
	background var(--uibrighter)
	border var(--chartborderstyle)
	box-sizing content-box

.scrollinner
	height: 100%;
	position: relative;
	background #fff

.scrolloverlay
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;

.sensorlabel
	right 0
	bottom 0
	position absolute
	display flex
	flex-direction row
	justify-content flex-end
	align-items baseline

.depths
	display inline-flex
	flex-direction column
	margin 0 2px -.5em
	.sensor
		display flex
		flex-direction row
		align-items baseline
		justify-content flex-start
		flex-basis auto
		flex-grow 0
		flex-shrink 0
		margin 0
		.graphcolor
			width 18px
			height 2px
			align-self center
			margin-right 3px
		.depth
			font-weight bold
			font-weight normal
			opacity .7
			font-size 8pt
			margin-right 12px
		.value
			font-size 10pt
		.unit
			font-weight normal
			display inline-block
			margin-left .15em
			font-size 9.5pt
			opacity .8
		.data
			display inline-block
			margin-left 8px

.date
	position absolute
	top 0
	right 0
	padding .3em .6em
	font-size 8pt
	opacity .8
	display none

.y-axis
	position absolute
	right -24px
	width 24px
	top 0
	pointer-events none
	z-index 1
	
.y-axis-tick
	position absolute
	text-align left
	left 7px
	font-size 9px
	color #000000cc

@media (max-width: 600px)
	.y-axis-tick
		left -12px


.hover-dot
	stroke-width 0
	pointer-events none




</style>

<style lang="stylus">

@media (min-width: 601px) 
	.sidebar .depths
		position absolute
		right -56px
		bottom 8px
		margin-bottom 0
		.sensor
			flex-direction column
			.graphcolor
				width 24px
				margin-right 0
			.depth
				margin-right 0
				margin-bottom 8px
				font-size 9px


</style>