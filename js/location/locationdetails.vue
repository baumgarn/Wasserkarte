<template>
	<div class="locationdetails" v-if="device.attributes.avg_FK && device.attributes.avg_TW">
		<div class="toparea">
			<div class="barrell">
				<Barrell :device :hoverData />
			</div>
			<div class="datacol" @click="setView(state.locationView === 'analyse' ? 'oberboden' : 'analyse')">
				<div class="nfklabel">

					<template v-if="isInactive">

						<div class="notelemetry">
							Keine Telemetrie seit {{ daysSinceLastTelemetry }} Tagen
						</div>

					</template>

					<template v-else>

						<div class="name" :style="'color:'+nfk_color">
							{{ nfk_label }}
						</div>


					</template>
				</div>
				<LocationDetailsAnalyse v-if="state.locationView === 'analyse'" :device :isInactive />
				<LocationDetailsOberboden v-else :device :hoverData />
			</div>
			<div class="view-dots barrel-view-dots">
				<span v-for="view in ['barrel', 'square']" :key="view" :class="{ active: state.barrelView === view }" @click="setBarrelView(view)"></span>
			</div>
			<div class="view-dots">
				<span v-for="view in ['oberboden', 'analyse']" :key="view" :class="{ active: state.locationView === view }" @click="setView(view)"></span>
			</div>
		</div>
	</div>
</template>

<script>
import Barrell from '@/charts/barrell.vue';
import LocationDetailsOberboden from '@/location/locationdetails_oberboden.vue';
import LocationDetailsAnalyse from '@/location/locationdetails_analyse.vue';
import { state } from '@/state.js';
import { dataModel } from '@/datamodel.js';
import dataStore from '@/datastore.js';
import { config } from '@/config.js';

export default {
	name: 'LocationDetails',
	components: { Barrell, LocationDetailsOberboden, LocationDetailsAnalyse },
	props: { device: Object, hoverData: Object },
	setup() {
		return { state };
	},
	computed: {
		hoverOrLastData() {
			const lastRow = this.device?.telemetrySchema?.data?.[0];
			const schema = this.device?.telemetrySchema?.schema;
			return this.hoverData || (Array.isArray(lastRow) && Array.isArray(schema) ? dataModel.rowToProps(lastRow, schema) : {});
		},
		nfk() {
			return Math.round(Math.max(0, this.hoverOrLastData.nfk_avg));
		},
		nfk_label() {
			return this.hoverOrLastData.vol_avg ? dataModel.get_nfk_label(this.nfk) : '';
		},
		nfk_color() {
			return dataModel.get_nfk_label_color(this.nfk);
		},
		hoursSinceLastTelemetry() {
			return dataStore.hoursSinceLastTelemetry(this.device.id);
		},
		daysSinceLastTelemetry() {
			return Math.floor(this.hoursSinceLastTelemetry / 24);
		},
		isInactive() {
			return !state.timelineDate && this.hoursSinceLastTelemetry > config.noTelemetryCutoff;
		},
	},
	methods: {
		setBarrelView(view) {
			state.barrelView = view;
			localStorage.setItem('barrelView', JSON.stringify(view));
		},
		setView(view) {
			state.locationView = view;
			localStorage.setItem('locationView', JSON.stringify(view));
		},
	},
};
</script>

<style lang="stylus" scoped>
	.locationdetails
		--location-table-height 118px
		margin .6em 0 0
		width 100%
		display flex
		flex-direction column
	.toparea
		display grid
		grid-template-columns auto minmax(0, 1fr)
		align-items start
		width 100%
		min-width 0
		margin .2em 0 0
	.barrell
		box-sizing border-box
		flex 0 0 auto
		min-width 0
		margin-right 12px
		margin-top -4px
		margin-bottom -4px
		transform translateX(-2px) translateY(-3px)
		position relative
	.datacol
		box-sizing border-box
		flex-grow 1
		min-width 0
		padding-left 0
		padding-bottom 10px
		cursor pointer
	.nfklabel
		font-weight bold
		font-size 13pt
		height 20px
		margin 0 0 4px
		letter-spacing 0.03em;
		position relative
		text-transform uppercase
	.notelemetry
		width 100%
		color var(--warningred)
		text-transform normal
		font-size 11pt
		opacity .8
		margin-top 4px
	.notelemetry .icon
		width 30px
		height 30px
	.view-dots
		display flex
		justify-content center
		gap 0
		height 0
		position relative
		top -3px
		top 2px
		span
			width 10px
			height 2px
			transform translateY(1px)
			background #00000022
			cursor pointer
			&:first-child
				border-radius 2px 0 0 2px
			&:last-child
				border-radius 0 2px 2px 0
			&.active
				background #00000088
	.barrel-view-dots
		margin-right 12px
		transform translateX(-2px)
	@media (max-width 500px)
		.nfklabel
			font-size 11pt
		.barrell
			align-self center
			margin-right 6px
			transform translateX(-2px)
		.barrel-view-dots
			margin-right 6px
		.datacol
			padding-left 6px
			padding-right 6px
		:deep(.location-table-caption)
			white-space nowrap
			overflow hidden
			text-overflow ellipsis
</style>
