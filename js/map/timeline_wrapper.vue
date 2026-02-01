<template>
	<div class="timelinewrapper" :class="{ sidebaropen: state.sidebarOpen }">

		<div class="settingsbutton" ref="settingsbuttonRef" @click="openSettings">
			
		</div>

		<PopoverMenuMulti
			class="timelinesettingspopup"
			ref="popovermenuRef" 
			:items="popoverMenuItems" />

		<div class="timelinecontainer" :class="timelineRange">
			
			<template v-if="timelineRange == '365d'">

				<Timeline :dailyAverages :startTimestamp="_365d" :endTimestamp="latestTimestamp" />
				
			</template>
		
			<template v-else-if="timelineRange == '180d'">

				<Timeline :dailyAverages :startTimestamp="_180d" :endTimestamp="latestTimestamp" />
				
			</template>
			
			<template v-else-if="timelineRange == '90d'">

				<Timeline :dailyAverages :startTimestamp="_90d" :endTimestamp="latestTimestamp" />
				
			</template>
			
			<template v-else-if="timelineRange == 'all'">
				
				<Timeline :dailyAverages :startTimestamp="earliestTimestamp" :endTimestamp="latestTimestamp" />

			</template>
			
			<template v-else-if="timelineRange == 'years'">

				<template v-for="(year, index) in years" :key="year.id || index">
				
					<template v-if="index < years.length-1">

						<Timeline
						:dailyAverages
						:startTimestamp="year.firstts"
						:endTimestamp="year.lastts"
						:dateAxis="false"
						:label="year.year"
						/>

					</template>

					<template v-else>

						<Timeline
						:dailyAverages
						:startTimestamp="year.firstts"
						:endTimestamp="year.lastts"
						:label="year.year"
						:dateAxis="true"
						:firstItemPadding="true"
						/>

					</template>

				</template>
					
			</template>

		</div>

	</div>
</template>

<script>
import { state } from '@/state.js'
import dataStore from '@/datastore.js'
import Timeline from '@/map/timeline.vue'
import PopoverMenuMulti from '@/ui/popovermenu_multi.vue'
import DateAxis from '@/charts/dateaxis.vue'

export default {
	name: 'TimelineWrapper',
	components: { Timeline, PopoverMenuMulti, DateAxis },
	setup() {
		return { state }
	},
	data() {
		return {
			dailyAverages: [],
			earliestTimestamp: 0,
			latestTimestamp: 0,
		}
	},
	computed: {
		timelineRange() {
			if (this.numberOfDays <= 365) return 'all';
			return state.timelineRange;
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		numberOfDays() {
			return (this.latestTimestamp - this.earliestTimestamp) / (1000 * 60 * 60 * 24);
		},
		_365d() {
			const DAY = 24 * 60 * 60 * 1000;
			return this.latestTimestamp - (365 * DAY);
		},
		_180d() {
			const DAY = 24 * 60 * 60 * 1000;
			return this.latestTimestamp - (180 * DAY);
		},
		_90d() {
			const DAY = 24 * 60 * 60 * 1000;
			return this.latestTimestamp - (90 * DAY);
		},
		years() {
			const startYear = new Date(this.earliestTimestamp).getFullYear();
			const endYear = new Date(this.latestTimestamp).getFullYear();
			const years = [];

			for (let year = endYear; year >= startYear; year--) {
				const firstts = new Date(year, 0, 1, 0, 0, 0, 0).getTime();
				const lastts  = new Date(year + 1, 0, 1, 0, 0, 0, 0).getTime();

				years.push({
				year,
				firstts,
				lastts
				});
			}

			return years;
		},
		popoverMenuItems() {
			let menu = [];
			if (this.years.length > 1 && this.numberOfDays > 365) {
				menu.push(
					{type:'select', label:'Jahresvergleich', value:'years', group:'timerange', stateProp:'timelineRange'},
					{type:'select', label:'Gesamte Zeit', value:'all', group:'timerange', stateProp:'timelineRange'},
					{type:'select', label:'Letzte 365 Tage', value:'365d', group:'timerange', stateProp:'timelineRange'},
					{type:'select', label:'Letzte 180 Tage', value:'180d', group:'timerange', stateProp:'timelineRange'},
					// {type:'select', label:'Letzte 90 Tage', value:'90d', group:'timerange', stateProp:'timelineRange'},
				)
			}
			menu.push(
				{type:'divider'},
				{type:'select', label:'Durchschnitt nFK', value:'nfk_avg', group:'style', stateProp:'timelineStyle'},
				{type:'select', label:'Trockenheitsstufen', value:'levels', group:'style', stateProp:'timelineStyle'},
			);

			return menu;
		},
	},
	methods: {
		getNfkDailyAverages() {
			const rows = dataStore.getNfkDailyAverages()
			if (!rows?.length) return

			const msPerDay = 86400000
			this.dailyAverages = rows
			this.earliestTimestamp = rows[0].ts
			this.latestTimestamp = rows[rows.length - 1].ts + msPerDay
		},
		openSettings() {
			const button = this.$refs.settingsbuttonRef;
			const rect = button.getBoundingClientRect();
			const bottomDistance = window.innerHeight - rect.bottom;

			var position = {
				bottom: bottomDistance - 4,
				right: 20,
				zIndex: 10,
			};
			this.$refs.popovermenuRef.open();
			// this.$refs.popovermenuRef.open(position);
		}
	},
	watch: {
		telemetryLoaded: 'getNfkDailyAverages',
		filteredDevices: 'getNfkDailyAverages'
	}
}
</script>



<style lang="stylus" scoped>
	.timelinewrapper
		position relative
		flex-direction column
		background #fff
		box-shadow 2px 0 4px #00000033
		content ''
		left 0
		right 0
		bottom 0
	
	
	// .timelinecontainer
		// margin 0 16px
		// border-left 1px solid #00000022
		// border-right 1px solid #00000022

	.timelinesettingspopup
		bottom calc(100% + 8px) 
		right 24px

	.settingsbutton
		width 28px
		height 28px
		position absolute
		background #fff
		background #eee
		filter drop-shadow(0 2px 2px #00000044)
		border-radius 50%
		right 32px
		bottom calc(100% + 12px)
		background-color #fff
		cursor pointer
		&:after
			content ''
			position absolute
			left 0
			top 0
			right 0
			bottom 0
			background url(/img/clock2.png)
			background-size 95% 95%
			background-repeat no-repeat
			background-position center
			border-radius 50%
			opacity .52
			transition opacity linear .05s
		&:hover
			&:after
				opacity .8
</style>
