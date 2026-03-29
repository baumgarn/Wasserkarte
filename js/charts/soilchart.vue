<template>
	<div class="soilchart" :class="{compact: compact, humusMode: humusMode, soilMode: soilMode}" >
		<div class="chart-header">

			<div v-if="title" class="headerwrapper">
				<h3>{{ title }}</h3>
			</div>

			<div
				v-if="humusMode && selectedSoil"
				class="chart-current-soil-wrap"
			>
				<div
					ref="soilPickerTriggerRef"
					class="chart-current-soil"
					@click.stop="openSoilPicker"
				>
					<div class="bg"></div>
					<Icon :obj="selectedSoil" :size="24" :shadow="false" />
					<span>{{ selectedSoil.name }}</span>
				</div>
				<PopoverMenu
					ref="soilPickerRef"
					class="soil-picker-popover"
					:items="soilPickerItems"
				/>
			</div>
		</div>

		<div
			class="chart-area-container"
			:style="{height: totalChartHeight + 'px', marginBottom: (- bottomPad) + 'px'}"
			ref="chartContainerRef"
			:class="{ hoverBottom: hoverValue === 0, hoverTop: hoverValue >= chartRange, hoverBottomBounds: hoverPos >= 0 && hoverPos < hoverEndBounds, hoverTopBounds: hoverPos > chartHeight - hoverEndBounds }"
			@mousemove="handleHoverMove"
			@mouseleave="handleChartMouseLeave"
			@mousedown="handleColumnMouseDown"
		>
			<div class="axis" :style="{top: topPad + 'px', height: chartHeight + 'px'}">
				<template v-if="showLines">
					<div
						v-for="tick in ticks"
						:key="tick"
						class="axis-line"
						:style="{ top: valueTopPercent(tick)+ '%' }"
						>
						<div class="label" v-if="showAxisValues">
							{{ tick }} %
						</div>
					</div>
					<div class="legend" v-if="showAxisValues">
						<div class="label">
							Vol %
						</div>
					</div>
				</template>

				<div
					v-if="chartmousedown && hoverPos >= 0 && hoverValue != null"
					class="label-vol"
					:style="{ bottom: hoverPos + 'px' }"
				>
					<span class="value">{{ Math.round(hoverValue) }}</span>
					<span class="unit">Vol %</span>
				</div>

			</div>

			<div class="chart-area" :style="{top: topPad + 'px', height: chartHeight + 'px', width: chartWidth + 'px'}" ref="chartAreaRef">
				

				<div class="chart-columns" :style="containerStyle" >
					
					<div
						v-for="(entry, index) in chartSeries"
						:key="entry.short"
						class="chart-column"
						:style="{height: chartHeight + 'px'}"
					>
						<div class="column-container" v-if="!shouldShowHoverColumn(index)">

							<div class="column-fk" :style="{height: valueBottomPixel(entry.fk) + 'px'}">
								{{ entry.fk }}
								
								<img
								:src="chartImageSrc(entry)"
								class="column-image"
								:style="{height: chartHeight + 'px'}"
								/>
							</div>
							
							<div class="area-tw" :style="{height: valueBottomPixel(entry.tw) + 'px'}">
								
							</div>
							<div class="label-fk" :style="{bottom: valueBottomPixel(entry.fk) + 'px'}">
								<span class="label" v-if="!veryCompact">{{ compact ? 'FK' : 'Feldkapazität' }}</span>
								<span class="value">{{entry.fk}}</span>
								<span class="unit">Vol %</span>
							</div>
							<div class="label-tw" :style="{bottom: valueBottomPixel(entry.tw) + 'px'}">
								<span class="label" v-if="!veryCompact">{{ compact ? 'WP' : 'Welkepunkt' }}</span>
								<span class="value">{{entry.tw}}</span>
								<span class="unit">Vol %</span>
							</div>
							
						</div>
							
						<div class="column-container-hover" v-if="shouldShowHoverColumn(index)">
							
							<div class="column-hover" :style="{height: hoverPos + 'px', background: hoverNfkColorForEntry(entry)}" >
								<div class="area-tw" :style="{height: valueBottomPixel(entry.tw) + 'px'}">
								</div>
								<div class="area-sw" v-if="valueBottomPixel(entry.fk) < hoverPos" :style="{bottom: valueBottomPixel(entry.fk) + 'px'}">
								</div>
								<!-- <div class="area-fk" v-if="valueBottomPixel(entry.fk) < hoverPos" :style="{height: valueBottomPixel(entry.fk) + 'px'}"> -->
								<!-- </div> -->
							</div>
							
							<div class="label-nfk" :style="{height: hoverPos + 'px'}">
								<span class="value">{{hoverNfkValueForEntry(entry)}}</span>
								<span class="unit">nFK %</span>
							</div>

							<div class="soilstatus" :style="{bottom: (hoverPos) + 'px'}">
								<div class="name" :style="{ color: hoverNfkColorForEntry(entry) }">
									{{ hoverNfkStageLabelForEntry(entry) }}
								</div>
								<div class="nameoverlay">
									{{ hoverNfkStageLabelForEntry(entry) }}
								</div>								
							</div>

						</div>

					</div>

				</div>
			</div>

		</div>
		
		<div class="chart-info-container" :style="{ ...containerStyle, marginTop: chartGapV + 'px'}">
			<div
				v-for="entry in chartSeries"
				:key="entry.short"
				class="chart-info-column"

			>
				<div class="chart-column-header"
				:class="{
					active: isIncludedFilter(entry),
					exclude: isExcludedFilter(entry),
					hover: isHoverFilter(entry)
				}"
				@click="handleHeaderClick(entry, $event)">
					<div class="chart-icon">
						<Icon :obj="entry.filterObj" :size="humusMode ? 24 : 24" shadow="true" />
					</div>
					<div class="chart-name">
						{{ entry.name }}
					</div>
				</div>

				<div class="chart-additional-info">

					<div
						v-if="showTextureRows"
						class="chart-property-info"
						:class="{ showValues: !showTextureBars }"
						@click="toggleTextureInfoDisplay"
					>
						<div
						v-for="textureType in textureTypes"
						:key="entry.short + '-' + textureType.key"
						class="texture-row"
						>
							<div class="texture-bar-container">
								<div
								class="texture-bar"
								:style="textureBarStyle(entry, textureType.key)"
								></div>
							</div>
							<div class="texture-text">
								<span class="texture-name">{{ textureType.label }}</span>
								<span class="texture-values">{{ textureRangeLabel(entry, textureType.key) }}</span>
							</div>
						</div>
					</div>

					<div
						v-if="showHumusRows"
						class="chart-property-info showValues staticRows"
					>
						<div
							v-for="infoRow in humusInfoRows(entry)"
							:key="entry.short + '-' + infoRow.key"
							class="texture-row"
						>
							<div class="texture-text">
								<span class="texture-name">{{ infoRow.label }}</span>
								<span class="texture-values">{{ infoRow.value }}</span>
							</div>
						</div>
					</div>




				</div>
					</div>

				</div>
				</div>
</template>

<script>
import { dataModel } from '@/datamodel.js';
import Icon from '@/ui/Icon.vue';
import PopoverMenu from '@/ui/popovermenu.vue';
import { state } from '@/state.js';

export default {
	name: 'SoilChart',
	emits: ['update:soilKey', 'update:soil-key'],
	components: {
		Icon,
		PopoverMenu
	},
	props: {
		title: {
			type: String
		},
		mode: {
			type: String,
			default: 'soil'
		},
		humusKey: {
			type: String,
			default: 'h0'
		},
		soilKey: {
			type: String,
			default: 'Ss'
		},
	},
	data() {
		return {
			showAdditionalInfo: true,
			hoverChartsMousedownOnly: true,
			showLines: false,
			showAxisValues: false,
			showHoverChart: true,
			compactColumnWidth: 115,
			veryCompactColumnWidth: 70,
			pixelPerVol: 9,
			hoverEndBounds: 16,
			defaultChartGap: 12,
			compactChartGap: 4,
			defaultSidePad: 0,
			compactSidePad: 0,
			bottomPad: 0,
			chartWidth: 0,
			axisWidth: 50,
			chartImages: {},
			hoverValue: null,
			hoverPos: -1,
			hoverEntry: null,
			chartmousedown: false,
			chartMousemoveListener: null,
			chartMouseupListener: null,
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
		chartHeight() {
			return this.chartRange * this.pixelPerVol;
			return 200;
		},
		topPad() {
			return (this.humusMode && this.selectedSoil) ? 56 : 20;
			// return (this.humusMode && this.selectedSoil) ? 56 : 20;
		},
		hoverPosTop() {
			return this.chartHeight-this.hoverPos;
		},
		chartRange() {
			if (this.topValue) return this.topValue
			if (this.soilMode) return this.maxFK + 7
			return this.maxSoilTableFK + 7;
			// // upper soil table value rounded to next tick line in 5er steps
			// let upper = 0;
			// while (upper <= this.maxSoilTableFK) {
			// 	upper += 5;
			// }
			// return upper;
			// return this.maxSoilTableFK;
		},
		maxFK() {
			return this.chartSeries.reduce((maxValue, entry) => Math.max(maxValue, entry.fk), 0);
		},
		maxSoilTableFK() {
			return this.getMaxSoilTableFK();
		},
		ticks() {
			const values = [];
			for (let tick = 0; tick <= this.chartRange; tick += 5) {
				values.push(tick);
			}
			return values.reverse();
		},

		containerStyle() {
			const count = Math.max(this.chartSeries.length, 1);
			return {
				width: this.chartWidth + 'px',
				boxSizing: 'border-box',
				paddingLeft: this.sidePad + 'px',
				paddingRight: this.sidePad + 'px',
				gridTemplateColumns: `repeat(${count}, minmax(0, 1fr))`,
				columnGap: this.chartGap + 'px'
			};
		},

		compactCheckColumnWidth() {
			const count = Math.max(this.chartSeries.length, 1);
			const gapWidth = Math.max(0, count - 1) * this.defaultChartGap;
			const availableWidth = Math.max(0, this.chartWidth - gapWidth);
			return availableWidth / count;
		},
		sidePad() {
			return this.compact ? this.compactSidePad : this.defaultSidePad;
		},
		innerChartWidth() {
			return Math.max(0, this.chartWidth - this.sidePad * 2);
		},

		baseChartColumnWidth() {
			const count = Math.max(this.chartSeries.length, 1);
			const gapWidth = Math.max(0, count - 1) * this.defaultChartGap;
			const availableWidth = Math.max(0, this.innerChartWidth - gapWidth);
			return availableWidth / count;
		},

		chartColumnWidth() {
			const count = Math.max(this.chartSeries.length, 1);
			const gapWidth = Math.max(0, count - 1) * this.chartGap;
			const availableWidth = Math.max(0, this.innerChartWidth - gapWidth);
			return availableWidth / count;
		},
		hoverColumnIndex() {
			if (!this.hoverEntry) {
				return -1;
			}

			return this.chartSeries.findIndex(entry => entry.short === this.hoverEntry.short);
		},
		hoverCursorLineStyle() {
			if (this.hoverPos < 0) {
				return null;
			}

			const top = this.chartHeight - this.hoverPos;

			return {
				top: top + 'px'
			};
		},
		compact() {
			return this.compactCheckColumnWidth > 0 && this.compactCheckColumnWidth < this.compactColumnWidth;
		},
		veryCompact() {
			return this.baseChartColumnWidth > 0 && this.baseChartColumnWidth < this.veryCompactColumnWidth;
		},

		chartGap() {
			return this.compact ? this.compactChartGap : this.defaultChartGap;
		},
		chartGapV() {
			return 0;
		},
		humusMode() {
			return this.mode === 'humus';
		},
		soilMode() {
			return this.mode === 'soil';
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
		selectedSoil() {
			return dataModel.soil_table[this.soilKey] || null;
		},
		humusSeries() {
			const soil = this.selectedSoil;
			if (!soil) {
				return [];
			}

			return ['h1', 'h2', 'h3', 'h4']
				.map((humusKey) => {
					const humus = dataModel.humus_table[humusKey];
					const lookupKey = this.normalizeHumusKey(humusKey);

					if (!humus || soil?.FK?.[lookupKey] == null || soil?.TW?.[lookupKey] == null) {
						return null;
					}

					return {
						...humus,
						fk: soil.FK[lookupKey],
						tw: soil.TW[lookupKey],
						filterObj: humus
					};
				})
				.filter(Boolean);
		},
		chartSeries() {
			if (this.humusMode) {
				return this.humusSeries;
			}

			return this.soilSeries.map(soil => ({
				...soil,
				filterObj: soil
			}));
		},

		normalizedHumusKey() {
			return this.normalizeHumusKey(this.humusKey);
		},
		textureTypes() {
			return [
				{ key: 'sand', label: 'Sand' },
				{ key: 'schluff', label: 'Schluff' },
				{ key: 'ton', label: 'Ton' }
			];
		},
		showTextureRows() {
			return !this.humusMode && this.showAdditionalInfo;
		},
		showHumusRows() {
			return this.humusMode;
		},
		showTextureLine() {
			return !this.humusMode && this.chartTexturesLine;
		},
		showTextureBars() {
			return state.soilchart_texture_showbars;
		},
		soilPickerItems() {
			return Object.values(dataModel.soil_table)
				.sort((a, b) => (a.sort ?? 999) - (b.sort ?? 999))
				.map(soil => ({
					type: 'select',
					obj: soil,
					label: soil.name,
					active: this.soilKey === soil.short,
					onSelect: () => this.selectSoilKey(soil.short)
				}));
		},


	},
	methods: {
		getMaxSoilTableFK() {
			return Object.values(dataModel.soil_table).reduce((maxValue, soil) => {
				const soilMax = Object.values(soil?.FK || {}).reduce((currentMax, fkValue) => {
					return typeof fkValue === 'number' ? Math.max(currentMax, fkValue) : currentMax;
				}, 0);

				return Math.max(maxValue, soilMax);
			}, 0);
		},
		normalizeHumusKey(humusKey) {
			if (humusKey === 'h0' || humusKey === 'h1') {
				return 'h0-1';
			}
			return humusKey;
		},
		filterTarget(entry) {
			return entry?.filterObj || entry;
		},
		isIncludedFilter(entry) {
			const target = this.filterTarget(entry);
			return state.includeFilter.some(item => item.name === target?.name);
		},
		isExcludedFilter(entry) {
			const target = this.filterTarget(entry);
			return state.excludeFilter.some(item => item.name === target?.name);
		},
		isHoverFilter(entry) {
			const target = this.filterTarget(entry);
			return state.hoverFilter?.name === target?.name;
		},
		handleHeaderClick(entry, event) {
			const target = this.filterTarget(entry);

			if (!target) {
				return;
			}

			if (!this.isIncludedFilter(entry)) {
				state.includeFilter = [target];
				state.excludeFilter = [];
			} else {
				state.includeFilter = state.includeFilter.filter(item => item.name !== target.name);
				state.hoverFilter = null;
			}

			if (state.isMobile) {
				state.selectedDevice = null;
			}

			event.stopPropagation();
		},
		openSoilPicker() {
			const popover = this.$refs.soilPickerRef;
			const trigger = this.$refs.soilPickerTriggerRef;

			if (!popover?.open || !trigger) {
				return;
			}

			if (popover.isOpen) {
				popover.close();
				return;
			}

			window.dispatchEvent(new CustomEvent('app:close-popovers'));
			popover.open({
				bottom: -36,
				right: 0,
				left: 0,
				width: '100%',
			});
		},
		selectSoilKey(soilKey) {
			this.$emit('update:soilKey', soilKey);
			this.$emit('update:soil-key', soilKey);
		},
		toggleTextureInfoDisplay() {
			state.soilchart_texture_showbars = !state.soilchart_texture_showbars;
		},
		humusInfoRows(entry) {
			return [
				{ key: 'humus', label: 'Humus', value: entry?.humus || '-' },
				{ key: 'corg', label: 'Corg', value: entry?.corg || '-' }
			];
		},
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
		handleColumnMouseDown() {
			this.chartmousedown = true;

			if (this.chartMousemoveListener) {
				window.removeEventListener('mousemove', this.chartMousemoveListener);
			}

			this.chartMousemoveListener = (event) => {
				this.updateHoverFromEvent(event);
			};

			window.addEventListener('mousemove', this.chartMousemoveListener);

			if (this.chartMouseupListener) {
				window.removeEventListener('mouseup', this.chartMouseupListener);
			}

			this.chartMouseupListener = (event) => {
				this.chartmousedown = false;
				if (this.chartMousemoveListener) {
					window.removeEventListener('mousemove', this.chartMousemoveListener);
					this.chartMousemoveListener = null;
				}
				this.chartMouseupListener = null;

				if (!this.isEventWithinChartArea(event)) {
					this.clearHover();
				}
			};

			window.addEventListener('mouseup', this.chartMouseupListener, { once: true });
		},
		isEventWithinChartArea(event) {
			const chartAreaRect = this.$refs.chartAreaRef?.getBoundingClientRect();

			if (!chartAreaRect || !event) {
				return false;
			}

			return (
				event.clientX >= chartAreaRect.left &&
				event.clientX <= chartAreaRect.right &&
				event.clientY >= chartAreaRect.top &&
				event.clientY <= chartAreaRect.bottom
			);
		},
		hoverNfkValueForEntry(entry) {
			if (!entry || this.hoverValue == null) {
				return null;
			}

			return Math.round(this.soilNfk(entry, this.hoverValue));
		},
		hoverNfkColorForEntry(entry) {
			const hoverNfkValue = this.hoverNfkValueForEntry(entry);

			if (hoverNfkValue == null) {
				return 'inherit';
			}

			return dataModel.get_nfk_color(hoverNfkValue);
		},
		hoverNfkStageLabelForEntry(entry) {
			const hoverNfkValue = this.hoverNfkValueForEntry(entry);

			if (hoverNfkValue == null) {
				return '-';
			}

			return dataModel.get_nfk_label(hoverNfkValue);
		},
		shouldShowHoverColumn(index) {
			if (!this.showHoverChart || this.hoverPos < 0 || this.hoverValue == null) {
				return false;
			}

			if (this.chartmousedown) {
				return true;
			}

			if (this.hoverChartsMousedownOnly) {
				return false;
			}

			return this.hoverColumnIndex === index;
		},
		handleChartMouseLeave() {
			if (this.chartmousedown) {
				return;
			}

			this.clearHover();
		},
		updateHoverFromEvent(event) {
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
			const hoverEntry = this.hoveredEntryAtX(relativeX);

			this.hoverValue = hoverValue;
			this.hoverPos = hoverPos;
			this.hoverEntry = hoverEntry;
		},
		handleHoverMove(event) {
			this.updateHoverFromEvent(event);
		},
		clearHover() {
			this.hoverValue = null;
			this.hoverPos = -1;
			this.hoverEntry = null;
		},
		hoveredEntryAtX(relativeX) {
			if (relativeX < this.sidePad || relativeX > this.chartWidth - this.sidePad || !this.chartSeries.length) {
				return null;
			}

			const stepWidth = this.chartColumnWidth + this.chartGap;
			if (stepWidth <= 0 || this.chartColumnWidth <= 0 || this.innerChartWidth <= 0) {
				return null;
			}

			const innerX = relativeX - this.sidePad;
			const safeX = Math.min(innerX, Math.max(0, this.innerChartWidth - 0.0001));
			const columnIndex = Math.floor(safeX / stepWidth);
			return this.chartSeries[columnIndex] || null;
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

			this.chartSeries.forEach(entry => {
				const canvas = document.createElement('canvas');
				canvas.width = 1;
				canvas.height = this.chartHeight;
				const ctx = canvas.getContext('2d');

				if (!ctx) {
					return;
				}

				ctx.clearRect(0, 0, 1, this.chartHeight);

				for (let y = 0; y < this.chartHeight; y += 1) {
					const value = this.yToValue(y + 0.5);
					const nfk = this.soilNfk(entry, value);
					ctx.fillStyle = dataModel.get_nfk_color(nfk);
					ctx.fillRect(0, y, 1, 1);
				}

				nextImages[entry.short] = canvas.toDataURL();
			});

			this.chartImages = nextImages;
		},
		textureRangeLabel(entry, type) {
			const range = entry?.textur?.[type];
			if (!range) {
				return '-';
			}

			const min = typeof range.min === 'number' ? range.min : dataModel.getTexturWeight(range);
			const max = typeof range.max === 'number' ? range.max : min;
			return `${min} – ${max} %`;
		},
		textureBarStyle(entry, type) {
			const range = entry?.textur?.[type];
			const avg = Math.max(0, Math.min(100, dataModel.getTexturWeight(range)));
			const backgroundColor = dataModel.soilColors[type] || 'transparent';

			return {
				width: avg + '%',
				backgroundColor
			};
		},
		textureLineSegmentStyle(entry, type) {
			const range = entry?.textur?.[type];
			const avg = Math.max(0, Math.min(100, dataModel.getTexturWeight(range)));
			const backgroundColor = dataModel.soilColors[type] || 'transparent';

			return {
				flexGrow: avg,
				backgroundColor
			};
		}
		
	},
	watch: {
		chartSeries() {
			this.$nextTick(() => {
				this.updateChartMetrics();
				this.renderChartImages();
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
		if (this.chartMousemoveListener) {
			window.removeEventListener('mousemove', this.chartMousemoveListener);
			this.chartMousemoveListener = null;
		}
		if (this.chartMouseupListener) {
			window.removeEventListener('mouseup', this.chartMouseupListener);
			this.chartMouseupListener = null;
		}
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
	padding-bottom 0
	margin-top 36px
	display flex
	flex-direction column
	background #fff
	user-select none
	position relative
	.headerwrapper
		display flex
		align-items center
		width 100%
		gap 0px
		&:before
		&:after
			content ''
			flex 1
			height 1px
			border-bottom var(--thinline)
	h3
		color var(--menusectionheadercolor)
		color #000
		font-size 10pt
		text-align center
		display inline-block
		flex 0 0 auto
		margin 0
		padding 0 6px
		background #fff
	.chart-area-container
		display block
		position relative
		width 100%
		z-index 1
		.chart-area
			position absolute
		.axis
			position absolute
			left 0
			right 0
			.axis-line
				position absolute
				left 0
				right 0
				height 1px
				background #00000011
			.label
				position absolute
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
				.label
					margin-left -2px
					transform none
					margin-bottom 1px

.chart-columns
	display grid
	align-items start
	min-width 0
	user-select none
	.chart-column
		height 100%
		position relative
		user-select none
		pointer-events none
		&.zeroVol
			border-bottom 1px solid #00000033
		.column-container
		.column-container-hover
			position absolute
			inset 0
		.column-fk
		.column-hover
			position absolute
			display block
			left 0
			width 100%
			bottom 0
			background #ddd
			border-radius 4px
			border-radius 4px 4px 0 0
			overflow hidden
			// filter: drop-shadow(0 2px 2px rgba(0,0,0,.22));
			box-shadow 0 2px 2px rgba(0,0,0,.22)
		.column-hover
			min-height 0.5px
		.column-image
			position absolute
			bottom 0
			left 0
			width 100%

// .area-fk
.area-sw
.area-tw
	position absolute
	bottom 0
	left 0
	width 100%
	opacity .5
	background-repeat repeat
	background-position bottom left
	background-size 6px 6px
	overflow hidden

.area-tw
	// border-top 1px solid #aa000044
	background-image url(/img/totwasser4.png)
	border-bottom-left-radius 4px
	border-bottom-right-radius 4px

.column-hover .area-tw
	opacity .5
.hoverBottom .area-tw
	opacity 0
	
// .area-fk
// 	border-top 1px solid #fff
// 	border-top-left-radius 6px
// 	border-top-right-radius 6px

.area-sw
	position absolute
	top 0
	left 0
	width 100%
	opacity 1
	// opacity .4
	// background #fff
	border-bottom 1px dashed #00000044
	// background-image url(/img/sickerwasser5.png)
	background-repeat repeat
	background-position bottom left
	background-size 8px 8px
	border-radius 0

.label-tw
.label-fk
.label-nfk
	position absolute
	left 0
	width 100%
	padding 1px 3px
	text-align right
	.value
		font-size 9.5pt
		opacity .8
		margin-left .3em
		margin-right .1em
	.label
	.unit
		color #00000099
		font-size 7.5pt

.label-vol
	position absolute
	display block
	left 100%
	padding 0
	margin-left 12px
	text-align left
	z-index 3
	overflow hidden
	width 42px
	border-bottom 1px solid #00000020
	margin-bottom -1px
	padding-bottom 2px
	white-space nowrap
	transition opacity .2s linear
	opacity 1
	.value
		font-size 7.5pt
		opacity .8
		margin-right .2em
	.unit
		color #00000099
		font-size 7.5pt


.label-fk
	padding 1px 2px

.label-nfk
	bottom 0
	overflow hidden
	text-align left
	transition opacity .2s linear
	opacity 1
	.value
		font-size 7.5pt

.hoverTop .label-nfk
.hoverBottomBounds .label-nfk
.hoverTop .label-vol
	opacity 0

.label-nfk
	.value
		opacity .8
		font-size 7.5pt

.soilstatus
	font-weight bold
	font-size 7.5pt
	margin 0 
	letter-spacing 0.01em;
	// background red
	display block
	position absolute
	height auto
	width 100%
	left 0
	text-transform uppercase
	text-align left
	> *
		padding 2px 4px
		bottom 0
		left 0
		width 100%
	.nameoverlay
		position absolute
		opacity .1
		color #000



.chart-info-container
	display grid
	grid-template-rows auto auto
	align-items stretch
	font-size 7.5pt
	min-width 0
	.chart-info-column
		display grid
		grid-template-rows subgrid
		grid-row span 2
		border-radius 0
		user-select none
		position relative
		.chart-column-header
			display flex
			flex-direction column
			align-items center
			justify-content flex-start
			align-self start
			min-height 100%
			cursor pointer
			padding 9px 1px 6px
			border-radius 0 0 8px 8px
			transition background-color .12s linear
			&:hover
				background var(--activecolorgreybrightest)
			&.active:hover
			&.active
				background var(--activecolorgreymid)
		.chart-name
			text-align center
			font-weight bold
			margin-top 2px

.chart-additional-info
	display block
	margin-top 6px

.humusMode .chart-property-info
	max-width 100px

.chart-property-info
	display block
	font-size 7.5pt
	color #000000aa
	margin 8px auto 24px
	background #f8f8f8
	max-width 90px
	width 95%
	border-radius 4px
	border 1px solid #00000014
	overflow hidden
	box-shadow 0 1px 2px 0 #00000014
	cursor pointer
	transition background-color .12s linear
	background var(--activecolorgreybrightest)
	.texture-row
		display flex
		margin 0
		position relative
		height 18px
		box-sizing content-box
		border-bottom 1px solid #00000018
		&:last-child
			border-bottom none
		.texture-bar-container
		.texture-text
			position absolute
			inset 0
		.texture-bar-container
			top 0
			left 50%
			right 0
		.texture-bar
			position absolute
			top 0
			left 0
			bottom 0
			opacity 1
		.texture-text
			display flex
			align-items center
			justify-content space-between
			padding 0 3px
		.texture-name
			text-align left
		.texture-values
			text-align right
			white-space nowrap
			display none
	&.showValues
		.texture-bar-container
			display none
		.texture-values
			display block
	&.staticRows
		cursor default

.chart-textures-line
	display flex
	margin 0 6px 6px
	.texture-line-segment
		height 5px
		flex-direction row

.chart-current-soil-wrap
	position relative
	width 184px
	margin 0 auto
	height 0
	// width 100%
	// border-top 6px solid var(--activecolorgreymid)


// .humusMode .chart-info-container
// 	background var(--activecolorgreybrighter)
// 	border-radius 0 0 8px 8px
// 	overflow hidden

.chart-current-soil
	display flex
	align-items center
	justify-content flex-start
	gap 6px
	font-weight bold
	border-radius 999px
	user-select none
	margin 4px 0 0
	padding 2px 2px 2px
	padding-right 16px
	width 184px
	left 50%
	transform translate(-50%,0)
	z-index 10
	position absolute
	cursor pointer
	box-shadow 0 1px 1px rgba(0,0,0,.18)
	background #fff
	.icon
		position relative
		top 0.25px
	.bg
		transition background-color .12s linear
		position absolute
		background var(--activecolorgreybrightest)
		border-radius 999px
		inset 0
	&:hover .bg
		background var(--activecolorgreybrighter)
	&:after
		content ''
		position absolute
		right 10px
		top 50%
		transform translate(0,-40%)
		opacity .7
		border-left 6px solid transparent
		border-right 6px solid transparent
		border-top 9px solid black

</style>
