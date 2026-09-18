<template>
	<div class="settingsouter">

		<div class="settingscontainer" :class="{wideView}">
			<Tabs 
			:items="chartStyles" 
			:stateProperty="'chartStyle'" 
			/>
			<div ref="openTimeRangeRef" class="daterange" @click="openTimeRanges" >

				<template v-if="!isPopoverOpen">
					{{ selectedTimeRange }}
					<template v-if="showDuration">
						<span class="separator"></span>
						{{displayedDurationDays}} Tage
					</template>
				</template>
			</div>
		</div>
		<PopoverMenu
			ref="popoverRef"
			:items="popoverItems" />
	</div>

</template>

<script>
import Tabs from '@/ui/tabs.vue';
import PopoverMenu from '@/ui/popovermenu.vue';
import {state} from '@/state.js';

export default {
	name: 'ChartSettings',
	components: {
		Tabs,
		PopoverMenu,
	},
	props: {
		frameWidth: {
			type: Number,
			required: true
		},
		earliestTimestamp: {
			type: Number,
			required: false,
			default: 0
		},
		latestTimestamp: {
			type: Number,
			required: false,
			default: 0
		},
		visibleDays: {
			type: Number,
			required: false,
			default: 0
		},
	},
	data() {
		return {
			chartStyles: [
				{ label: 'Horizonte', value: 'schichten' },
				{ label: 'Profil', value: 'heatmap' },
				{ label: 'Graphen', value: 'ueberlagert' },
				// { label: 'Vol', value: 'vol_avg' },
				// { label: 'nFK', value: 'nfk_avg' },
			],	
			aggregationOptions: [
				{ label: 'Tagesdurchschnitt', value: '1d' },
				{ label: 'Rohdaten', value: 'raw' },
			]
		}
	},
	methods: {
		openTimeRanges() {
			const popover = this.$refs.popoverRef;
			if (!popover?.open) return;
			popover.open({ bottom: -5, right: 0 });
		}
	},
	computed: {
		chartTimeRanges() {
			return [
				{ label: 'Gesamte Zeit', value: -1 },
				{ label: '365 Tage', value: 365 },
				{ label: '180 Tage', value: 180 },
				{ label: '90 Tage', value: 90 },
				{ label: '30 Tage', value: 30 },
				{ label: '7 Tage', value: 7 },
				{ label: '24 Stunden', value: 1 },
			];
		},
		popoverItems() {
			return [
				...this.chartTimeRanges.map(item => ({ type: 'select', stateProp: 'chartTimeRange', ...item })),
				{ type: 'divider' },
				...this.aggregationOptions.map(item => ({ type: 'select', stateProp: 'dataAggregation', ...item })),
			];
		},
		wideView() {
			return (this.frameWidth > 800);
		},
		showTelemetryDuration() {
			return this.earliestTimestamp > 0 && this.latestTimestamp >= this.earliestTimestamp;
		},
		showDuration() {
			return this.showTelemetryDuration && ["Gesamte Zeit", "Eigener Zeitraum"].includes(this.selectedTimeRange);
		},
		selectedTimeRange() {
			if (state.chartTimeRange === 0) {
				return 'Eigener Zeitraum';
			}
			for (let item of this.chartTimeRanges) {
				
				if (item.value == state.chartTimeRange) {
					return item.label;
				}
			}
		},
		daysSinceFirstTelemetry() {
				return Math.floor((this.latestTimestamp - this.earliestTimestamp) / (1000 * 60 * 60 * 24))
			},
			displayedDurationDays() {
				return this.selectedTimeRange === 'Eigener Zeitraum'
					? Math.floor(this.visibleDays)
					: this.daysSinceFirstTelemetry;
			},
			isPopoverOpen() {
				return Boolean(this.$refs.popoverRef?.isOpen);
			}
		}
	};
</script>

<style lang="stylus" scoped>
.settingsouter
	margin: .6em 0 .6em;
	position relative
.settingscontainer
	height: 36px
	background #eeeeeeaa
	// background: linear-gradient(to top, #00000022, #00000011);
	box-shadow 0 2px 4px #00000014
	padding 0
	border-top-left-radius 6px
	border-top-right-radius 6px
	border-top 1px solid #aaaaaa22
	overflow hidden
	display: flex;
	justify-content: space-between;
	position relative
	&:after
		content ''
		position absolute
		left 0	
		right 0
		bottom 0
		border-bottom 2px solid #00000044
	// align-items center

.daterange
	position relative
	align-items center
	padding 0
	height 100%
	cursor pointer
	letter-spacing .02em
	padding: 0 8px 0 14px
	font-weight bold
	font-size 10px
	display flex
	align-items center
	opacity 1
	color #000000ee
	&:hover
		color #000000bb
		background: var(--activecolorgreybrighter)
		opacity 1
	&:after
		content '▼'
		display inline-block
		font-size 90%
		position relative
		margin-left 4px
		top .05em
		opacity .4

@media (max-width: 475px)
	.settingscontainer
		height 42px
	.daterange
		font-size 9px	

</style>
