<template>
	
	<div class="interpretation" v-if="averages && averages.nfk_avg != null" :class="{ 'filteractive': filterActive, 'full':fullView }" @click="toggleFull">
		<!-- :style="'background:'+dataModel.get_nfk_color(averages.nfk_avg)" -->
		 <div class="inforow">

			 <div class="trocken" v-if="!isNaN(trockenstress)">
				 <span class="value">{{ trockenstress }}</span>
				 <span class="unit">%</span>
				 <span class="label">&nbsp;Trockenstress</span>
			</div>
			 <div class="date">{{displayDate}}</div>
			 <!-- <div class="count">{{ averages.count }} 
				<div class="pinicon"></div></div> -->
		</div>

		<template v-if="fullView">
		
			<div class="nfktable" v-if="levelPercentages && levelPercentages.length">
				<template v-for="(percentage, index) in levelPercentages" :key="index">
					<div class="row" >
						<div
						class="bar"
						:style="{
							width: percentage + '%',
							backgroundColor: levelColors[index]
						}">
						</div>
						
						<div class="textoverlay">
							<span class="label">&nbsp;{{nfkLabels[index].name}}</span>
							<span class="count">
								{{ averages.nfk_level[index] }}
							</span>
						</div>
						

					</div>
				</template>
			</div>

		</template>
		
		<template v-else>
			
			<div class="nfkbar" v-if="levelPercentages && levelPercentages.length">
				<template v-for="(percentage, index) in levelPercentages" :key="index">
					<div
					v-if="percentage > 0"
					class="segment"
					:style="{
						flexBasis: percentage + '%',
						backgroundColor: levelColors[index]
					}">
					</div>
				</template>
			</div>

		</template>

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
			fullView: false,
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
		levelPercentages() {
			if (this.averages && this.averages.nfk_level) {
				const total = this.averages?.nfk_level.reduce((sum, v) => sum + v, 0);
				const percentages = this.averages?.nfk_level.map(v => total === 0 ? 0 : (v / total) * 100);
				return percentages;
			} else {
				return null;
			}
		},
		nfkLabels() {
			return dataModel.nfk_labels;
		},
		levelColors() {
			const labels = dataModel.nfk_labels;

			return labels.map((label, index) => {
				// first entry stays at 0
				if (index === 0) {
				return dataModel.get_nfk_color(0);
				}
				// other entry midway between nfk level bounds
				const prevValue = labels[index - 1].value;
				const midpoint = prevValue + (label.value - prevValue) / 2;

				return dataModel.get_nfk_color(midpoint);
			});
		},
		// levelColors() {
  		// 	return dataModel.nfk_labels.map(label => dataModel.get_nfk_color(label.value));
		// },
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
		toggleFull() {
			this.fullView = !this.fullView;
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
	min-width 210px
	background #fff
	background #e8e8e8
	background #f8f8f8
	display flex
	flex-direction column
	align-items center
	justify-content flex-start
	margin 0
	font-size 9pt
	color rgba(0,0,0,0.7)
	cursor pointer
	transition background linear .05s
	&:hover
		background #f0f0f0

.nfktable
	display flex
	flex-direction column-reverse
	width 100%
	background #00000011
	border-bottom 1px solid #00000022
	color rgba(0,0,0,0.55)
	.row
		height 17px
		position relative
		.bar
			position absolute
			right 0
			top 1px
			bottom 0
			transition width linear .05s
		.textoverlay
			position absolute
			left 0
			top 0
			right 0
			bottom 0
			border-top 1px solid #00000018
			display flex
			font-size 8pt
			padding-top 1px
			padding-right 6px
			padding-left 4px
		.value
			display inline-block
			width 2.7em
			font-weight normal
			text-align right
		.unit
			margin-left .2em
		.label
			margin-left .09em
			text-align left
			flex-grow 1


.nfkbar
	display flex
	width 100%
	.segment
		height 8px
	.segment:last-of-type
		flex-grow 1

.interpretation.filteractive:after
	content ''
	position absolute
	left 0
	top 0
	right 0
	height 6px
	background: linear-gradient(to bottom, rgba(0, 0, 0, 0.08), rgba(0, 0, 0, 0))

.inforow
	height 30px
	display flex
	width 100%
	padding-top 6px
	align-items baseline
	justify-content flex-start
	user-select none

.interpretation.filteractive .inforow
	height 27px
	padding-top 4px

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
		top .125em
		margin-left -.05em
		filter grayscale(1)
		position relative

.trocken
	flex-basis 50px
	text-align right
	padding-right .8em
	.value
		display inline-block
		width 2em
		text-align right
		opacity 1
		font-size 10.5pt
		color #000
	.unit
		margin-left .2em
	.label
		opacity 1
		font-size 8.5pt
		margin-left .09em
		// font-weight bold

.date
	flex-grow 1
	margin-right 1.1em
	font-size 8pt
	text-align right


</style>
