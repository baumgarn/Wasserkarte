<template>
	
	<div class="interpretation" v-if="averages" :class="{ 'filteractive': filterActive }">
		<!-- :style="'background:'+dataModel.get_nfk_color(averages.nfk_avg)" -->
		 <div class="inforow">

			 <div class="date">{{displayDate}}</div>
			 <div class="count">{{ averages.count }} 
				<div class="pinicon"></div></div>
			 <div class="trockenstress" v-if="!isNaN(trockenstress)">
				 <span class="value">{{ trockenstress }}</span><span class="unit">%</span><span class="label">&nbsp;Trocken</span>
				</div>
		</div>
	</div>

</template>

<script>
import {state} from '@/state.js';
import {dataModel} from '@/datamodel.js';
import {displayutil} from '@/displayutil.js';
import dataStore from '@/datastore.js';

export default {
	name: 'Interpretation',
	setup() {
		return {dataModel};
	},
	data() {
		return {
			dailyAverages: [],
			lastAverages: [],
			trockencolor: [{ value: 0, color: '#666666'},{ value: 100, color: '#ff0000'}],
		};
	},
	components: {
	},
	props: {
	},
	computed: {
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		timelineDate() {
			return state.timelineDate;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		filterActive() {
			return (state.includeFilter.length > 0 || state.excludeFilter.length > 0)
		},
		averages() {
			if (this.timelineDate) {
				const index = this.get_telemetry_index_binary(this.dailyAverages, this.timelineDate);
				const avg = this.dailyAverages[index];
				if (avg) return avg;
			} else {
				return this.lastAverages;
			}
		},
		trockenstress() {
			if (this.averages) {
				const p = this.averages.trockenstress / this.averages.count * 100;
				return Math.round(p);
			}
		},
		displayDate() {
			if (this.timelineDate) {	
				// timeline date is displayed as minus one day as the timestamp is midnight end of the day
				return displayutil.formatDateAggregated(this.timelineDate)
			} else {
				return displayutil.formatDateShort(this.averages.ts)
			}
		}
	},
	methods: {
		getNfkDailyAverages() {
			this.dailyAverages = dataStore.getNfkDailyAverages();
			this.lastAverages = dataStore.getNfkAveragesForLastTelemetry();
		},
		get_telemetry_index_binary(data, timestamp) { 
			const n = data.length;
			if (n < 2) return -1;
			let lo = 0, hi = n;
			while (lo < hi) {
				const mid = (lo + hi) >>> 1;
				if (data[mid].ts <= timestamp) lo = mid + 1;
				else hi = mid;
			}
			const i = lo - 1;
			if (i < 0) return -1;  // Only check lower bound
			return i;
		},
	},
	mounted() {
	},
	watch: {
		telemetryLoaded() {
			this.getNfkDailyAverages()
		},
		filteredDevices() {
			this.getNfkDailyAverages()
		},
	}
};
</script>

<style lang="stylus" scoped>



	
.interpretation
	position relative
	padding 0
	min-width 220px
	// background var(--menuinactivebg)
	background #fff
	// background: var(--infobg);
	// background: #555555aa
	// color #fff
	// outline 1px solid #00000011
	display flex
	flex-direction column
	align-items center
	justify-content flex-start
	margin 0
	font-size 9pt
	// font-weight bold
	color #00000088
	// border-radius 4px
.interpretation.filteractive:after
	content ''
	position absolute
	left 0
	top 0
	right 0
	height 6px
	background: linear-gradient(to bottom, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0))
	border-top 1px solid #00000018
.inforow
	height 28px
	border-left 4px solid #eee
	border-right 4px solid #eee
	display flex
	width 100%
	align-items center
	justify-content flex-start

.interpretation.filteractive .inforow
	height 26px
	padding-bottom 2px

.date
	flex-basis 36%
	text-align right
.count
	flex-basis 19.5%
	text-align right
	.pinicon
		display inline-block
		width 1em
		height 1em 
		background-size 100% 100%
		background-position center
		background-repeat no-repeat
		background-image url(/img/sensor.png)
		opacity .6
		top .145em
		// background-image url(/img/pin_fill.png)
		// opacity .3
		// top .125em
		// margin-left -.05em
		filter grayscale(1)
		position relative

.trockenstress
	flex-grow 1
	text-align right
	padding-right .8em
	.value
		display inline-block
		width 2em
		text-align right
		opacity .8
		color #000
		// font-weight normal
		// font-size 10pt
	.unit
		// opacity .6
	.label
		opacity 1
		// opacity .9
		// font-size 10pt
	// .label
		// font-size 9pt
	.info
		// font-size 8pt
		display inline-block
		opacity .55
		flex-grow 1
		margin-right 1em
		text-align right
	// .label
		// text-transform uppercase
		// font-weight bold
		// opacity .6
		// letter-spacing 0.03em;


</style>
