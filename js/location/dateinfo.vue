<template>

	<div class="dateinfo" v-if="timeSinceLastTelemetry > 48">
		<!-- <template v-if="earliestTimestamp && earliestTimestamp > 0"> -->

		<!-- <div class="earliestdate">
			Seit {{ displayutil.formatDateShort(earliestTimestamp) }}
			&nbsp;({{ daysSinceFirstTelemetry }} {{daysSinceFirstTelemetry == 1 ? 'Tag' : 'Tage'}})
		</div> -->
		
		<!-- <div v-if="hoverData && hoverData.ts" class="latestdate">
			{{ displayutil.formatDateShort(hoverData.ts) }}
			<span class="time">{{ displayutil.formatDateTime(hoverData.ts) }}</span>
		</div> -->
		<!-- <div v-else class="latestdate">
			Letzte Messung {{ displayutil.formatDateShort(latestTimestamp) }}
			<span class="time">{{ displayutil.formatDateTime(latestTimestamp) }}</span>
		</div>  -->

		<div class="latestdate warning" v-if="daysSinceLastTelemetry > 1000">
			Keine Telemetrie
		</div>
		<div class="latestdate warning" v-else-if="timeSinceLastTelemetry > 48">
			Keine Telemetrie seit {{ displayutil.formatDateShort(latestTimestamp) }}&nbsp;({{daysSinceLastTelemetry}} {{daysSinceLastTelemetry == 1 ? 'Tag' : 'Tage'}})
			
		</div>
		<!-- <div class="latestdate warning" v-if="timeSinceLastTelemetry > 24">
			Keine Telemetrie seit {{ displayutil.formatDateShort(latestTimestamp) }}
			{{ displayutil.formatDateTime(latestTimestamp) }}
			&nbsp;({{daysSinceLastTelemetry}} {{daysSinceLastTelemetry == 1 ? 'Tag' : 'Tage'}})
			
		</div> -->

	<!-- </template> -->

	</div>

</template>

<script>

import { state } from '../state.js';
import dataStore from '../datastore.js';
import { displayutil } from '../displayutil.js'

export default {
	components: { },
	props: {
		device: {
			required: true,
			type: Object,
		},
		hoverData: {
			type: Object,
			required: false
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
	},
	data() {
		return {
			displayutil,
		}
	},
	computed: {
		daysSinceLastTelemetry() {
			const hours = dataStore.timeSinceLastTelemetry(this.device.id);
			const days = Math.floor(hours / 24);
			return days;
		},
		daysSinceFirstTelemetry() {
			return Math.floor((Date.now() - this.earliestTimestamp) / (1000 * 60 * 60 * 24))
		},
		timeSinceLastTelemetry() {
			return dataStore.timeSinceLastTelemetry(this.device.id)
		},
	},
	methods: {
	},
};
</script>



<style lang="stylus" scoped>
	.dateinfo
		display flex
		align-items space-between
		font-size 8pt
		font-weight bold
		height 1em
		line-height 1em
		margin 1em var(--sidebartextmargin) 1.2em
		// margin-top 1em
		// margin-top .3em
		// margin-bottom -.7em
		opacity 1
		color #000000cc
		// color #000000aa
		// max-width 595px
		.latestdate
			flex-grow 1
			// text-align right
			.time
				display inline-block
				width 2.6em
				white-space nowrap
				overflow visible
		.warning	
			color red
			opacity .7
			> span
				color red
			&:before
				content: ''
				display inline-block
				width 1.2em
				height 1.2em
				background url('/img/error.png') no-repeat center center / 100%
				position relative
				margin -.2em .1em 0
				vertical-align middle
</style>
