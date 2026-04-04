<template>
	<div class="colordotcontainer">

		<div class="colordot" :style="dotStyle">
		</div>

	</div>
</template>

<script>
import { state } from '@/state.js'
import { config } from '@/config.js'
import { dataModel } from '@/datamodel.js'
import dataStore from '@/datastore.js';

export default {
	name: 'ColorDot',
	setup() {
		return { state, dataModel };
	},
	props: {
		device: Object,
	},
	data() {
		return {
			telemetryData: null,
		};
	},
	mounted() {
		this.getTelemetry();
	},
	computed: {
		nfk() {
			const nfk = this.nfk_avg;
			if (isNaN(nfk)) return '–'
			return parseFloat(nfk.toFixed(0));
		},
		schema() {
			return this.device.telemetrySchema?.schema || [];
		},
		nfkavg_index() {
			const i = this.schema.indexOf('nfk_avg');
			return (i >= 0) ? i : null;
		},
		nfk_avg() {
			if (this.nfkavg_index != null && this.displayData) {
				return Math.max(0, this.displayData[this.nfkavg_index]);
			}
			return null;
		},
		nfk_color() {
			if (this.nfk_avg != null && !Number.isNaN(this.nfk_avg)) {
				return dataModel.get_nfk_color(this.nfk_avg);
			}
			return null;
		},
		dotStyle() {
			if (this.isInactive) {
				return { backgroundColor: '#e4e4e4' };
			}
			return this.nfk_color ? { backgroundColor: this.nfk_color } : null;
		},
		lastData() {
			if (!this.device.telemetrySchema?.data?.[0]) return null;
			return this.device.telemetrySchema.data[0];
		},
		firstDate() {
			return this.telemetryData?.data?.[0]?.[0] ?? null;
		},
		lastDate() {
			const rows = this.telemetryData?.data;
			return rows?.length ? rows[rows.length - 1][0] : null;
		},
		displayData() {
			if (state.timelineDate && this.telemetryData) {
				const data = dataStore.getDataAtTimestamp(this.device.index, state.timelineDate);
				if (data) return data;
			}
			return this.lastData;
		},
		timelineDate() {
			return state.timelineDate;
		},
		hoursSinceLastTelemetry() {
			return dataStore.hoursSinceLastTelemetry(this.device.id);
		},
		isInactive() {
			if (!this.timelineDate) {
				return this.hoursSinceLastTelemetry === -1 || this.hoursSinceLastTelemetry > config.noTelemetryCutoff;
			}
			if (!this.firstDate || !this.lastDate) {
				return true;
			}
			return this.timelineDate < this.firstDate || this.timelineDate >= this.lastDate + config.timeDisplayCutoff;
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
	},
	watch: {
		telemetryLoaded() {
			this.getTelemetry();
		},
	},
	methods: {
		getTelemetry(){
			this.telemetryData = dataStore.fetchTelemetryCache(this.device.id);
		},
	}
};
</script>

<style lang="stylus" scoped>
	.colordotcontainer
		display inline-flex
		align-items center
		justify-content center
		width 30px
		position relative
		margin-left -7px
		margin-top -4px
		margin-right 0
		margin-bottom -4px
		flex-shrink 0
		flex-grow 0
	.colordot
		position absolute
		// left 0 
		// top -2px
		width 16px
		height 16px
		// border 1px solid #00000011
		border-radius 50%
		background #e4e4e4
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));


</style>
