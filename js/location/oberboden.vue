<template>

	<div class="oberboden_uebersicht" v-if="hasSoilAttributes">

			<div class="toparea">

				<div class="barrell">
					<Barrell v-if="hasSoilAttributes" :device :hoverData />
				</div>
				
				<div class="datacol">
					
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
						
						<div class="nameoverlay">
							{{ nfk_label }}
						</div>

					</template>
				</div>

				<div
					class="datarow wassergehalt"
					v-tooltip
					tooltipcontent="Die gesamte Menge Wasser im Oberboden 1m² bis 60 cm."
					tooltipside="left"
					tooltipoffset="-1"
					tooltipwidth="210">

					<Icon type="tropfen-flat" :size="24" class="icon" />
				
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
					v-tooltip
					tooltipcontent="Das tatsächlich für die Wurzeln der Pflanzen erreichbare Wasser, die nutzbare Feldkapazität (nFK %)"
					tooltipside="left"
					tooltipoffset="-1"
					tooltipwidth="210">

					<Icon type="pflanze" :size="24" class="icon" />
					
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
					v-tooltip
					tooltipcontent="Ein Teil des Wassers ist zu stark an den Boden gebunden und nicht für Pflanzen erreichbar."
					tooltipside="left"
					tooltipoffset="-1"
					tooltipwidth="210">

					<Icon type="totwasser" :size="24" class="icon" />

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
					v-tooltip
					tooltipcontent="So viel Wasser kann der Boden gegen die Schwerkraft speichern. Überschüssiges Wasser fließt ins Grundwasser ab."
					tooltipside="left"
					tooltipoffset="-1"
					tooltipwidth="210">

					<Icon type="gesamt" :size="24" class="icon" />

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

				<div class="hinweis">
					Modellierte Annäherungswerte Oberboden 1m², 60cm Tiefe
				</div>

			</div>

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
	import Barrell from '@/charts/barrell.vue';
	import FilterItem from '@/location/filteritem.vue';
	import Icon from '@/ui/Icon.vue';

	export default {
		name: 'Wassergehalt',
		components: {Barrell, Beschreibung, FilterItem, Icon},
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
			is_valid() {
				return (this.wassergehalt_oberboden == '–')
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
				if (isNaN(vol)) return '–'
				return parseFloat(vol.toFixed(0));
			},
			nfk() {
				if (this.isInactive) return '–';
				const nfk = Math.max(0,this.hoverOrLastData.nfk_avg);
				if (isNaN(nfk)) return '–'
				return parseFloat(nfk.toFixed(0));
			},
			nfk_liter() {
				let l =  Math.max(0, this.wassergehalt_oberboden - this.TW_liter);
				if (isNaN(l)) return '–'
				return parseFloat(l.toFixed(0));
			},
			nfk_label() {
				if (this.is_valid) {
					return ''
				}
				return dataModel.get_nfk_label(this.nfk);
			},
			nfk_color() {
				return dataModel.get_nfk_color(this.nfk);
			},
				hasSoilAttributes() {
					return (this.device.attributes.avg_FK && this.device.attributes.avg_TW)
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
			daysSinceLastTelemetry() {
				return Math.floor(this.hoursSinceLastTelemetry / 24);
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
		margin .6em 0 0
		width 100%
		display flex
		flex-direction column
	.toparea
		display flex
		flex-direction row
		align-items center
		margin .2em 0 0
	.barrell
		flex-grow 0
		flex-shrink 0
		margin-right 1.65em
		margin-left -2px
		margin-top -4px
		margin-bottom -4px
		position relative
	.datacol	
		flex-grow 1
	.nfklabel
		font-weight bold
		font-size 13pt
		height 20px
		margin 0 0 .25em
		letter-spacing 0.03em;
		position relative
		text-transform uppercase
		> *
			position absolute
		.nameoverlay
			opacity .1
			text-shadow none
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
	.hinweis
		font-size 8pt
		opacity .55
		flex-grow 1
		padding-top .65em
	.datarow
		display flex
		flex-direction row
		align-items baseline
		white-space nowrap
		height 32px
		padding 3px 0 0
		cursor default
		position relative
		border-bottom 1px solid #eee
		.label
			flex-basis 40%
		.num
			flex-basis 60px
			text-align right
		.num.light
			flex-basis 30%
			padding-right 4px
	.datarow .icon
		content ''
		display inline-block
		position relative
		// width 30px
		// flex-basis 30px
		flex-grow 0
		flex-shrink 0
		height 30px
		opacity .7
		margin-right 8px
		margin-top -12px
		top 6px
	.datarow .icon.type-pflanze
		opacity .65
	.datarow .icon.type-gesamt
		opacity .45
	.datarow .icon.type-totwasser
		opacity .5
	.value
		display inline-block
		font-size 13.5pt
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
	.label
		font-size 9pt
		opacity 1
		font-weight bold
		color #000
		color #000000a8
		font-weight 600
		letter-spacing .01em
	@media (max-width 500px)
		.nfklabel
			font-size 11pt
		.barrell
			margin-right 12px
		.nomobile
			display none
		.datarow .label
			flex-grow 1
</style>
