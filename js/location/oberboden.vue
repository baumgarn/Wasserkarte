<template>

	<div class="oberboden_uebersicht" v-if="hasSoilAttributes">

			<div class="nfklabel mobile">
				<div class="name" :style="'color:'+nfk_color">
					{{ nfk_label }}
				</div>
				<div class="nameoverlay">
					{{ nfk_label }}
				</div>
			</div>

			<div class="toparea">

				<div class="barrell">
					<Barrell v-if="hasSoilAttributes" :device :hoverData />
				</div>
				
				<div class="datacol">
					
				<div class="nfklabel">
					<div class="name" :style="'color:'+nfk_color">
						{{ nfk_label }}
					</div>
					<div class="nameoverlay">
						{{ nfk_label }}
					</div>
				</div>

				<div class="datarow wassergehalt hastooltip">

					<div class="icon"></div>
				
					<div class="label">
						Wassergehalt
					</div>
					
					<div class="num">
						<span class="value">{{ formatNumber(wassergehalt_oberboden) }} </span>
						<span class="unit"><div class="smaller">Liter</div></span>
					</div>
					<div class="num light">
						<span class="value">{{ vol }}</span>
						<span class="unit"><span class="smaller">Vol</span> %</span>
					</div>

					<div class="tooltip left opaque">
						Die gesamte Menge Wasser im Oberboden 1m&sup2; bis 60 cm.
					</div>

				</div>
				
				<div class="datarow pflanzenverfuegbar hastooltip">

					<div class="icon"></div>
					
					<div class="label">
						Pflanzenverfügbar
					</div>

					<div class="num">
						<span class="value">{{ nfk_liter }}</span>
						<span class="unit"><span class="smaller">Liter</span></span>
					</div>


					<div class="num light">
						<span class="value">{{ nfk }}</span>
						<span class="unit"><span class="smaller">nFK</span> %</span>
					</div>

					<div class="tooltip left opaque">
						Das tatsächlich für die Wurzeln der Pflanzen erreichbare Wasser. 
					</div>

				</div>
				
				<div class="datarow totwasser hastooltip">

					<div class="icon"></div>

					<div class="label">
						Nicht verfügbar
					</div>
					
					<div class="num">
						<span class="value">{{ formatNumber(TW_liter) }}</span>
						<span class="unit"><span class="smaller">Liter</span></span>
					</div>

					<div class="num light">
						<span class="before"><</span>
						<span class="value">{{ TW }}</span>
						<span class="unit"><span class="smaller">Vol</span> %</span>
					</div>


					<div class="tooltip left opaque">
						Ein Teil des Wassers ist zu stark an den Boden gebunden und nicht für Pflanzen erreichbar.
					</div>


				</div>

				<div class="datarow gesamtkapazitaet hastooltip" >

					<div class="icon"></div>

					<div class="label">
						Gesamtkapazität
					</div>
					
					<div class="num">
						<span class="value">{{ gesamtkapazität_oberboden }}</span>
						<span class="unit"><div class="smaller">Liter</div></span>
					</div>
				
					<div class="num light">
						<span class="value">{{ device.attributes.feldkapazität }}</span>
						<span class="unit"><span class="smaller">Vol</span> %</span>
					</div>


					<div class="tooltip left opaque">
						So viel Wasser kann der Boden gegen die Schwerkraft speichern. Überschüssiges Wasser fließt ins Grundwasser ab.
					</div>

					
				</div>

				<div class="hinweis">
					Modellierte Annäherungswerte Oberboden 1m², 60cm Tiefe
				</div>

			</div>

		</div>

		<div class="hinweis mobile">
			Modellierte Annäherungswerte Oberboden 1m² bis 60cm Tiefe
		</div>
			
		<div class="soilinfo">

			<span class="soil" v-if="usageName || soilName || humusName" >

				<template v-if="usageName">

					<span  class="usagename typeitem" >
						{{ usageName }}
					</span>

				</template>

				<template v-if="device.attributes.Bewässerung">
					
					<span class="bewaesserung typeitem">
						regelmäßig bewässert
					</span>
										
				</template>
				
				<template v-if="device.attributes.Grundwasser">
					
					<span class="bewaesserung typeitem">
						grundwassernah
					</span>
										
				</template>
				
				<template v-if="soilName">
						
					<span class="soiltype typeitem" >
						{{ soilName }}
					</span>
										
				</template>
				
				<template v-if="humusName">
					
					<span class="humustype typeitem">
						{{ humusName }}
					</span>

				</template>

			</span>

			<div v-if="device.attributes.Beschreibung" class="beschreibung">
				{{ device.attributes.Beschreibung }}
			</div>

		</div>
				
	</div>
		

</template>

<script>
	import { displayutil } from '@/displayutil.js'
	import { dataModel } from '@/datamodel.js'
	import { state } from '@/state.js'

	import Barrell from '@/charts/barrell.vue';

	export default {
		name: 'Wassergehalt',
		components: {Barrell},
		props: {device: Object, hoverData: Object},
		setup() {
			return {state};
		},
		computed: {
			wassergehalt_oberboden() {
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
				return Math.round(this.device.attributes.avg_TW)
			},
			TW_liter(){
				if (isNaN(this.nfk)) return Math.round((this.gesamtkapazität_oberboden / this.device.attributes.avg_FK * this.device.attributes.avg_TW));
				return Math.round(Math.min((this.gesamtkapazität_oberboden / this.device.attributes.avg_FK * this.device.attributes.avg_TW), this.wassergehalt_oberboden));
			},
			vol() {
				const vol = this.hoverOrLastData.vol_avg;
				if (isNaN(vol)) return '–'
				return parseFloat(vol.toFixed(0));
			},
			nfk() {
				const nfk = this.hoverOrLastData.nfk_avg;
				if (isNaN(nfk)) return '–'
				return parseFloat(nfk.toFixed(0));
			},
			nfk_liter() {
				if (isNaN(this.nfk)) return '–'
				let l =  Math.max(0, this.wassergehalt_oberboden - this.TW_liter);
				return parseFloat(l.toFixed(0));
			},
			nfk_label() {
				return dataModel.get_nfk_label(this.nfk);
			},
			nfk_color() {
				return dataModel.get_nfk_color(this.nfk);
			},
			hasSoilAttributes() {
				return (this.device.attributes.avg_FK && this.device.attributes.avg_TW)
			},
			usageName() {
				return dataModel.get_usage_name(this.device);
			},
			soilName() {
				return dataModel.get_soil_name(this.device);
			},
			soilColor() {
				return dataModel.get_soil_color(this.device);
			},
			humusName() {
				return dataModel.get_humus_name(this.device);
			},
			humusColor() {
				return dataModel.get_humus_color(this.device);
			},
			hoverOrLastData() {
				return this.hoverData || dataModel.rowToProps(this.device.telemetrySchema.data[0],this.device.telemetrySchema.schema)
			}
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
		margin 1em 0 0
		max-width 600px
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
		position relative
	.datacol	
		flex-grow 1
	.nfklabel
		font-weight bold
		font-size 13.5pt
		height 20px
		margin 0 0 .3em
		letter-spacing 0.03em;
		position relative
		> *
			position absolute
		.nameoverlay
			opacity .12
	.nfklabel.mobile
	.hinweis.mobile
		display none
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
		height 35px
		padding 0
		cursor default
		position relative
		border-bottom var(--separatorline)
		border-bottom 1px solid #eee
		.label
			flex-basis 42%
		.num
			flex-basis 60px
			text-align right
		.num.light
			flex-basis 30%
			padding-right .6em
	.datarow .icon
		content ''
		display inline-block
		position relative
		width 30px
		flex-basis 30px
		flex-grow 0
		flex-shrink 0
		height 30px
		opacity .45
		margin-right 8px
		margin-top -8px
		top 10px
		background-size contain
		background-position center
		background-repeat no-repeat
	.datarow.wassergehalt .icon
		opacity .85
		background-image url(/img/tropfen_flat.png)
		background-size 60% 90%
	.datarow.pflanzenverfuegbar .icon
		background-image url(/img/plant.svg)
		opacity .55
		// top 10px
	.datarow.totwasser .icon
		background-size auto
		opacity .55
		box-sizing border-box
		background-image url(/img/totwasser2.png)
		background-repeat repeat
		background-position -3px 0
	.datarow.gesamtkapazitaet .icon
		background-image url(/img/barrellflat.png)
		opacity .44
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
	.soilinfo
		margin 2em 0 .7em
		font-size 9pt
		.description
		.soil
			margin .2em 0
			display inline-block
		.soil
			font-weight bold
		.typeitem + .typeitem:before
			content '–'
			opacity .3
			display inline-block
			margin 0 .3em
		.soiltype
		.humustype
			display inline-block

	@media (max-width 500px)
		.oberboden_uebersicht
			max-width 100%
			> .nfklabel.mobile
				display block
				margin-bottom .75em
			> .hinweis.mobile
				display block
		.barrell
			// margin-bottom .75em
			margin-right 12px
		.toparea
			.nfklabel
			.hinweis
				display none
		.datarow .label
			font-size 10px
		.datarow.totwasser .icon
			top 10px
		.datarow.gesamtkapazitaet
			border-bottom none
		.soilinfo
			margin 1.8em 0 1.2em
			font-size 13px
		.num.light
			display none
		.num
			flex-grow 1
</style>