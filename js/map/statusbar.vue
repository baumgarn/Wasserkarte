<template>

	<div class="statusbarouter" :class="{ intableview, narrowview, verynarrowview }">

		<div class="statusbar">

			<div class="filteritems" v-if="filterCount >= 0">

				<template v-if="hasIncludeFilter">
					
					<template v-if="intableview || filterCount > 1">
						<FilterItem v-for="item in includeFilter" :obj="item" type="statusbaritemsmall"/>
					</template>
					<template v-else>
						<FilterItem v-for="item in includeFilter" :obj="item" type="statusbaritem"/>
					</template>
					
				</template>
				
				<template v-if="hasExcludeFilter">
					
					<template v-if="intableview || filterCount > 1">
						<FilterItem v-for="item in excludeFilter" :obj="item" type="statusbaritemsmall"/>
					</template>
					<template v-else>
						<FilterItem v-for="item in excludeFilter" :obj="item" type="statusbaritem"/>
					</template>

				</template>

			</div>

			<div class="stats" :class="{ 'filteractive': filterActive, 'full':fullView }" @click="toggleFull"
			>
			
			<div class="inforow">
			

					<div class="trocken infoitem" :class="{'novalue': !(averages && averages.nfk_avg != null)}"

						v-tooltip
						tooltipcontent="Anteil der Standorte bei denen zu wenig Wasser für Pflanzen verfügbar ist (nFK < 50%)"
						tooltipside="bottom"
						tooltipalign="left"
						tooltipalignoffset="0"
						:tooltipoffset="intableview? -2 : 3"
						tooltipwidth="264"
						:tooltiparrowsize="intableview? '':'12 10'"
						tooltiparrowposition="50"
						:tooltipdisabled="fullView"
						>
							<span v-if="averages && averages.nfk_avg != null" class="value">{{ trocken }}</span>
							<span v-else class="no value">–</span>
							<span class="unit">%</span>
							<span class="label">&nbsp;Trocken</span>
							
					</div>
					<div class="devicecount infoitem">
						<span class="value">{{ averages.count }}</span> 
						<!-- <div v-if="!intableview" class="pinicon"></div> -->
						<span class="label">Standorte</span>
					</div>
					<div class="date infoitem">{{displayDate}}</div>
				</div>

				<template v-if="!fullView">
					
					<div class="nfkbar">
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

				<template v-else>
				
					<div class="nfktable" v-if="levelPercentages && levelPercentages.length">
						<template v-for="(percentage, index) in levelPercentages" :key="index">
							<div class="nfkrow" >
								<div
								class="colorbar"
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
				
				

			</div>
		</div>
	</div>

</template>

<script>
import {state} from '@/state.js';
import {dataModel} from '@/datamodel.js';
import {displayutil} from '@/displayutil.js';
import dataStore from '@/datastore.js';
import FilterItem from '@/location/filteritem.vue';

export default {
	name: 'stats',
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
	components: {FilterItem},
	props: {
		intableview: false,
		containerWidth: Number,
	},
	computed: {
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		verynarrowview() {
			return (this.intableview && this.containerWidth < 400)
		},
		narrowview() {
			return (this.intableview && this.containerWidth < 800)
		},
		timelineDate() {
			return state.timelineDate;
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
		averages() {
			if (this.timelineDate) {
				const index = this.get_telemetry_index_binary(this.dailyAverages, this.timelineDate);
				const avg = this.dailyAverages[index];
				if (avg) return avg;
			} else {
				return this.lastAverages;
			}
			return {};
		},
		trockenstress() {
			if (this.averages) {
				const p = this.averages.trockenstress / this.averages.count * 100;
				return Math.round(p);
			}
		},
		trocken() {
			if (this.averages) {
				const p = this.averages.trocken / this.averages.count * 100;
				return Math.round(p);
			}
		},
		displayDate() {
			if (this.timelineDate) {	
				return displayutil.formatDateAggregated(this.timelineDate)
			} else if (this.averages?.ts != null) {
				return displayutil.formatDateShort(this.averages.ts)
			}

			return '–'
		},
		includeFilter() {
			return state.includeFilter;
		},
		excludeFilter() {
			return state.excludeFilter;
		},
		hasIncludeFilter() {
			return (state.includeFilter.length > 0);
		},
		hasExcludeFilter() {
			return (state.excludeFilter.length > 0);
		},
		filterCount() {
			return state.includeFilter.length + state.excludeFilter.length
		},
		filteredDevices() {
			return state.filteredDevices
		},
		filteredDevicesCount() {
			return state.filteredDevices.length;
		},
		filterActive() {
			return (state.includeFilter.length > 0 || state.excludeFilter.length > 0)
		},
		infoTipId() {
			const uid = this.$ && this.$.uid ? this.$.uid : '0';
			return `statusbar-info-tip-${uid}`;
		},
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
			if (! this.intableview) {
				this.fullView = !this.fullView;
			}
		},
		clearFilter() {
			state.includeFilter = [];
			state.excludeFilter = [];
		}
	},
	mounted() {
		if (this.telemetryLoaded) {
			this.getNfkDailyAverages()
		}  
	},
	watch: {
		telemetryLoaded(val) {
			if (val) this.getNfkDailyAverages()
		},
		filteredDevices() {
			if (this.telemetryLoaded) this.getNfkDailyAverages()
		},
	}
};
</script>

<style lang="stylus" scoped>

.statusbarouter
	display flex
	align-items center
	justify-content center
	flex-grow 1
	flex-shrink 1
	min-width 0
	pointer-events none

.statusbar
	display flex
	flex-direction column
	align-items center
	justify-content center
	white-space nowrap
	min-width: 0
	flex-shrink 1
	flex-wrap wrap
	font-size 9pt
	border-top none
	padding 0 6px
	margin-top 6px
	> *
		pointer-events all

.intableview .statusbar
	filter none
	flex-direction row
	margin-top 0
	padding 0
	margin 0 50px

.narrowview .statusbar
	flex-direction column
.verynarrowview .statusbar
	margin 0

// FILTER

.filteritems
	z-index 1
	filter drop-shadow(0 3px 2px #00000018)
	display flex
	flex-direction column
	align-items center
	// gap 0.5px

.intableview .filteritems
	filter none
	flex-direction row
	display inline-flex
	gap 3px
	min-width 200px
	justify-content center
	flex-wrap wrap
	:first-child 
		margin-left: auto

	// flex-basis 200px
	// background red

.narrowview .filteritems
	margin-right 0
	max-width 270px
	flex-wrap wrap
	justify-content center
	:first-child 
		margin-left unset

.verynarrowview .filteritems
	margin-top 24px


// STATS
	
.stats
	position relative
	padding 0
	margin 0
	background #f8f8f8
	display flex
	flex-direction column
	align-items center
	justify-content center
	font-size 8.5pt
	color rgba(0,0,0,0.7)
	box-shadow 0 2px 4px #00000033
	cursor pointer
	transition background linear .1s
	&:hover
		background #f0f0f0

.intableview .stats
	display inline-flex
	background #fff
	flex-direction row
	justify-content center
	align-items center
	cursor default
	box-shadow none
	background transparent

.narrowview .stats
	// padding-top 8ox
	flex-direction column

.verynarrowview .stats:first-child
	margin-top 28px

// .stats
	// border-top 1px solid #00000022

.stats.filteractive
	border-top 6px solid #00000018
	margin-top -5px

.intableview .stats.filteractive
	border-top none
	margin-top 0


// INFO ROW

.inforow
	margin 6px 0 6px
	display flex
	height 19px
	// width 275px
	align-items baseline
	justify-content center
	padding-top 3px
	gap 0
	user-select none

.intableview .inforow 
	margin 6px 6px

// .stats.filteractive .inforow

.infoitem 
	height 18px
	flex-grow 0
	flex-shrink 0
	width 92px
	padding-right 9px
	overflow hidden
	// border 1px solid #00000022
	text-align right

.infoitem + .infoitem
	border-left 1px solid #00000011

.trocken
.trockenstress
	.value
		display inline-block
		width 1.8em
		text-align right
		opacity 1
		font-size 10pt
		margin-top -1em
		color #000
	.no.value
		opacity .4
		font-weight normal
	.unit
		margin-left .2em
		opacity .7
	.label
		opacity 1
		font-size 8.5pt
		margin-left .09em

.intableview .trocken .value
	font-size 10pt

.devicecount
	.value
		font-weight normal
	.label
		display inline-block
		margin-left .4em



// NFK BAR

.nfkbar
	display flex
	width 100%
	background #ddd
	height 6px
	.segment
		height 100%
	.segment:last-of-type
		flex-grow 1

.intableview .nfkbar
	margin-top 1px
	overflow hidden
	filter drop-shadow(0 3px 2px #00000011)
	// height 6px
	width 180px
	margin-right 20px

.narrowview .nfkbar
	margin-left 0
	margin-right 0
	margin-bottom 6px
	// height 6px
	margin-top 0
	width calc( 100% - 28px)



// NFK TABLE

.nfktable
	display flex
	flex-direction column-reverse
	width 100%
	background #00000011
	border-bottom 1px solid #00000022
	color rgba(0,0,0,0.55)
	.nfkrow
		height 17px
		position relative
		.colorbar
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
			padding-right 7px
			padding-left 7px
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





</style>




<style lang="stylus">

	.is-mobile .statusbarouter
		flex-basis 100vw

	@media (max-width: 1300px) 
		.sidebaropen .topbar .statusbarouter
			position fixed
			left 0px
			top 48px
			right 600px
		.is-mobile .topbar .statusbarouter
			position fixed
			top 12px
			left 0
			right 0


	@media (min-width: 1300px) 
		.wrapper:not(.sidebaropen) .topbar .statusbarouter
			position fixed
			left 0px
			right 0px

	.is-mobile .topbar .statusbarouter .statusbar
		margin-top 0 

</style>
