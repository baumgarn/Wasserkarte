<template>

	<div class="timeline" ref="timeline" :class="[{selected}, {showdepths: showDepths}]" @mousemove="hover" @mouseleave="hoverOut" @touchstart="hoverOut" @touchmove="hover" @touchend="hoverOut" @touchcancel="hoverOut">

		<canvas ref="heatmap"></canvas>

	</div>

</template>

<script>
import { nextTick } from 'vue';
import { state } from '@/state.js';
import { config } from '@/config.js';
import { dataModel } from '@/dataModel.js'
import dataStore from '@/datastore.js';
import { displayutil } from '@/displayutil.js'
import DateAxis from '@/charts/dateaxis.vue'



export default {
	name: 'TimelineInner',
	components: {DateAxis},
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
			schichten_gaps: false,
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
		fullWidth() {
			return (state.selectedDevice == null && !state.menuOpen.info) 
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		colorScheme() {
			return state.colorScheme;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		numberOfDays() {
			return (this.endTimestamp - this.startTimestamp) / (1000 * 60 * 60 * 24);
		},	
		showDate() {
			return (this.hoverPosition > -1 && this.hoverLinePosition > 0)
		},
		timelineDate() {
			let ts = null;
			if (this.hoverPosition > -1) {
				const fraction = this.hoverPosition / (this.timelineWidth - 1);
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
				// Snap to data range boundaries
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
		schema() {
			return this.device.telemetrySchema.schema;
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
	},
	methods: {
		drawHeatmap() {
			if (this.telemetryLoaded) {

				const canvas = this.$refs.heatmap;

				const dpr = window.devicePixelRatio || 1;
				canvas.style.width = this.timelineWidth + 'px';
				canvas.width = Math.max(1, Math.floor(this.timelineWidth * dpr));

				if (!this.telemetryData) return;

				const rows = this.telemetryData;
				this.latestTimestamp = this.telemetryData[this.telemetryData.length - 1][0];
				this.earliestTimestamp = this.telemetryData[0][0];

				const msPerDay = 24 * 60 * 60 * 1000;

				this.timelineSpan = Math.max(1, this.endTimestamp - this.startTimestamp);
				this.dayWidth = this.timelineWidth / this.timelineSpan;
				const ctx = canvas.getContext('2d');

				if (this.showDepths) {
					const allDepthIndices = [this.nfk10_index, this.nfk30_index, this.nfk60_index, this.nfk80_index];

					// In gaps mode: keep 4 fixed slots, missing depths stay empty.
					// In compact mode: only render present depths, canvas height = number present.
					const bands = this.schichten_gaps
						? allDepthIndices.map((dataIndex, slotIdx) => ({ dataIndex, bandIdx: slotIdx }))
						: allDepthIndices.reduce((acc, dataIndex) => {
							if (dataIndex !== null) acc.push({ dataIndex, bandIdx: acc.length });
							return acc;
						}, []);

					const numBands = this.schichten_gaps ? allDepthIndices.length : bands.length;

					canvas.height = Math.max(numBands, Math.round(numBands * dpr));
					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
					ctx.clearRect(0, 0, this.timelineWidth, numBands);

					for (const { dataIndex, bandIdx } of bands) {
						if (dataIndex === null) continue;

						for (let i = 0; i < rows.length; i++) {
							const row = rows[i];
							const ts = row[0];
							const nfk = row[dataIndex];
							if (nfk == null) continue;

							// Find the next row that has data for this specific depth
							let nextTs = ts + msPerDay;
							for (let j = i + 1; j < rows.length; j++) {
								if (rows[j][dataIndex] != null) {
									nextTs = rows[j][0];
									break;
								}
							}
							if (state.showDataGaps && (nextTs - ts) > config.dataGapLength) nextTs = ts + msPerDay;

							const startRel = ts - this.startTimestamp;
							const endRel = nextTs - this.startTimestamp;
							const segX = startRel * this.dayWidth;
							const segW = (endRel - startRel) * this.dayWidth + 1;

							ctx.fillStyle = dataModel.get_nfk_color(nfk);
							ctx.fillRect(segX, bandIdx, segW, 1);
						}
					}
				} else {
					canvas.height = Math.max(1, Math.round(dpr));
					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
					ctx.clearRect(0, 0, this.timelineWidth, 1);

					for (let i = 0; i < rows.length; i++) {
						const row = rows[i];
						const ts = row[0];
						const nfk = row[this.nfkavg_index];
						let nextTs = (i < rows.length - 1) ? rows[i + 1][0] : ts + msPerDay;
						if (state.showDataGaps && (nextTs - ts) > config.dataGapLength) nextTs = ts + msPerDay;

						const startRel = ts - this.startTimestamp;
						const endRel = nextTs - this.startTimestamp;
						const segX = startRel * this.dayWidth;
						const segW = (endRel - startRel) * this.dayWidth + 1;

						if (nfk != null) {
							ctx.fillStyle = dataModel.get_nfk_color(nfk);
							ctx.fillRect(segX, 0, segW, 1);
						}
					}
				}

			}
		},
		hover(event) {
			const rect = this.$refs.timeline.getBoundingClientRect();
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
		toTimelineX(ts) {
			if (!this.timelineWidth || !this.timelineSpan) return 0;
			return ((ts - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
		},
		fetchTelemetry() {
			if (!this.device) return;
			this.telemetryData = dataStore.fetchTelemetryCache(this.device.id).data;
		}

	},
	watch: {
		startTimestamp() {
			this.drawHeatmap();
		},
		telemetryLoaded() {
			this.fetchTelemetry();
			this.drawHeatmap();
		},
		filteredDevices() {
			this.drawHeatmap();
		},
		fullWidth() {
			nextTick(()=>{
				this.drawHeatmap()
			})	
		},
		colorScheme() {
			this.drawHeatmap()
		},
		'state.showDataGaps'() {
			this.drawHeatmap()
		},
		timelineWidth() {
			nextTick(()=>{
				this.drawHeatmap()
			})
		},
		showDepths() {
			nextTick(()=>{
				this.drawHeatmap()
			})
		},
		device() {
			this.$nextTick(async () => {
				if (this.device) {
					this.fetchTelemetry();
					this.drawHeatmap();
				} else {
				}
			});

		}

	},
	mounted() {
		window.addEventListener('resize', this.drawHeatmap);
		this.fetchTelemetry();
		this.drawHeatmap();
	},
	beforeUnmount() {
		window.removeEventListener('resize', this.drawHeatmap);
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
		overflow hidden
		canvas
			display block
			height 100%
			image-rendering pixelated
		&.showdepths 
			height 100%
			canvas
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
