<template>
	<div class="soilchart-root">
		<div class="soilcharts_headers" :style="containerStyle">
			<div
				v-for="soil in soilSeries"
				:key="soil.short"
				class="soilchart_header"
			>
				<div class="soilchart_icon">
					<Icon :obj="soil" :size="24" />
				</div>
				<div class="soilchart_name">
					{{ soil.name }}
				</div>
			</div>

			<div v-if="showAxisLabel" class="soilchart_header_spacer"></div>
		</div>

		<div
			class="soilcharts_plot_shell"
			ref="plotShell"
		>
			<div class="soilcharts_plots" :style="containerStyle">
				<div
					v-for="soil in soilSeries"
					:key="soil.short"
					class="soilchart_content"
				>
					<div class="soilchart_plot_frame">
						<img
							:src="chartImageSrc(soil)"
							class="soilchart_image"
							alt=""
						/>

						<div class="soilchart_background_overlay">
							<div
								class="soilchart_fk_top_fill"
								:style="fkTopFillStyle(soil)"
							></div>
							<div
								class="soilchart_hover_top_fill"
								:class="{ active: hoverActive }"
								:style="hoverTopFillStyle()"
							></div>
							<div
								class="soilchart_hover_fill"
								:class="{ active: hoverActive }"
								:style="hoverFillStyle(soil)"
							></div>
							<div
								class="soilchart_hover_deficit_fill"
								:class="{ active: hoverActive }"
								:style="hoverDeficitFillStyle(soil)"
							></div>
						</div>

						<div class="soilchart_line_overlay">
							<div
								class="soilchart_hover_line"
								:class="{ active: hoverActive }"
								:style="hoverLineStyle()"
							>
								<div
									class="soilchart_hover_value"
									:style="hoverLabelStyle()"
								>
									{{ hoverNfkLabel(soil) }}
								</div>
							</div>

							<div :class="lineClass(soil, 'fk')" :style="lineStyle(soil, 'fk')">
								<div class="soilchart_line_value" :class="{ hidden: hoverActive }">
									<div class="label">{{ compactLineLabels ? 'FK' : 'Feldkapazität' }}</div> 
									<div class="value">
										{{ soil.fk }}<span class="unit">Vol %</span>
									</div>
								</div>
							</div>
							<div :class="lineClass(soil, 'tw')" :style="lineStyle(soil, 'tw')">
								<div class="soilchart_line_value" :class="{ hidden: hoverActive }">
									<div class="label">{{ compactLineLabels ? 'WP' : 'Welkepunkt' }}</div> 
									<div class="value">
										{{ soil.tw }}<span class="unit">Vol %</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div
					v-if="soilSeries.length && showAxisLabel"
					class="soilchart_axis"
				>
					<div class="soilchart_axis_scale" :class="{ hidden: hoverActive }">
						<div
							v-for="tick in ticks"
							:key="tick"
							class="soilchart_axis_label"
							:style="{ top: axisTickTop(tick) }"
						>
							{{ tick }}
						</div>
					</div>
					<div
						class="soilchart_axis_indicator_line"
						:class="{ active: hoverActive }"
						:style="hoverAxisIndicatorStyle()"
					>
						<div
							class="soilchart_axis_indicator_value"
							:style="hoverLabelStyle()"
						>
							<img
								src="/img/tropfen_flat.png"
								class="soilchart_axis_indicator_icon"
								alt=""
							/>
							<div class="soilchart_axis_indicator_text">{{ hoverVolLabel() }}</div>
						</div>
					</div>
				</div>

				<div
					class="soilcharts_hover_capture"
					:style="hoverCaptureStyle"
					@mousemove="handleHoverMove"
					@mouseleave="clearHover"
				></div>
			</div>
		</div>

		<div class="soilchart_footer_area">
			<div class="soilchart_footer_grid" :style="containerStyle">
				<div
					v-for="soil in soilSeries"
					:key="soil.short + '-footer'"
					class="soilchart_footer"
				>
					<div
						class="soilchart_footer_label"
						:class="{ active: hoverActive }"
					>
						<div
							class="soilchart_footer_label_text"
							:style="hoverNfkStageStyle(soil)"
						>
							{{ hoverNfkStageLabel(soil) }}
						</div>
						<div class="soilchart_footer_label_overlay">
							{{ hoverNfkStageLabel(soil) }}
						</div>
					</div>
				</div>

				<div v-if="showAxisLabel" class="soilchart_footer_spacer"></div>
			</div>

			<div
				v-if="compactLineLabels && !hoverActive"
				class="soilchart_compact_legend"
				:style="compactLegendStyle"
			>
				FK Feldkapazität<br>
				WP Welkepunkt
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
			axisLabelWidth: 50,
			chartGap: 4,
			hoverCaptureTop: 10,
			hoverCaptureBottom: 10,
			hoverLabelHeight: 18,
			lineLabelCompactWidth: 115,
			showAxisLabel: true,
			flat_colors: false,
			topPad: 0,
			bottomPad: 0,
			chartImages: {},
			hoverActive: false,
			hoverYRatio: 0,
			plotShellWidth: 0,
			resizeObserver: null
		};
	},
	computed: {
		colorScheme() {
			return state.colorScheme;
		},
		normalizedHumusKey() {
			if (this.humusKey === 'h0' || this.humusKey === 'h1') {
				return 'h0-1';
			}
			return this.humusKey;
		},
		humusLabel() {
			return dataModel.humus_table[this.humusKey]?.name || this.humusKey;
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
		chartMax() {
			const maxFk = this.soilSeries.reduce((maxValue, soil) => Math.max(maxValue, soil.fk), 0);
			return Math.max(1, maxFk);
		},
		ticks() {
			const values = [];
			for (let tick = 0; tick <= this.displayMax; tick += 5) {
				values.push(tick);
			}
			// if (values[values.length - 1] !== this.chartMax) {
			// 	values.push(this.chartMax);
			// }
			return values.reverse();
		},
		containerStyle() {
			const count = Math.max(this.soilSeries.length, 1);
			return {
				gridTemplateColumns: this.showAxisLabel
					? `repeat(${count}, minmax(0, 1fr)) ${this.axisLabelWidth}px`
					: `repeat(${count}, minmax(0, 1fr))`,
				columnGap: this.chartGap + 'px'
			};
		},
		chartColumnWidth() {
			const count = Math.max(this.soilSeries.length, 1);
			const axisWidth = this.showAxisLabel ? this.axisLabelWidth : 0;
			const gaps = Math.max(0, count - 1) * this.chartGap;
			const availableWidth = Math.max(0, this.plotShellWidth - axisWidth - gaps);
			return availableWidth / count;
		},
		compactLineLabels() {
			return this.chartColumnWidth > 0 && this.chartColumnWidth < this.lineLabelCompactWidth;
		},
		compactLegendStyle() {
			return {
				left: '0',
				right: this.showAxisLabel ? this.axisLabelWidth + 'px' : '0'
			};
		},
		axisTitleStyle() {
			return {
				top: this.plotMiddlePercent + '%'
			};
		},
		hoverCaptureStyle() {
			return {
				top: `-${this.hoverCaptureTop}px`,
				bottom: `-${this.hoverCaptureBottom}px`
			};
		},
		plotHeight() {
			return this.chartHeight - this.topPad - this.bottomPad;
		},
		displayMax() {
			if (this.plotHeight <= 0) {
				return this.chartMax;
			}
			return this.chartMax * (this.chartHeight / this.plotHeight);
		},
		topPadPercent() {
			return (this.topPad / this.chartHeight) * 100;
		},
		bottomPadPercent() {
			return (this.bottomPad / this.chartHeight) * 100;
		},
		plotHeightPercent() {
			return (this.plotHeight / this.chartHeight) * 100;
		},
		plotMiddlePercent() {
			return this.topPadPercent + this.plotHeightPercent / 2;
		},
		hoverTopPercent() {
			return this.hoverYRatio * 100;
		},
		flatNfkColors() {
			const labels = dataModel.nfk_labels;
			return labels.map((label, index) => {
				if (index === 0) {
					return dataModel.get_nfk_color(0);
				}

				const prevValue = labels[index - 1].value;
				const midpoint = prevValue + (label.value - prevValue) / 2;
				return dataModel.get_nfk_color(midpoint);
			});
		}
	},
	watch: {
		humusKey() {
			this.queueDraw();
		},
		soilSeries() {
			this.$nextTick(() => {
				this.updatePlotMetrics();
			});
		},
		colorScheme() {
			this.queueDraw();
		}
	},
	mounted() {
		this.queueDraw();
		this.$nextTick(() => {
			this.updatePlotMetrics();
			if (typeof ResizeObserver !== 'undefined' && this.$refs.plotShell) {
				this.resizeObserver = new ResizeObserver(() => {
					this.updatePlotMetrics();
				});
				this.resizeObserver.observe(this.$refs.plotShell);
			}
		});
	},
	beforeUnmount() {
		if (this.resizeObserver) {
			this.resizeObserver.disconnect();
			this.resizeObserver = null;
		}
	},
	methods: {
		queueDraw() {
			this.$nextTick(() => {
				this.renderChartImages();
			});
		},
		chartImageSrc(soil) {
			return this.chartImages[soil.short] || '';
		},
		updatePlotMetrics() {
			this.plotShellWidth = this.$refs.plotShell?.clientWidth || 0;
		},
		handleHoverMove(event) {
			const rect = event.currentTarget.getBoundingClientRect();
			const y = Math.max(0, Math.min(this.chartHeight, event.clientY - rect.top - this.hoverCaptureTop));
			this.hoverYRatio = this.chartHeight > 0 ? y / this.chartHeight : 0;
			this.hoverActive = true;
		},
		clearHover() {
			this.hoverActive = false;
		},
		normalizedValue(value) {
			const clamped = Math.max(0, Math.min(this.displayMax, value));
			return clamped / this.displayMax;
		},
		valueToY(value) {
			return this.valueTopPercent(value) * this.chartHeight / 100;
		},
		yToValue(y) {
			const relative = (this.chartHeight - y) / this.chartHeight;
			return Math.max(0, Math.min(this.displayMax, relative * this.displayMax));
		},
		soilNfk(soil, value) {
			const range = soil.fk - soil.tw;
			if (range <= 0) {
				return 0;
			}
			return Math.max(0, ((value - soil.tw) / range) * 100);
		},
		getFlatNfkColor(value) {
			const labels = dataModel.nfk_labels;

			if (value <= labels[0].value) {
				return this.flatNfkColors[0];
			}

			if (value >= labels[labels.length - 1].value) {
				return this.flatNfkColors[labels.length - 1];
			}

			for (let i = 0; i < labels.length - 1; i += 1) {
				const curr = labels[i];
				const next = labels[i + 1];

				if (value >= curr.value && value < next.value) {
					return this.flatNfkColors[i + 1];
				}
			}

			return this.flatNfkColors[labels.length - 1];
		},
		lineTop(soil, key) {
			return this.valueToY(soil[key]);
		},
		valueTopPercent(value) {
			return (1 - this.normalizedValue(value)) * 100;
		},
		valueBottomPercent(value) {
			return this.normalizedValue(value) * 100;
		},
		lineStyle(soil, key) {
			return {
				bottom: this.valueBottomPercent(soil[key]) + '%'
			};
		},
		lineClass(soil, key) {
			const hoverValue = this.hoverVolValue();
			return {
				soilchart_line: true,
				[key]: true,
				top_edge_hidden: !this.hoverActive && key === 'fk',
				nfk_above: this.hoverActive && (
					key === 'fk'
						? hoverValue < soil[key]
						: hoverValue >= soil[key]
				)
			};
		},
		fkTopFillStyle(soil) {
			const fkBottom = this.valueBottomPercent(soil.fk);
			const hoverBottom = 100 - this.hoverTopPercent;
			return {
				top: '0%',
				bottom: (this.hoverActive ? Math.max(fkBottom, hoverBottom) : fkBottom) + '%',
				backgroundColor: '#00000044'
			};
		},
		twAreaStyle(soil) {
			return {
				top: this.valueTopPercent(soil.tw) + '%',
				bottom: this.bottomPadPercent + '%'
			};
		},
		axisTickTop(tick) {
			return this.valueTopPercent(tick) + '%';
		},
		hoverVolValue() {
			return this.yToValue(this.hoverYRatio * this.chartHeight);
		},
		hoverNfkValue(soil) {
			return this.soilNfk(soil, this.hoverVolValue());
		},
		hoverNfkLabel(soil) {
			return Math.round(this.hoverNfkValue(soil)) + ' nFK %';
		},
		hoverNfkStageLabel(soil) {
			return dataModel.get_nfk_label(this.hoverNfkValue(soil));
		},
		hoverNfkStageStyle(soil) {
			const nfk = this.hoverNfkValue(soil);
			return {
				color: this.flat_colors
					? this.getFlatNfkColor(nfk)
					: dataModel.get_nfk_color(nfk)
			};
		},
		hoverVolLabel() {
			return Math.floor(this.hoverVolValue()) + ' Vol %';
		},
		hoverFillStyle(soil) {
			const color = this.flat_colors
				? this.getFlatNfkColor(this.hoverNfkValue(soil))
				: dataModel.get_nfk_color(this.hoverNfkValue(soil));

			return {
				top: this.hoverTopPercent + '%',
				bottom: '0%',
				backgroundColor: color
			};
		},
		hoverTopFillStyle() {
			return {
				top: '0%',
				bottom: (100 - this.hoverTopPercent) + '%',
				backgroundColor: '#eee'
			};
		},
		hoverDeficitFillStyle(soil) {
			return {
				top: Math.max(this.hoverTopPercent, this.valueTopPercent(soil.tw)) + '%',
				bottom: '0%',
				backgroundColor: '#ff0000'
			};
		},
		hoverLineStyle() {
			return {
				top: this.hoverTopPercent + '%'
			};
		},
		hoverLabelStyle() {
			const hoverPixelY = this.hoverYRatio * this.chartHeight;
			const topOffset = Math.min(4, this.chartHeight - this.hoverLabelHeight - hoverPixelY);
			return {
				top: topOffset + 'px'
			};
		},
		hoverAxisIndicatorStyle() {
			return {
				top: this.hoverTopPercent + '%'
			};
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
					ctx.fillStyle = this.flat_colors
						? this.getFlatNfkColor(nfk)
						: dataModel.get_nfk_color(nfk);
					ctx.fillRect(0, y, 1, 1);
				}

				nextImages[soil.short] = canvas.toDataURL();
			});

			this.chartImages = nextImages;
		}
	}
};
</script>

<style lang="stylus" scoped>

.soilchart-root
	width 100%

.soilcharts_headers
	display grid
	align-items start
	width 100%
	min-width 0

.soilchart_header
	display flex
	flex-direction column
	min-width 0
	width 100%
	padding 6px 0 4px

.soilchart_header_spacer
	min-width 0

.soilcharts_plot_shell
	position relative
	width 100%
	height 300px
	min-width 0

.soilcharts_plots
	position relative
	display grid
	align-items stretch
	width 100%
	height 100%
	min-width 0

.soilchart_icon
	display flex
	justify-content center
	align-items center
	padding-bottom 4px
	filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

.soilchart_name
	grid-row 2
	text-align center
	align-self start
	min-width 0
	width 100%
	font-size 8pt
	opacity .8
	font-weight bold
	overflow-wrap anywhere
	word-break break-word
	hyphens auto


.soilchart_content
	position relative
	box-sizing border-box
	min-width 0
	height 100%
	border-radius 4px
	overflow hidden
	z-index 1

.soilchart_plot_frame
	position relative
	width 100%
	height 100%
	border-radius inherit

// .soilchart:first-child 
// 	.soilchart_content
// 		border-top-left-radius 4px
// 		border-bottom-left-radius 4px
// .soilchart:last-child 
// 	.soilchart_content
// 		border-top-right-radius 4px
// 		border-bottom-right-radius 4px

.soilchart_image
	display block
	width 100%
	height 100%
	object-fit fill

.soilchart_background_overlay
	position absolute
	inset 0
	pointer-events none
	z-index 1
	border-radius inherit
	overflow hidden

.soilchart_line_overlay
	position absolute
	inset -1px 0 0 0
	pointer-events none
	z-index 3
	border-radius inherit
	overflow visible

.soilcharts_hover_capture
	position absolute
	inset 0
	z-index 4
	pointer-events auto

.soilchart_axis
	position relative
	width 38px
	height 100%
	align-self stretch
	pointer-events none
	min-width 38px
	font-size 7pt
	.soilchart_axis_scale
		opacity 1
		transition opacity .14s ease
	.soilchart_axis_scale.hidden
		opacity 0
	.soilchart_axis_title
		position absolute
		left 32px
		white-space nowrap
		transform translate(-50%, -50%) rotate(-90deg)
	.soilchart_axis_label
		position absolute
		left 3px
		display none
		padding-bottom 2px
		transform translateY(-50%)
	.soilchart_axis_label:last-child:after
		content ' Vol %'
	.soilchart_axis_indicator_line
		position absolute
		left 0
		right 0
		height 0
		border-top 1px solid rgba(0, 0, 0, .15)
		opacity 0
		transition opacity .14s ease
	.soilchart_axis_indicator_line.active
		opacity 1
.soilchart_axis_indicator_value
	position absolute
	left 0
	padding 2px 4px
	font-size 7pt
	line-height 1
	white-space nowrap
	color rgba(0, 0, 0, .78)
	background rgba(255, 255, 255, .82)
	border-radius 2px
	display flex
	align-items center
	gap 2px

.soilchart_axis_indicator_icon
	position static
	width 8px
	height 8px
	object-fit contain

.soilchart_axis_indicator_text
	display block

.soilchart_footer_area
	position relative
	margin-top 12px
	width 100%
	height 30px
	min-width 0

.soilchart_footer_grid
	display grid
	align-items stretch
	width 100%
	height 100%
	min-width 0

.soilchart_footer
	display flex
	align-items center
	justify-content center
	min-width 0
	height 100%

.soilchart_footer_spacer
	min-width 0

.soilchart_footer_label
	position relative
	width 100%
	height 100%
	font-size 7.5pt
	line-height 1.1
	font-weight bold
	text-transform uppercase
	letter-spacing .015em
	opacity 0
	transition opacity .14s ease

.soilchart_footer_label.active
	opacity 1

.soilchart_footer_label_text
.soilchart_footer_label_overlay
	position absolute
	inset 0
	display flex
	align-items center
	justify-content center
	text-align center
	white-space normal
	overflow-wrap normal
	word-break normal
	hyphens none

.soilchart_footer_label_overlay
	color #000
	opacity .12
	text-shadow none

.soilchart_compact_legend
	position absolute
	top 0
	bottom 0
	font-size 7.5pt
	line-height 1.35
	opacity .75
	display flex
	flex-direction column
	align-items flex-start
	justify-content center
	text-align left
	pointer-events none

.soilchart_legend
	display flex
	flex-direction column
	align-items flex-start
	gap 6px
	flex 0 0 auto

.soilchart_legend_item
	display inline-flex
	align-items center
	gap 6px

.soilchart_line
	position absolute
	left 0
	right 0
	height 0
	z-index 1

.soilchart_hover_fill
	position absolute
	left 0
	right 0
	bottom 0
	z-index 1
	opacity 0
	transition opacity .14s ease

.soilchart_fk_top_fill
	position absolute
	left 0
	right 0
	z-index 3

.soilchart_hover_top_fill
	position absolute
	left 0
	right 0
	z-index 0
	opacity 0
	transition opacity .14s ease

.soilchart_hover_deficit_fill
	position absolute
	left 0
	right 0
	bottom 0
	z-index 2
	opacity 0
	transition opacity .14s ease

.soilchart_hover_line
	position absolute
	left 0
	right 0
	height 0
	border-top 1px solid rgba(0, 0, 0, .15)
	z-index 2
	opacity 0
	transition opacity .14s ease

.soilchart_hover_top_fill.active
.soilchart_hover_fill.active
.soilchart_hover_deficit_fill.active
.soilchart_hover_line.active
	opacity 1

.soilchart_hover_value
	position absolute
	right 4px
	padding 2px 4px
	font-size 7pt
	line-height 1
	white-space nowrap
	color rgba(0, 0, 0, .78)
	background rgba(255, 255, 255, .72)
	border-radius 2px

.soilchart_line_value
	position absolute
	left 3px
	top 4px
	display flex
	justify-content flex-start
	align-items baseline
	font-size 8pt
	line-height 1
	opacity 1
	transition opacity .14s ease
	.label
		opacity .6
	.value
		font-weight normal
		margin-left .3em
		opacity 1
	.unit
		font-size 7pt
		opacity .6
		margin-left .4em

.soilchart_line_value.hidden
	opacity 0

.soilchart_line_sample
	display inline-block
	width 32px

.soilchart_line_sample.fk
.soilchart_line.fk
	border-bottom 1px dotted #000

.soilchart_line_sample.tw
.soilchart_line.tw
	border-bottom 1px dotted #000

.soilchart_line.nfk_above
	border-bottom-color transparent

.soilchart_line.top_edge_hidden
	border-bottom-color transparent




</style>
