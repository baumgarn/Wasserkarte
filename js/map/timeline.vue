<template>
	
	<div class="timeline" ref="timeline" :class="{'sidebaropen': state.sidebarOpen, 'telemetryloaded': telemetryLoaded}"
>
		    <canvas ref="heatmap"></canvas>

	</div>

</template>

<script>
import { nextTick } from 'vue';
import { state } from '@/state.js';
import { dataModel } from '@/dataModel.js'
import dataStore from '@/datastore.js';


export default {
	name: 'MapInfoArrow',
	setup() {
		return {state}
	},
	props: {
	},
	computed: {
		fullWidth() {
			return (state.selectedDevice == null && !state.menuOpen.info) 
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		}
	},
	methods: {
		drawHeatmap() {
			console.log('drawHeatmap')
			if (this.telemetryLoaded) {
				const timeline = this.$refs.timeline;
				const canvas = this.$refs.heatmap;

				const { width: cssWidth, height: cssHeight } = timeline.getBoundingClientRect();
				const dpr = window.devicePixelRatio || 1;
				canvas.style.width = cssWidth + 'px';
				canvas.style.height = cssHeight + 'px';
				canvas.width = Math.max(1, Math.floor(cssWidth * dpr));
				canvas.height = Math.max(1, Math.floor(cssHeight * dpr));

				const ctx = canvas.getContext('2d');
				ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				ctx.clearRect(0, 0, cssWidth, cssHeight);

				if (!this.telemetryLoaded) return;

				const rows = dataStore.nfk_daily_averages;
				const msPerDay = 24 * 60 * 60 * 1000;

				// time span is from first timestamp to (last timestamp + 1 day)
				const firstTs = rows[0][0];
				const lastTs = rows[rows.length - 1][0] + msPerDay;
				const spanMs = Math.max(1, lastTs - firstTs);
				const pxPerMs = cssWidth / spanMs;

				// draw each row from its ts to the next ts (or +1 day for the last)
				let x = 0; // running x, but we'll compute directly from time to avoid drift
				for (let i = 0; i < rows.length; i++) {
				const [ts, nfk] = rows[i];
				const nextTs = (i < rows.length - 1) ? rows[i + 1][0] : ts + msPerDay;

				const startRel = ts - firstTs;      // ms from start
				const endRel = nextTs - firstTs;    // ms from start
				const segX = startRel * pxPerMs;
				const segW = (endRel - startRel) * pxPerMs + 1;

				ctx.fillStyle = dataModel.get_nfk_color(nfk);
				ctx.fillRect(segX, 0, segW, cssHeight);
				}

				// optional thin top line for definition
				ctx.fillStyle = 'rgba(0,0,0,0.06)';
				ctx.fillRect(0, 0, cssWidth, 1);

			}
		}
	},
	watch: {
		telemetryLoaded() {
			this.drawHeatmap()
			console.log(dataStore.nfk_daily_averages);
		},
		fullWidth() {
			nextTick(()=>{
				this.drawHeatmap()
			})	
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
		height var(--timelineheight);
		left 0
		bottom 0
		right 0
		z-index 10
		background #00000044
		filter drop-shadow(2px -2px 2px #00000022)
		&.telemetryloaded
			background: #fff
		&.sidebaropen
			right 600px !important


</style>