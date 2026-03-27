<template>

	<div class="timeline" :class="[{selected}, {showdepths: showDepths}]">

		<img v-if="heatmapImageUrl" class="heatmap-image" :src="heatmapImageUrl" alt="" draggable="false">

	</div>

</template>

<script>
import { state } from '@/state.js';
import { config } from '@/config.js';
import { dataModel } from '@/dataModel.js'
import dataStore from '@/datastore.js';

const timelineImageCache = new Map();

function buildTimelineImageCacheKey({
	deviceId,
	telemetryFingerprint,
	startTimestamp,
	endTimestamp,
	showDepths,
	showDataGaps,
	colorScheme,
}) {
	return [
		deviceId ?? 'none',
		telemetryFingerprint ?? 'empty',
		startTimestamp ?? 0,
		endTimestamp ?? 0,
		showDepths ? 1 : 0,
		showDataGaps ? 1 : 0,
		colorScheme ?? 'normal',
	].join('|');
}

export default {
	name: 'TimelineInner',
	setup() {
		return {
			state,
		}
	},
	data() {
		return {
			telemetryData: null,
			heatmapSegments: null,
			heatmapNumBands: 1,
			heatmapImageUrl: null,
			heatmapRenderRaf: null,
			deferredRenderFrameA: null,
			deferredRenderFrameB: null,
			deferredRenderIdle: null,
			deferredRenderTimeout: null,
			telemetryFingerprint: 'empty',
		};
	},
	props: {
		device: Object,
		startTimestamp: Number,
		endTimestamp: Number,
		chartWidth: Number,
		selected: Boolean,
		timelineWidth: Number,
	},
	computed: {
		schema() {
			return this.device?.telemetrySchema?.schema || [];
		},
		nfkavg_index() {
			const i = this.schema.indexOf('nfk_avg');
			return (i >= 0) ? i : null;
		},
		nfk10_index() {
			const i = this.schema.indexOf('nfk_10cm');
			return (i >= 0) ? i : null;
		},
		nfk30_index() {
			const i = this.schema.indexOf('nfk_30cm');
			return (i >= 0) ? i : null;
		},
		nfk60_index() {
			const i = this.schema.indexOf('nfk_60cm');
			return (i >= 0) ? i : null;
		},
		nfk80_index() {
			const i = this.schema.indexOf('nfk_80cm');
			return (i >= 0) ? i : null;
		},
		showDepths() {
			return state.tableview_showdepths;
		},
		cacheKey() {
			return buildTimelineImageCacheKey({
				deviceId: this.device?.id,
				telemetryFingerprint: this.telemetryFingerprint,
				startTimestamp: this.startTimestamp,
				endTimestamp: this.endTimestamp,
				showDepths: this.showDepths,
				showDataGaps: state.showDataGaps,
				colorScheme: state.colorScheme,
			});
		},
	},
	methods: {
		clearDeferredRender() {
			if (this.deferredRenderFrameA != null) {
				cancelAnimationFrame(this.deferredRenderFrameA);
				this.deferredRenderFrameA = null;
			}
			if (this.deferredRenderFrameB != null) {
				cancelAnimationFrame(this.deferredRenderFrameB);
				this.deferredRenderFrameB = null;
			}
			if (this.deferredRenderIdle != null && typeof cancelIdleCallback === 'function') {
				cancelIdleCallback(this.deferredRenderIdle);
				this.deferredRenderIdle = null;
			}
			if (this.deferredRenderTimeout != null) {
				clearTimeout(this.deferredRenderTimeout);
				this.deferredRenderTimeout = null;
			}
		},
		queueRenderHeatmapImage({ defer = false } = {}) {
			this.clearDeferredRender();
			if (this.heatmapRenderRaf != null) {
				cancelAnimationFrame(this.heatmapRenderRaf);
			}

			if (defer && !timelineImageCache.has(this.cacheKey)) {
				this.deferredRenderFrameA = requestAnimationFrame(() => {
					this.deferredRenderFrameA = null;
					this.deferredRenderFrameB = requestAnimationFrame(() => {
						this.deferredRenderFrameB = null;
						if (typeof requestIdleCallback === 'function') {
							this.deferredRenderIdle = requestIdleCallback(() => {
								this.deferredRenderIdle = null;
								this.queueRenderHeatmapImage();
							}, { timeout: 180 });
							return;
						}
						this.deferredRenderTimeout = setTimeout(() => {
							this.deferredRenderTimeout = null;
							this.queueRenderHeatmapImage();
						}, 0);
					});
				});
				return;
			}

			this.heatmapRenderRaf = requestAnimationFrame(() => {
				this.heatmapRenderRaf = null;
				this.renderHeatmapImage();
			});
		},
		rebuildHeatmapSegments() {
			if (!this.telemetryData || this.telemetryData.length === 0) {
				this.heatmapSegments = [];
				this.heatmapNumBands = this.showDepths ? 0 : 1;
				return;
			}

			const rows = this.telemetryData;
			const msPerDay = 24 * 60 * 60 * 1000;
			const limitGapLength = state.showDataGaps;

			if (this.showDepths) {
				const depthIndices = [this.nfk10_index, this.nfk30_index, this.nfk60_index, this.nfk80_index]
					.filter(index => index !== null);
				const segments = [];

				for (let bandIdx = 0; bandIdx < depthIndices.length; bandIdx++) {
					const dataIndex = depthIndices[bandIdx];
					let nextTs = null;

					for (let i = rows.length - 1; i >= 0; i--) {
						const row = rows[i];
						const ts = row[0];
						const nfk = row[dataIndex];
						if (nfk == null) continue;

						let endTs = nextTs ?? (ts + msPerDay);
						if (limitGapLength && (endTs - ts) > config.dataGapLength) endTs = ts + msPerDay;

						segments.push({ startTs: ts, endTs, nfk, bandIdx });
						nextTs = ts;
					}
				}

				this.heatmapSegments = segments;
				this.heatmapNumBands = depthIndices.length;
				return;
			}

			const avgIndex = this.nfkavg_index;
			const segments = [];

			if (avgIndex !== null) {
				for (let i = 0; i < rows.length; i++) {
					const row = rows[i];
					const ts = row[0];
					const nfk = row[avgIndex];
					if (nfk == null) continue;

					let endTs = (i < rows.length - 1) ? rows[i + 1][0] : ts + msPerDay;
					if (limitGapLength && (endTs - ts) > config.dataGapLength) endTs = ts + msPerDay;

					segments.push({ startTs: ts, endTs, nfk, bandIdx: 0 });
				}
			}

			this.heatmapSegments = segments;
			this.heatmapNumBands = 1;
		},
		renderHeatmapImage() {
			if (!state.telemetryLoaded || !this.heatmapSegments || this.heatmapSegments.length === 0) {
				this.heatmapImageUrl = null;
				return;
			}

			const cachedImageUrl = timelineImageCache.get(this.cacheKey);
			if (cachedImageUrl) {
				this.heatmapImageUrl = cachedImageUrl;
				return;
			}

			const msPerDay = 24 * 60 * 60 * 1000;
			const width = Math.max(1, Math.ceil((this.endTimestamp - this.startTimestamp) / msPerDay) + 1);
			const numBands = Math.max(1, this.heatmapNumBands || 1);
			const canvas = this._heatmapCanvas || (this._heatmapCanvas = document.createElement('canvas'));
			canvas.width = width;
			canvas.height = numBands;

			const ctx = canvas.getContext('2d');
			ctx.setTransform(1, 0, 0, 1, 0, 0);
			ctx.clearRect(0, 0, width, numBands);

			const visibleEndTs = this.endTimestamp + msPerDay;

			for (let i = 0; i < this.heatmapSegments.length; i++) {
				const segment = this.heatmapSegments[i];
				const clippedStartTs = Math.max(segment.startTs, this.startTimestamp);
				const clippedEndTs = Math.min(segment.endTs, visibleEndTs);
				if (clippedEndTs <= clippedStartTs) continue;

				const startDay = Math.max(0, Math.floor((clippedStartTs - this.startTimestamp) / msPerDay));
				const endDay = Math.min(width, Math.ceil((clippedEndTs - this.startTimestamp) / msPerDay));
				const segW = Math.max(1, endDay - startDay);

				ctx.fillStyle = dataModel.get_nfk_color(segment.nfk);
				ctx.fillRect(startDay, segment.bandIdx, segW, 1);
			}

			const imageUrl = canvas.toDataURL();
			timelineImageCache.set(this.cacheKey, imageUrl);
			this.heatmapImageUrl = imageUrl;
		},
		fetchTelemetry() {
			if (!this.device) {
				this.telemetryData = null;
				this.heatmapSegments = [];
				this.heatmapNumBands = this.showDepths ? 0 : 1;
				this.heatmapImageUrl = null;
				this.telemetryFingerprint = 'empty';
				return;
			}
			this.telemetryData = dataStore.fetchTelemetryCache(this.device.id).data;
			const rows = this.telemetryData || [];
			const firstTs = rows.length ? rows[0][0] : 0;
			const lastTs = rows.length ? rows[rows.length - 1][0] : 0;
			this.telemetryFingerprint = [
				rows.length,
				firstTs,
				lastTs,
				this.schema.join(','),
			].join(':');
			this.rebuildHeatmapSegments();
		}

	},
	watch: {
		startTimestamp() {
			this.queueRenderHeatmapImage();
		},
		endTimestamp() {
			this.queueRenderHeatmapImage();
		},
		'state.telemetryLoaded'() {
			this.fetchTelemetry();
			this.queueRenderHeatmapImage({ defer: true });
		},
		'state.showDataGaps'() {
			this.rebuildHeatmapSegments();
			this.queueRenderHeatmapImage();
		},
		'state.colorScheme'() {
			this.queueRenderHeatmapImage();
		},
		showDepths() {
			this.rebuildHeatmapSegments();
			this.queueRenderHeatmapImage();
		},
		device() {
			this.fetchTelemetry();
			this.queueRenderHeatmapImage({ defer: true });
		}

	},
	mounted() {
		this.fetchTelemetry();
		this.queueRenderHeatmapImage({ defer: true });
	},
	beforeUnmount() {
		if (this.heatmapRenderRaf != null) cancelAnimationFrame(this.heatmapRenderRaf);
		this.clearDeferredRender();
	},
};
</script>

<style lang="stylus" scoped>
	
	.timeline
		position absolute
		display block
		width 100%
		height 100%
		// height calc(100%)
		// margin-top -1px
		user-select none
		overflow hidden
		.heatmap-image
			display block
			width 100%
			height 100%
			image-rendering pixelated
		&.showdepths 
			height 100%
			.heatmap-image
				height 100%
		.hoverline
			border-left 1px dotted #000000
			top -1px
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
