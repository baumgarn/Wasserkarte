<template>

	<div class="timeline" ref="timeline" @mousemove="hover" @mouseleave="hoverOut" @touchstart="hover" @touchmove="hover" @touchend="hoverOut" @touchcancel="hoverOut">

		<img v-if="heatmapImageUrl" class="heatmap-image" :src="heatmapImageUrl" alt="" draggable="false">

		<div class="hoverline" v-if="(hoverLinePosition > 0)" :style="{ left: (hoverLinePosition ) + 'px' }"></div>

		<div class="timelinehovertriangle" v-if="showDate" :style="{ left: (hoverLinePosition - 1 ) + 'px' }"></div>

		<div class="hoverdate" v-if="showDate" :style="{ left: (hoverDatePosition ) + 'px', width: hoverDateWidth + 'px' }">
			{{ formattedHoverDate }}
		</div>

		<div
			class="selectedDeviceArea"
			v-if="selectedDeviceWidth > 0"
			:style="{ left: selectedDeviceStartPos + 'px', width: selectedDeviceWidth + 'px' }"></div>

		<DateAxis
		v-if="dateAxis"
		:chartWidth="timelineWidth"
		:frameWidth="timelineWidth"
		:startTimestamp="startTimestamp"
		:numberOfDays
		:insideTimeline="true"
		:firstItemPadding
		:monthsOnly="true"
		></DateAxis>

		<div v-if="label" class="label">{{ label }}</div>

	</div>

</template>

<script>
import { state } from '@/state.js';
import { dataModel } from '@/dataModel.js'
import dataStore from '@/datastore.js';
import { displayutil } from '@/displayutil.js'
import DateAxis from '@/charts/dateaxis.vue'

const timelineImageCache = new Map();
const LEVELS_IMAGE_HEIGHT = 64;
const NFK_AVG_PIXELS_PER_DAY = 2;
const LEVELS_PIXELS_PER_DAY = 4;
const DAY_MS = 24 * 60 * 60 * 1000;

function buildTimelineImageCacheKey({
	dailyAveragesFingerprint,
	startTimestamp,
	endTimestamp,
	timelineStyle,
	colorScheme,
}) {
	return [
		dailyAveragesFingerprint ?? 'empty',
		startTimestamp ?? 0,
		endTimestamp ?? 0,
		timelineStyle ?? 'nfk_avg',
		colorScheme ?? 'normal',
	].join('|');
}

export default {
	name: 'TimelineInner',
	components: { DateAxis },
	setup() {
		return {
			state, displayutil,
		}
	},
	data() {
		return {
			selectedDeviceTelemetry: null,
			hoverPosition: -1,
			hoverDateWidth: 70,
			timelineWidth: 0,
			heatmapImageUrl: null,
			heatmapRenderRaf: null,
			dailyAveragesFingerprint: 'empty',
			earliestTimestamp: 0,
			latestTimestamp: 0,
			selectedDeviceStartDate: 0,
			selectedDeviceEndDate: 0,
			selectedDeviceStartPos: 0,
			selectedDeviceWidth: 0,
		};
	},
	props: {
		dailyAverages: Object,
		startTimestamp: Number,
		endTimestamp: Number,
		dateAxis: { type: Boolean, default: true },
		firstItemPadding: { type: Boolean, default: false },
		label: { type: String, default: '' },
	},
	computed: {
		device() {
			return dataStore.getDeviceByName(state.selectedDevice);
		},
		numberOfDays() {
			return (this.endTimestamp - this.startTimestamp) / (1000 * 60 * 60 * 24);
		},
		timelineSpan() {
			return Math.max(1, this.endTimestamp - this.startTimestamp);
		},
		showDate() {
			return (this.hoverPosition > -1 && this.hoverLinePosition > 0)
		},
		timelineDate() {
			let ts = null;
			if (this.hoverPosition > -1) {
				const fraction = this.hoverPosition / Math.max(1, this.timelineWidth - 1);
				ts = this.startTimestamp + fraction * this.timelineSpan;
				ts = dataStore.floorToMidnight(ts);
				if (ts < this.earliestTimestamp) ts = this.earliestTimestamp;
				if (ts > this.latestTimestamp) ts = this.latestTimestamp;
				if (ts > this.endTimestamp) ts = this.endTimestamp;

				state.timelineDate = ts;
				return state.timelineDate;
			} else if (state.timelineDate && this.hoverPosition < 0) {
				return state.timelineDate;
			}
			return null;
		},
		hoverLinePosition() {
			let pos;
			if (this.hoverPosition > -1) {
				pos = this.hoverPosition;
				if (this.earliestTimestamp && this.latestTimestamp) {
					const msPerDay = 24 * 60 * 60 * 1000;
					const earliestX = ((this.earliestTimestamp - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
					const latestX = ((this.latestTimestamp + msPerDay - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
					if (pos < earliestX) pos = earliestX;
					if (pos > latestX) pos = latestX;
				}
			} else if (state.timelineDate && this.hoverPosition < 0 && state.timelineDate > this.startTimestamp && state.timelineDate < this.endTimestamp) {
				const fraction = (state.timelineDate - this.startTimestamp) / this.timelineSpan;
				pos = fraction * this.timelineWidth;
			} else {
				pos = -1;
			}
			return pos;
		},
		hoverDatePosition() {
			let pos = this.hoverLinePosition - (this.hoverDateWidth / 2);
			if (pos < 0) pos = 0;
			if (pos > this.timelineWidth - this.hoverDateWidth) pos = this.timelineWidth - this.hoverDateWidth;
			return pos;
		},
		formattedHoverDate() {
			return displayutil.formatDateAggregated(this.timelineDate);
		},
		cacheKey() {
			return buildTimelineImageCacheKey({
				dailyAveragesFingerprint: this.dailyAveragesFingerprint,
				startTimestamp: this.startTimestamp,
				endTimestamp: this.endTimestamp,
				timelineStyle: state.timelineStyle,
				colorScheme: state.colorScheme,
			});
		},
	},
	methods: {
		getPixelsPerDay() {
			return state.timelineStyle === 'levels' ? LEVELS_PIXELS_PER_DAY : NFK_AVG_PIXELS_PER_DAY;
		},
		updateTimelineMetrics() {
			const timeline = this.$refs.timeline;
			if (!timeline) return;
			this.timelineWidth = timeline.getBoundingClientRect().width;
			this.updateSelectedDevicePositions();
		},
		buildDailyAveragesFingerprint(rows) {
			if (!rows?.length) return 'empty';
			let hash = 0;
			for (let i = 0; i < rows.length; i++) {
				const row = rows[i];
				hash = (hash * 33 + (row.nfk_avg ?? -999)) % 2147483647;
				const levels = row.nfk_level || [];
				for (let j = 0; j < levels.length; j++) {
					hash = (hash * 33 + (levels[j] || 0)) % 2147483647;
				}
			}
			return [
				rows.length,
				rows[0].ts,
				rows[rows.length - 1].ts,
				hash,
			].join(':');
		},
		queueRenderHeatmapImage() {
			if (this.heatmapRenderRaf != null) {
				cancelAnimationFrame(this.heatmapRenderRaf);
			}
			this.heatmapRenderRaf = requestAnimationFrame(() => {
				this.heatmapRenderRaf = null;
				this.renderHeatmapImage();
			});
		},
		renderHeatmapImage() {
			const rows = this.dailyAverages;
			if (!state.telemetryLoaded || !rows?.length) {
				this.heatmapImageUrl = null;
				return;
			}

			const cachedImageUrl = timelineImageCache.get(this.cacheKey);
			if (cachedImageUrl) {
				this.heatmapImageUrl = cachedImageUrl;
				return;
			}

			const msPerDay = 24 * 60 * 60 * 1000;
			const pixelsPerDay = this.getPixelsPerDay();
			const width = Math.max(1, Math.ceil(this.numberOfDays * pixelsPerDay));
			const isLevels = state.timelineStyle === 'levels';
			const height = isLevels ? LEVELS_IMAGE_HEIGHT : 1;
			const canvas = this._heatmapCanvas || (this._heatmapCanvas = document.createElement('canvas'));
			canvas.width = width;
			canvas.height = height;

			const ctx = canvas.getContext('2d');
			ctx.setTransform(1, 0, 0, 1, 0, 0);
			ctx.clearRect(0, 0, width, height);

			if (isLevels) {
				const labels = dataModel.nfk_labels;
				const levelColors = labels.map((label, index) => {
					if (index === 0) return dataModel.get_nfk_color(0);
					const prevValue = labels[index - 1].value;
					const midpoint = prevValue + (label.value - prevValue) / 2;
					return dataModel.get_nfk_color(midpoint);
				});

				const rowPercentages = rows.map(row => {
					if (!row.nfk_level?.length) return null;
					const total = row.nfk_level.reduce((sum, value) => sum + value, 0);
					if (!total) return null;
					return row.nfk_level.map(value => value / total);
				});

				for (let i = 0; i < rows.length; i++) {
					const row = rows[i];
					const nextRow = i < rows.length - 1 ? rows[i + 1] : null;
					const nextTs = nextRow ? nextRow.ts : row.ts + msPerDay;
					const leftPerc = rowPercentages[i];
					const rightPerc = nextRow ? rowPercentages[i + 1] : leftPerc;
					if (!leftPerc) continue;

					const startRel = row.ts - this.startTimestamp;
					const endRel = nextTs - this.startTimestamp;
					const startX = Math.max(0, Math.floor((startRel / this.timelineSpan) * width));
					const endX = Math.min(width, Math.ceil((endRel / this.timelineSpan) * width));

					for (let x = startX; x < endX; x++) {
						const t = rightPerc && endX > startX ? (x - startX) / (endX - startX) : 0;
						const percentages = rightPerc && rightPerc !== leftPerc
							? leftPerc.map((v1, idx) => v1 * (1 - t) + rightPerc[idx] * t)
							: leftPerc;

						let currentY = height;
						for (let levelIndex = 0; levelIndex < percentages.length; levelIndex++) {
							const percentage = percentages[levelIndex];
							if (percentage <= 0) continue;
							const segmentHeight = percentage * height;
							ctx.fillStyle = levelColors[levelIndex];
							ctx.fillRect(x, currentY - segmentHeight, 1, segmentHeight);
							currentY -= segmentHeight;
						}
					}
				}
			} else {
				for (let i = 0; i < rows.length; i++) {
					const row = rows[i];
					const nextTs = i < rows.length - 1 ? rows[i + 1].ts : row.ts + msPerDay;
					if (row.nfk_avg == null) continue;

					const startRel = row.ts - this.startTimestamp;
					const endRel = nextTs - this.startTimestamp;
					const startX = Math.max(0, Math.floor((startRel / this.timelineSpan) * width));
					const endX = Math.min(width, Math.ceil((endRel / this.timelineSpan) * width));
					const segW = Math.max(1, endX - startX);

					ctx.fillStyle = dataModel.get_nfk_color(row.nfk_avg);
					ctx.fillRect(startX, 0, segW, 1);
				}
			}

			const imageUrl = canvas.toDataURL();
			timelineImageCache.set(this.cacheKey, imageUrl);
			this.heatmapImageUrl = imageUrl;
		},
		hover(event) {
			if (event.type === 'mousemove' && this._lastTouchEventAt && Date.now() - this._lastTouchEventAt < 700) {
				return;
			}

			const rect = this.$refs.timeline.getBoundingClientRect();
			if (event.type === 'mousemove') {
				this.hoverPosition = event.clientX - rect.left;
			} else if (event.type === 'touchmove' || event.type === 'touchstart') {
				this._lastTouchEventAt = Date.now();
				if (!event.touches?.length) {
					this.hoverOut();
					return;
				}
				this.hoverPosition = event.touches[0].clientX - rect.left;
			}
		},
		hoverOut(event) {
			if (event?.type?.startsWith('touch')) {
				this._lastTouchEventAt = Date.now();
			}
			this.hoverPosition = -1;
			state.timelineDate = null;
		},
		toTimelineX(ts) {
			if (!this.timelineWidth || !this.timelineSpan) return 0;
			return ((ts - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
		},
		updateSelectedDevicePositions() {
			if (!this.selectedDeviceTelemetry?.data?.length) {
				this.selectedDeviceStartPos = 0;
				this.selectedDeviceWidth = 0;
				return;
			}
			const startPos = this.toTimelineX(this.selectedDeviceStartDate);
			const endPos = this.toTimelineX(this.selectedDeviceEndDate);
			const clippedStart = Math.max(0, Math.min(this.timelineWidth, startPos));
			const clippedEnd = Math.max(0, Math.min(this.timelineWidth, endPos));

			this.selectedDeviceStartPos = clippedStart;
			this.selectedDeviceWidth = Math.max(0, clippedEnd - clippedStart);
		},
		processDailyAverages() {
			const rows = this.dailyAverages || [];
			if (!rows.length) {
				this.dailyAveragesFingerprint = 'empty';
				this.earliestTimestamp = 0;
				this.latestTimestamp = 0;
				this.heatmapImageUrl = null;
				return;
			}

			this.earliestTimestamp = rows[0].ts;
			this.latestTimestamp = rows[rows.length - 1].ts;
			this.dailyAveragesFingerprint = this.buildDailyAveragesFingerprint(rows);
			this.updateTimelineMetrics();
			this.queueRenderHeatmapImage();
		},
		updateSelectedDeviceTelemetry() {
			if (this.device) {
				this.selectedDeviceTelemetry = dataStore.fetchTelemetryCache(this.device.id);
				if (this.selectedDeviceTelemetry?.data?.length) {
					this.selectedDeviceStartDate = dataStore.floorToMidnight(this.selectedDeviceTelemetry.data[0][0]);
					this.selectedDeviceEndDate =
						dataStore.floorToMidnight(this.selectedDeviceTelemetry.data[this.selectedDeviceTelemetry.data.length - 1][0]) + DAY_MS;
					this.updateSelectedDevicePositions();
					return;
				}
			}
			this.selectedDeviceTelemetry = null;
			this.selectedDeviceWidth = 0;
		},
	},
	watch: {
		dailyAverages() {
			this.processDailyAverages();
		},
		startTimestamp() {
			this.updateTimelineMetrics();
			this.queueRenderHeatmapImage();
			this.updateSelectedDevicePositions();
		},
		endTimestamp() {
			this.updateTimelineMetrics();
			this.queueRenderHeatmapImage();
			this.updateSelectedDevicePositions();
		},
		'state.telemetryLoaded'() {
			this.processDailyAverages();
		},
		'state.colorScheme'() {
			this.queueRenderHeatmapImage();
		},
		'state.timelineStyle'() {
			this.queueRenderHeatmapImage();
		},
		device() {
			this.updateSelectedDeviceTelemetry();
		}

	},
	mounted() {
		this.processDailyAverages();
		this.updateSelectedDeviceTelemetry();
		this.updateTimelineMetrics();
		this._boundHoverOut = this.hoverOut.bind(this);
		window.addEventListener('touchend', this._boundHoverOut, { passive: true });
		window.addEventListener('touchcancel', this._boundHoverOut, { passive: true });
		window.addEventListener('blur', this._boundHoverOut);
		this._timelineResizeObserver = new ResizeObserver(() => {
			this.updateTimelineMetrics();
		});
		if (this.$refs.timeline) this._timelineResizeObserver.observe(this.$refs.timeline);
	},
	beforeUnmount() {
		if (this._timelineResizeObserver) this._timelineResizeObserver.disconnect();
		if (this.heatmapRenderRaf != null) cancelAnimationFrame(this.heatmapRenderRaf);
		window.removeEventListener('touchend', this._boundHoverOut);
		window.removeEventListener('touchcancel', this._boundHoverOut);
		window.removeEventListener('blur', this._boundHoverOut);
	},
};
</script>

<style lang="stylus" scoped>
	
	.timeline
		position relative
		display block
		width 100%
		height var(--timelineheight);
		user-select none
		.heatmap-image
			position absolute
			inset 0
			display block
			width 100%
			height 100%
			max-width none
			object-fit fill
			pointer-events none
			background #eaeaea
			image-rendering pixelated
		.hoverline
			border-left 1px dotted #000000
			top -1px
		.hoverdate
			position absolute
			bottom calc(100% + 8px)
			font-size 8pt
			opacity 1
			pointer-events none
			background var(--infobg)
			backdrop-filter: blur(20px);
			color #fff
			text-align center
			padding 3px 0 2px
			white-space nowrap
			border-radius 6px
			z-index 100
			overflow visible
		.timelinehovertriangle
			margin-left -4.5px
			top -8px
			position absolute
			border-top 8px solid var(--infobg)
			border-left 6px solid transparent
			border-right 6px solid transparent
			opacity 1
			z-index 101
		.selectedDeviceArea
			position absolute
			top 0
			height 2px
			pointer-events none
			background rgba(125,125,125,0.6)
			z-index 2
			display none
		.label
			position absolute
			left 0.5vw
			top 0
			top 10%
			display flex
			align-items center
			font-size 10px
			font-size 11px
			font-weight bold
			color #000000aa
			opacity .6
			color #000
			user-select none
		.chart-time
			position absolute
			bottom 0
			top unset
			left 0
</style>
