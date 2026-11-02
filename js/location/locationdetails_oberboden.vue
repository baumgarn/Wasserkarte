<template>
	<div class="oberboden_uebersicht">

		<div class="soil-table">
			<div
				class="datarow wassergehalt"
				v-tooltip="{ disabled: true }"
				tooltipdelay="1000"
				tooltipcontent="Die gesamte Menge Wasser im Oberboden 1m² bis 60 cm."
				tooltipside="left"
				tooltipoffset="-1"
				tooltipwidth="210">

				<Icon type="tropfen-flat" :size="22" class="icon" />

				<div class="label">
					Wassergehalt
				</div>

				<div class="num">
					<span class="value">{{ formatNumber(wassergehalt_oberboden) }} </span>
					<span class="unit"><div class="smaller">Liter</div></span>
				</div>
				<div class="num light nomobile">
					<span class="value">{{ vol }}</span>
					<span class="unit"><span class="smaller">Vol</span> %</span>
				</div>

			</div>

			<div
				class="datarow pflanzenverfuegbar"
				v-tooltip="{ disabled: true }"
				tooltipdelay="1000"
				tooltipcontent="Das tatsächlich für die Wurzeln der Pflanzen erreichbare Wasser, die nutzbare Feldkapazität (nFK %)"
				tooltipside="left"
				tooltipoffset="-1"
				tooltipwidth="210">

				<Icon type="pflanze" :size="22" class="icon" />

				<div class="label">
					Pflanzenverfügbar
				</div>

				<div class="num">
					<span class="value">{{ nfk_liter }}</span>
					<span class="unit"><span class="smaller">Liter</span></span>
				</div>


				<div class="num light nomobile">
					<span class="value">{{ nfk }}</span>
					<span class="unit"><span class="smaller">nFK</span> %</span>
				</div>

			</div>

			<div
				class="datarow totwasser"
				v-tooltip="{ disabled: true }"
				tooltipdelay="1000"
				tooltipcontent="Ein Teil des Wassers ist zu stark an den Boden gebunden und nicht für Pflanzen erreichbar."
				tooltipside="left"
				tooltipoffset="-1"
				tooltipwidth="210">

				<Icon type="totwasser" :size="22" class="icon" />

				<div class="label">
					Nicht verfügbar
				</div>

				<div class="num">
					<span class="value">{{ formatNumber(TW_liter) }}</span>
					<span class="unit"><span class="smaller">Liter</span></span>
				</div>

				<div class="num light nomobile">
					<span class="before"><</span>
					<span class="value">{{ Math.round(TW) }}</span>
					<span class="unit"><span class="smaller">Vol</span> %</span>
				</div>


			</div>

			<div
				class="datarow gesamtkapazitaet"
				v-tooltip="{ disabled: true }"
				tooltipdelay="1000"
				tooltipcontent="So viel Wasser kann der Boden gegen die Schwerkraft speichern. Überschüssiges Wasser fließt ins Grundwasser ab."
				tooltipside="left"
				tooltipoffset="-1"
				tooltipwidth="210">

				<Icon type="gesamt" :size="22" class="icon" />

				<div class="label">
					Gesamtkapazität
				</div>

				<div class="num">
					<span class="value">{{ gesamtkapazität_oberboden }}</span>
					<span class="unit"><div class="smaller">Liter</div></span>
				</div>

				<div class="num light nomobile">
					<span class="before"></span>
					<span class="value">{{ FK }}</span>
					<span class="unit"><span class="smaller">Vol</span> %</span>
				</div>


			</div>
		</div>

		<div class="hinweis">
			Modellierung Annäherung Oberboden 1m² bis 60cm Tiefe
		</div>
	</div>
</template>

<script>
	import { displayutil } from '@/displayutil.js'
	import { dataModel } from '@/datamodel.js'
	import { state } from '@/state.js'
	import dataStore from '@/datastore.js'
	import {config} from '@/config.js'

	import Beschreibung from '@/location/beschreibung.vue'
	import FilterItem from '@/location/filteritem.vue';
	import Icon from '@/ui/Icon.vue';

	export default {
		name: 'LocationDetailsOberboden',
		components: {Beschreibung, FilterItem, Icon},
		props: {device: Object, hoverData: Object},
		setup() {
			return {state};
		},
		data() {
			return {
				beschrExpanded: false
			};
		},
		computed: {
			wassergehalt_oberboden() {
				if (this.isInactive) return '–';
				const wassergehalt = this.hoverOrLastData.vol_avg * 6;
				if (!wassergehalt) return '–';
				if (wassergehalt < 0 ) return 0;
				if (wassergehalt < 10 ) return parseFloat(wassergehalt.toFixed(1));
				return wassergehalt.toFixed(0)
			},
			gesamtkapazität_oberboden() {
				return Math.round(this.device.attributes.avg_FK * 6)
			},
			FK() {
				return Math.round(this.device.attributes.avg_FK)
			},
			TW() {
				return this.device.attributes.avg_TW;
			},
			TW_liter(){
				var l = Math.min((this.gesamtkapazität_oberboden / this.device.attributes.avg_FK * this.device.attributes.avg_TW), this.wassergehalt_oberboden);
				if (isNaN(l)) return '–'
				if (l < 10 ) return parseFloat(l.toFixed(1));
				return l.toFixed(0)
			},
			vol() {
				if (this.isInactive) return '–';
				const vol = this.hoverOrLastData.vol_avg;
				if (isNaN(vol) || vol == null) return '–'
				return parseFloat(vol.toFixed(0));
			},
			nfk() {
				if (this.isInactive) return '–';
				const nfk = Math.max(0,this.hoverOrLastData.nfk_avg);
				if (isNaN(nfk) || nfk == null) return '–'
				return parseFloat(nfk.toFixed(0));
			},
			nfk_liter() {
				let l =  Math.max(0, this.wassergehalt_oberboden - this.TW_liter);
				if (isNaN(l)) return '–'
				return parseFloat(l.toFixed(0));
			},
				hoverOrLastData() {
					const lastRow = this.device?.telemetrySchema?.data?.[0];
					const schema = this.device?.telemetrySchema?.schema;
					return this.hoverData || (Array.isArray(lastRow) && Array.isArray(schema) ? dataModel.rowToProps(lastRow, schema) : {})
				},
				timelineDate() {
					return state.timelineDate;
				},
			hoursSinceLastTelemetry() {
				return dataStore.hoursSinceLastTelemetry(this.device.id);
			},
			isInactive() { // no current telemetry
				if (!this.timelineDate && this.hoursSinceLastTelemetry > config.noTelemetryCutoff ) {
					return true;
				}
				// TODO return true if inactive on timeline
			},
		},
		methods: {
			formatNumber(value) {
				const num = Number(value);
				if (Number.isNaN(num)) return value;
				const withOneDecimal = num.toFixed(1);
				const cleaned = withOneDecimal.replace(/\.0$/, '');
				return cleaned.replace('.', ',');
			}
		},
		watch: {

		}

	}

</script>

<style lang="stylus" scoped>

	.oberboden_uebersicht
		cursor pointer
		width 100%
	.oberboden_uebersicht .hinweis
		font-size 8pt
		color #000
		opacity .5
		font-weight normal
		padding 5px 0 10px
		border-top var(--thinnerline)
	.soil-table
		display grid
		grid-template-rows repeat(4, minmax(0, 1fr))
		height var(--location-table-height)
	.datarow
		display flex
		flex-direction row
		align-items baseline
		white-space nowrap
		min-height 0
		padding 5px 0 0
		cursor pointer
		position relative
		border-bottom var(--thinnerline)
	.datarow.wassergehalt
		border-top var(--thinnerline)
	.datarow
		.label
			flex-basis 39%
			opacity .5
			font-size 8.5pt
			font-weight bold
		.num
			flex-basis 60px
			text-align right
		.num.light
			flex-grow 1
	.datarow .icon
		content ''
		display inline-block
		position relative
		flex-grow 0
		flex-shrink 0
		opacity .7
		margin-right 8px
		margin-top -12px
		top 5px
	.datarow .icon.type-pflanze
		opacity .65
	.datarow .icon.type-gesamt
		opacity .45
	.datarow .icon.type-totwasser
		opacity .5
	.value
		display inline-block
		// font-size 13.5pt
		font-size 12pt
		margin-right .2em
	.num.light .value
		font-weight normal
		font-size 9pt
		opacity .55
		margin-right .2em
	.num.light .unit
		margin-left .1em
	.before
		font-size 9pt
		opacity .55
		display inline-block
		margin-right .4em
	.unit
		display inline-block
		font-size 9pt
		opacity .55
		text-align left
		.smaller
			font-size 8pt
	@media (max-width 500px)
		.nomobile
			display none
		.datarow .label
			flex-grow 1
</style>
