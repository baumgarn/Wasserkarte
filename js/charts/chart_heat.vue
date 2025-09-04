<template>
	<div class="chartouterframe" :class="heatmap ? 'heatmap' : 'schichten'">
		<div class="chartheader">

			<h3>{{ title }}</h3>

			<div v-if="showDate && hoverData && hoverData.ts && dataAggregation != '1d'" class="latestdate">
				{{ displayutil.formatDateShort(hoverData.ts) }}
				<span class="time">{{ displayutil.formatDateTime(hoverData.ts) }}</span>
			</div>

			<div v-if="showDate && hoverData && hoverData.ts && hoverData.ts != getLastTimestamp() && dataAggregation == '1d'" class="latestdate">
				{{ displayutil.formatDateAggregated(hoverData.ts) }}
			</div>
			
			<div v-else-if="hoverData && !hoverData.ts" class="latestdate">
				
			</div>

			<div v-else-if="showDate" class="latestdate">
				{{ displayutil.formatDateShort(getLastTimestamp()) }}
				<span class="time">{{ displayutil.formatDateTime(getLastTimestamp()) }}</span>
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
							:ref="el => linePathRefs[i] = el">
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
		title: { type: String, default: '' },
		sensors: { type: Array, default: () => [], },
		sensorData: { type: Object, required: true },
		device: { required: true, type: Object, },
		chartWidth: { type: Number, required: true },
		frameWidth: { type: Number, required: true },
		scrollLeft: { type: Number, required: true },
		hoverPosition: { type: Number, required: false },
		numberOfDays: { type: Number, required: true },
		startTimestamp: { type: Number, required: true },
		latestTimestamp: { type: Number, required: false, default: 0 },
		baseline: { type: Number, required: false },
		ceiling: { type: Number, required: false },
		offsetTop: { type: Number, required: false, default: 5 },
		offsetBottom: { type: Number, required: false, default: 0 },
		heatmap: { type: Boolean, required: false, default: false },
		showDate: { type: Boolean, required: false, default: true },
		dataPresent: { type: Boolean, required: false },
		hoverData: { type: Object, required: false },
	},
	computed: {
		globalExtentY() {
			const tele = this.sensorData;
			if (!tele?.schema?.length || !tele?.data?.length) return [0, 100];

			const idxTs = tele.schema.indexOf('ts');
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
			if (this.ceiling !== undefined) max = this.ceiling;
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
		rowMargin() {
			return this.heatmap ? 0 : 20;
		},
		rowHeight() {
			return 115 - this.rowMargin
		}, 
		hasSoilAttributes() {
			return (this.device.attributes.totwasserbereich && this.device.attributes.feldkapazität)
		},
		dataAggregation() {
			return state.dataAggregation;
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
			const tele = this.sensorData;
			if (!this.sensors.length || this.chartWidth <= 0 || !tele?.schema?.length || !tele?.data?.length) return;

			const idxTs = tele.schema.indexOf('ts');
			const [yMin, yMax] = this.globalExtentY;
			const yScale = d3.scaleLinear().domain([yMin, yMax]).range([this.rowHeight - this.offsetBottom, this.offsetTop]);
			const [xStart, xEnd] = this.globalExtentX;
			const xScale = d3.scaleTime().domain([xStart, xEnd]).range([0, this.chartWidth]);

			this.sensors.forEach((sensor, i) => {
			const parentNode = this.linePathRefs[i];
			if (!parentNode) return;

			d3.select(parentNode).selectAll('.top-line,.heat,.gap-aware-line,.gap-aware-area').remove();
			this.heatmapImages[i] = null;

			// Need at least 2 points overall to draw anything
			if (tele.data.length < 2) return;

			// Build heatmap strip with minimal passes
			const MAX_CANVAS_WIDTH = 30000;
			const CANVAS_HEIGHT = 1;
			const canvas = document.createElement('canvas');
			canvas.width = Math.min(this.chartWidth, MAX_CANVAS_WIDTH);
			canvas.height = CANVAS_HEIGHT;
			const ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			const canvasScale = canvas.width / this.chartWidth;
			const GAP_COLOR = '#fff';

			for (let j = 0; j < tele.data.length - 1; j++) {
				const r0 = tele.data[j];
				const r1 = tele.data[j + 1];
				const ts0 = r0[idxTs], ts1 = r1[idxTs];
				const v0 = r0[sensor.col];

				const timeGap = ts1 - ts0;
				const x0 = Math.floor(xScale(new Date(ts0)) * canvasScale);
				const x1 = Math.floor(xScale(new Date(ts1)) * canvasScale);
				const width = Math.max(1, Math.ceil(x1 - x0));

				let color;
				if (config.segmentation && timeGap > config.dataGapLength) {
				color = GAP_COLOR;
				} else if (!Number.isFinite(v0)) {
				color = GAP_COLOR;
				} else if (this.title === 'Bodenfeuchte') {
				color = dataModel.get_vol_color(this.device, v0);
				} else if (this.title === 'Bodentemperatur') {
				color = dataModel.get_temperature_color(this.device, v0);
				} else {
				color = '#000';
				}

				ctx.fillStyle = color;
				ctx.fillRect(x0, 0, width, CANVAS_HEIGHT);
			}
			this.heatmapImages[i] = canvas.toDataURL();

			if (this.heatmap) {
				d3.select(parentNode)
				.append('rect')
				.attr('x', 0).attr('y', 0)
				.attr('class', 'heat')
				.attr('width', this.chartWidth)
				.attr('height', this.rowHeight)
				.style('fill', `url(#heatmapPattern${this.componentId}-${i})`);
			} else {
				const lineGen = d3.line()
				.x(row => xScale(new Date(row[idxTs])))
				.y(row => yScale(row[sensor.col]))
				.defined(row => Number.isFinite(row[sensor.col]));

				// Split by time gaps if enabled
				const segments = this.splitByGapsRows(tele.data, idxTs, config.segmentation ? config.dataGapLength : Infinity);

				d3.select(parentNode).selectAll('.gap-aware-line,.gap-aware-area').remove();

				segments.forEach(seg => {
				if (seg.length < 2) return;

				// Area (using heatmap pattern as fill)
				const areaGen = d3.area()
					.x(row => xScale(new Date(row[idxTs])))
					.y0(this.rowHeight)
					.y1(row => yScale(row[sensor.col]))
					.defined(row => Number.isFinite(row[sensor.col]));

				d3.select(parentNode)
					.append('path')
					.datum(seg)
					.attr('class', 'gap-aware-area')
					.style('fill', `url(#heatmapPattern${this.componentId}-${i})`)
					.style('opacity', 1)
					.attr('d', areaGen);

				// Line
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
			});
		},

		// Split once for all sensors; segments are arrays of ORIGINAL rows (no copies)
		splitByGapsRows(rows, idxTs, maxGap) {
			if (!rows?.length) return [];
			const segs = [];
			let seg = [rows[0]];
			for (let i = 1; i < rows.length; i++) {
			const prev = rows[i - 1][idxTs];
			const cur  = rows[i][idxTs];
			if (cur - prev > maxGap) {
				segs.push(seg);
				seg = [];
			}
			seg.push(rows[i]);
			}
			if (seg.length) segs.push(seg);
			return segs;
		},
		drawChartsNextTick() {
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
		getLastSensorData(key) {
			const s = this.sensors.find(s => s.key === key);
			const tele = this.sensorData;
			if (!s || !tele?.data?.length) return null;
			const row = tele.data[tele.data.length - 1];
			const tsIndex = tele.schema.indexOf('ts');
			const ts  = row[tsIndex];
			const val = row[s.col];
			return Number.isFinite(val) ? { ts, value: val } : null;
		},
		getLastTimestamp() {
			return this.sensorData.data[this.sensorData.data.length - 1][0];
		},
		getData(key) {
			const hv = this.hoverData?.[key];
			if (hv && hv.value !== '-' && hv.value != null) return hv.value;
			const last = this.getLastSensorData(key);
			return last ? last.value : '-';
		},
		validData(key) {
			const v = this.getData(key);
			return typeof v === 'number' && Number.isFinite(v);
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
		sensorData: {
			handler() {
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
	},
	mounted() {
		window.addEventListener('sidebar:toggleFullWindow', this.drawCharts);
		window.addEventListener('resize', this.drawCharts);
		window.addEventListener('chartstyleselected', this.drawCharts);
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