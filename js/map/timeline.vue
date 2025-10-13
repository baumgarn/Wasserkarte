<template>
	
	<div class="timeline" ref="timeline" :class="{'sidebaropen': state.sidebarOpen, 'telemetryloaded': telemetryLoaded}" @mousemove="hover" @mouseleave="hoverOut" @touchstart="hoverOut" @touchmove="hover" @touchend="hoverOut" @touchcancel="hoverOut">
		<div class="timelineinner">
			<canvas ref="heatmap"></canvas>
			<div class="hoverline" v-if="(hoverLinePosition > 0)" :style="{ left: (hoverLinePosition ) + 'px' }"></div>
			<div class="hoverdate" v-if="(hoverLinePosition > 0)">
				{{ formattedHoverDate }}
			</div>
		</div>
	</div>

</template>

<script>
import { nextTick } from 'vue';
import { state } from '@/state.js';
import { dataModel } from '@/dataModel.js'
import dataStore from '@/datastore.js';
import { displayutil } from '@/displayutil.js'



export default {
	name: 'MapInfoArrow',
	setup() {
		return {
			state, displayutil,
		}
	},
	data() {
		return {
			earliestTimestamp: 0,
			latestTimestamp: 0,
			hoverPosition: -1,
		};
	},
	props: {
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
		timelineDate() {
			let ts = null;
			if (this.hoverPosition > -1) {
				const fraction = this.hoverPosition / this.timelineWidth;
				ts = this.earliestTimestamp + fraction * this.timelineSpan;
				state.timelineDate = ts;
				return ts;
			} else if (state.timelineDate && this.hoverPosition < 0 ) {
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
		formattedHoverDate() {
			return displayutil.formatDateShort(this.timelineDate);
		}
	},
	methods: {
		drawHeatmap() {
			if (this.telemetryLoaded) {
				const timeline = this.$refs.timeline;
				const canvas = this.$refs.heatmap;

				this.timelineWidth = timeline.getBoundingClientRect().width;
				const dpr = window.devicePixelRatio || 1;
				canvas.style.width = this.timelineWidth + 'px';
				canvas.width = Math.max(1, Math.floor(this.timelineWidth * dpr));
				canvas.height = 1;

				const ctx = canvas.getContext('2d');
				ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				ctx.clearRect(0, 0, this.timelineWidth, 1);

				if (!this.telemetryLoaded) return;

				const rows = dataStore.nfk_daily_averages;
				const msPerDay = 24 * 60 * 60 * 1000;

				// time span is from first timestamp to (last timestamp + 1 day)
				this.earliestTimestamp = rows[0][0];
				this.latestTimestamp = rows[rows.length - 1][0] + msPerDay;
				this.timelineSpan = Math.max(1, this.latestTimestamp - this.earliestTimestamp);
				this.dayWidth = this.timelineWidth / this.timelineSpan;

				// draw each row from its ts to the next ts (or +1 day for the last)
				let x = 0; // running x, but we'll compute directly from time to avoid drift
				for (let i = 0; i < rows.length; i++) {
				const [ts, nfk] = rows[i];
				const nextTs = (i < rows.length - 1) ? rows[i + 1][0] : ts + msPerDay;

				const startRel = ts - this.earliestTimestamp;      // ms from start
				const endRel = nextTs - this.earliestTimestamp;    // ms from start
				const segX = startRel * this.dayWidth;
				const segW = (endRel - startRel) * this.dayWidth + 1;

				ctx.fillStyle = dataModel.get_nfk_color(nfk);
				ctx.fillRect(segX, 0, segW, 1);
				}

				// optional thin top line for definition
				ctx.fillStyle = 'rgba(0,0,0,0.06)';
				ctx.fillRect(0, 0, this.timelineWidth, 1);

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
	},
	watch: {
		telemetryLoaded() {
			this.drawHeatmap()
		},
		fullWidth() {
			nextTick(()=>{
				this.drawHeatmap()
			})	
		},
		colorScheme() {
			this.drawHeatmap()
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
		canvas
			height 100%
		&.sidebaropen
			right 600px !important
		.hoverline
			border-left 1px dotted #000000
			border-left 1px solid #00000066
		.hoverdate
			position absolute
			right 0
			bottom 100%
			font-size 12px
			opacity 1
			pointer-events none
			width 95px
			background #44444488
			color #fff
			text-align center
			padding 4px 6px
			border-top-left-radius 4px
			border-bottom 1px solid #00000022
			border-right 1px solid #00000022


</style>