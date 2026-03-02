<template>

	<div class="timeline" ref="timeline" @mousemove="hover" @mouseleave="hoverOut" @touchstart="hoverOut" @touchmove="hover" @touchend="hoverOut" @touchcancel="hoverOut">

		<canvas ref="heatmap"></canvas>

		<div class="hoverline" v-if="(hoverLinePosition > 0)" :style="{ left: (hoverLinePosition ) + 'px' }"></div>

		<div class="timelinehovertriangle" v-if="showDate" :style="{ left: (hoverLinePosition - 1 ) + 'px' }"></div>

		<div class="hoverdate" v-if="showDate" :style="{ left: (hoverDatePosition ) + 'px', width: hoverDateWidth + 'px' }">
			{{ formattedHoverDate }}
		</div>

		<div class="selectedDeviceArea" v-if="selectedDeviceTelemetry" :style="{ left: (selectedDeviceStartPos ) + 'px', right: (selectedDeviceEndPos ) + 'px' }"></div>

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
import { nextTick } from 'vue';
import { state } from '@/state.js';
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
			timelineWidth: 0
		};
	},
	props: {
		dailyAverages: Object,
		startTimestamp: Number,
		endTimestamp: Number,
		dateAxis: {type: Boolean, default: true},
		firstItemPadding: {type: Boolean, default: false},
		label: {type: String, default: ''},
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
			if (this.telemetryLoaded ) {
				const timeline = this.$refs.timeline;
				if (timeline) {
					this.timelineWidth = timeline.getBoundingClientRect().width;
				}
				const canvas = this.$refs.heatmap;

				const dpr = window.devicePixelRatio || 1;
				canvas.style.width = this.timelineWidth + 'px';
				canvas.width = Math.max(1, Math.floor(this.timelineWidth * dpr));

				const rows = this.dailyAverages;
				this.latestTimestamp = this.dailyAverages[this.dailyAverages.length-1].ts;
				this.earliestTimestamp = this.dailyAverages[0].ts;

				const msPerDay = 24 * 60 * 60 * 1000;

				this.timelineSpan = Math.max(1, this.endTimestamp - this.startTimestamp);
				this.dayWidth = this.timelineWidth / this.timelineSpan;

				const ctx = canvas.getContext('2d');

				// Check if we should draw levels or regular heatmap
				if (state.timelineStyle === 'levels') {
					// Draw vertical bars showing level distribution per day
					const canvasHeight = timeline.getBoundingClientRect().height;
					canvas.height = Math.max(1, Math.floor(canvasHeight * dpr));
					canvas.style.height = canvasHeight + 'px';

					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
					ctx.clearRect(0, 0, this.timelineWidth, canvasHeight);

					const labels = dataModel.nfk_labels;
					const levelColors = labels.map((label, index) => {
						if (index === 0) {
							return dataModel.get_nfk_color(0);
						}
						const prevValue = labels[index - 1].value;
						const midpoint = prevValue + (label.value - prevValue) / 2;
						return dataModel.get_nfk_color(midpoint);
					});

					// Helper to calculate percentages for a row
					const getPercentages = (row) => {
						if (!row.nfk_level || !row.nfk_level.length) return null;
						const total = row.nfk_level.reduce((sum, v) => sum + v, 0);
						if (total === 0) return null;
						return row.nfk_level.map(count => count / total);
					};

					// Pre-calculate percentages for all rows
					const rowPercentages = rows.map(getPercentages);

					// Iterate through rows and draw interpolated segments between them
					for (let i = 0; i < rows.length; i++) {
						const row = rows[i];
						const ts = row.ts;
						const nextRow = i < rows.length - 1 ? rows[i + 1] : null;
						const nextTs = nextRow ? nextRow.ts : ts + msPerDay;

						const leftPerc = rowPercentages[i];
						const rightPerc = nextRow ? rowPercentages[i + 1] : leftPerc;

						if (!leftPerc) continue;

						// Calculate pixel range for this segment
						const startRel = ts - this.startTimestamp;
						const endRel = nextTs - this.startTimestamp;
						const startX = Math.floor((startRel / this.timelineSpan) * this.timelineWidth);
						const endX = Math.ceil((endRel / this.timelineSpan) * this.timelineWidth);

						// Draw each pixel in this segment
						for (let x = startX; x < endX; x++) {
							if (x < 0 || x >= this.timelineWidth) continue;

							// Calculate interpolation factor
							const t = rightPerc ? (x - startX) / (endX - startX) : 0;

							// Interpolate percentages
							let percentages;
							if (rightPerc && rightPerc !== leftPerc) {
								percentages = leftPerc.map((v1, idx) => v1 * (1 - t) + rightPerc[idx] * t);
							} else {
								percentages = leftPerc;
							}

							// Draw the column
							let currentY = canvasHeight;
							for (let levelIndex = 0; levelIndex < percentages.length; levelIndex++) {
								const percentage = percentages[levelIndex];
								if (percentage > 0) {
									const segmentHeight = percentage * canvasHeight;
									ctx.fillStyle = levelColors[levelIndex];
									ctx.fillRect(x, currentY - segmentHeight, 1, segmentHeight);
									currentY -= segmentHeight;
								}
							}
						}
					}
				} else {
					// Draw regular heatmap (single line)
					canvas.height = 1;
					canvas.style.height = '';

					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
					ctx.clearRect(0, 0, this.timelineWidth, 1);

					for (let i = 0; i < rows.length; i++) {
						const row = rows[i];
						const ts = row.ts;
						const nfk = row.nfk_avg;
						const nextTs = (i < rows.length - 1) ? rows[i + 1].ts : ts + msPerDay;

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

	},
	watch: {
		dailyAverages() {
		},
		telemetryLoaded() {
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
		// timelineWidth() {
		// 	nextTick(()=>{
		// 		this.drawHeatmap()
		// 	})
		// },
		'state.timelineStyle'() {
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
		position relative
		display block
		width 100%
		height var(--timelineheight);
		canvas
			height 100%
			background #eaeaea
		.hoverline
			border-left 1px dotted #000000
			top -1px
		.hoverdate
			position absolute
			bottom calc(100% + 8px)
			font-size 8pt
			opacity 1
			pointer-events none
			background var(--timelinedatebg)
			color #fff
			text-align center
			padding 3px 0 2px
			white-space nowrap
			border-radius 4px
			z-index 100
			overflow visible
		.timelinehovertriangle
			margin-left -4.5px
			top -8px
			position absolute
			border-top 8px solid var(--timelinedatebg)
			border-left 6px solid transparent
			border-right 6px solid transparent
			opacity 1
			z-index 101
		// .selectedDeviceArea
		// 	position absolute
		// 	bottom 0
		// 	height 4px
		// 	// background #00000044
		// 	z-index 102
		// 	display none
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
		// 	top 60%
		// 	transform translate(0,-10px)

	// .dateaxisbelowcontainer
	// 	position relative
	// 	width 100%
	// 	.chart-time
	// 		bottom 0

	// .dateaxisbelowcontainer
	// .timeline
	// 	&:after
	// 		content ''
	// 		position absolute
	// 		left 0
	// 		top 0
	// 		right 0
	// 		border-top 1px solid #00000022
</style>
