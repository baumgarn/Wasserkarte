<template>
	
	<div class="timeline" ref="timeline" :class="{'sidebaropen': state.sidebarOpen, 'telemetryloaded': telemetryLoaded}" @mousemove="hover" @mouseleave="hoverOut" @touchstart="hoverOut" @touchmove="hover" @touchend="hoverOut" @touchcancel="hoverOut">
		<div class="timelineinner">
			<canvas ref="heatmap"></canvas>
			<div class="hoverline" v-if="(hoverLinePosition > 0)" :style="{ left: (hoverLinePosition ) + 'px' }"></div>
			<div class="timelinehovertriangle" v-if="showDate" :style="{ left: (hoverLinePosition - 1 ) + 'px' }"></div>
			<div class="hoverdate" v-if="showDate" :style="{ left: (hoverDatePosition ) + 'px', width: hoverDateWidth + 'px' }">
				{{ formattedHoverDate }}
			</div>
			<div class="selectedDeviceArea" v-if="selectedDeviceTelemetry" :style="{ left: (selectedDeviceStartPos ) + 'px', right: (selectedDeviceEndPos ) + 'px' }"></div>
			<DateAxis
				:chartWidth="timelineWidth"
				:frameWidth="timelineWidth"
				:startTimestamp="earliestTimestamp"
				:numberOfDays
				:insideTimeline="true"
			></DateAxis>
		</div>
	</div>

</template>

<script>
import { nextTick } from 'vue';
import { state } from '@/state.js';
import { dataModel } from '@/dataModel.js'
import dataStore from '@/datastore.js';
import { displayutil } from '@/displayutil.js'
import DateAxis from '@/charts/dateaxis.vue'



export default {
	name: 'MapInfoArrow',
	components: {DateAxis},
	setup() {
		return {
			state, displayutil,
		}
	},
	data() {
		return {
			selectedDeviceTelemetry: null,
			earliestTimestamp: 0,
			latestTimestamp: 0,
			hoverPosition: -1,
			hoverDateWidth: 85,
			timelineWidth: 0
		};
	},
	props: {
	},
	computed: {
		device() {
			return dataStore.getDeviceByName(state.selectedDevice);
		},
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
			return (this.latestTimestamp - this.earliestTimestamp) / (1000 * 60 * 60 * 24);
		},	
		showDate() {
			return (this.hoverPosition > -1 && this.hoverLinePosition > 0)
		},
		timelineDate() {
			let ts = null;
			if (this.hoverPosition > -1) {
				const fraction = this.hoverPosition / (this.timelineWidth - 1);
				ts = this.earliestTimestamp + fraction * this.timelineSpan;
				state.timelineDate = dataStore.floorToMidnight(ts);
				return state.timelineDate;
			} else if (state.timelineDate && this.hoverPosition < 0) {
				return state.timelineDate;
			}
		},
		hoverLinePosition() {
			let pos;
			if (this.hoverPosition > -1) {
				pos = this.hoverPosition;
			} else if (state.timelineDate && this.hoverPosition < 0 ) {
				const fraction = (state.timelineDate - this.earliestTimestamp) / this.timelineSpan;
				pos = fraction * this.timelineWidth;
			} else {
				pos = -1;
			}
			return pos;
		},
		hoverDatePosition() {
			let pos = this.hoverLinePosition - (this.hoverDateWidth / 2)
			if (pos < 0) pos = 0;
			if (pos > this.timelineWidth - this.hoverDateWidth) pos = this.timelineWidth - this.hoverDateWidth;
			return pos
		},
		formattedHoverDate() {
			return displayutil.formatDateAggregated(this.timelineDate);
		},
	},
	methods: {
		drawHeatmap() {
			if (this.telemetryLoaded) {
				const timeline = this.$refs.timeline;
				if (timeline) {
					this.timelineWidth = timeline.getBoundingClientRect().width;
				}
				const canvas = this.$refs.heatmap;
				
				const dpr = window.devicePixelRatio || 1;
				canvas.style.width = this.timelineWidth + 'px';
				canvas.width = Math.max(1, Math.floor(this.timelineWidth * dpr));
				canvas.height = 1;

				const ctx = canvas.getContext('2d');
				ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				ctx.clearRect(0, 0, this.timelineWidth, 1);

				const rows = this.dailyAverages;
				const msPerDay = 24 * 60 * 60 * 1000;

				// time span is from first timestamp to (last timestamp + 1 day)
				this.earliestTimestamp = rows[0].ts;
				this.latestTimestamp = rows[rows.length - 1].ts + msPerDay;
				this.timelineSpan = Math.max(1, this.latestTimestamp - this.earliestTimestamp);
				this.dayWidth = this.timelineWidth / this.timelineSpan;

				// draw each row from its ts to the next ts (or +1 day for the last)
				let x = 0; // running x, but we'll compute directly from time to avoid drift
				for (let i = 0; i < rows.length; i++) {
					const row = rows[i];
					const ts = row.ts;
					const nfk = row.nfk_avg;
					const nextTs = (i < rows.length - 1) ? rows[i + 1].ts : ts + msPerDay;

					const startRel = ts - this.earliestTimestamp;      // ms from start
					const endRel = nextTs - this.earliestTimestamp;    // ms from start
					const segX = startRel * this.dayWidth;
					const segW = (endRel - startRel) * this.dayWidth + 1;

					if (nfk != null) {

						ctx.fillStyle = dataModel.get_nfk_color(nfk);
						ctx.fillRect(segX, 0, segW, 1);
					}
				}

				// optional thin top line for definition
				ctx.fillStyle = 'rgba(0,0,0,0.06)';
				ctx.fillRect(0, 0, this.timelineWidth, 1);

			}
		},
		getNfkDailyAverages() {
			this.dailyAverages = dataStore.getNfkDailyAverages();
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
			return ((ts - this.earliestTimestamp) / this.timelineSpan) * this.timelineWidth;
		},

	},
	watch: {
		telemetryLoaded() {
			this.getNfkDailyAverages()
			this.drawHeatmap();
		},
		filteredDevices() {
			this.getNfkDailyAverages()
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
		timelineWidth() {
			nextTick(()=>{
				this.drawHeatmap()
			})	
		},
		device() {
			this.$nextTick(async () => {
				if (this.device) {
					this.selectedDeviceTelemetry = await dataStore.fetchTelemetryCache(this.device.id);
					if (this.selectedDeviceTelemetry && this.selectedDeviceTelemetry.data) {
						this.selectedDeviceStartDate = this.selectedDeviceTelemetry.data[0][0]
						this.selectedDeviceEndDate = this.selectedDeviceTelemetry.data[this.selectedDeviceTelemetry.data.length-1][0]
						this.selectedDeviceStartPos = this.toTimelineX(this.selectedDeviceStartDate)
						this.selectedDeviceEndPos = this.toTimelineX(this.selectedDeviceEndDate)
					}
				} else {
					this.selectedDeviceTelemetry = null;
				}
			});

		}

	},
	mounted() {
		window.addEventListener('resize', this.drawHeatmap);
		this.drawHeatmap()
	},
	beforeUnmount() {
		window.removeEventListener('resize', this.drawHeatmap);
	},
};
</script>

<style lang="stylus" scoped>
	.timeline
		position fixed
		left 0
		bottom 0
		right 0
		z-index 10
		background transparent
		padding-top 16px
		.timelineinner
			display block
			width 100%
			height var(--timelineheight);
			filter drop-shadow(2px -2px 2px #00000033)
			background #ddd
		canvas
			height 100%
		&.sidebaropen
			right 600px !important
		.hoverline
			border-left 1px dotted #000000
			top -1px
		.hoverdate
			position absolute
			bottom calc(100% + 8px)
			font-size 9pt
			opacity 1
			pointer-events none
			background var(--timelinedatebg)
			color #fff
			text-align center
			padding 4px 0
			white-space nowrap
			border-radius 4px
		.timelinehovertriangle
			margin-left -4.5px
			top -8px
			position absolute
			border-top 8px solid var(--timelinedatebg)
			border-left 6px solid transparent
			border-right 6px solid transparent
			opacity 1
			z-index 101
		.selectedDeviceArea
			position absolute
			bottom 0
			height 4px
			background #00000044
			z-index 102
			display none
		.chart-time
			top 55%
			transform translate(0,-65%)
</style>
