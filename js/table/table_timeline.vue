<template>

	<div class="timeline" :class="[{selected}, {showdepths: showDepths}]">

		<img v-if="heatmapImageUrl" class="heatmap-image" :src="heatmapImageUrl" alt="" draggable="false">

	</div>

</template>

<script>
import { state } from '@/state.js';
import {
	buildDeviceTimelineTelemetryState,
	buildTimelineImageCacheKey,
	getCachedTimelineImage,
	renderTimelineImage,
} from '@/table/timeline_heatmap.js';

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
				deviceId: this.device?.id || this.device?.name,
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

			if (defer && !getCachedTimelineImage(this.cacheKey)) {
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
			const timelineState = buildDeviceTimelineTelemetryState(this.device, {
				showDepths: this.showDepths,
				showDataGaps: state.showDataGaps,
			});
			this.telemetryData = timelineState.telemetryData;
			this.heatmapSegments = timelineState.heatmapSegments;
			this.heatmapNumBands = timelineState.heatmapNumBands;
			this.telemetryFingerprint = timelineState.telemetryFingerprint;
		},
		renderHeatmapImage() {
			if (!state.telemetryLoaded || !this.heatmapSegments || this.heatmapSegments.length === 0) {
				this.heatmapImageUrl = null;
				return;
			}

			const cachedImageUrl = getCachedTimelineImage(this.cacheKey);
			if (cachedImageUrl) {
				this.heatmapImageUrl = cachedImageUrl;
				return;
			}

			this.heatmapImageUrl = renderTimelineImage({
				cacheKey: this.cacheKey,
				startTimestamp: this.startTimestamp,
				endTimestamp: this.endTimestamp,
				colorScheme: state.colorScheme,
				heatmapSegments: this.heatmapSegments,
				heatmapNumBands: this.heatmapNumBands,
			});
		},
		fetchTelemetry() {
			const timelineState = buildDeviceTimelineTelemetryState(this.device, {
				showDepths: this.showDepths,
				showDataGaps: state.showDataGaps,
			});
			this.telemetryData = timelineState.telemetryData;
			this.heatmapSegments = timelineState.heatmapSegments;
			this.heatmapNumBands = timelineState.heatmapNumBands;
			this.telemetryFingerprint = timelineState.telemetryFingerprint;
			if (!this.device) {
				this.heatmapImageUrl = null;
			}
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
