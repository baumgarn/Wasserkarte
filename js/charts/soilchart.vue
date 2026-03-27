<template>
	<div class="soilchart" :class="{compact: compact}" >

		<div
			class="chart-area-container"
			:style="{height: totalChartHeight + 'px'}"
			ref="chartContainerRef"
			@mousemove="handleHoverMove"
			@mouseleave="clearHover"
		>
			<div class="axis" v-if="showAxis" :style="{top: topPad + 'px', width: axisWidth + 'px', height: chartHeight + 'px'}">
				<div
					v-for="tick in ticks"
					:key="tick"
					class="axis-line"
					:style="{ top: valueTopPercent(tick)+ '%' }"
				>
					<div class="label">
						{{ tick }} %
					</div>
				</div>
				<div class="legend">
					<div class="label">
						Vol %
					</div>
				</div>
			</div>

			<div class="chart-area" :style="{top: topPad + 'px', height: chartHeight + 'px', width: chartWidth + 'px'}" ref="chartAreaRef">

				<div class="chart-columns" :style="containerStyle">
					
					<div
						v-for="soil in soilSeries"
						:key="soil.short"
						class="chart-column"
						:style="{height: chartHeight + 'px'}"
					>
						<div class="column-fk" :style="{height: valueBottomPixel(soil.fk) + 'px'}">
							{{ soil.fk }}

							<img
								:src="chartImageSrc(soil)"
								class="column-image"
								:style="{height: chartHeight + 'px'}"
							/>
						</div>

						<div class="area-tw" :style="{height: valueBottomPixel(soil.tw) + 'px'}">

						</div>
						<div class="label-fk" :style="{bottom: valueBottomPixel(soil.fk) + 'px'}">
							<span class="label">{{ compact ? 'FK' : 'Feldkapazität' }}</span> 
							<span class="value">{{soil.fk}}</span>
							<span class="unit">Vol %</span>
						</div>
						<div class="label-tw" :style="{bottom: valueBottomPixel(soil.tw) + 'px'}">
							<span class="label">{{ compact ? 'WP' : 'Welkepunkt' }}</span> 
							<span class="value">{{soil.tw}}</span>
							<span class="unit">Vol %</span>
						</div>
					</div>

				</div>
			</div>

		</div>
		
		<div class="chart-headers" :style="containerStyle">
			<div
				v-for="soil in soilSeries"
				:key="soil.short"
				class="chart-header"
			>
			<div class="chart-icon">
				<Icon :obj="soil" :size="24" />
			</div>
			<div class="chart-name">
				{{ soil.name }}
			</div>
		</div>

		</div>

	</div>
</template>

<script>
import { dataModel } from '@/datamodel.js';
import Icon from '@/ui/Icon.vue';
import { state } from '@/state.js';

export default {
	name: 'SoilChart',
	components: {
		Icon
	},
	props: {
		humusKey: {
			type: String,
			default: 'h0'
		}
	},
	data() {
		return {
			chartHeight: 300,
			compactColumnWidth: 115,
			topPad: 24,
			bottomPad: 8,
			chartWidth: 0,
			axisWidth: 50,
			showAxis: false,
			chartImages: {},
			hoverValue: null,
			hoverPos: -1,
			hoverSoil: null,
			resizeObserver: null,
		};
	},
	computed: {
		colorScheme() {
			return state.colorScheme;
		},
		totalChartHeight() {
			return this.chartHeight + this.topPad + this.bottomPad;
		},
		chartRange() {
			return Math.max(1, this.maxFK);
		},
		maxFK() {
			return this.soilSeries.reduce((maxValue, soil) => Math.max(maxValue, soil.fk), 0);
		},
		ticks() {
			const values = [];
			for (let tick = 0; tick <= this.chartRange; tick += 5) {
				values.push(tick);
			}
			return values.reverse();
		},

		containerStyle() {
			const count = Math.max(this.soilSeries.length, 1);
			return {
				width: this.chartWidth + 'px',
				gridTemplateColumns: `repeat(${count}, minmax(0, 1fr))`,
				columnGap: this.chartGap + 'px'
			};
		},

		chartColumnWidth() {
			const count = Math.max(this.soilSeries.length, 1);
			const gapWidth = Math.max(0, count - 1) * this.chartGap;
			const availableWidth = Math.max(0, this.chartWidth - gapWidth);
			return availableWidth / count;
		},

		compact() {
			return this.chartColumnWidth > 0 && this.chartColumnWidth < this.compactColumnWidth;
		},

		chartGap() {
			return this.compact ? 4 : 8;
		},


		soilSeries() {
			return Object.values(dataModel.soil_table)
				.filter(soil => soil?.FK?.[this.normalizedHumusKey] != null && soil?.TW?.[this.normalizedHumusKey] != null)
				.sort((a, b) => (a.sort ?? 999) - (b.sort ?? 999))
				.map(soil => ({
					...soil,
					fk: soil.FK[this.normalizedHumusKey],
					tw: soil.TW[this.normalizedHumusKey]
				}));
		},

		normalizedHumusKey() {
			if (this.humusKey === 'h0' || this.humusKey === 'h1') {
				return 'h0-1';
			}
			return this.humusKey;
		},


	},
	methods: {
		valueTopPercent(value) {
			return (1 - this.normalizedValue(value)) * 100;
		},
		valueBottomPercent(value) {
			return this.normalizedValue(value) * 100;
		},
		valueTopPixel(value) {
			return (1 - this.normalizedValue(value)) * this.chartHeight;
		},
		valueBottomPixel(value) {
			return this.normalizedValue(value) * this.chartHeight;
		},
		normalizedValue(value) {
			const clamped = Math.max(0, Math.min(this.chartRange, value));
			return clamped / this.chartRange;
		},
		valueToY(value) {
			return this.valueTopPercent(value) * this.chartHeight / 100;
		},
		yToValue(y) {
			const relative = (this.chartHeight - y) / this.chartHeight;
			return Math.max(0, Math.min(this.chartRange, relative * this.chartRange));
		},
		soilNfk(soil, value) {
			const range = soil.fk - soil.tw;
			if (range <= 0) {
				return 0;
			}
			return Math.max(0, ((value - soil.tw) / range) * 100);
		},
		handleHoverMove(event) {
			const chartAreaRect = this.$refs.chartAreaRef?.getBoundingClientRect();

			if (!chartAreaRect || this.chartHeight <= 0) {
				this.clearHover();
				return;
			}

			const relativeX = event.clientX - chartAreaRect.left;
			const relativeY = event.clientY - chartAreaRect.top;
			const clampedY = Math.max(0, Math.min(this.chartHeight, relativeY));
			const hoverPos = this.chartHeight - clampedY;
			const hoverValue = this.yToValue(clampedY);
			const hoverSoil = this.hoveredSoilAtX(relativeX);

			this.hoverValue = hoverValue;
			this.hoverPos = hoverPos;
			this.hoverSoil = hoverSoil;
		},
		clearHover() {
			this.hoverValue = null;
			this.hoverPos = -1;
			this.hoverSoil = null;
		},
		hoveredSoilAtX(relativeX) {
			if (relativeX < 0 || relativeX > this.chartWidth || !this.soilSeries.length) {
				return null;
			}

			const stepWidth = this.chartColumnWidth + this.chartGap;
			if (stepWidth <= 0 || this.chartColumnWidth <= 0) {
				return null;
			}

			const safeX = Math.min(relativeX, Math.max(0, this.chartWidth - 0.0001));
			const columnIndex = Math.floor(safeX / stepWidth);
			const xInsideStep = safeX - columnIndex * stepWidth;

			if (xInsideStep > this.chartColumnWidth) {
				return null;
			}

			return this.soilSeries[columnIndex] || null;
		},
		updateChartMetrics() {
			const containerWidth = this.$refs.chartContainerRef?.clientWidth || 0;
			this.chartWidth = Math.max(0, containerWidth - (this.showAxis ? this.axisWidth : 0));
			
		},

		chartImageSrc(soil) {
			return this.chartImages[soil.short] || '';
		},
		queueDraw() {
			this.$nextTick(() => {
				this.renderChartImages();
			});
		},
		renderChartImages() {
			const nextImages = {};

			this.soilSeries.forEach(soil => {
				const canvas = document.createElement('canvas');
				canvas.width = 1;
				canvas.height = this.chartHeight;
				const ctx = canvas.getContext('2d');

				ctx.clearRect(0, 0, 1, this.chartHeight);

				for (let y = 0; y < this.chartHeight; y += 1) {
					const value = this.yToValue(y + 0.5);
					const nfk = this.soilNfk(soil, value);
					ctx.fillStyle = dataModel.get_nfk_color(nfk);
					ctx.fillRect(0, y, 1, 1);
				}

				nextImages[soil.short] = canvas.toDataURL();
			});

			this.chartImages = nextImages;
		}
		
	},
	watch: {
		humusKey() {
			this.queueDraw();
		},
		soilSeries() {
			this.$nextTick(() => {
				this.updateChartMetrics();
			});
		},
		colorScheme() {
			this.queueDraw();
		}
	},
	mounted() {
		this.queueDraw();
		this.$nextTick(() => {
			this.updateChartMetrics();
			if (typeof ResizeObserver !== 'undefined' && this.$refs.chartContainerRef) {
				this.resizeObserver = new ResizeObserver(() => {
					this.updateChartMetrics();
				});
				this.resizeObserver.observe(this.$refs.chartContainerRef);
			}
		});
	},
	beforeUnmount() {
		if (this.resizeObserver) {
			this.resizeObserver.disconnect();
			this.resizeObserver = null;
		}
	},

};
</script>

<style lang="stylus" scoped>


.soilchart
	font-size 8pt
	margin-right 8px
	padding-bottom 16px
	display block
	max-width 500px
	.chart-area-container
		display block
		position relative
		width 100%
		z-index 1
		.axis
			right 0
			position absolute
			width 100%
			.axis-line
				position absolute
				left 8px
				width 12px
				height 1px
				background #00000030
			.label
				position absolute
				left 18px
				font-size 7.5pt
				opacity .55
				white-space nowrap
				transform translate(0,-50%)
			.legend
				position absolute
				bottom 100%
				left 10px
				width 16px
				height 16px
				// background-image url(/img/tropfen_flat.png)
				// background-size 60% 90%
				// background-repeat no-repeat
				// background-position 1px 0
				.label
					margin-left -2px
					transform none
					margin-bottom 1px

		.chart-area
			position absolute

.chart-columns
	display grid
	align-items start
	min-width 0
	.chart-column
		height 100%
		position relative
		.column-fk
			position absolute
			display block
			left 0
			width 100%
			bottom 0
			background #ddd
			border-radius 4px
			overflow hidden
			filter: drop-shadow(0 1px 2px rgba(0,0,0,.22));
		.column-image
			position absolute
			bottom 0
			left 0
			width 100%
		.area-tw
			position absolute
			bottom 0
			left 0
			width 100%
			border-top 1px solid #ff000044
			opacity .4
			background-image url(/img/totwasser4.png)
			background-repeat repeat
			background-position -3px 0
			background-size 6px 6px
			overflow hidden
			border-bottom-left-radius 4px
			border-bottom-right-radius 4px
		.label-tw
		.label-fk
			position absolute
			left 0
			width 100%
			padding 1px 3px
			text-align right
			// .compact &
			.value
				font-size 9.5pt
				opacity .8
				margin-left .3em
				margin-right .1em
			.label
			.unit
				opacity .5
				font-size 7.5pt
		.label-fk
			padding 1px 2px
	

	// .chart-column:last-child

// .chart-column:not(:last-child) .label-tw .label
// .chart-column:not(:last-child) .label-fk .label
// 	display none


.chart-headers
	display grid
	align-items start
	min-width 0
	.chart-header
		display flex
		flex-direction column
		align-items center
		padding-bottom 6px
		margin-top -2px
		.chart-name
			text-align center
			font-weight bold
		.chart-icon
			filter: drop-shadow(0 1px 1px rgba(0,0,0,.22));

</style>
